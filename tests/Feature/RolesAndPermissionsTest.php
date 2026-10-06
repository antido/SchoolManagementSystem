<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\PermissionRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_and_permissions_are_seeded(): void
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn (RoleName $role) => $role->value, RoleName::cases()),
            Role::pluck('name')->all(),
        );

        $this->assertEqualsCanonicalizing(PermissionRegistry::all(), Permission::pluck('name')->all());
    }

    public function test_permission_names_follow_the_area_dot_action_pattern(): void
    {
        foreach (PermissionRegistry::all() as $permission) {
            $this->assertMatchesRegularExpression('/^[a-z_]+\.[a-z_]+$/', $permission);
        }

        $this->assertContains('students.view', PermissionRegistry::all());
        $this->assertContains('students.create', PermissionRegistry::all());
        $this->assertContains('students.update', PermissionRegistry::all());
        $this->assertContains('students.delete', PermissionRegistry::all());
    }

    public function test_each_role_has_the_expected_access(): void
    {
        $role = fn (RoleName $name) => Role::findByName($name->value);

        $this->assertTrue($role(RoleName::SuperAdmin)->hasPermissionTo('settings.manage'));
        $this->assertFalse($role(RoleName::Admin)->hasPermissionTo('settings.manage'));
        $this->assertTrue($role(RoleName::Admin)->hasPermissionTo('users.create'));

        $this->assertTrue($role(RoleName::Hr)->hasPermissionTo('teachers.create'));
        $this->assertFalse($role(RoleName::Hr)->hasPermissionTo('students.create'));

        $this->assertTrue($role(RoleName::Teacher)->hasPermissionTo('assignments.create'));
        $this->assertTrue($role(RoleName::Teacher)->hasPermissionTo('attendance.record'));
        $this->assertFalse($role(RoleName::Teacher)->hasPermissionTo('users.view'));

        $this->assertTrue($role(RoleName::Student)->hasPermissionTo('assignments.submit'));
        $this->assertTrue($role(RoleName::Student)->hasPermissionTo('examinations.take'));
        $this->assertFalse($role(RoleName::Student)->hasPermissionTo('assignments.create'));
        $this->assertFalse($role(RoleName::Student)->hasPermissionTo('submissions.grade'));
    }

    public function test_the_seeder_can_run_twice(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(count(RoleName::cases()), Role::count());
        $this->assertSame(count(PermissionRegistry::all()), Permission::count());
    }

    public function test_users_can_be_given_a_role_and_checked_with_spatie(): void
    {
        $teacher = User::factory()->withRole(RoleName::Teacher)->create();

        $this->assertTrue($teacher->hasRole('teacher'));
        $this->assertTrue($teacher->can('grades.manage'));
        $this->assertFalse($teacher->can('users.delete'));
    }

    public function test_a_user_without_a_role_has_no_permissions(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('students.view'));
        $this->assertCount(0, $user->getAllPermissions());
    }

    public function test_permission_middleware_refuses_users_without_the_permission(): void
    {
        Route::middleware(['web', 'auth', 'permission:students.create'])
            ->get('/_test/students-create', fn () => 'allowed');

        $this->get('/_test/students-create')->assertRedirect('/login');

        $this->actingAs(User::factory()->withRole(RoleName::Student)->create())
            ->get('/_test/students-create')
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));

        $this->actingAs(User::factory()->withRole(RoleName::Admin)->create())
            ->get('/_test/students-create')
            ->assertOk()
            ->assertSee('allowed');
    }

    public function test_the_interface_receives_the_users_roles_and_permissions(): void
    {
        $student = User::factory()->withRole(RoleName::Student)->create();

        $this->actingAs($student)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.roles', ['student'])
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('assignments.submit')
                    && ! collect($permissions)->contains('users.view'))
            );
    }
}
