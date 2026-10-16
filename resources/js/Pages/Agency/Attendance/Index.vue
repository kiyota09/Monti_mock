<script setup lang="ts">
// Page 20: Agency attendance (read-only + confirm/dispute within 3 days)
import { ref, watch, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps<{
    records: Array<{
        id: number; date: string; worker_name: string | null; worker_code: string | null;
        request_code: string | null; department: string | null; status: string;
        hours: string | number | null; overtime_hours: string | number | null;
        approval: string; confirmation: string; confirmable: boolean; dispute_reason: string | null;
    }>;
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
    filters: { request_id?: string; from?: string; to?: string };
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true, deep: true });

const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

function applyFilters(pageNum = 1) {
    const params: any = { page: pageNum };
    if (from.value) params.from = from.value;
    if (to.value) params.to = to.value;
    router.get(route('agency.attendance.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

let t: any = null;
watch([from, to], () => {
    clearTimeout(t);
    t = setTimeout(() => applyFilters(1), 400);
});

const pageNumbers = computed(() => {
    const total = props.pagination.last_page;
    const current = props.pagination.current_page;
    const out: number[] = [];
    for (let i = Math.max(1, current - 2); i <= Math.min(total, current + 2); i++) out.push(i);
    return out;
});

function confirmRow(id: number) {
    router.post(route('agency.attendance.confirm', id), {}, { preserveScroll: true });
}

const disputeId = ref<number | null>(null);
const disputeReason = ref('');
const disputeError = ref('');
function openDispute(id: number) {
    disputeId.value = id;
    disputeReason.value = '';
    disputeError.value = '';
}
function submitDispute() {
    if (!disputeReason.value.trim()) {
        disputeError.value = 'A reason is required to dispute.';
        return;
    }
    router.post(route('agency.attendance.dispute', disputeId.value!), { reason: disputeReason.value }, {
        preserveScroll: true,
        onSuccess: () => { disputeId.value = null; },
        onError: (e: any) => toast.error(Object.values(e)[0] as string || 'Could not dispute.'),
    });
}
</script>

<template>
    <Head title="Attendance - Monti ERP" />
    <AgencyLayout>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">Attendance</h1>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5 mb-4">Recorded attendance for your workers. Confirm or dispute Approved records within 3 days.</p>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2 bg-slate-50/50 dark:bg-slate-800/50">
                <input v-model="from" type="date" title="From" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                <input v-model="to" type="date" title="To" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[760px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Worker</th>
                            <th class="py-3 px-4">Request</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-center">Hours</th>
                            <th class="py-3 px-4 text-center">Confirmation</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="r in records" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="py-3 px-4 text-xs font-bold whitespace-nowrap">{{ r.date }}</td>
                            <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ r.worker_name }} <span class="font-mono text-[10px] text-slate-400">({{ r.worker_code }})</span></td>
                            <td class="py-3 px-4 text-xs font-mono text-slate-500">{{ r.request_code }}</td>
                            <td class="py-3 px-4 text-xs">{{ r.status }} <span class="text-slate-400">({{ r.approval }})</span></td>
                            <td class="py-3 px-4 text-center text-xs">{{ r.hours ?? '—' }}</td>
                            <td class="py-3 px-4 text-center text-xs font-bold">{{ r.confirmation }}</td>
                            <td class="py-3 px-4 text-right">
                                <template v-if="r.confirmable">
                                    <button @click="confirmRow(r.id)" class="px-3 py-1.5 text-[11px] font-bold text-emerald-600 hover:bg-emerald-50 rounded-lg transition">Confirm</button>
                                    <button @click="openDispute(r.id)" class="px-3 py-1.5 text-[11px] font-bold text-rose-600 hover:bg-rose-50 rounded-lg transition">Dispute</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="records.length === 0" class="py-10 text-center text-sm font-bold">No attendance records yet.</div>
            </div>

            <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <p class="text-xs text-slate-500">Page {{ pagination.current_page }} of {{ pagination.last_page }}</p>
                <div class="flex items-center gap-1">
                    <button :disabled="pagination.current_page <= 1" @click="applyFilters(pagination.current_page - 1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border disabled:opacity-40">Prev</button>
                    <button v-for="p in pageNumbers" :key="p" @click="applyFilters(p)" :class="['px-3 py-1.5 text-xs font-bold rounded-lg border', p === pagination.current_page ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 dark:border-slate-700']">{{ p }}</button>
                    <button :disabled="pagination.current_page >= pagination.last_page" @click="applyFilters(pagination.current_page + 1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>

        <div v-if="disputeId !== null" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="disputeId = null">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md p-5 shadow-2xl">
                <h3 class="text-base font-black">Dispute attendance?</h3>
                <p class="text-xs text-slate-500 mt-0.5">It goes back to HR, who will adjust it or reject the dispute.</p>
                <textarea v-model="disputeReason" rows="2" placeholder="Reason (required)" class="mt-3 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl"></textarea>
                <p v-if="disputeError" class="mt-1 text-xs text-rose-600">{{ disputeError }}</p>
                <div class="mt-3 flex items-center justify-end gap-2">
                    <button @click="disputeId = null" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                    <button @click="submitDispute" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold">Send dispute</button>
                </div>
            </div>
        </div>
    </AgencyLayout>
</template>
