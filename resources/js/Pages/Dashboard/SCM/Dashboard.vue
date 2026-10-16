<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { LayoutDashboard, ShoppingCart, ClipboardList, PackageCheck, AlertCircle, Building2, Truck } from 'lucide-vue-next';

defineProps({ stats: Object, openSalesOrders: Array, recentReceivings: Array });

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) : '—';
</script>

<template>
    <AuthenticatedLayout>
        <Head title="SCM Dashboard" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 shadow-lg">
                            <LayoutDashboard class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">SCM · Command Center</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Supply Chain Dashboard</h1>
                            <p class="text-sm text-blue-100/90">Plan → source → deliver pipeline at a glance.</p>
                        </div>
                    </div>
                    <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                        <div v-for="(v, k) in stats" :key="k" class="rounded-2xl bg-white/12 ring-1 ring-white/25 px-4 py-3 backdrop-blur">
                            <p class="text-2xl font-black">{{ v }}</p>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">{{ k.replace(/([A-Z])/g, ' $1') }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid lg:grid-cols-2 gap-4">
                    <div class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 sm:p-6 shadow-sm">
                        <h3 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 mb-4">
                            <ShoppingCart class="h-4 w-4 text-indigo-500" /> Open sales orders awaiting supply
                        </h3>
                        <div v-if="openSalesOrders.length" class="space-y-2.5">
                            <div v-for="o in openSalesOrders" :key="o.id" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-100 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-800/50 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-black truncate">{{ o.jo_number }} · {{ o.client_name }}</p>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ o.quantity }} units · {{ fmtDate(o.created_at) }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 px-3 py-1 text-[10px] font-black uppercase">{{ o.status }}</span>
                            </div>
                        </div>
                        <p v-else class="text-center text-xs font-bold text-slate-400 py-8 uppercase tracking-widest">No open sales orders</p>
                        <Link :href="route('scm.sales-orders')" class="mt-4 inline-block text-[11px] font-black uppercase tracking-widest text-indigo-600 hover:underline">Open sales orders →</Link>
                    </div>

                    <div class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 sm:p-6 shadow-sm">
                        <h3 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 mb-4">
                            <Truck class="h-4 w-4 text-emerald-500" /> Latest inbound receipts
                        </h3>
                        <div v-if="recentReceivings.length" class="space-y-2.5">
                            <div v-for="r in recentReceivings" :key="r.id" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-100 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-800/50 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-black truncate">{{ r.receiving_number }}</p>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ r.warehouse_name }} · {{ fmtDate(r.received_at) }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-3 py-1 text-[10px] font-black uppercase">{{ r.status }}</span>
                            </div>
                        </div>
                        <p v-else class="text-center text-xs font-bold text-slate-400 py-8 uppercase tracking-widest">No receipts yet</p>
                        <Link :href="route('scm.deliveries')" class="mt-4 inline-block text-[11px] font-black uppercase tracking-widest text-indigo-600 hover:underline">Open deliveries →</Link>
                    </div>
                </div>

                <div class="grid sm:grid-cols-4 gap-3">
                    <Link :href="route('scm.planning')" class="rounded-2xl border p-5 bg-white dark:bg-zinc-900 hover:border-indigo-300 transition group">
                        <ClipboardList class="h-6 w-6 text-indigo-500 mb-2 group-hover:scale-110 transition" />
                        <p class="text-sm font-black">Planning</p><p class="text-[11px] text-slate-400 font-bold">Shortages vs on-hand</p>
                    </Link>
                    <Link :href="route('scm.purchase-orders')" class="rounded-2xl border p-5 bg-white dark:bg-zinc-900 hover:border-indigo-300 transition group">
                        <PackageCheck class="h-6 w-6 text-blue-500 mb-2 group-hover:scale-110 transition" />
                        <p class="text-sm font-black">Purchase Orders</p><p class="text-[11px] text-slate-400 font-bold">Track PO pipeline</p>
                    </Link>
                    <Link :href="route('scm.vendors')" class="rounded-2xl border p-5 bg-white dark:bg-zinc-900 hover:border-indigo-300 transition group">
                        <Building2 class="h-6 w-6 text-violet-500 mb-2 group-hover:scale-110 transition" />
                        <p class="text-sm font-black">Vendors</p><p class="text-[11px] text-slate-400 font-bold">{{ stats.pendingVendors }} pending approval</p>
                    </Link>
                    <Link :href="route('scm.analytics')" class="rounded-2xl border p-5 bg-white dark:bg-zinc-900 hover:border-indigo-300 transition group">
                        <AlertCircle class="h-6 w-6 text-amber-500 mb-2 group-hover:scale-110 transition" />
                        <p class="text-sm font-black">Analytics</p><p class="text-[11px] text-slate-400 font-bold">{{ stats.overdueInvoices }} overdue invoices</p>
                    </Link>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
