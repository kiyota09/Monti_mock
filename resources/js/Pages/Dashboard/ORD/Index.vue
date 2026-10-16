<template>
    <AuthenticatedLayout>
        <Head title="Order Management Dashboard" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <ClipboardCheck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> ORD · Command Center
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Order Management</h1>
                            <p class="text-sm text-blue-100/90"> intake → production → dispatch → cash, monitored end-to-end</p>
                        </div>
                        <Link :href="route('ord.orders')" class="rounded-2xl bg-white px-4 py-2.5 text-sm font-black text-indigo-700 shadow-lg transition-all hover:bg-blue-50 active:scale-95">
                            Open worklist
                        </Link>
                    </div>

                    <!-- KPI strip -->
                    <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2">
                        <div v-for="kpi in kpiCards" :key="kpi.label" class="rounded-2xl bg-white/15 px-3 py-2.5 ring-1 ring-white/25 backdrop-blur">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">{{ kpi.label }}</p>
                            <p class="text-xl font-black">{{ kpi.value }}</p>
                        </div>
                    </div>
                </div>

                <!-- Pipeline funnel -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5 sm:p-6" style="animation-delay: 80ms">
                    <h2 class="font-black text-gray-900 dark:text-white tracking-tight">Pipeline funnel</h2>
                    <p class="text-xs text-gray-500 mb-4">Live job-order distribution across the textile pipeline</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2">
                        <button
                            v-for="stage in funnelStages"
                            :key="stage.key"
                            @click="goToGroup(stage.key)"
                            class="rounded-2xl border p-3 text-left transition-all hover:-translate-y-0.5 hover:shadow-lg active:scale-95"
                            :class="stage.card"
                        >
                            <p class="text-2xl font-black">{{ funnel[stage.key] ?? 0 }}</p>
                            <p class="text-[11px] font-bold uppercase tracking-wide">{{ stage.label }}</p>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <!-- Attention queue -->
                    <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5 sm:p-6" style="animation-delay: 140ms">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="font-black text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                                <TriangleAlert class="h-4 w-4 text-amber-500" /> Needs attention
                            </h2>
                            <span class="text-xs font-black text-amber-600">{{ attention.length }} open</span>
                        </div>
                        <div v-if="attention.length === 0" class="text-sm text-gray-500 py-6 text-center">All clear — no holds, returns or overdue orders.</div>
                        <div v-else class="space-y-2 max-h-80 overflow-y-auto pr-1">
                            <Link
                                v-for="o in attention"
                                :key="o.id"
                                :href="route('ord.orders.show', { type: o.type.toLowerCase(), id: o.id })"
                                class="flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-3 hover:border-amber-300 hover:shadow-md transition-all"
                            >
                                <span class="rounded-xl px-2 py-1 text-[10px] font-black uppercase" :class="statusBadge(o.status)">{{ formatStatus(o.status) }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-black truncate">{{ o.number }} · {{ o.client_name }}</p>
                                    <p class="text-xs text-gray-500">Due {{ o.expected_ship_date || '—' }} · {{ o.quantity }} kg · {{ o.priority }}</p>
                                </div>
                                <ChevronRight class="h-4 w-4 text-gray-300" />
                            </Link>
                        </div>
                    </div>

                    <!-- Activity -->
                    <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5 sm:p-6" style="animation-delay: 200ms">
                        <h2 class="font-black text-gray-900 dark:text-white tracking-tight mb-3 flex items-center gap-2">
                            <Activity class="h-4 w-4 text-indigo-500" /> Lifecycle activity
                        </h2>
                        <div v-if="activity.length === 0" class="text-sm text-gray-500 py-6 text-center">No transitions recorded yet.</div>
                        <ol v-else class="relative space-y-3 max-h-80 overflow-y-auto pr-1 border-l-2 border-indigo-100 dark:border-indigo-900 ml-2 pl-4">
                            <li v-for="a in activity" :key="a.id" class="text-sm">
                                <p class="font-bold text-gray-800 dark:text-gray-100">
                                    {{ a.order_type }} #{{ a.order_id }}
                                    <span class="font-medium text-gray-500">{{ a.from ? `${formatStatus(a.from)} → ` : '' }}</span><span class="text-indigo-600">{{ formatStatus(a.to) }}</span>
                                </p>
                                <p class="text-xs text-gray-500">{{ a.by }} · {{ a.at }}{{ a.notes ? ` — ${a.notes}` : '' }}</p>
                            </li>
                        </ol>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Activity, ChevronRight, ClipboardCheck, Sparkles, TriangleAlert } from 'lucide-vue-next';

const props = defineProps({
    kpis: Object,
    funnel: Object,
    attention: Array,
    activity: Array,
});

const peso = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });

const kpiCards = computed(() => [
    { label: 'Open orders', value: props.kpis.open_orders },
    { label: 'In production', value: props.kpis.in_production },
    { label: 'Ready / transit', value: props.kpis.ready_transit },
    { label: 'Attention', value: props.kpis.attention },
    { label: 'Revenue MTD', value: peso(props.kpis.revenue_mtd) },
    { label: 'Uncollected', value: peso(props.kpis.unpaid_total) },
]);

const funnelStages = [
    { key: 'intake', label: 'Intake', card: 'bg-slate-50 border-slate-200 dark:bg-zinc-800/60' },
    { key: 'confirmed', label: 'Confirmed', card: 'bg-violet-50 border-violet-200 dark:bg-violet-500/10' },
    { key: 'production', label: 'Production', card: 'bg-blue-50 border-blue-200 dark:bg-blue-500/10' },
    { key: 'ready', label: 'Ready', card: 'bg-cyan-50 border-cyan-200 dark:bg-cyan-500/10' },
    { key: 'transit', label: 'In transit', card: 'bg-indigo-50 border-indigo-200 dark:bg-indigo-500/10' },
    { key: 'done', label: 'Done', card: 'bg-green-50 border-green-200 dark:bg-green-500/10' },
    { key: 'attention', label: 'Attention', card: 'bg-amber-50 border-amber-200 dark:bg-amber-500/10' },
    { key: 'cancelled', label: 'Cancelled', card: 'bg-gray-50 border-gray-200 dark:bg-zinc-800/60' },
];

const goToGroup = (key) => router.get(route('ord.orders'), { group: key }, { preserveScroll: true });

const statusBadge = (s) => ({
    on_hold: 'bg-amber-100 text-amber-700',
    returned: 'bg-red-100 text-red-700',
}[s] || 'bg-indigo-100 text-indigo-700');

const formatStatus = (s) => String(s || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
</style>
