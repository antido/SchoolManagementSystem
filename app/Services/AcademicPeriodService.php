<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

/**
 * Keeps exactly one academic year and one term "active", so the rest of the
 * system always knows which period new classes, enrollments and grades
 * belong to.
 */
class AcademicPeriodService
{
    /**
     * Make this the active year. Every other year, and every term outside
     * this year, stops being active.
     */
    public function activateYear(AcademicYear $year): void
    {
        DB::transaction(function () use ($year): void {
            AcademicYear::query()->whereKeyNot($year->id)->update(['is_active' => false]);
            AcademicTerm::query()->where('academic_year_id', '!=', $year->id)->update(['is_active' => false]);

            $year->is_active = true;
            $year->save();
        });
    }

    /**
     * Make this the active term. Its year becomes the active year too.
     */
    public function activateTerm(AcademicTerm $term): void
    {
        DB::transaction(function () use ($term): void {
            $this->activateYear($term->academicYear);

            AcademicTerm::query()->whereKeyNot($term->id)->update(['is_active' => false]);

            $term->is_active = true;
            $term->save();
        });
    }

    /**
     * Stop the year (and its terms) being active, for example after the
     * school year ends.
     */
    public function deactivateYear(AcademicYear $year): void
    {
        DB::transaction(function () use ($year): void {
            $year->terms()->update(['is_active' => false]);

            $year->is_active = false;
            $year->save();
        });
    }
}
