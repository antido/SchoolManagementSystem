<?php

namespace App\Enums;

/**
 * Whether a class is in use. Archived classes keep their history but
 * accept no new students, teachers or activities.
 */
enum ClassStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

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
