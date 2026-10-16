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
                                Attendance
                            </h1>
                            <p class="text-sm text-slate-500 mt-1.5">
                                Live clock records from the employee portal —
                                same data as Workforce scheduling.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex bg-slate-100 rounded-xl p-1 text-xs font-bold">
                                <button type="button" @click="switchMode('daily')"
                                    :class="mode === 'daily' ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                                    class="px-3 py-1.5 rounded-lg transition">Daily</button>
                                <button type="button" @click="switchMode('history')"
                                    :class="mode === 'history' ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                                    class="px-3 py-1.5 rounded-lg transition">History</button>
                            </div>
                            <div v-if="mode === 'daily'" class="relative group">
                                <svg
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-hover:text-blue-500 transition-colors"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                                <input
                                    v-model="selectedDate"
                                    @change="applyFilter"
                                    type="date"
                                    class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm hover:shadow-md transition-all duration-200"
                                />
                            </div>
                            <div v-else class="flex flex-wrap items-center gap-2">
                                <input v-model="fromDate" type="date" aria-label="History from"
                                    class="px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                <span class="text-xs font-bold text-slate-400">to</span>
                                <input v-model="toDate" type="date" aria-label="History to"
                                    class="px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                <button type="button" @click="applyFilter"
                                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition shadow-sm">Apply</button>
                                <button type="button" @click="clearHistory"
                                    class="px-3 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-xl text-xs font-bold transition">Clear</button>
                            </div>
                            <button
                                @click="exportCsv"
                                class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:-translate-y-0.5"
                            >
                                <Upload class="w-4 h-4" />
                                Export
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Non-working holiday notice (Holiday Management link) -->
                <div v-if="holidaysInRange.length"
                    class="mb-6 flex flex-wrap items-center gap-2 bg-cyan-50 border border-cyan-200 rounded-2xl px-4 py-3 text-xs text-cyan-800 shadow-sm">
                    <TreePalm class="w-4 h-4 shrink-0" />
                    <span class="font-bold">Non-working holiday{{ holidaysInRange.length === 1 ? '' : 's' }} in view — nobody is marked absent on these days:</span>
                    <span v-for="h in holidaysInRange" :key="h.date" class="font-semibold bg-white border border-cyan-200 rounded-full px-2.5 py-0.5">{{ h.name }} ({{ h.date }})</span>
                    <button type="button" @click="goHolidays" class="ml-auto font-bold underline hover:text-cyan-600 transition">Manage holidays</button>
                </div>

                <!-- KPI METRICS ROW - Floating Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div
                        v-for="m in metricCards"
                        :key="m.label"
                        class="group bg-white rounded-2xl border border-slate-200/60 p-5 transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 hover:border-blue-200/60 shadow-md backdrop-blur-sm"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <span
                                class="text-xs font-semibold text-slate-500 uppercase tracking-wider"
                                >{{ m.label }}</span
                            >
                            <div
                                class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center group-hover:from-blue-100 group-hover:to-blue-200 transition-all duration-300 shadow-inner"
                            >
                                <component :is="m.icon" class="w-5 h-5" :class="m.color" />
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-slate-900">
                            {{ m.value }}
                        </div>
                    </div>
                </div>

                <!-- ATTENDANCE TABLE -->
                <div
                    class="bg-white rounded-2xl border border-slate-200/60 shadow-lg hover:shadow-2xl transition-all duration-300 backdrop-blur-sm overflow-hidden"
                >
                    <div
                        class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4"
                    >
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <span
                                    class="absolute inset-y-0 left-0 flex items-center pl-3"
                                    ><Search class="w-4 h-4 text-slate-400" /></span
                                >
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search employee..."
                                    class="w-64 pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm transition-all duration-200"
                                />
                            </div>
                        </div>
                        <select
                            v-model="department"
                            @change="applyFilter"
                            class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm transition-all duration-200 cursor-pointer"
                        >
                            <option value="">All Departments</option>
                            <option v-for="d in departmentOptions" :key="d.code || d.name" :value="d.code || d.name">{{ d.name }}</option>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500"
                                >
                                    <th class="py-3 px-5">Date</th>
                                    <th class="py-3 px-5">Employee</th>
                                    <th class="py-3 px-5">Employee ID</th>
                                    <th class="py-3 px-5">Department</th>
                                    <th class="py-3 px-5">Shift</th>
                                    <th class="py-3 px-5">Time In</th>
                                    <th class="py-3 px-5">Time Out</th>
                                    <th class="py-3 px-5">Hours</th>
                                    <th class="py-3 px-5 text-center">Late min</th>
                                    <th class="py-3 px-5 text-center">Status</th>
                                    <th class="py-3 px-5">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="a in recordList"
                                    :key="a.id"
                                    class="hover:bg-gradient-to-r hover:from-blue-50/50 hover:to-transparent transition-all duration-200 text-xs"
                                >
                                    <td class="py-3 px-5 text-slate-500 whitespace-nowrap">
                                        {{ a.date }}
                                    </td>
                                    <td class="py-3 px-5">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-[10px] font-bold text-blue-600 shadow-inner"
                                            >
                                                {{ initials(a.employee) }}
                                            </div>
                                            <span
                                                class="font-semibold text-slate-800"
                                                >{{ a.employee }}</span
                                            >
                                        </div>
                                    </td>
                                    <td class="py-3 px-5 font-mono text-slate-600">
                                        {{ a.employee_id || '—' }}
                                    </td>
                                    <td class="py-3 px-5 text-slate-600">
                                        {{ a.department || '—' }}
                                    </td>
                                    <td class="py-3 px-5 text-slate-600">
                                        {{ a.shift || '—' }}
                                    </td>
                                    <td class="py-3 px-5 font-medium">
                                        {{ a.time_in }}
                                    </td>
                                    <td class="py-3 px-5 font-medium">
                                        {{ a.time_out }}
                                    </td>
                                    <td class="py-3 px-5 font-semibold">
                                        {{ a.hours }}
                                    </td>
                                    <td class="py-3 px-5 text-center font-semibold" :class="a.late_minutes ? 'text-rose-600' : 'text-slate-300'">
                                        {{ a.late_minutes || '—' }}
                                    </td>
                                    <td class="py-3 px-5 text-center">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold shadow-sm border',
                                                statusStyle(a.status).badge,
                                            ]"
                                        >
                                            <span
                                                :class="['w-1.5 h-1.5 rounded-full', statusStyle(a.status).dot]"
                                            ></span>
                                            {{ a.status }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-5 text-slate-500 max-w-48">
                                        <div class="flex items-center gap-2">
                                            <span class="block truncate flex-1" :title="a.remarks || ''">{{ a.remarks || '—' }}</span>
                                            <button v-if="canEdit" @click="openRemark(a)" title="Edit remark"
                                                class="shrink-0 p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                                <Pencil class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="recordList.length === 0">
                                    <td colspan="11" class="py-10 text-center text-sm text-slate-400">{{ mode === 'history' ? 'No attendance records for this range.' : 'No attendance records for this date.' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="pagination && pagination.last_page > 1" class="px-5 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span>Page {{ pagination.current_page }} of {{ pagination.last_page }} · {{ pagination.total }} records</span>
                        <div class="flex gap-2">
                            <button type="button" @click="goPage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                                class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold hover:bg-slate-50 disabled:opacity-40 transition">Prev</button>
                            <button type="button" @click="goPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
                                class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold hover:bg-slate-50 disabled:opacity-40 transition">Next</button>
                        </div>
                    </div>
                    <div v-else-if="pagination && pagination.total > 0" class="px-5 py-3 border-t border-slate-100 text-xs text-slate-400">
                        {{ pagination.total }} record{{ pagination.total === 1 ? '' : 's' }}{{ mode === 'history' && history ? ` · ${history.from} to ${history.to}` : '' }}
                    </div>
                </div>

                <!-- Remark modal -->
                <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-slate-900/50" @click="closeRemark"></div>
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="p-5 border-b border-slate-100">
                            <h3 class="font-bold text-slate-900">Attendance Remark</h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ editing.employee }} · {{ editing.date }} · {{ editing.status }}</p>
                        </div>
                        <div class="p-5 space-y-4">
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1.5">Remark Type</label>
                                <select v-model="remarkType"
                                    class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option v-for="t in REMARK_TYPES" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </div>
                            <div v-if="remarkType === 'Other'">
                                <label class="text-xs font-bold text-slate-700 block mb-1.5">Custom Remark</label>
                                <input v-model="customRemark" @input="remarkError = ''" type="text" placeholder="Enter custom remark..."
                                    class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                <p v-if="remarkError" class="text-xs text-rose-600 font-semibold mt-1.5">{{ remarkError }}</p>
                            </div>
                        </div>
                        <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                            <button @click="closeRemark" :disabled="remarkSaving"
                                class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition disabled:opacity-50">Cancel</button>
                            <button @click="saveRemark" :disabled="remarkSaving"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-50">{{ remarkSaving ? 'Saving…' : 'Save Remark' }}</button>
                        </div>
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
import { AlertTriangle, CheckCircle2, Pencil, Search, TreePalm, Upload, XCircle } from "lucide-vue-next";

const props = defineProps({
    records: { type: [Array, Object], default: () => [] },
    pagination: { type: Object, default: () => ({}) },
    metrics: { type: [Array, Object], default: () => [] },
    filters: { type: Object, default: () => ({ date: '', from: '', to: '', search: '', department: '' }) },
    history: { type: Object, default: null },
    holidays: { type: Array, default: () => [] },
    departments: { type: [Array, Object], default: () => [] },
});

const selectedDate = ref(props.filters?.date || new Date().toISOString().slice(0, 10));
const search = ref(props.filters?.search || '');
const department = ref(props.filters?.department || '');
const mode = ref(props.history ? 'history' : 'daily');
const fromDate = ref(props.filters?.from || props.history?.from || new Date().toISOString().slice(0, 10));
const toDate = ref(props.filters?.to || props.history?.to || new Date().toISOString().slice(0, 10));
const historySummary = computed(() => props.history?.summary ?? []);

const asArray = (v) => (Array.isArray(v) ? v : (v?.data ?? [])) ?? [];
const recordList = computed(() => asArray(props.records).filter((r) => r && typeof r === 'object' && r.id != null));
const holidaysInRange = computed(() => asArray(props.holidays));
const departmentOptions = computed(() => asArray(props.departments));

const iconFor = (label) => ({
    'Present Today': CheckCircle2, 'Late': AlertTriangle, 'Absent': XCircle, 'On Leave': TreePalm,
}[label] || CheckCircle2);
const colorFor = (label) => ({
    'Present Today': 'text-emerald-600', 'Late': 'text-amber-600', 'Absent': 'text-rose-600', 'On Leave': 'text-blue-600',
}[label] || 'text-blue-600');

const metricCards = computed(() => asArray(props.metrics).map((m) => ({
    ...m, icon: iconFor(m.label), color: colorFor(m.label),
})));

const initials = (name) => String(name || '?').split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase();

// Status colors: Present green, Late amber, Absent red, On Leave blue,
// Half Day purple, Rest Day slate, On Duty orange, Holiday cyan.
const statusOptions = ['Present', 'Late', 'Absent', 'On Leave', 'Half Day', 'Rest Day', 'On Duty', 'Holiday'];
const statusStyle = (s) => ({
    'Present': { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
    'Late': { badge: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-400' },
    'Absent': { badge: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' },
    'On Leave': { badge: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' },
    'Half Day': { badge: 'bg-purple-50 text-purple-700 border-purple-200', dot: 'bg-purple-500' },
    'Rest Day': { badge: 'bg-slate-100 text-slate-500 border-slate-200', dot: 'bg-slate-300' },
    'On Duty': { badge: 'bg-orange-50 text-orange-700 border-orange-200', dot: 'bg-orange-500' },
    'Holiday': { badge: 'bg-cyan-50 text-cyan-700 border-cyan-200', dot: 'bg-cyan-500' },
}[s] || { badge: 'bg-slate-100 text-slate-500 border-slate-200', dot: 'bg-slate-300' });

// Row action removed — table is read-only.

let searchTimer = null;
watch(search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilter, 400); });

const applyFilter = (page = null) => {
    const params = { search: search.value.trim(), department: department.value };
    if (mode.value === 'history') {
        params.from = fromDate.value; params.to = toDate.value;
    } else {
        params.date = selectedDate.value;
    }
    if (page) params.page = page;
    router.get(route('hrm.workforce.attendance.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};
const switchMode = (m) => {
    mode.value = m;
    if (m === 'history' && !fromDate.value) fromDate.value = selectedDate.value;
    if (m === 'history' && !toDate.value) toDate.value = selectedDate.value;
    applyFilter();
};
const clearHistory = () => {
    mode.value = 'daily';
    router.get(route('hrm.workforce.attendance.index'), {
        date: selectedDate.value, search: search.value.trim(), department: department.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
};
const goPage = (page) => {
    if (!page || page === props.pagination?.current_page) return;
    applyFilter(page);
};

const exportCsv = () => {
    const params = mode.value === 'history'
        ? { from: fromDate.value, to: toDate.value }
        : { date: selectedDate.value };
    window.location.href = route('hrm.workforce.attendance.export', params);
};
const goHolidays = () => {
    router.get(route('hrm.workforce.holidays.index'));
};

// ---- Remark editing (Remark Type dropdown; Custom Remark when Other) ----
// Mirrors CheckPagePermission: explicit 'edit' row wins; otherwise CEO,
// secretaries/special officers, and native HRM managers/staff (with no
// explicit grants) all have full access — otherwise the pencil would stay
// hidden for users the PATCH route would actually allow.
const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const canEdit = computed(() => {
    if (props.permissions?.attendance === 'edit') return true;
    if (authUser.value?.role === 'CEO') return true;
    if (['secretary', 'special_officer'].includes(authUser.value?.position)) return true;
    if (String(authUser.value?.role ?? '').toUpperCase() === 'HRM' && ['manager', 'staff'].includes(authUser.value?.position)) {
        // Explicit grants (non-empty permissions) are the exact access set;
        // an 'edit' row would already have returned true above.
        if (props.permissions && Object.keys(props.permissions).length > 0) return false;
        return true;
    }
    return false;
});
const REMARK_TYPES = ['None', 'Traffic / Transportation Delay', 'Forgot Time In', 'Forgot Time Out', 'System/Device Issue', 'Official Business', 'Work From Home', 'Approved Early Departure', 'Other'];
const editing = ref(null);
const remarkType = ref('None');
const customRemark = ref('');
const remarkError = ref('');
const remarkSaving = ref(false);

const openRemark = (a) => {
    editing.value = a;
    remarkError.value = '';
    const current = (a.remarks || '').trim();
    if (!current) {
        remarkType.value = 'None';
        customRemark.value = '';
    } else if (REMARK_TYPES.includes(current) && current !== 'Other' && current !== 'None') {
        remarkType.value = current;
        customRemark.value = '';
    } else {
        remarkType.value = 'Other';
        customRemark.value = current === 'Other' ? '' : current;
    }
};
const closeRemark = () => {
    editing.value = null;
    remarkType.value = 'None';
    customRemark.value = '';
    remarkError.value = '';
    remarkSaving.value = false;
};
const saveRemark = () => {
    if (!editing.value || remarkSaving.value) return;
    const finalText = remarkType.value === 'Other'
        ? customRemark.value.trim()
        : (remarkType.value === 'None' ? '' : remarkType.value);
    if (remarkType.value === 'Other' && !finalText) {
        remarkError.value = 'Please enter a custom remark.';
        return;
    }
    remarkSaving.value = true;
    router.patch(route('hrm.workforce.attendance.update', editing.value.id), {
        status: editing.value.status_raw || editing.value.status,
        remarks: finalText,
    }, {
        preserveScroll: true,
        onSuccess: () => closeRemark(),
        onFinish: () => { remarkSaving.value = false; },
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
