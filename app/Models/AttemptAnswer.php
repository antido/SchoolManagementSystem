<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's answer to one question in an attempt.
 */
#[Fillable(['activity_attempt_id', 'question_id', 'selected_options', 'answer_text'])]
class AttemptAnswer extends Model
{
    /**
     * @return BelongsTo<ActivityAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ActivityAttempt::class, 'activity_attempt_id');
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selected_options' => 'array',
            'is_correct' => 'boolean',
            'points_awarded' => 'decimal:2',
        ];
    }
}
