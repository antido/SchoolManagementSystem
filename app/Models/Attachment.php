<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A file attached to an activity, a submission or another record. Created
 * and deleted through AttachmentService, which also handles the file.
 */
class Attachment extends Model
{
    /**
     * The record the file belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return array{id: int, name: string, size: int, mime_type: string|null, url: string}
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'url' => route('attachments.show', $this),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
