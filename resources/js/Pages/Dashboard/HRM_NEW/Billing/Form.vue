<script setup lang="ts">
// Page 15: Generate statement (agency + period + live preview)
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { AlertTriangle, CalendarClock, FileCheck2, LoaderCircle } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { formatHours } from '@/lib/utils';

const props = defineProps<{
    statement: any | null;
    suggested_code: string;
    agencies: Array<{ id: number; code: string; name: string; status: string }>;
    agency_id: number | null;
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

const isEdit = !!props.statement;
const agencyId = ref(props.agency_id || '');
const from = ref(props.from);
const to = ref(props.to);
// Today in the app timezone (Asia/Manila), supplied by the backend rule.
const today = props.today || new Date().toLocaleDateString('en-CA');

const form = useForm({ code: props.statement?.code || '' });
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

const serverPeriodError = computed(() => fieldErrors.value.period_start || fieldErrors.value.period_end || fieldErrors.value.agency_id || '');
const validationMessage = computed(() => dateError.value || serverPeriodError.value);
const isPastPeriod = computed(() => !!to.value && to.value < today);

function reloadPreview() {
    if (!agencyId.value || !from.value || !to.value || dateError.value) return;
    const url = isEdit
        ? route('hrm.billing.edit', props.statement.id)
        : route('hrm.billing.create');
    router.get(url, { agency_id: agencyId.value, from: from.value, to: to.value }, { preserveState: true, preserveScroll: true, replace: true });
}

// Keeping Period to on/after Period from is automatic: a new Period from
// pushes Period to forward when it would otherwise fall before it.
watch(from, (v) => {
    if (v && to.value && to.value < v) {
        to.value = v;
    }
});

let t: any = null;
watch([agencyId, from, to], () => {
    clearTimeout(t);
    t = setTimeout(reloadPreview, 500);
});

const modalOpen = ref(false);
const modalMode = ref<'confirm' | 'past' | 'loading' | 'error'>('confirm');
const errorMsg = ref('');

const agencyName = computed(() => props.agencies.find((a) => a.id === agencyId.value)?.name || 'this agency');

function openIssueConfirm() {
    if (validationMessage.value) {
        errorMsg.value = validationMessage.value;
        modalMode.value = 'error';
        modalOpen.value = true;
        return;
    }
    // An old period is allowed but confirmed first, so HR never issues a
    // statement for the wrong (historical) period by accident.
    modalMode.value = isPastPeriod.value ? 'past' : 'confirm';
    modalOpen.value = true;
}

function confirmIssue() {
    modalMode.value = 'loading';
    save(true);
}

function save(issue: boolean) {
    fieldErrors.value = {};
    form.transform((d: any) => ({
        code: d.code || null,
        agency_id: agencyId.value,
        period_start: from.value,
        period_end: to.value,
        issue,
    }));
    const options = {
        preserveScroll: true,
        onError: (e: any) => {
            fieldErrors.value = e;
            const msg = (Object.values(e)[0] as string) || 'Failed to save.';
            if (issue) {
                errorMsg.value = msg;
                modalMode.value = 'error';
                modalOpen.value = true;
            } else {
                toast.error(msg);
            }
        },
    };
    if (isEdit) {
        form.put(route('hrm.billing.update', props.statement.id), options);
    } else {
        form.post(route('hrm.billing.store'), options);
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
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label :class="labelCls">Agency <span class="text-red-500">*</span></label>
                    <select v-model="agencyId" :class="inputCls">
                        <option value="">Select agency...</option>
                        <option v-for="a in agencies" :key="a.id" :value="a.id">{{ a.name }} ({{ a.code }})</option>
                    </select>
                </div>
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
            <p class="mt-2 text-[11px] text-slate-400">Preview covers all of this agency's attendance (any approved, unbilled records) and time-clock logs (My Attendance) in the period, plus workers still deployed to Monti (e.g. the 1st to the 15th).</p>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden mb-4">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                <p class="text-sm font-black text-slate-900 dark:text-white">Preview ({{ preview?.count ?? 0 }})</p>
            </div>
            <div v-if="!preview || preview.lines.length === 0" class="py-10 text-center text-sm font-bold text-slate-500 dark:text-slate-400">
                No attendance, time-clock logs, or deployed workers in this period yet.
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
                :href="isEdit ? route('hrm.billing.show', statement.id) : route('hrm.billing.index')"
                class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
            >
                Cancel
            </Link>
            <button @click="save(false)" :disabled="form.processing || !preview || preview.count === 0 || !!dateError" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition disabled:opacity-60">
                Save as draft
            </button>
            <button @click="openIssueConfirm" :disabled="form.processing || !preview || !!dateError" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 disabled:opacity-60">
                Issue statement
            </button>
        </div>

        <Modal :show="modalOpen" max-width="md" :closeable="modalMode !== 'loading'" @close="modalOpen = false">
            <div v-if="modalMode === 'confirm'" class="p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/15">
                        <FileCheck2 class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-tight">Issue statement</h2>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                            Issue a statement for <strong class="text-slate-900 dark:text-white">{{ agencyName }}</strong> covering
                            <strong class="text-slate-900 dark:text-white">{{ from }}</strong> to
                            <strong class="text-slate-900 dark:text-white">{{ to }}</strong>?
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="modalOpen = false" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                        Cancel
                    </button>
                    <button type="button" @click="confirmIssue" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30">
                        Issue statement
                    </button>
                </div>
            </div>
            <div v-else-if="modalMode === 'past'" class="p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/15">
                        <CalendarClock class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-tight">Confirm Past Billing Period</h2>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                            The selected billing period is in the past. The statement will include eligible, unbilled attendance records within the selected dates. Do you want to continue?
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="modalOpen = false" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                        Cancel
                    </button>
                    <button type="button" @click="confirmIssue" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30">
                        Continue
                    </button>
                </div>
            </div>
            <div v-else-if="modalMode === 'loading'" class="p-8">
                <div class="flex flex-col items-center justify-center text-center">
                    <LoaderCircle class="h-9 w-9 animate-spin text-blue-600 dark:text-blue-400" />
                    <p class="mt-3 text-sm font-bold text-slate-700 dark:text-slate-200">Issuing statement…</p>
                    <p class="mt-1 text-xs text-slate-400">Please wait while the period is checked and records are billed.</p>
                </div>
            </div>
            <div v-else class="p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-500/15">
                        <AlertTriangle class="h-5 w-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-tight">Cannot issue statement</h2>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ errorMsg }}</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" @click="modalOpen = false" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30">
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
