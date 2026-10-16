<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CheckCircle2, Search } from 'lucide-vue-next';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    period_meta: { type: Array, default: () => [] },
    current_period: { type: String, default: 'Oct 1–15, 2026' },
    calendar_year: { type: Number, default: 2026 },
    frequency: { type: String, default: 'semi_monthly' },
});

const periodList = computed(() => (props.period_meta || []).length ? props.period_meta : [
    { label: 'Oct 1–15, 2026', start: null, end: null, pay_date: null, locked: false },
    { label: 'Oct 16–31, 2026', start: null, end: null, pay_date: null, locked: false },
    { label: 'Nov 1–15, 2026', start: null, end: null, pay_date: null, locked: false },
]);

const form = useForm({
    cutoff_start: '',
    cutoff_end: '',
    period: props.current_period || 'Oct 1–15, 2026',
    frequency: props.frequency || 'semi_monthly',
    employee_ids: [],
});

const activeMeta = computed(() => periodList.value.find((p) => p.label === form.period) || null);

// Cutoff follows the pay period: Oct 1–15 → start Oct 1, end Oct 15, automatically.
const applyPeriodDates = () => {
    if (activeMeta.value?.start) form.cutoff_start = activeMeta.value.start;
    if (activeMeta.value?.end) form.cutoff_end = activeMeta.value.end;
};
watch(() => form.period, applyPeriodDates);
onMounted(applyPeriodDates);

const query = ref('');

const list = computed(() => {
    const q = query.value.trim().toLowerCase();
    const rows = props.employees || [];
    if (!q) return rows;
    return rows.filter((e) => (e.name || '').toLowerCase().includes(q) || String(e.id).includes(q)
        || (e.position || '').toLowerCase().includes(q) || (e.role || '').toLowerCase().includes(q)
        || (e.department || '').toLowerCase().includes(q));
});

const levelOf = (e) => [e.position, e.role, e.department].filter(Boolean).join(' · ');

const toggleAll = (e) => {
    form.employee_ids = e.target.checked ? list.value.map((emp) => emp.id) : [];
};

const submit = () => {
    form.post(route('hrm.payroll.generate.store'));
};
const switchFrequency = (e) => {
    router.get(route('hrm.payroll.generate'), { frequency: e.target.value, year: props.calendar_year || undefined }, { preserveState: false });
};
</script>

<template>
    <Head title="Generate Payroll" />
    <AuthenticatedLayout>
        <div class="flex min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-blue-50 payroll-caps">
            <div class="flex-1 p-8 text-slate-900 font-sans min-w-0 relative">
                <div class="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,transparent,black,transparent)] pointer-events-none"></div>
                <header class="mb-8 relative">
                    <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3">
                        <Link :href="route('hrm.payroll')" class="hover:text-slate-700 transition-colors">Payroll</Link>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        <span class="text-slate-800 font-semibold">Generate Payroll</span>
                    </nav>
                    <h1 class="text-4xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">Generate Payroll</h1>
                    <p class="text-sm text-slate-500 mt-1.5">Select a cutoff period and employees to run payroll. Each period starts as Draft.</p>
                </header>

                <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg backdrop-blur-sm overflow-hidden relative">
                    <div v-if="form.errors.error" class="m-5 mb-0 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold">{{ form.errors.error }}</div>
                    <div v-if="form.errors.period" class="m-5 mb-0 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold">{{ form.errors.period }}</div>
                    <div class="p-5 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1.5">Frequency</label>
                                <select :value="frequency" @change="switchFrequency" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="weekly">Weekly</option>
                                    <option value="semi_monthly">Semi-monthly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1.5">Pay period</label>
                                <select v-model="form.period" @change="applyPeriodDates" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option v-for="p in periodList" :key="p.label" :value="p.label">{{ p.label }}{{ p.pay_date ? ` · pay ${p.pay_date}` : '' }}{{ p.locked ? ' · Locked' : '' }}</option>
                                </select>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            <span v-if="activeMeta">Cutoff {{ activeMeta.start }} – {{ activeMeta.end }} · pay {{ activeMeta.pay_date }}.</span>
                            Statuses and inputs are stored per pay period. Locked periods refuse new drafts. Paid is final.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1.5">Cutoff start (from pay period)</label>
                                <input type="date" v-model="form.cutoff_start" readonly aria-readonly="true"
                                    class="w-full px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-600 focus:outline-none cursor-not-allowed" />
                                <div v-if="form.errors.cutoff_start" class="text-xs text-rose-600 mt-1">{{ form.errors.cutoff_start }}</div>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1.5">Cutoff end (from pay period)</label>
                                <input type="date" v-model="form.cutoff_end" readonly aria-readonly="true"
                                    class="w-full px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-600 focus:outline-none cursor-not-allowed" />
                                <div v-if="form.errors.cutoff_end" class="text-xs text-rose-600 mt-1">{{ form.errors.cutoff_end }}</div>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">Cutoff follows the pay period automatically — e.g. Oct 1–15 sets start Oct 1 and end Oct 15. Change the pay period to change the cutoff.</p>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-bold text-slate-700">Select employees ({{ form.employee_ids.length }} selected)</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3"><Search class="w-4 h-4 text-slate-400" /></span>
                                    <input v-model="query" type="text" placeholder="Search name, level, or department..."
                                        class="w-64 pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                                </div>
                            </div>
                            <label class="flex items-center gap-2 text-xs mb-2 cursor-pointer">
                                <input type="checkbox" @change="toggleAll" :checked="form.employee_ids.length === list.length && list.length > 0" class="rounded" />
                                Select all
                            </label>
                            <div class="border border-slate-200 rounded-xl overflow-hidden">
                                <div class="max-h-72 overflow-y-auto p-2 space-y-1">
                                    <div v-if="list.length === 0" class="p-4 text-center text-slate-400 text-sm">No active employees found.</div>
                                    <label v-for="emp in list" :key="emp.id" class="flex items-center gap-3 p-2 hover:bg-blue-50/50 rounded-lg cursor-pointer text-sm">
                                        <input type="checkbox" :value="emp.id" v-model="form.employee_ids" class="rounded" />
                                        <span class="font-medium">{{ emp.name }}</span>
                                        <span class="text-xs text-slate-400 ml-auto text-right">{{ levelOf(emp) }}{{ emp.basic_salary ? ` · ₱${Number(emp.basic_salary).toLocaleString('en-PH')}` : '' }}</span>
                                    </label>
                                </div>
                            </div>
                            <div v-if="form.errors.employee_ids" class="text-xs text-rose-600 mt-1">{{ form.errors.employee_ids }}</div>
                        </div>
                    </div>
                    <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                        <Link :href="route('hrm.payroll')" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition">Cancel</Link>
                        <button type="button" :disabled="form.processing" @click="submit"
                            class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-50">
                            <CheckCircle2 class="w-4 h-4" />{{ form.processing ? 'Generating…' : 'Generate Payroll' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.payroll-caps, .payroll-caps *, .payroll-caps input::placeholder { text-transform: capitalize; }
.bg-grid-slate-100 { background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px); background-size: 24px 24px; }
.bg-clip-text { -webkit-background-clip: text; background-clip: text; }
</style>
