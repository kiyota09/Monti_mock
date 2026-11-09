<script setup lang="ts">
// Page 5: Agency Workers list (HRM > Workers)
import { ref, watch, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from '@/lib/feedback';
import { Search, Flag, X } from 'lucide-vue-next';

const props = defineProps<{
    workers: Array<{
        id: number;
        code: string;
        name: string;
        agency: string | null;
        agency_id: number;
        position_skill: string | null;
        department: string | null;
        current_assignment: string | null;
        status: string;
        clearance_expiry: string | null;
        clearance_expired: boolean;
        expiring_soon: boolean;
    }>;
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
    stats: { total: number; available: number; deployed: number; inactive: number; blacklisted: number; pending: number };
    pending_workers: Array<{ id: number; code: string; name: string; agency: string | null; position_skill: string | null; daily_rate: string | number | null; created_at: string }>;
    rate_requests: Array<{ id: number; worker_id: number; worker_code: string | null; worker_name: string | null; agency: string | null; old_rate: string | number | null; new_rate: string | number; requested_by: string | null; created_at: string }>;
    agencies: Array<{ id: number; code: string; name: string; status: string }>;
    filters: { search?: string; agency_id?: string; status?: string; expiring?: boolean };
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.message) toast.info(flash.message);
}, { immediate: true, deep: true });

const search = ref(props.filters?.search || '');
const agencyId = ref(props.filters?.agency_id || 'All');
const status = ref(props.filters?.status || 'All');
const expiring = ref(!!props.filters?.expiring);
const reportTarget = ref<{ id: number; agency_id: number; name: string; agency: string | null } | null>(null);
const reportForm = useForm<{ reason: string; attachment: File | null }>({ reason: '', attachment: null });

function openReport(worker: { id: number; agency_id: number; name: string; agency: string | null }) {
    reportTarget.value = worker;
    reportForm.reset();
    reportForm.clearErrors();
}

function closeReport() {
    if (reportForm.processing) return;
    reportTarget.value = null;
    reportForm.reset();
    reportForm.clearErrors();
}

function submitReport() {
    if (!reportTarget.value || !reportForm.reason.trim()) return;

    reportForm.post(route('hrm.agencies.workers.report', {
        agency: reportTarget.value.agency_id,
        worker: reportTarget.value.id,
    }), {
        preserveScroll: true,
        onSuccess: closeReport,
    });
}

function selectReportAttachment(event: Event) {
    const input = event.target as HTMLInputElement;
    reportForm.attachment = input.files?.[0] || null;
}

function applyFilters(pageNum = 1) {
    const params: any = { page: pageNum };
    if (search.value) params.search = search.value;
    if (agencyId.value && agencyId.value !== 'All') params.agency_id = agencyId.value;
    if (status.value && status.value !== 'All') params.status = status.value;
    if (expiring.value) params.expiring = 1;
    router.get(route('hrm.workers.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

let searchTimeout: any = null;
watch([search, agencyId, status, expiring], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(1), 400);
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

function openWorker(id: number) {
    router.visit(route('hrm.workers.show', id));
}

function statusBadge(s: string): string {
    const map: Record<string, string> = {
        Available: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Deployed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        Inactive: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
        Blacklisted: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
        Pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        'For Correction': 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 border-orange-200 dark:border-orange-800',
    };
    return map[s] || 'bg-slate-100 text-slate-600 border-slate-200';
}
</script>

<template>
    <Head title="Agency Workers - Monti ERP" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Agency Workers
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Workers supplied by manpower agencies.</p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total</div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ stats.total }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Available</div>
                <div class="text-2xl font-black text-emerald-600 mt-1">{{ stats.available }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Deployed</div>
                <div class="text-2xl font-black text-blue-600 mt-1">{{ stats.deployed }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Inactive</div>
                <div class="text-2xl font-black text-slate-500 mt-1">{{ stats.inactive }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Blacklisted</div>
                <div class="text-2xl font-black text-rose-600 mt-1">{{ stats.blacklisted }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pending approval</div>
                <div class="text-2xl font-black text-amber-600 mt-1">{{ stats.pending }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search name..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select v-model="agencyId" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="All">All agencies</option>
                        <option v-for="a in agencies" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <select v-model="status" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="All">All statuses</option>
                        <option value="Available">Available</option>
                        <option value="Deployed">Deployed</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Blacklisted">Blacklisted</option>
                        <option value="Pending">Pending approval</option>
                        <option value="For Correction">For correction</option>
                    </select>
                    <label class="inline-flex items-center gap-2 px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 cursor-pointer">
                        <input v-model="expiring" type="checkbox" class="w-4 h-4 accent-blue-600" />
                        Expiring within 30 days
                    </label>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[820px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Name</th>
                            <th class="py-3 px-4">Agency</th>
                            <th class="py-3 px-4">Position / skill</th>
                            <th class="py-3 px-4">Department</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr
                            v-for="w in workers"
                            :key="w.id"
                            @click="openWorker(w.id)"
                            :class="[
                                'transition cursor-pointer',
                                w.expiring_soon || w.clearance_expired
                                    ? 'bg-yellow-50 hover:bg-yellow-100/70 dark:bg-yellow-900/10 dark:hover:bg-yellow-900/20'
                                    : 'hover:bg-slate-50 dark:hover:bg-slate-800/60',
                            ]"
                        >
                            <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ w.name }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ w.agency || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ w.position_skill || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ w.department || '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', statusBadge(w.status)]">{{ w.status }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-1" @click.stop>
                                    <button
                                        type="button"
                                        @click="openReport(w)"
                                        class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1.5 text-[10px] font-bold text-rose-700 transition hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-900/20"
                                        title="Report worker"
                                    >
                                        <Flag class="h-3.5 w-3.5" />
                                        <span class="hidden xl:inline">Report</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="workers.length === 0" class="py-12 text-center">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">No workers yet. Workers appear here once an agency submits them.</p>
                </div>
            </div>

            <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} workers)
                </p>
                <div class="flex items-center gap-1">
                    <button
                        :disabled="pagination.current_page <= 1"
                        @click="applyFilters(pagination.current_page - 1)"
                        class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-700"
                    >
                        Prev
                    </button>
                    <button
                        v-for="p in pageNumbers"
                        :key="p"
                        @click="applyFilters(p)"
                        :class="[
                            'px-3 py-1.5 text-xs font-bold rounded-lg border',
                            p === pagination.current_page
                                ? 'bg-blue-600 text-white border-blue-600'
                                : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700',
                        ]"
                    >
                        {{ p }}
                    </button>
                    <button
                        :disabled="pagination.current_page >= pagination.last_page"
                        @click="applyFilters(pagination.current_page + 1)"
                        class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-700"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <div v-if="reportTarget" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="presentation" @click.self="closeReport">
            <form @submit.prevent="submitReport" class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="worker-report-title">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="worker-report-title" class="text-lg font-black text-slate-900 dark:text-white">Report worker</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ reportTarget.name }} <span v-if="reportTarget.agency">· {{ reportTarget.agency }}</span></p>
                    </div>
                    <button type="button" @click="closeReport" :disabled="reportForm.processing" aria-label="Close report dialog" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 disabled:opacity-50 dark:hover:bg-slate-800"><X class="h-4 w-4" /></button>
                </div>
                <label class="mt-5 block text-xs font-bold text-slate-700 dark:text-slate-200">Reason for report <span class="text-rose-600">*</span>
                    <textarea v-model="reportForm.reason" required minlength="3" maxlength="5000" rows="5" placeholder="Describe the reason for reporting this worker..." class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white p-3 text-sm font-normal text-slate-800 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" />
                </label>
                <p v-if="reportForm.errors.reason" class="mt-1 text-xs text-rose-600">{{ reportForm.errors.reason }}</p>
                <label class="mt-4 block text-xs font-bold text-slate-700 dark:text-slate-200">Supporting document or image <span class="font-normal text-slate-400">(optional)</span>
                    <input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" @change="selectReportAttachment" class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white p-2 text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-bold dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:file:bg-slate-700" />
                </label>
                <p v-if="reportForm.errors.attachment" class="mt-1 text-xs text-rose-600">{{ reportForm.errors.attachment }}</p>
                <p v-if="reportForm.attachment" class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Selected: {{ reportForm.attachment.name }}</p>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">The agency will be notified about this worker and the report reason.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="closeReport" :disabled="reportForm.processing" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
                    <button type="submit" :disabled="reportForm.processing || !reportForm.reason.trim()" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50">{{ reportForm.processing ? 'Sending report...' : 'Submit report' }}</button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
