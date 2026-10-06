<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Models\Concerns\Auditable;
use App\Services\AvatarService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * A person who can sign in: an administrator, HR staff, a teacher or a student.
 *
 * `is_active` and roles are not fillable. They are changed only by the
 * user-management code, never straight from a form.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'avatar_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable;

    /**
     * New accounts are active unless deactivated.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The student profile, when this account belongs to a student.
     *
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * The teacher profile, when this account belongs to a teacher.
     *
     * @return HasOne<Teacher, $this>
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * Remove the profile picture file along with the account.
     */
    protected static function booted(): void
    {
        static::deleting(fn (User $user) => app(AvatarService::class)->deleteFile($user));
    }

    /**
     * Where the profile picture is shown from, or null when there is none.
     * The address includes part of the file name, so it changes (and old
     * copies stop being cached) when the picture is replaced.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? route('avatars.show', [$this, 'v' => substr(md5($this->avatar_path), 0, 8)]) : null;
    }

    /**
     * Whether this person only sees their own classes and students.
     *
     * Teachers and students (by role or by profile) are limited to the
     * classes they teach or are enrolled in; with the role but no profile
     * yet, that is no classes at all. Staff who may manage classes (admins)
     * and other staff without such a role or profile (HR) see every class.
     */
    public function isLimitedToOwnClasses(): bool
    {
        if ($this->can('classes.update')) {
            return false;
        }

        return $this->teacher !== null
            || $this->student !== null
            || $this->hasAnyRole([RoleName::Teacher->value, RoleName::Student->value]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function auditLabel(): string
    {
        return $this->name.' ('.$this->email.')';
    }

    /**
     * The last sign-in time changes on every sign-in, which is not worth recording.
     *
     * @return list<string>
     */
    public function auditIgnore(): array
    {
        return ['last_login_at'];
    }
}
