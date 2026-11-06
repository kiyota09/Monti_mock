<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CheckCircle2, History as HistoryIcon, RotateCcw } from 'lucide-vue-next';

defineProps({ inspections: { type: Object, default: () => ({ data: [], links: [] }) } });
</script>

<template>
    <Head title="Knitting Inspector History" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-blue-800 to-indigo-700 p-6 text-white shadow-lg"><p class="text-xs font-black uppercase tracking-widest text-blue-100">Knitting Department</p><h1 class="mt-1 text-2xl font-black">My Inspection History</h1><p class="mt-1 text-sm text-blue-100">Your recorded approvals and returns.</p></header>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center gap-2 border-b border-slate-100 p-5 dark:border-zinc-800"><HistoryIcon class="h-5 w-5 text-blue-600"/><h2 class="font-black text-slate-900 dark:text-white">Inspection decisions</h2><span class="ml-auto text-sm text-slate-500">{{ inspections.total ?? inspections.data?.length ?? 0 }} records</span></div>
                <div v-if="!inspections.data?.length" class="p-10 text-center text-sm text-slate-500">No inspection history yet.</div>
                <div v-else class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-800/60"><tr><th class="px-5 py-3">Fabric</th><th class="px-5 py-3">Yarn / Weight</th><th class="px-5 py-3">Decision</th><th class="px-5 py-3">Details</th><th class="px-5 py-3">Date</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-zinc-800"><tr v-for="item in inspections.data" :key="item.id"><td class="px-5 py-3 font-mono font-bold text-slate-800 dark:text-white">{{ item.fabric?.code || `Fabric #${item.fabric_id}` }}</td><td class="px-5 py-3 text-slate-600 dark:text-zinc-300">{{ item.fabric?.yarn_type || '—' }} · {{ item.fabric?.weight ?? '—' }} kg</td><td class="px-5 py-3"><span :class="item.result === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-black uppercase"><CheckCircle2 v-if="item.result === 'approved'" class="h-3.5 w-3.5"/><RotateCcw v-else class="h-3.5 w-3.5"/>{{ item.result }}</span></td><td class="max-w-sm px-5 py-3 text-slate-600 dark:text-zinc-300">{{ item.result === 'approved' ? `Passed to ${item.destination || 'Dyeing'}` : (item.rejection_reason || 'Returned for remake') }}</td><td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ new Date(item.created_at).toLocaleString() }}</td></tr></tbody></table></div>
                <div v-if="inspections.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 p-4 dark:border-zinc-800"><Link v-for="link in inspections.links" :key="link.label" :href="link.url || '#' " v-html="link.label" :class="['rounded-lg px-3 py-1.5 text-xs font-bold', link.active ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300']" preserve-scroll /></div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
