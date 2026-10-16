<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Users, Briefcase, TrendingUp, Clock, Calendar, MessageSquare,
    CheckCircle, XCircle, AlertCircle, Building2, KanbanSquare,
    PieChart, BarChart3, ArrowRight, Eye, Sparkles, LayoutDashboard,
    FileText, Activity, Megaphone
} from 'lucide-vue-next';

const page = usePage();
const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_clients: 0,
            pending_clients: 0,
            total_leads: 0,
            open_feedback: 0,
        }),
    },
    pipeline: { type: Object, default: () => ({ deals: 0, value: 0, forecast: 0 }) },
    funnel: { type: Object, default: () => ({ leads: 0, qualified: 0, open_deals: 0, won: 0, win_rate: 0 }) },
    avgCycle: { type: [Number, null], default: null },
    service: { type: Object, default: () => ({ open_cases: 0, urgent_cases: 0, overdue_tasks: 0 }) },
    recentFeedback: {
        type: Array,
        default: () => [],
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
});

const user = computed(() => page.props.auth.user);
const isStaff = computed(() => user.value?.position === 'staff');
const isManager = computed(() => user.value?.position === 'manager');
const isCeo = computed(() => user.value?.role === 'CEO');

// Helper for greeting
const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
};

// Check if user has at least view permission for a page
const canViewPage = (pageKey) => {
    if (isCeo.value) return true;
    if (isManager.value) return true;
    return props.permissions[pageKey] !== undefined;
};

// Quick access links (filtered by permissions – only show if user has view access)
const quickLinks = computed(() => {
    const links = [];
    if (canViewPage('leads')) {
        links.push({ label: 'Lead Pipeline', href: route('crm.lead'), icon: Briefcase, permKey: 'leads' });
    }
    if (canViewPage('opportunities')) {
        links.push({ label: 'Opportunities', href: route('crm.opportunities'), icon: KanbanSquare, permKey: 'opportunities' });
    }
    if (canViewPage('inquiry')) {
        links.push({ label: 'Inquiries', href: route('crm.inquiries'), icon: MessageSquare, permKey: 'inquiry' });
    }
    if (canViewPage('customer_profiles')) {
        links.push({ label: 'Accounts', href: route('crm.customerprofile.index'), icon: Building2, permKey: 'customer_profiles' });
    }
    if (canViewPage('quotations')) {
        links.push({ label: 'Quotations', href: route('crm.quotations'), icon: FileText, permKey: 'quotations' });
    }
    if (canViewPage('activities')) {
        links.push({ label: 'Activities', href: route('crm.activities'), icon: Activity, permKey: 'activities' });
    }
    if (canViewPage('cases')) {
        links.push({ label: 'Cases', href: route('crm.cases'), icon: AlertCircle, permKey: 'cases' });
    }
    if (canViewPage('approvals')) {
        links.push({ label: 'Pending Approvals', href: route('crm.approval.index'), icon: Clock, permKey: 'approvals' });
    }
    if (canViewPage('campaigns')) {
        links.push({ label: 'Campaigns', href: route('crm.campaigns'), icon: Megaphone, permKey: 'campaigns' });
    }
    if (canViewPage('investigation')) {
        links.push({ label: 'Due Diligence', href: route('crm.investigation.index'), icon: AlertCircle, permKey: 'investigation' });
    }
    return links;
});

const getFeedbackIcon = (type) => {
    return type === 'complaint' ? XCircle : MessageSquare;
};

const getFeedbackClass = (type) => {
    return type === 'complaint' ? 'text-red-600 bg-red-50' : 'text-blue-600 bg-blue-50';
};

const canManageFeedback = computed(() => {
    return isCeo.value || isManager.value || props.permissions.investigation === 'edit';
});

const peso0 = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });

const statCards = computed(() => ([
    { key: 'clients', label: 'Active Clients', value: props.stats.total_clients, icon: Users, foot: '+8% from last month', footIcon: TrendingUp, footClass: 'text-blue-600 dark:text-blue-400', tint: 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400', bar: 'from-blue-500 to-indigo-500' },
    { key: 'forecast', label: 'Weighted Forecast', value: peso0(props.pipeline.forecast), icon: TrendingUp, foot: `${props.pipeline.deals} open deals`, footIcon: null, footClass: 'text-emerald-600 dark:text-emerald-400', tint: 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400', bar: 'from-emerald-500 to-teal-500' },
    { key: 'winrate', label: 'Win Rate', value: `${props.funnel.win_rate}%`, icon: PieChart, foot: props.avgCycle !== null ? `Avg cycle ${props.avgCycle}d` : 'No closed deals yet', footIcon: null, footClass: 'text-purple-600 dark:text-purple-400', tint: 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400', bar: 'from-violet-500 to-fuchsia-500' },
    { key: 'service', label: 'Open Cases', value: props.service.open_cases, icon: AlertCircle, foot: `${props.service.urgent_cases} urgent · ${props.service.overdue_tasks} overdue tasks`, footIcon: null, footClass: 'text-red-600 dark:text-red-400', tint: 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400', bar: 'from-rose-500 to-red-500' },
    { key: 'pending', label: 'Pending Approvals', value: props.stats.pending_clients, icon: Clock, foot: 'Awaiting review', footIcon: null, footClass: 'text-amber-600 dark:text-amber-400', tint: 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400', bar: 'from-amber-500 to-orange-500' },
    { key: 'leads', label: 'Active Leads', value: props.stats.total_leads, icon: Briefcase, foot: 'In pipeline', footIcon: null, footClass: 'text-purple-600 dark:text-purple-400', tint: 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400', bar: 'from-violet-500 to-fuchsia-500' },
]));
</script>

<template>
    <Head title="CRM Dashboard" />

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
                            <LayoutDashboard class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> CRM · Overview
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">
                                Customer Relationship Dashboard
                            </h1>
                            <p class="text-sm text-blue-100/90">{{ getGreeting() }}, {{ user?.name }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                <Calendar class="h-3.5 w-3.5" />
                                {{ new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) }}
                            </span>
                            <span v-if="permissions.dashboard === 'view' && !isCeo && !isManager" class="flex items-center gap-1.5 rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> View only
                            </span>
                            <span v-else class="flex items-center gap-1.5 rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Full access
                            </span>
                        </div>
                    </div>

                    <!-- Quick access pills inside hero -->
                    <div v-if="quickLinks.length > 0" class="relative mt-6 flex flex-wrap gap-2">
                        <Link v-for="link in quickLinks" :key="link.label" :href="link.href"
                            class="flex items-center gap-1.5 rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 hover:bg-white hover:text-indigo-700 hover:shadow-lg hover:scale-105 active:scale-95">
                            <component :is="link.icon" class="h-4 w-4" /> {{ link.label }}
                        </Link>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div v-for="(card, i) in statCards" :key="card.key"
                        :style="{ animationDelay: `${i * 80}ms` }"
                        class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div :class="['absolute left-0 top-5 bottom-5 w-1 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300 bg-gradient-to-b', card.bar]" />
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ card.label }}</p>
                                <p class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mt-1 tracking-tight">{{ card.value }}</p>
                            </div>
                            <div :class="['p-3 rounded-2xl group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shadow-sm', card.tint]">
                                <component :is="card.icon" class="w-5 h-5" />
                            </div>
                        </div>
                        <div :class="['mt-3 text-xs font-bold flex items-center gap-1', card.footClass]">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                            <component v-if="card.footIcon" :is="card.footIcon" class="w-3 h-3" /> {{ card.foot }}
                        </div>
                    </div>
                </div>

                <!-- Funnel: leads → qualified → open deals → won -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-zinc-800 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20"><BarChart3 class="w-4 h-4 text-indigo-500" /></span>
                        <h3 class="text-[11px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-200">Sales Funnel</h3>
                        <span class="ml-auto rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-[11px] font-black px-2.5 py-1">{{ funnel.win_rate }}% win rate</span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div v-for="(s, i) in [
                            { label: 'Leads in', value: funnel.leads, bar: 'from-blue-500 to-indigo-500', pct: 100 },
                            { label: 'Qualified', value: funnel.qualified, bar: 'from-indigo-500 to-violet-500', pct: funnel.leads ? Math.round(funnel.qualified / funnel.leads * 100) : 0 },
                            { label: 'Open deals', value: funnel.open_deals, bar: 'from-violet-500 to-fuchsia-500', pct: funnel.leads ? Math.round(funnel.open_deals / funnel.leads * 100) : 0 },
                            { label: 'Won', value: funnel.won, bar: 'from-emerald-500 to-teal-500', pct: funnel.win_rate },
                        ]" :key="i" class="rounded-2xl bg-slate-50 dark:bg-zinc-800/60 p-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ s.label }}</p>
                            <p class="text-2xl font-black mt-1">{{ s.value }}</p>
                            <div class="mt-2 h-2 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r transition-all duration-700" :class="s.bar" :style="{ width: `${Math.min(100, s.pct)}%` }" />
                            </div>
                            <p class="mt-1 text-[10px] font-bold text-gray-400">{{ s.pct }}%</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Access cards -->
                <TransitionGroup v-if="quickLinks.length > 0" name="card" tag="div" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <Link v-for="(link, i) in quickLinks" :key="link.label" :href="link.href"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative flex flex-col items-center p-5 bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 overflow-hidden">
                        <div class="pointer-events-none absolute -top-12 -right-12 h-28 w-28 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="p-3 rounded-2xl bg-gray-50 dark:bg-zinc-800 group-hover:bg-indigo-600 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                            <component :is="link.icon" class="w-6 h-6 text-gray-500 dark:text-gray-300 group-hover:text-white transition-colors" />
                        </div>
                        <span class="mt-2.5 text-xs font-black text-gray-700 dark:text-gray-200 group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors text-center">{{ link.label }}</span>
                        <ArrowRight class="w-3.5 h-3.5 text-gray-300 dark:text-zinc-600 group-hover:text-indigo-500 group-hover:translate-x-1 mt-1.5 transition-all" />
                    </Link>
                </TransitionGroup>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- Lead Conversion Progress -->
                    <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-zinc-800 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300" style="animation-delay:80ms">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20"><PieChart class="w-4 h-4 text-blue-500" /></span>
                            <h3 class="text-[11px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-200">Lead Conversion Rate</h3>
                            <span class="ml-auto rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-[11px] font-black px-2.5 py-1">32%</span>
                        </div>
                        <div class="relative pt-1">
                            <div class="flex justify-between text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">
                                <span>Conversion</span>
                                <span>32%</span>
                            </div>
                            <div class="w-full h-3 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 rounded-full transition-all duration-1000" style="width: 32%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-2 font-medium">
                                <span>Leads: {{ stats.total_leads }}</span>
                                <span>Won: 8</span>
                            </div>
                        </div>
                    </div>

                    <!-- Feedback Status Distribution -->
                    <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-zinc-800 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300" style="animation-delay:160ms">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20"><BarChart3 class="w-4 h-4 text-emerald-500" /></span>
                            <h3 class="text-[11px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-200">Feedback Status</h3>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-xs font-bold mb-1 text-gray-600 dark:text-gray-300">
                                    <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse" /> Open</span>
                                    <span>{{ stats.open_feedback }}</span>
                                </div>
                                <div class="w-full h-2 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-rose-500 to-red-500 rounded-full transition-all duration-700" :style="{ width: `${(stats.open_feedback / (stats.open_feedback + 5)) * 100}%` }"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs font-bold mb-1 text-gray-600 dark:text-gray-300">
                                    <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse" /> In Progress</span>
                                    <span>3</span>
                                </div>
                                <div class="w-full h-2 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-amber-400 to-yellow-500 rounded-full" style="width: 25%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs font-bold mb-1 text-gray-600 dark:text-gray-300">
                                    <span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Resolved</span>
                                    <span>12</span>
                                </div>
                                <div class="w-full h-2 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full" style="width: 60%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Feedback / Complaints -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-center bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20">
                        <h3 class="text-[11px] font-black uppercase tracking-widest flex items-center gap-2 text-gray-700 dark:text-gray-200">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/30"><MessageSquare class="w-4 h-4 text-violet-600 dark:text-violet-400" /></span>
                            Recent Feedback & Complaints
                        </h3>
                        <Link v-if="canManageFeedback" :href="route('crm.investigation.index')" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-500/30 hover:scale-105 active:scale-95 transition-all">View All</Link>
                    </div>
                    <TransitionGroup v-if="recentFeedback.length > 0" name="card" tag="div" class="divide-y divide-gray-100 dark:divide-zinc-800">
                        <div v-for="(fb, i) in recentFeedback" :key="fb.id" :style="{ transitionDelay: `${Math.min(i * 50, 400)}ms` }" class="group p-5 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                            <div class="flex items-start gap-3">
                                <div :class="['p-2.5 rounded-xl shadow-sm group-hover:scale-110 transition-transform', getFeedbackClass(fb.type)]">
                                    <component :is="getFeedbackIcon(fb.type)" class="w-4 h-4" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap justify-between items-start gap-2">
                                        <p class="font-black text-sm text-gray-900 dark:text-white tracking-tight">{{ fb.subject }}</p>
                                        <span :class="fb.status === 'open' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300 ring-red-200 dark:ring-red-500/30' : fb.status === 'in_progress' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 ring-amber-200 dark:ring-amber-500/30' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 ring-emerald-200 dark:ring-emerald-500/30'" class="text-[9px] px-2.5 py-1 rounded-full font-black uppercase ring-1 flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ fb.status }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">{{ fb.message }}</p>
                                    <div class="flex justify-between items-center mt-2 text-xs text-gray-400 font-medium">
                                        <span>{{ fb.client?.company_name || 'Client' }}</span>
                                        <span>{{ new Date(fb.created_at).toLocaleDateString() }}</span>
                                    </div>
                                </div>
                                <Link v-if="canManageFeedback" :href="route('crm.investigation.index')" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                                    <Eye class="w-4 h-4" />
                                </Link>
                            </div>
                        </div>
                    </TransitionGroup>
                    <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <CheckCircle class="h-9 w-9 text-emerald-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">All clear — no recent feedback</p>
                        <p class="text-xs text-gray-400 mt-1">New feedback and complaints will appear here.</p>
                    </div>
                </div>
            </div>
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
</style>
