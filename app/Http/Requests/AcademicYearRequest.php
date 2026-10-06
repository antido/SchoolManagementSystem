<?php

namespace App\Http\Requests;

use App\Models\AcademicYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Used for creating and for editing an academic year.
 */
class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $year = $this->route('academic_year');

        return $year
            ? $this->user()->can('update', $year)
            : $this->user()->can('create', AcademicYear::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique(AcademicYear::class)->ignore($this->route('academic_year'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    /**
     * Two academic years may not overlap. When editing a year, its terms
     * must still fit inside the new dates.
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

                /** @var AcademicYear|null $year */
                $year = $this->route('academic_year');

                $overlapping = AcademicYear::query()
                    ->when($year, fn ($query) => $query->whereKeyNot($year->id))
                    ->where('start_date', '<=', $this->input('end_date'))
                    ->where('end_date', '>=', $this->input('start_date'))
                    ->first();

                if ($overlapping) {
                    $validator->errors()->add('start_date', "These dates overlap with {$overlapping->name}.");
                }

                $termOutside = $year?->terms()
                    ->where(function ($query): void {
                        $query->where('start_date', '<', $this->input('start_date'))
                            ->orWhere('end_date', '>', $this->input('end_date'));
                    })
                    ->first();

                if ($termOutside) {
                    $validator->errors()->add('end_date', "{$termOutside->name} would fall outside these dates. Change the term first.");
                }
            },
        ];
    }
}
