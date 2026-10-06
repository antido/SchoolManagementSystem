<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turns a percentage into a descriptor and a pass or fail.
 */
#[Fillable(['name', 'passing_percentage'])]
class GradingScale extends Model
{
    /**
     * Highest band first.
     *
     * @return HasMany<GradingScaleBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(GradingScaleBand::class)->orderByDesc('min_percentage');
    }

    /**
     * @return HasMany<GradingSystem, $this>
     */
    public function systems(): HasMany
    {
        return $this->hasMany(GradingSystem::class);
    }

    /**
     * The descriptor of the highest band the percentage reaches. Load `bands` first.
     */
    public function describe(float $percentage): string
    {
        return $this->bands->first(fn (GradingScaleBand $band) => $percentage >= (float) $band->min_percentage)?->label ?? '';
    }

    public function passes(float $percentage): bool
    {
        return $percentage >= (float) $this->passing_percentage;
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * Load `bands` first.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'passing_percentage' => (float) $this->passing_percentage,
            'is_default' => $this->is_default,
            'bands' => $this->bands->map(fn (GradingScaleBand $band) => [
                'min_percentage' => (float) $band->min_percentage,
                'label' => $band->label,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'passing_percentage' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }
}
