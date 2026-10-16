<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePageAccess } from '@/composables/usePageAccess';
import {
    Eye, CheckCircle, XCircle, AlertTriangle, Ban,
    X, Clock, BadgeCheck, FileText, Send, DollarSign, Sparkles, ArrowUpRight
} from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditQuotations = computed(() => canEdit('PRO', 'quotations'));

const props = defineProps({
    rfqs: Array,
});

// Modals state
const showDetailModal = ref(false);
const selectedRFQ = ref(null);
const showAcceptModal = ref(false);
const acceptTarget = ref(null);
const showDeclineModal = ref(false);
const declineTarget = ref(null);
const declineReason = ref('');
const isLoading = ref(false);

// Helpers
const formatCurrency = (val) => '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });

const statusBadge = (status) => {
    const map = {
        sent: 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        responded: 'bg-green-100 text-green-700 ring-green-200 dark:bg-green-500/15 dark:text-green-300 dark:ring-green-500/30',
        closed: 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700',
        pending_review: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        accepted: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        declined: 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30',
    };
    return map[status] || 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const statusLabel = (status) => {
    if (!status) return '';
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

// Modal handlers
const openDetail = (rfq) => {
    selectedRFQ.value = rfq;
    showDetailModal.value = true;
};

const openAccept = (rfq, response) => {
    acceptTarget.value = { rfq, response };
    showAcceptModal.value = true;
};

const confirmAccept = () => {
    isLoading.value = true;
    router.post(route('pro.manager.quotations.accept', acceptTarget.value.response.id), {
        rfq_id: acceptTarget.value.rfq.id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAcceptModal.value = false;
            isLoading.value = false;
        },
        onError: () => { isLoading.value = false; }
    });
};

const openDecline = (rfq, response) => {
    declineTarget.value = { rfq, response };
    declineReason.value = '';
    showDeclineModal.value = true;
};

const confirmDecline = () => {
    if (!declineReason.value.trim()) return;
    isLoading.value = true;
    router.post(route('pro.manager.quotations.decline', declineTarget.value.response.id), {
        reason: declineReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showDeclineModal.value = false;
            isLoading.value = false;
        },
        onError: () => { isLoading.value = false; }
    });
};
</script>

<template>
    <Head title="PRO - Supplier Quotations" />
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
                            <DollarSign class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> PRO · Quotations
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Supplier Quotations</h1>
                            <p class="text-sm text-blue-100/90">Review and accept supplier quotes · {{ rfqs.length }} RFQ{{ rfqs.length !== 1 ? 's' : '' }}</p>
                            <span v-if="!canEditQuotations" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> Live review
                            </span>
                        </div>
                    </div>
                </div>

                <!-- RFQ list -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay:100ms">
                    <div class="p-6 sm:p-8 border-b border-gray-100 dark:border-zinc-800">
                        <h3 class="text-lg font-black uppercase tracking-tight text-gray-900 dark:text-white">RFQ Pipeline</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Compare responses incl. 12% VAT grand totals</p>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div v-if="rfqs.length === 0" class="flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <Send class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No RFQs have been sent</p>
                            <p class="text-xs text-gray-400 mt-1">Generate an RFQ from Material Requests first.</p>
                        </div>
                        <TransitionGroup v-else name="card" tag="div" class="space-y-4">
                            <div v-for="(rfq, i) in rfqs" :key="rfq.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                class="group relative rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 overflow-hidden hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300">
                                <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                                <div class="p-5 bg-gray-50/60 dark:bg-zinc-800/40 flex justify-between items-center gap-3 flex-wrap">
                                    <div class="min-w-0">
                                        <div class="flex gap-2 items-center flex-wrap">
                                            <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ rfq.rfq_number }}</span>
                                            <span :class="statusBadge(rfq.status)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ statusLabel(rfq.status) }}</span>
                                        </div>
                                        <p class="font-black text-gray-900 dark:text-white mt-1">{{ rfq.material_name }} <span class="font-bold text-gray-500">({{ rfq.required_qty }} {{ rfq.unit }})</span></p>
                                        <p class="text-xs text-gray-500 flex items-center gap-1 mt-0.5"><Clock class="w-3 h-3" /> Deadline: {{ rfq.deadline }}</p>
                                    </div>
                                    <button @click="openDetail(rfq)"
                                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-500 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300 active:scale-95">
                                        <Eye class="w-5 h-5" />
                                    </button>
                                </div>
                                <div class="p-4 sm:p-5 space-y-2.5">
                                    <div v-for="res in rfq.responses" :key="res.id"
                                        class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 p-4 rounded-2xl border transition-all"
                                        :class="res.status === 'accepted' ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-800/40 dark:bg-emerald-900/10' : res.status === 'declined' ? 'border-red-200 bg-red-50/60 dark:border-red-800/30 dark:bg-red-900/10 opacity-70' : 'border-gray-100 dark:border-zinc-800 bg-gray-50/50 dark:bg-zinc-800/30 hover:border-indigo-200'">
                                        <div class="min-w-0">
                                            <p class="font-black text-sm text-gray-900 dark:text-white flex items-center gap-1.5"><BadgeCheck v-if="res.status==='accepted'" class="w-4 h-4 text-emerald-500" /> {{ res.supplier_name }}</p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">Unit: {{ formatCurrency(res.unit_price) }} | Total (Inc. VAT): <span class="font-black text-gray-900 dark:text-white">{{ formatCurrency(res.total_price * 1.12) }}</span></p>
                                        </div>
                                        <div class="flex gap-2 flex-shrink-0">
                                            <template v-if="res.status === 'pending_review' && canEditQuotations">
                                                <button @click="openAccept(rfq, res)"
                                                    class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-xl text-xs font-black uppercase shadow-lg active:scale-95 transition">Accept</button>
                                                <button @click="openDecline(rfq, res)"
                                                    class="px-4 py-2 bg-gradient-to-r from-rose-600 to-red-600 text-white rounded-xl text-xs font-black uppercase shadow-lg active:scale-95 transition">Decline</button>
                                            </template>
                                            <span v-else-if="res.status === 'accepted'"
                                                class="text-emerald-600 text-xs font-black uppercase flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Accepted</span>
                                            <span v-else-if="res.status === 'declined'" class="text-red-600 text-xs font-black uppercase">✗ Declined</span>
                                            <span v-else :class="statusBadge(res.status)" class="text-[10px] font-black uppercase px-2 py-1 rounded-full ring-1">{{ statusLabel(res.status) }}</span>
                                        </div>
                                    </div>
                                    <p v-if="!rfq.responses?.length" class="text-center py-4 text-xs text-gray-400 italic font-medium">Awaiting vendor responses...</p>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>
            </div>

            <!-- Detail Modal -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showDetailModal && selectedRFQ"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-md"
                        @click.self="showDetailModal = false">
                        <div
                            class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl w-full max-w-lg border border-gray-100 dark:border-zinc-800 overflow-hidden flex flex-col max-h-[90vh] shadow-2xl">
                            <div
                                class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 sm:p-6 text-white flex items-center justify-between flex-shrink-0">
                                <div class="absolute -top-12 -right-12 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                                <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 20px 20px;" />
                                <div class="relative">
                                    <h3 class="text-lg font-black flex items-center gap-2">
                                        <FileText class="w-5 h-5" /> RFQ Details
                                    </h3>
                                    <p class="text-xs font-mono font-bold text-blue-100 mt-1">{{ selectedRFQ.rfq_number }}</p>
                                </div>
                                <button @click="showDetailModal = false"
                                    class="relative p-2.5 bg-white/15 rounded-2xl text-white hover:bg-white/25 ring-1 ring-white/25 backdrop-blur transition active:scale-95">
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                            <div class="p-5 space-y-5 overflow-y-auto flex-1">
                                <div
                                    class="grid grid-cols-2 gap-4 bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-2xl border border-gray-100 dark:border-zinc-800">
                                    <div class="col-span-2">
                                        <p class="text-[10px] text-gray-400 uppercase font-black tracking-widest mb-1">Target Material</p>
                                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ selectedRFQ.material_name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase font-black tracking-widest mb-1">Required Qty</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ Number(selectedRFQ.required_qty).toLocaleString() }}
                                            <span class="text-xs text-gray-500 ml-1">{{ selectedRFQ.unit }}</span></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase font-black tracking-widest mb-1">Deadline</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedRFQ.deadline }}</p>
                                    </div>
                                    <div class="col-span-2" v-if="selectedRFQ.notes">
                                        <p class="text-[10px] text-gray-400 uppercase font-black tracking-widest mb-1">Notes</p>
                                        <p class="text-xs italic border-l-2 border-indigo-300 pl-2 text-gray-600 dark:text-gray-300">{{ selectedRFQ.notes }}</p>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-xs font-black flex items-center justify-between mb-3 border-b border-gray-100 dark:border-zinc-800 pb-2">
                                        <span class="text-gray-900 dark:text-white uppercase tracking-tight">Supplier Responses</span>
                                        <span
                                            class="bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300 px-2 py-0.5 rounded-md text-[10px] uppercase tracking-widest">{{
                                            selectedRFQ.responses?.length || 0 }} Total</span>
                                    </p>
                                    <div v-if="!selectedRFQ.responses?.length" class="text-center py-6 text-gray-400 italic text-sm">
                                        Awaiting vendor responses...
                                    </div>
                                    <div v-else class="space-y-3">
                                        <div v-for="res in selectedRFQ.responses" :key="res.id"
                                            :class="['p-4 rounded-2xl border', res.status === 'accepted' ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-800/40 dark:bg-emerald-900/10' : res.status === 'declined' ? 'border-red-200 bg-red-50/60 dark:border-red-800/30 dark:bg-red-900/10 opacity-70' : 'border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900']">
                                            <div class="flex justify-between items-start mb-2 gap-2">
                                                <p class="font-black text-sm truncate text-gray-900 dark:text-white">{{ res.supplier_name }}</p>
                                                <span
                                                    :class="['text-[9px] font-black uppercase px-2 py-0.5 rounded-md ring-1 flex items-center gap-1', statusBadge(res.status)]"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{
                                                    statusLabel(res.status) }}</span>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4 mt-3 text-xs">
                                                <div>
                                                    <p class="text-[9px] text-gray-400 font-bold uppercase mb-0.5">Unit Price</p>
                                                    <p class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(res.unit_price) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[9px] text-gray-400 font-bold uppercase mb-0.5">Lead Time</p>
                                                    <p class="font-bold text-gray-900 dark:text-white">{{ res.lead_time }}</p>
                                                </div>
                                                <div class="col-span-2 pt-3 border-t border-gray-100 dark:border-zinc-800 mt-1 space-y-1.5">
                                                    <div class="flex justify-between items-center text-gray-500 dark:text-gray-400">
                                                        <p class="text-[10px] font-bold uppercase">Subtotal</p>
                                                        <p class="font-bold text-[11px]">{{ formatCurrency(res.total_price) }}</p>
                                                    </div>
                                                    <div class="flex justify-between items-center text-indigo-600 dark:text-indigo-300">
                                                        <p class="text-[10px] font-bold uppercase">VAT (12%)</p>
                                                        <p class="font-bold text-[11px]">+ {{ formatCurrency(res.total_price * 0.12) }}</p>
                                                    </div>
                                                    <div class="flex justify-between items-center pt-1.5 border-t border-dashed border-gray-200 dark:border-zinc-700 mt-1">
                                                        <p class="text-[10px] text-gray-900 dark:text-white font-black uppercase tracking-tight">Grand Total</p>
                                                        <p class="font-black text-sm text-gray-900 dark:text-white">{{ formatCurrency(res.total_price * 1.12) }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <!-- Accept Modal -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showAcceptModal && acceptTarget"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-md"
                        @click.self="showAcceptModal = false">
                        <div
                            class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl w-full max-w-md border border-gray-100 dark:border-zinc-800 overflow-hidden shadow-2xl">
                            <div
                                class="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 p-5 text-white flex items-center justify-between">
                                <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                                <h3 class="relative text-lg font-black flex items-center gap-2">
                                    <CheckCircle class="w-5 h-5" /> Accept Quotation
                                </h3>
                                <button @click="showAcceptModal = false"
                                    class="relative p-2 rounded-xl bg-white/15 ring-1 ring-white/25 text-white hover:bg-white/25 transition active:scale-95">
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                            <div class="p-5 space-y-5">
                                <div
                                    class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/50 rounded-2xl p-4 shadow-inner">
                                    <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-300 uppercase mb-3 tracking-widest text-center">Generating Purchase Order for:</p>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between items-center gap-2">
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400 text-[10px] uppercase">Supplier</span>
                                            <strong class="font-black text-gray-900 dark:text-white truncate max-w-[200px]">{{
                                                acceptTarget.response.supplier_name
                                                }}</strong>
                                        </div>
                                        <div class="flex justify-between items-center gap-2">
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400 text-[10px] uppercase">Material</span>
                                            <strong class="font-black text-gray-900 dark:text-white">{{ acceptTarget.rfq.material_name }}</strong>
                                        </div>
                                        <div class="flex justify-between items-center gap-2">
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400 text-[10px] uppercase">Quantity</span>
                                            <strong class="font-black text-gray-900 dark:text-white">{{ Number(acceptTarget.rfq.required_qty).toLocaleString()
                                                }} {{
                                                acceptTarget.rfq.unit }}</strong>
                                        </div>
                                        <div class="pt-4 mt-4 border-t border-emerald-200/60 dark:border-emerald-800/40">
                                            <div class="flex justify-between items-center text-emerald-600/80 dark:text-emerald-300/80 mb-1">
                                                <span class="font-bold uppercase text-[10px]">Subtotal</span>
                                                <span class="font-bold text-xs">{{ formatCurrency(acceptTarget.response.total_price) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center text-emerald-600/80 dark:text-emerald-300/80 mb-2">
                                                <span class="font-bold uppercase text-[10px]">VAT (12%)</span>
                                                <span class="font-bold text-xs">+ {{ formatCurrency(acceptTarget.response.total_price * 0.12) }}</span>
                                            </div>
                                            <div class="pt-3 border-t-2 border-emerald-300 border-dashed">
                                                <div class="flex justify-between items-end">
                                                    <span class="font-black uppercase text-xs text-emerald-700 dark:text-emerald-300 mb-0.5">Grand Total</span>
                                                    <strong class="text-2xl font-black text-emerald-800 dark:text-emerald-200">{{
                                                        formatCurrency(acceptTarget.response.total_price * 1.12) }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400 text-center uppercase font-bold tracking-tight px-4">Accepting will automatically <span class="text-red-500">decline</span> all other quotes for this RFQ.</p>
                            </div>
                            <div class="p-5 border-t border-gray-100 dark:border-zinc-800 flex gap-3 bg-gray-50/60 dark:bg-zinc-800/40">
                                <button @click="showAcceptModal = false"
                                    class="flex-1 py-2.5 border border-gray-200 dark:border-zinc-700 rounded-2xl text-sm font-black uppercase text-gray-600 dark:text-gray-300 hover:bg-gray-100 transition active:scale-95">Cancel</button>
                                <button v-if="canEditQuotations" @click="confirmAccept" :disabled="isLoading"
                                    class="flex-1 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-2xl text-sm font-black uppercase flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/25 active:scale-95 disabled:opacity-50">
                                    <CheckCircle class="w-4 h-4" /> {{ isLoading ? 'Processing...' : 'Confirm Order' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <!-- Decline Modal -->
            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showDeclineModal && declineTarget"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-md"
                        @click.self="showDeclineModal = false">
                        <div
                            class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl w-full max-w-md border border-gray-100 dark:border-zinc-800 overflow-hidden shadow-2xl">
                            <div
                                class="relative overflow-hidden bg-gradient-to-br from-rose-600 via-red-600 to-orange-600 p-5 text-white flex items-center justify-between">
                                <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                                <h3 class="relative text-lg font-black flex items-center gap-2">
                                    <XCircle class="w-5 h-5" /> Decline Quotation
                                </h3>
                                <button @click="showDeclineModal = false"
                                    class="relative p-2 rounded-xl bg-white/15 ring-1 ring-white/25 text-white hover:bg-white/25 transition active:scale-95">
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                            <div class="p-5 space-y-5">
                                <div
                                    class="bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-800/30 p-4 rounded-2xl flex items-start gap-3">
                                    <AlertTriangle class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                                    <p class="text-sm font-medium text-red-800 dark:text-red-300">
                                        Declining quote from <strong class="font-black">{{ declineTarget.response.supplier_name
                                            }}</strong>.
                                        This action is final and the supplier will be notified.
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 mb-1.5 uppercase tracking-widest">Reason for
                                        Declining
                                        *</label>
                                    <textarea v-model="declineReason" rows="3"
                                        placeholder="e.g. Price too high, better offer found..."
                                        class="w-full px-4 py-3 text-sm bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-2xl resize-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all text-gray-900 dark:text-white outline-none"></textarea>
                                </div>
                            </div>
                            <div class="p-5 border-t border-gray-100 dark:border-zinc-800 flex gap-3 bg-gray-50/60 dark:bg-zinc-800/40">
                                <button @click="showDeclineModal = false"
                                    class="flex-1 py-2.5 border border-gray-200 dark:border-zinc-700 rounded-2xl text-sm font-black uppercase text-gray-600 dark:text-gray-300 hover:bg-gray-100 transition active:scale-95">Cancel</button>
                                <button v-if="canEditQuotations" @click="confirmDecline" :disabled="isLoading || !declineReason.trim()"
                                    class="flex-1 py-2.5 bg-gradient-to-r from-rose-600 to-red-600 text-white rounded-2xl text-sm font-black uppercase flex items-center justify-center gap-2 shadow-lg shadow-red-500/25 active:scale-95 disabled:opacity-50">
                                    <Ban class="w-4 h-4" /> {{ isLoading ? 'Processing...' : 'Confirm Decline' }}
                                </button>
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
