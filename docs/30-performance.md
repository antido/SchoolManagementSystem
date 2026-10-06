# Task 29 — Performance Optimization

## Objective
Prepare the system for realistic student and teacher usage.

## Requirements
- Eager-load relationships.
- Avoid N+1 queries.
- Paginate large datasets.
- Add database indexes.
- Optimize grade/report queries.
- Cache stable reference data where appropriate.
- Use queued jobs for expensive report generation.
- Use queues for large notification batches.
- Monitor slow queries.

## Acceptance Criteria
- Large student lists remain responsive.
- Reports do not unnecessarily load complete datasets into memory.
- Notification batches do not block normal web requests.
