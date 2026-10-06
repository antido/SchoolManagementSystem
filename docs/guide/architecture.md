# Architecture and conventions

## The shape of a request

```
Route (routes/web.php, inside the auth + verified group)
  -> Controller        thin: authorise, call a service, choose the page
  -> Form Request      validation, and authorize() for the record involved
  -> Service           the business rules and database writes
  -> Model             relationships, query scopes, small facts about itself
  -> Policy            who may do what to which record
  -> Inertia page      a React + TypeScript component in resources/js/Pages
```

Every action checks permission on the server: route middleware (`permission:...`) is a first line, and each controller action or form request also checks a policy. Hiding a button in React is never the only protection.

## Backend conventions

**Controllers** (`app/Http/Controllers`) stay short. They call `$this->authorize()` (or rely on a form request's `authorize()`), pass validated data to a service, and return an Inertia page or a redirect with a flash message (`success` or `error`). Data for pages is shaped into plain arrays (often by a model's `summary()` method) so pages never receive whole models.

**Form requests** (`app/Http/Requests`) hold validation. Rules that depend on several fields go in `after()`. `authorize()` checks the specific record (for example, that a teacher manages this class subject), not just a permission.

**Services** (`app/Services`) hold the business logic: `LearningActivityService`, `SubmissionService`, `AttemptService`, `GradeService`, `AttendanceService`, `AwardService`, `ForumService` and others. A service method does one complete thing (start an attempt, record a day's attendance), uses a transaction when it writes several rows, and fires events for things other parts of the system react to. There are no separate "Action" classes: a service method plays that role.

**Models** (`app/Models`) declare `#[Fillable]` for fields set from validated input; fields with rules attached (statuses, scores, who did something) are set explicitly in services instead. Common query shapes are scopes, for example `SchoolClass::visibleTo($user)`, `ClassSubject::managedBy($user)` and `LearningActivity::upcoming()`. Lazy loading and silently discarded attributes throw errors outside production, so missing `with()` calls show up during development.

**Policies** (`app/Policies`) combine a permission with ownership. Permissions are named `area.action` and listed, with the roles that have them, in `app/Support/PermissionRegistry.php`. Teachers and students are additionally limited to their own classes through `User::isLimitedToOwnClasses()` and the `visibleTo` scopes.

**Enums** (`app/Enums`) hold every fixed list (statuses, types, roles) with labels and the rules that belong to them, for example `ActivityStatus::canChangeTo()` and `ActivityType::permission()`.

**Settings** that may differ per school are in `config/school.php` and the database (grading systems and scales), never hard-coded in controllers.

## Frontend conventions

- **Pages** live in `resources/js/Pages/<Area>/<Page>.tsx` and wrap their content in `AuthenticatedLayout` (or `GuestLayout` and `PublicLayout`). A page receives everything it shows as props; parts used only by one page go in a `Partials/` folder beside it.
- **Types** for props are in `resources/js/Types` (`academics.d.ts`, `activities.d.ts`, `grades.d.ts` and so on). `PageProps` describes what every page receives: the app name, upload limits, the signed-in user, their permissions and unread notification count, and flash messages.
- **Reusable components** are in `resources/js/Components`: `Button`, `Card`, `FormField` (label, hint and error wired for screen readers), `TextInput`, `SelectInput`, `Table`, `FilterBar`, `Pagination`, `PageHeader` with breadcrumbs, `EmptyState`, `ConfirmDialog`, `StatusBadge`, `FileInput`, `AttachmentList` and the SVG charts in `Charts.tsx`. Use them rather than styling elements directly, so pages stay consistent.
- **Design tokens** (colours, fonts) are defined once in `resources/css/app.css` and used through Tailwind classes such as `bg-surface`, `text-muted` and `border-line`.
- **Permissions in the interface**: `usePermissions()` gives `can()` and `hasRole()` for showing or hiding controls; pages usually receive a `can` object from the server instead, which also accounts for the specific record.
- **Forms** use Inertia's `useForm`. Forms with files send `forceFormData: true`, and edits with files are sent as POST with `_method: 'put'`. Dates are converted to ISO strings with the browser's time zone (`fromDateTimeInput`), so the server stores the moment the person meant.
- **Requests that should not reload the page** (exam autosave, the notification menu) use the small `fetch` helpers in `resources/js/Lib/http.ts`.

## Notifications

```
Something happens        a service fires an event (App\Events\ActivityPublished, WorkSubmitted, ...)
   -> listener           App\Listeners\SendSchoolNotifications (queued, after the transaction commits)
   -> notification       App\Notifications\* (all extend SchoolNotification)
   -> database           the notifications table: title, body, url, icon
   -> page               the unread count is shared with every page; the bell loads the latest on demand
   -> (optional)         broadcast over Reverb when SCHOOL_REALTIME is on
```

Notifications are sent for: a new activity (to the class's students), work handed in (to the teacher), work graded and exam results (to the student), awards (to the student), teacher assignments (to the teacher), forum replies (to the topic's author) and announcements. Announcements go out in batches from the `SendAnnouncement` job. Opening a notification marks it read and goes to its page; only paths inside the application are followed.

## Audit trail

Models that use the `App\Models\Concerns\Auditable` trait record their changes in `audit_logs`: who, what, which record, and the values before and after. Each model can rename itself in the trail (`auditName()`), choose which events count (`auditEvents()`) and leave out noisy fields (`auditIgnore()`). Hidden attributes such as passwords are never recorded. Code that changes several records at once can call `AuditLogger::record()` directly. Entries cannot be changed or deleted through the application, and only people with `audit.view` can read them.

## Reports

Each report is a class in `app/Reports` that extends `Report` (or `ScopedReport` for school records), declares its filters and permission checks, and returns a `ReportResult` (title, columns, rows, summary lines). `ReportExporter` turns any result into CSV, Excel or PDF, and `ReportRegistry` lists the reports. To add a report, write the class and add it to the registry; no controller changes are needed. Text that looks like a spreadsheet formula is never executed by exported files.

## Files

`AttachmentService` stores, serves and removes files for any model with an `attachments()` relation; the rules for what may be uploaded are `AttachmentService::rules()` and `imageRules()`. Downloads go through `AttachmentController`, whose policy asks the parent record's own policy whether the person may see it. See [installation](installation.md#storage) for where files are kept.
