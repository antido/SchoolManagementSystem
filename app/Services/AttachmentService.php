<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use App\Rules\FileContentMatchesExtension;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores, serves and removes uploaded files.
 *
 * Files go to the private disk set in config/school.php under a name
 * Laravel generates, so nothing the browser sends is used as a path. The
 * name the user gave the file is kept only to show it and to name downloads.
 */
class AttachmentService
{
    /**
     * Validation rules for an uploaded image (award pictures and the like).
     *
     * @return list<mixed>
     */
    public static function imageRules(): array
    {
        return ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048', new FileContentMatchesExtension];
    }

    /**
     * Validation rules for one uploaded file.
     *
     * @return list<mixed>
     */
    public static function fileRules(): array
    {
        $extensions = implode(',', config('school.attachments.extensions'));

        return [
            'file',
            'max:'.config('school.attachments.max_kilobytes'),
            // Both the name's extension and the detected content type must be allowed.
            'extensions:'.$extensions,
            'mimes:'.$extensions,
            new FileContentMatchesExtension,
        ];
    }

    /**
     * Validation rules for a list of files under `$field`, plus the list of
     * existing attachment ids to remove under `remove_attachments`.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $field = 'attachments'): array
    {
        return [
            $field => ['nullable', 'array', 'max:'.config('school.attachments.max_files')],
            "{$field}.*" => self::fileRules(),
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],
        ];
    }

    /**
     * Attach the uploaded files to a record. The record needs an
     * `attachments()` morphMany relation.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function attach(Model $attachable, array $files, ?User $uploader, string $directory): void
    {
        $disk = config('school.attachments.disk');

        foreach ($files as $file) {
            $attachment = new Attachment;
            $attachment->disk = $disk;
            $attachment->path = $file->store($directory.'/'.now()->format('Y/m'), $disk);
            $attachment->original_name = $this->safeName($file->getClientOriginalName());
            $attachment->mime_type = $file->getMimeType();
            $attachment->size = $file->getSize();
            $attachment->uploaded_by = $uploader?->id;

            $this->relation($attachable)->save($attachment);
        }
    }

    /**
     * Remove the given attachments of this record. Ids that belong to other
     * records are ignored.
     *
     * @param  array<int, int|string>  $ids
     */
    public function detach(Model $attachable, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->relation($attachable)->whereKey($ids)->get()->each(fn (Attachment $attachment) => $this->delete($attachment));
    }

    /**
     * Remove every attachment of this record, for when the record is deleted.
     */
    public function detachAll(Model $attachable): void
    {
        $this->relation($attachable)->get()->each(fn (Attachment $attachment) => $this->delete($attachment));
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    /**
     * Send the file as a download, or, for images asked for inline, to be
     * shown in the page.
     */
    public function download(Attachment $attachment, bool $inline = false): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if ($inline && str_starts_with((string) $attachment->mime_type, 'image/')) {
            return $disk->response($attachment->path, $attachment->original_name, [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }

    /**
     * How many attachments the record would have after removing and adding.
     *
     * @param  array<int, int|string>  $removeIds
     */
    public function countAfter(?Model $attachable, array $removeIds, int $adding): int
    {
        $current = $attachable ? $this->relation($attachable)->whereKeyNot($removeIds)->count() : 0;

        return $current + $adding;
    }

    /**
     * Keep only the base name, without control characters, for display.
     */
    private function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name))) ?? '';

        return mb_substr($name !== '' ? $name : 'file', -200);
    }

    /**
     * @return MorphMany<Attachment, Model>
     */
    private function relation(Model $attachable): MorphMany
    {
        return $attachable->attachments();
    }
}
