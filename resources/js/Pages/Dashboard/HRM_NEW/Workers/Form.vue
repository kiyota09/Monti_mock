<script setup lang="ts">
// Page 6: Add / Edit worker
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps<{
    worker: any | null;
    suggested_code: string;
    agencies: Array<{ id: number; code: string; name: string }>;
}>();

const isEdit = !!props.worker;

const form = useForm({
    name: props.worker?.name || '',
    code: props.worker?.code || '',
    agency_id: props.worker?.agency_id || '',
    birth_date: props.worker?.birth_date || '',
    gender: props.worker?.gender || '',
    phone: props.worker?.phone || '',
    email: props.worker?.email || '',
    address: props.worker?.address || '',
    resume: null as File | null,
    education: props.worker?.education || '',
    work_experience: props.worker?.work_experience || '',
    skills: props.worker?.skills || '',
    trainings_certifications: props.worker?.trainings_certifications || '',
    position_skill: props.worker?.position_skill || '',
    years_experience: props.worker?.years_experience ?? '',
    proposed_department: props.worker?.proposed_department || '',
    availability: props.worker?.availability || '',
    daily_rate: props.worker?.daily_rate ?? '',
    employment_arrangement: props.worker?.employment_arrangement || '',
    gov_id_type: props.worker?.gov_id_type || '',
    gov_id_number: props.worker?.gov_id_number || '',
    clearance_expiry: props.worker?.clearance_expiry || '',
    emergency_name: props.worker?.emergency_name || '',
    emergency_relationship: props.worker?.emergency_relationship || '',
    emergency_phone: props.worker?.emergency_phone || '',
});

function onResume(e: Event) {
    const files = (e.target as HTMLInputElement).files;
    (form as any).resume = files && files[0] ? files[0] : null;
}

const AVAILABILITY_OPTIONS = [
    'Immediately available',
    'Available on a specific date',
    'Currently employed — serving notice period',
    'Not currently available',
];
const availabilityDate = ref('');

(function initAvailability() {
    const v: string = (form as any).availability || '';
    const dm = v.match(/^Available on a specific date(?:\s*\((\d{4}-\d{2}-\d{2})\))?/);
    if (dm) {
        (form as any).availability = 'Available on a specific date';
        availabilityDate.value = dm[1] || '';
    }
})();

const isLegacyAvailability = () =>
    !!form.availability && !AVAILABILITY_OPTIONS.includes(form.availability);

function submit() {
    if (form.availability === 'Available on a specific date' && availabilityDate.value) {
        (form as any).availability = `Available on a specific date (${availabilityDate.value})`;
    }
    const opts = { preserveScroll: true, forceFormData: true } as any;
    if (isEdit) {
        form.transform((data: any) => ({ ...data, _method: 'put' })).post(route('hrm.workers.update', props.worker.id), opts);
    } else {
        form.post(route('hrm.workers.store'), opts);
    }
}

const inputCls = 'w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200';
const labelCls = 'block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase';
</script>

<template>
    <Head :title="(isEdit ? 'Edit Worker' : 'Add Worker') + ' - Monti ERP'" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
                <Link :href="route('hrm.workers.index')" class="hover:text-blue-600">Workers</Link>
                <span>/</span>
                <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ isEdit ? 'Edit' : 'Add' }} worker</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                {{ isEdit ? 'Edit worker' : 'Add worker' }}
            </h1>
            <p v-if="!isEdit" class="text-slate-500 dark:text-slate-400 text-sm mt-1">A new worker starts as Available.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4 max-w-4xl">
            <!-- Personal -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">Personal</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Full name <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" :class="inputCls" placeholder="Full name" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Birth date</label>
                        <input v-model="form.birth_date" type="date" :class="inputCls" />
                        <p v-if="form.errors.birth_date" class="mt-1 text-xs text-rose-600">{{ form.errors.birth_date }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Gender</label>
                        <select v-model="form.gender" :class="inputCls">
                            <option value="">Select...</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                        <p v-if="form.errors.gender" class="mt-1 text-xs text-rose-600">{{ form.errors.gender }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Phone</label>
                        <input v-model="form.phone" type="text" :class="inputCls" placeholder="Phone number" />
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Email</label>
                        <input v-model="form.email" type="email" :class="inputCls" placeholder="Email address" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Resume / CV (PDF or DOCX)</label>
                        <input type="file" accept=".pdf,.doc,.docx" :class="inputCls" @change="onResume" />
                        <p v-if="props.worker?.resume_url" class="mt-1 text-[11px]"><a :href="props.worker.resume_url" target="_blank" class="text-blue-600 hover:underline font-semibold">View current resume</a></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Education</label>
                        <textarea v-model="form.education" rows="2" :class="inputCls"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Work experience</label>
                        <textarea v-model="form.work_experience" rows="2" :class="inputCls"></textarea>
                    </div>
                    <div>
                        <label :class="labelCls">Skills</label>
                        <textarea v-model="form.skills" rows="2" :class="inputCls"></textarea>
                    </div>
                    <div>
                        <label :class="labelCls">Training / certifications</label>
                        <textarea v-model="form.trainings_certifications" rows="2" :class="inputCls"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Address</label>
                        <textarea v-model="form.address" rows="2" :class="inputCls" placeholder="Home address"></textarea>
                        <p v-if="form.errors.address" class="mt-1 text-xs text-rose-600">{{ form.errors.address }}</p>
                    </div>
                </div>
            </div>

            <!-- Agency -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">Agency</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Agency <span class="text-red-500">*</span></label>
                        <select v-model="form.agency_id" :class="inputCls">
                            <option value="">Select agency...</option>
                            <option v-for="a in agencies" :key="a.id" :value="a.id">{{ a.name }} ({{ a.code }})</option>
                        </select>
                        <p v-if="form.errors.agency_id" class="mt-1 text-xs text-rose-600">{{ form.errors.agency_id }}</p>
                        <p v-if="agencies.length === 0" class="mt-1 text-xs text-amber-600">No Active agencies — activate an agency first.</p>
                    </div>
                    <div>
                        <label :class="labelCls">Position / skill</label>
                        <input v-model="form.position_skill" type="text" :class="inputCls" placeholder="e.g. Sewer, Packer" />
                        <p v-if="form.errors.position_skill" class="mt-1 text-xs text-rose-600">{{ form.errors.position_skill }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Years of experience</label>
                        <input v-model="form.years_experience" type="number" min="0" step="0.5" :class="inputCls" />
                    </div>
                    <div>
                        <label :class="labelCls">Proposed department</label>
                        <input v-model="form.proposed_department" type="text" :class="inputCls" />
                    </div>
                    <div>
                        <label :class="labelCls">Availability</label>
                        <select v-model="form.availability" :class="inputCls">
                            <option value="">Select...</option>
                            <option v-for="o in AVAILABILITY_OPTIONS" :key="o" :value="o">{{ o }}</option>
                            <option v-if="isLegacyAvailability()" :value="form.availability">{{ form.availability }}</option>
                        </select>
                        <input v-if="form.availability === 'Available on a specific date'" v-model="availabilityDate" type="date" :class="inputCls + ' mt-2'" />
                    </div>
                    <div>
                        <label :class="labelCls">Employment arrangement</label>
                        <select v-model="form.employment_arrangement" :class="inputCls">
                            <option value="">Select...</option>
                            <option>Full-time</option><option>Part-time</option>
                            <option>Contractual</option><option>Project-based</option><option>Seasonal</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelCls">Daily rate (₱)</label>
                        <input v-model="form.daily_rate" type="number" min="0" step="0.01" :class="inputCls" placeholder="e.g. 650" />
                        <p v-if="form.errors.daily_rate" class="mt-1 text-xs text-rose-600">{{ form.errors.daily_rate }}</p>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">Documents</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Government ID type</label>
                        <input v-model="form.gov_id_type" type="text" :class="inputCls" placeholder="SSS / PhilHealth / ..." />
                    </div>
                    <div>
                        <label :class="labelCls">Government ID number</label>
                        <input v-model="form.gov_id_number" type="text" :class="inputCls" placeholder="SSS / PhilHealth / Pag-IBIG no." />
                        <p v-if="form.errors.gov_id_number" class="mt-1 text-xs text-rose-600">{{ form.errors.gov_id_number }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Clearance / medical expiry</label>
                        <input v-model="form.clearance_expiry" type="date" :class="inputCls" />
                        <p v-if="form.errors.clearance_expiry" class="mt-1 text-xs text-rose-600">{{ form.errors.clearance_expiry }}</p>
                    </div>
                </div>
            </div>

            <!-- Emergency contact -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">Emergency contact</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Name</label>
                        <input v-model="form.emergency_name" type="text" :class="inputCls" placeholder="Contact name" />
                        <p v-if="form.errors.emergency_name" class="mt-1 text-xs text-rose-600">{{ form.errors.emergency_name }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Relationship</label>
                        <input v-model="form.emergency_relationship" type="text" :class="inputCls" placeholder="e.g. Spouse" />
                    </div>
                    <div>
                        <label :class="labelCls">Phone</label>
                        <input v-model="form.emergency_phone" type="text" :class="inputCls" placeholder="Contact phone" />
                        <p v-if="form.errors.emergency_phone" class="mt-1 text-xs text-rose-600">{{ form.errors.emergency_phone }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pb-8">
                <Link
                    :href="isEdit ? route('hrm.workers.show', worker.id) : route('hrm.workers.index')"
                    class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving...' : 'Save worker' }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
