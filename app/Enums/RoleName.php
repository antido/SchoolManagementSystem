<?php

namespace App\Enums;

/**
 * The five roles. Code should check permissions, not role names; these
 * names are used when assigning roles and when choosing a dashboard.
 */
enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Hr = 'hr';
    case Teacher = 'teacher';
    case Student = 'student';

    /**
     * The name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Hr => 'HR',
            self::Teacher => 'Teacher',
            self::Student => 'Student',
        };
    }
}
