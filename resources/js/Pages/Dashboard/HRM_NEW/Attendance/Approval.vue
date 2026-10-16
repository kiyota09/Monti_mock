<script setup lang="ts">
// Page 13: Attendance approval queue (+ disputes tab)
import { ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

type Row = {
    id: number; date: string; worker_id: number; worker_code: string | null; worker_name: string | null;
    agency: string | null; request_id: number; request_code: string | null; department: string | null;
    time_in: string | null; time_out: string | null; hours: string | number | null;
    overtime_hours: string | number | null; status: string; approval: string;
    review_reason: string | null; confirmation: string; confirmable: boolean;
    dispute_reason: string | null; rate_snapshot: string | number | null; remarks: string | null; billed: boolean;
};

const props = defineProps<{
    groups: Array<{
        request_id: number; request_code: string | null; department: string | null;
        agency: string | null; date: string; rows: Row[];
    }>;
    disputes: Row[];
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.message) toast.info(flash.message);
}, { immediate: true, deep: true });

const tab = ref<'queue' | 'disputes'>('queue');

// Reject modal
const showReject = ref(false);
const rejectId = ref<number | null>(null);
const rejectReason = ref('');
const rejectError = ref('');
function openReject(id: number) {
    rejectId.value = id;
    rejectReason.value = '';
    rejectError.value = '';
    showReject.value = true;
}
function submitReject() {
    if (!rejectReason.value.trim()) {
        rejectError.value = 'A reason is required.';
        return;
    }
    router.post(route('hrm.attendance.reject', rejectId.value!), { reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => { showReject.value = false; },
    });
}

function approve(id: number) {
    router.post(route('hrm.attendance.approve', id), {}, { preserveScroll: true });
}

function approveAll(requestId: number, date: string | null) {
    const payload: any = { request_id: requestId };
    if (date) payload.date = date;
    router.post(route('hrm.attendance.approve-all'), payload, { preserveScroll: true });
}

// Adjustment modal (locked Approved rows)
const showAdjust = ref(false);
const adjustForm = useForm({
    id: 0, date: '', status: 'Present', time_in: '', time_out: '',
    overtime_hours: 0, remarks: '', reason: '',
});
function openAdjust(r: Row) {
    adjustForm.id = r.id;
    adjustForm.date = r.date;
    adjustForm.status = r.status;
    adjustForm.time_in = r.time_in || '';
    adjustForm.time_out = r.time_out || '';
    adjustForm.overtime_hours = Number(r.overtime_hours || 0);
    adjustForm.remarks = r.remarks || '';
    adjustForm.reason = '';
    adjustForm.clearErrors();
    showAdjust.value = true;
}
function submitAdjust() {
    adjustForm.transform((d: any) => ({ ...d }));
    adjustForm.post(route('hrm.attendance.adjust', adjustForm.id), {
        preserveScroll: true,
        onSuccess: () => { showAdjust.value = false; },
    });
}

// Reject-dispute modal
const showDisputeReject = ref(false);
const disputeId = ref<number | null>(null);
const disputeReason = ref('');
const disputeError = ref('');
function openDisputeReject(id: number) {
    disputeId.value = id;
    disputeReason.value = '';
    disputeError.value = '';
    showDisputeReject.value = true;
}
function submitDisputeReject() {
    if (!disputeReason.value.trim()) {
        disputeError.value = 'A reason is required.';
        return;
    }
    router.post(route('hrm.attendance.reject-dispute', disputeId.value!), { reason: disputeReason.value }, {
        preserveScroll: true,
        onSuccess: () => { showDisputeReject.value = false; },
    });
}

const inputCls = 'w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200';
const labelCls = 'block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase';
</script>

<template>
    <Head title="Attendance Approval - Monti ERP" />
    <AuthenticatedLayout>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('hrm.attendance.index')" class="hover:text-blue-600">Attendance</Link>
            <span>/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold">Approval</span>
        </nav>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight mb-4">
            Approval queue
        </h1>

        <div class="mb-4 flex items-center gap-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-1 w-fit">
            <button @click="tab = 'queue'" :class="['px-4 py-2 rounded-lg text-xs font-bold transition', tab === 'queue' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']">
                Draft queue
            </button>
            <button @click="tab = 'disputes'" :class="['px-4 py-2 rounded-lg text-xs font-bold transition', tab === 'disputes' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700']">
                Disputes ({{ disputes.length }})
            </button>
        </div>

        <div v-if="tab === 'queue'" class="space-y-4">
            <div v-if="groups.length === 0" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-10 text-center">
                <p class="text-sm font-bold text-slate-900 dark:text-white">No Draft records awaiting approval.</p>
            </div>
            <div v-for="g in groups" :key="g.request_id + g.date" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2 bg-slate-50/50 dark:bg-slate-800/50">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">
                        {{ g.request_code }} · {{ g.department }}
                        <span class="text-xs font-normal text-slate-500">— {{ g.date }}{{ g.agency ? ' · ' + g.agency : '' }} ({{ g.rows.length }})</span>
                    </p>
                    <button @click="approveAll(g.request_id, g.date)" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[11px] font-bold transition">
                        Approve all for this date
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[760px]">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-2 px-4">Worker</th>
                                <th class="py-2 px-4">Status</th>
                                <th class="py-2 px-4">In / Out</th>
                                <th class="py-2 px-4 text-center">Hours</th>
                                <th class="py-2 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr v-for="r in g.rows" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <td class="py-2 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ r.worker_name }} <span class="font-mono text-[10px] text-slate-400">({{ r.worker_code }})</span></td>
                                <td class="py-2 px-4 text-xs text-slate-600 dark:text-slate-300">{{ r.status }}</td>
                                <td class="py-2 px-4 text-xs text-slate-600 dark:text-slate-300">{{ r.time_in || '—' }} / {{ r.time_out || '—' }}</td>
                                <td class="py-2 px-4 text-center text-xs font-bold text-slate-700 dark:text-slate-200">{{ r.hours ?? '—' }}</td>
                                <td class="py-2 px-4">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="approve(r.id)" class="px-3 py-1.5 text-[11px] font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition">Approve</button>
                                        <button @click="openReject(r.id)" class="px-3 py-1.5 text-[11px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition">Reject</button>
                                        <button @click="openAdjust(r)" class="px-3 py-1.5 text-[11px] font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition">Adjust</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="tab === 'disputes'" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div v-if="disputes.length === 0" class="p-10 text-center text-sm font-bold text-slate-900 dark:text-white">No open disputes.</div>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[820px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Worker</th>
                            <th class="py-3 px-4">Request</th>
                            <th class="py-3 px-4">Dispute reason</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="d in disputes" :key="d.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="py-3 px-4 text-xs font-bold text-slate-700 dark:text-slate-200">{{ d.date }}</td>
                            <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ d.worker_name }} <span class="font-mono text-[10px] text-slate-400">({{ d.worker_code }})</span></td>
                            <td class="py-3 px-4 text-xs font-mono text-slate-600 dark:text-slate-300">{{ d.request_code }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ d.dispute_reason }}</td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="openAdjust(d)" class="px-3 py-1.5 text-[11px] font-bold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition">Adjust</button>
                                    <button @click="openDisputeReject(d.id)" class="px-3 py-1.5 text-[11px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition">Reject dispute</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Reject modal -->
        <div v-if="showReject" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showReject = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md p-5 shadow-2xl">
                <h3 class="text-base font-black text-slate-900 dark:text-white">Reject attendance?</h3>
                <textarea v-model="rejectReason" rows="2" placeholder="Reason (required)" class="mt-3 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
                <p v-if="rejectError" class="mt-1 text-xs text-rose-600">{{ rejectError }}</p>
                <div class="mt-3 flex items-center justify-end gap-2">
                    <button @click="showReject = false" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">Cancel</button>
                    <button @click="submitReject" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold">Confirm rejection</button>
                </div>
            </div>
        </div>

        <!-- Adjustment modal -->
        <div v-if="showAdjust" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showAdjust = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg p-5 shadow-2xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-base font-black text-slate-900 dark:text-white">Adjust locked record</h3>
                <p class="text-xs text-slate-500 mt-0.5">Corrections to Approved records require a reason and are written to the activity trail.</p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div>
                        <label :class="labelCls">Date</label>
                        <input v-model="adjustForm.date" type="date" :class="inputCls" />
                    </div>
                    <div>
                        <label :class="labelCls">Status</label>
                        <select v-model="adjustForm.status" :class="inputCls">
                            <option>Present</option><option>Absent</option><option>Late</option>
                            <option>Half day</option><option>Leave</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelCls">Time in</label>
                        <input v-model="adjustForm.time_in" type="time" :class="inputCls" />
                    </div>
                    <div>
                        <label :class="labelCls">Time out</label>
                        <input v-model="adjustForm.time_out" type="time" :class="inputCls" />
                    </div>
                    <div>
                        <label :class="labelCls">Overtime hours</label>
                        <input v-model="adjustForm.overtime_hours" type="number" min="0" max="24" step="0.25" :class="inputCls" />
                    </div>
                    <div class="col-span-2">
                        <label :class="labelCls">Remarks</label>
                        <input v-model="adjustForm.remarks" type="text" :class="inputCls" />
                    </div>
                    <div class="col-span-2">
                        <label :class="labelCls">Adjustment reason <span class="text-red-500">*</span></label>
                        <textarea v-model="adjustForm.reason" rows="2" :class="inputCls" placeholder="Why is this correction needed?"></textarea>
                        <p v-if="adjustForm.errors.reason" class="mt-1 text-xs text-rose-600">{{ adjustForm.errors.reason }}</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button @click="showAdjust = false" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">Cancel</button>
                    <button @click="submitAdjust" :disabled="adjustForm.processing" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold disabled:opacity-60">Apply adjustment</button>
                </div>
            </div>
        </div>

        <!-- Reject-dispute modal -->
        <div v-if="showDisputeReject" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showDisputeReject = false">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md p-5 shadow-2xl">
                <h3 class="text-base font-black text-slate-900 dark:text-white">Reject dispute?</h3>
                <p class="text-xs text-slate-500 mt-0.5">The record stands as Confirmed.</p>
                <textarea v-model="disputeReason" rows="2" placeholder="Reason (required)" class="mt-3 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
                <p v-if="disputeError" class="mt-1 text-xs text-rose-600">{{ disputeError }}</p>
                <div class="mt-3 flex items-center justify-end gap-2">
                    <button @click="showDisputeReject = false" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">Cancel</button>
                    <button @click="submitDisputeReject" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold">Confirm</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
