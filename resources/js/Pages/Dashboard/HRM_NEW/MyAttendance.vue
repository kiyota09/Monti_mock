<template>
    <AuthenticatedLayout>
        <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50">
            <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative">
                <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>

                <Transition name="toast">
                    <div v-if="toast" class="fixed top-6 right-6 z-[100] flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl border"
                        :class="toastKind === 'error' ? 'bg-red-600 text-white border-red-400' : 'bg-slate-900 text-white border-white/10'">
                        <p class="text-sm font-bold">{{ toast }}</p>
                    </div>
                </Transition>

                <!-- Header -->
                <header class="mb-8 relative">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div>
                            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
                                <span class="hover:text-slate-700 cursor-pointer transition-colors">Dashboard</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                <span class="text-slate-800 font-semibold">My Attendance</span>
                            </nav>
                            <h1 class="text-4xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">My Attendance</h1>
                            <p class="text-sm text-slate-500 mt-1.5">Your personal time records and leave requests.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button @click="openLeave" class="flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-sm">
                                <CalendarPlus class="w-4 h-4" /> Request Leave
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Today's Attendance -->
                <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg p-6 mb-6 relative">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-1">Today's Attendance</h2>
                    <p class="text-xs text-slate-500 mb-4">Date: {{ today.date_label }}</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">Time In</p>
                            <p class="text-xl font-bold text-slate-900 mt-1">{{ today.time_in }}</p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">Time Out</p>
                            <p class="text-xl font-bold text-slate-900 mt-1">{{ today.time_out }}</p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">Status</p>
                            <p class="mt-2"><span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border', statusStyle(today.status).badge]"><span :class="['w-2 h-2 rounded-full', statusStyle(today.status).dot]"></span>{{ today.status }}</span></p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">Schedule</p>
                            <p class="text-xl font-bold text-slate-900 mt-1">{{ today.schedule }}</p>
                        </div>
                    </div>
                    <p v-if="today.status === 'On Leave'" class="mt-3 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-xl px-3 py-2">Status: On Leave · Leave Type: {{ today.leave_type }}</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button @click="clock('in')" :disabled="!today.can_clock_in || clockSaving" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">{{ clockSaving ? 'Saving…' : 'Time In' }}</button>
                        <button @click="clock('out')" :disabled="!today.can_clock_out || clockSaving" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-700 text-white rounded-xl text-sm font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">{{ clockSaving ? 'Saving…' : 'Time Out' }}</button>
                    </div>
                </div>

                <!-- Monthly Summary -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6 relative">
                    <div v-for="m in summaryCards" :key="m.label" class="bg-white rounded-2xl border border-slate-200/60 p-5 shadow-md">
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">{{ m.label }}</p>
                        <p class="text-2xl font-bold mt-1" :class="m.color">{{ m.value }}</p>
                    </div>
                </div>

                <!-- Attendance History -->
                <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg overflow-hidden mb-6 relative">
                    <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Attendance History</h2>
                            <div class="flex items-center bg-slate-100 rounded-xl p-0.5">
                                <button @click="viewMode = 'calendar'" :class="['flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-bold transition', viewMode === 'calendar' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-700']">
                                    <CalendarDays class="w-3.5 h-3.5" /> Calendar
                                </button>
                                <button @click="viewMode = 'table'" :class="['flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-bold transition', viewMode === 'table' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-700']">
                                    <List class="w-3.5 h-3.5" /> Table
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <input v-model="month" @change="onMonthChange" type="month" title="Month selector"
                                class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm" />
                            <input v-model="date" @change="onDateChange" type="date" title="Date filter"
                                class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm" />
                            <select v-model="status" @change="applyFilter" title="Status filter"
                                class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer">
                                <option value="">All Statuses</option>
                                <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <button v-if="date || status" @click="clearFilters" class="px-3 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition">Clear</button>
                        </div>
                    </div>
                    <!-- Calendar view -->
                    <div v-if="viewMode === 'calendar'" class="p-4 md:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <button @click="shiftMonth(-1)" title="Previous month" class="p-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 transition">
                                <ChevronLeft class="w-4 h-4" />
                            </button>
                            <p class="text-sm font-bold text-slate-900">{{ monthLabel }}</p>
                            <button @click="shiftMonth(1)" title="Next month" class="p-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 transition">
                                <ChevronRight class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="grid grid-cols-7 gap-1 md:gap-2 mb-1.5">
                            <div v-for="d in dayNames" :key="d" class="text-center text-[10px] font-bold uppercase tracking-wider text-slate-400 py-1">{{ d }}</div>
                        </div>
                        <div class="grid grid-cols-7 gap-1 md:gap-2">
                            <template v-for="cell in calendarDays" :key="cell.key">
                                <div v-if="cell.empty" class="h-16 md:h-24 rounded-xl bg-slate-50/50"></div>
                                <button v-else type="button" @click="selectedDate = cell.date"
                                    :title="cell.record ? `${cell.record.date_label} · ${cell.record.status}${cell.record.remark && cell.record.remark !== '—' ? ' · ' + cell.record.remark : ''}${cell.record.time_in !== '—' ? ' · ' + cell.record.time_in + ' - ' + cell.record.time_out : ''}` : cell.date"
                                    :class="['h-16 md:h-24 w-full rounded-xl border p-1.5 md:p-2 text-left transition flex flex-col overflow-hidden',
                                        calendarCard(cell.record?.status),
                                        selectedDate === cell.date ? 'ring-2 ring-blue-500 ring-offset-1' : '']">
                                    <span class="flex items-center justify-between gap-1">
                                        <span class="text-xs font-bold text-slate-800">{{ cell.day }}</span>
                                        <span v-if="cell.record && cell.record.status !== '—'" :class="['w-1.5 h-1.5 rounded-full shrink-0', statusStyle(cell.record.status).dot]"></span>
                                    </span>
                                    <span v-if="cell.record && cell.record.status !== '—'" class="mt-1 text-[9px] md:text-[10px] font-semibold leading-tight truncate text-slate-600">{{ cell.record.status }}</span>
                                    <span v-if="cell.record && cell.record.remark && cell.record.remark !== '—'" class="text-[8px] md:text-[9px] font-medium leading-tight text-slate-500 truncate" :title="cell.record.remark">{{ cell.record.remark }}</span>
                                    <span v-if="cell.record && cell.record.time_in !== '—'" class="hidden md:block mt-auto text-[9px] text-slate-500 leading-tight truncate">{{ cell.record.time_in }}</span>
                                </button>
                            </template>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-4 pt-4 border-t border-slate-100">
                            <span v-for="l in legend" :key="l.label" class="flex items-center gap-1.5 text-[10px] font-semibold text-slate-500">
                                <span :class="['w-2 h-2 rounded-full', l.dot]"></span>{{ l.label }}
                            </span>
                        </div>
                        <div v-if="selectedDate" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-xs font-bold text-slate-800">{{ selectedDateLabel }}</p>
                                <button @click="selectedDate = ''" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200 transition" title="Close">
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <div v-if="selectedRecord" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 text-xs">
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Schedule</p><p class="font-semibold text-slate-700 mt-0.5">{{ selectedRecord.schedule }}</p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Time In</p><p class="font-semibold text-slate-700 mt-0.5">{{ selectedRecord.time_in }}</p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Time Out</p><p class="font-semibold text-slate-700 mt-0.5">{{ selectedRecord.time_out }}</p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Hours</p><p class="font-semibold text-slate-700 mt-0.5">{{ selectedRecord.hours }}</p></div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Status</p>
                                    <span :class="['inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border', statusStyle(selectedRecord.status).badge]">
                                        <span :class="['w-1.5 h-1.5 rounded-full', statusStyle(selectedRecord.status).dot]"></span>{{ selectedRecord.status }}
                                    </span>
                                </div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase">Remark</p><p class="font-semibold text-slate-700 mt-0.5 truncate" :title="selectedRecord.remark">{{ selectedRecord.remark }}</p></div>
                            </div>
                            <p v-else class="text-xs text-slate-400">No attendance record for this day.</p>
                        </div>
                    </div>

                    <!-- Table view -->
                    <div v-if="viewMode === 'table'" class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gradient-to-r from-slate-50 to-transparent border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                    <th class="py-3 px-5">Date</th>
                                    <th class="py-3 px-5">Schedule</th>
                                    <th class="py-3 px-5">Time In</th>
                                    <th class="py-3 px-5">Time Out</th>
                                    <th class="py-3 px-5">Hours</th>
                                    <th class="py-3 px-5 text-center">Status</th>
                                    <th class="py-3 px-5">Remark</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="h in history" :key="h.date" class="hover:bg-blue-50/50 transition-colors text-xs">
                                    <td class="py-3 px-5 font-semibold text-slate-800 whitespace-nowrap">{{ h.date_label }}</td>
                                    <td class="py-3 px-5 text-slate-600 whitespace-nowrap">{{ h.schedule }}</td>
                                    <td class="py-3 px-5 font-medium">{{ h.time_in }}</td>
                                    <td class="py-3 px-5 font-medium">{{ h.time_out }}</td>
                                    <td class="py-3 px-5 font-semibold">{{ h.hours }}</td>
                                    <td class="py-3 px-5 text-center">
                                        <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold shadow-sm border', statusStyle(h.status).badge]">
                                            <span :class="['w-1.5 h-1.5 rounded-full', statusStyle(h.status).dot]"></span>{{ h.status }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-5 text-slate-500 max-w-40"><span class="block truncate" :title="h.remark">{{ h.remark }}</span></td>
                                </tr>
                                <tr v-if="!history.length">
                                    <td colspan="7" class="py-10 text-center text-sm text-slate-400">No attendance records for this period.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave modal -->
        <div v-if="showLeave" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" @click="closeLeave"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">Request Leave</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Goes through the Leave Management approval workflow.</p>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="text-xs font-bold text-slate-700 block mb-1.5">Leave Type</label>
                        <select v-model="leaveType" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option v-for="t in leaveTypes" :key="t.code" :value="t.code">{{ t.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-slate-700 block mb-1.5">Start Date</label>
                            <input v-model="leaveStart" type="date" :min="today.date" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-700 block mb-1.5">End Date</label>
                            <input v-model="leaveEnd" type="date" :min="leaveStart || today.date" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">Days: <b class="text-slate-800">{{ leaveDays }}</b></p>
                    <div>
                        <label class="text-xs font-bold text-slate-700 block mb-1.5">Reason</label>
                        <textarea v-model="leaveReason" @input="leaveError = ''" rows="3" placeholder="Personal" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        <p v-if="leaveError" class="text-xs text-rose-600 font-semibold mt-1.5">{{ leaveError }}</p>
                    </div>
                </div>
                <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="closeLeave" :disabled="leaveSaving" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition disabled:opacity-50">Cancel</button>
                    <button @click="submitLeave" :disabled="leaveSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-50">{{ leaveSaving ? 'Submitting…' : 'Submit Request' }}</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { CalendarPlus, CalendarDays, List, ChevronLeft, ChevronRight, X } from "lucide-vue-next";

const props = defineProps({
    today: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    history: { type: Array, default: () => [] },
    leaveTypes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ month: '', date: '', status: '' }) },
    permissions: { type: Object, default: () => ({}) },
});

const page = usePage();
const toast = ref('');
const toastKind = ref('ok');
let toastTimer = null;
const showToast = (msg, kind = 'ok') => {
    toast.value = msg;
    toastKind.value = kind;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.value = ''; }, 4000);
};
if (page.props.flash?.message) showToast(page.props.flash.message);
if (page.props.flash?.error) showToast(page.props.flash.error, 'error');

const summaryCards = computed(() => [
    { label: 'Present', value: props.summary.present ?? 0, color: 'text-emerald-600' },
    { label: 'Late', value: props.summary.late ?? 0, color: 'text-amber-600' },
    { label: 'Absent', value: props.summary.absent ?? 0, color: 'text-rose-600' },
    { label: 'On Leave', value: props.summary.on_leave ?? 0, color: 'text-blue-600' },
    { label: 'Incomplete', value: props.summary.incomplete ?? 0, color: 'text-purple-600' },
    { label: 'Total Working Days', value: props.summary.working_days ?? 0, color: 'text-slate-900' },
]);

const statusStyle = (s) => ({
    'Present': { badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
    'Late': { badge: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-400' },
    'Absent': { badge: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' },
    'On Leave': { badge: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' },
    'Incomplete': { badge: 'bg-purple-50 text-purple-700 border-purple-200', dot: 'bg-purple-500' },
    'In Progress': { badge: 'bg-orange-50 text-orange-700 border-orange-200', dot: 'bg-orange-500' },
    'Rest Day': { badge: 'bg-slate-100 text-slate-500 border-slate-200', dot: 'bg-slate-300' },
    'Holiday': { badge: 'bg-cyan-50 text-cyan-700 border-cyan-200', dot: 'bg-cyan-500' },
}[s] || { badge: 'bg-slate-100 text-slate-500 border-slate-200', dot: 'bg-slate-300' });

// ---- Filters ----
const month = ref(props.filters?.month || '');
const date = ref(props.filters?.date || '');
const status = ref(props.filters?.status || '');
const statusOptions = ['Present', 'Late', 'Absent', 'On Leave', 'Incomplete', 'In Progress', 'Rest Day', 'Holiday'];

const applyFilter = () => {
    router.get(route('hrm.workforce.my-attendance.index'), {
        month: month.value, date: date.value, status: status.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
};
const onDateChange = () => {
    if (date.value) month.value = String(date.value).slice(0, 7);
    applyFilter();
};
const onMonthChange = () => {
    date.value = '';
    selectedDate.value = '';
    applyFilter();
};
const clearFilters = () => {
    date.value = '';
    status.value = '';
    applyFilter();
};

// ---- Calendar view ----
const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const viewMode = ref('calendar');
const selectedDate = ref('');

const historyMap = computed(() => {
    const map = {};
    props.history.forEach((h) => { map[h.date] = h; });
    return map;
});

const calendarDays = computed(() => {
    const [y, m] = String(month.value || '').split('-').map(Number);
    if (!y || !m) return [];
    const leading = new Date(y, m - 1, 1).getDay();
    const daysInMonth = new Date(y, m, 0).getDate();
    const cells = [];
    for (let i = 0; i < leading; i++) cells.push({ key: `pad-${i}`, empty: true });
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({ key: dateStr, day: d, date: dateStr, record: historyMap.value[dateStr] || null });
    }
    while (cells.length % 7 !== 0) cells.push({ key: `tail-${cells.length}`, empty: true });
    return cells;
});

const monthLabel = computed(() => {
    const [y, m] = String(month.value || '').split('-').map(Number);
    if (!y || !m) return '';
    return new Date(y, m - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

const selectedRecord = computed(() => selectedDate.value ? (historyMap.value[selectedDate.value] || null) : null);
const selectedDateLabel = computed(() => {
    if (!selectedDate.value) return '';
    return new Date(`${selectedDate.value}T00:00:00`).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
});

const calendarCard = (s) => ({
    'Present': 'bg-emerald-50 border-emerald-200 hover:border-emerald-400',
    'Late': 'bg-amber-50 border-amber-200 hover:border-amber-400',
    'Absent': 'bg-rose-50 border-rose-200 hover:border-rose-400',
    'On Leave': 'bg-blue-50 border-blue-200 hover:border-blue-400',
    'Incomplete': 'bg-purple-50 border-purple-200 hover:border-purple-400',
    'In Progress': 'bg-orange-50 border-orange-200 hover:border-orange-400',
    'Rest Day': 'bg-slate-50 border-slate-200 hover:border-slate-300',
    'Holiday': 'bg-cyan-50 border-cyan-200 hover:border-cyan-400',
}[s] || 'bg-white border-slate-200 hover:border-slate-300');

const legend = [
    { label: 'Present', dot: 'bg-emerald-500' },
    { label: 'Late', dot: 'bg-amber-400' },
    { label: 'Absent', dot: 'bg-rose-500' },
    { label: 'On Leave', dot: 'bg-blue-500' },
    { label: 'Incomplete', dot: 'bg-purple-500' },
    { label: 'Rest Day', dot: 'bg-slate-300' },
    { label: 'Holiday', dot: 'bg-cyan-500' },
];

const shiftMonth = (delta) => {
    const [y, m] = String(month.value || '').split('-').map(Number);
    const base = y && m ? new Date(y, m - 1, 1) : new Date();
    base.setMonth(base.getMonth() + delta);
    month.value = `${base.getFullYear()}-${String(base.getMonth() + 1).padStart(2, '0')}`;
    date.value = '';
    selectedDate.value = '';
    applyFilter();
};

// ---- Clock ----
const clockSaving = ref(false);
const clock = (action) => {
    if (clockSaving.value) return;
    clockSaving.value = true;
    router.post(route(`hrm.workforce.my-attendance.clock-${action}`), {}, {
        preserveScroll: true,
        onSuccess: () => showToast(page.props.flash?.message || (action === 'in' ? 'Time in recorded.' : 'Time out recorded.')),
        onError: (e) => { showToast(Object.values(e)[0] || 'Clock action failed.', 'error'); },
        onFinish: () => { clockSaving.value = false; },
    });
};

// ---- Leave modal ----
const showLeave = ref(false);
const leaveType = ref('');
const leaveStart = ref('');
const leaveEnd = ref('');
const leaveReason = ref('');
const leaveError = ref('');
const leaveSaving = ref(false);

const openLeave = () => {
    showLeave.value = true;
    if (!leaveType.value && props.leaveTypes.length) leaveType.value = props.leaveTypes[0].code;
    leaveError.value = '';
};
const closeLeave = () => {
    showLeave.value = false;
    leaveSaving.value = false;
};
const leaveDays = computed(() => {
    if (!leaveStart.value || !leaveEnd.value) return '—';
    const diff = Math.round((new Date(leaveEnd.value) - new Date(leaveStart.value)) / 86400000) + 1;
    return Number.isFinite(diff) && diff > 0 ? diff : '—';
});
const submitLeave = () => {
    if (leaveSaving.value) return;
    if (!leaveType.value) { leaveError.value = 'Please select a leave type.'; return; }
    if (!leaveStart.value || !leaveEnd.value) { leaveError.value = 'Please select start and end dates.'; return; }
    if (!leaveReason.value.trim()) { leaveError.value = 'Please enter a reason.'; return; }
    leaveSaving.value = true;
    router.post(route('hrm.workforce.my-attendance.leave.store'), {
        leave_type: leaveType.value,
        start_date: leaveStart.value,
        end_date: leaveEnd.value,
        reason: leaveReason.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => { closeLeave(); showToast(page.props.flash?.message || 'Leave request submitted.'); },
        onError: (e) => { leaveSaving.value = false; leaveError.value = Object.values(e)[0] || 'Failed to submit leave request.'; },
        onFinish: () => { leaveSaving.value = false; },
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
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-12px); }
</style>
