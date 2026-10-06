# School Management System

A school management and e-learning application: people, classes and subjects; assignments, quizzes and examinations; grades, attendance and awards; a forum, a FAQ, notifications, reports and analytics.

Built with Laravel 13, Inertia 2, React 18 with TypeScript, Tailwind CSS 4 and Spatie Laravel Permission.

## What it does

| Area | Highlights |
|---|---|
| People | User accounts with roles (super admin, admin, HR, teacher, student), student and teacher records, account activation |
| Academics | Academic years and terms, classes, subjects, class subjects (a subject taught to a class in a term), enrollment, teacher assignments |
| Learning | Activities (assignment, quiz, examination, questionnaire, activity) with a draft, published and closed lifecycle; submissions with files and late rules; a question bank with six question types; timed examinations enforced by the server |
| Results | Automatic and manual scoring, weighted grading systems and scales, grade history, attendance with summaries, awards |
| Community | Forum with reactions, reports and moderation; a searchable FAQ |
| Insight | Ten reports exported as PDF, Excel or CSV; an analytics page with charts; dashboards for each role |
| Platform | Database notifications (optionally realtime over Reverb), an audit trail, private file storage, queues and scheduling |

## Quick start (development)

Requirements: PHP 8.3+ with `pdo_mysql`, `fileinfo`, `gd`, `zip` and `mbstring`; Composer; Node 22+; MySQL 8 (or MariaDB 10.6+).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

# Set DB_* in .env, then create the tables and the demo school:
php artisan migrate --seed

composer dev          # web server, queue worker, Vite and logs together
```

Open the address `composer dev` prints and sign in with a demo account (password `password` for all):

| Role | Email |
|---|---|
| Super admin | superadmin@school.test |
| Admin | admin@school.test |
| HR | hr@school.test |
| Teacher | teacher@school.test |
| Student | student@school.test |

Demo data is only created outside production.

## Documentation

| Guide | For |
|---|---|
| [Installation and configuration](docs/guide/installation.md) | Setting up, environment settings, database, seeding, queues, storage, realtime |
| [Architecture and conventions](docs/guide/architecture.md) | How the code is organised and how to add to it |
| [Business rules](docs/guide/business-rules.md) | Roles and permissions, who sees what, activities, exams, grading, attendance |
| [Testing](docs/guide/testing.md) | Running and writing tests, CI |
| [Deployment and production checklist](docs/guide/deployment.md) | Going live and keeping it running |

The `docs/NN-*.md` files are the original build tasks, kept for reference.

## Common commands

```bash
php artisan test                  # the test suite
vendor/bin/pint                   # format PHP code
npm run build                     # type-check (tsc) and build the frontend
php artisan migrate:fresh --seed  # rebuild the database with demo data (erases everything)
```
