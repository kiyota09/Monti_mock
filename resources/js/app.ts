import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// ─── GLOBAL JAVASCRIPT ERROR HANDLERS ──────────────────────────────────
// Catches errors that happen outside Vue (e.g., in setTimeout, fetch, etc.)
// NOTE: these intentionally only log + toast. A previous revision called
// alert(), which blocks the UI thread and leaks raw error text to end users.
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

function notifyError(message: string) {
    console.error(message);
    // Toast only outside production SSR / tests where document may be missing.
    try {
        if (typeof document !== 'undefined') {
            toast.error(message);
        }
    } catch {
        /* toast is best-effort; logging above is the source of truth */
    }
}

window.onerror = function (message, source, lineno, colno, error) {
    console.error('Window Error:', { message, source, lineno, colno, error });
    notifyError(`Uncaught Error: ${String(message)}`);
};

window.onunhandledrejection = function (event) {
    console.error('Unhandled Promise Rejection:', event.reason);
    const reason =
        event.reason instanceof Error ? event.reason.message : String(event.reason);
    notifyError(`Unhandled Promise Rejection: ${reason}`);
    // Prevent the browser's own unhandledrejection console spam; we logged it.
    event.preventDefault();
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // ─── VUE ERROR HANDLER ──────────────────────────────────────────
        // Catches errors during component rendering, lifecycle, and event handlers.
        // Logs with component context and toasts a generic message (no alert()).
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