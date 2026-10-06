/**
 * Small formatting helpers. Dates arrive from Laravel as ISO 8601 strings.
 */

const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });
const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

/** "2026-10-06" -> "Oct 6, 2026". Returns an empty string for null. */
export function formatDate(value: string | null | undefined): string {
    return value ? dateFormatter.format(new Date(value)) : '';
}

/** "2026-10-06T14:30:00Z" -> "Oct 6, 2026, 2:30 PM". */
export function formatDateTime(value: string | null | undefined): string {
    return value ? dateTimeFormatter.format(new Date(value)) : '';
}

/** "on_leave" -> "On leave" */
export function humanize(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const text = value.replace(/[_-]+/g, ' ');

    return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Join class names, skipping empty ones: cn('a', isOn && 'b') */
export function cn(...classes: Array<string | false | null | undefined>): string {
    return classes.filter(Boolean).join(' ');
}

/** Two-digit padding for date parts. */
const pad = (value: number) => String(value).padStart(2, '0');

/**
 * An ISO date-time -> the "2026-10-06T14:30" value a datetime-local input
 * expects, in the browser's time zone. Empty for null.
 */
export function toDateTimeInput(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * A datetime-local value -> an ISO date-time with the browser's offset, so
 * the server stores the moment the user meant. Empty stays empty.
 */
export function fromDateTimeInput(value: string): string {
    return value ? new Date(value).toISOString() : '';
}

/** 2048 -> "2 KB", 3500000 -> "3.3 MB". */
export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * The first error for a list field and its items, e.g. "attachments" or
 * "attachments.2", since Laravel reports file errors per item.
 */
export function firstListError(errors: Partial<Record<string, string>>, field: string): string | undefined {
    return errors[field] ?? Object.entries(errors).find(([key]) => key.startsWith(`${field}.`))?.[1];
}
