<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { BarChart3 } from 'lucide-vue-next';

defineProps({ summary: Object, funnel: Object, suppliers: Array });

const fmtMoney = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const funnelBar = (obj) => {
    const entries = Object.entries(obj || {});
    const max = Math.max(1, ...entries.map(([, v]) => Number(v)));
    return entries.map(([k, v]) => ({ label: k, count: v, pct: Math.round(Number(v) / max * 100) }));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="SCM Analytics" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 shadow-lg">
                            <BarChart3 class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">SCM · Enable</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Supply Chain Analytics</h1>
                            <p class="text-sm text-blue-100/90">Supplier scorecard, spend &amp; sourcing funnel.</p>
                        </div>
                    </div>
                    <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.suppliers }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Suppliers</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.purchaseOrders }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Purchase orders</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-2xl font-black">{{ summary.quotes }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Quotes received</p></div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3"><p class="text-lg sm:text-xl font-black truncate">{{ fmtMoney(summary.totalSpend) }}</p><p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Total PO spend</p></div>
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 sm:p-6 shadow-sm">
                    <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 mb-4">Request → RFQ → PO → Invoice funnel</h3>
                    <div class="grid sm:grid-cols-4 gap-4">
                        <div v-for="stage in [{ t: 'Material requests', d: funnel.requests }, { t: 'RFQs', d: funnel.rfqs }, { t: 'Purchase orders', d: funnel.purchaseOrders }, { t: 'Invoices', d: funnel.invoices }]" :key="stage.t" class="rounded-2xl bg-slate-50 dark:bg-zinc-800/60 p-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">{{ stage.t }}</p>
                            <div v-for="row in funnelBar(stage.d)" :key="row.label" class="mb-2">
                                <div class="flex justify-between text-[11px] font-bold mb-1"><span class="uppercase">{{ row.label }}</span><span>{{ row.count }}</span></div>
                                <div class="h-1.5 rounded-full bg-slate-200 dark:bg-zinc-700 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r from-blue-600 to-indigo-600" :style="{ width: row.pct + '%' }" /></div>
                            </div>
                            <p v-if="!funnelBar(stage.d).length" class="text-[11px] font-bold text-slate-400">No data</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm overflow-hidden">
                    <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 px-5 sm:px-6 pt-5">Supplier scorecard <span class="normal-case font-bold">· ranked by spend</span></h3>
                    <div class="overflow-x-auto mt-2">
                        <table class="w-full text-sm min-w-[760px]">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-widest text-slate-400 border-b border-slate-100 dark:border-zinc-800">
                                    <th class="px-5 sm:px-6 py-3.5">Supplier</th>
                                    <th class="px-5 py-3.5">Status</th>
                                    <th class="px-5 py-3.5 text-right">POs</th>
                                    <th class="px-5 py-3.5 text-right">Quotes</th>
                                    <th class="px-5 py-3.5 text-right">Overdue inv.</th>
                                    <th class="px-5 sm:px-6 py-3.5 text-right">Total spend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in suppliers" :key="s.id" class="border-b border-slate-50 dark:border-zinc-800/60 hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition">
                                    <td class="px-5 sm:px-6 py-3.5 font-black">{{ s.business_name }}</td>
                                    <td class="px-5 py-3.5"><span class="rounded-full px-3 py-1 text-[10px] font-black uppercase" :class="s.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : s.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500'">{{ s.status }}</span></td>
                                    <td class="px-5 py-3.5 text-right font-black">{{ s.purchase_orders }}</td>
                                    <td class="px-5 py-3.5 text-right font-black">{{ s.quotes_submitted }}</td>
                                    <td class="px-5 py-3.5 text-right font-black" :class="s.overdue_invoices > 0 ? 'text-rose-600' : ''">{{ s.overdue_invoices }}</td>
                                    <td class="px-5 sm:px-6 py-3.5 text-right font-black">{{ fmtMoney(s.total_spend) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="!suppliers.length" class="text-center text-xs font-bold text-slate-400 py-10 uppercase tracking-widest">No suppliers yet</p>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
