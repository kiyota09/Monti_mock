<template>
    <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-3 dark:border-zinc-800">
            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Trip map · live navigation</p>
                <p class="truncate text-sm font-black text-gray-900 dark:text-white">
                    {{ nextStop?.label || nextStop?.client?.company_name || 'All stops handled' }}
                </p>
            </div>
            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wide">
                <span :class="trackingDot" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1" :title="trackingLabel">
                    <span class="h-1.5 w-1.5 rounded-full" :class="trackingPulse" /> {{ trackingLabel }}
                </span>
                <span v-if="distanceLabel" class="rounded-full bg-indigo-50 px-2.5 py-1 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                    {{ distanceLabel }}
                </span>
            </div>
        </div>

        <!-- Map -->
        <div class="relative">
            <div :id="mapId" class="relative z-0 h-[340px] w-full sm:h-[440px]" />
        </div>

        <!-- Footer / controls -->
        <div class="space-y-3 border-t border-gray-100 px-5 py-4 dark:border-zinc-800">
            <div v-if="hasArrived" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-emerald-50 px-4 py-3 dark:bg-emerald-900/20">
                <div class="flex items-center gap-2">
                    <CheckCircle2 class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    <div>
                        <p class="text-sm font-black text-emerald-700 dark:text-emerald-300">You have reached the destination</p>
                        <p class="text-[11px] text-emerald-600/80 dark:text-emerald-300/80">Complete the proof of delivery to finish this stop.</p>
                    </div>
                </div>
                <button type="button" @click="completePod"
                    class="rounded-2xl bg-emerald-600 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-emerald-700 active:scale-95">
                    Complete Proof of Delivery
                </button>
            </div>

            <p v-else-if="!simulation && geoError" class="rounded-2xl bg-amber-50 px-4 py-2.5 text-[11px] font-bold text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                {{ geoError }} The map follows your device GPS — enable location access to track your movement.
            </p>
            <p v-else-if="!simulation" class="text-[11px] font-bold text-gray-400">
                Following your device GPS in real time{{ nextStop ? ' towards the next stop' : '' }}.
            </p>

            <div v-if="simulation" class="flex flex-wrap items-center gap-3 rounded-2xl bg-amber-50 px-4 py-3 dark:bg-amber-900/20">
                <FlaskConical class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                <p class="min-w-0 flex-1 text-[11px] font-bold text-amber-700 dark:text-amber-300">
                    Simulation testing is on. Use the button to auto-drive the truck along the route.
                </p>
                <button type="button" :disabled="simulating || !canSimulate" @click="startSimulation"
                    class="rounded-2xl bg-amber-600 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-amber-700 active:scale-95 disabled:opacity-50">
                    <span class="inline-flex items-center gap-2">
                        <Loader2 v-if="simulating" class="h-3.5 w-3.5 animate-spin" />
                        {{ simulating ? 'Simulating…' : 'Simulate delivery now' }}
                    </span>
                </button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { CheckCircle2, FlaskConical, Loader2 } from 'lucide-vue-next';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    delivery: { type: Object, required: true },
    simulation: { type: Boolean, default: false },
    montiLocation: { type: Object, default: null },
    notify: { type: Function, required: true },
});

const emit = defineEmits(['open-pod', 'proximity']);

const ARRIVAL_METERS = 10;

const mapId = `driver-trip-map-${props.delivery.id}`;

const simulating = ref(false);
const distanceMeters = ref(null);
const geoError = ref('');
const gpsLive = ref(false);

let map = null;
let routeLayer = null;
let truckMarker = null;
let watchId = null;
let rafId = null;
let truckPos = null;

const haversine = (lat1, lng1, lat2, lng2) => {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

const parseGeometry = (geom) => {
    if (!geom) return null;
    if (typeof geom === 'string') {
        try { geom = JSON.parse(geom); } catch { return null; }
    }
    return Array.isArray(geom) && geom.length > 1 ? geom.map((c) => [Number(c[0]), Number(c[1])]) : null;
};

const stops = computed(() =>
    (props.delivery.stops || [])
        .filter((s) => s.latitude != null && s.longitude != null)
        .slice()
        .sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0))
);

const origin = computed(() => {
    const lat = props.delivery.origin_lat ?? props.montiLocation?.latitude;
    const lng = props.delivery.origin_lng ?? props.montiLocation?.longitude;
    return lat != null && lng != null ? [Number(lat), Number(lng)] : null;
});

const nextStop = computed(() =>
    stops.value.find((s) => (s.outcome || 'pending') === 'pending') || null
);

const hasArrived = computed(() => !!(nextStop.value && nextStop.value.status === 'arrived'));

const canSimulate = computed(() => !!(nextStop.value && nextStop.value.latitude != null && nextStop.value.longitude != null));

const distanceLabel = computed(() => {
    if (hasArrived.value) return 'Arrived';
    if (distanceMeters.value == null) return '';
    return distanceMeters.value < 1000
        ? `${Math.round(distanceMeters.value)} m`
        : `${(distanceMeters.value / 1000).toFixed(1)} km`;
});

const trackingLabel = computed(() => {
    if (simulating.value) return 'Simulating';
    if (props.simulation) return 'Simulation mode';
    return gpsLive.value ? 'GPS live' : 'Locating…';
});
const trackingDot = computed(() => (props.simulation ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300'));
const trackingPulse = computed(() => (simulating.value || gpsLive.value ? 'bg-current animate-pulse' : 'bg-gray-400'));

// Full drawn path: real road geometry when available, else origin -> stops.
const routePath = computed(() => {
    const geometry = parseGeometry(props.delivery.route_geometry);
    if (geometry) return geometry;
    const pts = [];
    if (origin.value) pts.push(origin.value);
    stops.value.forEach((s) => pts.push([Number(s.latitude), Number(s.longitude)]));
    return pts;
});

// Path used by the simulation — trimmed to the next pending stop.
const travelPath = computed(() => {
    const full = routePath.value;
    if (!nextStop.value || full.length < 2) return full;
    const target = [Number(nextStop.value.latitude), Number(nextStop.value.longitude)];
    let bestIdx = full.length - 1;
    let bestDist = Infinity;
    full.forEach((p, i) => {
        const d = haversine(p[0], p[1], target[0], target[1]);
        if (d < bestDist) { bestDist = d; bestIdx = i; }
    });
    if (bestIdx < 1) return [full[0], target];
    return full.slice(0, bestIdx + 1);
});

const truckIcon = () => L.divIcon({
    className: '',
    html: '<div style="background:#f59e0b;color:#fff;border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:3px solid #fff;box-shadow:0 3px 10px rgba(245,158,11,.6);font-size:16px;">🚚</div>',
    iconSize: [34, 34],
    iconAnchor: [17, 17],
});

const setTruck = (latlng, follow = false) => {
    truckPos = latlng;
    if (!truckMarker) {
        truckMarker = L.marker(latlng, { icon: truckIcon(), zIndexOffset: 1000 }).addTo(map);
    } else {
        truckMarker.setLatLng(latlng);
    }
    if (follow && map) map.panTo(latlng, { animate: true, duration: 0.4 });
};

const drawRoute = () => {
    if (!map || !routeLayer) return;
    routeLayer.clearLayers();

    if (origin.value) {
        L.marker(origin.value, {
            icon: L.divIcon({
                html: '<div style="background:#3b82f6;color:#fff;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:3px solid #fff;font-size:14px;">🏭</div>',
                iconSize: [30, 30], iconAnchor: [15, 15],
            }),
        }).bindPopup('Monti · Origin').addTo(routeLayer);
    }

    const path = routePath.value;
    if (path.length > 1) {
        L.polyline(path, { color: '#6366f1', weight: 6, opacity: 0.85, lineJoin: 'round' }).addTo(routeLayer);
    }

    stops.value.forEach((stop, index) => {
        const done = stop.outcome && stop.outcome !== 'pending';
        L.marker([Number(stop.latitude), Number(stop.longitude)], {
            icon: L.divIcon({
                html: `<div style="background:${done ? '#10b981' : '#6366f1'};color:#fff;border-radius:9999px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:11px;border:2px solid #fff;">${stop.sequence ?? index + 1}</div>`,
                iconSize: [26, 26], iconAnchor: [13, 13],
            }),
        }).bindPopup(`<strong>${stop.sequence ?? index + 1}. ${stop.label || stop.client?.company_name || 'Stop'}</strong><br>${stop.address || ''}`).addTo(routeLayer);
    });

    const pts = [...path];
    if (origin.value) pts.push(origin.value);
    stops.value.forEach((s) => pts.push([Number(s.latitude), Number(s.longitude)]));
    if (pts.length) map.fitBounds(L.latLngBounds(pts), { padding: [40, 40], maxZoom: 15 });
};

const updateDistanceToTarget = (pos) => {
    if (!nextStop.value) { distanceMeters.value = null; return; }
    distanceMeters.value = haversine(pos[0], pos[1], Number(nextStop.value.latitude), Number(nextStop.value.longitude));
};

// Tell the parent how close the truck is so the Arrive button can be gated.
watch([distanceMeters, () => nextStop.value?.id], ([meters, stopId]) => {
    if (stopId == null) return;
    emit('proximity', {
        stopId,
        meters: meters == null ? null : Math.round(meters),
        withinRange: meters != null && meters <= ARRIVAL_METERS,
    });
});

const startGps = () => {
    if (props.simulation) return;
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
        geoError.value = 'This device does not expose location services.';
        return;
    }
    watchId = navigator.geolocation.watchPosition(
        (pos) => {
            gpsLive.value = true;
            geoError.value = '';
            const ll = [pos.coords.latitude, pos.coords.longitude];
            setTruck(ll, true);
            updateDistanceToTarget(ll);
        },
        (err) => {
            gpsLive.value = false;
            geoError.value = err.code === err.PERMISSION_DENIED
                ? 'Location permission was denied.'
                : 'Waiting for a GPS fix…';
        },
        { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
    );
};

const animateAlong = (coords, durationMs) => {
    if (coords.length < 2) return;
    const segs = [];
    let total = 0;
    for (let i = 0; i < coords.length - 1; i++) {
        const d = haversine(coords[i][0], coords[i][1], coords[i + 1][0], coords[i + 1][1]);
        segs.push(d);
        total += d;
    }
    const start = performance.now();
    const step = (now) => {
        const t = Math.min(1, (now - start) / durationMs);
        const travelled = t * total;
        let acc = 0;
        let i = 0;
        while (i < segs.length - 1 && acc + segs[i] < travelled) { acc += segs[i]; i++; }
        const segLen = segs[i] || 0;
        const localT = segLen > 0 ? Math.min(1, (travelled - acc) / segLen) : 0;
        const pos = [
            coords[i][0] + (coords[i + 1][0] - coords[i][0]) * localT,
            coords[i][1] + (coords[i + 1][1] - coords[i][1]) * localT,
        ];
        setTruck(pos, true);
        updateDistanceToTarget(pos);
        if (t < 1) {
            rafId = requestAnimationFrame(step);
        } else {
            simulating.value = false;
        }
    };
    rafId = requestAnimationFrame(step);
};

const startSimulation = () => {
    if (simulating.value || !canSimulate.value) return;
    const path = travelPath.value.length > 1 ? travelPath.value : (truckPos ? [truckPos, [Number(nextStop.value.latitude), Number(nextStop.value.longitude)]] : null);
    if (!path) { props.notify('error', 'No route to simulate.'); return; }

    const km = path.reduce((sum, p, i) => (i ? sum + haversine(path[i - 1][0], path[i - 1][1], p[0], p[1]) : 0), 0) / 1000;
    const duration = Math.min(16000, Math.max(4000, km * 1200));

    simulating.value = true;
    if (truckPos) setTruck(truckPos, false);
    animateAlong(path, duration);
};

const completePod = () => {
    if (nextStop.value) emit('open-pod', props.delivery, nextStop.value);
};

const initMap = () => {
    const el = document.getElementById(mapId);
    if (!el || map) return;
    map = L.map(el, { zoomControl: true, attributionControl: false }).setView(
        origin.value || [14.5995, 120.9842], 12
    );
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    routeLayer = L.layerGroup().addTo(map);
    drawRoute();
    setTruck(truckPos || origin.value || [14.5995, 120.9842], false);
    startGps();
};

onMounted(() => {
    // Defer so the container is laid out before Leaflet measures it.
    setTimeout(initMap, 0);
});

onBeforeUnmount(() => {
    if (watchId != null && typeof navigator !== 'undefined' && navigator.geolocation) {
        navigator.geolocation.clearWatch(watchId);
    }
    if (rafId != null) cancelAnimationFrame(rafId);
    if (map) { map.remove(); map = null; truckMarker = null; routeLayer = null; }
});

watch(() => props.delivery.id, () => {
    distanceMeters.value = null;
    truckPos = null;
});

watch(stops, () => { drawRoute(); }, { deep: true });
</script>
