<?php

namespace App\Models;

use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school year, for example "2026-2027". `is_active` is changed only by
 * AcademicPeriodService, so there is never more than one active year.
 */
#[Fillable(['name', 'start_date', 'end_date'])]
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    /**
     * @return HasMany<AcademicTerm, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(AcademicTerm::class)->orderBy('start_date');
    }

    /**
     * The year that is currently active, if any.
     */
    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * Newest first.
     *
     * @param  Builder<AcademicYear>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('start_date');
    }

    /**
     * Whether other records depend on this year. Such a year is kept for
     * history instead of being deleted.
     */
    public function hasDependents(): bool
    {
        return $this->terms()->exists() || $this->classes()->exists();
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'is_active' => $this->is_active,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
