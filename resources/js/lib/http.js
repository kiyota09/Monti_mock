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

const geofenceHeaders = () => {
    if (typeof sessionStorage === 'undefined') return {};
    const lat = sessionStorage.getItem('erp-user-lat');
    const lng = sessionStorage.getItem('erp-user-lng');
    return lat && lng ? { 'X-User-Lat': lat, 'X-User-Lng': lng } : {};
};

// Ziggy can emit an absolute URL based on APP_URL, which may not exactly match
// the host users opened (for example www/non-www or a reverse-proxy hostname).
// Keep authenticated portal API calls on the page's origin so its session and
// XSRF cookies are always sent with the request.
const sameOriginUrl = (url) => {
    if (typeof window === 'undefined') return url;
    try {
        const target = new URL(url, window.location.href);
        if (target.origin !== window.location.origin) {
            target.protocol = window.location.protocol;
            target.host = window.location.host;
        }
        return target.toString();
    } catch {
        return url;
    }
};

/**
 * Fetch helper for JSON and FormData mutations. Read Laravel's current XSRF
 * cookie on every attempt because the initial page's CSRF meta tag can become
 * stale during a long lived Inertia session. A 419 response refreshes that
 * cookie, so retry the request once with the new value.
 */
export const csrfFetch = async (url, init = {}) => {
    const send = () => {
        const headers = new Headers(init.headers ?? {});
        Object.entries(csrfHeaders()).forEach(([name, value]) => headers.set(name, value));
        Object.entries(geofenceHeaders()).forEach(([name, value]) => headers.set(name, value));

        return fetch(sameOriginUrl(url), {
            ...init,
            headers,
            credentials: 'same-origin',
        });
    };

    const response = await send();
    return response.status === 419 ? send() : response;
};

/**
 * fetch() wrapper returning parsed JSON and throwing an Error (with `.status`
 * and `.message`) on non-2xx. Retries once on 419 so a rotated token that the
 * response has since refreshed can still succeed.
 */
export const request = async (url, method = 'POST', body, retryOn419 = true) => {
    const isGet = method === 'GET';

    const res = await fetch(sameOriginUrl(url), {
        method,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...geofenceHeaders(),
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
