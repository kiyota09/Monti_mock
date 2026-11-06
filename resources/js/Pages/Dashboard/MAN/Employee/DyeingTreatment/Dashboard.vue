<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ClipboardList, FileText, Waves } from 'lucide-vue-next'

defineProps({ treatmentLabel: String, routePrefix: String, stats: Object, recent: Array })
</script>

<template>
    <Head :title="`${treatmentLabel} Dashboard`" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-indigo-700 to-violet-700 p-7 text-white"><p class="text-xs font-bold uppercase tracking-widest">Dyeing Department</p><h1 class="mt-2 text-3xl font-black">{{ treatmentLabel }} Dashboard</h1><p class="mt-2 text-sm text-indigo-100">Stage workload and recently updated fabrics.</p></header>
            <section class="grid gap-4 sm:grid-cols-2"><article class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-sm text-slate-500">Fabrics at your stage</p><p class="mt-2 text-3xl font-black">{{ stats?.waiting ?? 0 }}</p></article><article class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-sm text-slate-500">Updated today</p><p class="mt-2 text-3xl font-black">{{ stats?.updated_today ?? 0 }}</p></article></section>
            <nav class="flex flex-wrap gap-3"><Link :href="route(`${routePrefix}.page`)" class="inline-flex items-center gap-2 rounded-xl bg-indigo-700 px-4 py-3 font-bold text-white"><Waves class="h-4 w-4"/> Open work page</Link><Link :href="route(`${routePrefix}.history`)" class="inline-flex items-center gap-2 rounded-xl border px-4 py-3 font-bold"><ClipboardList class="h-4 w-4"/> History</Link><Link :href="route(`${routePrefix}.reports`)" class="inline-flex items-center gap-2 rounded-xl border px-4 py-3 font-bold"><FileText class="h-4 w-4"/> Reports</Link></nav>
            <section class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="mb-3 text-lg font-black">Recent fabrics at this stage</h2><p v-if="!recent?.length" class="text-sm text-slate-500">No fabrics are currently at this stage.</p><div v-for="fabric in recent" :key="fabric.id" class="flex justify-between border-t py-3 text-sm"><span class="font-mono font-bold">{{ fabric.code }}</span><span class="text-slate-500">{{ fabric.status }} · {{ fabric.updated_at }}</span></div></section>
        </main>
    </AuthenticatedLayout>
</template>
