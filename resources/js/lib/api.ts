import { http, router } from '@inertiajs/react';

type Method = 'get' | 'post' | 'put' | 'patch' | 'delete';

export class ApiError extends Error {
    constructor(
        readonly status: number,
        readonly errors: Record<string, string[]>,
        message: string,
    ) {
        super(message);
    }

    /** First validation message for a field, if any. */
    first(field: string): string | undefined {
        return this.errors[field]?.[0];
    }
}

function parse(body: string): Record<string, unknown> {
    try {
        return body ? JSON.parse(body) : {};
    } catch {
        return {};
    }
}

/**
 * Calls the JSON API through Inertia's HTTP client, so it shares the session cookie, CSRF token
 * and interceptors with useHttp and router visits. Use it for one-off calls; use useHttp for forms.
 * Resolves with the parsed body (undefined for 204) and rejects with an ApiError.
 */
export async function api<T = unknown>(
    method: Method,
    url: string,
    body?: object,
): Promise<T> {
    try {
        const response = await http.getClient().request({
            method,
            url,
            data: body,
            headers: { Accept: 'application/json' },
        });

        return (
            response.status === 204 ? undefined : parse(response.data)
        ) as T;
    } catch (error) {
        const response = (
            error as { response?: { status: number; data: string } }
        ).response;

        if (!response) {
            throw new ApiError(0, {}, 'Network error.');
        }

        const json = parse(response.data);

        throw new ApiError(
            response.status,
            (json.errors as Record<string, string[]>) ?? {},
            (json.message as string) ?? 'Request failed.',
        );
    }
}

/** Re-fetch the current page's props after an API write, keeping scroll and local state. */
export function refreshPage(): Promise<void> {
    return new Promise((resolve) => {
        router.reload({
            onFinish: () => resolve(),
        });
    });
}
