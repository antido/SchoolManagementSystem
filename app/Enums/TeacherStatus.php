<?php

namespace App\Enums;

/**
 * A teacher's employment status. Active teachers and teachers on leave keep
 * their sign-in; the others cannot sign in. Teachers are given another
 * status rather than deleted, so their class history is kept.
 */
enum TeacherStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Inactive = 'inactive';
    case Resigned = 'resigned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnLeave => 'On leave',
            self::Inactive => 'Inactive',
            self::Resigned => 'Resigned',
        };
    }

    /**
     * Whether a teacher with this status may sign in.
     */
    public function canSignIn(): bool
    {
        return in_array($this, [self::Active, self::OnLeave], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
