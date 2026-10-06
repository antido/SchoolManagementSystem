export interface AppNotification {
    id: string;
    title: string;
    body: string | null;
    /** Opens the related page and marks the notification read. */
    url: string;
    icon: string;
    read: boolean;
    created_at: string;
    when: string;
}
