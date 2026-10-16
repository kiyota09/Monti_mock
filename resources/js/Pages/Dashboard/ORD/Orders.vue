<template>
    <AuthenticatedLayout>
        <Head title="Orders Worklist" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <ClipboardList class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> ORD · Order-to-Cash
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Orders Worklist</h1>
                            <p class="text-sm text-blue-100/90">Client purchase orders and mill job orders in one pipeline</p>
                        </div>
                        <button v-if="canEditOrders" @click="showCreate = true" class="rounded-2xl bg-white px-4 py-2.5 text-sm font-black text-indigo-700 shadow-lg transition-all hover:bg-blue-50 active:scale-95">
                            + New PO
                        </button>
                        <span v-else class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25">View only</span>
                    </div>
                    <!-- Group tabs -->
                    <div class="relative mt-6 flex flex-wrap gap-2">
                        <button
                            v-for="(label, key) in groups"
                            :key="key"
                            @click="setGroup(key)"
                            class="rounded-full px-3.5 py-1.5 text-xs font-black ring-1 backdrop-blur transition-all active:scale-95"
                            :class="filters.group === key ? 'bg-white text-indigo-700 ring-white shadow' : 'bg-white/15 text-white ring-white/25 hover:bg-white/25'"
                        >
                            {{ label }} · {{ groupCounts[key] ?? (key === 'all' ? orders.length : 0) }}
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="animate-fade-up flex flex-wrap items-center gap-2" style="animation-delay: 80ms">
                    <div class="relative flex-1 min-w-[12rem]">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input v-model="local.search" @keyup.enter="applyFilters" placeholder="Search PO / JO / control no…" class="w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 pl-9 pr-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400" />
                    </div>
                    <select v-model="local.client_id" @change="applyFilters" class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm font-medium">
                        <option value="">All clients</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                    </select>
                    <select v-model="local.status" @change="applyFilters" class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm font-medium">
                        <option value="">All statuses</option>
                        <optgroup label="Sales orders"><option v-for="s in soStatuses" :key="s" :value="s">{{ formatStatus(s) }}</option></optgroup>
                        <optgroup label="Purchase orders"><option v-for="s in poStatuses" :key="s" :value="s">{{ formatStatus(s) }}</option></optgroup>
                    </select>
                    <button @click="clearFilters" class="rounded-2xl border border-gray-200 dark:border-zinc-700 px-3 py-2.5 text-sm font-bold text-gray-500 hover:bg-gray-50">Reset</button>
                </div>

                <!-- Table -->
                <div class="animate-fade-up overflow-hidden rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur shadow-sm" style="animation-delay: 140ms">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[52rem] text-sm">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100 dark:border-zinc-800">
                                    <th class="px-4 py-3">Document</th>
                                    <th class="px-4 py-3">Client</th>
                                    <th class="px-4 py-3">Detail</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th class="px-4 py-3">Expected</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="o in orders" :key="`${o.type}-${o.id}`" class="border-b border-gray-50 dark:border-zinc-800/60 hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-lg px-2 py-0.5 text-[10px] font-black" :class="o.type === 'PO' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'">{{ o.type }}</span>
                                            <span class="font-black">{{ o.number }}</span>
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-0.5">{{ o.created_at }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ o.client_name }}</td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">{{ o.type === 'SO' ? `${o.product_name} · ${o.quantity} kg` : `${o.group === 'intake' ? 'Pending review' : formatStatus(o.status)}` }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase border" :class="statusBadge(o.status)">{{ formatStatus(o.status) }}</span>
                                        <span v-if="o.priority !== 'normal'" class="ml-1 rounded-full px-2 py-1 text-[10px] font-black uppercase bg-red-100 text-red-700">{{ o.priority }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black">₱{{ Number(o.total).toLocaleString() }}</td>
                                    <td class="px-4 py-3 text-xs" :class="isOverdue(o) ? 'text-red-600 font-black' : 'text-gray-500'">{{ o.expected_ship_date || '—' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1">
                                            <Link :href="route('ord.orders.show', { type: o.type.toLowerCase(), id: o.id })" class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-black text-white hover:bg-indigo-500">Open</Link>
                                            <button v-if="canEditOrders && (o.transitions.includes('confirmed') || o.transitions.includes('approved'))" @click="quickTransition(o, o.type === 'PO' ? 'approved' : 'confirmed')" class="rounded-xl bg-green-600 px-3 py-1.5 text-xs font-black text-white hover:bg-green-500">Confirm</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="orders.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">No orders in this view.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Create PO modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreate" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/40 backdrop-blur-sm" @click.self="showCreate = false">
                    <form @submit.prevent="submitPo" class="bg-white dark:bg-zinc-900 w-full sm:max-w-2xl rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-zinc-800">
                            <h3 class="font-black text-lg">New client purchase order</h3>
                            <button type="button" @click="showCreate = false" class="rounded-xl p-1.5 hover:bg-gray-100"><X class="h-5 w-5" /></button>
                        </div>
                        <div class="p-5 space-y-4 overflow-y-auto">
                            <div class="grid sm:grid-cols-2 gap-3">
                                <label class="block text-sm font-bold">Client
                                    <select v-model="poForm.client_id" required class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-medium">
                                        <option value="">Select client…</option>
                                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                                    </select>
                                </label>
                                <label class="block text-sm font-bold">Priority
                                    <select v-model="poForm.priority" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-medium">
                                        <option value="normal">Normal</option><option value="rush">Rush</option><option value="urgent">Urgent</option>
                                    </select>
                                </label>
                                <label class="block text-sm font-bold">Delivery date
                                    <input v-model="poForm.delivery_date" type="date" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm" />
                                </label>
                                <label class="block text-sm font-bold">Expected ship date
                                    <input v-model="poForm.expected_ship_date" type="date" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm" />
                                </label>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm font-black">Line items</p>
                                    <button type="button" @click="addLine" class="text-xs font-black text-indigo-600 hover:underline">+ Add line</button>
                                </div>
                                <div v-for="(line, i) in poForm.items" :key="i" class="grid grid-cols-12 gap-2 mb-2">
                                    <select v-model="line.product_id" required class="col-span-6 rounded-xl border px-2 py-2 text-sm">
                                        <option value="">Product…</option>
                                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                    <input v-model.number="line.quantity" type="number" min="1" required placeholder="Qty" class="col-span-2 rounded-xl border px-2 py-2 text-sm" />
                                    <input v-model.number="line.unit_price" type="number" min="0" step="0.01" required placeholder="Price" class="col-span-3 rounded-xl border px-2 py-2 text-sm" />
                                    <button type="button" @click="poForm.items.splice(i, 1)" class="col-span-1 text-red-500 font-black">×</button>
                                </div>
                            </div>
                            <label class="block text-sm font-bold">Notes
                                <textarea v-model="poForm.notes" rows="2" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm"></textarea>
                            </label>
                            <p v-if="poForm.errors.items" class="text-xs text-red-600">{{ poForm.errors.items }}</p>
                        </div>
                        <div class="px-5 py-4 border-t border-gray-100 dark:border-zinc-800 flex justify-end gap-2">
                            <button type="button" @click="showCreate = false" class="rounded-xl border px-4 py-2 text-sm font-bold">Cancel</button>
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
import { ClipboardList, Search, Sparkles, X } from 'lucide-vue-next';

const props = defineProps({
    orders: Array,
    filters: Object,
    groups: Object,
    soStatuses: Array,
    poStatuses: Array,
    clients: Array,
    products: Array,
});

const { canEdit } = usePageAccess();
const canEditOrders = computed(() => canEdit('ORD', 'orders'));

const local = ref({ group: props.filters.group || 'all', status: props.filters.status || '', client_id: props.filters.client_id || '', search: props.filters.search || '' });

const groupCounts = computed(() => {
    const counts = { all: props.orders.length };
    for (const o of props.orders) counts[o.group] = (counts[o.group] || 0) + 1;
    return counts;
});

const setGroup = (key) => { local.value.group = key; applyFilters(); };
const applyFilters = () => router.get(route('ord.orders'), { ...local.value }, { preserveState: true, preserveScroll: true });
const clearFilters = () => router.get(route('ord.orders'), { group: 'all' });

const quickTransition = (o, to) => {
    if (!confirm(`Move ${o.number} to ${to}?`)) return;
    router.post(route('ord.orders.transition', { type: o.type.toLowerCase(), id: o.id }), { to }, { preserveScroll: true });
};

const isOverdue = (o) => o.expected_ship_date && !['completed', 'cancelled', 'delivered'].includes(o.status) && o.expected_ship_date < new Date().toISOString().slice(0, 10);

const statusBadge = (s) => ({
    pending: 'bg-slate-100 text-slate-600 border-slate-200',
    credit_review: 'bg-slate-100 text-slate-600 border-slate-200',
    confirmed: 'bg-violet-100 text-violet-700 border-violet-200',
    approved: 'bg-violet-100 text-violet-700 border-violet-200',
    in_planning: 'bg-purple-100 text-purple-700 border-purple-200',
    in_production: 'bg-blue-100 text-blue-700 border-blue-200',
    production_done: 'bg-cyan-100 text-cyan-700 border-cyan-200',
    ready_for_dispatch: 'bg-cyan-100 text-cyan-700 border-cyan-200',
    in_transit: 'bg-indigo-100 text-indigo-700 border-indigo-200',
    delivered: 'bg-green-100 text-green-700 border-green-200',
    completed: 'bg-green-100 text-green-700 border-green-200',
    on_hold: 'bg-amber-100 text-amber-700 border-amber-200',
    returned: 'bg-red-100 text-red-700 border-red-200',
    cancelled: 'bg-gray-100 text-gray-500 border-gray-200',
}[s] || 'bg-gray-100 text-gray-600 border-gray-200');

const formatStatus = (s) => String(s || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

// Create PO form
const showCreate = ref(false);
const poForm = useForm({ client_id: '', priority: 'normal', delivery_date: '', expected_ship_date: '', notes: '', items: [{ product_id: '', quantity: 1, unit_price: 0 }] });
const addLine = () => poForm.items.push({ product_id: '', quantity: 1, unit_price: 0 });
const submitPo = () => poForm.post(route('ord.orders.store'), { onSuccess: () => { showCreate.value = false; poForm.reset(); } });
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
