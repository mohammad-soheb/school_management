<?php

namespace App\Services\Staff;

use App\Enums\Staff\StaffStatus;
use App\Models\Documents\StoredFile;
use App\Models\Identity\Invitation;
use App\Models\Identity\Role;
use App\Models\Identity\SchoolMembership;
use App\Models\Staff\StaffProfile;
use App\Models\User;
use App\Services\Documents\PrivateFileService;
use App\Services\Identity\InvitationService;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staff profiles (FR-208, FR-209): employee id unique per school, status instead of deletion, photo and documents
 * on the private disk, and the link between a profile and a school login (existing member or a new invitation).
 */
class StaffService
{
    public const FIELDS = ['employee_id', 'first_name', 'last_name', 'gender', 'date_of_birth', 'designation', 'department', 'qualification', 'phone', 'email', 'address', 'joined_on'];

    public const DOCUMENT_PURPOSE = 'staff_document';

    private const SORTS = ['name' => ['first_name', 'last_name'], 'employee_id' => ['employee_id'], 'joined_on' => ['joined_on']];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PrivateFileService $files,
        private readonly InvitationService $invitations,
    ) {}

    /**
     * @param  array{q?: ?string, status?: ?string, designation?: ?string, sort?: ?string, dir?: ?string}  $filters
     * @return LengthAwarePaginator<int, StaffProfile>
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = StaffProfile::query()->with('user');

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn (Builder $w) => $w->where('employee_id', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) like ?", [$like])
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['designation'])) {
            $query->where('designation', $filters['designation']);
        }

        $sort = self::SORTS[$filters['sort'] ?? ''] ?? self::SORTS['name'];
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        foreach ($sort as $column) {
            $query->orderBy($column, $dir);
        }

        return $query->orderBy('id')->paginate($perPage)->withQueryString();
    }

    /** @return array<string, string> designations in use, for the filter */
    public function designations(): array
    {
        return StaffProfile::query()->whereNotNull('designation')->distinct()->orderBy('designation')
            ->pluck('designation', 'designation')->all();
    }

    /** @param  array<string, mixed>  $data  validated StaffProfileRequest data */
    public function create(array $data, User $actor): StaffProfile
    {
        return DB::transaction(function () use ($data, $actor): StaffProfile {
            $this->assertEmployeeIdFree($data['employee_id']);

            $staff = StaffProfile::create([...Arr::only($data, self::FIELDS), 'status' => StaffStatus::Active]);
            $this->audit->record('staff.created', $staff, new: ['employee_id' => $staff->employee_id, 'designation' => $staff->designation], actor: $actor);

            return $staff;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(StaffProfile $staff, array $data, User $actor): StaffProfile
    {
        return DB::transaction(function () use ($staff, $data, $actor): StaffProfile {
            $staff = StaffProfile::query()->lockForUpdate()->findOrFail($staff->getKey());

            if (($data['employee_id'] ?? $staff->employee_id) !== $staff->employee_id) {
                $this->assertEmployeeIdFree($data['employee_id'], $staff->getKey());
            }

            $before = $this->snapshot($staff);
            $staff->fill(Arr::only($data, self::FIELDS))->save();
            [$old, $new] = $this->audit->diff($before, $this->snapshot($staff));

            foreach (['phone', 'email', 'address', 'date_of_birth'] as $private) {
                if (array_key_exists($private, $new)) {
                    $old[$private] = $new[$private] = '[changed]';
                }
            }

            if ($new !== []) {
                $this->audit->record('staff.updated', $staff, $old, $new, actor: $actor);
            }

            return $staff;
        });
    }

    /** Active / inactive / left (with the leaving date). Staff are never deleted. */
    public function changeStatus(StaffProfile $staff, StaffStatus $status, ?string $leftOn, User $actor): StaffProfile
    {
        return DB::transaction(function () use ($staff, $status, $leftOn, $actor): StaffProfile {
            $staff = StaffProfile::query()->lockForUpdate()->findOrFail($staff->getKey());
            $from = $staff->status;

            if ($status === StaffStatus::Left) {
                if ($leftOn === null || $leftOn === '') {
                    throw ValidationException::withMessages(['left_on' => 'Enter the date the staff member left.']);
                }
                $date = CarbonImmutable::parse($leftOn);
                if ($staff->joined_on !== null && $date->lessThan($staff->joined_on)) {
                    throw ValidationException::withMessages(['left_on' => 'The leaving date cannot be before the joining date.']);
                }
            }

            if ($from === $status) {
                throw ValidationException::withMessages(['status' => 'The staff member is already '.Str::lower($status->label()).'.']);
            }

            $staff->forceFill([
                'status' => $status,
                'left_on' => $status === StaffStatus::Left ? CarbonImmutable::parse($leftOn)->toDateString() : null,
            ])->save();

            $this->audit->record('staff.status_changed', $staff,
                old: ['status' => $from->value],
                new: ['status' => $status->value, 'left_on' => $staff->left_on?->toDateString()],
                actor: $actor,
            );

            return $staff;
        });
    }

    public function updatePhoto(StaffProfile $staff, UploadedFile $photo, User $actor): StoredFile
    {
        return DB::transaction(function () use ($staff, $photo, $actor): StoredFile {
            $staff = StaffProfile::query()->lockForUpdate()->findOrFail($staff->getKey());
            $old = $staff->photo_file_id !== null ? StoredFile::query()->find($staff->photo_file_id) : null;

            $stored = $this->files->store($photo, 'staff_photo', $staff, $actor);
            $staff->forceFill(['photo_file_id' => $stored->getKey()])->save();

            if ($old !== null) {
                $this->files->delete($old, $actor);
            }

            $this->audit->record('staff.photo_updated', $staff, new: ['photo_file_id' => $stored->getKey()], actor: $actor);

            return $stored;
        });
    }

    /** @return Collection<int, StoredFile> */
    public function documents(StaffProfile $staff): Collection
    {
        return StoredFile::query()
            ->where('purpose', self::DOCUMENT_PURPOSE)
            ->where('attachable_type', $staff->getMorphClass())
            ->where('attachable_id', $staff->getKey())
            ->with('uploader')
            ->latest('id')
            ->get();
    }

    public function uploadDocument(StaffProfile $staff, UploadedFile $file, User $actor): StoredFile
    {
        return $this->files->store($file, self::DOCUMENT_PURPOSE, $staff, $actor);
    }

    public function deleteDocument(StaffProfile $staff, StoredFile $file, User $actor): void
    {
        if ($file->purpose !== self::DOCUMENT_PURPOSE || $file->attachable_type !== $staff->getMorphClass() || (int) $file->attachable_id !== (int) $staff->getKey()) {
            abort(404);
        }

        $this->files->delete($file, $actor);
    }

    /**
     * School logins that could be linked to a staff profile: active staff-side members (not parent-only) who have no
     * profile yet.
     *
     * @return array<int, string> user id => "Name (email)"
     */
    public function linkableMembers(): array
    {
        $taken = StaffProfile::query()->whereNotNull('user_id')->pluck('user_id')->all();

        return SchoolMembership::query()
            ->with(['user', 'roles'])
            ->whereNotIn('user_id', $taken === [] ? [0] : $taken)
            ->get()
            ->filter(fn (SchoolMembership $m) => $m->roles->contains(fn ($role) => $role->slug !== 'parent'))
            ->sortBy(fn (SchoolMembership $m) => Str::lower($m->user->name))
            ->mapWithKeys(fn (SchoolMembership $m) => [$m->user_id => $m->user->name.' ('.$m->user->email.')'])
            ->all();
    }

    /** @return array<int, string> staff-side roles a new login may be invited with (never Parent) */
    public function invitableRoles(): array
    {
        return Role::query()->where('slug', '!=', 'parent')->orderBy('name')->pluck('name', 'id')->all();
    }

    public function linkUser(StaffProfile $staff, int $userId, User $actor): StaffProfile
    {
        return DB::transaction(function () use ($staff, $userId, $actor): StaffProfile {
            $staff = StaffProfile::query()->lockForUpdate()->findOrFail($staff->getKey());

            if ($staff->user_id !== null) {
                throw ValidationException::withMessages(['user_id' => 'This profile is already linked to a login. Unlink it first.']);
            }

            if (! array_key_exists($userId, $this->linkableMembers())) {
                throw ValidationException::withMessages(['user_id' => 'Choose a school user who does not have a staff profile yet.']);
            }

            $staff->forceFill(['user_id' => $userId])->save();
            $this->audit->record('staff.login_linked', $staff, new: ['user_id' => $userId], actor: $actor);

            return $staff;
        });
    }

    /** Detach the login from the profile. The login's school access is managed separately (Users page). */
    public function unlinkUser(StaffProfile $staff, User $actor): StaffProfile
    {
        return DB::transaction(function () use ($staff, $actor): StaffProfile {
            $staff = StaffProfile::query()->lockForUpdate()->findOrFail($staff->getKey());
            if ($staff->user_id === null) {
                return $staff;
            }

            $old = $staff->user_id;
            $staff->forceFill(['user_id' => null])->save();
            $this->audit->record('staff.login_unlinked', $staff, old: ['user_id' => $old], actor: $actor);

            return $staff;
        });
    }

    /**
     * Invite the staff member to create a login with the chosen roles. The invitation is matched back to this profile
     * by email when it is accepted (linkAcceptedInvitation), so the profile's email is set to the invited address.
     *
     * @param  list<int>  $roleIds
     * @return array{invitation: Invitation, url: string}
     */
    public function invite(StaffProfile $staff, string $email, array $roleIds, User $actor): array
    {
        if ($staff->user_id !== null) {
            throw ValidationException::withMessages(['email' => 'This staff member already has a login.']);
        }

        $email = Str::lower(trim($email));
        $clash = StaffProfile::query()->whereKeyNot($staff->getKey())->whereRaw('LOWER(email) = ?', [$email])->whereNull('user_id')->exists();
        if ($clash) {
            throw ValidationException::withMessages(['email' => 'Another staff profile without a login uses this email. Change it there first.']);
        }

        $result = $this->invitations->invite($email, $roleIds, $actor);

        if (Str::lower((string) $staff->email) !== $email) {
            $staff->forceFill(['email' => $email])->save();
        }

        $this->audit->record('staff.invited', $staff, meta: ['invitation_id' => $result['invitation']->getKey(), 'role_ids' => $roleIds], actor: $actor);

        return $result;
    }

    /**
     * Called when any staff-side invitation is accepted (PeopleServiceProvider listener, inside the school's tenant
     * context): if exactly one unlinked staff profile carries the invited email, link it to the new login.
     */
    public function linkAcceptedInvitation(Invitation $invitation): void
    {
        $userId = $invitation->getAttribute('accepted_user_id');
        if ($userId === null || StaffProfile::query()->where('user_id', $userId)->exists()) {
            return;
        }

        $candidates = StaffProfile::query()->whereNull('user_id')->whereRaw('LOWER(email) = ?', [Str::lower($invitation->email)])->get();
        if ($candidates->count() !== 1) {
            return;
        }

        $staff = $candidates->first();
        $staff->forceFill(['user_id' => $userId])->save();
        $this->audit->record('staff.login_linked', $staff, new: ['user_id' => $userId], meta: ['via' => 'invitation'], actor: User::query()->find($userId));
    }

    private function assertEmployeeIdFree(string $employeeId, ?int $ignoreId = null): void
    {
        $taken = StaffProfile::query()
            ->where('employee_id', $employeeId)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['employee_id' => 'This employee ID is already used in this school.']);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(StaffProfile $staff): array
    {
        $values = [];
        foreach (self::FIELDS as $field) {
            $value = $staff->getAttribute($field);
            $values[$field] = match (true) {
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                $value instanceof \BackedEnum => $value->value,
                default => $value,
            };
        }

        return $values;
    }
}
