<template>
    <Head title="Logistics Dashboard" />
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
                            <Truck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Logistics · Fleet Operations
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Logistics Dashboard</h1>
                            <p class="text-sm text-blue-100/90">Monitor shipments, fleet status, and delivery performance.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ stats.availableTrucks }} trucks ready
                            </span>
                            <button @click="refreshData" title="Refresh"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 hover:scale-105 active:scale-95 transition-all">
                                <RefreshCw class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Stat strip -->
                    <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:80ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><Package class="h-3.5 w-3.5" /> Pending</p>
                            <p class="mt-1 text-lg font-black">{{ stats.pendingPackages }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:160ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><Send class="h-3.5 w-3.5" /> Dispatched</p>
                            <p class="mt-1 text-lg font-black">{{ stats.dispatchedDeliveries }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:240ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><Navigation class="h-3.5 w-3.5" /> In Transit</p>
                            <p class="mt-1 text-lg font-black">{{ stats.inTransitDeliveries }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:320ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><Truck class="h-3.5 w-3.5" /> Trucks</p>
                            <p class="mt-1 text-lg font-black">{{ stats.availableTrucks }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:400ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><Users class="h-3.5 w-3.5" /> Drivers</p>
                            <p class="mt-1 text-lg font-black">{{ stats.availableDrivers }}</p>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Package class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Pending</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.pendingPackages }}</h3>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Packages waiting to be loaded</p>
                    </div>

                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-amber-500 to-orange-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Send class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Dispatched</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.dispatchedDeliveries }}</h3>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">On the way to departure</p>
                    </div>

                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:240ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-cyan-400/20 to-blue-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-cyan-500 to-blue-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-500 via-sky-600 to-blue-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Navigation class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">In Transit <span class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse" /></p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.inTransitDeliveries }}</h3>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Currently on the road</p>
                    </div>

                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:320ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-emerald-500 to-teal-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Truck class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Trucks</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.availableTrucks }}</h3>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Ready for dispatch</p>
                    </div>

                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:400ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-violet-400/20 to-purple-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-violet-500 to-purple-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 via-purple-600 to-fuchsia-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Users class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Drivers</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.availableDrivers }}</h3>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Ready to drive</p>
                    </div>
                </div>

                <!-- Recent Deliveries Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Clock class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Recent Deliveries</h2>
                        <Link :href="route('logistics.dispatch.index')" class="ml-auto text-xs font-bold text-indigo-600 hover:underline">
                            View All
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">Delivery #</th>
                                    <th class="px-6 py-4">Driver</th>
                                    <th class="px-6 py-4">Truck</th>
                                    <th class="px-6 py-4">Route</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-center">Departure</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="delivery in recentDeliveries" :key="delivery.id" class="group text-sm hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600">
                                                <Truck class="h-5 w-5" />
                                            </div>
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ delivery.delivery_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-6 w-6 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-[10px] font-black text-white">
                                                {{ delivery.driver?.user?.name?.charAt(0) || '?' }}
                                            </div>
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ delivery.driver?.user?.name || '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ delivery.truck?.truck_number || '—' }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ delivery.route?.name || '—' }}</td>
                                    <td class="px-6 py-3 text-center">
                                        <span :class="statusBadge(delivery.status)" class="px-3 py-1 rounded-full ring-1 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                            {{ formatStatus(delivery.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDateTime(delivery.scheduled_departure) }}
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <Link :href="route('logistics.dispatch.index')" class="inline-flex items-center gap-1 text-indigo-600 text-xs font-bold hover:underline">
                                            Track <ArrowRight class="h-3 w-3" />
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="recentDeliveries.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <Truck class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                                        <p class="mt-2 text-sm font-bold text-gray-400">No recent deliveries. Start by loading packages from the manufacturing module.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Link key="load" :href="route('logistics.load.index')"
                        class="animate-fade-up group relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-blue-700 to-cyan-700 p-6 text-white shadow-lg shadow-indigo-500/20 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300" style="animation-delay:80ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-100">Step 1</p>
                                <p class="text-xl font-black mt-1">Load Packages</p>
                                <p class="text-sm text-indigo-100/90 mt-2">Receive finished goods from Manufacturing</p>
                            </div>
                            <Package class="h-12 w-12 opacity-80 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300" />
                        </div>
                    </Link>

                    <Link key="dispatch" :href="route('logistics.dispatch.index')"
                        class="animate-fade-up group relative overflow-hidden rounded-3xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 p-6 text-white shadow-lg shadow-orange-500/20 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300" style="animation-delay:160ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-100">Step 2</p>
                                <p class="text-xl font-black mt-1">Dispatch</p>
                                <p class="text-sm text-amber-100/90 mt-2">Assign drivers, trucks, and routes</p>
                            </div>
                            <Send class="h-12 w-12 opacity-80 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300" />
                        </div>
                    </Link>

                    <Link key="fleet" :href="route('logistics.fleet.index')"
                        class="animate-fade-up group relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 p-6 text-white shadow-lg shadow-emerald-500/20 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300" style="animation-delay:240ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-100">Fleet</p>
                                <p class="text-xl font-black mt-1">Manage Fleet</p>
                                <p class="text-sm text-emerald-100/90 mt-2">Trucks, drivers, and conductors</p>
                            </div>
                            <Users class="h-12 w-12 opacity-80 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300" />
                        </div>
                    </Link>
                </TransitionGroup>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Truck, Package, Send, Navigation, Users, RefreshCw, Clock, ArrowRight, Sparkles } from 'lucide-vue-next';

defineProps({
    stats: {
        type: Object,
        default: () => ({
            pendingPackages: 0,
            dispatchedDeliveries: 0,
            inTransitDeliveries: 0,
            availableTrucks: 0,
            availableDrivers: 0,
        })
    },
    recentDeliveries: {
        type: Array,
        default: () => []
    }
});

const refreshData = () => {
    router.reload({ only: ['stats', 'recentDeliveries'] });
};

const statusBadge = (status) => {
    const map = {
        pending: 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700',
        dispatched: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        in_transit: 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        delivered: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        cancelled: 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30',
    };
    return map[status] || 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const formatStatus = (status) => {
    const map = {
        pending: 'Pending',
        dispatched: 'Dispatched',
        in_transit: 'In Transit',
        delivered: 'Delivered',
        cancelled: 'Cancelled',
    };
    return map[status] || status;
};

const formatDateTime = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
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
</style>
