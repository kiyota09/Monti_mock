import { ref } from 'vue';

export type ThemeKey = 'light' | 'dark' | 'blue' | 'red' | 'green';

export interface ThemeOption {
    key: ThemeKey;
    label: string;
    desc: string;
    dot: string;
    dark: boolean;
}

export interface ThemeSettings {
    enabledThemes?: ThemeKey[];
    preference?: ThemeKey | null;
    preferenceUrl?: string | null;
}

export const THEMES: ThemeOption[] = [
    { key: 'light', label: 'Light', desc: 'Black & white', dot: '#ffffff', dark: false },
    { key: 'dark', label: 'Dark', desc: 'Default dark', dot: '#18181b', dark: true },
    { key: 'blue', label: 'Blue', desc: 'Ice-Blue & Navy · whole ERP', dot: '#1A73E8', dark: false },
    { key: 'red', label: 'Red', desc: 'Rose & Wine · whole ERP', dot: '#E11D48', dark: false },
    { key: 'green', label: 'Green', desc: 'Soft Sage & Eucalyptus · whole ERP', dot: '#40916C', dark: true },
];

const STORAGE_KEY = 'monti-theme';

const validThemes: ThemeKey[] = ['light', 'dark', 'blue', 'red', 'green'];
let enabledThemes: ThemeKey[] = [...validThemes];
let savedPreferenceUrl: string | null = null;
let serverPreference: ThemeKey | null = null;
let preferenceSaveQueue = Promise.resolve();

const currentTheme = ref<ThemeKey>('light');

/** Explicit user choice stored in localStorage (null when never chosen). */
export function getStoredTheme(): ThemeKey | null {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return validThemes.includes(stored as ThemeKey) ? (stored as ThemeKey) : null;
    } catch {
        return null;
    }
}

/** OS / browser preference — the default when the user never picked a theme. */
export function getSystemTheme(): 'light' | 'dark' {
    try {
        if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
    } catch {
        /* noop */
    }
    return 'light';
}

export function applyThemeValue(theme: ThemeKey, persist = true) {
    currentTheme.value = theme;
    const opt = THEMES.find((t) => t.key === theme) ?? THEMES[0];
    const el = document.documentElement;
    el.classList.toggle('dark', opt.dark);
    el.setAttribute('data-theme', theme);
    if (!persist) return;
    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        /* noop */
    }
}

const fallbackTheme = (): ThemeKey => enabledThemes.includes('light') ? 'light' : enabledThemes[0];

export function applyThemeSettings(settings?: ThemeSettings | null): ThemeKey {
    if (settings) {
        const nextEnabled = (settings.enabledThemes || []).filter((theme): theme is ThemeKey => validThemes.includes(theme));
        enabledThemes = nextEnabled.length ? [...new Set(nextEnabled)] : [...validThemes];
        savedPreferenceUrl = settings.preferenceUrl || null;
        serverPreference = settings.preference && enabledThemes.includes(settings.preference)
            ? settings.preference
            : null;
    }

    const preferred = serverPreference ?? currentTheme.value;
    if (!enabledThemes.includes(preferred)) {
        const next = serverPreference ?? fallbackTheme();
        applyThemeValue(next, true);
        return next;
    }
    if (serverPreference && currentTheme.value !== serverPreference) {
        applyThemeValue(serverPreference, true);
    }
    return serverPreference ?? currentTheme.value;
}

const saveThemePreference = (theme: ThemeKey) => {
    if (!savedPreferenceUrl || typeof window === 'undefined') return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    preferenceSaveQueue = preferenceSaveQueue.then(async () => {
        const response = await fetch(savedPreferenceUrl as string, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ theme }),
        });
        // IT may disable a theme while this page is still open. Reload once so
        // the server's new policy and fallback preference take effect.
        if (response.status === 422) window.location.reload();
    }).catch(() => { /* Keep the local choice if the preference endpoint is temporarily unavailable. */ });
};

// Follows the OS theme live until the user makes an explicit choice
// (a stored choice always wins and stops the sync). Singleton listener.
let systemWatcherStarted = false;
export function watchSystemTheme() {
    if (systemWatcherStarted) return;
    systemWatcherStarted = true;
    try {
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => {
            if (!getStoredTheme()) {
                const systemTheme = getSystemTheme();
                applyThemeValue(enabledThemes.includes(systemTheme) ? systemTheme : fallbackTheme(), false);
            }
        };
        if (typeof mq.addEventListener === 'function') mq.addEventListener('change', onChange);
        else if (typeof (mq as any).addListener === 'function') (mq as any).addListener(onChange);
    } catch {
        /* noop */
    }
}

export function initTheme(settings?: ThemeSettings | null): ThemeKey {
    // Stored user choice wins; otherwise fall back to the OS/browser theme
    // (logged-out default) without persisting it, so the page keeps tracking
    // system changes until the user explicitly picks a theme.
    if (settings) applyThemeSettings(settings);
    const stored = getStoredTheme();
    const candidate = serverPreference ?? stored ?? getSystemTheme();
    const theme: ThemeKey = enabledThemes.includes(candidate) ? candidate : fallbackTheme();
    applyThemeValue(theme, !!stored || !!serverPreference || theme !== candidate);
    // Import pre-policy browser choices into the account preference record.
    // This lets IT move existing accounts automatically when a theme is later
    // disabled, including choices made before preferences became server-backed.
    if (stored && !serverPreference && enabledThemes.includes(stored)) saveThemePreference(stored);
    watchSystemTheme();
    return theme;
}

export function useTheme() {
    const setTheme = (theme: ThemeKey) => {
        if (!enabledThemes.includes(theme)) return;
        applyThemeValue(theme, true);
        serverPreference = theme;
        saveThemePreference(theme);
    };
    return { currentTheme, THEMES, setTheme, applyThemeValue, applyThemeSettings, initTheme, getStoredTheme, getSystemTheme, watchSystemTheme };
}
