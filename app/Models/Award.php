<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use Database\Factories\AwardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A kind of award the school gives, such as Academic Excellence.
 */
#[Fillable(['name', 'description', 'criteria', 'status'])]
class Award extends Model
{
    /** @use HasFactory<AwardFactory> */
    use HasFactory;

    /**
     * Every time it was given, including revoked ones.
     *
     * @return HasMany<StudentAward, $this>
     */
    public function studentAwards(): HasMany
    {
        return $this->hasMany(StudentAward::class);
    }

    /**
     * The optional image (at most one).
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    /**
     * Awards that were given to someone are kept for history.
     */
    public function hasDependents(): bool
    {
        return $this->studentAwards()->exists();
    }

    /**
     * Load `attachments` first.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $image = $this->attachments->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'criteria' => $this->criteria,
            'status' => $this->status->value,
            'image_url' => $image ? route('attachments.show', [$image, 'inline' => 1]) : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
        ];
    }
}
