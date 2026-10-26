<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CleanTable from '@/Components/ceo/CleanTable.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ScanSearch, Search, Loader2, X, RotateCcw, TriangleAlert,
    Package, Factory, Truck, FlaskConical, CreditCard, ClipboardList,
    ReceiptText, Warehouse, Info, ArrowRight, BadgeCheck,
} from 'lucide-vue-next';

const props = defineProps({
    filters: Object,
    trace: Object,
    recentFabrics: Array,
    recentPackages: Array,
    recentLots: Array,
});

const mode = ref(props.filters?.mode || 'unit');
const q = ref(props.filters?.q || '');
const searching = ref(false);

const search = (term) => {
    if (term !== undefined) q.value = term;

    const query = q.value.trim();
    if (!query) return;

    searching.value = true;
    router.get(route('ceo.traceability'), { mode: mode.value, q: query }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { searching.value = false; },
    });
};

const setMode = (nextMode) => {
    if (mode.value === nextMode) return;
    mode.value = nextMode;
    if (q.value.trim()) search();
};

const clearTrace = () => {
    q.value = '';
    searching.value = true;
    router.get(route('ceo.traceability'), {}, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
        onFinish: () => { searching.value = false; },
    });
};

const traceUnit = (code) => {
    if (!code) return;
    mode.value = 'unit';
    search(code);
};

const traceLot = (control) => {
    if (!control) return;
    mode.value = 'lot';
    search(control);
};

const fdate = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const ftime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const peso = (value) => '₱' + Number(value || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });

const orDash = (value) => (value === null || value === undefined || value === '' ? '—' : value);

const statusTone = (value) => {
    const status = String(value ?? '').toLowerCase();

    if (!status) {
        return 'bg-slate-100 text-slate-500 ring-slate-200 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700';
    }
    if (/(unpaid|reject|fail|cancel|overdue|lost|returned|void)/.test(status)) {
        return 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-500/30';
    }
    if (/(paid|complete|delivered|active|approved|received|confirmed|good|passed|in stock|closed)/.test(status)) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-500/30';
    }
    if (/(pending|partial|progress|scheduled|released|processing|open|draft|queued)/.test(status)) {
        return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-500/30';
    }

    return 'bg-slate-100 text-slate-500 ring-slate-200 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700';
};

const stageIcon = (stage) => ({
    Knitting: Factory,
    Dyeing: FlaskConical,
    Softener: FlaskConical,
    Squeezer: Factory,
    Ironing: Factory,
    Packaging: Package,
}[stage] || ClipboardList);

const hasTrace = computed(() => !!props.trace?.found);
const isLot = computed(() => props.trace?.kind === 'lot');
const isUnit = computed(() => props.trace?.kind === 'unit' || props.trace?.kind === 'order');
const showPicks = computed(() => !props.trace || props.trace.found === false);

const kindLabel = computed(() => ({
    lot: 'Raw lot',
    unit: 'Finished unit',
    order: 'Job order',
}[props.trace?.kind] || 'Trace'));

const summaryColumns = [
    { key: 'label', label: 'Field' },
    { key: 'value', label: 'Detail', wrap: true },
];

const lotOverviewRows = computed(() => {
    const summary = props.trace?.summary ?? {};

    return [
        { label: 'Material', value: orDash(summary.material) },
        { label: 'Quantity', value: orDash(summary.quantity) },
        { label: 'Status', value: summary.status, status: true },
        { label: 'Warehouse', value: orDash(summary.warehouse) },
        { label: 'Location', value: orDash(summary.location) },
    ];
});

const lotOriginRows = computed(() => {
    const origin = props.trace?.origin ?? {};

    return [
        { label: 'Received', value: fdate(origin.received_at) },
        { label: 'Received by', value: orDash(origin.received_by) },
        { label: 'Supplier PO', value: orDash(origin.scm_po), mono: true },
        { label: 'Supplier', value: orDash(origin.supplier) },
    ];
});

const unitOrderRows = computed(() => {
    const summary = props.trace?.summary ?? {};
    const order = props.trace?.order ?? {};

    return [
        { label: 'Client', value: orDash(summary.client) },
        { label: 'Job order', value: orDash(summary.jo), mono: true },
        { label: 'Client PO', value: orDash(summary.po), mono: true },
        { label: 'Order value', value: peso(summary.ordered_total) },
        { label: 'PO payment', value: summary.po_payment, status: true },
        { label: 'PO delivery', value: fdate(summary.po_delivery) },
        { label: 'Order qty', value: orDash(order.qty) },
        { label: 'Order created', value: fdate(order.created) },
    ];
});

const unitProductRows = computed(() => {
    const summary = props.trace?.summary ?? {};

    return [
        { label: 'Fabric', value: orDash(summary.fabric), mono: true },
        { label: 'Yarn', value: orDash(summary.yarn) },
        { label: 'Weight', value: summary.weight ? `${summary.weight} kg` : '—' },
        { label: 'Status', value: summary.status, status: true },
        { label: 'Process steps', value: props.trace?.stages?.length ?? 0 },
        { label: 'Deliveries', value: props.trace?.deliveries?.length ?? 0 },
    ];
});

const overviewCards = computed(() => {
    if (props.trace?.kind === 'lot') {
        return [
            { title: 'Lot overview', subtitle: 'Material and stock position', rows: lotOverviewRows.value },
            { title: 'Receipt & supplier', subtitle: 'Origin of this raw lot', rows: lotOriginRows.value },
        ];
    }

    if (props.trace?.kind === 'unit' || props.trace?.kind === 'order') {
        return [
            { title: 'Order overview', subtitle: 'Commercial and client details', rows: unitOrderRows.value },
            { title: 'Product & progress', subtitle: 'Fabric and throughput', rows: unitProductRows.value },
        ];
    }

    return [];
});

const inboundRows = computed(() => {
    const inbound = props.trace?.inbound;
    return inbound ? [{
        receipt: inbound.receiving_number,
        date: inbound.received_at,
        warehouse: inbound.warehouse,
        status: inbound.status,
    }] : [];
});

const inboundLineRows = computed(() => props.trace?.inbound?.lines ?? []);

const billingRows = computed(() => {
    const billing = props.trace?.billing;
    return billing ? [{
        invoice: billing.invoice_number,
        amount: billing.amount,
        status: billing.status,
        due: billing.due_date,
        paid: billing.paid_at,
        method: billing.pay_method,
    }] : [];
});

const releaseRows = computed(() => {
    const release = props.trace?.release;
    return release ? [{
        department: release.department,
        qty: release.qty,
        remaining: release.remaining,
        released_by: release.released_by,
        released_at: release.released_at,
        status: release.status,
    }] : [];
});

const consumptionRows = computed(() => props.trace?.consumptions ?? []);
const stageRows = computed(() => props.trace?.stages ?? []);
const deliveryRows = computed(() => props.trace?.deliveries ?? []);

const inboundColumns = [
    { key: 'receipt', label: 'Receipt' },
    { key: 'date', label: 'Received' },
    { key: 'warehouse', label: 'Warehouse' },
    { key: 'status', label: 'Status' },
];

const inboundLineColumns = [
    { key: 'material', label: 'Material' },
    { key: 'expected', label: 'Expected', align: 'right' },
    { key: 'received', label: 'Received', align: 'right' },
    { key: 'rejected', label: 'Rejected', align: 'right' },
    { key: 'status', label: 'Status' },
];

const billingColumns = [
    { key: 'invoice', label: 'Invoice' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'due', label: 'Due' },
    { key: 'paid', label: 'Paid' },
    { key: 'method', label: 'Method' },
];

const releaseColumns = [
    { key: 'department', label: 'Department' },
    { key: 'qty', label: 'Released', align: 'right' },
    { key: 'remaining', label: 'Remaining', align: 'right' },
    { key: 'released_by', label: 'Released by' },
    { key: 'released_at', label: 'Released at' },
    { key: 'status', label: 'Status' },
];

const consumptionColumns = [
    { key: 'dye_job', label: 'Dye job' },
    { key: 'dye_type', label: 'Dye type' },
    { key: 'qty_used', label: 'Qty used', align: 'right' },
    { key: 'fabric', label: 'Fabric' },
    { key: 'operator', label: 'Operator' },
    { key: 'processed_at', label: 'Processed' },
    { key: 'client', label: 'Client', wrap: true },
];

const stageColumns = [
    { key: 'stage', label: 'Stage' },
    { key: 'code', label: 'Code' },
    { key: 'operator', label: 'Operator' },
    { key: 'machine', label: 'Machine' },
    { key: 'detail', label: 'Detail', wrap: true },
    { key: 'at', label: 'Processed' },
    { key: 'status', label: 'Status' },
    { key: 'lots', label: 'Raw lots' },
];

const deliveryColumns = [
    { key: 'delivery_number', label: 'Delivery' },
    { key: 'status', label: 'Status' },
    { key: 'truck', label: 'Truck' },
    { key: 'driver', label: 'Driver' },
    { key: 'route', label: 'Route', wrap: true },
    { key: 'scheduled', label: 'Scheduled' },
    { key: 'departed', label: 'Departed' },
    { key: 'arrived', label: 'Arrived' },
    { key: 'pod_at', label: 'POD' },
    { key: 'client_confirmed', label: 'Confirmed', align: 'center' },
];

const fabricColumns = [
    { key: 'code', label: 'Fabric' },
    { key: 'yarn', label: 'Yarn' },
    { key: 'status', label: 'Status' },
    { key: 'client', label: 'Client', wrap: true },
];

const packageColumns = [
    { key: 'number', label: 'Package' },
    { key: 'qty', label: 'Qty', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'jo', label: 'Job order' },
];

const lotColumns = [
    { key: 'control', label: 'Control no.' },
    { key: 'material', label: 'Material', wrap: true },
    { key: 'qty', label: 'Quantity', align: 'right' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <AuthenticatedLayout>
        <Head title="CEO Traceability" />

        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-7xl space-y-5 p-4 pb-16 sm:p-6">

                <div class="relative overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-xl sm:p-7">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800" />
                    <div class="absolute -top-20 -right-16 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 p-3 ring-1 ring-white/30">
                            <ScanSearch class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">CEO · Oversight</p>
                            <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Material Traceability</h1>
                            <p class="mt-1 text-sm text-blue-100/90">Follow any raw lot to finished product — every hand it passed through.</p>
                        </div>
                    </div>

                    <div class="relative mt-5 flex flex-col gap-2 sm:flex-row">
                        <div class="flex shrink-0 gap-1 rounded-xl bg-white/15 p-1 ring-1 ring-white/25">
                            <button
                                type="button"
                                @click="setMode('unit')"
                                :class="mode === 'unit' ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow' : 'text-white/80'"
                                class="rounded-lg px-4 py-2 text-[11px] font-black uppercase tracking-wide transition"
                            >
                                Finished unit
                            </button>
                            <button
                                type="button"
                                @click="setMode('lot')"
                                :class="mode === 'lot' ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow' : 'text-white/80'"
                                class="rounded-lg px-4 py-2 text-[11px] font-black uppercase tracking-wide transition"
                            >
                                Raw lot
                            </button>
                        </div>

                        <div class="relative flex-1">
                            <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-zinc-500" />
                            <input
                                v-model="q"
                                @keyup.enter="search()"
                                :placeholder="mode === 'lot' ? 'Control number, e.g. CTL-… (see lots below)' : 'Fabric code, package no, JO or PO number…'"
                                class="w-full rounded-xl border-0 bg-white/95 py-3 pl-10 pr-10 text-sm font-medium text-gray-900 outline-none placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 dark:bg-zinc-900/95 dark:text-zinc-100"
                            />
                            <button
                                v-if="q"
                                type="button"
                                @click="q = ''"
                                class="absolute right-2.5 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                aria-label="Clear search"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <button
                            type="button"
                            @click="search()"
                            :disabled="searching || !q.trim()"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-xs font-extrabold uppercase tracking-wide text-indigo-700 shadow transition hover:bg-indigo-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-zinc-900 dark:text-indigo-300 dark:hover:bg-zinc-800"
                        >
                            <Loader2 v-if="searching" class="h-4 w-4 animate-spin" />
                            <Search v-else class="h-4 w-4" />
                            {{ searching ? 'Tracing…' : 'Trace' }}
                        </button>
                    </div>

                    <p class="relative mt-2 text-[11px] font-medium text-blue-100/80">
                        Search by fabric code, package number, job order, client PO, or raw-lot control number.
                    </p>
                </div>

                <div
                    v-if="trace && !trace.found"
                    class="flex flex-wrap items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-900/20"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                        <TriangleAlert class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-black text-amber-800 dark:text-amber-200">No match for “{{ trace.query }}”</p>
                        <p class="mt-1 text-[11px] font-bold text-amber-700/80 dark:text-amber-300/80">{{ trace.hint }}</p>
                    </div>
                    <button
                        type="button"
                        @click="clearTrace"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-3 py-1.5 text-[11px] font-bold text-amber-700 transition hover:bg-amber-100 dark:border-amber-700 dark:bg-transparent dark:text-amber-300"
                    >
                        <RotateCcw class="h-3.5 w-3.5" /> Clear
                    </button>
                </div>

                <div
                    v-if="hasTrace"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500">Trace result</span>
                        <span class="font-mono text-sm font-black text-slate-900 dark:text-white">{{ trace.query }}</span>
                        <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wide text-indigo-700 ring-1 ring-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:ring-indigo-500/30">
                            {{ kindLabel }}
                        </span>
                    </div>
                    <button
                        type="button"
                        @click="clearTrace"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:border-indigo-300 hover:text-indigo-700 dark:border-zinc-700 dark:text-zinc-300"
                    >
                        <RotateCcw class="h-3.5 w-3.5" /> Clear
                    </button>
                </div>

                <div v-if="hasTrace && overviewCards.length" class="grid gap-4 lg:grid-cols-2">
                    <section
                        v-for="card in overviewCards"
                        :key="card.title"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ card.title }}</h2>
                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">{{ card.subtitle }}</p>
                        </div>

                        <div class="mt-3">
                            <CleanTable :columns="summaryColumns" :rows="card.rows" get-row-key="label" empty="No details recorded.">
                                <template #cell-label="{ row }">
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-zinc-500">{{ row.label }}</span>
                                </template>
                                <template #cell-value="{ row }">
                                    <span
                                        v-if="row.status"
                                        class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1"
                                        :class="statusTone(row.value)"
                                    >
                                        {{ orDash(row.value) }}
                                    </span>
                                    <span
                                        v-else
                                        :class="row.mono ? 'font-mono font-bold text-slate-800 dark:text-zinc-100' : 'font-semibold text-slate-700 dark:text-zinc-200'"
                                    >
                                        {{ row.value }}
                                    </span>
                                </template>
                            </CleanTable>
                        </div>
                    </section>
                </div>

                <template v-if="hasTrace && isLot">
                    <div class="grid gap-4 xl:grid-cols-3">
                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 xl:col-span-2">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex items-start gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                                        <ReceiptText class="h-4 w-4" />
                                    </span>
                                    <div>
                                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Inbound receipt</h2>
                                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Warehouse receiving details and line items.</p>
                                    </div>
                                </div>
                                <span
                                    v-if="trace.inbound"
                                    class="rounded-full px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1"
                                    :class="statusTone(trace.inbound.status)"
                                >
                                    {{ orDash(trace.inbound.status) }}
                                </span>
                            </div>

                            <div class="mt-3 space-y-3">
                                <CleanTable :columns="inboundColumns" :rows="inboundRows" get-row-key="receipt" empty="No receiving record linked.">
                                    <template #cell-receipt="{ row }">
                                        <span class="font-mono font-black text-slate-800 dark:text-zinc-100">{{ row.receipt || '—' }}</span>
                                    </template>
                                    <template #cell-date="{ row }">{{ fdate(row.date) }}</template>
                                    <template #cell-status="{ row }">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1" :class="statusTone(row.status)">
                                            {{ orDash(row.status) }}
                                        </span>
                                    </template>
                                </CleanTable>

                                <div v-if="trace.inbound">
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-wide text-slate-400 dark:text-zinc-500">Received lines</p>
                                    <CleanTable :columns="inboundLineColumns" :rows="inboundLineRows" empty="No lines on this receipt.">
                                        <template #cell-material="{ row }">{{ orDash(row.material) }}</template>
                                        <template #cell-status="{ row }">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1" :class="statusTone(row.status)">
                                                {{ orDash(row.status) }}
                                            </span>
                                        </template>
                                    </CleanTable>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex items-start gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300">
                                    <CreditCard class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Supplier billing</h2>
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Invoice and payment position.</p>
                                </div>
                            </div>

                            <div class="mt-3">
                                <CleanTable :columns="billingColumns" :rows="billingRows" get-row-key="invoice" empty="No supplier invoice linked.">
                                    <template #cell-invoice="{ row }">
                                        <span class="font-mono font-black text-slate-800 dark:text-zinc-100">{{ row.invoice || '—' }}</span>
                                    </template>
                                    <template #cell-amount="{ row }">{{ peso(row.amount) }}</template>
                                    <template #cell-status="{ row }">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1" :class="statusTone(row.status)">
                                            {{ orDash(row.status) }}
                                        </span>
                                    </template>
                                    <template #cell-due="{ row }">{{ fdate(row.due) }}</template>
                                    <template #cell-paid="{ row }">{{ fdate(row.paid) }}</template>
                                    <template #cell-method="{ row }">{{ orDash(row.method) }}</template>
                                </CleanTable>
                            </div>
                        </section>
                    </div>

                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-start gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300">
                                    <Warehouse class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Released to production</h2>
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Warehouse release and remaining balance.</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <CleanTable :columns="releaseColumns" :rows="releaseRows" get-row-key="department" empty="Still in warehouse — not released.">
                                <template #cell-department="{ row }">
                                    <span class="font-semibold uppercase text-slate-700 dark:text-zinc-200">{{ orDash(row.department) }}</span>
                                </template>
                                <template #cell-released_at="{ row }">{{ ftime(row.released_at) }}</template>
                                <template #cell-status="{ row }">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1" :class="statusTone(row.status)">
                                        {{ orDash(row.status) }}
                                    </span>
                                </template>
                            </CleanTable>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-start gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300">
                                    <FlaskConical class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Dye-job consumption</h2>
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Every dye job that used this raw lot.</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                {{ consumptionRows.length }} record{{ consumptionRows.length === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <CleanTable :columns="consumptionColumns" :rows="consumptionRows" get-row-key="dye_job" empty="No dye-job consumption recorded.">
                                <template #cell-dye_job="{ row }">
                                    <span class="font-mono font-black text-slate-800 dark:text-zinc-100">{{ orDash(row.dye_job) }}</span>
                                </template>
                                <template #cell-dye_type="{ row }">{{ orDash(row.dye_type) }}</template>
                                <template #cell-qty_used="{ row }">
                                    <span class="font-bold text-slate-800 dark:text-zinc-100">{{ row.qty_used ?? '—' }} kg</span>
                                </template>
                                <template #cell-fabric="{ row }">
                                    <button
                                        v-if="row.fabric"
                                        type="button"
                                        @click="traceUnit(row.fabric)"
                                        class="font-mono font-black text-indigo-600 hover:underline dark:text-indigo-400"
                                    >
                                        {{ row.fabric }}
                                    </button>
                                    <span v-else>—</span>
                                </template>
                                <template #cell-operator="{ row }">{{ orDash(row.operator) }}</template>
                                <template #cell-processed_at="{ row }">{{ fdate(row.processed_at) }}</template>
                                <template #cell-client="{ row }">{{ orDash(row.client) }}</template>
                            </CleanTable>
                        </div>
                    </section>

                    <div
                        v-if="trace.note"
                        class="flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-[11px] font-bold text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
                    >
                        <Info class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ trace.note }}</span>
                    </div>
                </template>

                <template v-if="hasTrace && isUnit">
                    <div
                        v-if="trace.note"
                        class="flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-[11px] font-bold text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
                    >
                        <Info class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ trace.note }}</span>
                    </div>

                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-start gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                                    <Factory class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Process trail</h2>
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Knit to pack, with the operator and machine at each step.</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                {{ stageRows.length }} step{{ stageRows.length === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <CleanTable :columns="stageColumns" :rows="stageRows" get-row-key="code" empty="No process steps recorded yet.">
                                <template #cell-stage="{ row }">
                                    <span class="inline-flex items-center gap-1.5 font-black uppercase tracking-wide text-indigo-600 dark:text-indigo-400">
                                        <component :is="stageIcon(row.stage)" class="h-3.5 w-3.5" />
                                        {{ orDash(row.stage) }}
                                    </span>
                                </template>
                                <template #cell-code="{ row }">
                                    <span class="font-mono font-black text-slate-800 dark:text-zinc-100">{{ orDash(row.code) }}</span>
                                </template>
                                <template #cell-operator="{ row }">{{ orDash(row.operator) }}</template>
                                <template #cell-machine="{ row }">{{ orDash(row.machine) }}</template>
                                <template #cell-detail="{ row }">{{ orDash(row.detail) }}</template>
                                <template #cell-at="{ row }">{{ ftime(row.at) }}</template>
                                <template #cell-status="{ row }">
                                    <span
                                        v-if="row.status"
                                        class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1"
                                        :class="statusTone(row.status)"
                                    >
                                        {{ row.status }}
                                    </span>
                                    <span v-else class="text-slate-400">—</span>
                                </template>
                                <template #cell-lots="{ row }">
                                    <div v-if="row.lots?.length" class="flex flex-wrap gap-1">
                                        <button
                                            v-for="lot in row.lots"
                                            :key="lot.lot"
                                            type="button"
                                            @click="traceLot(lot.lot)"
                                            class="rounded-md bg-amber-50 px-2 py-0.5 font-mono text-[9px] font-black text-amber-700 ring-1 ring-amber-200 transition hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-500/30"
                                            :title="`${lot.material || 'Raw lot'} · ${lot.used} kg used`"
                                        >
                                            {{ lot.lot }}
                                        </button>
                                    </div>
                                    <span v-else class="text-slate-400">—</span>
                                </template>
                            </CleanTable>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-start gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300">
                                    <Truck class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Deliveries</h2>
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Dispatch, proof of delivery, and client confirmation.</p>
                                </div>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                {{ deliveryRows.length }} deliver{{ deliveryRows.length === 1 ? 'y' : 'ies' }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <CleanTable :columns="deliveryColumns" :rows="deliveryRows" get-row-key="delivery_number" empty="No deliveries linked yet.">
                                <template #cell-delivery_number="{ row }">
                                    <span class="font-mono font-black text-slate-800 dark:text-zinc-100">{{ orDash(row.delivery_number) }}</span>
                                </template>
                                <template #cell-status="{ row }">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1" :class="statusTone(row.status)">
                                        {{ orDash(row.status) }}
                                    </span>
                                </template>
                                <template #cell-truck="{ row }">{{ orDash(row.truck) }}</template>
                                <template #cell-driver="{ row }">{{ orDash(row.driver) }}</template>
                                <template #cell-route="{ row }">{{ orDash(row.route) }}</template>
                                <template #cell-scheduled="{ row }">{{ ftime(row.scheduled) }}</template>
                                <template #cell-departed="{ row }">{{ ftime(row.departed) }}</template>
                                <template #cell-arrived="{ row }">{{ ftime(row.arrived) }}</template>
                                <template #cell-pod_at="{ row }">{{ ftime(row.pod_at) }}</template>
                                <template #cell-client_confirmed="{ row }">
                                    <span
                                        class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wide"
                                        :class="row.client_confirmed ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-zinc-500'"
                                    >
                                        <BadgeCheck v-if="row.client_confirmed" class="h-3.5 w-3.5" />
                                        {{ row.client_confirmed ? 'Yes' : 'No' }}
                                    </span>
                                </template>
                            </CleanTable>
                        </div>
                    </section>
                </template>

                <div v-if="showPicks" class="space-y-4">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Start a trace</h2>
                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Click any record below to run its full genealogy.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                            Latest 12 each
                        </span>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-3">
                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex items-start gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                                    <Factory class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent fabrics</h2>
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Finished rolls with a process trail.</p>
                                </div>
                            </div>

                            <div class="mt-3">
                                <CleanTable :columns="fabricColumns" :rows="recentFabrics ?? []" get-row-key="code" empty="No fabrics yet.">
                                    <template #cell-code="{ row }">
                                        <button
                                            type="button"
                                            @click="traceUnit(row.code)"
                                            class="inline-flex items-center gap-1 font-mono font-black text-indigo-600 transition hover:text-indigo-800 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            {{ row.code }} <ArrowRight class="h-3 w-3" />
                                        </button>
                                    </template>
                                    <template #cell-yarn="{ row }">{{ orDash(row.yarn) }}</template>
                                    <template #cell-status="{ row }">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1" :class="statusTone(row.status)">
                                            {{ orDash(row.status) }}
                                        </span>
                                    </template>
                                    <template #cell-client="{ row }">{{ orDash(row.client) }}</template>
                                </CleanTable>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex items-start gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300">
                                    <Package class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent packages</h2>
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Warehouse packages ready to ship.</p>
                                </div>
                            </div>

                            <div class="mt-3">
                                <CleanTable :columns="packageColumns" :rows="recentPackages ?? []" get-row-key="number" empty="No packages yet.">
                                    <template #cell-number="{ row }">
                                        <button
                                            type="button"
                                            @click="traceUnit(row.number)"
                                            class="inline-flex items-center gap-1 font-mono font-black text-indigo-600 transition hover:text-indigo-800 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            {{ row.number }} <ArrowRight class="h-3 w-3" />
                                        </button>
                                    </template>
                                    <template #cell-status="{ row }">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1" :class="statusTone(row.status)">
                                            {{ orDash(row.status) }}
                                        </span>
                                    </template>
                                    <template #cell-jo="{ row }">{{ orDash(row.jo) }}</template>
                                </CleanTable>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex items-start gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300">
                                    <ClipboardList class="h-4 w-4" />
                                </span>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent raw lots</h2>
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Inbound stock with a control number.</p>
                                </div>
                            </div>

                            <div class="mt-3">
                                <CleanTable :columns="lotColumns" :rows="recentLots ?? []" get-row-key="control" empty="No raw lots yet.">
                                    <template #cell-control="{ row }">
                                        <button
                                            type="button"
                                            @click="traceLot(row.control)"
                                            class="inline-flex items-center gap-1 font-mono font-black text-indigo-600 transition hover:text-indigo-800 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            {{ row.control }} <ArrowRight class="h-3 w-3" />
                                        </button>
                                    </template>
                                    <template #cell-material="{ row }">{{ orDash(row.material) }}</template>
                                    <template #cell-qty="{ row }">
                                        <span class="font-bold text-slate-800 dark:text-zinc-100">{{ row.qty ?? '—' }} {{ row.unit || '' }}</span>
                                    </template>
                                    <template #cell-status="{ row }">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1" :class="statusTone(row.status)">
                                            {{ orDash(row.status) }}
                                        </span>
                                    </template>
                                </CleanTable>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
