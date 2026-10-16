<template>
    <Head title="Payroll Rates" />
    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto space-y-10 p-4 lg:p-10">

            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-indigo-600 font-black text-[10px] uppercase tracking-[0.2em]">
                        <DollarSign class="h-3.5 w-3.5" />
                        Compensation Setup
                    </div>
                    <h1 class="text-4xl font-black text-gray-900 dark:text-white tracking-tighter uppercase">
                        Payroll <span class="text-indigo-600">Rates</span>
                    </h1>
                    <p class="text-sm font-medium text-gray-500 italic">
                        Configure daily rates, overtime premiums, night differential, and statutory contributions.
                    </p>
                </div>
                <button @click="refreshData" class="p-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    <RefreshCw class="h-4 w-4 text-gray-500" />
                </button>
            </div>

            <!-- Stats / Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Daily Rate (₱)</p>
                    <p class="text-3xl font-black text-indigo-600 mt-1">{{ formatMoney(rates.daily_rate) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">OT Rate</p>
                    <p class="text-3xl font-black text-amber-600 mt-1">{{ rates.overtime_rate }}%</p>
                </div>
                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Night Diff</p>
                    <p class="text-3xl font-black text-emerald-600 mt-1">{{ rates.night_diff_rate }}%</p>
                </div>
                <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Holiday Premium</p>
                    <p class="text-3xl font-black text-purple-600 mt-1">{{ rates.holiday_rate }}%</p>
                </div>
            </div>

            <!-- Main Form -->
            <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Edit Payroll Rates</h2>
                    <p class="text-xs text-gray-500">Changes will apply to future payroll calculations.</p>
                </div>

                <form @submit.prevent="saveRates" class="p-8 space-y-8">
                    <!-- Base Daily Rate -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Daily Rate (₱) *</label>
                            <input type="number" v-model.number="form.daily_rate" step="0.01" min="0" required
                                class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-400 mt-1">Base salary per day (e.g., 537.00 for minimum wage).</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Daily Rate (USD) – Optional</label>
                            <input type="number" v-model.number="form.daily_rate_usd" step="0.01" min="0"
                                class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-400 mt-1">Only needed if you run international payroll.</p>
                        </div>
                    </div>

                    <!-- Overtime & Night Differential -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Overtime Rate (%) *</label>
                            <input type="number" v-model.number="form.overtime_rate" min="0" max="300" required
                                class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-400 mt-1">Percentage of daily rate per hour. Standard: 125%.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Night Differential Rate (%)</label>
                            <input type="number" v-model.number="form.night_diff_rate" min="0" max="200"
                                class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-400 mt-1">Extra pay for hours between 10 PM – 6 AM. Standard: 10%.</p>
                        </div>
                    </div>

                    <!-- Holiday & Rest Day Premiums -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Regular Holiday Rate (%)</label>
                            <input type="number" v-model.number="form.holiday_rate" min="0" max="300"
                                class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-400 mt-1">Standard: 200% of daily rate.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Special Non‑Working Holiday Rate (%)</label>
                            <input type="number" v-model.number="form.special_holiday_rate" min="0" max="300"
                                class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-400 mt-1">Standard: 130% if worked, 30% extra if unworked but paid.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Rest Day / Sunday Premium (%)</label>
                            <input type="number" v-model.number="form.rest_day_rate" min="0" max="300"
                                class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-400 mt-1">Standard: 130% of daily rate.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Late Deduction Rate (per minute, ₱)</label>
                            <input type="number" v-model.number="form.late_deduction_per_minute" step="0.01" min="0"
                                class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                            <p class="text-xs text-gray-400 mt-1">Amount deducted for each minute of lateness.</p>
                        </div>
                    </div>

                    <!-- Statutory Contributions (optional) -->
                    <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
                        <h3 class="text-md font-black text-gray-800 dark:text-gray-200 mb-4">Statutory Contributions</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">SSS Contribution (%)</label>
                                <input type="number" v-model.number="form.sss_rate" step="0.01" min="0" max="100"
                                    class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm">
                                <p class="text-[9px] text-gray-400">Employee share percentage.</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">PhilHealth (%)</label>
                                <input type="number" v-model.number="form.philhealth_rate" step="0.01" min="0" max="100"
                                    class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Pag‑IBIG (%)</label>
                                <input type="number" v-model.number="form.pagibig_rate" step="0.01" min="0" max="100"
                                    class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <button type="button" @click="resetForm"
                            class="flex-1 px-4 py-3 border border-gray-200 dark:border-gray-700 rounded-xl text-[10px] font-black uppercase hover:bg-gray-50 transition">
                            Reset
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 px-4 py-3 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase hover:bg-indigo-700 transition disabled:opacity-50 flex items-center justify-center gap-2">
                            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <Save v-else class="h-4 w-4" />
                            {{ form.processing ? 'Saving...' : 'Save Rates' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Toast Notification -->
            <Transition name="toast">
                <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-xl shadow-lg text-white font-bold text-sm"
                    :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                    {{ toast.message }}
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { DollarSign, RefreshCw, Save, Loader2 } from 'lucide-vue-next';

const props = defineProps({
    rates: {
        type: Object,
        default: () => ({
            daily_rate: 537.00,
            daily_rate_usd: null,
            overtime_rate: 125,
            night_diff_rate: 10,
            holiday_rate: 200,
            special_holiday_rate: 130,
            rest_day_rate: 130,
            late_deduction_per_minute: 1.50,
            sss_rate: 4.5,
            philhealth_rate: 3.0,
            pagibig_rate: 2.0,
        })
    }
});

const toast = ref({ show: false, type: 'success', message: '' });

const form = useForm({
    daily_rate: props.rates.daily_rate,
    daily_rate_usd: props.rates.daily_rate_usd,
    overtime_rate: props.rates.overtime_rate,
    night_diff_rate: props.rates.night_diff_rate,
    holiday_rate: props.rates.holiday_rate,
    special_holiday_rate: props.rates.special_holiday_rate,
    rest_day_rate: props.rates.rest_day_rate,
    late_deduction_per_minute: props.rates.late_deduction_per_minute,
    sss_rate: props.rates.sss_rate,
    philhealth_rate: props.rates.philhealth_rate,
    pagibig_rate: props.rates.pagibig_rate,
});

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const refreshData = () => {
    router.reload({ only: ['rates'] });
};

const resetForm = () => {
    form.daily_rate = props.rates.daily_rate;
    form.daily_rate_usd = props.rates.daily_rate_usd;
    form.overtime_rate = props.rates.overtime_rate;
    form.night_diff_rate = props.rates.night_diff_rate;
    form.holiday_rate = props.rates.holiday_rate;
    form.special_holiday_rate = props.rates.special_holiday_rate;
    form.rest_day_rate = props.rates.rest_day_rate;
    form.late_deduction_per_minute = props.rates.late_deduction_per_minute;
    form.sss_rate = props.rates.sss_rate;
    form.philhealth_rate = props.rates.philhealth_rate;
    form.pagibig_rate = props.rates.pagibig_rate;
};

const saveRates = () => {
    form.post(route('hrm.payroll.rates.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', 'Payroll rates updated successfully.');
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Failed to update rates.';
            showToast('error', errorMsg);
        }
    });
};

const formatMoney = (value) => {
    if (value === null || value === undefined) return '0.00';
    return parseFloat(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<style scoped>
.toast-enter-active, .toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from, .toast-leave-to {
    opacity: 0;
    transform: translateY(20px);
}
</style>