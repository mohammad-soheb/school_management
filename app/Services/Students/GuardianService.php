<?php

namespace App\Services\Students;

use App\Enums\Students\GuardianLinkStatus;
use App\Enums\Students\GuardianRelation;
use App\Models\Identity\Invitation;
use App\Models\Identity\Role;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\Students\StudentGuardian;
use App\Models\User;
use App\Services\Identity\InvitationService;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guardians and student-guardian links (FR-204, FR-205, FR-602). One guardian may be linked to several children
 * (siblings). The parent portal honours a link only while it is active AND verified by the school, so linking,
 * verifying and revoking are separate, audited steps. Links are revoked, never deleted.
 */
class GuardianService
{
    public const GUARDIAN_FIELDS = ['name', 'relation', 'phone', 'email', 'occupation', 'address'];

    public const LOOKUP_LIMIT = 15;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InvitationService $invitations,
    ) {}

    /**
     * @param  array{q?: ?string, portal?: ?string}  $filters
     * @return LengthAwarePaginator<int, Guardian>
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Guardian::query()
            ->with(['links' => fn ($q) => $q->where('status', GuardianLinkStatus::Active->value)->with('student')->orderByDesc('is_primary')]);

        $this->applySearch($query, $filters['q'] ?? null);

        match ($filters['portal'] ?? null) {
            'linked' => $query->whereNotNull('user_id'),
            'not_linked' => $query->whereNull('user_id'),
            default => null,
        };

        return $query->orderBy('name')->orderBy('id')->paginate($perPage)->withQueryString();
    }

    /**
     * JSON lookup used to link an existing guardian (siblings): name or phone.
     *
     * @return list<array{id: int, label: string, sublabel: string}>
     */
    public function lookup(string $term): array
    {
        if (trim($term) === '') {
            return [];
        }

        $query = Guardian::query()->with(['links' => fn ($q) => $q->where('status', GuardianLinkStatus::Active->value)->with('student')]);
        $this->applySearch($query, $term);

        return $query->orderBy('name')->limit(self::LOOKUP_LIMIT)->get()
            ->map(function (Guardian $guardian): array {
                $children = $guardian->links->map(fn (StudentGuardian $l) => $l->student->first_name)->implode(', ');

                return [
                    'id' => $guardian->getKey(),
                    'label' => $guardian->name,
                    'sublabel' => trim($guardian->phone.($children !== '' ? ' · Parent of '.$children : '')),
                ];
            })
            ->all();
    }

    public function profile(Guardian $guardian): Guardian
    {
        return $guardian->load([
            'user',
            'links' => fn ($q) => $q->with(['student.currentEnrollment.schoolClass', 'student.currentEnrollment.section', 'verifier'])
                ->orderByRaw('status = ? desc', [GuardianLinkStatus::Active->value])->orderBy('id'),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Invitation> */
    public function invitations(Guardian $guardian): \Illuminate\Support\Collection
    {
        return Invitation::query()->where('guardian_id', $guardian->getKey())->with('inviter')->latest('id')->limit(5)->get();
    }

    /**
     * Link a guardian to a student: an existing guardian (`id`) or a new one from the given details. A new guardian
     * whose name AND phone match an existing one is treated as that person (no duplicate rows for siblings).
     * Re-linking a revoked link reactivates it, unverified.
     *
     * @param  array<string, mixed>  $data  id? | name, relation, phone, email?, occupation?, address?; plus is_primary?, is_emergency_contact?, verify?
     */
    public function link(Student $student, array $data, User $actor, bool $audit = true): StudentGuardian
    {
        return DB::transaction(function () use ($student, $data, $actor, $audit): StudentGuardian {
            $guardian = $this->resolveGuardian($data, $actor);
            $relation = GuardianRelation::from($data['relation'] ?? $guardian->relation?->value ?? GuardianRelation::Guardian->value);

            $link = StudentGuardian::query()
                ->where('student_id', $student->getKey())
                ->where('guardian_id', $guardian->getKey())
                ->lockForUpdate()
                ->first();

            if ($link !== null && $link->status === GuardianLinkStatus::Active) {
                throw ValidationException::withMessages(['guardian' => "{$guardian->name} is already linked to this student."]);
            }

            $isPrimary = (bool) ($data['is_primary'] ?? false)
                || ! StudentGuardian::query()->where('student_id', $student->getKey())->where('status', GuardianLinkStatus::Active->value)->exists();

            if ($isPrimary) {
                $this->clearPrimary($student);
            }

            $attributes = [
                'relation' => $relation->value,
                'is_primary' => $isPrimary,
                'is_emergency_contact' => (bool) ($data['is_emergency_contact'] ?? false),
                'status' => GuardianLinkStatus::Active,
                'verified_at' => ! empty($data['verify']) ? now() : null,
                'verified_by' => ! empty($data['verify']) ? $actor->getKey() : null,
                'revoked_at' => null,
            ];

            if ($link === null) {
                $link = StudentGuardian::create(['student_id' => $student->getKey(), 'guardian_id' => $guardian->getKey(), ...$attributes]);
            } else {
                $link->forceFill($attributes)->save();
            }

            if ($audit) {
                $this->audit->record('guardian.linked', $link, new: [
                    'student_id' => $student->getKey(),
                    'guardian_id' => $guardian->getKey(),
                    'relation' => $relation->value,
                    'is_primary' => $isPrimary,
                    'verified' => $link->verified_at !== null,
                ], actor: $actor);
            }

            return $link->setRelation('guardian', $guardian);
        });
    }

    /** @param  array{relation: string, is_primary?: bool, is_emergency_contact?: bool}  $data */
    public function updateLink(StudentGuardian $link, array $data, User $actor): StudentGuardian
    {
        return DB::transaction(function () use ($link, $data, $actor): StudentGuardian {
            $link = StudentGuardian::query()->lockForUpdate()->findOrFail($link->getKey());
            $this->assertActive($link);

            $before = ['relation' => $link->relation, 'is_primary' => $link->is_primary, 'is_emergency_contact' => $link->is_emergency_contact];
            $after = [
                'relation' => GuardianRelation::from($data['relation'])->value,
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'is_emergency_contact' => (bool) ($data['is_emergency_contact'] ?? false),
            ];

            if ($after['is_primary'] && ! $link->is_primary) {
                $this->clearPrimary($link->student()->firstOrFail());
            }

            $link->forceFill($after)->save();
            [$old, $new] = $this->audit->diff($before, $after);
            if ($new !== []) {
                $this->audit->record('guardian_link.updated', $link, $old, $new, actor: $actor);
            }

            return $link;
        });
    }

    /** The school confirms the relationship (D07: e.g. documents seen at the office). Only now may the parent portal use it. */
    public function verify(StudentGuardian $link, User $actor): StudentGuardian
    {
        return DB::transaction(function () use ($link, $actor): StudentGuardian {
            $link = StudentGuardian::query()->lockForUpdate()->findOrFail($link->getKey());
            $this->assertActive($link);

            if ($link->verified_at !== null) {
                return $link;
            }

            $link->forceFill(['verified_at' => now(), 'verified_by' => $actor->getKey()])->save();
            $this->audit->record('guardian_link.verified', $link, new: ['student_id' => $link->student_id, 'guardian_id' => $link->guardian_id], actor: $actor);

            return $link;
        });
    }

    /** Stop the guardian's access to this student (portal included). The row stays for history. */
    public function revoke(StudentGuardian $link, User $actor): StudentGuardian
    {
        return DB::transaction(function () use ($link, $actor): StudentGuardian {
            $link = StudentGuardian::query()->lockForUpdate()->findOrFail($link->getKey());
            $this->assertActive($link);

            $link->forceFill(['status' => GuardianLinkStatus::Revoked, 'revoked_at' => now(), 'is_primary' => false])->save();
            $this->audit->record('guardian_link.revoked', $link,
                old: ['status' => GuardianLinkStatus::Active->value, 'verified' => $link->verified_at !== null],
                new: ['status' => GuardianLinkStatus::Revoked->value],
                meta: ['student_id' => $link->student_id, 'guardian_id' => $link->guardian_id],
                actor: $actor,
            );

            return $link;
        });
    }

    /** @param  array<string, mixed>  $data  validated GuardianRequest data */
    public function update(Guardian $guardian, array $data, User $actor): Guardian
    {
        return DB::transaction(function () use ($guardian, $data, $actor): Guardian {
            $guardian = Guardian::query()->lockForUpdate()->findOrFail($guardian->getKey());

            // The contact email is not the portal login's email: changing it here never changes how the parent signs in.
            if (isset($data['email']) && $data['email'] !== '') {
                $data['email'] = Str::lower(trim((string) $data['email']));
            }

            $before = $this->snapshot($guardian);
            $guardian->fill(Arr::only($data, self::GUARDIAN_FIELDS))->save();
            [$old, $new] = $this->audit->diff($before, $this->snapshot($guardian));

            foreach (['phone', 'email', 'address'] as $private) {
                if (array_key_exists($private, $new)) {
                    $old[$private] = $new[$private] = '[changed]';
                }
            }

            if ($new !== []) {
                $this->audit->record('guardian.updated', $guardian, $old, $new, actor: $actor);
            }

            return $guardian;
        });
    }

    /**
     * Invite the guardian to the parent portal with the school's Parent role. Returns the one-time link (also
     * emailed). The login only sees children whose links are active and verified.
     *
     * @return array{invitation: Invitation, url: string}
     */
    public function invite(Guardian $guardian, string $email, User $actor): array
    {
        if ($guardian->user_id !== null) {
            throw ValidationException::withMessages(['email' => "{$guardian->name} already has a parent portal login."]);
        }

        $parentRoleId = Role::query()->where('slug', 'parent')->value('id')
            ?? throw ValidationException::withMessages(['email' => 'This school has no Parent role. Ask the administrator to restore it.']);

        $email = Str::lower(trim($email));

        // InvitationService validates the address (not already a member) and sends the mail after its own commit.
        $result = $this->invitations->invite($email, [(int) $parentRoleId], $actor, $guardian);

        if (Str::lower((string) $guardian->email) !== $email) {
            $guardian->forceFill(['email' => $email])->save();
        }

        $this->audit->record('guardian.invited', $guardian, meta: ['invitation_id' => $result['invitation']->getKey()], actor: $actor);

        return $result;
    }

    /** @param  array<string, mixed>  $data */
    private function resolveGuardian(array $data, User $actor): Guardian
    {
        if (! empty($data['id'])) {
            return Guardian::query()->findOrFail((int) $data['id']);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        if ($name === '' || $phone === '') {
            throw ValidationException::withMessages(['name' => 'Enter the guardian\'s name and phone number, or pick an existing guardian.']);
        }

        $existing = Guardian::query()->where('phone', $phone)->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
        if ($existing !== null) {
            return $existing;
        }

        $guardian = Guardian::create([
            ...Arr::only($data, self::GUARDIAN_FIELDS),
            'name' => $name,
            'phone' => $phone,
            'relation' => $data['relation'] ?? GuardianRelation::Guardian->value,
            'email' => isset($data['email']) && $data['email'] !== '' ? Str::lower(trim((string) $data['email'])) : null,
        ]);

        $this->audit->record('guardian.created', $guardian, new: ['relation' => $guardian->relation->value], actor: $actor);

        return $guardian;
    }

    private function clearPrimary(Student $student): void
    {
        StudentGuardian::query()->where('student_id', $student->getKey())->where('is_primary', true)->update(['is_primary' => false]);
    }

    private function assertActive(StudentGuardian $link): void
    {
        if ($link->status !== GuardianLinkStatus::Active) {
            throw ValidationException::withMessages(['guardian' => 'This link has been revoked. Link the guardian again instead.']);
        }
    }

    private function applySearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like));
    }

    /** @return array<string, mixed> */
    private function snapshot(Guardian $guardian): array
    {
        $values = $guardian->only(self::GUARDIAN_FIELDS);
        $values['relation'] = $guardian->relation?->value;

        return $values;
    }
}
