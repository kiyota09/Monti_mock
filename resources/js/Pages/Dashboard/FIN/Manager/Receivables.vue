<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReceiptsModal from '@/Components/ReceiptsModal.vue';
import {
    HandCoins, Search, Sparkles, ReceiptText, Wallet,
    CalendarClock, Banknote, Hourglass, BadgeCheck,
} from 'lucide-vue-next';

const props = defineProps({
    receivables: { type: Array, default: () => [] },
    arAging: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    gateway: { type: Object, default: () => ({ mode: 'simulated', methods: {} }) },
    isDummy: { type: Boolean, default: false },
});

// Live rows from props. Collections now arrive from the partner portal's
// PayMongo checkout (and any legacy ledger postings), so finance monitors the
// ledger and reviews each invoice's receipts here.
const localRows = ref(props.receivables.map(r => ({ ...r })));

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
        return [r.invoice_no, r.client]
            .filter(Boolean).some(v => String(v).toLowerCase().includes(q));
    });
});

const totalBilled = computed(() => localRows.value.reduce((s, r) => s + Number(r.amount ?? 0), 0));
const totalCollected = computed(() => localRows.value.reduce((s, r) => s + Number(r.paid ?? 0), 0));
const totalOutstanding = computed(() => totalBilled.value - totalCollected.value);

const getStatusClass = (status) => {
    switch (status) {
        case 'paid':    return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'partial': return 'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30';
        case 'unpaid':  return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'overdue': return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
        default:        return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};

const agingGradients = [
    'from-sky-500 via-blue-600 to-indigo-700',
    'from-amber-500 via-orange-600 to-rose-600',
    'from-rose-500 via-red-600 to-pink-700',
    'from-violet-500 via-purple-600 to-fuchsia-700',
];

// --- Receipts / payment history: every collection recorded against an
// invoice, rendered as online-receipt cards so finance can monitor them. ---
const showReceipts = ref(false);
const receiptRow = ref(null);
const openReceipts = (row) => { receiptRow.value = row; showReceipts.value = true; };
const closeReceipts = () => { showReceipts.value = false; receiptRow.value = null; };
</script>

<template>
    <Head title="Receivables" />
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
                            <HandCoins class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> FIN · Manager · Receivables
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Accounts Receivable</h1>
                            <p class="text-sm text-blue-100/90">{{ filtered.length }} of {{ localRows.length }} invoice{{ localRows.length !== 1 ? 's' : '' }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ peso(stats?.outstanding ?? totalOutstanding) }} outstanding
                            </span>
                            <span v-if="isDummy" class="rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">Dummy data</span>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search invoice no. or client..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="key in pillKeys" :key="key" @click="statusFilter = key"
                                :class="statusFilter === key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ key }} · {{ counts[key] ?? 0 }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Aging summary cards -->
                <div v-if="arAging.length" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div v-for="(a, i) in arAging" :key="a.bucket"
                        :style="{ animationDelay: `${i * 80}ms` }"
                        class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-center gap-3">
                            <div :class="['flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', agingGradients[i % agingGradients.length]]">
                                <Hourglass class="h-5 w-5" />
                            </div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ a.bucket }}</p>
                        </div>
                        <p class="mt-3 text-xl font-black tracking-tight text-gray-900 dark:text-white">{{ peso(a.amount) }}</p>
                        <p class="text-[11px] font-bold text-gray-400">{{ a.count }} invoice{{ a.count !== 1 ? 's' : '' }}</p>
                    </div>
                </div>

                <!-- Invoice table card -->
                <div class="animate-fade-up rounded-3xl bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 120ms">
                    <div class="flex items-center gap-3 p-5 pb-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg">
                            <ReceiptText class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Invoices</h2>
                            <p class="text-[11px] text-gray-400 font-medium">Live ledger — saved to the database</p>
                        </div>
                    </div>

                    <div v-if="filtered.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <ReceiptText class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">{{ localRows.length === 0 ? 'No receivables yet' : 'No matches found' }}</p>
                        <p class="text-xs text-gray-400 mt-1">Try a different search or filter.</p>
                        <button v-if="searchQuery || statusFilter !== 'all'" @click="searchQuery=''; statusFilter='all'"
                            class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[820px] text-sm">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 border-y border-gray-100 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-950/40">
                                    <th class="px-5 py-3">Invoice No.</th>
                                    <th class="px-5 py-3">Client</th>
                                    <th class="px-5 py-3 text-right">Amount</th>
                                    <th class="px-5 py-3 text-right">Paid</th>
                                    <th class="px-5 py-3 text-right">Balance</th>
                                    <th class="px-5 py-3">Due Date</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Receipts</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody">
                                <tr v-for="(r, i) in filtered" :key="r.id"
                                    :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                    class="group border-b border-gray-50 dark:border-zinc-800/60 last:border-0 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-5 py-3.5 font-black text-gray-900 dark:text-white whitespace-nowrap">{{ r.invoice_no }}</td>
                                    <td class="px-5 py-3.5 text-gray-500 dark:text-gray-400 font-medium max-w-[220px] truncate">{{ r.client }}</td>
                                    <td class="px-5 py-3.5 text-right font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ peso(r.amount) }}</td>
                                    <td class="px-5 py-3.5 text-right text-emerald-600 dark:text-emerald-400 font-bold whitespace-nowrap">{{ peso(r.paid) }}</td>
                                    <td class="px-5 py-3.5 text-right font-black text-indigo-700 dark:text-indigo-300 whitespace-nowrap">{{ peso(balanceOf(r)) }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                                            <CalendarClock class="h-3.5 w-3.5 text-indigo-400" /> {{ fmtDate(r.due_date) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span :class="getStatusClass(r.status)" class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 whitespace-nowrap">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ r.status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <button @click="openReceipts(r)"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black text-white hover:bg-indigo-700 transition active:scale-95">
                                            <ReceiptText class="h-3.5 w-3.5" /> Receipt
                                            <span v-if="r.receipt_count" class="rounded-full bg-white/25 px-1.5 text-[10px] font-black">{{ r.receipt_count }}</span>
                                        </button>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                    </div>

                    <!-- Totals footer -->
                    <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-end px-5 py-4 border-t border-gray-100 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-950/40">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-gray-400">
                            <Banknote class="h-4 w-4 text-indigo-400" /> Total billed:
                            <span class="font-black text-gray-900 dark:text-white">{{ peso(totalBilled) }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-gray-400">
                            <Wallet class="h-4 w-4 text-emerald-400" /> Collected:
                            <span class="font-black text-emerald-600 dark:text-emerald-400">{{ peso(totalCollected) }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-2xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-4 py-2 text-xs font-black text-white shadow-lg shadow-indigo-500/20">
                            <BadgeCheck class="h-4 w-4" /> Outstanding: {{ peso(totalOutstanding) }}
                        </span>
                    </div>
                </div>

            </div>

            <!-- Payment history: every collection recorded against the invoice -->
            <ReceiptsModal
                :show="showReceipts"
                :row="receiptRow"
                title="Receivable Receipts"
                subtitle="Client payment history"
                @close="closeReceipts"
            />
        </div>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(12px) scale(0.98); }
</style>
