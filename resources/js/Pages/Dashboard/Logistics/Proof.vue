<template>
    <Head title="Proof of Delivery" />
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
                            <Camera class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <CheckCircle class="h-3.5 w-3.5" /> Logistics · Delivery Confirmation
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Proof of Delivery</h1>
                            <p class="text-sm text-blue-100/90">Completed deliveries with photo evidence and driver notes.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ deliveries.length }} delivered
                            </span>
                            <button @click="refreshData" class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95">
                                Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Search + date filter inside hero -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by delivery #, driver, or client..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2">
                            <button v-for="opt in ['all','today','week','month']" :key="opt" @click="dateFilter = opt"
                                :class="dateFilter === opt ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ opt === 'all' ? 'All Time' : opt === 'today' ? 'Today' : opt === 'week' ? 'Week' : 'Month' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-emerald-500 to-teal-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <CheckCircle class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Delivered</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ deliveries.length }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Camera class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">This Month</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ thisMonthCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col rounded-3xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 p-5 text-white shadow-lg shadow-orange-500/20 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300 overflow-hidden" style="animation-delay:240ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <CheckCircle class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-amber-100">Unique Drivers</p>
                                <h3 class="text-3xl font-black leading-none">{{ uniqueDriversCount }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deliveries Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Camera class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Delivered Orders</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredDeliveries.length }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-8 py-4">Delivery #</th>
                                    <th class="px-8 py-4">Driver</th>
                                    <th class="px-8 py-4">Truck</th>
                                    <th class="px-8 py-4">Delivered At</th>
                                    <th class="px-8 py-4">Proof Image</th>
                                    <th class="px-8 py-4">Notes</th>
                                    <th class="px-8 py-4 text-center">Packages</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(delivery, i) in filteredDeliveries" :key="delivery.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-all">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                                <CheckCircle class="h-5 w-5" />
                                            </div>
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ delivery.delivery_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-2">
                                            <div class="h-8 w-8 rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-[10px] font-black text-white uppercase">
                                                {{ delivery.driver?.user?.name?.charAt(0) || '?' }}
                                            </div>
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ delivery.driver?.user?.name || '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-sm text-gray-500 dark:text-gray-400">{{ delivery.truck?.truck_number || '—' }}</td>
                                    <td class="px-8 py-6 text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDateTime(delivery.proof_of_delivery?.delivered_at || delivery.arrival_time) }}
                                    </td>
                                    <td class="px-8 py-6">
                                        <button @click="openImageViewer(delivery.proof_of_delivery?.image_path)"
                                            v-if="delivery.proof_of_delivery?.image_path"
                                            class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 rounded-xl text-[10px] font-black uppercase hover:bg-indigo-600 hover:text-white transition flex items-center gap-1 hover:-translate-y-0.5">
                                            <Image class="h-3 w-3" /> View Photo
                                        </button>
                                        <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-zinc-800 text-gray-400 text-[9px] font-black uppercase">No proof</span>
                                    </td>
                                    <td class="px-8 py-6">
                                        <p class="text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate" :title="delivery.proof_of_delivery?.notes">
                                            {{ delivery.proof_of_delivery?.notes || '—' }}
                                        </p>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <span class="px-2.5 py-1 bg-gray-100 dark:bg-zinc-800 rounded-xl text-[10px] font-black text-gray-700 dark:text-gray-300">
                                            {{ delivery.packages?.length || 0 }}
                                        </span>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="filteredDeliveries.length === 0" class="px-8 py-20 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 inline-flex animate-bounce-soft">
                                <Camera class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No delivered orders with proof yet.</p>
                            <p class="text-xs text-gray-400 mt-1">Drivers will upload photos upon delivery.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Viewer Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showImageViewer" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="closeImageViewer">
                    <div class="relative max-w-4xl max-h-[90vh] w-full bg-white dark:bg-zinc-900 rounded-3xl overflow-hidden shadow-2xl border border-gray-100 dark:border-zinc-800">
                        <div class="px-6 py-4 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex items-center justify-between relative overflow-hidden">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <p class="relative text-sm font-black tracking-tight">Proof of Delivery</p>
                            <button @click="closeImageViewer" class="relative p-1.5 bg-white/15 hover:bg-white/30 rounded-xl text-white transition">
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                        <img :src="imageViewUrl" class="max-w-full max-h-[70vh] w-full object-contain bg-slate-100 dark:bg-zinc-950" alt="Proof of Delivery" />
                        <div class="p-4 text-center text-xs text-gray-500 border-t border-gray-100 dark:border-zinc-800">
                            Proof of Delivery - Captured by driver upon drop-off
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-lg text-white font-bold text-sm"
                :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                {{ toast.message }}
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Camera, RefreshCw, Search, CheckCircle, Image, X } from 'lucide-vue-next';

const props = defineProps({
    deliveries: {
        type: Array,
        default: () => []
    }
});

// Filters
const searchTerm = ref('');
const dateFilter = ref('all');

// Image viewer
const showImageViewer = ref(false);
const imageViewUrl = ref('');

// Toast
const toast = ref({ show: false, type: 'success', message: '' });

const filteredDeliveries = computed(() => {
    let list = props.deliveries;

    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(d =>
            d.delivery_number?.toLowerCase().includes(term) ||
            d.driver?.user?.name?.toLowerCase().includes(term)
        );
    }

    if (dateFilter.value !== 'all') {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const weekAgo = new Date(today);
        weekAgo.setDate(weekAgo.getDate() - 7);
        const monthAgo = new Date(today);
        monthAgo.setMonth(monthAgo.getMonth() - 1);

        list = list.filter(d => {
            const deliveredAt = new Date(d.proof_of_delivery?.delivered_at || d.arrival_time);
            if (dateFilter.value === 'today') {
                return deliveredAt >= today;
            } else if (dateFilter.value === 'week') {
                return deliveredAt >= weekAgo;
            } else if (dateFilter.value === 'month') {
                return deliveredAt >= monthAgo;
            }
            return true;
        });
    }

    return list;
});

const thisMonthCount = computed(() => {
    const now = new Date();
    const monthAgo = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
    return props.deliveries.filter(d => {
        const deliveredAt = new Date(d.proof_of_delivery?.delivered_at || d.arrival_time);
        return deliveredAt >= monthAgo;
    }).length;
});

const uniqueDriversCount = computed(() => {
    const driverIds = new Set();
    props.deliveries.forEach(d => {
        if (d.driver?.id) driverIds.add(d.driver.id);
    });
    return driverIds.size;
});

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const refreshData = () => {
    router.reload({ only: ['deliveries'] });
};

const formatDateTime = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const openImageViewer = (path) => {
    if (!path) return;
    imageViewUrl.value = '/storage/' + path;
    showImageViewer.value = true;
};

const closeImageViewer = () => {
    showImageViewer.value = false;
    imageViewUrl.value = '';
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
.modal-enter-active, .modal-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95) translateY(12px); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
