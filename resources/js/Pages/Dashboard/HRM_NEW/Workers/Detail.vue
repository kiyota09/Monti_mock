<script setup lang="ts">
// Page 7: Worker detail (header + overview + assignments + activity)
import { ref, computed, watch } from 'vue';
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
        blacklist_reason: string | null;
        pending_note?: string | null;
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
    put('education', 'Education'); put('work_experience', 'Experience'); put('skills', 'Skills');
    put('trainings_certifications', 'Training'); put('position_skill', 'Position');
    put('years_experience', 'Years exp.'); put('proposed_department', 'Department');
    put('availability', 'Availability'); put('daily_rate', 'Daily rate');
    put('employment_arrangement', 'Arrangement'); put('gov_id_type', 'Gov ID type');
    put('gov_id_number', 'Gov ID no.'); put('clearance_expiry', 'Clearance expiry');
    put('emergency_name', 'Emergency name'); put('emergency_relationship', 'Emergency relation');
    put('emergency_phone', 'Emergency phone');
    return rows;
});

function refreshWorker() {
    router.reload({ only: ['worker'], onSuccess: () => {} });
}

const ovInput = 'w-full px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200';
const ovLabel = 'block mb-1 text-[10px] font-black uppercase tracking-wider text-slate-400';

const confirmAction = ref<'deactivate' | 'blacklist' | 'reject_pending' | 'request_changes' | null>(null);
const reason = ref('');
const reasonError = ref('');

function statusBadge(s: string): string {
    const map: Record<string, string> = {
        Available: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Deployed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        Inactive: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
        Blacklisted: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
        Pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        'For Correction': 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 border-orange-200 dark:border-orange-800',
    };
    return map[s] || 'bg-slate-100 text-slate-600 border-slate-200';
}

function openConfirm(action: 'deactivate' | 'blacklist' | 'reject_pending' | 'request_changes') {
    confirmAction.value = action;
    reason.value = '';
    reasonError.value = '';
    if (action === 'request_changes') {
        // Overview becomes input-filled for HR correction.
        syncOverviewFromWorker();
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

function approvePending() {
    router.post(route('hrm.workers.approve', props.worker.id), {}, { preserveScroll: true });
}

function submitConfirm() {
    if ((confirmAction.value === 'blacklist' || confirmAction.value === 'reject_pending' || confirmAction.value === 'request_changes') && !reason.value.trim()) {
        reasonError.value = confirmAction.value === 'request_changes' ? 'Tell the agency what to correct or complete.' : 'A reason is required.';
        return;
    }
    if (confirmAction.value === 'request_changes') {
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

function liftBlacklist() {
    if (!window.confirm('Lift the blacklist for this worker? Only HR/IT may do this.')) return;
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
    };
    return map[a] || a;
}

const verifyingId = ref<number | null>(null);
const verifyStatus = ref('Verified');
const verifyNote = ref('');

function openVerify(docId: number, current: string) {
    verifyingId.value = docId;
    verifyStatus.value = current || 'Verified';
    verifyNote.value = '';
}

function submitVerify(docId: number) {
    router.post(route('hrm.workers.documents.verify', docId), {
        status: verifyStatus.value,
        review_note: verifyNote.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { verifyingId.value = null; verifyNote.value = ''; },
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
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('hrm.workers.index')" class="hover:text-blue-600">Workers</Link>
            <span>/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ worker.code }}</span>
        </nav>

        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-lg shrink-0">
                    {{ worker.name?.charAt(0) || 'W' }}
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white leading-tight">{{ worker.name }}</h1>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <span class="text-xs font-mono text-slate-400">{{ worker.code }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ worker.agency || 'No agency' }}</span>
                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', statusBadge(worker.status)]">{{ worker.status }}</span>
                    </div>
                </div>
            </div>
            <div v-if="canEdit" class="flex flex-wrap items-center gap-2 shrink-0">
                <button
                    v-if="worker.status === 'Available'"
                    @click="openConfirm('deactivate')"
                    class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition"
                >
                    Reject
                </button>
                <button
                    v-if="worker.status === 'Inactive'"
                    @click="reactivate"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition"
                >
                    Re-open
                </button>
                <button
                    v-if="worker.status === 'Pending'"
                    @click="approvePending"
                    :disabled="hasPendingDocs"
                    :title="hasPendingDocs ? 'All supporting documents must be Verified before approving.' : ''"
                    :class="[
                        'px-4 py-2 rounded-xl text-xs font-bold transition',
                        hasPendingDocs
                            ? 'bg-slate-300 dark:bg-slate-700 text-slate-500 dark:text-slate-400 cursor-not-allowed'
                            : 'bg-emerald-600 hover:bg-emerald-700 text-white',
                    ]"
                >
                    Approve
                </button>
                <button
                    v-if="worker.status === 'Pending' || worker.status === 'Available' || worker.status === 'Deployed' || worker.status === 'For Correction'"
                    @click="openConfirm('request_changes')"
                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs font-bold transition"
                >
                    Request changes
                </button>
                <button
                    v-if="worker.status === 'Pending' || worker.status === 'For Correction'"
                    @click="openConfirm('reject_pending')"
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition"
                >
                    Reject
                </button>
                <button
                    v-if="worker.status !== 'Blacklisted'"
                    @click="openConfirm('blacklist')"
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition"
                >
                    Blacklist
                </button>
                <button
                    v-if="worker.status === 'Blacklisted'"
                    @click="liftBlacklist"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition"
                >
                    Lift blacklist
                </button>
            </div>
        </div>

        <div v-if="flash.success" class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-sm font-semibold text-rose-700 dark:text-rose-400">
            {{ flash.error }}
        </div>

        <div v-if="worker.pending_note && (worker.status === 'For Correction' || worker.status === 'Deployed')" class="mb-4 p-3 rounded-xl bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 text-xs text-orange-800 dark:text-orange-300">
            <span class="font-black">Waiting on agency:</span> {{ worker.pending_note || 'Correction requested.' }}
        </div>
        <div v-if="worker.status === 'Blacklisted' && worker.blacklist_reason" class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-400">
            <span class="font-black">Blacklisted:</span> {{ worker.blacklist_reason }}
        </div>
        <div v-if="worker.clearance_expired" class="mb-4 p-3 rounded-xl bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-800 text-xs font-bold text-yellow-800 dark:text-yellow-300">
            Work permit / medical clearance has expired — this worker cannot be assigned.
        </div>

        <div v-if="confirmAction && canEdit" class="mb-4 p-4 rounded-xl bg-white dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-600">
            <p class="text-sm font-black text-slate-900 dark:text-white">
                {{ confirmAction === 'blacklist' ? 'Blacklist worker' : confirmAction === 'reject_pending' ? 'Reject worker' : confirmAction === 'request_changes' ? 'Request corrections from agency' : 'Reject worker' }} — {{ worker.name }}?
            </p>
            <p v-if="confirmAction === 'request_changes'" class="mt-1 text-[11px] text-slate-500">The Overview card below becomes editable — propose the corrections there. Nothing is saved on HR side; the agency portal applies them.</p>
            <div v-if="confirmAction === 'request_changes'" class="mt-2 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <p class="px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-500 bg-slate-50 dark:bg-slate-900">Requested changes (current → proposed) — {{ overviewDiff.length }}</p>
                <ul v-if="overviewDiff.length" class="px-3 py-2 text-xs space-y-1 max-h-40 overflow-y-auto">
                    <li v-for="r in overviewDiff" :key="r.label" class="flex flex-wrap gap-x-1.5"><span class="font-bold text-slate-700 dark:text-slate-200">{{ r.label }}:</span><span class="text-slate-400 line-through">{{ r.old }}</span><span class="text-slate-400">→</span><span class="font-bold text-emerald-600">{{ r.next }}</span></li>
                </ul>
                <p v-else class="px-3 py-2 text-[11px] text-slate-400">No field edits yet — edit the Overview below or just send a note.</p>
            </div>
            <textarea
                v-model="reason"
                rows="2"
                :placeholder="confirmAction === 'request_changes' ? 'What should the agency correct or complete? (required)' : (confirmAction === 'blacklist' || confirmAction === 'reject_pending') ? 'Reason (required)' : 'Reason (optional)'"
                class="mt-3 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200"
            ></textarea>
            <p v-if="reasonError" class="mt-1 text-xs text-rose-600">{{ reasonError }}</p>
            <div class="mt-3 flex items-center justify-end gap-2">
                <button @click="closeConfirm" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
                <button @click="submitConfirm" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition">Confirm</button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white">Overview</h2>
                    <div v-if="canEdit" class="flex items-center gap-1.5 shrink-0">
                        <button @click="refreshWorker" title="Pull latest agency changes" class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700">Refresh</button>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mb-3">
                    <span v-if="worker.last_agency_update">Agency update: {{ worker.last_agency_update }}</span>
                    <span v-if="worker.updated_at"> · Last change: {{ worker.updated_at }}</span>
                    <span v-else>Synced with agency portal — Refresh pulls the latest submission.</span>
                    <span v-if="editingOverview" class="block mt-1 font-bold text-orange-500">Proposing via Request changes — your edits are sent as requests; the agency applies them, then Confirm.</span>
                </p>
                <!-- Read mode -->
                <dl v-if="!editingOverview" class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Agency</dt><dd class="font-semibold text-slate-700 dark:text-slate-200 text-right">{{ worker.agency || '—' }} <span class="text-slate-400">({{ worker.agency_code || '—' }})</span></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Position / skill</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.position_skill || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Years experience</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.years_experience ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Proposed dept.</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.proposed_department || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Availability</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.availability || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Arrangement</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.employment_arrangement || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Daily rate</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.daily_rate !== null ? '₱' + Number(worker.daily_rate).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Birth date</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.birth_date || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Gender</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.gender || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Phone</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.phone || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Email</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.email || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Address</dt><dd class="font-semibold text-slate-700 dark:text-slate-200 text-right">{{ worker.address || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Government ID</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ (worker.gov_id_type ? worker.gov_id_type + ' · ' : '') + (worker.gov_id_number || '—') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Clearance expiry</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.clearance_expiry || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Emergency contact</dt><dd class="font-semibold text-slate-700 dark:text-slate-200 text-right">{{ worker.emergency_name || '—' }}{{ worker.emergency_relationship ? ' (' + worker.emergency_relationship + ')' : '' }}{{ worker.emergency_phone ? ' · ' + worker.emergency_phone : '' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Consent</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ worker.consent_given ? 'Yes' + (worker.consent_at ? ' · ' + worker.consent_at : '') : '—' }}</dd></div>
                    <div v-if="worker.resume_url" class="flex justify-between gap-4"><dt class="text-slate-400">Resume</dt><dd class="font-semibold text-right"><a :href="worker.resume_url" target="_blank" class="text-blue-600 hover:underline">{{ worker.resume_original_name || 'View resume' }}</a></dd></div>
                </dl>
                <div v-if="!editingOverview && (worker.education || worker.work_experience || worker.skills || worker.trainings_certifications)" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 space-y-2 text-xs">
                    <div v-if="worker.education"><p class="font-black uppercase text-[10px] text-slate-400">Education</p><p class="text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ worker.education }}</p></div>
                    <div v-if="worker.work_experience"><p class="font-black uppercase text-[10px] text-slate-400">Experience</p><p class="text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ worker.work_experience }}</p></div>
                    <div v-if="worker.skills"><p class="font-black uppercase text-[10px] text-slate-400">Skills</p><p class="text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ worker.skills }}</p></div>
                    <div v-if="worker.trainings_certifications"><p class="font-black uppercase text-[10px] text-slate-400">Training / Certifications</p><p class="text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ worker.trainings_certifications }}</p></div>
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
                        <input v-model="overviewForm.years_experience" type="number" min="0" max="60" step="0.5" :class="[ovInput, worker.status === 'Deployed' && 'bg-slate-100 dark:bg-slate-900 opacity-70']" :readonly="worker.status === 'Deployed'" />
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
                        <label :class="ovLabel">Clearance expiry</label>
                        <input v-model="overviewForm.clearance_expiry" type="date" :class="ovInput" />
                    </div>
                    <div>
                        <label :class="ovLabel">Emergency name</label>
                        <input v-model="overviewForm.emergency_name" type="text" :class="ovInput" />
                    </div>
                    <div>
                        <label :class="ovLabel">Emergency relationship</label>
                        <input v-model="overviewForm.emergency_relationship" type="text" :class="ovInput" />
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

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-1">Documents — HR verification</h2>
                <p class="text-[11px] text-slate-400 mb-3">Mark each file as Pending, Verified, Rejected, or Expired. Approval requires all files Verified — no Pending / Rejected / Expired files.</p>
                <p v-if="hasPendingDocs && worker.status === 'Pending'" class="mb-3 p-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-[11px] font-bold text-amber-700 dark:text-amber-400">
                    Verify every document before approving — {{ (worker.documents || []).filter((d: any) => d.status === 'Pending').length }} still Pending.
                </p>
                <div v-if="!worker.documents || worker.documents.length === 0" class="text-xs text-slate-400">No supporting files uploaded yet.</div>
                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="d in worker.documents" :key="d.id" class="py-3 flex flex-col gap-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <a :href="d.url" target="_blank" class="text-xs font-bold text-blue-600 hover:underline truncate">{{ d.original_name || d.label || d.doc_type_label }}</a>
                                <p class="text-[11px] text-slate-400">{{ d.doc_type_label }}{{ d.label && d.label !== d.original_name ? ' · ' + d.label : '' }}{{ d.issue_date ? ' · start ' + d.issue_date : '' }}{{ d.expiry_date ? ' · exp ' + d.expiry_date : '' }}{{ d.verified_by ? ' · by ' + d.verified_by : '' }}</p>
                                <p v-if="d.review_note" class="text-[11px] italic text-slate-400">“{{ d.review_note }}”</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', docBadge(d.status)]">{{ d.status }}</span>
                                <button v-if="canEdit" @click="openVerify(d.id, d.status)" class="px-3 py-1.5 text-[11px] font-bold rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700">Verify</button>
                            </div>
                        </div>
                        <div v-if="verifyingId === d.id && canEdit" class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row gap-2 sm:items-center">
                            <select v-model="verifyStatus" class="px-3 py-2 text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg">
                                <option v-for="s in (worker.document_statuses || ['Pending','Verified','Rejected','Expired'])" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <input v-model="verifyNote" type="text" placeholder="Review note (optional)" class="flex-1 px-3 py-2 text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg" />
                            <div class="flex gap-2">
                                <button @click="verifyingId = null" class="px-3 py-2 text-[11px] font-bold rounded-lg border border-slate-200 dark:border-slate-700">Cancel</button>
                                <button @click="submitVerify(d.id)" class="px-3 py-2 text-[11px] font-bold rounded-lg bg-blue-600 text-white hover:bg-blue-700">Save</button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
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

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 lg:col-span-2">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Attendance history (last 30)</h2>
                <div v-if="worker.attendance_history.length === 0" class="text-xs text-slate-400">No attendance records yet.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[520px]">
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
                            <tr v-for="h in worker.attendance_history" :key="h.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
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

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 lg:col-span-2">
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
    </AuthenticatedLayout>
</template>
