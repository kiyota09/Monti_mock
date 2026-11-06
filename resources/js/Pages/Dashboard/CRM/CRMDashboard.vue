<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    ArrowDownRight, ArrowRight, ArrowUpRight, BriefcaseBusiness, CalendarClock,
    CheckCircle2, CircleAlert, Clock3, Handshake, Headphones, Layers3,
    MessageSquareText, UsersRound,
} from 'lucide-vue-next';

const page = usePage();
const props = defineProps({
    stats: { type: Object, default: () => ({ total_clients: 0, pending_clients: 0, total_leads: 0, active_leads: 0, open_feedback: 0 }) },
    pipeline: { type: Object, default: () => ({ deals: 0, value: 0, forecast: 0 }) },
    funnel: { type: Object, default: () => ({ leads: 0, qualified: 0, open_deals: 0, won: 0, win_rate: 0, closed_deals: 0 }) },
    avgCycle: { type: [Number, null], default: null },
    service: { type: Object, default: () => ({ open_cases: 0, urgent_cases: 0, overdue_tasks: 0 }) },
    stageSummary: { type: Array, default: () => [] },
    upcomingActivities: { type: Array, default: () => [] },
    recentLeads: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({}) },
});

const user = computed(() => page.props.auth?.user);
const canView = (key) => ['CEO', 'COO'].includes(user.value?.role)
    || (user.value?.role === 'CRM' && user.value?.position === 'manager')
    || props.permissions[key] !== undefined;
const greeting = computed(() => {
    const hour = new Date().getHours();
    return hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
});
const today = new Date().toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' });
const peso = (value) => `₱${Number(value || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}`;
const dateLabel = (value) => value
    ? new Date(value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
    : 'No date';
const dateTimeLabel = (value) => value
    ? new Date(value).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
    : 'No date set';
const activityIcon = (type) => ({ call: 'Call', meeting: 'Meeting', reminder: 'Reminder', todo: 'Task', task: 'Task', email: 'Email' }[type] || 'Activity');
const stageMaximum = computed(() => Math.max(1, ...props.stageSummary.map((stage) => Number(stage.value) || 0)));

const navLinks = computed(() => [
    canView('leads') && { label: 'Leads', href: route('crm.lead'), icon: BriefcaseBusiness },
    canView('opportunities') && { label: 'Opportunities', href: route('crm.opportunities'), icon: Layers3 },
    canView('inquiry') && { label: 'Inquiries', href: route('crm.inquiries'), icon: MessageSquareText },
    canView('customer_profiles') && { label: 'Accounts', href: route('crm.customerprofile.index'), icon: UsersRound },
    canView('activities') && { label: 'Activities', href: route('crm.activities'), icon: CalendarClock },
    canView('cases') && { label: 'Cases', href: route('crm.cases'), icon: Headphones },
    canView('investigation') && { label: 'Feedback', href: route('crm.investigation.index'), icon: MessageSquareText },
].filter(Boolean));

const metrics = computed(() => [
    { label: 'Open pipeline', value: peso(props.pipeline.value), detail: `${props.pipeline.deals} active deals`, icon: Layers3, tone: 'forest' },
    { label: 'Weighted forecast', value: peso(props.pipeline.forecast), detail: 'Based on deal probability', icon: ArrowUpRight, tone: 'mint' },
    { label: 'Active leads', value: props.stats.active_leads ?? props.stats.total_leads, detail: `${props.stats.total_leads} total leads`, icon: BriefcaseBusiness, tone: 'blue' },
    { label: 'Win rate', value: `${Number(props.funnel.win_rate || 0).toLocaleString()}%`, detail: props.funnel.closed_deals ? `${props.funnel.closed_deals} closed opportunities` : 'No closed opportunities yet', icon: Handshake, tone: 'gold' },
]);
</script>

<template>
    <Head title="CRM Dashboard" />
    <AuthenticatedLayout>
        <main class="crm-dashboard min-h-screen px-4 py-5 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6 pb-12">
                <header class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="crm-eyebrow">Customer relationships</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">CRM overview</h1>
                        <p class="mt-1 text-sm crm-muted">{{ greeting }}, {{ user?.name }}. Here’s your team’s current pipeline and next actions.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="crm-date"><Clock3 class="h-4 w-4" /> {{ today }}</span>
                        <Link v-if="canView('opportunities')" :href="route('crm.opportunities')" class="crm-primary-link">
                            Open pipeline <ArrowRight class="h-4 w-4" />
                        </Link>
                    </div>
                </header>

                <nav v-if="navLinks.length" aria-label="CRM sections" class="flex flex-wrap gap-2">
                    <Link v-for="item in navLinks" :key="item.label" :href="item.href" class="crm-nav-link">
                        <component :is="item.icon" class="h-4 w-4" /> {{ item.label }}
                    </Link>
                </nav>

                <section aria-label="CRM performance indicators" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="metric in metrics" :key="metric.label" class="crm-metric-card">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="crm-label">{{ metric.label }}</p>
                                <p class="mt-2 truncate text-2xl font-semibold tracking-tight">{{ metric.value }}</p>
                                <p class="mt-1 text-xs crm-muted">{{ metric.detail }}</p>
                            </div>
                            <span class="crm-metric-icon" :class="`is-${metric.tone}`"><component :is="metric.icon" class="h-5 w-5" /></span>
                        </div>
                    </article>
                </section>

                <section class="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                    <article class="crm-panel">
                        <div class="crm-panel-heading">
                            <div>
                                <p class="crm-eyebrow">Revenue outlook</p>
                                <h2 class="mt-1 text-base font-semibold">Pipeline by stage</h2>
                            </div>
                            <Link v-if="canView('opportunities')" :href="route('crm.opportunities')" class="crm-text-link">View opportunities <ArrowRight class="h-3.5 w-3.5" /></Link>
                        </div>
                        <div v-if="stageSummary.length" class="mt-5 space-y-4">
                            <div v-for="stage in stageSummary" :key="stage.id" class="space-y-1.5">
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium">{{ stage.name }} <span class="crm-muted font-normal">· {{ stage.count }}</span></span>
                                    <span class="font-semibold tabular-nums">{{ peso(stage.value) }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-emerald-50">
                                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-800 to-emerald-500 transition-all" :style="{ width: `${Math.max(stage.value > 0 ? 4 : 0, stage.value / stageMaximum * 100)}%` }" />
                                </div>
                                <p class="text-right text-[11px] crm-muted">Weighted {{ peso(stage.forecast) }}</p>
                            </div>
                        </div>
                        <div v-else class="crm-empty-state"><Layers3 class="h-6 w-6" /><span>No open opportunities in the pipeline yet.</span></div>
                        <div class="crm-pipeline-summary">
                            <div><span class="crm-label">Qualified leads</span><strong>{{ funnel.qualified }}</strong></div>
                            <div><span class="crm-label">Won deals</span><strong>{{ funnel.won }}</strong></div>
                            <div><span class="crm-label">Average sales cycle</span><strong>{{ avgCycle !== null ? `${avgCycle} days` : '—' }}</strong></div>
                        </div>
                    </article>

                    <article class="crm-panel">
                        <div class="crm-panel-heading">
                            <div>
                                <p class="crm-eyebrow">Your work queue</p>
                                <h2 class="mt-1 text-base font-semibold">Upcoming follow-ups</h2>
                            </div>
                            <Link v-if="canView('activities')" :href="route('crm.activities')" class="crm-text-link">All activities <ArrowRight class="h-3.5 w-3.5" /></Link>
                        </div>
                        <div v-if="upcomingActivities.length" class="mt-3 divide-y divide-emerald-50">
                            <div v-for="activity in upcomingActivities" :key="activity.id" class="flex gap-3 py-3">
                                <span class="crm-activity-icon" :class="activity.overdue ? 'is-overdue' : ''">
                                    <CircleAlert v-if="activity.overdue" class="h-4 w-4" />
                                    <CalendarClock v-else class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ activity.title }}</p>
                                    <p class="mt-0.5 truncate text-xs crm-muted">{{ activity.account }}<span v-if="activity.assignee"> · {{ activity.assignee }}</span></p>
                                </div>
                                <span class="shrink-0 text-right text-[11px] font-medium" :class="activity.overdue ? 'text-rose-700' : 'crm-muted'">
                                    {{ activity.overdue ? 'Overdue' : activityIcon(activity.type) }}<br />{{ dateTimeLabel(activity.due_at) }}
                                </span>
                            </div>
                        </div>
                        <div v-else class="crm-empty-state"><CheckCircle2 class="h-6 w-6" /><span>No overdue or upcoming follow-ups this week.</span></div>
                    </article>
                </section>

                <section class="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                    <article class="crm-panel overflow-hidden !p-0">
                        <div class="crm-panel-heading px-5 py-4 sm:px-6">
                            <div>
                                <p class="crm-eyebrow">Prospecting</p>
                                <h2 class="mt-1 text-base font-semibold">Recently added leads</h2>
                            </div>
                            <Link v-if="canView('leads')" :href="route('crm.lead')" class="crm-text-link">Lead list <ArrowRight class="h-3.5 w-3.5" /></Link>
                        </div>
                        <div v-if="recentLeads.length" class="divide-y divide-emerald-50">
                            <div v-for="lead in recentLeads" :key="lead.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 sm:px-6">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="crm-lead-avatar">{{ (lead.company_name || '?').slice(0, 1).toUpperCase() }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ lead.company_name }}</p>
                                        <p class="truncate text-xs crm-muted">{{ lead.contact_person || 'No contact' }}<span v-if="lead.source"> · {{ lead.source }}</span></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="crm-status-pill" :class="`status-${String(lead.status || '').toLowerCase().replace(/[^a-z]+/g, '-')}`">{{ lead.status }}</span>
                                    <span class="hidden min-w-20 text-right text-xs crm-muted sm:inline">{{ dateLabel(lead.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="crm-empty-state px-5 py-8"><BriefcaseBusiness class="h-6 w-6" /><span>No leads have been added yet.</span></div>
                    </article>

                    <article class="crm-panel">
                        <div class="crm-panel-heading">
                            <div>
                                <p class="crm-eyebrow">Customer health</p>
                                <h2 class="mt-1 text-base font-semibold">Service and account pulse</h2>
                            </div>
                            <Link v-if="canView('cases')" :href="route('crm.cases')" class="crm-text-link">View cases <ArrowRight class="h-3.5 w-3.5" /></Link>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="crm-pulse-tile"><UsersRound class="h-4 w-4" /><span>Active clients</span><strong>{{ stats.total_clients }}</strong></div>
                            <div class="crm-pulse-tile"><Headphones class="h-4 w-4" /><span>Open cases</span><strong>{{ service.open_cases }}</strong></div>
                            <div class="crm-pulse-tile is-warning"><CircleAlert class="h-4 w-4" /><span>Urgent cases</span><strong>{{ service.urgent_cases }}</strong></div>
                            <div class="crm-pulse-tile is-warning"><ArrowDownRight class="h-4 w-4" /><span>Overdue tasks</span><strong>{{ service.overdue_tasks }}</strong></div>
                            <div class="crm-pulse-tile"><MessageSquareText class="h-4 w-4" /><span>Open feedback</span><strong>{{ stats.open_feedback }}</strong></div>
                        </div>
                        <div v-if="stats.pending_clients" class="crm-notice mt-4">
                            <Clock3 class="h-4 w-4 shrink-0" /> {{ stats.pending_clients }} client account{{ stats.pending_clients === 1 ? '' : 's' }} awaiting approval.
                            <Link v-if="canView('approvals')" :href="route('crm.approval.index')" class="ml-auto font-semibold">Review</Link>
                        </div>
                    </article>
                </section>
            </div>
        </main>
    </AuthenticatedLayout>
</template>

<style scoped>
.crm-dashboard { --crm-page: #f8fafc; --crm-panel: #fff; --crm-ink: #1e293b; --crm-muted: #64748b; --crm-line: #e2e8f0; --crm-accent: #4f46e5; --crm-accent-dark: #3730a3; --crm-accent-soft: #eef2ff; --crm-accent-ink: #4338ca; --crm-soft-green: #ecfdf5; --crm-green: #047857; --crm-green-ink: #065f46; --crm-warning: #fffbeb; --crm-warning-ink: #92400e; color: var(--crm-ink); background: var(--crm-page); }
.crm-eyebrow, .crm-label { color: var(--crm-muted); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.09em; text-transform: uppercase; }
.crm-muted { color: var(--crm-muted); }
.crm-date, .crm-nav-link, .crm-primary-link, .crm-text-link { display: inline-flex; align-items: center; gap: 0.5rem; }
.crm-date { color: var(--crm-muted); font-size: 0.8rem; }
.crm-primary-link { border-radius: 0.8rem; background: var(--crm-accent); color: #fff; padding: 0.65rem 0.9rem; font-size: 0.8rem; font-weight: 700; transition: background .15s ease; }
.crm-primary-link:hover { background: var(--crm-accent-dark); }
.crm-nav-link { border: 1px solid var(--crm-line); border-radius: 999px; background: var(--crm-panel); color: var(--crm-accent-ink); padding: 0.48rem 0.78rem; font-size: 0.75rem; font-weight: 600; transition: background .15s ease, border-color .15s ease; }
.crm-nav-link:hover { border-color: var(--crm-accent); background: var(--crm-accent-soft); }
.crm-metric-card, .crm-panel { border: 1px solid var(--crm-line); border-radius: 1rem; background: var(--crm-panel); box-shadow: 0 4px 14px rgba(15, 23, 42, .035); }
.crm-metric-card { padding: 1.15rem; }
.crm-panel { padding: 1.25rem; }
.crm-metric-icon, .crm-activity-icon, .crm-lead-avatar { display: flex; flex: none; align-items: center; justify-content: center; }
.crm-metric-icon { width: 2.5rem; height: 2.5rem; border-radius: .8rem; background: var(--crm-accent-soft); color: var(--crm-accent); }
.crm-metric-icon.is-mint { background: var(--crm-soft-green); color: var(--crm-green); }
.crm-metric-icon.is-blue { background: #eff6ff; color: #2563eb; }
.crm-metric-icon.is-gold { background: var(--crm-warning); color: var(--crm-warning-ink); }
.crm-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
.crm-text-link { color: var(--crm-accent-ink); font-size: .75rem; font-weight: 650; white-space: nowrap; }
.crm-text-link:hover { color: var(--crm-accent-dark); }
.crm-empty-state { display: flex; min-height: 8rem; align-items: center; justify-content: center; gap: .7rem; color: var(--crm-muted); font-size: .8rem; }
.crm-pipeline-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; margin-top: 1.3rem; border-top: 1px solid var(--crm-line); padding-top: 1rem; }
.crm-pipeline-summary > div { display: flex; flex-direction: column; gap: .35rem; }
.crm-pipeline-summary strong { font-size: 1rem; font-weight: 650; }
.crm-activity-icon { width: 2rem; height: 2rem; border-radius: .65rem; background: var(--crm-soft-green); color: var(--crm-green); }
.crm-activity-icon.is-overdue { background: #fff0ed; color: #b54736; }
.crm-lead-avatar { width: 2.3rem; height: 2.3rem; border-radius: .7rem; background: var(--crm-accent-soft); color: var(--crm-accent-ink); font-size: .85rem; font-weight: 700; }
.crm-status-pill { border-radius: 999px; background: #f1f5f9; padding: .25rem .55rem; color: #475569; font-size: .65rem; font-weight: 650; text-transform: capitalize; }
.crm-status-pill.status-inquiry { background: #eff6ff; color: #1d4ed8; }
.crm-status-pill.status-negotiation, .crm-status-pill.status-approval-sent { background: var(--crm-warning); color: var(--crm-warning-ink); }
.crm-status-pill.status-closed-won, .crm-status-pill.status-converted { background: var(--crm-soft-green); color: var(--crm-green-ink); }
.crm-status-pill.status-lost, .crm-status-pill.status-archived { background: #fff0ed; color: #a84235; }
.crm-pulse-tile { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: .5rem .6rem; border: 1px solid var(--crm-line); border-radius: .8rem; background: var(--crm-page); padding: .8rem; color: var(--crm-muted); }
.crm-pulse-tile span { font-size: .72rem; }
.crm-pulse-tile strong { grid-column: 2; color: var(--crm-ink); font-size: 1.2rem; font-weight: 650; }
.crm-pulse-tile.is-warning { border-color: #f0e5c9; background: var(--crm-warning); color: var(--crm-warning-ink); }
.crm-pulse-tile.is-warning strong { color: var(--crm-warning-ink); }
.crm-notice { display: flex; align-items: center; gap: .5rem; border-radius: .7rem; background: var(--crm-warning); padding: .75rem; color: var(--crm-warning-ink); font-size: .75rem; }
@media (max-width: 639px) { .crm-pipeline-summary { grid-template-columns: 1fr; } }
</style>
