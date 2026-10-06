<?php

namespace App\Events;

use App\Models\ActivitySubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student handed in (or replaced) work for an activity.
 */
class WorkSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ActivitySubmission $submission) {}
}
