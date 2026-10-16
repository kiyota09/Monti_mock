<template>
    <AuthenticatedLayout>
        <Head title="In-Transit Deliveries" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

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
                                <Sparkles class="h-3.5 w-3.5" /> ORD · Logistics
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Delivery Tracking</h1>
                            <p class="text-sm text-blue-100/90">Monitor all active and completed shipments</p>
                        </div>
                        <!-- Summary pill -->
                        <div class="flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                            <span class="h-2 w-2 rounded-full bg-blue-200 animate-pulse"></span>
                            {{ deliveries.length }} Shipment{{ deliveries.length !== 1 ? 's' : '' }}
                        </div>
                    </div>
                </div>

                <!-- Cards Grid -->
                <TransitionGroup name="card" v-if="deliveries.length > 0" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-5">
                    <div
                        v-for="(delivery, i) in deliveries"
                        :key="delivery.id"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 overflow-hidden flex flex-col"
                    >
                        <!-- hover glow -->
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <!-- Card Top Accent -->
                        <div :class="statusAccentBar(delivery.status)" class="h-1 w-full"></div>

                        <!-- Card Header -->
                        <div class="px-5 pt-4 pb-3 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-black text-gray-900 dark:text-white text-base truncate tracking-tight group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ delivery.delivery_number }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate mt-0.5">{{ delivery.client_name || 'No client assigned' }}</p>
                            </div>
                            <span :class="statusBadge(delivery.status)" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-wide rounded-full flex-shrink-0 border flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                {{ formatStatus(delivery.status) }}
                            </span>
                        </div>

                        <!-- Card Body -->
                        <div class="px-5 pb-4 flex-1">
                            <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">Driver</p>
                                    <p class="text-gray-800 dark:text-gray-100 font-medium truncate">{{ delivery.driver_name || '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">Truck</p>
                                    <p class="text-gray-800 dark:text-gray-100 font-medium truncate">{{ delivery.truck_number || '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">Route</p>
                                    <p class="text-gray-800 dark:text-gray-100 font-medium truncate">{{ delivery.route_name || '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-0.5">Packages</p>
                                    <p class="text-indigo-600 dark:text-indigo-300 font-black text-base leading-tight">{{ delivery.package_count }}</p>
                                </div>
                            </div>

                            <!-- Route Visual -->
                            <div class="mt-4 flex items-center gap-2 bg-slate-50 dark:bg-zinc-800/60 rounded-2xl px-3 py-2.5 border border-gray-100 dark:border-zinc-800">
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">From</p>
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate">{{ delivery.origin }}</p>
                                </div>
                                <div class="flex-shrink-0 flex flex-col items-center">
                                    <div class="w-1 h-1 rounded-full bg-indigo-400 animate-pulse"></div>
                                    <div class="w-8 h-px bg-indigo-300 my-0.5"></div>
                                    <div class="w-1 h-1 rounded-full bg-indigo-600 animate-pulse"></div>
                                </div>
                                <div class="flex-1 min-w-0 text-right">
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">To</p>
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate">{{ delivery.destination }}</p>
                                </div>
                            </div>

                            <p class="text-[11px] text-gray-400 mt-2.5">
                                <span class="font-bold">Scheduled:</span> {{ formatDateTime(delivery.scheduled_departure) }}
                            </p>
                        </div>

                        <!-- Card Footer -->
                        <div class="px-5 py-3 border-t border-gray-100 dark:border-zinc-800 flex gap-2">
                            <button
                                @click="openPackagesModal(delivery)"
                                class="flex-1 py-2.5 bg-gray-100 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 hover:bg-gray-200 dark:hover:bg-zinc-700 rounded-2xl text-xs font-bold text-gray-700 dark:text-gray-200 transition-all active:scale-95"
                            >
                                View Packages
                            </button>
                            <Link
                                :href="route('ord.delivery.track', { id: delivery.id })"
                                class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-xs font-bold text-center transition-all hover:shadow-lg hover:shadow-indigo-500/25 active:scale-95"
                            >
                                Track & POD
                            </Link>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Empty State -->
                <div v-if="deliveries.length === 0" class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 dark:to-violet-900/20 rounded-full mb-4 animate-bounce-soft">
                        <Package class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No deliveries in transit</p>
                    <p class="text-xs text-gray-400 mt-1">Shipments will appear here once dispatched</p>
                </div>

            </div>
        </div>

        <!-- Packages Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="selectedDelivery"
                    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/40 backdrop-blur-sm"
                    @click.self="selectedDelivery = null"
                >
                    <div
                        class="bg-white dark:bg-zinc-900 w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] border border-gray-100 dark:border-zinc-800"
                    >
                        <!-- Modal Handle (mobile) -->
                        <div class="sm:hidden flex justify-center pt-3 pb-1">
                            <div class="w-10 h-1 rounded-full bg-gray-200 dark:bg-zinc-700"></div>
                        </div>

                        <!-- Modal Header -->
                        <div class="relative overflow-hidden px-6 py-4 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 flex items-center justify-between flex-shrink-0">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <div class="relative">
                                <p class="text-blue-100 text-[10px] font-bold uppercase tracking-[0.2em]">Packages</p>
                                <h3 class="text-white font-black text-lg leading-tight">{{ selectedDelivery.delivery_number }}</h3>
                            </div>
                            <button
                                @click="selectedDelivery = null"
                                class="relative w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-xl transition-colors"
                            >
                                <X class="w-4 h-4 text-white" />
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="overflow-y-auto flex-1 p-4">
                            <div v-if="selectedDelivery.packages.length === 0" class="flex flex-col items-center justify-center py-12">
                                <div class="w-12 h-12 bg-slate-100 dark:bg-zinc-800 rounded-2xl flex items-center justify-center mb-3 animate-bounce-soft">
                                    <Package class="w-6 h-6 text-gray-400" />
                                </div>
                                <p class="text-gray-400 text-sm font-semibold">No packages listed</p>
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="(pkg, idx) in selectedDelivery.packages"
                                    :key="idx"
                                    class="flex items-center gap-3 p-3.5 bg-slate-50 dark:bg-zinc-800/60 border border-gray-100 dark:border-zinc-800 rounded-2xl hover:border-indigo-200 dark:hover:border-indigo-800 hover:shadow-md transition-all"
                                >
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center flex-shrink-0 shadow-md">
                                        <span class="text-[10px] font-black text-white">{{ idx + 1 }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ pkg.product_name }}</p>
                                        <p class="text-[11px] text-gray-400 mt-0.5">{{ pkg.package_number }}</p>
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <span class="text-indigo-600 dark:text-indigo-300 font-black text-base">{{ pkg.quantity }}</span>
                                        <span class="text-gray-400 text-[10px] block">pcs</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="p-4 border-t border-gray-100 dark:border-zinc-800 flex-shrink-0">
                            <button
                                @click="selectedDelivery = null"
                                class="w-full py-3 bg-gray-900 dark:bg-white hover:bg-gray-800 dark:hover:bg-gray-100 text-white dark:text-zinc-900 rounded-2xl text-sm font-bold transition-all active:scale-[0.99]"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { X, Truck, Sparkles, Package } from 'lucide-vue-next';

const props = defineProps({
    deliveries: Array
});

const selectedDelivery = ref(null);

const statusAccentBar = (status) => {
    const map = {
        dispatched: 'bg-yellow-400',
        in_transit: 'bg-blue-500',
        delivered: 'bg-green-500',
    };
    return map[status] || 'bg-gray-200';
};

const statusBadge = (status) => {
    const map = {
        dispatched: 'bg-yellow-50 text-yellow-700 border-yellow-200',
        in_transit: 'bg-blue-50 text-blue-700 border-blue-200',
        delivered: 'bg-green-50 text-green-700 border-green-200',
    };
    return map[status] || 'bg-gray-50 text-gray-600 border-gray-200';
};

const formatStatus = (status) => {
    const map = {
        dispatched: 'Dispatched',
        in_transit: 'In Transit',
        delivered: 'Delivered',
    };
    return map[status] || status;
};

const formatDateTime = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const openPackagesModal = (delivery) => {
    selectedDelivery.value = delivery;
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
.modal-enter-active { transition: opacity 0.25s ease, transform 0.32s cubic-bezier(0.22,1,0.36,1); }
.modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(18px) scale(0.97); }
</style>
