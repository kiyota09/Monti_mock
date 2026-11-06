<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({ packages: Object })
</script>

<template>
    <Head title="Finishing Quality Check History" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-5 p-4 sm:p-6"><header class="rounded-3xl bg-gradient-to-r from-emerald-700 to-teal-800 p-7 text-white"><p class="text-xs font-bold uppercase tracking-widest">Finishing Department</p><h1 class="mt-2 text-3xl font-black">Quality Check History</h1></header>
            <section class="overflow-hidden rounded-2xl border bg-white dark:border-zinc-800 dark:bg-zinc-900"><p v-if="!packages?.data?.length" class="p-8 text-center text-sm text-slate-500">No package checks recorded for your account.</p><div v-for="pkg in packages.data" :key="pkg.id" class="flex flex-wrap justify-between gap-3 border-b p-4 last:border-0 dark:border-zinc-800"><div><p class="font-mono font-bold">{{ pkg.code }} · {{ pkg.fabric?.code }}</p><p class="text-sm text-slate-500">Packed by {{ pkg.operator?.name ?? 'Unknown' }}</p></div><div class="text-right text-sm"><p class="font-bold uppercase">{{ pkg.status }}</p><p class="text-slate-500">{{ pkg.checked_at }}</p></div></div></section>
            <nav v-if="packages?.links" class="flex flex-wrap gap-2"><Link v-for="link in packages.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['rounded-lg border px-3 py-2 text-sm', link.active ? 'bg-emerald-700 text-white' : '']" /></nav>
        </main>
    </AuthenticatedLayout>
</template>
