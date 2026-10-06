<?php

namespace App\Enums;

/**
 * Where a student stands with the school. Only active students can be
 * enrolled in classes. Students are given another status rather than
 * deleted, so their records are kept.
 */
enum StudentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Graduated = 'graduated';
    case Transferred = 'transferred';
    case Dropped = 'dropped';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
