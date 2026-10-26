<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity, AlertTriangle, ArrowDownRight, ArrowUpRight, BarChart3, Boxes,
    CalendarDays, ChevronRight, ClipboardList, Download, Factory, FileText,
    Gauge, Layers, Minus, PackageCheck, PackageX, Receipt, ScanSearch,
    ShieldAlert, Stamp, Target, TrendingUp, Truck, Users,
    Wallet, Wrench, X,
} from 'lucide-vue-next';

const props = defineProps({
    generatedAt: { type: String, default: '' },
    periodLabel: { type: String, default: '' },
    kpis: {
        type: Object,
        default: () => ({
            revenue: { booked: 0, mtd: 0, growth: null },
            orders: { open: 0, in_production: 0, in_production_value: 0 },
            production: { output_mtd: 0, output_weight: 0, growth: null },
            quality: { yield: 0, reject_rate: 0, rejected: 0, total: 0 },
            delivery: { on_time: 0, delivered: 0, active: 0 },
            finance: {
                receivables: 0, payables: 0, collected_mtd: 0,
                overdue_receivables: 0, net_position: 0,
            },
            people: { headcount: 0, present_today: 0, pending_leaves: 0 },
            approvals: { total: 0, payroll: 0, vendors: 0, credit: 0 },
        }),
    },
    trends: {
        type: Object,
        default: () => ({ labels: [], revenue: [], spend: [], output: [], rejects: [] }),
    },
    plant: {
        type: Object,
        default: () => ({ available_machines: 0, total_machines: 0, machine_availability: 0, wip: 0 }),
    },
    commercial: {
        type: Object,
        default: () => ({ active_clients: 0, leads_open: 0, leads_won: 0 }),
    },
    procurementSpend: { type: Number, default: 0 },
    pipeline: { type: Array, default: () => [] },
    stages: { type: Array, default: () => [] },
    topClients: { type: Array, default: () => [] },
    recentOrders: { type: Array, default: () => [] },
    alerts: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    lowStock: { type: Array, default: () => [] },
});

const activeAlert = ref(null);
const modalOpen = ref(false);
const openAlert = (alert) => {
    activeAlert.value = alert;
    modalOpen.value = true;
};
const closeAlert = () => {
    modalOpen.value = false;
};

const nf = new Intl.NumberFormat('en-PH');
const peso = (value) => '₱' + nf.format(Math.round(Number(value) || 0));
const pesoCompact = (value) => {
    const amount = Number(value) || 0;
    const abs = Math.abs(amount);
    if (abs >= 1_000_000_000) return '₱' + (amount / 1_000_000_000).toFixed(2) + 'B';
    if (abs >= 1_000_000) return '₱' + (amount / 1_000_000).toFixed(2) + 'M';
    if (abs >= 10_000) return '₱' + (amount / 1_000).toFixed(1) + 'K';
    return '₱' + nf.format(Math.round(amount));
};
const count = (value) => nf.format(Number(value) || 0);

const kpiCards = computed(() => [
    {
        key: 'revenue',
        label: 'Booked Revenue',
        value: pesoCompact(props.kpis.revenue.booked),
        caption: `MTD ${pesoCompact(props.kpis.revenue.mtd)}`,
        growth: props.kpis.revenue.growth,
        icon: TrendingUp,
        accent: 'indigo',
    },
    {
        key: 'orders',
        label: 'Orders in Production',
        value: count(props.kpis.orders.in_production),
        caption: `${count(props.kpis.orders.open)} open orders · ${pesoCompact(props.kpis.orders.in_production_value)}`,
        icon: Factory,
        accent: 'sky',
    },
    {
        key: 'output',
        label: 'Output This Month',
        value: count(props.kpis.production.output_mtd),
        suffix: 'rolls',
        caption: `${count(props.kpis.production.output_weight)} kg processed`,
        growth: props.kpis.production.growth,
        icon: Boxes,
        accent: 'emerald',
    },
    {
        key: 'quality',
        label: 'Quality Yield',
        value: props.kpis.quality.yield + '%',
        caption: `${props.kpis.quality.reject_rate}% reject · ${count(props.kpis.quality.rejected)} of ${count(props.kpis.quality.total)}`,
        icon: ShieldAlert,
        accent: 'rose',
    },
    {
        key: 'delivery',
        label: 'On-Time Delivery',
        value: props.kpis.delivery.on_time + '%',
        caption: `${count(props.kpis.delivery.delivered)} delivered · ${count(props.kpis.delivery.active)} active`,
        icon: Truck,
        accent: 'violet',
    },
    {
        key: 'finance',
        label: 'Receivables',
        value: pesoCompact(props.kpis.finance.receivables),
        caption: `${count(props.kpis.finance.overdue_receivables)} overdue · Payables ${pesoCompact(props.kpis.finance.payables)}`,
        icon: Wallet,
        accent: 'amber',
    },
]);

const accentMap = {
    indigo: { chip: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300', bar: 'from-indigo-500 to-indigo-400', dot: 'bg-indigo-500' },
    sky: { chip: 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300', bar: 'from-sky-500 to-sky-400', dot: 'bg-sky-500' },
    emerald: { chip: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300', bar: 'from-emerald-500 to-emerald-400', dot: 'bg-emerald-500' },
    rose: { chip: 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300', bar: 'from-rose-500 to-rose-400', dot: 'bg-rose-500' },
    violet: { chip: 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300', bar: 'from-violet-500 to-violet-400', dot: 'bg-violet-500' },
    amber: { chip: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300', bar: 'from-amber-500 to-amber-400', dot: 'bg-amber-500' },
};

const iconMap = {
    Stamp, PackageX, AlertTriangle, Wrench, ClipboardList, Receipt,
    ShieldAlert, Layers, TrendingUp, Factory, Boxes, Wallet, Users,
};

const severityMap = {
    critical: {
        wrap: 'border-rose-200 bg-rose-50/70 dark:border-rose-500/20 dark:bg-rose-500/5',
        icon: 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',
        badge: 'bg-rose-600 text-white',
    },
    warning: {
        wrap: 'border-amber-200 bg-amber-50/70 dark:border-amber-500/20 dark:bg-amber-500/5',
        icon: 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
        badge: 'bg-amber-500 text-white',
    },
    info: {
        wrap: 'border-sky-200 bg-sky-50/70 dark:border-sky-500/20 dark:bg-sky-500/5',
        icon: 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300',
        badge: 'bg-sky-600 text-white',
    },
};

const revenueSpendMax = computed(() => Math.max(
    1,
    ...props.trends.revenue.map(Number),
    ...props.trends.spend.map(Number),
));
const productionMax = computed(() => Math.max(
    1,
    ...props.trends.output.map(Number),
    ...props.trends.rejects.map(Number),
));
const stageMax = computed(() => Math.max(1, ...props.stages.map((stage) => Number(stage.count) || 0)));
const openPipelineValue = computed(() => props.pipeline
    .filter((stage) => stage.key !== 'done')
    .reduce((sum, stage) => sum + (Number(stage.value) || 0), 0));

const stageColorMap = {
    pending: 'bg-sky-500',
    dyeing: 'bg-indigo-500',
    softener: 'bg-violet-500',
    squeezer: 'bg-fuchsia-500',
    iron: 'bg-amber-500',
    forming: 'bg-teal-500',
    packed: 'bg-emerald-500',
};

const barHeight = (value, max) => `${Math.max(2, Math.round(((Number(value) || 0) / max) * 100))}%`;

const growthLabel = (growth) => {
    if (growth === null || growth === undefined) return null;
    return `${growth > 0 ? '+' : ''}${growth}%`;
};
const growthClass = (growth) => {
    if (growth === null || growth === undefined) return 'text-slate-400';
    return growth >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';
};
const growthIcon = (growth) => {
    if (growth === null || growth === undefined) return Minus;
    return growth >= 0 ? ArrowUpRight : ArrowDownRight;
};

const statusClass = (status) => {
    switch (status) {
        case 'in_production':
            return 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/20';
        case 'pending':
            return 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700';
        case 'delivered':
        case 'completed':
            return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20';
        case 'in_transit':
            return 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20';
        case 'on_hold':
        case 'returned':
            return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20';
        case 'cancelled':
            return 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20';
        default:
            return 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700';
    }
};

const stockPercent = (item) => {
    const target = Math.max(1, Number(item.reorder_point) || 1);
    return Math.min(100, Math.round(((Number(item.stock) || 0) / target) * 100));
};

const leadShare = computed(() => {
    const total = props.topClients.reduce((sum, client) => sum + (Number(client.revenue) || 0), 0);
    return total > 0 ? total : 1;
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="CEO Dashboard" />

        <div class="min-h-screen bg-slate-50/80 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1600px] space-y-5 p-4 pb-16 sm:p-6">

                <!-- Executive header -->
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.22em] text-indigo-600 dark:text-indigo-400">
                            <Activity class="h-3.5 w-3.5" />
                            Monti Textile · CEO Command Center
                        </div>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                            Executive Overview
                        </h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                            Commercial, plant, supply chain, finance and people — {{ periodLabel }}.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="hidden items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-500 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400 sm:flex">
                            <CalendarDays class="h-3.5 w-3.5" />
                            {{ generatedAt }}
                        </span>
                        <Link
                            :href="route('ceo.approvals')"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:border-indigo-300 hover:text-indigo-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-indigo-500/40"
                        >
                            <Stamp class="h-4 w-4" />
                            Approvals
                            <span
                                v-if="kpis.approvals.total"
                                class="rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] font-black text-white"
                            >{{ kpis.approvals.total }}</span>
                        </Link>
                        <Link
                            :href="route('ceo.reports')"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:border-indigo-300 hover:text-indigo-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-indigo-500/40"
                        >
                            <BarChart3 class="h-4 w-4" />
                            Reports
                        </Link>
                        <Link
                            :href="route('ceo.board-pack')"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm shadow-indigo-600/20 transition hover:bg-indigo-700"
                        >
                            <Download class="h-4 w-4" />
                            Board Pack
                        </Link>
                    </div>
                </div>

                <!-- KPI strip -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    <div
                        v-for="card in kpiCards"
                        :key="card.key"
                        class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-500/30"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400 dark:text-zinc-500">
                                    {{ card.label }}
                                </p>
                                <div class="mt-2 flex items-baseline gap-1.5">
                                    <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                                        {{ card.value }}
                                    </span>
                                    <span v-if="card.suffix" class="text-[11px] font-semibold text-slate-400">
                                        {{ card.suffix }}
                                    </span>
                                </div>
                            </div>
                            <span :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl', accentMap[card.accent].chip]">
                                <component :is="card.icon" class="h-4 w-4" />
                            </span>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <p class="truncate text-[11px] font-medium text-slate-500 dark:text-zinc-400">{{ card.caption }}</p>
                            <span
                                v-if="card.growth !== undefined"
                                :class="['inline-flex shrink-0 items-center gap-0.5 text-[11px] font-bold', growthClass(card.growth)]"
                            >
                                <component :is="growthIcon(card.growth)" class="h-3.5 w-3.5" />
                                {{ growthLabel(card.growth) ?? '—' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Revenue vs spend + priority actions -->
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 xl:col-span-8">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Revenue vs Procurement Spend</h2>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">
                                    Booked order value against purchasing spend, last 6 months.
                                </p>
                            </div>
                            <div class="flex items-center gap-4 text-[11px] font-semibold text-slate-500 dark:text-zinc-400">
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-indigo-500"></span>Revenue</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-400"></span>Spend</span>
                            </div>
                        </div>

                        <div class="mt-6 flex h-56 items-end gap-3 sm:gap-5">
                            <div
                                v-for="(label, index) in trends.labels"
                                :key="label"
                                class="flex min-w-0 flex-1 flex-col items-center gap-2"
                            >
                                <div class="flex h-48 w-full items-end justify-center gap-1">
                                    <div
                                        class="w-1/2 max-w-6 rounded-t-md bg-gradient-to-t from-indigo-600 to-indigo-400 transition-all duration-500 hover:from-indigo-700"
                                        :style="{ height: barHeight(trends.revenue[index], revenueSpendMax) }"
                                        :title="`Revenue: ${peso(trends.revenue[index])}`"
                                    ></div>
                                    <div
                                        class="w-1/2 max-w-6 rounded-t-md bg-gradient-to-t from-amber-500 to-amber-300 transition-all duration-500 hover:from-amber-600"
                                        :style="{ height: barHeight(trends.spend[index], revenueSpendMax) }"
                                        :title="`Spend: ${peso(trends.spend[index])}`"
                                    ></div>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ label }}</span>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 dark:border-zinc-800 sm:grid-cols-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Booked</p>
                                <p class="mt-0.5 text-sm font-bold text-slate-900 dark:text-white">{{ peso(kpis.revenue.booked) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">This month</p>
                                <p class="mt-0.5 text-sm font-bold text-slate-900 dark:text-white">{{ peso(kpis.revenue.mtd) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Collected MTD</p>
                                <p class="mt-0.5 text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ peso(kpis.finance.collected_mtd) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Net AR / AP</p>
                                <p
                                    class="mt-0.5 text-sm font-bold"
                                    :class="kpis.finance.net_position >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                >{{ peso(kpis.finance.net_position) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Priority actions -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 xl:col-span-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Priority Actions</h2>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Exceptions that need executive attention.</p>
                            </div>
                            <span
                                v-if="alerts.length"
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-800 dark:text-zinc-400"
                            >
                                {{ alerts.length }} open
                            </span>
                        </div>

                        <div v-if="alerts.length" class="mt-4 space-y-2.5">
                            <button
                                v-for="alert in alerts"
                                :key="alert.key"
                                type="button"
                                :class="[
                                    'flex w-full items-center gap-3 rounded-xl border p-3 text-left transition hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400/60',
                                    severityMap[alert.severity]?.wrap,
                                ]"
                                @click="openAlert(alert)"
                            >
                                <span :class="['flex h-8 w-8 shrink-0 items-center justify-center rounded-lg', severityMap[alert.severity]?.icon]">
                                    <component :is="iconMap[alert.icon] || AlertTriangle" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-xs font-bold text-slate-800 dark:text-zinc-100">{{ alert.title }}</span>
                                    <span class="block truncate text-[10px] font-medium text-slate-500 dark:text-zinc-400">{{ alert.detail }}</span>
                                </span>
                                <span :class="['flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-[10px] font-black', severityMap[alert.severity]?.badge]">
                                    {{ alert.count }}
                                </span>
                                <ChevronRight class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                            </button>
                        </div>

                        <div v-else class="mt-6 flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-emerald-200 bg-emerald-50/50 py-8 text-center dark:border-emerald-500/20 dark:bg-emerald-500/5">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <PackageCheck class="h-5 w-5" />
                            </span>
                            <p class="text-xs font-bold text-emerald-700 dark:text-emerald-300">All clear</p>
                            <p class="text-[10px] text-emerald-600/80 dark:text-emerald-400/70">No exceptions across the supply chain.</p>
                        </div>
                    </div>
                </div>

                <!-- Production, pipeline, clients -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <!-- Production vs rejects -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Production Output</h2>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Rolls processed vs rejected.</p>
                            </div>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300">
                                <Gauge class="h-4 w-4" />
                            </span>
                        </div>

                        <div class="mt-6 flex h-40 items-end gap-3">
                            <div v-for="(label, index) in trends.labels" :key="label" class="flex min-w-0 flex-1 flex-col items-center gap-2">
                                <div class="flex h-32 w-full items-end justify-center gap-1">
                                    <div
                                        class="w-1/2 max-w-5 rounded-t-md bg-gradient-to-t from-emerald-600 to-emerald-400"
                                        :style="{ height: barHeight(trends.output[index], productionMax) }"
                                        :title="`Output: ${trends.output[index]} rolls`"
                                    ></div>
                                    <div
                                        class="w-1/2 max-w-5 rounded-t-md bg-gradient-to-t from-rose-600 to-rose-400"
                                        :style="{ height: barHeight(trends.rejects[index], productionMax) }"
                                        :title="`Rejects: ${trends.rejects[index]} rolls`"
                                    ></div>
                                </div>
                                <span class="text-[10px] font-bold uppercase text-slate-400">{{ label }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-[11px] font-semibold dark:border-zinc-800">
                            <span class="flex items-center gap-1.5 text-slate-500 dark:text-zinc-400">
                                <span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>Output
                            </span>
                            <span class="flex items-center gap-1.5 text-slate-500 dark:text-zinc-400">
                                <span class="h-2.5 w-2.5 rounded-sm bg-rose-500"></span>Rejects
                            </span>
                            <span class="text-slate-900 dark:text-white">{{ kpis.quality.yield }}% yield</span>
                        </div>
                    </div>

                    <!-- Order pipeline -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Order Pipeline</h2>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Order-to-cash by stage.</p>
                            </div>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                                <Target class="h-4 w-4" />
                            </span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div v-for="stage in pipeline" :key="stage.key">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-semibold text-slate-600 dark:text-zinc-300">{{ stage.label }}</span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ stage.count }}</span>
                                </div>
                                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-indigo-600 to-violet-500 transition-all duration-500"
                                        :style="{ width: `${Math.max(stage.count ? 3 : 0, stage.percent)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-zinc-800">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Open value</span>
                            <span class="text-xs font-bold text-slate-900 dark:text-white">{{ pesoCompact(openPipelineValue) }}</span>
                        </div>
                    </div>

                    <!-- Top clients -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Top Clients</h2>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Revenue contribution.</p>
                            </div>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300">
                                <Users class="h-4 w-4" />
                            </span>
                        </div>

                        <div v-if="topClients.length" class="mt-4 space-y-3">
                            <div v-for="(client, index) in topClients" :key="client.name" class="flex items-center gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-slate-100 text-[10px] font-black text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ index + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate text-[11px] font-semibold text-slate-700 dark:text-zinc-200">{{ client.name }}</span>
                                        <span class="shrink-0 text-[11px] font-bold text-slate-900 dark:text-white">{{ pesoCompact(client.revenue) }}</span>
                                    </div>
                                    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-amber-500 to-orange-400"
                                            :style="{ width: `${Math.max(3, Math.round((client.revenue / leadShare) * 100))}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-8 text-center text-[11px] text-slate-400">No client revenue recorded yet.</p>

                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-zinc-800">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Active clients</span>
                            <span class="text-xs font-bold text-slate-900 dark:text-white">{{ count(commercial.active_clients) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Floor stages + materials + recent orders -->
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    <!-- Production floor -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Production Floor</h2>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Live work-in-progress by stage.</p>
                            </div>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300">
                                <Layers class="h-4 w-4" />
                            </span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div v-for="stage in stages" :key="stage.key">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="flex items-center gap-2 font-semibold text-slate-600 dark:text-zinc-300">
                                        <span :class="['h-2 w-2 rounded-full', stageColorMap[stage.key] || 'bg-slate-400']"></span>
                                        {{ stage.label }}
                                    </span>
                                    <span class="font-bold text-slate-900 dark:text-white">{{ stage.count }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                                    <div
                                        :class="['h-full rounded-full', stageColorMap[stage.key] || 'bg-slate-400']"
                                        :style="{ width: barHeight(stage.count, stageMax) }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 dark:border-zinc-800">
                            <div class="rounded-lg bg-slate-50 p-2 dark:bg-zinc-800/60">
                                <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Machines up</p>
                                <p class="mt-0.5 text-sm font-bold text-slate-900 dark:text-white">{{ plant.available_machines }}/{{ plant.total_machines }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-2 dark:bg-zinc-800/60">
                                <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">WIP</p>
                                <p class="mt-0.5 text-sm font-bold text-slate-900 dark:text-white">{{ count(plant.wip) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Materials watchlist -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Materials Watchlist</h2>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Below reorder point.</p>
                            </div>
                            <Link
                                :href="route('inv.materials')"
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 transition hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300"
                                title="Open materials"
                            >
                                <PackageX class="h-4 w-4" />
                            </Link>
                        </div>

                        <div v-if="lowStock.length" class="mt-4 space-y-3.5">
                            <div v-for="item in lowStock" :key="item.id">
                                <div class="flex items-center justify-between gap-2 text-[11px]">
                                    <span class="truncate font-semibold text-slate-700 dark:text-zinc-200">{{ item.name }}</span>
                                    <span class="shrink-0 font-bold text-rose-600 dark:text-rose-400">
                                        {{ count(item.stock) }} / {{ count(item.reorder_point) }} {{ item.unit }}
                                    </span>
                                </div>
                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-rose-500 to-amber-400"
                                        :style="{ width: `${stockPercent(item)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-8 flex flex-col items-center gap-2 text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300">
                                <PackageCheck class="h-5 w-5" />
                            </span>
                            <p class="text-[11px] font-semibold text-slate-500 dark:text-zinc-400">All materials above reorder point.</p>
                        </div>

                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-zinc-800">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Procurement spend</span>
                            <span class="text-xs font-bold text-slate-900 dark:text-white">{{ pesoCompact(procurementSpend) }}</span>
                        </div>
                    </div>

                    <!-- Recent orders -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent Orders</h2>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Latest job orders.</p>
                            </div>
                            <Link
                                :href="route('ceo.reports')"
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300"
                                title="Open executive reports"
                            >
                                <FileText class="h-4 w-4" />
                            </Link>
                        </div>

                        <div v-if="recentOrders.length" class="-mx-1 mt-2 divide-y divide-slate-100 dark:divide-zinc-800">
                            <div v-for="order in recentOrders" :key="order.id" class="flex items-center justify-between gap-3 px-1 py-2.5">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate text-[11px] font-bold text-slate-800 dark:text-zinc-100">{{ order.client }}</span>
                                        <span :class="['shrink-0 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase ring-1', statusClass(order.status)]">
                                            {{ order.status_label }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 truncate text-[10px] text-slate-400">
                                        {{ order.jo_number }} · {{ order.color }} · {{ count(order.quantity) }} pcs
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-[11px] font-bold text-slate-900 dark:text-white">{{ peso(order.amount) }}</p>
                                    <p class="text-[10px] text-slate-400">{{ order.expected_ship_date || order.created_at }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-8 text-center text-[11px] text-slate-400">No orders yet.</p>
                    </div>
                </div>

                <!-- Balanced scorecard -->
                <div class="pt-1">
                    <div class="mb-3 flex items-end justify-between px-1">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Business Scorecard</h2>
                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">One line per function — the numbers that matter.</p>
                        </div>
                        <Link
                            :href="route('ceo.goals')"
                            class="hidden items-center gap-1.5 text-[11px] font-bold text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400 sm:flex"
                        >
                            Goals & Targets
                            <ChevronRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <div
                            v-for="department in departments"
                            :key="department.key"
                            class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-500/30"
                        >
                            <div class="flex items-center gap-2.5">
                                <span :class="['flex h-8 w-8 items-center justify-center rounded-lg', accentMap[department.accent]?.chip]">
                                    <component :is="iconMap[department.icon] || Factory" class="h-4 w-4" />
                                </span>
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white">{{ department.title }}</h3>
                            </div>

                            <dl class="mt-4 space-y-2.5">
                                <div v-for="metric in department.metrics" :key="metric.label" class="flex items-baseline justify-between gap-2">
                                    <dt class="truncate text-[10px] font-medium text-slate-500 dark:text-zinc-400">{{ metric.label }}</dt>
                                    <dd class="shrink-0 text-[11px] font-bold text-slate-900 dark:text-white">{{ metric.value }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-[11px] font-medium text-slate-500 dark:text-zinc-400">
                        Read-only executive view · Figures update in real time across every module.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="route('ceo.traceability')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:border-indigo-300 hover:text-indigo-700 dark:border-zinc-700 dark:text-zinc-300"
                        >
                            <ScanSearch class="h-3.5 w-3.5" />Traceability
                        </Link>
                        <Link
                            :href="route('ceo.deliveries')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:border-indigo-300 hover:text-indigo-700 dark:border-zinc-700 dark:text-zinc-300"
                        >
                            <Truck class="h-3.5 w-3.5" />Deliveries
                        </Link>
                        <Link
                            :href="route('ceo.audit')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:border-indigo-300 hover:text-indigo-700 dark:border-zinc-700 dark:text-zinc-300"
                        >
                            <ClipboardList class="h-3.5 w-3.5" />Audit Trail
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Priority action detail modal -->
        <Modal :show="modalOpen" max-width="4xl" @close="closeAlert">
            <div v-if="activeAlert" class="max-h-[85vh] overflow-y-auto">
                <div class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-100 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex min-w-0 items-start gap-3">
                        <span :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl', severityMap[activeAlert.severity]?.icon]">
                            <component :is="iconMap[activeAlert.icon] || AlertTriangle" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[9px] font-bold uppercase tracking-[0.2em] text-slate-400">Priority action</p>
                            <h2 class="mt-0.5 text-base font-bold text-slate-900 dark:text-white">
                                {{ activeAlert.modal?.title || activeAlert.title }}
                            </h2>
                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">
                                {{ activeAlert.modal?.subtitle }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                        aria-label="Close details"
                        @click="closeAlert"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <div v-if="activeAlert.modal?.summary?.length" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div
                            v-for="stat in activeAlert.modal.summary"
                            :key="stat.label"
                            class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/60"
                        >
                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">{{ stat.label }}</p>
                            <p class="mt-0.5 truncate text-sm font-bold text-slate-900 dark:text-white" :title="stat.value">{{ stat.value }}</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-zinc-800">
                        <table class="min-w-full text-left text-[11px]">
                            <thead class="bg-slate-50 dark:bg-zinc-800/60">
                                <tr>
                                    <th
                                        v-for="col in activeAlert.modal?.columns || []"
                                        :key="col.key"
                                        class="whitespace-nowrap px-3 py-2 text-[9px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400"
                                    >
                                        {{ col.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                <tr
                                    v-for="(row, index) in activeAlert.modal?.rows || []"
                                    :key="index"
                                    class="align-top transition hover:bg-slate-50/70 dark:hover:bg-zinc-800/40"
                                >
                                    <td
                                        v-for="col in activeAlert.modal?.columns || []"
                                        :key="col.key"
                                        class="px-3 py-2 font-medium text-slate-600 dark:text-zinc-300"
                                        :class="col.wrap ? 'min-w-56 whitespace-normal' : 'whitespace-nowrap'"
                                    >
                                        {{ row[col.key] ?? '—' }}
                                    </td>
                                </tr>
                                <tr v-if="!(activeAlert.modal?.rows || []).length">
                                    <td
                                        :colspan="(activeAlert.modal?.columns || []).length || 1"
                                        class="px-3 py-10 text-center text-[11px] text-slate-400"
                                    >
                                        {{ activeAlert.modal?.empty || 'No records found.' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-[10px] text-slate-400">
                        Snapshot {{ generatedAt }} · Read-only executive view
                    </p>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
