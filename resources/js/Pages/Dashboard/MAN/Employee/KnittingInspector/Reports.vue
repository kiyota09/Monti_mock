<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CheckCircle2, ClipboardCheck, RotateCcw, TrendingUp } from 'lucide-vue-next';

defineProps({ stats: { type: Object, default: () => ({}) }, recent: { type: Array, default: () => [] } });
</script>

<template>
    <Head title="Knitting Inspector Reports" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-blue-800 to-indigo-700 p-6 text-white shadow-lg"><p class="text-xs font-black uppercase tracking-widest text-blue-100">Knitting Department</p><h1 class="mt-1 text-2xl font-black">Knitting Inspector Reports</h1><p class="mt-1 text-sm text-blue-100">Your inspection decisions and approval rate.</p></header>
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><ClipboardCheck class="h-4 w-4"/> Total decisions</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.total ?? 0 }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><CheckCircle2 class="h-4 w-4"/> Approved</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.approved ?? 0 }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><RotateCcw class="h-4 w-4"/> Returned</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.returned ?? 0 }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><TrendingUp class="h-4 w-4"/> Approval rate</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.approval_rate ?? 0 }}%</p><p class="mt-1 text-xs text-slate-500">{{ stats.today ?? 0 }} decisions today</p></article>
            </section>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-slate-100 p-5 dark:border-zinc-800"><h2 class="font-black text-slate-900 dark:text-white">Recent inspection activity</h2><p class="mt-1 text-sm text-slate-500">Latest decisions recorded under your account.</p></div>
                <div v-if="!recent.length" class="p-8 text-center text-sm text-slate-500">No inspection data to report yet.</div>
                <div v-else class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-800/60"><tr><th class="px-5 py-3">Fabric</th><th class="px-5 py-3">Decision</th><th class="px-5 py-3">Details</th><th class="px-5 py-3">Date</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-zinc-800"><tr v-for="item in recent" :key="item.id"><td class="px-5 py-3 font-mono font-bold text-slate-800 dark:text-white">{{ item.fabric?.code || `Fabric #${item.fabric_id}` }}</td><td class="px-5 py-3 font-bold capitalize" :class="item.result === 'approved' ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'">{{ item.result }}</td><td class="px-5 py-3 text-slate-600 dark:text-zinc-300">{{ item.result === 'approved' ? `Passed to ${item.destination || 'Dyeing'}` : (item.rejection_reason || 'Returned for remake') }}</td><td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ new Date(item.created_at).toLocaleString() }}</td></tr></tbody></table></div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
