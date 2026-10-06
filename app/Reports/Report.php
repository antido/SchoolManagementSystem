<?php

namespace App\Reports;

use App\Models\User;

/**
 * One report. A report declares the filters it uses, checks who may run it
 * (including whether they may see the chosen student, class or subject),
 * and produces a ReportResult. Exporting to CSV, Excel and PDF is done by
 * ReportExporter, so reports only deal with data.
 *
 * To add a report: extend this class and list it in ReportRegistry.
 */
abstract class Report
{
    /**
     * The address used in URLs, e.g. "student-grades".
     */
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Which group the report is listed under: "Students", "Classes", "Activities" or "School".
     */
    abstract public function group(): string;

    /**
     * The filters the report uses, from: academic_year_id, academic_term_id,
     * school_class_id, class_subject_id, student_id, from, to. Required
     * ones are keys of required().
     *
     * @return list<string>
     */
    abstract public function filters(): array;

    /**
     * @return list<string>
     */
    public function required(): array
    {
        return [];
    }

    /**
     * Whether the person may open this report at all.
     */
    abstract public function allows(User $user): bool;

    /**
     * Whether the person may run it with these filters (for example, see
     * this student). Called after the required filters are present.
     */
    abstract public function allowsFilters(User $user, ReportFilters $filters): bool;

    abstract public function run(ReportFilters $filters, User $user): ReportResult;
}
