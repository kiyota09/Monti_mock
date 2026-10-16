<template>
    <Head title="Order Tracking" />
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
                            <Navigation class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Monti Textile · Order Tracking
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Track My Orders</h1>
                            <p class="text-sm text-blue-100/90">{{ filtered.length }} of {{ orders.length }} orders · approval → production → delivery</p>
                        </div>
                        <button @click="refreshData" title="Refresh"
                            class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/30 hover:rotate-12 transition-all active:scale-95">
                            <RefreshCw class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search PO / JO number..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2 overflow-x-auto">
                            <button v-for="opt in typeOpts" :key="opt[0]" @click="typeFilter = opt[0]"
                                :class="typeFilter === opt[0] ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="whitespace-nowrap rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ opt[1] }}
                            </button>
                        </div>
                    </div>
                    <div class="relative mt-3 flex gap-2 overflow-x-auto">
                        <button v-for="opt in groupOpts" :key="opt[0]" @click="groupFilter = opt[0]"
                            :class="groupFilter === opt[0] ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="whitespace-nowrap rounded-2xl px-4 py-2 text-[11px] font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            {{ opt[1] }}
                        </button>
                    </div>

                    <!-- Stat strip -->
                    <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Total</p>
                            <p class="mt-1 text-lg font-black">{{ stats.total }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:80ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">In production</p>
                            <p class="mt-1 text-lg font-black">{{ stats.inProduction }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:160ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">In transit</p>
                            <p class="mt-1 text-lg font-black">{{ stats.inTransit }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:240ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Delivered</p>
                            <p class="mt-1 text-lg font-black">{{ stats.delivered }}</p>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="filtered.length === 0" class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <Package class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No orders to track</p>
                    <p class="text-xs text-gray-400 mt-1">Accepted quotations and shipments will appear here.</p>
                </div>

                <!-- Order cards -->
                <TransitionGroup v-else name="card" tag="div" class="space-y-4">
                    <div v-for="(o, i) in filtered" :key="o.type + '-' + o.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 overflow-hidden hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300">
                        <div :class="statusAccentBar(o.status)" class="h-1 w-full" />
                        <div class="p-5">
                            <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex gap-2 items-center flex-wrap">
                                        <span class="rounded-md bg-slate-100 dark:bg-zinc-800 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-slate-500">{{ o.type }}</span>
                                        <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ o.number }}</span>
                                        <span :class="statusBadge(o.status)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ formatStatus(o.status) }}
                                        </span>
                                        <span v-if="o.shipment" class="inline-flex items-center gap-1 text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 ring-1 ring-blue-200">
                                            <Truck class="h-3 w-3" />{{ o.shipment.delivery_number }}
                                        </span>
                                    </div>
                                    <p v-if="o.type === 'SO'" class="font-bold text-gray-900 dark:text-white mt-1 truncate">{{ o.product_name }} · {{ o.quantity }} pcs</p>
                                    <p v-else class="font-bold text-gray-900 dark:text-white mt-1 truncate">{{ o.item_count }} item(s){{ o.items_summary?.length ? ' · ' + o.items_summary.join(', ') : '' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Placed {{ o.date }} · ETA {{ o.expected_ship_date || o.delivery_date || '—' }}</p>
                                    <!-- Progress -->
                                    <div class="mt-3">
                                        <div class="flex justify-between text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">
                                            <span>Progress</span><span class="text-indigo-600">{{ o.progress }}%</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-slate-100 dark:bg-zinc-800 overflow-hidden">
                                            <div class="h-full rounded-full bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 transition-all duration-500" :style="{ width: o.progress + '%' }" />
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            <span v-for="s in o.steps" :key="s.key"
                                                :class="s.done ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : s.current ? 'bg-indigo-600 text-white animate-pulse' : 'bg-slate-100 text-slate-400 dark:bg-zinc-800 dark:text-zinc-500'"
                                                class="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full">
                                                {{ s.done ? '✓ ' : '' }}{{ s.label }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-left sm:text-right flex-shrink-0 w-full sm:w-auto">
                                    <p class="font-black text-emerald-600 dark:text-emerald-400 text-lg">₱{{ formatCurrency(o.total) }}</p>
                                    <div class="mt-2 flex sm:justify-end gap-2">
                                        <button @click="openQuickTrack(o)"
                                            class="flex-1 sm:flex-none px-4 py-2.5 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 rounded-2xl text-xs font-bold text-gray-700 dark:text-gray-200 transition-all active:scale-95">
                                            Quick view
                                        </button>
                                        <Link :href="route('client.tracking.show', { type: o.type.toLowerCase(), id: o.id })"
                                            class="flex-1 sm:flex-none px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-xs font-bold text-center transition-all hover:shadow-lg hover:shadow-indigo-500/25 active:scale-95">
                                            Track order →
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Active shipments strip -->
                <div v-if="deliveries.length" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20">
                        <Truck class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">My shipments</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ deliveries.length }}</span>
                    </div>
                    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3 p-4">
                        <div v-for="d in deliveries" :key="d.id" class="rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 hover:border-indigo-200 hover:shadow-md transition-all">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-black">{{ d.delivery_number }}</span>
                                <span :class="shipBadge(d.status)" class="ml-auto text-[9px] font-black uppercase px-2 py-0.5 rounded-full">{{ formatStatus(d.status) }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1 truncate">{{ d.origin }} → {{ d.destination }}</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">{{ d.driver_name || '—' }} · {{ d.truck_number || '—' }} · {{ d.package_count }} pkgs</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick-view modal (same language as PRO tracking modal) -->
            <Transition name="modal">
                <div v-if="quick" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="quick = null" />
                    <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl">
                        <div class="sticky top-0 z-10 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 text-white">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Order tracking · {{ quick.type }}</p>
                                    <h3 class="font-mono text-lg font-black tracking-tight truncate">{{ quick.number }}</h3>
                                </div>
                                <button @click="quick = null" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition active:scale-95">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-white/20 overflow-hidden">
                                <div class="h-full bg-white rounded-full transition-all" :style="{ width: quick.progress + '%' }" />
                            </div>
                        </div>
                        <div class="p-5 sm:p-6 space-y-5">
                            <ol class="relative ml-2 border-l-2 border-gray-100 dark:border-zinc-800 space-y-5">
                                <li v-for="s in quick.steps" :key="s.key" class="relative pl-8">
                                    <span class="absolute left-0 top-0 flex h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full ring-4 ring-white dark:ring-zinc-900"
                                        :class="s.done ? 'bg-emerald-500 text-white' : s.current ? 'bg-indigo-600 text-white animate-pulse' : 'bg-gray-200 dark:bg-zinc-700 text-gray-400'">
                                        <CheckCircle v-if="s.done" class="h-3.5 w-3.5" />
                                        <span v-else class="h-1.5 w-1.5 rounded-full bg-current" />
                                    </span>
                                    <p class="text-sm font-black" :class="!s.done && !s.current ? 'text-gray-400' : 'text-gray-900 dark:text-white'">{{ s.label }}</p>
                                    <p v-if="s.current" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400">Current stage</p>
                                </li>
                            </ol>
                            <div v-if="quick.shipment" class="rounded-2xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 p-4 text-xs flex items-center gap-2">
                                <Truck class="h-4 w-4 text-blue-600 shrink-0" />
                                <p class="font-bold text-blue-900 dark:text-blue-100">{{ quick.shipment.delivery_number }} · {{ formatStatus(quick.shipment.status) }} · {{ quick.shipment.origin }} → {{ quick.shipment.destination }}</p>
                            </div>
                            <Link :href="route('client.tracking.show', { type: quick.type.toLowerCase(), id: quick.id })"
                                class="block text-center w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-sm font-black transition-all active:scale-[0.99]">
                                Open full tracking →
                            </Link>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Navigation, RefreshCw, Search, Package, Truck, X, CheckCircle, Sparkles } from 'lucide-vue-next';

const props = defineProps({
    orders: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, inProduction: 0, inTransit: 0, delivered: 0 }) },
    deliveries: { type: Array, default: () => [] },
});

const searchTerm = ref('');
const typeFilter = ref('all');
const groupFilter = ref('all');
const quick = ref(null);

const typeOpts = [['all', 'All'], ['PO', 'Purchase orders'], ['SO', 'Job orders']];
const groupOpts = [['all', 'All stages'], ['intake', 'Intake'], ['confirmed', 'Confirmed'], ['production', 'Production'], ['ready', 'Ready'], ['transit', 'In transit'], ['done', 'Delivered'], ['attention', 'Attention'], ['cancelled', 'Cancelled']];

const filtered = computed(() => {
    const q = searchTerm.value.trim().toLowerCase();
    return (props.orders || []).filter((o) => {
        if (typeFilter.value !== 'all' && o.type !== typeFilter.value) return false;
        if (groupFilter.value !== 'all' && o.group !== groupFilter.value) return false;
        if (!q) return true;
        return String(o.number || '').toLowerCase().includes(q)
            || String(o.product_name || '').toLowerCase().includes(q);
    });
});

const formatCurrency = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const formatStatus = (s) => String(s || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const statusAccentBar = (status) => {
    const s = String(status || '');
    if (['delivered', 'completed'].includes(s)) return 'bg-emerald-500';
    if (['in_transit'].includes(s)) return 'bg-blue-500';
    if (['approved', 'released_to_production', 'production', 'ready_for_dispatch', 'production_done'].includes(s)) return 'bg-indigo-500';
    if (['on_hold', 'returned'].includes(s)) return 'bg-amber-500';
    if (['cancelled'].includes(s)) return 'bg-rose-500';
    return 'bg-slate-300';
};

const statusBadge = (status) => {
    const map = {
        credit_review: 'bg-orange-100 text-orange-700',
        tier_assignment: 'bg-blue-100 text-blue-700',
        pending_client_approval: 'bg-amber-100 text-amber-700',
        approved: 'bg-emerald-100 text-emerald-700',
        released_to_production: 'bg-indigo-100 text-indigo-700',
        confirmed: 'bg-indigo-100 text-indigo-700',
        in_planning: 'bg-sky-100 text-sky-700',
        in_production: 'bg-violet-100 text-violet-700',
        production_done: 'bg-teal-100 text-teal-700',
        ready_for_dispatch: 'bg-teal-100 text-teal-700',
        in_transit: 'bg-blue-100 text-blue-700',
        delivered: 'bg-emerald-100 text-emerald-700',
        completed: 'bg-green-100 text-green-700',
        on_hold: 'bg-amber-100 text-amber-700',
        cancelled: 'bg-rose-100 text-rose-700',
    };
    return map[status] || 'bg-gray-100 text-gray-600';
};

const shipBadge = (status) => {
    const map = { dispatched: 'bg-amber-100 text-amber-700', in_transit: 'bg-blue-100 text-blue-700', delivered: 'bg-emerald-100 text-emerald-700' };
    return map[status] || 'bg-gray-100 text-gray-600';
};

const openQuickTrack = (o) => { quick.value = o; };
const refreshData = () => router.reload({ only: ['orders', 'stats', 'deliveries'] });
</script>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(12px) scale(0.98); }
</style>
