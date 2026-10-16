<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Wallet, Search, Sparkles, ReceiptText, Tags, X } from 'lucide-vue-next';

const props = defineProps({
    expenses: { type: Array, default: () => [] },
    expenseCategories: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    isDummy: { type: Boolean, default: false },
});

const searchQuery = ref('');
const categoryFilter = ref('all');

// --- Record-expense modal (live ledger post) ---
const showExpenseModal = ref(false);
const expenseForm = useForm({
    expense_date: new Date().toISOString().split('T')[0],
    category: 'Utilities',
    description: '',
    amount: '',
    department: '',
});
const openExpenseModal = () => {
    expenseForm.reset();
    expenseForm.expense_date = new Date().toISOString().split('T')[0];
    expenseForm.clearErrors();
    showExpenseModal.value = true;
};
const submitExpense = () => {
    expenseForm.post(route('fin.manager.expenses.store'), {
        preserveScroll: true,
        onSuccess: () => { showExpenseModal.value = false; },
    });
};

const peso = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));

const grandTotal = computed(() => props.expenses.reduce((s, e) => s + Number(e.amount ?? 0), 0));

const categoryTotals = computed(() => {
    if (props.expenseCategories?.length) return props.expenseCategories;
    const map = {};
    props.expenses.forEach((e) => { map[e.category] = (map[e.category] ?? 0) + Number(e.amount ?? 0); });
    return Object.entries(map).map(([category, amount]) => ({ category, amount }));
});

const maxCategory = computed(() => Math.max(1, ...categoryTotals.value.map((c) => Number(c.amount ?? 0))));

const shareOf = (amount) => {
    const t = grandTotal.value || 1;
    return Math.min(100, Math.round((Number(amount ?? 0) / t) * 100));
};

const filteredExpenses = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return props.expenses.filter((e) => {
        if (categoryFilter.value !== 'all' && e.category !== categoryFilter.value) return false;
        if (!q) return true;
        return [e.description, e.category, e.department, e.date].filter(Boolean).some((v) => String(v).toLowerCase().includes(q));
    });
});

const filteredTotal = computed(() => filteredExpenses.value.reduce((s, e) => s + Number(e.amount ?? 0), 0));

const categoryChip = (category) => {
    const map = {
        'Raw materials': 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        'Payroll': 'bg-violet-100 text-violet-700 ring-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/30',
        'Utilities': 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        'Dye chemicals': 'bg-fuchsia-100 text-fuchsia-700 ring-fuchsia-200 dark:bg-fuchsia-500/15 dark:text-fuchsia-300 dark:ring-fuchsia-500/30',
        'Logistics': 'bg-cyan-100 text-cyan-700 ring-cyan-200 dark:bg-cyan-500/15 dark:text-cyan-300 dark:ring-cyan-500/30',
        'Maintenance': 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
    };
    return map[category] ?? 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};
</script>

<template>
    <Head title="Expenses" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Wallet class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Finance · Expenses
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Expense Tracking</h1>
                            <p class="text-sm text-blue-100/90">{{ filteredExpenses.length }} of {{ expenses.length }} record{{ expenses.length !== 1 ? 's' : '' }} showing · {{ peso(filteredTotal) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ peso(grandTotal) }} total
                            </span>
                            <span v-if="isDummy" class="rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">Demo data</span>
                        </div>
                    </div>

                    <!-- Search + category pills -->
                    <div class="relative mt-6 flex flex-col gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search description, category, department..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button @click="categoryFilter = 'all'"
                                :class="categoryFilter === 'all' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                All · {{ expenses.length }}
                            </button>
                            <button v-for="c in categoryTotals" :key="c.category" @click="categoryFilter = categoryFilter === c.category ? 'all' : c.category"
                                :class="categoryFilter === c.category ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ c.category }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Category cards with share-of-total bars -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="(c, i) in categoryTotals" :key="c.category"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                                    <Tags class="h-5 w-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-black text-gray-900 dark:text-white truncate tracking-tight">{{ c.category }}</p>
                                    <p class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase">{{ shareOf(c.amount) }}% of total</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30 flex items-center gap-1 flex-shrink-0">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> live
                            </span>
                        </div>
                        <p class="text-xl font-black text-gray-900 dark:text-white tracking-tight">{{ peso(c.amount) }}</p>
                        <div class="mt-3 h-2.5 w-full rounded-full bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 transition-all duration-500"
                                :style="{ width: `${Math.max(4, (Number(c.amount) / maxCategory) * 100)}%` }" />
                        </div>
                        <p class="mt-2 text-[11px] font-medium text-gray-400 dark:text-gray-500">{{ peso(c.amount) }} of {{ peso(grandTotal) }}</p>
                    </div>
                </TransitionGroup>

                <!-- Expense table -->
                <div class="animate-fade-up relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm">
                    <div class="flex items-center gap-3 p-5 pb-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-violet-700 text-white shadow-lg">
                            <ReceiptText class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-sm font-black text-gray-900 dark:text-white tracking-tight">Expense records</h2>
                            <p class="text-[11px] text-gray-400 font-medium">Date · category · department · amount</p>
                        </div>
                    </div>
                    <div v-if="filteredExpenses.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Wallet class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No expenses found</p>
                        <p class="text-xs text-gray-400 mt-1">Try a different search or category.</p>
                        <button v-if="searchQuery || categoryFilter !== 'all'" @click="searchQuery=''; categoryFilter='all'"
                            class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
                    </div>
                    <div v-else class="overflow-x-auto p-3">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3">Description</th>
                                    <th class="px-4 py-3">Department</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody">
                                <tr v-for="e in filteredExpenses" :key="e.id"
                                    class="group border-t border-gray-100 dark:border-zinc-800 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/30 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap text-xs font-bold text-gray-500 dark:text-gray-400">{{ e.date }}</td>
                                    <td class="px-4 py-3"><span :class="categoryChip(e.category)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 whitespace-nowrap">{{ e.category }}</span></td>
                                    <td class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-100">{{ e.description }}</td>
                                    <td class="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400">{{ e.department }}</td>
                                    <td class="px-4 py-3 text-right font-black text-gray-900 dark:text-white whitespace-nowrap">{{ peso(e.amount) }}</td>
                                </tr>
                            </TransitionGroup>
                        </table>
                    </div>
                    <!-- Month total footer -->
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-5 py-4 text-white">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">Month total · {{ filteredExpenses.length }} record{{ filteredExpenses.length !== 1 ? 's' : '' }}</p>
                        <div class="flex items-center gap-3">
                            <p class="text-xl font-black tracking-tight">{{ peso(filteredTotal) }}</p>
                            <button @click="openExpenseModal" class="rounded-xl bg-white px-4 py-2 text-xs font-black text-indigo-700 hover:bg-indigo-50 transition active:scale-95">+ Record expense</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Record-expense modal (live ledger post) -->
        <Transition name="modal">
            <div v-if="showExpenseModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="showExpenseModal = false" />
                <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl">
                    <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">New expense</p>
                                <h3 class="text-lg font-black tracking-tight">Record expense</h3>
                            </div>
                            <button @click="showExpenseModal = false" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition active:scale-95">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="text-[11px] font-black uppercase tracking-widest text-gray-400">Date *</span>
                                <input v-model="expenseForm.expense_date" type="date"
                                    class="mt-1.5 w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition" />
                                <p v-if="expenseForm.errors.expense_date" class="text-[11px] font-bold text-rose-600 mt-1">{{ expenseForm.errors.expense_date }}</p>
                            </label>
                            <label class="block">
                                <span class="text-[11px] font-black uppercase tracking-widest text-gray-400">Amount (₱) *</span>
                                <input v-model="expenseForm.amount" type="number" min="0.01" step="0.01" placeholder="0.00"
                                    class="mt-1.5 w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition" />
                                <p v-if="expenseForm.errors.amount" class="text-[11px] font-bold text-rose-600 mt-1">{{ expenseForm.errors.amount }}</p>
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="text-[11px] font-black uppercase tracking-widest text-gray-400">Category *</span>
                                <select v-model="expenseForm.category"
                                    class="mt-1.5 w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                    <option>Raw materials</option>
                                    <option>Payroll</option>
                                    <option>Utilities</option>
                                    <option>Dye chemicals</option>
                                    <option>Logistics</option>
                                    <option>Maintenance</option>
                                    <option>Rent</option>
                                    <option>Other</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-[11px] font-black uppercase tracking-widest text-gray-400">Department</span>
                                <input v-model="expenseForm.department" type="text" placeholder="e.g. Dyeing"
                                    class="mt-1.5 w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition" />
                            </label>
                        </div>
                        <label class="block">
                            <span class="text-[11px] font-black uppercase tracking-widest text-gray-400">Description *</span>
                            <input v-model="expenseForm.description" type="text" placeholder="e.g. Electricity — Meralco"
                                class="mt-1.5 w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition" />
                            <p v-if="expenseForm.errors.description" class="text-[11px] font-bold text-rose-600 mt-1">{{ expenseForm.errors.description }}</p>
                        </label>
                        <div class="flex gap-2">
                            <button @click="showExpenseModal = false" class="flex-1 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-zinc-700 transition active:scale-95">Cancel</button>
                            <button @click="submitExpense" :disabled="expenseForm.processing" class="flex-1 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 shadow-lg shadow-indigo-500/20 hover:opacity-95 transition active:scale-95 disabled:opacity-50">{{ expenseForm.processing ? 'Saving…' : 'Save expense' }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
</style>
