<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useTheme, initTheme } from '@/composables/useTheme';

const props = withDefaults(
    defineProps<{
        title: string;
        highlight?: string;
        subtitle?: string;
        badge?: string;
        accent?: 'blue' | 'sky' | 'emerald' | 'red';
        wide?: boolean;
        compact?: boolean;
        hideHomeLink?: boolean;
        variant?: 'cinematic' | 'warm';
    }>(),
    {
        highlight: '',
        subtitle: '',
        badge: '',
        accent: 'blue',
        wide: false,
        compact: false,
        hideHomeLink: false,
        variant: 'cinematic',
    },
);

// Logged-out pages honor the stored user theme; with no stored choice they
// fall back to the OS/browser theme (see useTheme.initTheme).
const { currentTheme } = useTheme();
const page = usePage();
onMounted(() => {
    initTheme(page.props.themeSettings);
});

// The auth accent follows the user's color theme when one is set
// (blue/red/green); otherwise it keeps the page's own accent prop.
const THEME_ACCENT: Record<string, 'blue' | 'sky' | 'emerald' | 'red'> = {
    blue: 'blue',
    red: 'red',
    green: 'emerald',
};
const effectiveAccent = computed(() => THEME_ACCENT[currentTheme.value] ?? props.accent);
const warmThemeStyle = computed(() => {
    const palettes: Record<string, Record<string, string>> = {
        light: { accent: '#d95b16', ink: '#1a1a1a', muted: '#6b6b6b', surface: '#ffffff', tile: '#fbf6f1', pageStart: '#fffaf6', pageEnd: '#f6e9df' },
        blue: { accent: '#2563eb', ink: '#10234f', muted: '#52638b', surface: '#ffffff', tile: '#eff5ff', pageStart: '#f4f8ff', pageEnd: '#dce8ff' },
        red: { accent: '#be123c', ink: '#4c0519', muted: '#754451', surface: '#fffafb', tile: '#fff0f2', pageStart: '#fff7f8', pageEnd: '#f7dce2' },
        green: { accent: '#15803d', ink: '#173b28', muted: '#52685a', surface: '#fbfefb', tile: '#edf7ef', pageStart: '#f6fbf6', pageEnd: '#dceee0' },
        dark: { accent: '#fb923c', ink: '#f4f4f5', muted: '#a1a1aa', surface: '#18181b', tile: '#27272a', pageStart: '#18181b', pageEnd: '#27272a' },
    };
    const palette = palettes[currentTheme.value] ?? palettes.light;
    return {
        '--auth-accent': palette.accent,
        '--auth-accent-ink': palette.accent,
        '--auth-accent-50': palette.tile,
        '--auth-ink': palette.ink,
        '--auth-muted': palette.muted,
        '--auth-surface': palette.surface,
        '--auth-tile': palette.tile,
        '--auth-page-start': palette.pageStart,
        '--auth-page-end': palette.pageEnd,
        '--auth-focus': currentTheme.value === 'dark' ? 'rgba(251, 146, 60, .22)' : 'color-mix(in srgb, var(--auth-accent) 16%, transparent)',
    };
});

const goHome = () => router.visit('/');

// Split the title so `highlight` renders in Welcome's gradient style.
const titleParts = computed(() => {
    if (!props.highlight || !props.title.includes(props.highlight)) {
        return [props.title];
    }
    return props.title.split(props.highlight);
});
</script>

<template>
    <div
        v-if="variant === 'warm'"
        class="auth-warm relative flex min-h-screen w-full flex-col overflow-x-clip"
        :style="warmThemeStyle"
    >
        <!-- Soft background light / shadow shapes (mirrors Welcome.vue) -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="auth-blob auth-blob-a"></div>
            <div class="auth-blob auth-blob-b"></div>
            <div class="auth-blob auth-blob-c"></div>
        </div>

        <!-- HEADER (floating pill, mirrors Welcome.vue) -->
        <header class="sticky top-0 z-40 px-3 pt-3 sm:px-6 lg:px-10">
            <div class="relative mx-auto max-w-7xl">
                <div class="auth-nav-pill flex items-center justify-between gap-3 py-2 pl-4 pr-2 sm:py-2.5 sm:pl-6 sm:pr-3">
                    <button
                        type="button"
                        class="group flex items-center gap-2.5 rounded-full focus:outline-none"
                        aria-label="Monti Textile home"
                        @click="goHome"
                    >
                        <img src="/images/applogo.png" alt="" class="h-7 w-7 object-contain transition-transform duration-300 group-hover:scale-105 sm:h-8 sm:w-8" />
                        <span class="auth-wordmark text-[1.65rem] leading-none">monti<span class="auth-accent">.</span></span>
                    </button>

                    <nav class="hidden items-center gap-1 md:flex" aria-label="Auth navigation">
                        <Link v-if="!hideHomeLink" href="/" class="auth-nav-link">Home</Link>
                        <slot name="nav" />
                    </nav>

                    <Link
                        href="/"
                        class="auth-btn-dark group inline-flex min-h-11 items-center gap-2 px-5 text-sm font-semibold"
                    >
                        Back to Home
                        <svg class="auth-arrow h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </Link>
                </div>
            </div>
        </header>

        <!-- Card -->
        <main class="relative z-10 mx-auto flex w-full max-w-7xl flex-1 flex-col px-3 sm:px-6 lg:px-10">
            <div class="flex flex-1 items-center justify-center" :class="compact ? 'py-4 sm:py-6' : 'py-8 sm:py-12'">
                <div :class="wide ? 'w-full max-w-4xl' : 'w-full max-w-md'">
                    <div class="auth-card overflow-hidden">
                        <div :class="compact ? 'p-5 sm:p-6' : 'p-6 sm:p-8'">
                            <div class="text-center" :class="compact ? 'mb-5' : 'mb-8'">
                                <p v-if="badge" class="auth-eyebrow mb-3">{{ badge }}</p>
                                <h1 class="auth-title text-3xl font-extrabold tracking-tight sm:text-4xl">
                                    <template v-for="(part, i) in titleParts" :key="i">{{ part }}<span v-if="i < titleParts.length - 1" class="auth-accent">{{ highlight }}</span></template>
                                </h1>
                                <p v-if="subtitle" class="auth-muted mx-auto mt-3 max-w-md text-sm leading-relaxed">
                                    {{ subtitle }}
                                </p>
                            </div>

                            <slot />
                        </div>
                    </div>

                    <p class="auth-muted mt-6 text-center text-xs">
                        © 2026 Monti Textile Manufacturing. All rights reserved.
                    </p>
                </div>
            </div>
        </main>
    </div>

    <div
        v-else
        class="auth-scope relative flex min-h-screen w-full flex-col overflow-y-auto font-sans"
        :data-accent="effectiveAccent"
        style="background-image: url('/images/landingTheme.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed; background-color: #2a2a3e;"
    >
        <div class="absolute inset-0 bg-black/30 backdrop-blur-[1px]"></div>
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-black/10"></div>

        <!-- HEADER (mirrors Welcome.vue) -->
        <header class="sticky top-0 z-20 w-full shrink-0 border-b border-white/10 bg-black/20 backdrop-blur-md">
            <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <button
                    type="button"
                    class="group flex cursor-pointer items-center gap-3"
                    @click="goHome"
                    aria-label="Back to home"
                >
                    <span class="block rounded-lg border border-white/20 bg-white/10 p-1.5 shadow-lg backdrop-blur-md transition-transform duration-300 group-hover:scale-105">
                        <img src="/images/applogo.png" alt="MontiTextile logo" class="h-7 w-7 object-contain sm:h-8 sm:w-8" />
                    </span>
                    <span class="flex flex-col text-left">
                        <span class="text-base font-black uppercase leading-none tracking-tight text-white drop-shadow-md sm:text-lg">
                            Monti<span class="text-blue-400">Textile</span>
                        </span>
                        <span class="mt-0.5 text-[7px] font-bold uppercase tracking-[0.2em] text-slate-300 sm:text-[8px]">
                            Manufacturing ERP
                        </span>
                    </span>
                </button>
                <nav class="flex flex-wrap items-center gap-1.5 sm:gap-3">
                    <slot name="nav">
                        <Link
                            href="/"
                            class="rounded-lg px-2 py-1 text-[9px] font-semibold text-slate-300 transition-all hover:bg-white/5 hover:text-white sm:text-xs"
                        >
                            Home
                        </Link>
                    </slot>
                </nav>
            </div>
        </header>

        <!-- Card -->
        <div class="relative z-10 mx-auto flex w-full max-w-7xl flex-1 flex-col px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex flex-1 items-center justify-center px-0 pb-8 pt-4" :class="compact ? 'py-2 sm:py-3' : ''">
                <div :class="wide ? 'w-full max-w-3xl' : 'w-full max-w-md'">
                    <div class="auth-card overflow-hidden">
                        <div :class="compact ? 'p-5 sm:p-6' : 'p-6 sm:p-8'">
                            <div class="text-center" :class="compact ? 'mb-5' : 'mb-8'">
                                <p v-if="badge" class="auth-badge mb-4">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" :style="{ background: 'var(--auth-pill-dot)' }"></span>
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full" :style="{ background: 'var(--auth-pill-dot)' }"></span>
                                    </span>
                                    {{ badge }}
                                </p>
                                <h1 class="text-3xl font-black tracking-tight text-white drop-shadow-2xl sm:text-4xl">
                                    <template v-for="(part, i) in titleParts" :key="i">{{ part }}<span v-if="i < titleParts.length - 1" class="auth-gradient-word">{{ highlight }}</span></template>
                                </h1>
                                <p v-if="subtitle" class="mx-auto mt-3 max-w-md text-xs leading-relaxed text-slate-300 drop-shadow-md sm:text-sm">
                                    {{ subtitle }}
                                </p>
                            </div>

                            <slot />
                        </div>
                    </div>

                    <p class="mt-6 border-t border-white/10 pt-4 text-center text-[8px] text-slate-500">
                        © 2026 Monti Textile Manufacturing. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

/* ============================================================
   Warm variant — mirrors Welcome.vue's landing design tokens
   ============================================================ */
.auth-warm {
    --auth-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    --auth-shadow-lift: 0 18px 50px rgba(0, 0, 0, 0.12);

    font-family: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
    color: var(--auth-ink);
    background: linear-gradient(145deg, var(--auth-page-start) 0%, var(--auth-page-end) 100%);
    background-attachment: scroll;
}

.auth-warm :focus-visible {
    outline: 2px solid var(--auth-accent);
    outline-offset: 2px;
}

.auth-blob {
    position: absolute;
    border-radius: 9999px;
    filter: blur(80px);
}
.auth-blob-a { top: -8rem; left: -6rem; width: 28rem; height: 28rem; background: color-mix(in srgb, var(--auth-surface) 75%, transparent); }
.auth-blob-b { top: 18rem; right: -8rem; width: 30rem; height: 30rem; background: color-mix(in srgb, var(--auth-accent) 15%, transparent); }
.auth-blob-c { bottom: 10%; left: -10rem; width: 26rem; height: 26rem; background: color-mix(in srgb, var(--auth-accent) 10%, transparent); }

.auth-accent { color: var(--auth-accent); }
.auth-muted { color: var(--auth-muted); }

.auth-wordmark {
    font-weight: 800;
    letter-spacing: -0.04em;
    color: var(--auth-ink);
}

.auth-eyebrow {
    display: block;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--auth-accent-ink);
}

.auth-title {
    line-height: 1.1;
    color: var(--auth-ink);
}

/* Floating pill navbar */
.auth-nav-pill {
    background: var(--auth-surface);
    border-radius: 9999px;
    box-shadow: var(--auth-shadow);
}

.auth-nav-link {
    padding: 0.55rem 1rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--auth-ink);
    transition: background-color 0.2s ease, color 0.2s ease;
}
.auth-nav-link:hover { background: var(--auth-tile); }

/* Buttons */
.auth-btn-dark {
    background: var(--auth-ink);
    color: var(--auth-surface);
    border-radius: 9999px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.auth-btn-dark:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.2);
}
.auth-btn-dark:active { transform: translateY(0); }

.auth-arrow { color: var(--auth-accent); transition: transform 0.2s ease; }
.auth-btn-dark:hover .auth-arrow { transform: translateX(3px); }

/* Surfaces */
.auth-card {
    background: var(--auth-surface);
    border-radius: 28px;
    box-shadow: var(--auth-shadow);
}

/* ---------- Slotted form controls (from child auth pages) ---------- */
.auth-warm :deep(label) {
    color: var(--auth-ink);
}
.auth-warm :deep(.text-slate-700),
.auth-warm :deep(.text-slate-800),
.auth-warm :deep(.text-gray-900) { color: var(--auth-ink) !important; }
.auth-warm :deep(.text-slate-500),
.auth-warm :deep(.text-gray-500) { color: var(--auth-muted) !important; }
.auth-warm :deep(input.auth-input),
.auth-warm :deep(select.auth-input),
.auth-warm :deep(textarea.auth-input) {
    width: 100%;
    background: var(--auth-tile);
    border: 2px solid transparent;
    border-radius: 9999px;
    padding: 0.7rem 1.25rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    color: var(--auth-ink);
    caret-color: var(--auth-ink);
    transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
}
.auth-warm :deep(input.auth-input::placeholder),
.auth-warm :deep(textarea.auth-input::placeholder) {
    color: var(--auth-muted);
}
.auth-warm :deep(input.auth-input:hover),
.auth-warm :deep(select.auth-input:hover),
.auth-warm :deep(textarea.auth-input:hover) {
    background: var(--auth-surface);
}
.auth-warm :deep(input.auth-input:focus),
.auth-warm :deep(select.auth-input:focus),
.auth-warm :deep(textarea.auth-input:focus) {
    outline: none;
    background: var(--auth-surface);
    border-color: var(--auth-accent);
    box-shadow: 0 0 0 4px var(--auth-focus);
}
.auth-warm :deep(select.auth-input option) {
    background-color: var(--auth-surface);
    color: var(--auth-ink);
}
.auth-warm :deep(input.auth-input:-webkit-autofill),
.auth-warm :deep(input.auth-input:-webkit-autofill:hover),
.auth-warm :deep(input.auth-input:-webkit-autofill:focus),
.auth-warm :deep(input.auth-input:-webkit-autofill:active) {
    -webkit-box-shadow: 0 0 0 60px var(--auth-tile) inset !important;
    -webkit-text-fill-color: var(--auth-ink) !important;
    caret-color: var(--auth-ink) !important;
}

.auth-warm :deep(.text-\[\#C2410C\]) { color: var(--auth-accent-ink) !important; }
.auth-warm :deep(.auth-section-title),
.auth-warm :deep(.auth-accent-text) { color: var(--auth-accent-ink); }
.auth-warm :deep(.auth-field-addon) { background: var(--auth-tile); color: var(--auth-ink); }
.auth-warm :deep(.auth-input) { min-height: 2.9rem; }
.auth-warm :deep(.auth-btn-dark) { background: var(--auth-ink); color: var(--auth-surface); }
.auth-warm :deep(.border-slate-200) { border-color: color-mix(in srgb, var(--auth-ink) 12%, transparent); }

.auth-warm :deep(.auth-btn) {
    background: var(--auth-accent);
    color: #fff;
    font-weight: 700;
    border-radius: 9999px;
    box-shadow: 0 10px 24px -8px color-mix(in srgb, var(--auth-accent) 55%, transparent);
    transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
}
.auth-warm :deep(.auth-btn:hover:not(:disabled)) {
    background: color-mix(in srgb, var(--auth-accent) 88%, #111);
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.2);
}
.auth-warm :deep(.auth-btn:active:not(:disabled)) {
    transform: translateY(0);
}
</style>
