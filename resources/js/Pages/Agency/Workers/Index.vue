<script setup lang="ts">
// Page 18: Agency workers (own workers only)
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { toast } from '@/lib/feedback';
import { Search, Plus, Eye, Pencil } from 'lucide-vue-next';

const props = defineProps<{
    workers: Array<{
        id: number; code: string; name: string; created_at: string | null; position_skill: string | null; department: string | null;
        daily_rate: string | number | null; status: string; clearance_expiry: string | null;
        current_assignment: string | null; pending_note?: string | null;
    }>;
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
    filters: { search?: string; status?: string };
    expired_documents: Array<{ worker_id: number; worker_code: string; worker_name: string; label: string; review_note: string | null }>;
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

function formatAddedAt(value: string | null): { date: string; time: string } | null {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return {
        date: new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }).format(date),
        time: new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }).format(date),
    };
}
</script>

<template>
    <Head title="My Workers - Monti ERP" />
    <AgencyLayout>
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-400">Agency Portal</p>
                <h1 class="mt-1 text-2xl font-black text-slate-900 dark:text-white">My Workers</h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5">Your agency's workers. New workers need HR approval.</p>
            </div>
            <Link :href="route('agency.workers.create')" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-bold rounded-xl transition shadow-md">
                <Plus class="w-4 h-4" /> Add worker
            </Link>
        </div>

        <section v-if="expired_documents?.length" class="mb-4 rounded-2xl border border-rose-300 bg-rose-50 p-4 dark:border-rose-900 dark:bg-rose-950/30" aria-live="polite">
            <h2 class="text-sm font-black text-rose-800 dark:text-rose-300">HR requires replacement documents</h2>
            <p class="mt-1 text-xs text-rose-700 dark:text-rose-300">These documents were marked Expired. Replace each one in the worker profile and submit the new file for HR review.</p>
            <ul class="mt-3 space-y-2">
                <li v-for="(doc, index) in expired_documents" :key="`${doc.worker_id}-${index}`" class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white/70 px-3 py-2 text-xs dark:bg-black/20">
                    <span class="text-rose-900 dark:text-rose-200"><strong>{{ doc.worker_name }} ({{ doc.worker_code }})</strong> · {{ doc.label }}<span v-if="doc.review_note"> — HR: {{ doc.review_note }}</span></span>
                    <Link :href="route('agency.workers.edit', doc.worker_id)" class="shrink-0 font-bold text-rose-700 underline underline-offset-2 dark:text-rose-300">Replace document</Link>
                </li>
            </ul>
        </section>

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
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-slate-800 text-[10px] font-bold uppercase tracking-wider text-white/90">
                            <th class="py-3 px-4">Name</th>
                            <th class="py-3 px-4">Department</th>
                            <th class="py-3 px-4">Position</th>
                            <th class="py-3 px-4">Assignment</th>
                            <th class="py-3 px-4">Added</th>
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
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ w.department || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ w.position_skill || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">{{ w.current_assignment || '—' }}</td>
                            <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400">
                                <template v-if="formatAddedAt(w.created_at)">
                                    <p>{{ formatAddedAt(w.created_at)?.date }}</p>
                                    <p class="mt-0.5">{{ formatAddedAt(w.created_at)?.time }}</p>
                                </template>
                                <span v-else>—</span>
                            </td>
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
                    <button v-for="p in pageNumbers" :key="p" @click="applyFilters(p)" :class="['px-3 py-1.5 text-xs font-bold rounded-lg border', p === pagination.current_page ? 'bg-slate-800 text-white border-slate-800' : 'border-slate-200 dark:border-slate-700']">{{ p }}</button>
                    <button :disabled="pagination.current_page >= pagination.last_page" @click="applyFilters(pagination.current_page + 1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>
    </AgencyLayout>
</template>
