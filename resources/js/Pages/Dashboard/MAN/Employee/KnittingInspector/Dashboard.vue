<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowRight, CheckCircle2, ClipboardCheck, Clock3, RotateCcw } from 'lucide-vue-next';

defineProps({
    stats: { type: Object, default: () => ({}) },
    recent: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Knitting Inspector Dashboard" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-gradient-to-r from-blue-800 to-indigo-700 p-6 text-white shadow-lg">
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-blue-100">Knitting Department</p>
                    <h1 class="mt-1 text-2xl font-black">Knitting Inspector Dashboard</h1>
                    <p class="mt-1 text-sm text-blue-100">Review finished knitting output before it moves to Dyeing.</p>
                </div>
                <Link :href="route('man.staff.knitting-inspector.page')" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-black text-blue-800 shadow hover:bg-blue-50">
                    Open Inspection Queue <ArrowRight class="h-4 w-4" />
                </Link>
            </header>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><Clock3 class="h-4 w-4" /> Waiting inspection</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.waiting ?? 0 }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><CheckCircle2 class="h-4 w-4" /> Approved by you</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.approved ?? 0 }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><RotateCcw class="h-4 w-4" /> Returned by you</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.returned ?? 0 }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="flex items-center gap-2 text-sm font-semibold text-slate-500"><ClipboardCheck class="h-4 w-4" /> Decisions today</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ stats.today ?? 0 }}</p></article>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-zinc-800"><div><h2 class="font-black text-slate-900 dark:text-white">Your recent decisions</h2><p class="mt-1 text-sm text-slate-500">Recent inspection approvals and returns.</p></div><Link :href="route('man.staff.knitting-inspector.history')" class="text-sm font-bold text-blue-700 hover:underline dark:text-blue-300">View history</Link></div>
                <div v-if="!recent.length" class="p-8 text-center text-sm text-slate-500">No inspection decisions recorded yet.</div>
                <div v-for="item in recent" :key="item.id" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 last:border-0 dark:border-zinc-800">
                    <div><p class="font-mono text-sm font-black text-slate-800 dark:text-white">{{ item.fabric?.code || `Fabric #${item.fabric_id}` }}</p><p class="text-sm text-slate-500">{{ item.fabric?.yarn_type || 'Knitting output' }} · {{ item.fabric?.weight ?? '—' }} kg</p></div>
                    <div class="text-right"><span :class="item.result === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'" class="rounded-full px-2.5 py-1 text-xs font-black uppercase">{{ item.result }}</span><p class="mt-1 text-xs text-slate-500">{{ new Date(item.created_at).toLocaleString() }}</p></div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
