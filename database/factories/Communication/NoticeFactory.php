<?php

namespace Database\Factories\Communication;

use App\Enums\Communication\NoticeAudienceType;
use App\Enums\Communication\NoticeStatus;
use App\Models\Communication\Notice;
use App\Models\Communication\NoticeAudience;
use App\Models\Identity\Role;
use App\Models\SchoolSetup\SchoolClass;
use Database\Factories\TenantFactory;

/**
 * Notices default to a published, school-wide notice. Audience helpers add NoticeAudience rows after creation.
 *
 * @extends TenantFactory<Notice>
 */
class NoticeFactory extends TenantFactory
{
    protected $model = Notice::class;

    public function definition(): array
    {
        return [
            'school_id' => $this->schoolId(),
            'title' => fake()->randomElement(['Parent-teacher meeting', 'Sports day', 'Fee reminder', 'Holiday notice', 'Uniform update']).' '.fake()->numberBetween(1, 999),
            'body' => fake()->paragraph(),
            'status' => NoticeStatus::Published,
            'is_pinned' => false,
            'publish_at' => now()->subHour(),
            'expires_at' => null,
            'published_at' => now()->subHour(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => NoticeStatus::Draft, 'publish_at' => null, 'published_at' => null]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => NoticeStatus::Archived, 'archived_at' => now()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['publish_at' => now()->addDay()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['publish_at' => now()->subDays(5), 'expires_at' => now()->subMinute()]);
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }

    public function forWholeSchool(): static
    {
        return $this->afterCreating(fn (Notice $n) => NoticeAudience::create(['notice_id' => $n->id, 'type' => NoticeAudienceType::School]));
    }

    public function forAllStaff(): static
    {
        return $this->afterCreating(fn (Notice $n) => NoticeAudience::create(['notice_id' => $n->id, 'type' => NoticeAudienceType::Staff]));
    }

    public function forAllParents(): static
    {
        return $this->afterCreating(fn (Notice $n) => NoticeAudience::create(['notice_id' => $n->id, 'type' => NoticeAudienceType::Parents]));
    }

    /** @param  list<SchoolClass|int>  $classes */
    public function forClasses(array $classes): static
    {
        return $this->afterCreating(function (Notice $n) use ($classes) {
            foreach ($classes as $class) {
                NoticeAudience::create(['notice_id' => $n->id, 'type' => NoticeAudienceType::SchoolClass, 'school_class_id' => $class instanceof SchoolClass ? $class->id : $class]);
            }
        });
    }

    /** @param  list<Role|string>  $roles  Role models or role slugs of the notice's school */
    public function forRoles(array $roles): static
    {
        return $this->afterCreating(function (Notice $n) use ($roles) {
            foreach ($roles as $role) {
                $roleId = $role instanceof Role ? $role->id : Role::query()->where('school_id', $n->school_id)->where('slug', $role)->value('id');
                NoticeAudience::create(['notice_id' => $n->id, 'type' => NoticeAudienceType::Role, 'role_id' => $roleId]);
            }
        });
    }
}
