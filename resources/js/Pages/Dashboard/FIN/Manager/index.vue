<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { route } from 'ziggy-js';
import {
    Wallet, TrendingUp, Landmark, HandCoins, Banknote, PiggyBank,
    ReceiptText, ArrowDownToLine, ArrowUpFromLine, CalendarClock,
    FileChartColumn, Sparkles, ChevronRight, CircleAlert, X
} from 'lucide-vue-next';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    revenueTrend: { type: Array, default: () => [] },
    cashFlow: { type: Array, default: () => [] },
    arAging: { type: Array, default: () => [] },
    apAging: { type: Array, default: () => [] },
    recentTransactions: { type: Array, default: () => [] },
    budgets: { type: Array, default: () => [] },
    isDummy: { type: Boolean, default: false },
});

const peso = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));

const maxRevExp = computed(() => Math.max(1, ...props.revenueTrend.flatMap(r => [Number(r.revenue ?? 0), Number(r.expenses ?? 0)])));
const maxFlow = computed(() => Math.max(1, ...props.cashFlow.flatMap(c => [Number(c.inflow ?? 0), Number(c.outflow ?? 0)])));
const maxAging = computed(() => Math.max(1,
    ...props.arAging.map(a => Number(a.amount ?? 0)),
    ...props.apAging.map(a => Number(a.amount ?? 0)),
));

const kpis = computed(() => [
    { label: 'Revenue MTD', value: peso(props.stats?.revenueMonth), sub: `YTD ${peso(props.stats?.revenueYtd)}`, icon: TrendingUp, grad: 'from-emerald-500 via-teal-600 to-cyan-700', chip: 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30' },
    { label: 'Outstanding AR', value: peso(props.stats?.outstandingAR), sub: `${peso(props.stats?.overdueAR)} overdue`, icon: HandCoins, grad: 'from-blue-600 via-indigo-600 to-violet-700', chip: 'bg-rose-500/15 text-rose-300 ring-rose-500/30', alert: true },
    { label: 'Outstanding AP', value: peso(props.stats?.outstandingAP), sub: `${peso(props.stats?.overdueAP)} overdue`, icon: ReceiptText, grad: 'from-amber-500 via-orange-600 to-rose-600', chip: 'bg-amber-500/15 text-amber-300 ring-amber-500/30', alert: true },
    { label: 'Cash on Hand', value: peso(props.stats?.cashOnHand), sub: 'Across all accounts', icon: Wallet, grad: 'from-sky-500 via-blue-600 to-indigo-700', chip: 'bg-sky-500/15 text-sky-300 ring-sky-500/30' },
    { label: 'Net Profit (Mo)', value: peso(props.stats?.netProfitMonth), sub: `Expenses ${peso(props.stats?.expenseMonth)}`, icon: PiggyBank, grad: 'from-violet-600 via-purple-600 to-fuchsia-700', chip: 'bg-violet-500/15 text-violet-300 ring-violet-500/30' },
    { label: 'Payroll (Mo)', value: peso(props.stats?.payrollMonth), sub: 'Salaries + benefits', icon: Banknote, grad: 'from-slate-500 via-gray-600 to-zinc-700', chip: 'bg-zinc-500/15 text-zinc-300 ring-zinc-500/30' },
]);

const quickLinks = [
    { label: 'Receivables', desc: 'Invoices & collections', icon: HandCoins, grad: 'from-blue-600 via-indigo-600 to-violet-700', href: 'fin.manager.receivables' },
    { label: 'Payables', desc: 'Bills & vendors', icon: ReceiptText, grad: 'from-amber-500 via-orange-600 to-rose-600', href: 'fin.manager.payables' },
    { label: 'Expenses', desc: 'Claims & budgets', icon: Landmark, grad: 'from-emerald-500 via-teal-600 to-cyan-700', href: 'fin.manager.expenses' },
    { label: 'Payroll', desc: 'Runs & payouts', icon: Banknote, grad: 'from-violet-600 via-purple-600 to-fuchsia-700', href: 'fin.manager.payroll' },
    { label: 'Reports', desc: 'P&L & statements', icon: FileChartColumn, grad: 'from-sky-500 via-blue-600 to-indigo-700', href: 'fin.manager.reports' },
];

const budgetPct = (b) => {
    const a = Number(b.allocated ?? 0);
    if (!a) return 0;
    return Math.min(100, Math.round((Number(b.spent ?? 0) / a) * 100));
};

// --- Set-budget modal (live ledger post, current month by default) ---
const showBudgetModal = ref(false);
const budgetForm = useForm({ department: '', period: new Date().toISOString().slice(0, 7), allocated: '' });
const openBudgetModal = () => {
    budgetForm.reset();
    budgetForm.period = new Date().toISOString().slice(0, 7);
    budgetForm.clearErrors();
    showBudgetModal.value = true;
};
const submitBudget = () => {
    budgetForm.post(route('fin.manager.budgets.store'), {
        preserveScroll: true,
        onSuccess: () => { showBudgetModal.value = false; },
    });
};
</script>

<template>
    <Head title="Finance Overview" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Landmark class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> FIN · Manager
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Finance Overview</h1>
                            <p class="text-sm text-blue-100/90">Welcome back{{ user?.name ? `, ${user.name}` : '' }} — here's the money picture.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span v-if="isDummy" class="flex items-center gap-1.5 rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Demo data
                            </span>
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                Cash on hand · {{ peso(stats?.cashOnHand) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- KPI stat cards -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="(k, i) in kpis" :key="k.label" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-start justify-between mb-3">
                            <div :class="['flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', k.grad]">
                                <component :is="k.icon" class="h-6 w-6" />
                            </div>
                            <span class="flex items-center gap-1 rounded-full px-2.5 py-1 text-[9px] font-black uppercase ring-1" :class="k.chip">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Live
                            </span>
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ k.label }}</p>
                        <p class="text-xl font-black tracking-tight text-gray-900 dark:text-white">{{ k.value }}</p>
                        <p class="mt-1 flex items-center gap-1 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                            <CircleAlert v-if="k.alert" class="h-3 w-3 text-rose-400" /> {{ k.sub }}
                        </p>
                    </div>
                </TransitionGroup>

                <!-- Revenue vs expenses -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 p-5 sm:p-6 overflow-hidden" style="animation-delay: 120ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                        <div>
                            <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Revenue vs Expenses</h2>
                            <p class="text-[11px] text-gray-400 font-medium">Monthly trend · amounts in PHP</p>
                        </div>
                        <div class="flex items-center gap-3 text-[10px] font-bold uppercase">
                            <span class="flex items-center gap-1.5 text-emerald-500"><span class="h-2.5 w-2.5 rounded-md bg-gradient-to-br from-emerald-500 to-teal-600" /> Revenue</span>
                            <span class="flex items-center gap-1.5 text-rose-400"><span class="h-2.5 w-2.5 rounded-md bg-gradient-to-br from-rose-500 to-orange-500" /> Expenses</span>
                        </div>
                    </div>
                    <div v-if="revenueTrend.length === 0" class="py-10 text-center text-xs text-gray-400">No trend data yet.</div>
                    <div v-else class="flex items-end gap-3 sm:gap-4 overflow-x-auto pb-2">
                        <div v-for="r in revenueTrend" :key="r.month" class="flex-1 min-w-[52px] text-center">
                            <div class="flex items-end justify-center gap-1.5 h-40">
                                <div class="w-5 sm:w-7 rounded-t-xl bg-gradient-to-t from-emerald-600 to-teal-400 shadow-lg shadow-emerald-500/20 transition-all hover:opacity-90"
                                    :style="{ height: `${Math.max(4, (Number(r.revenue ?? 0) / maxRevExp) * 100)}%` }"
                                    :title="`Revenue ${peso(r.revenue)}`" />
                                <div class="w-5 sm:w-7 rounded-t-xl bg-gradient-to-t from-rose-600 to-orange-400 shadow-lg shadow-rose-500/20 transition-all hover:opacity-90"
                                    :style="{ height: `${Math.max(4, (Number(r.expenses ?? 0) / maxRevExp) * 100)}%` }"
                                    :title="`Expenses ${peso(r.expenses)}`" />
                            </div>
                            <p class="mt-2 text-[10px] font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 truncate">{{ r.month }}</p>
                        </div>
                    </div>
                </div>

                <!-- Cash flow + AR/AP aging -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 p-5 sm:p-6 overflow-hidden" style="animation-delay: 160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-cyan-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Cash Flow</h2>
                        <p class="text-[11px] text-gray-400 font-medium mb-4">Inflows vs outflows per period</p>
                        <div v-if="cashFlow.length === 0" class="py-8 text-center text-xs text-gray-400">No cash-flow data yet.</div>
                        <ul v-else class="space-y-3">
                            <li v-for="c in cashFlow" :key="c.label" class="rounded-2xl border border-gray-100 dark:border-zinc-800 p-3 hover:border-indigo-200 dark:hover:border-indigo-800 transition">
                                <div class="flex items-center justify-between text-xs font-black text-gray-800 dark:text-gray-100">
                                    <span>{{ c.label }}</span>
                                    <span class="text-[10px] font-bold text-gray-400">Net {{ peso(Number(c.inflow ?? 0) - Number(c.outflow ?? 0)) }}</span>
                                </div>
                                <div class="mt-2 space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <ArrowDownToLine class="h-3 w-3 text-emerald-500 flex-shrink-0" />
                                        <div class="h-2 flex-1 rounded-full bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-600 to-teal-400" :style="{ width: `${(Number(c.inflow ?? 0) / maxFlow) * 100}%` }" />
                                        </div>
                                        <span class="w-24 text-right text-[10px] font-bold text-emerald-600 dark:text-emerald-300">{{ peso(c.inflow) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <ArrowUpFromLine class="h-3 w-3 text-rose-400 flex-shrink-0" />
                                        <div class="h-2 flex-1 rounded-full bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                                            <div class="h-full rounded-full bg-gradient-to-r from-rose-600 to-orange-400" :style="{ width: `${(Number(c.outflow ?? 0) / maxFlow) * 100}%` }" />
                                        </div>
                                        <span class="w-24 text-right text-[10px] font-bold text-rose-500 dark:text-rose-300">{{ peso(c.outflow) }}</span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-4">
                        <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 p-5 overflow-hidden" style="animation-delay: 200ms">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-blue-400/20 to-indigo-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">AR Aging</h2>
                            <p class="text-[11px] text-gray-400 font-medium mb-4">Receivables by bucket</p>
                            <ul class="space-y-2.5">
                                <li v-for="a in arAging" :key="a.bucket">
                                    <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 dark:text-gray-300">
                                        <span>{{ a.bucket }}</span>
                                        <span>{{ peso(a.amount) }} <span class="text-gray-400 font-medium">· {{ a.count }}</span></span>
                                    </div>
                                    <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                                        <div class="h-full rounded-full bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600" :style="{ width: `${(Number(a.amount ?? 0) / maxAging) * 100}%` }" />
                                    </div>
                                </li>
                            </ul>
                            <p v-if="arAging.length === 0" class="py-6 text-center text-xs text-gray-400">No AR data.</p>
                        </div>
                        <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 p-5 overflow-hidden" style="animation-delay: 240ms">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-rose-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">AP Aging</h2>
                            <p class="text-[11px] text-gray-400 font-medium mb-4">Payables by bucket</p>
                            <ul class="space-y-2.5">
                                <li v-for="a in apAging" :key="a.bucket">
                                    <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 dark:text-gray-300">
                                        <span>{{ a.bucket }}</span>
                                        <span>{{ peso(a.amount) }} <span class="text-gray-400 font-medium">· {{ a.count }}</span></span>
                                    </div>
                                    <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                                        <div class="h-full rounded-full bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500" :style="{ width: `${(Number(a.amount ?? 0) / maxAging) * 100}%` }" />
                                    </div>
                                </li>
                            </ul>
                            <p v-if="apAging.length === 0" class="py-6 text-center text-xs text-gray-400">No AP data.</p>
                        </div>
                    </div>
                </div>

                <!-- Transactions + budgets -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 p-5 sm:p-6 overflow-hidden" style="animation-delay: 280ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Recent Transactions</h2>
                        <p class="text-[11px] text-gray-400 font-medium mb-4">Latest money movement</p>
                        <TransitionGroup v-if="recentTransactions.length > 0" name="card" tag="ul" class="space-y-2">
                            <li v-for="t in recentTransactions" :key="t.id"
                                class="flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-3 hover:border-indigo-200 dark:hover:border-indigo-800 hover:-translate-y-0.5 transition-all">
                                <span :class="t.type === 'inflow'
                                    ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-500'
                                    : 'bg-rose-50 dark:bg-rose-900/20 text-rose-400'"
                                    class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl">
                                    <ArrowDownToLine v-if="t.type === 'inflow'" class="h-4 w-4" />
                                    <ArrowUpFromLine v-else class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-black text-gray-800 dark:text-gray-100">{{ t.description }}</p>
                                    <p class="flex items-center gap-1 text-[10px] text-gray-400 font-medium">
                                        <CalendarClock class="h-3 w-3" /> {{ t.date }}
                                        <span class="flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[8px] font-black uppercase ring-1"
                                            :class="t.type === 'inflow' ? 'bg-emerald-500/10 text-emerald-500 ring-emerald-500/20' : 'bg-rose-500/10 text-rose-400 ring-rose-500/20'">
                                            <span class="h-1 w-1 rounded-full bg-current animate-pulse" />{{ t.type }}
                                        </span>
                                    </p>
                                </div>
                                <span :class="t.type === 'inflow' ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-500 dark:text-rose-300'"
                                    class="text-xs font-black whitespace-nowrap">{{ t.type === 'inflow' ? '+' : '−' }}{{ peso(t.amount) }}</span>
                            </li>
                        </TransitionGroup>
                        <p v-else class="py-8 text-center text-xs text-gray-400">No transactions yet.</p>
                    </div>

                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 p-5 sm:p-6 overflow-hidden" style="animation-delay: 320ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-violet-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Budget Utilization</h2>
                                <p class="text-[11px] text-gray-400 font-medium mb-4">Spent vs allocated by department</p>
                            </div>
                            <button @click="openBudgetModal" class="rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black text-white hover:bg-indigo-700 transition active:scale-95 whitespace-nowrap">+ Set budget</button>
                        </div>
                        <ul v-if="budgets.length > 0" class="space-y-3">
                            <li v-for="b in budgets" :key="b.department">
                                <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 dark:text-gray-300">
                                    <span>{{ b.department }}</span>
                                    <span>{{ peso(b.spent) }} <span class="text-gray-400 font-medium">of {{ peso(b.allocated) }} · {{ budgetPct(b) }}%</span></span>
                                </div>
                                <div class="mt-1 h-2.5 rounded-full bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r from-violet-600 via-purple-500 to-fuchsia-500 transition-all"
                                        :class="budgetPct(b) >= 90 ? 'from-rose-600 via-orange-500 to-amber-400' : ''"
                                        :style="{ width: `${(Number(b.spent ?? 0) / Math.max(1, Number(b.allocated ?? 0))) * 100}%` }"
                                        :title="`${maxBudget ? '' : ''}${peso(b.spent)} of ${peso(b.allocated)}`" />
                                </div>
                            </li>
                        </ul>
                        <p v-else class="py-8 text-center text-xs text-gray-400">No budgets yet.</p>
                    </div>
                </div>

                <!-- Quick links -->
                <div>
                    <h2 class="animate-fade-up text-sm font-black tracking-tight text-gray-900 dark:text-white mb-3" style="animation-delay: 360ms">Jump to a workspace</h2>
                    <TransitionGroup name="card" tag="div" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                        <Link v-for="(q, i) in quickLinks" :key="q.label" :href="route(q.href)" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex items-center gap-3 bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-4 overflow-hidden">
                            <div class="pointer-events-none absolute -top-10 -right-10 h-24 w-24 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <span :class="['flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', q.grad]">
                                <component :is="q.icon" class="h-5 w-5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-black text-gray-900 dark:text-white group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ q.label }}</span>
                                <span class="block truncate text-[10px] text-gray-400 font-medium">{{ q.desc }}</span>
                            </span>
                            <ChevronRight class="ml-auto h-4 w-4 flex-shrink-0 text-gray-300 group-hover:text-indigo-500 group-hover:translate-x-1 transition-all" />
                        </Link>
                    </TransitionGroup>
                </div>

            </div>
        </div>

        <!-- Set-budget modal (live ledger post) -->
        <Transition name="modal">
            <div v-if="showBudgetModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="showBudgetModal = false" />
                <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl">
                    <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Monthly budget</p>
                                <h3 class="text-lg font-black tracking-tight">Set budget</h3>
                            </div>
                            <button @click="showBudgetModal = false" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition active:scale-95">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div class="p-5 space-y-4">
                        <label class="block">
                            <span class="text-[11px] font-black uppercase tracking-widest text-gray-400">Department *</span>
                            <input v-model="budgetForm.department" type="text" placeholder="e.g. Dyeing"
                                class="mt-1.5 w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition" />
                            <p v-if="budgetForm.errors.department" class="text-[11px] font-bold text-rose-600 mt-1">{{ budgetForm.errors.department }}</p>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="text-[11px] font-black uppercase tracking-widest text-gray-400">Period</span>
                                <input v-model="budgetForm.period" type="month"
                                    class="mt-1.5 w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition" />
                            </label>
                            <label class="block">
                                <span class="text-[11px] font-black uppercase tracking-widest text-gray-400">Allocated (₱) *</span>
                                <input v-model="budgetForm.allocated" type="number" min="0" step="0.01" placeholder="0.00"
                                    class="mt-1.5 w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition" />
                                <p v-if="budgetForm.errors.allocated" class="text-[11px] font-bold text-rose-600 mt-1">{{ budgetForm.errors.allocated }}</p>
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <button @click="showBudgetModal = false" class="flex-1 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-zinc-700 transition active:scale-95">Cancel</button>
                            <button @click="submitBudget" :disabled="budgetForm.processing" class="flex-1 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 shadow-lg shadow-indigo-500/20 hover:opacity-95 transition active:scale-95 disabled:opacity-50">{{ budgetForm.processing ? 'Saving…' : 'Save budget' }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
</style>
