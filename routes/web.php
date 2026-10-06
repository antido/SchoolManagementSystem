<?php

use App\Http\Controllers\AcademicTermController;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\ClassSubjectController;
use App\Http\Controllers\ClassTeacherController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\ForumModerationController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\GradingSettingsController;
use App\Http\Controllers\LearningActivityController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * Pages anyone can open.
 */
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// The FAQ. Guests may read it when config('school.faq_public') is on; the controller checks.
Route::get('faq', [FaqController::class, 'index'])->name('faqs.index');

/*
 * Pages for signed-in users. Each controller action also checks a policy,
 * so these route groups are a first line of defence, not the only one.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/analytics', AnalyticsController::class)->name('analytics');

    // User accounts.
    Route::middleware('permission:users.view')->group(function () {
        Route::resource('users', UserController::class);
        Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
        Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])
            ->middleware('throttle:6,1')
            ->name('users.password-reset');
    });

    // Students. A student may open their own profile, so the policy (not
    // route middleware) decides who can see each page.
    Route::resource('students', StudentController::class);
    Route::patch('students/{student}/status', [StudentController::class, 'updateStatus'])->name('students.status');

    // Teachers. Managed by administrators and HR; a teacher may open their own profile.
    Route::resource('teachers', TeacherController::class);
    Route::patch('teachers/{teacher}/status', [TeacherController::class, 'updateStatus'])->name('teachers.status');

    // Academic years and their terms. Terms are managed on the year's page.
    Route::middleware('permission:academic_years.view')->group(function () {
        Route::resource('academic-years', AcademicYearController::class);
        Route::patch('academic-years/{academic_year}/activate', [AcademicYearController::class, 'activate'])
            ->name('academic-years.activate');
        Route::post('academic-years/{academic_year}/terms', [AcademicTermController::class, 'store'])
            ->name('academic-terms.store');
        Route::put('academic-terms/{academic_term}', [AcademicTermController::class, 'update'])
            ->name('academic-terms.update');
        Route::patch('academic-terms/{academic_term}/activate', [AcademicTermController::class, 'activate'])
            ->name('academic-terms.activate');
        Route::delete('academic-terms/{academic_term}', [AcademicTermController::class, 'destroy'])
            ->name('academic-terms.destroy');
    });

    // Classes ("class" is reserved in PHP, so the route parameter is schoolClass).
    Route::resource('classes', SchoolClassController::class)->parameters(['classes' => 'schoolClass']);

    // Enrollment: putting students into classes and taking them out again.
    Route::post('classes/{schoolClass}/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::patch('enrollments/{enrollment}/withdraw', [EnrollmentController::class, 'withdraw'])->name('enrollments.withdraw');

    // Teachers attached to a class as adviser or teacher.
    Route::post('classes/{schoolClass}/teachers', [ClassTeacherController::class, 'store'])->name('class-teachers.store');
    Route::patch('class-teachers/{classTeacher}/end', [ClassTeacherController::class, 'end'])->name('class-teachers.end');

    // Subjects, and the subjects each class takes per term.
    Route::middleware('permission:subjects.view')->group(function () {
        Route::resource('subjects', SubjectController::class);
        Route::post('classes/{schoolClass}/subjects', [ClassSubjectController::class, 'store'])->name('class-subjects.store');
        Route::get('class-subjects/{classSubject}', [ClassSubjectController::class, 'show'])->name('class-subjects.show');
        Route::put('class-subjects/{classSubject}', [ClassSubjectController::class, 'update'])->name('class-subjects.update');
        Route::delete('class-subjects/{classSubject}', [ClassSubjectController::class, 'destroy'])->name('class-subjects.destroy');
    });

    // Learning activities: assignments, quizzes, examinations and others.
    Route::resource('activities', LearningActivityController::class)->parameters(['activities' => 'activity']);
    Route::patch('activities/{activity}/status', [LearningActivityController::class, 'updateStatus'])->name('activities.status');

    // Questions on quizzes, examinations and questionnaires.
    Route::post('activities/{activity}/questions', [QuestionController::class, 'store'])->name('questions.store');
    Route::post('activities/{activity}/questions/copy', [QuestionController::class, 'copy'])->name('questions.copy');
    Route::put('questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    Route::patch('questions/{question}/move', [QuestionController::class, 'move'])->name('questions.move');
    Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');

    // Taking quizzes, examinations and questionnaires.
    Route::post('activities/{activity}/attempts', [AttemptController::class, 'store'])->name('attempts.store');
    Route::get('attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
    Route::put('attempts/{attempt}/answer', [AttemptController::class, 'saveAnswer'])
        ->middleware('throttle:120,1')
        ->name('attempts.answer');
    Route::post('attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
    Route::patch('attempts/{attempt}/grade', [AttemptController::class, 'grade'])->name('attempts.grade');

    // Students' work on activities.
    Route::post('activities/{activity}/submission', [SubmissionController::class, 'store'])->name('submissions.store');
    Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
    Route::patch('submissions/{submission}/grade', [SubmissionController::class, 'grade'])->name('submissions.grade');

    // Grades: computed per class subject; students see their own.
    Route::get('grades', [GradeController::class, 'index'])->name('grades.index');
    Route::post('class-subjects/{classSubject}/grades', [GradeController::class, 'compute'])->name('grades.compute');
    Route::patch('class-subjects/{classSubject}/grading-system', [GradeController::class, 'chooseSystem'])->name('grades.system');
    Route::get('class-subjects/{classSubject}/grades/{student}', [GradeController::class, 'student'])->name('grades.student');

    // School-wide grading settings: weights and scales.
    Route::prefix('settings/grading')->name('grading.')->controller(GradingSettingsController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('systems/create', 'createSystem')->name('systems.create');
        Route::post('systems', 'storeSystem')->name('systems.store');
        Route::get('systems/{gradingSystem}/edit', 'editSystem')->name('systems.edit');
        Route::put('systems/{gradingSystem}', 'updateSystem')->name('systems.update');
        Route::delete('systems/{gradingSystem}', 'destroySystem')->name('systems.destroy');
        Route::get('scales/create', 'createScale')->name('scales.create');
        Route::post('scales', 'storeScale')->name('scales.store');
        Route::get('scales/{gradingScale}/edit', 'editScale')->name('scales.edit');
        Route::put('scales/{gradingScale}', 'updateScale')->name('scales.update');
        Route::delete('scales/{gradingScale}', 'destroyScale')->name('scales.destroy');
    });

    // Attendance: sheets per class (and subject) and day, summaries, and each student's record.
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('classes/{schoolClass}/attendance', [AttendanceController::class, 'sheet'])->name('attendance.sheet');
    Route::put('classes/{schoolClass}/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('classes/{schoolClass}/attendance/summary', [AttendanceController::class, 'summary'])->name('attendance.summary');
    Route::get('students/{student}/attendance', [AttendanceController::class, 'student'])->name('attendance.student');

    // Awards, and giving them to students. Given awards are revoked, never deleted.
    Route::resource('awards', AwardController::class);
    Route::post('awards/{award}/grants', [AwardController::class, 'grant'])->name('awards.grant');
    Route::patch('student-awards/{studentAward}/revoke', [AwardController::class, 'revoke'])->name('awards.revoke');

    // Notifications (each person's own) and announcements.
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('notifications/announce', [NotificationController::class, 'createAnnouncement'])->name('notifications.announce');
    Route::post('notifications/announce', [NotificationController::class, 'sendAnnouncement'])->name('notifications.send');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // The discussion forum. Removed topics stay reachable for moderators.
    Route::prefix('forum')->name('forum.')->group(function () {
        Route::get('/', [ForumController::class, 'index'])->name('index');
        Route::get('reports', [ForumModerationController::class, 'reports'])->name('reports.index');
        Route::patch('reports/{report}', [ForumModerationController::class, 'resolve'])->name('reports.resolve');
        Route::post('categories', [ForumModerationController::class, 'storeCategory'])->name('categories.store');
        Route::put('categories/{category}', [ForumModerationController::class, 'updateCategory'])->name('categories.update');
        Route::delete('categories/{category}', [ForumModerationController::class, 'destroyCategory'])->name('categories.destroy');
        Route::get('categories/{category}', [ForumController::class, 'category'])->name('categories.show');
        Route::get('categories/{category}/topics/create', [ForumController::class, 'createTopic'])->name('topics.create');
        Route::post('categories/{category}/topics', [ForumController::class, 'storeTopic'])
            ->middleware('throttle:10,1')
            ->name('topics.store');
        Route::get('topics/{topic}', [ForumController::class, 'showTopic'])->withTrashed()->name('topics.show');
        Route::patch('topics/{topic}', [ForumController::class, 'updateTopic'])->name('topics.update');
        Route::delete('topics/{topic}', [ForumController::class, 'destroyTopic'])->name('topics.destroy');
        Route::post('topics/{topic}/replies', [ForumController::class, 'reply'])
            ->middleware('throttle:20,1')
            ->name('topics.reply');
        Route::put('posts/{post}', [ForumController::class, 'updatePost'])->name('posts.update');
        Route::delete('posts/{post}', [ForumController::class, 'destroyPost'])->name('posts.destroy');
        Route::post('posts/{post}/reactions', [ForumController::class, 'react'])->name('posts.react');
        Route::post('posts/{post}/reports', [ForumController::class, 'report'])->name('posts.report');
    });

    // Managing the FAQ.
    Route::prefix('faq/manage')->name('faqs.')->controller(FaqController::class)->group(function () {
        Route::get('/', 'manage')->name('manage');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{faq}/edit', 'edit')->name('edit');
        Route::put('{faq}', 'update')->name('update');
        Route::delete('{faq}', 'destroy')->name('destroy');
        Route::patch('{faq}/publish', 'togglePublished')->name('publish');
        Route::patch('{faq}/move', 'move')->name('move');
        Route::post('categories', 'storeCategory')->name('categories.store');
        Route::put('categories/{category}', 'updateCategory')->name('categories.update');
        Route::delete('categories/{category}', 'destroyCategory')->name('categories.destroy');
    });

    // Reports, previewed on screen and exported as CSV, Excel or PDF.
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('reports/{report}/export/{format}', [ReportController::class, 'export'])
        ->middleware('throttle:20,1')
        ->name('reports.export');

    // The audit trail (read-only).
    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

    // Downloading uploaded files. Each download checks access to the record the file belongs to.
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::get('/avatars/{user}', [ProfileController::class, 'showAvatar'])->name('avatars.show');
});

require __DIR__.'/auth.php';
