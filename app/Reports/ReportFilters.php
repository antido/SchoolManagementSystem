<?php

namespace App\Reports;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The filters a report is run with, read from the query string and turned
 * into models. Unknown ids simply become null.
 */
final class ReportFilters
{
    public function __construct(
        public readonly ?AcademicYear $academicYear = null,
        public readonly ?AcademicTerm $academicTerm = null,
        public readonly ?SchoolClass $schoolClass = null,
        public readonly ?ClassSubject $classSubject = null,
        public readonly ?Student $student = null,
        public readonly ?Carbon $from = null,
        public readonly ?Carbon $to = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $date = function (string $key) use ($request): ?Carbon {
            $value = $request->query($key);

            return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
                ? rescue(fn () => Carbon::createFromFormat('Y-m-d', $value)->startOfDay(), null, report: false)
                : null;
        };

        $find = fn (string $class, string $key) => $request->integer($key) > 0 ? $class::find($request->integer($key)) : null;

        return new self(
            academicYear: $find(AcademicYear::class, 'academic_year_id'),
            academicTerm: $find(AcademicTerm::class, 'academic_term_id'),
            schoolClass: $find(SchoolClass::class, 'school_class_id'),
            classSubject: $find(ClassSubject::class, 'class_subject_id'),
            student: $find(Student::class, 'student_id'),
            from: $date('from'),
            to: $date('to'),
        );
    }

    /**
     * Whether a filter has a value.
     */
    public function has(string $filter): bool
    {
        return match ($filter) {
            'academic_year_id' => $this->academicYear !== null,
            'academic_term_id' => $this->academicTerm !== null,
            'school_class_id' => $this->schoolClass !== null,
            'class_subject_id' => $this->classSubject !== null,
            'student_id' => $this->student !== null,
            'from' => $this->from !== null,
            'to' => $this->to !== null,
            default => false,
        };
    }

    /**
     * The period asked for, defaulting to the last 90 days up to today.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function period(): array
    {
        $from = $this->from ?? today()->subDays(90);
        $to = $this->to ?? today();

        return $from->lte($to) ? [$from, $to] : [$to, $from];
    }

    /**
     * The chosen filters in words, for report headings.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        return array_values(array_filter([
            $this->student ? "Student: {$this->student->full_name} ({$this->student->student_number})" : null,
            $this->schoolClass ? "Class: {$this->schoolClass->name}" : null,
            $this->classSubject ? 'Subject: '.$this->classSubject->loadMissing(['subject', 'schoolClass', 'academicTerm'])->label() : null,
            $this->academicYear ? "Academic year: {$this->academicYear->name}" : null,
            $this->academicTerm ? "Term: {$this->academicTerm->name}" : null,
            $this->from || $this->to ? 'Period: '.implode(' to ', array_map(fn (Carbon $date) => $date->format('M j, Y'), $this->period())) : null,
        ]));
    }
}
