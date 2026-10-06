<?php

namespace App\Http\Requests;

use App\Enums\ActivityType;
use App\Models\GradingSystem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A grading system: its components must weigh 100% together, and each kind
 * of activity may count towards one component only.
 */
class GradingSystemRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('grading_systems')->ignore($this->route('gradingSystem'))],
            'grading_scale_id' => ['required', 'integer', 'exists:grading_scales,id'],
            'is_default' => ['boolean'],
            'components' => ['required', 'array', 'min:1', 'max:10'],
            'components.*.name' => ['required', 'string', 'max:100'],
            'components.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'components.*.activity_types' => ['required', 'array', 'min:1'],
            'components.*.activity_types.*' => [Rule::enum(ActivityType::class)],
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

                $components = collect($this->input('components'));
                $total = round($components->sum(fn (array $component) => (float) $component['weight']), 2);

                if ($total !== 100.0) {
                    $validator->errors()->add('components', "The weights add up to {$total}%. They must add up to 100%.");
                }

                $types = $components->flatMap(fn (array $component) => $component['activity_types']);

                if ($types->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('components', 'Each kind of activity can count towards one component only.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'grading_scale_id' => 'grading scale',
            'components.*.name' => 'component name',
            'components.*.weight' => 'weight',
            'components.*.activity_types' => 'kinds of activity',
        ];
    }
}
