# Installation and configuration

## Requirements

- PHP 8.3 or newer with the `pdo_mysql`, `fileinfo`, `gd`, `zip`, `mbstring` and `dom` extensions (`pdo_sqlite` too, for the tests)
- Composer 2
- Node 22 or newer, with npm
- MySQL 8 or MariaDB 10.6+ (the tests use an in-memory SQLite database)

## First-time setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Edit `.env` (see below), then create the database tables:

```bash
php artisan migrate          # tables only
php artisan migrate --seed   # tables, roles, grading defaults and (outside production) the demo school
```

Start everything for development with:

```bash
composer dev
```

This runs the web server, a queue worker, the Vite dev server and the log viewer side by side. Notifications are sent by the queue worker, so keep it running (see [Queues](#queues)).

## Environment settings

The standard Laravel settings (`APP_*`, `DB_*`, `MAIL_*`, `CACHE_STORE`, `SESSION_*`) work as usual. The settings specific to this application live in `config/school.php` and can be set in `.env`:

| Setting | Default | What it does |
|---|---|---|
| `SCHOOL_REGISTRATION_ENABLED` | `false` | Lets people create their own account at `/register`. Normally the school creates accounts. Self-registered accounts get no role until an administrator gives one. |
| `SCHOOL_FAQ_PUBLIC` | `true` | Lets guests read the FAQ at `/faq`. When off, readers must sign in and have `faqs.view`. |
| `SCHOOL_ATTACHMENTS_DISK` | `local` | The filesystem disk for uploaded files. It must be private (not web-accessible). |
| `SCHOOL_ATTACHMENT_MAX_KB` | `10240` | The largest uploaded file, in kilobytes. Allowed types and the per-record limit (5 files) are in `config/school.php`. |
| `SCHOOL_AUDIT_STORE_IP` | `false` | Records IP addresses in the audit trail. IP addresses are personal data; only turn this on if the school's privacy policy covers it. |
| `SCHOOL_SLOW_QUERY_MS` | `500` | Database queries slower than this are written to the log. `0` turns it off. |
| `SCHOOL_REALTIME` | `false` | Pushes new notifications to open pages over Laravel Reverb. See [Realtime notifications](#realtime-notifications-optional). |

## Database

```bash
php artisan migrate                   # apply new migrations
php artisan migrate:fresh --seed      # rebuild from scratch with demo data (erases all data)
```

MySQL limits index names to 64 characters; long composite indexes are given short names in their migrations (for example `activity_attempts_student_attempt_unique`).

## Seeders

| Seeder | Runs in | Creates |
|---|---|---|
| `RolesAndPermissionsSeeder` | every environment | The roles and permissions defined in `app/Support/PermissionRegistry.php`. Run it again after changing that file. |
| `GradingSeeder` | every environment | The default grading scale (75% passing) and grading system (assignments 20%, quizzes 20%, examinations 40%, projects and other work 20%), unless defaults exist. |
| `DemoAccountsSeeder` | not production | One account per role, password `password`. |
| `DemoSchoolSeeder` | not production | A complete demo school: one year with two terms, four classes of about 25 students, six subjects, ten teachers, activities at every stage, quiz attempts, six weeks of attendance, grades, awards, a forum and a FAQ. It uses a fixed random seed, so it produces the same school each time, and it does nothing if the database already has academic years. |

`php artisan db:seed` runs them in that order. A single one can be run with `php artisan db:seed --class=GradingSeeder`.

## Queues

Notifications (from events such as an activity being published) and announcements are sent on the queue, so large classes and school-wide announcements do not slow down requests. Event notifications are only queued after the change that caused them is saved.

- Development: `QUEUE_CONNECTION=database` with the worker started by `composer dev`. Without a worker, notifications wait in the `jobs` table.
- Tests: `QUEUE_CONNECTION=sync` (set in `phpunit.xml`), so jobs run immediately.
- Production: run a worker under a process manager; see the [deployment guide](deployment.md).

## Scheduler

`routes/console.php` schedules `attempts:close-expired` every minute: it hands in examination attempts whose time ran out while the student was away. Attempts are also closed whenever they are next opened, so the scheduler only keeps results and lists up to date. In production add the usual cron entry (`* * * * * php artisan schedule:run`).

## Storage

Uploaded files (activity materials, student work, forum files, award pictures, profile pictures) are stored on the private `local` disk under `storage/app/private`, in folders such as `assignments/`, `submissions/`, `forum/`, `awards/` and `avatars/`. File names are generated; the name the user gave is kept only for display and downloads. Files are never served directly: every download goes through the application, which checks that the person may see the record the file belongs to.

No `storage:link` is needed. The web server user needs write access to `storage/` and `bootstrap/cache/`.

## Realtime notifications (optional)

Notifications are always stored in the database; pages check for new ones every minute. To push them instantly instead:

1. Set `BROADCAST_CONNECTION=reverb` and `SCHOOL_REALTIME=true`, and fill in the `REVERB_*` keys (`php artisan reverb:install` can generate them).
2. Rebuild the frontend so the `VITE_REVERB_*` values are included: `npm run build`.
3. Run the Reverb server: `php artisan reverb:start`.

If the Reverb server is not reachable, pages fall back to checking every minute. Each person can only listen on their own private channel (`routes/channels.php`).

## Mail

Password reset links and account emails use the `MAIL_*` settings. In development `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log`.
