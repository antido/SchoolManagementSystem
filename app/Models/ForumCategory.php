<?php

namespace App\Models;

use Database\Factories\ForumCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A section of the forum, such as "Homework help".
 */
#[Fillable(['name', 'description', 'order'])]
class ForumCategory extends Model
{
    /** @use HasFactory<ForumCategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<ForumTopic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(ForumTopic::class);
    }

    /**
     * Categories with topics (even removed ones) are kept.
     */
    public function hasDependents(): bool
    {
        return $this->topics()->withTrashed()->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }
}
