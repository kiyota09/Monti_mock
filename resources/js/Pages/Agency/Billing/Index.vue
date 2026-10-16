<script setup lang="ts">
// Page 21: Agency billing (Issued + Paid only, read-only + exports)
import { Head, Link } from '@inertiajs/vue3';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { Eye, FileDown, FileSpreadsheet } from 'lucide-vue-next';

defineProps<{
    statements: Array<{
        id: number; code: string; period: string;
        labor_cost: string | number; service_fee: string | number; total: string | number;
        status: string; paid_at: string | null; payment_reference: string | null;
    }>;
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
}>();

function money(v: any): string {
    return '₱' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Billing - Monti ERP" />
    <AgencyLayout>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">Billing</h1>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5 mb-4">Issued and paid statements for your agency.</p>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[680px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Period</th>
                            <th class="py-3 px-4 text-right">Labor</th>
                            <th class="py-3 px-4 text-right">Fee</th>
                            <th class="py-3 px-4 text-right">Total</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="s in statements" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="py-3 px-4 text-xs font-mono font-bold">{{ s.code }}</td>
                            <td class="py-3 px-4 text-xs whitespace-nowrap">{{ s.period }}</td>
                            <td class="py-3 px-4 text-right text-xs font-bold">{{ money(s.labor_cost) }}</td>
                            <td class="py-3 px-4 text-right text-xs font-bold">{{ money(s.service_fee) }}</td>
                            <td class="py-3 px-4 text-right text-xs font-black">{{ money(s.total) }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', s.status === 'Paid' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-blue-100 text-blue-700 border-blue-200']">{{ s.status }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="route('agency.billing.show', s.id)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="View">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                    <a :href="route('agency.billing.export-pdf', s.id)" class="p-1.5 text-slate-500 hover:bg-slate-100 rounded-lg" title="PDF">
                                        <FileDown class="w-4 h-4" />
                                    </a>
                                    <a :href="route('agency.billing.export-excel', s.id)" class="p-1.5 text-slate-500 hover:bg-slate-100 rounded-lg" title="Excel">
                                        <FileSpreadsheet class="w-4 h-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="statements.length === 0" class="py-10 text-center text-sm font-bold">No statements yet.</div>
            </div>
        </div>
    </AgencyLayout>
</template>
