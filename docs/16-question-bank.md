# Task 15 — Question Bank and Questionnaires

## Objective
Create reusable questions and questionnaire functionality.

## Question Types
- multiple_choice
- true_false
- multiple_select
- identification
- short_answer
- essay

## Tables
`questions`:
- learning_activity_id
- question
- question_type
- points
- order

`question_options`:
- question_id
- option_text
- is_correct

## Requirements
- Question CRUD within an activity.
- Question ordering.
- Options for objective questions.
- Correct answer configuration.
- Validation based on question type.
- Reusable question bank if practical.

## Acceptance Criteria
- Teacher can construct a questionnaire/exam.
- Question types behave correctly.
- Objective questions can identify correct answers.
