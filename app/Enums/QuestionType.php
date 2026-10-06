<?php

namespace App\Enums;

/**
 * The kinds of question on a quiz, examination or questionnaire.
 */
enum QuestionType: string
{
    /** Pick one of several options. */
    case MultipleChoice = 'multiple_choice';

    /** True or false. */
    case TrueFalse = 'true_false';

    /** Pick every correct option. */
    case MultipleSelect = 'multiple_select';

    /** A word or phrase, checked against the accepted answers (ignoring case and extra spaces). */
    case Identification = 'identification';

    /** A sentence or two, scored by the teacher. */
    case ShortAnswer = 'short_answer';

    /** A longer written answer, scored by the teacher. */
    case Essay = 'essay';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple choice',
            self::TrueFalse => 'True or false',
            self::MultipleSelect => 'Multiple select',
            self::Identification => 'Identification',
            self::ShortAnswer => 'Short answer',
            self::Essay => 'Essay',
        };
    }

    /**
     * Whether students choose from options (as opposed to typing an answer).
     */
    public function hasChoices(): bool
    {
        return in_array($this, [self::MultipleChoice, self::TrueFalse, self::MultipleSelect], true);
    }

    /**
     * Whether the system can score answers itself. The others are scored by the teacher.
     */
    public function isAutoScored(): bool
    {
        return $this !== self::ShortAnswer && $this !== self::Essay;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
