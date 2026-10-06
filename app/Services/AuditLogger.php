<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes audit entries. Models using the Auditable trait call it
 * automatically; other code can call record() for changes that are not a
 * single model save.
 */
class AuditLogger
{
    /** Long text is shortened so the trail stays readable and small. */
    private const MAX_TEXT = 500;

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(string $action, ?Model $subject, string $description, ?array $old = null, ?array $new = null): AuditLog
    {
        $actorId = Auth::id();

        // People deleting their own account: the account is gone, so the
        // entry cannot point to it. Say so in words instead.
        if ($actorId !== null && $subject instanceof User && ! $subject->exists && $subject->getKey() === $actorId) {
            $actorId = null;
            $description .= ' by the account holder';
        }

        $log = new AuditLog;
        $log->user_id = $actorId;
        $log->action = $action;
        $log->subject_type = $subject?->getMorphClass();
        $log->subject_id = $subject?->getKey();
        $log->description = mb_substr($description, 0, 255);
        $log->old_values = $old === null ? null : $this->clean($old);
        $log->new_values = $new === null ? null : $this->clean($new);
        $log->ip_address = config('school.audit.store_ip') && app()->bound('request') ? request()->ip() : null;
        $log->save();

        return $log;
    }

    /**
     * Enums become their values, dates become strings, long text is cut.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function clean(array $values): array
    {
        return array_map(function (mixed $value) {
            return match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
                is_string($value) && mb_strlen($value) > self::MAX_TEXT => mb_substr($value, 0, self::MAX_TEXT).'…',
                default => $value,
            };
        }, $values);
    }
}
