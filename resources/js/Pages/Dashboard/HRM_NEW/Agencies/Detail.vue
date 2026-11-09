<script setup lang="ts">
import { confirmModal } from '@/lib/feedback';
// Page 3: Agency detail (header actions + Overview / Users tabs)
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Pencil, Pause, Play, Ban, Star } from 'lucide-vue-next';

const props = defineProps<{
    agency: {
        id: number;
        code: string;
        name: string;
        tin: string | null;
        address: string | null;
        dole_number: string | null;
        dole_expiry: string | null;
        contract_start: string | null;
        contract_end: string | null;
        billing_cycle: string | null;
        payment_terms: string | null;
        payment_custom_terms: string | null;
        service_fee_type: string;
        service_fee_value: string | number | null;
        ot_multiplier: number | string | null;
        contact_name: string | null;
        contact_email: string | null;
        contact_phone: string | null;
        status: string;
        stored_status: string;
        expiring_soon: boolean;
        users: Array<{ id: number; name: string; email: string; is_active: boolean }>;
        activities: Array<{ id: number; date: string; action: string; details: string | null; actor: string; reason: string | null }>;
        workers: Array<{ id: number; code: string; name: string; position_skill: string | null; status: string }>;
        requests: Array<{ id: number; code: string; department: string; position_skill: string; start_date: string | null; end_date: string | null; is_ended: boolean; needed: number; filled: number; status: string }>;
        attendance: Array<{ id: number; date: string; worker_name: string | null; worker_code: string | null; request_code: string | null; status: string; approval: string }>;
        statements: Array<{ id: number; code: string; period: string; total: string | number; status: string }>;
    };
    permissions?: Record<string, string>;
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash || {});
const canEdit = computed(() => (props.permissions?.agencies || 'edit') === 'edit');

const tab = ref<'overview' | 'users' | 'workers' | 'requests' | 'attendance' | 'billing'>('overview');
const confirmAction = ref<'suspend' | 'terminate' | null>(null);
const reason = ref('');
const reasonError = ref('');
const confirmUserCreate = ref(false);
const confirmUserAction = ref<{ type: 'reset' | 'toggle'; user: { id: number; name: string; is_active: boolean } } | null>(null);
const userActionProcessing = ref(false);
const reportTargetWorker = ref<{ id: number; name: string } | null>(null);
const workerReportForm = useForm({ reason: '', rating: 0 });

function openWorkerReport(worker: { id: number; name: string }) {
    reportTargetWorker.value = worker;
    workerReportForm.reset();
    workerReportForm.clearErrors();
}

function submitWorkerReport() {
    if (!reportTargetWorker.value) return;
    workerReportForm.post(route('hrm.agencies.workers.report', {
        agency: props.agency.id,
        worker: reportTargetWorker.value.id,
    }), {
        preserveScroll: true,
        onSuccess: () => {
            reportTargetWorker.value = null;
            workerReportForm.reset();
        },
    });
}

function statusBadge(s: string): string {
    const map: Record<string, string> = {
        Active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Pending: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        Suspended: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        Terminated: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
        Available: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Deployed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        Inactive: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
        Blacklisted: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
        'For Correction': 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 border-orange-200 dark:border-orange-800',
    };
    return map[s] || 'bg-slate-100 text-slate-600 border-slate-200';
}

function workerStatusBadge(s: string): string {
    const map: Record<string, string> = {
        Available: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Deployed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        Pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        Inactive: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
        Blacklisted: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
        'For Correction': 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 border-orange-200 dark:border-orange-800',
    };
    return map[s] || 'bg-slate-100 text-slate-600 border-slate-200';
}

function feeLabel(): string {
    const v = Number(props.agency.service_fee_value || 0);
    if (props.agency.service_fee_type === 'fixed') return '₱' + v.toLocaleString(undefined, { minimumFractionDigits: 2 });
    return v + '% of labor cost';
}

function openConfirm(action: 'suspend' | 'terminate') {
    confirmAction.value = action;
    reason.value = '';
    reasonError.value = '';
}

function submitConfirm() {
    if (!reason.value.trim()) {
        reasonError.value = 'A reason is required.';
        return;
    }
    const routeName = confirmAction.value === 'suspend' ? 'hrm.agencies.suspend' : 'hrm.agencies.terminate';
    router.post(route(routeName, props.agency.id), { reason: reason.value }, {
        preserveScroll: true,
        onSuccess: () => { confirmAction.value = null; reason.value = ''; },
    });
}

function reactivate() {
    router.post(route('hrm.agencies.reactivate', props.agency.id), {}, { preserveScroll: true });
}

// --- Users tab: add-user form (with confirmation) ---
const userForm = useForm({ name: '', email: '' });
function openUserConfirm() {
    userForm.clearErrors();
    if (!userForm.name.trim() || !userForm.email.trim()) {
        if (!userForm.name.trim()) userForm.setError('name', 'Full name is required.');
        if (!userForm.email.trim()) userForm.setError('email', 'Email is required.');
        return;
    }
    confirmUserCreate.value = true;
}
function submitUserCreate() {
    userForm.post(route('hrm.agencies.users.store', props.agency.id), {
        preserveScroll: true,
        onSuccess: () => {
            userForm.reset();
            confirmUserCreate.value = false;
        },
    });
}

function resetPassword(u: any) {
    confirmUserAction.value = { type: 'reset', user: u };
}

function toggleUser(u: any) {
    confirmUserAction.value = { type: 'toggle', user: u };
}

function submitUserAction() {
    if (!confirmUserAction.value) return;
    const action = confirmUserAction.value;
    userActionProcessing.value = true;
    const url = action.type === 'reset'
        ? route('hrm.agencies.users.reset-password', { agency: props.agency.id, user: action.user.id })
        : route('hrm.agencies.users.toggle-status', { agency: props.agency.id, user: action.user.id });
    router.post(url, {}, {
        preserveScroll: true,
        onSuccess: () => { confirmUserAction.value = null; },
        onFinish: () => { userActionProcessing.value = false; },
    });
}

function actionLabel(a: string): string {
    const map: Record<string, string> = {
        created: 'Created', updated: 'Updated', suspended: 'Suspended', reactivated: 'Reactivated',
        terminated: 'Terminated', user_added: 'User added', user_deactivated: 'User deactivated',
        user_reactivated: 'User reactivated', password_reset: 'Password reset',
    };
    return map[a] || a;
}
</script>

<template>
    <Head :title="agency.name + ' - Agencies - Monti ERP'" />
    <AuthenticatedLayout>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('hrm.agencies.index')" class="hover:text-blue-600">Agencies</Link>
            <span>/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ agency.code }}</span>
        </nav>

        <!-- Header -->
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-lg shrink-0">
                    {{ agency.name?.charAt(0) || 'A' }}
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white leading-tight">{{ agency.name }}</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs font-mono text-slate-400">{{ agency.code }}</span>
                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', statusBadge(agency.status)]">{{ agency.status }}</span>
                    </div>
                </div>
            </div>
            <div v-if="canEdit" class="flex flex-wrap items-center gap-2 shrink-0">
                <Link
                    :href="route('hrm.agencies.edit', agency.id)"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                >
                    <Pencil class="w-3.5 h-3.5" /> Edit
                </Link>
                <button
                    v-if="agency.stored_status === 'Active'"
                    @click="openConfirm('suspend')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition"
                >
                    <Pause class="w-3.5 h-3.5" /> Suspend
                </button>
                <button
                    v-if="agency.stored_status === 'Suspended'"
                    @click="reactivate"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition"
                >
                    <Play class="w-3.5 h-3.5" /> Reactivate
                </button>
                <button
                    v-if="agency.stored_status !== 'Terminated'"
                    @click="openConfirm('terminate')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition"
                >
                    <Ban class="w-3.5 h-3.5" /> Terminate contract
                </button>
            </div>
        </div>

        <!-- Green success message after an action -->
        <div v-if="flash.success" class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-sm font-semibold text-rose-700 dark:text-rose-400">
            {{ flash.error }}
        </div>

        <!-- Confirm panel for Suspend / Terminate (reason required) -->
        <div v-if="confirmAction && canEdit" class="mb-4 p-4 rounded-xl bg-white dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-600">
            <p class="text-sm font-black text-slate-900 dark:text-white">
                {{ confirmAction === 'suspend' ? 'Suspend agency' : 'Terminate contract' }} — {{ agency.name }}?
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ confirmAction === 'suspend'
                    ? 'Suspended agencies keep their records, but all of their users are blocked from logging in until reactivation.'
                    : 'Termination deactivates every user of this agency. All records are kept.' }}
            </p>
            <textarea
                v-model="reason"
                rows="2"
                placeholder="Reason (required)"
                class="mt-3 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200"
            ></textarea>
            <p v-if="reasonError" class="mt-1 text-xs text-rose-600">{{ reasonError }}</p>
            <div class="mt-3 flex items-center justify-end gap-2">
                <button @click="confirmAction = null" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
                <button
                    @click="submitConfirm"
                    :class="['px-4 py-2 text-white rounded-xl text-xs font-bold transition', confirmAction === 'suspend' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-rose-600 hover:bg-rose-700']"
                >
                    Confirm {{ confirmAction === 'suspend' ? 'suspension' : 'termination' }}
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-4 flex items-center gap-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-1 w-fit">
            <button
                @click="tab = 'overview'"
                :class="['px-4 py-2 rounded-lg text-xs font-bold transition', tab === 'overview' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']"
            >
                Overview
            </button>
            <button
                @click="tab = 'users'"
                :class="['px-4 py-2 rounded-lg text-xs font-bold transition', tab === 'users' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']"
            >
                Users ({{ agency.users.length }})
            </button>
            <button
                @click="tab = 'workers'"
                :class="['px-4 py-2 rounded-lg text-xs font-bold transition', tab === 'workers' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']"
            >
                Workers ({{ agency.workers.length }})
            </button>
            <button
                @click="tab = 'requests'"
                :class="['px-4 py-2 rounded-lg text-xs font-bold transition', tab === 'requests' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']"
            >
                Requests ({{ agency.requests.length }})
            </button>
            <button
                @click="tab = 'attendance'"
                :class="['px-4 py-2 rounded-lg text-xs font-bold transition', tab === 'attendance' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']"
            >
                Attendance
            </button>
            <button
                @click="tab = 'billing'"
                :class="['px-4 py-2 rounded-lg text-xs font-bold transition', tab === 'billing' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']"
            >
                Billing ({{ agency.statements.length }})
            </button>
        </div>

        <!-- Tab 1: Overview -->
        <div v-if="tab === 'overview'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Company</h2>
                <dl class="space-y-2 text-sm">
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">TIN</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.tin || '—' }}</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">Address</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.address || '—' }}</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">Contact person</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.contact_name || '—' }}</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">Email</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.contact_email || '—' }}</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">Phone</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.contact_phone || '—' }}</dd></div>
                </dl>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Contract &amp; compliance</h2>
                <dl class="space-y-2 text-sm">
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">Contract start</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.contract_start || '—' }}</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">Contract end</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.contract_end || '—' }}</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">Billing cycle</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.billing_cycle || '—' }}</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">Payment terms</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.payment_terms === 'Custom terms' && agency.payment_custom_terms ? agency.payment_custom_terms : (agency.payment_terms || '—') }}</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">Service fee</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ feeLabel() }}</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">Overtime rate</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">×{{ Number(agency.ot_multiplier || 1.25) }} of hourly</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">DOLE number</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.dole_number || '—' }}</dd></div>
                    <div class="grid grid-cols-[120px_minmax(0,1fr)] items-start gap-4 pb-2 border-b-2 border-slate-200 dark:border-slate-600 last:border-0"><dt class="text-slate-400">DOLE expiry</dt><dd class="min-w-0 break-words text-left font-semibold text-slate-700 dark:text-slate-200">{{ agency.dole_expiry || '—' }}</dd></div>
                </dl>
                <p v-if="agency.expiring_soon" class="mt-3 px-2 py-1.5 rounded-lg text-[11px] font-bold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                    Contract or DOLE registration expiring within 30 days.
                </p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 lg:col-span-2">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Activity (last 20)</h2>
                <div v-if="agency.activities.length === 0" class="text-xs text-slate-400">No activity recorded yet.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="a in agency.activities" :key="a.id" class="py-2 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 text-xs">
                        <span class="text-slate-400 whitespace-nowrap w-40 shrink-0">{{ a.date }}</span>
                        <span class="font-bold text-slate-800 dark:text-slate-100 shrink-0">{{ actionLabel(a.action) }}</span>
                        <span class="text-slate-500 dark:text-slate-400 flex-1">{{ a.details || '' }}</span>
                        <span class="text-slate-500 dark:text-slate-400 shrink-0">by {{ a.actor }}</span>
                        <span v-if="a.reason" class="text-slate-400 italic shrink-0">â€œ{{ a.reason }}â€</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Tab 2: Users -->
        <div v-if="tab === 'users'" class="space-y-4">
            <div v-if="canEdit" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Add agency user</h2>
                <form @submit.prevent="openUserConfirm" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Full name <span class="text-red-500">*</span></label>
                        <input v-model="userForm.name" type="text" placeholder="Full name" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200" />
                        <p v-if="userForm.errors.name" class="mt-1 text-xs text-rose-600">{{ userForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Email <span class="text-red-500">*</span></label>
                        <input v-model="userForm.email" type="email" placeholder="user@agency.com" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200" />
                        <p v-if="userForm.errors.email" class="mt-1 text-xs text-rose-600">{{ userForm.errors.email }}</p>
                    </div>
                    <button type="submit" :disabled="userForm.processing" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 disabled:opacity-60">
                        {{ userForm.processing ? 'Creating...' : 'Create user' }}
                    </button>
                </form>
                <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">A temporary password will be emailed to the new login.</p>

            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[560px]">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-3 px-4">Name</th>
                                <th class="py-3 px-4">Email</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr v-for="u in agency.users" :key="u.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ u.name }}</td>
                                <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ u.email }}</td>
                                <td class="py-3 px-4 text-center">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', u.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600']">
                                        {{ u.is_active ? 'Active' : 'Deactivated' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div v-if="canEdit" class="flex items-center justify-end gap-1">
                                        <button @click="resetPassword(u)" class="px-3 py-1.5 text-[11px] font-bold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition">Reset password</button>
                                        <button @click="toggleUser(u)" :class="['px-3 py-1.5 text-[11px] font-bold rounded-lg transition', u.is_active ? 'text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20' : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20']">
                                            {{ u.is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="agency.users.length === 0" class="py-10 text-center">
                        <p class="text-sm font-bold text-slate-900 dark:text-white">No users yet. Create the agencyâ€™s first login above.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Workers (this agency's workers) -->
        <div v-if="tab === 'workers'" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[560px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Name</th>
                            <th class="py-3 px-4">Position / skill</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th v-if="canEdit" class="py-3 px-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr
                            v-for="w in agency.workers"
                            :key="w.id"
                            @click="router.visit(route('hrm.workers.show', w.id))"
                            class="transition cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        >
                            <td class="py-3 px-4 text-sm font-bold text-blue-600 hover:text-blue-700">{{ w.name }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ w.position_skill || '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', workerStatusBadge(w.status)]">{{ w.status }}</span>
                            </td>
                            <td v-if="canEdit" class="py-2 px-3 text-center">
                                <button type="button" @click.stop="openWorkerReport(w)" class="rounded-lg border border-rose-200 px-3 py-1.5 text-[11px] font-bold text-rose-700 transition hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-900/20">Report</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="agency.workers.length === 0" class="py-10 text-center">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">No workers supplied by this agency yet.</p>
                </div>
            </div>
        </div>

        <!-- Tab 4: Requests (this agency's requests) -->
        <div v-if="tab === 'requests'" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[640px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Department</th>
                            <th class="py-3 px-4">Position</th>
                            <th class="py-3 px-4">Request dates</th>
                            <th class="py-3 px-4 text-center">Filled</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr
                            v-for="r in agency.requests"
                            :key="r.id"
                            @click="router.visit(route('hrm.manpower-requests.show', r.id))"
                            class="transition cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        >
                            <td class="py-3 px-4 text-xs font-mono font-bold text-slate-700 dark:text-slate-200">{{ r.code }}</td>
                            <td class="py-3 px-4 text-sm font-bold text-blue-600 hover:text-blue-700">{{ r.department }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ r.position_skill }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">
                                <span v-if="r.start_date || r.end_date">
                                    {{ r.start_date || '—' }}<template v-if="r.end_date"> – {{ r.end_date }}</template>
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td class="py-3 px-4 text-center text-xs font-bold text-slate-700 dark:text-slate-200">{{ r.filled }} of {{ r.needed }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', r.is_ended ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300 border-rose-200 dark:border-rose-800' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600']">{{ r.is_ended ? 'Not available' : r.status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="agency.requests.length === 0" class="py-10 text-center">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">No manpower requests for this agency yet.</p>
                </div>
            </div>
        </div>

        <!-- Tab 5: Attendance (latest 50 records) -->
        <div v-if="tab === 'attendance'" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[640px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Worker</th>
                            <th class="py-3 px-4">Request</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-center">Approval</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="t in agency.attendance" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="py-3 px-4 text-xs font-bold text-slate-700 dark:text-slate-200">{{ t.date }}</td>
                            <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ t.worker_name }} <span class="font-mono text-[10px] text-slate-400">({{ t.worker_code }})</span></td>
                            <td class="py-3 px-4 text-xs font-mono text-slate-600 dark:text-slate-300">{{ t.request_code || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ t.status }}</td>
                            <td class="py-3 px-4 text-center text-xs text-slate-600 dark:text-slate-300">{{ t.approval }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="agency.attendance.length === 0" class="py-10 text-center">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">No attendance recorded for this agency yet.</p>
                </div>
            </div>
        </div>

        <!-- Tab 6: Billing (statements) -->
        <div v-if="tab === 'billing'" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[560px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Period</th>
                            <th class="py-3 px-4 text-right">Total</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr
                            v-for="s in agency.statements"
                            :key="s.id"
                            @click="router.visit(route('hrm.billing.show', s.id))"
                            class="transition cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        >
                            <td class="py-3 px-4 text-xs font-mono font-bold text-slate-700 dark:text-slate-200">{{ s.code }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ s.period }}</td>
                            <td class="py-3 px-4 text-right text-xs font-black text-slate-900 dark:text-white">₱{{ Number(s.total || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
                            <td class="py-3 px-4 text-center text-xs text-slate-600 dark:text-slate-300">{{ s.status }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="agency.statements.length === 0" class="py-10 text-center">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">No billing statements for this agency yet.</p>
                </div>
            </div>
        </div>
        <div v-if="reportTargetWorker" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="presentation" @click.self="!workerReportForm.processing && (reportTargetWorker = null)">
            <form @submit.prevent="submitWorkerReport" class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="worker-report-title">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="worker-report-title" class="text-lg font-black text-slate-900 dark:text-white">Report worker</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ reportTargetWorker.name }}</p>
                    </div>
                    <button type="button" @click="reportTargetWorker = null" :disabled="workerReportForm.processing" aria-label="Close report dialog" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 disabled:opacity-50 dark:hover:bg-slate-800"><Ban class="h-4 w-4" /></button>
                </div>
                <fieldset class="mt-5">
                    <legend class="text-xs font-bold text-slate-700 dark:text-slate-200">Rating <span class="text-rose-600">*</span></legend>
                    <div class="mt-2 flex items-center gap-1" role="group" aria-label="Worker rating from one to five stars">
                        <button v-for="star in 5" :key="star" type="button" @click="workerReportForm.rating = star" :aria-label="`Rate ${star} out of 5 stars`" :aria-pressed="workerReportForm.rating === star" class="rounded p-1 focus:outline-none focus:ring-2 focus:ring-amber-400"><Star class="h-7 w-7" :class="workerReportForm.rating >= star ? 'fill-amber-400 text-amber-500' : 'text-slate-300'" /></button>
                        <span class="ml-2 text-xs text-slate-500">{{ workerReportForm.rating ? `${workerReportForm.rating} of 5` : 'Select a rating' }}</span>
                    </div>
                    <p v-if="workerReportForm.errors.rating" class="mt-1 text-xs text-rose-600">{{ workerReportForm.errors.rating }}</p>
                </fieldset>
                <label class="mt-4 block text-xs font-bold text-slate-700 dark:text-slate-200">Reason for report <span class="text-rose-600">*</span>
                    <textarea v-model="workerReportForm.reason" required minlength="3" maxlength="5000" rows="4" placeholder="Describe the reason for reporting this worker..." class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white p-3 text-sm font-normal text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" />
                </label>
                <p v-if="workerReportForm.errors.reason" class="mt-1 text-xs text-rose-600">{{ workerReportForm.errors.reason }}</p>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">The agency will be notified of this report and rating.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="reportTargetWorker = null" :disabled="workerReportForm.processing" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
                    <button type="submit" :disabled="workerReportForm.processing || workerReportForm.rating < 1 || !workerReportForm.reason.trim()" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50">{{ workerReportForm.processing ? 'Sending report...' : 'Submit report' }}</button>
                </div>
            </form>
        </div>
        <div v-if="confirmUserCreate" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="presentation" @click.self="!userForm.processing && (confirmUserCreate = false)">
            <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="alertdialog" aria-modal="true" aria-labelledby="agency-user-create-title">
                <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"><Users class="h-5 w-5" /></div>
                <h2 id="agency-user-create-title" class="text-lg font-black text-slate-900 dark:text-white">Create agency login?</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Create login for <strong>{{ userForm.name }}</strong> (<strong>{{ userForm.email }}</strong>)?</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">A temporary password will be emailed to {{ userForm.email }}.</p>
                <p v-if="userForm.errors.email" class="mt-2 text-xs text-rose-600">{{ userForm.errors.email }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="confirmUserCreate = false" :disabled="userForm.processing" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
                    <button type="button" @click="submitUserCreate" :disabled="userForm.processing" class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-50">{{ userForm.processing ? 'Creating...' : 'Confirm creation' }}</button>
                </div>
            </section>
        </div>
        <div v-if="userForm.processing" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="status" aria-live="polite">
            <section class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-7 text-center shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                <span class="mx-auto block h-10 w-10 animate-spin rounded-full border-4 border-blue-100 border-t-blue-600 dark:border-slate-700 dark:border-t-blue-400" aria-hidden="true"></span>
                <h2 class="mt-4 text-base font-black text-slate-900 dark:text-white">Creating agency login</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Setting up {{ userForm.name }}. Please wait.</p>
            </section>
        </div>
        <div v-if="confirmUserAction" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="presentation" @click.self="!userActionProcessing && (confirmUserAction = null)">
            <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="alertdialog" aria-modal="true" aria-labelledby="agency-user-action-title">
                <h2 id="agency-user-action-title" class="text-lg font-black text-slate-900 dark:text-white">
                    {{ confirmUserAction.type === 'reset' ? 'Reset password?' : confirmUserAction.user.is_active ? 'Deactivate agency login?' : 'Reactivate agency login?' }}
                </h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    <template v-if="confirmUserAction.type === 'reset'">Reset the password for <strong>{{ confirmUserAction.user.name }}</strong>? A new temporary password will be shown once.</template>
                    <template v-else-if="confirmUserAction.user.is_active">Deactivate <strong>{{ confirmUserAction.user.name }}</strong>? They will no longer be able to sign in to the agency portal.</template>
                    <template v-else>Reactivate <strong>{{ confirmUserAction.user.name }}</strong> so they can sign in to the agency portal again.</template>
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="confirmUserAction = null" :disabled="userActionProcessing" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
                    <button type="button" @click="submitUserAction" :disabled="userActionProcessing" class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-50">
                        {{ userActionProcessing ? 'Please waitâ€¦' : confirmUserAction.type === 'reset' ? 'Reset password' : confirmUserAction.user.is_active ? 'Deactivate' : 'Reactivate' }}
                    </button>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
