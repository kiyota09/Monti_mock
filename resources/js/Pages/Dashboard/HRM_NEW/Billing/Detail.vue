<script setup lang="ts">
// Page 16: Billing statement detail
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Pencil, FileDown, FileSpreadsheet } from 'lucide-vue-next';
import { formatHours } from '@/lib/utils';

const props = defineProps<{
    statement: {
        id: number; code: string; agency_id: number; agency: string | null; agency_code: string | null;
        service_fee_type: string | null; service_fee_value: string | number | null;
        period: string; period_start: string; period_end: string; status: string;
        labor_cost: string | number; service_fee: string | number; total: string | number;
        total_hours: number; total_days: number;
        lines: Array<{
            worker_id: number; worker_code: string | null; worker_name: string | null;
            request_id: number; request_code: string | null; department: string | null;
            days: number; hours: number; overtime_hours: number; rate: number | null; labor: number;
        }>;
        record_count: number;
        paid_at: string | null; payment_reference: string | null; cancel_reason: string | null;
        activities: Array<{ id: number; date: string; action: string; details: string | null; actor: string; reason: string | null }>;
    };
    permissions?: Record<string, string>;
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash || {});
const canEdit = computed(() => (props.permissions?.agency_billing || 'edit') === 'edit');

const showCancel = ref(false);
const cancelReason = ref('');
const cancelError = ref('');

const showPay = ref(false);
const payForm = useForm({ paid_at: new Date().toISOString().slice(0, 10), payment_reference: '' });

function submitCancel() {
    if (!cancelReason.value.trim()) {
        cancelError.value = 'A reason is required.';
        return;
    }
    router.post(route('hrm.billing.cancel', props.statement.id), { reason: cancelReason.value }, {
        preserveScroll: true,
        onSuccess: () => { showCancel.value = false; cancelReason.value = ''; },
    });
}

function submitPay() {
    payForm.post(route('hrm.billing.pay', props.statement.id), {
        preserveScroll: true,
        onSuccess: () => { showPay.value = false; },
    });
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

function actionLabel(a: string): string {
    const map: Record<string, string> = {
        statement_created: 'Created', statement_updated: 'Updated', statement_issued: 'Issued',
        statement_paid: 'Paid', statement_cancelled: 'Cancelled',
    };
    return map[a] || a;
}

const inputCls = 'w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200';
const labelCls = 'block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase';
</script>

<template>
    <Head :title="statement.code + ' - Billing - Monti ERP'" />
    <AuthenticatedLayout>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('hrm.billing.index')" class="hover:text-blue-600">Billing</Link>
            <span>/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ statement.code }}</span>
        </nav>

        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white leading-tight">{{ statement.code }}</h1>
                <div class="flex flex-wrap items-center gap-2 mt-1">
                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase border', statusBadge(statement.status)]">{{ statement.status }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ statement.agency || '' }} · {{ statement.period }}</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <Link
                    v-if="statement.status === 'Draft' && canEdit"
                    :href="route('hrm.billing.edit', statement.id)"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                >
                    <Pencil class="w-3.5 h-3.5" /> Edit
                </Link>
                <a :href="route('hrm.billing.export-pdf', statement.id)" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                    <FileDown class="w-3.5 h-3.5" /> PDF
                </a>
                <a :href="route('hrm.billing.export-excel', statement.id)" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                    <FileSpreadsheet class="w-3.5 h-3.5" /> Excel
                </a>
                <button v-if="statement.status === 'Issued' && canEdit" @click="showPay = true" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition">Mark as paid</button>
                <button v-if="['Draft', 'Issued'].includes(statement.status) && canEdit" @click="showCancel = true; cancelReason = ''; cancelError = ''" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition">Cancel</button>
            </div>
        </div>

        <div v-if="flash.success" class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-sm font-semibold text-rose-700 dark:text-rose-400">
            {{ flash.error }}
        </div>
        <div v-if="statement.status === 'Cancelled' && statement.cancel_reason" class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-400">
            <span class="font-black">Cancelled:</span> {{ statement.cancel_reason }}
        </div>

        <div v-if="showCancel && canEdit" class="mb-4 p-4 rounded-xl bg-white dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-600">
            <p class="text-sm font-black text-slate-900 dark:text-white">Cancel statement {{ statement.code }}?</p>
            <p class="mt-1 text-xs text-slate-500">Issued statements release their attendance back to billable.</p>
            <textarea v-model="cancelReason" rows="2" placeholder="Reason (required)" class="mt-3 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
            <p v-if="cancelError" class="mt-1 text-xs text-rose-600">{{ cancelError }}</p>
            <div class="mt-3 flex items-center justify-end gap-2">
                <button @click="showCancel = false" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">Back</button>
                <button @click="submitCancel" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold">Confirm cancellation</button>
            </div>
        </div>

        <div v-if="showPay && canEdit" class="mb-4 p-4 rounded-xl bg-white dark:bg-slate-800 border-2 border-dashed border-slate-300 dark:border-slate-600">
            <p class="text-sm font-black text-slate-900 dark:text-white">Mark {{ statement.code }} as paid</p>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label :class="labelCls">Date paid <span class="text-red-500">*</span></label>
                    <input v-model="payForm.paid_at" type="date" :class="inputCls" />
                    <p v-if="payForm.errors.paid_at" class="mt-1 text-xs text-rose-600">{{ payForm.errors.paid_at }}</p>
                </div>
                <div>
                    <label :class="labelCls">Reference number <span class="text-red-500">*</span></label>
                    <input v-model="payForm.payment_reference" type="text" :class="inputCls" placeholder="OR / transaction ref" />
                    <p v-if="payForm.errors.payment_reference" class="mt-1 text-xs text-rose-600">{{ payForm.errors.payment_reference }}</p>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-end gap-2">
                <button @click="showPay = false" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">Back</button>
                <button @click="submitPay" :disabled="payForm.processing" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold disabled:opacity-60">Confirm payment</button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 lg:col-span-2">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Breakdown by worker and request</h2>
                <div v-if="statement.lines.length === 0" class="text-xs text-slate-400">No billed records.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[560px]">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-2 px-3">Worker</th>
                                <th class="py-2 px-3">Request</th>
                                <th class="py-2 px-3 text-center">Days</th>
                                <th class="py-2 px-3 text-center">Hours</th>
                                <th class="py-2 px-3 text-right">Labor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr v-for="(l, i) in statement.lines" :key="i" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <td class="py-2 px-3 text-sm font-bold text-slate-900 dark:text-white">{{ l.worker_name }} <span class="font-mono text-[10px] text-slate-400">({{ l.worker_code }})</span></td>
                                <td class="py-2 px-3 text-xs font-mono text-slate-600 dark:text-slate-300">{{ l.request_code }}</td>
                                <td class="py-2 px-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ l.days }}</td>
                                <td class="py-2 px-3 text-center text-xs text-slate-600 dark:text-slate-300">{{ formatHours(l.hours) }}<span v-if="l.overtime_hours">+{{ formatHours(l.overtime_hours) }} OT</span></td>
                                <td class="py-2 px-3 text-right text-xs font-bold text-slate-900 dark:text-white">{{ money(l.labor) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Totals</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-400">Records</dt><dd class="font-bold text-slate-700 dark:text-slate-200">{{ statement.record_count }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-400">Days / Hours</dt><dd class="font-bold text-slate-700 dark:text-slate-200">{{ statement.total_days }} / {{ formatHours(statement.total_hours) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-400">Labor cost</dt><dd class="font-bold text-slate-700 dark:text-slate-200">{{ money(statement.labor_cost) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-400">Service fee</dt><dd class="font-bold text-slate-700 dark:text-slate-200">{{ money(statement.service_fee) }}</dd></div>
                        <div class="flex justify-between text-base"><dt class="font-black text-slate-900 dark:text-white">Total</dt><dd class="font-black text-slate-900 dark:text-white">{{ money(statement.total) }}</dd></div>
                    </dl>
                    <div v-if="statement.status === 'Paid'" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 text-xs text-slate-500">
                        Paid {{ statement.paid_at || '' }}{{ statement.payment_reference ? ' · Ref: ' + statement.payment_reference : '' }}
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-3">Activity (last 20)</h2>
                    <div v-if="statement.activities.length === 0" class="text-xs text-slate-400">No activity yet.</div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <li v-for="a in statement.activities" :key="a.id" class="py-2 text-xs">
                            <span class="text-slate-400">{{ a.date }} · </span>
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ actionLabel(a.action) }}</span>
                            <span class="text-slate-500 dark:text-slate-400"> — {{ a.details || '' }} by {{ a.actor }}</span>
                            <span v-if="a.reason" class="text-slate-400 italic"> “{{ a.reason }}”</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
