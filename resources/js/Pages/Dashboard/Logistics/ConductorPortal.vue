<template>
    <Head title="Conductor Portal - My Trips" />
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
                            <ClipboardList class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Logistics · Conductor Portal
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">My Trips &amp; Reports</h1>
                            <p class="text-sm text-blue-100/90">View assigned deliveries, submit on-road reports to the logistics manager.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse" /> {{ deliveries.length }} trip{{ deliveries.length !== 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="deliveries.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <ClipboardList class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No trips assigned.</p>
                    <p class="text-xs text-gray-400 mt-1">You will appear here when assigned to a delivery.</p>
                </div>

                <!-- Delivery Cards -->
                <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 gap-4">
                    <div v-for="(delivery, i) in deliveries" :key="delivery.id" :style="{ transitionDelay: `${Math.min(i * 60, 400)}ms` }"
                        class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-amber-500 to-orange-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <!-- Header -->
                        <div class="relative overflow-hidden bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 px-6 py-4 text-white flex justify-between items-center">
                            <div class="absolute -top-10 -right-10 h-28 w-28 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <div class="relative">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-100">Delivery #</p>
                                <p class="font-mono text-lg font-black">{{ delivery.delivery_number }}</p>
                            </div>
                            <span class="relative px-3 py-1 rounded-full ring-1 ring-white/30 bg-white/15 backdrop-blur text-[9px] font-black uppercase inline-flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                {{ formatStatus(delivery.status) }}
                            </span>
                        </div>

                        <div class="relative p-6 space-y-4">
                            <!-- Driver & Route -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex items-start gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-4">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex-shrink-0"><Users class="h-4 w-4 text-indigo-500" /></span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Driver</p>
                                        <p class="font-bold text-sm text-gray-900 dark:text-white">{{ delivery.driver?.user?.name || '—' }}</p>
                                        <p class="text-xs text-gray-500">License: {{ delivery.driver?.license_number }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-4">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex-shrink-0"><Navigation class="h-4 w-4 text-emerald-500" /></span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Route</p>
                                        <p class="font-bold text-sm text-gray-900 dark:text-white">{{ delivery.route?.name || '—' }}</p>
                                        <p class="text-xs text-gray-500">{{ delivery.route?.origin }} → {{ delivery.route?.destination }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Packages Summary -->
                            <div class="p-4 bg-gray-50 dark:bg-zinc-800/50 rounded-2xl border border-gray-100 dark:border-zinc-800">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Packages on Board</p>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ delivery.packages?.length || 0 }} package(s) · {{ totalQuantity(delivery) }} pcs total</p>
                            </div>

                            <!-- Report Button (only for in_transit or delivered) -->
                            <div v-if="delivery.status !== 'dispatched'" class="pt-2">
                                <button @click="openReportModal(delivery)" class="w-full py-3 rounded-2xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/70 dark:bg-indigo-900/10 text-indigo-700 dark:text-indigo-300 text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white hover:shadow-lg hover:-translate-y-0.5 active:scale-95 transition-all flex items-center justify-center gap-2">
                                    <FileText class="h-4 w-4" />
                                    Submit Report
                                </button>
                            </div>
                            <div v-else class="text-center text-xs text-gray-400 italic">
                                Report available after trip starts.
                            </div>
                        </div>
                    </div>
                </TransitionGroup>
            </div>
        </div>

        <!-- Report Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showReportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeReportModal">
                    <div class="modal-panel bg-white dark:bg-zinc-900 w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden">
                        <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white flex justify-between items-center">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <h3 class="relative font-black text-lg">Submit Conductor Report</h3>
                            <button @click="closeReportModal" class="relative p-1.5 hover:bg-white/20 rounded-xl transition"><X class="h-5 w-5" /></button>
                        </div>
                        <form @submit.prevent="submitReport" class="p-6 space-y-5">
                            <div class="bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-2xl border border-gray-100 dark:border-zinc-800">
                                <p class="text-xs font-black text-gray-900 dark:text-white">Delivery #{{ selectedDelivery?.delivery_number }}</p>
                                <p class="text-xs text-gray-500">Driver: {{ selectedDelivery?.driver?.user?.name }}</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Report Details *</label>
                                <textarea v-model="reportText" rows="5" required
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500"
                                    placeholder="e.g., Road conditions, delays, accidents, customer feedback, vehicle issues..."></textarea>
                            </div>
                            <div class="text-xs text-gray-400">
                                Your report will be sent directly to the Logistics Manager.
                            </div>
                            <button type="submit" :disabled="submitting"
                                class="w-full py-3 bg-amber-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-amber-700 hover:shadow-lg active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                                <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                                <Send v-else class="h-4 w-4" />
                                {{ submitting ? 'Sending...' : 'Send Report' }}
                            </button>
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
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ClipboardList, Users, Navigation, FileText, X, Loader2, Send, Sparkles } from 'lucide-vue-next';

const props = defineProps({
    deliveries: {
        type: Array,
        default: () => []
    }
});

const showReportModal = ref(false);
const selectedDelivery = ref(null);
const reportText = ref('');
const submitting = ref(false);
const toast = ref({ show: false, type: 'success', message: '' });

const statusBadge = (status) => {
    const map = {
        dispatched: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        in_transit: 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        delivered: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    };
    return map[status] || 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const formatStatus = (status) => {
    const map = { dispatched: 'Dispatched', in_transit: 'In Transit', delivered: 'Delivered' };
    return map[status] || status;
};

const totalQuantity = (delivery) => {
    return delivery.packages?.reduce((sum, p) => sum + (p.quantity || 0), 0) || 0;
};

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const openReportModal = (delivery) => {
    selectedDelivery.value = delivery;
    reportText.value = '';
    showReportModal.value = true;
};

const closeReportModal = () => {
    showReportModal.value = false;
    selectedDelivery.value = null;
};

const submitReport = async () => {
    if (!reportText.value.trim()) {
        showToast('error', 'Please enter report details.');
        return;
    }
    submitting.value = true;
    try {
        await router.post(route('logistics.conductor.report', selectedDelivery.value.id), {
            report: reportText.value
        });
        showToast('success', 'Report submitted to Logistics Manager.');
        closeReportModal();
    } catch (error) {
        showToast('error', 'Failed to submit report.');
    } finally {
        submitting.value = false;
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
