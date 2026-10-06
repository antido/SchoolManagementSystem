<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A weighted part of a grading system, made of the scores of some activity types.
 */
#[Fillable(['name', 'weight', 'activity_types', 'order'])]
class GradingComponent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'activity_types' => 'array',
            'order' => 'integer',
        ];
    }
}
