<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    LifeBuoy, MonitorSmartphone, Activity, BookOpen,
    AlertTriangle, Clock, ArrowRight, ServerCrash, ShieldAlert,
} from 'lucide-vue-next';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    recentTickets: { type: Array, default: () => [] },
    attentionSystems: { type: Array, default: () => [] },
});

const priorityClass = (p) => ({
    P1: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    P2: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    P3: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    P4: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[p] || 'bg-slate-100 text-slate-600');

const statusClass = (s) => s === 'down'
    ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';

const cards = [
    { title: 'Open Tickets', value: props.stats.openTickets ?? 0, icon: LifeBuoy, color: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-900/20', href: 'it.tickets' },
    { title: 'SLA Breached', value: props.stats.breachedTickets ?? 0, icon: ShieldAlert, color: 'text-red-600', bg: 'bg-red-50 dark:bg-red-900/20', href: 'it.tickets' },
    { title: 'P1 Critical Open', value: props.stats.p1Open ?? 0, icon: AlertTriangle, color: 'text-amber-600', bg: 'bg-amber-50 dark:bg-amber-900/20', href: 'it.tickets' },
    { title: 'Assets In Use', value: props.stats.assetsInUse ?? 0, icon: MonitorSmartphone, color: 'text-indigo-600', bg: 'bg-indigo-50 dark:bg-indigo-900/20', href: 'it.assets' },
    { title: 'Warranties Expiring', value: props.stats.assetsExpiring ?? 0, icon: Clock, color: 'text-orange-600', bg: 'bg-orange-50 dark:bg-orange-900/20', href: 'it.assets' },
    { title: 'Systems Down', value: props.stats.systemsDown ?? 0, icon: ServerCrash, color: 'text-rose-600', bg: 'bg-rose-50 dark:bg-rose-900/20', href: 'it.monitoring' },
    { title: 'Changes Pending CAB', value: props.stats.pendingChanges ?? 0, icon: Activity, color: 'text-emerald-600', bg: 'bg-emerald-50 dark:bg-emerald-900/20', href: 'it.changes' },
    { title: 'Knowledge Articles', value: props.stats.knowledgeArticles ?? 0, icon: BookOpen, color: 'text-cyan-600', bg: 'bg-cyan-50 dark:bg-cyan-900/20', href: 'it.knowledge' },
];
</script>

<template>
    <Head title="IT Operations Dashboard | Monti Textile" />
    <AuthenticatedLayout>
        <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    IT <span class="text-blue-600">Operations</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">
                    Service desk, assets, systems health and changes — one pane of glass.
                </p>
            </div>
            <Link :href="route('it.tickets')"
                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-4 py-3 sm:py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95">
                Open Service Desk <ArrowRight class="w-4 h-4" />
            </Link>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <Link v-for="card in cards" :key="card.title" :href="route(card.href)"
                class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between mb-4">
                    <div :class="['p-2.5 rounded-xl', card.bg]">
                        <component :is="card.icon" :class="['h-5 w-5', card.color]" />
                    </div>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ card.title }}</p>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ card.value }}</p>
            </Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-500">Priority Queue</h2>
                    <Link :href="route('it.tickets')" class="text-xs font-bold text-blue-600 hover:text-blue-500">View all →</Link>
                </div>
                <div v-if="recentTickets.length === 0" class="p-8 text-center text-sm text-slate-400">
                    Queue clear. No open tickets.
                </div>
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="t in recentTickets" :key="t.id" class="px-4 sm:px-6 py-3 sm:py-3.5 flex items-center gap-3">
                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black', priorityClass(t.priority)]">{{ t.priority }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ t.ticket_no }} — {{ t.title }}</p>
                            <p class="text-[11px] text-slate-400">{{ t.status.replace('_', ' ') }} · {{ t.assignee?.name || 'Unassigned' }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-500">Systems Needing Attention</h2>
                    <Link :href="route('it.monitoring')" class="text-xs font-bold text-blue-600 hover:text-blue-500">Monitor →</Link>
                </div>
                <div v-if="attentionSystems.length === 0" class="p-8 text-center text-sm text-slate-400">
                    All monitored systems operational.
                </div>
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="s in attentionSystems" :key="s.id" class="px-4 sm:px-6 py-3 sm:py-3.5 flex items-center gap-3">
                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', statusClass(s.status)]">{{ s.status }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ s.name }}</p>
                            <p class="text-[11px] text-slate-400">{{ s.location || '—' }}</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
