<script setup lang="ts">
// Worker Attendance — view only (search + department filter + date).
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';

const props = defineProps<{
    departments: Array<string>;
    department: string;
    date: string;
    sheet: Array<{
        worker_id: number; worker_name: string | null; department: string | null;
        position_skill: string | null; shift: string | null; request_id: number | null; record_id: number | null;
        status: string; time_in: string | null; time_out: string | null;
        overtime_hours: string | number | null; remarks: string | null;
    }>;
}>();

const selectedDepartment = ref(props.department || '');
const pickedDate = ref(props.date);
const search = ref('');

function reload() {
    router.get(route('agency.attendance.index'), { department: selectedDepartment.value || undefined, date: pickedDate.value }, { preserveState: true, preserveScroll: true, replace: true });
}
watch([selectedDepartment, pickedDate], () => reload());

const rows = computed(() => props.sheet);

// Search starts from the department filter bar — narrows displayed workers.
const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return rows.value;
    return rows.value.filter((r) => ((r.worker_name || '') + ' ' + (r.position_skill || '')).toLowerCase().includes(q));
});

const fmtTime = (t: string | null) => {
    if (!t || t === '') return '—';
    // Accepts "HH:MM" or "h:i A" (employee clock format) → "HH:MM".
    const m12 = String(t).match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
    if (m12) {
        let h = Number(m12[1]);
        const mm = m12[2];
        const ap = m12[3].toUpperCase();
        if (ap === 'PM' && h < 12) h += 12;
        if (ap === 'AM' && h === 12) h = 0;
        return `${String(h).padStart(2, '0')}:${mm}`;
    }
    return String(t).slice(0, 5);
};

// Late time vs shift start (Morning 8AM / Afternoon 2PM / Graveyard-Night 10PM).
function shiftStartMinutes(shift: string | null): number {
    const s = (shift || '').toLowerCase();
    if (/after/.test(s)) return 14 * 60;
    if (/grave|night/.test(s)) return 22 * 60;
    return 8 * 60;
}
function lateOf(timeIn: string | null, shift: string | null): string {
    if (!timeIn) return '—';
    const m = String(timeIn).match(/(\d{1,2}):(\d{2})/);
    if (!m) return '—';
    const mins = Number(m[1]) * 60 + Number(m[2]);
    const diff = mins - shiftStartMinutes(shift);
    if (diff <= 0) return '—';
    const h = Math.floor(diff / 60);
    const mm = diff % 60;
    return h > 0 ? `${h}h ${mm}m` : `${mm}m`;
}
</script>

<template>
    <Head title="Worker Attendance - Monti ERP" />
    <AgencyLayout>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <span class="text-slate-800 dark:text-slate-200 font-semibold">Worker Attendance</span>
        </nav>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight mb-4">
            Worker Attendance
        </h1>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4 mb-4 flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Search worker</label>
                <input v-model="search" type="text" placeholder="Search name or position..." class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400" />
            </div>
            <div class="flex-1">
                <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Department (filter)</label>
                <select v-model="selectedDepartment" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                    <option value="">All departments</option>
                    <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                </select>
            </div>
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Date</label>
                <input v-model="pickedDate" type="date" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ department || 'All departments' }}
                    <span class="text-xs font-normal text-slate-500">— {{ date }} · {{ filteredRows.length }} of {{ rows.length }} worker{{ rows.length === 1 ? '' : 's' }}</span>
                </p>
            </div>

            <div v-if="filteredRows.length === 0" class="py-10 text-center text-sm font-bold text-slate-900 dark:text-white">
                {{ search ? 'No workers match your search.' : 'No workers found.' }}
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[1100px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Worker</th>
                            <th class="py-3 px-4">Department</th>
                            <th class="py-3 px-4">Job Position</th>
                            <th class="py-3 px-4">Shift</th>
                            <th class="py-3 px-4">Time in</th>
                            <th class="py-3 px-4">Time out</th>
                            <th class="py-3 px-4">Late</th>
                            <th class="py-3 px-4">OT hrs</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="r in filteredRows" :key="r.worker_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="py-2 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ r.worker_name }}</td>
                            <td class="py-2 px-4 text-xs text-slate-500 dark:text-slate-400">{{ r.department || '—' }}</td>
                            <td class="py-2 px-4 text-xs text-slate-500 dark:text-slate-400">{{ r.position_skill || '—' }}</td>
                            <td class="py-2 px-4 text-xs text-slate-500 dark:text-slate-400">{{ r.shift || '—' }}</td>
                            <td class="py-2 px-4 text-xs text-slate-700 dark:text-slate-200">{{ fmtTime(r.time_in) }}</td>
                            <td class="py-2 px-4 text-xs text-slate-700 dark:text-slate-200">{{ fmtTime(r.time_out) }}</td>
                            <td class="py-2 px-4 text-xs font-bold text-slate-700 dark:text-slate-200">{{ lateOf(r.time_in, r.shift) }}</td>
                            <td class="py-2 px-4 text-xs text-slate-700 dark:text-slate-200">{{ r.overtime_hours ?? '—' }}</td>
                            <td class="py-2 px-4 text-xs text-slate-700 dark:text-slate-200">{{ r.status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AgencyLayout>
</template>
