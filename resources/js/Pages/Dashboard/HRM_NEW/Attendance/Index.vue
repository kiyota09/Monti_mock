<script setup lang="ts">
// Page 11: Agency Attendance list
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Search, Plus, ClipboardList } from 'lucide-vue-next';

const props = defineProps<{
    records: Array<{
        id: number; date: string; worker_id: number; worker_code: string | null; worker_name: string | null;
        agency: string | null; request_id: number; request_code: string | null; department: string | null;
        time_in: string | null; time_out: string | null; hours: string | number | null;
        overtime_hours: string | number | null; status: string; approval: string;
        review_reason: string | null; confirmation: string; confirmable: boolean;
        dispute_reason: string | null; rate_snapshot: string | number | null;
        remarks: string | null; billed: boolean;
    }>;
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
    stats: { draft: number; approved: number; disputed: number };
    agencies: Array<{ id: number; code: string; name: string; status: string }>;
    assignable: Array<{ id: number; label: string; workers: Array<{ id: number; label: string }> }>;
    filters: any;
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.message) toast.info(flash.message);
}, { immediate: true, deep: true });

const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const agencyId = ref(props.filters?.agency_id || 'All');
const status = ref(props.filters?.status || 'All');
const approval = ref(props.filters?.approval || 'All');

function applyFilters(pageNum = 1) {
    const params: any = { page: pageNum };
    if (from.value) params.from = from.value;
    if (to.value) params.to = to.value;
    if (agencyId.value && agencyId.value !== 'All') params.agency_id = agencyId.value;
    if (status.value && status.value !== 'All') params.status = status.value;
    if (approval.value && approval.value !== 'All') params.approval = approval.value;
    router.get(route('hrm.attendance.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

let t: any = null;
watch([from, to, agencyId, status, approval], () => {
    clearTimeout(t);
    t = setTimeout(() => applyFilters(1), 400);
});

const pageNumbers = computed(() => {
    const total = props.pagination.last_page;
    const current = props.pagination.current_page;
    const start = Math.max(1, current - 2);
    const end = Math.min(total, start + 4);
    const out: number[] = [];
    for (let i = start; i <= end; i++) out.push(i);
    return out;
});

// --- Add-record modal ---
const showAdd = ref(false);
const addForm = useForm({
    request_id: '', worker_id: '', date: new Date().toISOString().slice(0, 10),
    status: 'Present', time_in: '', time_out: '', overtime_hours: 0, remarks: '',
});
const addWorkers = computed(() => props.assignable.find((r) => String(r.id) === String(addForm.request_id))?.workers || []);
function openAdd() {
    addForm.reset();
    addForm.date = new Date().toISOString().slice(0, 10);
    addForm.status = 'Present';
    showAdd.value = true;
}
function saveAdd(submit: boolean) {
    addForm.transform((d: any) => ({ ...d, submit }));
    addForm.post(route('hrm.attendance.store'), {
        preserveScroll: true,
        onSuccess: () => { showAdd.value = false; },
    });
}

function approvalBadge(a: string): string {
    const map: Record<string, string> = {
        Draft: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
        Approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
    };
    return map[a] || 'bg-slate-100 text-slate-600 border-slate-200';
}

const inputCls = 'w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200';
const labelCls = 'block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase';
</script>

<template>
    <Head title="Agency Attendance - Monti ERP" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Agency Attendance
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Daily records for deployed agency workers.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button @click="openAdd" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition shadow-lg shadow-blue-500/30 active:scale-95">
                    <Plus class="w-4 h-4" /> Add record
                </button>
                <Link :href="route('hrm.attendance.bulk')" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm font-bold rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                    <ClipboardList class="w-4 h-4" /> Bulk entry
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Draft</div>
                <div class="text-2xl font-black text-slate-500 mt-1">{{ stats.draft }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Approved</div>
                <div class="text-2xl font-black text-emerald-600 mt-1">{{ stats.approved }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Disputed</div>
                <div class="text-2xl font-black text-rose-600 mt-1">{{ stats.disputed }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="flex items-center gap-2">
                    <input v-model="from" type="date" title="From" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    <input v-model="to" type="date" title="To" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select v-model="agencyId" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="All">All agencies</option>
                        <option v-for="a in agencies" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <select v-model="status" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="All">All statuses</option>
                        <option>Present</option><option>Absent</option><option>Late</option>
                        <option>Half day</option><option>Leave</option>
                    </select>
                    <select v-model="approval" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="All">All approvals</option>
                        <option>Draft</option><option>Approved</option><option>Rejected</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[1020px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Worker</th>
                            <th class="py-3 px-4">Agency</th>
                            <th class="py-3 px-4">Request</th>
                            <th class="py-3 px-4">Time in</th>
                            <th class="py-3 px-4">Time out</th>
                            <th class="py-3 px-4 text-center">Hours</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Approval</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="r in records" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60 transition">
                            <td class="py-3 px-4 text-xs font-bold text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ r.date }}</td>
                            <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ r.worker_name }} <span class="font-mono text-[10px] text-slate-400">({{ r.worker_code }})</span></td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ r.agency || '—' }}</td>
                            <td class="py-3 px-4 text-xs font-mono text-slate-600 dark:text-slate-300">{{ r.request_code || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ r.time_in || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ r.time_out || '—' }}</td>
                            <td class="py-3 px-4 text-center text-xs font-bold text-slate-700 dark:text-slate-200">{{ r.hours ?? '—' }}</td>
                            <td class="py-3 px-4 text-center text-xs font-bold text-slate-700 dark:text-slate-200">{{ r.status }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border whitespace-nowrap', approvalBadge(r.approval)]">{{ r.approval }}</span>
                                <p v-if="r.confirmation && r.confirmation !== 'Pending'" class="mt-0.5 text-[9px] text-slate-400">{{ r.confirmation }}</p>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <Link :href="route('hrm.attendance.approval')" class="px-2 py-1 text-[11px] font-bold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg">
                                    {{ r.approval === 'Approved' ? 'Review' : 'Approve' }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="records.length === 0" class="py-12 text-center">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">No attendance records yet. Record the first day for a deployed worker.</p>
                </div>
            </div>

            <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <p class="text-xs text-slate-500 dark:text-slate-400">Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} records)</p>
                <div class="flex items-center gap-1">
                    <button :disabled="pagination.current_page <= 1" @click="applyFilters(pagination.current_page - 1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-700">Prev</button>
                    <button v-for="p in pageNumbers" :key="p" @click="applyFilters(p)" :class="['px-3 py-1.5 text-xs font-bold rounded-lg border', p === pagination.current_page ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700']">{{ p }}</button>
                    <button :disabled="pagination.current_page >= pagination.last_page" @click="applyFilters(pagination.current_page + 1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-700">Next</button>
                </div>
            </div>
        </div>

        <!-- Add-record modal -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showAdd = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="p-5 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-base font-black text-slate-900 dark:text-white">Add attendance record</h3>
                </div>
                <div class="p-5 space-y-3 max-h-[70vh] overflow-y-auto">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label :class="labelCls">Request</label>
                            <select v-model="addForm.request_id" :class="inputCls">
                                <option value="">Select request...</option>
                                <option v-for="r in assignable" :key="r.id" :value="r.id">{{ r.label }}</option>
                            </select>
                            <p v-if="addForm.errors.request_id" class="mt-1 text-xs text-rose-600">{{ addForm.errors.request_id }}</p>
                        </div>
                        <div class="col-span-2">
                            <label :class="labelCls">Worker</label>
                            <select v-model="addForm.worker_id" :class="inputCls">
                                <option value="">Select worker...</option>
                                <option v-for="w in addWorkers" :key="w.id" :value="w.id">{{ w.label }}</option>
                            </select>
                            <p v-if="addForm.errors.worker_id" class="mt-1 text-xs text-rose-600">{{ addForm.errors.worker_id }}</p>
                        </div>
                        <div>
                            <label :class="labelCls">Date</label>
                            <input v-model="addForm.date" type="date" :class="inputCls" />
                            <p v-if="addForm.errors.date" class="mt-1 text-xs text-rose-600">{{ addForm.errors.date }}</p>
                        </div>
                        <div>
                            <label :class="labelCls">Status</label>
                            <select v-model="addForm.status" :class="inputCls">
                                <option>Present</option><option>Absent</option><option>Late</option>
                                <option>Half day</option><option>Leave</option>
                            </select>
                        </div>
                        <div>
                            <label :class="labelCls">Time in</label>
                            <input v-model="addForm.time_in" type="time" :class="inputCls" />
                            <p v-if="addForm.errors.time_in" class="mt-1 text-xs text-rose-600">{{ addForm.errors.time_in }}</p>
                        </div>
                        <div>
                            <label :class="labelCls">Time out</label>
                            <input v-model="addForm.time_out" type="time" :class="inputCls" />
                            <p v-if="addForm.errors.time_out" class="mt-1 text-xs text-rose-600">{{ addForm.errors.time_out }}</p>
                        </div>
                        <div>
                            <label :class="labelCls">Overtime hours</label>
                            <input v-model="addForm.overtime_hours" type="number" min="0" max="24" step="0.25" :class="inputCls" />
                        </div>
                        <div class="col-span-2">
                            <label :class="labelCls">Remarks</label>
                            <input v-model="addForm.remarks" type="text" :class="inputCls" placeholder="Optional" />
                        </div>
                    </div>
                </div>
                <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex items-center justify-end gap-2">
                    <button @click="showAdd = false" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">Cancel</button>
                    <button @click="saveAdd(false)" :disabled="addForm.processing" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 disabled:opacity-60">Save as draft</button>
                    <button @click="saveAdd(true)" :disabled="addForm.processing" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold disabled:opacity-60">Submit for approval</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
