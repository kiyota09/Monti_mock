<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    ShoppingCart, Package, Truck, CheckCircle,
    Clock, Receipt, DollarSign, X, Navigation,
    Factory, ChevronDown, Plus
} from 'lucide-vue-next';

const props = defineProps({
    auth: Object,
    orders: {
        type: Array,
        default: () => []
    }
});

const searchQuery = ref('');
const statusFilter = ref('All');

const filteredOrders = computed(() => {
    let list = props.orders;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(o => o.po_number.toLowerCase().includes(q));
    }
    if (statusFilter.value !== 'All') {
        list = list.filter(o => o.status === statusFilter.value.toLowerCase());
    }
    return list;
});

const formatCurrency = (val) => '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });

const statusStyle = (status) => {
    const map = {
        sent: 'bg-blue-100 text-blue-700 border-blue-200',
        production: 'bg-amber-100 text-amber-700 border-amber-200',
        shipping: 'bg-violet-100 text-violet-700 border-violet-200',
        delivered: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        completed: 'bg-slate-100 text-slate-700 border-slate-200'
    };
    return map[status] || 'bg-slate-100 text-slate-700 border-slate-200';
};

const statusText = (status) => {
    if (status === 'sent') return 'New Order';
    if (status === 'production') return 'In Production';
    if (status === 'shipping') return 'Shipped (For Warehouse)';
    if (status === 'delivered') return 'Delivered (Received)';
    return status.charAt(0).toUpperCase() + status.slice(1);
};

const processingId = ref(null);
const updateStatus = (id, newStatus) => {
    processingId.value = id;
    router.post(route('supplier.orders.update_status', id), { status: newStatus }, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; }
    });
};

// Invoice modal
const showInvoiceModal = ref(false);
const selectedOrder = ref(null);
const invoiceForm = useForm({
    invoice_number: '',
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: '',
    amount: 0,
});

const openInvoiceModal = (order) => {
    selectedOrder.value = order;
    invoiceForm.invoice_number = 'INV-' + order.po_number.split('-').pop() + '-' + Math.floor(Math.random() * 1000);
    invoiceForm.amount = order.grand_total;
    let dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 30);
    invoiceForm.due_date = dueDate.toISOString().split('T')[0];
    showInvoiceModal.value = true;
};

const submitInvoice = () => {
    invoiceForm.post(route('supplier.orders.invoice', selectedOrder.value.id), {
        preserveScroll: true,
        onSuccess: () => { showInvoiceModal.value = false; }
    });
};

// ── Order tracking (same pipeline language as PRO tracking UI) ──
const TRACK_STEPS = [
    { key: 'sent', label: 'New order' },
    { key: 'production', label: 'In production' },
    { key: 'shipping', label: 'Shipped' },
    { key: 'delivered', label: 'Delivered' },
    { key: 'completed', label: 'Completed' },
];
const tracked = ref(null);
const openTracking = (order) => { tracked.value = order; };
const closeTracking = () => { tracked.value = null; };
const stepState = (order, key) => {
    const orderIdx = TRACK_STEPS.findIndex((s) => s.key === order.status);
    const idx = TRACK_STEPS.findIndex((s) => s.key === key);
    if (orderIdx === -1) return 'todo';
    if (idx < orderIdx) return 'done';
    if (idx === orderIdx) return 'current';
    return 'todo';
};
const trackProgress = (order) => {
    const idx = TRACK_STEPS.findIndex((s) => s.key === order.status);
    if (idx === -1) return 0;
    return Math.round(((idx + 1) / TRACK_STEPS.length) * 100);
};
</script>

<template>
    <Head title="Purchase Orders | Supplier Hub" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">Purchase Orders</h1>
                <p class="text-slate-500 text-sm mt-0.5">Manage production, shipping, and billing for Monti Textile.</p>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 mb-6">
            <input v-model="searchQuery" type="text" placeholder="Search PO Number..."
                class="flex-1 px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20" />
            <div class="relative w-full sm:w-48">
                <select v-model="statusFilter"
                    class="w-full appearance-none px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm font-bold focus:ring-2 focus:ring-emerald-500/20">
                    <option value="All">All Statuses</option>
                    <option value="sent">New Orders</option>
                    <option value="production">In Production</option>
                    <option value="shipping">Shipped</option>
                    <option value="delivered">Delivered (Received)</option>
                </select>
                <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
            </div>
        </div>

        <div v-if="filteredOrders.length === 0"
            class="text-center py-16 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
            <ShoppingCart class="w-12 h-12 text-slate-300 mx-auto mb-3" />
            <p class="text-sm font-bold text-slate-500">No purchase orders found.</p>
        </div>

        <div class="space-y-4">
            <div v-for="order in filteredOrders" :key="order.id"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm flex flex-col">

                <div class="flex flex-col md:flex-row justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4 mb-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="font-mono text-sm font-black text-slate-800 dark:text-white">{{ order.po_number }}</span>
                            <span :class="['text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full border', statusStyle(order.status)]">
                                {{ statusText(order.status) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 flex items-center gap-1.5">
                            <Clock class="w-3.5 h-3.5" /> Expected Delivery: <strong class="text-slate-700 dark:text-slate-300">{{ order.expected_delivery }}</strong>
                        </p>
                        <!-- Order tracking progress (mirrors PRO tracking UI) -->
                        <div class="mt-3">
                            <div class="flex justify-between text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">
                                <span>Tracking</span><span class="text-emerald-600">{{ trackProgress(order) }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all" :style="{ width: trackProgress(order) + '%' }" />
                            </div>
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                <span v-for="s in TRACK_STEPS" :key="s.key"
                                    :class="stepState(order, s.key) === 'done' ? 'bg-emerald-100 text-emerald-700' : stepState(order, s.key) === 'current' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500'"
                                    class="text-[8px] font-black uppercase tracking-wide px-1.5 py-0.5 rounded-full">
                                    {{ stepState(order, s.key) === 'done' ? '✓ ' : '' }}{{ s.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="md:text-right">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Order Total</p>
                        <p class="text-xl font-black text-emerald-600 dark:text-emerald-400 leading-none mt-0.5">{{ formatCurrency(order.grand_total) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 flex-1">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Order Items</p>
                        <div class="space-y-2">
                            <div v-for="item in order.items" :key="item.id"
                                class="flex justify-between items-center bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-lg border border-slate-100 dark:border-slate-700">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.material_name }}</p>
                                    <p class="text-[10px] text-slate-500">{{ item.qty }} {{ item.unit }} @ {{ formatCurrency(item.unit_price) }}</p>
                                </div>
                                <p class="text-xs font-black text-slate-700 dark:text-slate-300">{{ formatCurrency(item.total) }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Billing & Payments</p>
                            <button v-if="order.invoices.length === 0" @click="openInvoiceModal(order)"
                                class="text-[10px] font-black text-blue-600 hover:underline flex items-center gap-1">
                                <Plus class="w-3 h-3" /> Create Invoice
                            </button>
                        </div>

                        <div v-if="order.invoices.length === 0"
                            class="h-20 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl flex items-center justify-center text-xs font-medium text-slate-400">
                            No invoice generated yet.
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="inv in order.invoices" :key="inv.id"
                                class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                                <div class="flex justify-between items-center mb-1.5">
                                    <span class="font-mono text-xs font-bold">{{ inv.invoice_number }}</span>
                                    <span :class="['text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md', inv.status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
                                        {{ inv.status }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-end">
                                    <p class="text-[10px] text-slate-500">Due: {{ inv.due_date }}</p>
                                    <p class="text-sm font-black">{{ formatCurrency(inv.amount) }}</p>
                                </div>
                                <div v-if="inv.payments && inv.payments.length > 0"
                                    class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-700/50">
                                    <div v-for="pay in inv.payments" :key="pay.id"
                                        class="flex justify-between text-[10px] text-emerald-600 font-bold">
                                        <span>Paid via {{ pay.method }} ({{ pay.paid_date }})</span>
                                        <span>{{ formatCurrency(pay.amount) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap gap-2 justify-end">
                    <button @click="openTracking(order)"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-slate-900 dark:bg-white hover:opacity-90 text-white dark:text-slate-900 rounded-xl text-sm font-black shadow-sm transition-all active:scale-[0.98]">
                        <Navigation class="w-4 h-4" /> Track Order · {{ trackProgress(order) }}%
                    </button>
                    <button v-if="order.status === 'sent'" @click="updateStatus(order.id, 'production')"
                        :disabled="processingId === order.id"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-black shadow-sm transition-all active:scale-[0.98] disabled:opacity-50">
                        <Factory class="w-4 h-4" /> Start Production
                    </button>

                    <button v-if="order.status === 'production'" @click="updateStatus(order.id, 'shipping')"
                        :disabled="processingId === order.id"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-sm font-black shadow-sm transition-all active:scale-[0.98] disabled:opacity-50">
                        <Truck class="w-4 h-4" /> Ship Order
                    </button>

                    <div v-if="order.status === 'shipping'"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-500 rounded-xl text-sm font-black">
                        <Package class="w-4 h-4" /> Awaiting Warehouse Receiving
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Tracking Modal (mirrors PRO tracking UI) -->
        <Teleport to="body">
            <div v-if="tracked"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="closeTracking">
                <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                    <div class="px-5 py-4 bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 text-white flex justify-between items-center flex-shrink-0">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-100">Order tracking</p>
                            <h3 class="text-base font-black font-mono">{{ tracked.po_number }}</h3>
                            <p class="text-[11px] text-emerald-100 font-bold">{{ statusText(tracked.status) }} · {{ trackProgress(tracked) }}% complete</p>
                        </div>
                        <button @click="closeTracking"
                            class="p-2 rounded-xl bg-white/15 hover:bg-white/25 transition">
                            <X class="w-4 h-4 text-white" />
                        </button>
                    </div>
                    <div class="p-5 sm:p-6 overflow-y-auto flex-1 space-y-5">
                        <ol class="relative ml-2 border-l-2 border-slate-100 dark:border-slate-800 space-y-5">
                            <li v-for="s in TRACK_STEPS" :key="s.key" class="relative pl-8">
                                <span class="absolute left-0 top-0 flex h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full ring-4 ring-white dark:ring-slate-900"
                                    :class="stepState(tracked, s.key) === 'done' ? 'bg-emerald-500 text-white' : stepState(tracked, s.key) === 'current' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 animate-pulse' : 'bg-slate-200 dark:bg-slate-700 text-slate-400'">
                                    <CheckCircle v-if="stepState(tracked, s.key) === 'done'" class="h-3.5 w-3.5" />
                                    <span v-else class="h-1.5 w-1.5 rounded-full bg-current" />
                                </span>
                                <p class="text-sm font-black" :class="stepState(tracked, s.key) === 'todo' ? 'text-slate-400' : 'text-slate-900 dark:text-white'">{{ s.label }}</p>
                                <p v-if="stepState(tracked, s.key) === 'current'" class="text-[11px] font-bold text-emerald-600">Current stage — update it from the order card actions</p>
                            </li>
                        </ol>
                        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                            <p class="px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-400 bg-slate-50 dark:bg-slate-800">Items · ETA {{ tracked.expected_delivery || '—' }}</p>
                            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                <li v-for="item in tracked.items" :key="item.id" class="px-4 py-2.5 text-xs flex justify-between gap-3">
                                    <span class="font-bold min-w-0">{{ item.material_name }} <span class="text-slate-400 font-medium">× {{ item.qty }} {{ item.unit }}</span></span>
                                    <span class="font-black whitespace-nowrap">{{ formatCurrency(item.total) }}</span>
                                </li>
                            </ul>
                        </div>
                        <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 p-4 text-xs">
                            <p class="font-bold text-slate-600 dark:text-slate-300">Billing: {{ tracked.invoices?.length || 0 }} invoice(s) · paid amounts appear on the order card.</p>
                        </div>
                    </div>
                    <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-800 flex-shrink-0">
                        <button @click="closeTracking"
                            class="w-full py-3 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-xl text-sm font-black transition active:scale-[0.99]">Close tracking</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Invoice Modal (unchanged) -->
        <Teleport to="body">
            <div v-if="showInvoiceModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="showInvoiceModal = false">
                <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden flex flex-col">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 flex justify-between items-center">
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <Receipt class="w-5 h-5 text-blue-500" /> Generate Invoice
                        </h3>
                        <button @click="showInvoiceModal = false"
                            class="p-2 rounded-xl bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 transition shadow-sm border border-slate-200 dark:border-slate-600">
                            <X class="w-4 h-4 text-slate-500" />
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Invoice Number *</label>
                            <input v-model="invoiceForm.invoice_number" type="text"
                                class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold font-mono" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Invoice Date *</label>
                                <input v-model="invoiceForm.invoice_date" type="date"
                                    class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Due Date *</label>
                                <input v-model="invoiceForm.due_date" type="date"
                                    class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold" />
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Amount to Bill (₱) *</label>
                            <input v-model="invoiceForm.amount" type="number" step="0.01"
                                class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-black text-emerald-600" />
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex gap-3 bg-slate-50 dark:bg-slate-800">
                        <button @click="showInvoiceModal = false"
                            class="flex-1 py-3 text-sm font-bold rounded-xl border border-slate-200 dark:border-slate-600 hover:bg-white transition">Cancel</button>
                        <button @click="submitInvoice" :disabled="invoiceForm.processing"
                            class="flex-1 inline-flex items-center justify-center gap-2 py-3 text-sm font-black rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition shadow-lg shadow-blue-500/20 disabled:opacity-40">
                            <DollarSign class="w-4 h-4" /> {{ invoiceForm.processing ? 'Saving...' : 'Send Invoice' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>