<script setup lang="ts">
// Page 2: Add / Edit agency (one form, 6 sections)
import { computed, watch } from 'vue';
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
    billing_cycle: props.agency?.billing_cycle || 'Monthly',
    payment_terms: props.agency?.payment_terms || '15 days after invoice receipt',
    payment_custom_terms: props.agency?.payment_custom_terms || '',
    service_fee_type: props.agency?.service_fee_type || 'percentage',
    service_fee_value: props.agency?.service_fee_value ?? '',
    ot_multiplier: props.agency?.ot_multiplier ?? '1.25',
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

// Percentage vs fixed presentation for the service-fee value.
const isPercentage = computed(() => form.service_fee_type === 'percentage');

// Numbers only: strip letters / spaces / special chars, keep one dot, max 2 decimals.
function sanitizeFee(raw: string): string {
    let v = (raw ?? '').replace(/[^0-9.]/g, '');
    const firstDot = v.indexOf('.');
    if (firstDot !== -1) {
        v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
    }
    const [intPart, decPart] = v.split('.');
    if (decPart !== undefined && decPart.length > 2) {
        v = intPart + '.' + decPart.slice(0, 2);
    }
    // Drop leading zeros like "007" -> "7", but keep "0." / "0".
    if (v.length > 1 && v[0] === '0' && v[1] !== '.') {
        v = v.replace(/^0+/, '') || '0';
    }
    return v;
}

function onFeeInput(e: Event) {
    const el = e.target as HTMLInputElement;
    const clean = sanitizeFee(el.value);
    if (el.value !== clean) el.value = clean;
    form.service_fee_value = clean as any;
}

// Block e/E/+/-/letters at keypress level (number-only field).
function onFeeKeydown(e: KeyboardEvent) {
    const allowed = ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
    if (allowed.includes(e.key) || e.ctrlKey || e.metaKey) return;
    const isDigit = /^[0-9]$/.test(e.key);
    if (e.key === '.') {
        if (String(form.service_fee_value ?? '').includes('.')) e.preventDefault();
        return;
    }
    if (!isDigit) e.preventDefault();
}

function onFeePaste(e: ClipboardEvent) {
    e.preventDefault();
    const text = e.clipboardData?.getData('text') ?? '';
    form.service_fee_value = sanitizeFee(text) as any;
}

// Switching to percentage clamps anything above 100.
watch(() => form.service_fee_type, (t) => {
    if (t === 'percentage') {
        const n = Number(form.service_fee_value);
        if (form.service_fee_value !== '' && !Number.isNaN(n) && n > 100) {
            form.service_fee_value = '100' as any;
        }
    }
});

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
                </div>
            </div>

            <!-- Section 4: Payment Terms (after Contract, before Service Fee) -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">Payment Terms</h2>
                <div class="space-y-4">
                    <div>
                        <label :class="labelCls">Billing cycle <span class="text-red-500">*</span></label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="opt in ['Weekly', 'Semi-monthly', 'Monthly', 'Per invoice / As agreed']"
                                :key="opt"
                                type="button"
                                @click="form.billing_cycle = opt"
                                :class="['px-4 py-2 rounded-xl text-xs font-bold border transition', form.billing_cycle === opt ? 'bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-500/30' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-blue-400']"
                            >
                                {{ opt }}
                            </button>
                        </div>
                        <p v-if="form.errors.billing_cycle" class="mt-1 text-xs text-rose-600">{{ form.errors.billing_cycle }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Payment terms <span class="text-red-500">*</span></label>
                        <div class="space-y-2">
                            <label
                                v-for="opt in ['Due upon invoice approval', '7 days after invoice receipt', '15 days after invoice receipt', '30 days after invoice receipt', 'Custom terms']"
                                :key="opt"
                                class="flex items-center gap-3 px-4 py-2.5 rounded-xl border cursor-pointer transition text-sm"
                                :class="form.payment_terms === opt ? 'border-blue-600 bg-blue-50/60 dark:bg-blue-900/10 font-bold text-slate-800 dark:text-slate-100' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-blue-300'"
                            >
                                <input v-model="form.payment_terms" type="radio" :value="opt" class="accent-blue-600" />
                                {{ opt }}
                            </label>
                        </div>
                        <p v-if="form.errors.payment_terms" class="mt-1 text-xs text-rose-600">{{ form.errors.payment_terms }}</p>
                    </div>
                    <div v-if="form.payment_terms === 'Custom terms'">
                        <label :class="labelCls">Custom terms <span class="text-red-500">*</span></label>
                        <input v-model="form.payment_custom_terms" type="text" :class="inputCls" placeholder="e.g. 45 days after invoice receipt, 50% upon approval..." />
                        <p v-if="form.errors.payment_custom_terms" class="mt-1 text-xs text-rose-600">{{ form.errors.payment_custom_terms }}</p>
                    </div>
                </div>
            </div>

            <!-- Section 5: Service Fee -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">Service Fee</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        <div class="relative">
                            <span v-if="!isPercentage" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400">₱</span>
                            <input :value="form.service_fee_value" @input="onFeeInput" @keydown="onFeeKeydown" @paste="onFeePaste"
                                type="text" inputmode="decimal" autocomplete="off"
                                :placeholder="isPercentage ? 'e.g. 10 (%)' : 'e.g. 5000 (₱)'"
                                :class="[inputCls, isPercentage ? 'pr-9' : 'pl-8']" />
                            <span v-if="isPercentage" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400">%</span>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">{{ isPercentage ? 'Enter 0 – 100 (% of labor cost).' : 'Enter a fixed peso amount.' }}</p>
                        <p v-if="form.errors.service_fee_value" class="mt-1 text-xs text-rose-600">{{ form.errors.service_fee_value }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Overtime multiplier <span class="text-red-500">*</span></label>
                        <input v-model="form.ot_multiplier" type="number" min="1" max="5" step="0.05" :class="inputCls" placeholder="1.25" />
                        <p class="mt-1 text-[11px] text-slate-400">Contract OT rate: overtime is billed at (daily rate ÷ 8) × this. 1.25 = 125% of the regular hourly rate.</p>
                        <p v-if="form.errors.ot_multiplier" class="mt-1 text-xs text-rose-600">{{ form.errors.ot_multiplier }}</p>
                    </div>
                </div>
            </div>

            <!-- Section 6: Contact person -->
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
