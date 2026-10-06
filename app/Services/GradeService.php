<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\AttemptStatus;
use App\Enums\SubmissionStatus;
use App\Models\ActivityAttempt;
use App\Models\ActivitySubmission;
use App\Models\ClassSubject;
use App\Models\GradingComponent;
use App\Models\GradingSystem;
use App\Models\LearningActivity;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Computes students' grades for a class subject from their activity scores.
 *
 * Which scores count, per published or closed activity worth points:
 * - graded work counts with its score (for quizzes and examinations, the
 *   best graded attempt);
 * - work handed in but not graded yet is left out until it is graded;
 * - nothing handed in counts as zero once the activity is past due or
 *   closed, and is left out before that.
 */
class GradeService
{
    public function __construct(private readonly GradeCalculator $calculator) {}

    /**
     * The grading system a class subject uses: its own, or the default.
     */
    public function systemFor(ClassSubject $classSubject): ?GradingSystem
    {
        $system = $classSubject->grading_system_id
            ? GradingSystem::find($classSubject->grading_system_id)
            : GradingSystem::default();

        return $system?->load(['components', 'scale.bands']);
    }

    /**
     * Compute the grades of every student currently enrolled in the class.
     * A grade that changed (or is new) is recorded; an unchanged grade is
     * left as it is. Returns how many grades were recorded.
     *
     * @throws RuntimeException when no grading system is set up
     */
    public function compute(ClassSubject $classSubject, ?User $computedBy): int
    {
        $system = $this->systemFor($classSubject) ?? throw new RuntimeException('No grading system is set up.');

        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query->active()->where('school_class_id', $classSubject->school_class_id))
            ->get();

        $scores = $this->scoresByStudent($classSubject, $students->pluck('id')->all());
        $components = $this->componentsOf($system);

        return DB::transaction(function () use ($classSubject, $students, $scores, $components, $system, $computedBy) {
            $current = StudentGrade::query()
                ->where('class_subject_id', $classSubject->id)
                ->current()
                ->lockForUpdate()
                ->get()
                ->keyBy('student_id');

            $recorded = 0;

            foreach ($students as $student) {
                $result = $this->calculator->calculate($components, $scores[$student->id] ?? []);

                // Nothing to grade yet.
                if ($result['percentage'] === null) {
                    continue;
                }

                $previous = $current->get($student->id);

                if ($previous && (float) $previous->percentage === $result['percentage'] && $previous->breakdown == $result['components']) {
                    continue;
                }

                $previous?->forceFill(['superseded_at' => now()])->save();

                $grade = new StudentGrade;
                $grade->class_subject_id = $classSubject->id;
                $grade->student_id = $student->id;
                $grade->percentage = $result['percentage'];
                $grade->descriptor = $system->scale->describe($result['percentage']);
                $grade->passed = $system->scale->passes($result['percentage']);
                $grade->breakdown = $result['components'];
                $grade->computed_by = $computedBy?->id;
                $grade->computed_at = now();
                $grade->save();

                $recorded++;
            }

            return $recorded;
        });
    }

    /**
     * The scores that count, per student, as the calculator expects them.
     *
     * @param  list<int>  $studentIds
     * @return array<int, list<array{type: string, score: float, total: float}>>
     */
    public function scoresByStudent(ClassSubject $classSubject, array $studentIds): array
    {
        $activities = $classSubject->learningActivities()
            ->whereIn('status', [ActivityStatus::Published, ActivityStatus::Closed])
            ->where('total_points', '>', 0)
            ->get();

        $activityIds = $activities->pluck('id');

        // Two queries for all students: submissions, and attempts.
        $submissions = ActivitySubmission::query()
            ->whereIn('learning_activity_id', $activityIds)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy(fn (ActivitySubmission $submission) => "{$submission->learning_activity_id}:{$submission->student_id}");

        $attempts = ActivityAttempt::query()
            ->whereIn('learning_activity_id', $activityIds)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy(fn (ActivityAttempt $attempt) => "{$attempt->learning_activity_id}:{$attempt->student_id}");

        $scores = [];

        foreach ($studentIds as $studentId) {
            foreach ($activities as $activity) {
                $key = "{$activity->id}:{$studentId}";
                $score = $activity->type->usesQuestions()
                    ? $this->attemptScore($activity, $attempts->get($key, collect()))
                    : $this->submissionScore($activity, $submissions->get($key, collect())->first());

                if ($score !== null) {
                    $scores[$studentId][] = ['type' => $activity->type->value, 'score' => $score, 'total' => (float) $activity->total_points];
                }
            }
        }

        return $scores;
    }

    /**
     * @return list<array{name: string, weight: float, activity_types: list<string>}>
     */
    private function componentsOf(GradingSystem $system): array
    {
        return $system->components->map(fn (GradingComponent $component) => [
            'name' => $component->name,
            'weight' => (float) $component->weight,
            'activity_types' => $component->activity_types,
        ])->values()->all();
    }

    private function submissionScore(LearningActivity $activity, ?ActivitySubmission $submission): ?float
    {
        return match (true) {
            $submission === null => $this->isOver($activity) ? 0.0 : null,
            $submission->status === SubmissionStatus::Graded => (float) $submission->score,
            default => null,
        };
    }

    /**
     * @param  Collection<int, ActivityAttempt>  $attempts
     */
    private function attemptScore(LearningActivity $activity, Collection $attempts): ?float
    {
        if ($attempts->isEmpty()) {
            return $this->isOver($activity) ? 0.0 : null;
        }

        $graded = $attempts->where('status', AttemptStatus::Graded);

        return $graded->isEmpty() ? null : (float) $graded->max(fn (ActivityAttempt $attempt) => (float) $attempt->score);
    }

    /**
     * Whether missing work counts as zero. Late work handed in afterwards
     * replaces the zero the next time grades are computed.
     */
    private function isOver(LearningActivity $activity): bool
    {
        return $activity->status === ActivityStatus::Closed || $activity->isPastDue();
    }
}
