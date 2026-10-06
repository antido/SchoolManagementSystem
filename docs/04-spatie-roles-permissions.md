# Task 03 — Spatie Roles and Permissions

## Objective
Install and configure Spatie Laravel Permission and implement the application's authorization foundation.

## Roles
- `super-admin`
- `admin`
- `hr`
- `teacher`
- `student`

## Permission Groups
Create granular permissions for:
- users
- students
- teachers
- academic years
- academic terms
- classes
- subjects
- enrollments
- class teacher assignments
- assignments
- examinations
- questions
- submissions
- grades
- attendance
- awards
- notifications
- forum
- FAQs
- reports
- settings

Use permissions such as:
- `students.view`
- `students.create`
- `students.update`
- `students.delete`

Repeat the pattern where appropriate.

## Requirements
- Seed roles.
- Seed permissions.
- Assign initial permissions to roles.
- Use middleware and policies.
- Avoid hard-coded role checks throughout controllers.
- Add authorization checks to backend actions.
- Hide unauthorized frontend navigation/actions.

## Acceptance Criteria
- Roles and permissions exist in the database.
- Users can be assigned roles.
- Permissions can be checked with Spatie.
- Unauthorized backend requests return proper authorization errors.
- Frontend navigation respects permissions.
