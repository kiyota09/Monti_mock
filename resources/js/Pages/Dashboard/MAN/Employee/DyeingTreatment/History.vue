<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({ treatmentLabel: String, routePrefix: String, fabrics: Object })
</script>

<template>
    <Head :title="`${treatmentLabel} History`" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-5 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-indigo-700 to-violet-700 p-7 text-white"><p class="text-xs font-bold uppercase tracking-widest">Dyeing Department</p><h1 class="mt-2 text-3xl font-black">{{ treatmentLabel }} History</h1><p class="mt-2 text-sm text-indigo-100">Recently updated fabric records for this production stage.</p></header>
            <div class="overflow-hidden rounded-2xl border bg-white dark:border-zinc-800 dark:bg-zinc-900"><div v-if="!fabrics?.data?.length" class="p-8 text-center text-sm text-slate-500">No fabric records found.</div><div v-for="fabric in fabrics.data" :key="fabric.id" class="flex flex-wrap justify-between gap-2 border-b p-4 last:border-0 dark:border-zinc-800"><div><p class="font-mono font-bold">{{ fabric.code }}</p><p class="text-sm text-slate-500">{{ fabric.yarn_type }} · {{ fabric.weight }} kg</p></div><div class="text-right text-sm"><p class="font-semibold">{{ fabric.status }}</p><p class="text-slate-500">{{ fabric.updated_at }}</p></div></div></div>
            <nav v-if="fabrics?.links" class="flex flex-wrap gap-2"><Link v-for="link in fabrics.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['rounded-lg border px-3 py-2 text-sm', link.active ? 'bg-indigo-700 text-white' : '']" /></nav>
        </main>
    </AuthenticatedLayout>
</template>
