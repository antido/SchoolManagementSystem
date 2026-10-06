# Task 07 — Academic Years and Terms

## Objective
Create academic period management.

## Requirements
Create:
- Academic years.
- Academic terms/semesters.

Academic year fields:
- name
- start_date
- end_date
- is_active

Academic term fields:
- academic_year_id
- name
- start_date
- end_date
- is_active

## Business Rules
- Prevent overlapping invalid periods where appropriate.
- Support active academic year/term.
- Preserve historical records.
- Do not delete academic periods that have dependent academic records without a deliberate archive strategy.

## Acceptance Criteria
- Admins can create and manage academic years and terms.
- One active period can be clearly identified.
- Existing academic records remain associated with their original period.
