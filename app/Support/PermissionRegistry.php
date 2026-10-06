<?php

namespace App\Support;

use App\Enums\RoleName;

/**
 * The single list of permissions, grouped by area, and which role has which.
 *
 * Permission names follow "area.action", for example `students.create`.
 * The seeder (RolesAndPermissionsSeeder) writes this list to the database.
 * To add a permission: add it here, add it to the roles that should have it,
 * and run `php artisan db:seed --class=RolesAndPermissionsSeeder`.
 */
final class PermissionRegistry
{
    /**
     * Every permission, grouped by area, with what it allows.
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'users' => [
                'users.view' => 'See user accounts',
                'users.create' => 'Create user accounts',
                'users.update' => 'Edit accounts, assign roles, activate and deactivate',
                'users.delete' => 'Delete user accounts',
            ],
            'students' => [
                'students.view' => 'See student profiles',
                'students.create' => 'Add students',
                'students.update' => 'Edit students and change their status',
                'students.delete' => 'Delete students',
            ],
            'teachers' => [
                'teachers.view' => 'See teacher profiles',
                'teachers.create' => 'Add teachers',
                'teachers.update' => 'Edit teachers and change their status',
                'teachers.delete' => 'Delete teachers',
            ],
            'academic years' => [
                'academic_years.view' => 'See academic years',
                'academic_years.create' => 'Add academic years',
                'academic_years.update' => 'Edit academic years and set the active one',
                'academic_years.delete' => 'Delete academic years',
            ],
            'academic terms' => [
                'academic_terms.view' => 'See terms and semesters',
                'academic_terms.create' => 'Add terms',
                'academic_terms.update' => 'Edit terms and set the active one',
                'academic_terms.delete' => 'Delete terms',
            ],
            'classes' => [
                'classes.view' => 'See classes',
                'classes.create' => 'Add classes',
                'classes.update' => 'Edit classes',
                'classes.delete' => 'Delete classes',
            ],
            'subjects' => [
                'subjects.view' => 'See subjects',
                'subjects.create' => 'Add subjects and assign them to classes',
                'subjects.update' => 'Edit subjects and class subjects',
                'subjects.delete' => 'Delete subjects',
            ],
            'enrollments' => [
                'enrollments.view' => 'See class rosters and enrollment history',
                'enrollments.create' => 'Enroll students in classes',
                'enrollments.delete' => 'Remove students from classes',
            ],
            'class teacher assignments' => [
                'class_assignments.view' => 'See which teachers are assigned to which classes',
                'class_assignments.create' => 'Assign teachers to classes',
                'class_assignments.delete' => 'End teacher assignments',
            ],
            'assignments' => [
                'assignments.view' => 'See assignments',
                'assignments.create' => 'Create assignments',
                'assignments.update' => 'Edit, publish and close assignments',
                'assignments.delete' => 'Delete assignments',
                'assignments.submit' => 'Hand in assignments',
            ],
            'examinations' => [
                'examinations.view' => 'See examinations',
                'examinations.create' => 'Create examinations',
                'examinations.update' => 'Edit, publish and close examinations',
                'examinations.delete' => 'Delete examinations',
                'examinations.take' => 'Take examinations',
            ],
            'questions' => [
                'questions.view' => 'See questions and answer keys',
                'questions.create' => 'Add questions',
                'questions.update' => 'Edit questions',
                'questions.delete' => 'Delete questions',
            ],
            'submissions' => [
                'submissions.view' => 'See student submissions',
                'submissions.grade' => 'Score submissions and give feedback',
            ],
            'grades' => [
                'grades.view' => 'See grades',
                'grades.manage' => 'Compute grades and manage grading settings',
            ],
            'attendance' => [
                'attendance.view' => 'See attendance',
                'attendance.record' => 'Record and change attendance',
            ],
            'awards' => [
                'awards.view' => 'See awards',
                'awards.create' => 'Create awards',
                'awards.update' => 'Edit awards',
                'awards.delete' => 'Delete awards',
                'awards.grant' => 'Give awards to students',
            ],
            'notifications' => [
                'notifications.view' => 'Receive and read notifications',
                'notifications.send' => 'Send announcements',
            ],
            'forum' => [
                'forum.view' => 'Read the forum',
                'forum.participate' => 'Start topics, reply and react',
                'forum.moderate' => 'Pin, lock and remove forum content; review reports',
            ],
            'faqs' => [
                'faqs.view' => 'Read the FAQ',
                'faqs.manage' => 'Write and publish FAQ entries',
            ],
            'reports' => [
                'reports.view' => 'Open reports',
                'reports.export' => 'Download reports as PDF, Excel or CSV',
            ],
            'audit' => [
                'audit.view' => 'See the audit trail of changes',
            ],
            'settings' => [
                'settings.manage' => 'Change system settings and role permissions',
            ],
        ];
    }

    /**
     * All permission names in one flat list.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::groups())));
    }

    /**
     * What a permission allows, for screens that list permissions.
     */
    public static function description(string $permission): string
    {
        return array_merge(...array_values(self::groups()))[$permission] ?? '';
    }

    /**
     * The permissions each role starts with.
     *
     * Teachers and students are additionally limited by policies to their own
     * classes and their own records; a permission here never means "all records".
     *
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        $all = self::all();

        return [
            RoleName::SuperAdmin->value => $all,

            // Everything except changing system settings and role permissions.
            RoleName::Admin->value => array_values(array_diff($all, ['settings.manage'])),

            RoleName::Hr->value => [
                'users.view',
                'teachers.view', 'teachers.create', 'teachers.update', 'teachers.delete',
                'academic_years.view', 'academic_terms.view',
                'classes.view', 'subjects.view',
                'class_assignments.view', 'class_assignments.create', 'class_assignments.delete',
                'notifications.view',
                'forum.view', 'forum.participate',
                'faqs.view',
                'reports.view', 'reports.export',
            ],

            RoleName::Teacher->value => [
                'students.view',
                'academic_years.view', 'academic_terms.view',
                'classes.view', 'subjects.view',
                'enrollments.view', 'class_assignments.view',
                'assignments.view', 'assignments.create', 'assignments.update', 'assignments.delete',
                'examinations.view', 'examinations.create', 'examinations.update', 'examinations.delete',
                'questions.view', 'questions.create', 'questions.update', 'questions.delete',
                'submissions.view', 'submissions.grade',
                'grades.view', 'grades.manage',
                'attendance.view', 'attendance.record',
                'awards.view', 'awards.grant',
                'notifications.view',
                'forum.view', 'forum.participate', 'forum.moderate',
                'faqs.view',
                'reports.view', 'reports.export',
            ],

            RoleName::Student->value => [
                'classes.view', 'subjects.view',
                'assignments.view', 'assignments.submit',
                'examinations.view', 'examinations.take',
                'grades.view',
                'attendance.view',
                'awards.view',
                'notifications.view',
                'forum.view', 'forum.participate',
                'faqs.view',
            ],
        ];
    }
}
