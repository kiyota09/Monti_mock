<template>
    <Head title="Invoices & Payments" />
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
                            <CreditCard class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Financial Overview
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Invoices & Payments</h1>
                            <p class="text-sm text-blue-100/90">Track your outstanding balances and payment history.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ pendingCount }} pending
                            </span>
                            <button @click="refreshData"
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/30 hover:rotate-180 active:scale-95 transition-all duration-500">
                                <RefreshCw class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by PO number..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="key in ['all','pending','paid','overdue']" :key="key" @click="statusFilter = key"
                                :class="statusFilter === key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ key === 'all' ? 'All orders' : key }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div :style="{ animationDelay: '0ms' }"
                        class="group animate-fade-up relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-rose-500/15 hover:-translate-y-1.5 hover:border-rose-200 dark:hover:border-rose-900 hover:scale-[1.01] transition-all duration-300 p-6">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-rose-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative">
                            <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Total Outstanding</p>
                            <p class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1 tracking-tight">₱{{ formatCurrency(totalOutstanding) }}</p>
                        </div>
                    </div>
                    <div :style="{ animationDelay: '80ms' }"
                        class="group animate-fade-up relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-emerald-500/15 hover:-translate-y-1.5 hover:border-emerald-200 dark:hover:border-emerald-900 hover:scale-[1.01] transition-all duration-300 p-6">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative">
                            <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Paid Orders</p>
                            <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 tracking-tight">{{ paidCount }}</p>
                        </div>
                    </div>
                    <div :style="{ animationDelay: '160ms' }"
                        class="group animate-fade-up relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-amber-500/15 hover:-translate-y-1.5 hover:border-amber-200 dark:hover:border-amber-900 hover:scale-[1.01] transition-all duration-300 p-6">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative">
                            <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Pending Payments</p>
                            <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1 tracking-tight">{{ pendingCount }}</p>
                        </div>
                    </div>
                </div>

                <!-- Invoices card -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-xl transition-shadow duration-300 overflow-hidden" style="animation-delay:200ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <FileText class="w-5 h-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Invoices</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredOrders.length }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50/50 dark:bg-zinc-800/30 text-[10px] font-black uppercase text-gray-400 tracking-[0.15em]">
                                <tr>
                                    <th class="px-8 py-5">Invoice / PO #</th>
                                    <th class="px-8 py-5">Date</th>
                                    <th class="px-8 py-5 text-right">Amount</th>
                                    <th class="px-8 py-5 text-center">Status</th>
                                    <th class="px-8 py-5 text-center">Due Date</th>
                                    <th class="px-8 py-5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(order, i) in filteredOrders" :key="order.id"
                                    :style="{ animationDelay: `${Math.min(i * 40, 400)}ms` }"
                                    class="animate-fade-up group hover:bg-indigo-50/40 dark:hover:bg-indigo-900/10 transition-all">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-3">
                                            <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                                                <FileText class="h-5 w-5" />
                                            </div>
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ order.po_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-sm text-gray-600 dark:text-gray-400 font-medium">{{ formatDate(order.created_at) }}</td>
                                    <td class="px-8 py-6 text-right font-black text-gray-900 dark:text-white">₱{{ formatCurrency(order.total_amount) }}</td>
                                    <td class="px-8 py-6 text-center">
                                        <span :class="getStatusBadge(order.payment_status || 'pending')" class="px-3 py-1 rounded-full text-[9px] font-black uppercase ring-1 inline-flex items-center gap-1.5">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ order.payment_status || 'Pending' }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-center text-sm font-medium" :class="isOverdue(order) ? 'text-red-600 dark:text-red-400 font-bold' : 'text-gray-500 dark:text-gray-400'">
                                        {{ formatDate(order.due_date || order.created_at) }}
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link :href="route('client.invoices.show', order.id)"
                                                class="px-5 py-2.5 bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-100 hover:text-indigo-700 active:scale-95 transition-all">
                                                View
                                            </Link>
                                            <button @click="payNow(order)"
                                                :disabled="order.payment_status === 'paid'"
                                                class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-105 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">
                                                {{ order.payment_status === 'paid' ? 'Paid' : 'Pay Now' }}
                                            </button>
                                        </div>
                                    </td>
                                 </tr>
                                <tr v-if="filteredOrders.length === 0">
                                    <td colspan="6" class="px-8 py-20 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 dark:to-violet-900/20 rounded-full mb-4 animate-bounce-soft">
                                                <FileText class="h-9 w-9 text-indigo-400" />
                                            </div>
                                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No invoices found</p>
                                            <p class="text-xs text-gray-400 mt-1">Try a different search or filter.</p>
                                        </div>
                                    </td>
                                 </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment Modal -->
                <Teleport to="body">
                    <Transition name="modal">
                        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showPaymentModal = false">
                            <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden">
                                <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-6 py-5 text-white flex justify-between items-center relative overflow-hidden">
                                    <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl animate-float" />
                                    <h3 class="relative font-black text-lg tracking-tight">Make Payment</h3>
                                    <button @click="showPaymentModal = false" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 hover:bg-white/35 hover:rotate-90 transition-all"><X class="h-5 w-5" /></button>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div class="rounded-2xl bg-gradient-to-br from-indigo-50 to-violet-50 dark:from-indigo-900/20 dark:to-violet-900/10 ring-1 ring-indigo-100 dark:ring-indigo-900 p-4">
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Order #</p>
                                        <p class="font-mono font-bold text-gray-900 dark:text-white">{{ selectedOrder?.po_number }}</p>
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-2">Amount Due</p>
                                        <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400">₱{{ formatCurrency(selectedOrder?.total_amount) }}</p>
                                    </div>
                                    <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                                        <p class="font-bold text-gray-700 dark:text-gray-200">Payment Gateway Integration</p>
                                        <p>This will connect to the Finance Module.</p>
                                        <p class="mt-2 text-xs">Supported methods: Bank Transfer, Credit Card, GCash</p>
                                    </div>
                                    <div class="flex gap-3">
                                        <button @click="showPaymentModal = false" class="flex-1 py-3 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 font-bold text-sm hover:bg-gray-200 dark:hover:bg-zinc-700 active:scale-95 transition">Cancel</button>
                                        <button @click="simulatePayment" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-black text-sm shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all">
                                            Proceed to Payment
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </Teleport>

                <!-- Toast Notification -->
                <Transition name="toast">
                    <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-xl text-white font-bold text-sm ring-1 ring-white/20"
                        :class="toast.type === 'success' ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-gradient-to-r from-rose-600 to-red-600'">
                        {{ toast.message }}
                    </div>
                </Transition>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { CreditCard, RefreshCw, Search, FileText, X, Sparkles } from 'lucide-vue-next';

const props = defineProps({
    orders: {
        type: Array,
        default: () => []
    }
});

// Filter state
const searchTerm = ref('');
const statusFilter = ref('all');

// Payment modal
const showPaymentModal = ref(false);
const selectedOrder = ref(null);

// Toast
const toast = ref({ show: false, type: 'success', message: '' });

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

// Computed
const filteredOrders = computed(() => {
    let list = props.orders;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(o => o.po_number.toLowerCase().includes(term));
    }
    if (statusFilter.value === 'pending') {
        list = list.filter(o => o.payment_status !== 'paid');
    } else if (statusFilter.value === 'paid') {
        list = list.filter(o => o.payment_status === 'paid');
    } else if (statusFilter.value === 'overdue') {
        list = list.filter(o => isOverdue(o) && o.payment_status !== 'paid');
    }
    return list;
});

const totalOutstanding = computed(() => {
    return props.orders
        .filter(o => o.payment_status !== 'paid')
        .reduce((sum, o) => sum + (parseFloat(o.total_amount) || 0), 0);
});

const paidCount = computed(() => props.orders.filter(o => o.payment_status === 'paid').length);
const pendingCount = computed(() => props.orders.filter(o => o.payment_status !== 'paid').length);

const formatCurrency = (val) => Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const formatDate = (date) => new Date(date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });

const isOverdue = (order) => {
    if (order.payment_status === 'paid') return false;
    const dueDate = new Date(order.due_date || order.created_at);
    const today = new Date();
    return dueDate < today;
};

const getStatusBadge = (status) => {
    const map = {
        paid: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        pending: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        overdue: 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30'
    };
    return map[status] || 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const refreshData = () => {
    router.reload({ only: ['orders'] });
};

const payNow = (order) => {
    if (order.payment_status === 'paid') {
        showToast('error', 'This invoice is already paid.');
        return;
    }
    selectedOrder.value = order;
    showPaymentModal.value = true;
};

const simulatePayment = () => {
    // This will be replaced with actual Finance module integration
    showToast('success', `Payment for ${selectedOrder.value.po_number} initiated. You will be redirected to the payment gateway.`);
    showPaymentModal.value = false;
    // In real implementation, redirect to payment gateway or open modal from Finance module
    // For now, just reload to simulate status change
    setTimeout(() => refreshData(), 1500);
};
</script>

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
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
.toast-enter-active, .toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from, .toast-leave-to {
    opacity: 0;
    transform: translateY(20px);
}
</style>
