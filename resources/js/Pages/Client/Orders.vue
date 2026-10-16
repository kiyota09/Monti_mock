<template>
    <Head title="My Orders" />
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
                            <ShoppingCart class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Order Management
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">My Orders</h1>
                            <p class="text-sm text-blue-100/90">{{ filteredOrders.length }} order{{ filteredOrders.length !== 1 ? 's' : '' }} showing · approve finalized quotations here</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="refreshData" class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/30 hover:rotate-12 transition-all active:scale-95" title="Refresh">
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
                        <div class="flex gap-2 overflow-x-auto">
                            <button v-for="opt in [['all','All'],['pending_client_approval','Pending'],['approved','Approved'],['credit_review','Credit'],['tier_assignment','Tier'],['rejected','Rejected']]" :key="opt[0]" @click="statusFilter = opt[0]"
                                :class="statusFilter === opt[0] ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="whitespace-nowrap rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ opt[1] }}
                            </button>
                        </div>
                    </div>

                    <!-- Stat strip -->
                    <div class="relative mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:80ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Total orders</p>
                            <p class="mt-1 text-lg font-black">{{ stats.totalOrders }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:160ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Pending approval</p>
                            <p class="mt-1 text-lg font-black">{{ stats.pendingApproval }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:240ms">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Completed</p>
                            <p class="mt-1 text-lg font-black">{{ stats.completed }}</p>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300"><ShoppingCart class="h-6 w-6" /></span>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Orders</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.totalOrders }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-amber-200 dark:hover:border-amber-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300"><FileText class="h-6 w-6" /></span>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Pending Approval</p>
                                <p class="text-3xl font-black text-amber-600 tracking-tight">{{ stats.pendingApproval }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-emerald-200 dark:hover:border-emerald-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:240ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300"><Check class="h-6 w-6" /></span>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Completed</p>
                                <p class="text-3xl font-black text-emerald-600 tracking-tight">{{ stats.completed }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Orders Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20">
                        <FileText class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Purchase Orders</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredOrders.length }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black uppercase text-gray-400 tracking-[0.15em] border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">PO Number</th>
                                    <th class="px-6 py-4">Date</th>
                                    <th class="px-6 py-4 text-right">Total Amount</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(order, i) in filteredOrders" :key="order.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform shrink-0">
                                                <FileText class="h-5 w-5" />
                                            </div>
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ order.po_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</td>
                                    <td class="px-6 py-4 text-right font-black text-gray-900 dark:text-white">₱{{ formatCurrency(order.total_amount) }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span :class="getStatusBadge(order.status)" class="px-2.5 py-1 rounded-full ring-1 ring-current/20 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ formatStatus(order.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button v-if="order.status === 'pending_client_approval'"
                                            @click="openAcceptModal(order)"
                                            class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-105 active:scale-95 transition-all">
                                            Accept Order
                                        </button>
                                        <button v-else disabled
                                            class="px-5 py-2.5 bg-gray-100 dark:bg-zinc-800 text-gray-400 rounded-xl text-[10px] font-black uppercase tracking-widest cursor-not-allowed">
                                            No Action
                                        </button>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="filteredOrders.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <ShoppingCart class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No orders found</p>
                            <p class="text-xs text-gray-400 mt-1">Try a different search or filter.</p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="orders.meta?.last_page > 1" class="px-6 py-5 border-t border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic">
                            Showing {{ orders.meta.from }}–{{ orders.meta.to }} of {{ orders.meta.total }} orders
                        </p>
                        <div class="flex gap-2">
                            <component v-for="link in orders.links" :key="link.label" :is="link.url ? 'a' : 'span'"
                                :href="link.url ?? undefined" v-text="link.label.replace('&laquo;', '«').replace('&raquo;', '»')" :class="[
                                    'px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all',
                                    link.active ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/30' : link.url ? 'text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20' : 'text-gray-200 dark:text-zinc-700 cursor-default'
                                ]" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accept Order Modal -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showAcceptModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showAcceptModal = false">
                        <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden">
                            <div class="px-6 py-5 bg-gradient-to-r from-indigo-600 via-blue-600 to-violet-600 text-white relative overflow-hidden">
                                <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                                <div class="relative flex justify-between items-center">
                                    <h3 class="font-black text-lg">Accept Order</h3>
                                    <button @click="showAcceptModal = false" class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 hover:bg-white/35 hover:rotate-90 transition-all"><X class="h-5 w-5" /></button>
                                </div>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="rounded-2xl bg-indigo-50/70 dark:bg-indigo-900/10 border border-indigo-100 dark:border-indigo-900 p-4">
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Order #</p>
                                    <p class="font-mono font-bold text-gray-900 dark:text-white">{{ selectedOrder?.po_number }}</p>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-2">Total Amount</p>
                                    <p class="text-2xl font-black text-indigo-600">₱{{ formatCurrency(selectedOrder?.total_amount) }}</p>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">By accepting this order, you confirm that the quotation details are correct and you agree to the payment terms. The order will be sent to our supply chain for processing.</p>
                                <button @click="acceptOrder" :disabled="accepting"
                                    class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-black text-sm shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-50">
                                    <Loader2 v-if="accepting" class="h-4 w-4 animate-spin inline mr-2" />
                                    {{ accepting ? 'Processing...' : 'Confirm Acceptance' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <!-- Toast Notification -->
            <Transition name="toast">
                <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-xl text-white font-bold text-sm"
                    :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                    {{ toast.message }}
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ShoppingCart, RefreshCw, Search, FileText, X, Loader2, Check, Sparkles } from 'lucide-vue-next';

const props = defineProps({
    orders: {
        type: Object,
        default: () => ({ data: [], links: [], meta: {} })
    },
    stats: {
        type: Object,
        default: () => ({ totalOrders: 0, pendingApproval: 0, completed: 0 })
    }
});

// Filters
const searchTerm = ref('');
const statusFilter = ref('all');

// Accept modal
const showAcceptModal = ref(false);
const selectedOrder = ref(null);
const accepting = ref(false);

// Toast
const toast = ref({ show: false, type: 'success', message: '' });

const filteredOrders = computed(() => {
    let list = props.orders.data;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(o => o.po_number.toLowerCase().includes(term));
    }
    if (statusFilter.value !== 'all') {
        list = list.filter(o => o.status === statusFilter.value);
    }
    return list;
});

const formatCurrency = (val) => Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const formatDate = (date) => new Date(date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });

const getStatusBadge = (status) => {
    const map = {
        credit_review: 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
        tier_assignment: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
        pending_client_approval: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        rejected: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
    };
    return map[status] || 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-300';
};

const formatStatus = (status) => {
    const map = {
        credit_review: 'Credit Review',
        tier_assignment: 'Tier Assignment',
        pending_client_approval: 'Pending Approval',
        approved: 'Approved',
        rejected: 'Rejected'
    };
    return map[status] || status;
};

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const openAcceptModal = (order) => {
    selectedOrder.value = order;
    showAcceptModal.value = true;
};

const acceptOrder = async () => {
    accepting.value = true;
    try {
        await router.post(route('client.orders.accept', selectedOrder.value.id));
        showAcceptModal.value = false;
        showToast('success', `Order ${selectedOrder.value.po_number} accepted.`);
        refreshData();
    } catch (error) {
        showToast('error', 'Failed to accept order.');
    } finally {
        accepting.value = false;
    }
};

const refreshData = () => {
    router.reload({ only: ['orders', 'stats'] });
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
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
