<?php

namespace Database\Seeders;

use App\Enums\ActivityStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ClassTeacherRole;
use App\Enums\QuestionType;
use App\Enums\SubmissionStatus;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ActivitySubmission;
use App\Models\ClassSubject;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\ForumCategory;
use App\Models\LearningActivity;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AcademicPeriodService;
use App\Services\AttemptService;
use App\Services\AttendanceService;
use App\Services\AwardService;
use App\Services\ClassTeacherService;
use App\Services\EnrollmentService;
use App\Services\ForumService;
use App\Services\GradeService;
use App\Services\LearningActivityService;
use App\Services\QuestionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A small, believable school for development and demos: one academic year
 * with two terms, four classes of about 25 students, six subjects, teachers,
 * activities at every stage (draft, open, past due, graded), quiz attempts,
 * six weeks of attendance, computed grades, awards, a lively forum and a FAQ.
 *
 * The demo teacher and student accounts (DemoAccountsSeeder) are given a
 * teacher and a student record, so signing in as them shows real work.
 *
 * Data is generated from a fixed random seed, so every run produces the
 * same school. Run once on an empty database:
 *     php artisan db:seed --class=DemoSchoolSeeder
 */
class DemoSchoolSeeder extends Seeder
{
    private const STUDENTS_PER_CLASS = 25;

    private const SUBJECTS = [
        'MATH' => 'Mathematics',
        'SCI' => 'Science',
        'ENG' => 'English',
        'FIL' => 'Filipino',
        'AP' => 'Araling Panlipunan',
        'MAPEH' => 'MAPEH',
    ];

    private const CLASSES = [
        ['Grade 7', 'Rizal'],
        ['Grade 7', 'Mabini'],
        ['Grade 8', 'Bonifacio'],
        ['Grade 8', 'Luna'],
    ];

    public function __construct(
        private readonly AcademicPeriodService $periods,
        private readonly EnrollmentService $enrollments,
        private readonly ClassTeacherService $classTeachers,
        private readonly LearningActivityService $activities,
        private readonly QuestionService $questions,
        private readonly AttemptService $attempts,
        private readonly AttendanceService $attendance,
        private readonly GradeService $grades,
        private readonly AwardService $awards,
        private readonly ForumService $forum,
    ) {}

    public function run(): void
    {
        $demoTeacherUser = User::firstWhere('email', DemoAccountsSeeder::ACCOUNTS['teacher']['email']);
        $demoStudentUser = User::firstWhere('email', DemoAccountsSeeder::ACCOUNTS['student']['email']);
        $admin = User::firstWhere('email', DemoAccountsSeeder::ACCOUNTS['admin']['email']);

        if (! $demoTeacherUser || ! $demoStudentUser || ! $admin) {
            $this->command?->warn('Run DemoAccountsSeeder first.');

            return;
        }

        if (AcademicYear::query()->exists()) {
            $this->command?->warn('The school already has academic years; the demo school was not added.');

            return;
        }

        // The same school on every run.
        fake()->seed(20261006);
        mt_srand(20261006);

        // Notifications are created as things happen, not left in the queue.
        config(['queue.default' => 'sync']);

        [$year, $firstTerm] = $this->calendar();
        $subjects = $this->subjects();
        $teachers = $this->teachers($demoTeacherUser);
        $classes = $this->classes($year, $teachers);
        $studentsByClass = $this->students($classes, $demoStudentUser);
        $classSubjects = $this->classSubjects($classes, $subjects, $teachers, $firstTerm);

        $this->activitiesAndWork($classSubjects, $studentsByClass);
        $this->attendanceRecords($classes, $studentsByClass);

        foreach ($classSubjects as $classSubject) {
            $this->grades->compute($classSubject, $classSubject->teacher->user);
        }

        $this->awardsFor($studentsByClass, $admin, $year);
        $this->forumPosts($admin, $demoTeacherUser, $demoStudentUser, $studentsByClass);
        $this->faq();

        $this->command?->info('Demo school added: '.Student::count().' students, '.Teacher::count().' teachers, '.SchoolClass::count().' classes.');
    }

    /**
     * The current school year (June to March) with two terms, the first active.
     *
     * @return array{0: AcademicYear, 1: AcademicTerm}
     */
    private function calendar(): array
    {
        $start = today()->month >= 6 ? today()->year : today()->year - 1;

        $year = AcademicYear::create([
            'name' => $start.'-'.($start + 1),
            'start_date' => "{$start}-06-01",
            'end_date' => ($start + 1).'-03-31',
        ]);

        // The term that contains today is the active one.
        $first = $year->terms()->create(['name' => 'First Semester', 'start_date' => "{$start}-06-01", 'end_date' => "{$start}-10-31"]);
        $second = $year->terms()->create(['name' => 'Second Semester', 'start_date' => "{$start}-11-01", 'end_date' => ($start + 1).'-03-31']);

        $this->periods->activateYear($year);
        $current = today()->lte($first->end_date) ? $first : $second;
        $this->periods->activateTerm($current);

        return [$year, $current];
    }

    /**
     * @return Collection<string, Subject>
     */
    private function subjects(): Collection
    {
        return collect(self::SUBJECTS)->map(fn (string $name, string $code) => Subject::create([
            'code' => $code,
            'name' => $name,
            'description' => "{$name} for junior high school.",
            'status' => 'active',
        ]));
    }

    /**
     * The demo teacher plus nine colleagues.
     *
     * @return Collection<int, Teacher>
     */
    private function teachers(User $demoTeacherUser): Collection
    {
        $specializations = array_values(self::SUBJECTS);

        $demo = Teacher::factory()->create([
            'user_id' => $demoTeacherUser->id,
            'first_name' => 'Tomas',
            'last_name' => 'Teacher',
            'employee_number' => 'T-00001',
            'specialization' => 'Mathematics',
        ]);

        $others = collect(range(1, 9))->map(fn (int $i) => Teacher::factory()->create([
            'employee_number' => sprintf('T-%05d', $i + 1),
            'specialization' => $specializations[$i % count($specializations)],
        ]));

        return $others->prepend($demo)->values();
    }

    /**
     * Four classes, each with an adviser. The demo teacher advises the first.
     *
     * @param  Collection<int, Teacher>  $teachers
     * @return Collection<int, SchoolClass>
     */
    private function classes(AcademicYear $year, Collection $teachers): Collection
    {
        return collect(self::CLASSES)->map(function (array $class, int $index) use ($year, $teachers) {
            [$grade, $section] = $class;

            $schoolClass = SchoolClass::create([
                'academic_year_id' => $year->id,
                'name' => "{$grade} - {$section}",
                'code' => 'G'.substr($grade, -1).'-'.strtoupper(substr($section, 0, 3)),
                'grade_level' => $grade,
                'section' => $section,
                'status' => 'active',
            ]);

            $this->classTeachers->assign($schoolClass, $teachers[$index], ClassTeacherRole::Adviser);

            return $schoolClass;
        });
    }

    /**
     * About 25 students per class; the demo student is in the first class.
     *
     * @param  Collection<int, SchoolClass>  $classes
     * @return Collection<int, Collection<int, Student>> keyed by class id
     */
    private function students(Collection $classes, User $demoStudentUser): Collection
    {
        $number = 1;

        return $classes->mapWithKeys(function (SchoolClass $class, int $index) use ($demoStudentUser, &$number) {
            // A closure taking $number by reference: an arrow function would copy it.
            $students = collect(range(1, self::STUDENTS_PER_CLASS))->map(function () use (&$number) {
                return Student::factory()->create(['student_number' => sprintf('S-%06d', ++$number)]);
            });

            if ($index === 0) {
                $students->prepend(Student::factory()->create([
                    'user_id' => $demoStudentUser->id,
                    'first_name' => 'Sofia',
                    'last_name' => 'Student',
                    'student_number' => 'S-000001',
                ]));
            }

            $this->enrollments->enroll($class, $students->pluck('id')->all());

            return [$class->id => $students->values()];
        });
    }

    /**
     * Every class takes every subject this term. The demo teacher teaches
     * Mathematics in both Grade 7 classes.
     *
     * @param  Collection<int, SchoolClass>  $classes
     * @param  Collection<string, Subject>  $subjects
     * @param  Collection<int, Teacher>  $teachers
     * @return Collection<int, ClassSubject>
     */
    private function classSubjects(Collection $classes, Collection $subjects, Collection $teachers, AcademicTerm $term): Collection
    {
        $all = collect();

        foreach ($classes as $classIndex => $class) {
            foreach ($subjects->values() as $subjectIndex => $subject) {
                $teacher = $subject->code === 'MATH' && $classIndex < 2
                    ? $teachers[0]
                    : $teachers[1 + (($classIndex + $subjectIndex) % ($teachers->count() - 1))];

                $classSubject = new ClassSubject(['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'academic_term_id' => $term->id, 'status' => 'active']);
                $classSubject->school_class_id = $class->id;
                $classSubject->save();

                $all->push($classSubject->load(['teacher.user', 'subject', 'schoolClass']));
            }
        }

        return $all;
    }

    /**
     * For Mathematics and Science in every class: a graded assignment that
     * is past due, an open assignment, a graded quiz, an upcoming exam and a
     * draft. Other subjects get one graded assignment.
     *
     * @param  Collection<int, ClassSubject>  $classSubjects
     * @param  Collection<int, Collection<int, Student>>  $studentsByClass
     */
    private function activitiesAndWork(Collection $classSubjects, Collection $studentsByClass): void
    {
        foreach ($classSubjects as $classSubject) {
            $students = $studentsByClass[$classSubject->school_class_id];
            $teacherUser = $classSubject->teacher->user;
            $subject = $classSubject->subject->name;
            $core = in_array($classSubject->subject->code, ['MATH', 'SCI'], true);

            $this->gradedAssignment($classSubject, $teacherUser, $students, "{$subject}: Chapter 1 worksheet");

            if (! $core) {
                continue;
            }

            $open = $this->activities->create($classSubject, $teacherUser, [
                'type' => 'assignment',
                'title' => "{$subject}: Reflection journal",
                'description' => 'A short reflection on this week\'s lessons.',
                'instructions' => "Write one paragraph about something new you learned in {$subject} this week, and one question you still have.",
                'due_at' => now()->addDays(5)->startOfHour(),
                'total_points' => 10,
                'allow_late_submissions' => true,
            ]);
            $this->activities->changeStatus($open, ActivityStatus::Published);
            $this->handInSome($open, $students->take(9));

            $this->gradedQuiz($classSubject, $teacherUser, $students, "{$subject}: Quiz 1");

            $exam = $this->activities->create($classSubject, $teacherUser, [
                'type' => 'examination',
                'title' => "{$subject}: First quarter examination",
                'description' => 'Covers chapters 1 to 3.',
                'instructions' => "Answer every question. You have 45 minutes once you start.\nThe exam closes at the due date.",
                'due_at' => now()->addDays(10)->startOfHour(),
                'duration_minutes' => 45,
                'total_points' => 0,
                'max_attempts' => 1,
                'shuffle_questions' => true,
            ]);
            $this->addQuestions($exam, $subject);
            $this->activities->changeStatus($exam, ActivityStatus::Published);

            $this->activities->create($classSubject, $teacherUser, [
                'type' => 'activity',
                'title' => "{$subject}: Group project (draft)",
                'description' => 'Still being prepared; students cannot see it yet.',
                'due_at' => now()->addDays(21),
                'total_points' => 50,
            ]);
        }
    }

    /**
     * Published two weeks ago, due a week ago: most students handed in and
     * were graded, a few were late, a few handed in nothing.
     *
     * @param  Collection<int, Student>  $students
     */
    private function gradedAssignment(ClassSubject $classSubject, User $teacherUser, Collection $students, string $title): void
    {
        $assignment = $this->activities->create($classSubject, $teacherUser, [
            'type' => 'assignment',
            'title' => $title,
            'description' => 'Practice problems from chapter 1.',
            'instructions' => "Answer all items on the worksheet.\nShow your solutions.",
            'due_at' => now()->addDay(),
            'total_points' => 20,
            'allow_late_submissions' => true,
        ]);
        $this->activities->changeStatus($assignment, ActivityStatus::Published);

        // Move it into the past now that it is published.
        $assignment->forceFill(['start_at' => now()->subDays(14), 'due_at' => now()->subDays(7)->startOfHour()])->save();

        foreach ($students->values() as $index => $student) {
            if ($index % 9 === 8) {
                continue; // Nothing handed in.
            }

            $late = $index % 7 === 6;
            $submission = new ActivitySubmission;
            $submission->learning_activity_id = $assignment->id;
            $submission->student_id = $student->id;
            $submission->body = 'My answers are in the attached worksheet. I showed my solutions for each item.';
            $submission->status = SubmissionStatus::Graded;
            $submission->submitted_at = $late ? now()->subDays(6) : now()->subDays(8 + $index % 4);
            $submission->is_late = $late;
            $submission->score = max(8, min(20, 13 + ($index * 7) % 8 - ($late ? 2 : 0)));
            $submission->feedback = $submission->score >= 18 ? 'Excellent work, clear solutions.' : 'Good effort. Check your work on items 4 and 7.';
            $submission->graded_by = $teacherUser->id;
            $submission->graded_at = now()->subDays(5);
            $submission->save();
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function handInSome(LearningActivity $activity, Collection $students): void
    {
        foreach ($students as $student) {
            $submission = new ActivitySubmission;
            $submission->learning_activity_id = $activity->id;
            $submission->student_id = $student->id;
            $submission->body = 'This week I learned how to set up the problem before solving it. I still wonder how this applies outside class.';
            $submission->status = SubmissionStatus::Submitted;
            $submission->submitted_at = now()->subHours(mt_rand(2, 40));
            $submission->is_late = false;
            $submission->save();
        }
    }

    /**
     * A five-question quiz that every student took through the exam screen's
     * own rules, then closed by its due date.
     *
     * @param  Collection<int, Student>  $students
     */
    private function gradedQuiz(ClassSubject $classSubject, User $teacherUser, Collection $students, string $title): void
    {
        $quiz = $this->activities->create($classSubject, $teacherUser, [
            'type' => 'quiz',
            'title' => $title,
            'description' => 'Five questions on chapter 1.',
            'instructions' => 'Choose the best answer. You have 15 minutes.',
            'due_at' => now()->addDay(),
            'duration_minutes' => 15,
            'total_points' => 0,
            'max_attempts' => 2,
        ]);
        $this->addQuestions($quiz, $classSubject->subject->name, objectiveOnly: true);
        $this->activities->changeStatus($quiz, ActivityStatus::Published);

        $questions = $quiz->questions()->with('options')->get();

        foreach ($students->values() as $index => $student) {
            if ($index % 10 === 9) {
                continue; // Did not take it.
            }

            $attempt = $this->attempts->start($quiz, $student);

            foreach ($questions as $number => $question) {
                // Stronger students get more right.
                $right = (($index * 3 + $number * 5) % 10) < 8 + ($index % 3);
                $correct = $question->options->where('is_correct', true)->pluck('id')->all();
                $wrong = $question->options->where('is_correct', false)->pluck('id')->all();

                if ($question->question_type === QuestionType::Identification) {
                    $this->attempts->saveAnswer($attempt, $question->id, null, $right ? $question->options->first()->option_text : 'I am not sure');
                } else {
                    $this->attempts->saveAnswer($attempt, $question->id, $right ? $correct : [$wrong[0]], null);
                }
            }

            $this->attempts->submit($attempt);
        }

        $quiz->forceFill(['due_at' => now()->subDays(3)->startOfHour()])->save();
        $this->activities->changeStatus($quiz->refresh(), ActivityStatus::Closed);
    }

    private function addQuestions(LearningActivity $activity, string $subject, bool $objectiveOnly = false): void
    {
        $items = [
            ['multiple_choice', 'Which of these is the first step in solving a word problem?', 2, ['Read the problem carefully' => true, 'Write the answer' => false, 'Guess and check' => false, 'Skip it' => false]],
            ['true_false', "In {$subject}, units must always be written with the final answer.", 1, ['true']],
            ['multiple_select', 'Which of these are good study habits? Choose every answer that applies.', 2, ['Review notes daily' => true, 'Ask questions in class' => true, 'Cram the night before' => false]],
            ['identification', 'What do we call a statement that is accepted as true without proof?', 2, ['Axiom', 'Postulate']],
            ['multiple_choice', 'What should you do when your answer looks wrong?', 1, ['Check your work' => true, 'Hand it in anyway' => false, 'Erase everything' => false]],
        ];

        if (! $objectiveOnly) {
            $items[] = ['essay', "Explain, in your own words, why {$subject} matters in everyday life.", 5, []];
        }

        foreach ($items as [$type, $text, $points, $options]) {
            $data = ['question' => $text, 'question_type' => $type, 'points' => $points];

            if ($type === 'true_false') {
                $data['correct_answer'] = $options[0];
            } elseif ($type === 'identification') {
                $data['options'] = array_map(fn (string $answer) => ['text' => $answer], $options);
            } elseif ($options) {
                $data['options'] = array_map(fn (string $option, bool $correct) => ['text' => $option, 'is_correct' => $correct], array_keys($options), $options);
            }

            $this->questions->create($activity, $data);
        }
    }

    /**
     * Daily attendance for the last six weeks of school days, recorded by
     * each class's adviser. Most students are present on most days.
     *
     * @param  Collection<int, SchoolClass>  $classes
     * @param  Collection<int, Collection<int, Student>>  $studentsByClass
     */
    private function attendanceRecords(Collection $classes, Collection $studentsByClass): void
    {
        $days = collect(range(1, 42))
            ->map(fn (int $daysAgo) => today()->subDays($daysAgo))
            ->filter(fn (Carbon $day) => $day->isWeekday())
            ->reverse();

        foreach ($classes as $class) {
            $recorder = $class->classTeachers()->current()->with('teacher.user')->first()->teacher->user;

            foreach ($days as $dayIndex => $day) {
                $records = $studentsByClass[$class->id]->values()->map(function (Student $student, int $index) use ($dayIndex) {
                    $roll = ($index * 13 + $dayIndex * 7) % 100;

                    // A couple of students are often absent, so "needs attention" has someone to show.
                    $status = match (true) {
                        $index === 5 && $roll < 35 => AttendanceStatus::Absent,
                        $roll < 3 => AttendanceStatus::Absent,
                        $roll < 8 => AttendanceStatus::Late,
                        $roll < 10 => AttendanceStatus::Excused,
                        default => AttendanceStatus::Present,
                    };

                    return [
                        'student_id' => $student->id,
                        'status' => $status->value,
                        'remarks' => $status === AttendanceStatus::Excused ? 'Medical appointment' : null,
                    ];
                })->all();

                $this->attendance->record($class, null, $day, $records, $recorder);
            }
        }
    }

    /**
     * @param  Collection<int, Collection<int, Student>>  $studentsByClass
     */
    private function awardsFor(Collection $studentsByClass, User $admin, AcademicYear $year): void
    {
        $kinds = [
            ['Academic Excellence', 'For outstanding grades across all subjects.', 'An average of 90% or higher for the quarter.'],
            ['Perfect Attendance', 'For being present every school day.', 'No absences during the quarter.'],
            ['Most Improved', 'For the biggest progress since last quarter.', 'Chosen by the class adviser.'],
            ['Outstanding Student', 'For leadership and kindness in class.', 'Nominated by teachers and classmates.'],
        ];

        $created = collect($kinds)->map(fn (array $kind) => $this->awards->save(null, [
            'name' => $kind[0],
            'description' => $kind[1],
            'criteria' => $kind[2],
            'status' => 'active',
        ], null, false, $admin));

        foreach ($studentsByClass as $classId => $students) {
            foreach ($created as $index => $award) {
                $this->awards->grant($award, $students[($index * 4 + 1) % $students->count()], [
                    'academic_year_id' => $year->id,
                    'school_class_id' => $classId,
                    'awarded_on' => today()->subDays(10 + $index)->toDateString(),
                    'remarks' => 'Congratulations, keep it up!',
                ], $admin);
            }
        }

        // The demo student has something to see.
        $this->awards->grant($created[3], $studentsByClass->first()->first(), [
            'academic_year_id' => $year->id,
            'school_class_id' => $studentsByClass->keys()->first(),
            'awarded_on' => today()->subDays(2)->toDateString(),
            'remarks' => 'Thank you for helping your classmates during group work.',
        ], $admin);
    }

    /**
     * @param  Collection<int, Collection<int, Student>>  $studentsByClass
     */
    private function forumPosts(User $admin, User $demoTeacherUser, User $demoStudentUser, Collection $studentsByClass): void
    {
        $general = ForumCategory::create(['name' => 'General', 'description' => 'Anything about school life.', 'order' => 1]);
        $homework = ForumCategory::create(['name' => 'Homework help', 'description' => 'Ask and answer questions about lessons.', 'order' => 2]);
        $news = ForumCategory::create(['name' => 'Announcements', 'description' => 'News from teachers and the school office.', 'order' => 3]);

        $students = $studentsByClass->flatten()->values();
        $user = fn (int $i) => $students[$i % $students->count()]->user;

        $rules = $this->forum->createTopic($news, $admin, 'Forum rules: please read first', "Be kind, stay on topic, and never share passwords or personal details.\nPosts that break these rules are removed by moderators.");
        $this->forum->setPinned($rules, true);
        $this->forum->setLocked($rules, true);

        $fractions = $this->forum->createTopic($homework, $demoStudentUser, 'How do I add fractions with different denominators?', 'I keep getting the wrong answer for 1/2 + 1/3. What am I missing?');
        $this->forum->reply($fractions, $user(3), 'Find a common denominator first. For 2 and 3 that is 6, so 1/2 = 3/6 and 1/3 = 2/6.');
        $this->forum->reply($fractions, $demoTeacherUser, 'Exactly right. So the answer is 5/6. Try 1/4 + 1/6 next and post your answer here.');
        $this->forum->reply($fractions, $demoStudentUser, 'Is it 5/12? Thank you both!');

        $science = $this->forum->createTopic($homework, $user(7), 'Science project partners?', 'Is anyone in Grade 7 still looking for a partner for the plant growth project?');
        $this->forum->reply($science, $user(11), 'I am! Let\'s meet at the library tomorrow.');

        $fair = $this->forum->createTopic($general, $user(20), 'Ideas for the school fair', 'What booths would you like to see this year? I vote for a science magic show.');
        $this->forum->reply($fair, $user(31), 'A book swap corner would be nice.');
        $spam = $this->forum->reply($fair, $user(44), 'BUY CHEAP GAME CREDITS HERE!!! click my profile');
        $this->forum->report($spam, $user(20), 'This is spam, not about the fair.');
        $this->forum->report($spam, $user(31), 'Advertising.');

        $this->forum->createTopic($news, $demoTeacherUser, 'Mathematics review session on Friday', 'There will be a review session for the first quarter exam on Friday at 3:30 PM in Room 204. Bring your questions.');
    }

    private function faq(): void
    {
        $sections = [
            ['Accounts and signing in', [
                ['How do I sign in?', 'Use the email address the school registered for you and the password you were given. If you forgot it, choose "Forgot your password?" on the log in page.', true],
                ['Can I change my email address?', 'No. Ask the school office if your email address has changed.', true],
            ]],
            ['Activities and exams', [
                ['Where do I find my assignments?', 'Open Activities in the menu. Open ones are marked Open, and the dashboard lists what is coming up.', true],
                ['What happens if my exam time runs out?', 'Your saved answers are handed in automatically. Answers are saved as you work, so nothing is lost if your connection drops.', true],
                ['Can I hand in work after the due date?', 'Only if your teacher allows late work. It is then marked late.', true],
            ]],
            ['Grades and attendance', [
                ['How are my grades computed?', 'Your grade is the weighted average of your assignments, quizzes, exams and projects. Open a subject under Grades to see how it is made up.', true],
                ['Why does my attendance leave out some days?', 'Excused days are not counted for or against you.', true],
                ['When are report cards released?', 'This answer is still being written by the school office.', false],
            ]],
        ];

        foreach ($sections as $order => [$name, $items]) {
            $category = FaqCategory::create(['name' => $name, 'order' => $order + 1]);

            foreach ($items as $position => [$question, $answer, $published]) {
                $faq = new Faq(['faq_category_id' => $category->id, 'question' => $question, 'answer' => $answer, 'is_published' => $published]);
                $faq->order = $position + 1;
                $faq->save();
            }
        }
    }
}
