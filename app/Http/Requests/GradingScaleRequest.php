<?php

namespace App\Http\Requests;

use App\Models\GradingSystem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A grading scale: bands with different starting percentages, one of them
 * starting at 0 so every percentage gets a descriptor.
 */
class GradingScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', GradingSystem::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('grading_scales')->ignore($this->route('gradingScale'))],
            'passing_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['boolean'],
            'bands' => ['required', 'array', 'min:2', 'max:20'],
            'bands.*.min_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'distinct'],
            'bands.*.label' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! collect($this->input('bands'))->contains(fn (array $band) => (float) $band['min_percentage'] === 0.0)) {
                    $validator->errors()->add('bands', 'One band must start at 0% so that every grade has a descriptor.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bands.*.min_percentage.distinct' => 'Two bands start at the same percentage.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'passing_percentage' => 'passing percentage',
            'bands.*.min_percentage' => 'starting percentage',
            'bands.*.label' => 'descriptor',
        ];
    }
}
