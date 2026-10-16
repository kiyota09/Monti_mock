<template>
    <Head title="Dispatch Center" />
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
                            <Send class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Logistics · Fleet Control
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Dispatch Center <span v-if="!canEditDispatch" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">Assign trucks, drivers, and routes to pending deliveries.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse" /> {{ deliveries.length }} pending
                            </span>
                            <button @click="refreshData" title="Refresh"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 hover:scale-105 active:scale-95 transition-all">
                                <RefreshCw class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Search + resource strip -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by delivery number..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2">
                            <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide ring-1 ring-white/25 backdrop-blur">{{ trucks.length }} trucks</span>
                            <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide ring-1 ring-white/25 backdrop-blur">{{ drivers.length }} drivers</span>
                            <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide ring-1 ring-white/25 backdrop-blur hidden sm:inline-block">{{ conductors.length }} conductors</span>
                        </div>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-amber-500 to-orange-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Send class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">Pending Dispatch <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" /></p>
                                <h3 class="text-3xl font-black text-amber-600 tracking-tight">{{ deliveries.length }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 p-5 text-white shadow-lg shadow-emerald-500/20 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300" style="animation-delay:160ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <p class="relative text-[10px] font-black text-emerald-100 uppercase tracking-widest">Available Resources</p>
                        <div class="relative flex gap-8 mt-2">
                            <div>
                                <p class="text-2xl font-black">{{ trucks.length }}</p>
                                <p class="text-[9px] font-bold uppercase tracking-widest text-emerald-100">Trucks</p>
                            </div>
                            <div>
                                <p class="text-2xl font-black">{{ drivers.length }}</p>
                                <p class="text-[9px] font-bold uppercase tracking-widest text-emerald-100">Drivers</p>
                            </div>
                            <div>
                                <p class="text-2xl font-black">{{ conductors.length }}</p>
                                <p class="text-[9px] font-bold uppercase tracking-widest text-emerald-100">Conductors</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deliveries Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Truck class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Pending Deliveries</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredDeliveries.length }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">Delivery #</th>
                                    <th class="px-6 py-4">Packages</th>
                                    <th class="px-6 py-4">Products</th>
                                    <th class="px-6 py-4 text-center">Total Qty</th>
                                    <th class="px-6 py-4 text-center">Created</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(delivery, i) in filteredDeliveries" :key="delivery.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group text-sm hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 group-hover:scale-110 transition-transform">
                                                <Truck class="h-5 w-5" />
                                            </div>
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ delivery.delivery_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="space-y-1">
                                            <div v-for="pkg in delivery.packages" :key="pkg.id" class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                                {{ pkg.package_number }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="space-y-1">
                                            <div v-for="pkg in delivery.packages" :key="pkg.id" class="text-xs font-medium text-gray-700 dark:text-gray-200">
                                                {{ pkg.product?.name }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-center font-bold text-gray-900 dark:text-white">
                                        {{ delivery.packages.reduce((sum, p) => sum + (p.quantity || 0), 0) }} pcs
                                    </td>
                                    <td class="px-6 py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(delivery.created_at) }}
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <button v-if="canEditDispatch" @click="openDispatchModal(delivery)"
                                            class="px-5 py-2.5 bg-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5 active:scale-95 transition-all">
                                            Assign &amp; Dispatch
                                        </button>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="filteredDeliveries.length === 0" class="px-6 py-12 text-center">
                            <Send class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No pending deliveries.</p>
                            <p class="text-xs text-gray-400">Load packages first to create deliveries.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispatch Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeModal">
                    <div class="modal-panel bg-white dark:bg-zinc-900 w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
                        <div class="sticky top-0 z-10 relative overflow-hidden px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex justify-between items-center">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <h3 class="relative font-black text-lg">Dispatch Delivery</h3>
                            <button @click="closeModal" class="relative p-1.5 hover:bg-white/20 rounded-xl transition"><X class="h-5 w-5" /></button>
                        </div>

                        <form @submit.prevent="submitDispatch" class="p-6 space-y-6">
                            <!-- Delivery Info -->
                            <div class="bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-2xl border border-gray-100 dark:border-zinc-800">
                                <p class="text-[10px] font-black text-gray-500 uppercase tracking-wider">Delivery #</p>
                                <p class="font-mono font-black text-lg text-gray-900 dark:text-white">{{ selectedDelivery?.delivery_number }}</p>
                                <p class="text-xs text-gray-500 mt-2">{{ selectedDelivery?.packages?.length }} package(s) · {{ totalQuantity }} pcs total</p>
                            </div>

                            <!-- Truck Selection -->
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Assign Truck *</label>
                                <select v-model="form.truck_id" required
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Select a truck</option>
                                    <option v-for="truck in trucks" :key="truck.id" :value="truck.id">
                                        {{ truck.truck_number }} - {{ truck.model }} ({{ truck.plate_number }})
                                    </option>
                                </select>
                            </div>

                            <!-- Driver Selection -->
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Assign Driver *</label>
                                <select v-model="form.driver_id" required
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Select a driver</option>
                                    <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                        {{ driver.user?.name }} (License: {{ driver.license_number }}) - Rating: {{ driver.rating }}★
                                    </option>
                                </select>
                            </div>

                            <!-- Conductor 1 -->
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Conductor 1 (Optional)</label>
                                <select v-model="form.conductor1_id"
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">None</option>
                                    <option v-for="conductor in conductors" :key="conductor.id" :value="conductor.id">
                                        {{ conductor.user?.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Conductor 2 -->
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Conductor 2 (Optional)</label>
                                <select v-model="form.conductor2_id"
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">None</option>
                                    <option v-for="conductor in conductors" :key="conductor.id" :value="conductor.id">
                                        {{ conductor.user?.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Route Selection -->
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Route *</label>
                                <select v-model="form.route_id" required
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Select a route</option>
                                    <option v-for="route in routes" :key="route.id" :value="route.id">
                                        {{ route.name }} ({{ route.distance_km }}km, ~{{ route.estimated_minutes }}min)
                                    </option>
                                </select>
                            </div>

                            <!-- Scheduled Departure -->
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Scheduled Departure *</label>
                                <input type="datetime-local" v-model="form.scheduled_departure" required
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Notes (Optional)</label>
                                <textarea v-model="form.notes" rows="2"
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500"
                                    placeholder="Special instructions for driver/conductor..."></textarea>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex gap-3 pt-4 border-t border-gray-100 dark:border-zinc-800">
                                <button type="button" @click="closeModal"
                                    class="flex-1 px-4 py-3 border border-gray-200 dark:border-zinc-700 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-zinc-800 active:scale-95 transition-all">
                                    Cancel
                                </button>
                                <button v-if="canEditDispatch" type="submit" :disabled="form.processing"
                                    class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 hover:shadow-lg active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                    <Send v-else class="h-4 w-4" />
                                    {{ form.processing ? 'Dispatching...' : 'Dispatch Now' }}
                                </button>
                            </div>
                        </form>
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
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Send, RefreshCw, Search, Truck, X, Loader2, Sparkles } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditDispatch = computed(() => canEdit('LOG', 'dispatch'));

const props = defineProps({
    deliveries: {
        type: Array,
        default: () => []
    },
    trucks: {
        type: Array,
        default: () => []
    },
    drivers: {
        type: Array,
        default: () => []
    },
    conductors: {
        type: Array,
        default: () => []
    },
    routes: {
        type: Array,
        default: () => []
    }
});

const searchTerm = ref('');
const showModal = ref(false);
const selectedDelivery = ref(null);
const toast = ref({ show: false, type: 'success', message: '' });

const form = useForm({
    truck_id: '',
    driver_id: '',
    conductor1_id: '',
    conductor2_id: '',
    route_id: '',
    scheduled_departure: '',
    notes: ''
});

const filteredDeliveries = computed(() => {
    if (!searchTerm.value) return props.deliveries;
    const term = searchTerm.value.toLowerCase();
    return props.deliveries.filter(d => d.delivery_number.toLowerCase().includes(term));
});

const totalQuantity = computed(() => {
    if (!selectedDelivery.value) return 0;
    return selectedDelivery.value.packages.reduce((sum, p) => sum + (p.quantity || 0), 0);
});

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const refreshData = () => {
    router.reload({ only: ['deliveries', 'trucks', 'drivers', 'conductors', 'routes'] });
};

const openDispatchModal = (delivery) => {
    selectedDelivery.value = delivery;
    form.reset();
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedDelivery.value = null;
    form.reset();
};

const submitDispatch = () => {
    if (!selectedDelivery.value) return;
    form.post(route('logistics.dispatch.assign', selectedDelivery.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', `Delivery ${selectedDelivery.value.delivery_number} dispatched successfully.`);
            closeModal();
            refreshData();
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Failed to dispatch. Please check all fields.';
            showToast('error', errorMsg);
        }
    });
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
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
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-panel { opacity: 0; transform: translateY(24px) scale(0.97); }
.modal-leave-to .modal-panel { opacity: 0; transform: translateY(12px) scale(0.98); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
