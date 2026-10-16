<script setup lang="ts">
// Page 17: Agency dashboard (own data: cards + alerts)
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
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
    { label: 'Open requests', value: props.cards?.open_requests ?? 0, href: 'agency.requests.index', tone: 'text-blue-600' },
    { label: 'Workers deployed', value: props.cards?.workers_deployed ?? 0, href: 'agency.workers.index', tone: 'text-emerald-600' },
    { label: 'Workers available', value: props.cards?.workers_available ?? 0, href: 'agency.workers.index', tone: 'text-slate-900 dark:text-white' },
    { label: 'Attendance to confirm', value: props.cards?.pending_confirmation ?? 0, href: 'agency.attendance.index', tone: 'text-amber-600' },
    { label: 'Unpaid statements', value: props.cards?.unpaid_statements ?? 0, href: 'agency.billing.index', tone: 'text-rose-600' },
]);
</script>

<template>
    <Head title="Agency Dashboard - Monti ERP" />
    <AgencyLayout>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white">{{ agency?.name || 'Agency workspace' }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            Contract ends {{ agency?.contract_end || '—' }}.
        </p>

        <div v-if="agency?.expiring_soon" class="mt-4 p-3 rounded-xl bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-800 text-xs font-bold text-yellow-800 dark:text-yellow-300">
            Your contract or DOLE registration expires within 30 days — please coordinate with Monti Corp HR.
        </div>

        <div class="mt-4 grid grid-cols-2 lg:grid-cols-5 gap-3">
            <Link
                v-for="c in cards"
                :key="c.label"
                :href="route(c.href)"
                class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-4 hover:shadow-md transition"
            >
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ c.label }}</div>
                <div :class="['text-2xl font-black mt-1', c.tone]">{{ c.value }}</div>
            </Link>
        </div>

        <div v-if="clearances_soon.length > 0" class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-orange-200 dark:border-orange-800 shadow-sm p-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-2">Worker clearances expiring soon</h2>
            <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                <li v-for="w in clearances_soon" :key="w.code" class="py-1.5 flex items-center justify-between gap-2 text-xs">
                    <span class="font-bold text-slate-800 dark:text-slate-100">{{ w.name }} <span class="font-mono text-slate-400">({{ w.code }})</span></span>
                    <span :class="['font-bold', w.expired ? 'text-rose-600' : 'text-orange-600']">{{ w.expired ? 'Expired' : w.clearance_expiry }}</span>
                </li>
            </ul>
        </div>

        <div class="mt-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-4 text-xs text-slate-500 dark:text-slate-400">
            Signed in as <strong class="text-slate-800 dark:text-slate-100">{{ user.name }}</strong> ({{ user.email }}).
        </div>
    </AgencyLayout>
</template>
