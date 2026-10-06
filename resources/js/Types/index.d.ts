/**
 * Types shared by every page. Page-specific types live next to the
 * module they belong to (for example Types/students.d.ts).
 */

/** The signed-in user, as sent by HandleInertiaRequests. */
export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    avatar_url: string | null;
    roles: string[];
}

export interface Flash {
    success: string | null;
    error: string | null;
}

/** Props that every Inertia page receives, plus the page's own props `T`. */
export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    app: {
        name: string;
        uploads: { max_files: number; max_kilobytes: number; extensions: string[] };
        realtime: boolean;
    };
    auth: {
        user: AuthUser | null;
        permissions: string[];
        unread_notifications: number;
    };
    flash: Flash;
};

/** A link in a Laravel paginator's `links` list. */
export interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

/** The JSON shape of Laravel's LengthAwarePaginator. */
export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginatorLink[];
}

/** A value/label pair for select inputs and filters. */
export interface Option<V = string> {
    value: V;
    label: string;
}
