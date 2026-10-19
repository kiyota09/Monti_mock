<template>
    <Head title="Dispatch Center" />
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
                            <Send class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Logistics · Fleet Control
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Dispatch Center <span v-if="!canEditDispatch" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">Build truck loads from staged packages, assign crew, and send them out.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse" /> {{ packages.length }} staged
                            </span>
                            <button @click="refreshData" title="Refresh"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur hover:bg-white/25 hover:scale-105 active:scale-95 transition-all">
                                <RefreshCw class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="relative mt-6 flex flex-wrap gap-2">
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide ring-1 ring-white/25 backdrop-blur">{{ packages.length }} packages</span>
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide ring-1 ring-white/25 backdrop-blur">{{ trucks.length }} trucks</span>
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide ring-1 ring-white/25 backdrop-blur">{{ drivers.length }} drivers</span>
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide ring-1 ring-white/25 backdrop-blur hidden sm:inline-block">{{ conductors.length }} conductors</span>
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide ring-1 ring-white/25 backdrop-blur">{{ deliveries.length }} active loads</span>
                    </div>
                </div>

                <!-- Load builder + map -->
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                    <!-- Builder -->
                    <div class="lg:col-span-3 animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                            <Truck class="h-5 w-5 text-indigo-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Build a Truck Load</h2>
                        </div>

                        <div class="p-6 space-y-5">
                            <!-- Truck -->
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Truck *</label>
                                <select v-model="form.truck_id"
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Select a truck</option>
                                    <option v-for="truck in trucks" :key="truck.id" :value="truck.id">
                                        {{ truck.truck_number }} - {{ truck.model }} ({{ truck.plate_number }}) · cap {{ truck.max_packages }}
                                    </option>
                                </select>
                                <div v-if="selectedTruck" class="mt-2 flex items-center gap-2">
                                    <div class="h-2 flex-1 rounded-full bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                                        <div class="h-full rounded-full transition-all"
                                            :class="capacityPct >= 100 ? 'bg-rose-500' : capacityPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500'"
                                            :style="{ width: capacityPct + '%' }" />
                                    </div>
                                    <span class="text-[11px] font-black" :class="overCapacity ? 'text-rose-600' : 'text-gray-500'">
                                        {{ selectedIds.length }} / {{ selectedTruck.max_packages }} packages
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Driver -->
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Driver *</label>
                                    <select v-model="form.driver_id"
                                        class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="">Select a driver</option>
                                        <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                            {{ driver.user?.name }} — {{ driver.rating }}★
                                        </option>
                                    </select>
                                </div>
                                <!-- Scheduled -->
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Scheduled Departure *</label>
                                    <input type="datetime-local" v-model="form.scheduled_departure"
                                        class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <!-- Conductor 1 -->
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Conductor 1</label>
                                    <select v-model="form.conductor1_id"
                                        class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="">None</option>
                                        <option v-for="c in conductors" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
                                    </select>
                                </div>
                                <!-- Conductor 2 -->
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Conductor 2</label>
                                    <select v-model="form.conductor2_id"
                                        class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="">None</option>
                                        <option v-for="c in conductors" :key="c.id" :value="c.id">{{ c.user?.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Package pool -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider">Packages for this load *</label>
                                    <button v-if="canEditDispatch && packages.length" @click="selectAllForTruck" class="text-[10px] font-black uppercase tracking-widest text-indigo-600 hover:underline">Fill to capacity</button>
                                </div>
                                <div class="max-h-72 overflow-y-auto rounded-2xl border border-gray-100 dark:border-zinc-800 divide-y divide-gray-50 dark:divide-zinc-800">
                                    <label v-for="pkg in packages" :key="pkg.id"
                                        :class="['flex items-center gap-3 px-4 py-3 transition-colors', isDisabled(pkg) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 cursor-pointer']">
                                        <input type="checkbox" :value="pkg.id" v-model="selectedIds" :disabled="isDisabled(pkg) && !selectedIds.includes(pkg.id)"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-black text-gray-900 dark:text-white">{{ pkg.product_name || 'Package' }} <span class="font-mono text-xs font-bold text-gray-400">· {{ pkg.package_number }}</span></p>
                                            <p class="text-xs text-gray-500 truncate">{{ pkg.client_name || 'Unassigned destination' }} <span class="text-gray-400">{{ pkg.latitude ? '· mapped' : '· no coords' }}</span></p>
                                        </div>
                                        <span class="text-xs font-bold text-gray-500 shrink-0">{{ pkg.quantity }} pcs</span>
                                    </label>
                                    <div v-if="!packages.length" class="px-4 py-10 text-center">
                                        <Package class="mx-auto h-9 w-9 text-gray-200 dark:text-zinc-700" />
                                        <p class="mt-2 text-xs font-bold text-gray-400">No staged packages. Stage packages from the Load page first.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Ordered stops preview -->
                            <div v-if="orderedStops.length" class="rounded-2xl bg-gray-50 dark:bg-zinc-800/50 border border-gray-100 dark:border-zinc-800 p-4">
                                <p class="text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Optimized stop order (nearest first)</p>
                                <ol class="space-y-1.5">
                                    <li v-for="(stop, i) in orderedStops" :key="stop.client_id ?? i" class="flex items-center gap-3 text-sm">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-[11px] font-black text-white">{{ i + 1 }}</span>
                                        <span class="font-bold text-gray-700 dark:text-gray-200 truncate">{{ stop.label }}</span>
                                        <span v-if="stop.leg_km != null" class="ml-auto text-[11px] font-black text-gray-400">{{ stop.leg_km }} km</span>
                                    </li>
                                </ol>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Notes (optional)</label>
                                <textarea v-model="form.notes" rows="2" placeholder="Special instructions for driver/conductor..."
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
                            </div>

                            <button v-if="canEditDispatch" @click="submitLoad" :disabled="!canSubmit"
                                class="w-full py-3.5 bg-emerald-600 text-white rounded-2xl text-[11px] font-black uppercase tracking-widest hover:bg-emerald-700 hover:shadow-lg active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                                <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <Send v-else class="h-4 w-4" />
                                {{ overCapacity ? 'Too many packages for truck' : `Create Load (${selectedIds.length} pkg)` }}
                            </button>
                        </div>
                    </div>

                    <!-- Map preview -->
                    <div class="lg:col-span-2 animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-emerald-50 to-transparent dark:from-emerald-900/20 flex items-center gap-2">
                            <MapPin class="h-5 w-5 text-emerald-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Route Preview</h2>
                        </div>
                        <div id="dispatch-map" class="flex-1 min-h-[420px] w-full z-0"></div>
                        <div class="p-3 text-[11px] font-bold text-gray-400 border-t border-gray-100 dark:border-zinc-800">
                            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-600" /> Selected stop</span>
                            <span class="ml-3 inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gray-400" /> Staged destination</span>
                        </div>
                    </div>
                </div>

                <!-- Active loads -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Truck class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Active Loads</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ deliveries.length }}</span>
                    </div>

                    <div class="divide-y divide-gray-50 dark:divide-zinc-800">
                        <div v-for="d in deliveries" :key="d.id" class="p-5 hover:bg-indigo-50/40 dark:hover:bg-indigo-900/10 transition-colors">
                            <div class="flex flex-wrap items-start gap-4">
                                <div class="h-11 w-11 rounded-2xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 shrink-0">
                                    <Truck class="h-5 w-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ d.delivery_number }}</span>
                                        <span :class="d.status === 'in_transit' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'" class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase">{{ d.status === 'in_transit' ? 'Out for delivery' : 'Scheduled' }}</span>
                                        <span class="text-[11px] font-bold text-gray-400">{{ d.packages?.length }} pkg · {{ d.total_distance_km ? d.total_distance_km + ' km' : '—' }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ d.truck?.truck_number || '—' }} · {{ d.driver?.user?.name || 'No driver' }}
                                        <span v-if="d.conductor1?.user?.name"> · {{ d.conductor1.user.name }}</span>
                                    </p>
                                    <ol class="mt-2 space-y-1">
                                        <li v-for="stop in d.stops" :key="stop.id" class="flex items-center gap-2 text-xs">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-700 text-[10px] font-black text-white">{{ stop.sequence }}</span>
                                            <span class="font-bold text-gray-700 dark:text-gray-200">{{ stop.label }}</span>
                                            <span class="text-gray-400 truncate">{{ stop.address }}</span>
                                            <span v-if="stop.status === 'delivered'" class="ml-auto text-emerald-600 font-black">received</span>
                                        </li>
                                    </ol>
                                </div>
                                <div v-if="canEditDispatch" class="shrink-0">
                                    <button v-if="d.status === 'dispatched'" @click="deliver(d)"
                                        class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:shadow-lg hover:-translate-y-0.5 active:scale-95 transition-all flex items-center gap-2">
                                        <Navigation class="h-4 w-4" /> Deliver
                                    </button>
                                    <span v-else class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-blue-600">
                                        <Navigation class="h-4 w-4" /> En route
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-if="!deliveries.length" class="px-6 py-12 text-center">
                            <Truck class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                            <p class="mt-2 text-sm font-bold text-gray-400">No active loads.</p>
                            <p class="text-xs text-gray-400">Build a load above to get started.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-lg text-white font-bold text-sm"
                :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                {{ toast.message }}
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { Send, RefreshCw, Truck, Loader2, Sparkles, Package, MapPin, Navigation } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const { canEdit } = usePageAccess();
const canEditDispatch = computed(() => canEdit('LOG', 'dispatch'));

const props = defineProps({
    packages:      { type: Array, default: () => [] },
    trucks:        { type: Array, default: () => [] },
    drivers:       { type: Array, default: () => [] },
    conductors:    { type: Array, default: () => [] },
    routes:        { type: Array, default: () => [] },
    deliveries:    { type: Array, default: () => [] },
    montiLocation: { type: Object, default: null },
});

const selectedIds = ref([]);
const toast = ref({ show: false, type: 'success', message: '' });

const form = useForm({
    truck_id: '',
    driver_id: '',
    conductor1_id: '',
    conductor2_id: '',
    scheduled_departure: '',
    notes: '',
    package_ids: [],
});

const montiLat = computed(() => Number(props.montiLocation?.lat) || 10.3157);
const montiLng = computed(() => Number(props.montiLocation?.lng) || 123.8854);

const selectedTruck = computed(() => props.trucks.find(t => t.id === form.truck_id) || null);
const overCapacity = computed(() => selectedTruck.value && selectedIds.value.length > selectedTruck.value.max_packages);
const capacityPct = computed(() => selectedTruck.value ? Math.min(100, (selectedIds.value.length / selectedTruck.value.max_packages) * 100) : 0);

const canSubmit = computed(() => canEditDispatch.value
    && form.truck_id && form.driver_id && form.scheduled_departure
    && selectedIds.value.length > 0 && !overCapacity.value && !form.processing);

const packageRows = computed(() => props.packages);
const selectedRows = computed(() => packageRows.value.filter(p => selectedIds.value.includes(p.id)));

const isDisabled = (pkg) => {
    if (!selectedTruck.value) return false;
    if (selectedIds.value.includes(pkg.id)) return false;
    return selectedIds.value.length >= selectedTruck.value.max_packages;
};

const selectAllForTruck = () => {
    if (!selectedTruck.value) { selectedIds.value = packageRows.value.map(p => p.id); return; }
    selectedIds.value = packageRows.value.slice(0, selectedTruck.value.max_packages).map(p => p.id);
};

const haversine = (lat1, lng1, lat2, lng2) => {
    const R = 6371, dLat = (lat2 - lat1) * Math.PI / 180, dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

// Group selected packages by destination client, then order nearest-first for preview.
const orderedStops = computed(() => {
    const groups = {};
    for (const p of selectedRows.value) {
        const key = p.client_id ?? `x_${p.id}`;
        groups[key] ??= { client_id: p.client_id, label: p.client_name || 'Unassigned destination', latitude: p.latitude, longitude: p.longitude };
    }
    const stops = Object.values(groups).filter(s => s.latitude != null && s.longitude != null);
    const noCoords = Object.values(groups).filter(s => s.latitude == null || s.longitude == null);

    const ordered = [];
    let cLat = montiLat.value, cLng = montiLng.value;
    const remaining = [...stops];
    while (remaining.length) {
        let bi = 0, bk = Infinity;
        remaining.forEach((s, i) => {
            const km = haversine(cLat, cLng, Number(s.latitude), Number(s.longitude));
            if (km < bk) { bk = km; bi = i; }
        });
        const chosen = remaining.splice(bi, 1)[0];
        chosen.leg_km = Math.round(bk * 10) / 10;
        ordered.push(chosen);
        cLat = Number(chosen.latitude); cLng = Number(chosen.longitude);
    }
    return [...ordered, ...noCoords];
});

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3200);
};

const refreshData = () => {
    router.reload({ only: ['packages', 'trucks', 'drivers', 'conductors', 'deliveries'] });
};

const submitLoad = () => {
    form.package_ids = [...selectedIds.value];
    form.post(route('logistics.dispatch.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', 'Truck load created and crew assigned.');
            form.reset();
            selectedIds.value = [];
            refreshData();
        },
        onError: (errors) => showToast('error', Object.values(errors)[0] || 'Failed to create load.'),
    });
};

const deliver = (d) => {
    router.post(route('logistics.dispatch.deliver', d.id), {}, {
        preserveScroll: true,
        onSuccess: () => { showToast('success', 'Load is out for delivery. Clients notified.'); refreshData(); },
        onError: () => showToast('error', 'Failed to send load out for delivery.'),
    });
};

// ─── Map ──────────────────────────────────────────────────────────────
let map = null;
let markersLayer = null;
let polylineLayer = null;
let previewTimer = null;
let previewController = null;
let previewSeq = 0;

const initMap = () => {
    if (map) return;
    map = L.map('dispatch-map', { center: [montiLat.value, montiLng.value], zoom: 11 });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    markersLayer = L.layerGroup().addTo(map);
    polylineLayer = L.layerGroup().addTo(map);
    L.marker([montiLat.value, montiLng.value]).bindPopup('MontiTextiles HQ').addTo(map);
    drawMap();
};

const numberedIcon = (n, color) => L.divIcon({
    className: '',
    html: `<div style="background:${color};color:#fff;border-radius:9999px;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:11px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3)">${n}</div>`,
    iconSize: [24, 24], iconAnchor: [12, 12],
});

const drawMap = () => {
    if (!map || !markersLayer) return;
    markersLayer.clearLayers();
    polylineLayer.clearLayers();

    // Staged destinations (grey).
    const seen = new Set();
    for (const p of packageRows.value) {
        if (p.latitude == null || p.longitude == null) continue;
        const key = p.client_id ?? p.id;
        if (seen.has(key)) continue;
        seen.add(key);
        L.circleMarker([Number(p.latitude), Number(p.longitude)], { radius: 5, color: '#94a3b8', fillColor: '#94a3b8', fillOpacity: 0.9 })
            .bindPopup(p.client_name || 'Destination').addTo(markersLayer);
    }

    // Selected stops (numbered, nearest-first).
    const pts = [[montiLat.value, montiLng.value]];
    orderedStops.value.forEach((s, i) => {
        if (s.latitude == null || s.longitude == null) return;
        L.marker([Number(s.latitude), Number(s.longitude)], { icon: numberedIcon(i + 1, '#4f46e5') })
            .bindPopup(`<strong>${i + 1}. ${s.label}</strong>`).addTo(markersLayer);
        pts.push([Number(s.latitude), Number(s.longitude)]);
    });

    if (pts.length > 1) {
        // Straight dashed line is shown instantly, then replaced by the real
        // highway geometry once the routing lookup resolves.
        L.polyline(pts, { color: '#4f46e5', weight: 3, opacity: 0.55, dashArray: '8,6' }).addTo(polylineLayer);
        map.fitBounds(L.polyline(pts).getBounds(), { padding: [40, 40], maxZoom: 14 });

        clearTimeout(previewTimer);
        previewTimer = setTimeout(() => fetchRoadGeometry(pts), 300);
    }
};

// Fetch highway-accurate geometry for the ordered preview points.
const fetchRoadGeometry = async (pts) => {
    if (previewController) previewController.abort();
    previewController = new AbortController();
    const seq = ++previewSeq;

    const params = new URLSearchParams();
    pts.forEach((p, i) => {
        params.append(`points[${i}][lat]`, p[0]);
        params.append(`points[${i}][lng]`, p[1]);
    });

    try {
        const res = await fetch(`${route('logistics.dispatch.preview')}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: previewController.signal,
        });
        if (!res.ok) return;
        const data = await res.json();
        if (seq !== previewSeq || !map || !polylineLayer) return;

        const geometry = Array.isArray(data.geometry) ? data.geometry : null;
        if (!geometry || geometry.length < 2) return;

        polylineLayer.clearLayers();
        L.polyline(geometry, { color: '#4f46e5', weight: 4, opacity: 0.85, lineJoin: 'round' }).addTo(polylineLayer);
        map.fitBounds(L.polyline(geometry).getBounds(), { padding: [40, 40], maxZoom: 14 });
    } catch (e) {
        // Routing unavailable — keep the straight dashed fallback line.
    }
};

onMounted(() => nextTick(initMap));
watch(orderedStops, () => nextTick(drawMap));
watch(() => form.truck_id, () => {
    if (selectedTruck.value && selectedIds.value.length > selectedTruck.value.max_packages) {
        selectedIds.value = selectedIds.value.slice(0, selectedTruck.value.max_packages);
    }
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
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
:deep(.leaflet-container) { background: #e5e7eb; }
</style>
