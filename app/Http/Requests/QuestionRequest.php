<?php

namespace App\Http\Requests;

use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adding or editing a question. What else is needed depends on the type:
 *
 * - multiple choice: 2-10 options, exactly one correct;
 * - multiple select: 2-10 options, at least one correct;
 * - true or false: `correct_answer` ("true" or "false");
 * - identification: 1-10 accepted answers, given as options;
 * - short answer and essay: nothing; the teacher scores them.
 */
class QuestionRequest extends FormRequest
{
    public const MAX_OPTIONS = 10;

    public function authorize(): bool
    {
        $question = $this->route('question');

        return $question
            ? $this->user()->can('update', $question)
            : $this->user()->can('create', [Question::class, $this->route('activity')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:5000'],
            'question_type' => ['required', Rule::enum(QuestionType::class)],
            'points' => ['required', 'numeric', 'min:0', 'max:1000'],
            'options' => ['nullable', 'array', 'max:'.self::MAX_OPTIONS],
            'options.*.text' => ['required', 'string', 'max:1000'],
            'options.*.is_correct' => ['boolean'],
            'correct_answer' => [
                Rule::requiredIf($this->input('question_type') === QuestionType::TrueFalse->value),
                'nullable',
                'in:true,false',
            ],
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

                $options = collect($this->input('options', []));
                $correct = $options->filter(fn (array $option) => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))->count();

                match (QuestionType::from($this->input('question_type'))) {
                    QuestionType::MultipleChoice => match (true) {
                        $options->count() < 2 => $validator->errors()->add('options', 'Give at least two options.'),
                        $correct !== 1 => $validator->errors()->add('options', 'Mark exactly one option as correct.'),
                        default => null,
                    },
                    QuestionType::MultipleSelect => match (true) {
                        $options->count() < 2 => $validator->errors()->add('options', 'Give at least two options.'),
                        $correct < 1 => $validator->errors()->add('options', 'Mark at least one option as correct.'),
                        default => null,
                    },
                    QuestionType::Identification => $options->isEmpty()
                        ? $validator->errors()->add('options', 'Give at least one accepted answer.')
                        : null,
                    default => null,
                };
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'question_type' => 'question type',
            'options.*.text' => 'option',
            'correct_answer' => 'correct answer',
        ];
    }
}
