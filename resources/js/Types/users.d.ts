/** A user account as shown on the user-management screens. */
export interface UserSummary {
    id: number;
    name: string;
    email: string;
    role: string | null;
    role_label: string | null;
    is_active: boolean;
    last_login_at: string | null;
    created_at?: string;
}
