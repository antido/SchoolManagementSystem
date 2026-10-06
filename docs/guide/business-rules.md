# Business rules

These are the rules the application enforces on the server. The code is the final word; each section names where its rules live.

## Roles and permissions

Permissions are named `area.action` (for example `students.create`). The full list, with which role starts with which, is in `app/Support/PermissionRegistry.php`. After changing it, run `php artisan db:seed --class=RolesAndPermissionsSeeder`.

| Role | Starts with |
|---|---|
| Super admin | Every permission |
| Admin | Every permission except `settings.manage` (system settings and role permissions) |
| HR | Teacher records and teacher-to-class assignments; read access to users, years, terms, classes and subjects; forum, FAQ and reports |
| Teacher | Activities, questions, submissions, grades, attendance and awards for their own classes; read access to students, classes and rosters; forum (including moderation), FAQ and reports |
| Student | Their own classes, activities, grades, attendance and awards; handing in work and taking exams; forum and FAQ |

A permission never means "all records" on its own. Policies in `app/Policies` also check that the record belongs to the person.

## Who sees what

- People who can edit classes (`classes.update`, normally admins) see every class, student and activity.
- Everyone else who has a teacher or student record, or the teacher or student role, is **limited to their own classes** (`User::isLimitedToOwnClasses()`):
  - teachers see classes they are assigned to, and the class subjects they teach;
  - students see classes they are enrolled in, and only published activities.
- An account with the teacher or student role but no teacher or student record sees no classes until one is linked.
- Teachers change only the activities, attendance and grades of class subjects they manage (`ClassSubject::managedBy()`).
- Students see only their own submissions, attempts, grades, attendance and awards.

## Activities

Types: assignment, quiz, examination, questionnaire and activity. Quizzes and examinations use the `examinations.*` permissions; the others use `assignments.*`.

| Type | Students… |
|---|---|
| Assignment, activity | hand in written work and files |
| Quiz, examination, questionnaire | answer questions in a timed or untimed attempt |

Lifecycle: **draft → published → closed**, and a closed activity can be published again to reopen it. Students never see drafts. Publishing notifies the class's students.

## Handing in work

- Work is accepted while the activity is published, has started (if it has a start time), and is not past due, unless late work is allowed.
- Work handed in after the due date is marked **late**. A student can change their work until it is graded; **graded work is final**.
- Each submission has up to 5 files (`config/school.php`). Statuses shown to teachers: pending, submitted, late, graded and missing (nothing handed in after the due date).

## Questions and scoring

Six question types. Multiple-choice, true-or-false, multiple-select and identification answers are scored automatically; essays and other open answers are scored by the teacher.

- **Multiple select** earns the points only when exactly the correct options are chosen.
- **Identification** ignores capitals and extra spaces.
- Once any student has started an attempt, questions are **locked** and can no longer be edited, added or removed.

## Timed attempts (quizzes and examinations)

- The time limit is enforced by the server: an attempt ends at the earlier of the start time plus the duration and the activity's due date.
- Answers are saved as the student goes. When time runs out, the page hands the attempt in; if it cannot (the student closed the page), the server hands it in after a **15-second grace period**, either when the attempt is next opened or by the `attempts:close-expired` command that runs every minute.
- Teachers set the number of attempts allowed (1 to 10) and whether questions and options are shuffled. The **best graded attempt** counts towards the grade.
- Students see which questions they got right only after the activity is closed.

## Grading

Configured under Grading settings (`grades.manage`). The defaults (`GradingSeeder`):

| Component | Weight | Activity types |
|---|---|---|
| Assignments | 20% | assignment |
| Quizzes | 20% | quiz |
| Examinations | 40% | examination |
| Projects and other work | 20% | activity, questionnaire |

Scale: 75% passes. Descriptors: Outstanding (90+), Very satisfactory (85+), Satisfactory (80+), Fairly satisfactory (75+), Did not meet expectations (below 75).

How a grade is worked out (`GradeCalculator` and `GradeService`):

1. Each component's percentage is its points earned over its points possible.
2. Work not handed in counts as 0 once the activity is past due or closed. Work still to be graded is left out until it is graded.
3. Components with no graded work are left out, and the remaining weights are scaled to add up to 100%.
4. The result is rounded only at the end.

Recomputing a grade keeps the previous one in the student's grade history.

## Attendance

Statuses: present, late, absent and excused.

**Attendance rate = (present + late) ÷ (present + late + absent).** Excused days are left out entirely. Attendance is taken for a whole class (daily) or for one class subject, with at most one record per student, class, subject and day.

## Awards

Awards are defined by admins and given to students by people with `awards.grant` (teachers only for students in their classes). A given award is never deleted: it is **revoked** with a reason, and stays in the student's history. An award that has been given cannot be deleted, and a student with history (enrollments, submissions, attempts, attendance, grades or awards) cannot be deleted.

## Forum

- Anyone with `forum.participate` can start topics, reply and react. Authors can edit their posts while the topic is unlocked, and delete their replies (not a topic's opening post).
- Participants can report someone else's post, once each. Moderators (`forum.moderate`: teachers and admins) pin, lock and remove topics and posts, and review reports.
- Locked topics take no new replies. Topic authors are notified of replies.

## Files

- Allowed types: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv, jpg, jpeg, png, gif and zip, up to `SCHOOL_ATTACHMENT_MAX_KB` (10 MB by default).
- A file's content must match its extension (`FileContentMatchesExtension`), so a renamed program is rejected.
- Files are private: each download checks that the person may see the record the file belongs to.

## Accounts

- Deactivated accounts cannot sign in.
- Administrators, and people with a student or teacher record, cannot delete their own account; an administrator removes it instead.
- Sign-in, password reset and password confirmation are rate-limited.

## Audit trail

Changes to people, enrollments, assignments, activities, attendance, grades, awards and forum moderation are recorded with who made them and the values before and after. Entries cannot be changed or deleted. Only `audit.view` (admins) can read them, and IP addresses are stored only when `SCHOOL_AUDIT_STORE_IP` is on.
