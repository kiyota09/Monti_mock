<script setup lang="ts">
// Page 3: Agency detail (header actions + Overview / Users tabs)
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Pencil, Pause, Play, Ban } from 'lucide-vue-next';

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
        service_fee_type: string;
        service_fee_value: string | number | null;
        contact_name: string | null;
        contact_email: string | null;
        contact_phone: string | null;
        status: string;
        stored_status: string;
        expiring_soon: boolean;
        users: Array<{ id: number; name: string; email: string; is_active: boolean }>;
        activities: Array<{ id: number; date: string; action: string; details: string | null; actor: string; reason: string | null }>;
        workers: Array<{ id: number; code: string; name: string; position_skill: string | null; status: string }>;
        requests: Array<{ id: number; code: string; department: string; position_skill: string; needed: number; filled: number; status: string }>;
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

function statusBadge(s: string): string {
    const map: Record<string, string> = {
        Active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Pending: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        Suspended: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        Terminated: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
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
    if (!window.confirm(`Reset the password for ${u.name}? A new temporary password will be shown once.`)) return;
    router.post(route('hrm.agencies.users.reset-password', { agency: props.agency.id, user: u.id }), {}, { preserveScroll: true });
}

function toggleUser(u: any) {
    const verb = u.is_active ? 'deactivate' : 'reactivate';
    if (!window.confirm(`${verb === 'deactivate' ? 'Deactivate' : 'Reactivate'} the login for ${u.name}?`)) return;
    router.post(route('hrm.agencies.users.toggle-status', { agency: props.agency.id, user: u.id }), {}, { preserveScroll: true });
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
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">TIN</dt><dd class="font-semibold text-slate-700 dark:text-slate-200 text-right">{{ agency.tin || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Address</dt><dd class="font-semibold text-slate-700 dark:text-slate-200 text-right">{{ agency.address || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Contact person</dt><dd class="font-semibold text-slate-700 dark:text-slate-200 text-right">{{ agency.contact_name || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Email</dt><dd class="font-semibold text-slate-700 dark:text-slate-200 text-right">{{ agency.contact_email || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Phone</dt><dd class="font-semibold text-slate-700 dark:text-slate-200 text-right">{{ agency.contact_phone || '—' }}</dd></div>
                </dl>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Contract &amp; compliance</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Contract start</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ agency.contract_start || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Contract end</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ agency.contract_end || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">Service fee</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ feeLabel() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">DOLE number</dt><dd class="font-semibold text-slate-700 dark:text-slate-200 text-right">{{ agency.dole_number || '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-400">DOLE expiry</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ agency.dole_expiry || '—' }}</dd></div>
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
                        <span v-if="a.reason" class="text-slate-400 italic shrink-0">“{{ a.reason }}”</span>
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

                <!-- Confirm panel for adding a user -->
                <div v-if="confirmUserCreate" class="mt-3 p-4 rounded-xl bg-white dark:bg-slate-900 border-2 border-dashed border-slate-300 dark:border-slate-600">
                    <p class="text-sm font-black text-slate-900 dark:text-white">Create login for {{ userForm.name }} ({{ userForm.email }})?</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">A temporary password will be emailed to {{ userForm.email }}.</p>
                    <p v-if="userForm.errors.email" class="mt-1 text-xs text-rose-600">{{ userForm.errors.email }}</p>
                    <div class="mt-3 flex items-center justify-end gap-2">
                        <button @click="confirmUserCreate = false" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Cancel</button>
                        <button @click="submitUserCreate" :disabled="userForm.processing" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-60">
                            {{ userForm.processing ? 'Creating...' : 'Confirm creation' }}
                        </button>
                    </div>
                </div>
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
                        <p class="text-sm font-bold text-slate-900 dark:text-white">No users yet. Create the agency’s first login above.</p>
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
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Name</th>
                            <th class="py-3 px-4">Position / skill</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr
                            v-for="w in agency.workers"
                            :key="w.id"
                            @click="router.visit(route('hrm.workers.show', w.id))"
                            class="transition cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        >
                            <td class="py-3 px-4 text-xs font-mono font-bold text-slate-700 dark:text-slate-200">{{ w.code }}</td>
                            <td class="py-3 px-4 text-sm font-bold text-blue-600 hover:text-blue-700">{{ w.name }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ w.position_skill || '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase border bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600">{{ w.status }}</span>
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
                            <td class="py-3 px-4 text-center text-xs font-bold text-slate-700 dark:text-slate-200">{{ r.filled }} of {{ r.needed }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase border bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600">{{ r.status }}</span>
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
    </AuthenticatedLayout>
</template>
