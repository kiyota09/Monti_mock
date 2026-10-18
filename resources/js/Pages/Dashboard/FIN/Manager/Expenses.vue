<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Wallet, Search, Sparkles, ReceiptText, Tags, X, Calendar, Eye } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    expenses: { type: Array, default: () => [] },
    expenseCategories: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    isDummy: { type: Boolean, default: false },
});

const searchQuery = ref('');
const categoryFilter = ref('all');

// --- Review State ---
const showReviewModal = ref(false);
const selectedPayroll = ref(null);
const isLoadingReview = ref(false);
const dateHistory = computed(() => {
    const dates = props.expenses.map(e => e.expense_date).filter(Boolean);
    return [...new Set(dates)].sort((a, b) => new Date(b) - new Date(a));
});
const activeDateFilter = ref(null);

const openReview = async (expense) => {
    if (!expense.payroll_id) {
        alert('This expense is not linked to a payroll record.');
        return;
    }
    isLoadingReview.value = true;
    try {
        const res = await axios.get(`/manager/expenses/review/${expense.id}`);
        selectedPayroll.value = res.data;
        showReviewModal.value = true;
    } catch (e) {
        alert('Failed to load payroll details.');
    } finally {
        isLoadingReview.value = false;
    }
};

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
        if (activeDateFilter.value && e.expense_date !== activeDateFilter.value) return false;
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
                            <button @click="categoryFilter = 'all'; activeDateFilter = null"
                                :class="categoryFilter === 'all' && !activeDateFilter ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
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

                <!-- Date History and Summary Cards -->
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- Date History Sidebar -->
                    <div class="lg:col-span-1 space-y-4">
                        <div class="bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 p-5 shadow-sm">
                            <div class="flex items-center gap-2 mb-4">
                                <Calendar class="h-4 w-4 text-indigo-600" />
                                <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Date History</h3>
                            </div>
                            <div class="flex flex-col gap-2 max-h-[400px] overflow-y-auto pr-2">
                                <button v-for="date in dateHistory" :key="date" @click="activeDateFilter = activeDateFilter === date ? null : date"
                                    :class="activeDateFilter === date ? 'bg-indigo-600 text-white shadow-md scale-[1.02]' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700'"
                                    class="text-left px-3 py-2 rounded-xl text-xs font-bold transition-all duration-200">
                                    {{ date }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Category Summary Cards -->
                    <div class="lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <TransitionGroup name="card" tag="div" class="contents">
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
                    </div>
                </div>

                <!-- Expense table -->
                <div class="animate-fade-up relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm">
                    <div class="flex items-center gap-3 p-5 pb-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-violet-700 text-white shadow-lg">
                            <ReceiptText class="h-5 w-5" />
                        </div>
                        <div class="flex-1">
                            <h2 class="text-sm font-black text-gray-900 dark:text-white tracking-tight">Expense records</h2>
                            <p class="text-[11px] text-gray-400 font-medium">Date · category · department · amount</p>
                        </div>
                        <div v-if="activeDateFilter" class="flex items-center gap-2 bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1 rounded-full border border-indigo-100 dark:border-indigo-800">
                            <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase">Filtered by: {{ activeDateFilter }}</span>
                            <button @click="activeDateFilter = null" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-300">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                    <div v-if="filteredExpenses.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Wallet class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No expenses found</p>
                        <p class="text-xs text-gray-400 mt-1">Try a different search or category.</p>
                        <button v-if="searchQuery || categoryFilter !== 'all' || activeDateFilter" @click="searchQuery=''; categoryFilter='all'; activeDateFilter=null"
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
                                    <th class="px-4 py-3 text-center">Review</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody">
                                <tr v-for="e in filteredExpenses" :key="e.id"
                                    @click="openReview(e)"
                                    class="group cursor-pointer border-t border-gray-100 dark:border-zinc-800 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/30 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap text-xs font-bold text-gray-500 dark:text-gray-400">{{ e.expense_date }}</td>
                                    <td class="px-4 py-3"><span :class="categoryChip(e.category)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 whitespace-nowrap">{{ e.category }}</span></td>
                                    <td class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-100">{{ e.description }}</td>
                                    <td class="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400">{{ e.department }}</td>
                                    <td class="px-4 py-3 text-right font-black text-gray-900 dark:text-white whitespace-nowrap">{{ peso(e.amount) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex justify-center">
                                            <Eye class="h-4 w-4 text-indigo-400 group-hover:text-indigo-600 transition-colors" />
                                        </div>
                                    </td>
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

        <!-- Payroll Review Modal -->
        <Transition name="modal">
            <div v-if="showReviewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="showReviewModal = false" />
                <div class="relative w-full max-w-2xl overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl">
                    <div class="bg-gradient-to-br from-indigo-700 to-violet-800 p-5 text-white">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-2xl bg-white/15 flex items-center justify-center">
                                    <ReceiptText class="h-5 w-5" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-lg font-black tracking-tight">Payslip Review</h3>
                                    <p class="text-xs text-indigo-100 font-medium truncate">{{ selectedPayroll?.payroll?.employee_name }} · {{ selectedPayroll?.payroll?.period }}</p>
                                </div>
                            </div>
                            <button @click="showReviewModal = false" class="h-9 w-9 flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/20 transition">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div class="p-6 space-y-6 max-h-[80vh] overflow-y-auto">
                        <!-- Summary Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700">
                                <p class="text-[10px] font-black uppercase text-gray-400">Days Present</p>
                                <p class="text-lg font-black text-gray-900 dark:text-white">{{ selectedPayroll?.payroll?.days_worked }} day{{ selectedPayroll?.payroll?.days_worked !== 1 ? 's' : '' }}</p>
                            </div>
                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700">
                                <p class="text-[10px] font-black uppercase text-gray-400">Scheduled</p>
                                <p class="text-lg font-black text-gray-900 dark:text-white">{{ selectedPayroll?.payroll?.scheduled_shifts || 0 }}</p>
                            </div>
                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700">
                                <p class="text-[10px] font-black uppercase text-gray-400">Absent</p>
                                <p class="text-lg font-black text-gray-900 dark:text-white">{{ selectedPayroll?.payroll?.absent_days || 0 }}</p>
                            </div>
                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700">
                                <p class="text-[10px] font-black uppercase text-gray-400">On Leave</p>
                                <p class="text-lg font-black text-gray-900 dark:text-white">{{ selectedPayroll?.payroll?.leave_days || 0 }}</p>
                            </div>
                        </div>

                        <!-- Detail Table -->
                        <div class="rounded-2xl border border-gray-100 dark:border-zinc-700 overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-zinc-800 text-left text-[10px] font-black uppercase text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3">Field</th>
                                        <th class="px-4 py-3 text-right">Value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-zinc-700">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Days worked (payroll)</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ selectedPayroll?.payroll?.days_worked }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Daily rate</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.daily_rate) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Basic total</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.total_days_amt) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Night hours</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ selectedPayroll?.payroll?.night_hours || '—' }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Night rate</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.night_rate) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Night amount</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.night_amt) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">O.T. hours</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ selectedPayroll?.payroll?.overtime_hours || '—' }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">O.T. rate</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.ot_rate) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">O.T. amount</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.ot_amt) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Sun / special holiday hours</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ selectedPayroll?.payroll?.sunday_restday_hours || '—' }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Sun / special holiday rate</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.sun_sp_rate) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Sun / special holiday amount</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.sun_sp_amt) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Holiday</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.holiday_amt) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Late (min)</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ selectedPayroll?.payroll?.late_minutes || '—' }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Late rate/min</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.late_rate_min) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Late total</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.late_total_deduction) }}</td>
                                    </tr>
                                    <tr class="bg-indigo-50 dark:bg-indigo-900/20">
                                        <td class="px-4 py-3 font-black text-indigo-600 dark:text-indigo-400">Gross</td>
                                        <td class="px-4 py-3 text-right font-black text-indigo-600 dark:text-indigo-400">{{ peso(selectedPayroll?.payroll?.gross_pay) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">SSS</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.sss_deduction) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">PhilHealth</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.philhealth_deduction) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Pag-IBIG</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.pagibig_deduction) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">SSS loan</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.sss_loan) }}</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-800">
                                        <td class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">PF loan</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">{{ peso(selectedPayroll?.payroll?.pf_loan) }}</td>
                                    </tr>
                                    <tr class="bg-indigo-600 dark:bg-indigo-700 text-white">
                                        <td class="px-4 py-3 font-black">Net</td>
                                        <td class="px-4 py-3 text-right font-black">{{ peso(selectedPayroll?.payroll?.net_pay) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { from { opacity: 0; transform: translateY(0); } to { opacity: 1; transform: translateY(-14px); } }
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
