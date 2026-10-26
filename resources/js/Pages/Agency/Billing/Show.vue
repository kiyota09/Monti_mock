<script setup lang="ts">
// Agency portal: statement detail (read-only breakdown + exports)
import { Head, Link, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { FileDown, FileSpreadsheet } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { formatHours } from '@/lib/utils';

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.message) toast.success(flash.message);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true, deep: true });

defineProps<{
    statement: {
        id: number; code: string; period: string; status: string;
        service_fee_type: string | null; service_fee_value: string | number | null;
        labor_cost: string | number; service_fee: string | number; total: string | number;
        total_hours: number; total_days: number;
        lines: Array<{
            worker_id: number; worker_code: string | null; worker_name: string | null;
            request_id: number; request_code: string | null; department: string | null;
            days: number; hours: number; overtime_hours: number; rate: number | null; labor: number;
        }>;
        record_count: number;
        paid_at: string | null; payment_reference: string | null;
    };
}>();

function money(v: any): string {
    return '₱' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head :title="statement.code + ' - Monti ERP'" />
    <AgencyLayout>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('agency.billing.index')" class="hover:text-blue-600">Billing</Link>
            <span>/</span>
            <span class="font-semibold">{{ statement.code }}</span>
        </nav>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ statement.code }}</h1>
                <p class="text-sm text-slate-500 mt-0.5">{{ statement.period }} · <strong>{{ statement.status }}</strong></p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a :href="route('agency.billing.export-pdf', statement.id)" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold hover:bg-slate-50 transition">
                    <FileDown class="w-3.5 h-3.5" /> PDF
                </a>
                <a :href="route('agency.billing.export-excel', statement.id)" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold hover:bg-slate-50 transition">
                    <FileSpreadsheet class="w-3.5 h-3.5" /> Excel
                </a>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5 mb-4">
            <h2 class="text-sm font-black uppercase tracking-wider mb-3">Breakdown by worker</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[560px]">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="py-2 px-3">Worker</th>
                            <th class="py-2 px-3">Request</th>
                            <th class="py-2 px-3 text-center">Days</th>
                            <th class="py-2 px-3 text-center">Hours</th>
                            <th class="py-2 px-3 text-right">Labor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="(l, i) in statement.lines" :key="i">
                            <td class="py-2 px-3 text-sm font-bold">{{ l.worker_name }} <span class="font-mono text-[10px] text-slate-400">({{ l.worker_code }})</span></td>
                            <td class="py-2 px-3 text-xs font-mono">{{ l.request_code }}</td>
                            <td class="py-2 px-3 text-center text-xs">{{ l.days }}</td>
                            <td class="py-2 px-3 text-center text-xs">{{ formatHours(l.hours) }}{{ l.overtime_hours ? '+' + formatHours(l.overtime_hours) + ' OT' : '' }}</td>
                            <td class="py-2 px-3 text-right text-xs font-bold">{{ money(l.labor) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <dl class="mt-4 space-y-1 text-sm max-w-xs ml-auto">
                <div class="flex justify-between"><dt class="text-slate-400">Labor cost</dt><dd class="font-bold">{{ money(statement.labor_cost) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Service fee</dt><dd class="font-bold">{{ money(statement.service_fee) }}</dd></div>
                <div class="flex justify-between text-base"><dt class="font-black">Total</dt><dd class="font-black">{{ money(statement.total) }}</dd></div>
            </dl>
            <p v-if="statement.status === 'Paid'" class="mt-3 text-xs text-slate-500 text-right">
                Paid {{ statement.paid_at || '' }}{{ statement.payment_reference ? ' · Ref: ' + statement.payment_reference : '' }}
            </p>
        </div>
    </AgencyLayout>
</template>
