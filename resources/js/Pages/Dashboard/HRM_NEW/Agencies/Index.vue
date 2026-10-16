<script setup lang="ts">
// Page 1: Agencies list (HRM > Agencies)
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Search, Plus, Eye, Pencil } from 'lucide-vue-next';

const props = defineProps<{
    agencies: Array<{
        id: number;
        code: string;
        name: string;
        contact_name: string | null;
        users_count: number;
        workers_count: number;
        contract_end: string | null;
        status: string;
        stored_status: string;
        expiring_soon: boolean;
    }>;
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
    stats: { total: number; active: number; suspended: number; terminated: number; expiring: number };
    filters: { search?: string; status?: string; expiring?: boolean };
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.message) toast.info(flash.message);
}, { immediate: true, deep: true });

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'All');
const expiring = ref(!!props.filters?.expiring);

function applyFilters(pageNum = 1) {
    const params: any = { page: pageNum };
    if (search.value) params.search = search.value;
    if (status.value && status.value !== 'All') params.status = status.value;
    if (expiring.value) params.expiring = 1;
    router.get(route('hrm.agencies.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

let searchTimeout: any = null;
watch([search, status, expiring], () => {
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

function statusBadge(s: string): string {    const map: Record<string, string> = {
        Active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Pending: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        Suspended: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        Terminated: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
    };
    return map[s] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700';
}

// Clicking anywhere on a row opens the agency detail (view).
function openAgency(id: number) {
    router.visit(route('hrm.agencies.show', id));
}
</script>

<template>
    <Head title="Agencies - Monti ERP" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Agencies
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Manpower agencies Monti Corp works with.</p>
            </div>
            <Link
                :href="route('hrm.agencies.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition shadow-lg shadow-blue-500/30 active:scale-95"
            >
                <Plus class="w-4 h-4" /> Add agency
            </Link>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total</div>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ stats.total }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Active</div>
                <div class="text-2xl font-black text-emerald-600 mt-1">{{ stats.active }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Suspended</div>
                <div class="text-2xl font-black text-amber-600 mt-1">{{ stats.suspended }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Terminated</div>
                <div class="text-2xl font-black text-rose-600 mt-1">{{ stats.terminated }}</div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-4">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Expiring ≤30d</div>
                <div class="text-2xl font-black text-orange-600 mt-1">{{ stats.expiring }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search agency name or code..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select v-model="status" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="All">All statuses</option>
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Terminated">Terminated</option>
                    </select>
                    <label class="inline-flex items-center gap-2 px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 cursor-pointer">
                        <input v-model="expiring" type="checkbox" class="w-4 h-4 accent-blue-600" />
                        Expiring within 30 days
                    </label>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[860px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Agency</th>
                            <th class="py-3 px-4">Contact</th>
                            <th class="py-3 px-4 text-center">Users</th>
                            <th class="py-3 px-4 text-center">Workers</th>
                            <th class="py-3 px-4">Contract end</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr
                            v-for="a in agencies"
                            :key="a.id"
                            @click="openAgency(a.id)"
                            :class="[
                                'transition cursor-pointer',
                                a.expiring_soon
                                    ? 'bg-yellow-50 hover:bg-yellow-100/70 dark:bg-yellow-900/10 dark:hover:bg-yellow-900/20'
                                    : 'hover:bg-slate-50 dark:hover:bg-slate-800/60',
                            ]"
                        >
                            <td class="py-3 px-4 text-xs font-mono font-bold text-slate-700 dark:text-slate-200">{{ a.code }}</td>
                            <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ a.name }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ a.contact_name || '—' }}</td>
                            <td class="py-3 px-4 text-center text-sm font-bold text-slate-700 dark:text-slate-200">{{ a.users_count }}</td>
                            <td class="py-3 px-4 text-center text-sm font-bold text-slate-700 dark:text-slate-200">{{ a.workers_count }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ a.contract_end ? new Date(a.contract_end + 'T00:00:00').toLocaleDateString() : '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', statusBadge(a.status)]">{{ a.status }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-1" @click.stop>
                                    <Link
                                        :href="route('hrm.agencies.show', a.id)"
                                        class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg"
                                        title="View"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                    <Link
                                        :href="route('hrm.agencies.edit', a.id)"
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
                <div v-if="agencies.length === 0" class="py-12 text-center">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">No agencies found. Add the first agency Monti Corp works with.</p>
                    <Link
                        :href="route('hrm.agencies.create')"
                        class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30"
                    >
                        <Plus class="w-4 h-4" /> Add agency
                    </Link>
                </div>
            </div>

            <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} agencies)
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
