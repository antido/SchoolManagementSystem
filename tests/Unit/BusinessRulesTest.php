<?php

namespace Tests\Unit;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\AttendanceStatus;
use App\Enums\QuestionType;
use App\Services\AttendanceService;
use PHPUnit\Framework\TestCase;

/**
 * Small rules that many features rely on.
 */
class BusinessRulesTest extends TestCase
{
    public function test_activities_move_only_along_their_lifecycle(): void
    {
        $this->assertTrue(ActivityStatus::Draft->canChangeTo(ActivityStatus::Published));
        $this->assertFalse(ActivityStatus::Draft->canChangeTo(ActivityStatus::Closed));
        $this->assertTrue(ActivityStatus::Published->canChangeTo(ActivityStatus::Closed));
        $this->assertFalse(ActivityStatus::Published->canChangeTo(ActivityStatus::Draft));
        $this->assertTrue(ActivityStatus::Closed->canChangeTo(ActivityStatus::Published));
    }

    public function test_each_kind_of_activity_uses_the_right_permissions_and_workflow(): void
    {
        $this->assertSame('examinations.create', ActivityType::Quiz->permission('create'));
        $this->assertSame('examinations.create', ActivityType::Examination->permission('create'));
        $this->assertSame('assignments.create', ActivityType::Assignment->permission('create'));
        $this->assertSame('assignments.create', ActivityType::Questionnaire->permission('create'));

        $this->assertSame('take', ActivityType::Examination->studentAction());
        $this->assertSame('submit', ActivityType::Assignment->studentAction());

        // Every kind is either handed in or answered by questions, never both.
        foreach (ActivityType::cases() as $type) {
            $this->assertNotSame($type->acceptsSubmissions(), $type->usesQuestions(), $type->value);
        }
    }

    public function test_only_written_questions_need_the_teacher(): void
    {
        $manual = array_filter(QuestionType::cases(), fn (QuestionType $type) => ! $type->isAutoScored());

        $this->assertEqualsCanonicalizing([QuestionType::ShortAnswer, QuestionType::Essay], array_values($manual));
    }

    public function test_attendance_percentage_counts_late_as_attended_and_leaves_excused_out(): void
    {
        $this->assertTrue(AttendanceStatus::Late->attended());
        $this->assertFalse(AttendanceStatus::Excused->attended());

        $summary = AttendanceService::summarise(present: 6, absent: 2, late: 2, excused: 5);

        $this->assertSame(80.0, $summary['percentage']);
        $this->assertSame(15, $summary['total']);
        $this->assertNull(AttendanceService::summarise(0, 0, 0, 3)['percentage']);
        $this->assertSame(0.0, AttendanceService::summarise(0, 4, 0, 0)['percentage']);
    }
}
