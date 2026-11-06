<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({ stationLabel: String })
</script>

<template>
    <Head :title="`${stationLabel} History`" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-5 p-4 sm:p-6"><header class="rounded-3xl bg-gradient-to-r from-cyan-700 to-blue-700 p-7 text-white"><p class="text-xs font-bold uppercase tracking-widest">Finishing Department</p><h1 class="mt-2 text-3xl font-black">{{ stationLabel }} History</h1></header><section class="rounded-2xl border p-8 text-center text-sm text-slate-600 dark:border-zinc-800 dark:text-slate-300">No station job events are recorded yet. This role currently has a workspace page, but no station operation action is connected to the job history.</section></main>
    </AuthenticatedLayout>
</template>
