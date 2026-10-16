<template>
    <Head title="Live Tracking" />
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
                            <Truck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <MapPin class="h-3.5 w-3.5" /> Logistics · Fleet Visibility
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Delivery Tracking</h1>
                            <p class="text-sm text-blue-100/90">Real-time map of all active deliveries and complete trip history.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ activeDeliveries.length }} active
                            </span>
                            <button @click="refreshData" class="flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95">
                                <RefreshCw class="h-4 w-4" /> Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Filter pills inside hero -->
                    <div class="relative mt-6 flex flex-wrap gap-2">
                        <button @click="setFilter('all')"
                            :class="currentFilter === 'all' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            All Deliveries
                        </button>
                        <button @click="setFilter('scheduled')"
                            :class="currentFilter === 'scheduled' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            Scheduled
                        </button>
                        <button @click="setFilter('active')"
                            :class="currentFilter === 'active' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            Active
                        </button>
                        <button @click="setFilter('delivered')"
                            :class="currentFilter === 'delivered' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            Delivered · {{ deliveredCount }}
                        </button>
                    </div>
                </div>

                <!-- Live Map Card -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay:80ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex flex-wrap items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg"><Globe class="h-5 w-5" /></span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Live Map</p>
                            <p class="text-sm font-black text-gray-900 dark:text-white">Active Deliveries &amp; Routes</p>
                        </div>
                        <div class="ml-auto flex flex-wrap items-center gap-4 text-[10px] font-black uppercase tracking-widest text-gray-400">
                            <div class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-blue-500"></span> HQ
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span> Destination
                            </div>
                            <div class="flex items-center gap-1.5">
                                <Truck class="h-3.5 w-3.5 text-amber-500" /> Truck
                            </div>
                        </div>
                    </div>
                    <div class="relative p-3">
                        <div v-if="activeDeliveries.length === 0" class="absolute inset-3 z-10 flex items-center justify-center bg-gray-50/80 dark:bg-zinc-800/50 backdrop-blur-sm rounded-3xl border-2 border-dashed border-gray-200 dark:border-zinc-700">
                            <div class="text-center">
                                <Truck class="h-8 w-8 text-gray-400 mx-auto mb-2 animate-bounce-soft" />
                                <p class="text-sm font-black text-gray-500 dark:text-gray-300">No active deliveries on the map</p>
                                <p class="text-xs text-gray-400 mt-1">Dispatched or in‑transit trips will appear here</p>
                            </div>
                        </div>
                        <div id="tracking-map" class="w-full rounded-3xl z-0" style="height: 480px;"></div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 dark:border-zinc-800 flex items-center gap-4 text-[10px] font-black uppercase tracking-widest">
                        <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            {{ activeDeliveries.length }} active trip(s)
                        </div>
                        <span class="text-gray-300">•</span>
                        <span class="text-gray-400">{{ deliveredCount }} delivered</span>
                    </div>
                </div>

                <!-- Deliveries Table -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-900/10 flex items-center gap-2">
                        <Truck class="h-5 w-5 text-blue-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Deliveries</h2>
                        <span class="ml-auto rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-[11px] font-black px-2.5 py-1">{{ filteredDeliveries.length }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-6 py-4">Delivery #</th>
                                    <th class="px-6 py-4">Driver / Truck</th>
                                    <th class="px-6 py-4">Route</th>
                                    <th class="px-6 py-4">Scheduled</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4 text-right">Packages</th>
                                    <th class="px-6 py-4 text-right"></th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(delivery, i) in filteredDeliveries" :key="delivery.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ delivery.delivery_number }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ delivery.driver?.user?.name || '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ delivery.truck?.truck_number || '—' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ delivery.route?.name || '—' }}</div>
                                        <div class="text-xs text-gray-500 truncate max-w-[200px]">
                                            {{ delivery.route?.origin }} → {{ delivery.route?.destination }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDateTime(delivery.scheduled_departure) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="statusBadge(delivery.status)" class="px-2.5 py-1 rounded-full ring-1 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ formatStatus(delivery.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-black text-gray-900 dark:text-white">
                                        {{ delivery.packages?.length || 0 }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button
                                            @click="flyToDelivery(delivery)"
                                            class="inline-flex items-center gap-1 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-300 hover:bg-indigo-600 hover:text-white transition-all hover:-translate-y-0.5"
                                        >
                                            <Eye class="h-3.5 w-3.5" /> View on Map
                                        </button>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="filteredDeliveries.length === 0" class="px-6 py-16 text-center">
                            <Truck class="h-10 w-10 mx-auto mb-2 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                            <p class="text-sm font-black text-gray-400">No deliveries match the selected filter.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { MapPin, RefreshCw, Globe, Truck, Eye } from 'lucide-vue-next';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    filter: { type: String, default: 'all' },
    montiLocation: { type: Object, default: null },
});

const currentFilter = ref(props.filter || 'all');
let map = null;
let markersLayer = null;
let polylinesLayer = null;

// Fallback coordinates if CEO hasn't set any
const defaultLat = 14.3275;
const defaultLng = 120.9404;
const montiLat = props.montiLocation ? Number(props.montiLocation.latitude) : defaultLat;
const montiLng = props.montiLocation ? Number(props.montiLocation.longitude) : defaultLng;

const filteredDeliveries = computed(() => {
    if (currentFilter.value === 'all') return props.deliveries;
    if (currentFilter.value === 'scheduled') return props.deliveries.filter(d => d.status === 'pending');
    if (currentFilter.value === 'active') return props.deliveries.filter(d => ['dispatched', 'in_transit'].includes(d.status));
    if (currentFilter.value === 'delivered') return props.deliveries.filter(d => d.status === 'delivered');
    return props.deliveries;
});

const activeDeliveries = computed(() =>
    props.deliveries.filter(d => ['dispatched', 'in_transit'].includes(d.status))
);

const deliveredCount = computed(() => props.deliveries.filter(d => d.status === 'delivered').length);

const setFilter = (filter) => {
    currentFilter.value = filter;
    router.get(route('logistics.tracking'), { filter }, { preserveState: true, preserveScroll: true });
};

const refreshData = () => {
    router.reload({ only: ['deliveries'] });
};

const statusBadge = (status) => {
    const map = {
        pending: 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700',
        dispatched: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        in_transit: 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        delivered: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
    };
    return map[status] || 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const formatStatus = (status) => {
    const map = {
        pending: 'Scheduled',
        dispatched: 'Dispatched',
        in_transit: 'In Transit',
        delivered: 'Delivered',
    };
    return map[status] || status;
};

const formatDateTime = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// ---------- Map ----------
const buildTruckIcon = () =>
    L.divIcon({
        className: '',
        html: `<div style="background:#f59e0b;color:white;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:3px solid white;box-shadow:0 3px 10px rgba(245,158,11,0.5);font-size:16px;">🚚</div>`,
        iconSize: [32, 32],
        iconAnchor: [16, 16],
    });

const buildDestIcon = () =>
    L.divIcon({
        className: '',
        html: `<div style="background:#10b981;color:white;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border:2px solid white;box-shadow:0 3px 10px rgba(16,185,129,0.5);font-size:14px;">📍</div>`,
        iconSize: [28, 28],
        iconAnchor: [14, 28],
    });

const initMap = () => {
    if (map) return;
    const street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });
    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 20 });
    map = L.map('tracking-map', { center: [montiLat, montiLng], zoom: 11, layers: [street] });
    L.control.layers({ Street: street, Satellite: satellite }).addTo(map);

    // HQ marker
    L.marker([montiLat, montiLng], {
        icon: L.divIcon({
            html: `<div style="background:#3b82f6;color:white;border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:3px solid white;box-shadow:0 3px 10px rgba(59,130,246,0.5);font-size:16px;">🏭</div>`,
            iconSize: [34, 34],
            iconAnchor: [17, 17],
        }),
    }).bindPopup('<strong>MontiTextiles HQ</strong>').addTo(map);

    markersLayer = L.layerGroup().addTo(map);
    polylinesLayer = L.layerGroup().addTo(map);

    drawActiveDeliveries();
};

const drawActiveDeliveries = () => {
    if (!map || !markersLayer || !polylinesLayer) return;
    markersLayer.clearLayers();
    polylinesLayer.clearLayers();

    activeDeliveries.value.forEach((delivery) => {
        const route = delivery.route;
        if (!route) return;

        let polyline;
        if (route.route_geometry) {
            let geom = route.route_geometry;
            if (typeof geom === 'string') {
                try { geom = JSON.parse(geom); } catch { geom = null; }
            }
            if (Array.isArray(geom) && geom.length > 1) {
                polyline = L.polyline(geom, { color: '#6366f1', weight: 5, opacity: 0.8 });
            }
        }
        if (!polyline && route.origin_lat && route.destination_lat) {
            polyline = L.polyline(
                [
                    [Number(route.origin_lat), Number(route.origin_lng)],
                    [Number(route.destination_lat), Number(route.destination_lng)],
                ],
                { color: '#f59e0b', weight: 4, dashArray: '10, 6', opacity: 0.7 }
            );
        }
        if (polyline) {
            polyline.bindPopup(`
                <strong>${delivery.delivery_number}</strong><br>
                ${delivery.driver?.user?.name || 'No driver'}<br>
                Truck: ${delivery.truck?.truck_number || '—'}<br>
                Status: ${formatStatus(delivery.status)}
            `);
            polyline.addTo(polylinesLayer);
        }

        // Destination marker
        if (route.destination_lat && route.destination_lng) {
            L.marker([Number(route.destination_lat), Number(route.destination_lng)], { icon: buildDestIcon() })
                .bindPopup(`<strong>Destination</strong><br>${route.destination}`)
                .addTo(markersLayer);
        }

        // Truck marker – placed at origin for now (future GPS can update this)
        const truckLat = route.origin_lat || montiLat;
        const truckLng = route.origin_lng || montiLng;
        L.marker([Number(truckLat), Number(truckLng)], { icon: buildTruckIcon() })
            .bindPopup(`
                <strong>${delivery.delivery_number}</strong><br>
                Driver: ${delivery.driver?.user?.name || '—'}<br>
                Truck: ${delivery.truck?.truck_number || '—'}<br>
                Status: ${formatStatus(delivery.status)}
            `)
            .addTo(markersLayer);
    });

    if (activeDeliveries.value.length > 0) {
        const bounds = L.latLngBounds([]);
        markersLayer.eachLayer((layer) => {
            if (layer.getLatLng) bounds.extend(layer.getLatLng());
        });
        polylinesLayer.eachLayer((layer) => {
            if (layer.getBounds) bounds.extend(layer.getBounds());
        });
        if (bounds.isValid()) map.fitBounds(bounds, { padding: [50, 50] });
    }
};

const flyToDelivery = (delivery) => {
    if (!map) return;
    const route = delivery.route;
    if (route?.route_geometry) {
        let geom = route.route_geometry;
        if (typeof geom === 'string') {
            try { geom = JSON.parse(geom); } catch { geom = null; }
        }
        if (Array.isArray(geom) && geom.length > 1) {
            map.fitBounds(L.polyline(geom).getBounds(), { padding: [50, 50] });
            return;
        }
    }
    if (route?.origin_lat && route?.destination_lat) {
        map.fitBounds([
            [Number(route.origin_lat), Number(route.origin_lng)],
            [Number(route.destination_lat), Number(route.destination_lng)],
        ], { padding: [50, 50] });
    }
};

watch(activeDeliveries, () => {
    nextTick(() => drawActiveDeliveries());
}, { deep: true });

onMounted(() => {
    nextTick(() => initMap());
});
</script>

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
.modal-enter-active, .modal-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95) translateY(12px); }
:deep(.leaflet-control-layers) {
    border-radius: 1rem !important; border: none !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.12); padding: 6px 10px;
    font-weight: 900; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em;
}
:deep(.leaflet-popup-content-wrapper) { border-radius: 1rem !important; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
:deep(.leaflet-popup-tip) { display: none; }
</style>
