<?php

namespace App\Enums;

use App\Models\User;

/**
 * The kinds of learning activity a teacher can give a class. Every kind is
 * stored in the same table; later modules add what is specific to each
 * (submissions for assignments, questions for quizzes and examinations).
 */
enum ActivityType: string
{
    case Assignment = 'assignment';
    case Quiz = 'quiz';
    case Examination = 'examination';
    case Questionnaire = 'questionnaire';
    case Activity = 'activity';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The permission area that governs this kind: quizzes and examinations
     * use examinations.*, everything else uses assignments.*.
     */
    public function permissionArea(): string
    {
        return match ($this) {
            self::Quiz, self::Examination => 'examinations',
            default => 'assignments',
        };
    }

    /**
     * Whether students hand in work (text and files) for it. Quizzes,
     * examinations and questionnaires are answered question by question instead.
     */
    public function acceptsSubmissions(): bool
    {
        return $this === self::Assignment || $this === self::Activity;
    }

    /**
     * Whether it is made of questions that students answer one by one.
     */
    public function usesQuestions(): bool
    {
        return $this === self::Quiz || $this === self::Examination || $this === self::Questionnaire;
    }

    /**
     * The permission action a student needs to answer it: quizzes and
     * examinations are "taken", the other kinds are "submitted".
     */
    public function studentAction(): string
    {
        return $this->permissionArea() === 'examinations' ? 'take' : 'submit';
    }

    /**
     * The permission needed to do something with this kind, e.g. "examinations.create".
     */
    public function permission(string $action): string
    {
        return "{$this->permissionArea()}.{$action}";
    }

    /**
     * The kinds this person may do something with.
     *
     * @return list<self>
     */
    public static function allowedFor(User $user, string $action): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $user->can($type->permission($action))));
    }

    /**
     * @param  list<self>|null  $cases
     * @return list<array{value: string, label: string}>
     */
    public static function options(?array $cases = null): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], $cases ?? self::cases());
    }
}
