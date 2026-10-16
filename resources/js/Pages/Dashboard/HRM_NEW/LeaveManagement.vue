<template>
    <AuthenticatedLayout>
        <div
            class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50"
        >
            <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative">
                <!-- Subtle Background Pattern -->
                <div
                    class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"
                ></div>

                <!-- Professional Header -->
                <header class="mb-8 relative">
                    <div
                        class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6"
                    >
                        <div>
                            <nav
                                class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3"
                            >
                                <span
                                    class="hover:text-slate-700 cursor-pointer transition-colors"
                                    >Dashboard</span
                                >
                                <svg
                                    class="w-3 h-3"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 5l7 7-7 7"
                                    />
                                </svg>
                                <span class="text-slate-800 font-semibold"
                                    >Time & Attendance</span
                                >
                            </nav>
                            <h1
                                class="text-4xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent"
                            >
                                Leave Management
                            </h1>
                            <p class="text-sm text-slate-500 mt-1.5">
                                Manage employee leave requests, balances, and
                                approvals.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <div class="relative">
                                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search employee or type..."
                                    class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
                                />
                            </div>
                            <select
                                v-model="filterStatus"
                                @change="applyFilter"
                                class="px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm"
                            >
                                <option value="">All Statuses</option>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                            <button
                                @click="showRequestModal = true"
                                class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 text-white rounded-xl text-sm font-bold transition-all duration-200 shadow-lg hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5"
                            >
                                <svg
                                    class="w-4 h-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v16m8-8H4"
                                    />
                                </svg>
                                Request Leave
                            </button>
                        </div>
                    </div>
                </header>

                <!-- LEAVE BALANCE CARDS - Floating -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                    <div
                        v-for="b in balanceCards"
                        :key="b.code"
                        class="group bg-white rounded-2xl border border-slate-200/60 p-5 text-center transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 hover:border-blue-200/60 shadow-md backdrop-blur-sm"
                    >
                        <div
                            class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center mx-auto group-hover:from-blue-100 group-hover:to-blue-200 transition-all duration-300 shadow-inner"
                        >
                            <component :is="b.icon" class="w-6 h-6 text-blue-600" />
                        </div>
                        <div class="text-2xl font-bold text-slate-900 mt-3">
                            {{ b.remaining }}
                        </div>
                        <p
                            class="text-[10px] text-slate-500 uppercase tracking-wider mt-1 font-semibold"
                        >
                            {{ b.name }}
                        </p>
                        <div
                            class="w-full h-2 bg-slate-100 rounded-full mt-3 overflow-hidden shadow-inner"
                        >
                            <div
                                class="h-full rounded-full shadow-lg transition-all duration-500"
                                :class="b.color"
                                :style="{ width: b.percent + '%' }"
                            ></div>
                        </div>
                        <p
                            class="text-[10px] text-slate-400 mt-1.5 font-medium"
                        >
                            {{ b.used }} used
                        </p>
                    </div>
                </div>

                <!-- LEAVE REQUESTS TABLE -->
                <div
                    class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 backdrop-blur-sm overflow-hidden"
                >
                    <div
                        class="p-5 border-b border-slate-100 bg-gradient-to-r from-slate-50/50 to-transparent"
                    >
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-slate-900">
                                Leave Requests
                            </h3>
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-semibold rounded-xl border border-amber-200 shadow-sm"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"
                                ></span>
                                {{ pendingCount }}
                                Pending
                            </span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500"
                                >
                                    <th class="py-3 px-5">Employee</th>
                                    <th class="py-3 px-5">Type</th>
                                    <th class="py-3 px-5">Dates</th>
                                    <th class="py-3 px-5">Days</th>
                                    <th class="py-3 px-5 text-center">
                                        Status
                                    </th>
                                    <th class="py-3 px-5 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="l in requestList"
                                    :key="l.id"
                                    class="hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-transparent transition-all duration-200 text-xs"
                                >
                                    <td class="py-3 px-5">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-[10px] font-bold text-blue-600 shadow-inner"
                                            >
                                                {{ initials(l.employee) }}
                                            </div>
                                            <span
                                                class="font-semibold text-slate-800"
                                                >{{ l.employee }}</span
                                            >
                                        </div>
                                    </td>
                                    <td class="py-3 px-5">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded-lg text-[10px] font-semibold shadow-sm',
                                                l.type === 'Sick Leave'
                                                    ? 'bg-amber-50 text-amber-700 border border-amber-100'
                                                    : l.type === 'Vacation Leave'
                                                      ? 'bg-blue-50 text-blue-700 border border-blue-100'
                                                      : 'bg-slate-50 text-slate-600 border border-slate-100',
                                            ]"
                                            >{{ l.type }}</span
                                        >
                                    </td>
                                    <td
                                        class="py-3 px-5 text-slate-500 font-medium"
                                    >
                                        {{ l.dates }}
                                    </td>
                                    <td
                                        class="py-3 px-5 font-semibold text-slate-800"
                                    >
                                        {{ l.days }} days
                                    </td>
                                    <td class="py-3 px-5 text-center">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold shadow-sm',
                                                l.status === 'Pending'
                                                    ? 'bg-amber-50 text-amber-700 border border-amber-200'
                                                    : l.status === 'Approved'
                                                      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                      : 'bg-rose-50 text-rose-700 border border-rose-200',
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'w-1.5 h-1.5 rounded-full',
                                                    l.status === 'Pending'
                                                        ? 'bg-amber-500 animate-pulse'
                                                        : l.status === 'Approved'
                                                          ? 'bg-emerald-500'
                                                          : 'bg-rose-500',
                                                ]"
                                            ></span>
                                            {{ l.status }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-5">
                                        <div v-if="l.status === 'Pending'" class="flex justify-end gap-1">
                                            <button
                                                @click="approve(l)"
                                                class="p-2 bg-white border border-slate-200 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-emerald-100 hover:text-emerald-600 hover:border-emerald-200 rounded-xl text-emerald-500 text-xs font-bold transition-all duration-200 shadow-sm hover:shadow-md inline-flex items-center gap-1"
                                            >
                                                <Check class="w-4 h-4" /> Approve
                                            </button>
                                            <button
                                                @click="askReject(l)"
                                                class="p-2 bg-white border border-slate-200 hover:bg-gradient-to-r hover:from-rose-50 hover:to-rose-100 hover:text-rose-600 hover:border-rose-200 rounded-xl text-rose-500 text-xs font-bold transition-all duration-200 shadow-sm hover:shadow-md inline-flex items-center gap-1"
                                            >
                                                <X class="w-4 h-4" /> Reject
                                            </button>
                                        </div>
                                        <div v-else class="text-right text-[10px] text-slate-400 font-medium">{{ l.reason || '—' }}</div>
                                    </td>
                                </tr>
                                <tr v-if="requestList.length === 0">
                                    <td colspan="6" class="py-10 text-center text-sm text-slate-400">No leave requests found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Request Leave Modal -->
        <div v-if="showRequestModal" class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showRequestModal = false">
            <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">File Leave Request</h3>
                    <button @click="showRequestModal = false" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Employee <span class="text-rose-500">*</span></label>
                        <select v-model="form.user_id" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500">
                            <option :value="''" disabled>Select employee</option>
                            <option v-for="e in employeeOptions" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Leave Type <span class="text-rose-500">*</span></label>
                            <select v-model="form.leave_type" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500">
                                <option :value="''" disabled>Select type</option>
                                <option v-for="t in typeOptions" :key="t.code" :value="t.code">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Reason</label>
                            <input v-model="form.reason" type="text" placeholder="Optional note" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Start Date <span class="text-rose-500">*</span></label>
                            <input v-model="form.start_date" type="date" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">End Date <span class="text-rose-500">*</span></label>
                            <input v-model="form.end_date" type="date" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>
                    <button @click="submitRequest" :disabled="submitting" class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl text-xs font-bold shadow-lg disabled:opacity-50">
                        {{ submitting ? 'Filing...' : 'File Request' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showRejectModal = false">
            <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Reject leave for {{ rejectTarget?.employee }}?</h3>
                    <p class="text-xs text-slate-500 mt-1">Optionally add a note. The employee portal and Workforce queue update instantly.</p>
                </div>
                <div class="p-5 space-y-4">
                    <textarea v-model="rejectReason" rows="3" placeholder="Reason (optional)" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-rose-500"></textarea>
                    <div class="flex gap-2">
                        <button @click="showRejectModal = false" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 rounded-xl text-xs font-bold">Cancel</button>
                        <button @click="confirmReject" :disabled="submitting" class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold disabled:opacity-50">Reject</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { TreePalm, Hospital, Siren, Baby, ClipboardList, Check, X } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps({
    balances: { type: [Array, Object], default: () => [] },
    leaveRequests: { type: [Array, Object], default: () => [] },
    pagination: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ pending: 0, approved: 0, rejected: 0 }) },
    filters: { type: Object, default: () => ({ search: '', status: '' }) },
    leaveTypes: { type: [Array, Object], default: () => [] },
    employees: { type: [Array, Object], default: () => [] },
});

const page = usePage();
const search = ref(props.filters?.search || '');
const filterStatus = ref(props.filters?.status || '');
const showRequestModal = ref(false);
const showRejectModal = ref(false);
const rejectTarget = ref(null);
const rejectReason = ref('');
const submitting = ref(false);
const form = ref({ user_id: '', leave_type: '', start_date: '', end_date: '', reason: '' });

const asArray = (v) => (Array.isArray(v) ? v : (v?.data ?? [])) ?? [];

const requestList = computed(() => asArray(props.leaveRequests).filter((l) => l && typeof l === 'object' && l.id != null));
const typeOptions = computed(() => asArray(props.leaveTypes));
const employeeOptions = computed(() => asArray(props.employees));

const iconFor = (code) => ({
    vacation: TreePalm, sick: Hospital, emergency: Siren, maternity: Baby,
}[String(code || '').toLowerCase()] || ClipboardList);
const colorFor = (code) => ({
    vacation: "bg-gradient-to-r from-blue-500 to-blue-400 shadow-blue-500/20",
    sick: "bg-gradient-to-r from-emerald-500 to-emerald-400 shadow-emerald-500/20",
    emergency: "bg-gradient-to-r from-amber-500 to-amber-400 shadow-amber-500/20",
    maternity: "bg-gradient-to-r from-pink-500 to-pink-400 shadow-pink-500/20",
}[String(code || '').toLowerCase()] || "bg-gradient-to-r from-purple-500 to-purple-400 shadow-purple-500/20");

const balanceCards = computed(() => asArray(props.balances).map((b) => ({
    ...b, icon: iconFor(b.code), color: colorFor(b.code),
})));

const pendingCount = computed(() => props.stats?.pending ?? requestList.value.filter((l) => l.status === 'Pending').length);

const initials = (name) => String(name || '?').split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase();

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true });

let searchTimer = null;
watch(search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilter, 400); });

const applyFilter = () => {
    router.get(route('hrm.workforce.leave.index'), {
        search: search.value.trim(), status: filterStatus.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const submitRequest = () => {
    if (!form.value.user_id || !form.value.leave_type || !form.value.start_date || !form.value.end_date) {
        toast.error('Employee, type, and dates are required.');
        return;
    }
    submitting.value = true;
    router.post(route('hrm.workforce.leave.store'), form.value, {
        onSuccess: () => { toast.success('Leave request filed.'); showRequestModal.value = false; form.value = { user_id: '', leave_type: '', start_date: '', end_date: '', reason: '' }; },
        onError: (e) => toast.error(String(Object.values(e || {})[0] || 'Failed to file request.')),
        onFinish: () => { submitting.value = false; },
    });
};

const approve = (l) => {
    if (!l?.id) return;
    router.post(route('hrm.workforce.leave.approve', l.id), {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Leave approved.'),
        onError: () => toast.error('Failed to approve leave.'),
    });
};

const askReject = (l) => {
    if (!l?.id) return;
    rejectTarget.value = l;
    rejectReason.value = '';
    showRejectModal.value = true;
};

const confirmReject = () => {
    if (!rejectTarget.value?.id) return;
    submitting.value = true;
    router.post(route('hrm.workforce.leave.reject', rejectTarget.value.id), { reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => { toast.success('Leave rejected.'); showRejectModal.value = false; rejectTarget.value = null; },
        onError: () => toast.error('Failed to reject leave.'),
        onFinish: () => { submitting.value = false; },
    });
};
</script>

<style scoped>
.bg-grid-slate-100 {
    background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
    background-size: 24px 24px;
}
.bg-clip-text {
    -webkit-background-clip: text;
    background-clip: text;
}
.backdrop-blur-sm {
    backdrop-filter: blur(8px);
}
</style>
