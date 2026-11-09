<script setup lang="ts">
// Worker Attendance — view only (search + department filter + date).
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { FileSpreadsheet, FileText } from 'lucide-vue-next';
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

function csvCell(value: unknown): string {
    let text = String(value ?? '');
    if (/^[=+\-@]/.test(text)) text = `'${text}`;
    return `"${text.replace(/"/g, '""')}"`;
}

function reportFilename(extension: string): string {
    const department = selectedDepartment.value || 'all-departments';
    const safeDepartment = department.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    return `worker-attendance-${safeDepartment}-${pickedDate.value}.${extension}`;
}

function downloadCsv() {
    const headers = ['Worker', 'Department', 'Job position', 'Shift', 'Time in', 'Time out', 'Late', 'Overtime hours', 'Status'];
    const records = filteredRows.value.map((row) => [
        row.worker_name, row.department, row.position_skill, row.shift,
        fmtTime(row.time_in), fmtTime(row.time_out), lateOf(row.time_in, row.shift),
        row.overtime_hours, row.status,
    ]);
    const csv = [headers, ...records].map((record) => record.map(csvCell).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob(['\uFEFF', csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = reportFilename('csv');
    link.click();
    URL.revokeObjectURL(url);
}

function escapeHtml(value: unknown): string {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function downloadPdf() {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;
    printWindow.opener = null;
    const headers = ['Worker', 'Department', 'Job position', 'Shift', 'Time in', 'Time out', 'Late', 'OT hours', 'Status'];
    const bodyRows = filteredRows.value.map((row) => [
        row.worker_name, row.department, row.position_skill, row.shift,
        fmtTime(row.time_in), fmtTime(row.time_out), lateOf(row.time_in, row.shift),
        row.overtime_hours, row.status,
    ]).map((record) => `<tr>${record.map((value) => `<td>${escapeHtml(value)}</td>`).join('')}</tr>`).join('');
    printWindow.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(reportFilename('pdf'))}</title><style>
        body{font:12px Arial,sans-serif;color:#172033;margin:32px}h1{font-size:20px;margin:0 0 6px}p{color:#526078;margin:0 0 18px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:7px;text-align:left;vertical-align:top}th{background:#e2e8f0;font-size:10px;text-transform:uppercase}@media print{body{margin:14mm}tr{break-inside:avoid}}
        </style></head><body><h1>Worker Attendance</h1><p>${escapeHtml(selectedDepartment.value || 'All departments')} · ${escapeHtml(pickedDate.value)} · ${filteredRows.value.length} worker(s)</p><table><thead><tr>${headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('')}</tr></thead><tbody>${bodyRows || `<tr><td colspan="9">No workers found.</td></tr>`}</tbody></table><script>window.onload=()=>window.print();<\/script></body></html>`);
    printWindow.document.close();
}

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
        <header class="agency-page-header">
            <div>
                <p class="agency-page-eyebrow">Agency Portal</p>
                <h1>Worker Attendance</h1>
            </div>
        </header>

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
            <div class="flex items-end gap-2">
                <button type="button" @click="downloadCsv" aria-label="Download CSV" title="Download CSV" class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white transition hover:bg-emerald-700">
                    <FileSpreadsheet class="h-5 w-5" aria-hidden="true" />
                </button>
                <button type="button" @click="downloadPdf" aria-label="Download PDF" title="Download PDF" class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-600 text-white transition hover:bg-rose-700 dark:bg-rose-700 dark:hover:bg-rose-800">
                    <FileText class="h-5 w-5" aria-hidden="true" />
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ department || 'All departments' }}
                    <span class="text-xs font-normal text-slate-500">— {{ date }} · {{ filteredRows.length }} of {{ rows.length }} worker{{ rows.length === 1 ? '' : 's' }}</span>
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[1100px]">
                    <thead>
                        <tr class="bg-slate-800 text-[10px] font-bold uppercase tracking-wider text-white/90">
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
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="9" class="py-10 text-center text-sm font-bold text-slate-500 dark:text-slate-400">{{ search ? 'No workers match your search.' : 'No workers found.' }}</td>
                        </tr>
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
