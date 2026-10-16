<script setup lang="ts">
// Page 15: Generate statement (agency + period + live preview)
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps<{
    statement: any | null;
    suggested_code: string;
    agencies: Array<{ id: number; code: string; name: string; status: string }>;
    agency_id: number | null;
    from: string;
    to: string;
    preview: {
        lines: Array<{
            worker_id: number; worker_code: string | null; worker_name: string | null;
            request_id: number; request_code: string | null; department: string | null;
            days: number; hours: number; overtime_hours: number; rate: number | null; labor: number;
        }>;
        labor_cost: number; service_fee: number; total: number; count: number;
    } | null;
}>();

const isEdit = !!props.statement;
const agencyId = ref(props.agency_id || '');
const from = ref(props.from);
const to = ref(props.to);

const form = useForm({ code: props.statement?.code || '' });

function reloadPreview() {
    if (!agencyId.value || !from.value || !to.value) return;
    const url = isEdit
        ? route('hrm.billing.edit', props.statement.id)
        : route('hrm.billing.create');
    router.get(url, { agency_id: agencyId.value, from: from.value, to: to.value }, { preserveState: true, preserveScroll: true, replace: true });
}

let t: any = null;
watch([agencyId, from, to], () => {
    clearTimeout(t);
    t = setTimeout(reloadPreview, 500);
});

function save(issue: boolean) {
    form.transform((d: any) => ({
        code: d.code || null,
        agency_id: agencyId.value,
        period_start: from.value,
        period_end: to.value,
        issue,
    }));
    if (isEdit) {
        form.put(route('hrm.billing.update', props.statement.id), {
            preserveScroll: true,
            onError: (e: any) => toast.error(Object.values(e)[0] as string || 'Failed to save.'),
        });
    } else {
        form.post(route('hrm.billing.store'), {
            preserveScroll: true,
            onError: (e: any) => toast.error(Object.values(e)[0] as string || 'Failed to save.'),
        });
    }
}

function money(v: any): string {
    return '₱' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
}

const inputCls = 'w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200';
const labelCls = 'block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase';
</script>

<template>
    <Head :title="(isEdit ? 'Edit Statement' : 'Generate Statement') + ' - Monti ERP'" />
    <AuthenticatedLayout>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('hrm.billing.index')" class="hover:text-blue-600">Billing</Link>
            <span>/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ isEdit ? 'Edit' : 'Generate' }}</span>
        </nav>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight mb-4">
            {{ isEdit ? 'Edit statement' : 'Generate statement' }}
        </h1>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label :class="labelCls">Statement code</label>
                    <input v-model="form.code" type="text" :class="inputCls" :placeholder="suggested_code + ' (auto if blank)'" />
                    <p v-if="form.errors.code" class="mt-1 text-xs text-rose-600">{{ form.errors.code }}</p>
                </div>
                <div>
                    <label :class="labelCls">Agency <span class="text-red-500">*</span></label>
                    <select v-model="agencyId" :class="inputCls">
                        <option value="">Select agency...</option>
                        <option v-for="a in agencies" :key="a.id" :value="a.id">{{ a.name }} ({{ a.code }})</option>
                    </select>
                </div>
                <div>
                    <label :class="labelCls">Period from <span class="text-red-500">*</span></label>
                    <input v-model="from" type="date" :class="inputCls" />
                </div>
                <div>
                    <label :class="labelCls">Period to <span class="text-red-500">*</span></label>
                    <input v-model="to" type="date" :class="inputCls" />
                </div>
            </div>
            <p class="mt-2 text-[11px] text-slate-400">Preview covers approved, not-yet-billed attendance in the period (e.g. the 1st to the 15th).</p>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden mb-4">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                <p class="text-sm font-black text-slate-900 dark:text-white">Preview{{ preview ? ` — ${preview.count} record(s)` : '' }}</p>
            </div>
            <div v-if="!preview || preview.count === 0" class="py-10 text-center text-sm font-bold text-slate-500 dark:text-slate-400">
                No approved, unbilled attendance in this period yet.
            </div>
            <div v-else>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[760px]">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-2 px-4">Worker</th>
                                <th class="py-2 px-4">Request</th>
                                <th class="py-2 px-4 text-center">Days</th>
                                <th class="py-2 px-4 text-center">Hours</th>
                                <th class="py-2 px-4 text-center">OT</th>
                                <th class="py-2 px-4 text-right">Rate</th>
                                <th class="py-2 px-4 text-right">Labor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <tr v-for="(l, i) in preview.lines" :key="i" class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <td class="py-2 px-4 text-sm font-bold text-slate-900 dark:text-white">{{ l.worker_name }} <span class="font-mono text-[10px] text-slate-400">({{ l.worker_code }})</span></td>
                                <td class="py-2 px-4 text-xs font-mono text-slate-600 dark:text-slate-300">{{ l.request_code }}</td>
                                <td class="py-2 px-4 text-center text-xs text-slate-600 dark:text-slate-300">{{ l.days }}</td>
                                <td class="py-2 px-4 text-center text-xs text-slate-600 dark:text-slate-300">{{ l.hours }}</td>
                                <td class="py-2 px-4 text-center text-xs text-slate-600 dark:text-slate-300">{{ l.overtime_hours }}</td>
                                <td class="py-2 px-4 text-right text-xs text-slate-600 dark:text-slate-300">{{ l.rate !== null ? money(l.rate) : '—' }}</td>
                                <td class="py-2 px-4 text-right text-xs font-bold text-slate-900 dark:text-white">{{ money(l.labor) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex flex-col sm:flex-row sm:items-center justify-end gap-2 text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Labor <strong class="text-slate-900 dark:text-white">{{ money(preview.labor_cost) }}</strong></span>
                    <span class="text-slate-500 dark:text-slate-400">+ Fee <strong class="text-slate-900 dark:text-white">{{ money(preview.service_fee) }}</strong></span>
                    <span class="text-base font-black text-slate-900 dark:text-white">= {{ money(preview.total) }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pb-8">
            <Link
                :href="isEdit ? route('hrm.billing.show', statement.id) : route('hrm.billing.index')"
                class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
            >
                Cancel
            </Link>
            <button @click="save(false)" :disabled="form.processing || !preview || preview.count === 0" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition disabled:opacity-60">
                Save as draft
            </button>
            <button @click="save(true)" :disabled="form.processing || !preview || preview.count === 0" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 disabled:opacity-60">
                Issue statement
            </button>
        </div>
    </AuthenticatedLayout>
</template>
