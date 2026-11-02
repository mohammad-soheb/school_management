<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Production: only the Super Admin (PLATFORM_ADMIN_* in .env). Local: also two synthetic demo schools so every
     * screen has data to show. Demo data is fake - never put a real student in it.
     */
    public function run(): void
    {
        $this->call(PlatformAdminSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
