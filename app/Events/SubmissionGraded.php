<?php

namespace App\Events;

use App\Models\ActivitySubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A teacher graded a student's handed-in work.
 */
class SubmissionGraded
{
    use Dispatchable, SerializesModels;

    public function __construct(public ActivitySubmission $submission) {}
}
