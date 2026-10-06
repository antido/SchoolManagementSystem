<?php

namespace App\Events;

use App\Models\ActivityAttempt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Every answer of a quiz, examination or questionnaire attempt has been scored.
 */
class AttemptGraded
{
    use Dispatchable, SerializesModels;

    public function __construct(public ActivityAttempt $attempt) {}
}
