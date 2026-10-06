# Task 30 — Realtime Notifications

## Objective
Optionally extend persistent notifications with realtime delivery using Laravel broadcasting/Reverb.

## Requirements
- Keep database notification as the source of persistence.
- Broadcast important notifications.
- Update React notification count without page refresh.
- Handle reconnects gracefully.
- Do not depend solely on WebSockets for notification persistence.

## Acceptance Criteria
- New notifications can appear without refresh when realtime services are available.
- Notifications remain available after reconnecting or logging in later.
