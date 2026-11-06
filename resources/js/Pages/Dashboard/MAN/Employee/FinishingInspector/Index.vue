<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CheckCircle, ClipboardCheck, RotateCcw } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

defineProps({ squeezerJobs: Array, ironJobs: Array });
const { canEdit } = usePageAccess();
const canInspect = computed(() => canEdit('MAN', 'finishing_inspector'));
const decideSqueezer = (job, action) => router.post(route('man.staff.finishing-inspector.pass-squeezer', job.id), { action }, { preserveScroll: true });
const decideIron = (job, action) => router.post(route('man.staff.finishing-inspector.pass-iron', job.id), { action }, { preserveScroll: true });
</script>

<template>
    <AuthenticatedLayout title="Finishing Shrinkage Inspector">
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-br from-fuchsia-700 to-indigo-800 p-7 text-white shadow-xl"><div class="flex items-center gap-4"><ClipboardCheck class="h-10 w-10"/><div><p class="text-xs font-bold uppercase tracking-widest text-fuchsia-100">Finishing Department</p><h1 class="text-3xl font-black">Finishing Shrinkage Inspector</h1><p class="mt-1 text-fuchsia-100">Inspect squeezing and ironing work before each next process.</p></div></div></header>
            <section><div class="mb-4 flex items-center justify-between"><h2 class="text-xl font-black">Squeezer output</h2><span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-800">{{ squeezerJobs?.length ?? 0 }} waiting</span></div><div v-if="!squeezerJobs?.length" class="rounded-2xl border border-dashed p-8 text-center text-gray-500">No squeezer output is awaiting inspection.</div><div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><article v-for="job in squeezerJobs" :key="job.id" class="rounded-2xl border bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"><p class="font-mono text-lg font-black">{{ job.code }}</p><p class="mt-1 text-sm text-gray-500">Fabric {{ job.fabric?.code ?? '—' }} · {{ job.fabric?.sales_order?.client?.company_name ?? 'Job Order' }}</p><p class="mt-3 text-sm">Operator: {{ job.operator?.name ?? '—' }}</p><p v-if="job.remarks" class="mt-1 text-sm text-gray-500">{{ job.remarks }}</p><div v-if="canInspect" class="mt-5 flex gap-2"><button @click="decideSqueezer(job, 'quality')" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white"><CheckCircle class="h-4 w-4"/> Pass to Ironing</button><button @click="decideSqueezer(job, 'resqueeze')" class="flex items-center gap-2 rounded-xl border border-amber-200 px-3 py-2.5 text-sm font-bold text-amber-800"><RotateCcw class="h-4 w-4"/> Return</button></div></article></div></section>
            <section><div class="mb-4 flex items-center justify-between"><h2 class="text-xl font-black">Ironing output</h2><span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-800">{{ ironJobs?.length ?? 0 }} waiting</span></div><div v-if="!ironJobs?.length" class="rounded-2xl border border-dashed p-8 text-center text-gray-500">No ironing output is awaiting inspection.</div><div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><article v-for="job in ironJobs" :key="job.id" class="rounded-2xl border bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"><p class="font-mono text-lg font-black">{{ job.code }}</p><p class="mt-1 text-sm text-gray-500">Fabric {{ job.squeezer_job?.fabric?.code ?? '—' }} · {{ job.squeezer_job?.fabric?.sales_order?.client?.company_name ?? 'Job Order' }}</p><p class="mt-3 text-sm">Operator: {{ job.operator?.name ?? '—' }}</p><p v-if="job.remarks" class="mt-1 text-sm text-gray-500">{{ job.remarks }}</p><div v-if="canInspect" class="mt-5 flex gap-2"><button @click="decideIron(job, 'pack')" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white"><CheckCircle class="h-4 w-4"/> Pass to Packaging</button><button @click="decideIron(job, 'reiron')" class="flex items-center gap-2 rounded-xl border border-amber-200 px-3 py-2.5 text-sm font-bold text-amber-800"><RotateCcw class="h-4 w-4"/> Return</button></div></article></div></section>
        </main>
    </AuthenticatedLayout>
</template>
