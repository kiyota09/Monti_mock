<template>
    <Head :title="`Invoice ${order.po_number}`" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-5xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <Link :href="route('client.invoices')"
                            class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/30 active:scale-95 transition">
                            <ArrowLeft class="h-5 w-5" />
                        </Link>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">Invoice detail</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight font-mono">{{ order.po_number }}</h1>
                            <p class="text-sm text-blue-100/90">Review every line, then accept and send your PO.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-3 py-1.5 text-xs font-black uppercase ring-1 backdrop-blur"
                                :class="order.status === 'approved' ? 'bg-emerald-400/90 text-emerald-950' : 'bg-amber-300/90 text-amber-950'">
                                {{ order.status === 'approved' ? 'Accepted' : 'Awaiting acceptance' }}
                            </span>
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ order.payment_status || 'Pending' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Amount summary -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5 shadow-sm">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Subtotal</p>
                        <p class="text-xl font-black mt-1">₱{{ fmt(order.subtotal) }}</p>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5 shadow-sm">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Discount</p>
                        <p class="text-xl font-black mt-1">₱{{ fmt(order.discount_amount) }}</p>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-indigo-200 dark:border-indigo-800 p-5 shadow-sm ring-1 ring-indigo-100 dark:ring-indigo-900">
                        <p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">Grand total</p>
                        <p class="text-xl font-black mt-1 text-indigo-600">₱{{ fmt(order.total_amount) }}</p>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-5 shadow-sm">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Line items</p>
                        <p class="text-xl font-black mt-1">{{ order.items.length }}</p>
                    </div>
                </div>

                <!-- Line items -->
                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <FileText class="w-5 h-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Invoice lines</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50/50 dark:bg-zinc-800/30 text-[10px] font-black uppercase text-gray-400 tracking-[0.15em]">
                                <tr>
                                    <th class="px-8 py-5">Product</th>
                                    <th class="px-8 py-5 text-right">Qty</th>
                                    <th class="px-8 py-5 text-right">Unit price</th>
                                    <th class="px-8 py-5 text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="item in order.items" :key="item.id" class="hover:bg-indigo-50/40 dark:hover:bg-indigo-900/10 transition-all">
                                    <td class="px-8 py-5 font-bold">{{ item.product_name }}</td>
                                    <td class="px-8 py-5 text-right font-medium">{{ item.quantity }}</td>
                                    <td class="px-8 py-5 text-right font-medium">₱{{ fmt(item.unit_price) }}</td>
                                    <td class="px-8 py-5 text-right font-black">₱{{ fmt(item.line_total) }}</td>
                                </tr>
                                <tr v-if="!order.items.length">
                                    <td colspan="4" class="px-8 py-12 text-center text-sm font-bold text-gray-400">No line items on this invoice.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Meta + actions -->
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-6 shadow-sm space-y-3 text-sm">
                        <h2 class="text-sm font-black uppercase tracking-widest mb-2">Order info</h2>
                        <div class="flex justify-between"><span class="text-gray-400 font-bold">Issued</span><span class="font-bold">{{ fmtDate(order.created_at) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-400 font-bold">Delivery</span><span class="font-bold">{{ order.delivery_date ? fmtDate(order.delivery_date) : '—' }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-400 font-bold">Due</span><span class="font-bold">{{ order.due_date ? fmtDate(order.due_date) : fmtDate(order.created_at) }}</span></div>
                        <p v-if="order.notes" class="pt-2 border-t border-gray-100 dark:border-zinc-800 text-gray-500 whitespace-pre-line">{{ order.notes }}</p>
                        <p v-if="order.attachment_path" class="pt-2 border-t border-gray-100 dark:border-zinc-800 text-xs font-bold text-emerald-600">PO document(s) attached ✓</p>
                    </div>

                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 p-6 shadow-sm space-y-3">
                        <h2 class="text-sm font-black uppercase tracking-widest mb-2">Your decision</h2>
                        <button v-if="order.status === 'pending_client_approval'" @click="showAcceptModal = true"
                            class="w-full py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-emerald-500/25 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2">
                            <Check class="h-4 w-4" /> Accept invoice
                        </button>
                        <div v-else class="w-full py-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 text-xs font-black uppercase tracking-widest text-center">
                            Accepted ✓
                        </div>
                        <button @click="showPoModal = true"
                            class="w-full py-3.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-indigo-500/25 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2">
                            <Send class="h-4 w-4" /> Send PO document
                        </button>
                        <p class="text-[11px] text-gray-400 font-medium">Accepting confirms the price. Sending PO uploads your signed purchase-order file(s).</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accept confirm modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showAcceptModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showAcceptModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-5 text-white">
                            <h3 class="font-black text-lg">Accept {{ order.po_number }}?</h3>
                            <p class="text-xs text-emerald-50 mt-1">Total ₱{{ fmt(order.total_amount) }} · {{ order.items.length }} line(s)</p>
                        </div>
                        <div class="p-6 flex gap-3">
                            <button @click="showAcceptModal = false" class="flex-1 py-3 rounded-xl bg-gray-100 dark:bg-zinc-800 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                            <button @click="accept" :disabled="accepting" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-black text-sm shadow-lg disabled:opacity-50 hover:scale-[1.02] active:scale-95 transition-all">
                                {{ accepting ? 'Accepting…' : 'Confirm accept' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Send PO modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showPoModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showPoModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-5 text-white flex justify-between items-center">
                            <h3 class="font-black text-lg">Send PO document</h3>
                            <button @click="showPoModal = false"><X class="h-5 w-5" /></button>
                        </div>
                        <div class="p-6 space-y-4">
                            <input ref="poInput" type="file" multiple accept=".jpg,.jpeg,.png,.pdf" @change="onFiles" class="hidden" />
                            <button @click="poInput.click()" class="w-full py-4 rounded-2xl border-2 border-dashed border-indigo-300 dark:border-indigo-700 text-sm font-black text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition active:scale-95">
                                {{ poFiles.length ? `${poFiles.length} file(s) selected` : 'Choose JPG / PNG / PDF files' }}
                            </button>
                            <ul v-if="poFiles.length" class="text-xs font-bold text-gray-500 space-y-1">
                                <li v-for="(f, i) in poFiles" :key="i" class="truncate">· {{ f.name }}</li>
                            </ul>
                            <textarea v-model="poNotes" rows="2" maxlength="500" placeholder="Notes (optional)…"
                                class="w-full px-4 py-3 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 outline-none text-sm resize-none"></textarea>
                            <p v-if="poError" class="text-xs font-bold text-rose-500">{{ poError }}</p>
                            <div class="flex gap-3">
                                <button @click="showPoModal = false" class="flex-1 py-3 rounded-xl bg-gray-100 dark:bg-zinc-800 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button @click="sendPO" :disabled="sending || !poFiles.length" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-black text-sm shadow-lg disabled:opacity-50 hover:scale-[1.02] active:scale-95 transition-all">
                                    {{ sending ? 'Sending…' : 'Send PO' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Transition name="toast">
            <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-xl text-white font-bold text-sm ring-1 ring-white/20"
                :class="toast.type === 'success' ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-gradient-to-r from-rose-600 to-red-600'">
                {{ toast.message }}
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, FileText, Check, Send, X } from 'lucide-vue-next';

const props = defineProps({ order: Object });

const showAcceptModal = ref(false);
const showPoModal = ref(false);
const accepting = ref(false);
const sending = ref(false);
const poInput = ref(null);
const poFiles = ref([]);
const poNotes = ref('');
const poError = ref('');
const toast = ref({ show: false, type: 'success', message: '' });

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3500);
};

const fmt = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';

const accept = () => {
    if (accepting.value) return;
    accepting.value = true;
    router.post(route('client.invoices.accept', props.order.id), {}, {
        preserveScroll: true,
        onSuccess: () => { showAcceptModal.value = false; showToast('success', 'Invoice accepted.'); },
        onError: (e) => showToast('error', e.error || 'Could not accept invoice.'),
        onFinish: () => { accepting.value = false; },
    });
};

const onFiles = (e) => {
    poError.value = '';
    poFiles.value = Array.from(e.target.files || []);
};

const sendPO = () => {
    if (sending.value || !poFiles.value.length) return;
    sending.value = true;
    poError.value = '';
    const data = new FormData();
    poFiles.value.forEach((f) => data.append('po_files[]', f));
    if (poNotes.value) data.append('notes', poNotes.value);
    router.post(route('client.invoices.send-po', props.order.id), data, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showPoModal.value = false;
            poFiles.value = [];
            poNotes.value = '';
            showToast('success', 'PO document(s) sent.');
        },
        onError: (e) => { poError.value = e.po_files || e.error || 'Could not send PO.'; },
        onFinish: () => { sending.value = false; },
    });
};
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
