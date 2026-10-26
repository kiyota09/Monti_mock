<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    ArrowLeft, Building2, CalendarDays, ClipboardCheck, Factory, FileText, Gauge,
    Landmark, Lock, Printer, Scale, ShieldAlert, TrendingUp, Truck, Users, Wallet,
    Boxes, AlertTriangle, CheckCircle2,
} from 'lucide-vue-next';

const props = defineProps({
    meta: { type: Object, required: true },
    financial: { type: Object, required: true },
    commercial: { type: Object, required: true },
    operations: { type: Object, required: true },
    supply: { type: Object, required: true },
    people: { type: Object, required: true },
    approvals: { type: Object, required: true },
    risks: { type: Array, default: () => [] },
    board_actions: { type: Array, default: () => [] },
    decisions: { type: Array, default: () => [] },
});

const boardActions = computed(() => props.board_actions);

const peso = (v) => {
    const n = Number(v ?? 0);
    const s = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Math.abs(n));
    return (n < 0 ? '-₱' : '₱') + s;
};
const pesoCompact = (v) => {
    const n = Number(v ?? 0);
    const abs = Math.abs(n);
    const sign = n < 0 ? '-' : '';
    if (abs >= 1_000_000) return sign + '₱' + (abs / 1_000_000).toFixed(1) + 'M';
    if (abs >= 1_000) return sign + '₱' + (abs / 1_000).toFixed(0) + 'k';
    return sign + '₱' + abs;
};
const num = (v) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number(v ?? 0));
const count = (v, singular, plural) => `${num(v)} ${Number(v) === 1 ? singular : (plural ?? `${singular}s`)}`;
const pct = (v) => `${Number(v ?? 0).toFixed(1)}%`;
const growthHint = (g) => {
    if (g === null || g === undefined) return null;
    const arrow = g > 0 ? '▲' : g < 0 ? '▼' : '■';
    return `${arrow} ${Math.abs(g).toFixed(1)}% MoM`;
};
const growthTone = (g, invert = false) => {
    if (g === null || g === undefined) return 'text-slate-400';
    if (g === 0) return 'text-slate-500';
    return (g > 0) !== invert ? 'text-emerald-600' : 'text-rose-600';
};
const bar = (v, max) => `${Math.max(2, (Number(v ?? 0) / (max || 1)) * 100)}%`;
const shareWidth = (v) => `${Math.max(Number(v ?? 0) ? 3 : 0, Number(v ?? 0))}%`;

const sections = [
    { id: 'summary', n: '01', title: 'Executive Summary' },
    { id: 'financial', n: '02', title: 'Financial Performance' },
    { id: 'commercial', n: '03', title: 'Commercial Performance' },
    { id: 'operations', n: '04', title: 'Operations & Quality' },
    { id: 'supply', n: '05', title: 'Supply Chain' },
    { id: 'people', n: '06', title: 'People & Organisation' },
    { id: 'risk', n: '07', title: 'Risk & Compliance' },
    { id: 'decisions', n: '08', title: 'Executive Decisions' },
    { id: 'outlook', n: '09', title: 'Outlook & Board Actions' },
];

const activeSection = ref('summary');
let observer = null;

const go = (id) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    activeSection.value = id;
};

onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        if (visible[0]) activeSection.value = visible[0].target.id;
    }, { rootMargin: '-110px 0px -55% 0px', threshold: [0, 0.15] });

    sections.forEach((section) => {
        const el = document.getElementById(section.id);
        if (el) observer.observe(el);
    });
});

onBeforeUnmount(() => observer?.disconnect());

const printPack = () => window.print();

const maxMoney = computed(() => Math.max(1, ...props.financial.monthly.map((m) => Math.max(m.revenue, m.spend))));
const maxOutput = computed(() => Math.max(1, ...props.financial.monthly.map((m) => Math.max(m.output, m.rejects))));
const totalStages = computed(() => Math.max(1, props.operations.stages.reduce((sum, stage) => sum + stage.count, 0)));
const riskCritical = computed(() => props.risks.filter((risk) => risk.severity === 'critical').length);

const execSummary = computed(() => {
    const f = props.financial;
    const c = props.commercial;
    const o = props.operations;
    const p = props.people;
    const growth = f.revenue.growth;
    const direction = growth === null
        ? 'with no comparable prior month'
        : growth >= 0
            ? `up ${growth.toFixed(1)}% month-on-month`
            : `down ${Math.abs(growth).toFixed(1)}% month-on-month`;

    return [
        `Monti Textile recorded revenue of ${peso(f.revenue.mtd)} for ${props.meta.period}, ${direction}, against procurement of ${peso(f.procurement.mtd)} and payroll of ${peso(f.payroll.mtd)}. The period closes with a gross result of ${peso(f.gross_profit)} (${pct(f.gross_margin)}) and a net operating result of ${peso(f.net_result)}.`,
        `Open receivables stand at ${peso(f.receivables)}, of which ${peso(f.overdue_receivables)} is overdue across ${count(f.overdue_invoice_count, 'invoice')}. Payables total ${peso(f.payables)} with ${peso(f.overdue_payables)} past due. Collections for the period were ${peso(f.collected_mtd)}, leaving working capital at ${peso(f.working_capital)}.`,
        `Commercially, ${count(c.open_orders, 'order')} remain open and ${num(c.in_production)} are in production, representing a backlog of ${peso(c.backlog_value)}. ${count(c.active_clients, 'client')} are active and ${count(c.leads_open, 'lead')} remain open against ${num(c.leads_won)} won. The largest client represents ${pct(c.concentration)} of booked revenue.`,
        `The plant processed ${count(o.output_mtd, 'roll')} (${num(o.output_weight)} kg) at a ${pct(o.yield_rate)} yield, with machine availability at ${o.machine_availability}% and ${o.on_time_rate}% on-time delivery. Headcount is ${num(p.headcount)} with ${p.attendance_rate}% attendance today. ${count(props.risks.length, 'risk')} are recorded for board attention and ${count(props.approvals.total, 'executive approval')} remain pending.`,
    ];
});

const summaryTiles = computed(() => {
    const f = props.financial;
    const o = props.operations;
    const p = props.people;
    return [
        { label: 'Revenue (MTD)', value: peso(f.revenue.mtd), hint: growthHint(f.revenue.growth), tone: growthTone(f.revenue.growth), icon: TrendingUp },
        { label: 'Gross result', value: peso(f.gross_profit), hint: `${pct(f.gross_margin)} margin`, tone: f.gross_profit >= 0 ? 'text-emerald-600' : 'text-rose-600', icon: Scale },
        { label: 'Receivables', value: peso(f.receivables), hint: `${peso(f.overdue_receivables)} overdue`, tone: f.overdue_receivables > 0 ? 'text-rose-600' : 'text-slate-400', icon: Wallet },
        { label: 'Payables', value: peso(f.payables), hint: `${peso(f.overdue_payables)} overdue`, tone: f.overdue_payables > 0 ? 'text-amber-600' : 'text-slate-400', icon: Landmark },
        { label: 'Production output', value: `${num(o.output_mtd)} rolls`, hint: growthHint(o.output_growth), tone: growthTone(o.output_growth), icon: Factory },
        { label: 'Headcount', value: num(p.headcount), hint: `${num(p.present_today)} present today`, tone: 'text-slate-400', icon: Users },
    ];
});

const financialRows = computed(() => {
    const f = props.financial;
    return [
        { label: 'Revenue (booked orders)', current: f.revenue.mtd, prior: f.revenue.prev, ytd: f.revenue.ytd, growth: f.revenue.growth, invert: false },
        { label: 'Procurement spend', current: f.procurement.mtd, prior: f.procurement.prev, ytd: f.procurement.ytd, growth: f.procurement.growth, invert: true },
        { label: 'Payroll cost', current: f.payroll.mtd, prior: f.payroll.prev, ytd: f.payroll.ytd, growth: f.payroll.growth, invert: true },
        { label: 'Operating expenses', current: f.expenses_mtd, prior: null, ytd: null, growth: null, invert: true },
    ];
});

const workingCapitalTiles = computed(() => {
    const f = props.financial;
    return [
        { label: 'Receivables', value: peso(f.receivables), hint: `${num(f.overdue_invoice_count)} overdue · ${peso(f.overdue_receivables)}`, tone: f.overdue_receivables > 0 ? 'text-rose-600' : 'text-slate-400', icon: Wallet },
        { label: 'Payables', value: peso(f.payables), hint: `${num(f.overdue_bill_count)} overdue · ${peso(f.overdue_payables)}`, tone: f.overdue_payables > 0 ? 'text-amber-600' : 'text-slate-400', icon: Landmark },
        { label: 'Net working capital', value: peso(f.working_capital), hint: f.working_capital >= 0 ? 'Positive position' : 'Funding required', tone: f.working_capital >= 0 ? 'text-emerald-600' : 'text-rose-600', icon: Scale },
        { label: 'Collections (MTD)', value: peso(f.collected_mtd), hint: `Supplier payments ${peso(f.supplier_paid_mtd)}`, tone: 'text-slate-400', icon: TrendingUp },
    ];
});

const agingRows = (aging) => [
    { label: 'Not yet due', amount: aging.amounts.current, count: aging.counts.current },
    { label: '1 – 30 days', amount: aging.amounts['1_30'], count: aging.counts['1_30'] },
    { label: '31 – 60 days', amount: aging.amounts['31_60'], count: aging.counts['31_60'] },
    { label: '61 – 90 days', amount: aging.amounts['61_90'], count: aging.counts['61_90'] },
    { label: 'Over 90 days', amount: aging.amounts['90_plus'], count: aging.counts['90_plus'] },
];

const commercialTiles = computed(() => {
    const c = props.commercial;
    return [
        { label: 'Active clients', value: num(c.active_clients), hint: `${num(c.leads_open)} leads open`, tone: 'text-slate-400', icon: Building2 },
        { label: 'Open orders', value: num(c.open_orders), hint: `${num(c.in_production)} in production`, tone: 'text-slate-400', icon: ClipboardCheck },
        { label: 'Order backlog', value: peso(c.backlog_value), hint: `${peso(c.in_production_value)} in production`, tone: 'text-slate-400', icon: Scale },
        { label: 'Leads open / won', value: `${num(c.leads_open)} / ${num(c.leads_won)}`, hint: 'Commercial pipeline', tone: 'text-slate-400', icon: TrendingUp },
    ];
});

const operationsTiles = computed(() => {
    const o = props.operations;
    return [
        { label: 'Output (MTD)', value: `${num(o.output_mtd)} rolls`, hint: growthHint(o.output_growth), tone: growthTone(o.output_growth), icon: Factory },
        { label: 'Yield', value: pct(o.yield_rate), hint: `${num(o.rejects_mtd)} rejected`, tone: o.yield_rate >= 95 ? 'text-emerald-600' : 'text-amber-600', icon: CheckCircle2 },
        { label: 'Reject rate', value: pct(o.reject_rate), hint: `${num(o.fabrics_total)} rolls lifetime`, tone: o.reject_rate > 5 ? 'text-rose-600' : 'text-emerald-600', icon: AlertTriangle },
        { label: 'Work in progress', value: num(o.wip), hint: 'Rolls in process', tone: 'text-slate-400', icon: Boxes },
        { label: 'Machine availability', value: `${o.machine_availability}%`, hint: `${o.machines_available}/${o.machines_total} available`, tone: o.machine_availability >= 80 ? 'text-emerald-600' : 'text-amber-600', icon: Gauge },
        { label: 'On-time delivery', value: `${o.on_time_rate}%`, hint: `${o.delivered}/${o.deliveries_total} delivered`, tone: o.on_time_rate >= 90 ? 'text-emerald-600' : 'text-amber-600', icon: Truck },
    ];
});

const supplyTiles = computed(() => {
    const s = props.supply;
    return [
        { label: 'Procurement (MTD)', value: peso(s.procurement_mtd), hint: `${peso(s.procurement_ytd)} YTD`, tone: 'text-slate-400', icon: Boxes },
        { label: 'Approved suppliers', value: num(s.approved_suppliers), hint: `${count(s.open_purchase_orders, 'open PO')}`, tone: 'text-slate-400', icon: Building2 },
        { label: 'Material requests', value: num(s.open_requests), hint: `${num(s.pending_requests)} awaiting approval`, tone: s.pending_requests > 0 ? 'text-amber-600' : 'text-slate-400', icon: ClipboardCheck },
        { label: 'Low-stock items', value: num(s.low_stock_count), hint: s.low_stock_reorder_value > 0 ? peso(s.low_stock_reorder_value) : 'Reorder cost unavailable', tone: s.low_stock_count > 0 ? 'text-rose-600' : 'text-emerald-600', icon: AlertTriangle },
    ];
});

const peopleTiles = computed(() => {
    const p = props.people;
    return [
        { label: 'Active headcount', value: num(p.headcount), hint: 'Company-wide', tone: 'text-slate-400', icon: Users },
        { label: 'Present today', value: num(p.present_today), hint: `${p.attendance_rate}% attendance`, tone: p.attendance_rate >= 90 ? 'text-emerald-600' : 'text-amber-600', icon: CheckCircle2 },
        { label: 'Pending leaves', value: num(p.pending_leaves), hint: 'Awaiting approval', tone: p.pending_leaves > 0 ? 'text-amber-600' : 'text-slate-400', icon: CalendarDays },
        { label: 'Payroll (MTD)', value: peso(p.payroll_mtd), hint: `${peso(p.payroll_ytd)} YTD`, tone: 'text-slate-400', icon: Wallet },
    ];
});

const approvalTiles = computed(() => {
    const a = props.approvals;
    return [
        { label: 'Payroll approvals', value: num(a.payroll), hint: a.payroll_value > 0 ? peso(a.payroll_value) : 'No value pending', tone: a.payroll > 0 ? 'text-amber-600' : 'text-slate-400', icon: Wallet },
        { label: 'Vendor registrations', value: num(a.vendors), hint: 'Awaiting clearance', tone: a.vendors > 0 ? 'text-amber-600' : 'text-slate-400', icon: Building2 },
        { label: 'Credit reviews', value: num(a.credit), hint: 'Client orders on hold', tone: a.credit > 0 ? 'text-amber-600' : 'text-slate-400', icon: Scale },
        { label: 'Material requests', value: num(a.material_requests), hint: 'Awaiting procurement', tone: a.material_requests > 0 ? 'text-amber-600' : 'text-slate-400', icon: Boxes },
    ];
});

const severityChip = {
    critical: 'bg-rose-100 text-rose-700 ring-rose-200',
    high: 'bg-orange-100 text-orange-700 ring-orange-200',
    medium: 'bg-amber-100 text-amber-700 ring-amber-200',
    low: 'bg-sky-100 text-sky-700 ring-sky-200',
};
const severityLabel = {
    critical: 'Critical',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
};

const basis = [
    'Revenue is recognised from booked sales orders, excluding cancelled orders. It is a management figure and is not audited.',
    'Procurement spend represents purchase orders issued in the period and is not a full cost of goods sold; gross margin is indicative only.',
    'Receivables and payables are stated net of recorded payments and include all open finance documents as at the generation date.',
    'Production output counts fabric rolls processed in the period. Reject rate is rejected rolls over output for the same period.',
    'Payroll and headcount are drawn from HR records. Attendance reflects the current day and may be incomplete before shift close.',
    'All figures are system-generated from live module data and are unaudited unless otherwise stated.',
];
</script>

<template>
    <Head title="Board Pack" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-slate-100 dark:bg-zinc-950 print:bg-white">
            <div class="mx-auto max-w-[1440px] px-4 py-5 sm:px-6 print:max-w-none print:p-0">
                <!-- Mobile toolbar -->
                <div class="mb-4 flex flex-wrap items-center gap-2 print:hidden xl:hidden">
                    <Link :href="route('ceo.dashboard')"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-zinc-900 px-3 py-2 text-xs font-bold text-slate-600 dark:text-zinc-300 ring-1 ring-slate-200 dark:ring-zinc-800 transition hover:bg-slate-50">
                        <ArrowLeft class="h-3.5 w-3.5" /> Dashboard
                    </Link>
                    <p class="min-w-0 flex-1 truncate text-sm font-black text-slate-800 dark:text-zinc-100">{{ meta.title }} · {{ meta.period }}</p>
                    <button @click="printPack"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white shadow transition hover:bg-indigo-700">
                        <Printer class="h-3.5 w-3.5" /> Print / PDF
                    </button>
                </div>

                <div class="flex items-start gap-6">
                    <!-- Screen navigation rail -->
                    <aside class="sticky top-6 hidden w-72 shrink-0 xl:block print:hidden">
                        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="border-b border-slate-100 bg-gradient-to-br from-slate-900 to-indigo-900 p-5 text-white dark:border-zinc-800">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25">
                                        <FileText class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-200">Board Pack</p>
                                        <p class="truncate text-sm font-black">{{ meta.period }}</p>
                                    </div>
                                </div>
                                <p class="mt-3 text-[10px] font-bold uppercase tracking-widest text-indigo-200/80">{{ meta.ref }}</p>
                            </div>

                            <nav class="max-h-[calc(100vh-20rem)] space-y-0.5 overflow-y-auto p-2.5">
                                <button v-for="section in sections" :key="section.id" @click="go(section.id)"
                                    :class="[
                                        'flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-xs font-bold transition',
                                        activeSection === section.id
                                            ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300'
                                            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 dark:text-zinc-400 dark:hover:bg-zinc-800/60 dark:hover:text-zinc-200',
                                    ]">
                                    <span :class="[
                                        'font-mono text-[10px] font-black',
                                        activeSection === section.id ? 'text-indigo-500' : 'text-slate-300 dark:text-zinc-600',
                                    ]">{{ section.n }}</span>
                                    <span class="min-w-0 flex-1 truncate">{{ section.title }}</span>
                                </button>
                            </nav>

                            <div class="space-y-2 border-t border-slate-100 p-3 dark:border-zinc-800">
                                <button @click="printPack"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow transition hover:bg-indigo-700 active:scale-[0.99]">
                                    <Printer class="h-4 w-4" /> Print / PDF
                                </button>
                                <Link :href="route('ceo.dashboard')"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                    <ArrowLeft class="h-3.5 w-3.5" /> Back to dashboard
                                </Link>
                            </div>
                        </div>

                        <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-[11px] leading-relaxed text-slate-500 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                            <p class="flex items-center gap-1.5 font-black uppercase tracking-wider text-slate-700 dark:text-zinc-200">
                                <Lock class="h-3.5 w-3.5" /> {{ meta.classification }}
                            </p>
                            <p class="mt-2">Generated {{ meta.generated_at }}</p>
                            <p>Prepared by {{ meta.prepared_by }}</p>
                        </div>
                    </aside>

                    <!-- Document -->
                    <main class="min-w-0 flex-1">
                        <article id="board-doc"
                            class="mx-auto max-w-[920px] overflow-hidden rounded-3xl border border-slate-200 bg-white px-6 py-8 text-slate-900 shadow-xl sm:px-10 sm:py-10 print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none">

                            <!-- ═══ COVER ═══ -->
                            <header class="doc-cover">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p class="text-[11px] font-black uppercase tracking-[0.35em] text-indigo-700">{{ meta.company }}</p>
                                        <p class="mt-2 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-400">Office of the President · Executive Reporting</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 px-3.5 py-2 text-right">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Classification</p>
                                        <p class="mt-0.5 text-[10px] font-black uppercase tracking-wider text-rose-700">{{ meta.classification }}</p>
                                    </div>
                                </div>

                                <div class="mt-10 border-y-4 border-double border-slate-900 py-7">
                                    <p class="text-[11px] font-black uppercase tracking-[0.3em] text-slate-400">{{ meta.meeting }}</p>
                                    <h1 class="mt-2 text-3xl font-black leading-tight tracking-tight sm:text-4xl">{{ meta.title }}</h1>
                                    <p class="mt-2 text-lg font-bold text-slate-500">{{ meta.period }} <span class="text-slate-300">·</span> {{ meta.fiscal_year }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-400">Reporting period {{ meta.period_range }}</p>
                                </div>

                                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Reference</p>
                                        <p class="mt-0.5 font-mono text-sm font-black">{{ meta.ref }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Prepared for</p>
                                        <p class="mt-0.5 text-sm font-black">{{ meta.prepared_for }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Prepared by</p>
                                        <p class="mt-0.5 text-sm font-black">{{ meta.prepared_by }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Generated</p>
                                        <p class="mt-0.5 text-sm font-black">{{ meta.generated_at }}</p>
                                    </div>
                                </div>

                                <div class="mt-8">
                                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Agenda</p>
                                    <ol class="mt-3 grid gap-x-8 gap-y-2 sm:grid-cols-2">
                                        <li v-for="section in sections" :key="section.id" class="flex items-baseline gap-2.5 border-b border-dotted border-slate-200 pb-1.5">
                                            <span class="font-mono text-[10px] font-black text-indigo-600">{{ section.n }}</span>
                                            <span class="text-xs font-bold text-slate-700">{{ section.title }}</span>
                                        </li>
                                    </ol>
                                </div>

                                <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-4 text-[10px] leading-relaxed text-slate-500">
                                    <p class="font-black uppercase tracking-widest text-slate-600">Confidentiality notice</p>
                                    <p class="mt-1.5">This document is prepared exclusively for the Board of Directors of {{ meta.company }}. It contains confidential management information and may not be reproduced, distributed, or relied upon by any other party without the prior written consent of the President.</p>
                                </div>

                                <div class="mt-8 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 text-[9px] font-bold uppercase tracking-[0.2em] text-slate-400">
                                    <span>{{ meta.ref }}</span>
                                    <span>{{ meta.classification }}</span>
                                </div>
                            </header>

                            <!-- ═══ 01 EXECUTIVE SUMMARY ═══ -->
                            <section id="summary" class="doc-section scroll-mt-24">
                                <p class="doc-kicker"><span>01</span> Executive Summary</p>
                                <h2 class="doc-title">Performance for {{ meta.period }}</h2>
                                <p class="doc-sub">Management commentary on the period ended {{ meta.period_range }}</p>

                                <div class="mt-5 space-y-3 text-[13px] leading-relaxed text-slate-700">
                                    <p v-for="(para, i) in execSummary" :key="i">{{ para }}</p>
                                </div>

                                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    <div v-for="tile in summaryTiles" :key="tile.label" class="avoid-break rounded-2xl border border-slate-200 bg-slate-50/60 p-3.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ tile.label }}</p>
                                            <component :is="tile.icon" class="h-4 w-4 shrink-0 text-slate-300" />
                                        </div>
                                        <p class="mt-1.5 text-lg font-black tracking-tight tabular-nums">{{ tile.value }}</p>
                                        <p class="text-[10px] font-bold" :class="tile.tone">{{ tile.hint }}</p>
                                    </div>
                                </div>

                                <div v-if="risks.length" class="avoid-break mt-6 rounded-xl border-l-4 border-rose-500 bg-rose-50/70 p-4">
                                    <p class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-rose-700">
                                        <ShieldAlert class="h-4 w-4" /> Board attention required
                                    </p>
                                    <ul class="mt-2.5 space-y-2">
                                        <li v-for="risk in risks.slice(0, 3)" :key="risk.area" class="flex items-start gap-2 text-[11px] leading-snug text-slate-600">
                                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500" />
                                            <span><strong class="text-slate-800">{{ risk.area }}:</strong> {{ risk.description }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </section>

                            <!-- ═══ 02 FINANCIAL PERFORMANCE ═══ -->
                            <section id="financial" class="doc-section scroll-mt-24">
                                <p class="doc-kicker"><span>02</span> Financial Performance</p>
                                <h2 class="doc-title">Financial review</h2>
                                <p class="doc-sub">Revenue, cost and working-capital position</p>

                                <div class="mt-5 overflow-x-auto">
                                    <table class="doc-table w-full text-xs">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Metric</th>
                                                <th class="text-right">This month</th>
                                                <th class="text-right">Prior month</th>
                                                <th class="text-right">Year to date</th>
                                                <th class="text-right">Change / margin</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="row in financialRows" :key="row.label">
                                                <td class="font-bold">{{ row.label }}</td>
                                                <td class="text-right tabular-nums">{{ peso(row.current) }}</td>
                                                <td class="text-right tabular-nums text-slate-500">{{ row.prior === null ? '—' : peso(row.prior) }}</td>
                                                <td class="text-right tabular-nums text-slate-500">{{ row.ytd === null ? '—' : peso(row.ytd) }}</td>
                                                <td class="text-right font-bold tabular-nums" :class="growthTone(row.growth, row.invert)">{{ row.growth === null ? '—' : growthHint(row.growth) }}</td>
                                            </tr>
                                            <tr class="border-t-2 border-slate-900">
                                                <td class="font-black">Gross result</td>
                                                <td class="text-right font-black tabular-nums">{{ peso(financial.gross_profit) }}</td>
                                                <td class="text-right text-slate-400">—</td>
                                                <td class="text-right text-slate-400">—</td>
                                                <td class="text-right font-black tabular-nums" :class="financial.gross_profit >= 0 ? 'text-emerald-600' : 'text-rose-600'">{{ pct(financial.gross_margin) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="font-black">Net operating result</td>
                                                <td class="text-right font-black tabular-nums">{{ peso(financial.net_result) }}</td>
                                                <td class="text-right text-slate-400">—</td>
                                                <td class="text-right text-slate-400">—</td>
                                                <td class="text-right text-slate-400">—</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <h3 class="doc-h3">Working capital</h3>
                                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                                    <div v-for="tile in workingCapitalTiles" :key="tile.label" class="avoid-break rounded-xl border border-slate-200 p-3">
                                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ tile.label }}</p>
                                        <p class="mt-1 text-sm font-black tabular-nums">{{ tile.value }}</p>
                                        <p class="text-[10px] font-bold" :class="tile.tone">{{ tile.hint }}</p>
                                    </div>
                                </div>

                                <h3 class="doc-h3">Aging of receivables and payables</h3>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="overflow-hidden rounded-xl border border-slate-200">
                                        <p class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-500">Receivables</p>
                                        <table class="doc-table w-full text-[11px]">
                                            <thead><tr><th class="text-left">Bucket</th><th class="text-right">Docs</th><th class="text-right">Amount</th></tr></thead>
                                            <tbody>
                                                <tr v-for="row in agingRows(financial.ar_aging)" :key="row.label">
                                                    <td>{{ row.label }}</td>
                                                    <td class="text-right tabular-nums text-slate-500">{{ row.count }}</td>
                                                    <td class="text-right tabular-nums">{{ peso(row.amount) }}</td>
                                                </tr>
                                                <tr class="border-t-2 border-slate-900">
                                                    <td class="font-black">Total</td>
                                                    <td class="text-right font-black tabular-nums">{{ num(financial.ar_aging.count) }}</td>
                                                    <td class="text-right font-black tabular-nums">{{ peso(financial.receivables) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="overflow-hidden rounded-xl border border-slate-200">
                                        <p class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-500">Payables</p>
                                        <table class="doc-table w-full text-[11px]">
                                            <thead><tr><th class="text-left">Bucket</th><th class="text-right">Docs</th><th class="text-right">Amount</th></tr></thead>
                                            <tbody>
                                                <tr v-for="row in agingRows(financial.ap_aging)" :key="row.label">
                                                    <td>{{ row.label }}</td>
                                                    <td class="text-right tabular-nums text-slate-500">{{ row.count }}</td>
                                                    <td class="text-right tabular-nums">{{ peso(row.amount) }}</td>
                                                </tr>
                                                <tr class="border-t-2 border-slate-900">
                                                    <td class="font-black">Total</td>
                                                    <td class="text-right font-black tabular-nums">{{ num(financial.ap_aging.count) }}</td>
                                                    <td class="text-right font-black tabular-nums">{{ peso(financial.payables) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <h3 class="doc-h3">Twelve-month performance</h3>
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <div class="mb-3 flex flex-wrap gap-4 text-[9px] font-black uppercase tracking-widest text-slate-500">
                                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-indigo-600" /> Revenue</span>
                                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-rose-400" /> Procurement spend</span>
                                    </div>
                                    <div class="flex h-40 items-end gap-1.5">
                                        <div v-for="month in financial.monthly" :key="month.label" class="flex min-w-0 flex-1 flex-col items-center gap-1">
                                            <div class="flex h-32 w-full items-end justify-center gap-[3px]">
                                                <div class="w-1/2 max-w-[10px] rounded-t bg-indigo-600" :style="{ height: bar(month.revenue, maxMoney) }" :title="`${month.label}: ${peso(month.revenue)}`" />
                                                <div class="w-1/2 max-w-[10px] rounded-t bg-rose-400" :style="{ height: bar(month.spend, maxMoney) }" :title="`${month.label}: ${peso(month.spend)}`" />
                                            </div>
                                            <span class="truncate text-[8px] font-black uppercase text-slate-400">{{ month.short }}</span>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-[10px] font-bold text-slate-400">Peak month: {{ pesoCompact(Math.max(...financial.monthly.map((m) => m.revenue))) }} revenue · figures in Philippine peso.</p>
                                </div>

                                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="doc-table w-full min-w-[560px] text-[11px]">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Month</th>
                                                <th class="text-right">Revenue</th>
                                                <th class="text-right">Procurement</th>
                                                <th class="text-right">Gross</th>
                                                <th class="text-right">Payroll</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="month in financial.monthly" :key="month.label">
                                                <td class="font-bold">{{ month.label }}</td>
                                                <td class="text-right tabular-nums">{{ peso(month.revenue) }}</td>
                                                <td class="text-right tabular-nums">{{ peso(month.spend) }}</td>
                                                <td class="text-right tabular-nums" :class="month.gross >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ peso(month.gross) }}</td>
                                                <td class="text-right tabular-nums text-slate-500">{{ peso(month.payroll) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <!-- ═══ 03 COMMERCIAL PERFORMANCE ═══ -->
                            <section id="commercial" class="doc-section scroll-mt-24">
                                <p class="doc-kicker"><span>03</span> Commercial Performance</p>
                                <h2 class="doc-title">Sales, clients and order book</h2>
                                <p class="doc-sub">Commercial pipeline and customer concentration</p>

                                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <div v-for="tile in commercialTiles" :key="tile.label" class="avoid-break rounded-2xl border border-slate-200 bg-slate-50/60 p-3.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ tile.label }}</p>
                                            <component :is="tile.icon" class="h-4 w-4 shrink-0 text-slate-300" />
                                        </div>
                                        <p class="mt-1.5 text-lg font-black tracking-tight tabular-nums">{{ tile.value }}</p>
                                        <p class="text-[10px] font-bold" :class="tile.tone">{{ tile.hint }}</p>
                                    </div>
                                </div>

                                <h3 class="doc-h3">Top clients by booked revenue</h3>
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="doc-table w-full min-w-[560px] text-xs">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Client</th>
                                                <th class="text-right">Orders</th>
                                                <th class="text-right">Revenue</th>
                                                <th class="text-right">Share</th>
                                                <th class="w-[30%] text-left">Concentration</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="client in commercial.top_clients" :key="client.name">
                                                <td class="font-bold">{{ client.name }}</td>
                                                <td class="text-right tabular-nums">{{ num(client.orders) }}</td>
                                                <td class="text-right tabular-nums">{{ peso(client.revenue) }}</td>
                                                <td class="text-right tabular-nums">{{ pct(client.share) }}</td>
                                                <td>
                                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                                        <div class="h-full rounded-full bg-indigo-600" :style="{ width: shareWidth(client.share) }" />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="!commercial.top_clients.length">
                                                <td colspan="5" class="py-5 text-center text-slate-400">No billed clients for the period.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="avoid-break mt-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 text-[11px] leading-relaxed text-slate-500">
                                    <strong class="text-slate-700">Concentration note.</strong>
                                    The largest client accounts for {{ pct(commercial.concentration) }} of booked revenue.
                                    {{ commercial.concentration > 40 ? 'Concentration above 40% warrants diversification and credit monitoring.' : 'Concentration remains within an acceptable tolerance for the board.' }}
                                </div>

                                <h3 class="doc-h3">Order pipeline</h3>
                                <div class="space-y-2">
                                    <div v-for="stage in commercial.pipeline" :key="stage.key" class="avoid-break flex items-center gap-3">
                                        <span class="w-28 shrink-0 text-[11px] font-bold text-slate-500">{{ stage.label }}</span>
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-slate-800" :style="{ width: shareWidth(stage.percent) }" />
                                        </div>
                                        <span class="w-10 text-right text-[11px] font-black tabular-nums">{{ num(stage.count) }}</span>
                                        <span class="w-24 text-right text-[11px] tabular-nums text-slate-500">{{ peso(stage.value) }}</span>
                                    </div>
                                </div>
                            </section>

                            <!-- ═══ 04 OPERATIONS ═══ -->
                            <section id="operations" class="doc-section scroll-mt-24">
                                <p class="doc-kicker"><span>04</span> Operations &amp; Quality</p>
                                <h2 class="doc-title">Production, quality and delivery</h2>
                                <p class="doc-sub">Plant throughput, yield and service performance</p>

                                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    <div v-for="tile in operationsTiles" :key="tile.label" class="avoid-break rounded-2xl border border-slate-200 bg-slate-50/60 p-3.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ tile.label }}</p>
                                            <component :is="tile.icon" class="h-4 w-4 shrink-0 text-slate-300" />
                                        </div>
                                        <p class="mt-1.5 text-lg font-black tracking-tight tabular-nums">{{ tile.value }}</p>
                                        <p class="text-[10px] font-bold" :class="tile.tone">{{ tile.hint }}</p>
                                    </div>
                                </div>

                                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                    <div>
                                        <h3 class="doc-h3 mt-0">Work in progress by stage</h3>
                                        <div class="space-y-2">
                                            <div v-for="stage in operations.stages" :key="stage.key" class="flex items-center gap-3">
                                                <span class="w-28 shrink-0 text-[11px] font-bold text-slate-500">{{ stage.label }}</span>
                                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                    <div class="h-full rounded-full bg-slate-800" :style="{ width: stage.count ? `${Math.max(4, (stage.count / totalStages) * 100)}%` : '0%' }" />
                                                </div>
                                                <span class="w-8 text-right text-[11px] font-black tabular-nums">{{ num(stage.count) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 class="doc-h3 mt-0">Output vs rejects</h3>
                                        <div class="rounded-xl border border-slate-200 p-3">
                                            <div class="mb-2 flex gap-4 text-[9px] font-black uppercase tracking-widest text-slate-500">
                                                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500" /> Output</span>
                                                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-rose-400" /> Rejects</span>
                                            </div>
                                            <div class="flex h-28 items-end gap-1.5">
                                                <div v-for="month in financial.monthly" :key="month.label" class="flex min-w-0 flex-1 flex-col items-center gap-1">
                                                    <div class="flex h-20 w-full items-end justify-center gap-[3px]">
                                                        <div class="w-1/2 max-w-[9px] rounded-t bg-emerald-500" :style="{ height: bar(month.output, maxOutput) }" :title="`${month.label}: ${num(month.output)} rolls`" />
                                                        <div class="w-1/2 max-w-[9px] rounded-t bg-rose-400" :style="{ height: bar(month.rejects, maxOutput) }" :title="`${month.label}: ${num(month.rejects)} rejected`" />
                                                    </div>
                                                    <span class="truncate text-[7px] font-black uppercase text-slate-400">{{ month.short }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h3 class="doc-h3">Delivery performance</h3>
                                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                                    <div class="rounded-xl border border-slate-200 p-3">
                                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Total deliveries</p>
                                        <p class="mt-1 text-sm font-black tabular-nums">{{ num(operations.deliveries_total) }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 p-3">
                                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Delivered</p>
                                        <p class="mt-1 text-sm font-black tabular-nums">{{ num(operations.delivered) }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 p-3">
                                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">In transit</p>
                                        <p class="mt-1 text-sm font-black tabular-nums">{{ num(operations.in_transit) }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 p-3">
                                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">On-time rate</p>
                                        <p class="mt-1 text-sm font-black tabular-nums">{{ operations.on_time_rate }}%</p>
                                    </div>
                                </div>
                            </section>

                            <!-- ═══ 05 SUPPLY CHAIN ═══ -->
                            <section id="supply" class="doc-section scroll-mt-24">
                                <p class="doc-kicker"><span>05</span> Supply Chain</p>
                                <h2 class="doc-title">Procurement and materials</h2>
                                <p class="doc-sub">Supplier spend, open orders and inventory risk</p>

                                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <div v-for="tile in supplyTiles" :key="tile.label" class="avoid-break rounded-2xl border border-slate-200 bg-slate-50/60 p-3.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ tile.label }}</p>
                                            <component :is="tile.icon" class="h-4 w-4 shrink-0 text-slate-300" />
                                        </div>
                                        <p class="mt-1.5 text-lg font-black tracking-tight tabular-nums">{{ tile.value }}</p>
                                        <p class="text-[10px] font-bold" :class="tile.tone">{{ tile.hint }}</p>
                                    </div>
                                </div>

                                <h3 class="doc-h3">Materials below reorder point</h3>
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="doc-table w-full min-w-[560px] text-[11px]">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Material</th>
                                                <th class="text-left">Category</th>
                                                <th class="text-right">On hand</th>
                                                <th class="text-right">Reorder point</th>
                                                <th class="text-right">Shortage</th>
                                                <th class="text-right">Est. reorder value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="material in supply.low_stock" :key="material.id">
                                                <td class="font-bold">{{ material.name }}<span class="ml-1 font-mono text-[9px] text-slate-400">{{ material.mat_id }}</span></td>
                                                <td class="text-slate-500">{{ material.category }}</td>
                                                <td class="text-right tabular-nums">{{ num(material.stock) }} {{ material.unit }}</td>
                                                <td class="text-right tabular-nums text-slate-500">{{ num(material.reorder_point) }} {{ material.unit }}</td>
                                                <td class="text-right font-bold tabular-nums text-rose-700">{{ num(material.shortage) }} {{ material.unit }}</td>
                                                <td class="text-right tabular-nums">{{ material.unit_cost > 0 ? peso(material.shortage * material.unit_cost) : '—' }}</td>
                                            </tr>
                                            <tr v-if="!supply.low_stock.length">
                                                <td colspan="6" class="py-5 text-center text-slate-400">All materials are above their reorder point.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-if="supply.low_stock_reorder_value <= 0 && supply.low_stock.length" class="mt-2 text-[10px] font-bold text-slate-400">
                                    Estimated reorder value is unavailable because material unit costs are not yet maintained.
                                </p>

                                <h3 class="doc-h3">Top suppliers by procurement spend</h3>
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="doc-table w-full min-w-[480px] text-[11px]">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Supplier</th>
                                                <th class="text-right">POs</th>
                                                <th class="text-right">Spend</th>
                                                <th class="text-right">Share</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="supplier in supply.top_suppliers" :key="supplier.name">
                                                <td class="font-bold">{{ supplier.name }}</td>
                                                <td class="text-right tabular-nums">{{ num(supplier.orders) }}</td>
                                                <td class="text-right tabular-nums">{{ peso(supplier.spend) }}</td>
                                                <td class="text-right tabular-nums">{{ pct(supplier.share) }}</td>
                                            </tr>
                                            <tr v-if="!supply.top_suppliers.length">
                                                <td colspan="4" class="py-5 text-center text-slate-400">No procurement activity recorded.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <!-- ═══ 06 PEOPLE ═══ -->
                            <section id="people" class="doc-section scroll-mt-24">
                                <p class="doc-kicker"><span>06</span> People &amp; Organisation</p>
                                <h2 class="doc-title">Workforce and payroll</h2>
                                <p class="doc-sub">Headcount, attendance and organisational distribution</p>

                                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <div v-for="tile in peopleTiles" :key="tile.label" class="avoid-break rounded-2xl border border-slate-200 bg-slate-50/60 p-3.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ tile.label }}</p>
                                            <component :is="tile.icon" class="h-4 w-4 shrink-0 text-slate-300" />
                                        </div>
                                        <p class="mt-1.5 text-lg font-black tracking-tight tabular-nums">{{ tile.value }}</p>
                                        <p class="text-[10px] font-bold" :class="tile.tone">{{ tile.hint }}</p>
                                    </div>
                                </div>

                                <h3 class="doc-h3">Headcount by department</h3>
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="doc-table w-full min-w-[480px] text-[11px]">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Department</th>
                                                <th class="text-right">Headcount</th>
                                                <th class="text-right">Share</th>
                                                <th class="w-[35%] text-left">Distribution</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="department in people.departments" :key="department.department">
                                                <td class="font-bold">{{ department.department }}</td>
                                                <td class="text-right tabular-nums">{{ num(department.total) }}</td>
                                                <td class="text-right tabular-nums">{{ pct(department.share) }}</td>
                                                <td>
                                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                                        <div class="h-full rounded-full bg-violet-500" :style="{ width: shareWidth(department.share) }" />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="!people.departments.length">
                                                <td colspan="4" class="py-5 text-center text-slate-400">No active personnel records.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <!-- ═══ 07 RISK ═══ -->
                            <section id="risk" class="doc-section scroll-mt-24">
                                <p class="doc-kicker"><span>07</span> Risk &amp; Compliance</p>
                                <h2 class="doc-title">Risk register</h2>
                                <p class="doc-sub">Material exceptions requiring board oversight · {{ count(risks.length, 'item') }} recorded</p>

                                <div v-if="risks.length" class="mt-5 overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="doc-table w-full min-w-[680px] text-[11px]">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Risk area</th>
                                                <th class="text-left">Description</th>
                                                <th class="text-right">Exposure</th>
                                                <th class="text-left">Severity</th>
                                                <th class="text-left">Owner</th>
                                                <th class="text-left">Mitigation</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="risk in risks" :key="risk.area">
                                                <td class="font-black">{{ risk.area }}</td>
                                                <td class="text-slate-600">{{ risk.description }}</td>
                                                <td class="text-right font-bold tabular-nums">{{ risk.exposure }}</td>
                                                <td>
                                                    <span class="inline-flex rounded-full px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1" :class="severityChip[risk.severity]">
                                                        {{ severityLabel[risk.severity] }}
                                                    </span>
                                                </td>
                                                <td class="text-slate-500">{{ risk.owner }}</td>
                                                <td class="text-slate-600">{{ risk.mitigation }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="mt-5 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center">
                                    <CheckCircle2 class="mx-auto h-6 w-6 text-emerald-500" />
                                    <p class="mt-2 text-sm font-bold text-slate-600">No material risks recorded for the period.</p>
                                    <p class="text-xs text-slate-400">Exceptions will appear here as they arise across the modules.</p>
                                </div>

                                <div v-if="riskCritical" class="avoid-break mt-3 rounded-xl border-l-4 border-rose-500 bg-rose-50/70 p-3.5 text-[11px] leading-relaxed text-rose-800">
                                    <strong>{{ count(riskCritical, 'critical risk') }}</strong> require a board resolution or delegated action before the next reporting cycle.
                                </div>
                            </section>

                            <!-- ═══ 08 DECISIONS ═══ -->
                            <section id="decisions" class="doc-section scroll-mt-24">
                                <p class="doc-kicker"><span>08</span> Executive Decisions</p>
                                <h2 class="doc-title">Approvals and decision log</h2>
                                <p class="doc-sub">Actions taken in the period and items awaiting the President</p>

                                <div class="mt-5 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                                    <div v-for="tile in approvalTiles" :key="tile.label" class="avoid-break rounded-xl border border-slate-200 p-3">
                                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ tile.label }}</p>
                                        <p class="mt-1 text-lg font-black tabular-nums">{{ tile.value }}</p>
                                        <p class="text-[10px] font-bold" :class="tile.tone">{{ tile.hint }}</p>
                                    </div>
                                </div>

                                <h3 class="doc-h3">Decision log</h3>
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="doc-table w-full min-w-[640px] text-[11px]">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Date</th>
                                                <th class="text-left">Action</th>
                                                <th class="text-left">Subject</th>
                                                <th class="text-left">Decision</th>
                                                <th class="text-left">By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="decision in decisions" :key="decision.id">
                                                <td class="whitespace-nowrap font-bold">{{ decision.date }}</td>
                                                <td class="text-slate-500">{{ decision.action }}</td>
                                                <td class="text-slate-700">
                                                    {{ decision.subject }}
                                                    <span v-if="decision.reason" class="block text-[10px] text-slate-400">Reason: {{ decision.reason }}</span>
                                                </td>
                                                <td>
                                                    <span class="inline-flex rounded px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide"
                                                        :class="decision.decision === 'Approved' ? 'bg-emerald-100 text-emerald-800' : decision.decision === 'Rejected' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600'">
                                                        {{ decision.decision }}
                                                    </span>
                                                </td>
                                                <td class="whitespace-nowrap text-slate-500">{{ decision.actor }}</td>
                                            </tr>
                                            <tr v-if="!decisions.length">
                                                <td colspan="5" class="py-5 text-center text-slate-400">No decisions recorded in this period.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <!-- ═══ 09 OUTLOOK ═══ -->
                            <section id="outlook" class="doc-section scroll-mt-24">
                                <p class="doc-kicker"><span>09</span> Outlook &amp; Board Actions</p>
                                <h2 class="doc-title">Matters for board action</h2>
                                <p class="doc-sub">Recommended resolutions arising from the period</p>

                                <div class="mt-5 space-y-2.5">
                                    <div v-for="(action, index) in boardActions" :key="action.title" class="avoid-break flex items-start gap-3 rounded-xl border border-slate-200 p-3.5">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-900 font-mono text-[10px] font-black text-white">{{ String(index + 1).padStart(2, '0') }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-black text-slate-800">{{ action.title }}</p>
                                            <p class="mt-0.5 text-[10px] font-bold text-slate-400">{{ action.owner }} · {{ action.metric }}</p>
                                        </div>
                                        <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide ring-1" :class="severityChip[action.priority]">{{ severityLabel[action.priority] }}</span>
                                    </div>
                                    <div v-if="!boardActions.length" class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center">
                                        <CheckCircle2 class="mx-auto h-6 w-6 text-emerald-500" />
                                        <p class="mt-2 text-sm font-bold text-slate-600">No board actions outstanding.</p>
                                    </div>
                                </div>

                                <div class="avoid-break mt-5 rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-[11px] leading-relaxed text-slate-600">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Management recommendation</p>
                                    <p class="mt-1.5">
                                        Management recommends that the Board note the financial and operating results for {{ meta.period }},
                                        approve the matters listed above within the stated ownership, and direct management to report
                                        progress against each action at the next regular meeting.
                                    </p>
                                </div>
                            </section>

                            <!-- ═══ BASIS + ATTESTATION ═══ -->
                            <section id="attestation" class="doc-section no-break scroll-mt-24">
                                <p class="doc-kicker"><span>—</span> Basis of Preparation &amp; Attestation</p>
                                <h2 class="doc-title">Notes and sign-off</h2>
                                <p class="doc-sub">Basis of preparation and attestation of the board pack</p>

                                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Basis of preparation</p>
                                    <ol class="mt-2 grid gap-x-6 gap-y-1.5 text-[10px] leading-relaxed text-slate-500 sm:grid-cols-2">
                                        <li v-for="(note, index) in basis" :key="index" class="flex gap-1.5">
                                            <span class="font-mono font-black text-slate-400">{{ index + 1 }}.</span>
                                            <span>{{ note }}</span>
                                        </li>
                                    </ol>
                                </div>

                                <div class="mt-8 grid grid-cols-1 gap-8 sm:grid-cols-3">
                                    <div v-for="role in ['Prepared by', 'Reviewed by', 'Approved by']" :key="role" class="pt-6">
                                        <div class="border-t-2 border-slate-900 pt-2">
                                            <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">{{ role }}</p>
                                            <p class="mt-6 text-[10px] text-slate-400">Signature over printed name / Date</p>
                                        </div>
                                    </div>
                                </div>

                                <footer class="mt-8 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">
                                    <span>{{ meta.company }} · Board Pack {{ meta.ref }}</span>
                                    <span>{{ meta.classification }} · {{ meta.generated_at }}</span>
                                </footer>
                            </section>
                        </article>
                    </main>
                </div>
            </div>

            <!-- Print-only running footer -->
            <div class="print-footer">
                <span>{{ meta.company }} · Board Pack {{ meta.ref }} · {{ meta.classification }}</span>
                <span>Page <span class="page-num" /></span>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
#board-doc {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.doc-kicker {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    color: #6366f1;
}
.doc-kicker span {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    color: #94a3b8;
}
.doc-title {
    margin-top: 0.35rem;
    font-size: 1.35rem;
    font-weight: 900;
    letter-spacing: -0.015em;
    line-height: 1.2;
}
.doc-sub {
    margin-top: 0.2rem;
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
}
.doc-h3 {
    margin-top: 1.6rem;
    margin-bottom: 0.6rem;
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 0.35rem;
}

.doc-table thead tr {
    text-transform: uppercase;
    font-size: 9px;
    letter-spacing: 0.08em;
    color: #64748b;
    border-bottom: 2px solid #0f172a;
}
.doc-table th { padding: 0.5rem 0.7rem; font-weight: 900; }
.doc-table td { padding: 0.42rem 0.7rem; border-bottom: 1px solid #f1f5f9; }
.doc-table tbody tr:last-child td { border-bottom: none; }

@media screen {
    .doc-section {
        margin-top: 2.75rem;
        padding-top: 2rem;
        border-top: 1px dashed #e2e8f0;
    }
    .doc-cover {
        padding-bottom: 1.5rem;
    }
}

@page { size: A4; margin: 14mm 12mm 18mm; }

@media print {
    .doc-section {
        break-before: page;
        margin: 0;
        padding: 0;
        border: 0;
    }
    .doc-section.no-break { break-before: auto; }
    .avoid-break { break-inside: avoid; }
    #board-doc tr { break-inside: avoid; }
    #board-doc thead { display: table-header-group; }
    #board-doc { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    #board-doc > * + * { margin-top: 0; }

    .print-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        display: flex;
        justify-content: space-between;
        font-size: 8px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: #64748b;
        border-top: 1px solid #94a3b8;
        padding-top: 5px;
    }
    .print-footer .page-num::after { content: counter(page) ' of ' counter(pages); }
}

@media screen {
    .print-footer { display: none; }
}
</style>
