# Task 01 — Project Foundation

## Objective
Create the initial Laravel application foundation for the e-learning platform using Laravel, Inertia.js, React, TypeScript, and Tailwind CSS.

## Requirements
- Create/configure the Laravel application.
- Configure MySQL.
- Configure Inertia.js with React and TypeScript.
- Configure Vite.
- Configure Tailwind CSS.
- Establish a clean frontend structure under `resources/js`.
- Establish backend folders for Services, Actions, Policies, Notifications, Enums, and Form Requests.
- Configure environment variables.
- Configure storage and public storage linking.
- Create a base application layout.
- Create a reusable authenticated dashboard layout.
- Configure consistent error handling and validation responses.
- Do not implement business modules yet.

## Suggested Frontend Structure
```text
resources/js/
├── Components/
├── Layouts/
├── Pages/
├── Hooks/
├── Types/
├── Utils/
└── Lib/
```

## Suggested Backend Structure
```text
app/
├── Actions/
├── Enums/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Middleware/
├── Models/
├── Notifications/
├── Policies/
├── Services/
└── Support/
```

## Acceptance Criteria
- Application starts successfully.
- Vite starts successfully.
- React + TypeScript page renders through Inertia.
- Tailwind styles work.
- Authenticated and guest layouts are available.
- Environment/database configuration is documented.
- Code structure is beginner-readable.
