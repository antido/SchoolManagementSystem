export interface AcademicYear {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    terms_count?: number;
}

export interface SchoolClassSummary {
    id: number;
    name: string;
    code: string;
    grade_level: string;
    section: string | null;
    status: string;
    academic_year: { id: number; name: string; is_active: boolean };
}

export interface SchoolClassDetails extends SchoolClassSummary {
    academic_year_id?: number;
    description: string | null;
}

export interface Subject {
    id: number;
    code: string;
    name: string;
    description: string | null;
    status: string;
    classes_count?: number;
}

/** A subject taught to one class in one term. */
export interface ClassSubject {
    id: number;
    status: string;
    subject: { id: number; code: string; name: string };
    school_class: { id: number; name: string };
    term: { id: number; name: string };
    teacher: { id: number; name: string } | null;
    academic_year?: string;
    can_delete?: boolean;
}

/** One row of a class roster. */
export interface RosterEntry {
    id: number;
    status: string;
    enrolled_at: string;
    ended_at: string | null;
    student: { id: number; student_number: string; full_name: string; sortable_name: string; email: string; status: string };
}

export interface Roster {
    current: RosterEntry[];
    past: RosterEntry[];
}

/** A student who could be added to a class. */
export interface EnrollmentCandidate {
    id: number;
    name: string;
    student_number: string;
}

/** One class in a student's enrollment history. */
export interface EnrollmentHistoryItem {
    id: number;
    class: { id: number; name: string };
    academic_year: string;
    status: string;
    enrolled_at: string;
    ended_at: string | null;
}

/** A teacher attached to a class as adviser or teacher. */
export interface ClassTeacherAssignment {
    id: number;
    role: string;
    role_label: string;
    assigned_at: string;
    ended_at: string | null;
    teacher: { id: number; name: string };
}

export interface ClassTeachers {
    current: ClassTeacherAssignment[];
    past: ClassTeacherAssignment[];
    subject_teachers: { teacher: { id: number; name: string }; subjects: string[] }[];
}

/** One class a teacher is or was attached to, for the teacher's profile. */
export interface TeacherAssignmentHistoryItem {
    id: number;
    role_label: string;
    class: { id: number; name: string };
    academic_year: string;
    assigned_at: string;
    ended_at: string | null;
}

export interface AcademicTerm {
    id: number;
    academic_year_id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    can_delete?: boolean;
}
