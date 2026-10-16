<script setup lang="ts">
// Page 10: Manpower request detail (assign picker + progress + activity)
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Pencil } from 'lucide-vue-next';

const props = defineProps<{
    request: {
        id: number;
        code: string;
        department: string;
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
            assigned_at: string | null; assigned_by: string | null;
        }>;
        available_workers: Array<{ id: number; code: string; name: string; position_skill: string | null }>;
        proposals: Array<{
            id: number; worker_id: number; worker_code: string | null; worker_name: string | null;
            position_skill: string | null; worker_status: string | null; daily_rate: string | number | null;
            phone: string | null; gender: string | null; clearance_expiry: string | null;
            clearance_expired: boolean; status: string; proposed_by: string | null;
            proposed_at: string | null; review_reason: string | null; reviewed_by: string | null;
        }>;
        attendance_summary: Array<{
            worker_id: number; worker_code: string | null; worker_name: string | null;
            records: number; present: number; absent: number; late: number; hours: number; overtime_hours: number;
        }>;
        activities: Array<{ id: number; date: string; action: string; details: string | null; actor: string; reason: string | null }>;
    };
    permissions?: Record<string, string>;
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash || {});
const canEdit = computed(() => (props.permissions?.requests || 'edit') === 'edit');

const confirmAction = ref<'cancel' | null>(null);
const reason = ref('');
const reasonError = ref('');

const assignForm = useForm({ worker_id: '' as string | number });

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

function closeRequest() {
    if (!window.confirm('Close this filled request?')) return;
    router.post(route('hrm.manpower-requests.close', props.request.id), {}, { preserveScroll: true });
}

function assignWorker() {
    if (!assignForm.worker_id) return;
    assignForm.post(route('hrm.manpower-requests.assign', props.request.id), {
        preserveScroll: true,
        onSuccess: () => assignForm.reset(),
    });
}

function releaseWorker(a: any) {
    if (!window.confirm(`Release ${a.worker_name} from this request?`)) return;
    router.post(route('hrm.manpower-requests.release', { manpowerRequest: props.request.id, assignment: a.assignment_id }), {}, { preserveScroll: true });
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

function acceptProposal(id: number) {
    router.post(route('hrm.manpower-requests.proposals.accept', { manpowerRequest: props.request.id, proposal: id }), {}, {
        preserveScroll: true,
        onSuccess: () => { if (selectedProposal.value?.id === id) selectedProposal.value = null; },
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
    <Head :title="request.code + ' - Requests - Monti ERP'" />
    <AuthenticatedLayout>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('hrm.manpower-requests.index')" class="hover:text-blue-600">Requests</Link>
            <span>/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ request.code }}</span>
        </nav>

        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white leading-tight">
                    {{ request.code }} <span class="text-slate-400 font-bold">· {{ request.department }} · {{ request.position_skill }}</span>
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
            <p class="text-sm font-black text-slate-900 dark:text-white">Cancel request {{ request.code }}?</p>
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
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Request</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Department</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ request.department }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Position / skill</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ request.position_skill }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Headcount</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ request.needed }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Start date</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ request.start_date || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">End date</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ request.end_date || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Shift</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ request.shift || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Agency</dt><dd class="font-semibold text-slate-700 dark:text-slate-200 text-right">{{ request.agency || '—' }} <span class="text-slate-400">({{ request.agency_code || '—' }})</span></dd></div>
                    <div v-if="request.notes"><dt class="text-slate-400">Notes</dt><dd class="mt-1 text-slate-600 dark:text-slate-300 text-sm whitespace-pre-wrap">{{ request.notes }}</dd></div>
                </dl>
                <div class="mt-4">
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
                <form v-if="canAssign" @submit.prevent="assignWorker" class="mb-3 flex items-center gap-2">
                    <select v-model="assignForm.worker_id" class="flex-1 px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200">
                        <option value="">Select an available worker...</option>
                        <option v-for="w in request.available_workers" :key="w.id" :value="w.id">
                            {{ w.name }} ({{ w.code }}){{ w.position_skill ? ' · ' + w.position_skill : '' }}
                        </option>
                    </select>
                    <button type="submit" :disabled="!assignForm.worker_id || assignForm.processing" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-60 whitespace-nowrap">
                        Assign
                    </button>
                </form>
                <p v-if="assignForm.errors.worker_id" class="mb-2 text-xs text-rose-600">{{ assignForm.errors.worker_id }}</p>
                <p v-if="!canAssign && request.stored_status === 'Open' && request.agency_status !== 'Active'" class="mb-3 px-2 py-1.5 rounded-lg text-[11px] font-bold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                    No new workers can be assigned while the agency is not Active.
                </p>
                <div v-if="request.assigned.length === 0" class="text-xs text-slate-400">No workers assigned yet.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="a in request.assigned" :key="a.assignment_id" class="py-2 flex items-center justify-between gap-2 text-xs">
                        <div>
                            <Link :href="route('hrm.workers.show', a.worker_id)" class="font-bold text-blue-600 hover:text-blue-700">{{ a.worker_name }} <span class="font-mono text-slate-400">({{ a.worker_code }})</span></Link>
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
                                {{ p.worker_name }} <span class="font-mono text-slate-400">({{ p.worker_code }})</span>
                            </button>
                            <p class="text-slate-500 dark:text-slate-400">proposed {{ p.proposed_at || '' }}{{ p.proposed_by ? ' by ' + p.proposed_by : '' }}</p>
                            <p v-if="p.status !== 'Proposed'" class="text-slate-500 dark:text-slate-400">{{ p.status }}{{ p.reviewed_by ? ' by ' + p.reviewed_by : '' }}{{ p.review_reason ? ' — ' + p.review_reason : '' }}</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <span v-if="p.status !== 'Proposed'" :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', p.status === 'Accepted' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400']">{{ p.status }}</span>
                            <template v-if="p.status === 'Proposed' && canEdit">
                                <button @click="acceptProposal(p.id)" class="px-3 py-1.5 text-[11px] font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition">Accept</button>
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
                                <td class="py-2 px-3 text-xs font-bold text-slate-900 dark:text-white">{{ s.worker_name }} <span class="font-mono text-slate-400">({{ s.worker_code }})</span></td>
                                <td class="py-2 px-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ s.records }}</td>
                                <td class="py-2 px-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ s.present }} / {{ s.absent }} / {{ s.late }}</td>
                                <td class="py-2 px-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ s.hours }}{{ s.overtime_hours ? '+' + s.overtime_hours + ' OT' : '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 lg:col-span-2">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Activity (last 20)</h2>
                <div v-if="request.activities.length === 0" class="text-xs text-slate-400">No activity recorded yet.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="a in request.activities" :key="a.id" class="py-2 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 text-xs">
                        <span class="text-slate-400 whitespace-nowrap w-40 shrink-0">{{ a.date }}</span>
                        <span class="font-bold text-slate-800 dark:text-slate-100 shrink-0">{{ actionLabel(a.action) }}</span>
                        <span class="text-slate-500 dark:text-slate-400 flex-1">{{ a.details || '' }}</span>
                        <span class="text-slate-500 dark:text-slate-400 shrink-0">by {{ a.actor }}</span>
                        <span v-if="a.reason" class="text-slate-400 italic shrink-0">“{{ a.reason }}”</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Proposal worker-info modal -->
        <div v-if="selectedProposal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="closeProposalWorker">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg p-5 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">{{ selectedProposal.worker_name }}</h3>
                        <p class="text-xs font-mono text-slate-400">{{ selectedProposal.worker_code }}</p>
                    </div>
                    <button @click="closeProposalWorker" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="Close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span v-if="selectedProposal.worker_status" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase border bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">{{ selectedProposal.worker_status }}</span>
                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', selectedProposal.status === 'Accepted' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : selectedProposal.status === 'Rejected' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400']">Proposal: {{ selectedProposal.status }}</span>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Position / skill</dt>
                        <dd class="mt-0.5 font-bold text-slate-800 dark:text-slate-100">{{ selectedProposal.position_skill || '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Daily rate</dt>
                        <dd class="mt-0.5 font-bold text-slate-800 dark:text-slate-100">{{ selectedProposal.daily_rate ?? '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Phone</dt>
                        <dd class="mt-0.5 font-bold text-slate-800 dark:text-slate-100">{{ selectedProposal.phone || '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3">
                        <dt class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Gender</dt>
                        <dd class="mt-0.5 font-bold text-slate-800 dark:text-slate-100">{{ selectedProposal.gender || '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700 p-3 col-span-2">
                        <dt class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Clearance expiry</dt>
                        <dd class="mt-0.5 font-bold" :class="selectedProposal.clearance_expired ? 'text-rose-600' : 'text-slate-800 dark:text-slate-100'">
                            {{ selectedProposal.clearance_expiry || '—' }}
                            <span v-if="selectedProposal.clearance_expired" class="ml-1 text-[10px] font-black uppercase">(expired)</span>
                        </dd>
                    </div>
                </dl>

                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    Proposed {{ selectedProposal.proposed_at || '' }}{{ selectedProposal.proposed_by ? ' by ' + selectedProposal.proposed_by : '' }}
                    <span v-if="selectedProposal.status !== 'Proposed'"> · {{ selectedProposal.status }}{{ selectedProposal.reviewed_by ? ' by ' + selectedProposal.reviewed_by : '' }}{{ selectedProposal.review_reason ? ' — ' + selectedProposal.review_reason : '' }}</span>
                </p>

                <div class="mt-4 flex items-center justify-between gap-2">
                    <Link :href="route('hrm.workers.show', selectedProposal.worker_id)" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition">Open full worker profile →</Link>
                    <div v-if="selectedProposal.status === 'Proposed' && canEdit" class="flex items-center gap-2">
                        <button @click="acceptProposal(selectedProposal.id)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition">Accept</button>
                        <button @click="openRejectProposal(selectedProposal.id)" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition">Reject</button>
                    </div>
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
    </AuthenticatedLayout>
</template>
