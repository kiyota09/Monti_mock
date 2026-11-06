import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import axios from 'axios';
import GlobalFeedback from '@/Components/system/GlobalFeedback.vue';
import { notify, setSlow } from '@/lib/feedback';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');

const resolveInertiaPage = (name: string) => {
    const requested = `./Pages/${name}.vue`;
    const matched = Object.keys(pages).find((path) => path.toLowerCase() === requested.toLowerCase()) || requested;
    return resolvePageComponent(matched, pages);
};

// ─── GLOBAL JAVASCRIPT ERROR HANDLERS ──────────────────────────────────
// Catches errors that happen outside Vue (e.g., in setTimeout, fetch, etc.)
// Runtime errors are logged and surfaced through the shared feedback modal.
function notifyError(message: string) {
    console.error(message);
    try {
        if (typeof document !== 'undefined') notify({ kind: 'error', message });
    } catch {
        /* feedback is best-effort; logging above is the source of truth */
    }
}

const feedbackLoginPath = '/login';
let realtimeVersion: string | null = null;
let realtimeEnabled = false;
let realtimeReloadPending = false;
let realtimeRefreshRequested = false;
const nativeFetch = window.fetch.bind(window);

function pageHasAuthenticatedUser(pageProps: Record<string, any> | undefined) {
    const auth = pageProps?.auth;
    return Boolean(auth?.user || auth?.client || auth?.supplier || auth?.applicant || auth?.agency);
}

function refreshCurrentPageData() {
    if (!realtimeRefreshRequested || realtimeReloadPending) return;
    realtimeRefreshRequested = false;
    realtimeReloadPending = true;
    router.reload({
        onFinish: () => {
            realtimeReloadPending = false;
            if (realtimeRefreshRequested) refreshCurrentPageData();
        },
    });
}

function acceptRealtimeVersion(value: unknown) {
    if (value !== undefined && value !== null && String(value) !== '') realtimeVersion = String(value);
}

async function pollRealtimeChanges() {
    if (!realtimeEnabled || document.visibilityState !== 'visible' || !navigator.onLine) return;
    try {
        const response = await nativeFetch('/system/realtime/version', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (!response.ok) return;
        const payload = await response.json();
        const nextVersion = String(payload.version ?? '0');
        if (realtimeVersion === null) {
            realtimeVersion = nextVersion;
            return;
        }
        if (nextVersion === realtimeVersion) return;

        realtimeVersion = nextVersion;
        window.dispatchEvent(new CustomEvent('erp:data-updated', { detail: { version: nextVersion } }));
        realtimeRefreshRequested = true;
        refreshCurrentPageData();
    } catch {
        // Polling is best-effort; the next visible interval will retry.
    }
}

// Polling every few seconds was creating needless session/cache traffic on
// local and production servers. Keep updates responsive without constant hits.
window.setInterval(pollRealtimeChanges, 15000);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') pollRealtimeChanges();
});
router.on('navigate', (event) => {
    realtimeEnabled = pageHasAuthenticatedUser(event.detail.page?.props);
    acceptRealtimeVersion(event.detail.page?.props?.realtime_version);
});

axios.interceptors.request.use((config) => {
    (config as any).__feedbackTimer = window.setTimeout(() => {
        setSlow(true);
    }, 10000);
    return config;
});
axios.interceptors.response.use((response) => {
    window.clearTimeout((response.config as any).__feedbackTimer);
    setSlow(false);
    acceptRealtimeVersion(response.headers?.['x-erp-data-version']);
    return response;
}, (error) => {
    const config = error?.config;
    if (config?.__feedbackTimer) window.clearTimeout(config.__feedbackTimer);
    setSlow(false);
    const status = error?.response?.status;
    if (status === 401 || status === 419) {
        notify({ kind: 'session', actionLabel: 'Sign in', action: () => { window.location.href = feedbackLoginPath; } });
    } else if (status === 403) {
        notify({ kind: 'permission' });
    }
    return Promise.reject(error);
});

window.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
    const feedbackInit = init as (RequestInit & { __suppressSuccessFeedback?: boolean }) | undefined;
    const nativeInit = feedbackInit ? { ...feedbackInit } : undefined;
    if (nativeInit) delete (nativeInit as RequestInit & { __suppressSuccessFeedback?: boolean }).__suppressSuccessFeedback;
    const response = await nativeFetch(input, nativeInit);
    acceptRealtimeVersion(response.headers.get('X-ERP-Data-Version'));
    return response;
};

window.onerror = function (message, source, lineno, colno, error) {
    console.error('Window Error:', { message, source, lineno, colno, error });
    notifyError(`Uncaught Error: ${String(message)}`);
};

window.onunhandledrejection = function (event) {
    console.error('Unhandled Promise Rejection:', event.reason);
    // Request failures are handled by their caller (or the session/permission
    // notices above); do not turn server responses into the global error modal.
    if (axios.isAxiosError(event.reason)) {
        event.preventDefault();
        return;
    }
    const reason =
        event.reason instanceof Error ? event.reason.message : String(event.reason);
    notifyError(`Unhandled Promise Rejection: ${reason}`);
    // Prevent the browser's own unhandledrejection console spam; we logged it.
    event.preventDefault();
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolveInertiaPage(name),
    setup({ el, App, props, plugin }) {
        realtimeEnabled = pageHasAuthenticatedUser(props.initialPage?.props as Record<string, any> | undefined);
        acceptRealtimeVersion((props.initialPage?.props as Record<string, any> | undefined)?.realtime_version);
        pollRealtimeChanges();
        const app = createApp({ render: () => h('div', { class: 'contents' }, [h(App, props), h(GlobalFeedback)]) })
            .use(plugin)
            .use(ZiggyVue);

        // ─── VUE ERROR HANDLER ──────────────────────────────────────────
        // Catches errors during component rendering, lifecycle, and event handlers.
        // Logs with component context and shows a generic feedback modal.
        app.config.errorHandler = (err, vm, info) => {
            console.error('Vue Error:', err, info);
            const detail = err instanceof Error ? err.message : String(err);
            notifyError(`Something went wrong: ${detail}`);
        };

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
