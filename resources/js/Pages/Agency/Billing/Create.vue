<script setup lang="ts">
// Agency portal: create an invoice from unbilled attendance
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { Plus } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { formatHours } from '@/lib/utils';

const props = defineProps<{
    agency: { id: number; code: string; name: string };
    from: string;
    to: string;
    today?: string;
    preview: {
        lines: Array<{
            worker_id: number; worker_code: string | null; worker_name: string | null;
            request_id: number; request_code: string | null; department: string | null;
            days: number; hours: number; overtime_hours: number; rate: number | null; labor: number;
        }>;
        labor_cost: number; service_fee: number; total: number; count: number;
    } | null;
}>();

const from = ref(props.from);
const to = ref(props.to);
// Today in the app timezone (Asia/Manila), supplied by the backend rule.
const today = props.today || new Date().toLocaleDateString('en-CA');

const form = useForm({});
const fieldErrors = ref<Record<string, string>>({});

// Client-side mirror of the backend date rules. Past periods are allowed;
// future dates and reversed ranges are not.
const dateError = computed(() => {
    if (!from.value || !to.value) return 'Both Period from and Period to are required.';
    if (from.value > today) return 'Period from cannot be later than today.';
    if (to.value > today) return 'Period to cannot be later than today.';
    if (to.value < from.value) return 'Period to must be on or after Period from.';
    return '';
});

const serverPeriodError = computed(() => fieldErrors.value.period_start || fieldErrors.value.period_end || '');
const validationMessage = computed(() => dateError.value || serverPeriodError.value);

function reloadPreview() {
    if (!from.value || !to.value || dateError.value) return;
    router.get(route('agency.billing.create'), { from: from.value, to: to.value }, { preserveState: true, preserveScroll: true, replace: true });
}

// Keeping Period to on/after Period from is automatic: a new Period from
// pushes Period to forward when it would otherwise fall before it.
watch(from, (v) => {
    if (v && to.value && to.value < v) {
        to.value = v;
    }
});

let t: any = null;
watch([from, to], () => {
    clearTimeout(t);
    t = setTimeout(reloadPreview, 500);
});

const canCreate = computed(() => !form.processing && !validationMessage.value && !!props.preview && props.preview.count > 0);

function save() {
    if (validationMessage.value) {
        toast.error(validationMessage.value);
        return;
    }
    fieldErrors.value = {};
    form.transform(() => ({
        period_start: from.value,
        period_end: to.value,
    }));
    form.post(route('agency.billing.store'), {
        preserveScroll: true,
        onError: (e: any) => {
            fieldErrors.value = e;
            toast.error((Object.values(e)[0] as string) || 'Failed to create invoice.');
        },
    });
}

function money(v: any): string {
    return '₱' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
}

const inputCls = 'w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200';
const labelCls = 'block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase';
</script>

<template>
    <Head title="Create Invoice - Monti ERP" />
    <AgencyLayout>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('agency.billing.index')" class="hover:text-blue-600">Billing</Link>
            <span>/</span>
            <span class="text-slate-800 dark:text-slate-200 font-semibold">Create Invoice</span>
        </nav>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight mb-4">Create Invoice</h1>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label :class="labelCls">Period from <span class="text-red-500">*</span></label>
                    <input v-model="from" type="date" :max="today" :class="inputCls" />
                </div>
                <div>
                    <label :class="labelCls">Period to <span class="text-red-500">*</span></label>
                    <input v-model="to" type="date" :max="today" :min="from || undefined" :class="inputCls" />
                </div>
            </div>
            <p v-if="validationMessage" class="mt-2 text-xs font-semibold text-red-500">{{ validationMessage }}</p>
            <p class="mt-2 text-[11px] text-slate-400">The invoice covers all of {{ agency.name }}'s unbilled attendance and time-clock logs in the selected period.</p>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden mb-4">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                <p class="text-sm font-black text-slate-900 dark:text-white">Preview ({{ preview?.count ?? 0 }})</p>
            </div>
            <div v-if="!preview || preview.lines.length === 0" class="py-10 text-center text-sm font-bold text-slate-500 dark:text-slate-400">
                No unbilled attendance or time-clock logs in this period yet.
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
                                <td class="py-2 px-4 text-center text-xs text-slate-600 dark:text-slate-300">{{ formatHours(l.hours) }}</td>
                                <td class="py-2 px-4 text-center text-xs text-slate-600 dark:text-slate-300">{{ formatHours(l.overtime_hours) }}</td>
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
                :href="route('agency.billing.index')"
                class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
            >
                Cancel
            </Link>
            <button @click="save" :disabled="!canCreate" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 disabled:opacity-60">
                <Plus class="w-4 h-4" /> {{ form.processing ? 'Creating…' : 'Create Invoice' }}
            </button>
        </div>
    </AgencyLayout>
</template>
