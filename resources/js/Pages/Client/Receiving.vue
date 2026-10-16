<template>
    <Head title="Order Receiving" />
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
                                <Truck class="h-3.5 w-3.5" /> Logistics Portal · Receiving
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Order Receiving</h1>
                            <p class="text-sm text-blue-100/90">Confirm delivery of your shipments.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ inTransitCount }} in transit
                            </span>
                            <span class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">{{ deliveredCount }} delivered</span>
                            <button @click="refreshData" title="Refresh"
                                class="flex h-9 w-9 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/30 hover:rotate-180 transition-all duration-500 active:scale-95">
                                <RefreshCw class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by delivery number..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2">
                            <button v-for="opt in [{v:'all',l:'All shipments'},{v:'dispatched',l:'Dispatched'},{v:'in_transit',l:'In transit'},{v:'delivered',l:'Delivered'}]" :key="opt.v" @click="statusFilter = opt.v"
                                :class="statusFilter === opt.v ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ opt.l }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-6 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Truck class="h-6 w-6" />
                            </span>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse" /> In Transit</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ inTransitCount }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-emerald-500/15 hover:-translate-y-1.5 hover:border-emerald-200 dark:hover:border-emerald-800 transition-all duration-300 p-6 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Package class="h-6 w-6" />
                            </span>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" /> Delivered</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ deliveredCount }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deliveries Table -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay:200ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex flex-col sm:flex-row gap-3 sm:items-center">
                        <h2 class="text-sm font-black uppercase tracking-widest flex items-center gap-2">
                            <Package class="h-5 w-5 text-indigo-600" /> Shipments
                        </h2>
                        <span class="rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredDeliveries.length }}</span>
                        <p class="text-[11px] text-gray-400 font-medium sm:ml-auto">Use the hero search above to filter — table updates live.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50/50 dark:bg-zinc-800/30 text-[10px] font-black uppercase text-gray-400 tracking-[0.15em]">
                                <tr>
                                    <th class="px-8 py-5">Delivery #</th>
                                    <th class="px-8 py-5">Driver / Truck</th>
                                    <th class="px-8 py-5">Route</th>
                                    <th class="px-8 py-5">Scheduled</th>
                                    <th class="px-8 py-5 text-center">Status</th>
                                    <th class="px-8 py-5 text-center">Packages</th>
                                    <th class="px-8 py-5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="delivery in filteredDeliveries" :key="delivery.id" class="group/row hover:bg-indigo-50/40 dark:hover:bg-indigo-900/10 transition-all">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white shadow-lg group-hover/row:scale-110 transition-transform">
                                                <Package class="h-5 w-5" />
                                            </div>
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ delivery.delivery_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ delivery.driver_name || '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ delivery.truck_number || '—' }}</div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ delivery.route_name || '—' }}</div>
                                        <div class="text-xs text-gray-500 truncate max-w-[200px]">
                                            {{ delivery.origin }} → {{ delivery.destination }}
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-sm text-gray-600 dark:text-gray-300">{{ formatDateTime(delivery.scheduled) }}</td>
                                    <td class="px-8 py-6 text-center">
                                        <span :class="statusBadge(delivery.status)" class="px-3 py-1 rounded-full text-[9px] font-black uppercase ring-1 ring-inset ring-black/5 inline-flex items-center gap-1.5">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ formatStatus(delivery.status) }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <button @click="openPackagesModal(delivery)" class="text-indigo-600 dark:text-indigo-400 text-xs font-black hover:underline hover:scale-105 active:scale-95 transition">
                                            {{ delivery.packages.length }} package(s)
                                        </button>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <button @click="markAsReceived(delivery)"
                                            :disabled="delivery.status === 'delivered' || processing[delivery.id]"
                                            class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-105 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                            <Loader2 v-if="processing[delivery.id]" class="h-4 w-4 animate-spin inline mr-1" />
                                            {{ delivery.status === 'delivered' ? 'Received' : 'Confirm Receipt' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredDeliveries.length === 0">
                                    <td colspan="7" class="px-8 py-20 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                                <Package class="h-9 w-9 text-indigo-400" />
                                            </div>
                                            <p class="text-sm font-black text-gray-700 dark:text-gray-200 uppercase">No shipments found.</p>
                                            <p class="text-xs text-gray-400 mt-1">Try a different search or filter.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Packages Modal -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showPackagesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closePackagesModal">
                        <div class="bg-white dark:bg-zinc-900 w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden">
                            <div class="relative px-6 py-5 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white flex justify-between items-center overflow-hidden">
                                <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl animate-float" />
                                <div class="relative">
                                    <h3 class="font-black text-lg tracking-tight">Packages in Delivery</h3>
                                    <p class="text-xs text-blue-100 font-mono">{{ selectedDelivery?.delivery_number }}</p>
                                </div>
                                <button @click="closePackagesModal" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all"><X class="h-5 w-5" /></button>
                            </div>
                            <div class="p-6 space-y-3">
                                <div class="divide-y divide-gray-100 dark:divide-zinc-800 rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                    <div v-for="(pkg, idx) in selectedDelivery?.packages" :key="idx" class="py-3 px-4 flex justify-between items-center hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition">
                                        <div class="min-w-0">
                                            <p class="font-bold text-sm text-gray-800 dark:text-gray-100">{{ pkg.product_name || '—' }}</p>
                                            <p class="text-xs text-gray-500 font-mono">{{ pkg.package_number }}</p>
                                        </div>
                                        <p class="font-black text-sm text-indigo-700 dark:text-indigo-300 shrink-0">{{ pkg.quantity }} pcs</p>
                                    </div>
                                </div>
                                <button @click="closePackagesModal" class="w-full py-3 bg-gray-100 dark:bg-zinc-800 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-500 hover:bg-gray-200 active:scale-95 transition">Close</button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <!-- Toast Notification -->
            <Transition name="toast">
                <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-xl text-white font-bold text-sm ring-1 ring-white/20"
                    :class="toast.type === 'success' ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-gradient-to-r from-rose-600 to-red-600'">
                    {{ toast.message }}
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Truck, RefreshCw, Search, Package, Loader2, X } from 'lucide-vue-next';

const props = defineProps({
    deliveries: {
        type: Array,
        default: () => []
    }
});

const searchTerm = ref('');
const statusFilter = ref('all');
const processing = ref({});
const toast = ref({ show: false, type: 'success', message: '' });
const showPackagesModal = ref(false);
const selectedDelivery = ref(null);

const filteredDeliveries = computed(() => {
    let list = props.deliveries;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(d => d.delivery_number.toLowerCase().includes(term));
    }
    if (statusFilter.value !== 'all') {
        list = list.filter(d => d.status === statusFilter.value);
    }
    return list;
});

const inTransitCount = computed(() => props.deliveries.filter(d => d.status === 'in_transit').length);
const deliveredCount = computed(() => props.deliveries.filter(d => d.status === 'delivered').length);

const statusBadge = (status) => {
    const map = {
        dispatched: 'bg-amber-100 text-amber-700',
        in_transit: 'bg-blue-100 text-blue-700',
        delivered: 'bg-emerald-100 text-emerald-700'
    };
    return map[status] || 'bg-gray-100 text-gray-600';
};

const formatStatus = (status) => {
    const map = { dispatched: 'Dispatched', in_transit: 'In Transit', delivered: 'Delivered' };
    return map[status] || status;
};

const formatDateTime = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const refreshData = () => {
    router.reload({ only: ['deliveries'] });
};

const openPackagesModal = (delivery) => {
    selectedDelivery.value = delivery;
    showPackagesModal.value = true;
};

const closePackagesModal = () => {
    showPackagesModal.value = false;
    selectedDelivery.value = null;
};

const markAsReceived = async (delivery) => {
    if (delivery.status === 'delivered') return;
    processing.value[delivery.id] = true;
    try {
        await router.post(route('client.receiving.mark', delivery.id));
        showToast('success', `Delivery ${delivery.delivery_number} confirmed. Thank you!`);
        refreshData();
    } catch (error) {
        showToast('error', 'Failed to confirm delivery.');
    } finally {
        processing.value[delivery.id] = false;
    }
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
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
