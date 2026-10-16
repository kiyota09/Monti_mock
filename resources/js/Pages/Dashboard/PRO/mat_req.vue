<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { 
    ClipboardList, Send, ArrowRight, X, CheckCircle, 
    Users, AlertTriangle, Clock, TrendingUp, SearchX, 
    Info, AlertCircle, Loader2, Sparkles, ChevronRight
} from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditRequests = computed(() => canEdit('PRO', 'requests'));

const props = defineProps({
    materialRequests: Array,
    warehouses: Array,
    suppliers: Array,
    stats: Object,
});

const today = new Date().toISOString().split('T')[0];

const totalRequests = computed(() => props.materialRequests.length);
const highUrgencyCount = computed(() => props.materialRequests.filter(r => r.urgency === 'High').length);
const mediumUrgencyCount = computed(() => props.materialRequests.filter(r => r.urgency === 'Medium').length);
const lowUrgencyCount = computed(() => props.materialRequests.filter(r => r.urgency === 'Low').length);

const urgencyData = computed(() => [
    { label: 'High', count: highUrgencyCount.value, color: 'bg-red-500' },
    { label: 'Medium', count: mediumUrgencyCount.value, color: 'bg-yellow-500' },
    { label: 'Low', count: lowUrgencyCount.value, color: 'bg-green-500' },
]);
const maxUrgency = computed(() => Math.max(highUrgencyCount.value, mediumUrgencyCount.value, lowUrgencyCount.value, 1));

const showRFQModal = ref(false);
const selectedRequest = ref(null);
const rfqForm = useForm({
    mr_id: null,
    deadline: '',
    payment_terms: 'Cash on delivery',
    notes: '',
    selected_suppliers: [],
});
const supplierStep = ref(false);

const showConfirmModal = ref(false);
const confirmConfig = ref({
    title: '',
    message: '',
    type: 'confirm',
    action: null
});

const triggerModalAlert = (title, message, type = 'alert', action = null) => {
    confirmConfig.value = { title, message, type, action };
    showConfirmModal.value = true;
};

const openRFQ = (req) => {
    selectedRequest.value = req;
    rfqForm.mr_id = req.id;
    rfqForm.deadline = '';
    rfqForm.payment_terms = 'Cash on delivery';
    rfqForm.notes = '';
    rfqForm.selected_suppliers = [];
    supplierStep.value = false;
    showAllSuppliers.value = false;
    showRFQModal.value = true;
};

// Suppliers carrying the requested material (exact match on their product
// catalog). The list defaults to matches only, with a show-all fallback.
const showAllSuppliers = ref(false);
const matchedSuppliers = computed(() => {
    const mid = Number(selectedRequest.value?.material_id);
    if (!mid) return props.suppliers || [];
    return (props.suppliers || []).filter((s) => (s.product_material_ids || []).map(Number).includes(mid));
});
const visibleSuppliers = computed(() => showAllSuppliers.value ? (props.suppliers || []) : matchedSuppliers.value);
const matchLabel = computed(() => selectedRequest.value?.material_name || 'this material');
const carries = (sup) => {
    const mid = Number(selectedRequest.value?.material_id);
    if (!mid) return true;
    return (sup.product_material_ids || []).map(Number).includes(mid);
};

const proceedToSuppliers = () => {
    if (!rfqForm.deadline) {
        triggerModalAlert('Missing Information', 'Please fill in the response deadline before proceeding.');
        return;
    }
    if (rfqForm.deadline < today) {
        triggerModalAlert('Invalid Date', 'The response deadline cannot be set to a past date.', 'alert');
        return;
    }
    supplierStep.value = true;
};

const toggleSupplier = (id) => {
    const idx = rfqForm.selected_suppliers.indexOf(id);
    if (idx === -1) rfqForm.selected_suppliers.push(id);
    else rfqForm.selected_suppliers.splice(idx, 1);
};

const submitRFQ = () => {
    if (rfqForm.selected_suppliers.length === 0) {
        triggerModalAlert('No Suppliers Selected', 'Please select at least one supplier to send the RFQ to.');
        return;
    }

    // Security: every selected supplier must carry the requested material.
    // (Reachable via "Show all suppliers" — matches-only view can't pick
    // non-carriers in the first place.)
    const offenders = (props.suppliers || [])
        .filter((s) => rfqForm.selected_suppliers.includes(s.id) && !carries(s))
        .map((s) => s.business_name);
    if (offenders.length > 0) {
        triggerModalAlert(
            'Supplier Cannot Supply',
            `${offenders.join(', ')} ${offenders.length === 1 ? 'does' : 'do'} not carry ${matchLabel.value} in ${offenders.length === 1 ? 'its' : 'their'} product catalog. Deselect ${offenders.length === 1 ? 'it' : 'them'} or ask the vendor to list the material first.`
        );
        return;
    }

    // PREVENT DOUBLE CLICK: Only trigger if not already processing
    if (rfqForm.processing) return;

    triggerModalAlert(
        'Confirm Dispatch', 
        `Are you sure you want to send this RFQ to ${rfqForm.selected_suppliers.length} selected supplier(s)?`,
        'confirm',
        () => {
            rfqForm.post(route('pro.manager.rfq.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    showRFQModal.value = false;
                    showConfirmModal.value = false;
                },
                onFinish: () => {
                    // This ensures showConfirmModal closes even if there's an error
                    showConfirmModal.value = false;
                }
            });
        }
    );
};

const getUrgencyClass = (u) => {
    if (u === 'High') return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
    if (u === 'Medium') return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
    return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
};
</script>

<template>
    <Head title="Procurement - Material Requests" />
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
                            <ClipboardList class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> PRO · Material Requests
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Procurement Management</h1>
                            <p class="text-sm text-blue-100/90">Forwarded requests from SCM awaiting RFQ generation · {{ totalRequests }} pending</p>
                            <span v-if="!canEditRequests" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-300 animate-pulse" /> {{ highUrgencyCount }} high urgency
                            </span>
                            <span class="rounded-full bg-white px-3 py-1.5 text-xs font-black text-indigo-700 shadow-lg">{{ totalRequests }} total</span>
                        </div>
                    </div>
                </div>

                <!-- Stat cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div style="animation-delay:0ms" class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-black text-gray-400 uppercase tracking-widest">Total Requests</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white mt-2">{{ totalRequests }}</p>
                            </div>
                            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-2xl group-hover:scale-110 transition-transform"><ClipboardList class="w-6 h-6 text-blue-600 dark:text-blue-400" /></div>
                        </div>
                    </div>
                    <div style="animation-delay:80ms" class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-rose-500/15 hover:-translate-y-1.5 hover:border-rose-200 dark:hover:border-rose-800 transition-all duration-300 p-5 overflow-hidden text-red-600">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-rose-400/20 to-orange-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">High Urgency <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /></p>
                                <p class="text-3xl font-black mt-2">{{ highUrgencyCount }}</p>
                            </div>
                            <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-2xl group-hover:scale-110 transition-transform"><AlertTriangle class="w-6 h-6" /></div>
                        </div>
                    </div>
                    <div style="animation-delay:160ms" class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-amber-500/15 hover:-translate-y-1.5 hover:border-amber-200 dark:hover:border-amber-800 transition-all duration-300 p-5 overflow-hidden text-yellow-500">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-yellow-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-black text-gray-400 uppercase tracking-widest">Medium Urgency</p>
                                <p class="text-3xl font-black mt-2">{{ mediumUrgencyCount }}</p>
                            </div>
                            <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-2xl group-hover:scale-110 transition-transform"><Clock class="w-6 h-6" /></div>
                        </div>
                    </div>
                    <div style="animation-delay:240ms" class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-emerald-500/15 hover:-translate-y-1.5 hover:border-emerald-200 dark:hover:border-emerald-800 transition-all duration-300 p-5 overflow-hidden text-green-500">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-black text-gray-400 uppercase tracking-widest">Low Urgency</p>
                                <p class="text-3xl font-black mt-2">{{ lowUrgencyCount }}</p>
                            </div>
                            <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-2xl group-hover:scale-110 transition-transform"><TrendingUp class="w-6 h-6" /></div>
                        </div>
                    </div>
                </div>

                <!-- Queue -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden" style="animation-delay:120ms">
                    <div class="p-6 sm:p-8 border-b border-gray-100 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-black uppercase tracking-tight text-gray-900 dark:text-white">Material Requests Queue</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Initiate RFQ for forwarded production materials</p>
                        </div>
                        <span class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full ring-1 bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30 flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ materialRequests.length }} queued
                        </span>
                    </div>

                    <div class="p-4 sm:p-6">
                        <div v-if="materialRequests.length === 0" class="flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <SearchX class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No pending requests</p>
                            <p class="text-xs text-gray-400 mt-1">Forwarded SCM requests will appear here.</p>
                        </div>

                        <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 gap-4">
                            <div v-for="(req, i) in materialRequests" :key="req.id"
                                :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                class="group relative flex flex-col sm:flex-row sm:items-center justify-between p-5 sm:p-6 rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.005] transition-all duration-300 overflow-hidden">
                                <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                                <div class="flex items-center gap-4 min-w-0">
                                    <div class="hidden sm:flex h-14 w-14 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 items-center justify-center shadow-lg text-white flex-shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                        <ClipboardList class="w-6 h-6" />
                                    </div>
                                    <div class="space-y-1.5 min-w-0">
                                        <p class="font-black text-gray-900 dark:text-white text-lg tracking-tight truncate group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ req.material_name }}</p>
                                        <div class="flex flex-wrap gap-2 items-center text-[10px] font-black uppercase">
                                            <span class="px-3 py-1 bg-gray-100 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-full text-zinc-600 dark:text-zinc-300">{{ req.required_qty }} {{ req.unit }}</span>
                                            <span :class="getUrgencyClass(req.urgency)" class="px-3 py-1 ring-1 rounded-full tracking-wider flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ req.urgency }}</span>
                                            <span class="text-gray-400">REF: {{ req.req_number }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-5 sm:mt-0 sm:ml-4 flex-shrink-0">
                                    <button v-if="canEditRequests" @click="openRFQ(req)" class="w-full sm:w-auto px-6 py-3.5 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-700 hover:opacity-95 text-white rounded-2xl text-xs font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 shadow-lg shadow-indigo-500/25 active:scale-95">
                                        Generate RFQ <ArrowRight class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>
            </div>

            <!-- RFQ Modal -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showRFQModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md">
                        <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl w-full max-w-3xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col border border-gray-100 dark:border-zinc-800">
                            <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white flex justify-between items-center flex-shrink-0">
                                <div class="absolute -top-16 -right-16 h-48 w-48 rounded-full bg-white/10 blur-3xl animate-float" />
                                <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 20px 20px;" />
                                <div class="relative">
                                    <h3 class="text-xl font-black tracking-tight uppercase flex items-center gap-2"><Send class="w-5 h-5" /> Generate Request</h3>
                                    <p class="text-xs text-blue-100/90 font-bold uppercase mt-1 tracking-widest">Step {{ supplierStep ? '2 of 2 · Suppliers' : '1 of 2 · Details' }}</p>
                                </div>
                                <button @click="showRFQModal = false" class="relative p-2.5 rounded-2xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 backdrop-blur transition active:scale-95"><X class="w-5 h-5" /></button>
                            </div>

                            <div class="p-6 sm:p-8 overflow-y-auto flex-1">
                                <div v-if="!supplierStep" class="space-y-6">
                                    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white flex items-center justify-between shadow-xl shadow-indigo-500/20">
                                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                                        <div class="relative"><p class="text-[10px] font-black uppercase opacity-80 tracking-widest">Material</p><p class="text-xl font-black">{{ selectedRequest.material_name }}</p></div>
                                        <div class="relative text-right"><p class="text-[10px] font-black uppercase opacity-80 tracking-widest">Quantity</p><p class="text-xl font-black">{{ selectedRequest.required_qty }} {{ selectedRequest.unit }}</p></div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-6">
                                        <div>
                                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Response Deadline *</label>
                                            <input v-model="rfqForm.deadline" type="date" :min="today" class="w-full px-5 py-3.5 bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Payment Terms</label>
                                            <div class="flex flex-wrap gap-2">
                                                <button v-for="term in ['50% Downpayment - 50% COD', 'Cash on delivery', '30 Days', '60 Days', '150 Days']" :key="term" type="button"
                                                    @click="rfqForm.payment_terms = term"
                                                    :class="rfqForm.payment_terms === term ? 'bg-gradient-to-r from-blue-700 to-indigo-700 text-white shadow-lg shadow-indigo-500/25 scale-105' : 'bg-gray-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300 hover:bg-gray-200'"
                                                    class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase transition-all active:scale-95">
                                                    {{ term }}
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Notes</label>
                                            <textarea v-model="rfqForm.notes" rows="3" placeholder="Technical requirements..." class="w-full px-5 py-3.5 bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-3xl font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="space-y-3">
                                    <div class="flex items-center justify-between px-1">
                                        <p class="text-[11px] font-black uppercase tracking-widest text-gray-400">
                                            <template v-if="showAllSuppliers">Showing all {{ visibleSuppliers.length }} supplier{{ visibleSuppliers.length === 1 ? '' : 's' }}</template>
                                            <template v-else>Showing {{ visibleSuppliers.length }} supplier{{ visibleSuppliers.length === 1 ? '' : 's' }} carrying <span class="text-indigo-600 dark:text-indigo-400">{{ matchLabel }}</span></template>
                                        </p>
                                        <button type="button" @click="showAllSuppliers = !showAllSuppliers" class="text-[11px] font-black text-indigo-600 dark:text-indigo-400 hover:underline">
                                            {{ showAllSuppliers ? 'Show matches only' : 'Show all suppliers' }}
                                        </button>
                                    </div>
                                    <div v-for="sup in visibleSuppliers" :key="sup.id" @click="toggleSupplier(sup.id)"
                                        class="group flex items-center p-5 rounded-3xl border-2 cursor-pointer transition-all duration-300 hover:-translate-y-0.5"
                                        :class="rfqForm.selected_suppliers.includes(sup.id) ? 'border-indigo-500 bg-indigo-50/60 dark:bg-indigo-900/20 shadow-lg shadow-indigo-500/10' : 'border-gray-100 dark:border-zinc-800 hover:border-indigo-200 dark:hover:border-indigo-800 bg-white dark:bg-zinc-900'">
                                        <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 to-violet-700 text-white flex items-center justify-center mr-4 flex-shrink-0 shadow-lg">
                                            <Users v-if="!rfqForm.selected_suppliers.includes(sup.id)" class="w-5 h-5" />
                                            <CheckCircle v-else class="w-5 h-5" />
                                        </div>
                                        <div class="flex-1 min-w-0"><p class="font-black text-sm text-gray-900 dark:text-white tracking-tight truncate">{{ sup.business_name }}</p><p class="text-xs text-gray-500 font-bold truncate">{{ sup.email }}</p></div>
                                        <span v-if="carries(sup)" class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 mr-2">Carries {{ matchLabel }}</span>
                                        <span v-if="rfqForm.selected_suppliers.includes(sup.id)" class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full bg-indigo-600 text-white flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse" /> Selected</span>
                                    </div>
                                    <p v-if="!visibleSuppliers?.length" class="text-center py-8 text-sm text-gray-400 font-bold">No suppliers carry {{ matchLabel }} yet. <button type="button" @click="showAllSuppliers = true" class="text-indigo-600 hover:underline">Show all suppliers</button> or ask vendors to list it in My Products.</p>
                                </div>
                            </div>

                            <div class="p-6 border-t border-gray-100 dark:border-zinc-800 flex justify-between items-center bg-gray-50/60 dark:bg-zinc-800/30">
                                <button v-if="supplierStep" @click="supplierStep = false" class="text-xs font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition flex items-center gap-1"><ChevronRight class="w-4 h-4 rotate-180" /> Back</button>
                                <div v-else></div>
                                <button v-if="!supplierStep" @click="proceedToSuppliers" class="px-8 py-3.5 bg-gray-900 dark:bg-indigo-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-indigo-700 transition active:scale-95">Next <ArrowRight class="w-4 h-4 inline ml-1" /></button>
                                <button v-else-if="canEditRequests"
                                    @click="submitRFQ" 
                                    :disabled="rfqForm.selected_suppliers.length === 0 || rfqForm.processing" 
                                    class="px-8 py-3.5 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-700 text-white rounded-2xl text-xs font-black uppercase tracking-widest disabled:opacity-50 flex items-center gap-2 shadow-lg shadow-indigo-500/25 active:scale-95">
                                    <Loader2 v-if="rfqForm.processing" class="w-4 h-4 animate-spin" />
                                    {{ rfqForm.processing ? 'Sending...' : `Send RFQ (${rfqForm.selected_suppliers.length})` }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <!-- Confirm Modal -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showConfirmModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/70 backdrop-blur-md">
                        <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl w-full max-w-md p-8 shadow-2xl border border-gray-100 dark:border-zinc-800">
                            <div class="flex flex-col items-center text-center">
                                <div :class="confirmConfig.type === 'confirm' ? 'bg-gradient-to-br from-blue-600 to-indigo-700 text-white' : 'bg-gradient-to-br from-rose-500 to-red-600 text-white'" class="h-16 w-16 rounded-2xl flex items-center justify-center mb-5 shadow-lg animate-pop">
                                    <Info v-if="confirmConfig.type === 'confirm'" class="w-8 h-8" />
                                    <AlertCircle v-else class="w-8 h-8" />
                                </div>
                                <h3 class="text-xl font-black uppercase tracking-tight mb-2 text-zinc-900 dark:text-zinc-100">{{ confirmConfig.title }}</h3>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 leading-relaxed">{{ confirmConfig.message }}</p>
                                <div class="mt-7 flex w-full gap-3">
                                    <button v-if="confirmConfig.type === 'confirm'" @click="showConfirmModal = false" :disabled="rfqForm.processing" class="flex-1 px-6 py-3.5 bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 rounded-2xl text-xs font-black uppercase hover:bg-gray-200 transition disabled:opacity-50 active:scale-95">Cancel</button>
                                    <button @click="confirmConfig.type === 'confirm' ? confirmConfig.action() : (showConfirmModal = false)" 
                                        :disabled="rfqForm.processing"
                                        :class="confirmConfig.type === 'confirm' ? 'bg-gradient-to-r from-blue-700 to-indigo-700' : 'bg-gray-900'" 
                                        class="flex-1 px-6 py-3.5 text-white rounded-2xl text-xs font-black uppercase shadow-lg disabled:opacity-50 flex justify-center items-center gap-2 active:scale-95">
                                        <Loader2 v-if="rfqForm.processing" class="w-4 h-4 animate-spin" />
                                        {{ confirmConfig.type === 'confirm' ? (rfqForm.processing ? 'Dispatching...' : 'Confirm') : 'Got it' }}
                                    </button>
                                </div>
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
