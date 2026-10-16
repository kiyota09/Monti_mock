<script setup lang="ts">
// Page 19: Agency requests (read-only) + propose workers on open requests
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Search, Eye } from 'lucide-vue-next';

const props = defineProps<{
    requests: Array<{
        id: number; code: string; department: string; position_skill: string;
        needed: number; filled: number; start_date: string | null; status: string;
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
    router.get(route('agency.requests.index'), params, { preserveState: true, preserveScroll: true, replace: true });
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
        Draft: 'bg-slate-100 text-slate-500 border-slate-200',
        Open: 'bg-blue-100 text-blue-700 border-blue-200',
        'Partially filled': 'bg-amber-100 text-amber-700 border-amber-200',
        Filled: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        Cancelled: 'bg-rose-100 text-rose-700 border-rose-200',
        Closed: 'bg-slate-200 text-slate-600 border-slate-300',
    };
    return map[s] || 'bg-slate-100 text-slate-600 border-slate-200';
}
</script>

<template>
    <Head title="Requests - Monti ERP" />
    <AgencyLayout>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">Requests</h1>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5 mb-4">Requests addressed to your agency. Propose workers on open requests — HR approves each proposal.</p>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input v-model="search" type="text" placeholder="Search by request code..." class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-400" />
                </div>
                <select v-model="status" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                    <option value="All">All statuses</option>
                    <option>Open</option><option>Partially filled</option><option>Filled</option>
                    <option>Closed</option><option>Cancelled</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[720px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Department</th>
                            <th class="py-3 px-4">Position</th>
                            <th class="py-3 px-4 text-center">Needed</th>
                            <th class="py-3 px-4 text-center">Filled</th>
                            <th class="py-3 px-4">Start</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="r in requests" :key="r.id" @click="router.visit(route('agency.requests.show', r.id))" class="transition cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="py-3 px-4 text-xs font-mono font-bold text-slate-700 dark:text-slate-200">{{ r.code }}</td>
                            <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ r.department }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ r.position_skill }}</td>
                            <td class="py-3 px-4 text-center text-sm font-bold">{{ r.needed }}</td>
                            <td class="py-3 px-4 text-center text-sm font-bold">{{ r.filled }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500">{{ r.start_date || '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border whitespace-nowrap', statusBadge(r.status)]">{{ r.status }}</span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <Link :href="route('agency.requests.show', r.id)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="View" @click.stop>
                                    <Eye class="w-4 h-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="requests.length === 0" class="py-10 text-center text-sm font-bold text-slate-900 dark:text-white">
                    No requests addressed to your agency yet.
                </div>
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
    </AgencyLayout>
</template>
