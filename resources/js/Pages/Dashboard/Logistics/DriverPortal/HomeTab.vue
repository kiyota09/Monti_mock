<template>
    <div class="space-y-6">
        <!-- Duty status -->
        <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Current Duty Status</p>
                    <h2 class="mt-1 flex items-center gap-2 text-xl font-black text-gray-900 dark:text-white">
                        <span :class="statusDot(activeStatus)" class="h-2.5 w-2.5 rounded-full" />
                        {{ statusLabel(activeStatus) }}
                    </h2>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">On duty today</p>
                    <p class="text-sm font-black text-gray-900 dark:text-white">{{ formatMinutes(stats.on_duty_minutes_today) }}</p>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button v-for="option in dutyOptions" :key="option.value" type="button"
                    @click="setStatus(option.value)" :disabled="updatingDuty"
                    :class="activeStatus === option.value
                        ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25 ring-indigo-600'
                        : 'bg-gray-50 text-gray-600 ring-gray-200 hover:bg-gray-100 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700 dark:hover:bg-zinc-700'"
                    class="flex items-center justify-center gap-2 rounded-2xl px-3 py-3 text-[11px] font-black uppercase tracking-wide ring-1 transition-all active:scale-95 disabled:opacity-50">
                    <component :is="option.icon" class="h-4 w-4" />
                    {{ option.label }}
                </button>
            </div>
        </section>

        <!-- Active assignment -->
        <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 py-4 text-white">
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">Active Assignment</p>
                    <p class="truncate text-lg font-black">{{ activeDelivery ? activeDelivery.delivery_number : 'No active trip' }}</p>
                </div>
                <span v-if="activeDelivery" class="rounded-full bg-white/15 px-3 py-1 text-[10px] font-black uppercase ring-1 ring-white/20">
                    {{ activeDelivery.status.replace('_', ' ') }}
                </span>
            </div>

            <div v-if="activeDelivery" class="grid gap-3 p-5 sm:grid-cols-3">
                <div class="rounded-2xl border border-gray-100 p-3 dark:border-zinc-800">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Vehicle</p>
                    <p class="mt-1 text-sm font-black text-gray-900 dark:text-white">{{ activeDelivery.truck?.truck_number || '—' }}</p>
                    <p class="text-xs text-gray-500">{{ activeDelivery.truck?.plate_number || 'No plate' }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 p-3 dark:border-zinc-800">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Route</p>
                    <p class="mt-1 truncate text-sm font-black text-gray-900 dark:text-white">{{ activeDelivery.route?.name || 'Optimized route' }}</p>
                    <p class="truncate text-xs text-gray-500">{{ activeDelivery.stops?.length || 0 }} stop(s) · {{ activeDelivery.total_distance_km || 0 }} km</p>
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

        <!-- Route map -->
        <section v-if="activeDelivery" class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-5 py-3 dark:border-zinc-800">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Route map</p>
                    <p class="text-xs font-bold text-gray-500">Optimized stop sequence</p>
                </div>
                <button type="button" @click="navigate(nextStop)" :disabled="!nextStop"
                    class="rounded-xl bg-indigo-50 px-3 py-1.5 text-[10px] font-black uppercase text-indigo-600 hover:bg-indigo-100 disabled:opacity-50 dark:bg-indigo-900/30 dark:text-indigo-300">
                    Navigate next
                </button>
            </div>
            <div id="driver-home-map" class="relative z-0 h-80 w-full" />
        </section>

        <!-- Stats -->
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="stat in statCards" :key="stat.label" class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ stat.label }}</p>
                    <component :is="stat.icon" class="h-4 w-4" :class="stat.color" />
                </div>
                <p class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ stat.value }}</p>
                <p class="text-[11px] text-gray-400">{{ stat.hint }}</p>
            </div>
        </section>

        <!-- Quick actions -->
        <section class="grid gap-3 sm:grid-cols-3">
            <button type="button" @click="startTrip" :disabled="!startable"
                class="flex items-center gap-3 rounded-3xl border border-gray-100 bg-white p-4 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-900/30"><Play class="h-5 w-5 text-blue-600 dark:text-blue-300" /></span>
                <span>
                    <span class="block text-sm font-black text-gray-900 dark:text-white">Start Trip</span>
                    <span class="block text-xs text-gray-400">{{ startable ? 'Mark this trip as in transit' : 'No dispatched trip' }}</span>
                </span>
            </button>
                <button type="button" @click="$emit('open-tab', 'incidents')"
                class="flex items-center gap-3 rounded-3xl border border-gray-100 bg-white p-4 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-900/30"><AlertTriangle class="h-5 w-5 text-amber-600 dark:text-amber-300" /></span>
                <span>
                    <span class="block text-sm font-black text-gray-900 dark:text-white">Report Issue</span>
                    <span class="block text-xs text-gray-400">Delay, breakdown or accident</span>
                </span>
            </button>
            <button type="button" @click="navigate(nextStop)"
                :disabled="!nextStop"
                class="flex items-center gap-3 rounded-3xl border border-gray-100 bg-white p-4 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 dark:bg-emerald-900/30"><Navigation class="h-5 w-5 text-emerald-600 dark:text-emerald-300" /></span>
                <span>
                    <span class="block text-sm font-black text-gray-900 dark:text-white">Navigate</span>
                    <span class="block text-xs text-gray-400">{{ nextStop ? 'Open next stop in Maps' : 'No pending stop' }}</span>
                </span>
            </button>
        </section>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import {
    Play, Square, Coffee, LogOut, Truck, Navigation, AlertTriangle,
    Clock, Star, CheckCircle2, Gauge, Route as RouteIcon,
} from 'lucide-vue-next';

const props = defineProps({
    driver: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    duty: { type: Object, default: () => ({ current: null, logs: [], today_minutes: {} }) },
    deliveries: { type: Array, default: () => [] },
    currentTruck: { type: Object, default: null },
    montiLocation: { type: Object, default: null },
    notify: { type: Function, required: true },
});

const emit = defineEmits(['open-tab']);

const updatingDuty = ref(false);

const dutyOptions = [
    { value: 'on_duty', label: 'On Duty', icon: CheckCircle2 },
    { value: 'driving', label: 'Driving', icon: Truck },
    { value: 'on_break', label: 'On Break', icon: Coffee },
    { value: 'off_duty', label: 'Off Duty', icon: LogOut },
];

const activeDelivery = computed(() => props.deliveries[0] || null);
const activeStatus = computed(() => props.duty?.current?.status || 'off_duty');
const nextStop = computed(() => {
    for (const delivery of props.deliveries) {
        const stop = (delivery.stops || []).find((s) => (s.outcome || 'pending') === 'pending');
        if (stop) return stop;
    }
    return null;
});
const startable = computed(() => activeDelivery.value?.status === 'dispatched');

const statCards = computed(() => [
    { label: 'Trips this month', value: props.stats?.completed_month ?? 0, hint: 'Completed deliveries', icon: CheckCircle2, color: 'text-emerald-500' },
    { label: 'Distance', value: `${Number(props.stats?.km_month || 0).toFixed(1)} km`, hint: 'Driven this month', icon: Gauge, color: 'text-indigo-500' },
    { label: 'Drive time', value: formatMinutes(props.stats?.driving_minutes_today || 0), hint: 'Today', icon: Clock, color: 'text-amber-500' },
    { label: 'Rating', value: `${Number(props.stats?.rating || 0).toFixed(1)} ★`, hint: 'Customer feedback', icon: Star, color: 'text-fuchsia-500' },
]);

const statusLabel = (status) => ({
    on_duty: 'On Duty', driving: 'Driving', on_break: 'On Break', off_duty: 'Off Duty',
}[status] || 'Off Duty');

const statusDot = (status) => ({
    on_duty: 'bg-emerald-500', driving: 'bg-blue-500 animate-pulse', on_break: 'bg-amber-500', off_duty: 'bg-gray-400',
}[status] || 'bg-gray-400');

const formatMinutes = (minutes) => {
    const total = Math.max(0, Math.round(Number(minutes) || 0));
    const hours = Math.floor(total / 60);
    const mins = total % 60;
    return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
};

const setStatus = (status) => {
    if (updatingDuty.value || status === activeStatus.value) return;
    updatingDuty.value = true;

    const submit = (coords = {}) => {
        router.post(route('logistics.driver.duty'), { status, ...coords }, {
            preserveScroll: true,
            onSuccess: () => props.notify('success', `Duty status set to ${statusLabel(status)}.`),
            onError: () => props.notify('error', 'Could not update duty status.'),
            onFinish: () => { updatingDuty.value = false; },
        });
    };

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => submit({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }),
            () => submit(),
            { timeout: 4000 }
        );
    } else {
        submit();
    }
};

const startTrip = () => {
    if (!startable.value) return;
    router.post(route('logistics.driver.transit', activeDelivery.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => props.notify('success', 'Trip started. Stay safe!'),
        onError: () => props.notify('error', 'Could not start the trip.'),
    });
};

const navigate = (stop) => {
    if (!stop) return;
    if (stop.latitude && stop.longitude) {
        window.open(`https://www.google.com/maps/dir/?api=1&destination=${stop.latitude},${stop.longitude}`, '_blank');
    } else if (stop.address) {
        window.open(`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(stop.address)}`, '_blank');
    } else {
        props.notify('error', 'This stop has no location on file.');
    }
};

let map = null;
let layers = [];

const clearLayers = () => {
    layers.forEach((layer) => layer.remove());
    layers = [];
};

const drawMap = () => {
    if (!map) return;
    clearLayers();

    const delivery = activeDelivery.value;
    if (!delivery) return;

    const points = [];
    const geometry = Array.isArray(delivery.route_geometry) ? delivery.route_geometry : null;

    if (geometry && geometry.length > 1) {
        layers.push(L.polyline(geometry, { color: '#6366f1', weight: 5, opacity: 0.8 }).addTo(map));
        points.push(...geometry);
    } else {
        const coords = (delivery.stops || [])
            .filter((stop) => stop.latitude && stop.longitude)
            .map((stop) => [Number(stop.latitude), Number(stop.longitude)]);
        if (coords.length > 1) {
            layers.push(L.polyline(coords, { color: '#6366f1', weight: 4, dashArray: '8, 6' }).addTo(map));
        }
        points.push(...coords);
    }

    const originLat = delivery.origin_lat ?? props.montiLocation?.latitude;
    const originLng = delivery.origin_lng ?? props.montiLocation?.longitude;
    if (originLat && originLng) {
        const origin = [Number(originLat), Number(originLng)];
        points.push(origin);
        layers.push(L.marker(origin, {
            icon: L.divIcon({
                html: '<div style="background:#3b82f6;color:#fff;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:3px solid #fff;font-size:14px;">🏭</div>',
                iconSize: [30, 30],
                iconAnchor: [15, 15],
            }),
        }).bindPopup('Monti · Origin').addTo(map));
    }

    (delivery.stops || []).forEach((stop, index) => {
        if (!stop.latitude || !stop.longitude) return;
        const position = [Number(stop.latitude), Number(stop.longitude)];
        const done = stop.outcome && stop.outcome !== 'pending';
        layers.push(L.marker(position, {
            icon: L.divIcon({
                html: `<div style="background:${done ? '#10b981' : '#6366f1'};color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;font-size:11px;font-weight:800;">${stop.sequence ?? index + 1}</div>`,
                iconSize: [26, 26],
                iconAnchor: [13, 13],
            }),
        }).bindPopup(`<strong>${stop.label || stop.client?.company_name || 'Stop'}</strong><br>${stop.address || ''}`).addTo(map));
    });

    if (points.length) {
        map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 15 });
    }
};

const initMap = () => {
    const el = document.getElementById('driver-home-map');
    if (!el) return;

    if (!map) {
        map = L.map(el, { zoomControl: true }).setView([10.3157, 123.8854], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    }

    drawMap();
};

onMounted(() => nextTick(() => initMap()));
onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
        layers = [];
    }
});
watch(activeDelivery, () => nextTick(() => drawMap()));
</script>
