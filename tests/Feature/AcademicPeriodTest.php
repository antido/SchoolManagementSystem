<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    private function year(string $name = '2026-2027', string $start = '2026-06-01', string $end = '2027-03-31'): AcademicYear
    {
        return AcademicYear::factory()->create(['name' => $name, 'start_date' => $start, 'end_date' => $end]);
    }

    public function test_admin_can_create_an_academic_year(): void
    {
        $this->actingAs($this->admin())
            ->post('/academic-years', ['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31'])
            ->assertSessionHasNoErrors();

        $year = AcademicYear::firstWhere('name', '2026-2027');

        $this->assertFalse($year->is_active);
        $this->assertSame('2027-03-31', $year->end_date->toDateString());
    }

    public function test_year_dates_and_name_are_validated(): void
    {
        $admin = $this->admin();
        $this->year();

        $this->actingAs($admin)
            ->post('/academic-years', ['name' => '2026-2027', 'start_date' => '2028-06-01', 'end_date' => '2028-01-01'])
            ->assertSessionHasErrors(['name', 'end_date']);
    }

    public function test_academic_years_may_not_overlap(): void
    {
        $this->year();

        $this->actingAs($this->admin())
            ->post('/academic-years', ['name' => 'Overlapping', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31'])
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseMissing('academic_years', ['name' => 'Overlapping']);
    }

    public function test_terms_must_fall_inside_their_year_and_not_overlap(): void
    {
        $admin = $this->admin();
        $year = $this->year();

        $this->actingAs($admin)
            ->post("/academic-years/{$year->id}/terms", ['name' => 'First Semester', 'start_date' => '2026-06-01', 'end_date' => '2026-10-31'])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post("/academic-years/{$year->id}/terms", ['name' => 'Outside', 'start_date' => '2026-05-01', 'end_date' => '2026-06-15'])
            ->assertSessionHasErrors('start_date');

        $this->actingAs($admin)
            ->post("/academic-years/{$year->id}/terms", ['name' => 'Overlap', 'start_date' => '2026-10-01', 'end_date' => '2027-01-31'])
            ->assertSessionHasErrors('start_date');

        $this->actingAs($admin)
            ->post("/academic-years/{$year->id}/terms", ['name' => 'First Semester', 'start_date' => '2026-11-01', 'end_date' => '2027-03-31'])
            ->assertSessionHasErrors('name');

        $this->assertSame(['First Semester'], $year->terms()->pluck('name')->all());
    }

    public function test_the_same_term_name_can_be_used_in_another_year(): void
    {
        $admin = $this->admin();
        $first = $this->year();
        $second = $this->year('2027-2028', '2027-06-01', '2028-03-31');
        AcademicTerm::factory()->for($first)->create(['name' => 'First Semester']);

        $this->actingAs($admin)
            ->post("/academic-years/{$second->id}/terms", ['name' => 'First Semester', 'start_date' => '2027-06-01', 'end_date' => '2027-10-31'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_year_cannot_shrink_past_its_terms(): void
    {
        $year = $this->year();
        AcademicTerm::factory()->for($year)->create(['start_date' => '2027-01-01', 'end_date' => '2027-03-31']);

        $this->actingAs($this->admin())
            ->put("/academic-years/{$year->id}", ['name' => $year->name, 'start_date' => '2026-06-01', 'end_date' => '2026-12-31'])
            ->assertSessionHasErrors('end_date');
    }

    public function test_only_one_year_and_one_term_are_active(): void
    {
        $admin = $this->admin();
        $old = $this->year('2025-2026', '2025-06-01', '2026-03-31');
        $new = $this->year();
        $oldTerm = AcademicTerm::factory()->for($old)->create();
        $first = AcademicTerm::factory()->for($new)->create(['start_date' => '2026-06-01', 'end_date' => '2026-10-31']);
        $second = AcademicTerm::factory()->for($new)->create(['start_date' => '2026-11-01', 'end_date' => '2027-03-31']);

        $this->actingAs($admin)->patch("/academic-terms/{$oldTerm->id}/activate");
        $this->assertTrue($old->fresh()->is_active);

        $this->actingAs($admin)->patch("/academic-terms/{$first->id}/activate");

        $this->assertTrue($new->fresh()->is_active);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertFalse($oldTerm->fresh()->is_active);
        $this->assertTrue($first->fresh()->is_active);
        $this->assertSame($new->id, AcademicYear::active()->id);
        $this->assertSame($first->id, AcademicTerm::active()->id);

        $this->actingAs($admin)->patch("/academic-terms/{$second->id}/activate");

        $this->assertFalse($first->fresh()->is_active);
        $this->assertSame(1, AcademicTerm::where('is_active', true)->count());
        $this->assertSame(1, AcademicYear::where('is_active', true)->count());
    }

    public function test_deactivating_a_year_deactivates_its_terms(): void
    {
        $year = $this->year();
        $term = AcademicTerm::factory()->for($year)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/academic-terms/{$term->id}/activate");
        $this->actingAs($admin)->patch("/academic-years/{$year->id}/activate", ['active' => false]);

        $this->assertFalse($year->fresh()->is_active);
        $this->assertFalse($term->fresh()->is_active);
        $this->assertNull(AcademicYear::active());
    }

    public function test_is_active_cannot_be_set_through_the_form(): void
    {
        $this->actingAs($this->admin())
            ->post('/academic-years', ['name' => 'Sneaky', 'start_date' => '2030-06-01', 'end_date' => '2031-03-31', 'is_active' => true]);

        $this->assertFalse(AcademicYear::firstWhere('name', 'Sneaky')->is_active);
    }

    public function test_a_year_with_terms_is_kept_for_history(): void
    {
        $year = $this->year();
        AcademicTerm::factory()->for($year)->create();

        $this->actingAs($this->admin())
            ->delete("/academic-years/{$year->id}")
            ->assertSessionHas('error');

        $this->assertNotNull($year->fresh());
    }

    public function test_an_empty_year_and_a_term_can_be_deleted(): void
    {
        $admin = $this->admin();
        $year = $this->year();
        $term = AcademicTerm::factory()->for($year)->create();

        $this->actingAs($admin)->delete("/academic-terms/{$term->id}")->assertSessionHas('success');
        $this->assertNull($term->fresh());

        $this->actingAs($admin)->delete("/academic-years/{$year->id}")->assertRedirect('/academic-years');
        $this->assertNull($year->fresh());
    }

    public function test_teachers_can_view_but_not_change_academic_periods(): void
    {
        $teacher = User::factory()->withRole(RoleName::Teacher)->create();
        $year = $this->year();

        $this->actingAs($teacher)->get('/academic-years')->assertOk();
        $this->actingAs($teacher)->get("/academic-years/{$year->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.update', false)->where('can.createTerm', false));

        $this->actingAs($teacher)
            ->post('/academic-years', ['name' => 'X', 'start_date' => '2030-01-01', 'end_date' => '2030-12-31'])
            ->assertForbidden();
        $this->actingAs($teacher)->patch("/academic-years/{$year->id}/activate", ['active' => true])->assertForbidden();
        $this->actingAs($teacher)
            ->post("/academic-years/{$year->id}/terms", ['name' => 'T', 'start_date' => '2026-06-01', 'end_date' => '2026-07-01'])
            ->assertForbidden();
        $this->actingAs($teacher)->delete("/academic-years/{$year->id}")->assertForbidden();
    }

    public function test_students_cannot_open_academic_period_management(): void
    {
        $this->actingAs(User::factory()->withRole(RoleName::Student)->create())
            ->get('/academic-years')
            ->assertForbidden();
    }
}
