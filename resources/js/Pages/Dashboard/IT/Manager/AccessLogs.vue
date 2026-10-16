<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Search, ScrollText } from 'lucide-vue-next';

const props = defineProps({
    logs: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    actions: { type: Array, default: () => [] },
});

const applyFilters = (patch) => {
    router.get(route('it.access-logs'), { ...props.filters, ...patch }, { preserveScroll: true, replace: true });
};

const rows = computed(() => props.logs.data || []);

const actionClass = (a) => ({
    'account.enabled': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'account.restored': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'account.disabled': 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'account.suspended': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'position.updated': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'modules.updated': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    'pages.updated': 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
}[a] || 'bg-slate-100 text-slate-600');

const actionLabel = (a) => a.replace('account.', '').replace('position.', 'promoted: ').replace('.', ' → ').replace('_', ' ');

const goPage = (url) => {
    if (url) router.get(url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Access Logs | IT Department" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Access <span class="text-blue-600">Logs</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">
                    Immutable audit trail of every change made in IT Access Control.
                </p>
            </div>
            <Link :href="route('it.access-control')"
                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-4 py-3 sm:py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95">
                <ScrollText class="w-4 h-4" /> Back to Access Control
            </Link>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input :value="filters.search || ''" @input="applyFilters({ search: $event.target.value })" type="text"
                        placeholder="Search details, actor, or employee..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400" />
                </div>
                <select :value="filters.action || ''" @change="applyFilters({ action: $event.target.value })"
                    class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                    <option value="">All actions</option>
                    <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[720px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700">
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">When</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Action</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Employee</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Details</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Changed By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="log in rows" :key="log.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5 text-xs font-mono text-slate-500 whitespace-nowrap">
                                {{ new Date(log.created_at).toLocaleString() }}
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5 whitespace-nowrap">
                                <span :class="['px-2.5 py-1 rounded-full text-[10px] font-black uppercase', actionClass(log.action)]">
                                    {{ actionLabel(log.action) }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5 text-sm font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                {{ log.target?.name || '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5 text-xs text-slate-500 max-w-md">{{ log.details }}</td>
                            <td class="px-4 sm:px-6 py-3 sm:py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                {{ log.actor?.name || 'system' }}
                                <span v-if="log.ip_address" class="block font-mono text-[10px] text-slate-400">{{ log.ip_address }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="rows.length === 0" class="py-12 text-center">
                    <ScrollText class="w-12 h-12 mx-auto mb-3 text-slate-300" />
                    <p class="text-sm font-bold text-slate-400">No log entries yet. Changes made in Access Control will appear here.</p>
                </div>
            </div>

            <div v-if="logs.prev_page_url || logs.next_page_url"
                class="px-4 sm:px-6 py-3 sm:py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 flex items-center justify-between">
                <span class="text-xs text-slate-500">Page {{ logs.current_page }} of {{ logs.last_page }} · {{ logs.total }} entries</span>
                <div class="flex gap-2">
                    <button @click="goPage(logs.prev_page_url)" :disabled="!logs.prev_page_url"
                        class="px-3 py-2 sm:py-1.5 text-xs font-bold text-slate-500 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg disabled:opacity-50">Previous</button>
                    <button @click="goPage(logs.next_page_url)" :disabled="!logs.next_page_url"
                        class="px-3 py-2 sm:py-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg disabled:opacity-50">Next</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
