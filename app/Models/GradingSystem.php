<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How activity scores are weighed into a grade, and which scale describes it.
 */
#[Fillable(['name', 'grading_scale_id'])]
class GradingSystem extends Model
{
    /**
     * @return BelongsTo<GradingScale, $this>
     */
    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class, 'grading_scale_id');
    }

    /**
     * @return HasMany<GradingComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(GradingComponent::class)->orderBy('order')->orderBy('id');
    }

    /**
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * Load `components` and `scale` first.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'scale' => ['id' => $this->scale->id, 'name' => $this->scale->name],
            'components' => $this->components->map(fn (GradingComponent $component) => [
                'name' => $component->name,
                'weight' => (float) $component->weight,
                'activity_types' => $component->activity_types,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
