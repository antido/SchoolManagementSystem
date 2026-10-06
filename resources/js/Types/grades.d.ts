/** One weighted part of a grade, as computed. */
export interface GradeComponentResult {
    name: string;
    weight: number;
    percentage: number | null;
    earned: number;
    possible: number;
    count: number;
}

export interface StudentGrade {
    id: number;
    percentage: number;
    descriptor: string;
    passed: boolean;
    breakdown: GradeComponentResult[];
    computed_at: string;
    superseded_at: string | null;
}

export interface GradingSystem {
    id: number;
    name: string;
    is_default: boolean;
    scale: { id: number; name: string };
    components: { name: string; weight: number; activity_types: string[] }[];
    class_subjects_count?: number;
    can_delete?: boolean;
    grading_scale_id?: number;
}

export interface GradingScale {
    id: number;
    name: string;
    passing_percentage: number;
    is_default: boolean;
    bands: { min_percentage: number; label: string }[];
    can_delete?: boolean;
}
