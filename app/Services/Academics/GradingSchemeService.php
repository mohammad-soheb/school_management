<?php

namespace App\Services\Academics;

use App\Models\Academics\GradeBand;
use App\Models\Academics\GradingScheme;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grading schemes (percentage bands -> grade). The school's default scheme is seeded from
 * config/school_defaults/exams.php the first time exams are used, so a new school can start immediately.
 * Changing a scheme never changes published results: those are immutable snapshots.
 */
class GradingSchemeService
{
    public function __construct(
        private readonly GradeBandValidator $validator,
        private readonly ExamSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /** The default scheme, creating it from the configured defaults when the school has no scheme yet. */
    public function ensureDefault(?User $actor = null): GradingScheme
    {
        $default = GradingScheme::query()->where('default_flag', 1)->first();
        if ($default !== null) {
            return $default;
        }

        $existing = GradingScheme::query()->active()->orderBy('id')->first();
        if ($existing !== null) {
            return $existing;
        }

        $config = (array) $this->settings->get('default_grading_scheme', config('school_defaults.exams.default_grading_scheme'));

        try {
            return DB::transaction(function () use ($config, $actor) {
                $scheme = GradingScheme::create([
                    'name' => $config['name'] ?? 'Standard',
                    'description' => 'Created automatically from the default grading scale.',
                    'is_active' => true,
                    'default_flag' => 1,
                    'created_by' => $actor?->getKey(),
                ]);
                $this->writeBands($scheme, $config['bands'] ?? []);
                $this->audit->record('grading_scheme.created', $scheme, new: ['name' => $scheme->name, 'seeded' => true], actor: $actor);

                return $scheme;
            });
        } catch (UniqueConstraintViolationException) {
            // A parallel request seeded it first.
            return GradingScheme::query()->where('default_flag', 1)->firstOrFail();
        }
    }

    /** @return Collection<int, GradingScheme> */
    public function all(): Collection
    {
        $this->ensureDefault();

        return GradingScheme::query()->with('bands')->withCount('exams')->orderByDesc('default_flag')->orderBy('name')->get();
    }

    /** @return array<int, string> id => name, active schemes for a select */
    public function options(): array
    {
        $this->ensureDefault();

        return GradingScheme::query()->active()->orderByDesc('default_flag')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @param  array{name: string, description?: ?string, is_active?: bool, bands: list<array<string, mixed>>}  $data */
    public function create(array $data, User $actor): GradingScheme
    {
        $this->assertBands($data['bands']);

        return DB::transaction(function () use ($data, $actor) {
            $scheme = GradingScheme::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            $this->writeBands($scheme, $data['bands']);
            $this->audit->record('grading_scheme.created', $scheme, new: ['name' => $scheme->name, 'bands' => $this->bandSummary($scheme)], actor: $actor);

            return $scheme;
        });
    }

    /** @param  array{name: string, description?: ?string, is_active?: bool, bands: list<array<string, mixed>>}  $data */
    public function update(GradingScheme $scheme, array $data, User $actor): GradingScheme
    {
        $this->assertBands($data['bands']);

        $isActive = (bool) ($data['is_active'] ?? true);
        if (! $isActive && $scheme->isDefault()) {
            throw ValidationException::withMessages(['is_active' => 'The default scheme cannot be deactivated. Make another scheme the default first.']);
        }

        return DB::transaction(function () use ($scheme, $data, $actor, $isActive) {
            $scheme->load('bands');
            $before = ['name' => $scheme->name, 'description' => $scheme->description, 'is_active' => $scheme->is_active, 'bands' => $this->bandSummary($scheme)];

            $scheme->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $isActive,
                'updated_by' => $actor->getKey(),
            ])->save();

            // Bands are configuration (not student records): replace them.
            GradeBand::query()->where('grading_scheme_id', $scheme->getKey())->delete();
            $this->writeBands($scheme, $data['bands']);
            $scheme->load('bands');

            [$old, $new] = $this->audit->diff($before, ['name' => $scheme->name, 'description' => $scheme->description, 'is_active' => $scheme->is_active, 'bands' => $this->bandSummary($scheme)]);
            if ($new !== []) {
                $this->audit->record('grading_scheme.updated', $scheme, $old, $new, actor: $actor);
            }

            return $scheme;
        });
    }

    public function makeDefault(GradingScheme $scheme, User $actor): void
    {
        if (! $scheme->is_active) {
            throw ValidationException::withMessages(['scheme' => 'Activate the scheme before making it the default.']);
        }

        DB::transaction(function () use ($scheme, $actor) {
            $previous = GradingScheme::query()->where('default_flag', 1)->lockForUpdate()->first();
            $previous?->forceFill(['default_flag' => null])->save();
            $scheme->forceFill(['default_flag' => 1, 'updated_by' => $actor->getKey()])->save();

            $this->audit->record('grading_scheme.default_changed', $scheme, ['default' => $previous?->name], ['default' => $scheme->name], actor: $actor);
        });
    }

    /** @param  list<array<string, mixed>>  $bands */
    private function assertBands(array $bands): void
    {
        $errors = $this->validator->errors($bands);
        if ($errors !== []) {
            throw ValidationException::withMessages(['bands' => $errors]);
        }
    }

    /** @param  list<array<string, mixed>>  $bands */
    private function writeBands(GradingScheme $scheme, array $bands): void
    {
        usort($bands, fn ($a, $b) => (float) $b['min'] <=> (float) $a['min']);

        foreach (array_values($bands) as $i => $band) {
            GradeBand::create([
                'grading_scheme_id' => $scheme->getKey(),
                'min_percent' => $band['min'],
                'max_percent' => $band['max'],
                'grade' => trim((string) $band['grade']),
                'grade_point' => isset($band['grade_point']) && $band['grade_point'] !== '' ? $band['grade_point'] : null,
                'remark' => isset($band['remark']) && $band['remark'] !== '' ? $band['remark'] : null,
                'sort_order' => $i,
            ]);
        }
    }

    /** @return list<string> */
    private function bandSummary(GradingScheme $scheme): array
    {
        return GradeBand::query()->where('grading_scheme_id', $scheme->getKey())->orderByDesc('min_percent')->get()
            ->map(fn (GradeBand $b) => sprintf('%s: %s-%s', $b->grade, ResultCalculator::format(ResultCalculator::hundredths($b->min_percent)), ResultCalculator::format(ResultCalculator::hundredths($b->max_percent))))
            ->all();
    }
}
