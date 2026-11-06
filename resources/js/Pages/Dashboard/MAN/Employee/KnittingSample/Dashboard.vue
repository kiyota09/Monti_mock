<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowRight, CheckCircle2, Clock3, Scissors, Waves } from 'lucide-vue-next';

defineProps({
    stats: { type: Object, default: () => ({}) },
    nextRequests: { type: Array, default: () => [] },
    myRecent: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Knitting Sample Dashboard" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-gradient-to-r from-teal-800 to-emerald-600 p-6 text-white shadow-lg">
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-teal-100">Knitting Department</p>
                    <h1 class="mt-1 text-2xl font-black">Knitting Sample Dashboard</h1>
                    <p class="mt-1 text-sm text-teal-50">Your daily sample production overview.</p>
                </div>
                <Link :href="route('man.staff.knitting-sample.page')" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-black text-teal-800 shadow hover:bg-teal-50">
                    Open Sample Queue <ArrowRight class="h-4 w-4" />
                </Link>
            </header>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><Waves class="h-4 w-4" /> Waiting in queue</p>
                    <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.waiting ?? 0 }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><Clock3 class="h-4 w-4" /> In progress</p>
                    <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.in_progress ?? 0 }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><CheckCircle2 class="h-4 w-4" /> Your samples today</p>
                    <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.my_completed_today ?? 0 }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><Scissors class="h-4 w-4" /> Your completed samples</p>
                    <p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.my_completed_total ?? 0 }}</p>
                </article>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-zinc-800">
                        <div><h2 class="font-black text-slate-900 dark:text-white">Next sample requests</h2><p class="mt-1 text-sm text-slate-500">Oldest requests waiting to be started.</p></div>
                        <Link :href="route('man.staff.knitting-sample.page')" class="text-sm font-bold text-teal-700 hover:underline dark:text-teal-300">View queue</Link>
                    </div>
                    <div v-if="!nextRequests.length" class="p-8 text-center text-sm text-slate-500">No requests are waiting.</div>
                    <div v-for="item in nextRequests" :key="item.id" class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 last:border-0 dark:border-zinc-800">
                        <div class="min-w-0"><p class="font-mono text-sm font-black text-teal-700 dark:text-teal-300">{{ item.code }}</p><p class="truncate text-sm text-slate-600 dark:text-zinc-300">{{ item.fabric_design || item.fabric_sample_request?.fabric_name || 'Sample fabric' }}</p></div>
                        <span class="shrink-0 text-xs font-semibold text-slate-500">{{ item.requested_weight }} kg</span>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-zinc-800">
                        <div><h2 class="font-black text-slate-900 dark:text-white">Your recent completions</h2><p class="mt-1 text-sm text-slate-500">Latest samples recorded under your account.</p></div>
                        <Link :href="route('man.staff.knitting-sample.history')" class="text-sm font-bold text-teal-700 hover:underline dark:text-teal-300">History</Link>
                    </div>
                    <div v-if="!myRecent.length" class="p-8 text-center text-sm text-slate-500">You have no completed samples yet.</div>
                    <div v-for="item in myRecent" :key="item.id" class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 last:border-0 dark:border-zinc-800">
                        <div class="min-w-0"><p class="font-mono text-sm font-black text-slate-800 dark:text-white">{{ item.code }}</p><p class="truncate text-sm text-slate-600 dark:text-zinc-300">{{ item.fabric_design || item.fabric_sample_request?.fabric_name || 'Sample fabric' }}</p></div>
                        <span class="shrink-0 text-xs font-semibold text-slate-500">{{ item.processed_at ? new Date(item.processed_at).toLocaleDateString() : '—' }}</span>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
