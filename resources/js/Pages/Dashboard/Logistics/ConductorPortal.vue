<template>
    <Head title="Conductor Portal" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-7xl space-y-6 p-4 pb-16 sm:p-6">
                <!-- Hero -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white shadow-xl shadow-indigo-500/20 sm:p-8">
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                            <ClipboardList class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">Logistics · Conductor Portal</p>
                            <h1 class="text-2xl font-black tracking-tight sm:text-3xl">{{ conductor.user?.name || user?.name || 'Welcome' }}</h1>
                            <p class="text-sm text-blue-100/90">Trips, load verification, collections, returns and reports in one place.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full" :class="currentDelivery ? 'bg-emerald-300 animate-pulse' : 'bg-amber-300'" />
                                {{ currentDelivery ? 'On assignment' : 'Standby' }}
                            </span>
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ deliveries.length }} active trip{{ deliveries.length !== 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tab switcher -->
                <nav class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    <div class="flex w-max min-w-full gap-1.5 rounded-2xl border border-gray-100 bg-white/80 p-1.5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80">
                        <button v-for="tab in tabs" :key="tab.key" type="button" @click="switchTab(tab.key)"
                            :class="activeTab === tab.key
                                ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/25'
                                : 'text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-zinc-800 dark:hover:text-gray-200'"
                            class="flex shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-2 text-[10px] font-black uppercase tracking-wider transition-all">
                            <component :is="tab.icon" class="h-3.5 w-3.5" />
                            {{ tab.label }}
                        </button>
                    </div>
                </nav>

                <HomeTab v-if="activeTab === 'dashboard'" :conductor="conductor" :stats="stats" :deliveries="deliveries"
                    :current-delivery="currentDelivery" :notify="notify" @open-tab="switchTab" />

                <TripsTab v-else-if="activeTab === 'trips'" :deliveries="deliveries" :history="history"
                    :notify="notify" @open-tab="switchTab" />

                <LoadCheckTab v-else-if="activeTab === 'load'" :deliveries="deliveries" :history="history"
                    :load-checks="loadChecks" :current-delivery="currentDelivery" :notify="notify" />

                <CollectionsTab v-else-if="activeTab === 'collections'" :deliveries="deliveries" :history="history"
                    :collections="collections" :collection-totals="collectionTotals" :notify="notify" />

                <ReturnsTab v-else-if="activeTab === 'returns'" :deliveries="deliveries" :history="history"
                    :returns="returns" :return-totals="returnTotals" :notify="notify" />

                <ReportsTab v-else-if="activeTab === 'reports'" :deliveries="deliveries" :history="history"
                    :reports="reports" :notify="notify" />

                <HistoryTab v-else-if="activeTab === 'history'" :history="history" :collections="collections"
                    :returns="returns" :notify="notify" />
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
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ClipboardList, LayoutDashboard, Route, ClipboardCheck, HandCoins, PackageX, FileWarning, History,
} from 'lucide-vue-next';

import HomeTab from './ConductorPortal/HomeTab.vue';
import TripsTab from './ConductorPortal/TripsTab.vue';
import LoadCheckTab from './ConductorPortal/LoadCheckTab.vue';
import CollectionsTab from './ConductorPortal/CollectionsTab.vue';
import ReturnsTab from './ConductorPortal/ReturnsTab.vue';
import ReportsTab from './ConductorPortal/ReportsTab.vue';
import HistoryTab from './ConductorPortal/HistoryTab.vue';

const props = defineProps({
    conductor: { type: Object, default: () => ({ user: {} }) },
    user: { type: Object, default: () => ({}) },
    deliveries: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    loadChecks: { type: Array, default: () => [] },
    collections: { type: Array, default: () => [] },
    collectionTotals: { type: Object, default: () => ({ today: 0, pending: 0, remitted: 0, lifetime: 0 }) },
    returns: { type: Array, default: () => [] },
    returnTotals: { type: Object, default: () => ({ open: 0, resolved: 0, open_quantity: 0 }) },
    reports: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    currentDelivery: { type: Object, default: null },
    montiLocation: { type: Object, default: null },
});

const tabs = [
    { key: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { key: 'trips', label: 'My Trips', icon: Route },
    { key: 'load', label: 'Load Check', icon: ClipboardCheck },
    { key: 'collections', label: 'Collections', icon: HandCoins },
    { key: 'returns', label: 'Returns', icon: PackageX },
    { key: 'reports', label: 'Reports', icon: FileWarning },
    { key: 'history', label: 'History', icon: History },
];

const page = usePage();
const tabKeys = tabs.map((tab) => tab.key);
const tabFromUrl = () => {
    if (typeof window === 'undefined') return 'dashboard';
    const key = new URL(page.url, window.location.origin).searchParams.get('tab');
    return tabKeys.includes(key) ? key : 'dashboard';
};

const activeTab = ref(tabFromUrl());
const toast = ref({ show: false, type: 'success', message: '' });

watch(() => page.url, () => { activeTab.value = tabFromUrl(); });

const switchTab = (key) => {
    if (!tabKeys.includes(key)) return;
    activeTab.value = key;
    const base = page.url.split('?')[0];
    const target = key === 'dashboard' ? base : `${base}?tab=${key}`;
    if (page.url === target) return;
    router.get(target, {}, { preserveState: true, preserveScroll: true, replace: true });
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
