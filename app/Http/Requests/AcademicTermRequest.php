<?php

namespace App\Http\Requests;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Used for adding a term to a year and for editing a term.
 */
class AcademicTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $term = $this->route('academic_term');

        return $term
            ? $this->user()->can('update', $term)
            : $this->user()->can('create', AcademicTerm::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique(AcademicTerm::class)
                    ->where('academic_year_id', $this->year()->id)
                    ->ignore($this->route('academic_term')),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    /**
     * A term must lie inside its year and may not overlap another term of
     * the same year.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $year = $this->year();

                if ($this->input('start_date') < $year->start_date->toDateString()
                    || $this->input('end_date') > $year->end_date->toDateString()) {
                    $validator->errors()->add(
                        'start_date',
                        "The term must fall within {$year->name} ({$year->start_date->toFormattedDateString()} to {$year->end_date->toFormattedDateString()}).",
                    );

                    return;
                }

                $term = $this->route('academic_term');

                $overlapping = $year->terms()
                    ->when($term, fn ($query) => $query->whereKeyNot($term->id))
                    ->where('start_date', '<=', $this->input('end_date'))
                    ->where('end_date', '>=', $this->input('start_date'))
                    ->first();

                if ($overlapping) {
                    $validator->errors()->add('start_date', "These dates overlap with {$overlapping->name}.");
                }
            },
        ];
    }

    /**
     * The year the term belongs to: from the address when adding a term,
     * from the term itself when editing.
     */
    public function year(): AcademicYear
    {
        return $this->route('academic_year') ?? $this->route('academic_term')->academicYear;
    }
}
