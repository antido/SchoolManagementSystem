# Task 13 — Learning Activities Foundation

## Objective
Create a common learning activity model that supports assignments, quizzes, examinations, questionnaires, and future activities.

## Table
Create `learning_activities` with:
- class_subject_id
- teacher_id
- title
- description
- type
- instructions
- start_at
- due_at
- duration_minutes
- total_points
- status

## Types
- assignment
- quiz
- examination
- questionnaire
- activity

## Requirements
- Teacher authorization.
- Activity lifecycle.
- Draft/published/closed states.
- Activity visibility rules.
- Due date handling.
- Reusable frontend activity components.

## Acceptance Criteria
- Teachers can create and manage activities for assigned class subjects.
- Students can only see activities available to them.
