<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Always needed, in every environment.
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(GradingSeeder::class);

        // Sample accounts use the password "password", so they must never
        // be created on a live site.
        if (! app()->isProduction()) {
            $this->call(DemoAccountsSeeder::class);
            $this->call(DemoSchoolSeeder::class);
        }
    }
}
