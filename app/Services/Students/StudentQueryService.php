<?php

namespace App\Services\Students;

use App\Enums\Students\GuardianLinkStatus;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\SchoolSetup\Section;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Policies\Students\StudentPolicy;
use App\Support\Identity\AssignmentScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read side of the student register: the list, the JSON lookup and the profile. Every query starts from
 * visibleTo(), which applies the teacher's section restriction, so a list can never show more than the policy allows.
 */
class StudentQueryService
{
    /** Sortable columns: request value => SQL column(s). Never order by raw input. */
    private const SORTS = [
        'name' => ['first_name', 'last_name'],
        'admission_no' => ['admission_no'],
        'admission_date' => ['admission_date'],
    ];

    public const LOOKUP_LIMIT = 15;

    public function __construct(
        private readonly TenantContext $context,
        private readonly AssignmentScope $scope,
    ) {}

    /** Students the user may see: everyone with students.view_all, otherwise only the assigned sections this year. */
    public function visibleTo(User $user): Builder
    {
        $query = Student::query();
        $sections = $this->scope->sectionIds($user, StudentPolicy::VIEW_ALL);

        if ($sections !== null) {
            $yearId = $this->context->academicYear()?->getKey();
            $query->whereHas('enrollments', fn (Builder $e) => $e
                ->where('academic_year_id', $yearId ?? 0)
                ->whereIn('section_id', $sections === [] ? [0] : $sections));
        }

        return $query;
    }

    /**
     * @param  array{q?: ?string, class_id?: ?int, section_id?: ?int, status?: ?string, sort?: ?string, dir?: ?string}  $filters
     * @return LengthAwarePaginator<int, Student>
     */
    public function paginate(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->visibleTo($user)
            ->with([
                'currentEnrollment.schoolClass',
                'currentEnrollment.section',
                'guardians' => fn ($g) => $g->wherePivot('status', GuardianLinkStatus::Active->value)->orderByPivot('is_primary', 'desc'),
            ]);

        $this->applySearch($query, $filters['q'] ?? null);
        $this->applyPlacement($query, $filters['class_id'] ?? null, $filters['section_id'] ?? null);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sort = self::SORTS[$filters['sort'] ?? ''] ?? self::SORTS['name'];
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        foreach ($sort as $column) {
            $query->orderBy($column, $dir);
        }

        return $query->orderBy('id')->paginate($perPage)->withQueryString();
    }

    /**
     * JSON lookup for <x-form.lookup>: name, admission number or phone. On-roll students first.
     *
     * @return list<array{id: int, label: string, sublabel: string}>
     */
    public function lookup(User $user, string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 1) {
            return [];
        }

        $query = $this->visibleTo($user)->with(['currentEnrollment.schoolClass', 'currentEnrollment.section']);
        $this->applySearch($query, $term);

        return $query
            ->orderByRaw('status = ? desc', ['active'])
            ->orderByRaw('admission_no = ? desc', [$term])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(self::LOOKUP_LIMIT)
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->getKey(),
                'label' => $student->fullName(),
                'sublabel' => $this->sublabel($student),
            ])
            ->all();
    }

    /** Eager-load everything the profile page shows. */
    public function profile(Student $student): Student
    {
        return $student->load([
            'currentEnrollment.schoolClass',
            'currentEnrollment.section',
            'guardianLinks' => fn ($q) => $q->with(['guardian', 'verifier'])->orderByRaw('status = ? desc', ['active'])->orderByDesc('is_primary')->orderBy('id'),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, StudentEnrollment> every year's enrollment, newest first */
    public function enrollmentHistory(Student $student): \Illuminate\Support\Collection
    {
        return StudentEnrollment::query()
            ->where('student_id', $student->getKey())
            ->with(['academicYear', 'schoolClass', 'section'])
            ->join('academic_years', 'academic_years.id', '=', 'student_enrollments.academic_year_id')
            ->orderByDesc('academic_years.starts_on')
            ->select('student_enrollments.*')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\Students\StudentStatusChange> */
    public function statusHistory(Student $student): \Illuminate\Support\Collection
    {
        return $student->statusChanges()->with('changer')->orderByDesc('effective_on')->orderByDesc('id')->get();
    }

    /**
     * Class and section choices for filters and forms. A teacher without view_all only sees the sections they teach.
     *
     * Forms pass $activeOnly so deactivated classes/sections are not offered for new placements (FR-202).
     *
     * @return array{classes: array<int, string>, sections: array<string, array<int, string>>, sectionsByClass: array<int, array<int, string>>}
     */
    public function placementOptions(?User $user = null, bool $activeOnly = false): array
    {
        $allowed = $user === null ? null : $this->scope->sectionIds($user, StudentPolicy::VIEW_ALL);

        $classes = SchoolClass::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $sections = Section::query()
            ->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed === [] ? [0] : $allowed))
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'school_class_id', 'name']);

        $classNames = $classes->pluck('name', 'id');
        if ($allowed !== null) {
            $classNames = $classNames->only($sections->pluck('school_class_id')->unique()->all());
        }

        // Grouped by class name for <optgroup>s: ['Class 5' => [12 => 'Class 5 - A', ...]]
        $grouped = [];
        $byClass = [];
        foreach ($classNames as $classId => $className) {
            foreach ($sections->where('school_class_id', $classId) as $section) {
                $grouped[$className][$section->id] = $className.' - '.$section->name;
                $byClass[$classId][$section->id] = $section->name;
            }
        }

        return ['classes' => $classNames->all(), 'sections' => $grouped, 'sectionsByClass' => $byClass];
    }

    public function currentYear(): ?AcademicYear
    {
        return $this->context->academicYear();
    }

    public function sublabel(Student $student): string
    {
        $enrollment = $student->relationLoaded('currentEnrollment') ? $student->getRelation('currentEnrollment') : null;
        $parts = ['Adm. '.$student->admission_no];

        if ($enrollment !== null) {
            $parts[] = trim($enrollment->schoolClass->name.($enrollment->section ? ' - '.$enrollment->section->name : ''));
        }

        if (! $student->status->isOnRoll()) {
            $parts[] = $student->status->label();
        }

        return implode(' · ', $parts);
    }

    private function applySearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $w) use ($like): void {
            $w->where('admission_no', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) like ?", [$like])
                ->orWhere('phone', 'like', $like)
                ->orWhereHas('guardians', fn (Builder $g) => $g->where('guardians.phone', 'like', $like));
        });
    }

    private function applyPlacement(Builder $query, mixed $classId, mixed $sectionId): void
    {
        if (empty($classId) && empty($sectionId)) {
            return;
        }

        $yearId = $this->context->academicYear()?->getKey() ?? 0;

        $query->whereHas('enrollments', fn (Builder $e) => $e
            ->where('academic_year_id', $yearId)
            ->when(! empty($classId), fn ($q) => $q->where('school_class_id', (int) $classId))
            ->when(! empty($sectionId), fn ($q) => $q->where('section_id', (int) $sectionId)));
    }
}
