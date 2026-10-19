<template>
    <div class="space-y-5">
        <!-- Summary -->
        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">This month</p>
                <p class="mt-1 text-2xl font-black text-gray-900 dark:text-white">{{ money(earnings.month_total) }}</p>
                <p class="text-[11px] text-gray-400">Estimated payout</p>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Pending clearance</p>
                <p class="mt-1 text-2xl font-black text-amber-600 dark:text-amber-300">{{ money(earnings.pending_total) }}</p>
                <p class="text-[11px] text-gray-400">Released after 7 days</p>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Lifetime</p>
                <p class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-300">{{ money(earnings.lifetime_total) }}</p>
                <p class="text-[11px] text-gray-400">All completed trips</p>
            </div>
        </section>

        <!-- Trip payouts -->
        <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Trip payouts</h3>
                <span class="text-[10px] font-bold text-gray-400">Base + mileage + stop/rating bonuses</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:bg-zinc-800/60">
                        <tr>
                            <th class="px-4 py-3">Trip</th>
                            <th class="px-4 py-3">Base</th>
                            <th class="px-4 py-3">Mileage</th>
                            <th class="px-4 py-3">Stops</th>
                            <th class="px-4 py-3">Bonus</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                        <tr v-for="row in earnings.rows || []" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-zinc-800/40">
                            <td class="px-4 py-3">
                                <p class="font-black text-gray-900 dark:text-white">{{ row.delivery_number }}</p>
                                <p class="text-[10px] text-gray-400">{{ formatDate(row.completed_at) }} · {{ Number(row.distance_km).toFixed(1) }} km</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ money(row.base) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ money(row.mileage) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ money(row.stop_bonus) }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ money(row.rating_bonus) }}</td>
                            <td class="px-4 py-3 text-right font-black text-gray-900 dark:text-white">{{ money(row.total) }}</td>
                            <td class="px-4 py-3">
                                <span :class="row.status === 'settled' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'"
                                    class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">{{ row.status }}</span>
                            </td>
                        </tr>
                        <tr v-if="!(earnings.rows || []).length">
                            <td colspan="7" class="px-4 py-10 text-center text-xs font-bold text-gray-400">No completed trips to pay out yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-5">
            <!-- Expense submission -->
            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-2">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Submit expense</h3>
                <form @submit.prevent="submitExpense" class="mt-3 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Type</label>
                            <select v-model="expenseForm.type" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                <option value="fuel">Fuel</option>
                                <option value="toll">Toll</option>
                                <option value="scale">Scale</option>
                                <option value="repair">Repair</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Amount</label>
                            <input v-model="expenseForm.amount" type="number" step="0.01" min="0" required
                                class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Date</label>
                            <input v-model="expenseForm.spent_at" type="date" required
                                class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Trip</label>
                            <select v-model="expenseForm.delivery_id" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                <option value="">None</option>
                                <option v-for="delivery in history" :key="delivery.id" :value="delivery.id">{{ delivery.delivery_number }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Notes</label>
                        <input v-model="expenseForm.notes" type="text" placeholder="Optional"
                            class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Receipt</label>
                        <input type="file" accept="image/*,application/pdf" @change="onReceipt" class="w-full text-xs text-gray-500 dark:text-gray-400" />
                    </div>
                    <button type="submit" :disabled="expenseForm.processing"
                        class="w-full rounded-2xl bg-indigo-600 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ expenseForm.processing ? 'Submitting…' : 'Submit for reimbursement' }}
                    </button>
                </form>
            </section>

            <!-- Expense list -->
            <section class="rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-3">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">My expenses</h3>
                    <span class="text-[10px] font-bold text-gray-400">Pending {{ money(expenseTotals.pending) }}</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                    <div v-for="expense in expenses" :key="expense.id" class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="text-xs font-black capitalize text-gray-900 dark:text-white">{{ expense.type }} <span class="font-normal text-gray-400">· {{ formatDate(expense.spent_at) }}</span></p>
                            <p class="truncate text-[11px] text-gray-500">{{ expense.notes || expense.delivery?.delivery_number || '—' }}</p>
                            <a v-if="expense.receipt_path" :href="`/storage/${expense.receipt_path}`" target="_blank" class="text-[10px] font-black uppercase text-indigo-500">Receipt</a>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-black text-gray-900 dark:text-white">{{ money(expense.amount) }}</p>
                            <span :class="expenseStatus(expense.status)" class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">{{ expense.status }}</span>
                        </div>
                    </div>
                    <div v-if="!expenses.length" class="px-5 py-10 text-center text-xs font-bold text-gray-400">No expenses submitted.</div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    earnings: { type: Object, default: () => ({ rows: [], month_total: 0, pending_total: 0, lifetime_total: 0 }) },
    expenses: { type: Array, default: () => [] },
    expenseTotals: { type: Object, default: () => ({ pending: 0, approved: 0 }) },
    history: { type: Array, default: () => [] },
    notify: { type: Function, required: true },
});

const today = new Date().toISOString().slice(0, 10);

const expenseForm = useForm({
    type: 'fuel',
    amount: '',
    delivery_id: '',
    notes: '',
    spent_at: today,
    receipt: null,
});

const onReceipt = (event) => {
    expenseForm.receipt = event.target.files[0] || null;
};

const submitExpense = () => {
    expenseForm.post(route('logistics.driver.expense.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            props.notify('success', 'Expense submitted.');
            expenseForm.reset('amount', 'notes', 'receipt');
        },
        onError: (errors) => props.notify('error', Object.values(errors)[0] || 'Could not submit expense.'),
    });
};

const money = (value) => {
    const amount = Number(value) || 0;
    return `₱${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

const expenseStatus = (status) => ({
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
}[status] || 'bg-gray-100 text-gray-500');
</script>
