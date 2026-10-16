<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Navigation, Package, Truck, Search, X, CheckCircle,
    TriangleAlert, Wallet, ClipboardList,
} from 'lucide-vue-next';

const props = defineProps({
    purchaseOrders: { type: Array, default: () => [] },
});

const peso = (v) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));

const fmtDate = (d) => {
    if (!d) return '—';
    const dt = new Date(d);
    return isNaN(dt) ? String(d) : dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const searchQuery = ref('');
const statusFilter = ref('all');

const statusKeys = computed(() => {
    const keys = [...new Set((props.purchaseOrders || []).map((o) => o.status))];
    return ['all', ...keys];
});

const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return (props.purchaseOrders || []).filter((o) => {
        if (statusFilter.value !== 'all' && o.status !== statusFilter.value) return false;
        if (!q) return true;
        return [o.po_number, o.supplier_name]
            .filter(Boolean).some((v) => String(v).toLowerCase().includes(q));
    });
});

const statusBadge = (status) => {
    const map = {
        draft: 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700',
        sent: 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        production: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        shipping: 'bg-violet-100 text-violet-700 ring-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/30',
        delivered: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        completed: 'bg-green-100 text-green-700 ring-green-200 dark:bg-green-500/15 dark:text-green-300 dark:ring-green-500/30',
    };
    return map[status] || 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const finBadge = (s) => {
    switch (s) {
        case 'approved': return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'declined': return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
        default: return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
    }
};

// ── Tracking modal ─────────────────────────────────────────────
const tracked = ref(null);
const openTracking = (order) => { tracked.value = order; };
const closeTracking = () => { tracked.value = null; };

const STEPS = [
    { key: 'draft', label: 'Draft created' },
    { key: 'sent', label: 'Sent to supplier' },
    { key: 'production', label: 'In production' },
    { key: 'shipping', label: 'Shipping' },
    { key: 'delivered', label: 'Delivered' },
    { key: 'completed', label: 'Completed' },
];
const stepState = (order, key) => {
    const orderIdx = STEPS.findIndex((s) => s.key === order.status);
    const idx = STEPS.findIndex((s) => s.key === key);
    if (orderIdx === -1) return 'todo';
    if (idx < orderIdx) return 'done';
    if (idx === orderIdx) return 'current';
    return 'todo';
};
const stepDate = (order, key) => {
    if (key === 'draft') return order.created_at;
    if (idxOf(key) === idxOf(order.status)) return order.updated_at;
    return null;
};
const idxOf = (key) => STEPS.findIndex((s) => s.key === key);

const invoicedTotal = (order) => (order.invoices || []).reduce((s, i) => s + Number(i.amount ?? 0), 0);
const paidTotal = (order) => (order.invoices || []).reduce((s, i) => s + Number(i.paid ?? 0), 0);
</script>

<template>
    <Head title="Order Tracking" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Navigation class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">PRO · Order Tracking</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Orders to Suppliers</h1>
                            <p class="text-sm text-blue-100/90">{{ filtered.length }} of {{ purchaseOrders.length }} orders · click one to track it</p>
                        </div>
                        <div class="relative flex-1 sm:flex-none sm:min-w-[260px]">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search PO no. or supplier…"
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                    </div>
                    <div class="relative mt-4 flex flex-wrap gap-2">
                        <button v-for="key in statusKeys" :key="key" @click="statusFilter = key"
                            :class="statusFilter === key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="rounded-2xl px-4 py-2 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            {{ key }}
                        </button>
                    </div>
                </div>

                <div v-if="filtered.length === 0" class="flex flex-col items-center justify-center py-16 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4">
                        <Truck class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No orders found</p>
                    <p class="text-xs text-gray-400 mt-1">Accepted quotations generate purchase orders here.</p>
                </div>

                <TransitionGroup v-else name="card" tag="div" class="space-y-4">
                    <button v-for="(o, i) in filtered" :key="o.id" @click="openTracking(o)" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="w-full text-left group relative rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 p-5 overflow-hidden hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300">
                        <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                            <div class="min-w-0">
                                <div class="flex gap-2 items-center flex-wrap">
                                    <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ o.po_number }}</span>
                                    <span :class="statusBadge(o.status)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1">{{ o.status }}</span>
                                    <span :class="finBadge(o.finance_status)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1">{{ o.finance_status === 'approved' ? 'Finance approved' : o.finance_status === 'declined' ? 'Finance declined' : 'Awaiting finance' }}</span>
                                </div>
                                <p class="font-black text-gray-900 dark:text-white mt-1">{{ o.supplier_name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Issued {{ fmtDate(o.issued_date) }} · Expected {{ o.expected_delivery || '—' }}</p>
                            </div>
                            <div class="text-left sm:text-right flex-shrink-0">
                                <p class="font-black text-emerald-600 dark:text-emerald-400 text-lg">{{ peso(o.grand_total) }}</p>
                                <p class="text-[11px] font-black uppercase tracking-widest text-indigo-500 mt-1">Track order →</p>
                            </div>
                        </div>
                    </button>
                </TransitionGroup>

            </div>

            <Transition name="modal">
                <div v-if="tracked" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="closeTracking" />
                    <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl">
                        <div class="sticky top-0 z-10 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 text-white">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Order tracking</p>
                                    <h3 class="font-mono text-lg font-black tracking-tight truncate">{{ tracked.po_number }}</h3>
                                    <p class="text-xs text-blue-100/90 font-bold truncate">{{ tracked.supplier_name }}</p>
                                </div>
                                <button @click="closeTracking" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition active:scale-95">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-white/15 ring-1 ring-white/25 text-white"><span class="h-1.5 w-1.5 rounded-full bg-current" />{{ tracked.status }}</span>
                                <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-white/15 ring-1 ring-white/25 text-white"><span class="h-1.5 w-1.5 rounded-full bg-current" />{{ tracked.finance_status === 'approved' ? 'Finance approved' : tracked.finance_status === 'declined' ? 'Finance declined' : 'Awaiting finance' }}</span>
                                <span class="ml-auto font-black text-lg whitespace-nowrap">{{ peso(tracked.grand_total) }}</span>
                            </div>
                        </div>

                        <div class="p-5 sm:p-6 space-y-6">
                            <div v-if="tracked.finance_status === 'declined'" class="rounded-2xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 p-4 flex items-start gap-3">
                                <TriangleAlert class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
                                <p class="text-xs font-bold text-rose-800 dark:text-rose-200">Declined by finance{{ tracked.finance_remarks ? `: “${tracked.finance_remarks}”` : '' }} — return it to Material Requests from Receipts for a fresh round.</p>
                            </div>

                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Progress</p>
                                <ol class="relative ml-2 border-l-2 border-gray-100 dark:border-zinc-800 space-y-5">
                                    <li v-for="s in STEPS" :key="s.key" class="relative pl-8">
                                        <span class="absolute left-0 top-0 flex h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full ring-4 ring-white dark:ring-zinc-900"
                                            :class="stepState(tracked, s.key) === 'done' ? 'bg-emerald-500 text-white' : stepState(tracked, s.key) === 'current' ? 'bg-indigo-600 text-white animate-pulse' : 'bg-gray-200 dark:bg-zinc-700 text-gray-400'">
                                            <CheckCircle v-if="stepState(tracked, s.key) === 'done'" class="h-3.5 w-3.5" />
                                            <span v-else class="h-1.5 w-1.5 rounded-full bg-current" />
                                        </span>
                                        <p class="text-sm font-black" :class="stepState(tracked, s.key) === 'todo' ? 'text-gray-400' : 'text-gray-900 dark:text-white'">{{ s.label }}</p>
                                        <p v-if="stepState(tracked, s.key) === 'current'" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400">Current stage</p>
                                        <p v-else-if="stepDate(tracked, s.key)" class="text-[11px] text-gray-400 font-medium">{{ fmtDate(stepDate(tracked, s.key)) }}</p>
                                    </li>
                                </ol>
                            </div>

                            <div class="rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                <p class="px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-gray-400 bg-gray-50 dark:bg-zinc-800 flex items-center gap-1.5"><Package class="h-3.5 w-3.5" /> Items · ETA {{ tracked.expected_delivery || '—' }}</p>
                                <ul class="divide-y divide-gray-50 dark:divide-zinc-800">
                                    <li v-for="item in tracked.items" :key="item.material_name" class="px-4 py-2.5 text-xs flex justify-between gap-3">
                                        <span class="font-bold text-gray-800 dark:text-gray-100 min-w-0">{{ item.material_name }} <span class="text-gray-400 font-medium">× {{ item.qty }} {{ item.unit }}</span></span>
                                        <span class="font-black text-gray-900 dark:text-white whitespace-nowrap">{{ peso(item.total) }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="rounded-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                                <p class="px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-gray-400 bg-gray-50 dark:bg-zinc-800 flex items-center gap-1.5"><Wallet class="h-3.5 w-3.5" /> Invoices · {{ peso(paidTotal(tracked)) }} paid of {{ peso(invoicedTotal(tracked)) }}</p>
                                <p v-if="!tracked.invoices?.length" class="px-4 py-3 text-xs text-gray-400">No supplier invoices yet.</p>
                                <ul v-else class="divide-y divide-gray-50 dark:divide-zinc-800">
                                    <li v-for="inv in tracked.invoices" :key="inv.invoice_number" class="px-4 py-2.5 text-xs flex justify-between gap-3">
                                        <span class="font-bold text-gray-800 dark:text-gray-100 min-w-0">{{ inv.invoice_number }} <span class="font-mono font-medium text-gray-400">· {{ inv.status }}</span></span>
                                        <span class="font-black text-gray-900 dark:text-white whitespace-nowrap">{{ peso(inv.amount) }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="rounded-2xl bg-slate-50 dark:bg-zinc-950/60 border border-gray-100 dark:border-zinc-800 p-4 text-xs flex items-center gap-2">
                                <ClipboardList class="h-4 w-4 text-indigo-500 shrink-0" />
                                <p class="text-gray-500 font-medium">Finance decision {{ tracked.finance_decided_at ? 'on ' + fmtDate(tracked.finance_decided_at) : 'pending' }} · Last update {{ fmtDate(tracked.updated_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(12px) scale(0.98); }
</style>
