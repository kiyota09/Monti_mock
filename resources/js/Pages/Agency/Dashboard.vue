<script setup lang="ts">
// Page 17: Agency dashboard (own data: cards + alerts)
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { toast } from '@/lib/feedback';
import { watch } from 'vue';

const props = defineProps<{
    user: { name: string; email: string };
    agency: {
        code: string; name: string; status: string;
        contact_name: string | null; contact_email: string | null; contact_phone: string | null;
        contract_start: string | null; contract_end: string | null;
        dole_number: string | null; dole_expiry: string | null;
        service_fee_type: string; service_fee_value: string | number | null;
        expiring_soon: boolean;
    } | null;
    cards: {
        open_requests: number; workers_deployed: number; workers_available: number;
        pending_confirmation: number; unpaid_statements: number;
    } | null;
    clearances_soon: Array<{ code: string; name: string; clearance_expiry: string | null; expired: boolean }>;
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true, deep: true });

const cards = computed(() => [
    { label: 'Open requests', value: props.cards?.open_requests ?? 0, href: 'agency.requests.index', tone: 'text-amber-700' },
    { label: 'Workers deployed', value: props.cards?.workers_deployed ?? 0, href: 'agency.workers.index', tone: 'text-emerald-600' },
    { label: 'Workers available', value: props.cards?.workers_available ?? 0, href: 'agency.workers.index', tone: 'text-slate-900 dark:text-white' },
    { label: 'Attendance to confirm', value: props.cards?.pending_confirmation ?? 0, href: 'agency.attendance.index', tone: 'text-amber-600' },
    { label: 'Unpaid statements', value: props.cards?.unpaid_statements ?? 0, href: 'agency.billing.index', tone: 'text-rose-600' },
]);
</script>

<template>
    <Head title="Agency Dashboard - Monti ERP" />
    <AgencyLayout>
        <div class="agency-dashboard-content">
        <div class="agency-page-header">
        <div>
        <p class="agency-page-eyebrow">Agency Portal</p>
        <h1 class="mt-1 text-3xl font-bold leading-tight text-white">{{ agency?.name || 'Agency workspace' }}</h1>
        <p class="mt-1 text-base text-slate-300">
            Contract ends {{ agency?.contract_end || '—' }}.
        </p>
        </div>

        </div>

        <div v-if="agency?.expiring_soon" class="mb-3 rounded-xl border border-yellow-300 bg-yellow-100 p-3 text-xs font-bold text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
            Your contract or DOLE registration expires within 30 days — please coordinate with Monti Corp HR.
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <Link
                v-for="c in cards"
                :key="c.label"
                :href="route(c.href)"
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ c.label }}</div>
                <div :class="['mt-1 text-2xl font-bold', c.tone]">{{ c.value }}</div>
            </Link>
        </div>

        <div v-if="clearances_soon.length > 0" class="mt-3 rounded-xl border border-orange-200 bg-white p-4 shadow-sm dark:border-orange-800 dark:bg-slate-800">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-2">Worker clearances expiring soon</h2>
            <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                <li v-for="w in clearances_soon" :key="w.code" class="py-1.5 flex items-center justify-between gap-2 text-xs">
                    <span class="font-bold text-slate-800 dark:text-slate-100">{{ w.name }} <span class="font-mono text-slate-400">({{ w.code }})</span></span>
                    <span :class="['font-bold', w.expired ? 'text-rose-600' : 'text-orange-600']">{{ w.expired ? 'Expired' : w.clearance_expiry }}</span>
                </li>
            </ul>
        </div>

        <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
            Signed in as <strong class="text-slate-800 dark:text-slate-100">{{ user.name }}</strong> ({{ user.email }}).
        </div>
        </div>
    </AgencyLayout>
</template>
