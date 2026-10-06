<?php

namespace App\Events;

use App\Models\StudentAward;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student received an award.
 */
class AwardGranted
{
    use Dispatchable, SerializesModels;

    public function __construct(public StudentAward $studentAward) {}
}
