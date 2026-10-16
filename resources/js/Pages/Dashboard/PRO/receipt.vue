<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { Package, Receipt, Send, Eye, Printer, Wallet, X, Sparkles, ArrowUpRight, RotateCcw } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditReceipt = computed(() => canEdit('PRO', 'receipt'));

const props = defineProps({
    purchaseOrders: Array,
    invoices: Array,
});

const sendPO = (id) => {
    router.post(route('pro.manager.purchase-orders.send', id), {}, {
        preserveScroll: true,
    });
};

const returnToRequests = (id) => {
    if (!confirm('Return this declined PO\'s material request to Material Requests for a fresh RFQ round?')) return;
    router.post(route('pro.manager.purchase-orders.return', id), {}, {
        preserveScroll: true,
    });
};

const finBadge = (s) => {
    switch (s) {
        case 'approved': return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'declined': return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
        default: return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
    }
};
const finLabel = (s) => s === 'approved' ? 'Finance approved' : s === 'declined' ? 'Finance declined' : 'Awaiting finance';

// --- Pay Invoice modal state ---
const showPayModal = ref(false);
const activeInvoice = ref(null);

const payForm = useForm({
    amount: '',
    method: 'bank_transfer',
    paid_date: new Date().toISOString().slice(0, 10),
    bank_reference: '',
    remarks: '',
});

const openPayModal = (invoice) => {
    activeInvoice.value = invoice;
    const balance = Number(invoice.amount) - Number(invoice.amount_paid || 0);
    payForm.reset();
    payForm.amount = balance.toFixed(2);
    payForm.paid_date = new Date().toISOString().slice(0, 10);
    showPayModal.value = true;
};

const closePayModal = () => {
    showPayModal.value = false;
    activeInvoice.value = null;
    payForm.reset();
};

const submitPayment = () => {
    if (!activeInvoice.value) return;
    payForm.post(route('pro.manager.invoices.pay', activeInvoice.value.id), {
        preserveScroll: true,
        onSuccess: () => closePayModal(),
    });
};

const formatCurrency = (val) => '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });
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

const invoiceStatusBadge = (status) => {
    const map = {
        awaiting: 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700',
        unpaid: 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30',
        partial: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        paid: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        disputed: 'bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:ring-orange-500/30',
        cancelled: 'bg-gray-200 text-gray-500 ring-gray-300 dark:bg-zinc-800 dark:text-gray-400 dark:ring-zinc-700',
    };
    return map[status] || 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const canPay = (invoice) => !['paid', 'cancelled'].includes(invoice.status);
</script>

<template>

    <Head title="PRO - Receipt" />
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
                            <Receipt class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> PRO · Receipts
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Purchase Orders & Invoices</h1>
                            <p class="text-sm text-blue-100/90">Manage POs and track invoices from suppliers</p>
                            <span v-if="!canEditReceipt" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ purchaseOrders.length }} POs
                            </span>
                            <span class="rounded-full bg-white px-3 py-1.5 text-xs font-black text-indigo-700 shadow-lg">{{ invoices.length }} invoices</span>
                        </div>
                    </div>
                </div>

                <!-- Purchase Orders -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay:80ms">
                    <div class="px-6 sm:px-8 py-5 border-b border-gray-100 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-black uppercase tracking-tight text-gray-900 dark:text-white flex items-center gap-2"><Package class="w-4 h-4 text-indigo-500" /> Purchase Orders</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Send drafts and track fulfilment</p>
                        </div>
                        <span class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full ring-1 bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30 flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ purchaseOrders.length }} total
                        </span>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div v-if="purchaseOrders.length === 0" class="flex flex-col items-center justify-center py-16 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <Package class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No POs yet</p>
                            <p class="text-xs text-gray-400 mt-1">Accepted quotations will generate purchase orders.</p>
                        </div>
                        <TransitionGroup v-else name="card" tag="div" class="space-y-4">
                            <div v-for="(po, i) in purchaseOrders" :key="po.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                class="group relative rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 p-5 overflow-hidden hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.005] transition-all duration-300">
                                <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                                <div class="relative flex flex-col sm:flex-row justify-between items-start gap-4">
                                    <div class="min-w-0">
                                        <div class="flex gap-2 items-center flex-wrap">
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ po.po_number }}</span>
                                            <span :class="statusBadge(po.status)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ po.status }}</span>
                                            <span :class="finBadge(po.finance_status)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ finLabel(po.finance_status) }}</span>
                                        </div>
                                        <p class="font-black text-gray-900 dark:text-white mt-1 group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ po.supplier_name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Issued: {{ po.issued_date }} | Expected: {{ po.expected_delivery }}</p>
                                        <p v-if="po.finance_status === 'declined' && po.finance_remarks" class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-bold">Declined: {{ po.finance_remarks }}</p>
                                    </div>
                                    <div class="text-left sm:text-right flex-shrink-0">
                                        <p class="font-black text-emerald-600 dark:text-emerald-400 text-lg">{{ formatCurrency(po.grand_total) }}</p>
                                        <button v-if="po.status === 'draft' && po.finance_status === 'approved' && canEditReceipt" @click="sendPO(po.id)"
                                            class="mt-2 px-5 py-2.5 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-700 text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-indigo-500/25 hover:opacity-95 active:scale-95 transition inline-flex items-center gap-1.5"><Send class="w-3.5 h-3.5" /> Send PO</button>
                                        <p v-else-if="po.status === 'draft' && (po.finance_status ?? 'pending') === 'pending'" class="mt-2 text-[11px] font-bold text-amber-600 dark:text-amber-400">Awaiting finance approval</p>
                                        <button v-if="po.finance_status === 'declined' && po.has_rfq && canEditReceipt" @click="returnToRequests(po.id)"
                                            class="mt-2 px-5 py-2.5 bg-white dark:bg-zinc-800 border-2 border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 rounded-2xl text-xs font-black uppercase tracking-widest hover:border-indigo-400 active:scale-95 transition inline-flex items-center gap-1.5"><RotateCcw class="w-3.5 h-3.5" /> Back to Material Requests</button>
                                    </div>
                                </div>
                                <div class="relative mt-3 pt-3 border-t border-gray-100 dark:border-zinc-800 text-xs text-gray-500 dark:text-gray-400 space-y-1">
                                    <p v-for="item in po.items" :key="item.id" class="font-medium">{{ item.material_name }}: {{ item.qty }} {{ item.unit }} @ {{ formatCurrency(item.unit_price) }}</p>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>

                <!-- Invoices -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay:160ms">
                    <div class="px-6 sm:px-8 py-5 border-b border-gray-100 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-black uppercase tracking-tight text-gray-900 dark:text-white flex items-center gap-2"><Wallet class="w-4 h-4 text-emerald-500" /> Invoices from Suppliers</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Record partial or full payments</p>
                        </div>
                        <span class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full ring-1 bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30 flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ invoices.length }} recorded
                        </span>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div v-if="invoices.length === 0" class="flex flex-col items-center justify-center py-16 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800">
                            <div class="p-5 bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-900/30 rounded-full mb-4 animate-bounce-soft">
                                <Receipt class="h-9 w-9 text-emerald-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No invoices recorded</p>
                            <p class="text-xs text-gray-400 mt-1">Supplier invoices will appear here.</p>
                        </div>
                        <TransitionGroup v-else name="card" tag="div" class="space-y-3">
                            <div v-for="(inv, i) in invoices" :key="inv.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                class="group relative flex flex-col sm:flex-row justify-between sm:items-center gap-4 p-5 rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 overflow-hidden hover:shadow-2xl hover:shadow-emerald-500/10 hover:-translate-y-1 hover:border-emerald-200 dark:hover:border-emerald-800 transition-all duration-300">
                                <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <div class="relative min-w-0">
                                    <p class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ inv.invoice_number }}</p>
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ inv.supplier_name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Date: {{ inv.invoice_date }} | Due: {{ inv.due_date }}</p>
                                    <p v-if="inv.amount_paid > 0 && inv.status !== 'paid'" class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-bold">
                                        Paid so far: {{ formatCurrency(inv.amount_paid) }}
                                    </p>
                                </div>
                                <div class="relative text-left sm:text-right flex-shrink-0">
                                    <p class="font-black text-amber-600 dark:text-amber-400 text-lg">{{ formatCurrency(inv.amount) }}</p>
                                    <span :class="invoiceStatusBadge(inv.status)"
                                        class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 inline-flex items-center gap-1 mt-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ inv.status }}</span>
                                    <div>
                                        <button v-if="canPay(inv) && canEditReceipt" @click="openPayModal(inv)"
                                            class="mt-2 inline-flex items-center gap-1.5 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg shadow-emerald-500/25 hover:opacity-95 active:scale-95 transition">
                                            <Wallet class="w-3.5 h-3.5" /> Pay Invoice
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>
            </div>

            <!-- Pay Invoice Modal -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-md p-4">
                        <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl w-full max-w-md shadow-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden max-h-[90vh] flex flex-col">
                            <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 px-6 py-5 text-white flex justify-between items-center flex-shrink-0">
                                <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                                <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 20px 20px;" />
                                <h3 class="relative font-black uppercase tracking-tight flex items-center gap-2"><Wallet class="w-5 h-5" /> Pay Invoice</h3>
                                <button @click="closePayModal" class="relative p-2 rounded-xl bg-white/15 ring-1 ring-white/25 hover:bg-white/25 transition active:scale-95">
                                    <X class="w-5 h-5" />
                                </button>
                            </div>

                            <div class="px-6 py-5 space-y-4 overflow-y-auto" v-if="activeInvoice">
                                <div class="text-sm bg-gray-50 dark:bg-zinc-800/60 rounded-2xl p-4 border border-gray-100 dark:border-zinc-800">
                                    <p class="font-mono font-black text-gray-900 dark:text-white">{{ activeInvoice.invoice_number }}</p>
                                    <p class="font-bold text-gray-700 dark:text-gray-200">{{ activeInvoice.supplier_name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Invoice total: {{ formatCurrency(activeInvoice.amount) }}
                                        <span v-if="activeInvoice.amount_paid > 0">
                                            | Already paid: {{ formatCurrency(activeInvoice.amount_paid) }}
                                        </span>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Amount to Pay</label>
                                    <input v-model="payForm.amount" type="number" step="0.01" min="0.01"
                                        class="w-full px-4 py-3 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition" />
                                    <p v-if="payForm.errors.amount" class="text-xs text-red-600 mt-1">{{ payForm.errors.amount }}</p>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Payment Method</label>
                                    <select v-model="payForm.method" class="w-full px-4 py-3 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition">
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="check">Check</option>
                                        <option value="cash">Cash</option>
                                        <option value="gcash">GCash</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Date Paid</label>
                                    <input v-model="payForm.paid_date" type="date"
                                        class="w-full px-4 py-3 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition" />
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Reference No. (optional)</label>
                                    <input v-model="payForm.bank_reference" type="text" placeholder="Bank ref / check no."
                                        class="w-full px-4 py-3 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition" />
                                </div>

                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Remarks (optional)</label>
                                    <textarea v-model="payForm.remarks" rows="2"
                                        class="w-full px-4 py-3 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition"></textarea>
                                </div>

                                <p class="text-xs text-gray-400">The supplier will be notified by email once payment is recorded.</p>
                            </div>

                            <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 dark:border-zinc-800 bg-gray-50/60 dark:bg-zinc-800/40">
                                <button @click="closePayModal"
                                    class="px-5 py-2.5 text-xs font-black uppercase rounded-2xl border border-gray-200 dark:border-zinc-700 text-gray-500 dark:text-gray-300 hover:bg-gray-100 transition active:scale-95">Cancel</button>
                                <button v-if="canEditReceipt" @click="submitPayment" :disabled="payForm.processing"
                                    class="px-5 py-2.5 text-xs font-black uppercase rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-lg disabled:opacity-50 active:scale-95 transition">
                                    {{ payForm.processing ? 'Processing...' : 'Confirm Payment' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-panel, .modal-leave-to .modal-panel { opacity: 0; transform: translateY(16px) scale(0.97); }
</style>
