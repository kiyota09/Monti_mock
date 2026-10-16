import { ref } from 'vue';

export type ThemeKey = 'light' | 'dark' | 'blue' | 'red' | 'green';

export interface ThemeOption {
    key: ThemeKey;
    label: string;
    desc: string;
    dot: string;
    dark: boolean;
}

export const THEMES: ThemeOption[] = [
    { key: 'light', label: 'Light', desc: 'Default light', dot: '#e2e8f0', dark: false },
    { key: 'dark', label: 'Dark', desc: 'Default dark', dot: '#18181b', dark: true },
    { key: 'blue', label: 'Blue', desc: 'Ice-Blue & Navy · whole ERP', dot: '#1A73E8', dark: false },
    { key: 'red', label: 'Red', desc: 'Rose & Wine · whole ERP', dot: '#E11D48', dark: false },
    { key: 'green', label: 'Green', desc: 'Soft Sage & Eucalyptus · whole ERP', dot: '#40916C', dark: true },
];

const STORAGE_KEY = 'monti-theme';

const validThemes: ThemeKey[] = ['light', 'dark', 'blue', 'red', 'green'];

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

// Follows the OS theme live until the user makes an explicit choice
// (a stored choice always wins and stops the sync). Singleton listener.
let systemWatcherStarted = false;
export function watchSystemTheme() {
    if (systemWatcherStarted) return;
    systemWatcherStarted = true;
    try {
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => {
            if (!getStoredTheme()) applyThemeValue(getSystemTheme(), false);
        };
        if (typeof mq.addEventListener === 'function') mq.addEventListener('change', onChange);
        else if (typeof (mq as any).addListener === 'function') (mq as any).addListener(onChange);
    } catch {
        /* noop */
    }
}

export function initTheme(): ThemeKey {
    // Stored user choice wins; otherwise fall back to the OS/browser theme
    // (logged-out default) without persisting it, so the page keeps tracking
    // system changes until the user explicitly picks a theme.
    const theme: ThemeKey = getStoredTheme() ?? getSystemTheme();
    applyThemeValue(theme, !!getStoredTheme());
    watchSystemTheme();
    return theme;
}

export function useTheme() {
    // Explicit user pick — always persisted (this is what "depends on the user" means).
    const setTheme = (theme: ThemeKey) => applyThemeValue(theme, true);
    return { currentTheme, THEMES, setTheme, applyThemeValue, initTheme, getStoredTheme, getSystemTheme, watchSystemTheme };
}
