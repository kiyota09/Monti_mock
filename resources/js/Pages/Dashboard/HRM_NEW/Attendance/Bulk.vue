<script setup lang="ts">
// Page 12: Daily / bulk entry sheet (one request + one date)
import { ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps<{
    requests: Array<{ id: number; code: string; label: string; agency: string | null }>;
    request_id: number | null;
    request: {
        id: number; code: string; department: string; agency: string | null;
        agency_status: string | null; start_date: string | null; end_date: string | null;
    } | null;
    date: string;
    sheet: Array<{
        worker_id: number; worker_code: string | null; worker_name: string | null;
        position_skill: string | null; record_id: number | null; locked: boolean;
        status: string; time_in: string | null; time_out: string | null;
        overtime_hours: string | number | null; remarks: string | null;
    }>;
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.message) toast.info(flash.message);
}, { immediate: true, deep: true });

const pickedRequest = ref(props.request_id || '');
const pickedDate = ref(props.date);

function reload() {
    if (!pickedRequest.value) return;
    router.get(route('hrm.attendance.bulk'), { request_id: pickedRequest.value, date: pickedDate.value }, { preserveState: true, preserveScroll: true, replace: true });
}
watch([pickedRequest, pickedDate], () => reload());

const rows = ref(props.sheet.map((r) => ({ ...r })));
watch(() => props.sheet, (s) => { rows.value = s.map((r) => ({ ...r })); });

const form = useForm({ rows: [] as any[] });

function save(submit: boolean) {
    form.rows = rows.value.filter((r) => !r.locked).map((r) => ({
        worker_id: r.worker_id, status: r.status,
        time_in: r.time_in || null, time_out: r.time_out || null,
        overtime_hours: r.overtime_hours || 0, remarks: r.remarks || null,
    }));
    form.transform((d: any) => ({ request_id: props.request_id, date: props.date, rows: d.rows, submit }));
    form.post(route('hrm.attendance.bulk.store'), { preserveScroll: true });
}

const inputCls = 'w-full px-2 py-1.5 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200';
</script>

<template>
    <Head title="Bulk Attendance Entry - Monti ERP" />
    <AuthenticatedLayout>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('hrm.attendance.index')" class="hover:text-blue-600">Attendance</Link>
            <span>/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold">Bulk entry</span>
        </nav>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight mb-4">
            Daily / bulk entry
        </h1>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4 mb-4 flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Request</label>
                <select v-model="pickedRequest" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                    <option value="">Select request...</option>
                    <option v-for="r in requests" :key="r.id" :value="r.id">{{ r.label }} · {{ r.agency }}</option>
                </select>
            </div>
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Date</label>
                <input v-model="pickedDate" type="date" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
            </div>
        </div>

        <div v-if="request" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ request.code }} · {{ request.department }}
                    <span class="text-xs font-normal text-slate-500">— {{ date }}{{ request.agency ? ' · ' + request.agency : '' }}</span>
                </p>
                <p v-if="request.start_date || request.end_date" class="text-[11px] text-slate-400">
                    Request window: {{ request.start_date || '…' }} → {{ request.end_date || '…' }}
                </p>
            </div>

            <div v-if="rows.length === 0" class="py-10 text-center text-sm font-bold text-slate-900 dark:text-white">
                No actively assigned workers on this request.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[860px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Worker</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Time in</th>
                            <th class="py-3 px-4">Time out</th>
                            <th class="py-3 px-4">OT hrs</th>
                            <th class="py-3 px-4">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="r in rows" :key="r.worker_id" :class="[r.locked ? 'bg-slate-50 dark:bg-slate-900/50' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60']">
                            <td class="py-2 px-4 text-sm font-bold text-slate-900 dark:text-white">
                                {{ r.worker_name }} <span class="font-mono text-[10px] text-slate-400">({{ r.worker_code }})</span>
                                <span v-if="r.locked" class="ml-1 text-[9px] font-black uppercase text-emerald-600">locked</span>
                            </td>
                            <td class="py-2 px-4">
                                <select v-model="r.status" :disabled="r.locked" :class="inputCls">
                                    <option>Present</option><option>Absent</option><option>Late</option>
                                    <option>Half day</option><option>Leave</option>
                                </select>
                            </td>
                            <td class="py-2 px-4"><input v-model="r.time_in" type="time" :disabled="r.locked" :class="inputCls" /></td>
                            <td class="py-2 px-4"><input v-model="r.time_out" type="time" :disabled="r.locked" :class="inputCls" /></td>
                            <td class="py-2 px-4"><input v-model="r.overtime_hours" type="number" min="0" max="24" step="0.25" :disabled="r.locked" :class="inputCls" /></td>
                            <td class="py-2 px-4"><input v-model="r.remarks" type="text" :disabled="r.locked" :class="inputCls" placeholder="Optional" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="rows.length > 0" class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex items-center justify-end gap-2">
                <button @click="save(false)" :disabled="form.processing" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 disabled:opacity-60">Save as draft</button>
                <button @click="save(true)" :disabled="form.processing" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold disabled:opacity-60">Submit for approval</button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
