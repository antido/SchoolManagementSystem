<?php

namespace App\Enums;

/**
 * How a teacher is attached to a class (separately from the subjects they
 * teach it, which are recorded on class subjects).
 */
enum ClassTeacherRole: string
{
    /** The homeroom teacher responsible for the class. One per class. */
    case Adviser = 'adviser';

    /** Any other teacher attached to the class. */
    case Teacher = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::Adviser => 'Class adviser',
            self::Teacher => 'Teacher',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
