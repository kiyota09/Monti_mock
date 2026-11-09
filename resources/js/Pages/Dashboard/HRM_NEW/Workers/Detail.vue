<script setup lang="ts">
import { confirmModal } from '@/lib/feedback';
// Page 7: Worker detail (header + overview + assignments + activity)
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps<{
    worker: {
        id: number;
        code: string;
        name: string;
        agency_id: number;
        agency: string | null;
        agency_code: string | null;
        agency_status: string | null;
        birth_date: string | null;
        gender: string | null;
        phone: string | null;
        email?: string | null;
        address: string | null;
        resume_url?: string | null;
        resume_original_name?: string | null;
        education?: string | null;
        education_address?: string | null;
        work_experience?: string | null;
        skills?: string | null;
        trainings_certifications?: string | null;
        position_skill: string | null;
        years_experience?: string | number | null;
        proposed_department?: string | null;
        availability?: string | null;
        daily_rate: string | number | null;
        employment_arrangement?: string | null;
        gov_id_type?: string | null;
        gov_id_number: string | null;
        clearance_expiry: string | null;
        clearance_expired: boolean;
        emergency_name: string | null;
        emergency_relationship?: string | null;
        emergency_phone: string | null;
        consent_given?: boolean;
        consent_at?: string | null;
        privacy_notice_version?: string | null;
        updated_at?: string | null;
        last_agency_update?: string | null;
        documents?: Array<{
            id: number; doc_type: string; doc_type_label: string; label: string | null;
            original_name: string | null; url: string; mime: string | null;
            issue_date?: string | null; expiry_date: string | null; status: string; review_note: string | null;
            verified_by: string | null; verified_at: string | null;
        }>;
        document_statuses?: string[];
        status: string;
        show_review_actions: boolean;
        blacklist_reason: string | null;
        pending_note?: string | null;
        assignment_details?: {
            employee_id: string | null; company_email: string | null; department_module: string | null;
            position: string | null; department: string | null; employment_arrangement: string | null;
            reporting_manager: string | null; reporting_manager_position: string | null; request_code: string | null;
            deployment_start_date: string | null; shift: string | null; training_required: boolean;
            training_start_date: string | null; training_end_date: string | null; training_duration_weeks: number | null;
        } | null;
        assignments: Array<{
            id: number; request_id: number; request_code: string | null; department: string | null;
            assigned_at: string | null; released_at: string | null; assigned_by: string | null; active: boolean;
        }>;
        activities: Array<{ id: number; date: string; action: string; details: string | null; actor: string; reason: string | null }>;
        attendance_history: Array<{ id: number; date: string; request_code: string | null; status: string; hours: string | number | null; overtime_hours: string | number | null; approval: string }>;
    };
    permissions?: Record<string, string>;
    departments?: Array<{ id: number; code: string; name: string }>;
    positions?: Array<{ id: number; name: string; department_id: number | null }>;
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash || {});
const canEdit = computed(() => (props.permissions?.workers) === 'edit');
const hasPendingDocs = computed(() => ((props.worker.documents || []) as any[]).some((d) => d.status === 'Pending'));
const showHistoryOptions = ref(false);
const activeHistorySection = ref<'assignment' | 'attendance' | 'activity' | null>(null);

function toggleHistorySection(section: 'assignment' | 'attendance' | 'activity') {
    activeHistorySection.value = activeHistorySection.value === section ? null : section;
    showHistoryOptions.value = false;
}
const formattedYearsExperience = computed(() => {
    if (props.worker.years_experience === null || props.worker.years_experience === undefined || props.worker.years_experience === '') return '—';
    const years = Number(props.worker.years_experience);
    if (!Number.isFinite(years)) return '—';
    return `${years} ${years === 1 ? 'year' : 'years'}`;
});

// ─── Overview inline edit ─────────────────────────────────────────────
// HR can change overview fields directly here (same data the agency portal
// submits). Agency edits appear here on reload — Refresh pulls the latest
// without a full page reload.
const editingOverview = ref(false);
const overviewForm = useForm({
    agency_id: (props as any).worker?.agency_id || '',
    code: (props as any).worker?.code || '',
    name: (props as any).worker?.name || '',
    birth_date: (props as any).worker?.birth_date || '',
    gender: (props as any).worker?.gender || '',
    phone: (props as any).worker?.phone || '',
    email: (props as any).worker?.email || '',
    address: (props as any).worker?.address || '',
    education: (props as any).worker?.education || '',
    education_address: (props as any).worker?.education_address || '',
    work_experience: (props as any).worker?.work_experience || '',
    skills: (props as any).worker?.skills || '',
    trainings_certifications: (props as any).worker?.trainings_certifications || '',
    position_skill: (props as any).worker?.position_skill || '',
    years_experience: (props as any).worker?.years_experience ?? '',
    proposed_department: (props as any).worker?.proposed_department || '',
    availability: /^(\d{4}-\d{2}-\d{2})/.test((props as any).worker?.availability || '') ? ((props as any).worker.availability.match(/(\d{4}-\d{2}-\d{2})/) || [])[1] || '' : ((props as any).worker?.availability || ''),
    daily_rate: (props as any).worker?.daily_rate ?? '',
    employment_arrangement: (props as any).worker?.employment_arrangement || '',
    gov_id_type: (props as any).worker?.gov_id_type || '',
    gov_id_number: (props as any).worker?.gov_id_number || '',
    clearance_expiry: (props as any).worker?.clearance_expiry || '',
    emergency_name: (props as any).worker?.emergency_name || '',
    emergency_relationship: (props as any).worker?.emergency_relationship || '',
    emergency_phone: (props as any).worker?.emergency_phone || '',
});

watch(() => (props as any).worker, (w) => {
    if (editingOverview.value || !w) return;
    const f: any = overviewForm as any;
    f.agency_id = w.agency_id || '';
    f.code = w.code || '';
    f.name = w.name || '';
    f.birth_date = w.birth_date || '';
    f.gender = w.gender || '';
    f.phone = w.phone || '';
    f.email = w.email || '';
    f.address = w.address || '';
    f.education = w.education || '';
    f.education_address = w.education_address || '';
    f.work_experience = w.work_experience || '';
    f.skills = w.skills || '';
    f.trainings_certifications = w.trainings_certifications || '';
    f.position_skill = w.position_skill || '';
    f.years_experience = w.years_experience ?? '';
    f.proposed_department = w.proposed_department || '';
    f.availability = /^(\d{4}-\d{2}-\d{2})/.test(w.availability || '') ? (w.availability.match(/(\d{4}-\d{2}-\d{2})/) || [])[1] || '' : (w.availability || '');
    f.daily_rate = w.daily_rate ?? '';
    f.employment_arrangement = w.employment_arrangement || '';
    f.gov_id_type = w.gov_id_type || '';
    f.gov_id_number = w.gov_id_number || '';
    f.clearance_expiry = w.clearance_expiry || '';
    f.emergency_name = w.emergency_name || '';
    f.emergency_relationship = w.emergency_relationship || '';
    f.emergency_phone = w.emergency_phone || '';
}, { deep: true });

const legacyAvailability = computed(() => {
    const v: string = (props as any).worker?.availability || '';
    if (!v || /^\d{4}-\d{2}-\d{2}$/.test(v)) return '';
    return /(\d{4}-\d{2}-\d{2})/.test(v) ? '' : v;
});

const filteredPositions = computed(() => {
    const deptName = (overviewForm as any).proposed_department;
    if (!deptName) return [];
    const dept = (props.departments || []).find((d) => d.name === deptName);
    if (!dept) return (overviewForm as any).position_skill ? [{ id: -1, name: (overviewForm as any).position_skill, department_id: null }] : [];
    const all = (props.positions || []).filter((p) => p.department_id === dept.id);
    if ((overviewForm as any).position_skill && !all.some((p) => p.name === (overviewForm as any).position_skill)) {
        return [{ id: -1, name: (overviewForm as any).position_skill, department_id: dept.id }, ...all];
    }
    return all;
});

watch(() => (overviewForm as any).proposed_department, () => {
    const names = filteredPositions.value.map((p) => p.name);
    if ((overviewForm as any).position_skill && names.length && !names.includes((overviewForm as any).position_skill)) {
        (overviewForm as any).position_skill = '';
    }
});

function syncOverviewFromWorker() {
    const w: any = (props as any).worker;
    if (!w) return;
    const f: any = overviewForm as any;
    f.agency_id = w.agency_id || '';
    f.code = w.code || '';
    f.name = w.name || '';
    f.birth_date = w.birth_date || '';
    f.gender = w.gender || '';
    f.phone = w.phone || '';
    f.email = w.email || '';
    f.address = w.address || '';
    f.education = w.education || '';
    f.education_address = w.education_address || '';
    f.work_experience = w.work_experience || '';
    f.skills = w.skills || '';
    f.trainings_certifications = w.trainings_certifications || '';
    f.position_skill = w.position_skill || '';
    f.years_experience = w.years_experience ?? '';
    f.proposed_department = w.proposed_department || '';
    f.availability = /^(\d{4}-\d{2}-\d{2})/.test(w.availability || '') ? (w.availability.match(/(\d{4}-\d{2}-\d{2})/) || [])[1] || '' : (w.availability || '');
    f.daily_rate = w.daily_rate ?? '';
    f.employment_arrangement = w.employment_arrangement || '';
    f.gov_id_type = w.gov_id_type || '';
    f.gov_id_number = w.gov_id_number || '';
    f.clearance_expiry = w.clearance_expiry || '';
    f.emergency_name = w.emergency_name || '';
    f.emergency_relationship = w.emergency_relationship || '';
    f.emergency_phone = w.emergency_phone || '';
    f.clearErrors();
}

// Old → new diff preview for the Request-changes submit (also built
// server-side — this is the live preview HR sees before submitting).
const overviewDiff = computed(() => {
    const w: any = (props as any).worker || {};
    const f: any = overviewForm as any;
    const rows: Array<{ label: string; old: string; next: string }> = [];
    const put = (key: string, label: string) => {
        const o = (w[key] ?? '') === null ? '' : String(w[key] ?? '');
        const n = (f[key] ?? '') === null ? '' : String(f[key] ?? '');
        if (o !== n) rows.push({ label, old: o === '' ? '—' : o, next: n === '' ? '—' : n });
    };
    put('name', 'Full name'); put('birth_date', 'Birth date'); put('gender', 'Gender');
    put('phone', 'Phone'); put('email', 'Email'); put('address', 'Address');
    put('education', 'Education'); put('education_address', 'School address'); put('work_experience', 'Experience'); put('skills', 'Skills');
    put('trainings_certifications', 'Training'); put('position_skill', 'Position');
    put('years_experience', 'Years exp.'); put('proposed_department', 'Department');
    put('availability', 'Availability'); put('daily_rate', 'Daily rate');
    put('employment_arrangement', 'Arrangement'); put('gov_id_type', 'Gov ID type');
    put('gov_id_number', 'Gov ID no.');
    put('emergency_name', 'Emergency name'); put('emergency_relationship', 'Emergency relation');
    put('emergency_phone', 'Emergency phone');
    return rows;
});

function refreshWorker() {
    router.reload({ only: ['worker'], onSuccess: () => {} });
}

const ovInput = 'w-full px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200';
const ovLabel = 'block mb-1 text-[10px] font-black uppercase tracking-wider text-slate-400';

const confirmAction = ref<'approve' | 'deactivate' | 'blacklist' | 'reject_pending' | 'request_changes' | null>(null);
const showPendingChanges = ref(false);
const pendingChangeRequests = computed(() => {
    const note = (props as any).worker?.pending_note || '';
    return note.split(/\n\n--- Additional HR correction request ---\n\n/).filter((request: string) => request.trim());
});
const reason = ref('');
const reasonError = ref('');

function statusBadge(s: string): string {
    const map: Record<string, string> = {
        Available: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Deployed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        Inactive: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
        Blacklisted: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
        Pending: 'bg-amber-200 dark:bg-amber-900/40 border-amber-400 dark:border-amber-700',
        'For Correction': 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 border-orange-200 dark:border-orange-800',
    };
    return map[s] || 'bg-slate-100 text-slate-600 border-slate-200';
}

function openConfirm(action: 'approve' | 'deactivate' | 'blacklist' | 'reject_pending' | 'request_changes') {
    confirmAction.value = action;
    const continuingRequest = action === 'request_changes' && editingOverview.value;
    if (!continuingRequest) {
        reason.value = '';
        reasonError.value = '';
    }
    if (action === 'request_changes') {
        // Overview becomes input-filled for HR correction.
        if (!continuingRequest) syncOverviewFromWorker();
        editingOverview.value = true;
    } else {
        editingOverview.value = false;
    }
}

function closeConfirm() {
    confirmAction.value = null;
    reason.value = '';
    reasonError.value = '';
    editingOverview.value = false;
}

function continueEditingOverview() {
    confirmAction.value = null;
}

function approvePending() {
    openConfirm('approve');
}

async function submitConfirm() {
    if ((confirmAction.value === 'blacklist' || confirmAction.value === 'reject_pending' || confirmAction.value === 'request_changes') && !reason.value.trim()) {
        reasonError.value = confirmAction.value === 'request_changes' ? 'Tell the agency what to correct or complete.' : 'A reason is required.';
        return;
    }
    if (confirmAction.value === 'approve') {
        router.post(route('hrm.workers.approve', props.worker.id), {}, {
            preserveScroll: true,
            onSuccess: () => { confirmAction.value = null; },
        });
        return;
    }
    if (confirmAction.value === 'request_changes') {
        if (!overviewDiff.value.length) {
            reasonError.value = 'Edit at least one Overview field before sending a correction request.';
            return;
        }
        if (!await confirmModal(`Send ${overviewDiff.value.length} correction request${overviewDiff.value.length === 1 ? '' : 's'} to the agency?`)) return;
        // HR-corrected overview fields ride along with the message so the
        // agency portal is notified of old → new changes.
        const f: any = overviewForm as any;
        const payload: Record<string, any> = {
            message: reason.value,
            agency_id: (props as any).worker.agency_id,
            code: (props as any).worker.code,
            name: f.name, birth_date: f.birth_date || null, gender: f.gender || null,
            phone: f.phone || null, email: f.email || null, address: f.address || null,
            education: f.education || null, work_experience: f.work_experience || null,
            skills: f.skills || null, trainings_certifications: f.trainings_certifications || null,
            position_skill: f.position_skill || null,
            years_experience: f.years_experience === '' ? null : f.years_experience,
            proposed_department: f.proposed_department || null,
            availability: f.availability || null,
            daily_rate: f.daily_rate === '' ? null : f.daily_rate,
            employment_arrangement: f.employment_arrangement || null,
            gov_id_type: f.gov_id_type || null, gov_id_number: f.gov_id_number || null,
            clearance_expiry: f.clearance_expiry || null,
            emergency_name: f.emergency_name || null,
            emergency_relationship: f.emergency_relationship || null,
            emergency_phone: f.emergency_phone || null,
        };
        router.post(route('hrm.workers.request-changes', (props as any).worker.id), payload, {
            preserveScroll: true,
            onSuccess: () => { confirmAction.value = null; reason.value = ''; editingOverview.value = false; syncOverviewFromWorker(); },
        });
        return;
    }
    const routeName = confirmAction.value === 'blacklist'
        ? 'hrm.workers.blacklist'
        : confirmAction.value === 'reject_pending'
            ? 'hrm.workers.reject-pending'
            : 'hrm.workers.deactivate';
    const payload = { reason: reason.value || null };
    router.post(route(routeName, props.worker.id), payload, {
        preserveScroll: true,
        onSuccess: () => { confirmAction.value = null; reason.value = ''; },
    });
}

function reactivate() {
    router.post(route('hrm.workers.reactivate', props.worker.id), {}, { preserveScroll: true });
}

async function liftBlacklist() {
    if (!await confirmModal('Lift the blacklist for this worker? Only HR/IT may do this.')) return;
    router.post(route('hrm.workers.lift-blacklist', props.worker.id), {}, { preserveScroll: true });
}

function actionLabel(a: string): string {
    const map: Record<string, string> = {
        worker_created: 'Created', worker_updated: 'Updated', worker_deactivated: 'Rejected',
        worker_reactivated: 'Re-opened', worker_blacklisted: 'Blacklisted',
        worker_blacklist_lifted: 'Blacklist lifted', worker_assigned: 'Assigned', worker_released: 'Released',
        worker_approved: 'Approved', worker_rejected: 'Rejected',
        worker_correction_requested: 'Correction requested', worker_resubmitted: 'Resubmitted',
        document_pending: 'Doc pending', document_verified: 'Doc verified',
        document_rejected: 'Doc rejected', document_expired: 'Doc expired',
        document_resubmit_requested: 'Resubmission requested',
    };
    return map[a] || a;
}

const verifyingId = ref<number | null>(null);
const verifyAction = ref<'approve' | 'reject' | 'resubmit'>('approve');
const verifyConfirmationAction = ref<'approve' | 'reject' | 'resubmit' | null>(null);
const verifyNote = ref('');
const verifyNoteError = ref('');
const verifySaving = ref(false);
const verifyingDocument = computed(() => (props.worker.documents || []).find((document: any) => document.id === verifyingId.value) || null);
const previewDocument = ref<{ url: string; name: string; mime?: string | null } | null>(null);
const previewZoom = ref(1);
const previewOffset = ref({ x: 0, y: 0 });
const draggingPreview = ref(false);
const previewDragStart = ref({ x: 0, y: 0 });

function openDocumentPreview(document: { url: string; name: string; mime?: string | null }) {
    previewDocument.value = document;
    previewZoom.value = 1;
    previewOffset.value = { x: 0, y: 0 };
}

const previewIsImage = computed(() => Boolean(previewDocument.value?.mime?.startsWith('image/')));
function changePreviewZoom(delta: number) {
    previewZoom.value = Math.min(3, Math.max(0.5, Math.round((previewZoom.value + delta) * 100) / 100));
    if (previewZoom.value === 1) previewOffset.value = { x: 0, y: 0 };
}

function closeDocumentPreview() {
    previewDocument.value = null;
    draggingPreview.value = false;
}

function handlePreviewKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && previewDocument.value) closeDocumentPreview();
}

function startPreviewDrag(event: PointerEvent) {
    if (previewZoom.value <= 1 || !previewIsImage.value) return;
    draggingPreview.value = true;
    previewDragStart.value = { x: event.clientX - previewOffset.value.x, y: event.clientY - previewOffset.value.y };
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function movePreviewDrag(event: PointerEvent) {
    if (!draggingPreview.value) return;
    previewOffset.value = { x: event.clientX - previewDragStart.value.x, y: event.clientY - previewDragStart.value.y };
}

function stopPreviewDrag() {
    draggingPreview.value = false;
}

onMounted(() => window.addEventListener('keydown', handlePreviewKeydown));
onUnmounted(() => window.removeEventListener('keydown', handlePreviewKeydown));

function openVerify(document: any) {
    verifyingId.value = document.id;
    verifyAction.value = 'approve';
    verifyConfirmationAction.value = null;
    verifyNote.value = '';
    verifyNoteError.value = '';
}

function closeVerify() {
    if (verifySaving.value) return;
    verifyingId.value = null;
    verifyConfirmationAction.value = null;
    verifyNote.value = '';
    verifyNoteError.value = '';
}

function requestVerify(action: 'approve' | 'reject' | 'resubmit') {
    if (!verifyingId.value || verifySaving.value) return;
    if (action === 'approve') verifyNote.value = '';
    verifyNoteError.value = '';
    verifyAction.value = action;
    verifyConfirmationAction.value = action;
}

function cancelVerifyConfirmation() {
    if (verifySaving.value) return;
    verifyConfirmationAction.value = null;
}

function submitVerify() {
    const action = verifyConfirmationAction.value;
    if (!verifyingId.value || !action || verifySaving.value) return;
    if (action !== 'approve' && !verifyNote.value.trim()) {
        verifyNoteError.value = 'Add a note explaining the rejection or replacement needed.';
        return;
    }
    verifySaving.value = true;
    router.post(route('hrm.workers.documents.verify', verifyingId.value), {
        decision: action,
        status: action === 'approve' ? 'Verified' : 'Rejected',
        review_note: verifyNote.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { verifyingId.value = null; verifyConfirmationAction.value = null; verifyNote.value = ''; verifyNoteError.value = ''; },
        onFinish: () => { verifySaving.value = false; },
    });
}

function docBadge(s: string): string {
    const map: Record<string, string> = {
        Verified: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        Pending: 'bg-amber-100 text-amber-700 border-amber-200',
        Rejected: 'bg-rose-100 text-rose-700 border-rose-200',
        Expired: 'bg-slate-200 text-slate-600 border-slate-300',
    };
    return map[s] || 'bg-slate-100 text-slate-600 border-slate-200';
}
</script>

<template>
    <Head :title="worker.name + ' - Workers - Monti ERP'" />
    <AuthenticatedLayout>
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
            <Link :href="route('hrm.workers.index')" class="hover:text-blue-600">Workers</Link>
            <span>/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ worker.code }}</span>
        </nav>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-lg shrink-0">
                    {{ worker.name?.charAt(0) || 'W' }}
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white leading-tight">{{ worker.name }}</h1>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <span class="text-xs font-mono text-slate-400">{{ worker.code }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ worker.agency || 'No agency' }}</span>
                        <span :class="['px-2.5 py-1 rounded-full text-[11px] font-black uppercase border', statusBadge(worker.status), worker.status === 'Pending' && 'worker-status-pending']">{{ worker.status }}</span>
                    </div>
                </div>
            </div>
            <div v-if="canEdit && worker.show_review_actions" class="flex flex-wrap items-center gap-2 shrink-0">
                <button
                    v-if="worker.status === 'Pending' || worker.status === 'For Correction'"
                    @click="approvePending"
                    :disabled="hasPendingDocs"
                    :title="hasPendingDocs ? 'All supporting documents must be Verified before approving.' : ''"
                    :class="['px-4 py-2 rounded-xl text-xs font-bold transition', hasPendingDocs ? 'bg-slate-300 dark:bg-slate-700 text-slate-500 dark:text-slate-400 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 text-white']"
                >Approve</button>
                <button
                    v-if="worker.status === 'Pending' || worker.status === 'Available' || worker.status === 'Deployed' || worker.status === 'For Correction'"
                    @click="openConfirm('request_changes')"
                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs font-bold transition"
                >Request changes</button>
                <button
                    v-if="worker.status === 'Pending' || worker.status === 'For Correction'"
                    @click="openConfirm('reject_pending')"
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition"
                >Reject</button>
                <button
                    v-if="worker.status !== 'Blacklisted'"
                    @click="openConfirm('blacklist')"
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition"
                >Blacklist</button>
            </div>
        </div>
        </div>

        <div v-if="flash.success" class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-sm font-semibold text-rose-700 dark:text-rose-400">
            {{ flash.error }}
        </div>

        <div v-if="worker.pending_note && (worker.status === 'For Correction' || worker.status === 'Deployed')" class="mb-4 flex flex-wrap items-center justify-between gap-3 p-3 rounded-xl bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 text-xs text-orange-800 dark:text-orange-300">
            <span class="font-black">Waiting on agency to apply requested changes.</span>
            <button type="button" @click="showPendingChanges = true" class="px-3 py-1.5 rounded-lg border border-orange-300 dark:border-orange-700 font-bold hover:bg-orange-100 dark:hover:bg-orange-900/40">View request for changes</button>
        </div>
        <div v-if="worker.status === 'Blacklisted' && worker.blacklist_reason" class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-400">
            <span class="font-black">Blacklisted:</span> {{ worker.blacklist_reason }}
        </div>
        <div v-if="worker.clearance_expired" class="mb-4 p-3 rounded-xl bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-800 text-xs font-bold text-yellow-800 dark:text-yellow-300">
            Work permit / medical clearance has expired — this worker cannot be assigned.
        </div>

        <div v-if="confirmAction && canEdit" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="presentation" @click.self="closeConfirm">
          <section class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl dark:border-slate-700 dark:bg-slate-800 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="worker-action-title">
            <p class="text-sm font-black text-slate-900 dark:text-white">
                <span id="worker-action-title">{{ confirmAction === 'approve' ? 'Approve worker' : confirmAction === 'blacklist' ? 'Blacklist worker' : confirmAction === 'reject_pending' ? 'Reject worker' : confirmAction === 'request_changes' ? 'Request corrections from agency' : 'Deactivate worker' }} — {{ worker.name }}?</span>
            </p>
            <p v-if="confirmAction === 'approve'" class="mt-1 text-[11px] text-slate-500">This will approve the worker and move them to Available. Any pending correction requests will be cleared.</p>
            <p v-if="confirmAction === 'request_changes'" class="mt-1 text-[11px] text-slate-500">The Overview card below becomes editable — propose the corrections there. Nothing is saved on HR side; the agency portal applies them.</p>
            <div v-if="confirmAction === 'request_changes'" class="mt-2 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <p class="px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-500 bg-slate-50 dark:bg-slate-900">Requested changes (current → proposed) — {{ overviewDiff.length }}</p>
                <ul v-if="overviewDiff.length" class="px-3 py-2 text-xs space-y-1 max-h-40 overflow-y-auto">
                    <li v-for="r in overviewDiff" :key="r.label" class="flex flex-wrap gap-x-1.5"><span class="font-bold text-slate-700 dark:text-slate-200">{{ r.label }}:</span><span class="text-slate-400 line-through">{{ r.old }}</span><span class="text-slate-400">→</span><span class="font-bold text-emerald-600">{{ r.next }}</span></li>
                </ul>
                <p v-else class="px-3 py-2 text-[11px] text-slate-400">No field edits yet — edit the Overview below or just send a note.</p>
            </div>
            <textarea
                v-if="confirmAction !== 'approve'"
                v-model="reason"
                rows="2"
                :placeholder="confirmAction === 'request_changes' ? 'What should the agency correct or complete? (required)' : (confirmAction === 'blacklist' || confirmAction === 'reject_pending') ? 'Reason (required)' : 'Reason (optional)'"
                class="mt-3 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200"
            ></textarea>
            <p v-if="reasonError" class="mt-1 text-xs text-rose-600">{{ reasonError }}</p>
            <div class="mt-3 flex items-center justify-end gap-2">
                <button v-if="confirmAction === 'request_changes'" @click="continueEditingOverview" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Continue editing overview</button>
                <button @click="closeConfirm" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
                <button @click="submitConfirm" :disabled="confirmAction === 'request_changes' && !overviewDiff.length" :class="['px-4 py-2 text-white rounded-xl text-xs font-bold transition disabled:cursor-not-allowed disabled:opacity-50', confirmAction === 'approve' ? 'bg-emerald-600 hover:bg-emerald-700' : confirmAction === 'request_changes' ? 'bg-orange-500 hover:bg-orange-600' : 'bg-rose-600 hover:bg-rose-700']">{{ confirmAction === 'approve' ? 'Confirm approval' : confirmAction === 'request_changes' ? 'Send request' : confirmAction === 'blacklist' ? 'Confirm blacklist' : 'Confirm rejection' }}</button>
            </div>
          </section>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="flex items-start justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/30">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white">Overview</h2>
                    <div v-if="canEdit" class="flex items-center gap-1.5 shrink-0">
                        <button v-if="!editingOverview" @click="refreshWorker" title="Pull latest agency changes" class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700">Refresh</button>
                        <button v-else @click="openConfirm('request_changes')" class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg bg-orange-500 text-white hover:bg-orange-600">Review &amp; send request</button>
                    </div>
                </div>
                <p class="px-5 pt-3 text-[11px] text-slate-400">
                    <span v-if="worker.last_agency_update">Agency update: {{ worker.last_agency_update }}</span>
                    <span v-if="worker.updated_at"> · Last change: {{ worker.updated_at }}</span>
                    <span v-else>Synced with agency portal — Refresh pulls the latest submission.</span>
                    <span v-if="editingOverview" class="block mt-1 font-bold text-orange-500">Proposing via Request changes — your edits are sent as requests; the agency applies them, then Confirm.</span>
                </p>
                <!-- Read mode -->
                <div v-if="!editingOverview" class="overflow-x-auto px-5 pb-5 pt-2">
                    <table class="w-full text-left border-collapse text-xs [&_th]:py-1.5 [&_td]:py-1.5">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr><th class="py-2 pr-4 w-2/5 text-xs font-medium text-slate-400">Agency</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.agency || '—' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Position / skill</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.position_skill || '—' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Years experience</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ formattedYearsExperience }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Proposed dept.</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.proposed_department || '—' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Availability</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.availability || '—' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Arrangement</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">Agency-deployed</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Daily rate</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.daily_rate !== null ? '₱' + Number(worker.daily_rate).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Birth date</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.birth_date || '—' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Gender</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.gender || '—' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Phone</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.phone || '—' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Email</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.email || '—' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Address</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.address || '—' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Government ID</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ (worker.gov_id_type ? worker.gov_id_type + ' · ' : '') + (worker.gov_id_number || '—') }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Emergency contact</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.emergency_name || '—' }}{{ worker.emergency_relationship ? ' (' + worker.emergency_relationship + ')' : '' }}{{ worker.emergency_phone ? ' · ' + worker.emergency_phone : '' }}</td></tr>
                            <tr><th class="py-2 pr-4 text-xs font-medium text-slate-400">Consent</th><td class="py-2 font-semibold text-slate-700 dark:text-slate-200">{{ worker.consent_given ? 'Yes' + (worker.consent_at ? ' · ' + worker.consent_at : '') : '—' }}</td></tr>
                            <tr v-if="worker.resume_url"><th class="py-2 pr-4 text-xs font-medium text-slate-400">Resume</th><td class="py-2 font-semibold"><button type="button" @click="openDocumentPreview({ url: worker.resume_url!, name: worker.resume_original_name || 'Resume' })" class="text-blue-600 hover:underline">{{ worker.resume_original_name || 'View resume' }}</button></td></tr>
                            <tr v-if="worker.education"><th class="py-2 pr-4 text-xs font-medium text-slate-400">Education</th><td class="py-2 text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ worker.education }}</td></tr>
                            <tr v-if="worker.education_address"><th class="py-2 pr-4 text-xs font-medium text-slate-400">School address</th><td class="py-2 text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ worker.education_address }}</td></tr>
                            <tr v-if="worker.work_experience"><th class="py-2 pr-4 text-xs font-medium text-slate-400">Experience</th><td class="py-2 text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ worker.work_experience }}</td></tr>
                            <tr v-if="worker.skills"><th class="py-2 pr-4 text-xs font-medium text-slate-400">Skills</th><td class="py-2 text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ worker.skills }}</td></tr>
                            <tr v-if="worker.trainings_certifications"><th class="py-2 pr-4 text-xs font-medium text-slate-400">Training / Certifications</th><td class="py-2 text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ worker.trainings_certifications }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <!-- Edit mode — changeable overview, saves via HR update route -->
                <div v-else-if="editingOverview" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label :class="ovLabel">Full name *</label>
                        <input v-model="overviewForm.name" type="text" :class="ovInput" />
                        <p v-if="overviewForm.errors.name" class="mt-1 text-xs text-rose-600">{{ overviewForm.errors.name }}</p>
                    </div>
                    <div>
                        <label :class="ovLabel">Proposed department</label>
                        <select v-model="overviewForm.proposed_department" :class="[ovInput, worker.status === 'Deployed' && 'bg-slate-100 dark:bg-slate-900 opacity-70']" :disabled="worker.status === 'Deployed'">
                            <option value="">Select…</option>
                            <option v-for="d in (departments || [])" :key="d.id" :value="d.name">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label :class="ovLabel">Position / skill</label>
                        <select v-if="filteredPositions.length" v-model="overviewForm.position_skill" :class="[ovInput, worker.status === 'Deployed' && 'bg-slate-100 dark:bg-slate-900 opacity-70']" :disabled="worker.status === 'Deployed'">
                            <option value="">Select…</option>
                            <option v-for="p in filteredPositions" :key="p.id + '-' + p.name" :value="p.name">{{ p.name }}</option>
                        </select>
                        <input v-else v-model="overviewForm.position_skill" type="text" :class="[ovInput, worker.status === 'Deployed' && 'bg-slate-100 dark:bg-slate-900 opacity-70']" placeholder="e.g. HR Manager" :readonly="worker.status === 'Deployed'" />
                    </div>
                    <div>
                        <label :class="ovLabel">Years experience</label>
                        <input v-model="overviewForm.years_experience" type="number" min="0" max="60" step="1" :class="[ovInput, worker.status === 'Deployed' && 'bg-slate-100 dark:bg-slate-900 opacity-70']" :readonly="worker.status === 'Deployed'" />
                    </div>
                    <div>
                        <label :class="ovLabel">Availability</label>
                        <input v-model="overviewForm.availability" type="date" :class="[ovInput, worker.status === 'Deployed' && 'bg-slate-100 dark:bg-slate-900 opacity-70']" :readonly="worker.status === 'Deployed'" :disabled="worker.status === 'Deployed'" />
                        <p v-if="legacyAvailability" class="mt-1 text-[11px] text-slate-400">Previous: {{ legacyAvailability }} — pick a date to replace it.</p>
                    </div>
                    <div>
                        <label :class="ovLabel">Arrangement</label>
                        <select v-model="overviewForm.employment_arrangement" :class="[ovInput, worker.status === 'Deployed' && 'bg-slate-100 dark:bg-slate-900 opacity-70']" :disabled="worker.status === 'Deployed'">
                            <option value="">Select…</option>
                            <option>Full-time</option><option>Part-time</option><option>Contractual</option><option>Project-based</option><option>Seasonal</option>
                        </select>
                    </div>
                    <div>
                        <label :class="ovLabel">Daily rate (₱)</label>
                        <input v-model="overviewForm.daily_rate" type="number" min="0" step="0.01" :class="[ovInput, worker.status === 'Deployed' && 'bg-slate-100 dark:bg-slate-900 opacity-70']" :readonly="worker.status === 'Deployed'" />
                        <p v-if="overviewForm.errors.daily_rate" class="mt-1 text-xs text-rose-600">{{ overviewForm.errors.daily_rate }}</p>
                    </div>
                    <div>
                        <label :class="ovLabel">Birth date</label>
                        <input v-model="overviewForm.birth_date" type="date" :class="ovInput" />
                    </div>
                    <div>
                        <label :class="ovLabel">Gender</label>
                        <select v-model="overviewForm.gender" :class="ovInput"><option value="">Select…</option><option>Male</option><option>Female</option></select>
                    </div>
                    <div>
                        <label :class="ovLabel">Phone</label>
                        <input v-model="overviewForm.phone" type="text" :class="ovInput" />
                    </div>
                    <div>
                        <label :class="ovLabel">Email</label>
                        <input v-model="overviewForm.email" type="email" :class="ovInput" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="ovLabel">Address</label>
                        <textarea v-model="overviewForm.address" rows="2" :class="ovInput"></textarea>
                    </div>
                    <div>
                        <label :class="ovLabel">Government ID type</label>
                        <select v-model="overviewForm.gov_id_type" :class="ovInput"><option value="">Select…</option><option>SSS</option><option>PhilHealth</option><option>Pag-IBIG</option><option>TIN</option><option>Driver's License</option><option>Passport</option><option>National ID</option><option>Voter's ID</option></select>
                    </div>
                    <div>
                        <label :class="ovLabel">Government ID number</label>
                        <input v-model="overviewForm.gov_id_number" type="text" :class="ovInput" />
                    </div>
                    <div>
                        <label :class="ovLabel">Emergency name</label>
                        <input v-model="overviewForm.emergency_name" type="text" :class="ovInput" />
                    </div>
                    <div>
                        <label :class="ovLabel">Emergency relationship</label>
                        <select v-model="overviewForm.emergency_relationship" :class="ovInput">
                            <option value="">Select...</option>
                            <option>Spouse</option>
                            <option>Parent</option>
                            <option>Sibling</option>
                            <option>Child</option>
                            <option>Relative</option>
                            <option>Friend</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div>
                        <label :class="ovLabel">Emergency phone</label>
                        <input v-model="overviewForm.emergency_phone" type="text" :class="ovInput" />
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="ovLabel">Education</label>
                        <textarea v-model="overviewForm.education" rows="2" :class="ovInput"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="ovLabel">School address</label>
                        <textarea v-model="overviewForm.education_address" rows="2" :class="ovInput"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="ovLabel">Experience</label>
                        <textarea v-model="overviewForm.work_experience" rows="2" :class="ovInput"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="ovLabel">Skills</label>
                        <textarea v-model="overviewForm.skills" rows="2" :class="ovInput"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="ovLabel">Training / Certifications</label>
                        <textarea v-model="overviewForm.trainings_certifications" rows="2" :class="ovInput"></textarea>
                    </div>
                </div>
            </div>

            <div v-if="worker.assignment_details" class="lg:col-span-1 lg:self-start bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/30">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white">Employment &amp; Training</h2>
                        <p class="mt-1 text-[11px] text-slate-400">Assignment details saved when this worker was accepted.</p>
                    </div>
                    <span v-if="worker.assignment_details.request_code" class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-bold text-blue-700 dark:border-blue-900 dark:bg-blue-900/30 dark:text-blue-300">{{ worker.assignment_details.request_code }}</span>
                </div>
                <div class="overflow-x-auto px-5 pb-5 pt-2">
                    <table class="w-full min-w-[480px] border-collapse text-left text-xs [&_th]:py-2 [&_td]:py-2">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr><th class="w-2/5 pr-4 font-medium text-slate-400">Employee ID</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.employee_id || worker.code }}</td></tr>
                            <tr><th class="pr-4 font-medium text-slate-400">Company email</th><td class="break-all font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.company_email || '—' }}</td></tr>
                            <tr><th class="pr-4 font-medium text-slate-400">Employment arrangement</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.employment_arrangement || 'Agency-deployed' }}</td></tr>
                            <tr><th class="pr-4 font-medium text-slate-400">Position / skill</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.position || '—' }}</td></tr>
                            <tr><th class="pr-4 font-medium text-slate-400">Department</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.department || '—' }}</td></tr>
                            <tr><th class="pr-4 font-medium text-slate-400">Department module (users.role)</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.department_module || '—' }}</td></tr>
                            <tr><th class="pr-4 font-medium text-slate-400">Reporting manager</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.reporting_manager ? worker.assignment_details.reporting_manager + (worker.assignment_details.reporting_manager_position ? ' · ' + worker.assignment_details.reporting_manager_position : '') : '—' }}</td></tr>
                            <tr><th class="pr-4 font-medium text-slate-400">Deployment start date</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.deployment_start_date || '—' }}</td></tr>
                            <tr><th class="pr-4 font-medium text-slate-400">Shift</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.shift || '—' }}</td></tr>
                            <tr><th class="pr-4 font-medium text-slate-400">Training required</th><td><span :class="['inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold', worker.assignment_details.training_required ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300']">{{ worker.assignment_details.training_required ? 'Yes' : 'No' }}</span></td></tr>
                            <tr v-if="worker.assignment_details.training_required"><th class="pr-4 font-medium text-slate-400">Training start date</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.training_start_date || '—' }}</td></tr>
                            <tr v-if="worker.assignment_details.training_required"><th class="pr-4 font-medium text-slate-400">Training end date</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.training_end_date || '—' }}</td></tr>
                            <tr v-if="worker.assignment_details.training_required"><th class="pr-4 font-medium text-slate-400">Training duration</th><td class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.assignment_details.training_duration_weeks ? worker.assignment_details.training_duration_weeks + (worker.assignment_details.training_duration_weeks === 1 ? ' week' : ' weeks') : '—' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-1">Documents — HR verification</h2>
                <p class="text-[11px] text-slate-400 mb-3">Supporting files submitted by the agency.</p>
                <div v-if="!worker.documents || worker.documents.length === 0" class="text-xs text-slate-400">No supporting files uploaded yet.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs min-w-[640px] [&_th]:py-1.5 [&_td]:py-1.5">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-700">
                                <th class="py-2 px-2">Document</th>
                                <th class="py-2 px-2">Details</th>
                                <th class="py-2 px-2">Status</th>
                                <th v-if="worker.status !== 'Deployed'" class="py-2 px-2 text-right">Review</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <template v-for="d in worker.documents" :key="d.id">
                                <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                    <td class="py-3 px-2 text-xs"><button type="button" @click="openDocumentPreview({ url: d.url, name: d.original_name || d.label || d.doc_type_label, mime: d.mime })" class="font-bold text-blue-600 hover:underline">{{ d.original_name || d.label || d.doc_type_label }}</button></td>
                                    <td class="py-3 px-2 text-[11px] text-slate-400">{{ d.doc_type_label }}{{ d.label && d.label !== d.original_name ? ' · ' + d.label : '' }}{{ d.issue_date ? ' · start ' + d.issue_date : '' }}{{ d.expiry_date ? ' · exp ' + d.expiry_date : '' }}{{ d.verified_by ? ' · by ' + d.verified_by : '' }}<p v-if="d.review_note" class="mt-1 italic">“{{ d.review_note }}”</p></td>
                                    <td class="py-3 px-2"><span :class="['inline-flex rounded-full border px-2 py-0.5 text-[9px] font-black uppercase', docBadge(d.status)]">{{ d.status }}</span></td>
                                    <td v-if="worker.status !== 'Deployed'" class="py-3 px-2 text-right"><button type="button" @click="openVerify(d)" class="rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-[10px] font-bold text-blue-700 transition hover:bg-blue-50 dark:border-blue-900 dark:bg-slate-800 dark:text-blue-300 dark:hover:bg-slate-700">Review</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeHistorySection === 'assignment'" id="worker-assignment-history" class="scroll-mt-6 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Assignment history</h2>
                <div v-if="worker.assignments.length === 0" class="text-xs text-slate-400">No assignments yet.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="a in worker.assignments" :key="a.id" class="py-2 flex flex-col gap-1 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <Link :href="route('hrm.manpower-requests.show', a.request_id)" class="font-bold text-blue-600 hover:text-blue-700">{{ a.request_code || ('Request #' + a.request_id) }}</Link>
                            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', a.active ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300']">
                                {{ a.active ? 'Active' : 'Released' }}
                            </span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400">{{ a.department || '' }} · {{ a.assigned_at || '' }}{{ a.released_at ? ' → ' + a.released_at : '' }}{{ a.assigned_by ? ' · by ' + a.assigned_by : '' }}</p>
                    </li>
                </ul>
            </div>

            <div v-if="activeHistorySection === 'attendance'" id="worker-attendance-history" class="scroll-mt-6 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 lg:col-span-2">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Attendance history (last 30)</h2>
                <div v-if="worker.attendance_history.length === 0" class="text-xs text-slate-400">No attendance records yet.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs min-w-[480px] [&_th]:py-1.5 [&_td]:py-1.5">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-2 px-3">Date</th>
                                <th class="py-2 px-3">Request</th>
                                <th class="py-2 px-3">Status</th>
                                <th class="py-2 px-3 text-center">Hours</th>
                                <th class="py-2 px-3 text-center">Approval</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr v-for="h in worker.attendance_history" :key="h.id" class="hover:bg-blue-100 dark:hover:bg-blue-900/30">
                                <td class="py-2 px-3 text-xs font-bold text-slate-700 dark:text-slate-200">{{ h.date }}</td>
                                <td class="py-2 px-3 text-xs font-mono text-slate-600 dark:text-slate-300">{{ h.request_code || '—' }}</td>
                                <td class="py-2 px-3 text-xs text-slate-600 dark:text-slate-300">{{ h.status }}</td>
                                <td class="py-2 px-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ h.hours ?? '—' }}</td>
                                <td class="py-2 px-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ h.approval }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeHistorySection === 'activity'" id="worker-activity-history" class="scroll-mt-6 bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 lg:col-span-2">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Activity (last 20)</h2>                <div v-if="worker.activities.length === 0" class="text-xs text-slate-400">No activity recorded yet.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="a in worker.activities" :key="a.id" class="py-2 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 text-xs">
                        <span class="text-slate-400 whitespace-nowrap w-40 shrink-0">{{ a.date }}</span>
                        <span class="font-bold text-slate-800 dark:text-slate-100 shrink-0">{{ actionLabel(a.action) }}</span>
                        <span class="text-slate-500 dark:text-slate-400 flex-1">{{ a.details || '' }}</span>
                        <span class="text-slate-500 dark:text-slate-400 shrink-0">by {{ a.actor }}</span>
                        <span v-if="a.reason" class="text-slate-400 italic shrink-0">“{{ a.reason }}”</span>
                    </li>
                </ul>
            </div>
        </div>

        <Teleport to="body">
        <div class="fixed bottom-5 right-4 z-[60] flex flex-col items-end md:right-6">
            <div v-if="showHistoryOptions" class="mb-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <button type="button" @click="toggleHistorySection('assignment')" class="block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Assignment history</button>
                <button type="button" @click="toggleHistorySection('attendance')" class="block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Attendance history</button>
                <button type="button" @click="toggleHistorySection('activity')" class="block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Activity</button>
            </div>
            <button type="button" @click="showHistoryOptions = !showHistoryOptions" :aria-expanded="showHistoryOptions" aria-label="Open worker history options" title="Worker history"
                class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg shadow-blue-900/30 transition hover:scale-105 hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12a9 9 0 1 0 2.64-6.36L3 8m0-5v5h5m4-1v5l3 2" /></svg>
            </button>
        </div>
        </Teleport>

        <div v-if="verifyingDocument" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm" role="presentation" @click.self="closeVerify">
            <section class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-800" role="dialog" aria-modal="true" aria-labelledby="document-review-title">
                <header class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                    <h2 id="document-review-title" class="text-base font-black text-slate-900 dark:text-white">Review supporting document</h2>
                    <p class="mt-1 truncate text-xs font-semibold text-slate-500 dark:text-slate-400">{{ verifyingDocument.original_name || verifyingDocument.label || verifyingDocument.doc_type_label }}</p>
                </header>
                <div class="space-y-3 p-5">
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-900">
                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Current status</span>
                        <span :class="['rounded-full border px-2 py-0.5 text-[9px] font-black uppercase', docBadge(verifyingDocument.status)]">{{ verifyingDocument.status }}</span>
                    </div>
                </div>
                <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/50">
                    <button type="button" @click="closeVerify" :disabled="verifySaving" class="mr-auto rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-100 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                    <button type="button" @click="requestVerify('approve')" :disabled="verifySaving" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50">Approve</button>
                    <button type="button" @click="requestVerify('reject')" :disabled="verifySaving" class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-rose-700 disabled:opacity-50">Reject</button>
                    <button type="button" @click="requestVerify('resubmit')" :disabled="verifySaving" class="rounded-xl bg-amber-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-amber-700 disabled:opacity-50">Request resubmission</button>
                </footer>
            </section>
        </div>

        <div v-if="verifyConfirmationAction && verifyingDocument" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm" role="presentation" @click.self="cancelVerifyConfirmation">
            <section class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-800" role="alertdialog" aria-modal="true" aria-labelledby="document-review-confirm-title">
                <div class="p-5">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl" :class="verifyAction === 'approve' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : verifyAction === 'reject' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'">
                        <svg v-if="verifyAction === 'approve'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" /></svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18.5A1.7 1.7 0 0 0 3.3 21h17.4a1.7 1.7 0 0 0 1.5-2.5L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
                    </div>
                    <h2 id="document-review-confirm-title" class="mt-4 text-base font-black text-slate-900 dark:text-white">
                        {{ verifyAction === 'approve' ? 'Approve this document?' : verifyAction === 'reject' ? 'Reject this document?' : 'Request a replacement document?' }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        {{ verifyAction === 'approve' ? 'This will mark the document as verified.' : verifyAction === 'reject' ? 'The agency will be notified that this document was rejected.' : 'The agency will be asked to replace this document.' }}
                    </p>
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ verifyingDocument.original_name || verifyingDocument.label || verifyingDocument.doc_type_label }}</p>
                    </div>
                    <div v-if="verifyAction !== 'approve'" class="mt-4">
                        <label for="document-review-note-confirm" class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Note to agency <span class="font-medium normal-case">(required)</span></label>
                        <textarea id="document-review-note-confirm" v-model="verifyNote" rows="3" maxlength="2000" :disabled="verifySaving" :aria-invalid="!!verifyNoteError" @input="verifyNoteError = ''" placeholder="Explain what needs to be corrected or replaced..." class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"></textarea>
                        <p v-if="verifyNoteError" class="mt-1 text-xs font-semibold text-rose-600">{{ verifyNoteError }}</p>
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">The agency will receive this note with the {{ verifyAction === 'reject' ? 'rejection' : 'resubmission request' }}.</p>
                    </div>
                </div>
                <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/50">
                    <button type="button" @click="cancelVerifyConfirmation" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                    <button type="button" @click="submitVerify" class="rounded-xl px-4 py-2.5 text-xs font-bold text-white transition" :class="verifyAction === 'approve' ? 'bg-emerald-600 hover:bg-emerald-700' : verifyAction === 'reject' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700'">
                        {{ verifyAction === 'approve' ? 'Confirm approval' : verifyAction === 'reject' ? 'Notify agency & reject' : 'Notify agency & request resubmission' }}
                    </button>
                </footer>
            </section>
        </div>

        <div v-if="verifySaving" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" role="status" aria-live="polite">
            <div class="w-full max-w-xs rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-2xl dark:border-slate-700 dark:bg-slate-800">
                <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600 dark:border-slate-600 dark:border-t-blue-400"></div>
                <p class="mt-4 text-sm font-black text-slate-900 dark:text-white">{{ verifyAction === 'approve' ? 'Approving document…' : verifyAction === 'reject' ? 'Rejecting document…' : 'Sending resubmission request…' }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Please wait while we save this review.</p>
            </div>
        </div>

        <div v-if="previewDocument" class="fixed inset-0 z-[100] flex flex-col bg-[#111214] text-white"
            role="presentation" @click.self="closeDocumentPreview">
            <section class="flex min-h-0 flex-1 flex-col" role="dialog" aria-modal="true"
                :aria-label="'Document preview: ' + previewDocument.name">
                <header class="z-10 flex h-14 shrink-0 items-center justify-between gap-4 border-b border-white/10 bg-[#1a1b1e] px-4 shadow-lg sm:px-6">
                    <h2 class="min-w-0 truncate text-sm font-semibold text-white/90">{{ previewDocument.name }}</h2>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <button type="button" @click="changePreviewZoom(-0.25)" :disabled="previewZoom <= 0.5" aria-label="Zoom out"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-xl text-white/80 hover:bg-white/10 disabled:opacity-35">−</button>
                        <button type="button" @click="previewZoom = 1; previewOffset = { x: 0, y: 0 }" aria-label="Reset zoom"
                            class="min-w-14 rounded-lg px-2 py-2 text-xs font-semibold text-white/75 hover:bg-white/10">{{ Math.round(previewZoom * 100) }}%</button>
                        <button type="button" @click="changePreviewZoom(0.25)" :disabled="previewZoom >= 3" aria-label="Zoom in"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-xl text-white/80 hover:bg-white/10 disabled:opacity-35">+</button>
                        <button type="button" @click="closeDocumentPreview" aria-label="Close document preview"
                            class="ml-2 flex h-9 w-9 items-center justify-center rounded-lg text-xl text-white/80 hover:bg-white/10">×</button>
                    </div>
                </header>
                <div class="flex min-h-0 flex-1 items-center justify-center overflow-auto bg-[#111214] p-3 sm:p-6">
                    <img v-if="previewIsImage" :src="previewDocument.url" :alt="previewDocument.name"
                        class="max-h-full max-w-full select-none object-contain transition-transform duration-100"
                        :class="draggingPreview ? 'cursor-grabbing' : previewZoom > 1 ? 'cursor-grab' : 'cursor-zoom-in'"
                        :style="{ transform: `translate(${previewOffset.x}px, ${previewOffset.y}px) scale(${previewZoom})` }"
                        @pointerdown="startPreviewDrag" @pointermove="movePreviewDrag" @pointerup="stopPreviewDrag" @pointercancel="stopPreviewDrag" />
                    <iframe v-else :src="previewDocument.url" :title="previewDocument.name"
                        class="h-full w-full border-0 bg-white transition-transform duration-150"
                        :style="{ transform: `scale(${previewZoom})`, transformOrigin: 'center center' }"></iframe>
                </div>
            </section>
        </div>

        <div v-if="showPendingChanges" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm" role="presentation" @click.self="showPendingChanges = false">
            <section class="w-full max-w-2xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="pending-changes-title">
                <header class="relative overflow-hidden border-b border-orange-100 bg-gradient-to-br from-orange-50 via-white to-amber-50 px-6 py-5 dark:border-slate-700 dark:from-slate-800 dark:via-slate-900 dark:to-slate-800 sm:px-7">
                    <div class="absolute -right-8 -top-12 h-36 w-36 rounded-full bg-orange-200/30 blur-2xl dark:bg-orange-500/10"></div>
                    <div class="relative flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-orange-500 text-white shadow-lg shadow-orange-500/20">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
                            </div>
                            <div>
                                <div class="mb-1 flex flex-wrap items-center gap-2">
                                    <h2 id="pending-changes-title" class="text-base font-black text-slate-900 dark:text-white">Agency correction requests</h2>
                                    <span class="rounded-full border border-orange-200 bg-orange-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-orange-700 dark:border-orange-800 dark:bg-orange-900/40 dark:text-orange-300">{{ pendingChangeRequests.length }} pending</span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-400">{{ worker.name }} <span class="text-slate-400">· {{ worker.code }}</span></p>
                            </div>
                        </div>
                        <button type="button" @click="showPendingChanges = false" aria-label="Close pending correction requests" class="rounded-xl p-2 text-slate-400 transition hover:bg-white/80 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-white">×</button>
                    </div>
                    <div class="relative mt-4 flex items-center gap-2 rounded-xl border border-orange-200/80 bg-white/70 px-3 py-2 dark:border-orange-900/60 dark:bg-slate-950/40">
                        <span class="h-2 w-2 rounded-full bg-orange-500 ring-4 ring-orange-100 dark:ring-orange-900/50"></span>
                        <p class="text-[11px] font-semibold text-orange-800 dark:text-orange-300">Waiting for the agency to apply these requests</p>
                    </div>
                </header>
                <div class="max-h-[55vh] space-y-3 overflow-y-auto bg-slate-50/70 px-5 py-5 dark:bg-slate-950/40 sm:px-7">
                    <article v-for="(request, index) in pendingChangeRequests" :key="index" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-700">
                            <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-[10px] font-black text-slate-600 dark:bg-slate-700 dark:text-slate-200">{{ index + 1 }}</span>
                            <h3 class="text-xs font-black uppercase tracking-wide text-slate-700 dark:text-slate-200">Request {{ index + 1 }}</h3>
                        </div>
                        <div class="whitespace-pre-line break-words px-4 py-3 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ request.trim() }}</div>
                    </article>
                </div>
                <footer class="flex justify-end gap-2 border-t border-slate-100 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-900 sm:px-7">
                    <button type="button" @click="showPendingChanges = false" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                </footer>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.worker-status-pending {
    color: #000000 !important;
    background-color: #eab308 !important;
    border-color: #a16207 !important;
}

.dark .worker-status-pending {
    color: #fde68a !important;
    background-color: #854d0e !important;
    border-color: #ca8a04 !important;
}
</style>
