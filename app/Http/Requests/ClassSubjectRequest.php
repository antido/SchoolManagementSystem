<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Enums\TeacherStatus;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a subject to a class (with its term and teacher), or changing an
 * existing class subject's teacher and status.
 */
class ClassSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classSubject = $this->route('classSubject');

        return $classSubject
            ? $this->user()->can('update', $classSubject)
            : $this->user()->can('create', ClassSubject::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teacherRule = [
            'nullable', 'integer',
            // Only teachers who are working can be given classes.
            Rule::exists('teachers', 'id')->whereIn('status', [TeacherStatus::Active->value, TeacherStatus::OnLeave->value]),
        ];

        // When editing, only the teacher and status can change. Subject, class
        // and term define the row; to change them, remove it and add a new one.
        if ($this->route('classSubject')) {
            return [
                'teacher_id' => $teacherRule,
                'status' => ['required', Rule::enum(ActiveStatus::class)],
            ];
        }

        /** @var SchoolClass $class */
        $class = $this->route('schoolClass');

        return [
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('status', ActiveStatus::Active->value),
                // The same subject cannot be added to the same class twice in one term.
                Rule::unique('class_subjects', 'subject_id')
                    ->where('school_class_id', $class->id)
                    ->where('academic_term_id', $this->input('academic_term_id')),
            ],
            // The term must belong to the class's own academic year.
            'academic_term_id' => [
                'required', 'integer',
                Rule::exists('academic_terms', 'id')->where('academic_year_id', $class->academic_year_id),
            ],
            'teacher_id' => $teacherRule,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.unique' => 'This class already takes this subject in that term.',
            'subject_id.exists' => 'Choose an active subject.',
            'academic_term_id.exists' => 'Choose a term from this class\'s academic year.',
            'teacher_id.exists' => 'Choose an active teacher.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subject_id' => 'subject',
            'academic_term_id' => 'term',
            'teacher_id' => 'teacher',
        ];
    }
}
