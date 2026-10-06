<?php

namespace App\Enums;

/**
 * A student's attendance on a day.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Whether the student was there (late still counts as attending).
     */
    public function attended(): bool
    {
        return $this === self::Present || $this === self::Late;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
