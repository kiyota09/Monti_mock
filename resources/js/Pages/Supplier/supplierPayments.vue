<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReceiptsModal from '@/Components/ReceiptsModal.vue';
import {
    Wallet, Search, ReceiptText, FileText, CalendarClock,
    BadgeCheck, Building2, CheckCircle2, Hourglass, Tag,
} from 'lucide-vue-next';

const props = defineProps({
    auth: { type: Object, default: () => ({}) },
    invoices: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({ invoiced: 0, received: 0, outstanding: 0, paidCount: 0, openCount: 0 }),
    },
});

const supplierData = computed(() => props.auth?.supplier || props.auth?.user || {});
const localRows = ref(props.invoices.map(r => ({ ...r })));

const searchQuery = ref('');
const statusFilter = ref('all');
const pillKeys = ['all', 'paid', 'partial', 'unpaid', 'overdue'];

const peso = (v) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));

const fmtDate = (d) => {
    if (!d) return '—';
    const dt = new Date(d);
    return isNaN(dt) ? String(d) : dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const balanceOf = (r) => Number(r.amount ?? 0) - Number(r.paid ?? 0);

const counts = computed(() => {
    const c = { all: localRows.value.length, paid: 0, partial: 0, unpaid: 0, overdue: 0 };
    localRows.value.forEach(r => { if (c[r.status] !== undefined) c[r.status]++; });
    return c;
});

const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return localRows.value.filter(r => {
        if (statusFilter.value !== 'all' && r.status !== statusFilter.value) return false;
        if (!q) return true;
        return [r.bill_no, r.category, r.supplier]
            .filter(Boolean).some(v => String(v).toLowerCase().includes(q));
    });
});

const getStatusClass = (status) => {
    switch (status) {
        case 'paid':    return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'partial': return 'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30';
        case 'unpaid':  return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'overdue': return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
        default:        return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};

// --- Receipts / payment history (shared component) ---
const showReceipts = ref(false);
const receiptRow = ref(null);
const openReceipts = (row) => { receiptRow.value = row; showReceipts.value = true; };
const closeReceipts = () => { showReceipts.value = false; receiptRow.value = null; };
</script>

<template>
    <Head title="Payments | Supplier Hub" />
    <AuthenticatedLayout>
        <div class="min-h-screen">
            <div class="space-y-6 pb-10">

                <!-- Hero header -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-teal-700 to-slate-900 p-6 sm:p-8 text-white shadow-xl shadow-emerald-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-emerald-300/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg">
                            <Wallet class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-100">
                                <Building2 class="h-3.5 w-3.5" /> {{ supplierData.business_name || 'Supplier' }}
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Payments Received</h1>
                            <p class="text-sm text-emerald-100/90">Every payment Monti Textiles has made to you — with full receipts.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ peso(stats?.outstanding ?? 0) }} outstanding
                            </span>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search invoice no., PO, or status..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="key in pillKeys" :key="key" @click="statusFilter = key"
                                :class="statusFilter === key ? 'bg-white text-emerald-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ key }} · {{ counts[key] ?? 0 }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stat cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-600 to-slate-800 text-white shadow-lg">
                                <ReceiptText class="h-5 w-5" />
                            </span>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total invoiced</p>
                        </div>
                        <p class="mt-3 text-xl font-black tracking-tight text-slate-900 dark:text-white">{{ peso(stats?.invoiced ?? 0) }}</p>
                        <p class="text-[11px] font-bold text-slate-400">{{ localRows.length }} invoice{{ localRows.length !== 1 ? 's' : '' }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white shadow-lg">
                                <CheckCircle2 class="h-5 w-5" />
                            </span>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Received</p>
                        </div>
                        <p class="mt-3 text-xl font-black tracking-tight text-emerald-600 dark:text-emerald-400">{{ peso(stats?.received ?? 0) }}</p>
                        <p class="text-[11px] font-bold text-slate-400">{{ stats?.paidCount ?? 0 }} paid in full</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-lg">
                                <Hourglass class="h-5 w-5" />
                            </span>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Outstanding</p>
                        </div>
                        <p class="mt-3 text-xl font-black tracking-tight text-amber-600">{{ peso(stats?.outstanding ?? 0) }}</p>
                        <p class="text-[11px] font-bold text-slate-400">{{ stats?.openCount ?? 0 }} awaiting payment</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-lg">
                                <FileText class="h-5 w-5" />
                            </span>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Receipts</p>
                        </div>
                        <p class="mt-3 text-xl font-black tracking-tight text-slate-900 dark:text-white">{{ localRows.reduce((s, r) => s + (r.receipt_count ?? 0), 0) }}</p>
                        <p class="text-[11px] font-bold text-slate-400">payment records</p>
                    </div>
                </div>

                <!-- Invoices / payments table -->
                <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-3 p-5 pb-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white shadow-lg">
                            <Wallet class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-sm font-black tracking-tight text-slate-900 dark:text-white">Payment History</h2>
                            <p class="text-[11px] font-medium text-slate-400">Payments Monti Textiles has released against your invoices</p>
                        </div>
                    </div>

                    <div v-if="filtered.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="mb-4 rounded-full bg-gradient-to-br from-emerald-50 to-teal-50 p-5 dark:from-emerald-900/30">
                            <ReceiptText class="h-9 w-9 text-emerald-400" />
                        </div>
                        <p class="text-sm font-black text-slate-700 dark:text-slate-200">{{ localRows.length === 0 ? 'No payments yet' : 'No matches found' }}</p>
                        <p class="mt-1 text-xs text-slate-400">Try a different search or filter.</p>
                        <button v-if="searchQuery || statusFilter !== 'all'" @click="searchQuery=''; statusFilter='all'"
                            class="mt-4 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition active:scale-95">Clear filters</button>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[860px] text-sm">
                            <thead>
                                <tr class="border-y border-slate-100 bg-slate-50/60 text-left text-[10px] font-black uppercase tracking-widest text-slate-400 dark:border-slate-800 dark:bg-slate-950/40">
                                    <th class="px-5 py-3">Invoice No.</th>
                                    <th class="px-5 py-3">Reference</th>
                                    <th class="px-5 py-3 text-right">Amount</th>
                                    <th class="px-5 py-3 text-right">Received</th>
                                    <th class="px-5 py-3 text-right">Balance</th>
                                    <th class="px-5 py-3">Due Date</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in filtered" :key="r.id"
                                    class="border-b border-slate-50 transition-colors last:border-0 hover:bg-emerald-50/50 dark:border-slate-800/60 dark:hover:bg-emerald-950/20">
                                    <td class="whitespace-nowrap px-5 py-3.5 font-black text-slate-900 dark:text-white">{{ r.bill_no }}</td>
                                    <td class="px-5 py-3.5">
                                        <span v-if="r.category" class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">
                                            <Tag class="h-3 w-3" /> {{ r.category }}
                                        </span>
                                        <span v-else class="text-slate-300 dark:text-slate-600">—</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-bold text-slate-900 dark:text-white">{{ peso(r.amount) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-black text-emerald-600 dark:text-emerald-400">{{ peso(r.paid) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-black text-amber-600">{{ peso(balanceOf(r)) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                                            <CalendarClock class="h-3.5 w-3.5 text-emerald-400" /> {{ fmtDate(r.due_date) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span :class="getStatusClass(r.status)" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-black uppercase ring-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current" /> {{ r.status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end">
                                            <button @click="openReceipts(r)"
                                                class="relative inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-white px-3 py-1.5 text-[11px] font-black text-emerald-700 transition hover:bg-emerald-50 active:scale-95 dark:border-emerald-500/30 dark:bg-slate-900 dark:text-emerald-300 dark:hover:bg-emerald-500/10">
                                                <FileText class="h-3.5 w-3.5" /> Receipts
                                                <span v-if="r.receipt_count" class="ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-600 px-1 text-[9px] font-black text-white">{{ r.receipt_count }}</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals footer -->
                    <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4 dark:border-slate-800 dark:bg-slate-950/40 sm:flex-row sm:items-center sm:justify-end">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 dark:text-slate-400">
                            <ReceiptText class="h-4 w-4 text-slate-400" /> Total invoiced:
                            <span class="font-black text-slate-900 dark:text-white">{{ peso(stats?.invoiced ?? 0) }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 dark:text-slate-400">
                            <Wallet class="h-4 w-4 text-emerald-400" /> Received:
                            <span class="font-black text-emerald-600 dark:text-emerald-400">{{ peso(stats?.received ?? 0) }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-2xl bg-gradient-to-br from-emerald-600 via-teal-700 to-slate-900 px-4 py-2 text-xs font-black text-white shadow-lg shadow-emerald-500/20">
                            <BadgeCheck class="h-4 w-4" /> Outstanding: {{ peso(stats?.outstanding ?? 0) }}
                        </span>
                    </div>
                </div>

            </div>

            <!-- Shared receipts / payment history modal -->
            <ReceiptsModal :show="showReceipts" :row="receiptRow" @close="closeReceipts" />
        </div>
    </AuthenticatedLayout>
</template>
