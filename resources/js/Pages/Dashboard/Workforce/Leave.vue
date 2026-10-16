<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Calendar, CheckCircle, XCircle, Clock, Search, Filter,
    User, Briefcase, AlertCircle, ChevronDown, X
} from 'lucide-vue-next';

const props = defineProps({
    leaveRequests: {
        type: Array,
        default: () => []
    }
});

// Toast notification
const showToast = ref(false);
const toastMessage = ref('');
const triggerToast = (msg) => {
    toastMessage.value = msg;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 4000);
};

// Flash messages from server
const page = usePage();
if (page.props.flash?.message) {
    triggerToast(page.props.flash.message);
}

// Filters
const searchQuery = ref('');
const statusFilter = ref('ALL');

const filteredRequests = computed(() => {
    let list = props.leaveRequests;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(r =>
            r.user_name.toLowerCase().includes(q) ||
            r.user_role.toLowerCase().includes(q) ||
            r.leave_type.toLowerCase().includes(q)
        );
    }
    if (statusFilter.value !== 'ALL') {
        list = list.filter(r => r.status === statusFilter.value);
    }
    return list;
});

// Stats
const stats = computed(() => ({
    total: props.leaveRequests.length,
    pending: props.leaveRequests.filter(r => r.status === 'pending').length,
    approved: props.leaveRequests.filter(r => r.status === 'approved').length,
    rejected: props.leaveRequests.filter(r => r.status === 'rejected').length,
}));

// Rejection modal state
const isRejectModalOpen = ref(false);
const selectedRequest = ref(null);
const rejectReason = ref('');

const openRejectModal = (request) => {
    selectedRequest.value = request;
    rejectReason.value = '';
    isRejectModalOpen.value = true;
};

const closeRejectModal = () => {
    isRejectModalOpen.value = false;
    selectedRequest.value = null;
    rejectReason.value = '';
};

const approveRequest = (id) => {
    if (confirm('Approve this leave request?')) {
        router.post(route('workforce.leave.approve', id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast('Leave request approved.');
            },
            onError: (errors) => {
                triggerToast(Object.values(errors)[0] || 'Approval failed.');
            }
        });
    }
};

const rejectRequest = () => {
    if (!rejectReason.value.trim()) {
        alert('Please provide a reason for rejection.');
        return;
    }
    router.post(route('workforce.leave.reject', selectedRequest.value.id), {
        reason: rejectReason.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Leave request rejected.');
            closeRejectModal();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Rejection failed.');
        }
    });
};

// Helper functions
const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'approved': return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'pending': return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'rejected': return 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30';
        default: return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};

const formatDate = (date) => date ? new Date(date).toLocaleDateString() : 'N/A';
const getInitials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
</script>

<template>
    <Head title="Leave Management" />

    <AuthenticatedLayout>
        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="showToast"
                class="fixed top-6 right-6 z-[100] flex items-center gap-3 px-6 py-4 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-2xl shadow-2xl border border-white/10">
                <CheckCircle class="h-5 w-5 text-emerald-400 dark:text-emerald-600" />
                <p class="text-sm font-bold uppercase tracking-tight">{{ toastMessage }}</p>
            </div>
        </Transition>

        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Calendar class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Briefcase class="h-3.5 w-3.5" /> Workforce · Time Off
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Leave Management</h1>
                            <p class="text-sm text-blue-100/90">{{ filteredRequests.length }} of {{ stats.total }} request{{ stats.total !== 1 ? 's' : '' }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ stats.pending }} pending
                            </span>
                            <span class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">{{ stats.approved }} approved</span>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search by name, role, or leave type..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex gap-2">
                            <button v-for="status in ['ALL', 'pending', 'approved', 'rejected']" :key="status"
                                @click="statusFilter = status"
                                :class="statusFilter === status ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ status === 'ALL' ? 'All' : status }} · {{ status === 'ALL' ? stats.total : stats[status] ?? 0 }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div
                        v-for="(card, i) in [
                            { label: 'Total Requests', value: stats.total, icon: Calendar, chip: 'bg-blue-50 dark:bg-blue-900/20', iconClass: 'text-blue-500', bar: 'from-blue-500 to-indigo-500' },
                            { label: 'Pending', value: stats.pending, icon: Clock, chip: 'bg-amber-50 dark:bg-amber-900/20', iconClass: 'text-amber-500', bar: 'from-amber-500 to-orange-500' },
                            { label: 'Approved', value: stats.approved, icon: CheckCircle, chip: 'bg-emerald-50 dark:bg-emerald-900/20', iconClass: 'text-emerald-500', bar: 'from-emerald-500 to-teal-500' },
                            { label: 'Rejected', value: stats.rejected, icon: XCircle, chip: 'bg-red-50 dark:bg-red-900/20', iconClass: 'text-red-500', bar: 'from-rose-500 to-red-500' },
                        ]"
                        :key="card.label"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div :class="['absolute left-0 top-5 bottom-5 w-1 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300 bg-gradient-to-b', card.bar]" />
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ card.label }}</p>
                                <p class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ card.value }}</p>
                            </div>
                            <div :class="['p-3 rounded-2xl group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', card.chip]">
                                <component :is="card.icon" :class="['w-5 h-5', card.iconClass]" />
                            </div>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Empty state -->
                <div v-if="filteredRequests.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <Calendar class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">{{ stats.total === 0 ? 'No leave requests yet' : 'No matches found' }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ stats.total === 0 ? 'New requests will appear here.' : 'Try a different search or filter.' }}</p>
                    <button v-if="searchQuery || statusFilter !== 'ALL'" @click="searchQuery=''; statusFilter='ALL'"
                        class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
                </div>

                <!-- Leave Requests Table -->
                <div v-else class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay: 120ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Employee</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Leave Type</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Duration</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Reason</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                <tr v-for="req in filteredRequests" :key="req.id" class="hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center font-black text-xs text-white shadow-lg uppercase flex-shrink-0">
                                                {{ getInitials(req.user_name) }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ req.user_name }}</p>
                                                <p class="text-xs text-slate-400">{{ req.user_role }}</p>
                                            </div>
                                        </div>
                                     </td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-700 dark:text-slate-300 capitalize">{{ req.leave_type }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                        {{ formatDate(req.start_date) }} – {{ formatDate(req.end_date) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-500 max-w-xs truncate">{{ req.reason || 'No reason provided' }}</td>
                                    <td class="px-6 py-4">
                                        <span :class="[getStatusBadgeClass(req.status), 'px-3 py-1 rounded-full text-[10px] font-black uppercase ring-1 inline-flex items-center gap-1']">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ req.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div v-if="req.status === 'pending'" class="flex justify-end gap-2">
                                            <button @click="approveRequest(req.id)" class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 hover:bg-emerald-600 hover:text-white hover:scale-105 transition-all" title="Approve">
                                                <CheckCircle class="h-5 w-5" />
                                            </button>
                                            <button @click="openRejectModal(req)" class="p-2 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 hover:bg-red-600 hover:text-white hover:scale-105 transition-all" title="Reject">
                                                <XCircle class="h-5 w-5" />
                                            </button>
                                        </div>
                                        <span v-else class="text-xs text-slate-400 italic">No action</span>
                                    </td>
                                 </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rejection Modal -->
        <Transition name="modal">
            <div v-if="isRejectModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeRejectModal">
                <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 flex justify-between items-center">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                        <h2 class="relative text-lg font-black tracking-tight text-white uppercase">Reject Leave Request</h2>
                        <button @click="closeRejectModal" class="relative h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition"><X class="h-4 w-4" /></button>
                    </div>
                    <div class="p-6 space-y-4">
                        <p class="text-sm text-slate-600 dark:text-slate-300">Rejecting request for <strong>{{ selectedRequest?.user_name }}</strong></p>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Reason for Rejection</label>
                            <textarea v-model="rejectReason" rows="3" class="w-full px-3 py-2.5 rounded-2xl bg-slate-100 dark:bg-zinc-800 border-none text-sm outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Explain why this request is rejected..."></textarea>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button @click="closeRejectModal" class="flex-1 py-2.5 text-slate-500 dark:text-slate-400 font-bold text-sm hover:text-slate-700 transition">Cancel</button>
                            <button @click="rejectRequest" class="flex-1 py-2.5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white rounded-2xl font-extrabold text-sm shadow-lg shadow-indigo-500/20 hover:scale-[1.02] active:scale-95 transition">Confirm Rejection</button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from .modal-panel { opacity: 0; transform: translateY(16px) scale(0.97); }
.modal-leave-to .modal-panel { opacity: 0; transform: translateY(8px) scale(0.98); }
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}
</style>
