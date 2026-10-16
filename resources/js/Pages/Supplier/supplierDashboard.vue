<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    FileText, CheckCircle, Clock, AlertTriangle,
    ArrowRight, Send, X, DollarSign, Package,
    Building2, MapPin, FileQuestion
} from 'lucide-vue-next';

const props = defineProps({
    auth: Object,
    stats: {
        type: Object,
        default: () => ({ activeRFQs: 0, pendingResponses: 0, submittedQuotes: 0 })
    },
    rfqs: {
        type: Array,
        default: () => []
    },
    catalogPrices: {
        type: Object,
        default: () => ({})
    }
});

const isLoaded = ref(false);
onMounted(() => { isLoaded.value = true; });

// Safe access to the supplier data
const supplierData = computed(() => props.auth?.supplier || props.auth?.user || {});

// ─── Quotation Form Modal ──────────────────────────────────────────────────
const showQuoteModal = ref(false);
const selectedRfq = ref(null);
const priceFromCatalog = ref(false);

const quoteForm = useForm({
    unit_price: '',
    lead_time: '',
    validity_date: '',
    payment_terms: '',
    notes: '',
});

const openQuoteModal = (rfq) => {
    selectedRfq.value = rfq;
    // Auto-fill from My Products catalog price when listed (still editable).
    const catalogPrice = props.catalogPrices?.[rfq.material_id];
    quoteForm.unit_price = catalogPrice != null && catalogPrice !== '' ? catalogPrice : '';
    priceFromCatalog.value = quoteForm.unit_price !== '';
    quoteForm.lead_time = '';
    quoteForm.validity_date = '';
    quoteForm.payment_terms = rfq.payment_terms; // Default to what SCM requested
    quoteForm.notes = '';
    quoteForm.clearErrors();
    showQuoteModal.value = true;
};

const submitQuote = () => {
    quoteForm.post(`/supplier/rfq/${selectedRfq.value.id}/respond`, {
        preserveScroll: true,
        onSuccess: () => {
            showQuoteModal.value = false;
        }
    });
};

const formatCurrency = (val) => '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });
</script>

<template>

    <Head title="Supplier Hub | Monti Textile" />

    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">Vendor Hub
                </h1>
                <p class="text-slate-500 text-sm mt-0.5">Welcome back, {{ supplierData.representative_name || 'Vendor'
                    }}</p>
            </div>
            <div
                class="flex items-center gap-4 bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <div
                    class="h-10 w-10 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg flex items-center justify-center font-black text-lg">
                    {{ (supplierData.business_name || 'S').charAt(0).toUpperCase() }}
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ supplierData.business_name }}</p>
                    <p class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest">Official Vendor</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-5 mb-8">
            <div
                class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Active
                        RFQs</p>
                    <p class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ stats.activeRFQs }}</p>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-600 rounded-xl hidden sm:block">
                    <FileText class="w-6 h-6" />
                </div>
            </div>
            <div
                class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Pending
                        Action</p>
                    <p class="text-2xl sm:text-3xl font-black text-amber-600">{{ stats.pendingResponses }}</p>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 text-amber-600 rounded-xl hidden sm:block">
                    <AlertTriangle class="w-6 h-6" />
                </div>
            </div>
            <div
                class="col-span-2 md:col-span-1 bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Submitted
                        Quotes</p>
                    <p class="text-2xl sm:text-3xl font-black text-emerald-600">{{ stats.submittedQuotes }}</p>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 rounded-xl hidden sm:block">
                    <CheckCircle class="w-6 h-6" />
                </div>
            </div>
        </div>

        <h2 class="text-lg font-black text-slate-800 dark:text-white mb-4 flex items-center gap-2">
            <FileQuestion class="w-5 h-5 text-blue-500" /> Requests for Quotation (RFQ)
        </h2>

        <div v-if="rfqs.length === 0"
            class="text-center py-16 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
            <FileText class="w-12 h-12 text-slate-300 mx-auto mb-3" />
            <p class="text-sm font-bold text-slate-500">No RFQs have been assigned to you yet.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div v-for="rfq in rfqs" :key="rfq.id"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span
                                class="font-mono text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-md">{{
                                rfq.rfq_number }}</span>
                            <span
                                :class="['text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full', rfq.my_response ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
                                {{ rfq.my_response ? 'Quoted' : 'Requires Action' }}
                            </span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white leading-tight">{{ rfq.material_name
                            }}</h3>
                        <p class="text-xs text-slate-500 font-medium mt-0.5">{{ rfq.category }}</p>
                    </div>
                    <div
                        class="text-right flex-shrink-0 bg-slate-50 dark:bg-slate-800/50 p-2 rounded-xl border border-slate-100 dark:border-slate-700">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Required Qty
                        </p>
                        <p class="text-lg font-black text-slate-900 dark:text-white leading-none">{{
                            rfq.required_qty.toLocaleString() }} <span class="text-xs text-slate-500">{{ rfq.unit
                                }}</span></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4 flex-1">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">SCM Deadline</p>
                        <p
                            class="text-sm font-semibold text-slate-700 dark:text-slate-300 mt-0.5 flex items-center gap-1.5">
                            <Clock class="w-3.5 h-3.5" /> {{ rfq.deadline }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Delivery Address</p>
                        <p
                            class="text-sm font-semibold text-slate-700 dark:text-slate-300 mt-0.5 truncate flex items-center gap-1.5">
                            <MapPin class="w-3.5 h-3.5" /> {{ rfq.delivery_address }}
                        </p>
                    </div>
                    <div class="col-span-2" v-if="rfq.notes">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">SCM Notes</p>
                        <p
                            class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 italic bg-slate-50 dark:bg-slate-800 p-2 rounded-lg border border-slate-100 dark:border-slate-700">
                            {{ rfq.notes }}</p>
                    </div>
                </div>

                <div class="mt-auto pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div v-if="rfq.my_response"
                        class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-100 dark:border-emerald-800/30 p-3 rounded-xl">
                        <div>
                            <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Your Quoted
                                Price</p>
                            <p class="font-black text-emerald-700 dark:text-emerald-400">{{
                                formatCurrency(rfq.my_response.total_price) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-bold text-emerald-600">{{ rfq.my_response.lead_time }} lead</p>
                            <span
                                class="text-[10px] font-bold bg-emerald-200/50 text-emerald-700 px-2 py-0.5 rounded-md uppercase">{{
                                    rfq.my_response.status.replace('_', ' ') }}</span>
                        </div>
                    </div>
                    <button v-else @click="openQuoteModal(rfq)"
                        class="w-full inline-flex items-center justify-center gap-2 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-black shadow-lg shadow-blue-500/20 transition-all active:scale-[0.98]">
                        Submit Quotation
                        <ArrowRight class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showQuoteModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="showQuoteModal = false">
                <div
                    class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">

                    <div
                        class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 flex justify-between items-center flex-shrink-0">
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <DollarSign class="w-5 h-5 text-emerald-500" /> Submit Quotation
                            </h3>
                            <p class="text-[10px] text-slate-500 mt-0.5 uppercase tracking-widest">{{
                                selectedRfq?.rfq_number }}</p>
                        </div>
                        <button @click="showQuoteModal = false"
                            class="p-2 rounded-xl bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 transition shadow-sm border border-slate-200 dark:border-slate-600">
                            <X class="w-4 h-4 text-slate-500" />
                        </button>
                    </div>

                    <div class="p-5 sm:p-6 overflow-y-auto flex-1 space-y-5">
                        <div
                            class="bg-slate-50 dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                            <p class="text-sm font-black text-slate-800 dark:text-slate-200">{{
                                selectedRfq?.material_name }}</p>
                            <p class="text-xs text-slate-500 mt-1">Requested: <span
                                    class="font-black text-slate-700 dark:text-slate-300">{{
                                        selectedRfq?.required_qty.toLocaleString() }} {{ selectedRfq?.unit }}</span></p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Unit
                                    Price (₱) *</label>
                                <input v-model="quoteForm.unit_price" type="number" min="0.01" step="0.01"
                                    placeholder="e.g. 50.00" @input="priceFromCatalog = false"
                                    class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500/20 font-bold" />
                                <p v-if="priceFromCatalog && quoteForm.unit_price"
                                    class="text-[10px] text-emerald-600 font-bold mt-1 text-right">
                                    From your Products catalog — editable
                                </p>
                                <p v-if="quoteForm.unit_price"
                                    class="text-[10px] text-emerald-600 font-bold mt-1 text-right">
                                    Total: {{ formatCurrency(quoteForm.unit_price * selectedRfq.required_qty) }}
                                </p>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Estimated
                                    Lead Time *</label>
                                <input v-model="quoteForm.lead_time" type="text" placeholder="e.g. 7-10 Days"
                                    class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500/20 font-bold" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Offer
                                    Valid Until *</label>
                                <input v-model="quoteForm.validity_date" type="date"
                                    :min="new Date().toISOString().split('T')[0]"
                                    class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500/20 font-bold" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Payment
                                    Terms *</label>
                                <input v-model="quoteForm.payment_terms" type="text"
                                    class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500/20 font-bold" />
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Additional
                                    Notes</label>
                                <textarea v-model="quoteForm.notes" rows="2" placeholder="Any conditions or remarks..."
                                    class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-emerald-500/20 font-bold resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    <div
                        class="px-5 py-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row gap-3 bg-slate-50 dark:bg-slate-800 flex-shrink-0">
                        <button @click="showQuoteModal = false"
                            class="w-full sm:flex-1 py-3 text-sm font-bold rounded-xl border border-slate-200 dark:border-slate-600 hover:bg-white dark:hover:bg-slate-700 transition">
                            Cancel
                        </button>
                        <button @click="submitQuote"
                            :disabled="quoteForm.processing || !quoteForm.unit_price || !quoteForm.lead_time || !quoteForm.validity_date"
                            class="w-full sm:flex-1 inline-flex items-center justify-center gap-2 py-3 text-sm font-black rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 transition shadow-lg shadow-emerald-500/20 disabled:opacity-40">
                            <Send class="w-4 h-4" /> {{ quoteForm.processing ? 'Submitting...' : 'Submit Quotation' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AuthenticatedLayout>
</template>