<?php

namespace App\Models;

use App\Enums\QuestionType;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A question on a quiz, examination or questionnaire. Created and changed
 * through QuestionService, which also keeps the activity's total points.
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<LearningActivity, $this>
     */
    public function learningActivity(): BelongsTo
    {
        return $this->belongsTo(LearningActivity::class);
    }

    /**
     * Choices, or accepted answers for identification questions, in order.
     *
     * @return HasMany<QuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order')->orderBy('id');
    }

    /**
     * Load `options` first. With `$withAnswers`, says which options are
     * correct; leave it off for anything a student sees.
     *
     * @return array<string, mixed>
     */
    public function summary(bool $withAnswers = false): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'type' => $this->question_type->value,
            'type_label' => $this->question_type->label(),
            'points' => (float) $this->points,
            'order' => $this->order,
            // Students never see identification answers: they would give the answer away.
            'options' => $this->question_type === QuestionType::Identification && ! $withAnswers
                ? []
                : $this->options->map(fn (QuestionOption $option) => [
                    'id' => $option->id,
                    'text' => $option->option_text,
                    ...$withAnswers ? ['is_correct' => $option->is_correct] : [],
                ])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'points' => 'decimal:2',
            'order' => 'integer',
        ];
    }
}
