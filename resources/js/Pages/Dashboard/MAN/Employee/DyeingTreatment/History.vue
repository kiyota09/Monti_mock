<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({ treatmentLabel: String, routePrefix: String, records: Object })
</script>

<template>
    <Head :title="`${treatmentLabel} History`" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-5 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-indigo-700 to-violet-700 p-7 text-white"><p class="text-xs font-bold uppercase tracking-widest">Dyeing Department</p><h1 class="mt-2 text-3xl font-black">{{ treatmentLabel }} History</h1><p class="mt-2 text-sm text-indigo-100">Treatment work recorded under your staff account.</p></header>
            <div class="overflow-hidden rounded-2xl border bg-white dark:border-zinc-800 dark:bg-zinc-900"><div v-if="!records?.data?.length" class="p-8 text-center text-sm text-slate-500">No treatment work recorded yet.</div><div v-for="record in records.data" :key="record.id" class="flex flex-wrap justify-between gap-2 border-b p-4 last:border-0 dark:border-zinc-800"><div><p class="font-mono font-bold">{{ record.batch_fabric?.fabric?.code || record.code }}</p><p class="text-sm text-slate-500">{{ record.batch_fabric?.fabric?.yarn_type }} · {{ record.batch_fabric?.fabric?.weight }} kg · Attempt {{ record.attempt }}</p><p class="text-xs text-slate-500">Staff: {{ record.operator?.name || 'Unknown staff' }}</p></div><div class="text-right text-sm"><p class="font-semibold">{{ String(record.review_status || 'recorded').replaceAll('_', ' ') }}</p><p class="text-slate-500">{{ record.processed_at ? new Date(record.processed_at).toLocaleString() : '—' }}</p></div></div></div>
            <nav v-if="records?.links" class="flex flex-wrap gap-2"><Link v-for="link in records.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['rounded-lg border px-3 py-2 text-sm', link.active ? 'bg-indigo-700 text-white' : '']" /></nav>
        </main>
    </AuthenticatedLayout>
</template>
