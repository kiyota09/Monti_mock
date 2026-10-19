<template>
    <div class="space-y-5">
        <div class="flex items-center gap-2">
            <button type="button" @click="view = 'active'"
                :class="view === 'active' ? 'bg-amber-600 text-white shadow-lg shadow-amber-500/25' : 'bg-white text-gray-500 dark:bg-zinc-900 dark:text-gray-300'"
                class="rounded-2xl px-4 py-2 text-[11px] font-black uppercase tracking-wide ring-1 ring-gray-100 transition dark:ring-zinc-800">
                Active ({{ deliveries.length }})
            </button>
            <button type="button" @click="view = 'history'"
                :class="view === 'history' ? 'bg-amber-600 text-white shadow-lg shadow-amber-500/25' : 'bg-white text-gray-500 dark:bg-zinc-900 dark:text-gray-300'"
                class="rounded-2xl px-4 py-2 text-[11px] font-black uppercase tracking-wide ring-1 ring-gray-100 transition dark:ring-zinc-800">
                History ({{ history.length }})
            </button>
        </div>

        <!-- Active trips -->
        <div v-if="view === 'active'" class="space-y-4">
            <article v-for="delivery in deliveries" :key="delivery.id"
                class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-amber-600 dark:bg-amber-900/30 dark:text-amber-300">
                                {{ delivery.status.replace('_', ' ') }}
                            </span>
                            <span class="text-xs font-bold text-gray-400">{{ delivery.delivery_number }}</span>
                        </div>
                        <p class="mt-1 truncate text-sm font-black text-gray-900 dark:text-white">
                            Driver: {{ delivery.driver?.user?.name || '—' }} · {{ delivery.truck?.truck_number || 'No truck' }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ delivery.route?.name || 'Optimized route' }} · {{ delivery.stops?.length || 0 }} stops · {{ Number(delivery.total_distance_km || 0).toFixed(1) }} km
                        </p>
                    </div>
                    <div class="text-right text-xs text-gray-500">
                        <p>Departure</p>
                        <p class="font-bold text-gray-900 dark:text-white">{{ formatDateTime(delivery.actual_departure || delivery.scheduled_departure) }}</p>
                    </div>
                </div>

                <!-- Stop-by-stop manifest -->
                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                    <div v-for="(stop, index) in (delivery.stops || [])" :key="stop.id" class="space-y-2 px-5 py-4">
                        <div class="flex flex-wrap items-start gap-3">
                            <span :class="outcomeBadge(stop.outcome)"
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-xs font-black">
                                {{ stop.sequence ?? index + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ stop.label || stop.client?.company_name || 'Drop-off' }}</p>
                                <p class="truncate text-xs text-gray-500">{{ stop.address || 'No address' }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-wide">
                                    <span :class="outcomePill(stop.outcome)" class="rounded-full px-2 py-0.5">{{ outcomeLabel(stop.outcome) }}</span>
                                    <span v-if="stop.client?.phone" class="text-gray-400">{{ stop.client.phone }}</span>
                                    <span class="text-gray-400">{{ stopPackages(delivery, stop).length }} pkg</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <a v-if="stop.latitude && stop.longitude"
                                    :href="`https://www.google.com/maps/dir/?api=1&destination=${stop.latitude},${stop.longitude}`"
                                    target="_blank" class="rounded-xl bg-blue-50 px-2.5 py-1.5 text-[10px] font-black uppercase text-blue-600 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300">Maps</a>
                                <a v-if="contactNumber(stop)" :href="`tel:${contactNumber(stop)}`"
                                    class="rounded-xl bg-gray-100 px-2.5 py-1.5 text-[10px] font-black uppercase text-gray-600 dark:bg-zinc-800 dark:text-gray-300">Call</a>
                                <button type="button" @click="$emit('open-tab', 'collections')"
                                    class="rounded-xl bg-emerald-600 px-2.5 py-1.5 text-[10px] font-black uppercase text-white hover:bg-emerald-700">Collect</button>
                                <button type="button" @click="$emit('open-tab', 'returns')"
                                    class="rounded-xl bg-rose-50 px-2.5 py-1.5 text-[10px] font-black uppercase text-rose-600 hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-300">Return</button>
                            </div>
                        </div>

                        <div v-if="stopPackages(delivery, stop).length" class="ml-11 flex flex-wrap gap-1.5">
                            <span v-for="pkg in stopPackages(delivery, stop)" :key="pkg.id"
                                class="rounded-lg bg-gray-50 px-2 py-1 text-[10px] font-bold text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                {{ pkg.package_number }} · {{ pkg.product?.name || 'Item' }} ({{ pkg.quantity }} pcs)
                            </span>
                        </div>
                    </div>
                    <div v-if="!(delivery.stops || []).length" class="px-5 py-8 text-center text-xs font-bold text-gray-400">No stops on this load.</div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 bg-gray-50 px-5 py-3 text-xs text-gray-500 dark:bg-zinc-800/40">
                    <span>{{ delivery.packages?.length || 0 }} package(s) · {{ totalQuantity(delivery) }} pcs total</span>
                    <button type="button" @click="$emit('open-tab', 'load')" class="text-[10px] font-black uppercase tracking-wider text-indigo-500 hover:text-indigo-600">Load check</button>
                </div>
            </article>

            <div v-if="!deliveries.length" class="rounded-3xl border border-dashed border-gray-200 bg-white py-16 text-center dark:border-zinc-800 dark:bg-zinc-900">
                <Truck class="mx-auto h-8 w-8 text-gray-300" />
                <p class="mt-2 text-sm font-bold text-gray-500 dark:text-gray-400">No active trips assigned.</p>
            </div>
        </div>

        <!-- History -->
        <div v-else class="space-y-3">
            <article v-for="delivery in history" :key="delivery.id"
                class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span :class="delivery.status === 'delivered' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300'"
                                class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ delivery.status }}</span>
                            <span class="text-xs font-bold text-gray-400">{{ delivery.delivery_number }}</span>
                        </div>
                        <p class="mt-1 truncate text-sm font-black text-gray-900 dark:text-white">{{ delivery.route?.name || 'Route' }}</p>
                        <p class="text-xs text-gray-500">{{ delivery.stops?.length || 0 }} stops · {{ Number(delivery.total_distance_km || 0).toFixed(1) }} km · {{ delivery.truck?.truck_number || '—' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Completed</p>
                        <p class="text-xs font-bold text-gray-900 dark:text-white">{{ formatDateTime(delivery.arrival_time || delivery.updated_at) }}</p>
                    </div>
                </div>
            </article>

            <div v-if="!history.length" class="rounded-3xl border border-dashed border-gray-200 bg-white py-16 text-center dark:border-zinc-800 dark:bg-zinc-900">
                <RouteIcon class="mx-auto h-8 w-8 text-gray-300" />
                <p class="mt-2 text-sm font-bold text-gray-500 dark:text-gray-400">No completed trips yet.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Truck, Route as RouteIcon } from 'lucide-vue-next';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    notify: { type: Function, required: true },
});

defineEmits(['open-tab']);

const view = ref('active');

const totalQuantity = (delivery) =>
    (delivery.packages || []).reduce((sum, p) => sum + (Number(p.quantity) || 0), 0);

const stopPackages = (delivery, stop) =>
    (delivery.packages || []).filter((pkg) => Number(pkg.pivot?.delivery_stop_id) === Number(stop.id));

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const outcomeLabel = (outcome) => ({
    pending: 'Pending', delivered: 'Delivered', attempted: 'Attempted', refused: 'Refused', partial: 'Partial',
}[outcome || 'pending'] || 'Pending');

const outcomeBadge = (outcome) => ({
    pending: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300',
    delivered: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300',
    attempted: 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300',
    refused: 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300',
    partial: 'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-300',
}[outcome || 'pending'] || 'bg-gray-100 text-gray-500');

const outcomePill = (outcome) => ({
    pending: 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
    delivered: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    attempted: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    refused: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
    partial: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
}[outcome || 'pending'] || 'bg-gray-100 text-gray-500');

const contactNumber = (stop) => stop.client?.phone || stop.client?.mobile_number || null;
</script>
