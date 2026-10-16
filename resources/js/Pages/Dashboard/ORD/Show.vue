<template>
    <AuthenticatedLayout>
        <Head :title="`${orderType} ${order.number}`" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <Link :href="route('ord.orders')" class="inline-flex items-center gap-1 text-sm font-bold text-indigo-600 hover:underline">
                    <ChevronLeft class="h-4 w-4" /> Back to worklist
                </Link>

                <!-- Header card -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-start gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">{{ orderType === 'PO' ? 'Client purchase order' : 'Mill job order' }}</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">{{ order.number }}</h1>
                            <p class="text-sm text-blue-100/90">{{ order.client_name }} · ₱{{ Number(order.total).toLocaleString() }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <span class="rounded-full bg-white/95 px-3 py-1.5 text-xs font-black uppercase text-indigo-700">{{ formatStatus(order.status) }}</span>
                            <span v-if="order.priority !== 'normal'" class="rounded-full bg-red-500/90 px-3 py-1 text-[10px] font-black uppercase">{{ order.priority }}</span>
                        </div>
                    </div>
                    <!-- meta grid -->
                    <div class="relative mt-5 grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                        <div class="rounded-2xl bg-white/15 px-3 py-2 ring-1 ring-white/25"><p class="text-[10px] uppercase tracking-widest text-blue-100 font-bold">Expected ship</p><p class="font-black">{{ order.expected_ship_date || '—' }}</p></div>
                        <div class="rounded-2xl bg-white/15 px-3 py-2 ring-1 ring-white/25"><p class="text-[10px] uppercase tracking-widest text-blue-100 font-bold">Payment</p><p class="font-black">{{ formatStatus(order.payment_status) }}</p></div>
                        <div class="rounded-2xl bg-white/15 px-3 py-2 ring-1 ring-white/25"><p class="text-[10px] uppercase tracking-widest text-blue-100 font-bold">Pipeline</p><p class="font-black">{{ formatStatus(order.group) }}</p></div>
                        <div class="rounded-2xl bg-white/15 px-3 py-2 ring-1 ring-white/25"><p class="text-[10px] uppercase tracking-widest text-blue-100 font-bold">Created</p><p class="font-black">{{ order.created_at }}</p></div>
                    </div>
                    <!-- transition actions -->
                    <div v-if="canEditOrders && transitions.length" class="relative mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="t in transitions"
                            :key="t"
                            @click="openTransition(t)"
                            class="rounded-xl px-3.5 py-2 text-xs font-black shadow transition-all hover:brightness-110 active:scale-95"
                            :class="t === 'cancelled' ? 'bg-red-500 text-white' : t === 'on_hold' ? 'bg-amber-400 text-amber-950' : 'bg-white text-indigo-700'"
                        >
                            {{ transitionLabel(t) }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <!-- Lines -->
                    <div class="xl:col-span-2 space-y-4">
                        <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                            <h2 class="font-black mb-3">{{ orderType === 'PO' ? 'Line items' : 'Specification' }}</h2>
                            <table class="w-full text-sm">
                                <thead><tr class="text-left text-[10px] uppercase tracking-widest text-gray-400 border-b">
                                    <th class="py-2">Item</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Unit price</th><th class="py-2 text-right">Total</th>
                                </tr></thead>
                                <tbody>
                                    <tr v-for="l in lines" :key="l.id" class="border-b border-gray-50 dark:border-zinc-800/60 last:border-0">
                                        <td class="py-2.5 font-medium">{{ l.product }}</td>
                                        <td class="py-2.5 text-right">{{ l.quantity }}</td>
                                        <td class="py-2.5 text-right">₱{{ Number(l.unit_price).toLocaleString() }}</td>
                                        <td class="py-2.5 text-right font-black">₱{{ Number(l.line_total).toLocaleString() }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-if="orderType === 'SO'" class="mt-3 grid sm:grid-cols-3 gap-2 text-xs">
                                <div class="rounded-xl bg-slate-50 dark:bg-zinc-800 p-2.5"><p class="font-bold text-gray-400 uppercase text-[10px]">Yarn</p><p class="font-bold">{{ order.yarn_type || '—' }}</p></div>
                                <div class="rounded-xl bg-slate-50 dark:bg-zinc-800 p-2.5"><p class="font-bold text-gray-400 uppercase text-[10px]">Color</p><p class="font-bold">{{ order.color || '—' }}</p></div>
                                <div class="rounded-xl bg-slate-50 dark:bg-zinc-800 p-2.5"><p class="font-bold text-gray-400 uppercase text-[10px]">Design</p><p class="font-bold">{{ order.design || '—' }}</p></div>
                            </div>
                            <div v-if="orderType === 'SO' && order.recipe" class="mt-2 rounded-xl bg-violet-50 dark:bg-violet-500/10 border border-violet-200 p-3 text-xs">
                                <p class="font-black text-violet-700 mb-1">Linked BOM recipe #{{ order.recipe.id }}</p>
                                <p>{{ order.recipe.yarn_type }} · {{ order.recipe.dye_color }} · {{ order.recipe.weave_design }}</p>
                            </div>
                            <div v-if="orderType === 'SO' && order.manufacturing" class="mt-2 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 p-3 text-xs">
                                <p class="font-black text-blue-700">Manufacturing order #{{ order.manufacturing.id }} — {{ formatStatus(order.manufacturing.status) }}</p>
                                <p>{{ order.manufacturing.total - order.manufacturing.remaining }} / {{ order.manufacturing.total }} kg processed</p>
                            </div>
                        </div>

                        <!-- Generate SO (PO only) -->
                        <div v-if="orderType === 'PO' && canEditOrders" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                            <h2 class="font-black mb-1">Generate job orders</h2>
                            <p class="text-xs text-gray-500 mb-3">Explode PO lines into mill job orders (yarn / color / qty per line).</p>
                            <form @submit.prevent="submitGenerate" class="space-y-2">
                                <div v-for="(g, i) in genForm.lines" :key="i" class="grid grid-cols-2 sm:grid-cols-6 gap-2 rounded-2xl border border-gray-100 dark:border-zinc-800 p-2.5">
                                    <input v-model="g.yarn_type" required placeholder="Yarn type" class="rounded-xl border px-2 py-1.5 text-xs sm:col-span-2" />
                                    <input v-model="g.color" required placeholder="Color" class="rounded-xl border px-2 py-1.5 text-xs sm:col-span-2" />
                                    <input v-model.number="g.quantity" type="number" min="0.01" step="0.01" required placeholder="Qty kg" class="rounded-xl border px-2 py-1.5 text-xs" />
                                    <input v-model.number="g.unit_price" type="number" min="0" step="0.01" required placeholder="Unit ₱" class="rounded-xl border px-2 py-1.5 text-xs" />
                                    <input v-model="g.design" placeholder="Design (opt)" class="rounded-xl border px-2 py-1.5 text-xs sm:col-span-3" />
                                    <input v-model="g.expected_ship_date" type="date" class="rounded-xl border px-2 py-1.5 text-xs sm:col-span-2" />
                                    <button type="button" @click="genForm.lines.splice(i, 1)" class="text-red-500 font-black text-sm">×</button>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="genForm.lines.push({ yarn_type: '', color: '', quantity: 1, unit_price: 0, design: '', expected_ship_date: '' })" class="text-xs font-black text-indigo-600 hover:underline">+ Add line</button>
                                    <button type="submit" :disabled="genForm.processing || !genForm.lines.length" class="ml-auto rounded-xl bg-violet-600 px-4 py-2 text-xs font-black text-white hover:bg-violet-500 disabled:opacity-50">Generate job orders</button>
                                </div>
                            </form>
                        </div>

                        <!-- Payment -->
                        <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                            <h2 class="font-black mb-3">Billing & payment</h2>
                            <form v-if="canEditOrders" @submit.prevent="submitPayment" class="flex flex-wrap items-end gap-2">
                                <label class="text-xs font-bold">Status
                                    <select v-model="payForm.payment_status" class="ml-1 rounded-xl border px-2 py-1.5 text-sm">
                                        <option value="unpaid">Unpaid</option><option value="partially_paid">Partially paid</option><option value="paid">Paid</option>
                                    </select>
                                </label>
                                <label class="text-xs font-bold">Receipt
                                    <input type="file" @input="payForm.receipt = $event.target.files[0]" accept=".jpg,.jpeg,.png,.pdf" class="ml-1 text-xs" />
                                </label>
                                <button type="submit" :disabled="payForm.processing" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white hover:bg-emerald-500 disabled:opacity-50">Post payment</button>
                            </form>
                            <a v-if="order.receipt_file" :href="route('ord.orders.download-receipt', { type: orderType.toLowerCase(), id: order.id })" class="mt-2 inline-block text-xs font-black text-indigo-600 hover:underline">Download receipt</a>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5">
                        <h2 class="font-black mb-3">Status history</h2>
                        <ol v-if="history.length" class="relative space-y-3 border-l-2 border-indigo-100 ml-1.5 pl-4">
                            <li v-for="(h, i) in history" :key="i" class="text-sm">
                                <p class="font-bold">{{ h.from ? `${formatStatus(h.from)} → ` : '' }}<span class="text-indigo-600">{{ formatStatus(h.to) }}</span></p>
                                <p class="text-xs text-gray-500">{{ h.by }} · {{ h.at }}</p>
                                <p v-if="h.notes" class="text-xs text-gray-500 italic">{{ h.notes }}</p>
                            </li>
                        </ol>
                        <p v-else class="text-sm text-gray-400">No audited transitions yet — new moves from this screen are recorded.</p>
                        <div v-if="returns?.length" class="mt-4">
                            <h3 class="font-black text-sm mb-2">Returns</h3>
                            <div v-for="r in returns" :key="r.return_number" class="rounded-xl border p-2.5 text-xs mb-1.5">
                                <p class="font-black">{{ r.return_number }} · {{ r.type }} · {{ r.status }}</p>
                                <p class="text-gray-500">{{ r.quantity }} kg — {{ r.reason }}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Transition modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="pendingTo" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/40 backdrop-blur-sm" @click.self="pendingTo = null">
                    <form @submit.prevent="submitTransition" class="bg-white dark:bg-zinc-900 w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl p-5">
                        <h3 class="font-black text-lg mb-1">{{ transitionLabel(pendingTo) }}</h3>
                        <p class="text-xs text-gray-500 mb-3">{{ order.number }} · current: {{ formatStatus(order.status) }}</p>
                        <textarea v-model="transForm.notes" rows="3" :placeholder="needsNotes ? 'Reason (required)' : 'Notes (optional)'" class="w-full rounded-xl border px-3 py-2 text-sm"></textarea>
                        <p v-if="notesError" class="mt-1 text-xs font-bold text-red-600">{{ notesError }}</p>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" @click="pendingTo = null" class="rounded-xl border px-4 py-2 text-sm font-bold">Cancel</button>
                            <button type="submit" :disabled="transForm.processing" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white disabled:opacity-50">Confirm</button>
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
import { ChevronLeft } from 'lucide-vue-next';

const props = defineProps({
    orderType: String,
    order: Object,
    lines: Array,
    transitions: Array,
    history: Array,
    returns: Array,
});

const { canEdit } = usePageAccess();
const canEditOrders = computed(() => canEdit('ORD', 'orders'));

const formatStatus = (s) => String(s || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const transitionLabel = (t) => ({
    confirmed: 'Confirm order', approved: 'Approve order', in_planning: 'Move to planning',
    in_production: 'Start production', production_done: 'Mark production done',
    ready_for_dispatch: 'Ready for dispatch', in_transit: 'Dispatch', delivered: 'Mark delivered',
    completed: 'Close order', on_hold: 'Put on hold', cancelled: 'Cancel order', returned: 'Mark returned',
    released_to_production: 'Release to production', pending_client_approval: 'Send for client approval',
}[t] || `Move to ${formatStatus(t)}`);

const pendingTo = ref(null);
const needsNotes = computed(() => ['on_hold', 'cancelled'].includes(pendingTo.value));
const transForm = useForm({ to: '', notes: '' });
const openTransition = (t) => { pendingTo.value = t; transForm.to = t; transForm.notes = ''; };
const notesError = ref('');
const submitTransition = () => {
    if (needsNotes.value && !transForm.notes.trim()) { notesError.value = 'A reason is required for this action.'; return; }
    notesError.value = '';
    transForm.post(route('ord.orders.transition', { type: props.orderType.toLowerCase(), id: props.order.id }), {
        onSuccess: () => { pendingTo.value = null; transForm.reset(); },
    });
};

const genForm = useForm({ lines: [{ yarn_type: '', color: '', quantity: 1, unit_price: 0, design: '', expected_ship_date: '' }] });
const submitGenerate = () => genForm.post(route('ord.orders.generate-so', { id: props.order.id }), { onSuccess: () => genForm.reset() });

const payForm = useForm({ order_id: props.order.id, type: props.orderType, payment_status: props.order.payment_status || 'unpaid', receipt: null });
const submitPayment = () => payForm.post(route('ord.orders.payment'), { forceFormData: true, preserveScroll: true });
</script>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.modal-enter-active { transition: opacity 0.25s ease, transform 0.32s cubic-bezier(0.22,1,0.36,1); }
.modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(18px) scale(0.97); }
</style>
