<?php

namespace App\Enums;

/**
 * Where an activity is in its life: teachers prepare it as a draft, publish
 * it to the class, and close it when no more work is accepted. A closed
 * activity can be published again to reopen it.
 */
enum ActivityStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The statuses this one may change to.
     *
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Draft => [self::Published],
            self::Published => [self::Closed],
            self::Closed => [self::Published],
        };
    }

    public function canChangeTo(self $status): bool
    {
        return in_array($status, $this->nextStatuses(), true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
