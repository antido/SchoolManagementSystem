# Task 20 — Persistent Notification System

## Objective
Implement database-backed Laravel notifications that persist across sessions.

## Requirements
- Use Laravel database notifications.
- Create notification classes for important events.
- Notification dropdown.
- Notification center.
- Unread count.
- Mark as read.
- Mark all as read.
- Link notifications to relevant pages.

## Notification Events
- New assignment.
- New examination.
- Assignment submitted.
- Assignment graded.
- Exam result available.
- New award.
- Teacher/class assignment.
- Announcements where applicable.

## Architecture
Business event:
```text
Event
  ↓
Notification
  ↓
Database
  ↓
Inertia shared props / notification endpoint
  ↓
React notification UI
```

## Acceptance Criteria
- Notifications survive logout/login.
- Unread state persists.
- Clicking a notification navigates to the related record.
