<template>
    <div class="space-y-6">
        <!-- Duty / trip status -->
        <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Today&rsquo;s Assignment</p>
                    <h2 class="mt-1 flex items-center gap-2 text-xl font-black text-gray-900 dark:text-white">
                        <span :class="currentDelivery ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400'" class="h-2.5 w-2.5 rounded-full" />
                        {{ currentDelivery ? currentDelivery.status.replace('_', ' ') : 'No active trip' }}
                    </h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-[10px] font-black uppercase text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                        {{ stats.pending_stops || 0 }} stop(s) left
                    </span>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-[10px] font-black uppercase text-amber-600 dark:bg-amber-900/30 dark:text-amber-300">
                        {{ stats.packages_on_board || 0 }} package(s) on board
                    </span>
                </div>
            </div>
        </section>

        <!-- Active assignment -->
        <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3 bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 px-5 py-4 text-white">
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-100">Delivery Run</p>
                    <p class="truncate text-lg font-black">{{ currentDelivery ? currentDelivery.delivery_number : 'No active trip' }}</p>
                </div>
                <span v-if="currentDelivery" class="rounded-full bg-white/15 px-3 py-1 text-[10px] font-black uppercase ring-1 ring-white/20">
                    {{ currentDelivery.stops?.length || 0 }} stop(s)
                </span>
            </div>

            <div v-if="currentDelivery" class="grid gap-3 p-5 sm:grid-cols-3">
                <div class="rounded-2xl border border-gray-100 p-3 dark:border-zinc-800">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Driver</p>
                    <p class="mt-1 text-sm font-black text-gray-900 dark:text-white">{{ currentDelivery.driver?.user?.name || '—' }}</p>
                    <p class="text-xs text-gray-500">{{ currentDelivery.driver?.license_number || 'No license on file' }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 p-3 dark:border-zinc-800">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Vehicle</p>
                    <p class="mt-1 text-sm font-black text-gray-900 dark:text-white">{{ currentDelivery.truck?.truck_number || '—' }}</p>
                    <p class="text-xs text-gray-500">{{ currentDelivery.truck?.plate_number || 'No plate' }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 p-3 dark:border-zinc-800">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Next Stop</p>
                    <p class="mt-1 truncate text-sm font-black text-gray-900 dark:text-white">{{ nextStop?.label || nextStop?.client?.company_name || 'All stops handled' }}</p>
                    <p class="truncate text-xs text-gray-500">{{ nextStop?.address || '—' }}</p>
                </div>
            </div>
            <div v-else class="p-6 text-center">
                <p class="text-sm font-bold text-gray-500 dark:text-gray-400">You have no dispatched trip right now.</p>
                <p class="mt-1 text-xs text-gray-400">New assignments from dispatch will appear here.</p>
            </div>
        </section>

        <!-- Quick actions -->
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <button v-for="action in quickActions" :key="action.tab" type="button" @click="$emit('open-tab', action.tab)"
                class="flex items-center gap-3 rounded-3xl border border-gray-100 bg-white p-4 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="action.tint">
                    <component :is="action.icon" class="h-5 w-5" :class="action.color" />
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-black text-gray-900 dark:text-white">{{ action.label }}</span>
                    <span class="block truncate text-xs text-gray-400">{{ action.hint }}</span>
                </span>
            </button>
        </section>

        <!-- Money + returns + reports -->
        <section class="grid gap-3 lg:grid-cols-3">
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Collections</p>
                    <Wallet class="h-4 w-4 text-emerald-500" />
                </div>
                <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ money(stats.collected_today) }}</p>
                <p class="text-[11px] text-gray-400">Collected today</p>
                <div class="mt-3 flex items-center justify-between rounded-2xl bg-amber-50 px-3 py-2 dark:bg-amber-900/20">
                    <span class="text-[10px] font-black uppercase tracking-wide text-amber-600 dark:text-amber-300">Pending remittance</span>
                    <span class="text-xs font-black text-amber-700 dark:text-amber-200">{{ money(stats.pending_remittance) }}</span>
                </div>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Returns &amp; damages</p>
                    <PackageX class="h-4 w-4 text-rose-500" />
                </div>
                <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ stats.open_returns || 0 }}</p>
                <p class="text-[11px] text-gray-400">Open items awaiting warehouse action</p>
                <button type="button" @click="$emit('open-tab', 'returns')"
                    class="mt-3 w-full rounded-2xl bg-rose-50 py-2 text-[10px] font-black uppercase tracking-widest text-rose-600 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-300">
                    Manage returns
                </button>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Open reports</p>
                    <FileText class="h-4 w-4 text-indigo-500" />
                </div>
                <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ stats.open_reports || 0 }}</p>
                <p class="text-[11px] text-gray-400">Sent to the Logistics Manager</p>
                <button type="button" @click="$emit('open-tab', 'reports')"
                    class="mt-3 w-full rounded-2xl bg-indigo-50 py-2 text-[10px] font-black uppercase tracking-widest text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-300">
                    File a report
                </button>
            </div>
        </section>

        <!-- Next stops -->
        <section v-if="upcomingStops.length" class="rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Upcoming stops</h3>
                <button type="button" @click="$emit('open-tab', 'trips')" class="text-[10px] font-black uppercase tracking-wider text-indigo-500 hover:text-indigo-600">View manifest</button>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                <div v-for="item in upcomingStops" :key="item.stop.id" class="flex flex-wrap items-center gap-3 px-5 py-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-xs font-black text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                        {{ item.stop.sequence }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ item.stop.label || item.stop.client?.company_name || 'Drop-off' }}</p>
                        <p class="truncate text-xs text-gray-500">{{ item.stop.address || 'No address' }}</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[9px] font-black uppercase text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                        {{ item.delivery.delivery_number }}
                    </span>
                    <a v-if="item.stop.latitude && item.stop.longitude"
                        :href="`https://www.google.com/maps/dir/?api=1&destination=${item.stop.latitude},${item.stop.longitude}`"
                        target="_blank" class="rounded-xl bg-blue-50 px-2.5 py-1.5 text-[10px] font-black uppercase text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">Navigate</a>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Wallet, PackageX, FileText, ClipboardCheck, HandCoins, ArrowDownToLine, AlertTriangle } from 'lucide-vue-next';

const props = defineProps({
    conductor: { type: Object, default: () => ({ user: {} }) },
    stats: { type: Object, default: () => ({}) },
    deliveries: { type: Array, default: () => [] },
    currentDelivery: { type: Object, default: null },
    notify: { type: Function, required: true },
});

defineEmits(['open-tab']);

const quickActions = [
    { tab: 'load', label: 'Load check', hint: 'Verify packages before departure', icon: ClipboardCheck, tint: 'bg-indigo-50 dark:bg-indigo-900/30', color: 'text-indigo-600 dark:text-indigo-300' },
    { tab: 'collections', label: 'Record collection', hint: 'Cash, cheque or e-wallet', icon: HandCoins, tint: 'bg-emerald-50 dark:bg-emerald-900/30', color: 'text-emerald-600 dark:text-emerald-300' },
    { tab: 'returns', label: 'Log return', hint: 'Damaged, short or refused items', icon: ArrowDownToLine, tint: 'bg-rose-50 dark:bg-rose-900/30', color: 'text-rose-600 dark:text-rose-300' },
    { tab: 'reports', label: 'Report issue', hint: 'Delay, breakdown or accident', icon: AlertTriangle, tint: 'bg-amber-50 dark:bg-amber-900/30', color: 'text-amber-600 dark:text-amber-300' },
];

const nextStop = computed(() => {
    for (const delivery of props.deliveries) {
        const stop = (delivery.stops || []).find((s) => (s.outcome || 'pending') === 'pending');
        if (stop) return stop;
    }
    return null;
});

const upcomingStops = computed(() => {
    const items = [];
    props.deliveries.forEach((delivery) => {
        (delivery.stops || [])
            .filter((stop) => (stop.outcome || 'pending') === 'pending')
            .forEach((stop) => items.push({ delivery, stop }));
    });
    return items.slice(0, 5);
});

const money = (value) => {
    const amount = Number(value) || 0;
    return `₱${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};
</script>
