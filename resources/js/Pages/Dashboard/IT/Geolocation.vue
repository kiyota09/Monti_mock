<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    MapPin, ShieldCheck, Loader2, Globe, Navigation,
    Crosshair, Radio, Layers, Save, AlertCircle, CheckCircle2,
    Database, Activity, Clock, History, X, Archive,
    Pencil, Trash2, Power, LocateFixed, Building2, Warehouse
} from 'lucide-vue-next';
import Swal from 'sweetalert2';

// Leaflet imports
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    savedLocation: Object,
    locationHistory: { type: Array, default: () => [] },
    sites: { type: Array, default: () => [] },
    activeSites: { type: Array, default: () => [] },
});

// Helper function to safely convert to number
const toNum = (val) => val ? Number(val) : null;

// ── Multi-site state: every row is a company site (HQ, warehouse, branch) ──
const initialSites = (props.sites?.length ? props.sites : props.locationHistory) ?? [];
const sitesList = ref([...initialSites]);

const activeCount = computed(() => sitesList.value.filter(s => s.is_active ?? true).length);

// Draft pin for a NEW site
const firstActive = computed(() => sitesList.value.find(s => s.is_active ?? true) ?? sitesList.value[0] ?? props.savedLocation);
const coords = ref({
    latitude: toNum(firstActive.value?.latitude) || 14.5995,
    longitude: toNum(firstActive.value?.longitude) || 120.9842,
    accuracy: null
});
const siteName = ref('');
const rangeRadius = ref(toNum(firstActive.value?.range_radius) || 500);
const selectedSiteId = ref(firstActive.value?.id ?? null);

// For the Status Bar
const lastSavedInfo = ref({
    lat: toNum(props.savedLocation?.latitude),
    lng: toNum(props.savedLocation?.longitude),
    time: props.savedLocation?.created_at ? new Date(props.savedLocation.created_at).toLocaleTimeString() : null
});

const isSyncing = ref(false);
const isSaving = ref(false);
const statusMsg = ref(null);
const errorMsg = ref(null);
const showArchiveModal = ref(false);
const placeName = ref(null);
const resolvingPlace = ref(false);
const resolvedNames = ref({}); // { logId: placeName } for old archives
const placeCache = new Map();

// Edit modal state
const showEditModal = ref(false);
const editingSite = ref(null);
const editForm = ref({ label: '', latitude: 0, longitude: 0, range_radius: 500, place_name: '' });
const isUpdating = ref(false);

const shortName = (display_name) => display_name ? display_name.split(',').slice(0, 3).join(',').trim() : null;

const reverseGeocode = async (lat, lng) => {
    const key = `${Number(lat).toFixed(5)},${Number(lng).toFixed(5)}`;
    if (placeCache.has(key)) return placeCache.get(key);
    try {
        const res = await axios.get('https://nominatim.openstreetmap.org/reverse', {
            params: { lat, lon: lng, format: 'json' },
            headers: { 'Accept-Language': 'en' },
        });
        const name = shortName(res.data?.display_name);
        placeCache.set(key, name);
        return name;
    } catch {
        placeCache.set(key, null);
        return null;
    }
};

const openArchiveConfirm = async () => {
    errorMsg.value = null;
    if (!siteName.value.trim()) {
        errorMsg.value = 'Please name this site first (e.g. Main Warehouse, Second Branch).';
        Swal.fire({
            title: 'Site name required',
            text: 'Give this location a name so staff know which site it is — e.g. "Warehouse 1" or "Second Branch".',
            icon: 'warning',
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'Got it',
        });
        return;
    }
    showArchiveModal.value = true;
    resolvingPlace.value = true;
    placeName.value = null;
    placeName.value = await reverseGeocode(coords.value.latitude, coords.value.longitude);
    resolvingPlace.value = false;
};

// Local archive log (seeded from server, prepended on every save)
const history = sitesList;

let map = null;
let draftMarker = null;
let draftCircle = null;
const siteLayers = new Map(); // id -> { marker, circle }

const siteColor = (site) => (site.is_active ?? true) ? '#4f46e5' : '#9ca3af';

const dotIcon = (site, idx) => L.divIcon({
    className: 'custom-site-pin',
    html: `<div style="display:flex;flex-direction:column;align-items:center;">
        <div style="background:${siteColor(site)};color:#fff;font-size:10px;font-weight:900;border-radius:9999px;min-width:26px;height:26px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 4px 10px rgba(0,0,0,.35);padding:0 6px;">${idx + 1}</div>
        <div style="width:2px;height:10px;background:${siteColor(site)};"></div>
    </div>`,
    iconSize: [30, 40],
    iconAnchor: [15, 40],
});

const renderSites = () => {
    if (!map) return;
    // Clear old site layers
    siteLayers.forEach(({ marker, circle }) => { marker.remove(); circle.remove(); });
    siteLayers.clear();

    sitesList.value.forEach((site, idx) => {
        const lat = Number(site.latitude);
        const lng = Number(site.longitude);
        if (Number.isNaN(lat) || Number.isNaN(lng)) return;
        const color = siteColor(site);
        const marker = L.marker([lat, lng], { icon: dotIcon(site, idx) }).addTo(map);
        marker.bindTooltip(`<b>${site.label || site.place_name || 'Site ' + (idx + 1)}</b><br>${lat.toFixed(5)}, ${lng.toFixed(5)} · ${site.range_radius}m${(site.is_active ?? true) ? '' : '<br><i>Inactive</i>'}`, { direction: 'top', offset: [0, -38] });
        marker.on('click', () => { selectedSiteId.value = site.id; });
        const circle = L.circle([lat, lng], {
            color, fillColor: color, fillOpacity: (site.is_active ?? true) ? 0.15 : 0.06,
            weight: (site.is_active ?? true) ? 2 : 1,
            dashArray: (site.is_active ?? true) ? null : '6 6',
            radius: Number(site.range_radius) || 500,
        }).addTo(map);
        siteLayers.set(site.id, { marker, circle });
    });
};

const refreshDraftPin = () => {
    if (!map) return;
    if (!draftMarker) {
        draftMarker = L.marker([coords.value.latitude, coords.value.longitude], { draggable: true, opacity: 0.9 }).addTo(map);
        draftMarker.bindTooltip('New site pin — drag me or edit coordinates', { direction: 'top', offset: [0, -12] });
        draftMarker.on('dragend', () => {
            const p = draftMarker.getLatLng();
            coords.value.latitude = Number(p.lat.toFixed(6));
            coords.value.longitude = Number(p.lng.toFixed(6));
        });
        draftCircle = L.circle([coords.value.latitude, coords.value.longitude], {
            color: '#10b981', fillColor: '#10b981', fillOpacity: 0.12, dashArray: '4 4', radius: rangeRadius.value
        }).addTo(map);
    } else {
        draftMarker.setLatLng([coords.value.latitude, coords.value.longitude]);
        draftCircle.setLatLng([coords.value.latitude, coords.value.longitude]);
        draftCircle.setRadius(Number(rangeRadius.value) || 500);
    }
};

const initMap = () => {
    const streetView = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });
    const satelliteView = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 20 });

    map = L.map('map', {
        center: [coords.value.latitude, coords.value.longitude],
        zoom: 13,
        layers: [streetView]
    });

    L.control.layers({ "Default": streetView, "Satellite": satelliteView }).addTo(map);

    renderSites();
    refreshDraftPin();

    // Fit all active sites into view when there are several
    const actives = sitesList.value.filter(s => s.is_active ?? true);
    if (actives.length > 1) {
        const bounds = L.latLngBounds(actives.map(s => [Number(s.latitude), Number(s.longitude)]));
        if (bounds.isValid()) map.fitBounds(bounds.pad(0.25));
    }
};

const focusSite = (site) => {
    selectedSiteId.value = site.id;
    if (!map) return;
    const pos = [Number(site.latitude), Number(site.longitude)];
    map.flyTo(pos, Math.max(map.getZoom(), 16), { duration: 0.8 });
    const layers = siteLayers.get(site.id);
    if (layers) layers.marker.openTooltip();
};

const useAsDraft = (site) => {
    coords.value.latitude = Number(site.latitude);
    coords.value.longitude = Number(site.longitude);
    rangeRadius.value = Number(site.range_radius) || 500;
    siteName.value = site.label || '';
    refreshDraftPin();
    if (map) map.flyTo([coords.value.latitude, coords.value.longitude], Math.max(map.getZoom(), 16), { duration: 0.8 });
};

watch([() => coords.value.latitude, () => coords.value.longitude, rangeRadius], refreshDraftPin);
watch(sitesList, renderSites, { deep: true });

onMounted(async () => {
    initMap();
    // Backfill place names for old archives saved before place_name existed
    // (Nominatim policy: max 1 req/sec — capped to avoid hammering the API)
    let resolved = 0;
    for (const log of sitesList.value) {
        if (resolved >= 15) break;
        if (!log.place_name && log.id && !resolvedNames.value[log.id]) {
            const name = await reverseGeocode(log.latitude, log.longitude);
            if (name) resolvedNames.value[log.id] = name;
            resolved++;
            await new Promise(r => setTimeout(r, 1100));
        }
    }
});

const saveToDatabase = async () => {
    showArchiveModal.value = false;
    isSaving.value = true;
    errorMsg.value = null;
    statusMsg.value = null;

    const payload = {
        latitude: coords.value.latitude,
        longitude: coords.value.longitude,
        range_radius: parseInt(rangeRadius.value),
        label: siteName.value.trim(),
        place_name: placeName.value,
        is_active: true,
    };

    try {
        const response = await axios.post(route('it.location.store'), payload);
        statusMsg.value = response.data.message;

        lastSavedInfo.value = {
            lat: Number(payload.latitude),
            lng: Number(payload.longitude),
            time: new Date().toLocaleTimeString()
        };

        if (response.data?.data) {
            sitesList.value.unshift(response.data.data);
            renderSites();
        }
        siteName.value = '';

        Swal.fire({
            title: 'Site saved!',
            html: `<b>${payload.label}</b><br>(${Number(payload.latitude).toFixed(6)}, ${Number(payload.longitude).toFixed(6)}) with ${payload.range_radius}m radius is now an active work location.`,
            icon: 'success',
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'Great',
            background: '#fff',
            customClass: {
                popup: 'rounded-[2rem] shadow-2xl',
                title: 'font-black tracking-tight text-slate-900',
                confirmButton: 'rounded-xl px-6 py-2.5 font-bold',
            }
        });
    } catch (err) {
        errorMsg.value = err.response?.data?.message || err.response?.data?.errors?.label?.[0] || "Failed to save to database.";
        Swal.fire({
            title: 'Save Failed',
            text: errorMsg.value,
            icon: 'error',
            confirmButtonColor: '#e11d48',
            confirmButtonText: 'Retry',
            background: '#fff',
            customClass: {
                popup: 'rounded-[2rem] shadow-2xl',
                title: 'font-black tracking-tight text-slate-900',
                confirmButton: 'rounded-xl px-6 py-2.5 font-bold',
            }
        });
    } finally {
        isSaving.value = false;
    }
};

// ── Edit / toggle / delete ──
const openEdit = (site) => {
    editingSite.value = site;
    editForm.value = {
        label: site.label || '',
        latitude: Number(site.latitude),
        longitude: Number(site.longitude),
        range_radius: Number(site.range_radius),
        place_name: site.place_name || '',
    };
    showEditModal.value = true;
};

const saveEdit = async () => {
    if (!editingSite.value) return;
    if (!editForm.value.label.trim()) {
        Swal.fire({ title: 'Name required', text: 'Each site needs a name.', icon: 'warning', confirmButtonColor: '#4f46e5' });
        return;
    }
    isUpdating.value = true;
    try {
        const res = await axios.put(route('it.location.update', editingSite.value.id), {
            label: editForm.value.label.trim(),
            latitude: editForm.value.latitude,
            longitude: editForm.value.longitude,
            range_radius: parseInt(editForm.value.range_radius),
            place_name: editForm.value.place_name || null,
        });
        const idx = sitesList.value.findIndex(s => s.id === editingSite.value.id);
        if (idx !== -1) sitesList.value[idx] = res.data.data;
        renderSites();
        showEditModal.value = false;
        statusMsg.value = res.data.message;
    } catch (err) {
        Swal.fire({ title: 'Update failed', text: err.response?.data?.message || 'Could not update site.', icon: 'error', confirmButtonColor: '#e11d48' });
    } finally {
        isUpdating.value = false;
    }
};

const toggleSite = async (site) => {
    try {
        const res = await axios.post(route('it.location.toggle', site.id));
        const idx = sitesList.value.findIndex(s => s.id === site.id);
        if (idx !== -1) sitesList.value[idx] = res.data.data;
        renderSites();
        statusMsg.value = res.data.message;
    } catch (err) {
        Swal.fire({ title: 'Toggle failed', text: err.response?.data?.message || 'Could not change site status.', icon: 'error', confirmButtonColor: '#e11d48' });
    }
};

const deleteSite = async (site) => {
    const confirm = await Swal.fire({
        title: `Remove "${site.label || 'this site'}"?`,
        text: 'Staff will no longer be able to clock in from this location.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, remove',
    });
    if (!confirm.isConfirmed) return;
    try {
        const res = await axios.delete(route('it.location.destroy', site.id));
        sitesList.value = sitesList.value.filter(s => s.id !== site.id);
        renderSites();
        statusMsg.value = res.data.message;
    } catch (err) {
        Swal.fire({ title: 'Delete failed', text: err.response?.data?.message || 'Could not remove site.', icon: 'error', confirmButtonColor: '#e11d48' });
    }
};

const syncGps = () => {
    isSyncing.value = true;
    navigator.geolocation.getCurrentPosition((pos) => {
        coords.value.latitude = pos.coords.latitude;
        coords.value.longitude = pos.coords.longitude;
        coords.value.accuracy = pos.coords.accuracy ? pos.coords.accuracy.toFixed(2) : 'N/A';
        refreshDraftPin();
        map.setZoom(18);
        map.panTo([coords.value.latitude, coords.value.longitude]);
        isSyncing.value = false;
    }, () => {
        errorMsg.value = "GPS Signal Denied.";
        isSyncing.value = false;
    }, { enableHighAccuracy: true });
};
</script>

<template>
    <Head title="Strategic Geolocation" />
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
                            <MapPin class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Globe class="h-3.5 w-3.5" /> IT · Strategic Geolocation
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Company Locations</h1>
                            <p class="text-sm text-blue-100/90">Pin every MontiTextile site — HQ, warehouses, second branch — each with its own clock-in radius.</p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                <Building2 class="h-3.5 w-3.5" /> {{ activeCount }} active site{{ activeCount !== 1 ? 's' : '' }}
                            </span>
                            <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ rangeRadius }}m radius
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">

                    <!-- Controls -->
                    <div class="animate-fade-up lg:col-span-1" style="animation-delay: 80ms">
                        <div class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur p-6 sm:p-8 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="relative flex items-center gap-3 mb-6">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg">
                                    <Crosshair class="w-5 h-5" />
                                </span>
                                <div>
                                    <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white leading-tight">Add a site</h2>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Warehouse / branch</p>
                                </div>
                            </div>

                            <div class="relative space-y-4 mb-6">
                                <div>
                                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-1.5">Site name *</label>
                                    <input v-model="siteName" type="text" placeholder="e.g. Main Warehouse, Second Branch" class="w-full px-4 py-3 bg-amber-50/60 dark:bg-zinc-800/80 border border-amber-200 dark:border-zinc-700/60 rounded-2xl shadow-inner text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/50 transition placeholder:text-gray-400 placeholder:font-medium" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-1.5">Latitude</label>
                                    <input v-model.number="coords.latitude" type="number" step="any" class="w-full px-4 py-3 bg-slate-50 dark:bg-zinc-800/80 border border-gray-100 dark:border-zinc-700/60 rounded-2xl shadow-inner text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/50 transition" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-1.5">Longitude</label>
                                    <input v-model.number="coords.longitude" type="number" step="any" class="w-full px-4 py-3 bg-slate-50 dark:bg-zinc-800/80 border border-gray-100 dark:border-zinc-700/60 rounded-2xl shadow-inner text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/50 transition" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-1.5">Range Radius (M)</label>
                                    <input v-model.number="rangeRadius" type="number" class="w-full px-4 py-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800/50 rounded-2xl shadow-inner text-sm font-black text-indigo-600 dark:text-indigo-300 outline-none focus:ring-2 focus:ring-indigo-500/50 transition" />
                                </div>
                                <p class="text-[11px] text-gray-400 font-medium leading-relaxed">Drag the green pin on the map or type coordinates. Staff can clock in from <b>any</b> active site.</p>
                            </div>

                            <Transition name="modal">
                                <div v-if="errorMsg" class="relative mb-4 p-4 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-300 rounded-2xl text-xs font-bold border border-red-100 dark:border-red-500/20 flex items-center gap-2">
                                    <AlertCircle class="w-4 h-4 shrink-0" /> {{ errorMsg }}
                                </div>
                            </Transition>
                            <Transition name="modal">
                                <div v-if="statusMsg" class="relative mb-4 p-4 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-300 rounded-2xl text-xs font-bold border border-emerald-100 dark:border-emerald-500/20 flex items-center gap-2">
                                    <CheckCircle2 class="w-4 h-4 shrink-0" /> {{ statusMsg }}
                                </div>
                            </Transition>

                            <div class="relative flex flex-col gap-3">
                                <button @click="syncGps" :disabled="isSyncing" class="w-full py-3.5 bg-slate-900 text-white rounded-2xl shadow-lg flex items-center justify-center gap-2 text-[11px] font-black uppercase tracking-widest hover:bg-black transition-all disabled:opacity-50">
                                    <Loader2 v-if="isSyncing" class="w-4 h-4 animate-spin" />
                                    <Navigation v-else class="w-4 h-4" /> Use my GPS
                                </button>

                                <button @click="openArchiveConfirm" :disabled="isSaving" class="w-full py-4 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white rounded-2xl shadow-xl shadow-indigo-500/20 flex items-center justify-center gap-2 text-xs font-black uppercase tracking-widest hover:shadow-2xl hover:-translate-y-0.5 active:scale-95 transition-all disabled:opacity-50">
                                    <Loader2 v-if="isSaving" class="w-4 h-4 animate-spin" />
                                    <Save v-else class="w-4 h-4" /> Save this site
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Map -->
                    <div class="animate-fade-up lg:col-span-3" style="animation-delay: 140ms">
                        <div class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur p-2 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 h-[420px] sm:h-[560px] lg:h-[720px] overflow-hidden">
                            <div id="map" class="w-full h-full rounded-[1.4rem] z-0"></div>

                            <div class="absolute top-6 left-6 z-[1000] pointer-events-none">
                                <div class="bg-slate-900/80 backdrop-blur-md text-white px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2 ring-1 ring-white/20">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    {{ activeCount }} active site{{ activeCount !== 1 ? 's' : '' }} · green pin = new site
                                </div>
                            </div>

                            <div class="absolute bottom-6 left-6 right-6 z-[1000]">
                                <div class="bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl border border-gray-100 dark:border-zinc-800 rounded-3xl p-4 shadow-2xl flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex flex-wrap items-center gap-6">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20">
                                                <Database class="w-4 h-4 text-blue-500" />
                                            </span>
                                            <div>
                                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Database Status</p>
                                                <p class="text-xs font-black text-slate-900 dark:text-white">Active / Multi-site</p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 border-l border-gray-100 dark:border-zinc-800 pl-6">
                                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20">
                                                <Activity class="w-4 h-4 text-emerald-500" />
                                            </span>
                                            <div v-if="typeof lastSavedInfo.lat === 'number'">
                                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Last Saved Coordinates</p>
                                                <p class="text-xs font-black text-slate-900 dark:text-white">
                                                    {{ lastSavedInfo.lat.toFixed(4) }}, {{ lastSavedInfo.lng.toFixed(4) }}
                                                </p>
                                            </div>
                                            <div v-else>
                                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Last Saved Coordinates</p>
                                                <p class="text-xs font-bold text-gray-400 italic">No Archive Data</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 bg-slate-50 dark:bg-zinc-800/60 px-4 py-2 rounded-2xl border border-gray-100 dark:border-zinc-700/60">
                                        <Clock class="w-3.5 h-3.5 text-indigo-400" />
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-300">
                                            Sync: {{ lastSavedInfo.time ? lastSavedInfo.time : 'Waiting' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Company sites -->
                <div class="animate-fade-up" style="animation-delay: 200ms">
                    <div class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex flex-wrap items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg">
                                <Warehouse class="w-5 h-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-base font-black tracking-tight text-slate-900 dark:text-white leading-tight">Company Sites</h2>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">HQ · warehouses · branches — clock-in works at any active site</p>
                            </div>
                            <span class="rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 px-3 py-1.5 text-xs font-black">{{ activeCount }} active</span>
                            <span class="rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-3 py-1.5 text-xs font-black">{{ sitesList.length }} total</span>
                        </div>
                        <div v-if="sitesList.length === 0" class="relative p-12 text-center">
                            <Archive class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700 animate-bounce-soft" />
                            <p class="mt-3 text-sm font-black text-gray-500 dark:text-gray-400">No company sites yet</p>
                            <p class="text-xs text-gray-400 mt-1">Name a site above (e.g. Main Warehouse) and click Save this site — it will appear here and on the map.</p>
                        </div>
                        <TransitionGroup v-else name="card" tag="div" class="relative divide-y divide-gray-100 dark:divide-zinc-800">
                            <div v-for="(log, i) in sitesList" :key="log.id ?? i" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                :class="['flex flex-col xl:flex-row xl:items-center gap-3 p-4 sm:px-6 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors', selectedSiteId === log.id ? 'bg-indigo-50/70 dark:bg-indigo-900/20' : '', !(log.is_active ?? true) ? 'opacity-70' : '']">
                                <span :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-white shadow-md text-xs font-black', (log.is_active ?? true) ? 'bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700' : 'bg-gray-400']">{{ String(i + 1).padStart(2, '0') }}</span>
                                <div class="min-w-0 flex-1 cursor-pointer" @click="focusSite(log)" title="Click to locate on map">
                                    <p class="text-sm font-black text-gray-900 dark:text-white truncate flex items-center gap-1.5">
                                        <MapPin class="h-3.5 w-3.5 shrink-0 text-indigo-500" /> {{ log.label || log.place_name || resolvedNames[log.id] || 'Unnamed site' }}
                                        <span v-if="!(log.is_active ?? true)" class="rounded-full bg-gray-200 dark:bg-zinc-700 text-gray-500 dark:text-gray-300 px-2 py-0.5 text-[10px] font-black uppercase">Inactive</span>
                                        <span v-else class="rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-[10px] font-black uppercase">Active</span>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ log.place_name || resolvedNames[log.id] || 'Resolving address…' }}</p>
                                    <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ Number(log.latitude).toFixed(6) }}, {{ Number(log.longitude).toFixed(6) }}</p>
                                </div>
                                <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-2.5 py-1 text-[11px] font-black">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ log.range_radius }}m
                                </span>
                                <span class="text-[11px] font-bold text-gray-400 whitespace-nowrap">{{ log.created_at ? new Date(log.created_at).toLocaleString() : '' }}</span>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button @click="focusSite(log)" title="Locate on map" class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 hover:bg-blue-100 active:scale-95 transition"><LocateFixed class="h-4 w-4" /></button>
                                    <button @click="useAsDraft(log)" title="Load into editor" class="flex h-8 px-2.5 items-center gap-1 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 text-[11px] font-black hover:bg-slate-200 active:scale-95 transition">Edit pin</button>
                                    <button @click="openEdit(log)" title="Rename / adjust" class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-600 hover:bg-amber-100 active:scale-95 transition"><Pencil class="h-4 w-4" /></button>
                                    <button @click="toggleSite(log)" :title="(log.is_active ?? true) ? 'Deactivate site' : 'Activate site'" :class="['flex h-8 w-8 items-center justify-center rounded-xl active:scale-95 transition', (log.is_active ?? true) ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 hover:bg-emerald-100' : 'bg-gray-100 dark:bg-zinc-800 text-gray-400 hover:bg-gray-200']"><Power class="h-4 w-4" /></button>
                                    <button @click="deleteSite(log)" title="Remove site" class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-100 active:scale-95 transition"><Trash2 class="h-4 w-4" /></button>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save confirmation modal -->
        <Transition name="modal">
            <div v-if="showArchiveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showArchiveModal = false">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 p-5 flex items-center gap-3 relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                        <div class="relative flex h-10 w-10 items-center justify-center rounded-2xl bg-white/20 ring-1 ring-white/30">
                            <Archive class="w-5 h-5 text-white" />
                        </div>
                        <div class="relative min-w-0 flex-1">
                            <h3 class="text-white font-black">Confirm new site</h3>
                            <p class="text-xs text-blue-100">This becomes an active work location</p>
                        </div>
                        <button @click="showArchiveModal = false" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 p-4 space-y-2 text-sm">
                            <p class="flex justify-between gap-3"><span class="text-gray-500 font-bold">Site</span><span class="font-black text-gray-900 dark:text-white text-right">{{ siteName }}</span></p>
                            <p class="flex justify-between gap-3"><span class="text-gray-500 font-bold">Latitude</span><span class="font-mono font-black text-gray-900 dark:text-white">{{ Number(coords.latitude).toFixed(6) }}</span></p>
                            <p class="flex justify-between gap-3"><span class="text-gray-500 font-bold">Longitude</span><span class="font-mono font-black text-gray-900 dark:text-white">{{ Number(coords.longitude).toFixed(6) }}</span></p>
                            <p class="flex justify-between gap-3"><span class="text-gray-500 font-bold">Radius</span><span class="font-black text-indigo-600 dark:text-indigo-300">{{ rangeRadius }} meters</span></p>
                            <div class="flex justify-between gap-3 pt-2 border-t border-indigo-100 dark:border-indigo-800">
                                <span class="text-gray-500 font-bold flex items-center gap-1.5 shrink-0"><MapPin class="h-3.5 w-3.5" /> Place</span>
                                <span v-if="resolvingPlace" class="text-xs font-bold text-gray-400 flex items-center gap-1.5"><Loader2 class="h-3.5 w-3.5 animate-spin" /> Locating…</span>
                                <span v-else class="text-xs font-black text-gray-900 dark:text-white text-right">{{ placeName || 'Unknown location' }}</span>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button @click="showArchiveModal = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                            <button @click="saveToDatabase" :disabled="isSaving" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white font-black text-sm shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-60">
                                <Loader2 v-if="isSaving" class="w-4 h-4 animate-spin" />
                                <Save v-else class="w-4 h-4" /> {{ isSaving ? 'Saving…' : 'Confirm' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Edit site modal -->
        <Transition name="modal">
            <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showEditModal = false">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                    <div class="bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500 p-5 flex items-center gap-3 relative overflow-hidden">
                        <div class="relative flex h-10 w-10 items-center justify-center rounded-2xl bg-white/20 ring-1 ring-white/30">
                            <Pencil class="w-5 h-5 text-white" />
                        </div>
                        <div class="relative min-w-0 flex-1">
                            <h3 class="text-white font-black">Edit site</h3>
                            <p class="text-xs text-white/80">Rename, move or resize the geofence</p>
                        </div>
                        <button @click="showEditModal = false" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 text-white hover:bg-white/35 hover:rotate-90 transition-all">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-1.5">Site name *</label>
                            <input v-model="editForm.label" type="text" class="w-full px-4 py-3 bg-slate-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-orange-500/50" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-1.5">Latitude</label>
                                <input v-model.number="editForm.latitude" type="number" step="any" class="w-full px-4 py-3 bg-slate-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-orange-500/50" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-1.5">Longitude</label>
                                <input v-model.number="editForm.longitude" type="number" step="any" class="w-full px-4 py-3 bg-slate-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-orange-500/50" />
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-1.5">Range radius (m)</label>
                            <input v-model.number="editForm.range_radius" type="number" class="w-full px-4 py-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-2xl text-sm font-black text-orange-600 outline-none focus:ring-2 focus:ring-orange-500/50" />
                        </div>
                        <div class="flex gap-3">
                            <button @click="showEditModal = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                            <button @click="saveEdit" :disabled="isUpdating" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500 text-white font-black text-sm shadow-lg hover:shadow-xl active:scale-95 transition-all disabled:opacity-60">
                                <Loader2 v-if="isUpdating" class="w-4 h-4 animate-spin" /> {{ isUpdating ? 'Saving…' : 'Save changes' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<style scoped>
#map { background: #0f172a; }
:deep(.leaflet-control-layers) { border-radius: 1.5rem !important; border: none !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); padding: 8px; font-weight: 900; font-size: 9px; text-transform: uppercase; }
:deep(.custom-site-pin) { background: transparent; border: none; }

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
.modal-enter-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from { opacity: 0; transform: translateY(14px) scale(0.98); }
.modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.modal-leave-to { opacity: 0; transform: scale(0.97); }
</style>
