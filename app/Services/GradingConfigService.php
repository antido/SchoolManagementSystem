<?php

namespace App\Services;

use App\Models\GradingScale;
use App\Models\GradingSystem;
use Illuminate\Support\Facades\DB;

/**
 * Saves grading systems and scales. Exactly one of each is the default:
 * marking one as default unmarks the others.
 */
class GradingConfigService
{
    /**
     * @param  array<string, mixed>  $data  validated GradingSystemRequest data
     */
    public function saveSystem(?GradingSystem $system, array $data): GradingSystem
    {
        return DB::transaction(function () use ($system, $data) {
            $system ??= new GradingSystem;
            $system->fill(['name' => $data['name'], 'grading_scale_id' => $data['grading_scale_id']]);
            $system->save();

            $system->components()->delete();

            foreach (array_values($data['components']) as $order => $component) {
                $system->components()->create([
                    'name' => $component['name'],
                    'weight' => $component['weight'],
                    'activity_types' => array_values(array_unique($component['activity_types'])),
                    'order' => $order + 1,
                ]);
            }

            $this->applyDefault($system, (bool) ($data['is_default'] ?? false), GradingSystem::class);

            return $system;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated GradingScaleRequest data
     */
    public function saveScale(?GradingScale $scale, array $data): GradingScale
    {
        return DB::transaction(function () use ($scale, $data) {
            $scale ??= new GradingScale;
            $scale->fill(['name' => $data['name'], 'passing_percentage' => $data['passing_percentage']]);
            $scale->save();

            $scale->bands()->delete();

            foreach ($data['bands'] as $band) {
                $scale->bands()->create(['min_percentage' => $band['min_percentage'], 'label' => $band['label']]);
            }

            $this->applyDefault($scale, (bool) ($data['is_default'] ?? false), GradingScale::class);

            return $scale;
        });
    }

    /**
     * Make the record the default when asked, or when there is no other
     * default. The current default cannot be unmarked directly; mark
     * another one instead.
     *
     * @param  class-string<GradingSystem|GradingScale>  $class
     */
    private function applyDefault(GradingSystem|GradingScale $record, bool $makeDefault, string $class): void
    {
        $hasOtherDefault = $class::query()->where('is_default', true)->whereKeyNot($record->id)->exists();

        if ($makeDefault || ! $hasOtherDefault) {
            $class::query()->whereKeyNot($record->id)->update(['is_default' => false]);
            $record->forceFill(['is_default' => true])->save();
        }
    }
}
