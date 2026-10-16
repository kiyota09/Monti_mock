<template>
    <Head title="Inquiries - CRM Module" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

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
                                CRM · Workspace
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Client Inquiries</h1>
                            <p class="text-sm text-blue-100/90">{{ filteredInquiries.length }} of {{ inquiries.length }} inquir{{ inquiries.length !== 1 ? 'ies' : 'y' }} showing</p>
                        </div>
                        <button @click="refreshData"
                            class="rounded-2xl bg-white/15 p-3 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 hover:scale-105 active:scale-95 transition-all">
                            <RefreshCw class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchTerm" type="text" placeholder="Search by company or product..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition">
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <button v-for="key in ['all','open','quotation_sent','converted']" :key="key" @click="statusFilter = key"
                                :class="statusFilter === key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ key === 'all' ? 'All' : key.replace('_', ' ') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stat cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div v-for="(stat, index) in stats" :key="index"
                        :style="{ animationDelay: `${index * 80}ms` }"
                        class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur p-5 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="relative flex justify-between items-start">
                            <div>
                                <p class="text-[11px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">{{ stat.label }}</p>
                                <p class="text-4xl font-black tracking-tight text-gray-900 dark:text-white">{{ stat.value }}</p>
                                <p class="mt-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-indigo-500"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Live count</p>
                            </div>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <component :is="stat.icon" class="h-6 w-6" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inquiries panel -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-xl transition-shadow duration-300 overflow-hidden" style="animation-delay:120ms">

                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20">
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500 text-left">Company Details</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500 text-left">Message Snippet</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500 text-left">Status</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500 text-left">Last Activity</th>
                                    <th class="px-6 py-4 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="inquiry in filteredInquiries" :key="inquiry.id"
                                    class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="relative h-12 w-12 rounded-2xl flex items-center justify-center bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg transition-transform group-hover:scale-110 group-hover:rotate-3 flex-shrink-0">
                                                <Layers v-if="isBulkInquiry(inquiry)" class="h-5 w-5" />
                                                <Building2 v-else class="h-5 w-5" />
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-black text-gray-900 dark:text-white truncate tracking-tight group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ inquiry.client?.company_name }}</p>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span v-if="!isBulkInquiry(inquiry)" class="text-[11px] font-medium text-gray-400 dark:text-gray-500 truncate">
                                                        {{ inquiry.product?.name }}
                                                    </span>
                                                    <span v-else class="flex items-center gap-1.5 px-2 py-0.5 bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-500/30 text-[9px] font-black uppercase rounded-full">
                                                        <span class="h-1 w-1 rounded-full bg-current animate-pulse"></span>
                                                        {{ getBulkProductCount(inquiry.initial_message) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 max-w-xs line-clamp-1 italic">"{{ inquiry.initial_message || 'Empty inquiry content...' }}"</p>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span :class="statusBadge(inquiry.status)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full ring-1 ring-black/5 dark:ring-white/10 text-[9px] font-black uppercase tracking-wide">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse"></span>
                                            {{ inquiry.status.replace('_', ' ') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-xs font-bold text-gray-400 dark:text-gray-500 tracking-tight whitespace-nowrap">
                                        {{ formatDate(inquiry.last_message_at) }}
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <Link :href="route('crm.inquiry.show',
                                            inquiry.hash_key)"
                                            class="inline-flex items-center justify-center h-9 w-9 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-indigo-600 group-hover:text-white group-hover:translate-x-1 transition-all duration-300">
                                            <ArrowRight class="h-4 w-4" />
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="md:hidden">
                        <TransitionGroup name="card" tag="div" class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <div v-for="(inquiry, i) in filteredInquiries" :key="inquiry.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="p-5 space-y-4">
                                <div class="flex justify-between items-start gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white shadow-lg shrink-0">
                                            <Building2 class="h-6 w-6" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-black text-gray-900 dark:text-white truncate">{{ inquiry.client?.company_name }}</p>
                                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ formatDate(inquiry.last_message_at) }}</p>
                                        </div>
                                    </div>
                                    <span :class="statusBadge(inquiry.status)" class="px-3 py-1 rounded-full ring-1 ring-black/5 text-[9px] font-black uppercase flex items-center gap-1 shrink-0">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ inquiry.status }}
                                    </span>
                                </div>

                                <div class="bg-slate-50 dark:bg-zinc-800/50 p-4 rounded-2xl border border-gray-100 dark:border-zinc-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed italic line-clamp-1">"{{ inquiry.initial_message }}"</p>
                                </div>

                                <Link :href="route('crm.inquiry.show', inquiry.hash_key)"
                                    class="w-full flex items-center justify-center gap-2 py-3.5 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white rounded-2xl text-xs font-black uppercase tracking-[0.2em] shadow-lg shadow-indigo-500/25 active:scale-[0.98] transition-all">
                                    Open Inquiry
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                            </div>
                        </TransitionGroup>
                    </div>

                    <div v-if="filteredInquiries.length === 0" class="py-20 text-center">
                        <div class="inline-flex items-center justify-center p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <MessageSquare class="h-9 w-9 text-indigo-400" />
                        </div>
                        <h3 class="text-sm font-black text-gray-700 dark:text-gray-200">Nothing to show</h3>
                        <p class="text-xs text-gray-400 mt-1 italic">Adjust your search or filter to find inquiries.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    MessageSquare, RefreshCw, Search, Building2,
    ArrowRight, Layers, Clock, Send, CheckCircle2
} from 'lucide-vue-next';

const props = defineProps({
    inquiries: {
        type: Array,
        default: () => []
    }
});

const searchTerm = ref('');
const statusFilter = ref('all');

// Stats configuration with icon support
const stats = computed(() => [
    {
        label: 'In Queue',
        value: openCount.value,
        colorClass: 'bg-blue-600',
        textColor: 'text-blue-600',
        icon: Clock
    },
    {
        label: 'Active Proposals',
        value: quotationSentCount.value,
        colorClass: 'bg-amber-400',
        textColor: 'text-amber-500',
        icon: Send
    },
    {
        label: 'Closed Deals',
        value: convertedCount.value,
        colorClass: 'bg-slate-950',
        textColor: 'text-slate-900 dark:text-white',
        icon: CheckCircle2
    }
]);

const filteredInquiries = computed(() => {
    let list = props.inquiries;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(i =>
            i.client?.company_name?.toLowerCase().includes(term) ||
            i.product?.name?.toLowerCase().includes(term)
        );
    }
    if (statusFilter.value !== 'all') {
        list = list.filter(i => i.status === statusFilter.value);
    }
    return list;
});

const openCount = computed(() => props.inquiries.filter(i => i.status === 'open').length);
const quotationSentCount = computed(() => props.inquiries.filter(i => i.status === 'quotation_sent').length);
const convertedCount = computed(() => props.inquiries.filter(i => i.status === 'converted').length);

const statusBadge = (status) => {
    const map = {
        open: 'bg-blue-50 text-blue-600',
        quotation_sent: 'bg-amber-50 text-amber-600',
        converted: 'bg-black text-white dark:bg-white dark:text-black',
        abandoned: 'bg-slate-100 text-slate-400'
    };
    return map[status] || 'bg-slate-100 text-slate-500';
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};

const isBulkInquiry = (inquiry) => inquiry.product?.name === 'Bulk Inquiry' || !inquiry.product;

const getBulkProductCount = (message) => {
    if (!message) return 'Bulk';
    const match = message.match(/BULK PRODUCT INQUIRY\s*[—-]\s*(\d+)\s*Product/i);
    return match ? `${match[1]} Products` : 'Multi-Item';
};

const refreshData = () => {
    router.reload({
        only: ['inquiries'],
        onStart: () => {}, // Add loading states if needed
    });
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
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }

/* Custom Line Clamping */
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
