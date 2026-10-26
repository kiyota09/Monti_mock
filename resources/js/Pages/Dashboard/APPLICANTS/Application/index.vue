<script setup lang="ts">
// ============================================
// FILE LOCATION: resources/js/Pages/Dashboard/APPLICANTS/Applications/index.vue
// PURPOSE: List of applicant's job applications with confirmation modals
// ============================================

import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { MapPin, Search } from 'lucide-vue-next';

// ============================================
// PROPS
// ============================================
const props = defineProps<{
    applications: Array<{
        id: number;
        job_posting_id: number;
        job_title: string;
        department: string;
        employment_type: string;
        work_location: string;
        status: string;
        application_date: string;
        created_at: string;
        updated_at: string;
        progress?: string[];
        reapply_blocked?: boolean;
        can_reapply_at?: string | null;
        failed_at?: string | null;
        failed_stage?: string | null;
    }>;
}>();

// ============================================
// STATE
// ============================================
const filter = ref('All');
const searchQuery = ref('');

// ============================================
// MODAL STATE
// ============================================
const showModal = ref(false);
const modalType = ref(''); // 'withdraw', 'success', 'error', 'warning'
const modalTitle = ref('');
const modalMessage = ref('');
const modalConfirmText = ref('Confirm');
const modalCancelText = ref('Cancel');
const modalOnConfirm = ref<(() => void) | null>(null);
const selectedApp = ref<any>(null);
const isProcessing = ref(false);

// ============================================
// FILTERS
// ============================================
const filters = [
    'All',
    'Submitted',
    'Screening',
    'Shortlisted',
    'Interview Scheduled',
    'Initial Interview',
    'Final Interview',
    'Interviewed',
    'Selected',
    'Job Offer',
    'Offer Accepted',
    'Pre-employment',
    'Onboard',
    'Onboarding',
    'Hired',
    'Rejected',
    'Withdrawn',
];

// ============================================
// COMPUTED - Filtered Applications
// ============================================
const filteredApplications = computed(() => {
    let apps = props.applications || [];

    if (filter.value !== 'All') {
        apps = apps.filter(app => app.status === filter.value);
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        apps = apps.filter(app =>
            app.job_title?.toLowerCase().includes(query) ||
            app.department?.toLowerCase().includes(query)
        );
    }

    return apps;
});

// ============================================
// METRICS
// ============================================
const metrics = computed(() => {
    const total = props.applications?.length || 0;
    const inProgress = props.applications?.filter((a: any) => a.status === 'Submitted' || a.status === 'Screening').length || 0;
    const shortlisted = props.applications?.filter((a: any) => a.status === 'Shortlisted').length || 0;
    const hired = props.applications?.filter((a: any) => a.status === 'Hired').length || 0;

    return [
        { label: 'Total Applications', value: total, tone: 'navy', subtext: 'All applications' },
        { label: 'In Progress', value: inProgress, tone: 'gold', subtext: 'Submitted & screening' },
        { label: 'Shortlisted', value: shortlisted, tone: 'info', subtext: 'Shortlisted candidates' },
        { label: 'Hired', value: hired, tone: 'success', subtext: 'Successful hires' },
    ];
});

// ============================================
// MODAL FUNCTIONS
// ============================================

function showModalDialog(options: {
    type: 'withdraw' | 'success' | 'error' | 'warning' | 'confirm';
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    onConfirm?: () => void;
}) {
    modalType.value = options.type;
    modalTitle.value = options.title;
    modalMessage.value = options.message;
    modalConfirmText.value = options.confirmText || 'Confirm';
    modalCancelText.value = options.cancelText || 'Cancel';
    modalOnConfirm.value = options.onConfirm || null;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    modalOnConfirm.value = null;
    isProcessing.value = false;
}

function handleConfirm() {
    if (modalOnConfirm.value) {
        modalOnConfirm.value();
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function getStatusColor(status: string): string {
    const colors: Record<string, string> = {
        'Submitted': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'Screening': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Shortlisted': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
        'Interview Scheduled': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Initial Interview': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Final Interview': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Interviewed': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Selected': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Job Offer': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'Offer Accepted': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Pre-employment': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Onboard': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Onboarding': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Hired': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Rejected': 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        'Withdrawn': 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    };
    return colors[status] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}

function getStatusIcon(status: string): string {
    const icons: Record<string, string> = {
        'Submitted': '📋',
        'Screening': '🔍',
        'Shortlisted': '⭐',
        'Interview Scheduled': '📅',
        'Initial Interview': '📅',
        'Final Interview': '🎯',
        'Interviewed': '🎤',
        'Selected': '✅',
        'Job Offer': '💼',
        'Offer Accepted': '🤝',
        'Pre-employment': '📝',
        'Onboard': '🚀',
        'Onboarding': '🚀',
        'Hired': '🎉',
        'Rejected': '❌',
        'Withdrawn': '🚫',
    };
    return icons[status] || '📋';
}

function getProgressSteps(status: string): string[] {
    const steps = ['Application Submitted', 'Screening', 'Shortlisted', 'Interview', 'Hired'];
    const statusMap: Record<string, number> = {
        'Submitted': 0,
        'Screening': 1,
        'Shortlisted': 2,
        'Interview Scheduled': 3,
        'Initial Interview': 3,
        'Final Interview': 3,
        'Interviewed': 3,
        'Selected': 3,
        'Job Offer': 4,
        'Offer Accepted': 4,
        'Pre-employment': 4,
        'Onboard': 4,
        'Onboarding': 4,
        'Hired': 4,
        'Rejected': -1,
        'Withdrawn': -1,
    };

    const currentIndex = statusMap[status] ?? 0;

    const isTerminal = status === 'Hired';

    return steps.map((step, index) => {
        if (index < currentIndex && status !== 'Rejected' && status !== 'Withdrawn') {
            return `✓ ${step}`;
        } else if (index === currentIndex && status !== 'Rejected' && status !== 'Withdrawn') {
            return isTerminal && index === steps.length - 1 ? `✓ ${step}` : `● ${step}`;
        } else if (status === 'Rejected') {
            return index === 0 ? '✓ Submitted' : '✗ ' + step;
        } else if (status === 'Withdrawn') {
            return index === 0 ? '✓ Submitted' : '✗ ' + step;
        }
        return `○ ${step}`;
    });
}

function getRelativeTime(dateString: string): string {
    if (!dateString) return 'Recently';

    try {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();

        if (diffMs < 0) return 'Just now';

        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        const diffMonths = Math.floor(diffDays / 30);
        const diffYears = Math.floor(diffDays / 365);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return diffMins + 'm ago';
        if (diffHours < 24) return diffHours + 'h ago';
        if (diffDays < 7) return diffDays + 'd ago';
        if (diffDays < 30) return Math.floor(diffDays / 7) + 'w ago';
        if (diffMonths < 12) return diffMonths + 'mo ago';
        return diffYears + 'y ago';
    } catch (e) {
        return 'Recently';
    }
}

function viewApplicationDetails(app: any) {
    router.visit(route('applicant.applications.show', app.id));
}

// 6-month re-application cooldown after an interview/training failure.
function formatReapplyDate(dateString?: string | null): string {
    if (!dateString) return '';
    try {
        return new Date(dateString + 'T00:00:00').toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return dateString;
    }
}

// ============================================
// ✅ WITHDRAW WITH CONFIRMATION MODAL
// ============================================
function withdrawApplication(app: any) {
    selectedApp.value = app;

    showModalDialog({
        type: 'withdraw',
        title: 'Cancel Application?',
        message: `Are you sure you want to cancel your application for "<strong>${app.job_title}</strong>"? This action cannot be undone.`,
        confirmText: 'Yes, Cancel',
        cancelText: 'Cancel',
        onConfirm: confirmWithdraw
    });
}

function confirmWithdraw() {
    if (!selectedApp.value) return;

    isProcessing.value = true;

    router.post(route('applicant.applications.withdraw', selectedApp.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            isProcessing.value = false;
            closeModal();

            // Show success modal
            showModalDialog({
                type: 'success',
                title: 'Application Cancelled',
                message: `Your application for "<strong>${selectedApp.value.job_title}</strong>" has been cancelled successfully. You can now re-apply if you wish.`,
                confirmText: 'OK',
                cancelText: '',
                onConfirm: closeModal
            });

            // Update the application status in the list
            const appIndex = props.applications.findIndex(a => a.id === selectedApp.value.id);
            if (appIndex !== -1) {
                props.applications[appIndex].status = 'Withdrawn';
            }
        },
        onError: (errors) => {
            isProcessing.value = false;
            const errorMsg = Object.values(errors)[0] || 'Failed to cancel application. Please try again.';

            closeModal();
            showModalDialog({
                type: 'error',
                title: 'Cancellation Failed',
                message: errorMsg,
                confirmText: 'OK',
                cancelText: '',
                onConfirm: closeModal
            });
        }
    });
}
</script>

<template>
    <Head title="My Applications - Monti ERP" />

    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    My <span class="text-blue-600">Applications</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Track the progress of your job applications.</p>
            </div>
            <Link
                :href="route('applicant.jobs.index')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95"
            >
                <Search class="w-4 h-4" /> Browse Jobs
            </Link>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div
                v-for="metric in metrics"
                :key="metric.label"
                class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden p-4"
            >
                <div class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">{{ metric.label }}</div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ metric.value }}</div>
                <div v-if="metric.subtext" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ metric.subtext }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search applications by job title or department..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <select
                        v-model="filter"
                        class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"
                    >
                        <option v-for="f in filters" :key="f" :value="f">{{ f === 'All' ? 'All statuses' : f }}</option>
                    </select>
                </div>
            </div>

            <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Showing <span class="font-bold text-slate-900 dark:text-white">{{ filteredApplications.length }}</span>
                    application{{ filteredApplications.length !== 1 ? 's' : '' }}
                </p>
                <p
                    v-if="filter !== 'All' || searchQuery"
                    class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                >
                    Filters applied
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700">
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Position</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Applied</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Progress</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr
                            v-for="app in filteredApplications"
                            :key="app.id"
                            class="hover:bg-slate-50/80 dark:hover:bg-slate-800/60"
                        >
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ app.job_title || 'Position' }}</p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 flex items-center gap-1">
                                    {{ app.department || 'N/A' }} • {{ app.employment_type || 'N/A' }} •
                                    <span class="inline-flex items-center gap-1"><MapPin class="w-3 h-3" /> {{ app.work_location || 'On-site' }}</span>
                                </p>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', getStatusColor(app.status)]">
                                    {{ getStatusIcon(app.status) }} {{ app.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                {{ getRelativeTime(app.application_date || app.created_at) }}
                            </td>
                            <td class="px-6 py-4 min-w-[160px]">
                                <div class="flex items-center gap-1">
                                    <div
                                        v-for="(step, index) in getProgressSteps(app.status)"
                                        :key="index"
                                        class="h-1.5 flex-1 rounded-full"
                                        :class="[
                                            step.includes('✓') ? 'bg-emerald-500' :
                                            step.includes('●') ? 'bg-blue-500 animate-pulse' :
                                            'bg-slate-200 dark:bg-slate-700'
                                        ]"
                                    ></div>
                                </div>
                                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                                    <span v-if="app.status === 'Rejected'" class="text-rose-500 font-bold">Not Selected</span>
                                    <span v-else-if="app.status === 'Withdrawn'" class="text-slate-500 font-bold">Withdrawn</span>
                                    <span v-else class="text-blue-600 dark:text-blue-400 font-bold">{{ app.status || 'In Progress' }}</span>
                                    <span v-if="app.reapply_blocked" class="block mt-0.5 text-orange-600 dark:text-orange-400 font-semibold">
                                        Please wait 6 months before applying to this job again — re-apply after {{ formatReapplyDate(app.can_reapply_at) }}.
                                    </span>
                                </p>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <button
                                    @click="viewApplicationDetails(app)"
                                    class="px-3 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg"
                                >
                                    View Details
                                </button>
                                <button
                                    v-if="['Submitted', 'Screening', 'Shortlisted', 'Interview Scheduled', 'Initial Interview', 'Final Interview', 'Interviewed'].includes(app.status)"
                                    @click="withdrawApplication(app)"
                                    class="px-3 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg"
                                >
                                    Cancel
                                </button>
                                <Link
                                    v-if="app.status === 'Rejected' || app.status === 'Withdrawn'"
                                    :href="route('applicant.jobs.index')"
                                    class="px-3 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg"
                                >
                                    Browse Jobs
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="filteredApplications.length === 0" class="py-12 text-center">
                    <p class="text-sm text-slate-400 dark:text-slate-500 font-bold">No Applications Found</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 mb-4">
                        {{ searchQuery || filter !== 'All' ? 'Try adjusting your search or filters.' : "You haven't applied to any jobs yet." }}
                    </p>
                    <Link
                        :href="route('applicant.jobs.index')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95"
                    >
                        <Search class="w-4 h-4" />Browse Jobs
                    </Link>
                </div>
            </div>
        </div>

        <div class="mt-2 text-xs text-slate-400 dark:text-slate-500">
            Showing {{ filteredApplications.length }} {{ filteredApplications.length === 1 ? 'application' : 'applications' }}
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ modalTitle }}</h2>
                    <button @click="closeModal" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">✕</button>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed" v-html="modalMessage"></p>
                <div v-if="isProcessing" class="mt-4 flex items-center justify-center">
                    <svg class="animate-spin h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="ml-3 text-sm text-slate-600 dark:text-slate-300">Processing...</span>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        v-if="modalCancelText"
                        @click="closeModal"
                        :disabled="isProcessing"
                        class="px-4 py-2.5 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-bold rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 transition active:scale-95 disabled:opacity-50"
                    >
                        {{ modalCancelText }}
                    </button>
                    <button
                        @click="handleConfirm"
                        :disabled="isProcessing"
                        class="px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition active:scale-95 disabled:opacity-50"
                    >
                        <span v-if="isProcessing">Processing...</span>
                        <span v-else>{{ modalConfirmText }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
