<?php

namespace Database\Factories\Identity;

use App\Models\Identity\Role;
use Database\Factories\TenantFactory;
use Illuminate\Support\Str;

/**
 * @extends TenantFactory<Role>
 */
class RoleFactory extends TenantFactory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'school_id' => $this->schoolId(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'description' => null,
            'is_system' => false,
        ];
    }
}
