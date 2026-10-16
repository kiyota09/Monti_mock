<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Search, Plus, X, Eye, Pencil, Settings } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    shifts: { type: Array, default: () => [] },
    roster: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, active: 0, inactive: 0, assigned: 0 }) },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => ['Fixed', 'Flexible', 'Rotating'] },
    days: { type: Array, default: () => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] },
    nextCode: { type: String, default: 'SHF-001' },
});

// ---- Shift-definition filters (server-side, live inside Settings) ----
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const typeFilter = ref(props.filters?.type || '');
const saving = ref(false);

let searchTimer = null;
const applyFilter = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('hrm.workforce.shifts.index'), {
            search: search.value.trim(), status: statusFilter.value, type: typeFilter.value,
        }, { preserveState: true, preserveScroll: true, replace: true, only: ['shifts', 'filters', 'summary', 'nextCode'] });
    }, 350);
};

// ---- Employee roster filters (client-side) ----
const empSearch = ref('');
const deptFilter = ref('');
const rosterShiftFilter = ref('');

const filteredRoster = computed(() => {
    const q = empSearch.value.trim().toLowerCase();
    return props.roster.filter((e) => {
        if (deptFilter.value) {
            const matchId = e.department_id !== null && String(e.department_id) === String(deptFilter.value);
            const depName = props.departments.find((d) => String(d.id) === String(deptFilter.value))?.name;
            const matchName = depName ? e.department === depName : true;
            if (!matchId && !matchName) return false;
        }
        if (rosterShiftFilter.value === 'unassigned' && e.shift_id !== null) return false;
        if (rosterShiftFilter.value && rosterShiftFilter.value !== 'unassigned' && String(e.shift_id) !== String(rosterShiftFilter.value)) return false;
        if (q) {
            const hay = `${e.name} ${e.employee_id}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });
});

const clearRosterFilter = () => {
    empSearch.value = '';
    deptFilter.value = '';
    rosterShiftFilter.value = '';
};

// ---- Checkbox grouping: select rows, assign them the same shift ----
const selected = ref([]);
const bulkShiftId = ref('');
const bulkEffectiveDate = ref('');
const bulkSaving = ref(false);

const allChecked = computed(() =>
    filteredRoster.value.length > 0 &&
    filteredRoster.value.every((e) => selected.value.includes(e.id))
);
const toggleAll = () => {
    if (allChecked.value) {
        const ids = new Set(filteredRoster.value.map((e) => e.id));
        selected.value = selected.value.filter((id) => !ids.has(id));
    } else {
        const set = new Set(selected.value);
        filteredRoster.value.forEach((e) => set.add(e.id));
        selected.value = [...set];
    }
};
const toggleRow = (id) => {
    const i = selected.value.indexOf(id);
    if (i >= 0) selected.value.splice(i, 1);
    else selected.value.push(id);
};
const submitBulk = () => {
    if (!bulkShiftId.value || !selected.value.length) return;
    bulkSaving.value = true;
    router.post(route('hrm.workforce.shifts.bulk-assign'), {
        shift_id: bulkShiftId.value,
        user_ids: selected.value,
        effective_date: bulkEffectiveDate.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; bulkShiftId.value = ''; bulkEffectiveDate.value = ''; },
        onFinish: () => { bulkSaving.value = false; },
    });
};

// ---- Edit one employee's shift (time follows the shift definition) ----
const editingEmployee = ref(null);
const editShiftId = ref('');
const editEffectiveDate = ref('');
const editSaving = ref(false);

const openEditEmployee = (emp) => {
    editingEmployee.value = emp;
    editShiftId.value = emp.shift_id ? String(emp.shift_id) : '';
    editEffectiveDate.value = emp.effective_date || '';
};
const closeEditEmployee = () => {
    editingEmployee.value = null;
    editShiftId.value = '';
    editEffectiveDate.value = '';
};
const editShift = computed(() =>
    props.shifts.find((s) => String(s.id) === String(editShiftId.value)) || null
);
const saveEmployeeShift = () => {
    if (!editingEmployee.value) return;
    editSaving.value = true;
    router.post(route('hrm.workforce.shifts.reassign', editingEmployee.value.id), {
        shift_id: editShiftId.value ? editShiftId.value : null,
        effective_date: editEffectiveDate.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => closeEditEmployee(),
        onFinish: () => { editSaving.value = false; },
    });
};

// ---- Settings drawer (shift definitions live here) ----
const settingsOpen = ref(false);

// ---- Add / Edit shift definition ----
const showForm = ref(false);
const editing = ref(null);
const form = ref({ name: '', code: '', shift_type: 'Fixed', start_time: '8:00 AM', end_time: '5:00 PM', break_minutes: 60, grace_minutes: 15, working_days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], is_active: true });

const openAdd = () => {
    editing.value = null;
    form.value = { name: '', code: props.nextCode, shift_type: 'Fixed', start_time: '8:00 AM', end_time: '5:00 PM', break_minutes: 60, grace_minutes: 15, working_days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], is_active: true };
    showForm.value = true;
};
const openEdit = (s) => {
    editing.value = s;
    form.value = { name: s.name, code: s.code, shift_type: s.shift_type, start_time: s.start_time === '—' ? '' : s.start_time, end_time: s.end_time === '—' ? '' : s.end_time, break_minutes: s.break_minutes, grace_minutes: s.grace_minutes, working_days: [...(s.working_days || [])], is_active: s.is_active };
    showForm.value = true;
};
const toggleDay = (d) => {
    const i = form.value.working_days.indexOf(d);
    if (i >= 0) form.value.working_days.splice(i, 1);
    else form.value.working_days.push(d);
};
const submitForm = () => {
    saving.value = true;
    const payload = { ...form.value };
    if (editing.value) {
        router.patch(route('hrm.workforce.shifts.update', editing.value.id), payload, {
            preserveScroll: true, onSuccess: () => { showForm.value = false; }, onFinish: () => { saving.value = false; },
        });
    } else {
        router.post(route('hrm.workforce.shifts.store'), payload, {
            preserveScroll: true, onSuccess: () => { showForm.value = false; }, onFinish: () => { saving.value = false; },
        });
    }
};
const toggleStatus = (s) => {
    router.post(route('hrm.workforce.shifts.toggle', s.id), {}, { preserveScroll: true });
};

// ---- View shift detail ----
const detail = ref(null);
const detailEmployees = ref([]);
const loadingDetail = ref(false);
const openView = async (s) => {
    loadingDetail.value = true;
    detail.value = s;
    detailEmployees.value = [];
    try {
        const { data } = await axios.get(route('hrm.workforce.shifts.show', s.id));
        detail.value = data.shift;
        detailEmployees.value = data.employees || [];
    } finally {
        loadingDetail.value = false;
    }
};
const closeView = () => { detail.value = null; detailEmployees.value = []; };

// ---- Assign employees (inside shift detail) ----
const assignSearch = ref('');
const assignResults = ref([]);
const assignSelected = ref([]);
const assignEffectiveDate = ref('');
let assignTimer = null;
const searchEmployees = () => {
    clearTimeout(assignTimer);
    assignTimer = setTimeout(async () => {
        const { data } = await axios.get(route('hrm.workforce.shifts.employees'), { params: { search: assignSearch.value.trim() } });
        assignResults.value = data.employees || [];
    }, 300);
};
const toggleAssign = (id) => {
    const i = assignSelected.value.indexOf(id);
    if (i >= 0) assignSelected.value.splice(i, 1);
    else assignSelected.value.push(id);
};
const submitAssign = () => {
    if (!detail.value || !assignSelected.value.length) return;
    router.post(route('hrm.workforce.shifts.assign', detail.value.id), { user_ids: assignSelected.value, effective_date: assignEffectiveDate.value || null }, {
        preserveScroll: true,
        onSuccess: () => { assignSelected.value = []; assignSearch.value = ''; assignResults.value = []; assignEffectiveDate.value = ''; openView(detail.value); },
    });
};
const unassign = (userId) => {
    if (!detail.value) return;
    router.delete(route('hrm.workforce.shifts.unassign', { shift: detail.value.id, user: userId }), {
        preserveScroll: true, onSuccess: () => openView(detail.value),
    });
};

const statusBadge = (s) => s === 'Active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200';
</script>

<template>
    <Head title="Shift Management" />
    <AuthenticatedLayout>
        <div class="p-6 space-y-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-black text-slate-900">Shift Management</h1>
                    <p class="text-xs text-slate-500 mt-1">Employees and their shifts. Shift definitions live under Settings.</p>
                </div>
                <button @click="settingsOpen = true" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-700 text-white rounded-xl text-xs font-bold shadow-lg">
                    <Settings class="w-4 h-4" /> Settings
                </button>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm"><p class="text-[11px] font-bold text-slate-400 uppercase">Total Shifts</p><p class="text-2xl font-black mt-1">{{ summary.total }}</p></div>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm"><p class="text-[11px] font-bold text-slate-400 uppercase">Active</p><p class="text-2xl font-black text-emerald-600 mt-1">{{ summary.active }}</p></div>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm"><p class="text-[11px] font-bold text-slate-400 uppercase">Inactive</p><p class="text-2xl font-black text-slate-400 mt-1">{{ summary.inactive }}</p></div>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm"><p class="text-[11px] font-bold text-slate-400 uppercase">Employees Assigned</p><p class="text-2xl font-black text-blue-600 mt-1">{{ summary.assigned }}</p></div>
            </div>

            <!-- Employee filters: search + department (+ shift) -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="relative md:col-span-2">
                    <Search class="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                    <input v-model="empSearch" placeholder="Search employee name or ID..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-xs" />
                </div>
                <select v-model="deptFilter" class="px-3 py-2 border border-slate-200 rounded-xl text-xs">
                    <option value="">All Departments</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <div class="flex gap-2">
                    <select v-model="rosterShiftFilter" class="flex-1 px-3 py-2 border border-slate-200 rounded-xl text-xs">
                        <option value="">All Shifts</option>
                        <option value="unassigned">Unassigned</option>
                        <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <button @click="clearRosterFilter" class="px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-50">Clear</button>
                </div>
            </div>

            <!-- Bulk group bar -->
            <div v-if="selected.length" class="bg-blue-600 text-white rounded-2xl p-3 shadow-lg flex flex-col md:flex-row md:items-center gap-2">
                <p class="text-xs font-bold px-1">{{ selected.length }} employee(s) selected — group into the same shift:</p>
                <div class="flex flex-1 gap-2 flex-wrap">
                    <select v-model="bulkShiftId" class="flex-1 min-w-40 px-3 py-2 rounded-xl text-xs text-slate-800 border-none outline-none">
                        <option value="">Select shift...</option>
                        <option v-for="s in shifts.filter(x => x.is_active)" :key="s.id" :value="s.id">{{ s.name }} ({{ s.start_time }} – {{ s.end_time }})</option>
                    </select>
                    <input v-model="bulkEffectiveDate" type="date" title="Effective date" class="px-3 py-2 rounded-xl text-xs text-slate-800 border-none outline-none" />
                    <button @click="submitBulk" :disabled="!bulkShiftId || bulkSaving" class="px-4 py-2 bg-white text-blue-700 rounded-xl text-xs font-black disabled:opacity-50">{{ bulkSaving ? 'Applying...' : 'Apply Shift' }}</button>
                    <button @click="selected = []" class="px-3 py-2 bg-blue-700 hover:bg-blue-800 rounded-xl text-xs font-bold">Clear</button>
                </div>
            </div>

            <!-- Main roster table (no shift code here) -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-[10px]">
                            <tr>
                                <th class="px-4 py-3 text-left w-10"><input type="checkbox" :checked="allChecked" @change="toggleAll" title="Select all" /></th>
                                <th class="px-4 py-3 text-left">Employee Name</th>
                                <th class="px-4 py-3 text-left">Department</th>
                                <th class="px-4 py-3 text-left">Job Position</th>
                                <th class="px-4 py-3 text-left">Shift</th>
                                <th class="px-4 py-3 text-left">Effective Date</th>
                                <th class="px-4 py-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in filteredRoster" :key="e.id" class="border-t border-slate-100 hover:bg-slate-50">
                                <td class="px-4 py-3"><input type="checkbox" :checked="selected.includes(e.id)" @change="toggleRow(e.id)" /></td>
                                <td class="px-4 py-3">
                                    <p class="font-bold text-slate-900">{{ e.name }}</p>
                                    <p class="text-[10px] text-slate-400 font-mono">{{ e.employee_id }}</p>
                                </td>
                                <td class="px-4 py-3">{{ e.department }}</td>
                                <td class="px-4 py-3">{{ e.position }}</td>
                                <td class="px-4 py-3">
                                    <template v-if="e.shift_id">
                                        <p class="font-bold text-slate-900">{{ e.shift_name }}</p>
                                        <p class="text-[10px] text-slate-400">{{ e.start_time }} – {{ e.end_time }}</p>
                                    </template>
                                    <span v-else class="px-2 py-1 rounded-full font-bold bg-amber-50 text-amber-700 border border-amber-200">Unassigned</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ e.effective_date || '—' }}</td>
                                <td class="px-4 py-3">
                                    <button @click="openEditEmployee(e)" class="p-1.5 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg text-slate-500" title="Edit shift"><Pencil class="w-3.5 h-3.5" /></button>
                                </td>
                            </tr>
                            <tr v-if="!filteredRoster.length"><td colspan="7" class="px-4 py-10 text-center text-slate-400">No employees found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Edit employee shift modal (time follows shift definition) -->
            <div v-if="editingEmployee" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50" @click="closeEditEmployee"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-5 space-y-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-black">Edit Shift</h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ editingEmployee.name }} · {{ editingEmployee.department }} · {{ editingEmployee.position }}</p>
                        </div>
                        <button @click="closeEditEmployee" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Shift</label>
                        <select v-model="editShiftId" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs">
                            <option value="">— Unassigned —</option>
                            <option v-for="s in shifts.filter(x => x.is_active)" :key="s.id" :value="String(s.id)">{{ s.name }} ({{ s.start_time }} – {{ s.end_time }})</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="block text-xs font-semibold mb-1">Start Time</label><input :value="editShift ? editShift.start_time : '—'" disabled class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-500" /></div>
                        <div><label class="block text-xs font-semibold mb-1">End Time</label><input :value="editShift ? editShift.end_time : '—'" disabled class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-500" /></div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Effective Date</label>
                        <input v-model="editEffectiveDate" type="date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" />
                    </div>
                    <p class="text-[11px] text-slate-400">Time is taken from the selected shift definition created under Settings.</p>
                    <button @click="saveEmployeeShift" :disabled="editSaving" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold disabled:opacity-50">{{ editSaving ? 'Saving...' : 'Save Shift' }}</button>
                </div>
            </div>

            <!-- Settings modal: shift definitions -->
            <div v-if="settingsOpen" class="fixed inset-0 z-40 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50" @click="settingsOpen = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto p-5 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-black text-lg">Shift Settings</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Create shift times here. Employee times always follow these definitions.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="openAdd" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold"><Plus class="w-3.5 h-3.5" /> Add Shift</button>
                            <button @click="settingsOpen = false" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="relative">
                            <Search class="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                            <input v-model="search" @input="applyFilter" placeholder="Search shift..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-xs" />
                        </div>
                        <select v-model="statusFilter" @change="applyFilter" class="px-3 py-2 border border-slate-200 rounded-xl text-xs">
                            <option value="">Status</option><option>Active</option><option>Inactive</option>
                        </select>
                        <select v-model="typeFilter" @change="applyFilter" class="px-3 py-2 border border-slate-200 rounded-xl text-xs">
                            <option value="">Shift Type</option><option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>

                    <div class="border border-slate-200 rounded-2xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-[10px]">
                                    <tr><th class="px-4 py-3 text-left">Shift Code</th><th class="px-4 py-3 text-left">Shift Name</th><th class="px-4 py-3 text-left">Start Time</th><th class="px-4 py-3 text-left">End Time</th><th class="px-4 py-3 text-left">Break</th><th class="px-4 py-3 text-left">Grace Period</th><th class="px-4 py-3 text-left">Assigned Employees</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-left">Action</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="s in shifts" :key="s.id" class="border-t border-slate-100 hover:bg-slate-50">
                                        <td class="px-4 py-3 font-mono font-bold">{{ s.code }}</td>
                                        <td class="px-4 py-3 font-bold">{{ s.name }}</td>
                                        <td class="px-4 py-3">{{ s.start_time }}</td>
                                        <td class="px-4 py-3">{{ s.end_time }}</td>
                                        <td class="px-4 py-3">{{ s.break }}</td>
                                        <td class="px-4 py-3">{{ s.grace }}</td>
                                        <td class="px-4 py-3 font-bold">{{ s.assigned }}</td>
                                        <td class="px-4 py-3"><span :class="['px-2 py-1 rounded-full font-bold border', statusBadge(s.status)]">{{ s.status }}</span></td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-1.5">
                                                <button @click="openView(s)" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold text-[11px]">View</button>
                                                <button @click="openEdit(s)" class="p-1.5 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg text-slate-500" title="Edit"><Pencil class="w-3.5 h-3.5" /></button>
                                                <button @click="toggleStatus(s)" class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg font-bold text-[11px] text-slate-500">{{ s.is_active ? 'Deactivate' : 'Activate' }}</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!shifts.length"><td colspan="9" class="px-4 py-10 text-center text-slate-400">No shifts found.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add / Edit shift definition modal -->
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50" @click="showForm = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-5 space-y-4">
                    <div class="flex items-start justify-between">
                        <h3 class="font-black">{{ editing ? 'Edit Shift' : 'Add Shift' }}</h3>
                        <button @click="showForm = false" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
                    </div>
                    <div>
                        <h4 class="text-xs font-black uppercase text-slate-500 mb-2">Basic Information</h4>
                        <label class="block text-xs font-semibold mb-1">Shift Name</label>
                        <input v-model="form.name" placeholder="Morning Shift" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs mb-2" />
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="block text-xs font-semibold mb-1">Shift Code</label><input v-model="form.code" placeholder="SHF-001" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" /></div>
                            <div><label class="block text-xs font-semibold mb-1">Shift Type</label>
                                <select v-model="form.shift_type" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs"><option v-for="t in types" :key="t" :value="t">{{ t }}</option></select>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-xs font-black uppercase text-slate-500 mb-2">Schedule</h4>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="block text-xs font-semibold mb-1">Start Time</label><input v-model="form.start_time" placeholder="8:00 AM" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" /></div>
                            <div><label class="block text-xs font-semibold mb-1">End Time</label><input v-model="form.end_time" placeholder="5:00 PM" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" /></div>
                            <div><label class="block text-xs font-semibold mb-1">Break Duration (minutes)</label><input v-model.number="form.break_minutes" type="number" min="0" max="480" placeholder="60 = 1 hour" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" /></div>
                            <div><label class="block text-xs font-semibold mb-1">Grace Period (minutes)</label><input v-model.number="form.grace_minutes" type="number" min="0" max="180" placeholder="15" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" /></div>
                        </div>
                        <label class="block text-xs font-semibold mt-3 mb-1">Working Days</label>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <label v-for="d in days" :key="d" class="flex items-center gap-1.5 border border-slate-200 rounded-lg px-2 py-1.5 cursor-pointer"><input type="checkbox" :checked="form.working_days.includes(d)" @change="toggleDay(d)" /> {{ d.slice(0, 3) }}</label>
                        </div>
                        <label class="block text-xs font-semibold mt-3 mb-1">Status</label>
                        <select v-model="form.is_active" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs"><option :value="true">Active</option><option :value="false">Inactive</option></select>
                    </div>
                    <button @click="submitForm" :disabled="saving" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold disabled:opacity-50">{{ saving ? 'Saving...' : (editing ? 'Save Changes' : 'Add Shift') }}</button>
                </div>
            </div>

            <!-- View shift detail modal -->
            <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50" @click="closeView"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-5 space-y-4">
                    <div class="flex items-start justify-between">
                        <div><h3 class="font-black text-lg">{{ detail.name }}</h3><p class="text-xs text-slate-500 font-mono">{{ detail.code }}</p></div>
                        <button @click="closeView" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
                    </div>
                    <div class="border border-slate-200 rounded-xl overflow-hidden text-xs">
                        <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Shift Type</span><span class="px-3 py-2 font-bold">{{ detail.shift_type }}</span></div>
                        <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Start</span><span class="px-3 py-2">{{ detail.start_time }}</span></div>
                        <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">End</span><span class="px-3 py-2">{{ detail.end_time }}</span></div>
                        <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Break</span><span class="px-3 py-2">{{ detail.break }}</span></div>
                        <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Grace Period</span><span class="px-3 py-2">{{ detail.grace }}</span></div>
                        <div class="grid grid-cols-2 border-b border-slate-100"><span class="px-3 py-2 text-slate-400 bg-slate-50">Working Days</span><span class="px-3 py-2">{{ detail.working_days_label }}</span></div>
                        <div class="grid grid-cols-2"><span class="px-3 py-2 text-slate-400 bg-slate-50">Status</span><span class="px-3 py-2 font-bold">{{ detail.status }}</span></div>
                    </div>
                    <div>
                        <h4 class="text-xs font-black uppercase text-slate-500 mb-2">Assigned Employees ({{ detailEmployees.length }})</h4>
                        <div v-if="loadingDetail" class="text-xs text-slate-400">Loading...</div>
                        <table v-else class="w-full text-xs">
                            <thead class="text-slate-400 uppercase text-[10px]"><tr><th class="text-left py-1">Employee</th><th class="text-left py-1">Position</th><th class="text-left py-1">Department</th><th class="text-left py-1">Work Schedule</th><th class="text-left py-1">Status</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="e in detailEmployees" :key="e.id" class="border-t border-slate-100">
                                    <td class="py-1.5 font-bold">{{ e.name }}</td><td>{{ e.position }}</td><td>{{ e.department }}</td><td>{{ e.work_schedule }}</td><td>{{ e.status }}</td>
                                    <td><button @click="unassign(e.id)" class="text-rose-500 font-bold">Remove</button></td>
                                </tr>
                                <tr v-if="!detailEmployees.length"><td colspan="6" class="py-4 text-center text-slate-400">No employees assigned.</td></tr>
                            </tbody>
                        </table>
                        <div class="mt-3 flex gap-2 flex-wrap">
                            <input v-model="assignSearch" @input="searchEmployees" placeholder="Search employee to assign..." class="flex-1 min-w-40 px-3 py-2 border border-slate-200 rounded-xl text-xs" />
                            <input v-model="assignEffectiveDate" type="date" title="Effective date" class="px-3 py-2 border border-slate-200 rounded-xl text-xs" />
                            <button @click="submitAssign" :disabled="!assignSelected.length" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold disabled:opacity-50">Assign ({{ assignSelected.length }})</button>
                        </div>
                        <div v-if="assignResults.length" class="mt-2 border border-slate-200 rounded-xl divide-y text-xs max-h-40 overflow-y-auto">
                            <label v-for="e in assignResults" :key="e.id" class="flex items-center gap-2 px-3 py-1.5 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" :checked="assignSelected.includes(e.id)" @change="toggleAssign(e.id)" />
                                <span class="font-bold">{{ e.name }}</span><span class="text-slate-400 font-mono">{{ e.employee_id }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
