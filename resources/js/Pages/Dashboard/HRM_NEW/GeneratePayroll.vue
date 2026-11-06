<script setup>
<<<<<<< HEAD
import { showNotice } from '@/lib/feedback';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch } from 'vue';
=======
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch, nextTick } from 'vue';
>>>>>>> 4a5038e28b6a55ea65129e3176825edb8849a073
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CheckCircle2, Search, X } from 'lucide-vue-next';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    period_meta: { type: Array, default: () => [] },
    current_date: { type: String, default: '' },
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
    cutoff_end: props.current_date || '',
    period: props.current_period || 'Oct 1–15, 2026',
    frequency: props.frequency || 'semi_monthly',
    employee_ids: [],
});

const activeMeta = computed(() => periodList.value.find((p) => p.label === form.period) || null);

// Cutoff follows the pay period: Oct 1–15 → start Oct 1, end Oct 15, automatically.
const applyPeriodDates = () => {
    if (activeMeta.value?.start) form.cutoff_start = activeMeta.value.start;
    form.cutoff_end = props.current_date || form.cutoff_end;
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

const showConfirm = ref(false);
const confirmSubmitting = ref(false);
const missingEmployees = ref(false);

const selectedEmployees = computed(() => {
    if (!form.employee_ids.length) return [];
    const idSet = new Set(form.employee_ids);
    return (props.employees || []).filter((e) => idSet.has(e.id));
});

const openConfirm = () => {
    if (!form.employee_ids.length) {
<<<<<<< HEAD
        showNotice('Please select at least one employee.');
        return;
    }
    if (activeMeta.value?.is_current) {
        showNotice('Cannot generate payroll for the current period. Please select a past period.');
=======
        missingEmployees.value = true;
        showConfirm.value = true;
        return;
    }
    if (activeMeta.value?.locked) {
        alert('This pay period is locked because all payroll records are Paid.');
>>>>>>> 4a5038e28b6a55ea65129e3176825edb8849a073
        return;
    }
    missingEmployees.value = false;
    showConfirm.value = true;
};

const closeConfirm = () => {
    if (confirmSubmitting.value) return;
    showConfirm.value = false;
    missingEmployees.value = false;
};
const goToEmployees = async () => {
    closeConfirm();
    await nextTick();
    document.getElementById('employee-selection')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const confirmSubmit = () => {
    confirmSubmitting.value = true;
    form.post(route('hrm.payroll.generate.store'), {
        onSuccess: () => { showConfirm.value = false; },
        onFinish: () => { confirmSubmitting.value = false; },
    });
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
                    <p class="text-sm text-slate-500 mt-1.5">Set the cutoff dates and select employees to run payroll. Payroll starts as Draft.</p>
                </header>

                <div class="bg-white rounded-2xl border border-slate-200/60 shadow-lg backdrop-blur-sm overflow-hidden relative">
                    <div v-if="form.errors.error" class="m-5 mb-0 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold">{{ form.errors.error }}</div>
                    <div v-if="form.errors.period" class="m-5 mb-0 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold">{{ form.errors.period }}</div>
                    <div class="p-5 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1.5">Cutoff start</label>
                                <input type="date" v-model="form.cutoff_start" :max="props.current_date || undefined" required
                                    class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                <div v-if="form.errors.cutoff_start" class="text-xs text-rose-600 mt-1">{{ form.errors.cutoff_start }}</div>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1.5">Cutoff end</label>
                                <input type="date" v-model="form.cutoff_end" :min="form.cutoff_start || undefined" :max="props.current_date || undefined" required
                                    class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                <div v-if="form.errors.cutoff_end" class="text-xs text-rose-600 mt-1">{{ form.errors.cutoff_end }}</div>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">Choose the dates to include in this payroll run.</p>
                        <div id="employee-selection">
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
                        <button type="button" :disabled="form.processing || confirmSubmitting || activeMeta?.locked" @click="openConfirm"
                            class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-50">
                            <CheckCircle2 class="w-4 h-4" />{{ (form.processing || confirmSubmitting) ? 'Generating…' : 'Generate Payroll' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Confirmation Modal -->
    <div v-if="showConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="closeConfirm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-h-[80vh] overflow-hidden flex flex-col" :class="missingEmployees ? 'max-w-md' : 'max-w-3xl'">
            <div class="p-5 border-b border-slate-100 flex items-start justify-between">
                <div>
                    <h3 class="font-black text-slate-900">{{ missingEmployees ? 'No employees selected' : 'Confirm Payroll Generation' }}</h3>
                    <p v-if="!missingEmployees" class="text-xs text-slate-500 mt-0.5">{{ selectedEmployees.length }} employee(s) will be added to payroll for {{ form.period }}.</p>
                </div>
                <button @click="closeConfirm" class="p-2 hover:bg-slate-100 rounded-xl text-slate-400"><X class="w-4 h-4" /></button>
            </div>

            <div v-if="missingEmployees" class="p-5">
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 font-black">!</span>
                    <div>
                        <p class="text-sm font-semibold">Select at least one employee to generate payroll.</p>
                    </div>
                </div>
            </div>
            <div v-else class="p-5 overflow-y-auto flex-1">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                                <th class="py-2 px-3">Name</th>
                                <th class="py-2 px-3">Position</th>
                                <th class="py-2 px-3">Department</th>
                                <th class="py-2 px-3 text-right">Basic Salary</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="emp in selectedEmployees" :key="emp.id" class="hover:bg-slate-50/50">
                                <td class="py-2 px-3 font-medium">{{ emp.name }}</td>
                                <td class="py-2 px-3 text-slate-600">{{ emp.position }}</td>
                                <td class="py-2 px-3 text-slate-600">{{ emp.department || '—' }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ emp.basic_salary ? `₱${Number(emp.basic_salary).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                <button @click="missingEmployees ? goToEmployees() : closeConfirm()" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition">{{ missingEmployees ? 'Go to employee list' : 'Cancel' }}</button>
                <button v-if="!missingEmployees" @click="confirmSubmit" :disabled="confirmSubmitting"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition disabled:opacity-50">
                    <CheckCircle2 class="w-4 h-4" />{{ confirmSubmitting ? 'Generating…' : 'Confirm Generate' }}
                </button>
            </div>
        </div>
    </div>

    <div v-if="confirmSubmitting" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm" role="status" aria-live="polite" aria-label="Generating payroll">
        <div class="flex min-w-64 flex-col items-center rounded-2xl border border-white/20 bg-white px-8 py-7 text-center shadow-2xl">
            <div class="h-10 w-10 animate-spin rounded-full border-4 border-blue-100 border-t-blue-600"></div>
            <p class="mt-4 text-sm font-black text-slate-900">Generating payroll</p>
            <p class="mt-1 text-xs text-slate-500">Please wait while the selected employee records are processed.</p>
        </div>
    </div>
</template>

<style scoped>
.payroll-caps, .payroll-caps *, .payroll-caps input::placeholder { text-transform: capitalize; }
.bg-grid-slate-100 { background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px); background-size: 24px 24px; }
.bg-clip-text { -webkit-background-clip: text; background-clip: text; }
</style>
