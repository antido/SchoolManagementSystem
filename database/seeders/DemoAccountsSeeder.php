<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountsSeeder extends Seeder
{
    /**
     * One sign-in per role for local development and demos.
     * Every account uses the password "password".
     *
     * @var array<string, array{name: string, email: string}>
     */
    public const ACCOUNTS = [
        'super-admin' => ['name' => 'Sam Superadmin', 'email' => 'superadmin@school.test'],
        'admin' => ['name' => 'Ada Admin', 'email' => 'admin@school.test'],
        'hr' => ['name' => 'Hana HR', 'email' => 'hr@school.test'],
        'teacher' => ['name' => 'Tomas Teacher', 'email' => 'teacher@school.test'],
        'student' => ['name' => 'Sofia Student', 'email' => 'student@school.test'],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $role => $account) {
            $user = User::firstWhere('email', $account['email'])
                ?? User::factory()->create($account);

            $user->syncRoles([RoleName::from($role)->value]);
        }
    }
}
