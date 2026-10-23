/**
 * JSON requests of the exam player (autosave, events). Not Inertia visits:
 * plain fetch with Laravel's XSRF-TOKEN cookie, same origin only.
 */

export type JsonResult<T> =
    | { ok: true; status: number; data: T }
    | { ok: false; status: number; data: Record<string, unknown> | null }
    | { ok: false; status: 0; data: null };

function xsrfToken(): string | null {
    const match = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.slice('XSRF-TOKEN='.length)) : null;
}

/** status 0 = network error (offline, timeout): the caller retries. */
export async function sendJson<T>(
    method: 'PUT' | 'POST',
    url: string,
    body: unknown,
    options: { keepalive?: boolean } = {},
): Promise<JsonResult<T>> {
    const token = xsrfToken();

    try {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            keepalive: options.keepalive,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify(body),
        });

        const data =
            response.status === 204
                ? null
                : await response.json().catch(() => null);

        return response.ok
            ? { ok: true, status: response.status, data: data as T }
            : { ok: false, status: response.status, data };
    } catch {
        return { ok: false, status: 0, data: null };
    }
}
