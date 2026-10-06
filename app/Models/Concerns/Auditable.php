<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Records creates, updates and deletes of the model in the audit trail,
 * with the values that changed.
 *
 * A model can adjust what is recorded:
 * - auditName(): the name used in actions, e.g. "student" for "student.updated";
 * - auditLabel(): how the record is named in descriptions;
 * - auditIgnore(): attributes never recorded (secrets, timestamps, noise);
 * - auditEvents(): which of created, updated and deleted are recorded.
 * Hidden attributes (passwords and the like) are never recorded.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            if (in_array('created', $model->auditEvents(), true)) {
                $model->writeAudit('created', $model->auditOldValuesForCreate(), $model->auditable($model->getAttributes()));
            }
        });

        static::updated(function (self $model): void {
            if (! in_array('updated', $model->auditEvents(), true)) {
                return;
            }

            $changed = $model->auditable($model->getChanges());

            if ($changed !== []) {
                $model->writeAudit('updated', array_intersect_key($model->auditable($model->getRawOriginal()), $changed), $changed);
            }
        });

        static::deleted(function (self $model): void {
            if (in_array('deleted', $model->auditEvents(), true)) {
                $model->writeAudit('deleted', $model->auditable($model->getAttributes()), null);
            }
        });
    }

    public function auditName(): string
    {
        return str(class_basename($this))->snake()->toString();
    }

    public function auditLabel(): string
    {
        return '#'.$this->getKey();
    }

    /**
     * @return list<string>
     */
    public function auditIgnore(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function auditEvents(): array
    {
        return ['created', 'updated', 'deleted'];
    }

    /**
     * Earlier values to show beside a new record, for records that replace
     * an older one (a recomputed grade). None by default.
     *
     * @return array<string, mixed>|null
     */
    public function auditOldValuesForCreate(): ?array
    {
        return null;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected function writeAudit(string $event, ?array $old, ?array $new): void
    {
        $name = str($this->auditName())->replace('_', ' ')->toString();

        app(AuditLogger::class)->record(
            "{$this->auditName()}.{$event}",
            $this,
            ucfirst("{$name} {$this->auditLabel()} {$event}"),
            $old,
            $new,
        );
    }

    /**
     * The attributes worth recording, as stored (enums and dates are
     * already plain values there).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditable(array $attributes): array
    {
        $skip = [...$this->getHidden(), ...$this->auditIgnore(), 'created_at', 'updated_at', 'remember_token', 'password'];

        return array_diff_key($attributes, array_flip($skip));
    }
}
