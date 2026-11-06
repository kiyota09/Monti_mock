<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { FileText } from 'lucide-vue-next'

defineProps({ stats: Object, recentJobs: Array })
</script>

<template>
    <Head title="Dyeing Bleaching Reports" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="flex items-center gap-4 rounded-3xl bg-gradient-to-r from-blue-700 to-violet-800 p-7 text-white"><FileText class="h-9 w-9"/><div><p class="text-xs font-bold uppercase tracking-widest">Dyeing Department</p><h1 class="mt-1 text-3xl font-black">Bleaching Reports</h1></div></header>
            <section class="grid gap-4 sm:grid-cols-3"><article v-for="metric in [{label:'My bleaching jobs',value:stats?.total},{label:'Completed today',value:stats?.today},{label:'Waiting in queue',value:stats?.pending}]" :key="metric.label" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-sm text-slate-500">{{ metric.label }}</p><p class="mt-2 text-3xl font-black">{{ metric.value ?? 0 }}</p></article></section>
            <section class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="mb-3 text-lg font-black">Recent bleaching jobs</h2><p v-if="!recentJobs?.length" class="text-sm text-slate-500">No bleaching jobs recorded yet.</p><div v-for="job in recentJobs" :key="job.id" class="flex justify-between border-t py-3 text-sm"><span class="font-mono font-bold">{{ job.code }} · {{ job.fabric?.code }}</span><span>{{ job.bleaching_method }}</span></div></section>
        </main>
    </AuthenticatedLayout>
</template>
