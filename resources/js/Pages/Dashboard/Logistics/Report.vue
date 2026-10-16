<template>
    <Head title="Conductor Reports" />
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
                            <FileText class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Eye class="h-3.5 w-3.5" /> Logistics · Field Intelligence
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Conductor Reports</h1>
                            <p class="text-sm text-blue-100/90">On-road observations, incidents, and delivery feedback from conductors.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ reports.length }} reports
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
                            <input v-model="searchTerm" type="text" placeholder="Search by conductor, delivery #, or report text..."
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
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <FileText class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Reports</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ reports.length }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-emerald-500 to-teal-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Truck class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Active Conductors</p>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ uniqueConductorsCount }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative flex flex-col rounded-3xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 p-5 text-white shadow-lg shadow-orange-500/20 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300 overflow-hidden" style="animation-delay:240ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <FileText class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-amber-100">This Month</p>
                                <h3 class="text-3xl font-black leading-none">{{ thisMonthCount }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-amber-50 to-transparent dark:from-amber-900/10 flex items-center gap-2">
                        <FileText class="h-5 w-5 text-amber-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Field Reports</h2>
                        <span class="ml-auto rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-[11px] font-black px-2.5 py-1">{{ filteredReports.length }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-8 py-4">Conductor</th>
                                    <th class="px-8 py-4">Delivery #</th>
                                    <th class="px-8 py-4">Report</th>
                                    <th class="px-8 py-4 text-center">Submitted</th>
                                    <th class="px-8 py-4 text-center">Actions</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(report, i) in filteredReports" :key="report.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-all">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white text-sm font-black shadow-lg uppercase group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                                {{ report.conductor?.user?.name?.charAt(0) || 'C' }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-black text-gray-900 dark:text-white truncate group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ report.conductor?.user?.name }}</p>
                                                <p class="text-[10px] text-gray-400 truncate">{{ report.conductor?.user?.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20"><Truck class="h-3 w-3 text-blue-500" /></span>
                                            <span class="font-mono text-sm font-bold text-gray-900 dark:text-white">{{ report.delivery?.delivery_number }}</span>
                                        </div>
                                        <p class="text-[10px] text-gray-400 mt-1">
                                            {{ formatDate(report.delivery?.scheduled_departure) }}
                                        </p>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="max-w-md">
                                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap line-clamp-3">
                                                {{ report.report_text }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <div class="flex flex-col items-center">
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ formatTime(report.created_at) }}</span>
                                            <span class="text-[10px] text-gray-400">{{ formatDate(report.created_at) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <button @click="viewFullReport(report)" class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-600 hover:text-white transition hover:-translate-y-0.5">
                                            <Eye class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="filteredReports.length === 0" class="px-8 py-20 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 inline-flex animate-bounce-soft">
                                <FileText class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No conductor reports submitted yet.</p>
                            <p class="text-xs text-gray-400 mt-1">Conductors can submit reports from their portal.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Full Report Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showReportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeReportModal">
                    <div class="bg-white dark:bg-zinc-900 w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="px-6 py-5 bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white flex justify-between items-center relative overflow-hidden">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="relative flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur"><FileText class="h-5 w-5" /></span>
                                <h3 class="font-black text-lg tracking-tight">Conductor Report</h3>
                            </div>
                            <button @click="closeReportModal" class="relative p-1.5 hover:bg-white/20 rounded-xl transition"><X class="h-5 w-5" /></button>
                        </div>
                        <div v-if="selectedReport" class="p-6 space-y-5">
                            <!-- Header Info -->
                            <div class="grid grid-cols-2 gap-4 pb-4 border-b border-gray-100 dark:border-zinc-800">
                                <div>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-wider">Conductor</p>
                                    <p class="font-bold text-gray-900 dark:text-white">{{ selectedReport.conductor?.user?.name }}</p>
                                    <p class="text-xs text-gray-500">{{ selectedReport.conductor?.user?.email }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-wider">Delivery</p>
                                    <p class="font-mono font-bold text-gray-900 dark:text-white">{{ selectedReport.delivery?.delivery_number }}</p>
                                    <p class="text-xs text-gray-500">{{ formatDateTime(selectedReport.delivery?.scheduled_departure) }}</p>
                                </div>
                            </div>

                            <!-- Report Content -->
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-wider mb-2">Report Text</p>
                                <div class="bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-2xl whitespace-pre-wrap text-gray-700 dark:text-gray-300">
                                    {{ selectedReport.report_text }}
                                </div>
                            </div>

                            <!-- Metadata -->
                            <div class="text-xs text-gray-400 border-t border-gray-100 dark:border-zinc-800 pt-4 flex justify-between">
                                <span>Report ID: #{{ selectedReport.id }}</span>
                                <span>Submitted: {{ formatDateTime(selectedReport.created_at) }}</span>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-3 pt-2">
                                <button @click="closeReportModal" class="flex-1 px-4 py-3 border border-gray-200 dark:border-zinc-700 rounded-2xl text-[10px] font-black uppercase hover:bg-gray-50 dark:hover:bg-zinc-800 transition active:scale-95">
                                    Close
                                </button>
                                <button v-if="selectedReport.delivery" @click="goToDelivery(selectedReport.delivery.id)" class="flex-1 px-4 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-2xl text-[10px] font-black uppercase hover:shadow-lg hover:-translate-y-0.5 transition flex items-center justify-center gap-2 active:scale-95">
                                    <Truck class="h-3 w-3" />
                                    View Delivery
                                </button>
                            </div>
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
import { FileText, RefreshCw, Search, Truck, Eye, X } from 'lucide-vue-next';

const props = defineProps({
    reports: {
        type: Array,
        default: () => []
    }
});

// Filters
const searchTerm = ref('');
const dateFilter = ref('all');

// Modal
const showReportModal = ref(false);
const selectedReport = ref(null);
const toast = ref({ show: false, type: 'success', message: '' });

const filteredReports = computed(() => {
    let list = props.reports;

    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(r =>
            r.conductor?.user?.name?.toLowerCase().includes(term) ||
            r.delivery?.delivery_number?.toLowerCase().includes(term) ||
            r.report_text?.toLowerCase().includes(term)
        );
    }

    if (dateFilter.value !== 'all') {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const weekAgo = new Date(today);
        weekAgo.setDate(weekAgo.getDate() - 7);
        const monthAgo = new Date(today);
        monthAgo.setMonth(monthAgo.getMonth() - 1);

        list = list.filter(r => {
            const submittedAt = new Date(r.created_at);
            if (dateFilter.value === 'today') return submittedAt >= today;
            if (dateFilter.value === 'week') return submittedAt >= weekAgo;
            if (dateFilter.value === 'month') return submittedAt >= monthAgo;
            return true;
        });
    }

    return list;
});

const uniqueConductorsCount = computed(() => {
    const conductorIds = new Set();
    props.reports.forEach(r => {
        if (r.conductor?.id) conductorIds.add(r.conductor.id);
    });
    return conductorIds.size;
});

const thisMonthCount = computed(() => {
    const now = new Date();
    const monthAgo = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
    return props.reports.filter(r => new Date(r.created_at) >= monthAgo).length;
});

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const refreshData = () => {
    router.reload({ only: ['reports'] });
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatTime = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const formatDateTime = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const viewFullReport = (report) => {
    selectedReport.value = report;
    showReportModal.value = true;
};

const closeReportModal = () => {
    showReportModal.value = false;
    selectedReport.value = null;
};

const goToDelivery = (deliveryId) => {
    router.visit(route('logistics.dispatch.index')); // or a dedicated delivery view
    closeReportModal();
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
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.toast-enter-active, .toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from, .toast-leave-to {
    opacity: 0;
    transform: translateY(20px);
}
</style>
