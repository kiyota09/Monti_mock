<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { ArrowRightLeft, Check, Clock3, Mail, Search, Users, X } from 'lucide-vue-next';

const props = defineProps({
    department: { type: String, required: true },
    departmentLabel: { type: String, required: true },
    staff: { type: Array, default: () => [] },
    availableStaff: { type: Array, default: () => [] },
    targetRoles: { type: Array, default: () => [] },
    incomingRequests: { type: Array, default: () => [] },
    outgoingRequests: { type: Array, default: () => [] },
});

const page = usePage();
const { canEdit } = usePageAccess();
const canEditStaff = computed(() => canEdit('MAN', 'staff'));
const form = useForm({ staff_user_ids: [], target_role: '', reason: '' });
const reviewNotes = reactive({});
const activeTab = ref('team');
const selectedRequestIds = ref([]);
const pendingIncomingRequests = computed(() => props.incomingRequests.filter((request) => request.status === 'pending'));
const pendingOutgoingRequests = computed(() => props.outgoingRequests.filter((request) => request.status === 'pending'));
const incomingRequestHistory = computed(() => props.incomingRequests.filter((request) => request.status !== 'pending'));
const outgoingRequestHistory = computed(() => props.outgoingRequests.filter((request) => request.status !== 'pending'));
const pendingRequestCount = computed(() => pendingIncomingRequests.value.length + pendingOutgoingRequests.value.length);
const historyRequestCount = computed(() => incomingRequestHistory.value.length + outgoingRequestHistory.value.length);
const selectedIncomingRequests = computed(() => pendingIncomingRequests.value.filter((request) => selectedRequestIds.value.includes(Number(request.id))));
const selectedRequestsHaveActiveJobs = computed(() => selectedIncomingRequests.value.some((request) => request.active_job_orders?.length));
const staffDropdownOpen = ref(false);
const staffSearch = ref('');
const selectedStaffMembers = computed(() => props.availableStaff.filter((member) => form.staff_user_ids.includes(Number(member.id))));
const filteredAvailableStaff = computed(() => {
    const query = staffSearch.value.trim().toLowerCase();
    if (!query) return props.availableStaff;
    return props.availableStaff.filter((member) => [member.name, member.employee_id, member.department, member.role_label]
        .some((value) => String(value || '').toLowerCase().includes(query)));
});
const toggleStaffSelection = (id) => {
    const staffId = Number(id);
    form.staff_user_ids = form.staff_user_ids.includes(staffId)
        ? form.staff_user_ids.filter((selectedId) => selectedId !== staffId)
        : [...form.staff_user_ids, staffId];
};
const removeStaffSelection = (id) => {
    form.staff_user_ids = form.staff_user_ids.filter((selectedId) => selectedId !== Number(id));
};

const formatRole = (role) => String(role || '').replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const formatDepartment = (department) => `${String(department || '').replace(/_/g, ' ')} Department`.replace(/\b\w/g, (letter) => letter.toUpperCase());
const formatDate = (value) => value ? new Date(value).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—';
const statusClass = (status) => ({
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
    cancelled: 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300',
}[status] || 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300');

const submitRequest = () => form.post(route('man.manager.staff.transfer-requests.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});

const decide = (request, decision) => router.post(route('man.manager.staff.transfer-requests.decision', request.id), {
    decision,
    review_note: reviewNotes[request.id] || '',
}, {
    preserveScroll: true,
    onSuccess: () => {
        selectedRequestIds.value = selectedRequestIds.value.filter((id) => id !== Number(request.id));
    },
});

const toggleRequestSelection = (id) => {
    const requestId = Number(id);
    selectedRequestIds.value = selectedRequestIds.value.includes(requestId)
        ? selectedRequestIds.value.filter((selectedId) => selectedId !== requestId)
        : [...selectedRequestIds.value, requestId];
};

const selectAllPendingRequests = () => {
    const pendingIds = pendingIncomingRequests.value.map((request) => Number(request.id));
    selectedRequestIds.value = selectedRequestIds.value.length === pendingIds.length ? [] : pendingIds;
};

const bulkDecide = (decision) => router.post(route('man.manager.staff.transfer-requests.bulk-decision'), {
    request_ids: selectedRequestIds.value,
    decision,
}, {
    preserveScroll: true,
    onSuccess: () => { selectedRequestIds.value = []; },
});

const cancelRequest = (request) => router.post(route('man.manager.staff.transfer-requests.cancel', request.id), {}, { preserveScroll: true });
</script>

<template>
    <Head title="Department Staff | Manufacturing" />
    <AuthenticatedLayout>
        <main class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-indigo-50/40 px-4 py-5 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/20 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6 pb-12">
                <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-blue-900 p-6 text-white shadow-xl sm:p-8">
                    <div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-blue-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20"><Users class="h-7 w-7" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-200">Manufacturing · {{ departmentLabel }}</p>
                            <h1 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">Department staff</h1>
                            <p class="mt-1 text-sm text-blue-100/90">View your department team and request a staff transfer from another production department.</p>
                        </div>
                        <span class="rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold">{{ staff.length }} team members</span>
                    </div>
                </header>

                <div v-if="page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">{{ page.props.flash.error }}</div>

                <nav class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-label="Department staff sections">
                    <button type="button" @click="activeTab = 'team'" :aria-current="activeTab === 'team' ? 'page' : undefined" :class="activeTab === 'team' ? 'bg-indigo-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800'" class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition">
                        <Users class="h-4 w-4" /> Department staff
                    </button>
                    <button type="button" @click="activeTab = 'requests'" :aria-current="activeTab === 'requests' ? 'page' : undefined" :class="activeTab === 'requests' ? 'bg-indigo-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800'" class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition">
                        <Clock3 class="h-4 w-4" /> Transfer requests
                        <span :class="activeTab === 'requests' ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200'" class="rounded-full px-2 py-0.5 text-[10px] font-black">{{ pendingRequestCount }}</span>
                    </button>
                    <button type="button" @click="activeTab = 'history'" :aria-current="activeTab === 'history' ? 'page' : undefined" :class="activeTab === 'history' ? 'bg-indigo-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800'" class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition">
                        <Clock3 class="h-4 w-4" /> History
                        <span :class="activeTab === 'history' ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200'" class="rounded-full px-2 py-0.5 text-[10px] font-black">{{ historyRequestCount }}</span>
                    </button>
                </nav>

                <section v-if="activeTab === 'team'" class="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                    <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                            <div>
                                <h2 class="font-black text-slate-900 dark:text-white">Your department</h2>
                                <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Staff currently assigned to {{ departmentLabel }}.</p>
                            </div>
                            <span class="rounded-xl bg-indigo-50 px-3 py-2 text-xs font-black text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-200">{{ staff.length }} people</span>
                        </div>
                        <div v-if="staff.length" class="divide-y divide-slate-100 dark:divide-zinc-800">
                            <div v-for="member in staff" :key="member.id" class="flex items-center gap-3 px-5 py-4 sm:px-6">
                                <img v-if="member.photo" :src="member.photo" :alt="member.name" class="h-11 w-11 rounded-2xl object-cover ring-1 ring-slate-200 dark:ring-zinc-700">
                                <span v-else class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-100 text-sm font-black text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">{{ member.name?.slice(0, 1)?.toUpperCase() }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ member.name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-400">{{ member.employee_id || 'No employee ID' }} · {{ member.role_label }}</p>
                                </div>
                                <a v-if="member.email" :href="`mailto:${member.email}`" class="hidden items-center gap-1.5 text-xs font-semibold text-indigo-700 hover:text-indigo-900 dark:text-indigo-300 sm:inline-flex"><Mail class="h-3.5 w-3.5" /> Contact</a>
                                <span :class="member.is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400'" class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ member.is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                        </div>
                        <div v-else class="px-6 py-12 text-center text-sm text-slate-500 dark:text-zinc-400">No staff are currently assigned to this department.</div>
                    </article>

                    <article class="rounded-3xl border border-indigo-100 bg-white p-5 shadow-sm dark:border-indigo-900/50 dark:bg-zinc-900 sm:p-6">
                        <div class="mb-5 flex items-start gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200"><ArrowRightLeft class="h-5 w-5" /></span>
                            <div>
                                <h2 class="font-black text-slate-900 dark:text-white">Request a staff transfer</h2>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-zinc-400">The staff member’s current department supervisor must approve before the transfer takes effect.</p>
                            </div>
                        </div>
                        <form class="space-y-4" @submit.prevent="submitRequest">
                            <div class="relative">
                                <label class="block text-xs font-bold text-slate-700 dark:text-zinc-300">Staff members</label>
                                <button type="button" :disabled="!canEditStaff || !availableStaff.length" @click="staffDropdownOpen = !staffDropdownOpen" class="mt-1.5 flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-left text-sm text-slate-700 shadow-sm transition hover:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                    <span>{{ selectedStaffMembers.length ? `${selectedStaffMembers.length} employee${selectedStaffMembers.length === 1 ? '' : 's'} selected` : availableStaff.length ? 'Choose staff from other departments' : 'No available staff to request' }}</span>
                                    <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">{{ staffDropdownOpen ? 'Done' : 'Select' }}</span>
                                </button>
                                <div v-if="staffDropdownOpen" class="absolute z-30 mt-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="border-b border-slate-100 p-3 dark:border-zinc-800">
                                        <div class="relative">
                                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                            <input v-model="staffSearch" type="search" placeholder="Search name, department, or role" class="w-full rounded-xl border-slate-200 py-2 pl-9 pr-3 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500">
                                        </div>
                                        <p class="mt-2 text-[11px] text-slate-500 dark:text-zinc-400">Select one or more employees. Each request goes to their current department supervisor.</p>
                                    </div>
                                    <div class="max-h-72 overflow-y-auto p-2">
                                        <button v-for="member in filteredAvailableStaff" :key="member.id" type="button" @click="toggleStaffSelection(member.id)" class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 text-left transition hover:bg-indigo-50 dark:hover:bg-indigo-950/40">
                                            <img v-if="member.photo" :src="member.photo" :alt="member.name" class="h-10 w-10 shrink-0 rounded-xl object-cover ring-1 ring-slate-200 dark:ring-zinc-700">
                                            <span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-sm font-black text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">{{ member.name?.slice(0, 1)?.toUpperCase() }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-bold text-slate-900 dark:text-white">{{ member.name }}</span>
                                                <span class="mt-0.5 block truncate text-[11px] text-slate-500 dark:text-zinc-400">{{ member.employee_id || 'No employee ID' }} · {{ formatDepartment(member.department) }} · {{ member.role_label }}</span>
                                            </span>
                                            <span :class="form.staff_user_ids.includes(Number(member.id)) ? 'border-indigo-700 bg-indigo-700 text-white' : 'border-slate-300 bg-white text-transparent dark:border-zinc-600 dark:bg-zinc-800'" class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2"><Check class="h-3.5 w-3.5" /></span>
                                        </button>
                                        <p v-if="!filteredAvailableStaff.length" class="px-3 py-6 text-center text-xs text-slate-500 dark:text-zinc-400">No employees match your search.</p>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-slate-100 px-3 py-2.5 dark:border-zinc-800">
                                        <button type="button" @click="form.staff_user_ids = []" class="text-xs font-bold text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white">Clear selection</button>
                                        <button type="button" @click="staffDropdownOpen = false" class="rounded-lg bg-indigo-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-800">Done</button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="selectedStaffMembers.length" class="-mt-1 flex flex-wrap gap-2">
                                <span v-for="member in selectedStaffMembers" :key="member.id" class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-indigo-50 py-1 pl-1 pr-2 text-[11px] font-semibold text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-200">
                                    <img v-if="member.photo" :src="member.photo" :alt="''" class="h-5 w-5 rounded-full object-cover">
                                    <span v-else class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-200 text-[9px] font-black text-indigo-800 dark:bg-indigo-900 dark:text-indigo-100">{{ member.name?.slice(0, 1)?.toUpperCase() }}</span>
                                    <span class="max-w-40 truncate">{{ member.name }} · {{ formatDepartment(member.department) }}</span>
                                    <button type="button" :aria-label="`Remove ${member.name}`" @click="removeStaffSelection(member.id)" class="rounded-full p-0.5 hover:bg-indigo-200 dark:hover:bg-indigo-900"><X class="h-3 w-3" /></button>
                                </span>
                            </div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-zinc-300">Role in your department
                                <select v-model="form.target_role" required :disabled="!canEditStaff" class="mt-1.5 w-full rounded-xl border-slate-200 bg-white text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                    <option value="">Choose a manufacturing role</option>
                                    <option v-for="role in targetRoles" :key="role.value" :value="role.value">{{ role.label }}</option>
                                </select>
                            </label>
                            <label class="block text-xs font-bold text-slate-700 dark:text-zinc-300">Reason
                                <textarea v-model="form.reason" rows="3" maxlength="1500" :disabled="!canEditStaff" placeholder="Explain the staffing need" class="mt-1.5 w-full resize-y rounded-xl border-slate-200 bg-white text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500" />
                            </label>
                            <p v-if="form.errors.staff_user_ids || form.errors['staff_user_ids.0'] || form.errors.target_role" class="text-xs font-semibold text-rose-600">{{ form.errors.staff_user_ids || form.errors['staff_user_ids.0'] || form.errors.target_role }}</p>
                            <button type="submit" :disabled="!canEditStaff || !selectedStaffMembers.length || !form.target_role || form.processing" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-700 px-4 py-3 text-sm font-black text-white shadow-lg shadow-indigo-900/10 transition hover:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-50">
                                <ArrowRightLeft class="h-4 w-4" /> {{ form.processing ? 'Sending requests…' : canEditStaff ? `Send ${selectedStaffMembers.length || ''} transfer request${selectedStaffMembers.length === 1 ? '' : 's'}` : 'View only access' }}
                            </button>
                        </form>
                    </article>
                </section>

                <section v-if="activeTab === 'requests'" class="grid gap-5 xl:grid-cols-2">
                    <article class="rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="border-b border-slate-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                            <div class="flex items-center gap-2"><Clock3 class="h-4 w-4 text-amber-600 dark:text-amber-300" /><h2 class="font-black text-slate-900 dark:text-white">Requests for your decision</h2></div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Only the current department supervisor can release a staff member.</p>
                            <div v-if="canEditStaff && pendingIncomingRequests.length" class="mt-4 flex flex-wrap items-center gap-2">
                                <button type="button" @click="selectAllPendingRequests" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    {{ selectedRequestIds.length === pendingIncomingRequests.length ? 'Clear selection' : 'Select all pending' }}
                                </button>
                                <span class="mr-auto text-xs font-semibold text-slate-500 dark:text-zinc-400">{{ selectedRequestIds.length }} selected</span>
                                <button type="button" :disabled="!selectedRequestIds.length || selectedRequestsHaveActiveJobs" :title="selectedRequestsHaveActiveJobs ? 'Unfinished Job Orders must be completed or reassigned first.' : undefined" @click="bulkDecide('approved')" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-2 text-xs font-black text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"><Check class="h-4 w-4" /> Approve selected</button>
                                <button type="button" :disabled="!selectedRequestIds.length" @click="bulkDecide('rejected')" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-black text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"><X class="h-4 w-4" /> Reject selected</button>
                            </div>
                            <p v-if="selectedRequestsHaveActiveJobs" class="mt-2 text-xs font-semibold text-rose-700 dark:text-rose-300">At least one selected employee has unfinished Job Orders. They must be completed or reassigned before approval.</p>
                        </div>
                        <div v-if="pendingIncomingRequests.length" class="divide-y divide-slate-100 dark:divide-zinc-800">
                            <div v-for="request in pendingIncomingRequests" :key="request.id" class="space-y-3 p-5 sm:p-6">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">{{ request.staff?.name || 'Staff member' }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ formatRole(request.staff?.current_role) }} · requested by {{ request.requester }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input v-if="request.status === 'pending' && canEditStaff" type="checkbox" :checked="selectedRequestIds.includes(Number(request.id))" :aria-label="`Select request for ${request.staff?.name || 'staff member'}`" @change="toggleRequestSelection(request.id)" class="h-4 w-4 rounded border-slate-300 text-indigo-700 focus:ring-indigo-600 dark:border-zinc-600 dark:bg-zinc-800">
                                        <span :class="statusClass(request.status)" class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ request.status }}</span>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-600 dark:text-zinc-300"><span class="font-semibold">Move to:</span> {{ formatDepartment(request.target_department) }} · {{ formatRole(request.target_role) }}</p>
                                <div v-if="request.active_job_orders?.length" class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/70 dark:bg-amber-950/30">
                                    <p class="text-xs font-black text-amber-900 dark:text-amber-200">Transfer blocked · {{ request.active_job_orders.length }} unfinished Job Order assignment{{ request.active_job_orders.length === 1 ? '' : 's' }}</p>
                                    <ul class="mt-2 space-y-1 text-xs text-amber-800 dark:text-amber-200/90">
                                        <li v-for="job in request.active_job_orders" :key="job.id">JO #{{ job.manufacturing_order_id || job.sales_order_id || job.id }} · {{ formatDepartment(job.department) }} · {{ formatRole(job.stage) }}</li>
                                    </ul>
                                    <p class="mt-2 text-[11px] text-amber-800 dark:text-amber-200/90">Complete or reassign these JOs before approving this transfer.</p>
                                </div>
                                <div v-else class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">No unfinished Job Orders assigned.</div>
                                <p v-if="request.reason" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ request.reason }}</p>
                                <p class="text-[11px] text-slate-400">Requested {{ formatDate(request.created_at) }}</p>
                                <template v-if="request.status === 'pending' && canEditStaff">
                                    <textarea v-model="reviewNotes[request.id]" rows="2" maxlength="1500" placeholder="Optional note for the requester" class="w-full rounded-xl border-slate-200 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" :disabled="request.active_job_orders?.length" :title="request.active_job_orders?.length ? 'Unfinished Job Orders must be completed or reassigned first.' : undefined" @click="decide(request, 'approved')" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"><Check class="h-4 w-4" /> Approve transfer</button>
                                        <button type="button" @click="decide(request, 'rejected')" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-black text-rose-700 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"><X class="h-4 w-4" /> Decline</button>
                                    </div>
                                </template>
                                <p v-else-if="request.review_note" class="text-xs text-slate-500 dark:text-zinc-400">Supervisor note: {{ request.review_note }}</p>
                            </div>
                        </div>
                        <div v-else class="px-6 py-10 text-center text-sm text-slate-500 dark:text-zinc-400">No undecided requests are waiting for your department.</div>
                    </article>

                    <article class="rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="border-b border-slate-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                            <h2 class="font-black text-slate-900 dark:text-white">Requests you sent</h2>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Your staff request becomes a transfer only after approval.</p>
                        </div>
                        <div v-if="pendingOutgoingRequests.length" class="divide-y divide-slate-100 dark:divide-zinc-800">
                            <div v-for="request in pendingOutgoingRequests" :key="request.id" class="space-y-2 p-5 sm:p-6">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">{{ request.staff?.name || 'Staff member' }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ formatDepartment(request.source_department) }} → {{ formatDepartment(request.target_department) }}</p>
                                    </div>
                                    <span :class="statusClass(request.status)" class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ request.status }}</span>
                                </div>
                                <p class="text-sm text-slate-600 dark:text-zinc-300">Requested role: {{ formatRole(request.target_role) }}</p>
                                <p class="text-[11px] text-slate-400">Sent {{ formatDate(request.created_at) }}<span v-if="request.reviewer"> · Reviewed by {{ request.reviewer }}</span></p>
                                <p v-if="request.review_note" class="text-xs text-slate-500 dark:text-zinc-400">{{ request.review_note }}</p>
                                <button v-if="request.status === 'pending' && canEditStaff" type="button" @click="cancelRequest(request)" class="text-xs font-bold text-rose-700 hover:text-rose-900 dark:text-rose-300">Cancel request</button>
                            </div>
                        </div>
                        <div v-else class="px-6 py-10 text-center text-sm text-slate-500 dark:text-zinc-400">You have no undecided requests.</div>
                    </article>
                </section>

                <section v-if="activeTab === 'history'" class="grid gap-5 xl:grid-cols-2">
                    <article class="rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="border-b border-slate-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                            <h2 class="font-black text-slate-900 dark:text-white">Requests decided by your department</h2>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Completed decisions for employees requested from your department.</p>
                        </div>
                        <div v-if="incomingRequestHistory.length" class="divide-y divide-slate-100 dark:divide-zinc-800">
                            <div v-for="request in incomingRequestHistory" :key="request.id" class="space-y-2 p-5 sm:p-6">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">{{ request.staff?.name || 'Staff member' }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ formatRole(request.staff?.current_role) }} · requested by {{ request.requester }}</p>
                                    </div>
                                    <span :class="statusClass(request.status)" class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ request.status }}</span>
                                </div>
                                <p class="text-sm text-slate-600 dark:text-zinc-300">{{ formatDepartment(request.source_department) }} → {{ formatDepartment(request.target_department) }} · {{ formatRole(request.target_role) }}</p>
                                <p class="text-[11px] text-slate-400">{{ request.reviewed_at ? 'Decided' : 'Closed' }} {{ formatDate(request.reviewed_at || request.created_at) }}<span v-if="request.reviewer"> · by {{ request.reviewer }}</span></p>
                                <p v-if="request.review_note" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ request.review_note }}</p>
                            </div>
                        </div>
                        <div v-else class="px-6 py-10 text-center text-sm text-slate-500 dark:text-zinc-400">No completed decisions yet.</div>
                    </article>

                    <article class="rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="border-b border-slate-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
                            <h2 class="font-black text-slate-900 dark:text-white">Requests you sent</h2>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Final outcomes for transfer requests from your department.</p>
                        </div>
                        <div v-if="outgoingRequestHistory.length" class="divide-y divide-slate-100 dark:divide-zinc-800">
                            <div v-for="request in outgoingRequestHistory" :key="request.id" class="space-y-2 p-5 sm:p-6">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">{{ request.staff?.name || 'Staff member' }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ formatDepartment(request.source_department) }} → {{ formatDepartment(request.target_department) }}</p>
                                    </div>
                                    <span :class="statusClass(request.status)" class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ request.status }}</span>
                                </div>
                                <p class="text-sm text-slate-600 dark:text-zinc-300">Requested role: {{ formatRole(request.target_role) }}</p>
                                <p class="text-[11px] text-slate-400">{{ request.reviewed_at ? 'Decided' : 'Closed' }} {{ formatDate(request.reviewed_at || request.created_at) }}<span v-if="request.reviewer"> · by {{ request.reviewer }}</span></p>
                                <p v-if="request.review_note" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ request.review_note }}</p>
                            </div>
                        </div>
                        <div v-else class="px-6 py-10 text-center text-sm text-slate-500 dark:text-zinc-400">No completed requests in your history.</div>
                    </article>
                </section>
            </div>
        </main>
    </AuthenticatedLayout>
</template>
