<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One step of a grading scale, from min_percentage up to the next band.
 */
#[Fillable(['min_percentage', 'label'])]
class GradingScaleBand extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_percentage' => 'decimal:2',
        ];
    }
}
