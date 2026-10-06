<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\RoleName;
use App\Models\ActivityAttempt;
use App\Models\ActivitySubmission;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\LearningActivity;
use App\Models\Question;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Direct URL manipulation and leaks found in the security review.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private ClassSubject $classSubject;

    private Student $me;

    private Student $classmate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classSubject = ClassSubject::factory()->create();

        foreach (['me', 'classmate'] as $who) {
            $this->{$who} = Student::factory()->create();
            Enrollment::factory()->for($this->{$who})->create(['school_class_id' => $this->classSubject->school_class_id]);
        }
    }

    public function test_a_student_cannot_reach_a_classmates_records_by_changing_ids(): void
    {
        $assignment = LearningActivity::factory()->for($this->classSubject)->published()->create();
        $submission = ActivitySubmission::factory()->for($assignment)->for($this->classmate)->create();
        $attempt = ActivityAttempt::factory()->for($this->classmate)->create();
        Attendance::factory()->for($this->classmate)->create(['school_class_id' => $this->classSubject->school_class_id]);
        StudentAward::factory()->for($this->classmate)->create();

        $me = $this->me->user;

        $this->actingAs($me)->get(route('submissions.show', $submission))->assertForbidden();
        $this->actingAs($me)->patch(route('submissions.grade', $submission), ['score' => 100])->assertForbidden();
        $this->actingAs($me)->get(route('attempts.show', $attempt))->assertForbidden();
        $this->actingAs($me)->get(route('attendance.student', $this->classmate))->assertForbidden();
        $this->actingAs($me)->get(route('grades.student', [$this->classSubject, $this->classmate]))->assertForbidden();
        $this->actingAs($me)->get(route('students.show', $this->classmate))->assertForbidden();
        $this->actingAs($me)->get(route('students.edit', $this->me))->assertForbidden();
        $this->actingAs($me)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($me)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($me)->post(route('grades.compute', $this->classSubject))->assertForbidden();
    }

    public function test_quiz_results_do_not_reveal_the_key_while_attempts_remain(): void
    {
        $quiz = LearningActivity::factory()->for($this->classSubject)->ofType(ActivityType::Quiz)->published()->create(['max_attempts' => 3]);
        $question = Question::factory()->for($quiz)->multipleChoice()->create(['points' => 2]);

        $this->actingAs($this->me->user)->post(route('attempts.store', $quiz));
        $attempt = $quiz->attempts()->sole();
        $this->actingAs($this->me->user)->putJson(route('attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_options' => [$question->options->firstWhere('is_correct', false)->id],
        ]);
        $this->actingAs($this->me->user)->post(route('attempts.submit', $attempt));

        $this->actingAs($this->me->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page
                ->where('items.0.answer.is_correct', null)
                ->where('items.0.answer.points_awarded', null)
                ->missing('items.0.question.options.0.is_correct')
                ->where('attempt.score', 0)
            );

        // Once closed, the student sees everything.
        $quiz->forceFill(['status' => 'closed'])->save();
        $this->actingAs($this->me->user)
            ->get(route('attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page->where('items.0.answer.is_correct', false)->where('items.0.answer.points_awarded', 0));
    }

    public function test_only_people_who_may_enroll_get_the_school_wide_student_list(): void
    {
        $class = $this->classSubject->schoolClass;
        Student::factory()->create(['last_name' => 'Outsider']);

        $this->actingAs($this->me->user)
            ->get(route('classes.show', [$class, 'candidate_search' => 'Outsider']))
            ->assertInertia(fn ($page) => $page->reloadOnly('candidates', fn ($reload) => $reload->where('candidates', [])));

        $this->actingAs(User::factory()->withRole(RoleName::Admin)->create())
            ->get(route('classes.show', [$class, 'candidate_search' => 'Outsider']))
            ->assertInertia(fn ($page) => $page->reloadOnly('candidates', fn ($reload) => $reload->has('candidates', 1)));
    }

    public function test_teachers_cannot_identify_students_outside_their_classes(): void
    {
        $teacher = $this->classSubject->teacher->user;
        $stranger = Student::factory()->create();

        $this->actingAs($teacher)
            ->get(route('reports.show', ['class-grades', 'student_id' => $stranger->id]))
            ->assertInertia(fn ($page) => $page->where('options.student', null));

        $this->actingAs($teacher)->get(route('grades.student', [$this->classSubject, $stranger]))->assertForbidden();
        $this->actingAs($teacher)->get(route('grades.student', [$this->classSubject, $this->me]))->assertOk();
    }

    public function test_school_accounts_cannot_delete_themselves(): void
    {
        $admin = User::factory()->withRole(RoleName::SuperAdmin)->create();

        foreach ([$admin, $this->me->user, $this->classSubject->teacher->user] as $user) {
            $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($page) => $page->where('canDeleteAccount', false));
            $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHas('error');
            $this->assertModelExists($user);
        }

        // An account with no school records may still close itself.
        $plain = User::factory()->create();
        $this->actingAs($plain)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
        $this->assertModelMissing($plain);
    }

    public function test_password_forms_are_rate_limited(): void
    {
        foreach (range(1, 6) as $ignored) {
            $this->post(route('password.email'), ['email' => 'nobody@example.com']);
        }

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertStatus(429);
    }

    public function test_every_application_route_requires_sign_in_except_the_public_ones(): void
    {
        // Sign-in pages, the public FAQ, the health check and Sanctum's CSRF cookie.
        $public = ['/', 'faq', 'login', 'register', 'forgot-password', 'reset-password/{token}', 'reset-password', 'up', 'sanctum/csrf-cookie'];

        $unprotected = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($route) => in_array($route->uri(), $public, true))
            ->reject(fn ($route) => str_starts_with($route->uri(), '_') || str_starts_with($route->uri(), 'storage/'))
            ->filter(fn ($route) => ! in_array('auth', $route->gatherMiddleware(), true))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unprotected, 'These routes can be reached without signing in.');
    }

    public function test_state_changing_routes_are_protected_from_cross_site_requests(): void
    {
        $this->assertTrue(
            // Laravel 13 calls its CSRF check PreventRequestForgery.
            in_array(PreventRequestForgery::class, app(Kernel::class)->getMiddlewareGroups()['web'], true),
            'The web middleware group does not check CSRF tokens.',
        );
    }
}
