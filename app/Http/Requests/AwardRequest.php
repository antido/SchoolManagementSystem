<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Models\Award;
use App\Services\AttachmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding or editing an award, with an optional image.
 */
class AwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $award = $this->route('award');

        return $award ? $this->user()->can('update', $award) : $this->user()->can('create', Award::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('awards')->ignore($this->route('award'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'criteria' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(ActiveStatus::class)],
            'image' => ['nullable', ...AttachmentService::imageRules()],
            'remove_image' => ['boolean'],
        ];
    }
}
