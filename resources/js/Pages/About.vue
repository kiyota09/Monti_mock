<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Info, Layers, Cpu, Palette } from 'lucide-vue-next';

defineProps({
    laravel: { type: String, default: '' },
    php: { type: String, default: '' },
    modules: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="About" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-5xl space-y-5 p-4 pb-16 sm:p-6">

                <!-- Hero -->
                <div class="relative overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-xl sm:p-7">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800" />
                    <div class="absolute -top-20 -right-16 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white/15 p-2 ring-1 ring-white/30">
                            <img src="/images/applogo.png" alt="MontiTextile logo" class="h-full w-full object-contain brightness-0 invert" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Info class="h-3.5 w-3.5" /> About this system
                            </p>
                            <h1 class="mt-0.5 text-2xl font-extrabold tracking-tight sm:text-3xl">MontiTextile Manufacturing ERP</h1>
                            <p class="mt-0.5 text-sm text-blue-100/90">One workspace for the entire manufacturing lifecycle — from raw weaving to global distribution.</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <!-- Runtime -->
                    <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                        <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                            <Cpu class="h-4 w-4 text-indigo-500" /> Runtime
                        </h2>
                        <dl class="mt-4 space-y-3">
                            <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">Framework</dt>
                                <dd class="font-mono text-sm font-black text-slate-800 dark:text-zinc-100">Laravel {{ laravel }}</dd>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">PHP</dt>
                                <dd class="font-mono text-sm font-black text-slate-800 dark:text-zinc-100">{{ php }}</dd>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">Frontend</dt>
                                <dd class="font-mono text-sm font-black text-slate-800 dark:text-zinc-100">Vue 3 · Inertia</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Personalization -->
                    <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                        <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                            <Palette class="h-4 w-4 text-indigo-500" /> Personalization
                        </h2>
                        <p class="mt-4 text-sm leading-relaxed text-slate-500 dark:text-slate-300">
                            Pick a color theme from the gear menu in the top navigation bar — Light, Dark, Blue,
                            Red or Green. Your choice is remembered on each device, and new devices follow your
                            browser's own light/dark setting until you pick one.
                        </p>
                        <p class="mt-3 text-sm leading-relaxed text-slate-500 dark:text-slate-300">
                            Need a hand? Everything support-related lives in the
                            <span class="font-black text-indigo-600">Help Center</span>, right above this page in
                            the same Settings menu.
                        </p>
                    </div>
                </div>

                <!-- Modules -->
                <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                    <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                        <Layers class="h-4 w-4 text-indigo-500" /> Modules ({{ modules.length }})
                    </h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <div v-for="m in modules" :key="m.key"
                            class="rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 text-center dark:border-zinc-800 dark:bg-zinc-800/50">
                            <p class="font-mono text-xs font-black text-indigo-600">{{ m.key }}</p>
                            <p class="mt-0.5 truncate text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ m.label }}</p>
                        </div>
                    </div>
                    <p class="mt-4 border-t border-gray-100 pt-4 text-center text-[11px] text-slate-400 dark:border-zinc-800">
                        © 2026 Monti Textile Manufacturing. All rights reserved.
                    </p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
