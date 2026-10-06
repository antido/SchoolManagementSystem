<?php

namespace Database\Seeders;

use App\Models\GradingScale;
use App\Models\GradingSystem;
use Illuminate\Database\Seeder;

/**
 * The default grading scale and grading system. Safe to run again: it only
 * creates them when no default exists yet.
 */
class GradingSeeder extends Seeder
{
    public function run(): void
    {
        $scale = GradingScale::default();

        if ($scale === null) {
            $scale = GradingScale::create(['name' => 'Standard (75% passing)', 'passing_percentage' => 75]);
            $scale->forceFill(['is_default' => true])->save();

            $bands = [90 => 'Outstanding', 85 => 'Very satisfactory', 80 => 'Satisfactory', 75 => 'Fairly satisfactory', 0 => 'Did not meet expectations'];

            foreach ($bands as $min => $label) {
                $scale->bands()->create(['min_percentage' => $min, 'label' => $label]);
            }
        }

        if (GradingSystem::default() === null) {
            $system = GradingSystem::create(['name' => 'Standard weighting', 'grading_scale_id' => $scale->id]);
            $system->forceFill(['is_default' => true])->save();

            $components = [
                ['name' => 'Assignments', 'weight' => 20, 'activity_types' => ['assignment']],
                ['name' => 'Quizzes', 'weight' => 20, 'activity_types' => ['quiz']],
                ['name' => 'Examinations', 'weight' => 40, 'activity_types' => ['examination']],
                ['name' => 'Projects and other work', 'weight' => 20, 'activity_types' => ['activity', 'questionnaire']],
            ];

            foreach ($components as $order => $component) {
                $system->components()->create([...$component, 'order' => $order + 1]);
            }
        }
    }
}
