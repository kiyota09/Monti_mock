<template>
    <AuthenticatedLayout>
        <Head title="Orders Worklist" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-7xl space-y-5 p-4 pb-16 sm:p-6">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white shadow-xl shadow-indigo-500/20 sm:p-8">
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 shadow-lg ring-1 ring-white/30 backdrop-blur animate-pop">
                            <ClipboardList class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> ORD · Order-to-Cash
                            </p>
                            <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Orders Worklist</h1>
                            <p class="text-sm text-blue-100/90">Client purchase orders and mill job orders in one auditable pipeline</p>
                        </div>
                        <button v-if="canEditOrders" class="rounded-2xl bg-white px-4 py-2.5 text-sm font-black text-indigo-700 shadow-lg transition-all hover:bg-blue-50 active:scale-95" @click="showCreate = true">
                            + New PO
                        </button>
                        <span v-else class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25">View only</span>
                    </div>

                    <div class="relative mt-6 flex flex-wrap gap-2">
                        <button
                            v-for="(label, key) in groups"
                            :key="key"
                            class="rounded-full px-3.5 py-1.5 text-xs font-black ring-1 backdrop-blur transition-all active:scale-95"
                            :class="filters.group === key ? 'bg-white text-indigo-700 ring-white shadow' : 'bg-white/15 text-white ring-white/25 hover:bg-white/25'"
                            @click="setGroup(key)"
                        >
                            {{ label }} · {{ groupCounts[key] ?? 0 }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-2xl border border-gray-100 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Matching orders</p>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-white">{{ summary.count }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Still open</p>
                        <p class="mt-1 text-2xl font-black text-indigo-600 dark:text-indigo-300">{{ summary.open }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20">
                        <p class="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-300">Needs attention</p>
                        <p class="mt-1 text-2xl font-black text-amber-700 dark:text-amber-200">{{ summary.attention }}</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Filtered value</p>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-white">{{ formatMoney(summary.total) }}</p>
                    </div>
                </div>

                <div class="animate-fade-up space-y-3 rounded-3xl border border-gray-100 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80" style="animation-delay: 80ms">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[13rem] flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input v-model="local.search" class="w-full rounded-2xl border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm outline-none focus:ring-2 focus:ring-indigo-400 dark:border-zinc-700 dark:bg-zinc-950" placeholder="Search PO / JO / control no / client…" @keyup.enter="applyFilters" />
                        </div>
                        <select v-model="local.type" class="rounded-2xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium dark:border-zinc-700 dark:bg-zinc-950" @change="applyFilters">
                            <option value="">PO + JO</option>
                            <option value="PO">Purchase orders</option>
                            <option value="SO">Job orders</option>
                        </select>
                        <select v-model="local.client_id" class="rounded-2xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium dark:border-zinc-700 dark:bg-zinc-950" @change="applyFilters">
                            <option value="">All clients</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                        </select>
                        <select v-model="local.status" class="rounded-2xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium dark:border-zinc-700 dark:bg-zinc-950" @change="applyFilters">
                            <option value="">All statuses</option>
                            <optgroup label="Sales orders"><option v-for="s in soStatuses" :key="s" :value="s">{{ formatStatus(s) }}</option></optgroup>
                            <optgroup label="Purchase orders"><option v-for="s in poStatuses" :key="s" :value="s">{{ formatStatus(s) }}</option></optgroup>
                        </select>
                        <select v-model="local.payment_status" class="rounded-2xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium dark:border-zinc-700 dark:bg-zinc-950" @change="applyFilters">
                            <option value="">Any payment</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partially_paid">Partially paid</option>
                            <option value="paid">Paid</option>
                        </select>
                        <select v-model="local.priority" class="rounded-2xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium dark:border-zinc-700 dark:bg-zinc-950" @change="applyFilters">
                            <option value="">Any priority</option>
                            <option value="normal">Normal</option>
                            <option value="rush">Rush</option>
                            <option value="urgent">Urgent</option>
                        </select>
                        <button class="rounded-2xl border border-gray-200 px-3 py-2.5 text-sm font-bold text-gray-500 hover:bg-gray-50 dark:border-zinc-700 dark:hover:bg-zinc-800" @click="clearFilters">Reset</button>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 text-xs dark:border-zinc-800">
                        <span class="font-bold text-gray-400">Created</span>
                        <input v-model="local.from" type="date" class="rounded-xl border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-950" @change="applyFilters" />
                        <span class="text-gray-400">to</span>
                        <input v-model="local.to" type="date" class="rounded-xl border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-950" @change="applyFilters" />
                        <span class="ml-auto flex items-center gap-1.5 font-bold text-gray-400"><ArrowUpDown class="h-3.5 w-3.5" /> Sort</span>
                        <select v-model="local.sort" class="rounded-xl border border-gray-200 bg-white px-2 py-1.5 text-xs font-bold dark:border-zinc-700 dark:bg-zinc-950" @change="applyFilters">
                            <option value="created_at">Created</option>
                            <option value="expected_ship_date">Expected ship</option>
                            <option value="total">Value</option>
                            <option value="number">Document no.</option>
                            <option value="status">Status</option>
                        </select>
                        <button class="rounded-xl border border-gray-200 px-2 py-1.5 font-bold text-gray-600 dark:border-zinc-700 dark:text-zinc-300" @click="toggleDirection">
                            {{ local.dir === 'desc' ? 'Newest first' : 'Oldest first' }}
                        </button>
                    </div>
                </div>

                <div class="animate-fade-up overflow-hidden rounded-3xl border border-gray-100 bg-white/80 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80" style="animation-delay: 140ms">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[64rem] text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-[10px] font-black uppercase tracking-widest text-gray-400 dark:border-zinc-800">
                                    <th class="px-4 py-3">Document</th>
                                    <th class="px-4 py-3">Client</th>
                                    <th class="px-4 py-3">Detail</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Payment</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th class="px-4 py-3">Expected</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="o in orders.data" :key="`${o.type}-${o.id}`" class="border-b border-gray-50 transition-colors hover:bg-indigo-50/40 dark:border-zinc-800/60 dark:hover:bg-indigo-950/20">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-lg px-2 py-0.5 text-[10px] font-black" :class="o.type === 'PO' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'">{{ o.type }}</span>
                                            <span class="font-black text-gray-900 dark:text-white">{{ o.number }}</span>
                                        </div>
                                        <p class="mt-0.5 text-[11px] text-gray-400">{{ o.created_at }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-700 dark:text-zinc-200">{{ o.client_name }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-zinc-400">
                                        <template v-if="o.type === 'SO'">{{ o.product_name }} · {{ formatQuantity(o.quantity) }}</template>
                                        <template v-else>Purchase order · {{ o.group === 'intake' ? 'pending review' : formatStatus(o.status) }}</template>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <OrdStatusBadge :status="o.status" size="sm" />
                                            <span v-if="o.priority !== 'normal'" class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">{{ o.priority }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-black uppercase" :class="paymentBadge(o.payment_status)">{{ formatStatus(o.payment_status) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-gray-900 dark:text-white">{{ formatMoney(o.total) }}</td>
                                    <td class="px-4 py-3 text-xs" :class="isOverdue(o) ? 'font-black text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-zinc-400'">
                                        {{ o.expected_ship_date || '—' }}
                                        <p v-if="isOverdue(o)" class="text-[10px] font-black uppercase">Overdue</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1">
                                            <Link :href="route('ord.orders.show', { type: o.type.toLowerCase(), id: o.id })" class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-black text-white hover:bg-indigo-500">Open</Link>
                                            <button v-if="canEditOrders && o.type === 'SO' && o.transitions.includes('confirmed')" class="rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-black text-white hover:bg-emerald-500" @click="askTransition(o, 'confirmed', 'Confirm job order')">Confirm</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="orders.data.length === 0">
                                    <td colspan="8">
                                        <OrdEmptyState title="No orders in this view" message="Try clearing filters or changing the pipeline tab." />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="orders.total > 0" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 dark:border-zinc-800">
                        <p class="text-xs font-medium text-gray-500 dark:text-zinc-400">
                            Showing {{ orders.from }}–{{ orders.to }} of {{ orders.total }} orders
                        </p>
                        <div class="flex items-center gap-2">
                            <button class="rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-black text-gray-600 disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300" :disabled="orders.current_page <= 1" @click="goToPage(orders.current_page - 1)">Prev</button>
                            <span class="text-xs font-bold text-gray-500 dark:text-zinc-400">Page {{ orders.current_page }} of {{ orders.last_page }}</span>
                            <button class="rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-black text-gray-600 disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300" :disabled="orders.current_page >= orders.last_page" @click="goToPage(orders.current_page + 1)">Next</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <OrdConfirmDialog
            :open="!!pendingAction"
            :title="pendingAction?.title || ''"
            :message="pendingAction ? `Move ${pendingAction.order.number} to ${formatStatus(pendingAction.to)}?` : ''"
            :confirm-label="pendingAction?.confirmLabel || 'Confirm'"
            tone="warning"
            :processing="transitioning"
            @cancel="pendingAction = null"
            @confirm="runTransition"
        />

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreate" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 backdrop-blur-sm sm:items-center sm:p-4" @click.self="showCreate = false">
                    <form class="flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl dark:bg-zinc-900 sm:max-w-2xl sm:rounded-3xl" @submit.prevent="submitPo">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                            <div>
                                <h3 class="text-lg font-black text-gray-900 dark:text-white">New client purchase order</h3>
                                <p class="text-xs text-gray-500 dark:text-zinc-400">Starts at credit review; ECO approval is a separate gate.</p>
                            </div>
                            <button type="button" class="rounded-xl p-1.5 hover:bg-gray-100 dark:hover:bg-zinc-800" @click="showCreate = false"><X class="h-5 w-5" /></button>
                        </div>
                        <div class="space-y-4 overflow-y-auto p-5">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-sm font-bold text-gray-700 dark:text-zinc-200">Client
                                    <select v-model="poForm.client_id" required class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium dark:border-zinc-700 dark:bg-zinc-950">
                                        <option value="">Select client…</option>
                                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                                    </select>
                                    <p v-if="poForm.errors.client_id" class="mt-1 text-xs text-rose-600">{{ poForm.errors.client_id }}</p>
                                </label>
                                <label class="block text-sm font-bold text-gray-700 dark:text-zinc-200">Priority
                                    <select v-model="poForm.priority" class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium dark:border-zinc-700 dark:bg-zinc-950">
                                        <option value="normal">Normal</option><option value="rush">Rush</option><option value="urgent">Urgent</option>
                                    </select>
                                </label>
                                <label class="block text-sm font-bold text-gray-700 dark:text-zinc-200">Delivery date
                                    <input v-model="poForm.delivery_date" type="date" class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </label>
                                <label class="block text-sm font-bold text-gray-700 dark:text-zinc-200">Expected ship date
                                    <input v-model="poForm.expected_ship_date" type="date" class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                </label>
                            </div>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-sm font-black text-gray-900 dark:text-white">Line items</p>
                                    <button type="button" class="text-xs font-black text-indigo-600 hover:underline dark:text-indigo-400" @click="addLine">+ Add line</button>
                                </div>
                                <div v-for="(line, i) in poForm.items" :key="i" class="mb-2 grid grid-cols-12 gap-2">
                                    <select v-model="line.product_id" required class="col-span-6 rounded-xl border border-gray-200 bg-white px-2 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        <option value="">Product…</option>
                                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                    <input v-model.number="line.quantity" type="number" min="0.01" step="0.01" required placeholder="Qty kg" class="col-span-2 rounded-xl border border-gray-200 bg-white px-2 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                    <input v-model.number="line.unit_price" type="number" min="0" step="0.01" required placeholder="Unit price" class="col-span-3 rounded-xl border border-gray-200 bg-white px-2 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                    <button type="button" class="col-span-1 text-lg font-black text-rose-500" :disabled="poForm.items.length === 1" @click="poForm.items.splice(i, 1)">×</button>
                                </div>
                                <p v-if="poForm.errors.items" class="text-xs text-rose-600">{{ poForm.errors.items }}</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-sm font-bold text-gray-700 dark:text-zinc-200">Discount
                                    <input v-model.number="poForm.discount_amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                                    <p v-if="poForm.errors.discount_amount" class="mt-1 text-xs text-rose-600">{{ poForm.errors.discount_amount }}</p>
                                </label>
                                <div class="flex items-end justify-end">
                                    <p class="text-sm text-gray-500 dark:text-zinc-400">Subtotal <span class="font-black text-gray-900 dark:text-white">{{ formatMoney(poSubtotal) }}</span></p>
                                    <p class="ml-3 text-sm text-gray-500 dark:text-zinc-400">Total <span class="font-black text-indigo-600 dark:text-indigo-300">{{ formatMoney(poTotal) }}</span></p>
                                </div>
                            </div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-zinc-200">Notes
                                <textarea v-model="poForm.notes" rows="2" class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            </label>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4 dark:border-zinc-800">
                            <button type="button" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-bold text-gray-600 dark:border-zinc-700 dark:text-zinc-300" @click="showCreate = false">Cancel</button>
                            <button type="submit" :disabled="poForm.processing" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white hover:bg-indigo-500 disabled:opacity-50">Create PO</button>
                        </div>
                    </form>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { useOrdFeedback } from '@/composables/useOrdFeedback';
import { formatMoney, formatQuantity, formatStatus, isOverdue, paymentBadge } from '@/lib/ord';
import OrdStatusBadge from '@/Components/ord/OrdStatusBadge.vue';
import OrdEmptyState from '@/Components/ord/OrdEmptyState.vue';
import OrdConfirmDialog from '@/Components/ord/OrdConfirmDialog.vue';
import { ArrowUpDown, ClipboardList, Search, Sparkles, X } from 'lucide-vue-next';

const props = defineProps({
    orders: Object,
    groupCounts: Object,
    summary: Object,
    filters: Object,
    groups: Object,
    soStatuses: Array,
    poStatuses: Array,
    clients: Array,
    products: Array,
});

const { canEdit } = usePageAccess();
const canEditOrders = computed(() => canEdit('ORD', 'orders'));
useOrdFeedback();

const local = ref({
    group: props.filters.group || 'all',
    status: props.filters.status || '',
    client_id: props.filters.client_id || '',
    search: props.filters.search || '',
    type: props.filters.type || '',
    priority: props.filters.priority || '',
    payment_status: props.filters.payment_status || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
    sort: props.filters.sort || 'created_at',
    dir: props.filters.dir || 'desc',
});

const setGroup = (key) => {
    local.value.group = key;
    applyFilters();
};

const applyFilters = () => router.get(route('ord.orders'), { ...local.value }, { preserveState: true, preserveScroll: true });
const clearFilters = () => router.get(route('ord.orders'), { group: 'all' });
const toggleDirection = () => {
    local.value.dir = local.value.dir === 'desc' ? 'asc' : 'desc';
    applyFilters();
};
const goToPage = (page) => router.get(route('ord.orders'), { ...local.value, page }, { preserveState: true, preserveScroll: true });

const pendingAction = ref(null);
const transitioning = ref(false);

const askTransition = (order, to, title) => {
    pendingAction.value = { order, to, title, confirmLabel: 'Move order' };
};

const runTransition = () => {
    if (!pendingAction.value) return;
    transitioning.value = true;
    router.post(
        route('ord.orders.transition', { type: pendingAction.value.order.type.toLowerCase(), id: pendingAction.value.order.id }),
        { to: pendingAction.value.to },
        {
            preserveScroll: true,
            onSuccess: () => { pendingAction.value = null; },
            onFinish: () => { transitioning.value = false; },
        },
    );
};

const showCreate = ref(false);
const poForm = useForm({
    client_id: '',
    priority: 'normal',
    delivery_date: '',
    expected_ship_date: '',
    discount_amount: 0,
    notes: '',
    items: [{ product_id: '', quantity: 1, unit_price: 0 }],
});

const poSubtotal = computed(() => poForm.items.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_price || 0), 0));
const poTotal = computed(() => Math.max(0, poSubtotal.value - Number(poForm.discount_amount || 0)));
const addLine = () => poForm.items.push({ product_id: '', quantity: 1, unit_price: 0 });

const submitPo = () => poForm.post(route('ord.orders.store'), {
    preserveScroll: true,
    onSuccess: () => {
        showCreate.value = false;
        poForm.reset();
    },
});
</script>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.modal-enter-active { transition: opacity 0.25s ease, transform 0.32s cubic-bezier(0.22,1,0.36,1); }
.modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(18px) scale(0.97); }
</style>
