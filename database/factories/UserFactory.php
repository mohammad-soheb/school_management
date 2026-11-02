<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('9#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_platform_admin' => false,
            'status' => 'active',
            // Explicit nulls so an in-memory model has every column (strict mode refuses to read a missing attribute).
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** The product owner's Super Admin account. */
    public function platformAdmin(): static
    {
        return $this->state(fn () => ['is_platform_admin' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => 'disabled']);
    }

    /** Two-factor authentication set up and confirmed (secret JBSWY3DPEHPK3PXP, a well-known test vector). */
    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['aaaaaaaaaa-bbbbbbbbbb', 'cccccccccc-dddddddddd'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
