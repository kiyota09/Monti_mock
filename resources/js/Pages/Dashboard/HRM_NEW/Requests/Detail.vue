<script setup lang="ts">
import { confirmModal } from '@/lib/feedback';
// Page 10: Manpower request detail (assign picker + progress + activity)
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { History, Pencil } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    request: {
        id: number;
        code: string;
        department: string;
        department_module?: string | null;
        position_skill: string;
        needed: number;
        filled: number;
        start_date: string | null;
        end_date: string | null;
        shift: string | null;
        notes: string | null;
        agency_id: number;
        agency: string | null;
        agency_code: string | null;
        agency_status: string | null;
        status: string;
        stored_status: string;
        cancel_reason: string | null;
        assigned: Array<{
            assignment_id: number; worker_id: number; worker_code: string | null;
            worker_name: string | null; position_skill: string | null;
            worker_status: string | null; daily_rate: string | number | null;
            phone: string | null; gender: string | null;
            clearance_expiry: string | null; clearance_expired: boolean;
            resume_url: string | null; resume_name: string | null;
            assigned_at: string | null; assigned_by: string | null;
        }>;
        available_workers: Array<{ id: number; code: string; name: string; email?: string | null; position_skill: string | null; worker_status?: string | null; daily_rate?: string | number | null; phone?: string | null; gender?: string | null; clearance_expiry?: string | null; resume_url?: string | null; resume_name?: string | null; emergency_name?: string | null; emergency_relationship?: string | null; emergency_phone?: string | null; availability?: string | null; employment_arrangement?: string | null; proposed_department?: string | null; years_experience?: string | number | null }>;
        proposals: Array<{
            id: number; worker_id: number; worker_code: string | null; worker_name: string | null;
            position_skill: string | null; email?: string | null; worker_status: string | null; daily_rate: string | number | null;
            phone: string | null; gender: string | null; clearance_expiry: string | null;
            clearance_expired: boolean; resume_url: string | null; resume_name: string | null;
            emergency_name?: string | null; emergency_relationship?: string | null; emergency_phone?: string | null;
            availability?: string | null; employment_arrangement?: string | null; proposed_department?: string | null;
            years_experience?: string | number | null;
            status: string; proposed_by: string | null;
            proposed_at: string | null; review_reason: string | null; reviewed_by: string | null;
        }>;
        attendance_summary: Array<{
            worker_id: number; worker_code: string | null; worker_name: string | null;
            records: number; present: number; absent: number; late: number; hours: number; overtime_hours: number;
        }>;
        activities: Array<{ id: number; date: string; action: string; details: string | null; actor: string; reason: string | null }>;
    };
    hiring_managers?: Array<{ id: number; name: string; position: string }>;
    default_hiring_manager_id?: number | null;
    permissions?: Record<string, string>;
}>(), {
    hiring_managers: () => [],
    default_hiring_manager_id: null,
});

const page = usePage();
const flash = computed(() => (page.props as any).flash || {});
const canEdit = computed(() => (props.permissions?.requests || 'edit') === 'edit');

const confirmAction = ref<'cancel' | null>(null);
const showActivityModal = ref(false);
const reason = ref('');
const reasonError = ref('');

const assignForm = useForm({ worker_id: '' as string | number });
const selectedManagerId = ref<string | number>(props.default_hiring_manager_id || '');
const hirePreviewError = ref('');

function statusBadge(s: string): string {
    const map: Record<string, string> = {
        Draft: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
        Open: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        'Partially filled': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        Filled: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
        Closed: 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600',
    };
    return map[s] || 'bg-slate-100 text-slate-600 border-slate-200';
}

function progressPct(): number {
    if (!props.request.needed) return 0;
    return Math.min(100, Math.round((props.request.filled / props.request.needed) * 100));
}

function submitCancel() {
    if (!reason.value.trim()) {
        reasonError.value = 'A reason is required to cancel.';
        return;
    }
    router.post(route('hrm.manpower-requests.cancel', props.request.id), { reason: reason.value }, {
        preserveScroll: true,
        onSuccess: () => { confirmAction.value = null; reason.value = ''; },
    });
}

async function closeRequest() {
    if (!await confirmModal('Close this filled request?')) return;
    router.post(route('hrm.manpower-requests.close', props.request.id), {}, { preserveScroll: true });
}

function assignWorker() {
    // Same process as proposal acceptance: confirmation first, then worker-account preview.
    if (!assignForm.worker_id || accepting.value) return;
    const w = props.request.available_workers.find((x) => String(x.id) === String(assignForm.worker_id)) || null;
    if (!w) return;
    selectedAssigned.value = null;
    hirePreview.value = null;
    assignTarget.value = { ...w, worker_id: w.id, worker_name: w.name, worker_code: w.code };
}
function closeAssignConfirm() {
    if (accepting.value) return;
    assignTarget.value = null;
}
function confirmAssignWorker() {
    if (!assignTarget.value || accepting.value) return;
    // Step 1 (direct): open preview WITHOUT posting — nothing assigned yet.
    hirePreview.value = { ...assignTarget.value };
    hireMode.value = 'direct';
    assignTarget.value = null;
    previewEmail.value = '';
    suggestPreviewEmail(true);
    previewStartDate.value = defaultDeploymentDate(hirePreview.value.availability);
    previewStartDateError.value = '';
    selectedManagerId.value = props.default_hiring_manager_id || '';
    resetTraining();
}

const releaseTarget = ref<any>(null);
const releaseReason = ref('');
const releaseError = ref('');

function releaseWorker(a: any) {
    if (!a) return;
    selectedAssigned.value = null;
    releaseTarget.value = a;
    releaseReason.value = '';
    releaseError.value = '';
}
function closeReleaseWorker() {
    releaseTarget.value = null;
    releaseReason.value = '';
    releaseError.value = '';
}
function submitReleaseWorker() {
    if (!releaseTarget.value) return;
    if (!releaseReason.value.trim()) {
        releaseError.value = 'A reason is required to release.';
        return;
    }
    router.post(route('hrm.manpower-requests.release', { manpowerRequest: props.request.id, assignment: releaseTarget.value.assignment_id }), { reason: releaseReason.value }, {
        preserveScroll: true,
        onSuccess: () => { closeReleaseWorker(); },
    });
}

const rejectProposalId = ref<number | null>(null);
const rejectProposalReason = ref('');
const rejectProposalError = ref('');

// Worker-info modal for a clicked proposal.
const selectedProposal = ref<any>(null);

function openProposalWorker(p: any) {
    selectedProposal.value = p;
}
function closeProposalWorker() {
    selectedProposal.value = null;
}

function proposalDocumentBadge(status: string): string {
    const colors: Record<string, string> = {
        Verified: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800',
        Pending: 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800',
        Rejected: 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800',
        Expired: 'bg-slate-200 text-slate-600 border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600',
    };
    return colors[status] || 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600';
}

// Worker-info modal for a clicked assigned worker.
const selectedAssigned = ref<any>(null);

function openAssignedWorker(a: any) {
    selectedAssigned.value = a;
}
function closeAssignedWorker() {
    selectedAssigned.value = null;
}

// Live preview of the worker picked in the assign dropdown.
const selectedAvailable = computed(() =>
    props.request.available_workers.find((w) => String(w.id) === String(assignForm.worker_id)) || null
);
const hasAssignableAvailableWorkers = computed(() =>
    props.request.available_workers.some((worker) => worker.worker_status === 'Available')
);

function acceptProposal(id: number) {
    const target = props.request.proposals.find((p) => p.id === id) || null;
    openAcceptProposal(target && target.id === id ? target : { id } as any);
}

// --- Accept / assign flow: confirm -> preview -> finish creates assignment ---
// Proposals stay Proposed while the preview is open; direct assigns create
// nothing until Finish. Only Finish POSTs (proposals.accept or assign).
const acceptTarget = ref<any>(null);
const assignTarget = ref<any>(null);
const accepting = ref(false);
const hirePreview = ref<any>(null);
const hireMode = ref<'proposal' | 'direct' | null>(null);
// Reference-only company email (same format as Onboarding CREATE EMPLOYEE).
// Editable + Generate button. Never posted — preview only.
const previewEmail = ref('');
// Deployment start date saved with the completed assignment.
const previewStartDate = ref('');
const previewStartDateError = ref('');
const trainingRequired = ref(false);
const trainingDateError = ref('');
const managerError = ref('');

const todayDate = (() => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
})();

function defaultDeploymentDate(workerAvailability?: string | null): string {
    return [props.request.start_date, workerAvailability, todayDate]
        .filter((date): date is string => Boolean(date))
        .sort()
        .at(-1) || todayDate;
}

function addFourWeeks(dateValue: string): string {
    if (!dateValue) return '';
    const [year, month, day] = dateValue.split('-').map(Number);
    const target = new Date(year, month - 1, day);
    target.setDate(target.getDate() + 28);
    return `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}-${String(target.getDate()).padStart(2, '0')}`;
}

const trainingEndMax = computed(() => addFourWeeks(previewStartDate.value));
const trainingEndDate = computed(() => trainingRequired.value ? trainingEndMax.value : '');

function updateDeploymentStart() {
    previewStartDateError.value = '';
    trainingDateError.value = '';
}

function resetTraining() {
    trainingRequired.value = false;
    trainingDateError.value = '';
}

function onTrainingRequiredChange() {
    trainingDateError.value = '';
    managerError.value = '';
}

function formatYearsExperience(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    const years = Number(value);
    return Number.isFinite(years) ? String(Math.trunc(years)) : String(value);
}

function suggestPreviewEmail(force = false) {
    if (!force && previewEmail.value.trim()) return;
    const raw = hirePreview.value?.worker_name || '';
    const parts = String(raw).toLowerCase().split(/[^a-z0-9]+/).filter(Boolean);
    if (!parts.length) return;
    const local = (parts.length > 1 ? `${parts[0]}.${parts[parts.length - 1]}` : parts[0]).slice(0, 64);
    previewEmail.value = `${local}@monti.com`;
}

function openAcceptProposal(p: any) {
    if (!p) return;
    selectedProposal.value = null;
    hirePreview.value = null;
    hireMode.value = null;
    assignTarget.value = null;
    acceptTarget.value = p;
}
function closeAcceptConfirm() {
    if (accepting.value) return;
    acceptTarget.value = null;
}
function confirmAcceptProposal() {
    if (!acceptTarget.value || accepting.value) return;
    // Step 1 (proposal): open the worker-account preview WITHOUT posting —
    // status stays Proposed while HR reviews.
    hirePreview.value = { ...acceptTarget.value };
    hireMode.value = 'proposal';
    acceptTarget.value = null;
    previewEmail.value = '';
    suggestPreviewEmail(true);
    previewStartDate.value = defaultDeploymentDate(hirePreview.value.availability);
    previewStartDateError.value = '';
    selectedManagerId.value = props.default_hiring_manager_id || '';
    resetTraining();
}
function closeHirePreview() {
    if (accepting.value) return;
    // Cancel: no assignment; proposal stays Proposed / direct assign does nothing.
    hirePreview.value = null;
    hireMode.value = null;
    previewEmail.value = '';
    previewStartDate.value = '';
    hirePreviewError.value = '';
    resetTraining();
}
function finishHirePreview() {
    if (!hirePreview.value || accepting.value) return;
    if (!hirePreview.value.email) {
        hirePreviewError.value = 'Add a valid personal email to this worker profile before assigning them so the welcome email can be sent.';
        return;
    }
    hirePreviewError.value = '';
    if (!previewStartDate.value || previewStartDate.value < todayDate) {
        previewStartDateError.value = 'Choose today or a future deployment date.';
        return;
    }
    previewStartDateError.value = '';
    if (trainingRequired.value && !selectedManagerId.value) {
        managerError.value = 'Choose a reporting manager to supervise this training.';
        return;
    }
    managerError.value = '';
    if (trainingRequired.value && (!trainingEndDate.value || trainingEndDate.value < previewStartDate.value || trainingEndDate.value > trainingEndMax.value)) {
        trainingDateError.value = 'Choose a training end date between the deployment date and four weeks later.';
        return;
    }
    trainingDateError.value = '';
    // Finish: send real preview data — backend assigns (+ Accepted for proposals) and emails worker.
    accepting.value = true;
    const payload = {
        company_email: previewEmail.value || undefined,
        start_date: previewStartDate.value || undefined,
        manager_id: selectedManagerId.value || null,
        training_required: trainingRequired.value,
        training_end_date: trainingRequired.value ? trainingEndDate.value : null,
    } as any;
    const done = () => {
        hirePreview.value = null;
        hireMode.value = null;
        previewEmail.value = '';
        previewStartDate.value = '';
        previewStartDateError.value = '';
        hirePreviewError.value = '';
        resetTraining();
        assignForm.reset();
    };
    if (hireMode.value === 'direct') {
        router.post(route('hrm.manpower-requests.assign', props.request.id), {
            worker_id: hirePreview.value.worker_id || hirePreview.value.id,
            ...payload,
        }, {
            preserveScroll: true,
            onSuccess: done,
            onError: (errors) => { hirePreviewError.value = Object.values(errors || {}).join(' '); },
            onFinish: () => { accepting.value = false; },
        });
        return;
    }
    const proposalId = hirePreview.value.id;
    router.post(route('hrm.manpower-requests.proposals.accept', { manpowerRequest: props.request.id, proposal: proposalId }), payload, {
        preserveScroll: true,
        onSuccess: done,
        onError: (errors) => { hirePreviewError.value = Object.values(errors || {}).join(' '); },
        onFinish: () => { accepting.value = false; },
    });
}
function openRejectProposal(id: number) {
    selectedProposal.value = null;
    rejectProposalId.value = id;
    rejectProposalReason.value = '';
    rejectProposalError.value = '';
}
function submitRejectProposal() {
    if (!rejectProposalReason.value.trim()) {
        rejectProposalError.value = 'A reason is required.';
        return;
    }
    router.post(route('hrm.manpower-requests.proposals.reject', { manpowerRequest: props.request.id, proposal: rejectProposalId.value }), { reason: rejectProposalReason.value }, {
        preserveScroll: true,
        onSuccess: () => { rejectProposalId.value = null; },
    });
}

function actionLabel(a: string): string {
    const map: Record<string, string> = {
        request_created: 'Created', request_submitted: 'Submitted', request_updated: 'Updated',
        request_cancelled: 'Cancelled', request_closed: 'Closed',
        worker_assigned: 'Worker assigned', worker_released: 'Worker released',
        proposal_rejected: 'Proposal rejected',
    };
    return map[a] || a;
}

const canAssign = computed(() =>
    canEdit.value
    && props.request.stored_status === 'Open'
    && props.request.agency_status === 'Active'
    && props.request.filled < props.request.needed
);
</script>

<template>
    <Head :title="request.department + ' manpower request - Monti ERP'" />
    <AuthenticatedLayout>
        <button type="button" title="Request history" aria-label="Show request history" @click="showActivityModal = true" class="fixed bottom-6 right-6 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500/30">
            <History class="h-5 w-5" />
            <span v-if="request.activities.length" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-slate-900 px-1 text-[10px] font-bold text-white dark:border-slate-800">
                {{ request.activities.length }}
            </span>
        </button>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('hrm.manpower-requests.index')" class="hover:text-blue-600">Requests</Link>
            <span>/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold">Details</span>
        </nav>

        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white leading-tight">
                    {{ request.department }} <span class="text-slate-400 font-bold">· {{ request.position_skill }}</span>
                </h1>
                <div class="flex items-center gap-2 mt-1">
                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', statusBadge(request.status)]">{{ request.status }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ request.agency || 'No agency' }}</span>
                </div>
            </div>
            <div v-if="canEdit" class="flex flex-wrap items-center gap-2 shrink-0">
                <Link
                    v-if="request.stored_status !== 'Closed' && request.stored_status !== 'Cancelled'"
                    :href="route('hrm.manpower-requests.edit', request.id)"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                >
                    <Pencil class="w-3.5 h-3.5" /> Edit
                </Link>
                <button
                    v-if="['Open', 'Partially filled', 'Filled'].includes(request.status)"
                    @click="confirmAction = 'cancel'; reason = ''; reasonError = ''"
                    class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition"
                >
                    Cancel
                </button>
                <button
                    v-if="request.status === 'Filled'"
                    @click="closeRequest"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition"
                >
                    Close
                </button>
            </div>
        </div>

        <div v-if="flash.success" class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-sm font-semibold text-rose-700 dark:text-rose-400">
            {{ flash.error }}
        </div>

        <div v-if="request.status === 'Cancelled' && request.cancel_reason" class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-400">
            <span class="font-black">Cancelled:</span> {{ request.cancel_reason }}
        </div>

        <div v-if="confirmAction === 'cancel' && canEdit" class="mb-4 p-4 rounded-xl bg-white dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-600">
            <p class="text-sm font-black text-slate-900 dark:text-white">Cancel this request?</p>
            <textarea
                v-model="reason"
                rows="2"
                placeholder="Reason (required)"
                class="mt-3 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200"
            ></textarea>
            <p v-if="reasonError" class="mt-1 text-xs text-rose-600">{{ reasonError }}</p>
            <div class="mt-3 flex items-center justify-end gap-2">
                <button @click="confirmAction = null" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Back</button>
                <button @click="submitCancel" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition">Confirm cancellation</button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/50">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white">Request</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full table-fixed border-collapse text-sm">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr><th scope="row" class="w-1/3 bg-slate-50/70 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">Department</th><td class="break-words px-4 py-2.5 font-semibold text-slate-700 dark:text-slate-200">{{ request.department }}</td></tr>
                            <tr><th scope="row" class="w-1/3 bg-slate-50/70 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">Position / skill</th><td class="break-words px-4 py-2.5 font-semibold text-slate-700 dark:text-slate-200">{{ request.position_skill }}</td></tr>
                            <tr><th scope="row" class="w-1/3 bg-slate-50/70 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">Headcount</th><td class="px-4 py-2.5 font-semibold text-slate-700 dark:text-slate-200">{{ request.needed }}</td></tr>
                            <tr><th scope="row" class="w-1/3 bg-slate-50/70 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">Start date</th><td class="px-4 py-2.5 font-semibold text-slate-700 dark:text-slate-200">{{ request.start_date || '—' }}</td></tr>
                            <tr><th scope="row" class="w-1/3 bg-slate-50/70 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">End date</th><td class="px-4 py-2.5 font-semibold text-slate-700 dark:text-slate-200">{{ request.end_date || '—' }}</td></tr>
                            <tr><th scope="row" class="w-1/3 bg-slate-50/70 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">Shift</th><td class="break-words px-4 py-2.5 font-semibold text-slate-700 dark:text-slate-200">{{ request.shift || '—' }}</td></tr>
                            <tr><th scope="row" class="w-1/3 bg-slate-50/70 px-4 py-2.5 text-left text-xs font-semibold text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">Agency</th><td class="break-words px-4 py-2.5 font-semibold text-slate-700 dark:text-slate-200">{{ request.agency || '—' }}</td></tr>
                            <tr v-if="request.notes"><th scope="row" class="w-1/3 bg-slate-50/70 px-4 py-2.5 text-left align-top text-xs font-semibold text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">Notes</th><td class="whitespace-pre-wrap break-words px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 [overflow-wrap:anywhere]">{{ request.notes }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-700">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">
                        <span>{{ request.filled }} of {{ request.needed }} filled</span>
                        <span>{{ progressPct() }}%</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full bg-blue-600 transition-all" :style="{ width: progressPct() + '%' }"></div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Assigned workers</h2>
                <form v-if="canAssign" @submit.prevent="assignWorker" class="mb-3">
                    <div class="flex items-center gap-2">
                        <select v-model="assignForm.worker_id" class="flex-1 min-w-0 px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200">
                            <option value="">Select a matching worker...</option>
                            <option v-for="w in request.available_workers" :key="w.id" :value="w.id">
                                {{ w.name }}{{ w.proposed_department ? ' · ' + w.proposed_department : '' }}{{ w.position_skill ? ' · ' + w.position_skill : '' }}{{ w.worker_status !== 'Available' ? ' · Pending HR approval' : '' }}
                            </option>
                        </select>
                        <Link v-if="selectedAvailable?.worker_status === 'Pending'" :href="route('hrm.workers.show', selectedAvailable.id) + '?request_id=' + request.id" class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition whitespace-nowrap">
                            Review &amp; approve
                        </Link>
                        <button v-else type="submit" :disabled="!assignForm.worker_id || assignForm.processing || selectedAvailable?.worker_status !== 'Available'" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-60 whitespace-nowrap">
                            Assign
                        </button>
                    </div>
                    <div v-if="selectedAvailable" class="mt-2 rounded-xl bg-blue-50/60 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/40 px-3 py-3 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-bold text-blue-700 dark:text-blue-300">
                                {{ selectedAvailable.name }}
                            </p>
                            <span v-if="selectedAvailable.worker_status" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-white dark:bg-slate-800 border border-blue-100 dark:border-blue-900/40 text-blue-700 dark:text-blue-300">{{ selectedAvailable.worker_status }}</span>
                        </div>
                        <dl class="mt-2 grid grid-cols-2 gap-2">
                            <div class="rounded-lg bg-white dark:bg-slate-900/60 border border-blue-100 dark:border-blue-900/40 px-2.5 py-2">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Position / skill</dt>
                                <dd class="mt-0.5 font-bold text-slate-700 dark:text-slate-200">{{ selectedAvailable.position_skill || '—' }}</dd>
                            </div>
                            <div class="rounded-lg bg-white dark:bg-slate-900/60 border border-blue-100 dark:border-blue-900/40 px-2.5 py-2">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Daily rate</dt>
                                <dd class="mt-0.5 font-bold text-slate-700 dark:text-slate-200">{{ selectedAvailable.daily_rate ? '₱' + selectedAvailable.daily_rate + '/day' : '—' }}</dd>
                            </div>
                            <div class="rounded-lg bg-white dark:bg-slate-900/60 border border-blue-100 dark:border-blue-900/40 px-2.5 py-2">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Phone</dt>
                                <dd class="mt-0.5 font-bold text-slate-700 dark:text-slate-200">{{ selectedAvailable.phone || '—' }}</dd>
                            </div>
                            <div class="rounded-lg bg-white dark:bg-slate-900/60 border border-blue-100 dark:border-blue-900/40 px-2.5 py-2">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Gender</dt>
                                <dd class="mt-0.5 font-bold text-slate-700 dark:text-slate-200">{{ selectedAvailable.gender || '—' }}</dd>
                            </div>
                            <div class="rounded-lg bg-white dark:bg-slate-900/60 border border-blue-100 dark:border-blue-900/40 px-2.5 py-2 col-span-2">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Resume</dt>
                                <dd class="mt-0.5 font-bold text-slate-700 dark:text-slate-200">
                                    <a v-if="selectedAvailable.resume_url" :href="selectedAvailable.resume_url" target="_blank" rel="noopener"
                                        class="text-blue-600 hover:text-blue-700 hover:underline transition" :title="selectedAvailable.resume_name || 'View resume'">
                                        {{ selectedAvailable.resume_name || 'View resume' }} →
                                    </a>
                                    <span v-else class="text-slate-400">No resume uploaded</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </form>
                <p v-if="canAssign && request.available_workers.length === 0" class="mb-3 text-[11px] leading-5 text-slate-400">No agency workers match this request’s department and position.</p>
                <p v-else-if="canAssign && !hasAssignableAvailableWorkers" class="mb-3 text-[11px] leading-5 text-amber-600">Select a Pending worker and choose Review &amp; approve. Once HR approves the worker, return here to assign them.</p>
                <p v-if="assignForm.errors.worker_id" class="mb-2 text-xs text-rose-600">{{ assignForm.errors.worker_id }}</p>
                <p v-if="!canAssign && request.stored_status === 'Open' && request.agency_status !== 'Active'" class="mb-3 px-2 py-1.5 rounded-lg text-[11px] font-bold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                    No new workers can be assigned while the agency is not Active.
                </p>
                <div v-if="request.assigned.length === 0" class="text-xs text-slate-400">No workers assigned yet.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="a in request.assigned" :key="a.assignment_id" class="py-2 flex items-center justify-between gap-2 text-xs">
                        <div>
                            <button @click="openAssignedWorker(a)" title="View worker info"
                                class="font-bold text-blue-600 hover:text-blue-700 hover:underline text-left transition">
                                {{ a.worker_name }}
                            </button>
                            <p class="text-slate-500 dark:text-slate-400">{{ a.position_skill || '' }}{{ a.assigned_at ? ' · since ' + a.assigned_at : '' }}{{ a.assigned_by ? ' · by ' + a.assigned_by : '' }}</p>
                        </div>
                        <button
                            v-if="canEdit"
                            @click="releaseWorker(a)"
                            class="px-3 py-1.5 text-[11px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition shrink-0"
                        >
                            Release
                        </button>
                    </li>
                </ul>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Agency proposals</h2>
                <div v-if="request.proposals.length === 0" class="text-xs text-slate-400">No proposals from the agency yet.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="p in request.proposals" :key="p.id" class="py-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div>
                            <button @click="openProposalWorker(p)" title="View worker info"
                                class="font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 hover:underline text-left transition">
                                {{ p.worker_name }}
                            </button>
                            <p class="text-slate-500 dark:text-slate-400">proposed {{ p.proposed_at || '' }}{{ p.proposed_by ? ' by ' + p.proposed_by : '' }}</p>
                            <p v-if="p.status !== 'Proposed'" class="text-slate-500 dark:text-slate-400">{{ p.status }}{{ p.reviewed_by ? ' by ' + p.reviewed_by : '' }}{{ p.review_reason ? ' — ' + p.review_reason : '' }}</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click="openProposalWorker(p)" class="px-3 py-1.5 text-[11px] font-bold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20 rounded-lg transition">View</button>
                            <span v-if="p.status !== 'Proposed'" :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', p.status === 'Accepted' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400']">{{ p.status }}</span>
                            <template v-if="p.status === 'Proposed' && canEdit">
                                <button @click="openAcceptProposal(p)" class="px-3 py-1.5 text-[11px] font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition">Accept</button>
                                <button @click="openRejectProposal(p.id)" class="px-3 py-1.5 text-[11px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition">Reject</button>
                            </template>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Attendance summary per worker</h2>
                <div v-if="request.attendance_summary.length === 0" class="text-xs text-slate-400">No attendance recorded for this request yet.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[420px]">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-2 px-3">Worker</th>
                                <th class="py-2 px-3 text-center">Records</th>
                                <th class="py-2 px-3 text-center">P / A / L</th>
                                <th class="py-2 px-3 text-center">Hours</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr v-for="s in request.attendance_summary" :key="s.worker_id">
                                <td class="py-2 px-3 text-xs font-bold text-slate-900 dark:text-white">{{ s.worker_name }}</td>
                                <td class="py-2 px-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ s.records }}</td>
                                <td class="py-2 px-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ s.present }} / {{ s.absent }} / {{ s.late }}</td>
                                <td class="py-2 px-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ s.hours }}{{ s.overtime_hours ? '+' + s.overtime_hours + ' OT' : '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Request activity modal -->
        <div v-if="showActivityModal" class="fixed inset-0 z-50 bg-transparent" @click.self="showActivityModal = false" @keydown.esc="showActivityModal = false">
            <section role="dialog" aria-modal="true" aria-labelledby="request-activity-title" class="fixed bottom-24 right-6 flex max-h-[min(70vh,32rem)] w-[min(36rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-800">
                <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                    <div class="flex items-center gap-3">
                        <History class="h-5 w-5 text-blue-600" />
                        <div>
                            <h2 id="request-activity-title" class="text-base font-black text-slate-900 dark:text-white">Request history</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Last 20 activities</p>
                        </div>
                    </div>
                    <button type="button" @click="showActivityModal = false" class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Close request history">Close</button>
                </header>
                <div class="min-h-0 overflow-y-auto px-5">
                    <p v-if="request.activities.length === 0" class="py-10 text-center text-sm text-slate-400">No activity recorded yet.</p>
                    <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <li v-for="a in request.activities" :key="a.id" class="flex flex-col gap-1 py-4 sm:flex-row sm:items-start sm:gap-4">
                            <span class="shrink-0 text-xs text-slate-400 sm:w-40">{{ a.date }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ actionLabel(a.action) }}</p>
                                <p v-if="a.details" class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400">{{ a.details }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">by {{ a.actor }}</p>
                                <p v-if="a.reason" class="mt-1 text-xs italic text-slate-400">“{{ a.reason }}”</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <!-- Agency proposal worker profile -->
        <div v-if="selectedProposal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-3 backdrop-blur-sm sm:p-5" @click.self="closeProposalWorker">
            <section class="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" :aria-label="'Worker profile: ' + selectedProposal.worker_name">
                <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800 sm:px-5">
                    <nav class="flex min-w-0 items-center gap-2 text-xs font-medium text-slate-500">
                        <span>Workers</span><span>/</span><span class="truncate font-semibold text-slate-800 dark:text-slate-200">{{ selectedProposal.worker_code || ('WRK-' + selectedProposal.worker_id) }}</span>
                    </nav>
                    <button type="button" @click="closeProposalWorker" aria-label="Close worker profile" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </header>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3 sm:p-5">
                    <div class="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:p-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-lg font-black text-white">{{ selectedProposal.worker_name?.charAt(0) || 'W' }}</div>
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-black text-slate-900 dark:text-white sm:text-lg">{{ selectedProposal.worker_name }}</h2>
                                <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="font-mono">{{ selectedProposal.worker_code || '—' }}</span><span>{{ selectedProposal.worker_profile?.agency || request.agency || 'No agency' }}</span>
                                    <span :class="['rounded-full border px-2 py-0.5 text-[10px] font-black uppercase', proposalDocumentBadge(selectedProposal.worker_profile?.status || selectedProposal.worker_status || 'Pending') ]">{{ selectedProposal.worker_profile?.status || selectedProposal.worker_status || 'Pending' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span :class="['rounded-full px-2 py-1 text-[10px] font-black uppercase', selectedProposal.status === 'Accepted' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : selectedProposal.status === 'Rejected' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400']">Proposal {{ selectedProposal.status }}</span>
                            <Link :href="route('hrm.workers.show', selectedProposal.worker_id)" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-bold text-blue-600 transition hover:bg-blue-50 dark:border-slate-700 dark:text-blue-400 dark:hover:bg-slate-700">Open worker page</Link>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800 sm:p-4">
                        <h3 class="mb-2 text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">Overview</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[500px] border-collapse text-left text-xs [&_td]:py-2 [&_th]:py-2">
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                    <tr><th class="w-2/5 pr-3 font-medium text-slate-400">Agency</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.agency || request.agency || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Position / skill</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.position_skill || selectedProposal.position_skill || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Years experience</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.years_experience ? selectedProposal.worker_profile.years_experience + ' years' : '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Proposed dept.</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.proposed_department || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Availability</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.availability || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Arrangement</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.employment_arrangement || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Daily rate</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.daily_rate != null ? '₱' + Number(selectedProposal.worker_profile.daily_rate).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Birth date</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.birth_date || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Gender</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.gender || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Phone</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.phone || selectedProposal.phone || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Email</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.email || selectedProposal.email || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Address</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.address || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Government ID</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.gov_id_type ? selectedProposal.worker_profile.gov_id_type + ' · ' + (selectedProposal.worker_profile.gov_id_number || '—') : '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Emergency contact</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.emergency_name ? selectedProposal.worker_profile.emergency_name + (selectedProposal.worker_profile.emergency_relationship ? ' (' + selectedProposal.worker_profile.emergency_relationship + ')' : '') + (selectedProposal.worker_profile.emergency_phone ? ' · ' + selectedProposal.worker_profile.emergency_phone : '') : '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Consent</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.consent_given ? 'Yes' + (selectedProposal.worker_profile.consent_at ? ' · ' + selectedProposal.worker_profile.consent_at : '') : '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Resume</th><td class="font-semibold text-slate-700 dark:text-slate-200"><a v-if="selectedProposal.worker_profile?.resume_url || selectedProposal.resume_url" :href="selectedProposal.worker_profile?.resume_url || selectedProposal.resume_url" target="_blank" rel="noopener" class="text-blue-600 hover:underline dark:text-blue-400">{{ selectedProposal.worker_profile?.resume_name || selectedProposal.resume_name || 'View resume' }}</a><span v-else>—</span></td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Education</th><td class="whitespace-pre-line font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.education || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">School address</th><td class="whitespace-pre-line font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.education_address || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Experience</th><td class="whitespace-pre-line font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.work_experience || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Skills</th><td class="whitespace-pre-line font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.skills || '—' }}</td></tr>
                                    <tr><th class="pr-3 font-medium text-slate-400">Training / certifications</th><td class="whitespace-pre-line font-semibold text-slate-700 dark:text-slate-200">{{ selectedProposal.worker_profile?.trainings_certifications || '—' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800 sm:p-4">
                        <h3 class="mb-2 text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">Documents — HR verification</h3>
                        <p class="mb-3 text-[11px] text-slate-500 dark:text-slate-400">Review each uploaded file and its current verification status.</p>
                        <div v-if="!selectedProposal.worker_profile?.documents?.length" class="text-xs text-slate-400">No supporting files uploaded yet.</div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full min-w-[480px] border-collapse text-left text-xs [&_td]:py-2 [&_th]:py-2">
                                <thead><tr class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:text-slate-400"><th class="px-2">Document</th><th class="px-2">Details</th><th class="px-2 text-center">Status</th></tr></thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                    <tr v-for="doc in selectedProposal.worker_profile.documents" :key="doc.id" class="align-top">
                                        <td class="px-2"><a :href="doc.url" target="_blank" rel="noopener" class="font-bold text-blue-600 hover:underline dark:text-blue-400">{{ doc.original_name || doc.label || doc.doc_type_label }}</a></td>
                                        <td class="px-2 text-[11px] text-slate-500 dark:text-slate-400">{{ doc.doc_type_label }}{{ doc.label && doc.label !== doc.original_name ? ' · ' + doc.label : '' }}{{ doc.issue_date ? ' · start ' + doc.issue_date : '' }}{{ doc.expiry_date ? ' · exp ' + doc.expiry_date : '' }}{{ doc.verified_by ? ' · by ' + doc.verified_by : '' }}<p v-if="doc.review_note" class="mt-1 italic">{{ doc.review_note }}</p></td>
                                        <td class="px-2 text-center"><span :class="['rounded-full border px-2 py-0.5 text-[10px] font-black uppercase', proposalDocumentBadge(doc.status)]">{{ doc.status }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Proposed {{ selectedProposal.proposed_at || '' }}{{ selectedProposal.proposed_by ? ' by ' + selectedProposal.proposed_by : '' }}<span v-if="selectedProposal.status !== 'Proposed'"> · {{ selectedProposal.status }}{{ selectedProposal.reviewed_by ? ' by ' + selectedProposal.reviewed_by : '' }}{{ selectedProposal.review_reason ? ' — ' + selectedProposal.review_reason : '' }}</span></p>
                </div>
                <footer v-if="selectedProposal.status === 'Proposed' && canEdit" class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800 sm:px-5">
                    <button @click="openAcceptProposal(selectedProposal)" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-emerald-700">Accept</button>
                    <button @click="openRejectProposal(selectedProposal.id)" class="rounded-lg bg-rose-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-rose-700">Reject</button>
                </footer>
            </section>
        </div>

        <!-- Assigned worker-info modal -->
        <div v-if="selectedAssigned" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="closeAssignedWorker">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg p-5 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">{{ selectedAssigned.worker_name }}</h3>
                    </div>
                    <button @click="closeAssignedWorker" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="Close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span v-if="selectedAssigned.worker_status" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase border bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">{{ selectedAssigned.worker_status }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Assigned{{ selectedAssigned.assigned_at ? ' · since ' + selectedAssigned.assigned_at : '' }}</span>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Position / skill</dt>
                        <dd class="mt-0.5 font-bold text-slate-800 dark:text-slate-100">{{ selectedAssigned.position_skill || '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Daily rate</dt>
                        <dd class="mt-0.5 font-bold text-slate-800 dark:text-slate-100">{{ selectedAssigned.daily_rate ?? '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Phone</dt>
                        <dd class="mt-0.5 font-bold text-slate-800 dark:text-slate-100">{{ selectedAssigned.phone || '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Gender</dt>
                        <dd class="mt-0.5 font-bold text-slate-800 dark:text-slate-100">{{ selectedAssigned.gender || '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3 col-span-2">
                        <dt class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Resume</dt>
                        <dd class="mt-0.5 font-bold text-slate-800 dark:text-slate-100">
                            <a v-if="selectedAssigned.resume_url" :href="selectedAssigned.resume_url" target="_blank" rel="noopener"
                                class="text-blue-600 hover:text-blue-700 hover:underline transition" :title="selectedAssigned.resume_name || 'View resume'">
                                {{ selectedAssigned.resume_name || 'View resume' }} →
                            </a>
                            <span v-else class="text-slate-400">No resume uploaded</span>
                        </dd>
                    </div>
                </dl>

                <p v-if="selectedAssigned.assigned_by" class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    Assigned{{ selectedAssigned.assigned_at ? ' ' + selectedAssigned.assigned_at : '' }} by {{ selectedAssigned.assigned_by }}
                </p>

                <div class="mt-4 flex items-center justify-between gap-2">
                    <Link :href="route('hrm.workers.show', selectedAssigned.worker_id)" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition">Open full worker profile →</Link>
                    <button v-if="canEdit && selectedAssigned.assignment_id" @click="closeAssignedWorker(); releaseWorker(selectedAssigned)" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition">Release</button>
                </div>
            </div>
        </div>

        <!-- Release-worker modal (reason required) -->
        <div v-if="releaseTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="closeReleaseWorker">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md p-5 shadow-2xl">
                <h3 class="text-base font-black text-slate-900 dark:text-white">Release {{ releaseTarget.worker_name }}?</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    The worker returns to Available and a slot reopens on this request.
                </p>
                <textarea v-model="releaseReason" rows="2" placeholder="Reason (required)" class="mt-3 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
                <p v-if="releaseError" class="mt-1 text-xs text-rose-600">{{ releaseError }}</p>
                <div class="mt-3 flex items-center justify-end gap-2">
                    <button @click="closeReleaseWorker" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">Cancel</button>
                    <button @click="submitReleaseWorker" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold">Confirm release</button>
                </div>
            </div>
        </div>

        <!-- Reject-proposal modal -->
        <div v-if="rejectProposalId !== null" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="rejectProposalId = null">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md p-5 shadow-2xl">
                <h3 class="text-base font-black text-slate-900 dark:text-white">Reject proposal?</h3>
                <textarea v-model="rejectProposalReason" rows="2" placeholder="Reason (required)" class="mt-3 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
                <p v-if="rejectProposalError" class="mt-1 text-xs text-rose-600">{{ rejectProposalError }}</p>
                <div class="mt-3 flex items-center justify-end gap-2">
                    <button @click="rejectProposalId = null" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">Cancel</button>
                    <button @click="submitRejectProposal" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold">Confirm rejection</button>
                </div>
            </div>
        </div>

        <!-- Accept-proposal CONFIRMATION modal -->
        <div v-if="acceptTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="closeAcceptConfirm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md p-5 shadow-2xl">
                <h3 class="text-base font-black text-slate-900 dark:text-white">Review worker account?</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    This opens the worker account for this request
                    ({{ request.filled }} of {{ request.needed }} filled). Status stays Proposed until you click Finish.
                </p>
                <div class="mt-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3 text-xs">
                    <p class="font-black text-slate-900 dark:text-white">
                        {{ acceptTarget.worker_name }}
                    </p>
                    <p class="mt-1 text-slate-500 dark:text-slate-400">
                        {{ acceptTarget.position_skill || '—' }}{{ acceptTarget.daily_rate ? ' · ₱' + acceptTarget.daily_rate + '/day' : '' }}{{ acceptTarget.proposed_at ? ' · proposed ' + acceptTarget.proposed_at : '' }}{{ acceptTarget.proposed_by ? ' by ' + acceptTarget.proposed_by : '' }}
                    </p>
                </div>
                <div class="mt-3 flex items-center justify-end gap-2">
                    <button @click="closeAcceptConfirm" :disabled="accepting" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 disabled:opacity-50">Cancel</button>
                    <button @click="confirmAcceptProposal" :disabled="accepting" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold disabled:opacity-60">
                        {{ accepting ? 'Opening...' : 'Continue to worker account' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Direct-assign CONFIRMATION modal (same process as proposal acceptance) -->
        <div v-if="assignTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="closeAssignConfirm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md p-5 shadow-2xl">
                <h3 class="text-base font-black text-slate-900 dark:text-white">Review worker account?</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    This opens the worker account for this request
                    ({{ request.filled }} of {{ request.needed }} filled). Nothing is assigned until you click Finish.
                </p>
                <div class="mt-3 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3 text-xs">
                    <p class="font-black text-slate-900 dark:text-white">
                        {{ assignTarget.worker_name }}
                    </p>
                    <p class="mt-1 text-slate-500 dark:text-slate-400">
                        {{ assignTarget.position_skill || '—' }}{{ assignTarget.daily_rate ? ' · ₱' + assignTarget.daily_rate + '/day' : '' }}
                    </p>
                </div>
                <div class="mt-3 flex items-center justify-end gap-2">
                    <button @click="closeAssignConfirm" :disabled="accepting" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 disabled:opacity-50">Cancel</button>
                    <button @click="confirmAssignWorker" :disabled="accepting" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold disabled:opacity-60">
                        {{ accepting ? 'Opening...' : 'Continue to worker account' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Display-only CREATE-EMPLOYEE-style preview (separate from Onboarding flow — no DB write) -->
        <div v-if="hirePreview" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="closeHirePreview">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-3xl shadow-2xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="p-5 border-b border-slate-200 dark:border-slate-700 shrink-0">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold tracking-tight text-slate-900 dark:text-white">WORKER ASSIGNMENT — PREVIEW</h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800">{{ hireMode === 'direct' ? 'Direct assign — finishes as Assigned' : 'Still Proposed — finishes as Accepted' }}</span>
                            </div>
                        </div>
                        <button @click="closeHirePreview" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl text-slate-400 hover:text-slate-600 transition-all">✕</button>
                    </div>
                </div>

                <div class="p-5 space-y-6 overflow-y-auto">
                    <section>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white tracking-wide">1. WORKER INFORMATION</h4>
                        <div class="border-t border-slate-200 dark:border-slate-700 mt-1 mb-3"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-xs">
                            <div class="flex justify-between border-b border-slate-50 dark:border-slate-700/50 py-1.5"><span class="text-slate-400">Worker</span><span class="font-bold text-slate-800 dark:text-slate-100">{{ hirePreview.worker_name }}</span></div>
                            <div class="flex justify-between border-b border-slate-50 dark:border-slate-700/50 py-1.5"><span class="text-slate-400">Position / skill</span><span class="font-semibold text-slate-700 dark:text-slate-200">{{ hirePreview.position_skill || '—' }}</span></div>
                            <div class="flex justify-between border-b border-slate-50 dark:border-slate-700/50 py-1.5"><span class="text-slate-400">Phone</span><span class="font-semibold text-slate-700 dark:text-slate-200">{{ hirePreview.phone || '—' }}</span></div>
                            <div class="flex justify-between border-b border-slate-50 dark:border-slate-700/50 py-1.5"><span class="text-slate-400">Email for welcome notice</span><span class="font-semibold text-slate-700 dark:text-slate-200">{{ hirePreview.email || 'Missing email' }}</span></div>
                            <div class="flex justify-between border-b border-slate-50 dark:border-slate-700/50 py-1.5"><span class="text-slate-400">Gender</span><span class="font-semibold text-slate-700 dark:text-slate-200">{{ hirePreview.gender || '—' }}</span></div>
                            <div v-if="hireMode !== 'direct'" class="flex justify-between border-b border-slate-50 dark:border-slate-700/50 py-1.5"><span class="text-slate-400">Proposal</span><span class="font-semibold text-amber-600">⏳ Proposed — under review{{ hirePreview.proposed_at ? ' · ' + hirePreview.proposed_at : '' }}</span></div>
                            <div v-else class="flex justify-between border-b border-slate-50 dark:border-slate-700/50 py-1.5"><span class="text-slate-400">Source</span><span class="font-semibold text-blue-600">Direct assign — available worker</span></div>
                        </div>
                    </section>

                    <section>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white tracking-wide">2. EMPLOYMENT ASSIGNMENT (from request)</h4>
                        <div class="border-t border-slate-200 dark:border-slate-700 mt-1 mb-3"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div><p class="text-slate-400 font-semibold mb-1">Position</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">{{ request.position_skill }}</p></div>
                            <div><p class="text-slate-400 font-semibold mb-1">Department</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">{{ request.department }}</p></div>
                            <div><p class="text-slate-400 font-semibold mb-1">Department module (users.role)</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">{{ request.department_module || '—' }}</p><p class="mt-1 text-[10px] text-slate-500">Same mapping as CREATE EMPLOYEE — prefilled from department, display only.</p></div>
                            <div><p class="text-slate-400 font-semibold mb-1">Employment arrangement</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">Agency Contract</p><p class="mt-1 text-[10px] text-slate-500">Fixed for agency workers — display only.</p></div>
                            <div>
                                <label for="reporting-manager" class="mb-1 block font-semibold text-slate-400">Reporting manager</label>
                                <select id="reporting-manager" v-model="selectedManagerId" :disabled="hiring_managers.length === 0" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="">{{ hiring_managers.length ? 'Select a reporting manager...' : 'No manager assigned to this department' }}</option>
                                    <option v-for="manager in hiring_managers" :key="manager.id" :value="manager.id">{{ manager.name }} · {{ manager.position }}</option>
                                </select>
                                <p v-if="managerError" class="mt-1 text-[10px] font-bold text-rose-600">{{ managerError }}</p>
                                <p v-else class="mt-1 text-[10px] text-slate-500">Managers assigned to {{ request.department }}. Required when training is enabled.</p>
                            </div>
                            <div><p class="text-slate-400 font-semibold mb-1">Deployment start date</p><input v-model="previewStartDate" @input="updateDeploymentStart" type="date" :min="todayDate" required class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm font-semibold" /><p v-if="previewStartDateError" class="mt-1 text-[10px] font-bold text-rose-600">{{ previewStartDateError }}</p><p v-else class="mt-1 text-[10px] text-slate-500">Saved as the worker's deployment start date.</p></div>
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-3 text-xs font-bold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                <input v-model="trainingRequired" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500" @change="onTrainingRequiredChange" />
                                Training required
                            </label>
                            <template v-if="trainingRequired">
                                <div>
                                    <label for="training-end-date" class="mb-1 block font-semibold text-slate-400">Training end date</label>
                                    <input id="training-end-date" :value="trainingEndDate" type="date" :min="previewStartDate" :max="trainingEndMax" readonly class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                                    <p class="mt-1 text-[10px] text-slate-500">Automatically set to four weeks after deployment (maximum).</p>
                                </div>
                            </template>
                            <p v-if="trainingDateError" class="md:col-span-2 text-[10px] font-bold text-rose-600">{{ trainingDateError }}</p>
                            <div><p class="text-slate-400 font-semibold mb-1">Years of experience</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">{{ formatYearsExperience(hirePreview.years_experience) }}</p></div>
                        </div>
                    </section>

                    <section>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white tracking-wide">3. WORK ASSIGNMENT</h4>
                        <div class="border-t border-slate-200 dark:border-slate-700 mt-1 mb-3"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div><p class="text-slate-400 font-semibold mb-1">Shift</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">{{ request.shift || '—' }}</p></div>
                            <div><p class="text-slate-400 font-semibold mb-1">Agency</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">{{ request.agency || '—' }}</p></div>
                        </div>
                    </section>

                    <section>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white tracking-wide">4. COMPENSATION (display only)</h4>
                        <div class="border-t border-slate-200 dark:border-slate-700 mt-1 mb-3"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div><p class="text-slate-400 font-semibold mb-1">Daily rate</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">{{ hirePreview.daily_rate ? '₱' + hirePreview.daily_rate + '/day' : '—' }}</p></div>
                        </div>
                    </section>

                    <section>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white tracking-wide">5. EMPLOYEE ACCOUNT</h4>
                        <div class="border-t border-slate-200 dark:border-slate-700 mt-1 mb-3"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div>
                                <p class="text-slate-400 font-semibold mb-1">Company Email</p>
                                <div class="flex items-center gap-2">
                                    <input v-model="previewEmail" type="email" placeholder="juan.delacruz@monti.com" class="flex-1 min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                    <button type="button" @click="suggestPreviewEmail(true)" title="Auto-generate from worker name" class="shrink-0 px-3 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 transition-all">Generate</button>
                                </div>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Generate via system or type manually. On Finish the worker gets a welcome email with their ID, position, department, company account, temporary password, and training date and shift time.</p>
                    </section>

                    <section>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white tracking-wide">6. EMERGENCY CONTACT</h4>
                        <div class="border-t border-slate-200 dark:border-slate-700 mt-1 mb-3"></div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                            <div><p class="text-slate-400 font-semibold mb-1">Contact name</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">{{ hirePreview.emergency_name || '—' }}</p></div>
                            <div><p class="text-slate-400 font-semibold mb-1">Relationship</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">{{ hirePreview.emergency_relationship || '—' }}</p></div>
                            <div><p class="text-slate-400 font-semibold mb-1">Contact number</p><p class="px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-semibold">{{ hirePreview.emergency_phone || '—' }}</p></div>
                        </div>
                    </section>
                </div>

                <p v-if="hirePreviewError" class="mx-5 mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-300">{{ hirePreviewError }}</p>
                <div class="p-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3 shrink-0">
                    <Link :href="route('hrm.workers.show', hirePreview.worker_id || hirePreview.id)" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition">Open full worker profile →</Link>
                    <div class="flex items-center gap-2">
                        <button @click="closeHirePreview" :disabled="accepting" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-all disabled:opacity-50">Cancel</button>
                        <button @click="finishHirePreview" :disabled="accepting" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold transition-all disabled:opacity-60">{{ accepting ? 'Working...' : (hireMode === 'direct' ? 'Finish — Assign worker' : 'Finish — Accept worker') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="accepting" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm" role="status" aria-live="polite" aria-label="Assigning worker">
            <div class="w-full max-w-sm rounded-2xl border border-white/15 bg-white p-7 text-center shadow-2xl dark:bg-slate-800 dark:border-slate-700">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border-4 border-emerald-100 border-t-emerald-600 dark:border-emerald-900 dark:border-t-emerald-400 animate-spin"></div>
                <h2 class="mt-5 text-base font-black text-slate-900 dark:text-white">{{ hireMode === 'proposal' ? 'Accepting worker proposal' : 'Deploying worker' }}</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-300">Creating the worker account and saving the assignment. Please wait.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
