<script setup lang="ts">
// Page 9: Create / Edit manpower request
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps<{
    request: any | null;
    suggested_code: string;
    agencies: Array<{ id: number; code: string; name: string }>;
    departments: Array<{ id: number; code: string; name: string }>;
    positions: Array<{ id: number; name: string; department_id: number | null }>;
    shifts: Array<{ id: number; code: string; name: string; start_time: string | null; end_time: string | null }>;
}>();

const isEdit = !!props.request;
const isDraft = !isEdit || props.request?.stored_status === 'Draft';

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

const filteredPositions = computed(() => {
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

function save(submit: boolean) {
    // `submit` is a reserved useForm method name, so it travels via
    // transform instead of living in the form data.
    form.transform((data: any) => ({ ...data, submit }));
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

        <form @submit.prevent="save(false)" class="space-y-4 max-w-4xl">
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
                        <input v-model="form.headcount" type="number" min="1" :class="inputCls" />
                        <p v-if="form.errors.headcount" class="mt-1 text-xs text-rose-600">{{ form.errors.headcount }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Start date</label>
                        <input v-model="form.start_date" type="date" :class="inputCls" />
                        <p v-if="form.errors.start_date" class="mt-1 text-xs text-rose-600">{{ form.errors.start_date }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">End date</label>
                        <input v-model="form.end_date" type="date" :class="inputCls" />
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
                            <option v-for="a in agencies" :key="a.id" :value="a.id">{{ a.name }} ({{ a.code }})</option>
                        </select>
                        <p v-if="form.errors.agency_id" class="mt-1 text-xs text-rose-600">{{ form.errors.agency_id }}</p>
                        <p v-if="agencies.length === 0" class="mt-1 text-xs text-amber-600">No Active agencies — activate an agency first.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Notes</label>
                        <textarea v-model="form.notes" rows="2" :class="inputCls" placeholder="Shift details, requirements..."></textarea>
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pb-8">
                <Link
                    :href="isEdit ? route('hrm.manpower-requests.show', request.id) : route('hrm.manpower-requests.index')"
                    class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                >
                    Cancel
                </Link>
                <template v-if="isDraft">
                    <button
                        type="button"
                        @click="save(false)"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition disabled:opacity-60"
                    >
                        {{ form.processing ? 'Saving...' : 'Save as draft' }}
                    </button>
                    <button
                        type="button"
                        @click="save(true)"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Submitting...' : 'Submit request' }}
                    </button>
                </template>
                <button
                    v-else
                    type="button"
                    @click="save(false)"
                    :disabled="form.processing"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
