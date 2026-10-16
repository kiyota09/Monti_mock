<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FileText, Sparkles, TrendingUp, TrendingDown, Package, Users, AlertTriangle, Factory } from 'lucide-vue-next';

const props = defineProps({ months: Number, kpis: Object, trends: Object });

const setRange = (m) => router.get(route('ceo.reports'), { months: m }, { preserveState: true, preserveScroll: true, replace: true });
const peso = (v) => '₱' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(v ?? 0);
const num = (v) => new Intl.NumberFormat('en-US').format(v ?? 0);

const maxOf = (arr) => Math.max(...(arr ?? [0]), 1);
const cards = [
    { label: 'Approved Revenue', value: peso(props.kpis.revenue_approved), icon: TrendingUp, up: true },
    { label: 'Procurement Spend', value: peso(props.kpis.procurement_spend), icon: TrendingDown, up: false },
    { label: 'Payroll Cost (total)', value: peso(props.kpis.payroll_cost), icon: Users, up: false },
    { label: 'Unpaid Invoices', value: peso(props.kpis.invoices_unpaid), icon: AlertTriangle, up: false },
    { label: 'Fabrics Produced', value: num(props.kpis.fabrics_total), icon: Factory, up: true },
    { label: 'Reject Rate', value: `${props.kpis.reject_rate}%`, icon: AlertTriangle, up: false },
    { label: 'Jobs In Production', value: num(props.kpis.jobs_in_production), icon: Package, up: true },
    { label: 'Packages Delivered', value: num(props.kpis.packages_delivered), icon: Package, up: true },
];
</script>

<template>
    <Head title="Executive Reports" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><FileText class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> CEO · Company Performance</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Executive Reports</h1>
                            <p class="text-sm text-blue-100/90">Cross-module KPIs and monthly trends · read-only</p>
                        </div>
                        <div class="flex gap-1.5 rounded-2xl bg-white/15 p-1 ring-1 ring-white/25 backdrop-blur">
                            <button v-for="m in [3, 6, 12]" :key="m" @click="setRange(m)"
                                :class="['px-3 py-1.5 rounded-xl text-xs font-black transition', months === m ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow' : 'text-white/80 hover:text-white']">{{ m }}M</button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div v-for="c in cards" :key="c.label" class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 shadow-sm">
                        <component :is="c.icon" class="h-5 w-5" :class="c.up ? 'text-emerald-600' : 'text-indigo-500'" />
                        <p class="text-xl font-black mt-1 truncate">{{ c.value }}</p>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-zinc-400">{{ c.label }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-zinc-800">
                        <h3 class="text-sm font-black mb-1">Revenue vs Procurement Spend</h3>
                        <p class="text-[11px] text-gray-400 dark:text-zinc-500 mb-4">Approved order revenue against purchasing spend per month</p>
                        <div class="h-48 flex items-end gap-2">
                            <div v-for="(label, i) in trends.labels" :key="label" class="flex-1 flex flex-col items-center gap-1">
                                <div class="w-full flex items-end justify-center gap-0.5 h-36">
                                    <div class="w-1/2 max-w-4 bg-gradient-to-t from-blue-600 to-indigo-500 rounded-t" :style="{ height: `${(trends.revenue[i] / maxOf(trends.revenue)) * 100}%`, minHeight: '3px' }" :title="`Revenue ${peso(trends.revenue[i])}`" />
                                    <div class="w-1/2 max-w-4 bg-gradient-to-t from-amber-500 to-orange-400 rounded-t" :style="{ height: `${(trends.spend[i] / maxOf(trends.spend)) * 100}%`, minHeight: '3px' }" :title="`Spend ${peso(trends.spend[i])}`" />
                                </div>
                                <span class="text-[9px] text-gray-500 dark:text-zinc-400">{{ label }}</span>
                            </div>
                        </div>
                        <div class="flex gap-4 mt-2 text-[11px] text-gray-500 dark:text-zinc-400"><span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-indigo-500" /> Revenue</span><span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500" /> Spend</span></div>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-zinc-800">
                        <h3 class="text-sm font-black mb-1">Production Output vs Rejects</h3>
                        <p class="text-[11px] text-gray-400 dark:text-zinc-500 mb-4">Fabrics recorded and rejected per month</p>
                        <div class="h-48 flex items-end gap-2">
                            <div v-for="(label, i) in trends.labels" :key="label" class="flex-1 flex flex-col items-center gap-1">
                                <div class="w-full flex items-end justify-center gap-0.5 h-36">
                                    <div class="w-1/2 max-w-4 bg-gradient-to-t from-emerald-600 to-teal-400 rounded-t" :style="{ height: `${(trends.output[i] / maxOf(trends.output)) * 100}%`, minHeight: '3px' }" :title="`Output ${trends.output[i]}`" />
                                    <div class="w-1/2 max-w-4 bg-gradient-to-t from-rose-600 to-pink-400 rounded-t" :style="{ height: `${(trends.rejects[i] / maxOf(trends.rejects)) * 100}%`, minHeight: '3px' }" :title="`Rejects ${trends.rejects[i]}`" />
                                </div>
                                <span class="text-[9px] text-gray-500 dark:text-zinc-400">{{ label }}</span>
                            </div>
                        </div>
                        <div class="flex gap-4 mt-2 text-[11px] text-gray-500 dark:text-zinc-400"><span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500" /> Output</span><span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-rose-500" /> Rejects</span></div>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-zinc-800">
                    <h3 class="text-sm font-black mb-1">Monthly Payroll Cost</h3>
                    <p class="text-[11px] text-gray-400 dark:text-zinc-500 mb-4">Net pay totals per month</p>
                    <div class="h-40 flex items-end gap-2">
                        <div v-for="(label, i) in trends.labels" :key="label" class="flex-1 flex flex-col items-center gap-1">
                            <div class="w-full max-w-10 bg-gradient-to-t from-violet-600 to-fuchsia-400 rounded-t" :style="{ height: `${(trends.payroll[i] / maxOf(trends.payroll)) * 100}%`, minHeight: '3px' }" :title="peso(trends.payroll[i])" />
                            <span class="text-[9px] text-gray-500 dark:text-zinc-400">{{ label }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
                    <div class="rounded-2xl bg-gray-50 dark:bg-zinc-800/60 p-3"><p class="text-xl font-black">{{ num(kpis.orders_open) }}</p><p class="text-[10px] font-bold uppercase text-gray-500 dark:text-zinc-400">Open orders</p></div>
                    <div class="rounded-2xl bg-gray-50 dark:bg-zinc-800/60 p-3"><p class="text-xl font-black">{{ num(kpis.packages_pending) }}</p><p class="text-[10px] font-bold uppercase text-gray-500 dark:text-zinc-400">Packages pending</p></div>
                    <div class="rounded-2xl bg-gray-50 dark:bg-zinc-800/60 p-3"><p class="text-xl font-black">{{ num(kpis.leads_open) }} / {{ num(kpis.leads_won) }}</p><p class="text-[10px] font-bold uppercase text-gray-500 dark:text-zinc-400">Leads open / won</p></div>
                    <div class="rounded-2xl bg-gray-50 dark:bg-zinc-800/60 p-3"><p class="text-xl font-black">{{ num(kpis.headcount) }}</p><p class="text-[10px] font-bold uppercase text-gray-500 dark:text-zinc-400">Active headcount</p></div>
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
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
</style>
