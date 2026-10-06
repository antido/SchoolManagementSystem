<?php

namespace App\Enums;

/**
 * A simple in-use / not-in-use status, used by subjects and class subjects.
 * Inactive records are kept for history but cannot be chosen for new work.
 */
enum ActiveStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

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
