<script setup lang="ts">
// Page 1: Agencies list (HRM > Agencies)
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from '@/lib/feedback';
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

function applyFilters(pageNum = 1) {
    const params: any = { page: pageNum };
    if (search.value) params.search = search.value;
    if (status.value && status.value !== 'All') params.status = status.value;
    router.get(route('hrm.agencies.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

let searchTimeout: any = null;
watch([search, status], () => {
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
        <div class="mb-2 flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-sm font-semibold leading-4 text-slate-900 dark:text-white">Agencies</h1>
                <p class="text-[10px] leading-3 text-slate-500 dark:text-slate-400">Manpower agencies Monti Corp works with.</p>
            </div>
            <Link
                :href="route('hrm.agencies.create')"
                class="inline-flex h-7 items-center justify-center gap-1 rounded-md bg-blue-600 px-2.5 text-[11px] font-semibold text-white shadow-sm transition hover:bg-blue-700"
            >
                <Plus class="h-3 w-3" /> Add agency
            </Link>
        </div>

        <div class="mb-2 grid w-full grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
            <div class="flex h-12 items-center justify-between gap-2 rounded-md border border-slate-100 bg-white px-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="text-[9px] font-medium uppercase tracking-wide text-slate-400">Total</div>
                <div class="text-sm font-semibold leading-4 text-slate-900 dark:text-white">{{ stats.total }}</div>
            </div>
            <div class="flex h-12 items-center justify-between gap-2 rounded-md border border-slate-100 bg-white px-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="text-[9px] font-medium uppercase tracking-wide text-slate-400">Active</div>
                <div class="text-sm font-semibold leading-4 text-emerald-600">{{ stats.active }}</div>
            </div>
            <div class="flex h-12 items-center justify-between gap-2 rounded-md border border-slate-100 bg-white px-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="text-[9px] font-medium uppercase tracking-wide text-slate-400">Suspended</div>
                <div class="text-sm font-semibold leading-4 text-amber-600">{{ stats.suspended }}</div>
            </div>
            <div class="flex h-12 items-center justify-between gap-2 rounded-md border border-slate-100 bg-white px-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="text-[9px] font-medium uppercase tracking-wide text-slate-400">Terminated</div>
                <div class="text-sm font-semibold leading-4 text-rose-600">{{ stats.terminated }}</div>
            </div>
            <div class="flex h-12 items-center justify-between gap-2 rounded-md border border-slate-100 bg-white px-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="text-[9px] font-medium uppercase tracking-wide text-slate-400">Expiring ≤30d</div>
                <div class="text-sm font-semibold leading-4 text-orange-600">{{ stats.expiring }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-3 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row lg:items-center gap-2.5 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search agency name or code..."
                        class="w-full pl-9 pr-4 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select v-model="status" class="px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-200">
                        <option value="All">All statuses</option>
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Terminated">Terminated</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[860px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-2.5 px-3">Agency</th>
                            <th class="py-2.5 px-3">Contact</th>
                            <th class="py-2.5 px-3 text-center">Users</th>
                            <th class="py-2.5 px-3 text-center">Workers</th>
                            <th class="py-2.5 px-3">Contract end</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                            <th class="py-2.5 px-3 text-right">Actions</th>
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
                            <td class="py-2.5 px-3 text-sm font-semibold text-slate-900 dark:text-white">{{ a.name }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ a.contact_name || '—' }}</td>
                            <td class="py-2.5 px-3 text-center text-sm font-semibold text-slate-700 dark:text-slate-200">{{ a.users_count }}</td>
                            <td class="py-2.5 px-3 text-center text-sm font-semibold text-slate-700 dark:text-slate-200">{{ a.workers_count }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ a.contract_end ? new Date(a.contract_end + 'T00:00:00').toLocaleDateString() : '—' }}</td>
                            <td class="py-2.5 px-3 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', statusBadge(a.status)]">{{ a.status }}</span>
                            </td>
                            <td class="py-2.5 px-3">
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
                <div v-if="agencies.length === 0" class="py-8 px-4 text-center">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">No agencies found. Add the first agency Monti Corp works with.</p>
                    <Link
                        :href="route('hrm.agencies.create')"
                        class="mt-3 inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition shadow-sm"
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
