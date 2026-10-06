<?php

namespace App\Services;

/**
 * The grade arithmetic, kept free of the database so it can be tested on
 * its own.
 *
 * Each component's percentage is the points earned over the points
 * possible across its activities (so a 100-point exam counts more than a
 * 10-point one). The grade is the weighted average of the components. A
 * component with nothing to count yet is left out and the remaining
 * weights are scaled up, so an early-term grade reflects what has been
 * done so far. Results are rounded to two decimals only at the end.
 */
class GradeCalculator
{
    /**
     * @param  list<array{name: string, weight: float|int|string, activity_types: list<string>}>  $components
     * @param  list<array{type: string, score: float|int, total: float|int}>  $scores  one entry per counted activity
     * @return array{percentage: float|null, components: list<array{name: string, weight: float, percentage: float|null, earned: float, possible: float, count: int}>}
     */
    public function calculate(array $components, array $scores): array
    {
        $results = [];
        $weightedSum = 0.0;
        $weightUsed = 0.0;

        foreach ($components as $component) {
            $mine = array_filter($scores, fn (array $score) => in_array($score['type'], $component['activity_types'], true));
            $earned = array_sum(array_map(fn (array $score) => (float) $score['score'], $mine));
            $possible = array_sum(array_map(fn (array $score) => (float) $score['total'], $mine));
            $weight = (float) $component['weight'];

            $percentage = $possible > 0 ? $earned / $possible * 100 : null;

            if ($percentage !== null) {
                $weightedSum += $percentage * $weight;
                $weightUsed += $weight;
            }

            $results[] = [
                'name' => $component['name'],
                'weight' => $weight,
                'percentage' => $percentage === null ? null : round($percentage, 2),
                'earned' => round($earned, 2),
                'possible' => round($possible, 2),
                'count' => count($mine),
            ];
        }

        return [
            'percentage' => $weightUsed > 0 ? round($weightedSum / $weightUsed, 2) : null,
            'components' => $results,
        ];
    }
}
