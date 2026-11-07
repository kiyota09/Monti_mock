<template>
    <Head title="Inquiries - CRM Module" />
    <AuthenticatedLayout>
        <div :class="sidebarCollapsed ? 'md:left-0' : 'md:left-64'"
            class="fixed inset-x-0 bottom-0 top-32 md:top-16 z-20 overflow-hidden bg-stone-100 dark:bg-zinc-950">
            <div class="flex h-full w-full flex-col overflow-hidden p-2 pt-9 sm:p-3 sm:pt-9 lg:p-4 lg:pt-9 gap-2 sm:gap-3">

                <!-- Page header -->
                <div data-erp-page-banner="fallback" class="flex flex-wrap items-end justify-between gap-4 shrink-0">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-stone-900 dark:text-white">Client inquiries</h1>
                        <p class="mt-1 text-sm text-stone-500 dark:text-zinc-400">
                            {{ clients.length }} client{{ clients.length !== 1 ? 's' : '' }} ·
                            {{ filteredInquiries.length }} of {{ inquiries.length }} inquir{{ inquiries.length !== 1 ? 'ies' : 'y' }}
                        </p>
                    </div>

                    <!-- Stats as a compact strip -->
                    <div class="flex items-stretch divide-x divide-white/20 rounded-2xl bg-white/10 ring-1 ring-white/15">
                        <div v-for="stat in stats" :key="stat.label" class="flex items-center gap-3 px-4 py-2.5">
                            <span :class="stat.dot" class="h-2.5 w-2.5 rounded-full"></span>
                            <div>
                                <p class="text-lg font-bold leading-none text-stone-900 dark:text-white tabular-nums">{{ stat.value }}</p>
                                <p class="mt-1 text-xs text-stone-500 dark:text-zinc-400">{{ stat.label }}</p>
                            </div>
                        </div>
                        <button @click="refreshData" aria-label="Refresh inquiries"
                            class="flex items-center px-4 text-white/75 hover:text-white rounded-r-2xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-white transition-colors">
                            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': refreshing }" />
                        </button>
                    </div>
                </div>

                <!-- Workspace: client rail + conversation panel -->
                <div class="flex-1 min-h-0 grid grid-rows-[minmax(0,1fr)] lg:grid-cols-[340px_1fr] gap-2 sm:gap-3 items-stretch">

                    <!-- LEFT: clients -->
                    <aside :class="mobileDetail ? 'hidden lg:flex' : 'flex'"
                        class="flex-col h-full min-h-0 rounded-3xl bg-white dark:bg-zinc-900 ring-1 ring-stone-200 dark:ring-zinc-800 overflow-hidden">

                        <div class="p-4 space-y-3 border-b border-stone-100 dark:border-zinc-800 shrink-0">
                            <div class="relative">
                                <Search class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                                <input v-model="searchTerm" type="text" placeholder="Search company, product or message"
                                    class="w-full rounded-xl border-0 bg-stone-100 dark:bg-zinc-800 py-2.5 pl-10 pr-3 text-sm text-stone-900 dark:text-white placeholder:text-stone-400 focus:ring-2 focus:ring-indigo-500 outline-none transition">
                            </div>
                            <div class="flex gap-1.5 flex-wrap">
                                <button v-for="f in filters" :key="f.key" @click="statusFilter = f.key"
                                    :class="statusFilter === f.key
                                        ? 'bg-stone-900 text-white dark:bg-white dark:text-zinc-900'
                                        : 'bg-stone-100 text-stone-600 hover:bg-stone-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700'"
                                    class="rounded-full px-3 py-1 text-xs font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500">
                                    {{ f.label }}
                                </button>
                            </div>
                        </div>

                        <ul v-if="clients.length > 0" class="flex-1 min-h-0 overflow-y-auto divide-y divide-stone-100 dark:divide-zinc-800">
                            <li v-for="client in clients" :key="client.key">
                                <button @click="selectClient(client.key)"
                                    :class="activeClient?.key === client.key
                                        ? 'bg-indigo-50 dark:bg-indigo-500/10 shadow-[inset_3px_0_0_0] shadow-indigo-600'
                                        : 'hover:bg-stone-50 dark:hover:bg-zinc-800/60'"
                                    class="w-full flex items-center gap-3 px-4 py-3.5 text-left transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-500">
                                    <span :class="client.logoUrl ? 'overflow-hidden bg-white ring-1 ring-stone-200 dark:ring-zinc-700' : client.tone"
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold">
                                        <img v-if="client.logoUrl" :src="client.logoUrl" :alt="client.name"
                                            class="h-full w-full object-cover" loading="lazy" />
                                        <template v-else>{{ client.initials }}</template>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-baseline justify-between gap-2">
                                            <span class="truncate text-sm font-semibold text-stone-900 dark:text-white">{{ client.name }}</span>
                                            <span class="shrink-0 text-[11px] text-stone-400 dark:text-zinc-500">{{ formatShortDate(client.latest.last_message_at) }}</span>
                                        </span>
                                        <span class="mt-0.5 block truncate text-xs text-stone-500 dark:text-zinc-400">
                                            {{ previewText(client.latest) }}
                                        </span>
                                        <span class="mt-1.5 flex items-center gap-2">
                                            <span class="flex -space-x-0.5">
                                                <span v-for="s in client.statuses" :key="s" :class="statusDot(s)" class="h-2 w-2 rounded-full ring-2 ring-white dark:ring-zinc-900"></span>
                                            </span>
                                            <span class="text-[11px] text-stone-400 dark:text-zinc-500">
                                                {{ client.inquiries.length }} inquir{{ client.inquiries.length !== 1 ? 'ies' : 'y' }}
                                            </span>
                                        </span>
                                    </span>
                                </button>
                            </li>
                        </ul>

                        <div v-if="clients.length === 0" class="flex-1 min-h-0 flex flex-col items-center justify-center px-6 py-14 text-center">
                            <Inbox class="mx-auto h-8 w-8 text-stone-300 dark:text-zinc-600" />
                            <p class="mt-3 text-sm font-medium text-stone-700 dark:text-zinc-200">No clients match</p>
                            <p class="mt-1 text-xs text-stone-400">Clear the search or choose a different status.</p>
                            <button @click="resetFilters" class="mt-4 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Clear filters</button>
                        </div>
                    </aside>

                    <!-- RIGHT: selected client's conversations -->
                    <main v-if="activeClient" :class="mobileDetail ? 'block' : 'hidden lg:block'" class="min-w-0 h-full min-h-0 overflow-y-auto">
                        <button @click="mobileDetail = false"
                            class="lg:hidden mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 dark:text-zinc-300">
                            <ArrowLeft class="h-4 w-4" /> All clients
                        </button>

                        <Transition name="swap" mode="out-in">
                            <section :key="activeClient.key" class="space-y-4">

                                <!-- Client header -->
                                <div class="rounded-3xl bg-white dark:bg-zinc-900 ring-1 ring-stone-200 dark:ring-zinc-800 p-5 sm:p-6">
                                    <div class="flex flex-wrap items-center gap-4">
                                        <span :class="activeClient.logoUrl ? 'overflow-hidden bg-white ring-1 ring-stone-200 dark:ring-zinc-700' : activeClient.tone"
                                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl text-xl font-bold">
                                            <img v-if="activeClient.logoUrl" :src="activeClient.logoUrl" :alt="activeClient.name"
                                                class="h-full w-full object-cover" />
                                            <template v-else>{{ activeClient.initials }}</template>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <h2 class="truncate text-xl sm:text-2xl font-bold tracking-tight text-stone-900 dark:text-white">{{ activeClient.name }}</h2>
                                            <p class="mt-1 text-sm text-stone-500 dark:text-zinc-400">
                                                {{ activeClient.inquiries.length }} conversation{{ activeClient.inquiries.length !== 1 ? 's' : '' }}
                                                · last activity {{ formatDate(activeClient.latest.last_message_at) }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Status breakdown bar -->
                                    <div class="mt-5">
                                        <div class="flex h-2 overflow-hidden rounded-full bg-stone-100 dark:bg-zinc-800">
                                            <span v-for="seg in activeClient.breakdown" :key="seg.status" :class="statusDot(seg.status)"
                                                :style="{ width: (seg.count / activeClient.inquiries.length * 100) + '%' }"></span>
                                        </div>
                                        <div class="mt-2.5 flex flex-wrap gap-x-5 gap-y-1">
                                            <span v-for="seg in activeClient.breakdown" :key="seg.status" class="flex items-center gap-1.5 text-xs text-stone-500 dark:text-zinc-400">
                                                <span :class="statusDot(seg.status)" class="h-2 w-2 rounded-full"></span>
                                                {{ seg.count }} {{ statusLabel(seg.status).toLowerCase() }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Conversation threads -->
                                <article v-for="inquiry in activeClient.inquiries" :key="inquiry.id"
                                    class="group rounded-3xl bg-white dark:bg-zinc-900 ring-1 ring-stone-200 dark:ring-zinc-800 hover:ring-indigo-300 dark:hover:ring-indigo-500/50 transition-shadow overflow-hidden">

                                    <div class="flex">
                                        <!-- status rail -->
                                        <span :class="statusDot(inquiry.status)" class="w-1.5 shrink-0"></span>

                                        <div class="flex-1 min-w-0 p-5">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-stone-100 dark:bg-zinc-800 ring-1 ring-stone-200 dark:ring-zinc-700">
                                                        <img v-if="!isBulkInquiry(inquiry) && productImage(inquiry)"
                                                            :src="productImage(inquiry)" :alt="inquiry.product?.name"
                                                            class="h-full w-full object-cover" loading="lazy" />
                                                        <component v-else :is="isBulkInquiry(inquiry) ? Layers : Package" class="h-4 w-4 text-stone-400" />
                                                    </span>
                                                    <h3 class="truncate text-sm font-semibold text-stone-900 dark:text-white">
                                                        {{ isBulkInquiry(inquiry) ? 'Bulk inquiry' : inquiry.product?.name }}
                                                    </h3>
                                                    <span v-if="isBulkInquiry(inquiry)"
                                                        class="shrink-0 rounded-full bg-amber-100 dark:bg-amber-500/15 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-300">
                                                        {{ getBulkProductCount(inquiry.initial_message) }}
                                                    </span>
                                                </div>
                                                <span :class="statusBadge(inquiry.status)" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium">
                                                    <span :class="statusDot(inquiry.status)" class="h-1.5 w-1.5 rounded-full"></span>
                                                    {{ statusLabel(inquiry.status) }}
                                                </span>
                                            </div>

                                            <!-- message bubble -->
                                            <div class="mt-4 flex items-start gap-3">
                                                <span :class="activeClient.logoUrl ? 'overflow-hidden bg-white ring-1 ring-stone-200 dark:ring-zinc-700' : activeClient.tone"
                                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold">
                                                    <img v-if="activeClient.logoUrl" :src="activeClient.logoUrl" :alt="activeClient.name"
                                                        class="h-full w-full object-cover" />
                                                    <template v-else>{{ activeClient.initials }}</template>
                                                </span>
                                                <div class="min-w-0 rounded-2xl rounded-tl-md bg-stone-100 dark:bg-zinc-800 px-4 py-3">
                                                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-stone-400 dark:text-zinc-500">
                                                        {{ inquiry.latest_message?.sender_type === 'eco' ? 'CRM Team' : (activeClient.name || 'Client') }}
                                                    </p>
                                                    <p class="text-sm leading-relaxed text-stone-700 dark:text-zinc-200 line-clamp-3 whitespace-pre-line">{{ lastMessageText(inquiry) }}</p>
                                                </div>
                                            </div>

                                            <div class="mt-4 flex items-center justify-between gap-3">
                                                <span class="flex items-center gap-1.5 text-xs text-stone-400 dark:text-zinc-500">
                                                    <Clock class="h-3.5 w-3.5" /> {{ formatDate(inquiry.last_message_at) }}
                                                </span>
                                                <Link :href="route('crm.inquiry.show', inquiry.hash_key)"
                                                    class="inline-flex items-center gap-1.5 rounded-xl bg-stone-900 dark:bg-white px-3.5 py-2 text-xs font-semibold text-white dark:text-zinc-900 hover:bg-indigo-600 dark:hover:bg-indigo-400 dark:hover:text-white transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                                                    Open conversation
                                                    <ArrowRight class="h-3.5 w-3.5" />
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </section>
                        </Transition>
                    </main>

                    <!-- Nothing at all -->
                    <main v-else class="hidden lg:flex flex-col items-center justify-center h-full rounded-3xl bg-white dark:bg-zinc-900 ring-1 ring-stone-200 dark:ring-zinc-800 py-28 text-center">
                        <MessageSquare class="h-10 w-10 text-stone-300 dark:text-zinc-600" />
                        <p class="mt-3 text-sm font-medium text-stone-700 dark:text-zinc-200">No conversation to show</p>
                        <p class="mt-1 text-xs text-stone-400">Inquiries from your clients will appear here.</p>
                    </main>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useSidebarToggle } from '@/Layouts/composables/useSidebarToggle';
import {
    MessageSquare, RefreshCw, Search, ArrowRight, ArrowLeft,
    Layers, Package, Clock, Inbox
} from 'lucide-vue-next';

const props = defineProps({
    inquiries: {
        type: Array,
        default: () => []
    }
});

const { sidebarCollapsed } = useSidebarToggle();

const searchTerm = ref('');
const statusFilter = ref('all');
const selectedKey = ref(null);
const mobileDetail = ref(false);
const refreshing = ref(false);

// The page is fixed to the viewport; lock the underlying document so no
// page-level scrollbar appears behind it.
let previousBodyOverflow = '';
onMounted(() => {
    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
});
onBeforeUnmount(() => {
    document.body.style.overflow = previousBodyOverflow;
});

const filters = [
    { key: 'all', label: 'All' },
    { key: 'open', label: 'Open' },
    { key: 'quotation_sent', label: 'Quotation sent' },
    { key: 'converted', label: 'Converted' },
    { key: 'rejected', label: 'Rejected' },
];

/* ---------- status helpers ---------- */
const STATUS = {
    open:           { label: 'Open',           dot: 'bg-blue-500',    badge: 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' },
    quotation_sent: { label: 'Quotation sent', dot: 'bg-amber-500',   badge: 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' },
    converted:      { label: 'Converted',      dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' },
    abandoned:      { label: 'Abandoned',      dot: 'bg-stone-400',   badge: 'bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400' },
    rejected:       { label: 'Rejected',       dot: 'bg-red-500',     badge: 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300' },
};
const statusLabel = (s) => STATUS[s]?.label ?? (s || '').replace('_', ' ');
const statusDot = (s) => STATUS[s]?.dot ?? 'bg-stone-300';
const statusBadge = (s) => STATUS[s]?.badge ?? 'bg-stone-100 text-stone-500';

/* ---------- stats ---------- */
const countBy = (s) => props.inquiries.filter(i => i.status === s).length;
const stats = computed(() => [
    { label: 'In queue',         value: countBy('open'),           dot: 'bg-blue-500' },
    { label: 'Active proposals', value: countBy('quotation_sent'), dot: 'bg-amber-500' },
    { label: 'Closed deals',     value: countBy('converted'),      dot: 'bg-emerald-500' },
]);

/* ---------- filtering + grouping by client ---------- */
const filteredInquiries = computed(() => {
    let list = props.inquiries;
    if (searchTerm.value) {
        const term = searchTerm.value.toLowerCase();
        list = list.filter(i =>
            i.client?.company_name?.toLowerCase().includes(term) ||
            i.product?.name?.toLowerCase().includes(term) ||
            i.initial_message?.toLowerCase().includes(term)
        );
    }
    if (statusFilter.value !== 'all') {
        list = list.filter(i => i.status === statusFilter.value);
    }
    return list;
});

const TONES = [
    'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300',
    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
    'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
    'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
    'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/20 dark:text-fuchsia-300',
];
const toneFor = (name) => {
    let h = 0;
    for (const c of name) h = (h * 31 + c.charCodeAt(0)) >>> 0;
    return TONES[h % TONES.length];
};
const initialsFor = (name) =>
    name.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase() || '?';

const time = (i) => new Date(i.last_message_at || 0).getTime();

const clients = computed(() => {
    const map = new Map();
    for (const inq of filteredInquiries.value) {
        const name = inq.client?.company_name || 'Unknown client';
        const key = String(inq.client?.id ?? inq.client_id ?? name);
        if (!map.has(key)) map.set(key, { key, name, inquiries: [], logoUrl: inq.client?.logo?.logo_url ?? null });
        map.get(key).inquiries.push(inq);
    }
    return [...map.values()]
        .map(c => {
            c.inquiries.sort((a, b) => time(b) - time(a));
            const order = ['open', 'quotation_sent', 'converted', 'rejected', 'abandoned'];
            const breakdown = order
                .map(status => ({ status, count: c.inquiries.filter(i => i.status === status).length }))
                .filter(s => s.count > 0);
            return {
                ...c,
                latest: c.inquiries[0],
                initials: initialsFor(c.name),
                tone: toneFor(c.name),
                breakdown,
                statuses: breakdown.map(b => b.status),
            };
        })
        .sort((a, b) => time(b.latest) - time(a.latest));
});

const activeClient = computed(() =>
    clients.value.find(c => c.key === selectedKey.value) ?? clients.value[0] ?? null
);

const selectClient = (key) => {
    selectedKey.value = key;
    mobileDetail.value = true;
};

const resetFilters = () => {
    searchTerm.value = '';
    statusFilter.value = 'all';
};

/* ---------- formatting ---------- */
const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};
const formatShortDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
};

const isBulkInquiry = (inquiry) => inquiry.product?.name === 'Bulk Inquiry' || !inquiry.product;

const getBulkProductCount = (message) => {
    if (!message) return 'Bulk';
    const match = message.match(/BULK PRODUCT INQUIRY\s*[—-]\s*(\d+)\s*Product/i);
    return match ? `${match[1]} products` : 'Multi-item';
};

const previewText = (inquiry) => {
    const label = isBulkInquiry(inquiry) ? 'Bulk inquiry' : inquiry.product?.name;
    return label || inquiry.initial_message || 'No message content';
};

const productImage = (inquiry) => inquiry.product?.images?.[0]?.url ?? null;

const lastMessageText = (inquiry) => {
    const latest = inquiry.latest_message;
    if (!latest) return inquiry.initial_message || 'No message content.';
    return (latest.message || '').trim() || 'No message content.';
};

const refreshData = () => {
    router.reload({
        only: ['inquiries'],
        onStart: () => { refreshing.value = true; },
        onFinish: () => { refreshing.value = false; },
    });
};
</script>

<style scoped>
.swap-enter-active { transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.22, 1, 0.36, 1); }
.swap-leave-active { transition: opacity 0.12s ease; }
.swap-enter-from { opacity: 0; transform: translateX(14px); }
.swap-leave-to { opacity: 0; }

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@media (prefers-reduced-motion: reduce) {
    .swap-enter-active, .swap-leave-active { transition: none; }
}
</style>
