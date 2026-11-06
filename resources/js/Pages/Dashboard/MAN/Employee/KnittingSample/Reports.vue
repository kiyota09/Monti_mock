<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ClipboardList, Scissors, Weight, CalendarDays } from 'lucide-vue-next';

defineProps({
    stats: { type: Object, default: () => ({}) },
    recent: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Knitting Sample Reports" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-teal-700 to-emerald-600 p-6 text-white shadow-lg">
                <p class="text-xs font-black uppercase tracking-widest text-teal-100">Knitting Department</p>
                <h1 class="mt-1 text-2xl font-black">Knitting Sample Reports</h1>
                <p class="mt-1 text-sm text-teal-50">Your completed sample production and output.</p>
            </header>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><ClipboardList class="h-4 w-4" /> Completed samples</p>
                    <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.completed ?? 0 }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><CalendarDays class="h-4 w-4" /> Completed today</p>
                    <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.completed_today ?? 0 }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><Scissors class="h-4 w-4" /> In progress</p>
                    <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.in_progress ?? 0 }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><Weight class="h-4 w-4" /> Total produced</p>
                    <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ Number(stats.produced_weight ?? 0).toFixed(3) }} <span class="text-base">kg</span></p>
                </article>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-slate-100 p-5 dark:border-zinc-800">
                    <h2 class="font-black text-slate-900 dark:text-white">Recent completed samples</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ stats.completed_this_week ?? 0 }} completed this week</p>
                </div>
                <div v-if="!recent.length" class="p-8 text-center text-sm text-slate-500">No completed samples to report yet.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-800/60">
                            <tr><th class="px-5 py-3">Sample</th><th class="px-5 py-3">Fabric</th><th class="px-5 py-3">Machine</th><th class="px-5 py-3">Output</th><th class="px-5 py-3">Completed</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                            <tr v-for="item in recent" :key="item.id">
                                <td class="px-5 py-3 font-mono font-bold text-teal-700 dark:text-teal-300">{{ item.code }}</td>
                                <td class="px-5 py-3 text-slate-700 dark:text-zinc-200">{{ item.fabric_design || item.fabric_sample_request?.fabric_name || '—' }}</td>
                                <td class="px-5 py-3 text-slate-700 dark:text-zinc-200">{{ item.machine?.machine_no || '—' }}</td>
                                <td class="px-5 py-3 font-semibold text-slate-700 dark:text-zinc-200">{{ item.produced_weight ?? '—' }} kg</td>
                                <td class="px-5 py-3 text-slate-500">{{ item.processed_at ? new Date(item.processed_at).toLocaleString() : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
