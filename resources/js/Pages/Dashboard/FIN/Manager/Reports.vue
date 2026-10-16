<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { BarChart3, Sparkles, Printer, TrendingUp, Wallet, PiggyBank } from 'lucide-vue-next';

const props = defineProps({
    profitLoss: { type: Array, default: () => [] },
    revenueTrend: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    isDummy: { type: Boolean, default: false },
});

const peso = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));

const monthLabel = computed(() => {
    const t = props.revenueTrend?.[props.revenueTrend.length - 1];
    return t?.month ?? 'Current period';
});

const linesBy = (section) => props.profitLoss.filter(l => l.section === section);
const sectionTotal = (section) => linesBy(section).reduce((s, l) => s + Number(l.amount ?? 0), 0);

const totalIncome = computed(() => sectionTotal('income'));
const totalCogs = computed(() => sectionTotal('cogs'));
const totalOpex = computed(() => sectionTotal('opex'));
const grossProfit = computed(() => totalIncome.value - totalCogs.value);
const netIncome = computed(() => grossProfit.value - totalOpex.value);

const maxTrend = computed(() => Math.max(1, ...props.revenueTrend.flatMap(r => [Number(r.revenue ?? 0), Number(r.expenses ?? 0)])));

const sections = computed(() => [
    { key: 'income', title: 'Income', rows: linesBy('income'), total: totalIncome.value, grad: 'from-emerald-500 to-teal-600' },
    { key: 'cogs', title: 'Cost of Goods Sold', rows: linesBy('cogs'), total: totalCogs.value, grad: 'from-amber-500 to-orange-600' },
    { key: 'opex', title: 'Operating Expenses', rows: linesBy('opex'), total: totalOpex.value, grad: 'from-rose-500 to-orange-500' },
]);

const print = () => window.print();
</script>

<template>
    <Head title="Financial Reports" />
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
                            <BarChart3 class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> FIN · Manager
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Financial Reports</h1>
                            <p class="text-sm text-blue-100/90">Profit &amp; loss · {{ monthLabel }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span v-if="isDummy" class="flex items-center gap-1.5 rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Demo data
                            </span>
                            <button @click="print"
                                class="flex items-center gap-1.5 rounded-full bg-white px-4 py-2 text-xs font-black text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition">
                                <Printer class="h-4 w-4" /> Print
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary pills -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div v-for="(s, i) in [
                        { label: 'Gross Profit', value: peso(grossProfit), icon: TrendingUp, grad: 'from-emerald-500 via-teal-600 to-cyan-700' },
                        { label: 'Operating Expenses', value: peso(totalOpex), icon: Wallet, grad: 'from-amber-500 via-orange-600 to-rose-600' },
                        { label: 'Net Income', value: peso(netIncome), icon: PiggyBank, grad: 'from-violet-600 via-purple-600 to-fuchsia-700' },
                    ]" :key="s.label" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="animate-fade-up group relative flex items-center gap-3 bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <span :class="['flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', s.grad]">
                            <component :is="s.icon" class="h-6 w-6" />
                        </span>
                        <span>
                            <span class="block text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ s.label }}</span>
                            <span class="block text-xl font-black tracking-tight text-gray-900 dark:text-white">{{ s.value }}</span>
                        </span>
                    </div>
                </TransitionGroup>

                <!-- P&L statement -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 p-5 sm:p-6 overflow-hidden" style="animation-delay: 120ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Profit &amp; Loss Statement</h2>
                    <p class="text-[11px] text-gray-400 font-medium mb-4">Grouped by section · all figures in PHP</p>

                    <div v-if="profitLoss.length === 0" class="py-10 text-center text-xs text-gray-400">No P&amp;L lines yet.</div>
                    <div v-else class="space-y-5">
                        <div v-for="sec in sections" :key="sec.key" class="overflow-hidden rounded-2xl border border-gray-100 dark:border-zinc-800">
                            <div class="flex items-center gap-2 bg-gray-50/80 dark:bg-zinc-800/60 px-4 py-2.5">
                                <span :class="['h-2.5 w-2.5 rounded-md bg-gradient-to-br animate-pulse', sec.grad]" />
                                <p class="text-[11px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-300">{{ sec.title }}</p>
                                <p class="ml-auto text-[11px] font-black text-gray-700 dark:text-gray-200">{{ peso(sec.total) }}</p>
                            </div>
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="l in sec.rows" :key="l.line"
                                        class="border-t border-gray-100 dark:border-zinc-800 hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition">
                                        <td class="px-4 py-2.5 text-xs font-medium text-gray-600 dark:text-gray-300">{{ l.line }}</td>
                                        <td class="px-4 py-2.5 text-right text-xs font-black text-gray-900 dark:text-white whitespace-nowrap">{{ peso(l.amount) }}</td>
                                    </tr>
                                    <tr v-if="sec.rows.length === 0">
                                        <td colspan="2" class="px-4 py-3 text-center text-[11px] text-gray-400">No {{ sec.title.toLowerCase() }} lines.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Computed totals -->
                        <div class="rounded-2xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-4 sm:p-5 text-white shadow-lg shadow-indigo-500/20 space-y-2">
                            <div class="flex items-center justify-between text-xs font-bold text-blue-100">
                                <span>Total Income</span><span>{{ peso(totalIncome) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs font-bold text-blue-100">
                                <span>Less: Cost of Goods Sold</span><span>({{ peso(totalCogs) }})</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-white/20 pt-2 text-sm font-black">
                                <span>Gross Profit</span><span>{{ peso(grossProfit) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs font-bold text-blue-100">
                                <span>Less: Operating Expenses</span><span>({{ peso(totalOpex) }})</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-white/20 pt-2 text-base font-black">
                                <span class="flex items-center gap-1.5">Net Income <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /></span>
                                <span>{{ peso(netIncome) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly trend bars -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 p-5 sm:p-6 overflow-hidden" style="animation-delay: 160ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-cyan-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                        <div>
                            <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Monthly Trend</h2>
                            <p class="text-[11px] text-gray-400 font-medium">Revenue (teal) vs expenses (rose)</p>
                        </div>
                        <span class="rounded-full bg-emerald-500/10 px-3 py-1.5 text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-300 ring-1 ring-emerald-500/20">
                            YTD {{ peso(stats?.revenueYtd) }}
                        </span>
                    </div>
                    <div v-if="revenueTrend.length === 0" class="py-10 text-center text-xs text-gray-400">No trend data yet.</div>
                    <div v-else class="flex items-end gap-3 sm:gap-4 overflow-x-auto pb-2">
                        <div v-for="r in revenueTrend" :key="r.month" class="flex-1 min-w-[52px] text-center">
                            <div class="flex items-end justify-center gap-1.5 h-40">
                                <div class="w-5 sm:w-7 rounded-t-xl bg-gradient-to-t from-emerald-600 to-teal-400 shadow-lg shadow-emerald-500/20 hover:opacity-90 transition"
                                    :style="{ height: `${Math.max(4, (Number(r.revenue ?? 0) / maxTrend) * 100)}%` }"
                                    :title="`Revenue ${peso(r.revenue)}`" />
                                <div class="w-5 sm:w-7 rounded-t-xl bg-gradient-to-t from-rose-600 to-orange-400 shadow-lg shadow-rose-500/20 hover:opacity-90 transition"
                                    :style="{ height: `${Math.max(4, (Number(r.expenses ?? 0) / maxTrend) * 100)}%` }"
                                    :title="`Expenses ${peso(r.expenses)}`" />
                            </div>
                            <p class="mt-2 text-[10px] font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 truncate">{{ r.month }}</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
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
@media print {
    button { display: none !important; }
}
</style>
