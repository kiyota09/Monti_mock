<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Landmark, Sparkles, HandCoins, Receipt, Wallet, ArrowDownLeft, ArrowUpRight,
    CircleAlert, FileText, Truck,
} from 'lucide-vue-next';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    receivables: { type: Array, default: () => [] },
    payables: { type: Array, default: () => [] },
    recentTransactions: { type: Array, default: () => [] },
    isDummy: { type: Boolean, default: false },
});

const peso = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));
const balance = (r) => Number(r.amount ?? 0) - Number(r.paid ?? 0);

const topAR = computed(() =>
    [...props.receivables].sort((a, b) => balance(b) - balance(a)).slice(0, 5)
);

const kpis = computed(() => [
    { label: 'Outstanding AR', value: peso(props.stats.outstandingAR), icon: HandCoins, grad: 'from-blue-500 via-indigo-600 to-violet-700' },
    { label: 'Overdue AR', value: peso(props.stats.overdueAR), icon: CircleAlert, grad: 'from-rose-500 via-red-600 to-orange-600' },
    { label: 'Outstanding AP', value: peso(props.stats.outstandingAP), icon: Receipt, grad: 'from-amber-500 via-orange-600 to-rose-600' },
    { label: 'Cash on hand', value: peso(props.stats.cashOnHand), icon: Wallet, grad: 'from-emerald-500 via-teal-600 to-cyan-700' },
]);

const arStatusClass = (s) => {
    switch (s) {
        case 'paid': return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'partial': return 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30';
        case 'unpaid': return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'overdue': return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
        default: return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};
</script>

<template>
    <Head title="Finance Workspace" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
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
                                <Sparkles class="h-3.5 w-3.5" /> Finance · Staff
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Finance Workspace</h1>
                            <p class="text-sm text-blue-100/90">Welcome{{ user?.name ? `, ${user.name}` : '' }} — snapshot of receivables, payables &amp; cash movement</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ receivables.length }} AR · {{ payables.length }} AP
                            </span>
                            <span v-if="isDummy" class="rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">Demo data</span>
                        </div>
                    </div>
                </div>

                <!-- KPI cards -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div v-for="(k, i) in kpis" :key="k.label"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-center gap-3 mb-3">
                            <div :class="['h-11 w-11 rounded-2xl bg-gradient-to-br flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', k.grad]">
                                <component :is="k.icon" class="h-5 w-5" />
                            </div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ k.label }}</p>
                        </div>
                        <p class="text-xl font-black text-gray-900 dark:text-white tracking-tight">{{ k.value }}</p>
                        <p class="mt-1 flex items-center gap-1 text-[10px] font-bold uppercase text-gray-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> updated today
                        </p>
                    </div>
                </TransitionGroup>

                <!-- AR snapshot table (top 5 by balance) -->
                <div class="animate-fade-up relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm">
                    <div class="flex items-center gap-3 p-5 pb-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-violet-700 text-white shadow-lg">
                            <FileText class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-sm font-black text-gray-900 dark:text-white tracking-tight">Receivables snapshot · top 5 by balance</h2>
                            <p class="text-[11px] text-gray-400 font-medium">Invoice · client · balance · due</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto p-3">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                                    <th class="px-4 py-3">Invoice</th>
                                    <th class="px-4 py-3">Client</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3">Due date</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody">
                                <tr v-for="r in topAR" :key="r.id" class="border-t border-gray-100 dark:border-zinc-800 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/30 transition-colors">
                                    <td class="px-4 py-3 font-black text-indigo-700 dark:text-indigo-300 whitespace-nowrap">{{ r.invoice_no }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-100">{{ r.client }}</td>
                                    <td class="px-4 py-3"><span :class="arStatusClass(r.status)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 inline-flex items-center gap-1 whitespace-nowrap"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ r.status }}</span></td>
                                    <td class="px-4 py-3 text-right font-black text-gray-900 dark:text-white whitespace-nowrap">{{ peso(balance(r)) }}</td>
                                    <td class="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ r.due_date }}</td>
                                </tr>
                            </TransitionGroup>
                        </table>
                    </div>
                </div>

                <!-- AP snapshot table -->
                <div class="animate-fade-up relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm">
                    <div class="flex items-center gap-3 p-5 pb-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-rose-600 text-white shadow-lg">
                            <Truck class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-sm font-black text-gray-900 dark:text-white tracking-tight">Payables snapshot</h2>
                            <p class="text-[11px] text-gray-400 font-medium">Bill · supplier · balance · due</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto p-3">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                                    <th class="px-4 py-3">Bill</th>
                                    <th class="px-4 py-3">Supplier</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3">Due date</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody">
                                <tr v-for="p in payables" :key="p.id" class="border-t border-gray-100 dark:border-zinc-800 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/30 transition-colors">
                                    <td class="px-4 py-3 font-black text-indigo-700 dark:text-indigo-300 whitespace-nowrap">{{ p.bill_no }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-100">{{ p.supplier }}<span class="block text-[10px] font-bold uppercase text-gray-400">{{ p.category }}</span></td>
                                    <td class="px-4 py-3"><span :class="arStatusClass(p.status)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 inline-flex items-center gap-1 whitespace-nowrap"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ p.status }}</span></td>
                                    <td class="px-4 py-3 text-right font-black text-gray-900 dark:text-white whitespace-nowrap">{{ peso(balance(p)) }}</td>
                                    <td class="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ p.due_date }}</td>
                                </tr>
                            </TransitionGroup>
                        </table>
                    </div>
                </div>

                <!-- Recent transactions feed -->
                <div class="animate-fade-up relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg">
                            <ArrowDownLeft class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-sm font-black text-gray-900 dark:text-white tracking-tight">Recent transactions</h2>
                            <p class="text-[11px] text-gray-400 font-medium">Latest cash movement</p>
                        </div>
                    </div>
                    <TransitionGroup name="card" tag="div" class="space-y-2">
                        <div v-for="t in recentTransactions" :key="t.id"
                            class="group flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-3 hover:shadow-lg hover:shadow-indigo-500/10 hover:-translate-y-0.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300">
                            <span :class="t.type === 'inflow' ? 'bg-emerald-100 dark:bg-emerald-500/15' : 'bg-rose-100 dark:bg-rose-500/15'"
                                class="flex h-9 w-9 items-center justify-center rounded-xl flex-shrink-0">
                                <ArrowDownLeft v-if="t.type === 'inflow'" class="h-4 w-4 text-emerald-600 dark:text-emerald-300" />
                                <ArrowUpRight v-else class="h-4 w-4 text-rose-600 dark:text-rose-300" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-bold text-gray-800 dark:text-gray-100 truncate">{{ t.description }}</p>
                                <p class="text-[11px] font-medium text-gray-400">{{ t.date }} · <span class="uppercase font-black">{{ t.type }}</span></p>
                            </div>
                            <p :class="t.type === 'inflow' ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-500 dark:text-rose-300'"
                                class="text-sm font-black whitespace-nowrap">{{ t.type === 'inflow' ? '+' : '−' }}{{ peso(t.amount) }}</p>
                        </div>
                    </TransitionGroup>
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
</style>
