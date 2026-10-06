# Task 17 — Scoring and Grading System

## Objective
Implement automatic scoring, manual grading, weighted components, and configurable grade scales.

## Tables
Consider:
- grading_systems
- grading_scales
- grading_components
- student_grades

## Requirements
- Automatic scoring for objective questions.
- Manual scoring for essays.
- Assignment scores.
- Exam scores.
- Weighted grade components.
- Configurable grading scale.
- Grade calculation service.
- Grade history.
- Remarks/pass-fail status.

## Example
```text
Assignments 20%
Quizzes     20%
Exams       40%
Projects    20%
```

## Acceptance Criteria
- Scores calculate consistently.
- Grade calculations are covered by automated tests.
- Grading configuration is not hard-coded into controllers.
