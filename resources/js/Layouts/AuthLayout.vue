<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useTheme, initTheme } from '@/composables/useTheme';

const props = withDefaults(
    defineProps<{
        title: string;
        highlight?: string;
        subtitle?: string;
        badge?: string;
        accent?: 'blue' | 'sky' | 'emerald' | 'red';
        wide?: boolean;
    }>(),
    {
        highlight: '',
        subtitle: '',
        badge: '',
        accent: 'blue',
        wide: false,
    },
);

// Logged-out pages honor the stored user theme; with no stored choice they
// fall back to the OS/browser theme (see useTheme.initTheme).
const { currentTheme } = useTheme();
onMounted(() => {
    initTheme();
});

// The auth accent follows the user's color theme when one is set
// (blue/red/green); otherwise it keeps the page's own accent prop.
const THEME_ACCENT: Record<string, 'blue' | 'sky' | 'emerald' | 'red'> = {
    blue: 'blue',
    red: 'red',
    green: 'emerald',
};
const effectiveAccent = computed(() => THEME_ACCENT[currentTheme.value] ?? props.accent);

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
            <div class="flex flex-1 items-center justify-center px-0 pb-8 pt-4">
                <div :class="wide ? 'w-full max-w-3xl' : 'w-full max-w-md'">
                    <div class="auth-card overflow-hidden">
                        <div class="p-6 sm:p-8">
                            <div class="mb-8 text-center">
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
