<script setup lang="ts">
// Page 14: Billing statements list
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Search, Plus, Eye, Pencil } from 'lucide-vue-next';
import { formatHours } from '@/lib/utils';

const props = defineProps<{
    statements: Array<{
        id: number; code: string; agency: string | null; agency_id: number;
        period: string; period_start: string; period_end: string;
        total_hours: number; labor_cost: string | number; service_fee: string | number;
        total: string | number; status: string;
    }>;
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
    agencies: Array<{ id: number; code: string; name: string; status: string }>;
    filters: { agency_id?: string; from?: string; to?: string; status?: string };
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.message) toast.info(flash.message);
}, { immediate: true, deep: true });

const agencyId = ref(props.filters?.agency_id || 'All');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const status = ref(props.filters?.status || 'All');

function applyFilters(pageNum = 1) {
    const params: any = { page: pageNum };
    if (agencyId.value && agencyId.value !== 'All') params.agency_id = agencyId.value;
    if (from.value) params.from = from.value;
    if (to.value) params.to = to.value;
    if (status.value && status.value !== 'All') params.status = status.value;
    router.get(route('hrm.billing.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}

let t: any = null;
watch([agencyId, from, to, status], () => {
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

function openStatement(id: number) {
    router.visit(route('hrm.billing.show', id));
}

function statusBadge(s: string): string {
    const map: Record<string, string> = {
        Draft: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
        Issued: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        Paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        Cancelled: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
    };
    return map[s] || 'bg-slate-100 text-slate-600 border-slate-200';
}

function money(v: any): string {
    return '₱' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Billing Statements - Monti ERP" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Billing Statements
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Agency labor billing per period.</p>
            </div>
            <Link
                :href="route('hrm.billing.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition shadow-lg shadow-blue-500/30 active:scale-95"
            >
                <Plus class="w-4 h-4" /> Generate statement
            </Link>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center gap-2 bg-slate-50/50 dark:bg-slate-800/50">
                <select v-model="agencyId" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                    <option value="All">All agencies</option>
                    <option v-for="a in agencies" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
                <input v-model="from" type="date" title="Period from" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                <input v-model="to" type="date" title="Period to" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                <select v-model="status" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                    <option value="All">All statuses</option>
                    <option>Draft</option><option>Issued</option><option>Paid</option><option>Cancelled</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[920px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Agency</th>
                            <th class="py-3 px-4">Period</th>
                            <th class="py-3 px-4 text-center">Total hours</th>
                            <th class="py-3 px-4 text-right">Labor cost</th>
                            <th class="py-3 px-4 text-right">Service fee</th>
                            <th class="py-3 px-4 text-right">Total</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="s in statements" :key="s.id" @click="openStatement(s.id)" class="transition cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="py-3 px-4 text-xs font-mono font-bold text-slate-700 dark:text-slate-200">{{ s.code }}</td>
                            <td class="py-3 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ s.agency || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ s.period }}</td>
                            <td class="py-3 px-4 text-center text-xs font-bold text-slate-700 dark:text-slate-200">{{ formatHours(s.total_hours) }}</td>
                            <td class="py-3 px-4 text-right text-xs font-bold text-slate-700 dark:text-slate-200">{{ money(s.labor_cost) }}</td>
                            <td class="py-3 px-4 text-right text-xs font-bold text-slate-700 dark:text-slate-200">{{ money(s.service_fee) }}</td>
                            <td class="py-3 px-4 text-right text-xs font-black text-slate-900 dark:text-white">{{ money(s.total) }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border whitespace-nowrap', statusBadge(s.status)]">{{ s.status }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-1" @click.stop>
                                    <Link :href="route('hrm.billing.show', s.id)" class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg" title="View">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                    <Link v-if="s.status === 'Draft'" :href="route('hrm.billing.edit', s.id)" class="p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg" title="Edit">
                                        <Pencil class="w-4 h-4" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="statements.length === 0" class="py-12 text-center">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">No billing statements yet.</p>
                    <Link :href="route('hrm.billing.create')" class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">
                        <Plus class="w-4 h-4" /> Generate statement
                    </Link>
                </div>
            </div>

            <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <p class="text-xs text-slate-500 dark:text-slate-400">Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} statements)</p>
                <div class="flex items-center gap-1">
                    <button :disabled="pagination.current_page <= 1" @click="applyFilters(pagination.current_page - 1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-700">Prev</button>
                    <button v-for="p in pageNumbers" :key="p" @click="applyFilters(p)" :class="['px-3 py-1.5 text-xs font-bold rounded-lg border', p === pagination.current_page ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700']">{{ p }}</button>
                    <button :disabled="pagination.current_page >= pagination.last_page" @click="applyFilters(pagination.current_page + 1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-700">Next</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
