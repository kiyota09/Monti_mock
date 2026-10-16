<script setup lang="ts">
// Page 8: Manpower requests list (HRM > Requests)
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Search, Plus, Eye, Pencil } from 'lucide-vue-next';

const props = defineProps<{
    requests: Array<{
        id: number;
        code: string;
        department: string;
        position_skill: string;
        needed: number;
        filled: number;
        agency: string | null;
        agency_id: number;
        start_date: string | null;
        status: string;
        stored_status: string;
    }>;
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
    stats: { total: number; draft: number; open: number };
    agencies: Array<{ id: number; code: string; name: string; status: string }>;
    filters: { search?: string; agency_id?: string; status?: string; from?: string; to?: string };
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
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

function applyFilters(pageNum = 1) {
    const params: any = { page: pageNum };
    if (search.value) params.search = search.value;
    if (agencyId.value && agencyId.value !== 'All') params.agency_id = agencyId.value;
    if (status.value && status.value !== 'All') params.status = status.value;
    if (from.value) params.from = from.value;
    if (to.value) params.to = to.value;
    router.get(route('hrm.manpower-requests.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

let searchTimeout: any = null;
watch([search, agencyId, status, from, to], () => {
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

function openRequest(id: number) {
    router.visit(route('hrm.manpower-requests.show', id));
}

function statusBadge(s: string): string {
    const map: Record<string, string> = {
        Draft: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
        Open: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        'Partially filled': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        Filled: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
        Closed: 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-600',
    };
    return map[s] || 'bg-slate-100 text-slate-600 border-slate-200';
}
</script>

<template>
    <Head title="Manpower Requests - Monti ERP" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Manpower Requests
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Worker requests placed with manpower agencies.</p>
            </div>
            <Link
                :href="route('hrm.manpower-requests.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition shadow-lg shadow-blue-500/30 active:scale-95"
            >
                <Plus class="w-4 h-4" /> New request
            </Link>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total</div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ stats.total }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Draft</div>
                <div class="text-2xl font-black text-slate-500 mt-1">{{ stats.draft }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Open</div>
                <div class="text-2xl font-black text-blue-600 mt-1">{{ stats.open }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by request code..."
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
                        <option value="Draft">Draft</option>
                        <option value="Open">Open</option>
                        <option value="Partially filled">Partially filled</option>
                        <option value="Filled">Filled</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Closed">Closed</option>
                    </select>
                    <input v-model="from" type="date" title="Start date from" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    <input v-model="to" type="date" title="Start date to" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Department</th>
                            <th class="py-3 px-4">Position</th>
                            <th class="py-3 px-4 text-center">Needed</th>
                            <th class="py-3 px-4 text-center">Filled</th>
                            <th class="py-3 px-4">Agency</th>
                            <th class="py-3 px-4">Start date</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr
                            v-for="r in requests"
                            :key="r.id"
                            @click="openRequest(r.id)"
                            class="transition cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        >
                            <td class="py-3 px-4 text-xs font-mono font-bold text-slate-700 dark:text-slate-200">{{ r.code }}</td>
                            <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ r.department }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ r.position_skill }}</td>
                            <td class="py-3 px-4 text-center text-sm font-bold text-slate-700 dark:text-slate-200">{{ r.needed }}</td>
                            <td class="py-3 px-4 text-center text-sm font-bold text-slate-700 dark:text-slate-200">{{ r.filled }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ r.agency || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ r.start_date ? new Date(r.start_date + 'T00:00:00').toLocaleDateString() : '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border whitespace-nowrap', statusBadge(r.status)]">{{ r.status }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-1" @click.stop>
                                    <Link
                                        :href="route('hrm.manpower-requests.show', r.id)"
                                        class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg"
                                        title="View"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                    <Link
                                        :href="route('hrm.manpower-requests.edit', r.id)"
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
                <div v-if="requests.length === 0" class="py-12 text-center">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">No manpower requests yet.</p>
                    <Link
                        :href="route('hrm.manpower-requests.create')"
                        class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30"
                    >
                        <Plus class="w-4 h-4" /> New request
                    </Link>
                </div>
            </div>

            <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} requests)
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
