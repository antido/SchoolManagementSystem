# Testing

## Running the tests

```bash
php artisan test                              # everything
php artisan test --filter=SubmissionTest      # one file
php artisan test --filter=test_late_work      # tests whose names match
php artisan test --coverage                   # with coverage (needs pcov or Xdebug)
vendor/bin/pint                               # fix code style
vendor/bin/pint --test                        # check code style without changing files
npm run build                                 # type-check the React code and build it
```

The tests use an in-memory SQLite database, the `array` cache, mail and session stores, and the `sync` queue (all set in `phpunit.xml`), so they do not touch the development database, send email or need a queue worker. They also do not need `npm run build`: `tests/TestCase.php` turns Vite off, because the tests check what the server sends, not the compiled JavaScript.

Every test starts with the roles and permissions from `RolesAndPermissionsSeeder` already seeded.

## What is where

| Folder | Holds |
|---|---|
| `tests/Unit` | Rules that need no HTTP request: `GradeCalculatorTest`, `AnswerScorerTest`, `BusinessRulesTest` (the activity lifecycle, what each activity type allows, which questions need a teacher, attendance rates) |
| `tests/Feature` | One file per module (`StudentManagementTest`, `SubmissionTest`, `ExaminationTest`, `GradeTest`, `ForumTest`, `ReportTest` and so on), each going through the real routes, policies and database |
| `tests/Feature/SecurityTest.php` | Cross-cutting checks: changing IDs in the address, answer keys staying hidden, rate limits, and that every route requires sign-in except the public ones |
| `tests/Feature/PerformanceTest.php` | That notifications and announcements go through the queue, that slow queries are logged, and that long lists do not cost a query per row |
| `tests/Feature/DemoDataTest.php` | That the demo school seeds completely, only once, and that every demo account can sign in and see its own work |

## Writing tests

- Use the model factories (`Student::factory()`, `ClassSubject::factory()`, `LearningActivity::factory()`) and give explicit values for anything the test relies on. Random factory data has caused flaky tests before (for example a search that happened to match a random name).
- Act as a real role: create a user, `assignRole(RoleName::Teacher)`, link a `Teacher` or `Student` record if the rule depends on one, and call the route with `actingAs()`.
- Test both sides of every permission rule: the person who may, and the person who may not (another class's teacher, a classmate).
- For pages, assert the Inertia component and props with `assertInertia()`; for actions, assert the redirect, the flash message and the database (`assertDatabaseHas`).
- For uploads, call `Storage::fake('local')`. When the test depends on the file's real content (type checks), write a real temporary file rather than `UploadedFile::fake()`, which guesses the type from the name.
- For times (due dates, exam timers), use `$this->travelTo()` rather than waiting.
- The tests' `array` cache keeps values as they are, but real cache stores serialize them and do not restore objects (`cache.serializable_classes` is off). Cache plain arrays, and test cached pages with `config(['cache.default' => 'database'])` as `AnalyticsTest` does.
- New features need tests in the same change; run `vendor/bin/pint` before committing.

### On Windows

Antivirus software can block test files that look harmful (for example a PHP web shell used to test upload checks), which shows up as odd `finfo` errors. The suite uses harmless content for these cases; keep it that way when adding tests.

## Continuous integration

`.github/workflows/tests.yml` runs on every push and pull request:

1. `composer install` and `npm ci`
2. `vendor/bin/pint --test` (code style)
3. `npm run build` (TypeScript type check and frontend build)
4. `php artisan test --coverage` on PHP 8.4

The coverage figure is reported but no minimum is enforced yet. Once a baseline is known, add `--min=<percent>` to fail the build when coverage drops.
