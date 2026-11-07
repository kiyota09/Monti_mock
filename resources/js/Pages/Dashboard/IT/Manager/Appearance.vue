<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Check, Eye, Palette, Save, ShieldCheck } from 'lucide-vue-next';
import { route } from 'ziggy-js';

type ThemeKey = 'light' | 'dark' | 'blue' | 'red' | 'green';

const props = defineProps<{
    enabledThemes: ThemeKey[];
    themeUsage: Record<string, number>;
}>();

const page = usePage();
const form = useForm({ enabledThemes: [...props.enabledThemes] as ThemeKey[] });
const themes: Array<{ key: ThemeKey; name: string; description: string; color: string; surface: string; text: string }> = [
    { key: 'light', name: 'Light', description: 'Clean, bright surfaces with neutral contrast.', color: '#64748b', surface: '#f8fafc', text: '#0f172a' },
    { key: 'dark', name: 'Dark', description: 'Low light surfaces for a darker workspace.', color: '#18181b', surface: '#27272a', text: '#f4f4f5' },
    { key: 'blue', name: 'Blue', description: 'Cool ice blue and navy across the ERP.', color: '#2563eb', surface: '#eff6ff', text: '#172554' },
    { key: 'red', name: 'Red', description: 'Rose and wine accents across the ERP.', color: '#e11d48', surface: '#fff1f2', text: '#4c0519' },
    { key: 'green', name: 'Green', description: 'Soft sage and eucalyptus across the ERP.', color: '#40916c', surface: '#ecfdf5', text: '#064e3b' },
];

watch(() => props.enabledThemes, (enabled) => { form.enabledThemes = [...enabled]; }, { deep: true });
const enabledCount = computed(() => form.enabledThemes.length);
const changed = computed(() => themes.some((theme) => props.enabledThemes.includes(theme.key) !== form.enabledThemes.includes(theme.key)));
const fallbackTheme = computed(() => form.enabledThemes.includes('light') ? 'Light' : themes.find((theme) => form.enabledThemes.includes(theme.key))?.name || 'Light');

const toggleTheme = (theme: ThemeKey) => {
    const enabled = form.enabledThemes.includes(theme);
    if (enabled && form.enabledThemes.length <= 1) return;
    form.enabledThemes = enabled
        ? form.enabledThemes.filter((key) => key !== theme)
        : themes.filter((item) => form.enabledThemes.includes(item.key) || item.key === theme).map((item) => item.key);
};

const save = () => {
    if (!form.enabledThemes.length || !changed.value || form.processing) return;
    form.post(route('it.appearance.update'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Appearance | IT Systems" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl space-y-6 pb-12">
            <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-blue-900 p-6 text-white shadow-xl sm:p-8">
                <div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-blue-400/20 blur-3xl" />
                <div class="relative flex flex-wrap items-center gap-4">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20"><Palette class="h-7 w-7" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-200">IT systems control</p>
                        <h1 class="mt-1 text-2xl font-black sm:text-3xl">Appearance</h1>
                        <p class="mt-1 max-w-2xl text-sm text-blue-100/80">Manage which color themes people can use throughout Monti ERP.</p>
                    </div>
                    <div class="rounded-2xl bg-white/10 px-4 py-3 text-center ring-1 ring-white/15">
                        <p class="text-2xl font-black">{{ enabledCount }}</p>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-blue-100">themes enabled</p>
                    </div>
                </div>
            </header>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 dark:text-white">Available color themes</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">At least one theme must remain enabled.</p>
                    </div>
                    <button type="button" @click="save" :disabled="form.processing || !changed || !form.enabledThemes.length"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <Save class="h-4 w-4" />{{ form.processing ? 'Saving…' : 'Save appearance settings' }}
                    </button>
                </div>

                <p v-if="page.props.flash?.success" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200">{{ page.props.flash.success }}</p>
                <p v-if="form.errors.enabledThemes" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">{{ form.errors.enabledThemes }}</p>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="theme in themes" :key="theme.key"
                        class="overflow-hidden rounded-2xl border transition"
                        :class="form.enabledThemes.includes(theme.key) ? 'border-indigo-200 shadow-sm dark:border-indigo-900/60' : 'border-slate-200 opacity-75 dark:border-zinc-800'">
                        <div class="p-4">
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="h-10 w-10 shrink-0 rounded-xl ring-1 ring-black/10" :style="{ backgroundColor: theme.color }" />
                                    <div class="min-w-0">
                                        <h3 class="font-black text-slate-900 dark:text-white">{{ theme.name }}</h3>
                                        <p class="text-[10px] font-bold uppercase tracking-wider" :class="form.enabledThemes.includes(theme.key) ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-400'">
                                            {{ form.enabledThemes.includes(theme.key) ? 'Available' : 'Disabled' }}
                                        </p>
                                    </div>
                                </div>
                                <button type="button" role="switch" :aria-checked="form.enabledThemes.includes(theme.key)" :aria-label="`${form.enabledThemes.includes(theme.key) ? 'Disable' : 'Enable'} ${theme.name} theme`"
                                    :disabled="form.enabledThemes.includes(theme.key) && enabledCount <= 1" @click="toggleTheme(theme.key)"
                                    class="relative h-7 w-12 shrink-0 rounded-full transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="form.enabledThemes.includes(theme.key) ? 'bg-emerald-600' : 'bg-slate-300 dark:bg-zinc-700'">
                                    <span class="absolute top-1 flex h-5 w-5 items-center justify-center rounded-full bg-white shadow transition-all" :class="form.enabledThemes.includes(theme.key) ? 'left-6' : 'left-1'"><Check v-if="form.enabledThemes.includes(theme.key)" class="h-3 w-3 text-emerald-700" /></span>
                                </button>
                            </div>

                            <div class="overflow-hidden rounded-xl border border-slate-200 p-3 dark:border-zinc-700" :style="{ backgroundColor: theme.surface, color: theme.text }">
                                <div class="flex items-center gap-2"><Eye class="h-3.5 w-3.5" :style="{ color: theme.color }" /><span class="text-[10px] font-black uppercase tracking-widest">Preview</span><span class="ml-auto h-2 w-2 rounded-full" :style="{ backgroundColor: theme.color }" /></div>
                                <div class="mt-3 flex items-center gap-2">
                                    <span class="h-7 w-7 rounded-lg" :style="{ backgroundColor: theme.color }" />
                                    <div class="flex-1"><span class="block h-1.5 w-20 rounded-full opacity-80" :style="{ backgroundColor: theme.text }" /><span class="mt-1.5 block h-1 w-28 rounded-full opacity-40" :style="{ backgroundColor: theme.text }" /></div>
                                    <span class="rounded-lg px-2 py-1 text-[9px] font-black text-white" :style="{ backgroundColor: theme.color }">Action</span>
                                </div>
                            </div>
                            <p class="mt-3 min-h-10 text-xs leading-relaxed text-slate-500 dark:text-zinc-400">{{ theme.description }}</p>
                            <p class="mt-2 flex items-center gap-1.5 text-[10px] font-semibold text-slate-400"><ShieldCheck class="h-3.5 w-3.5" />{{ themeUsage[theme.key] || 0 }} saved account preference{{ (themeUsage[theme.key] || 0) === 1 ? '' : 's' }}</p>
                        </div>
                    </article>
                </div>

                <div class="mt-5 flex items-start gap-3 rounded-2xl border border-blue-100 bg-blue-50 p-4 text-blue-900 dark:border-blue-900/40 dark:bg-blue-950/30 dark:text-blue-100">
                    <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-300" />
                    <p class="text-xs leading-relaxed"><strong>Automatic fallback:</strong> when a theme is disabled, saved account preferences and browser choices move to {{ fallbackTheme }} when available, otherwise to the first enabled theme.</p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
