<script setup lang="ts">
// Page 2: Add / Edit agency (one form, 4 sections)
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps<{
    agency: any | null;
    suggested_code: string;
}>();

const isEdit = !!props.agency;

const form = useForm({
    name: props.agency?.name || '',
    code: props.agency?.code || '',
    tin: props.agency?.tin || '',
    address: props.agency?.address || '',
    dole_number: props.agency?.dole_number || '',
    dole_expiry: props.agency?.dole_expiry || '',
    contract_start: props.agency?.contract_start || '',
    contract_end: props.agency?.contract_end || '',
    service_fee_type: props.agency?.service_fee_type || 'percentage',
    service_fee_value: props.agency?.service_fee_value ?? '',
    contact_name: props.agency?.contact_name || '',
    contact_email: props.agency?.contact_email || '',
    contact_phone: props.agency?.contact_phone || '',
});

function submit() {
    if (isEdit) {
        form.put(route('hrm.agencies.update', props.agency.id), { preserveScroll: true });
    } else {
        form.post(route('hrm.agencies.store'), { preserveScroll: true });
    }
}

const inputCls = 'w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200';
const labelCls = 'block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase';
</script>

<template>
    <Head :title="(isEdit ? 'Edit Agency' : 'Add Agency') + ' - Monti ERP'" />
    <AuthenticatedLayout>
        <div class="mb-6">
            <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
                <Link :href="route('hrm.agencies.index')" class="hover:text-blue-600">Agencies</Link>
                <span>/</span>
                <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ isEdit ? 'Edit' : 'Add' }} agency</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                {{ isEdit ? 'Edit agency' : 'Add agency' }}
            </h1>
            <p v-if="!isEdit" class="text-slate-500 dark:text-slate-400 text-sm mt-1">A new agency starts as Active.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4 max-w-4xl">
            <!-- Section 1: Company -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">Company</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Agency name <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" :class="inputCls" placeholder="e.g. Prime Manpower Services" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">TIN</label>
                        <input v-model="form.tin" type="text" :class="inputCls" placeholder="Tax identification number" />
                        <p v-if="form.errors.tin" class="mt-1 text-xs text-rose-600">{{ form.errors.tin }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Address</label>
                        <textarea v-model="form.address" rows="2" :class="inputCls" placeholder="Office address"></textarea>
                        <p v-if="form.errors.address" class="mt-1 text-xs text-rose-600">{{ form.errors.address }}</p>
                    </div>
                </div>
            </div>

            <!-- Section 2: DOLE registration -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">DOLE registration</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Registration number</label>
                        <input v-model="form.dole_number" type="text" :class="inputCls" placeholder="DOLE registration no." />
                        <p v-if="form.errors.dole_number" class="mt-1 text-xs text-rose-600">{{ form.errors.dole_number }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Expiry date</label>
                        <input v-model="form.dole_expiry" type="date" :class="inputCls" />
                        <p v-if="form.errors.dole_expiry" class="mt-1 text-xs text-rose-600">{{ form.errors.dole_expiry }}</p>
                    </div>
                </div>
            </div>

            <!-- Section 3: Contract -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">Contract</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Start date</label>
                        <input v-model="form.contract_start" type="date" :class="inputCls" />
                        <p v-if="form.errors.contract_start" class="mt-1 text-xs text-rose-600">{{ form.errors.contract_start }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">End date</label>
                        <input v-model="form.contract_end" type="date" :class="inputCls" />
                        <p v-if="form.errors.contract_end" class="mt-1 text-xs text-rose-600">{{ form.errors.contract_end }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Service fee type <span class="text-red-500">*</span></label>
                        <select v-model="form.service_fee_type" :class="inputCls">
                            <option value="percentage">Percentage of labor cost</option>
                            <option value="fixed">Fixed amount</option>
                        </select>
                        <p v-if="form.errors.service_fee_type" class="mt-1 text-xs text-rose-600">{{ form.errors.service_fee_type }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Service fee value <span class="text-red-500">*</span></label>
                        <input v-model="form.service_fee_value" type="number" min="0" step="0.01" :class="inputCls" placeholder="e.g. 10 or 5000" />
                        <p v-if="form.errors.service_fee_value" class="mt-1 text-xs text-rose-600">{{ form.errors.service_fee_value }}</p>
                    </div>
                </div>
            </div>

            <!-- Section 4: Contact person -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">Contact person</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Name</label>
                        <input v-model="form.contact_name" type="text" :class="inputCls" placeholder="Full name" />
                        <p v-if="form.errors.contact_name" class="mt-1 text-xs text-rose-600">{{ form.errors.contact_name }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Phone</label>
                        <input v-model="form.contact_phone" type="text" :class="inputCls" placeholder="Phone number" />
                        <p v-if="form.errors.contact_phone" class="mt-1 text-xs text-rose-600">{{ form.errors.contact_phone }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Email</label>
                        <input v-model="form.contact_email" type="email" :class="inputCls" placeholder="contact@agency.com" />
                        <p v-if="form.errors.contact_email" class="mt-1 text-xs text-rose-600">{{ form.errors.contact_email }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pb-8">
                <Link
                    :href="isEdit ? route('hrm.agencies.show', agency.id) : route('hrm.agencies.index')"
                    class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving...' : 'Save agency' }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
