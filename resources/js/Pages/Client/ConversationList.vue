<template>
    <Head title="My Conversations" />
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
                            <MessageSquare class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Client Communications
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">My Conversations</h1>
                            <p class="text-sm text-blue-100/90">Track all your product inquiries and quotations.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ openCount }} open
                            </span>
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ quotationCount }} quoted
                            </span>
                            <Link :href="route('client.products')"
                                class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:bg-indigo-50 hover:scale-105 active:scale-95 transition-all flex items-center gap-2">
                                <Plus class="h-4 w-4" /> New Inquiry
                            </Link>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by product name..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="key in ['all','open','quotation_sent','converted','abandoned']" :key="key" @click="statusFilter = key"
                                :class="statusFilter === key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ key === 'quotation_sent' ? 'Quoted' : key }} · {{ statusCount(key) }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div v-for="(stat, i) in [
                        { label: 'Open Inquiries', value: openCount, tint: 'from-blue-500 via-indigo-600 to-violet-700', ring: 'group-hover:shadow-indigo-500/20' },
                        { label: 'Quotations Received', value: quotationCount, tint: 'from-indigo-500 via-violet-600 to-fuchsia-700', ring: 'group-hover:shadow-violet-500/20' },
                        { label: 'Converted to Orders', value: convertedCount, tint: 'from-emerald-500 via-teal-600 to-cyan-700', ring: 'group-hover:shadow-emerald-500/20' },
                    ]" :key="stat.label"
                        :style="{ animationDelay: `${i * 80}ms` }"
                        class="group animate-fade-up relative overflow-hidden bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-6">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative flex items-center gap-4">
                            <div :class="['flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br text-white text-xl font-black shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', stat.tint]">
                                {{ stat.value }}
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ stat.label }}</p>
                                <p class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">{{ stat.value }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inquiries card -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-xl transition-shadow duration-300 overflow-hidden" style="animation-delay:200ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <MessageSquare class="w-5 h-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Product Inquiries</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ filteredInquiries.length }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50/50 dark:bg-zinc-800/30 text-[10px] font-black uppercase text-gray-400 tracking-[0.15em]">
                                <tr>
                                    <th class="px-8 py-5">Product</th>
                                    <th class="px-8 py-5">Initial Message</th>
                                    <th class="px-8 py-5 text-center">Status</th>
                                    <th class="px-8 py-5 text-center">Last Activity</th>
                                    <th class="px-8 py-5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(inquiry, i) in filteredInquiries" :key="inquiry.id"
                                    :style="{ animationDelay: `${Math.min(i * 40, 400)}ms` }"
                                    class="animate-fade-up group hover:bg-indigo-50/40 dark:hover:bg-indigo-900/10 transition-all">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-4">
                                            <div class="h-11 w-11 rounded-2xl bg-gradient-to-br flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0"
                                                :class="isBulkInquiry(inquiry) ? 'from-indigo-500 via-violet-600 to-fuchsia-700' : 'from-blue-600 via-indigo-600 to-violet-700'">
                                                <Layers v-if="isBulkInquiry(inquiry)" class="h-5 w-5" />
                                                <Package v-else class="h-5 w-5" />
                                            </div>
                                            <div v-if="!isBulkInquiry(inquiry)" class="min-w-0">
                                                <p class="text-sm font-black text-gray-900 dark:text-white truncate tracking-tight group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ inquiry.product?.name }}</p>
                                                <p class="text-[10px] font-bold text-gray-400">{{ inquiry.product?.sku }}</p>
                                            </div>
                                            <div v-else class="min-w-0">
                                                <div class="flex items-center gap-1.5 mb-0.5">
                                                    <p class="text-sm font-black text-indigo-600 dark:text-indigo-400">Multiple Products</p>
                                                    <span class="text-[9px] bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 rounded-full px-2 py-0.5 font-black ring-1 ring-indigo-200 dark:ring-indigo-800">
                                                        {{ getBulkProductCount(inquiry.initial_message) }}
                                                    </span>
                                                </div>
                                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Bulk Inquiry</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2 max-w-xs">{{ inquiry.initial_message || '—' }}</p>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <span :class="statusBadge(inquiry.status)" class="px-3 py-1 rounded-full text-[9px] font-black uppercase ring-1 inline-flex items-center gap-1.5">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ formatStatus(inquiry.status) }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-center text-xs text-gray-500 dark:text-gray-400 font-medium">
                                        {{ formatDate(inquiry.last_message_at) }}
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <Link :href="route('client.conversation.show', inquiry.hash_key)"
                                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl text-[9px] font-black uppercase tracking-widest shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-105 active:scale-95 transition-all">
                                            Open
                                            <ArrowRight class="h-3.5 w-3.5" />
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="filteredInquiries.length === 0">
                                    <td colspan="5" class="px-8 py-20 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 dark:to-violet-900/20 rounded-full mb-4 animate-bounce-soft">
                                                <MessageSquare class="h-9 w-9 text-indigo-400" />
                                            </div>
                                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No conversations yet</p>
                                            <p class="text-xs text-gray-400 mt-1">Start by inquiring on a product.</p>
                                            <Link :href="route('client.products')" class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all">Browse products</Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { MessageSquare, Plus, Search, Package, ArrowRight, Layers, Sparkles } from 'lucide-vue-next';

const props = defineProps({
    inquiries: {
        type: Array,
        default: () => []
    }
});

const searchTerm = ref('');
const statusFilter = ref('all');

const filteredInquiries = computed(() => {
    let list = props.inquiries;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(i => i.product?.name?.toLowerCase().includes(term));
    }
    if (statusFilter.value !== 'all') {
        list = list.filter(i => i.status === statusFilter.value);
    }
    return list;
});

const openCount = computed(() => props.inquiries.filter(i => i.status === 'open').length);
const quotationCount = computed(() => props.inquiries.filter(i => i.status === 'quotation_sent').length);
const convertedCount = computed(() => props.inquiries.filter(i => i.status === 'converted').length);

const statusCount = (key) => {
    if (key === 'all') return props.inquiries.length;
    return props.inquiries.filter(i => i.status === key).length;
};

const statusBadge = (status) => {
    const map = {
        open: 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        quotation_sent: 'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30',
        converted: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        abandoned: 'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700'
    };
    return map[status] || 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const formatStatus = (status) => {
    const map = {
        open: 'Open',
        quotation_sent: 'Quotation Sent',
        converted: 'Converted',
        abandoned: 'Abandoned'
    };
    return map[status] || status;
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// Bulk inquiry helpers
const isBulkInquiry = (inquiry) => inquiry.product?.name === 'Bulk Inquiry' || !inquiry.product;

/**
 * Extracts the product count from the structured bulk message header.
 * Format: "📦 BULK PRODUCT INQUIRY — N Product(s)"
 */
const getBulkProductCount = (message) => {
    if (!message) return '';
    const match = message.match(/BULK PRODUCT INQUIRY\s*[—-]\s*(\d+)\s*Product/i);
    return match ? `${match[1]} items` : 'Bulk';
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
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
