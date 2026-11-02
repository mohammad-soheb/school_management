<?php

namespace App\Services\Communication;

use App\Enums\Communication\NoticeAudienceType;
use App\Enums\Communication\NoticeStatus;
use App\Models\Communication\Notice;
use App\Models\Communication\NoticeAudience;
use App\Models\Documents\StoredFile;
use App\Models\Identity\Role;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\User;
use App\Services\Communication\Concerns\StoresAttachments;
use App\Services\Documents\PrivateFileService;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Notices / circulars (FR-506, FR-508): draft -> published -> archived, with an optional publication window
 * (publish_at / expires_at), pinning, audience rows and private attachments. Nothing is deleted; every change is
 * audited (the body itself is never written to the audit log, only its length).
 *
 * Form input: audience = school | staff | parents | selected; for "selected", class_ids[] and/or role_ids[].
 * publish_at / expires_at arrive as school-local "Y-m-d\TH:i" strings and are stored in UTC.
 */
class NoticeService
{
    use StoresAttachments;

    public const AUDIENCE_CHOICES = ['school', 'staff', 'parents', 'selected'];

    /** Filter values for the management list (display states, see Notice::state()). */
    public const STATES = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'live' => 'Live', 'expired' => 'Expired', 'archived' => 'Archived'];

    public function __construct(
        private readonly PrivateFileService $files,
        private readonly AuditLogger $audit,
        private readonly NoticeAudienceResolver $resolver,
    ) {}

    /**
     * Every notice, for holders of notices.manage.
     *
     * @param  array{q?: ?string, state?: ?string}  $filters
     * @return LengthAwarePaginator<int, Notice>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $now = now();

        return Notice::query()
            ->with(['audiences.schoolClass', 'audiences.role', 'creator'])
            ->withCount('attachments')
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($filters['state'] ?? null, fn (Builder $q, string $state) => match ($state) {
                'draft' => $q->where('status', NoticeStatus::Draft->value),
                'archived' => $q->where('status', NoticeStatus::Archived->value),
                'scheduled' => $q->where('status', NoticeStatus::Published->value)->where('publish_at', '>', $now)
                    ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now)),
                'expired' => $q->where('status', NoticeStatus::Published->value)->whereNotNull('expires_at')->where('expires_at', '<=', $now),
                'live' => $q->visibleNow(),
                default => $q,
            })
            ->orderByRaw("CASE status WHEN 'archived' THEN 1 ELSE 0 END")
            ->boardOrder()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The staff notice board: live notices addressed to this user, pinned first.
     *
     * @return LengthAwarePaginator<int, Notice>
     */
    public function board(User $user, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return Notice::query()
            ->visibleNow()
            ->tap(fn (Builder $q) => $this->resolver->scopeForStaff($q, $user))
            ->with(['attachments', 'audiences.schoolClass', 'audiences.role', 'publisher'])
            ->when($search, fn (Builder $q, string $term) => $q->where(fn (Builder $q) => $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('body', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->boardOrder()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data  validated StoreNoticeRequest data
     * @param  list<UploadedFile>  $attachments
     */
    public function create(array $data, array $attachments, bool $publish, User $actor): Notice
    {
        $attributes = $this->attributes($data);
        $audiences = $this->audiences($data);
        $this->assertAttachmentCount(0, count($attachments));

        if ($publish) {
            $this->assertPublishable($attributes['publish_at'], $attributes['expires_at']);
        }

        return $this->withFileCleanup(function (array &$stored) use ($attributes, $audiences, $attachments, $publish, $actor) {
            $notice = Notice::create($attributes + [
                'status' => $publish ? NoticeStatus::Published : NoticeStatus::Draft,
                'published_at' => $publish ? now() : null,
                'published_by' => $publish ? $actor->getKey() : null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            $this->syncAudiences($notice, $audiences);
            $this->storeAttachments($notice, 'notice', $attachments, $actor, $stored);

            $this->audit->record('notice.created', $notice, new: $this->auditView($notice) + ['attachments' => count($attachments)], actor: $actor);
            if ($publish) {
                $this->audit->record('notice.published', $notice, ['status' => 'draft'], ['status' => 'published'], actor: $actor);
            }

            return $notice;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated UpdateNoticeRequest data
     * @param  list<UploadedFile>  $attachments
     * @param  list<int|string>  $removeAttachmentIds
     */
    public function update(Notice $notice, array $data, array $attachments, array $removeAttachmentIds, User $actor): Notice
    {
        if ($notice->status === NoticeStatus::Archived) {
            throw ValidationException::withMessages(['title' => 'Archived notices cannot be edited. Restore it first.']);
        }

        $attributes = $this->attributes($data);
        $audiences = $this->audiences($data);

        if ($notice->status === NoticeStatus::Published) {
            $this->assertPublishable($attributes['publish_at'], $attributes['expires_at']);
        }

        $toRemove = $notice->attachments()->whereIn('id', array_map('intval', $removeAttachmentIds))->get();
        $this->assertAttachmentCount($notice->attachments()->count() - $toRemove->count(), count($attachments));

        $updated = $this->withFileCleanup(function (array &$stored) use ($notice, $attributes, $audiences, $attachments, $toRemove, $actor) {
            /** @var Notice $locked */
            $locked = Notice::query()->lockForUpdate()->findOrFail($notice->getKey());
            $before = $this->auditView($locked);

            $locked->fill($attributes + ['updated_by' => $actor->getKey()])->save();
            $this->syncAudiences($locked, $audiences);
            $this->storeAttachments($locked, 'notice', $attachments, $actor, $stored);

            [$old, $new] = $this->audit->diff($before, $this->auditView($locked));
            if ($attachments !== [] || $toRemove->isNotEmpty()) {
                $new['attachments_added'] = count($attachments);
                $new['attachments_removed'] = $toRemove->count();
            }
            $this->audit->record('notice.updated', $locked, $old, $new, actor: $actor);

            return $locked;
        });

        $toRemove->each(fn (StoredFile $file) => $this->files->delete($file, $actor));

        return $updated;
    }

    public function publish(Notice $notice, User $actor): Notice
    {
        return $this->transition($notice, $actor, from: [NoticeStatus::Draft], to: NoticeStatus::Published, event: 'notice.published', extra: function (Notice $n) use ($actor) {
            $this->assertPublishable($n->publish_at?->toImmutable(), $n->expires_at?->toImmutable());
            if ($n->audiences()->doesntExist()) {
                throw ValidationException::withMessages(['audience' => 'Choose who the notice is for before publishing it.']);
            }

            return ['published_at' => now(), 'published_by' => $actor->getKey()];
        });
    }

    /** Take a published notice off the board (back to draft) without archiving it. */
    public function unpublish(Notice $notice, User $actor): Notice
    {
        return $this->transition($notice, $actor, from: [NoticeStatus::Published], to: NoticeStatus::Draft, event: 'notice.unpublished');
    }

    public function archive(Notice $notice, User $actor): Notice
    {
        return $this->transition($notice, $actor, from: [NoticeStatus::Draft, NoticeStatus::Published], to: NoticeStatus::Archived, event: 'notice.archived',
            extra: fn () => ['archived_at' => now(), 'archived_by' => $actor->getKey(), 'is_pinned' => false]);
    }

    /** An archived notice comes back as a draft, so it is never re-shown by accident. */
    public function restore(Notice $notice, User $actor): Notice
    {
        return $this->transition($notice, $actor, from: [NoticeStatus::Archived], to: NoticeStatus::Draft, event: 'notice.restored',
            extra: fn () => ['archived_at' => null, 'archived_by' => null]);
    }

    /** The attachment, if it really belongs to this notice (otherwise 404). */
    public function attachment(Notice $notice, StoredFile $file): StoredFile
    {
        abort_unless(
            $file->purpose === 'notice'
            && $file->attachable_type === $notice->getMorphClass()
            && (int) $file->attachable_id === (int) $notice->getKey(),
            404,
        );

        return $file;
    }

    /** @return array<int, string> */
    public function classOptions(): array
    {
        return SchoolClass::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> staff roles a notice may target (parents use the "All parents" audience) */
    public function roleOptions(): array
    {
        return Role::query()->where('slug', '!=', 'parent')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Form defaults for an existing notice (audience choice, selected ids, local datetimes). @return array<string, mixed> */
    public function formState(?Notice $notice): array
    {
        if ($notice === null) {
            return ['audience' => 'school', 'class_ids' => [], 'role_ids' => [], 'publish_at' => null, 'expires_at' => null];
        }

        $rows = $notice->audiences;
        $simple = $rows->count() === 1 ? $rows->first()->type : null;
        $tz = config('app.display_timezone');

        return [
            'audience' => in_array($simple, [NoticeAudienceType::School, NoticeAudienceType::Staff, NoticeAudienceType::Parents], true) ? $simple->value : ($rows->isEmpty() ? 'school' : 'selected'),
            'class_ids' => $rows->where('type', NoticeAudienceType::SchoolClass)->pluck('school_class_id')->map(fn ($id) => (int) $id)->values()->all(),
            'role_ids' => $rows->where('type', NoticeAudienceType::Role)->pluck('role_id')->map(fn ($id) => (int) $id)->values()->all(),
            'publish_at' => $notice->publish_at?->timezone($tz)->format('Y-m-d\TH:i'),
            'expires_at' => $notice->expires_at?->timezone($tz)->format('Y-m-d\TH:i'),
        ];
    }

    /**
     * @param  list<NoticeStatus>  $from
     * @param  (callable(Notice): array<string, mixed>)|null  $extra
     */
    private function transition(Notice $notice, User $actor, array $from, NoticeStatus $to, string $event, ?callable $extra = null): Notice
    {
        return DB::transaction(function () use ($notice, $actor, $from, $to, $event, $extra) {
            /** @var Notice $locked */
            $locked = Notice::query()->lockForUpdate()->findOrFail($notice->getKey());

            if ($locked->status === $to) {
                return $locked; // already done (double click)
            }

            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages(['status' => "A {$locked->status->label()} notice cannot be changed to {$to->label()}."]);
            }

            $previous = $locked->status->value;
            $locked->forceFill(['status' => $to, 'updated_by' => $actor->getKey()] + ($extra ? $extra($locked) : []))->save();
            $this->audit->record($event, $locked, ['status' => $previous], ['status' => $to->value], actor: $actor);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{title: string, body: string, is_pinned: bool, publish_at: ?CarbonImmutable, expires_at: ?CarbonImmutable}
     */
    private function attributes(array $data): array
    {
        $publishAt = $this->parseLocal($data['publish_at'] ?? null);
        $expiresAt = $this->parseLocal($data['expires_at'] ?? null);

        if ($publishAt !== null && $expiresAt !== null && $expiresAt->lte($publishAt)) {
            throw ValidationException::withMessages(['expires_at' => 'The expiry must be after the publish time.']);
        }

        return [
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'publish_at' => $publishAt,
            'expires_at' => $expiresAt,
        ];
    }

    private function assertPublishable(?CarbonImmutable $publishAt, ?CarbonImmutable $expiresAt): void
    {
        if ($expiresAt !== null && $expiresAt->lte(now())) {
            throw ValidationException::withMessages(['expires_at' => 'The expiry time has already passed; choose a later time or leave it empty.']);
        }
    }

    /**
     * Turn the form's audience choice into audience rows. Ids were already checked with TenantRule::exists.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{type: NoticeAudienceType, school_class_id: ?int, role_id: ?int}>
     */
    private function audiences(array $data): array
    {
        $choice = (string) ($data['audience'] ?? '');

        if (in_array($choice, ['school', 'staff', 'parents'], true)) {
            return [['type' => NoticeAudienceType::from($choice), 'school_class_id' => null, 'role_id' => null]];
        }

        $classIds = array_values(array_unique(array_map('intval', $data['class_ids'] ?? [])));
        $roleIds = array_values(array_unique(array_map('intval', $data['role_ids'] ?? [])));

        if ($classIds === [] && $roleIds === []) {
            throw ValidationException::withMessages(['class_ids' => 'Choose at least one class or role.']);
        }

        if ($roleIds !== [] && Role::query()->whereIn('id', $roleIds)->where('slug', 'parent')->exists()) {
            throw ValidationException::withMessages(['role_ids' => 'To reach parents, choose "All parents" or select their children\'s classes.']);
        }

        $rows = [];
        foreach ($classIds as $id) {
            $rows[] = ['type' => NoticeAudienceType::SchoolClass, 'school_class_id' => $id, 'role_id' => null];
        }
        foreach ($roleIds as $id) {
            $rows[] = ['type' => NoticeAudienceType::Role, 'school_class_id' => null, 'role_id' => $id];
        }

        return $rows;
    }

    /** @param  list<array{type: NoticeAudienceType, school_class_id: ?int, role_id: ?int}>  $rows */
    private function syncAudiences(Notice $notice, array $rows): void
    {
        // Audience rows are configuration of the notice, not records: replacing them is safe (the change is audited).
        NoticeAudience::query()->where('notice_id', $notice->getKey())->delete();

        foreach ($rows as $row) {
            NoticeAudience::create(['notice_id' => $notice->getKey()] + $row);
        }

        $notice->unsetRelation('audiences');
    }

    private function parseLocal(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value, config('app.display_timezone'))->utc();
    }

    /** @return array<string, mixed> */
    private function auditView(Notice $notice): array
    {
        $notice->unsetRelation('audiences');

        return [
            'title' => $notice->title,
            'body_length' => mb_strlen((string) $notice->body),
            'is_pinned' => (bool) $notice->is_pinned,
            'publish_at' => $notice->publish_at?->toIso8601String(),
            'expires_at' => $notice->expires_at?->toIso8601String(),
            'audience' => $notice->audiences()->get()
                ->map(fn (NoticeAudience $a) => $a->type->value.($a->school_class_id ? ':'.$a->school_class_id : '').($a->role_id ? ':'.$a->role_id : ''))
                ->implode(','),
        ];
    }
}
