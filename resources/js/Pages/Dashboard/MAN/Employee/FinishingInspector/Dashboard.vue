<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ClipboardCheck, ClipboardList, FileText } from 'lucide-vue-next'

defineProps({ stats: Object })
</script>

<template>
    <Head title="Finishing Shrinkage Inspector Dashboard" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6"><header class="rounded-3xl bg-gradient-to-r from-cyan-700 to-blue-800 p-7 text-white"><p class="text-xs font-bold uppercase tracking-widest">Finishing Department</p><h1 class="mt-2 text-3xl font-black">Shrinkage Inspector Dashboard</h1><p class="mt-2 text-sm text-cyan-100">Review squeezer and heatsetting quality gates.</p></header>
            <section class="grid gap-4 sm:grid-cols-2"><article v-for="metric in [{label:'Squeezer jobs awaiting inspection',value:stats?.squeezer_pending},{label:'Heatsetting jobs awaiting inspection',value:stats?.iron_pending}]" :key="metric.label" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-sm text-slate-500">{{ metric.label }}</p><p class="mt-2 text-3xl font-black">{{ metric.value ?? 0 }}</p></article></section>
            <nav class="flex flex-wrap gap-3"><Link :href="route('man.staff.finishing-inspector.page')" class="inline-flex items-center gap-2 rounded-xl bg-cyan-700 px-4 py-3 font-bold text-white"><ClipboardCheck class="h-4 w-4"/> Open inspection</Link><Link :href="route('man.staff.finishing-inspector.history')" class="inline-flex items-center gap-2 rounded-xl border px-4 py-3 font-bold"><ClipboardList class="h-4 w-4"/> History</Link><Link :href="route('man.staff.finishing-inspector.reports')" class="inline-flex items-center gap-2 rounded-xl border px-4 py-3 font-bold"><FileText class="h-4 w-4"/> Reports</Link></nav>
        </main>
    </AuthenticatedLayout>
</template>
