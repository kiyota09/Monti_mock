<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Filter, Download, Plus, ChevronDown, ArrowDownRight, ArrowUpRight,
    Settings2, ArrowUpRightSquare, Clock, X, Wallet, TrendingUp, Users, 
    Factory, Package, ShoppingCart, DollarSign, Activity
} from 'lucide-vue-next';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            totalOrders: 0,
            totalRevenue: 0,
            activeEmployees: 0,
            pendingLeads: 0
        })
    },
    revenueTrend: {
        type: Object,
        default: () => ({
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            values: [40, 75, 45, 90, 60, 50, 85],
            rawValues: [0, 0, 0, 0, 0, 0, 0]
        })
    },
    activityStats: {
        type: Object,
        default: () => ({
            receipts: { value: '0.00', trend: '+0%', isUp: true },
            contributions: { value: '0.00', trend: '+0%', isUp: true },
            owes: { value: '0.00', trend: '0%', isUp: false }
        })
    },
    transactions: {
        type: Array,
        default: () => []
    },
    modules: {
        type: Array,
        default: () => []
    }
});

const showBanner = ref(true);

// Format total revenue for display
const formattedTotalRevenue = computed(() => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(props.stats.totalRevenue);
});

// Format total orders
const formattedTotalOrders = computed(() => {
    return new Intl.NumberFormat('en-US').format(props.stats.totalOrders);
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="CEO Dashboard" />

        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30 font-sans">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <TrendingUp class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Activity class="h-3.5 w-3.5" /> CEO · Executive Overview
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Welcome back, Monti Leadership!</h1>
                            <p class="text-sm text-blue-100/90">Control your investment, income, and expenses.</p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                ₱{{ formattedTotalRevenue }} revenue
                            </span>
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ formattedTotalOrders }} orders
                            </span>
                        </div>
                    </div>

                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="route('ceo.board-pack')"
                                class="flex items-center gap-2 rounded-2xl bg-white dark:bg-zinc-900 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 dark:text-indigo-300 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95">
                                <Download class="w-4 h-4" /> Board pack
                            </Link>
                            <Link :href="route('ceo.reports')"
                                class="flex items-center gap-2 rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white backdrop-blur transition-all duration-200 hover:bg-white/25 active:scale-95">
                                <TrendingUp class="w-4 h-4" /> Reports
                            </Link>
                            <Link :href="route('ceo.traceability')"
                                class="flex items-center gap-2 rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white backdrop-blur transition-all duration-200 hover:bg-white/25 active:scale-95">
                                <Package class="w-4 h-4" /> Traceability
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">

                    <!-- Left Column: Summary + Small Cards -->
                    <div class="xl:col-span-4 flex flex-col gap-4">

                        <!-- Summary Card -->
                        <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-6 sm:p-7 overflow-hidden" style="animation-delay: 80ms">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="relative flex justify-between items-center mb-8">
                                <div>
                                    <h2 class="text-xl font-black tracking-tight text-gray-900 dark:text-zinc-100">Summary</h2>
                                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-zinc-500">Track your performance.</p>
                                </div>
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-500">
                                    <Wallet class="w-4 h-4" />
                                </span>
                            </div>

                            <div class="relative flex gap-4 mb-10">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-zinc-400 font-bold uppercase mb-1">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                                            <ArrowDownRight class="w-3 h-3 text-blue-500" />
                                        </span>
                                        Total income
                                    </div>
                                    <div class="text-2xl font-black tracking-tight text-gray-900 dark:text-zinc-100">₱{{ formattedTotalRevenue }}</div>
                                </div>
                                <div class="w-px bg-gray-100 dark:bg-zinc-800"></div>
                                <div class="flex-1 pl-2">
                                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-zinc-400 font-bold uppercase mb-1">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                                            <ArrowUpRight class="w-3 h-3 text-emerald-500" />
                                        </span>
                                        Total orders
                                    </div>
                                    <div class="text-2xl font-black tracking-tight text-gray-900 dark:text-zinc-100">{{ formattedTotalOrders }}</div>
                                </div>
                            </div>

                            <!-- Bar Chart -->
                            <div class="relative h-40 flex items-end justify-between gap-3 mt-4">
                                <div v-for="(val, index) in revenueTrend.values" :key="index"
                                    class="w-full relative group/bar flex flex-col justify-end h-full">
                                    <div class="w-full rounded-t-xl rounded-b-sm transition-all duration-500 group-hover/bar:scale-y-105 origin-bottom"
                                        :class="[val > 50 ? 'bg-gradient-to-t from-blue-600 to-indigo-500 shadow-lg shadow-indigo-500/20' : 'bg-gray-200 dark:bg-zinc-700']"
                                        :style="{ height: `${val}%` }">
                                        <div class="opacity-0 group-hover/bar:opacity-100 transition absolute -top-6 left-1/2 transform -translate-x-1/2 bg-gray-800 dark:bg-zinc-700 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                                            ₱{{ new Intl.NumberFormat().format(revenueTrend.rawValues[index]) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="relative flex justify-between mt-3 text-[10px] font-bold uppercase text-gray-400 dark:text-zinc-500">
                                <span v-for="(label, idx) in revenueTrend.labels" :key="idx">{{ label }}</span>
                            </div>
                        </div>

                        <!-- Two small stat cards -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-6 overflow-hidden" style="animation-delay: 140ms">
                                <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <div class="relative flex items-center gap-2 text-xs text-gray-500 dark:text-zinc-400 font-bold uppercase mb-4">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30"><Clock class="w-3 h-3 text-blue-500" /></span> Active Employees
                                </div>
                                <div class="relative flex items-center gap-1 text-sm font-black text-gray-900 dark:text-zinc-100 mb-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" /> Current
                                </div>
                                <div class="relative text-2xl font-black text-gray-900 dark:text-zinc-100">{{ stats.activeEmployees }}</div>
                            </div>
                            <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-6 overflow-hidden" style="animation-delay: 200ms">
                                <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <div class="relative flex items-center gap-2 text-xs text-gray-500 dark:text-zinc-400 font-bold uppercase mb-4">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/20"><Activity class="w-3 h-3 text-violet-500" /></span> Pending Leads
                                </div>
                                <div class="relative flex items-center gap-1 text-sm font-black text-indigo-600 dark:text-indigo-400 mb-1">
                                    <ArrowUpRight class="w-4 h-4" /> Inquiry
                                </div>
                                <div class="relative text-2xl font-black text-gray-900 dark:text-zinc-100">{{ stats.pendingLeads }}</div>
                            </div>
                        </div>

                        <!-- Banner (tip) -->
                        <Transition name="modal">
                            <div v-if="showBanner" class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 p-6 flex justify-between items-center overflow-hidden transition-all duration-300" style="animation-delay: 260ms">
                                <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                <h3 class="relative font-black text-base text-gray-900 dark:text-zinc-100 leading-tight max-w-[200px] tracking-tight">
                                    How is your business management going?
                                </h3>
                                <button @click="showBanner = false" class="relative w-10 h-10 rounded-full bg-gray-100 dark:bg-zinc-800 hover:bg-gray-200 dark:hover:bg-zinc-700 flex items-center justify-center transition active:scale-95">
                                    <X class="w-5 h-5 text-gray-500 dark:text-zinc-400" />
                                </button>
                            </div>
                        </Transition>
                    </div>

                    <!-- Right Column: Activity + Transactions -->
                    <div class="xl:col-span-8 flex flex-col gap-4">

                        <!-- Activity section -->
                        <div class="animate-fade-up" style="animation-delay: 120ms">
                            <div class="flex justify-between items-center mb-4 px-2">
                                <div>
                                    <h2 class="text-xl font-black tracking-tight text-gray-900 dark:text-zinc-100">Activity</h2>
                                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-zinc-500">Track your activity.</p>
                                </div>
                                <div class="flex gap-2">
                                    <Link :href="route('ceo.board-pack')" title="Printable board pack"
                                        class="w-10 h-10 rounded-2xl bg-white/80 dark:bg-zinc-900/80 backdrop-blur flex items-center justify-center shadow-sm border border-gray-100 dark:border-zinc-800 hover:-translate-y-0.5 hover:shadow-lg transition-all">
                                        <Download class="w-4 h-4 text-gray-600 dark:text-zinc-400" />
                                    </Link>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Receipts Card -->
                                <div class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-6 flex flex-col overflow-hidden">
                                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                    <div class="flex items-center gap-3 mb-6 relative z-10">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/30">
                                            <Wallet class="w-4 h-4 text-blue-500" />
                                        </span>
                                        <span class="font-black text-sm text-gray-900 dark:text-zinc-100">Receipts</span>
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse ml-auto" />
                                    </div>
                                    <div class="relative z-10">
                                        <div class="text-indigo-600 dark:text-indigo-400 text-sm font-black flex items-center gap-1 mb-1">
                                            <ArrowUpRight class="w-4 h-4" /> {{ activityStats.receipts.trend }}
                                        </div>
                                        <div class="text-3xl font-black tracking-tight text-gray-900 dark:text-zinc-100">₱{{ activityStats.receipts.value }}</div>
                                    </div>
                                    <div class="absolute bottom-0 left-0 w-full h-24 opacity-80 group-hover:opacity-100 transition duration-500">
                                        <svg viewBox="0 0 200 60" preserveAspectRatio="none" class="w-full h-full">
                                            <path d="M0,40 Q10,30 20,45 T40,35 T60,50 T80,20 T100,45 T120,25 T140,50 T160,15 T180,35 T200,20" fill="none" stroke="#6366F1" stroke-width="2.5" stroke-linecap="round" />
                                            <path d="M0,40 Q10,30 20,45 T40,35 T60,50 T80,20 T100,45 T120,25 T140,50 T160,15 T180,35 T200,20 L200,60 L0,60 Z" fill="url(#gradBlue)" opacity="0.15" />
                                            <defs>
                                                <linearGradient id="gradBlue" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#6366F1" />
                                                    <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                                                </linearGradient>
                                            </defs>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Contributions Card -->
                                <div class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-6 flex flex-col overflow-hidden">
                                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                                    <div class="flex items-center gap-3 mb-6 relative z-10">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20">
                                            <TrendingUp class="w-4 h-4 text-emerald-500" />
                                        </span>
                                        <span class="font-black text-sm text-gray-900 dark:text-zinc-100">Contributions</span>
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse ml-auto" />
                                    </div>
                                    <div class="relative z-10">
                                        <div class="text-indigo-600 dark:text-indigo-400 text-sm font-black flex items-center gap-1 mb-1">
                                            <ArrowUpRight class="w-4 h-4" /> {{ activityStats.contributions.trend }}
                                        </div>
                                        <div class="text-3xl font-black tracking-tight text-gray-900 dark:text-zinc-100">₱{{ activityStats.contributions.value }}</div>
                                    </div>
                                    <div class="absolute bottom-0 left-0 w-full h-24 opacity-80 group-hover:opacity-100 transition duration-500">
                                        <svg viewBox="0 0 200 60" preserveAspectRatio="none" class="w-full h-full">
                                            <path d="M0,50 Q15,20 30,40 T60,25 T90,45 T120,30 T150,55 T180,25 T200,40" fill="none" stroke="#6366F1" stroke-width="2.5" stroke-linecap="round" />
                                            <path d="M0,50 Q15,20 30,40 T60,25 T90,45 T120,30 T150,55 T180,25 T200,40 L200,60 L0,60 Z" fill="url(#gradBlue2)" opacity="0.15" />
                                            <defs>
                                                <linearGradient id="gradBlue2" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#6366F1" />
                                                    <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                                                </linearGradient>
                                            </defs>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Owes Card -->
                                <div class="group relative rounded-3xl p-6 shadow-sm flex flex-col overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white shadow-xl shadow-indigo-500/20 hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300">
                                    <div class="absolute -top-20 -right-20 h-48 w-48 rounded-full bg-white/10 blur-3xl animate-float" />
                                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                                    <div class="flex items-center gap-3 mb-6 relative z-10">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/30">
                                            <DollarSign class="w-4 h-4 text-white" />
                                        </span>
                                        <span class="font-black text-sm">Owes</span>
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse ml-auto" />
                                    </div>
                                    <div class="relative z-10">
                                        <div class="text-amber-200 text-sm font-black flex items-center gap-1 mb-1">
                                            <ArrowDownRight class="w-4 h-4" /> {{ activityStats.owes.trend }}
                                        </div>
                                        <div class="text-3xl font-black tracking-tight">₱{{ activityStats.owes.value }}</div>
                                    </div>
                                    <div class="absolute bottom-0 left-0 w-full h-24 opacity-90 group-hover:opacity-100 transition duration-500">
                                        <svg viewBox="0 0 200 60" preserveAspectRatio="none" class="w-full h-full">
                                            <path d="M0,35 Q20,15 40,45 T80,25 T120,50 T160,30 T200,45" fill="none" stroke="#ffffff" stroke-opacity="0.7" stroke-width="2.5" stroke-linecap="round" />
                                            <path d="M0,35 Q20,15 40,45 T80,25 T120,50 T160,30 T200,45 L200,60 L0,60 Z" fill="url(#gradLightBlue)" opacity="0.2" />
                                            <defs>
                                                <linearGradient id="gradLightBlue" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#ffffff" />
                                                    <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                                                </linearGradient>
                                            </defs>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transactions Table -->
                        <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 p-6 sm:p-7 flex-1 overflow-hidden" style="animation-delay: 180ms">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="relative flex justify-between items-center mb-6">
                                <div>
                                    <h2 class="text-xl font-black tracking-tight text-gray-900 dark:text-zinc-100">Transactions history</h2>
                                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-zinc-500">Track your history.</p>
                                </div>
                                <button class="w-10 h-10 rounded-2xl bg-gray-100 dark:bg-zinc-800 flex items-center justify-center hover:bg-indigo-600 hover:text-white text-gray-500 dark:text-zinc-400 transition-all duration-300">
                                    <Settings2 class="w-4 h-4" />
                                </button>
                            </div>

                            <div class="relative overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="text-gray-400 dark:text-zinc-500 text-[10px] font-black uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                            <th class="pb-4 font-black px-2">Name</th>
                                            <th class="pb-4 font-black px-2">ID</th>
                                            <th class="pb-4 font-black px-2">Status</th>
                                            <th class="pb-4 font-black px-2">Date</th>
                                            <th class="pb-4 font-black px-2 text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm">
                                        <tr v-for="tx in transactions" :key="tx.id" class="border-b border-gray-50 dark:border-zinc-800/60 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/30 transition-colors">
                                            <td class="py-4 px-2">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center font-black text-white text-sm overflow-hidden shadow-lg flex-shrink-0">
                                                        {{ tx.avatar }}
                                                    </div>
                                                    <div>
                                                        <div class="font-black text-gray-900 dark:text-zinc-100 tracking-tight">{{ tx.name }}</div>
                                                        <div class="text-xs text-gray-500 dark:text-zinc-400 font-medium">{{ tx.role }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-2 font-bold text-gray-900 dark:text-zinc-100">{{ tx.id }}</td>
                                            <td class="py-4 px-2">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase ring-1"
                                                    :class="tx.status === 'Completed' || tx.status === 'Approved' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 ring-emerald-200 dark:text-emerald-300 dark:ring-emerald-500/30' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 ring-blue-200 dark:ring-blue-500/30'">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                                                    {{ tx.status }}
                                                </span>
                                            </td>
                                            <td class="py-4 px-2 font-bold text-gray-900 dark:text-zinc-100">{{ tx.date }}</td>
                                            <td class="py-4 px-2 text-right font-black" :class="tx.isNegative ? 'text-red-500' : 'text-gray-900 dark:text-zinc-100'">
                                                {{ tx.amount }}
                                            </td>
                                        </tr>
                                        <tr v-if="transactions.length === 0">
                                            <td colspan="5" class="py-8 text-center">
                                                <div class="flex flex-col items-center gap-2 text-gray-400 dark:text-zinc-500">
                                                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 animate-bounce-soft"><Wallet class="h-5 w-5 text-indigo-400" /></span>
                                                    <span class="text-xs font-black uppercase tracking-wide">No transactions found</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Modules -->
                <div class="pt-2 animate-fade-up" style="animation-delay: 240ms">
                    <h2 class="text-xl font-black tracking-tight text-gray-900 dark:text-zinc-100 mb-4 px-2">Department Overview</h2>
                    <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div v-for="(mod, idx) in modules" :key="idx"
                            :style="{ transitionDelay: `${Math.min(idx * 40, 400)}ms` }"
                            class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-6 overflow-hidden">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="relative flex items-center gap-3 mb-5">
                                <div :class="[mod.bg, mod.color, 'p-3 rounded-2xl shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300']">
                                    <component v-if="mod.icon === 'Users'" :is="Users" class="w-5 h-5" />
                                    <component v-else-if="mod.icon === 'ShoppingCart'" :is="ShoppingCart" class="w-5 h-5" />
                                    <component v-else-if="mod.icon === 'Factory'" :is="Factory" class="w-5 h-5" />
                                    <component v-else-if="mod.icon === 'Package'" :is="Package" class="w-5 h-5" />
                                    <component v-else :is="Users" class="w-5 h-5" />
                                </div>
                                <h3 class="font-black tracking-tight text-gray-900 dark:text-zinc-100 group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ mod.title }}</h3>
                            </div>
                            <div class="relative space-y-3">
                                <div v-for="(val, key) in mod.stats" :key="key" class="flex justify-between items-center text-sm">
                                    <span class="text-gray-500 dark:text-zinc-400 font-medium">{{ key }}</span>
                                    <span class="font-black text-gray-900 dark:text-zinc-100">{{ val }}</span>
                                </div>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

.font-sans {
    font-family: 'Plus Jakarta Sans', sans-serif;
}

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
.modal-enter-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from { opacity: 0; transform: translateY(14px) scale(0.98); }
.modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.modal-leave-to { opacity: 0; transform: scale(0.97); }

/* Subtle scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
