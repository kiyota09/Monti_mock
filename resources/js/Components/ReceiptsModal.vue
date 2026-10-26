<script setup>
import { computed } from 'vue';
import {
    FileText, X, CalendarClock, Wallet, Hash, User,
} from 'lucide-vue-next';

const props = defineProps({
    show: { type: Boolean, default: false },
    row: { type: Object, default: null },
    brand: { type: String, default: 'Monti Textiles' },
    title: { type: String, default: 'Receipts' },
    subtitle: { type: String, default: 'Payment history' },
});

const emit = defineEmits(['close']);

const peso = (v) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));

const fmtDate = (d) => {
    if (!d) return '—';
    const dt = new Date(d);
    return isNaN(dt) ? String(d) : dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const balanceOf = (r) => Number(r?.amount ?? 0) - Number(r?.paid ?? 0);

const getStatusClass = (status) => {
    switch (status) {
        case 'paid':    return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'partial': return 'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30';
        case 'unpaid':  return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'overdue': return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
        default:        return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};

// Recompute a running balance from the oldest payment forward, then display
// newest first so the latest receipt sits on top.
const receiptsWithBalance = computed(() => {
    const row = props.row;
    if (!row) return [];
    const asc = [...(row.receipts ?? [])].sort((a, b) =>
        String(a.paid_at).localeCompare(String(b.paid_at)) || String(a.id).localeCompare(String(b.id)));
    let running = Number(row.amount ?? 0);
    const out = asc.map(r => {
        running = Math.round((running - Number(r.amount ?? 0)) * 100) / 100;
        return { ...r, balance_after: running };
    });
    return out.reverse();
});

const receiptPaidTotal = computed(() =>
    receiptsWithBalance.value.reduce((s, r) => s + Number(r.amount ?? 0), 0));
</script>

<template>
    <Transition name="receipt-modal">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="emit('close')" />
            <div class="relative flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl">
                <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">{{ subtitle }}</p>
                            <h3 class="flex items-center gap-2 text-lg font-black tracking-tight"><FileText class="h-5 w-5" /> {{ title }}</h3>
                        </div>
                        <button @click="emit('close')" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition active:scale-95">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <template v-if="row">
                    <!-- Document summary (supplier bill or client invoice) -->
                    <div class="border-b border-gray-100 dark:border-zinc-800 bg-slate-50/70 dark:bg-zinc-950/40 px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-black text-gray-900 dark:text-white">{{ row.bill_no || row.invoice_no }}</p>
                                <p class="truncate text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {{ row.supplier || row.client }}<template v-if="row.category"> · {{ row.category }}</template>
                                </p>
                                <p class="mt-0.5 text-[10px] font-medium text-gray-400">Due {{ fmtDate(row.due_date) }}</p>
                            </div>
                            <span :class="getStatusClass(row.status)" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-black uppercase ring-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-current" /> {{ row.status }}
                            </span>
                        </div>
                        <div v-if="row.invoice_no && Number(row.tax_amount) > 0" class="mt-3 border-t border-gray-200 dark:border-zinc-800 pt-3 text-xs space-y-1">
                            <div class="flex justify-between text-gray-500"><span>Subtotal</span><span>{{ peso(row.subtotal) }}</span></div>
                            <div class="flex justify-between text-gray-500"><span>VAT ({{ row.tax_rate }}%)</span><span>{{ peso(row.tax_amount) }}</span></div>
                            <div class="flex justify-between font-black text-gray-800 dark:text-zinc-100"><span>Invoice total</span><span>{{ peso(row.amount) }}</span></div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-2xl bg-white px-2 py-2 ring-1 ring-gray-100 dark:bg-zinc-900 dark:ring-zinc-800">
                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Billed</p>
                                <p class="text-sm font-black text-gray-900 dark:text-white">{{ peso(row.amount) }}</p>
                            </div>
                            <div class="rounded-2xl bg-white px-2 py-2 ring-1 ring-gray-100 dark:bg-zinc-900 dark:ring-zinc-800">
                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Paid</p>
                                <p class="text-sm font-black text-emerald-600 dark:text-emerald-400">{{ peso(row.paid) }}</p>
                            </div>
                            <div class="rounded-2xl bg-white px-2 py-2 ring-1 ring-gray-100 dark:bg-zinc-900 dark:ring-zinc-800">
                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Balance</p>
                                <p class="text-sm font-black text-indigo-600 dark:text-indigo-300">{{ peso(balanceOf(row)) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Receipts -->
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto bg-slate-100/70 p-5 dark:bg-zinc-950/60">
                        <div v-if="!receiptsWithBalance.length" class="flex flex-col items-center justify-center py-14 text-center">
                            <div class="mb-4 rounded-full bg-gradient-to-br from-blue-50 to-indigo-50 p-5 dark:from-indigo-900/30">
                                <FileText class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No payments yet</p>
                            <p class="mt-1 text-xs text-gray-400">Receipts appear here the moment a payment is received.</p>
                        </div>

                        <div v-for="rc in receiptsWithBalance" :key="rc.id" class="online-receipt">
                            <span class="online-receipt-notch online-receipt-notch-l" />
                            <span class="online-receipt-notch online-receipt-notch-r" />
                            <div class="px-5 py-4 font-mono">
                                <div class="text-center">
                                    <p class="text-[11px] font-black uppercase tracking-[0.3em] text-gray-800 dark:text-zinc-100">{{ brand }}</p>
                                    <p class="text-[9px] uppercase tracking-[0.25em] text-gray-400">Official payment receipt</p>
                                </div>

                                <div class="my-3 border-t border-dashed border-gray-300 dark:border-zinc-700" />

                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-[9px] uppercase tracking-widest text-gray-400">Receipt no.</p>
                                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ rc.receipt_no }}</p>
                                    </div>
                                    <span :class="rc.balance_after <= 0 ? 'border-emerald-500 text-emerald-600 dark:text-emerald-300' : 'border-amber-500 text-amber-600 dark:text-amber-300'" class="stamp">
                                        {{ rc.balance_after <= 0 ? 'Paid in full' : 'Partial' }}
                                    </span>
                                </div>

                                <div class="my-3 border-t border-dashed border-gray-300 dark:border-zinc-700" />

                                <dl class="space-y-1.5 text-[11px]">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="flex items-center gap-1.5 text-gray-400"><CalendarClock class="h-3.5 w-3.5" /> Date paid</dt>
                                        <dd class="font-bold text-gray-800 dark:text-zinc-200">{{ fmtDate(rc.paid_at) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="flex items-center gap-1.5 text-gray-400"><Wallet class="h-3.5 w-3.5" /> Method</dt>
                                        <dd class="font-bold text-gray-800 dark:text-zinc-200">{{ rc.method }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="flex items-center gap-1.5 text-gray-400"><Hash class="h-3.5 w-3.5" /> Reference</dt>
                                        <dd class="break-all text-right font-bold text-gray-800 dark:text-zinc-200">{{ rc.reference }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="flex items-center gap-1.5 text-gray-400"><User class="h-3.5 w-3.5" /> Recorded by</dt>
                                        <dd class="font-bold text-gray-800 dark:text-zinc-200">{{ rc.recorded_by }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="flex items-center gap-1.5 text-gray-400"><FileText class="h-3.5 w-3.5" /> Source</dt>
                                        <dd class="font-bold text-gray-800 dark:text-zinc-200">{{ rc.source }}</dd>
                                    </div>
                                </dl>

                                <div class="my-3 border-t border-dashed border-gray-300 dark:border-zinc-700" />

                                <div class="flex items-baseline justify-between">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Amount paid</span>
                                    <span class="text-xl font-black tracking-tight text-gray-900 dark:text-white">{{ peso(rc.amount) }}</span>
                                </div>
                                <div class="mt-1 flex items-center justify-between text-[10px] text-gray-400">
                                    <span>Balance after payment</span>
                                    <span :class="rc.balance_after <= 0 ? 'text-emerald-500' : 'text-amber-500'" class="font-bold">{{ peso(rc.balance_after) }}</span>
                                </div>

                                <div class="my-3 border-t border-dashed border-gray-300 dark:border-zinc-700" />

                                <div class="flex flex-col items-center gap-1">
                                    <div class="receipt-barcode" />
                                    <p class="text-[8px] tracking-[0.3em] text-gray-400">{{ rc.receipt_no }}</p>
                                    <p class="mt-1 text-[10px] text-gray-400">Thank you for your payment</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="border-t border-gray-100 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between text-xs font-bold text-gray-500 dark:text-gray-400">
                            <span>{{ row.receipt_count }} receipt{{ row.receipt_count !== 1 ? 's' : '' }} · total received</span>
                            <span class="font-black text-gray-900 dark:text-white">{{ peso(receiptPaidTotal) }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Outstanding balance</span>
                            <span :class="balanceOf(row) <= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-indigo-600 dark:text-indigo-300'" class="text-base font-black">{{ peso(balanceOf(row)) }}</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.receipt-modal-enter-active, .receipt-modal-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.receipt-modal-enter-from, .receipt-modal-leave-to { opacity: 0; transform: translateY(12px) scale(0.98); }

.online-receipt {
    --paper: #ffffff;
    --notch: #edf1f6;
    position: relative;
    background: var(--paper);
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 14px;
    box-shadow: 0 16px 32px -20px rgba(15, 23, 42, 0.5);
}
:global(.dark) .online-receipt {
    --paper: #18181b;
    --notch: #0a0a0c;
    border-color: rgba(63, 63, 70, 0.7);
    box-shadow: 0 16px 32px -20px rgba(0, 0, 0, 0.85);
}
.online-receipt-notch {
    position: absolute;
    top: 50%;
    z-index: 2;
    height: 16px;
    width: 16px;
    margin-top: -8px;
    border-radius: 9999px;
    background: var(--notch);
}
.online-receipt-notch-l { left: -8px; }
.online-receipt-notch-r { right: -8px; }

.stamp {
    display: inline-block;
    border: 2px solid currentColor;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    transform: rotate(-6deg);
    opacity: 0.9;
}

.receipt-barcode {
    height: 34px;
    width: 168px;
    color: #111827;
    opacity: 0.85;
    background-image: repeating-linear-gradient(90deg,
        currentColor 0 2px, transparent 2px 4px,
        currentColor 4px 5px, transparent 5px 9px,
        currentColor 9px 12px, transparent 12px 13px,
        currentColor 13px 15px, transparent 15px 19px);
}
:global(.dark) .receipt-barcode { color: #e4e4e7; }
</style>
