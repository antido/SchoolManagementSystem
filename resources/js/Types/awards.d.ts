export interface Award {
    id: number;
    name: string;
    description: string | null;
    criteria: string | null;
    status: string;
    image_url: string | null;
    given_count?: number;
}

/** An award given to a student. */
export interface StudentAward {
    id: number;
    award: Award;
    awarded_on: string;
    remarks: string | null;
    academic_year: string | null;
    school_class: { id: number; name: string } | null;
    awarded_by: string | null;
    revoked_at: string | null;
    revoked_reason: string | null;
}
