<template>
    <Head title="Driver Portal" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-7xl space-y-6 p-4 pb-16 sm:p-6">
                <!-- Hero -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white shadow-xl shadow-indigo-500/20 sm:p-8">
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                            <Truck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">Logistics · Driver Portal</p>
                            <h1 class="text-2xl font-black tracking-tight sm:text-3xl">{{ driver.user?.name || 'Welcome' }}</h1>
                            <p class="text-sm text-blue-100/90">Duty, trips, proof of delivery, safety and earnings in one place.</p>
                        </div>
                        <span class="flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                            <span class="h-1.5 w-1.5 rounded-full" :class="dutyDot" />
                            {{ dutyLabel }}
                        </span>
                    </div>
                </div>

                <HomeTab v-if="activeTab === 'home'" :driver="driver" :stats="stats" :duty="duty"
                    :deliveries="deliveries" :current-truck="currentTruck" :monti-location="montiLocation"
                    :notify="notify" @open-tab="switchTab" />

                <TripsTab v-else-if="activeTab === 'trips'" :deliveries="deliveries" :history="history"
                    :simulation="deliverySimulation" :monti-location="montiLocation"
                    :notify="notify" @open-pod="openPod" />

                <PodTab v-else-if="activeTab === 'pod'" :deliveries="deliveries" :history="history"
                    :pod-target="podTarget" :notify="notify" @consumed="podTarget = null" />

                <InspectionTab v-else-if="activeTab === 'inspection'" :trucks="trucks" :current-truck="currentTruck"
                    :inspections="inspections" :deliveries="deliveries" :notify="notify" />

                <IncidentTab v-else-if="activeTab === 'incidents'" :deliveries="deliveries" :incidents="incidents"
                    :current-truck="currentTruck" :notify="notify" />

                <HosTab v-else-if="activeTab === 'hos'" :duty="duty" :stats="stats" :driver="driver" :notify="notify" />

                <EarningsTab v-else-if="activeTab === 'earnings'" :earnings="earnings" :expenses="expenses"
                    :expense-totals="expenseTotals" :history="history" :notify="notify" />

                <DocumentsTab v-else-if="activeTab === 'documents'" :documents="documents" :driver="driver" :notify="notify" />
            </div>
        </div>

        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 max-w-sm rounded-2xl px-5 py-3 text-sm font-bold text-white shadow-lg"
                :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-rose-600'">
                {{ toast.message }}
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Truck, Home, Route, ClipboardCheck, Wrench, AlertTriangle, Clock, Wallet, FileText,
} from 'lucide-vue-next';

import HomeTab from './DriverPortal/HomeTab.vue';
import TripsTab from './DriverPortal/TripsTab.vue';
import PodTab from './DriverPortal/PodTab.vue';
import InspectionTab from './DriverPortal/InspectionTab.vue';
import IncidentTab from './DriverPortal/IncidentTab.vue';
import HosTab from './DriverPortal/HosTab.vue';
import EarningsTab from './DriverPortal/EarningsTab.vue';
import DocumentsTab from './DriverPortal/DocumentsTab.vue';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    driver: { type: Object, default: () => ({ user: {} }) },
    user: { type: Object, default: () => ({}) },
    montiLocation: { type: Object, default: null },
    montiLocations: { type: Array, default: () => [] },
    trucks: { type: Array, default: () => [] },
    currentTruck: { type: Object, default: null },
    duty: { type: Object, default: () => ({ current: null, logs: [], today_minutes: {} }) },
    inspections: { type: Array, default: () => [] },
    incidents: { type: Array, default: () => [] },
    expenses: { type: Array, default: () => [] },
    expense_totals: { type: Object, default: () => ({ pending: 0, approved: 0 }) },
    documents: { type: Array, default: () => [] },
    earnings: { type: Object, default: () => ({ rows: [], month_total: 0, pending_total: 0, lifetime_total: 0 }) },
    stats: { type: Object, default: () => ({}) },
    deliverySimulation: { type: Boolean, default: false },
});

const tabs = [
    { key: 'home', label: 'Dashboard', icon: Home },
    { key: 'trips', label: 'My Trips', icon: Route },
    { key: 'pod', label: 'Proof of Delivery', icon: ClipboardCheck },
    { key: 'inspection', label: 'Inspection', icon: Wrench },
    { key: 'incidents', label: 'Incidents', icon: AlertTriangle },
    { key: 'hos', label: 'Hours of Service', icon: Clock },
    { key: 'earnings', label: 'Earnings', icon: Wallet },
    { key: 'documents', label: 'Documents', icon: FileText },
];

const page = usePage();
const tabKeys = tabs.map((tab) => tab.key);
const tabFromUrl = () => {
    if (typeof window === 'undefined') return 'home';
    const key = new URL(page.url, window.location.origin).searchParams.get('tab');
    return tabKeys.includes(key) ? key : 'home';
};

const activeTab = ref(tabFromUrl());
const podTarget = ref(null);
const toast = ref({ show: false, type: 'success', message: '' });

watch(() => page.url, () => { activeTab.value = tabFromUrl(); });

const switchTab = (key) => {
    if (!tabKeys.includes(key)) return;
    activeTab.value = key;
    const base = page.url.split('?')[0];
    const target = key === 'home' ? base : `${base}?tab=${key}`;
    if (page.url === target) return;
    router.get(target, {}, { preserveState: true, preserveScroll: true, replace: true });
};

const expenseTotals = computed(() => props.expense_totals);
const dutyLabel = computed(() => ({
    on_duty: 'On Duty', driving: 'Driving', on_break: 'On Break', off_duty: 'Off Duty',
}[props.duty?.current?.status] || 'Off Duty'));
const dutyDot = computed(() => ({
    on_duty: 'bg-emerald-300', driving: 'bg-blue-300 animate-pulse', on_break: 'bg-amber-300', off_duty: 'bg-gray-300',
}[props.duty?.current?.status] || 'bg-gray-300'));

const openPod = (delivery, stop) => {
    podTarget.value = { delivery, stop };
    switchTab('pod');
};

const notify = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3200);
};
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
