<script setup lang="ts">
// Page 9: Create / Edit manpower request
import { computed, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps<{
    request: any | null;
    suggested_code: string;
    agencies: Array<{ id: number; code: string; name: string }>;
    departments: Array<{ id: number; code: string; name: string }>;
    positions: Array<{ id: number; name: string; department_id: number | null; approved_headcount?: number; filled_positions?: number; available_headcount?: number }>;
    shifts: Array<{ id: number; code: string; name: string; start_time: string | null; end_time: string | null }>;
}>();

type PositionOption = {
    id: number;
    name: string;
    department_id: number | null;
    approved_headcount?: number;
    filled_positions?: number;
    available_headcount?: number;
};

const isEdit = !!props.request;
const isDraft = !isEdit || props.request?.stored_status === 'Draft';
const today = (() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
})();

const form = useForm({
    code: props.request?.code || '',
    department: props.request?.department || '',
    position_skill: props.request?.position_skill || '',
    headcount: props.request?.needed ?? 1,
    start_date: props.request?.start_date || '',
    end_date: props.request?.end_date || '',
    shift: props.request?.shift || '',
    notes: props.request?.notes || '',
    agency_id: props.request?.agency_id || '',
});

function dateAfterDays(date: string, days: number): string {
    const result = new Date(`${date}T00:00:00`);
    result.setDate(result.getDate() + days);
    return `${result.getFullYear()}-${String(result.getMonth() + 1).padStart(2, '0')}-${String(result.getDate()).padStart(2, '0')}`;
}

watch(() => form.start_date, (startDate) => {
    if (startDate) form.end_date = dateAfterDays(startDate, 30);
});

const showSaveConfirm = ref(false);
const showDiscardConfirm = ref(false);
const saveIntent = ref(false);
const pendingNavigation = ref<string | URL | null>(null);
let bypassNavigationGuard = false;
const missingRequiredFields = computed(() => {
    const missing: string[] = [];
    if (!form.department) missing.push('Department');
    if (!form.position_skill) missing.push('Position / skill');
    if (!Number.isInteger(Number(form.headcount)) || Number(form.headcount) < 1) missing.push('Headcount needed');
    if (!form.agency_id) missing.push('Agency');
    return missing;
});
const hasFormInput = computed(() => isEdit
    ? form.isDirty
    : Boolean(form.code.trim() || form.department || form.position_skill || form.start_date || form.end_date || form.shift || form.notes.trim() || form.agency_id || Number(form.headcount) !== 1));

const removeNavigationGuard = router.on('before', (event: any) => {
    if (bypassNavigationGuard) {
        bypassNavigationGuard = false;
        return;
    }
    if (!hasFormInput.value || form.processing) return;

    event.preventDefault();
    pendingNavigation.value = event.detail?.visit?.url ?? route('hrm.manpower-requests.index');
    showDiscardConfirm.value = true;
});
onUnmounted(removeNavigationGuard);

function requestSave(submit: boolean) {
    saveIntent.value = submit;
    showSaveConfirm.value = true;
}

function confirmSave() {
    showSaveConfirm.value = false;
    save(saveIntent.value);
}

function requestCancel() {
    if (form.processing) return;
    pendingNavigation.value = isEdit ? route('hrm.manpower-requests.show', props.request.id) : route('hrm.manpower-requests.index');
    showDiscardConfirm.value = true;
}

function discardForm() {
    const destination = pendingNavigation.value || (isEdit ? route('hrm.manpower-requests.show', props.request.id) : route('hrm.manpower-requests.index'));
    showDiscardConfirm.value = false;
    pendingNavigation.value = null;
    bypassNavigationGuard = true;
    router.visit(destination);
}

// Department → positions connection (positions carry department_id).
const selectedDepartmentId = computed(() => {
    const found = (props.departments ?? []).find((d) => d.name === form.department);
    return found ? found.id : null;
});

// Keep legacy / custom values selectable (e.g. stored before the masters
// existed) by prepending the current value when it is not in the list.
const departmentOptions = computed(() => {
    const list = [...(props.departments ?? [])];
    if (form.department && !list.some((d) => d.name === form.department)) {
        list.unshift({ id: -1, code: '', name: form.department });
    }
    return list;
});

const filteredPositions = computed<PositionOption[]>(() => {
    // No department chosen → position list stays empty.
    if (!form.department) {
        return [];
    }
    // Legacy / custom department (not in masters) → keep current value so edit doesn't lose data.
    if (selectedDepartmentId.value === null) {
        if (form.position_skill) {
            return [{ id: -1, name: form.position_skill, department_id: null }];
        }
        return [];
    }
    const all = (props.positions ?? []).filter((p) => p.department_id === selectedDepartmentId.value);
    if (form.position_skill && !all.some((p) => p.name === form.position_skill)) {
        return [{ id: -1, name: form.position_skill, department_id: selectedDepartmentId.value }, ...all];
    }
    return all;
});

const selectedPosition = computed(() => filteredPositions.value.find((position) =>
    position.id !== -1 && position.name === form.position_skill,
) || null);
const availableHeadcount = computed(() => selectedPosition.value?.available_headcount ?? null);
const headcountLimit = computed(() => {
    if (!selectedPosition.value) return null;
    const available = selectedPosition.value.available_headcount ?? 0;
    const isCurrentPosition = isEdit
        && props.request?.department === form.department
        && props.request?.position_skill === form.position_skill;
    return isCurrentPosition ? Math.max(available, Number(props.request?.needed) || 0) : available;
});

const shiftOptions = computed(() => {
    const list = [...(props.shifts ?? [])];
    if (form.shift && !list.some((s) => s.name === form.shift)) {
        list.unshift({ id: -1, code: '', name: form.shift, start_time: null, end_time: null });
    }
    return list;
});

function shiftLabel(s: { name: string; start_time: string | null; end_time: string | null }): string {
    if (s.start_time && s.end_time) return `${s.name} (${s.start_time} – ${s.end_time})`;
    return s.name;
}

// Changing department clears the position when it no longer belongs to it.
// When department is emptied, position is always cleared.
watch(() => form.department, () => {
    if (!form.department) {
        form.position_skill = '';
        return;
    }
    const names = filteredPositions.value.map((p) => p.name);
    if (form.position_skill && !names.includes(form.position_skill)) {
        form.position_skill = '';
    }
});

watch(() => form.position_skill, () => {
    const limit = headcountLimit.value;
    if (!isEdit && limit !== null && limit > 0) {
        form.headcount = limit;
        return;
    }
    if (limit !== null && limit > 0 && Number(form.headcount) > limit) {
        form.headcount = limit;
    }
});

function clampHeadcountInput(event: Event) {
    const input = event.target as HTMLInputElement;
    const digits = input.value.replace(/\D/g, '');
    if (input.value !== digits) input.value = digits;
    (form as any).headcount = digits === '' ? '' : Number(digits);

    const limit = headcountLimit.value;
    const value = Number(digits);
    if (limit !== null && Number.isFinite(value) && value > limit) {
        form.headcount = limit;
        input.value = String(limit);
    }
}

function save(submit: boolean) {
    // `submit` is a reserved useForm method name, so it travels via
    // transform instead of living in the form data.
    form.transform((data: any) => ({ ...data, submit }));
    // The navigation guard protects leaving the form, but this visit saves it.
    bypassNavigationGuard = true;
    if (isEdit) {
        form.put(route('hrm.manpower-requests.update', props.request.id), { preserveScroll: true });
    } else {
        form.post(route('hrm.manpower-requests.store'), { preserveScroll: true });
    }
}

const inputCls = 'w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200';
const labelCls = 'block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase';
</script>

<template>
    <Head :title="(isEdit ? 'Edit Request' : 'New Request') + ' - Monti ERP'" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
                <Link :href="route('hrm.manpower-requests.index')" class="hover:text-blue-600">Requests</Link>
                <span>/</span>
                <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ isEdit ? 'Edit' : 'New' }} request</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                {{ isEdit ? 'Edit request' : 'New manpower request' }}
            </h1>
        </div>

        <form @submit.prevent="requestSave(false)" class="w-full space-y-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">Request details</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Department <span class="text-red-500">*</span></label>
                        <select v-model="form.department" :class="inputCls">
                            <option value="">Select department...</option>
                            <option v-for="d in departmentOptions" :key="d.id + '-' + d.name" :value="d.name" class="bg-white dark:bg-slate-800">{{ d.name }}</option>
                        </select>
                        <p v-if="form.errors.department" class="mt-1 text-xs text-rose-600">{{ form.errors.department }}</p>
                        <p v-else-if="departmentOptions.length === 0" class="mt-1 text-xs text-amber-600">No departments — add one under HRM → Departments first.</p>
                    </div>
                    <div>
                        <label :class="labelCls">Position / skill <span class="text-red-500">*</span></label>
                        <select v-model="form.position_skill" :class="inputCls" :disabled="!form.department">
                            <option value="">{{ !form.department ? 'Select department first...' : 'Select position...' }}</option>
                            <option v-for="p in filteredPositions" :key="p.id + '-' + p.name" :value="p.name" class="bg-white dark:bg-slate-800">{{ p.name }}</option>
                        </select>
                        <p v-if="form.errors.position_skill" class="mt-1 text-xs text-rose-600">{{ form.errors.position_skill }}</p>
                        <p v-else-if="!form.department" class="mt-1 text-xs text-slate-400">Select a department first to see its positions.</p>
                        <p v-else-if="form.department && filteredPositions.length === 0" class="mt-1 text-xs text-amber-600">No positions under this department — add one under HRM → Positions first.</p>
                    </div>
                    <div>
                        <label :class="labelCls">Headcount needed <span class="text-red-500">*</span></label>
                        <input v-model="form.headcount" type="text" inputmode="numeric" pattern="[0-9]*" :disabled="headcountLimit !== null && headcountLimit < 1" @input="clampHeadcountInput" :class="inputCls" />
                        <p v-if="form.errors.headcount" class="mt-1 text-xs text-rose-600">{{ form.errors.headcount }}</p>
                        <p v-else-if="selectedPosition" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ selectedPosition.filled_positions ?? 0 }}/{{ selectedPosition.approved_headcount ?? 0 }} filled · {{ availableHeadcount }} available for this request.
                        </p>
                        <p v-else class="mt-1 text-xs text-slate-400">Select a position to see its available headcount.</p>
                    </div>
                    <div>
                        <label :class="labelCls">Start date</label>
                        <input v-model="form.start_date" type="date" :min="today" :class="inputCls" />
                        <p v-if="form.errors.start_date" class="mt-1 text-xs text-rose-600">{{ form.errors.start_date }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">End date</label>
                        <input v-model="form.end_date" type="date" :min="form.start_date && form.start_date > today ? form.start_date : today" :class="inputCls" />
                        <p v-if="form.errors.end_date" class="mt-1 text-xs text-rose-600">{{ form.errors.end_date }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Shift</label>
                        <select v-model="form.shift" :class="inputCls">
                            <option value="">Select shift...</option>
                            <option v-for="s in shiftOptions" :key="s.id + '-' + s.name" :value="s.name" class="bg-white dark:bg-slate-800">{{ shiftLabel(s) }}</option>
                        </select>
                        <p v-if="form.errors.shift" class="mt-1 text-xs text-rose-600">{{ form.errors.shift }}</p>
                        <p v-else-if="shiftOptions.length === 0" class="mt-1 text-xs text-amber-600">No active shifts — add one under HRM → Shift first.</p>
                    </div>
                    <div>
                        <label :class="labelCls">Agency <span class="text-red-500">*</span></label>
                        <select v-model="form.agency_id" :class="inputCls">
                            <option value="">Select agency...</option>
                            <option v-for="a in agencies" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                        <p v-if="form.errors.agency_id" class="mt-1 text-xs text-rose-600">{{ form.errors.agency_id }}</p>
                        <p v-if="agencies.length === 0" class="mt-1 text-xs text-amber-600">No Active agencies — activate an agency first.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Notes <span class="font-normal normal-case text-slate-400">(Optional)</span></label>
                        <textarea v-model="form.notes" rows="2" :class="inputCls" placeholder="Shift details, requirements..."></textarea>
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pb-8">
                <button
                    type="button"
                    @click="requestCancel"
                    class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                >
                    Cancel
                </button>
                <template v-if="isDraft">
                    <button
                        type="button"
                        @click="requestSave(true)"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Submitting...' : 'Submit request' }}
                    </button>
                </template>
                <button
                    v-else
                    type="button"
                    @click="requestSave(false)"
                    :disabled="form.processing"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </form>

        <div v-if="showSaveConfirm" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="presentation" @click.self="showSaveConfirm = false">
            <section :class="['w-full max-w-md overflow-hidden rounded-2xl border bg-white shadow-2xl dark:bg-slate-900', saveIntent && missingRequiredFields.length ? 'border-amber-200 dark:border-amber-900/60' : 'border-slate-200 dark:border-slate-700']" role="alertdialog" aria-modal="true" aria-labelledby="request-save-title">
                <div v-if="saveIntent && missingRequiredFields.length" class="border-b border-amber-100 bg-amber-50/80 px-6 py-5 dark:border-amber-900/50 dark:bg-amber-950/30">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300">
                            <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                <path d="M12 8v4m0 4h.01M10.3 3.86 1.82 18.5A2 2 0 0 0 3.55 21h16.9a2 2 0 0 0 1.73-2.5L13.7 3.86a2 2 0 0 0-3.46 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">Request incomplete</p>
                            <h2 id="request-save-title" class="mt-1 text-lg font-black text-slate-900 dark:text-white">A few details need your attention</h2>
                            <p class="mt-1 text-sm leading-5 text-slate-600 dark:text-slate-300">Complete the required fields below before submitting.</p>
                        </div>
                    </div>
                </div>
                <div v-else class="px-6 pt-6">
                    <h2 id="request-save-title" class="text-lg font-black text-slate-900 dark:text-white">{{ saveIntent ? 'Submit this request?' : isDraft ? 'Save as draft?' : 'Save request changes?' }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ saveIntent ? 'The request will be submitted to the selected agency.' : isDraft ? 'Save the request details as a draft?' : 'Save your changes to this manpower request?' }}</p>
                </div>
                <div v-if="saveIntent && missingRequiredFields.length" class="px-6 pt-5">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Required fields</span>
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-extrabold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">{{ missingRequiredFields.length }} missing</span>
                    </div>
                    <ul class="space-y-2">
                        <li v-for="(field, index) in missingRequiredFields" :key="field" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-white text-xs font-black text-amber-700 shadow-sm dark:bg-slate-700 dark:text-amber-300">{{ index + 1 }}</span>
                            {{ field }}
                        </li>
                    </ul>
                </div>
                <div class="flex justify-end gap-2 px-6 py-5">
                    <button type="button" @click="showSaveConfirm = false" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">{{ saveIntent && missingRequiredFields.length ? 'Back to form' : 'Review' }}</button>
                    <button v-if="!saveIntent || !missingRequiredFields.length" type="button" @click="confirmSave" :disabled="form.processing" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-blue-500/20 transition hover:bg-blue-700 disabled:opacity-50">{{ saveIntent ? 'Submit request' : isDraft ? 'Save draft' : 'Save changes' }}</button>
                </div>
            </section>
        </div>

        <div v-if="showDiscardConfirm" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="presentation" @click.self="showDiscardConfirm = false">
            <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900" role="alertdialog" aria-modal="true" aria-labelledby="request-discard-title">
                <h2 id="request-discard-title" class="text-lg font-black text-slate-900 dark:text-white">Leave this request form?</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Any details you entered will be lost if you leave without saving.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="showDiscardConfirm = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Keep editing</button>
                    <button type="button" @click="discardForm" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700">Discard</button>
                </div>
            </section>
        </div>

        <div v-if="form.processing" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm" role="status" aria-live="polite" aria-label="Saving manpower request">
            <div class="w-full max-w-sm rounded-2xl border border-white/20 bg-white p-7 text-center shadow-2xl dark:bg-slate-900">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/60">
                    <span class="h-8 w-8 animate-spin rounded-full border-[3px] border-blue-600/20 border-t-blue-600 dark:border-blue-300/20 dark:border-t-blue-300"></span>
                </span>
                <h2 class="mt-5 text-lg font-black text-slate-900 dark:text-white">{{ saveIntent ? 'Submitting request' : 'Saving changes' }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ saveIntent ? 'Sending the manpower request to the agency. Please wait…' : 'Updating the manpower request. Please wait…' }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
