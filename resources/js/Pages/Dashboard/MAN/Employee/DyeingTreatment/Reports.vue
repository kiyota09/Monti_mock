<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { FileText } from 'lucide-vue-next'

defineProps({ treatmentLabel: String, stats: Object })
</script>

<template>
    <Head :title="`${treatmentLabel} Reports`" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="flex items-center gap-4 rounded-3xl bg-gradient-to-r from-indigo-700 to-violet-700 p-7 text-white"><FileText class="h-9 w-9"/><div><p class="text-xs font-bold uppercase tracking-widest">Dyeing Department</p><h1 class="mt-1 text-3xl font-black">{{ treatmentLabel }} Reports</h1></div></header>
            <section class="grid gap-4 sm:grid-cols-3"><article v-for="metric in [{label:'Fabrics at this stage',value:stats?.in_stage},{label:'Updated today',value:stats?.updated_today},{label:'Total fabric records',value:stats?.total_fabrics}]" :key="metric.label" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-sm text-slate-500">{{ metric.label }}</p><p class="mt-2 text-3xl font-black">{{ metric.value ?? 0 }}</p></article></section>
            <p class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900 dark:border-indigo-900 dark:bg-indigo-950 dark:text-indigo-100">These figures describe the current fabric stage. Treatment actions are not yet recorded as separate job events.</p>
        </main>
    </AuthenticatedLayout>
</template>
