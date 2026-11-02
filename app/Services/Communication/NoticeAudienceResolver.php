<?php

namespace App\Services\Communication;

use App\Enums\Communication\NoticeAudienceType;
use App\Models\Communication\Notice;
use App\Models\Communication\NoticeAudience;
use App\Models\Identity\SchoolMembership;
use App\Models\Staff\TeacherAssignment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who a notice is for (FR-506), in one place so the staff notice board, the dashboard widget, the notice policy
 * and the parent portal feed all agree.
 *
 *   Staff member  reached by: school, staff, role (one of their roles in this school), class (a class they are
 *                 assigned to teach in the current academic year).
 *   Guardian      reached by: school, parents, class (a class one of their children is enrolled in).
 *
 * Visibility in time (published, publish_at passed, not expired) is Notice::scopeVisibleNow(); this class only
 * answers "is it addressed to this person". Registered as a scoped binding (memo never crosses requests).
 */
class NoticeAudienceResolver
{
    /** @var array<string, array{roles: list<int>, classes: list<int>}> */
    private array $memo = [];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * The staff member's role ids in the active school and the class ids they teach this year.
     *
     * @return array{roles: list<int>, classes: list<int>}
     */
    public function staffProfile(User $user): array
    {
        $key = $this->context->requireId().':'.$user->getKey();

        return $this->memo[$key] ??= [
            'roles' => SchoolMembership::query()
                ->where('user_id', $user->getKey())
                ->with('roles:id')
                ->first()?->roles->pluck('id')->map(fn ($id) => (int) $id)->values()->all() ?? [],
            'classes' => ($yearId = $this->context->academicYear()?->getKey()) === null ? [] : TeacherAssignment::query()
                ->where('academic_year_id', $yearId)
                ->whereHas('staffProfile', fn (Builder $q) => $q->where('user_id', $user->getKey()))
                ->distinct()
                ->pluck('school_class_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ];
    }

    /**
     * Restrict a notice query to notices addressed to this staff member.
     *
     * @param  Builder<Notice>  $query
     */
    public function scopeForStaff(Builder $query, User $user): void
    {
        ['roles' => $roles, 'classes' => $classes] = $this->staffProfile($user);

        $query->whereHas('audiences', function (Builder $a) use ($roles, $classes) {
            $a->where(function (Builder $a) use ($roles, $classes) {
                $a->whereIn('type', [NoticeAudienceType::School->value, NoticeAudienceType::Staff->value])
                    ->orWhere(fn (Builder $q) => $q->where('type', NoticeAudienceType::Role->value)->whereIn('role_id', $roles))
                    ->orWhere(fn (Builder $q) => $q->where('type', NoticeAudienceType::SchoolClass->value)->whereIn('school_class_id', $classes));
            });
        });
    }

    /**
     * Restrict a notice query to notices addressed to guardians of children in these classes.
     *
     * @param  Builder<Notice>  $query
     * @param  list<int>  $classIds
     */
    public function scopeForGuardians(Builder $query, array $classIds): void
    {
        $query->whereHas('audiences', function (Builder $a) use ($classIds) {
            $a->where(function (Builder $a) use ($classIds) {
                $a->whereIn('type', [NoticeAudienceType::School->value, NoticeAudienceType::Parents->value])
                    ->orWhere(fn (Builder $q) => $q->where('type', NoticeAudienceType::SchoolClass->value)->whereIn('school_class_id', $classIds));
            });
        });
    }

    /** Is the notice addressed to this staff member (ignores the publication window)? */
    public function appliesToStaff(Notice $notice, User $user): bool
    {
        ['roles' => $roles, 'classes' => $classes] = $this->staffProfile($user);

        return $notice->audiences()->get()->contains(fn (NoticeAudience $a) => match ($a->type) {
            NoticeAudienceType::School, NoticeAudienceType::Staff => true,
            NoticeAudienceType::Role => in_array((int) $a->role_id, $roles, true),
            NoticeAudienceType::SchoolClass => in_array((int) $a->school_class_id, $classes, true),
            NoticeAudienceType::Parents => false,
        });
    }

    /**
     * Is the notice addressed to guardians of children in these classes (ignores the publication window)?
     *
     * @param  list<int>  $classIds
     */
    public function appliesToGuardians(Notice $notice, array $classIds): bool
    {
        return $notice->audiences()->get()->contains(fn (NoticeAudience $a) => match ($a->type) {
            NoticeAudienceType::School, NoticeAudienceType::Parents => true,
            NoticeAudienceType::SchoolClass => in_array((int) $a->school_class_id, $classIds, true),
            default => false,
        });
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
