/**
 * Shared JSON HTTP helper for raw fetch() calls (calls, polling, signalling).
 *
 * Why this exists:
 * Laravel rotates the session's CSRF token, and the <meta name="csrf-token">
 * tag only reflects the value at the *initial* page render. On an Inertia SPA
 * page that never does a full reload, that meta value goes stale relative to
 * the live session, so a raw fetch POST can be rejected with HTTP 419
 * "CSRF token mismatch".
 *
 * The `XSRF-TOKEN` cookie is re-issued by Laravel on every response and is the
 * exact source Inertia/axios rely on. We prefer it (sent as `X-XSRF-TOKEN`,
 * which Laravel decrypts), and fall back to the meta tag only if it's missing.
 */

const readCookie = (name) => {
    if (typeof document === 'undefined') return null;
    const escaped = name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1');
    const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + escaped + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
};

export const csrfHeaders = () => {
    const xsrf = readCookie('XSRF-TOKEN');
    if (xsrf) return { 'X-XSRF-TOKEN': xsrf };

    const meta = typeof document !== 'undefined'
        ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        : '';
    return meta ? { 'X-CSRF-TOKEN': meta } : {};
};

/**
 * fetch() wrapper returning parsed JSON and throwing an Error (with `.status`
 * and `.message`) on non-2xx. Retries once on 419 so a rotated token that the
 * response has since refreshed can still succeed.
 */
export const request = async (url, method = 'POST', body, retryOn419 = true) => {
    const isGet = method === 'GET';

    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(isGet ? {} : { 'Content-Type': 'application/json', ...csrfHeaders() }),
        },
        credentials: 'same-origin',
        body: isGet ? undefined : JSON.stringify(body ?? {}),
    });

    if (res.status === 419 && retryOn419 && !isGet) {
        return request(url, method, body, false);
    }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const error = new Error(data.message ?? `Request failed (HTTP ${res.status})`);
        error.status = res.status;
        throw error;
    }
    return data;
};

export default request;
