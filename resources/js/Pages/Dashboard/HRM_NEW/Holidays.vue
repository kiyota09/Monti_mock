<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CalendarDays, ChevronLeft, ChevronRight, X, Trash2, Pencil, Plus } from 'lucide-vue-next';

const props = defineProps({
    holidays: { type: Array, default: () => [] },
    types: { type: Array, default: () => ['regular', 'special_non_working', 'non_working'] },
    policies: { type: Array, default: () => ['non_working', 'required_to_work'] },
});

const typeLabel = (t) => ({
    regular: 'Regular',
    special_non_working: 'Special Non-Working Day',
    non_working: 'Non-Working Day',
    special_working: 'Special Working Day',
}[t] || t);
const policyLabel = (p) => (p === 'required_to_work' ? 'Required to Work' : 'Non-Working Day');

const typeChip = (t) => ({
    regular: 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
    special_non_working: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    non_working: 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600',
    special_working: 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800',
}[t] || 'bg-slate-100 text-slate-600 border-slate-200');

// ---- Calendar ----
const currentDate = ref(new Date());
const currentYear = computed(() => currentDate.value.getFullYear());
const currentMonth = computed(() => currentDate.value.getMonth());
const monthName = computed(() => new Date(currentYear.value, currentMonth.value).toLocaleString('default', { month: 'long' }));

const calendarDays = computed(() => {
    const year = currentYear.value, month = currentMonth.value;
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const days = [];
    for (let i = 0; i < firstDay; i++) days.push({ day: null, date: null });
    for (let d = 1; d <= daysInMonth; d++) {
        days.push({ day: d, date: `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}` });
    }
    return days;
});

const holidaysByDate = computed(() => {
    const map = {};
    props.holidays.forEach((h) => { if (h.date) map[String(h.date).substring(0, 10)] = h; });
    return map;
});

const todayDate = computed(() => {
    const n = new Date();
    return `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-${String(n.getDate()).padStart(2, '0')}`;
});

const prevMonth = () => { currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1); };
const nextMonth = () => { currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1); };
const goToday = () => { currentDate.value = new Date(); };
const goBack = () => { router.get(route('hrm.workforce.attendance.index')); };

const upcoming = computed(() =>
    [...props.holidays]
        .filter((h) => String(h.date).substring(0, 10) >= todayDate.value)
        .sort((a, b) => String(a.date).localeCompare(String(b.date)))
        .slice(0, 8)
);

// ---- Add / Edit modal (date auto-filled from the clicked day) ----
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const form = ref({ holiday_date: '', holiday_name: '', holiday_type: 'regular', work_policy: 'non_working' });

const openForDate = (date) => {
    if (!date) return;
    const existing = holidaysByDate.value[date];
    if (existing) {
        editing.value = existing;
        form.value = {
            holiday_date: String(existing.date).substring(0, 10),
            holiday_name: existing.name,
            holiday_type: existing.type,
            work_policy: existing.policy || 'non_working',
        };
    } else {
        editing.value = null;
        form.value = { holiday_date: date, holiday_name: '', holiday_type: 'regular', work_policy: 'non_working' };
    }
    showModal.value = true;
};
const closeModal = () => { showModal.value = false; editing.value = null; };

// From Edit mode, jump straight into adding another holiday.
const switchToAdd = () => {
    editing.value = null;
    form.value = { holiday_date: todayDate.value, holiday_name: '', holiday_type: 'regular', work_policy: 'non_working' };
};

const submit = () => {
    if (!form.value.holiday_name.trim()) { alert('Please enter a holiday name.'); return; }
    if (!form.value.holiday_date) { alert('Please pick a date.'); return; }
    saving.value = true;
    const payload = { ...form.value };
    if (editing.value) {
        router.patch(route('hrm.workforce.holidays.update', editing.value.id), payload, {
            preserveScroll: true, onSuccess: () => closeModal(), onFinish: () => { saving.value = false; },
        });
    } else {
        router.post(route('hrm.workforce.holidays.store'), payload, {
            preserveScroll: true, onSuccess: () => closeModal(), onFinish: () => { saving.value = false; },
        });
    }
};

const removeHoliday = () => {
    if (!editing.value) return;
    if (!confirm(`Remove holiday "${editing.value.name}" on ${editing.value.date}?`)) return;
    router.delete(route('hrm.workforce.holidays.destroy', editing.value.id), {
        preserveScroll: true, onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Head title="Holiday Management" />
    <AuthenticatedLayout>
        <div class="p-6 space-y-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <button @click="goBack" title="Back to Attendance Management"
                        class="h-9 w-9 shrink-0 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-all shadow-sm">
                        <ChevronLeft class="h-5 w-5" />
                    </button>
                    <div>
                        <h1 class="text-xl font-black text-slate-900">Holiday Management</h1>
                        <p class="text-xs text-slate-500 mt-1">Click any date to add or edit its holiday. Date fills in automatically.</p>
                    </div>
                </div>
                <button @click="openForDate(todayDate)" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold shadow-lg">
                    <Plus class="w-4 h-4" /> Add Holiday
                </button>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
                <!-- Big calendar -->
                <div class="xl:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                        <button @click="prevMonth" class="h-9 w-9 flex items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-all">
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <div class="text-center">
                            <p class="text-lg font-extrabold text-slate-900 leading-tight">{{ monthName }}</p>
                            <p class="text-xs font-semibold text-slate-400">{{ currentYear }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="goToday" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-bold transition">Today</button>
                            <button @click="nextMonth" class="h-9 w-9 flex items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-all">
                                <ChevronRight class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50">
                        <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day"
                            class="py-2.5 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            {{ day }}
                        </div>
                    </div>

                    <div class="grid grid-cols-7 divide-x divide-y divide-slate-100">
                        <div v-for="(cell, idx) in calendarDays" :key="idx"
                            @click="openForDate(cell.date)"
                            :class="[
                                'relative min-h-[110px] p-2 transition-colors duration-150 group',
                                !cell.day ? 'bg-slate-50/60' : (cell.date === todayDate ? 'bg-blue-50/70' : 'bg-white'),
                                cell.date && holidaysByDate[cell.date] ? '!bg-rose-50/60' : '',
                                cell.date ? 'hover:bg-indigo-50/60 cursor-pointer' : '',
                            ]">
                            <div class="flex items-start justify-between mb-1">
                                <span :class="[
                                    'inline-flex items-center justify-center h-6 w-6 rounded-full text-xs font-extrabold transition-all',
                                    cell.date === todayDate ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500',
                                ]">{{ cell.day || '' }}</span>
                                <button v-if="cell.date && holidaysByDate[cell.date]"
                                    @click.stop="openForDate(cell.date)"
                                    class="opacity-0 group-hover:opacity-100 h-5 w-5 rounded-full bg-slate-600 hover:bg-slate-700 text-white flex items-center justify-center transition-all shadow-sm" title="Edit">
                                    <Pencil class="h-3 w-3" />
                                </button>
                                <button v-else-if="cell.date"
                                    @click.stop="openForDate(cell.date)"
                                    class="opacity-0 group-hover:opacity-100 h-5 w-5 rounded-full bg-blue-500 hover:bg-blue-600 text-white flex items-center justify-center text-[11px] font-black transition-all shadow-sm">
                                    +
                                </button>
                            </div>
                            <div v-if="cell.date && holidaysByDate[cell.date]"
                                :class="['rounded-md px-1.5 py-1 leading-snug border', typeChip(holidaysByDate[cell.date].type)]">
                                <p class="font-extrabold text-[10px] truncate">{{ holidaysByDate[cell.date].name }}</p>
                                <p class="text-[9px] opacity-80 font-bold truncate">{{ holidaysByDate[cell.date].type_label }}</p>
                            </div>
                            <div v-else-if="cell.date"
                                class="opacity-0 group-hover:opacity-100 transition-opacity absolute bottom-1.5 left-0 right-0 text-center pointer-events-none">
                                <span class="text-[9px] text-blue-400">+ tap to add</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Side panel -->
                <div class="space-y-4">
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4">
                        <h3 class="text-xs font-black uppercase text-slate-500 mb-3">Legend</h3>
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-rose-300"></span> Regular</div>
                            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-amber-300"></span> Special Non-Working Day</div>
                            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-slate-300"></span> Non-Working Day</div>
                            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-blue-600"></span> Today</div>
                        </div>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <CalendarDays class="h-4 w-4 text-blue-500" />
                            <h3 class="text-xs font-black uppercase text-slate-500">Upcoming Holidays</h3>
                        </div>
                        <div v-if="!upcoming.length" class="text-xs text-slate-400 py-4 text-center">No upcoming holidays.</div>
                        <div v-else class="space-y-2">
                            <button v-for="h in upcoming" :key="h.id" @click="openForDate(String(h.date).substring(0, 10))"
                                class="w-full text-left border border-slate-200 rounded-xl p-3 hover:bg-slate-50 transition">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-bold text-xs text-slate-900 truncate">{{ h.name }}</p>
                                    <span class="text-[10px] font-mono text-slate-400 shrink-0">{{ String(h.date).substring(0, 10) }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-1.5">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold border', typeChip(h.type)]">{{ h.type_label }}</span>
                                    <span class="text-[10px] text-slate-400 font-semibold">{{ h.policy_label }}</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add / Edit modal -->
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50" @click="closeModal"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-5 space-y-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-black">{{ editing ? 'Edit Holiday' : 'Add Holiday' }}</h3>
                            <p v-if="editing" class="text-xs text-slate-500 mt-0.5">{{ editing.name }}</p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <button v-if="editing" @click="switchToAdd" title="Add another holiday"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-[11px] font-bold transition">
                                <Plus class="w-3.5 h-3.5" /> New
                            </button>
                            <button @click="closeModal" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Holiday Name <span class="text-red-500">*</span></label>
                        <input v-model="form.holiday_name" placeholder="Christmas Day" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Date <span class="text-red-500">*</span></label>
                        <input v-if="!editing" v-model="form.holiday_date" type="date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" />
                        <p v-if="editing" class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-600 font-semibold">{{ form.holiday_date }}</p>
                        <p v-if="!editing" class="text-[11px] text-slate-400 mt-1">Auto-filled from the date you clicked.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Holiday Type</label>
                        <select v-model="form.holiday_type" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs">
                            <option v-for="t in types" :key="t" :value="t">{{ typeLabel(t) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Working Policy</label>
                        <select v-model="form.work_policy" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs">
                            <option v-for="p in policies" :key="p" :value="p">{{ policyLabel(p) }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button v-if="editing" @click="removeHoliday"
                            class="px-4 py-2.5 border border-rose-200 text-rose-600 hover:bg-rose-50 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5">
                            <Trash2 class="w-3.5 h-3.5" /> Delete
                        </button>
                        <button @click="submit" :disabled="saving"
                            class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold disabled:opacity-50">
                            {{ saving ? 'Saving...' : (editing ? 'Save Changes' : 'Add Holiday') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
