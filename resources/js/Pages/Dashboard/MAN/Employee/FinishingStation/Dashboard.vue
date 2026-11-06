<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ClipboardList, FileText, Waves } from 'lucide-vue-next'

defineProps({ stationLabel: String, routePrefix: String })
</script>

<template>
    <Head :title="`${stationLabel} Dashboard`" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-cyan-700 to-blue-700 p-7 text-white"><p class="text-xs font-bold uppercase tracking-widest">Finishing Department</p><h1 class="mt-2 text-3xl font-black">{{ stationLabel }} Dashboard</h1><p class="mt-2 text-sm text-cyan-100">Station overview and quick links.</p></header>
            <section class="rounded-2xl border border-cyan-200 bg-cyan-50 p-5 text-sm text-cyan-950 dark:border-cyan-900 dark:bg-cyan-950 dark:text-cyan-100">This station is available in the staff workspace. Station-specific job activity is not currently recorded in the manufacturing workflow.</section>
            <nav class="flex flex-wrap gap-3"><Link :href="route(`${routePrefix}.page`)" class="inline-flex items-center gap-2 rounded-xl bg-cyan-700 px-4 py-3 font-bold text-white"><Waves class="h-4 w-4"/> Open work page</Link><Link :href="route(`${routePrefix}.history`)" class="inline-flex items-center gap-2 rounded-xl border px-4 py-3 font-bold"><ClipboardList class="h-4 w-4"/> History</Link><Link :href="route(`${routePrefix}.reports`)" class="inline-flex items-center gap-2 rounded-xl border px-4 py-3 font-bold"><FileText class="h-4 w-4"/> Reports</Link></nav>
        </main>
    </AuthenticatedLayout>
</template>
