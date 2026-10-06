<?php

namespace Tests\Unit;

use App\Services\GradeCalculator;
use PHPUnit\Framework\TestCase;

class GradeCalculatorTest extends TestCase
{
    private const COMPONENTS = [
        ['name' => 'Assignments', 'weight' => 20, 'activity_types' => ['assignment']],
        ['name' => 'Quizzes', 'weight' => 20, 'activity_types' => ['quiz']],
        ['name' => 'Examinations', 'weight' => 40, 'activity_types' => ['examination']],
        ['name' => 'Projects', 'weight' => 20, 'activity_types' => ['activity']],
    ];

    private GradeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new GradeCalculator;
    }

    public function test_the_grade_is_the_weighted_average_of_the_components(): void
    {
        $result = $this->calculator->calculate(self::COMPONENTS, [
            ['type' => 'assignment', 'score' => 90, 'total' => 100],
            ['type' => 'quiz', 'score' => 8, 'total' => 10],
            ['type' => 'examination', 'score' => 70, 'total' => 100],
            ['type' => 'activity', 'score' => 50, 'total' => 50],
        ]);

        // 90*0.2 + 80*0.2 + 70*0.4 + 100*0.2
        $this->assertSame(82.0, $result['percentage']);
        $this->assertSame([90.0, 80.0, 70.0, 100.0], array_column($result['components'], 'percentage'));
    }

    public function test_a_component_counts_points_so_bigger_activities_weigh_more(): void
    {
        $result = $this->calculator->calculate(self::COMPONENTS, [
            ['type' => 'quiz', 'score' => 10, 'total' => 10],
            ['type' => 'quiz', 'score' => 20, 'total' => 40],
        ]);

        // 30 of 50 points, not the average of 100% and 50%.
        $this->assertSame(60.0, $result['components'][1]['percentage']);
        $this->assertSame(30.0, $result['components'][1]['earned']);
        $this->assertSame(50.0, $result['components'][1]['possible']);
        $this->assertSame(2, $result['components'][1]['count']);
    }

    public function test_components_with_nothing_to_count_are_left_out_and_weights_rescaled(): void
    {
        $result = $this->calculator->calculate(self::COMPONENTS, [
            ['type' => 'assignment', 'score' => 18, 'total' => 20],
            ['type' => 'quiz', 'score' => 8, 'total' => 10],
            ['type' => 'examination', 'score' => 30, 'total' => 40],
        ]);

        // (90*20 + 80*20 + 75*40) / 80
        $this->assertSame(80.0, $result['percentage']);
        $this->assertNull($result['components'][3]['percentage']);
        $this->assertSame(0, $result['components'][3]['count']);
    }

    public function test_nothing_to_count_means_no_grade(): void
    {
        $this->assertNull($this->calculator->calculate(self::COMPONENTS, [])['percentage']);
    }

    public function test_zero_scores_count(): void
    {
        $result = $this->calculator->calculate(self::COMPONENTS, [
            ['type' => 'assignment', 'score' => 0, 'total' => 20],
            ['type' => 'examination', 'score' => 40, 'total' => 40],
        ]);

        // (0*20 + 100*40) / 60
        $this->assertSame(66.67, $result['percentage']);
    }

    public function test_rounding_happens_once_at_the_end(): void
    {
        $result = $this->calculator->calculate(
            [['name' => 'A', 'weight' => 50, 'activity_types' => ['quiz']], ['name' => 'B', 'weight' => 50, 'activity_types' => ['examination']]],
            [
                ['type' => 'quiz', 'score' => 1, 'total' => 3],
                ['type' => 'examination', 'score' => 2, 'total' => 3],
            ],
        );

        // 33.333... and 66.666... average to exactly 50, not 49.995 or 50.005.
        $this->assertSame(50.0, $result['percentage']);
        $this->assertSame(33.33, $result['components'][0]['percentage']);
    }

    public function test_activity_types_outside_every_component_are_ignored(): void
    {
        $result = $this->calculator->calculate(
            [['name' => 'Exams', 'weight' => 100, 'activity_types' => ['examination']]],
            [
                ['type' => 'examination', 'score' => 45, 'total' => 50],
                ['type' => 'questionnaire', 'score' => 0, 'total' => 10],
            ],
        );

        $this->assertSame(90.0, $result['percentage']);
    }

    public function test_the_same_input_always_gives_the_same_grade(): void
    {
        $scores = [
            ['type' => 'assignment', 'score' => 17.5, 'total' => 20],
            ['type' => 'quiz', 'score' => 7, 'total' => 9],
            ['type' => 'examination', 'score' => 61, 'total' => 75],
        ];

        $first = $this->calculator->calculate(self::COMPONENTS, $scores);
        $second = $this->calculator->calculate(self::COMPONENTS, array_reverse($scores));

        $this->assertSame($first, $second);
    }
}
