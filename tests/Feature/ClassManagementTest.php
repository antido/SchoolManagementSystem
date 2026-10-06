<?php

namespace Tests\Feature;

use App\Enums\ClassStatus;
use App\Enums\RoleName;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassManagementTest extends TestCase
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
    private function classData(AcademicYear $year, array $overrides = []): array
    {
        return [
            'academic_year_id' => $year->id,
            'name' => 'Grade 7 - Sampaguita',
            'code' => 'G7-SAMP',
            'grade_level' => 'Grade 7',
            'section' => 'Sampaguita',
            'description' => 'Morning section.',
            'status' => 'active',
            ...$overrides,
        ];
    }

    public function test_admin_can_create_a_class_in_an_academic_year(): void
    {
        $year = AcademicYear::factory()->create();

        $this->actingAs($this->admin())
            ->post('/classes', $this->classData($year))
            ->assertSessionHasNoErrors();

        $class = SchoolClass::firstWhere('code', 'G7-SAMP');

        $this->assertSame($year->id, $class->academic_year_id);
        $this->assertSame(ClassStatus::Active, $class->status);
    }

    public function test_class_codes_are_unique_within_a_year_only(): void
    {
        $admin = $this->admin();
        $year = AcademicYear::factory()->create();
        $nextYear = AcademicYear::factory()->create();
        SchoolClass::factory()->for($year)->create(['code' => 'G7-SAMP']);

        $this->actingAs($admin)
            ->post('/classes', $this->classData($year))
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->post('/classes', $this->classData($nextYear))
            ->assertSessionHasNoErrors();
    }

    public function test_class_fields_are_validated(): void
    {
        $this->actingAs($this->admin())
            ->post('/classes', [
                'academic_year_id' => 99999,
                'name' => '',
                'code' => 'bad code!',
                'grade_level' => '',
                'status' => 'deleted',
            ])
            ->assertSessionHasErrors(['academic_year_id', 'name', 'code', 'grade_level', 'status']);
    }

    public function test_class_list_defaults_to_the_active_year_and_can_be_filtered(): void
    {
        $admin = $this->admin();
        $old = AcademicYear::factory()->create();
        $current = AcademicYear::factory()->active()->create();
        // Fixed sections: the factory picks a random one, which could be the searched "Rizal".
        SchoolClass::factory()->for($old)->create(['name' => 'Old Class', 'section' => 'Luna']);
        SchoolClass::factory()->for($current)->create(['name' => 'Current Class', 'section' => 'Rizal']);
        SchoolClass::factory()->for($current)->archived()->create(['name' => 'Archived Class', 'section' => 'Luna']);

        $this->actingAs($admin)->get('/classes')
            ->assertInertia(fn ($page) => $page
                ->where('filters.academic_year_id', (string) $current->id)
                ->has('classes.data', 2)
            );

        $this->actingAs($admin)->get("/classes?academic_year_id={$old->id}")
            ->assertInertia(fn ($page) => $page->has('classes.data', 1)->where('classes.data.0.name', 'Old Class'));

        $this->actingAs($admin)->get("/classes?academic_year_id={$current->id}&status=archived")
            ->assertInertia(fn ($page) => $page->has('classes.data', 1));

        $this->actingAs($admin)->get('/classes?academic_year_id=&search=rizal')
            ->assertInertia(fn ($page) => $page->has('classes.data', 1)->where('classes.data.0.name', 'Current Class'));
    }

    public function test_class_detail_shows_its_academic_context(): void
    {
        $year = AcademicYear::factory()->active()->create(['name' => '2026-2027']);
        $class = SchoolClass::factory()->for($year)->create();

        $this->actingAs($this->admin())
            ->get("/classes/{$class->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Classes/Show')
                ->where('schoolClass.academic_year.name', '2026-2027')
                ->where('schoolClass.academic_year.is_active', true)
            );
    }

    public function test_admin_can_edit_and_archive_a_class(): void
    {
        $class = SchoolClass::factory()->create();

        $this->actingAs($this->admin())
            ->put("/classes/{$class->id}", $this->classData($class->academicYear, ['status' => 'archived']))
            ->assertSessionHasNoErrors();

        $this->assertSame(ClassStatus::Archived, $class->fresh()->status);
        $this->assertSame('Grade 7 - Sampaguita', $class->fresh()->name);
    }

    public function test_an_academic_year_with_classes_cannot_be_deleted(): void
    {
        $class = SchoolClass::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/academic-years/{$class->academic_year_id}")
            ->assertSessionHas('error');

        $this->assertNotNull($class->academicYear->fresh());
    }

    public function test_admin_can_delete_an_unused_class(): void
    {
        $class = SchoolClass::factory()->create();

        $this->actingAs($this->admin())->delete("/classes/{$class->id}")->assertRedirect('/classes');

        $this->assertNull($class->fresh());
    }

    public function test_teachers_and_students_can_view_but_not_manage_classes(): void
    {
        $class = SchoolClass::factory()->create(['name' => 'Kept']);

        foreach ([RoleName::Teacher, RoleName::Student] as $role) {
            $user = User::factory()->withRole($role)->create();

            $this->actingAs($user)->get('/classes/create')->assertForbidden();
            $this->actingAs($user)->post('/classes', $this->classData($class->academicYear, ['code' => 'NEW']))->assertForbidden();
            $this->actingAs($user)
                ->put("/classes/{$class->id}", $this->classData($class->academicYear, ['name' => 'Changed']))
                ->assertForbidden();
            $this->actingAs($user)->delete("/classes/{$class->id}")->assertForbidden();
        }

        $this->assertSame('Kept', $class->fresh()->name);
    }

    public function test_hr_cannot_create_classes(): void
    {
        $year = AcademicYear::factory()->create();

        $this->actingAs(User::factory()->withRole(RoleName::Hr)->create())
            ->post('/classes', $this->classData($year))
            ->assertForbidden();
    }
}
