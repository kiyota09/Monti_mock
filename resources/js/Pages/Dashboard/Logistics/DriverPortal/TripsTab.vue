<template>
    <div class="space-y-5">
        <div class="flex items-center gap-2">
            <button type="button" @click="view = 'upcoming'"
                :class="view === 'upcoming' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'bg-white text-gray-500 dark:bg-zinc-900 dark:text-gray-300'"
                class="rounded-2xl px-4 py-2 text-[11px] font-black uppercase tracking-wide ring-1 ring-gray-100 transition dark:ring-zinc-800">
                Upcoming &amp; Active ({{ deliveries.length }})
            </button>
            <button type="button" @click="view = 'history'"
                :class="view === 'history' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'bg-white text-gray-500 dark:bg-zinc-900 dark:text-gray-300'"
                class="rounded-2xl px-4 py-2 text-[11px] font-black uppercase tracking-wide ring-1 ring-gray-100 transition dark:ring-zinc-800">
                Trip History ({{ history.length }})
            </button>
        </div>

        <!-- Upcoming -->
        <div v-if="view === 'upcoming'" class="space-y-4">
            <!-- Live trip map -->
            <div v-if="selectedDelivery" class="space-y-3">
                <div v-if="deliveries.length > 1" class="flex flex-wrap gap-2">
                    <button v-for="d in deliveries" :key="d.id" type="button" @click="selectedId = d.id"
                        :class="selectedDelivery.id === d.id ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'bg-white text-gray-500 ring-gray-100 dark:bg-zinc-900 dark:text-gray-300 dark:ring-zinc-800'"
                        class="rounded-2xl px-3 py-1.5 text-[10px] font-black uppercase tracking-wide ring-1 transition">
                        {{ d.delivery_number }}
                    </button>
                </div>
                <TripMap :key="selectedDelivery.id" :delivery="selectedDelivery" :simulation="simulation"
                    :monti-location="montiLocation" :notify="notify"
                    @open-pod="(d, s) => $emit('open-pod', d, s)" @proximity="onProximity" />
            </div>

            <article v-for="delivery in deliveries" :key="delivery.id"
                class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                                {{ delivery.status.replace('_', ' ') }}
                            </span>
                            <span class="text-xs font-bold text-gray-400">{{ delivery.delivery_number }}</span>
                        </div>
                        <p class="mt-1 truncate text-sm font-black text-gray-900 dark:text-white">{{ delivery.route?.name || 'Optimized route' }}</p>
                        <p class="text-xs text-gray-500">
                            {{ delivery.truck?.truck_number || 'No truck' }} · {{ delivery.stops?.length || 0 }} stops · {{ delivery.total_distance_km || 0 }} km
                        </p>
                    </div>
                    <div class="text-right text-xs text-gray-500">
                        <p>Departed</p>
                        <p class="font-bold text-gray-900 dark:text-white">{{ formatDateTime(delivery.actual_departure) }}</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                    <div v-for="(stop, index) in (delivery.stops || [])" :key="stop.id" class="flex flex-wrap items-start gap-3 px-5 py-4">
                        <span :class="stopBadge(stop.outcome)"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-xs font-black">
                            {{ stop.sequence ?? index + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ stop.label || stop.client?.company_name || 'Drop-off' }}</p>
                            <p class="truncate text-xs text-gray-500">{{ stop.address || 'No address' }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-wide">
                                <span :class="outcomePill(stop.outcome)" class="rounded-full px-2 py-0.5">{{ outcomeLabel(stop.outcome) }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                            <button v-if="(stop.outcome || 'pending') === 'pending' && stop.status !== 'arrived'" type="button"
                                :disabled="!canArrive(delivery, stop)" :title="arriveHint(delivery, stop)"
                                @click="askArrive(delivery, stop)"
                                class="rounded-xl bg-amber-50 px-2.5 py-1.5 text-[10px] font-black uppercase text-amber-600 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-amber-900/30 dark:text-amber-300">Arrive</button>
                            <button v-if="stop.status === 'arrived' && ((stop.outcome || 'pending') === 'pending' || stop.outcome === 'partial')" type="button" @click="$emit('open-pod', delivery, stop)"
                                class="rounded-xl bg-emerald-600 px-2.5 py-1.5 text-[10px] font-black uppercase text-white hover:bg-emerald-700">Proof of Delivery</button>
                            <a v-if="stop.outcome === 'delivered' && stop.signature_path" :href="`/storage/${stop.signature_path}`" target="_blank"
                                class="rounded-xl bg-emerald-50 px-2.5 py-1.5 text-[10px] font-black uppercase text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300">Signed</a>
                            <p v-if="(stop.outcome || 'pending') === 'pending' && stop.status !== 'arrived' && arriveHint(delivery, stop)"
                                :class="canArrive(delivery, stop) ? 'text-emerald-600' : 'text-amber-600'"
                                class="w-full text-right text-[10px] font-bold">{{ arriveHint(delivery, stop) }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 bg-gray-50 px-5 py-3 text-xs text-gray-500 dark:bg-zinc-800/40">
                    <span>{{ delivery.packages?.length || 0 }} package(s) · {{ totalQuantity(delivery) }} pcs</span>
                    <span v-if="delivery.notes" class="truncate">Note: {{ delivery.notes }}</span>
                </div>
            </article>

            <div v-if="!deliveries.length" class="rounded-3xl border border-dashed border-gray-200 bg-white py-16 text-center dark:border-zinc-800 dark:bg-zinc-900">
                <Truck class="mx-auto h-8 w-8 text-gray-300" />
                <p class="mt-2 text-sm font-bold text-gray-500 dark:text-gray-400">No upcoming trips assigned.</p>
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
                        <p class="text-xs text-gray-500">
                            {{ delivery.stops?.length || 0 }} stops · {{ Number(delivery.total_distance_km || 0).toFixed(1) }} km · {{ delivery.truck?.truck_number || '—' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Completed</p>
                        <p class="text-xs font-bold text-gray-900 dark:text-white">{{ formatDateTime(delivery.arrival_time || delivery.updated_at) }}</p>
                        <p v-if="delivery.rating" class="text-[11px] font-bold text-amber-500">{{ delivery.rating }} ★</p>
                    </div>
                </div>
                <div v-if="delivery.rating_comment" class="mt-2 rounded-2xl bg-amber-50 px-3 py-2 text-xs italic text-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
                    “{{ delivery.rating_comment }}”
                </div>
            </article>

            <div v-if="!history.length" class="rounded-3xl border border-dashed border-gray-200 bg-white py-16 text-center dark:border-zinc-800 dark:bg-zinc-900">
                <RouteIcon class="mx-auto h-8 w-8 text-gray-300" />
                <p class="mt-2 text-sm font-bold text-gray-500 dark:text-gray-400">No completed trips yet.</p>
            </div>
        </div>

        <!-- Arrival confirmation modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="confirmArrive" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="confirmArrive = null" />
                    <div class="relative w-full max-w-sm overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                        <div class="flex items-center gap-3 bg-gradient-to-br from-amber-500 to-orange-600 px-5 py-4 text-white">
                            <MapPin class="h-6 w-6" />
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-100">Confirm arrival</p>
                                <h3 class="text-base font-black">Have you reached the destination?</h3>
                            </div>
                        </div>
                        <div class="space-y-4 p-5">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Confirm you have arrived at
                                <span class="font-black text-gray-900 dark:text-white">{{ confirmArrive.stop?.label || confirmArrive.stop?.client?.company_name || 'this stop' }}</span>.
                                This records your arrival time and unlocks the proof of delivery.
                            </p>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="confirmArrive = null"
                                    class="rounded-2xl bg-gray-100 px-5 py-2.5 text-xs font-black uppercase tracking-wide text-gray-600 hover:bg-gray-200 dark:bg-zinc-800 dark:text-gray-300">
                                    Cancel
                                </button>
                                <button type="button" @click="confirmArrival"
                                    class="rounded-2xl bg-emerald-600 px-5 py-2.5 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-700">
                                    Yes, I've arrived
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Truck, Route as RouteIcon, MapPin } from 'lucide-vue-next';
import TripMap from './TripMap.vue';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    simulation: { type: Boolean, default: false },
    montiLocation: { type: Object, default: null },
    notify: { type: Function, required: true },
});

const emit = defineEmits(['open-pod']);

const view = ref('upcoming');
const selectedId = ref(props.deliveries[0]?.id ?? null);

const selectedDelivery = computed(() =>
    props.deliveries.find((d) => d.id === selectedId.value) || props.deliveries[0] || null
);

watch(() => props.deliveries.map((d) => d.id).join(','), () => {
    if (!props.deliveries.some((d) => d.id === selectedId.value)) {
        selectedId.value = props.deliveries[0]?.id ?? null;
    }
});

const totalQuantity = (delivery) =>
    (delivery.packages || []).reduce((sum, p) => sum + (Number(p.quantity) || 0), 0);

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const outcomeLabel = (outcome) => ({
    pending: 'Pending', delivered: 'Delivered', attempted: 'Attempted', refused: 'Refused', partial: 'Partial',
}[outcome || 'pending'] || 'Pending');

const stopBadge = (outcome) => ({
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

const arrive = (delivery, stop) => {
    router.post(route('logistics.driver.stop.arrive', [delivery.id, stop.id]), {}, {
        preserveScroll: true,
        onSuccess: () => props.notify('success', 'Arrival recorded.'),
        onError: () => props.notify('error', 'Could not record arrival.'),
    });
};

const ARRIVAL_METERS = 10;
const proximity = ref({ stopId: null, meters: null, withinRange: false });
const onProximity = (payload) => { proximity.value = payload; };

const nextStopId = (delivery) => {
    const pending = (delivery.stops || [])
        .filter((s) => (s.outcome || 'pending') === 'pending')
        .slice()
        .sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
    return pending[0]?.id ?? null;
};

const canArrive = (delivery, stop) =>
    nextStopId(delivery) === stop.id
    && proximity.value.stopId === stop.id
    && proximity.value.withinRange === true;

const formatMeters = (meters) => (meters < 1000 ? `${meters} m` : `${(meters / 1000).toFixed(1)} km`);

const arriveHint = (delivery, stop) => {
    if (nextStopId(delivery) !== stop.id) return 'Complete the earlier stops first.';
    if (proximity.value.stopId !== stop.id || proximity.value.meters == null) return 'Waiting for your location…';
    if (proximity.value.withinRange) return 'You are at the drop-off — confirm arrival.';
    return `You are ${formatMeters(proximity.value.meters)} away — get within ${ARRIVAL_METERS} m to confirm arrival.`;
};

const confirmArrive = ref(null);
const askArrive = (delivery, stop) => {
    if (!canArrive(delivery, stop)) return;
    confirmArrive.value = { delivery, stop };
};
const confirmArrival = () => {
    const target = confirmArrive.value;
    confirmArrive.value = null;
    if (target) arrive(target.delivery, target.stop);
};
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95) translateY(12px); }
</style>
