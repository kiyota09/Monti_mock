<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { ShoppingCart, Eye, X, Loader2, Clock, Send, Check, Sparkles } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditSales = computed(() => canEdit('SCM', 'sales'));

const props = defineProps({ orders: Array });

const isLoading = ref(false);
const selectedOrder = ref(null);
const showDetailModal = ref(false);
const actionLoading = ref({});

// ── Confirmation modal state (for push to production) ──────────────────────
const confirmModal = ref({
    show: false,
    order: null,
    action: null,   // 'push'
    title: '',
    message: '',
});

// ── Toast notification state ───────────────────────────────────────────────
const toast = ref({ show: false, message: '', type: 'success' });

const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 4000);
};

// ── Open confirmation modal for push ───────────────────────────────────────
// Stock was already checked in the ECO Push Center; the backend re-verifies
// live with the same DSS engine when pushing, so no manual check step here.
const openPushToProductionConfirm = (order) => {
    confirmModal.value = {
        show: true,
        order,
        action: 'push',
        title: 'Push to Manufacturing?',
        message: 'This will forward the order to the Manufacturing plant. Warehouse stock is re-verified live on push — insufficient stock blocks automatically.',
    };
};

// ── Execute push action after confirmation ─────────────────────────────────
const executeConfirmedAction = () => {
    const { order, action } = confirmModal.value;
    if (!order || action !== 'push') return;

    confirmModal.value.show = false;
    actionLoading.value[order.id] = action;

    const url = order.type === 'sales_order'
        ? route('scm.sales-order.push-to-production-sales', order.sales_order_id)
        : route('scm.sales-order.push-to-production', order.id);

    router.post(url, {}, {
        preserveScroll: true,
        onSuccess: () => showToast('Order successfully pushed to Manufacturing!'),
        onError: (errors) => showToast(errors?.error ?? 'Failed to push order to production.', 'error'),
        onFinish: () => delete actionLoading.value[order.id],
    });
};

// ── Push availability ──────────────────────────────────────────────────────
// Sales orders: check happens once in the ECO Push Center; SCM pushing
// re-verifies live server-side. Purchase orders keep their queue gate.
const canPush = (order) => {
    if (!canEditSales.value) return false;
    if (order.type === 'sales_order') {
        return ['pushed_to_scm', 'inv_check', 'inv_checked'].includes(order.stage);
    }
    return order.stage === 'inv_checked' && order.inv_check_sufficient;
};

// ── Open detail modal ───────────────────────────────────────────────────────
const openDetail = (order) => {
    selectedOrder.value = order;
    showDetailModal.value = true;
};

const formatCurrency = (val) => '₱' + Number(val).toLocaleString();
const formatDate = (date) => new Date(date).toLocaleDateString();

const getStatusBadge = (stage, sufficient) => {
    if (stage === 'in_production' || stage === 'man_production') return { text: 'In Production', class: 'bg-purple-50 text-purple-700 border-purple-200' };
    if (stage === 'inv_checked' && sufficient) return { text: 'Inventory OK', class: 'bg-green-50 text-green-700 border-green-200' };
    if (stage === 'inv_check') return { text: 'Inventory Check', class: 'bg-amber-50 text-amber-700 border-amber-200' };
    if (stage === 'pushed_to_scm') return { text: 'Received from ECO', class: 'bg-blue-50 text-blue-700 border-blue-200' };
    return { text: stage.replace(/_/g, ' '), class: 'bg-slate-50 text-slate-600 border-slate-200' };
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="SCM Sales Orders · Monti" />
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
                                <Sparkles class="h-3.5 w-3.5" /> SCM · Supply Chain
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Sales Orders</h1>
                            <p class="text-sm text-blue-100/90">{{ orders.length }} order(s) pending</p>
                            <span v-if="!canEditSales" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ orders.length }} pending
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="orders.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <ShoppingCart class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No sales orders pending</p>
                    <p class="text-xs text-gray-400 mt-1">New orders forwarded from ECO will appear here.</p>
                </div>

                <div v-else class="space-y-4">
                    <!-- Mobile Cards -->
                    <TransitionGroup name="card" tag="div" class="space-y-4 md:hidden">
                        <div v-for="(order, i) in orders" :key="order.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="relative flex justify-between items-start">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            {{ order.type === 'sales_order' ? 'JO' : 'PO' }} Number
                                        </span>
                                        <span :class="getStatusBadge(order.stage, order.inv_check_sufficient).class"
                                              class="inline-flex items-center gap-1 text-[9px] px-2 py-0.5 rounded-full border font-bold">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                            {{ getStatusBadge(order.stage, order.inv_check_sufficient).text }}
                                        </span>
                                    </div>
                                    <p class="text-base font-black text-gray-900 dark:text-white tracking-tight">{{ order.po_number }}</p>
                                </div>
                                <span v-if="order.type === 'sales_order'"
                                      class="text-[9px] font-black bg-yellow-50 text-yellow-600 dark:bg-yellow-500/15 dark:text-yellow-300 px-2 py-1 rounded-lg border border-yellow-200 dark:border-yellow-500/30">
                                    Sales Order
                                </span>
                            </div>

                            <div class="relative space-y-2 mt-3">
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">Client</p>
                                    <p class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ order.client_name }}</p>
                                </div>
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">Total Amount</p>
                                        <p class="text-base font-black text-indigo-600 dark:text-indigo-300">{{ formatCurrency(order.total_amount) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">Date</p>
                                        <p class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</p>
                                    </div>
                                </div>
                                <div v-if="order.type === 'sales_order' && order.quantity" class="bg-indigo-50/70 dark:bg-indigo-900/10 border border-transparent dark:border-indigo-800/40 p-2 rounded-xl">
                                    <p class="text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">Quantity</p>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ order.quantity }} kg</p>
                                </div>
                            </div>

                            <!-- Mobile Action Buttons -->
                            <div class="relative flex gap-2 pt-3">
                                <button @click="openDetail(order)"
                                        class="flex-1 py-3 bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all flex justify-center items-center gap-1.5 active:scale-95">
                                    <Eye class="h-3.5 w-3.5" /> View
                                </button>
                                <button v-if="canPush(order)"
                                        @click="openPushToProductionConfirm(order)"
                                        :disabled="actionLoading[order.id]"
                                        class="flex-1 py-3 bg-gradient-to-br from-indigo-600 to-violet-700 hover:from-indigo-700 hover:to-violet-800 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition disabled:opacity-50 flex justify-center items-center gap-1.5 shadow-md active:scale-95">
                                    <Loader2 v-if="actionLoading[order.id] === 'push'" class="h-3.5 w-3.5 animate-spin" />
                                    Push
                                </button>
                            </div>
                        </div>
                    </TransitionGroup>

                    <!-- Desktop Table -->
                    <div class="animate-fade-up hidden md:block bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay:120ms">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                            <Clock class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                            <h2 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white">Pending Orders</h2>
                            <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ orders.length }}</span>
                        </div>
                        <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-8 py-4">Order Info</th>
                                    <th class="px-6 py-4">Client</th>
                                    <th class="px-6 py-4 text-center">Amount</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-8 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="order in orders" :key="order.id"
                                    class="text-sm hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">

                                    <!-- Order Info -->
                                    <td class="px-8 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-xl flex items-center justify-center font-black text-[10px] flex-shrink-0 bg-gradient-to-br"
                                                :class="order.type === 'sales_order' ? 'from-amber-500 via-orange-600 to-rose-600 text-white' : 'from-indigo-500 via-blue-600 to-cyan-600 text-white'">
                                                {{ order.type === 'sales_order' ? 'JO' : 'PO' }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-black text-gray-900 dark:text-white">{{ order.po_number }}</p>
                                                <p class="text-[10px] font-mono text-gray-400 mt-0.5">{{ formatDate(order.created_at) }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Client -->
                                    <td class="px-6 py-5">
                                        <p class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ order.client_name }}</p>
                                        <p v-if="order.type === 'sales_order' && order.quantity"
                                           class="text-[10px] text-gray-400 mt-0.5">{{ order.quantity }} kg</p>
                                    </td>

                                    <!-- Amount -->
                                    <td class="px-6 py-5 text-center">
                                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ formatCurrency(order.total_amount) }}</p>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-6 py-5 text-center">
                                        <span :class="getStatusBadge(order.stage, order.inv_check_sufficient).class"
                                              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border text-[10px] font-black uppercase tracking-wider">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                            {{ getStatusBadge(order.stage, order.inv_check_sufficient).text }}
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-8 py-5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="openDetail(order)"
                                                    class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-indigo-600 hover:text-white transition-all">
                                                <Eye class="h-4 w-4" />
                                            </button>
                                            <button v-if="canPush(order)"
                                                    @click="openPushToProductionConfirm(order)"
                                                    :disabled="actionLoading[order.id]"
                                                    class="px-4 py-2.5 bg-gradient-to-br from-indigo-600 to-violet-700 hover:from-indigo-700 hover:to-violet-800 text-white rounded-xl text-[10px] font-black uppercase tracking-wider transition disabled:opacity-50 flex items-center gap-1.5 shadow-md active:scale-95">
                                                <Loader2 v-if="actionLoading[order.id] === 'push'" class="h-3 w-3 animate-spin" />
                                                Push
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <Teleport to="body">
            <Transition name="toast">
                <div v-if="toast.show"
                    class="fixed bottom-6 right-6 z-[200] flex items-center gap-3 bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 px-6 py-4 rounded-2xl shadow-2xl max-w-sm">
                    <div class="h-8 w-8 rounded-xl flex items-center justify-center flex-shrink-0 animate-pop"
                        :class="toast.type === 'error' ? 'bg-rose-500' : 'bg-emerald-500'">
                        <Check v-if="toast.type !== 'error'" class="h-4 w-4 text-white" />
                        <X v-else class="h-4 w-4 text-white" />
                    </div>
                    <p class="text-sm font-bold leading-snug">{{ toast.message }}</p>
                </div>
            </Transition>
        </Teleport>

        <!-- Push to Production Confirmation Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="confirmModal.show"
                    class="fixed inset-0 z-[150] flex items-center justify-center p-4 sm:p-6 bg-zinc-950/60 backdrop-blur-sm"
                    @click.self="confirmModal.show = false">
                    <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden flex flex-col border border-gray-100 dark:border-zinc-800">

                        <!-- Modal Header -->
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 sm:px-8 py-6 text-white">
                            <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex justify-between items-start">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">
                                            Confirm Action
                                        </p>
                                    </div>
                                    <h3 class="font-black text-xl tracking-tight">{{ confirmModal.title }}</h3>
                                </div>
                                <button @click="confirmModal.show = false"
                                    class="p-2.5 bg-white/15 hover:bg-white/25 ring-1 ring-white/25 rounded-2xl transition-colors backdrop-blur">
                                    <X class="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <!-- Modal Body -->
                        <div class="p-6 sm:p-8 space-y-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium leading-relaxed">{{ confirmModal.message }}</p>

                            <!-- Order Summary Card -->
                            <div v-if="confirmModal.order" class="bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 rounded-2xl p-5 space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest">Order Number</span>
                                    <span class="text-xs font-bold text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/20 px-2 py-1 rounded-md border border-indigo-100 dark:border-indigo-800 font-mono">
                                        {{ confirmModal.order.po_number }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center border-t border-gray-100 dark:border-zinc-800 pt-3">
                                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest">Client</span>
                                    <span class="text-sm font-black text-gray-900 dark:text-white">{{ confirmModal.order.client_name }}</span>
                                </div>
                                <div class="flex justify-between items-center border-t border-gray-100 dark:border-zinc-800 pt-3">
                                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest">Amount</span>
                                    <span class="text-sm font-black text-indigo-600 dark:text-indigo-300">{{ formatCurrency(confirmModal.order.total_amount) }}</span>
                                </div>
                                <div v-if="confirmModal.order.quantity" class="flex justify-between items-center border-t border-gray-100 dark:border-zinc-800 pt-3">
                                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest">Volume</span>
                                    <span class="text-sm font-black text-gray-900 dark:text-white">{{ confirmModal.order.quantity }} kg</span>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="p-6 border-t border-gray-100 dark:border-zinc-800 flex gap-3">
                            <button @click="confirmModal.show = false"
                                class="flex-1 py-4 rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-gray-300 text-xs font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-zinc-700 transition-colors active:scale-95">
                                Cancel
                            </button>
                            <button @click="executeConfirmedAction"
                                :disabled="actionLoading[confirmModal.order?.id]"
                                class="flex-1 py-4 rounded-2xl text-white text-xs font-black uppercase tracking-widest transition-colors shadow-lg flex items-center justify-center gap-2 disabled:opacity-50 bg-gradient-to-br from-indigo-600 to-violet-700 hover:from-indigo-700 hover:to-violet-800 active:scale-95">
                                <Loader2 v-if="actionLoading[confirmModal.order?.id]" class="h-4 w-4 animate-spin" />
                                <Send v-else class="h-4 w-4" />
                                Push to Manufacturing
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Detail Modal -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="showDetailModal"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 bg-zinc-950/60 backdrop-blur-sm"
                @click.self="showDetailModal = false">
                <div class="bg-white dark:bg-zinc-900 w-full max-w-xl rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] border border-gray-100 dark:border-zinc-800">

                    <!-- Modal Header -->
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 sm:px-8 py-6 text-white">
                        <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-200 animate-pulse"></span>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">
                                        {{ selectedOrder?.type === 'sales_order' ? 'Job Order' : 'Purchase Order' }}
                                    </p>
                                </div>
                                <h3 class="font-black text-xl sm:text-2xl tracking-tight">{{ selectedOrder?.po_number }}</h3>
                            </div>
                            <button @click="showDetailModal = false"
                                    class="p-2.5 bg-white/15 hover:bg-white/25 ring-1 ring-white/25 rounded-2xl transition-colors backdrop-blur">
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 sm:p-8 space-y-6 overflow-y-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-5 bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 rounded-2xl">
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Client</p>
                                <p class="font-black text-gray-900 dark:text-white text-sm">{{ selectedOrder?.client_name }}</p>
                            </div>
                            <div class="p-5 bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 rounded-2xl">
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Amount</p>
                                <p class="font-black text-indigo-600 dark:text-indigo-300 text-xl">{{ formatCurrency(selectedOrder?.total_amount) }}</p>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="flex items-center gap-3">
                            <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Current Status:</span>
                            <span v-if="selectedOrder"
                                  :class="getStatusBadge(selectedOrder.stage, selectedOrder.inv_check_sufficient).class"
                                  class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border text-[10px] font-black uppercase tracking-wider">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                {{ getStatusBadge(selectedOrder.stage, selectedOrder.inv_check_sufficient).text }}
                            </span>
                        </div>

                        <!-- Sales Order Extra Fields -->
                        <div v-if="selectedOrder?.type === 'sales_order'" class="space-y-3">
                            <h4 class="text-[10px] font-black uppercase text-gray-400 tracking-[0.2em] flex items-center gap-2">
                                <span class="h-px bg-gray-200 dark:bg-zinc-800 flex-1"></span> Product Specifications <span class="h-px bg-gray-200 dark:bg-zinc-800 flex-1"></span>
                            </h4>
                            <div class="bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-800 rounded-2xl p-5 space-y-3">
                                <div class="flex justify-between items-center border-b border-gray-100 dark:border-zinc-800 pb-2">
                                    <span class="text-[10px] font-black text-gray-400">Color</span>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white uppercase">{{ selectedOrder?.color || '—' }}</span>
                                </div>
                                <div class="flex justify-between items-center border-b border-gray-100 dark:border-zinc-800 pb-2">
                                    <span class="text-[10px] font-black text-gray-400">Yarn Type</span>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedOrder?.yarn_type || '—' }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] font-black text-gray-400">Quantity (kg)</span>
                                    <span class="text-lg font-black text-indigo-600 dark:text-indigo-300">{{ selectedOrder?.quantity || 0 }} kg</span>
                                </div>
                            </div>
                        </div>

                        <!-- Items Table (for purchase orders) -->
                        <div v-if="selectedOrder?.items && selectedOrder.items.length" class="space-y-3">
                            <h4 class="text-[10px] font-black uppercase text-gray-400 tracking-[0.2em] flex items-center gap-2">
                                <span class="h-px bg-gray-200 dark:bg-zinc-800 flex-1"></span> Order Items <span class="h-px bg-gray-200 dark:bg-zinc-800 flex-1"></span>
                            </h4>
                            <div class="bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-800 rounded-2xl overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                        <tr>
                                            <th class="text-left p-3">Product</th>
                                            <th class="text-right p-3">Qty</th>
                                            <th class="text-right p-3">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                        <tr v-for="item in selectedOrder.items" :key="item.product_name" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                            <td class="p-3 text-sm font-medium text-gray-700 dark:text-gray-200">{{ item.product_name || 'Custom Product' }}</td>
                                            <td class="p-3 text-right text-sm font-mono">{{ item.quantity }} {{ selectedOrder.type === 'sales_order' ? 'kg' : '' }}</td>
                                            <td class="p-3 text-right text-sm font-bold text-indigo-600 dark:text-indigo-300">{{ formatCurrency(item.unit_price) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-6 border-t border-gray-100 dark:border-zinc-800 flex gap-3">
                        <button @click="showDetailModal = false"
                                class="flex-1 py-4 rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-gray-300 text-xs font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-zinc-700 transition active:scale-95">
                            Close
                        </button>
                        <!-- Quick action from modal -->
                        <button v-if="selectedOrder && canPush(selectedOrder)"
                                @click="() => { showDetailModal = false; openPushToProductionConfirm(selectedOrder); }"
                                class="flex-1 py-4 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-700 hover:from-indigo-700 hover:to-violet-800 text-white text-xs font-black uppercase tracking-widest transition shadow-lg flex items-center justify-center gap-2 active:scale-95">
                            Push to Manufacturing
                        </button>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>
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
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
.toast-enter-active, .toast-leave-active { transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(1rem) scale(0.95); }
</style>
