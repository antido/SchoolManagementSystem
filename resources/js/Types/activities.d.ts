export type ActivityType = 'assignment' | 'quiz' | 'examination' | 'questionnaire' | 'activity';

/** What a student would see right now. */
export type ActivityAvailability = 'draft' | 'scheduled' | 'open' | 'past_due' | 'closed';

export interface ActivitySummary {
    id: number;
    title: string;
    type: ActivityType;
    type_label: string;
    status: 'draft' | 'published' | 'closed';
    availability: ActivityAvailability;
    start_at: string | null;
    due_at: string | null;
    duration_minutes: number | null;
    total_points: number;
    allow_late_submissions: boolean;
    accepts_submissions: boolean;
    uses_questions: boolean;
    max_attempts: number;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    class_subject: {
        id: number;
        subject: string;
        school_class: { id: number; name: string };
        term: string;
    };
}

export interface ActivityDetails extends ActivitySummary {
    description: string | null;
    instructions: string | null;
    teacher?: { id: number; name: string } | null;
    created_at?: string;
    attachments: Attachment[];
}

/** A downloadable file. */
export interface Attachment {
    id: number;
    name: string;
    size: number;
    mime_type: string | null;
    url: string;
}

/** How a student's work on an activity stands. */
export type SubmissionStatus = 'pending' | 'submitted' | 'late' | 'graded' | 'missing';

export interface Submission {
    id: number;
    status: SubmissionStatus;
    body: string | null;
    submitted_at: string;
    is_late: boolean;
    score: number | null;
    feedback: string | null;
    graded_at: string | null;
    attachments: Attachment[];
}

/** One student on an activity's submissions list. */
export interface SubmissionRosterRow {
    student: { id: number; name: string; student_number: string };
    submission_id: number | null;
    status: SubmissionStatus;
    submitted_at: string | null;
    is_late: boolean;
    score: number | null;
}

export type QuestionType = 'multiple_choice' | 'true_false' | 'multiple_select' | 'identification' | 'short_answer' | 'essay';

/** A question. `is_correct` is only sent to people who manage the activity. */
export interface Question {
    id: number;
    question: string;
    type: QuestionType;
    type_label: string;
    points: number;
    order: number;
    options: { id: number; text: string; is_correct?: boolean }[];
}

export type AttemptStatus = 'in_progress' | 'submitted' | 'graded';

export interface Attempt {
    id: number;
    attempt_number: number;
    status: AttemptStatus;
    started_at: string;
    submitted_at: string | null;
    auto_submitted: boolean;
    score: number | null;
}

/** One student on a quiz's or examination's results list. */
export interface AttemptRosterRow {
    student: { id: number; name: string; student_number: string };
    attempts: number;
    latest_attempt_id: number | null;
    status: AttemptStatus | 'pending' | 'missing';
    best_score: number | null;
}
