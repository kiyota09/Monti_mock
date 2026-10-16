<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Truck, Package, X, CheckCircle, ChevronDown, 
    Eye, ClipboardList, Search, Calendar, User, 
    Info, Hash, ArrowDownToLine, AlertCircle, Loader2
} from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditReceiving = computed(() => canEdit('WAR', 'receiving'));

const props = defineProps({
    receivings: { type: Array, default: () => [] },
    pendingPOs: { type: Array, default: () => [] },
    materials: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    auth: Object,
});

// Safe computed properties
const safePendingPOs = computed(() => props.pendingPOs ?? []);
const safeReceivings = computed(() => props.receivings ?? []);
const safeWarehouses = computed(() => props.warehouses ?? []);

// UI State
const showReceiveModal = ref(false);
const showPastReceivings = ref(false);
const showViewModal = ref(false); 
const selectedPO = ref(null);
const selectedReceiving = ref(null); 
const searchQuery = ref('');

// Form for receiving
const receiveForm = useForm({
    warehouse_id: '',
    po_id: null,
    items: [],
});

/**
 * Extracts material name from PO structure
 */
const getPOMaterialDisplay = (po) => {
    if (!po.items || po.items.length === 0) return 'No Materials';
    const firstItem = po.items[0];
    const name = firstItem.material?.name || firstItem.material_name || 'Unknown Item';
    return po.items.length > 1 ? `${name} (+${po.items.length - 1} more)` : name;
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-PH', { 
        dateStyle: 'medium', 
        timeStyle: 'short' 
    });
};

const statusBadge = (status) => {
    const styles = {
        pending: 'bg-amber-100 text-amber-700',
        partial: 'bg-blue-100 text-blue-700',
        completed: 'bg-emerald-100 text-emerald-700',
    };
    return styles[status] || 'bg-gray-100 text-gray-700';
};

const selectPO = (po) => {
    selectedPO.value = po;
    receiveForm.po_id = po.id;
    receiveForm.warehouse_id = safeWarehouses.value.length > 0 ? safeWarehouses.value[0].id : '';
    
    receiveForm.items = po.items.map(item => ({
        material_id: item.material_id,
        material_name: item.material?.name || item.material_name,
        expected_qty: parseFloat(item.qty),
        received_qty: parseFloat(item.qty),
        rejected_qty: 0,
        reject_reason: '',
        unit: item.unit,
    }));
    
    showReceiveModal.value = true;
};

const openViewModal = (rec) => {
    selectedReceiving.value = rec;
    showViewModal.value = true;
};

const updateReceivedQty = (index, value) => {
    const item = receiveForm.items[index];
    let received = parseFloat(value) || 0;
    if (received > item.expected_qty) received = item.expected_qty;
    item.received_qty = received;
    item.rejected_qty = item.expected_qty - received;
};

const submitReceive = () => {
    if (!receiveForm.warehouse_id) return alert('Please select a destination warehouse.');
    
    receiveForm.post(route('warehouse.receiving.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showReceiveModal.value = false;
            receiveForm.reset();
        },
        onError: () => alert('Error processing receipt.')
    });
};

const filteredReceivings = computed(() => {
    if (!searchQuery.value) return safeReceivings.value;
    const q = searchQuery.value.toLowerCase();
    return safeReceivings.value.filter(rec =>
        rec.receiving_number?.toLowerCase().includes(q) ||
        (rec.purchase_order?.po_number || '').toLowerCase().includes(q)
    );
});
</script>

<template>
    <Head title="Receiving & Logistics" />
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
                            <Truck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <ArrowDownToLine class="h-3.5 w-3.5" /> Warehouse · Inbound
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Receiving Unit <span v-if="!canEditReceiving" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">Manage incoming supply deliveries and store as unique lots</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ safePendingPOs.length }} awaiting
                            </span>
                            <span class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">{{ safeReceivings.length }} logged</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">

                    <!-- Awaiting arrivals -->
                    <div class="animate-fade-up relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-white shadow-lg"><Truck class="w-4 h-4" /></span>
                                <h2 class="text-sm font-black uppercase tracking-wide text-gray-900 dark:text-white">Awaiting Arrivals</h2>
                            </div>
                            <span class="rounded-full bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30 px-3 py-1 text-[10px] font-black">{{ safePendingPOs.length }} ORDERS</span>
                        </div>

                        <div v-if="safePendingPOs.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="p-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-3 animate-bounce-soft">
                                <Package class="h-8 w-8 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">Queue Clear</p>
                            <p class="text-xs text-gray-400 mt-1">No pending purchase orders.</p>
                        </div>

                        <TransitionGroup v-else name="card" tag="div" class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <div v-for="(po, i) in safePendingPOs" :key="po.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group relative p-5 hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors overflow-hidden">
                                <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase">{{ po.po_number }}</span>
                                            <span :class="['text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex items-center gap-1', statusBadge(po.status)]">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ po.status }}
                                            </span>
                                        </div>
                                        <p class="text-base font-black text-gray-900 dark:text-white leading-tight uppercase truncate">{{ getPOMaterialDisplay(po) }}</p>
                                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 flex items-center gap-1.5"><User class="w-3 h-3" /> {{ po.supplier_name }}</p>
                                    </div>
                                    <button v-if="canEditReceiving" @click="selectPO(po)" class="flex-shrink-0 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-[10px] font-black uppercase tracking-wider rounded-2xl hover:opacity-90 hover:scale-105 active:scale-95 transition-all shadow-lg shadow-indigo-500/25">
                                        Receive
                                    </button>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>

                    <!-- Receiving history -->
                    <div class="animate-fade-up relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden" style="animation-delay: 80ms">
                        <button @click="showPastReceivings = !showPastReceivings" class="w-full px-6 py-5 flex items-center justify-between hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><ClipboardList class="w-4 h-4" /></span>
                                <h2 class="text-sm font-black uppercase tracking-wide text-gray-900 dark:text-white">Receiving History</h2>
                            </div>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 transition-transform duration-300" :class="showPastReceivings ? 'rotate-180' : ''"><ChevronDown class="w-4 h-4" /></span>
                        </button>

                        <div v-show="showPastReceivings" class="border-t border-gray-100 dark:border-zinc-800">
                            <div class="p-4">
                                <div class="relative">
                                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                                    <input v-model="searchQuery" type="text" placeholder="Search PO # or Rec #..." class="w-full pl-10 pr-4 py-2.5 text-sm font-medium bg-slate-50 dark:bg-zinc-800 border-0 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 outline-none text-slate-700 dark:text-slate-200 placeholder:text-gray-400" />
                                </div>
                            </div>

                            <TransitionGroup name="card" tag="div" class="divide-y divide-gray-100 dark:divide-zinc-800 max-h-[500px] overflow-y-auto">
                                <div v-for="(rec, i) in filteredReceivings" :key="rec.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group relative p-5 hover:bg-indigo-50/40 dark:hover:bg-indigo-950/20 transition-colors flex items-center justify-between overflow-hidden">
                                    <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                                    <div class="space-y-1 min-w-0">
                                        <span class="font-mono text-xs font-black text-slate-400 uppercase tracking-tighter flex items-center gap-1.5"><Hash class="w-3 h-3" />{{ rec.receiving_number }}</span>
                                        <p class="text-sm font-black text-gray-900 dark:text-white">PO: <span class="text-indigo-600 dark:text-indigo-400">{{ rec.purchase_order?.po_number || 'N/A' }}</span></p>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><Calendar class="w-3.5 h-3.5" /> {{ formatDate(rec.received_at) }}</p>
                                    </div>
                                    <button @click="openViewModal(rec)" class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                                        <Eye class="w-4 h-4" />
                                    </button>
                                </div>
                            </TransitionGroup>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showViewModal && selectedReceiving" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showViewModal = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-8 py-6 text-white">
                            <div class="absolute -top-12 -right-12 h-40 w-40 rounded-full bg-white/10 blur-3xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-black tracking-tight">Receiving Log Details</h3>
                                    <p class="text-[10px] font-bold text-blue-100 uppercase tracking-[0.2em] mt-1">LOG REF: {{ selectedReceiving.receiving_number }}</p>
                                </div>
                                <button @click="showViewModal = false" class="p-2 rounded-xl bg-white/15 ring-1 ring-white/25 hover:bg-white/25 transition"><X class="w-5 h-5" /></button>
                            </div>
                        </div>

                        <div class="p-6 sm:p-8 space-y-6 overflow-y-auto max-h-[70vh]">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 bg-slate-50 dark:bg-zinc-800 rounded-2xl border border-gray-100 dark:border-zinc-700">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Received At</p>
                                    <p class="text-sm font-bold mt-1 text-slate-800 dark:text-slate-200">{{ formatDate(selectedReceiving.received_at) }}</p>
                                </div>
                                <div class="p-4 bg-slate-50 dark:bg-zinc-800 rounded-2xl border border-gray-100 dark:border-zinc-700">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Warehouse</p>
                                    <p class="text-sm font-bold mt-1 text-slate-800 dark:text-slate-200">{{ selectedReceiving.warehouse?.name }}</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Received Items Breakdown</p>
                                <div v-for="item in selectedReceiving.items" :key="item.id" class="p-5 rounded-2xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 flex items-center justify-between shadow-sm hover:shadow-lg hover:shadow-indigo-500/10 hover:-translate-y-0.5 transition-all">
                                    <div class="min-w-0">
                                        <p class="font-black uppercase text-sm leading-tight text-slate-800 dark:text-slate-100 truncate">{{ item.material_name }}</p>
                                        <p class="text-[10px] text-slate-500 font-bold uppercase mt-1 flex items-center gap-1"><Info class="w-3 h-3" /> Expected: {{ item.expected_qty }} {{ item.unit }}</p>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-3">
                                        <p class="text-xl font-black text-emerald-600 dark:text-emerald-400">+{{ item.received_qty }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 sm:px-8 py-5 border-t border-gray-100 dark:border-zinc-800 flex justify-end bg-slate-50/60 dark:bg-zinc-800/40">
                            <button @click="showViewModal = false" class="px-10 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-[10px] font-black uppercase rounded-2xl transition tracking-[0.2em] shadow-lg shadow-indigo-500/25 hover:opacity-90 active:scale-95">Close Record</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showReceiveModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showReceiveModal = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">

                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-8 py-6 text-white flex-shrink-0">
                            <div class="absolute -top-12 -right-12 h-40 w-40 rounded-full bg-white/10 blur-3xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                            <div class="relative flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-black tracking-tight">Inventory Check-In</h3>
                                    <p class="text-xs font-bold text-blue-100 mt-1 uppercase tracking-widest">Verification for PO: <span class="text-white">{{ selectedPO?.po_number }}</span></p>
                                </div>
                                <button @click="showReceiveModal = false" class="p-2 rounded-xl bg-white/15 ring-1 ring-white/25 hover:bg-white/25 transition"><X class="w-5 h-5" /></button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-6 sm:p-8 space-y-6">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Destination Warehouse *</label>
                                <select v-model="receiveForm.warehouse_id" class="w-full pl-4 pr-10 py-3.5 text-sm font-bold bg-slate-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl focus:ring-2 focus:ring-indigo-500/30 outline-none text-slate-700 dark:text-slate-200">
                                    <option value="" disabled>Select a location...</option>
                                    <option v-for="wh in safeWarehouses" :key="wh.id" :value="wh.id">{{ wh.name }} ({{ wh.location }})</option>
                                </select>
                            </div>

                            <div class="space-y-4">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Verify Material Quantities</p>

                                <div v-for="(item, idx) in receiveForm.items" :key="idx" class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-lg hover:shadow-indigo-500/10 transition-all flex flex-col md:flex-row md:items-center gap-5">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-base font-black text-slate-800 dark:text-white uppercase tracking-tight truncate">{{ item.material_name }}</p>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Expected Qty: {{ item.expected_qty }} {{ item.unit }}</p>
                                    </div>

                                    <div class="flex gap-3 flex-shrink-0 flex-wrap">
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black text-emerald-500 uppercase tracking-tighter ml-1">Received</label>
                                            <input type="number" v-model.number="item.received_qty" @input="updateReceivedQty(idx, $event.target.value)" min="0" :max="item.expected_qty" class="w-28 px-4 py-3 text-sm font-black bg-emerald-50 dark:bg-emerald-900/20 border-0 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none text-slate-800 dark:text-slate-100" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black text-red-500 uppercase tracking-tighter ml-1">Rejected</label>
                                            <input type="number" :value="item.expected_qty - item.received_qty" readonly class="w-28 px-4 py-3 text-sm font-black bg-red-50 dark:bg-red-900/20 border-0 rounded-2xl text-red-600 dark:text-red-400 outline-none" />
                                        </div>
                                        <div class="space-y-1 flex-1 min-w-[150px]" v-if="item.received_qty < item.expected_qty">
                                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-tighter ml-1">Reason</label>
                                            <input v-model="item.reject_reason" type="text" placeholder="Shortage reason..." class="w-full px-4 py-3 text-[11px] bg-slate-50 dark:bg-zinc-700 border-0 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none text-slate-700 dark:text-slate-200" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 sm:px-8 py-5 border-t border-gray-100 dark:border-zinc-800 flex flex-col sm:flex-row gap-3 bg-slate-50/60 dark:bg-zinc-800/40 flex-shrink-0">
                            <button @click="showReceiveModal = false" class="flex-1 py-3.5 text-xs font-black uppercase rounded-2xl border border-slate-200 dark:border-zinc-700 text-slate-500 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-800 transition-colors active:scale-95">Cancel</button>
                            <button v-if="canEditReceiving" @click="submitReceive" :disabled="receiveForm.processing || !receiveForm.warehouse_id" class="flex-[2] inline-flex items-center justify-center gap-2 py-3.5 text-xs font-black uppercase tracking-[0.2em] rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white hover:opacity-90 transition shadow-xl shadow-emerald-500/25 disabled:opacity-40 active:scale-95">
                                <CheckCircle class="w-4 h-4" />
                                {{ receiveForm.processing ? 'Processing...' : 'Finalize & Update Stock' }}
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
.modal-enter-active { transition: opacity 0.3s ease; }
.modal-enter-active > div { transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { opacity: 0; transform: translateY(20px) scale(0.96); }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
