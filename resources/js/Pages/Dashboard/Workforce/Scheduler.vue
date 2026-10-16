<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    CalendarDays, Clock, X, Plus, Trash2, Settings,
    ChevronLeft, ChevronRight, Users, Edit,
    MapPin, FileText, Briefcase, Eye, Zap, Sun, Moon, Sunset, List, Layers
} from 'lucide-vue-next';

const page = usePage();
const props = defineProps({
    employees:     { type: Array,   default: () => [] },
    shifts:        { type: Array,   default: () => [] },
    holidays:      { type: Array,   default: () => [] },
    isCeoMode:     { type: Boolean, default: false },
    ceoView:       { type: String,  default: 'planner' },
    selectedModule:{ type: String,  default: 'ALL' },
    allModules:    { type: Array,   default: () => [] },
});

const currentUser = page.props.auth.user;

// ─── CEO View Toggle ─────────────────────────────────────────────────────────
const ceoViewMode = ref(props.ceoView || 'planner');

const switchCeoView = (mode) => {
    ceoViewMode.value = mode;
    if (mode === 'schedules') {
        router.get(route('workforce.scheduler'), { ceo_view: 'schedules', module: selectedModule.value }, { preserveState: false });
    } else {
        router.get(route('workforce.scheduler'), {}, { preserveState: false });
    }
};

// ─── Module Filter ────────────────────────────────────────────────────────────
const selectedModule = ref(props.selectedModule || 'ALL');

const modules = computed(() => {
    if (props.isCeoMode && ceoViewMode.value === 'schedules') return ['ALL', ...props.allModules];
    if (!props.isCeoMode) return ['ALL', ...new Set(props.employees.map(e => e.role))];
    return [];
});

const filteredEmployees = computed(() => {
    if (props.isCeoMode && ceoViewMode.value === 'planner') return [];
    if (selectedModule.value === 'ALL') return props.employees;
    return props.employees.filter(e => e.role === selectedModule.value);
});

watch(selectedModule, (newMod) => {
    if (!props.isCeoMode) {
        router.get(route('workforce.scheduler'), { module: newMod }, { preserveState: true, only: ['employees', 'shifts'] });
    } else if (ceoViewMode.value === 'schedules') {
        router.get(route('workforce.scheduler'), { ceo_view: 'schedules', module: newMod }, { preserveState: true, only: ['employees', 'shifts'] });
    }
});

// ─── Calendar ─────────────────────────────────────────────────────────────────
const currentDate   = ref(new Date());
const currentYear   = computed(() => currentDate.value.getFullYear());
const currentMonth  = computed(() => currentDate.value.getMonth());

const calendarDays = computed(() => {
    const year = currentYear.value, month = currentMonth.value;
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const days = [];
    for (let i = 0; i < firstDay; i++) days.push({ day: null, date: null });
    for (let d = 1; d <= daysInMonth; d++) {
        const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        days.push({ day: d, date });
    }
    return days;
});

const shiftsByDate = computed(() => {
    const map = {};
    props.shifts.forEach(item => {
        const rawDate = item.effective_date || item.event_date;
        if (rawDate) {
            const date = String(rawDate).substring(0, 10);
            if (!map[date]) map[date] = [];
            map[date].push(item);
        }
    });
    return map;
});

const holidaysByDate = computed(() => {
    const map = {};
    props.holidays.forEach(hol => { 
        if (hol.date) {
            const date = String(hol.date).substring(0, 10);
            map[date] = hol; 
        }
    });
    return map;
});

const todayDate = computed(() => {
    const n = new Date();
    return `${n.getFullYear()}-${String(n.getMonth()+1).padStart(2,'0')}-${String(n.getDate()).padStart(2,'0')}`;
});

const isNonWorkingDay = (date) => {
    const hol = holidaysByDate.value[date];
    return hol && (hol.type === 'regular' || hol.type === 'special_non_working');
};

const prevMonth = () => { currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1); fetchMonthData(); };
const nextMonth = () => { currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1); fetchMonthData(); };
const fetchMonthData = () => {
    const params = { month: currentMonth.value + 1, year: currentYear.value };
    if (props.isCeoMode && ceoViewMode.value === 'schedules') {
        params.ceo_view = 'schedules';
        if (selectedModule.value !== 'ALL') params.module = selectedModule.value;
    } else if (!props.isCeoMode && selectedModule.value !== 'ALL') {
        params.module = selectedModule.value;
    }
    router.get(route('workforce.scheduler'), params, { preserveState: true, preserveScroll: true, only: ['shifts', 'holidays'] });
};

const isPlannerView   = computed(() => props.isCeoMode && ceoViewMode.value === 'planner');
const isScheduleView  = computed(() => !props.isCeoMode || (props.isCeoMode && ceoViewMode.value === 'schedules'));

// ─── CEO Planner Event Modal ──────────────────────────────────────────────────
const isEventModalOpen = ref(false);
const editingEvent     = ref(null);
const eventForm        = useForm({ event_date: '', title: '', start_time: '', end_time: '', location: '', attendee: '', notes: '' });

const openEventModal = (date, event = null) => {
    if (event) {
        editingEvent.value = event;
        Object.assign(eventForm, { event_date: String(event.event_date).substring(0, 10), title: event.title, start_time: event.start_time || '', end_time: event.end_time || '', location: event.location || '', attendee: event.attendee || '', notes: event.notes || '' });
    } else {
        editingEvent.value = null;
        eventForm.reset();
        eventForm.event_date = String(date).substring(0, 10);
    }
    isEventModalOpen.value = true;
};
const closeEventModal = () => { isEventModalOpen.value = false; editingEvent.value = null; eventForm.reset(); };
const submitEvent = () => {
    if (!eventForm.title) { alert('Please enter an event title.'); return; }
    const url = editingEvent.value ? route('workforce.scheduler.planner.update', editingEvent.value.id) : route('workforce.scheduler.planner.store');
    eventForm[editingEvent.value ? 'patch' : 'post'](url, {
        preserveScroll: true,
        onSuccess: () => { closeEventModal(); fetchMonthData(); },
        onError: (errors) => alert(Object.values(errors)[0]),
    });
};
const deleteEvent = (eventId, title) => {
    if (confirm(`Delete event "${title}"?`)) {
        router.delete(route('workforce.scheduler.planner.destroy', eventId), { preserveScroll: true, onSuccess: () => fetchMonthData() });
    }
};

// ─── Event List Modal ─────────────────────────────────────────────────────────
const isEventListModalOpen = ref(false);
const selectedDateEvents = ref(null);
const eventsForSelectedDate = ref([]);

const openEventListModal = (date) => {
    if (!isPlannerView.value) return;
    const events = shiftsByDate.value[date] || [];
    if (events.length === 0) {
        openEventModal(date);
        return;
    }
    selectedDateEvents.value = date;
    eventsForSelectedDate.value = events;
    isEventListModalOpen.value = true;
};
const closeEventListModal = () => {
    isEventListModalOpen.value = false;
    selectedDateEvents.value = null;
    eventsForSelectedDate.value = [];
};

// ─── Shift Assignment Modal ───────────────────────────────────────────────────
const isShiftModalOpen = ref(false);
const shiftForm        = useForm({ user_id: null, shift_type: '', effective_date: '', schedule_range: '' });

const openShiftModal = (date) => {
    if (props.isCeoMode) return;
    if (isNonWorkingDay(date)) return;
    shiftForm.user_id = null; shiftForm.effective_date = date; shiftForm.shift_type = ''; shiftForm.schedule_range = '';
    isShiftModalOpen.value = true;
};
const closeShiftModal = () => { isShiftModalOpen.value = false; shiftForm.reset(); };
const submitShift = () => {
    if (!shiftForm.user_id || !shiftForm.shift_type) { alert('Please select an employee and shift type.'); return; }
    shiftForm.post(route('workforce.scheduler.shift.store'), { preserveScroll: true, onSuccess: () => closeShiftModal(), onError: (errors) => alert(Object.values(errors)[0]) });
};
const deleteShift = (id) => {
    if (confirm('Remove this shift?')) router.delete(route('workforce.scheduler.shift.destroy', id), { preserveScroll: true });
};

const handleCellClick = (date) => {
    if (!date) return;
    if (isPlannerView.value) {
        openEventListModal(date);
    } else {
        openShiftModal(date);
    }
};

// ─── Upcoming Agenda List for CEO ─────────────────────────────────────────────
const upcomingEvents = computed(() => {
    if (!isPlannerView.value) return [];
    return [...props.shifts].sort((a, b) => new Date(a.event_date) - new Date(b.event_date));
});

// ─── ADVANCED Bulk Shift Assignment ───────────────────────────────────────────
const isBulkModalOpen = ref(false);
const bulkAssignForm = useForm({
    user_id: null,
    action: 'assign',
    shift_type: 'Morning',
    start_date: '',
    end_date: '',
    schedule_range: ''
});

const openBulkModal = () => { bulkAssignForm.reset(); isBulkModalOpen.value = true; };
const closeBulkModal = () => { isBulkModalOpen.value = false; };
const submitBulkAction = (actionType) => {
    bulkAssignForm.action = actionType;
    if(actionType === 'assign' && !bulkAssignForm.shift_type) {
        alert("Please select a shift type to assign."); return;
    }
    if(!bulkAssignForm.user_id || !bulkAssignForm.start_date || !bulkAssignForm.end_date) {
        alert("Please fill all required fields (Employee and Dates)."); return;
    }
    
    if(actionType === 'assign' && !bulkAssignForm.schedule_range) {
        bulkAssignForm.schedule_range = getShiftRange(bulkAssignForm.shift_type);
    }

    bulkAssignForm.post(route('workforce.scheduler.shift.bulk'), {
        preserveScroll: true,
        onSuccess: () => {
            closeBulkModal();
            fetchMonthData();
            alert(actionType === 'assign' ? 'Bulk shifts assigned successfully.' : 'Shifts successfully cleared.');
        },
        onError: (errors) => alert(Object.values(errors)[0])
    });
};

const bulkForm = useForm({ user_id: null, action: 'assign', shift_type: '', start_date: '', end_date: '' });
const assignWeekly = (employeeId, shiftType) => {
    const start = new Date(currentYear.value, currentMonth.value, 1);
    const end   = new Date(currentYear.value, currentMonth.value + 1, 0);
    const firstMonday = new Date(start);
    firstMonday.setDate(start.getDate() - start.getDay() + 1);
    const dates = [];
    for (let d = new Date(firstMonday); d <= end; d.setDate(d.getDate() + 7)) {
        if (d.getMonth() === currentMonth.value) dates.push(d.toISOString().slice(0, 10));
    }
    if (!dates.length) { alert('No full weeks in this month.'); return; }
    
    bulkForm.user_id = employeeId;
    bulkForm.shift_type = shiftType;
    bulkForm.action = 'assign';
    bulkForm.start_date = dates[0];
    bulkForm.end_date = dates[dates.length - 1];
    
    bulkForm.post(route('workforce.scheduler.shift.bulk'), { preserveScroll: true, onSuccess: () => alert('Weekly shifts assigned.'), onError: (errors) => alert(Object.values(errors)[0]) });
};
const assignMonthly = (employeeId, shiftType) => {
    const year = currentYear.value, month = currentMonth.value + 1;
    const lastDay = new Date(year, month, 0).getDate();
    
    bulkForm.user_id = employeeId;
    bulkForm.shift_type = shiftType;
    bulkForm.action = 'assign';
    bulkForm.start_date = `${year}-${String(month).padStart(2,'0')}-01`;
    bulkForm.end_date = `${year}-${String(month).padStart(2,'0')}-${String(lastDay).padStart(2,'0')}`;
    
    bulkForm.post(route('workforce.scheduler.shift.bulk'), { preserveScroll: true, onSuccess: () => alert('Monthly shifts assigned.'), onError: (errors) => alert(Object.values(errors)[0]) });
};

// ─── Holiday Management ───────────────────────────────────────────────────────
const isHolidayModalOpen       = ref(false);
const isManageHolidaysModalOpen = ref(false);
const editingHoliday           = ref(null);
const holidayForm              = useForm({ holiday_date: '', holiday_name: '', holiday_type: 'regular', premium_rate: 1.0 });
const holidayEditForm          = useForm({ id: null, holiday_date: '', holiday_name: '', holiday_type: 'regular', premium_rate: 1.0 });

const openHolidayModal  = () => { holidayForm.reset(); isHolidayModalOpen.value = true; };
const closeHolidayModal = () => { isHolidayModalOpen.value = false; holidayForm.reset(); };
const submitHoliday = () => {
    holidayForm.post(route('workforce.scheduler.holiday.store'), { preserveScroll: true, onSuccess: () => closeHolidayModal(), onError: (errors) => alert(Object.values(errors)[0]) });
};
const deleteHoliday = (id, name) => {
    if (confirm(`Delete holiday "${name}"?`)) router.delete(route('workforce.scheduler.holiday.destroy', id), { preserveScroll: true });
};
const openManageHolidays  = () => { isManageHolidaysModalOpen.value = true; };
const closeManageHolidays = () => { isManageHolidaysModalOpen.value = false; editingHoliday.value = null; holidayEditForm.reset(); };
const editHoliday = (hol) => {
    editingHoliday.value = hol;
    Object.assign(holidayEditForm, { id: hol.id, holiday_date: String(hol.date).substring(0, 10), holiday_name: hol.name, holiday_type: hol.type, premium_rate: hol.premium_rate });
};
const updateHoliday = () => {
    holidayEditForm.patch(route('workforce.scheduler.holiday.update', holidayEditForm.id), {
        preserveScroll: true,
        onSuccess: () => { closeManageHolidays(); fetchMonthData(); },
        onError: (errors) => alert(Object.values(errors)[0]),
    });
};

// ─── Shift Types ──────────────────────────────────────────────────────────────
const shiftTypes = ref([
    { name: 'Morning',   start: '08:00', end: '17:00' },
    { name: 'Afternoon', start: '16:00', end: '01:00' },
    { name: 'Graveyard', start: '00:00', end: '09:00' },
]);
const isShiftConfigOpen = ref(false);
const updateShiftTimes  = () => { isShiftConfigOpen.value = false; alert('Shift times updated locally.'); };
const getShiftRange     = (type) => { const s = shiftTypes.value.find(x => x.name === type); return s ? `${s.start} – ${s.end}` : ''; };

const getShiftChipClass = (shiftType) => ({
    'Morning':   'bg-amber-50  dark:bg-amber-900/20  text-amber-700  dark:text-amber-300  border-amber-200  dark:border-amber-800',
    'Afternoon': 'bg-sky-50    dark:bg-sky-900/20    text-sky-700    dark:text-sky-300    border-sky-200    dark:border-sky-800',
    'Graveyard': 'bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-300 border-violet-200 dark:border-violet-800',
}[shiftType] || 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600');

const shiftEmoji = (t) => ({ Morning: '☀️', Afternoon: '🌤️', Graveyard: '🌙' }[t] || '⏰');

const employeesWithShift = computed(() => filteredEmployees.value.map(e => ({ ...e, selectedShiftType: '' })));

const monthName = computed(() => new Date(currentYear.value, currentMonth.value).toLocaleString('default', { month: 'long' }));
</script>

<template>
    <Head title="Workforce Scheduler" />
    <AuthenticatedLayout>

        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto space-y-6 p-4 sm:p-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex items-center gap-3.5">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop shrink-0">
                                <CalendarDays class="h-7 w-7 text-white" />
                            </div>
                            <div>
                                <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                    <Briefcase class="h-3.5 w-3.5" /> Workforce · Scheduling
                                </p>
                                <h1 class="text-2xl sm:text-3xl font-black tracking-tight leading-none">
                                    <span v-if="isPlannerView">Executive Planner</span>
                                    <span v-else-if="isCeoMode && ceoViewMode === 'schedules'">Schedule Overview</span>
                                    <span v-else>Shift Scheduler</span>
                                </h1>
                                <p class="text-sm text-blue-100/90 mt-1">
                                    <span v-if="isPlannerView">Your personal calendar for meetings &amp; business events</span>
                                    <span v-else-if="isCeoMode && ceoViewMode === 'schedules'">Read-only view of all department shift schedules</span>
                                    <span v-else>Assign shifts, manage holidays and bulk scheduling</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            <button v-if="!isCeoMode" @click="openBulkModal"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-white text-indigo-700 text-xs font-black shadow-lg hover:scale-105 active:scale-95 transition-all">
                                <Layers class="h-3.5 w-3.5" /> Bulk Schedule
                            </button>
                            <button @click="openHolidayModal"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 text-white text-xs font-bold transition-all active:scale-95">
                                <Plus class="h-3.5 w-3.5" /> Add Holiday
                            </button>
                            <button @click="openManageHolidays"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 text-white text-xs font-bold transition-all active:scale-95">
                                <CalendarDays class="h-3.5 w-3.5" /> Holidays
                            </button>
                            <button v-if="!isCeoMode" @click="isShiftConfigOpen = true"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 text-white text-xs font-bold transition-all active:scale-95">
                                <Settings class="h-3.5 w-3.5" /> Shift Times
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="isCeoMode" class="animate-fade-up flex gap-1 bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 rounded-3xl p-1.5 w-fit shadow-sm" style="animation-delay: 60ms">
                    <button @click="switchCeoView('planner')"
                        :class="['flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200',
                            ceoViewMode === 'planner'
                                ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-md shadow-blue-500/20'
                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white']">
                        <Briefcase class="h-4 w-4" />
                        My Planner
                    </button>
                    <button @click="switchCeoView('schedules')"
                        :class="['flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200',
                            ceoViewMode === 'schedules'
                                ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-md shadow-blue-500/20'
                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white']">
                        <Users class="h-4 w-4" />
                        All Schedules
                    </button>
                </div>

                <div v-if="isCeoMode && ceoViewMode === 'schedules'"
                    class="animate-fade-up flex items-center gap-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800/50 rounded-3xl px-4 py-3 shadow-sm" style="animation-delay: 80ms">
                    <Eye class="h-4.5 w-4.5 text-indigo-500 dark:text-indigo-400 shrink-0" />
                    <p class="text-sm text-indigo-700 dark:text-indigo-300">
                        Viewing employee shift schedules in <strong>read-only</strong> mode. Switch to
                        <button @click="switchCeoView('planner')" class="underline font-bold">My Planner</button>
                        to manage your events.
                    </p>
                </div>

                <div v-if="!isCeoMode || (isCeoMode && ceoViewMode === 'schedules')"
                    class="animate-fade-up group relative flex flex-wrap gap-2 bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 rounded-3xl p-2.5 shadow-sm hover:shadow-xl hover:shadow-indigo-500/10 transition-all duration-300 overflow-hidden" style="animation-delay: 100ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <button v-for="mod in modules" :key="mod" @click="selectedModule = mod"
                        :class="['px-4 py-1.5 rounded-xl text-xs font-bold transition-all',
                            selectedModule === mod
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-600 hover:text-slate-700 dark:hover:text-white']">
                        {{ mod === 'ALL' ? '⊞ All Modules' : mod }}
                    </button>
                </div>

                <div v-if="!isCeoMode && filteredEmployees.length"
                    class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 rounded-3xl shadow-sm hover:shadow-xl hover:shadow-indigo-500/10 transition-all duration-300 overflow-hidden" style="animation-delay: 140ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700/60">
                        <div class="flex items-center gap-2">
                            <div class="h-6 w-6 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                <Zap class="h-3.5 w-3.5 text-amber-500" />
                            </div>
                            <h3 class="text-sm font-extrabold text-slate-700 dark:text-slate-200">Quick Weekly/Monthly Assignment</h3>
                        </div>
                        <button @click="openBulkModal" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 underline">Advanced Options &rarr;</button>
                    </div>
                    <div class="overflow-x-auto p-4">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-700">
                                    <th class="pb-2.5 pr-6 text-[10px] font-black text-slate-400 uppercase tracking-wider">Employee</th>
                                    <th class="pb-2.5 pr-6 text-[10px] font-black text-slate-400 uppercase tracking-wider">Shift Type</th>
                                    <th class="pb-2.5 text-[10px] font-black text-slate-400 uppercase tracking-wider">Quick Assign</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
                                <tr v-for="emp in employeesWithShift" :key="emp.id" class="group">
                                    <td class="py-3 pr-6">
                                        <div class="flex items-center gap-2.5">
                                            <div class="h-8 w-8 rounded-full bg-gradient-to-br from-slate-400 to-slate-600 flex items-center justify-center text-white text-xs font-extrabold shrink-0 select-none">
                                                {{ emp.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-800 dark:text-white leading-tight">{{ emp.name }}</p>
                                                <p class="text-[10px] text-slate-400 leading-tight">{{ emp.role }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 pr-6">
                                        <select v-model="emp.selectedShiftType"
                                            class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 border-none text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 outline-none">
                                            <option value="">— Select —</option>
                                            <option v-for="st in shiftTypes" :key="st.name" :value="st.name">{{ shiftEmoji(st.name) }} {{ st.name }}</option>
                                        </select>
                                    </td>
                                    <td class="py-3 flex gap-2">
                                        <button @click="emp.selectedShiftType && assignWeekly(emp.id, emp.selectedShiftType)"
                                            :disabled="!emp.selectedShiftType"
                                            class="px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-[10px] font-black uppercase tracking-wider transition disabled:opacity-40 disabled:cursor-not-allowed">
                                            Weekly
                                        </button>
                                        <button @click="emp.selectedShiftType && assignMonthly(emp.id, emp.selectedShiftType)"
                                            :disabled="!emp.selectedShiftType"
                                            class="px-3 py-1.5 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-[10px] font-black uppercase tracking-wider transition disabled:opacity-40 disabled:cursor-not-allowed">
                                            Monthly
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 rounded-3xl shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay: 180ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />

                    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                        <button @click="prevMonth"
                            class="h-9 w-9 flex items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-700 dark:hover:text-white transition-all">
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <div class="text-center">
                            <p class="text-lg font-extrabold text-slate-900 dark:text-white leading-tight">{{ monthName }}</p>
                            <p class="text-xs font-semibold text-slate-400">{{ currentYear }}</p>
                        </div>
                        <button @click="nextMonth"
                            class="h-9 w-9 flex items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-700 dark:hover:text-white transition-all">
                            <ChevronRight class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="grid grid-cols-7 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30">
                        <div v-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day"
                            class="py-2.5 text-center text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">
                            {{ day }}
                        </div>
                    </div>

                    <div class="grid grid-cols-7 divide-x divide-y divide-slate-100 dark:divide-slate-700/60">
                        <div v-for="(cell, idx) in calendarDays" :key="idx"
                            @click="handleCellClick(cell.date)"
                            :class="[
                                'relative min-h-[118px] p-2 transition-colors duration-150 group',
                                !cell.day
                                    ? 'bg-slate-50/60 dark:bg-slate-900/20'
                                    : cell.date === todayDate
                                        ? 'bg-blue-50/70 dark:bg-blue-900/10'
                                        : 'bg-white dark:bg-slate-800',
                                cell.date && holidaysByDate[cell.date]
                                    ? '!bg-rose-50 dark:!bg-rose-900/10'
                                    : '',
                                cell.date && isPlannerView
                                    ? 'hover:bg-indigo-50/60 dark:hover:bg-indigo-900/10 cursor-pointer'
                                    : cell.date && !isNonWorkingDay(cell.date) && !props.isCeoMode
                                        ? 'hover:bg-slate-50 dark:hover:bg-slate-700/40 cursor-pointer'
                                        : cell.date && isNonWorkingDay(cell.date) && !props.isCeoMode
                                            ? 'cursor-not-allowed opacity-60'
                                            : '',
                            ]">

                            <div class="flex items-start justify-between mb-1">
                                <span :class="[
                                    'inline-flex items-center justify-center h-6 w-6 rounded-full text-xs font-extrabold transition-all',
                                    cell.date === todayDate
                                        ? 'bg-blue-600 text-white shadow-sm'
                                        : 'text-slate-500 dark:text-slate-400',
                                ]">{{ cell.day || '' }}</span>

                                <button v-if="isPlannerView && cell.date"
                                    @click.stop="openEventModal(cell.date)"
                                    class="opacity-0 group-hover:opacity-100 h-5 w-5 rounded-full bg-indigo-500 hover:bg-indigo-600 text-white flex items-center justify-center text-[11px] font-black transition-all shadow-sm">
                                    +
                                </button>
                            </div>

                            <div v-if="cell.date && holidaysByDate[cell.date]"
                                class="mb-1.5 flex items-center gap-1 bg-rose-100 dark:bg-rose-900/30 rounded-md px-1.5 py-0.5">
                                <span class="text-[9px]">🚫</span>
                                <span class="text-[9px] font-black text-rose-600 dark:text-rose-400 truncate leading-tight">{{ holidaysByDate[cell.date].name }}</span>
                            </div>

                            <div v-if="cell.date && shiftsByDate[cell.date]" class="space-y-1">
                                <div v-for="item in shiftsByDate[cell.date]" :key="item.id"
                                    :class="[
                                        'rounded-md px-1.5 py-1 leading-snug',
                                        isPlannerView
                                            ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 shadow-sm'
                                            : getShiftChipClass(item.shift_type),
                                    ]">

                                    <div v-if="isPlannerView" class="flex flex-col">
                                        <span class="font-extrabold text-[10px] truncate">{{ item.title }}</span>
                                        <span v-if="item.start_time" class="text-[9px] opacity-80 font-bold mt-0.5 flex items-center gap-1">
                                            <Clock class="w-2.5 h-2.5" /> {{ item.start_time }}{{ item.end_time ? ' - ' + item.end_time : '' }}
                                        </span>
                                    </div>

                                    <div v-else class="flex items-center justify-between gap-1 text-[9px] font-bold">
                                        <span class="truncate">{{ shiftEmoji(item.shift_type) }} {{ item.user_name }}</span>
                                        <button v-if="!props.isCeoMode" @click.stop="deleteShift(item.id)"
                                            class="text-red-400 hover:text-red-600 shrink-0 transition-colors">
                                            <Trash2 class="h-2.5 w-2.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="isPlannerView && cell.date && (!shiftsByDate[cell.date] || !shiftsByDate[cell.date].length)"
                                class="opacity-0 group-hover:opacity-100 transition-opacity absolute bottom-1.5 left-0 right-0 text-center pointer-events-none">
                                <span class="text-[9px] text-indigo-400">+ tap to add</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400 px-1">
                    <template v-if="!isPlannerView">
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-amber-300"></span>Morning</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-sky-300"></span>Afternoon</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-violet-300"></span>Graveyard</span>
                    </template>
                    <template v-else>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-indigo-300"></span>CEO Events</span>
                    </template>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded bg-rose-300"></span>Holiday</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>Today</span>
                </div>

                <div v-if="isPlannerView && upcomingEvents.length > 0" class="animate-fade-up mt-6 bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 rounded-3xl shadow-sm hover:shadow-xl hover:shadow-indigo-500/10 transition-all duration-300 overflow-hidden" style="animation-delay: 220ms">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2">
                        <List class="h-5 w-5 text-indigo-500" />
                        <h3 class="font-extrabold text-slate-800 dark:text-white">Upcoming Agenda</h3>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                        <div v-for="ev in upcomingEvents" :key="ev.id" class="p-4 flex items-start justify-between hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-black text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded-md uppercase tracking-wide">
                                        {{ String(ev.event_date).substring(0, 10) }}
                                    </span>
                                    <span v-if="ev.start_time" class="text-xs font-bold text-slate-500">
                                        <Clock class="inline h-3.5 w-3.5 mb-0.5" /> {{ ev.start_time }}{{ ev.end_time ? ' – ' + ev.end_time : '' }}
                                    </span>
                                </div>
                                <h4 class="font-extrabold text-slate-900 dark:text-white text-base">{{ ev.title }}</h4>
                                <div class="flex items-center gap-4 mt-1 text-sm text-slate-500">
                                    <span v-if="ev.location" class="flex items-center gap-1"><MapPin class="h-3.5 w-3.5" /> {{ ev.location }}</span>
                                    <span v-if="ev.attendee" class="flex items-center gap-1"><Users class="h-3.5 w-3.5" /> {{ ev.attendee }}</span>
                                </div>
                                <p v-if="ev.notes" class="mt-2 text-sm text-slate-600 dark:text-slate-400 border-l-2 border-indigo-200 dark:border-indigo-800 pl-2">
                                    {{ ev.notes }}
                                </p>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button @click="openEventModal(String(ev.event_date).substring(0, 10), ev)" class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 hover:bg-indigo-100 transition"><Edit class="h-4 w-4" /></button>
                                <button @click="deleteEvent(ev.id, ev.title)" class="p-1.5 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 hover:bg-red-100 transition"><Trash2 class="h-4 w-4" /></button>
                            </div>
                        </div>
                    </div>
                </div>

            </div></div>
            
        <Teleport to="body">
        <Transition name="modal">
        <div v-if="isEventListModalOpen"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="closeEventListModal">
            <div class="modal-panel bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-lg w-full max-h-[85vh] flex flex-col overflow-hidden">
                <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 flex items-center justify-between shrink-0">
                    <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                    <div>
                        <p class="text-white/70 text-xs font-semibold">All events for</p>
                        <h2 class="text-white font-extrabold text-xl">{{ selectedDateEvents }}</h2>
                    </div>
                    <button @click="closeEventListModal" class="h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="overflow-y-auto flex-1 p-5 space-y-3">
                    <div v-if="eventsForSelectedDate.length === 0" class="text-center py-10 text-slate-400">
                        No events scheduled on this day.
                    </div>
                    <div v-for="ev in eventsForSelectedDate" :key="ev.id"
                        class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <h3 class="font-extrabold text-slate-900 dark:text-white text-base">{{ ev.title }}</h3>
                                <div class="mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-300">
                                    <div v-if="ev.start_time" class="flex items-center gap-1.5">
                                        <Clock class="h-3.5 w-3.5" />
                                        <span>{{ ev.start_time }}{{ ev.end_time ? ' – ' + ev.end_time : '' }}</span>
                                    </div>
                                    <div v-if="ev.location" class="flex items-center gap-1.5">
                                        <MapPin class="h-3.5 w-3.5" />
                                        <span>{{ ev.location }}</span>
                                    </div>
                                    <div v-if="ev.attendee" class="flex items-center gap-1.5">
                                        <Users class="h-3.5 w-3.5" />
                                        <span>{{ ev.attendee }}</span>
                                    </div>
                                    <div v-if="ev.notes" class="flex items-start gap-1.5">
                                        <FileText class="h-3.5 w-3.5 shrink-0 mt-0.5" />
                                        <span class="whitespace-pre-wrap">{{ ev.notes }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button @click="() => { closeEventListModal(); openEventModal(selectedDateEvents, ev); }"
                                    class="p-1.5 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 hover:bg-blue-200 transition">
                                    <Edit class="h-4 w-4" />
                                </button>
                                <button @click="deleteEvent(ev.id, ev.title)"
                                    class="p-1.5 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-500 hover:bg-red-200 transition">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between shrink-0 bg-slate-50 dark:bg-slate-900/50">
                    <button @click="() => { closeEventListModal(); openEventModal(selectedDateEvents); }" 
                        class="px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-bold shadow-sm transition inline-flex items-center gap-1">
                        <Plus class="w-4 h-4" /> Add Event
                    </button>
                    <button @click="closeEventListModal" class="px-5 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-300 transition">Close</button>
                </div>
            </div>
        </div>
        </Transition>
        </Teleport>

        <Teleport to="body">
        <Transition name="modal">
        <div v-if="isEventModalOpen"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="closeEventModal">
            <div class="modal-panel bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 flex items-start justify-between">
                    <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                    <div>
                        <p class="text-white/70 text-xs font-semibold">{{ editingEvent ? 'Editing event' : 'New event' }}</p>
                        <h2 class="text-white font-extrabold text-lg leading-tight">{{ editingEvent ? editingEvent.title : 'Add Event' }}</h2>
                    </div>
                    <button @click="closeEventModal" class="h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition shrink-0">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="p-5 space-y-3.5">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Date</label>
                        <input type="date" v-model="eventForm.event_date"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Title <span class="text-red-500">*</span></label>
                        <input type="text" v-model="eventForm.title" placeholder="Board meeting, client call…"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Start Time</label>
                            <input type="time" v-model="eventForm.start_time"
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">End Time</label>
                            <input type="time" v-model="eventForm.end_time"
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Location</label>
                        <input type="text" v-model="eventForm.location" placeholder="Office, Zoom link, conference room…"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Attendees</label>
                        <input type="text" v-model="eventForm.attendee" placeholder="Names or teams"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Notes</label>
                        <textarea v-model="eventForm.notes" rows="2" placeholder="Agenda, reminders…"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button @click="closeEventModal" class="flex-1 py-2.5 text-slate-500 dark:text-slate-400 font-bold text-sm hover:text-slate-700 transition">Cancel</button>
                        <button @click="submitEvent"
                            class="flex-1 py-2.5 bg-gradient-to-r from-indigo-500 to-blue-600 hover:from-indigo-600 hover:to-blue-700 text-white rounded-xl font-extrabold text-sm transition shadow-lg shadow-blue-500/20">
                            {{ editingEvent ? 'Update Event' : 'Save Event' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Transition>
        </Teleport>

        <Teleport to="body">
        <Transition name="modal">
        <div v-if="!isCeoMode && isBulkModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="closeBulkModal">
            <div class="modal-panel bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 flex items-start justify-between">
                    <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                    <div>
                        <h2 class="text-white font-extrabold text-lg leading-tight">Advanced Bulk Scheduler</h2>
                        <p class="text-indigo-100 text-xs mt-1">Assign or clear specific shifts over a date range.</p>
                    </div>
                    <button @click="closeBulkModal" class="h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition shrink-0">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Employee <span class="text-red-500">*</span></label>
                        <select v-model="bulkAssignForm.user_id"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                            <option :value="null" disabled>Select employee</option>
                            <option v-for="emp in filteredEmployees" :key="emp.id" :value="emp.id">{{ emp.name }} ({{ emp.role }})</option>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Start Date <span class="text-red-500">*</span></label>
                            <input type="date" v-model="bulkAssignForm.start_date"
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">End Date <span class="text-red-500">*</span></label>
                            <input type="date" v-model="bulkAssignForm.end_date"
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Shift to Assign/Clear</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="st in shiftTypes" :key="st.name"
                                @click="bulkAssignForm.shift_type = st.name; bulkAssignForm.schedule_range = getShiftRange(st.name)"
                                :class="['py-3 rounded-xl text-xs font-black border-2 transition-all text-center',
                                    bulkAssignForm.shift_type === st.name
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 shadow-sm'
                                        : 'border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:border-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700']">
                                <div class="text-xl mb-1">{{ shiftEmoji(st.name) }}</div>
                                {{ st.name }}
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <button @click="submitBulkAction('clear')"
                            class="flex-1 py-2.5 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 rounded-xl font-extrabold text-sm transition">
                            Clear Shifts
                        </button>
                        <button @click="submitBulkAction('assign')"
                            class="flex-1 py-2.5 bg-gradient-to-r from-indigo-500 to-blue-600 hover:from-indigo-600 hover:to-blue-700 text-white rounded-xl font-extrabold text-sm shadow-lg transition">
                            Assign Shifts
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Transition>
        </Teleport>

        <Teleport to="body">
        <Transition name="modal">
        <div v-if="!isCeoMode && isShiftModalOpen"
            class="fixed inset-0 z-[55] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="closeShiftModal">
            <div class="modal-panel bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 flex items-start justify-between">
                    <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                    <div>
                        <p class="text-white/70 text-xs font-semibold">Assigning shift for</p>
                        <h2 class="text-white font-extrabold text-lg leading-tight">{{ shiftForm.effective_date }}</h2>
                    </div>
                    <button @click="closeShiftModal" class="h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition shrink-0">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Employee</label>
                        <select v-model="shiftForm.user_id"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                            <option :value="null" disabled>Select employee</option>
                            <option v-for="emp in filteredEmployees" :key="emp.id" :value="emp.id">{{ emp.name }} ({{ emp.role }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Shift Type</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="st in shiftTypes" :key="st.name"
                                @click="shiftForm.shift_type = st.name; shiftForm.schedule_range = getShiftRange(st.name)"
                                :class="['py-3 rounded-xl text-xs font-black border-2 transition-all text-center',
                                    shiftForm.shift_type === st.name
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 shadow-sm'
                                        : 'border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:border-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700']">
                                <div class="text-xl mb-1">{{ shiftEmoji(st.name) }}</div>
                                {{ st.name }}
                                <div class="text-[9px] font-semibold opacity-60 mt-0.5">{{ st.start }} – {{ st.end }}</div>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Custom Range (optional)</label>
                        <input type="text" v-model="shiftForm.schedule_range" placeholder="e.g., 08:00 – 17:00"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button @click="closeShiftModal" class="flex-1 py-2.5 text-slate-500 font-bold text-sm hover:text-slate-700 transition">Cancel</button>
                        <button @click="submitShift"
                            class="flex-1 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-xl font-extrabold text-sm shadow-lg transition">
                            Save Shift
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Transition>
        </Teleport>

        <Teleport to="body">
        <Transition name="modal">
        <div v-if="isHolidayModalOpen"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="closeHolidayModal">
            <div class="modal-panel bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 flex items-center justify-between">
                    <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                    <h2 class="text-white font-extrabold text-base">Add Holiday</h2>
                    <button @click="closeHolidayModal" class="h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition"><X class="h-4 w-4" /></button>
                </div>
                <div class="p-5 space-y-3.5">
                    <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Date</label><input type="date" v-model="holidayForm.holiday_date" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm outline-none focus:ring-2 focus:ring-rose-400" /></div>
                    <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Name</label><input type="text" v-model="holidayForm.holiday_name" placeholder="e.g., Independence Day" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm outline-none focus:ring-2 focus:ring-rose-400" /></div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Type</label>
                        <select v-model="holidayForm.holiday_type" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm outline-none focus:ring-2 focus:ring-rose-400">
                            <option value="regular">Regular Holiday (Non-working)</option>
                            <option value="special_non_working">Special Non-Working</option>
                            <option value="special_working">Special Working</option>
                        </select>
                    </div>
                    <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Premium Rate</label><input type="number" step="0.01" v-model="holidayForm.premium_rate" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm outline-none focus:ring-2 focus:ring-rose-400" /></div>
                    <div class="flex gap-3 pt-1">
                        <button @click="closeHolidayModal" class="flex-1 py-2.5 text-slate-500 font-bold text-sm">Cancel</button>
                        <button @click="submitHoliday" class="flex-1 py-2.5 bg-gradient-to-r from-rose-500 to-pink-600 text-white rounded-xl font-extrabold text-sm shadow-lg transition">Save Holiday</button>
                    </div>
                </div>
            </div>
        </div>
        </Transition>
        </Teleport>

        <Teleport to="body">
        <Transition name="modal">
        <div v-if="isManageHolidaysModalOpen"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="closeManageHolidays">
            <div class="modal-panel bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-xl w-full max-h-[80vh] flex flex-col overflow-hidden">
                <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 flex items-center justify-between shrink-0">
                    <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                    <h2 class="text-white font-extrabold text-base">Manage Holidays</h2>
                    <button @click="closeManageHolidays" class="h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition"><X class="h-4 w-4" /></button>
                </div>
                <div class="overflow-y-auto flex-1">
                    <div v-if="props.holidays.length === 0" class="py-14 text-center">
                        <CalendarDays class="h-10 w-10 mx-auto mb-2 text-slate-300" />
                        <p class="text-sm text-slate-400">No holidays defined yet.</p>
                    </div>
                    <div v-else class="divide-y divide-slate-100 dark:divide-slate-700">
                        <div v-for="hol in props.holidays" :key="hol.id"
                            class="flex items-center justify-between px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                            <div>
                                <p class="font-bold text-slate-800 dark:text-white text-sm">{{ hol.name }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    {{ String(hol.date).substring(0, 10) }} ·
                                    <span :class="hol.type === 'regular' ? 'text-red-500' : hol.type === 'special_non_working' ? 'text-orange-500' : 'text-emerald-500'" class="font-semibold">
                                        {{ hol.type.replace(/_/g, ' ') }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="editHoliday(hol)" class="h-8 w-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition"><Edit class="h-3.5 w-3.5" /></button>
                                <button @click="deleteHoliday(hol.id, hol.name)" class="h-8 w-8 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-500 flex items-center justify-center hover:bg-red-100 transition"><Trash2 class="h-3.5 w-3.5" /></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3.5 border-t border-slate-100 dark:border-slate-700 flex justify-end shrink-0">
                    <button @click="closeManageHolidays" class="px-5 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition">Close</button>
                </div>
            </div>
        </div>
        </Transition>
        </Teleport>

        <Teleport to="body">
        <Transition name="modal">
        <div v-if="editingHoliday"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="editingHoliday = null">
            <div class="modal-panel bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 flex items-center justify-between">
                    <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                    <h2 class="text-white font-extrabold text-base">Edit Holiday</h2>
                    <button @click="editingHoliday = null" class="h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition"><X class="h-4 w-4" /></button>
                </div>
                <div class="p-5 space-y-3.5">
                    <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Date</label><input type="date" v-model="holidayEditForm.holiday_date" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm outline-none" /></div>
                    <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Name</label><input type="text" v-model="holidayEditForm.holiday_name" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm outline-none" /></div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Type</label>
                        <select v-model="holidayEditForm.holiday_type" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm outline-none">
                            <option value="regular">Regular Holiday (Non-working)</option>
                            <option value="special_non_working">Special Non-Working</option>
                            <option value="special_working">Special Working</option>
                        </select>
                    </div>
                    <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Premium Rate</label><input type="number" step="0.01" v-model="holidayEditForm.premium_rate" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 border-none text-sm outline-none" /></div>
                    <div class="flex gap-3 pt-1">
                        <button @click="editingHoliday = null" class="flex-1 py-2.5 text-slate-500 font-bold text-sm">Cancel</button>
                        <button @click="updateHoliday" class="flex-1 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-xl font-extrabold text-sm shadow-lg transition">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
        </Transition>
        </Teleport>

        <Teleport to="body">
        <Transition name="modal">
        <div v-if="!isCeoMode && isShiftConfigOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="isShiftConfigOpen = false">
            <div class="modal-panel bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 flex items-center justify-between">
                    <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                    <h2 class="text-white font-extrabold text-base">Shift Configuration</h2>
                    <button @click="isShiftConfigOpen = false" class="h-8 w-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition"><X class="h-4 w-4" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <div v-for="(st, idx) in shiftTypes" :key="idx"
                        class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xl">{{ shiftEmoji(st.name) }}</span>
                            <span class="font-extrabold text-sm text-slate-800 dark:text-white">{{ st.name }} Shift</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="text-[10px] font-black text-slate-400 uppercase block mb-1">Start</label><input type="time" v-model="st.start" class="w-full px-3 py-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm outline-none" /></div>
                            <div><label class="text-[10px] font-black text-slate-400 uppercase block mb-1">End</label><input type="time" v-model="st.end" class="w-full px-3 py-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-sm outline-none" /></div>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button @click="isShiftConfigOpen = false" class="flex-1 py-2.5 text-slate-500 font-bold text-sm">Cancel</button>
                        <button @click="updateShiftTimes" class="flex-1 py-2.5 bg-slate-800 dark:bg-slate-600 hover:bg-slate-900 text-white rounded-xl font-extrabold text-sm transition">Save Configuration</button>
                    </div>
                </div>
            </div>
        </div>
        </Transition>
        </Teleport>

    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from .modal-panel { opacity: 0; transform: translateY(16px) scale(0.97); }
.modal-leave-to .modal-panel { opacity: 0; transform: translateY(8px) scale(0.98); }
::-webkit-scrollbar          { width: 5px; height: 5px; }
::-webkit-scrollbar-track    { background: transparent; }
::-webkit-scrollbar-thumb    { background: #cbd5e1; border-radius: 10px; }
.dark ::-webkit-scrollbar-thumb { background: #475569; }
</style>