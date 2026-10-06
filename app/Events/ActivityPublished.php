<?php

namespace App\Events;

use App\Models\LearningActivity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A learning activity was published to students for the first time.
 */
class ActivityPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public LearningActivity $activity) {}
}
