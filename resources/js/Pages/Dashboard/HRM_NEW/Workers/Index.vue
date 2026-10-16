<script setup lang="ts">
// Page 5: Agency Workers list (HRM > Workers)
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Search, Eye, Pencil } from 'lucide-vue-next';

const props = defineProps<{
    workers: Array<{
        id: number;
        code: string;
        name: string;
        agency: string | null;
        agency_id: number;
        position_skill: string | null;
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

const queueTab = ref<'pending' | 'rates'>('pending');
const rejectId = ref<number | null>(null);
const rejectReason = ref('');
const rejectError = ref('');

function approvePending(id: number) {
    router.post(route('hrm.workers.approve', id), {}, { preserveScroll: true });
}
function openRejectPending(id: number) {
    rejectId.value = id;
    rejectReason.value = '';
    rejectError.value = '';
}
function submitRejectPending() {
    if (!rejectReason.value.trim()) {
        rejectError.value = 'A reason is required.';
        return;
    }
    router.post(route('hrm.workers.reject-pending', rejectId.value!), { reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => { rejectId.value = null; },
    });
}
function approveRate(id: number) {
    router.post(route('hrm.workers.rates.approve', id), {}, { preserveScroll: true });
}
function rejectRate(id: number) {
    const reason = window.prompt('Reason for rejecting this rate change (required):');
    if (reason === null) return;
    if (!reason.trim()) {
        toast.error('A reason is required.');
        return;
    }
    router.post(route('hrm.workers.rates.reject', id), { reason }, { preserveScroll: true });
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

        <!-- HR queues: pending workers + rate changes -->
        <div v-if="pending_workers.length > 0 || rate_requests.length > 0" class="bg-white dark:bg-slate-800 rounded-2xl border border-amber-200 dark:border-amber-800 shadow-sm overflow-hidden mb-4">
            <div class="px-4 pt-3 flex items-center gap-1">
                <button @click="queueTab = 'pending'" :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', queueTab === 'pending' ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700']">
                    Pending approval ({{ pending_workers.length }})
                </button>
                <button @click="queueTab = 'rates'" :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition', queueTab === 'rates' ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700']">
                    Rate changes ({{ rate_requests.length }})
                </button>
            </div>
            <div v-if="queueTab === 'pending'" class="p-4">
                <div v-if="pending_workers.length === 0" class="text-xs text-slate-400">No workers awaiting approval.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="w in pending_workers" :key="w.id" class="py-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span><strong class="text-slate-900 dark:text-white">{{ w.name }}</strong> · {{ w.agency }} · {{ w.created_at }}</span>
                        <span class="flex items-center gap-1">
                            <button @click="approvePending(w.id)" class="px-3 py-1.5 text-[11px] font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition">Approve</button>
                            <button @click="openRejectPending(w.id)" class="px-3 py-1.5 text-[11px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition">Reject</button>
                        </span>
                    </li>
                </ul>
            </div>
            <div v-if="queueTab === 'rates'" class="p-4">
                <div v-if="rate_requests.length === 0" class="text-xs text-slate-400">No pending rate changes.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="r in rate_requests" :key="r.id" class="py-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span><strong class="text-slate-900 dark:text-white">{{ r.worker_name }}</strong> · {{ r.agency }} · {{ r.old_rate }} → <strong>{{ r.new_rate }}</strong> · by {{ r.requested_by }}</span>
                        <span class="flex items-center gap-1">
                            <button @click="approveRate(r.id)" class="px-3 py-1.5 text-[11px] font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition">Approve</button>
                            <button @click="rejectRate(r.id)" class="px-3 py-1.5 text-[11px] font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition">Reject</button>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="rejectId !== null" class="mb-4 p-4 rounded-xl bg-white dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-600">
            <p class="text-sm font-black text-slate-900 dark:text-white">Reject this worker?</p>
            <textarea v-model="rejectReason" rows="2" placeholder="Reason (required)" class="mt-2 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
            <p v-if="rejectError" class="mt-1 text-xs text-rose-600">{{ rejectError }}</p>
            <div class="mt-2 flex items-center justify-end gap-2">
                <button @click="rejectId = null" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">Cancel</button>
                <button @click="submitRejectPending" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold">Confirm rejection</button>
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
                        <option v-for="a in agencies" :key="a.id" :value="a.id">{{ a.name }} ({{ a.code }})</option>
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
                            <th class="py-3 px-4">Current assignment</th>
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
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ w.current_assignment || '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', statusBadge(w.status)]">{{ w.status }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-1" @click.stop>
                                    <Link
                                        :href="route('hrm.workers.show', w.id)"
                                        class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg"
                                        title="View"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                    <Link
                                        :href="route('hrm.workers.edit', w.id)"
                                        class="p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg"
                                        title="Edit"
                                    >
                                        <Pencil class="w-4 h-4" />
                                    </Link>
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
    </AuthenticatedLayout>
</template>
