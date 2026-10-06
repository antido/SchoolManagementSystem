/** A student as shown in lists. */
export interface StudentSummary {
    id: number;
    student_number: string;
    full_name: string;
    sortable_name: string;
    email: string;
    status: string;
}

/** A teacher as shown in lists. */
export interface TeacherSummary {
    id: number;
    employee_number: string;
    full_name: string;
    sortable_name: string;
    email: string;
    specialization: string | null;
    status: string;
}

/** A teacher with every profile field, for the profile page and edit form. */
export interface TeacherDetails extends TeacherSummary {
    first_name: string;
    middle_name: string | null;
    last_name: string;
    birth_date: string | null;
    gender: string | null;
    gender_label: string | null;
    contact_number: string | null;
    last_login_at: string | null;
}

/** A student with every profile field, for the profile page and edit form. */
export interface StudentDetails extends StudentSummary {
    first_name: string;
    middle_name: string | null;
    last_name: string;
    birth_date: string | null;
    gender: string | null;
    gender_label: string | null;
    address: string | null;
    contact_number: string | null;
    last_login_at: string | null;
}
