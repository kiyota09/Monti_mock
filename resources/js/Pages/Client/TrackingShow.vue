<template>
    <Head :title="`Track ${order.number}`" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('client.tracking')" class="inline-flex items-center gap-1 text-sm font-bold text-indigo-600 hover:underline">
                        <ChevronLeft class="h-4 w-4" /> All tracked orders
                    </Link>
                    <span class="ml-auto rounded-full bg-slate-100 dark:bg-zinc-800 px-3 py-1.5 text-[11px] font-black uppercase tracking-widest text-slate-500">{{ orderType }} · {{ order.group }}</span>
                </div>

                <!-- Header with milestone timeline -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-start gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30"><Navigation class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">Order tracking · {{ orderType === 'PO' ? 'Purchase order' : 'Job order' }}</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">{{ order.number }}</h1>
                            <p class="text-sm text-blue-100/90">
                                <span v-if="orderType === 'SO'">{{ order.product_name }} · {{ order.quantity }} pcs · </span>ETA {{ order.expected_ship_date || order.delivery_date || '—' }}
                            </p>
                        </div>
                        <span class="rounded-full bg-white/95 px-3 py-1.5 text-xs font-black uppercase text-indigo-700">{{ formatStatus(order.status) }}</span>
                    </div>
                    <!-- Progress bar -->
                    <div class="relative mt-5 h-2 rounded-full bg-white/20 overflow-hidden">
                        <div class="h-full bg-white rounded-full transition-all" :style="{ width: order.progress + '%' }" />
                    </div>
                    <!-- Milestone timeline -->
                    <div class="relative mt-6 flex items-start overflow-x-auto">
                        <div v-for="(t, i) in order.steps" :key="t.key" class="flex-1 flex items-start last:flex-none min-w-[90px]">
                            <div class="flex flex-col items-center text-center w-full">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full ring-2 font-black text-xs" :class="t.done ? 'bg-emerald-400 text-emerald-950 ring-emerald-200' : t.current ? 'bg-white text-indigo-700 ring-white animate-pulse' : 'bg-white/15 text-white ring-white/30'">
                                    <Check v-if="t.done" class="h-4 w-4" /><span v-else>{{ i + 1 }}</span>
                                </div>
                                <p class="mt-1 text-[10px] font-black uppercase tracking-wide">{{ t.label }}</p>
                                <p v-if="t.current" class="text-[10px] font-bold text-emerald-200">Current</p>
                            </div>
                            <div v-if="i < order.steps.length - 1" class="mx-1 mt-4 h-0.5 flex-1 rounded" :class="t.done ? 'bg-emerald-300' : 'bg-white/25'" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <div class="xl:col-span-2 space-y-4">
                        <!-- Lines -->
                        <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                            <h2 class="font-black mb-3 flex items-center gap-2"><Package class="h-4 w-4 text-indigo-500" /> Order lines — ₱{{ formatCurrency(order.total) }}</h2>
                            <div class="space-y-1.5">
                                <div v-for="l in lines" :key="l.id" class="flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 px-3 py-2 text-sm">
                                    <Package class="h-4 w-4 text-indigo-500 shrink-0" />
                                    <span class="font-bold truncate">{{ l.product }}</span>
                                    <span class="text-gray-500 text-xs">× {{ l.quantity }}</span>
                                    <span class="ml-auto font-black whitespace-nowrap">₱{{ formatCurrency(l.line_total) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Linked job orders (PO only) -->
                        <div v-if="orderType === 'PO'" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                            <h2 class="font-black mb-3">Job orders from this PO · {{ linkedOrders.length }}</h2>
                            <div v-if="!linkedOrders.length" class="text-sm text-gray-400">No job orders generated yet — Monti creates them after your approval.</div>
                            <div v-else class="space-y-1.5">
                                <Link v-for="o in linkedOrders" :key="o.id" :href="route('client.tracking.show', { type: 'so', id: o.id })" class="flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 px-3 py-2 text-sm hover:border-indigo-300 hover:shadow transition-all">
                                    <span class="font-black">{{ o.number }}</span>
                                    <span class="text-gray-500 truncate">{{ o.product_name }}</span>
                                    <span class="ml-auto rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-black uppercase text-indigo-700">{{ formatStatus(o.status) }}</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Shipments -->
                        <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                            <h2 class="font-black mb-3 flex items-center gap-2"><Truck class="h-4 w-4 text-blue-500" /> Shipments · {{ deliveries.length }}</h2>
                            <div v-if="!deliveries.length" class="text-sm text-gray-400">No shipments dispatched yet for this order.</div>
                            <div v-else class="grid sm:grid-cols-2 gap-2">
                                <div v-for="d in deliveries" :key="d.id" class="rounded-2xl border border-gray-100 dark:border-zinc-800 p-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-black text-xs">{{ d.delivery_number }}</span>
                                        <span :class="shipBadge(d.status)" class="ml-auto text-[9px] font-black uppercase px-2 py-0.5 rounded-full">{{ formatStatus(d.status) }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">{{ d.origin }} → {{ d.destination }}</p>
                                    <p class="text-[11px] text-gray-400">{{ d.driver_name || '—' }} · {{ d.truck_number || '—' }} · {{ d.package_count }} pkgs</p>
                                    <p class="text-[11px] text-gray-400">Departed {{ d.actual_departure || d.scheduled_departure || '—' }} · Arrived {{ d.arrival_time || '—' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- History -->
                        <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                            <h2 class="font-black mb-3 flex items-center gap-2"><History class="h-4 w-4 text-slate-400" /> Status history</h2>
                            <div v-if="!history.length" class="text-sm text-gray-400">No history recorded yet.</div>
                            <ol v-else class="relative ml-2 border-l-2 border-gray-100 dark:border-zinc-800 space-y-4">
                                <li v-for="(h, i) in history" :key="i" class="relative pl-6 text-sm">
                                    <span class="absolute left-0 top-1 flex h-3 w-3 -translate-x-1/2 rounded-full bg-indigo-500 ring-4 ring-indigo-100 dark:ring-indigo-900/30" />
                                    <p class="font-bold">{{ formatStatus(h.from) }} → {{ formatStatus(h.to) }}</p>
                                    <p class="text-xs text-gray-400">{{ h.at }} · {{ h.by }}</p>
                                    <p v-if="h.notes" class="text-xs text-gray-500 italic mt-0.5">{{ h.notes }}</p>
                                </li>
                            </ol>
                        </div>
                    </div>

                    <!-- Side summary -->
                    <div class="space-y-4">
                        <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5 h-fit">
                            <h2 class="font-black mb-3 flex items-center gap-2"><BadgeCheck class="h-4 w-4 text-emerald-500" /> Summary</h2>
                            <div class="text-sm space-y-2">
                                <p class="flex justify-between"><span class="text-gray-400 text-xs font-bold uppercase">Total</span><span class="font-black text-emerald-600">₱{{ formatCurrency(order.total) }}</span></p>
                                <p class="flex justify-between"><span class="text-gray-400 text-xs font-bold uppercase">Priority</span><span class="font-bold capitalize">{{ order.priority }}</span></p>
                                <p class="flex justify-between"><span class="text-gray-400 text-xs font-bold uppercase">Placed</span><span class="font-bold">{{ order.date }}</span></p>
                                <p class="flex justify-between"><span class="text-gray-400 text-xs font-bold uppercase">ETA</span><span class="font-bold">{{ order.expected_ship_date || order.delivery_date || '—' }}</span></p>
                                <p class="flex justify-between"><span class="text-gray-400 text-xs font-bold uppercase">Progress</span><span class="font-black text-indigo-600">{{ order.progress }}%</span></p>
                            </div>
                            <div v-if="order.manufacturing" class="mt-4 rounded-2xl bg-slate-50 dark:bg-zinc-800 p-3 text-xs space-y-1">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Production</p>
                                <p class="flex justify-between"><span>Status</span><span class="font-bold capitalize">{{ order.manufacturing.status }}</span></p>
                                <p class="flex justify-between"><span>Remaining</span><span class="font-bold">{{ order.manufacturing.remaining }} / {{ order.manufacturing.total }}</span></p>
                            </div>
                            <Link :href="route('client.receiving')" class="mt-4 block text-center w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-xs font-black uppercase tracking-widest transition-all active:scale-95">
                                Go to receiving →
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { BadgeCheck, Check, ChevronLeft, History, Navigation, Package, Truck } from 'lucide-vue-next';

defineProps({
    orderType: String,
    order: Object,
    lines: Array,
    linkedOrders: { type: Array, default: () => [] },
    deliveries: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const formatCurrency = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const formatStatus = (s) => String(s || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const shipBadge = (status) => {
    const map = { dispatched: 'bg-amber-100 text-amber-700', in_transit: 'bg-blue-100 text-blue-700', delivered: 'bg-emerald-100 text-emerald-700' };
    return map[status] || 'bg-gray-100 text-gray-600';
};
</script>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
</style>
