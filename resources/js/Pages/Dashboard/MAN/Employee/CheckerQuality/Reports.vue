<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { FileText } from 'lucide-vue-next'

defineProps({ stats: Object })
</script>

<template>
    <Head title="Finishing Quality Reports" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6"><header class="flex items-center gap-4 rounded-3xl bg-gradient-to-r from-emerald-700 to-teal-800 p-7 text-white"><FileText class="h-9 w-9"/><div><p class="text-xs font-bold uppercase tracking-widest">Finishing Department</p><h1 class="mt-1 text-3xl font-black">Quality Check Reports</h1></div></header>
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><article v-for="metric in [{label:'Packages checked',value:stats?.checked},{label:'Approved',value:stats?.approved},{label:'Rejected',value:stats?.rejected},{label:'Checked today',value:stats?.today}]" :key="metric.label" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-sm text-slate-500">{{ metric.label }}</p><p class="mt-2 text-3xl font-black">{{ metric.value ?? 0 }}</p></article></section>
        </main>
    </AuthenticatedLayout>
</template>
