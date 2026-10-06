/**
 * A small JSON request helper for the few calls that should not reload the
 * page, such as autosaving exam answers. Inertia visits are used everywhere else.
 *
 * Laravel's XSRF-TOKEN cookie is sent back in the X-XSRF-TOKEN header, as
 * the CSRF middleware expects.
 */
export class HttpError extends Error {
    constructor(
        public status: number,
        public body: { message?: string; errors?: Record<string, string[]> },
    ) {
        super(body.message ?? `Request failed with status ${status}`);
    }
}

export function xsrfToken(): string {
    const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

export async function sendJson<T>(method: 'POST' | 'PUT' | 'PATCH', url: string, data: unknown): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(data),
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new HttpError(response.status, body);
    }

    return body as T;
}

export async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new HttpError(response.status, body);
    }

    return body as T;
}
