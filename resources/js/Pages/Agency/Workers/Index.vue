<script setup lang="ts">
// Page 18: Agency workers (own workers only)
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Search, Plus, Eye, Pencil } from 'lucide-vue-next';

const props = defineProps<{
    workers: Array<{
        id: number; code: string; name: string; position_skill: string | null;
        daily_rate: string | number | null; status: string; clearance_expiry: string | null;
        current_assignment: string | null; pending_note?: string | null;
    }>;
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
    filters: { search?: string; status?: string };
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true, deep: true });

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'All');

function applyFilters(pageNum = 1) {
    const params: any = { page: pageNum };
    if (search.value) params.search = search.value;
    if (status.value && status.value !== 'All') params.status = status.value;
    router.get(route('agency.workers.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

let t: any = null;
watch([search, status], () => {
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
    <Head title="My Workers - Monti ERP" />
    <AgencyLayout>
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">Workers</h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5">Your agency's workers. New workers need HR approval.</p>
            </div>
            <Link :href="route('agency.workers.create')" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition shadow-lg shadow-blue-500/30">
                <Plus class="w-4 h-4" /> Add worker
            </Link>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input v-model="search" type="text" placeholder="Search name..." class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400" />
                </div>
                <select v-model="status" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                    <option value="All">All statuses</option>
                    <option>Available</option><option>Deployed</option><option>Inactive</option>
                    <option>Blacklisted</option><option>Pending</option><option>For Correction</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[680px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Name</th>
                            <th class="py-3 px-4">Position</th>
                            <th class="py-3 px-4">Assignment</th>
                            <th class="py-3 px-4">Clearance</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="w in workers" :key="w.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">
                                {{ w.name }}
                                <p v-if="w.status === 'For Correction' && w.pending_note" class="mt-0.5 text-[11px] font-medium text-orange-600 dark:text-orange-400">HR: {{ w.pending_note }}</p>
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ w.position_skill || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ w.current_assignment || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ w.clearance_expiry || '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', statusBadge(w.status)]">{{ w.status }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="route('agency.workers.edit', w.id)" class="p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg" title="Edit">
                                        <Pencil class="w-4 h-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="workers.length === 0" class="py-10 text-center text-sm font-bold text-slate-900 dark:text-white">
                    No workers yet.
                </div>
            </div>

            <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <p class="text-xs text-slate-500">Page {{ pagination.current_page }} of {{ pagination.last_page }}</p>
                <div class="flex items-center gap-1">
                    <button :disabled="pagination.current_page <= 1" @click="applyFilters(pagination.current_page - 1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40">Prev</button>
                    <button v-for="p in pageNumbers" :key="p" @click="applyFilters(p)" :class="['px-3 py-1.5 text-xs font-bold rounded-lg border', p === pagination.current_page ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 dark:border-slate-700']">{{ p }}</button>
                    <button :disabled="pagination.current_page >= pagination.last_page" @click="applyFilters(pagination.current_page + 1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>
    </AgencyLayout>
</template>
