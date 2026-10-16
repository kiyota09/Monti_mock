<script setup>
import { ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Archive, XCircle, Search, Filter, Eye, Calendar, Mail, Phone,
    Briefcase, User, X, FileText, AlertTriangle, CheckCircle, Users
} from 'lucide-vue-next';

const props = defineProps({
    rejectedItems: {
        type: Array,
        default: () => []
    },
    permissions: {
        type: Object,
        default: () => ({})
    }
});

// Archive lives under the Applications page grant (routes enforce
// page.permission:application) — it has no write actions of its own.
const canEdit = computed(() => props.permissions?.application === 'edit');

// Toast notification (for flash messages)
const showToast = ref(false);
const toastMessage = ref('');
const triggerToast = (msg) => {
    toastMessage.value = msg;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 4000);
};

const page = usePage();
if (page.props.flash?.message) {
    triggerToast(page.props.flash.message);
}

// Filters
const searchQuery = ref('');
const stageFilter = ref('ALL');

const stages = ['ALL', 'Application', 'Interview', 'Training'];

const filteredItems = computed(() => {
    let list = props.rejectedItems;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(item =>
            item.name.toLowerCase().includes(q) ||
            item.email.toLowerCase().includes(q) ||
            item.position_applied.toLowerCase().includes(q)
        );
    }
    if (stageFilter.value !== 'ALL') {
        list = list.filter(item => item.stage === stageFilter.value);
    }
    return list;
});

// Stats
const stats = computed(() => ({
    total: props.rejectedItems.length,
    byStage: {
        Application: props.rejectedItems.filter(i => i.stage === 'Application').length,
        Interview: props.rejectedItems.filter(i => i.stage === 'Interview').length,
        Training: props.rejectedItems.filter(i => i.stage === 'Training').length
    }
}));

// Modal state
const isViewModalOpen = ref(false);
const selectedItem = ref(null);

// Helper functions
const getInitials = (name) => name ? name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?';
const formatDate = (date) => date ? new Date(date).toLocaleDateString() : 'N/A';
const formatDateTime = (date) => date ? new Date(date).toLocaleString() : 'N/A';

const getStageBadgeClass = (stage) => {
    switch (stage) {
        case 'Application': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'Interview': return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
        case 'Training': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        default: return 'bg-slate-100 text-slate-700';
    }
};

const getStageIcon = (stage) => {
    switch (stage) {
        case 'Application': return FileText;
        case 'Interview': return Users;
        case 'Training': return AlertTriangle;
        default: return Archive;
    }
};

// Modal open
const openViewModal = (item) => {
    selectedItem.value = item;
    isViewModalOpen.value = true;
};
</script>

<template>
    <Head title="Rejected Applications" />

    <AuthenticatedLayout>
        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="showToast"
                class="fixed top-6 right-6 z-[100] flex items-center gap-3 px-6 py-4 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-2xl shadow-2xl border border-white/10">
                <CheckCircle class="h-5 w-5 text-emerald-400 dark:text-emerald-600" />
                <p class="text-sm font-bold uppercase tracking-tight">{{ toastMessage }}</p>
            </div>
        </Transition>

        <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 sm:space-y-8">
            <!-- Header -->
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Rejected <span class="text-red-600">Archive</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">
                    Review applications that were rejected at various stages.
                </p>
                <!-- Permission indicator -->
                <div v-if="!canEdit && permissions.application === 'view'" class="mt-2 text-xs text-amber-600 bg-amber-50 inline-block px-2 py-0.5 rounded-full">
                    View only access
                </div>
                <div v-else-if="canEdit" class="mt-2 text-xs text-emerald-600 bg-emerald-50 inline-block px-2 py-0.5 rounded-full">
                    Full access
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 sm:gap-6">
                <div
                    class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between mb-2">
                        <Archive class="h-6 w-6 text-slate-500" />
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.total }}</span>
                    </div>
                    <p class="text-sm font-medium text-slate-500">Total Rejected</p>
                </div>
                <div
                    class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between mb-2">
                        <FileText class="h-6 w-6 text-red-500" />
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.byStage.Application
                        }}</span>
                    </div>
                    <p class="text-sm font-medium text-slate-500">Application Stage</p>
                </div>
                <div
                    class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between mb-2">
                        <Users class="h-6 w-6 text-orange-500" />
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.byStage.Interview
                        }}</span>
                    </div>
                    <p class="text-sm font-medium text-slate-500">Interview Stage</p>
                </div>
                <div
                    class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between mb-2">
                        <AlertTriangle class="h-6 w-6 text-amber-500" />
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.byStage.Training
                        }}</span>
                    </div>
                    <p class="text-sm font-medium text-slate-500">Training Stage</p>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                <div class="relative w-full sm:w-80">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                    <input v-model="searchQuery" type="text" placeholder="Search by name, email, or position..."
                        class="w-full pl-11 pr-4 py-3 rounded-2xl bg-white dark:bg-slate-800 border-none ring-1 ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-red-500 text-sm" />
                </div>
                <div class="flex gap-2 overflow-x-auto pb-1 w-full sm:w-auto no-scrollbar">
                    <button v-for="stage in stages" :key="stage" @click="stageFilter = stage" :class="[
                        'px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all whitespace-nowrap',
                        stageFilter === stage
                            ? 'bg-red-600 text-white shadow-md'
                            : 'bg-white dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-700 hover:border-red-300'
                    ]">
                        {{ stage === 'ALL' ? 'All Stages' : stage }}
                    </button>
                </div>
            </div>

            <!-- Rejected Items Table -->
            <div
                class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/60 dark:bg-slate-900/40">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Applicant</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Position</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Stage</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Rejected On</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr v-if="filteredItems.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">
                                    No rejected records found.
                                </td>
                            </tr>
                            <tr v-for="item in filteredItems" :key="item.id"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center font-bold text-xs text-slate-600 dark:text-slate-300">
                                            {{ getInitials(item.name) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ item.name }}
                                            </p>
                                            <p class="text-xs text-slate-400">{{ item.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                    {{ item.position_applied }}
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase', getStageBadgeClass(item.stage)]">
                                        <component :is="getStageIcon(item.stage)" class="h-3 w-3" />
                                        {{ item.stage }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500">
                                    {{ formatDate(item.rejected_at) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="openViewModal(item)"
                                        class="p-2 text-slate-400 hover:text-blue-600 rounded-lg transition-all"
                                        title="View Details">
                                        <Eye class="h-5 w-5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- View Details Modal -->
        <div v-if="isViewModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
            @click.self="isViewModalOpen = false">
            <div
                class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                <div
                    class="px-8 py-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center sticky top-0 bg-white dark:bg-slate-800">
                    <h2 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tight">Rejected
                        Applicant Details</h2>
                    <button @click="isViewModalOpen = false"
                        class="p-2 hover:bg-slate-100 rounded-full transition-colors">
                        <X class="h-5 w-5 text-slate-400" />
                    </button>
                </div>
                <div class="overflow-y-auto p-8 space-y-6">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-xs font-black text-red-600 uppercase tracking-widest mb-3">Personal
                                Information</h3>
                            <div class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">Full Name</p>
                                    <p class="text-sm font-bold">{{ selectedItem?.name }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400">Email</p>
                                        <p class="text-xs">{{ selectedItem?.email }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400">Phone</p>
                                        <p class="text-xs">{{ selectedItem?.phone_number || 'N/A' }}</p>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">Address</p>
                                    <p class="text-xs">{{ selectedItem?.street_address }}, {{ selectedItem?.city }}, {{
                                        selectedItem?.state_province }} {{ selectedItem?.postal_zip_code }}</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xs font-black text-amber-600 uppercase tracking-widest mb-3">Application
                                Details</h3>
                            <div class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">Position Applied</p>
                                    <p class="text-sm font-bold">{{ selectedItem?.position_applied }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">Stage Rejected</p><span
                                        :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase', getStageBadgeClass(selectedItem?.stage)]">
                                        <component :is="getStageIcon(selectedItem?.stage)" class="h-3 w-3" /> {{
                                            selectedItem?.stage }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">Rejected On</p>
                                    <p class="text-sm">{{ formatDateTime(selectedItem?.rejected_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rejection Reason -->
                    <div>
                        <h3 class="text-xs font-black text-red-600 uppercase tracking-widest mb-3">Rejection Reason</h3>
                        <div
                            class="bg-red-50 dark:bg-red-900/10 p-6 rounded-2xl border border-red-200 dark:border-red-800/30">
                            <p class="text-sm text-red-800 dark:text-red-300">{{ selectedItem?.rejection_reason || 'No reason provided.' }}</p>
                        </div>
                    </div>

                    <!-- Additional details if available (optional) -->
                    <div v-if="selectedItem?.date_of_birth" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-3">Birth &
                                Personal</h3>
                            <div class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">Date of Birth</p>
                                    <p class="text-sm">{{ formatDate(selectedItem.date_of_birth) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">Place of Birth</p>
                                    <p class="text-sm">{{ selectedItem.place_of_birth || 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">Civil Status</p>
                                    <p class="text-sm">{{ selectedItem.civil_status || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-3">Government IDs
                            </h3>
                            <div class="bg-slate-50 dark:bg-slate-900/40 p-6 rounded-2xl space-y-4">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">SSS Number</p>
                                    <p class="text-sm">{{ selectedItem.sss_number || 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">PhilHealth Number</p>
                                    <p class="text-sm">{{ selectedItem.philhealth_number || 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">Pag-IBIG Number</p>
                                    <p class="text-sm">{{ selectedItem.pagibig_number || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-8 py-6 border-t border-slate-100 dark:border-slate-700 flex justify-end">
                    <button @click="isViewModalOpen = false"
                        class="px-8 py-3 bg-slate-900 text-white rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-red-600 transition-all">Close</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}

.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>