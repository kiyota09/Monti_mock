<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { FileText } from 'lucide-vue-next'

defineProps({ stationLabel: String })
</script>

<template>
    <Head :title="`${stationLabel} Reports`" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-5 p-4 sm:p-6"><header class="flex items-center gap-4 rounded-3xl bg-gradient-to-r from-cyan-700 to-blue-700 p-7 text-white"><FileText class="h-9 w-9"/><div><p class="text-xs font-bold uppercase tracking-widest">Finishing Department</p><h1 class="mt-1 text-3xl font-black">{{ stationLabel }} Reports</h1></div></header><section class="rounded-2xl border border-cyan-200 bg-cyan-50 p-5 text-sm text-cyan-950 dark:border-cyan-900 dark:bg-cyan-950 dark:text-cyan-100">Reports will populate when this station records job activity. No station-specific operation records exist yet.</section></main>
    </AuthenticatedLayout>
</template>
