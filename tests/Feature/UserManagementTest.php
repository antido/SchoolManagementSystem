<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function accountData(array $overrides = []): array
    {
        return [
            'name' => 'Nora Reyes',
            'email' => 'nora@school.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => 'teacher',
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_admin_can_list_search_and_filter_users(): void
    {
        $admin = $this->admin();
        User::factory()->withRole(RoleName::Teacher)->create(['name' => 'Teacher One']);
        User::factory()->withRole(RoleName::Student)->create(['name' => 'Student One']);
        User::factory()->withRole(RoleName::Student)->inactive()->create(['name' => 'Student Gone']);

        $this->actingAs($admin)->get('/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Users/Index')->has('users.data', 4));

        $this->actingAs($admin)->get('/users?search=teacher')
            ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.name', 'Teacher One'));

        $this->actingAs($admin)->get('/users?role=student')
            ->assertInertia(fn ($page) => $page->has('users.data', 2));

        $this->actingAs($admin)->get('/users?role=student&status=inactive')
            ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.name', 'Student Gone'));
    }

    public function test_user_list_is_paginated(): void
    {
        $admin = $this->admin();
        User::factory()->count(16)->create();

        $this->actingAs($admin)->get('/users')
            ->assertInertia(fn ($page) => $page->has('users.data', 15)->where('users.last_page', 2));
    }

    public function test_admin_can_create_an_account_with_a_role(): void
    {
        $this->actingAs($this->admin())
            ->post('/users', $this->accountData())
            ->assertSessionHasNoErrors();

        $user = User::firstWhere('email', 'nora@school.test');

        $this->assertTrue($user->hasRole('teacher'));
        $this->assertTrue($user->is_active);
        $this->assertNotSame('secret-password', $user->password);
    }

    public function test_account_fields_are_validated(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'taken@school.test']);

        $this->actingAs($admin)
            ->post('/users', $this->accountData([
                'name' => '',
                'email' => 'taken@school.test',
                'password' => 'short',
                'password_confirmation' => 'different',
                'role' => 'janitor',
            ]))
            ->assertSessionHasErrors(['name', 'email', 'password', 'role']);
    }

    public function test_admin_cannot_create_a_super_admin(): void
    {
        $this->actingAs($this->admin())
            ->post('/users', $this->accountData(['role' => 'super-admin']))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'nora@school.test']);
    }

    public function test_super_admin_can_create_a_super_admin(): void
    {
        $this->actingAs(User::factory()->withRole(RoleName::SuperAdmin)->create())
            ->post('/users', $this->accountData(['role' => 'super-admin']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::firstWhere('email', 'nora@school.test')->hasRole('super-admin'));
    }

    public function test_admin_can_edit_an_account_and_change_its_role(): void
    {
        $user = User::factory()->withRole(RoleName::Student)->create();

        $this->actingAs($this->admin())
            ->put("/users/{$user->id}", $this->accountData([
                'password' => '',
                'password_confirmation' => '',
                'role' => 'teacher',
            ]))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Nora Reyes', $user->name);
        $this->assertSame(['teacher'], $user->getRoleNames()->all());
        $this->assertTrue(password_verify('password', $user->password));
    }

    public function test_people_cannot_change_their_own_role_or_status(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/users/{$admin->id}", $this->accountData([
                'email' => $admin->email,
                'password' => '',
                'password_confirmation' => '',
                'role' => 'super-admin',
                'is_active' => false,
            ]))
            ->assertSessionHasErrors(['role', 'is_active']);

        $this->actingAs($admin)
            ->patch("/users/{$admin->id}/status", ['is_active' => false])
            ->assertForbidden();

        $this->assertSame(['admin'], $admin->fresh()->getRoleNames()->all());
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_touch_a_super_admin_account(): void
    {
        $superAdmin = User::factory()->withRole(RoleName::SuperAdmin)->create(['name' => 'Boss']);
        $admin = $this->admin();

        $this->actingAs($admin)->get("/users/{$superAdmin->id}/edit")->assertForbidden();
        $this->actingAs($admin)
            ->put("/users/{$superAdmin->id}", $this->accountData(['role' => 'student']))
            ->assertForbidden();
        $this->actingAs($admin)
            ->patch("/users/{$superAdmin->id}/status", ['is_active' => false])
            ->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$superAdmin->id}")->assertForbidden();

        $superAdmin->refresh();
        $this->assertSame('Boss', $superAdmin->name);
        $this->assertTrue($superAdmin->is_active);
    }

    public function test_deactivated_user_cannot_authenticate(): void
    {
        $user = User::factory()->withRole(RoleName::Teacher)->create();

        $this->actingAs($this->admin())
            ->patch("/users/{$user->id}/status", ['is_active' => false])
            ->assertSessionHas('success');

        $this->assertFalse($user->fresh()->is_active);

        auth()->logout();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reactivated_user_can_sign_in_again(): void
    {
        $user = User::factory()->withRole(RoleName::Teacher)->inactive()->create();

        $this->actingAs($this->admin())->patch("/users/{$user->id}/status", ['is_active' => true]);

        auth()->logout();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_send_a_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->post("/users/{$user->id}/password-reset")
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_admin_can_delete_another_account_but_not_their_own(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertForbidden();

        $this->actingAs($admin)->delete("/users/{$user->id}")->assertRedirect('/users');
        $this->assertNull($user->fresh());
    }

    public function test_is_active_and_roles_cannot_be_mass_assigned(): void
    {
        $this->assertNotContains('is_active', (new User)->getFillable());
        $this->assertNotContains('roles', (new User)->getFillable());
    }

    public function test_roles_without_user_permissions_are_refused(): void
    {
        $target = User::factory()->create();

        foreach ([RoleName::Teacher, RoleName::Student] as $role) {
            $actor = User::factory()->withRole($role)->create();

            $this->actingAs($actor)->get('/users')->assertForbidden();
            $this->actingAs($actor)->get("/users/{$target->id}")->assertForbidden();
            $this->actingAs($actor)->post('/users', $this->accountData())->assertForbidden();
            $this->actingAs($actor)->patch("/users/{$target->id}/status", ['is_active' => false])->assertForbidden();
            $this->actingAs($actor)->delete("/users/{$target->id}")->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'nora@school.test']);
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_hr_can_view_users_but_not_change_them(): void
    {
        $hr = User::factory()->withRole(RoleName::Hr)->create();
        $target = User::factory()->create();

        $this->actingAs($hr)->get('/users')->assertOk();
        $this->actingAs($hr)->get("/users/{$target->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.update', false)->where('can.delete', false));

        $this->actingAs($hr)->post('/users', $this->accountData())->assertForbidden();
        $this->actingAs($hr)->patch("/users/{$target->id}/status", ['is_active' => false])->assertForbidden();
    }

    public function test_search_treats_wildcards_as_plain_text(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Plain Name']);

        $this->actingAs($admin)->get('/users?search='.urlencode('%'))
            ->assertInertia(fn ($page) => $page->has('users.data', 0));
    }
}
