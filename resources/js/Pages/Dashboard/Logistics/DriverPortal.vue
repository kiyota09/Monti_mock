<template>
    <Head title="Driver Portal" />
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
                                <Sparkles class="h-3.5 w-3.5" /> Logistics · Driver Portal
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">My Dashboard</h1>
                            <p class="text-sm text-blue-100/90">View assigned trips, track routes, and manage your profile.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="activeDelivery" class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ formatStatus(activeDelivery.status) }}
                            </span>
                            <span v-else class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">No active trip</span>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="relative mt-6 flex gap-2">
                        <button @click="activeTab = 'trip'"
                            :class="activeTab === 'trip' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="rounded-2xl px-5 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            Active Trip
                        </button>
                        <button @click="activeTab = 'profile'"
                            :class="activeTab === 'profile' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="rounded-2xl px-5 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            My Profile
                        </button>
                    </div>
                </div>

                <!-- Active Trip Tab -->
                <div v-if="activeTab === 'trip'" class="space-y-6">
                    <!-- Map Card -->
                    <div v-if="activeDelivery" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-10 w-10 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                    <MapPin class="h-5 w-5 text-indigo-600" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Your Route</p>
                                    <p class="text-sm font-black text-gray-900 dark:text-white truncate">
                                        {{ activeDelivery.route?.name || 'Delivery Route' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span v-if="simulating" class="rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 px-3 py-1 text-[10px] font-black uppercase animate-pulse">Simulating...</span>
                                <span :class="statusBadge(activeDelivery.status)" class="px-3 py-1 rounded-full ring-1 text-[9px] font-black uppercase inline-flex items-center gap-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />
                                    {{ formatStatus(activeDelivery.status) }}
                                </span>
                            </div>
                        </div>
                        <div class="relative p-3">
                            <div id="driver-map" class="w-full rounded-3xl z-0" style="height: 400px;"></div>
                        </div>
                    </div>

                    <!-- No Active Delivery -->
                    <div v-else
                        class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Truck class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No active delivery assigned.</p>
                        <p class="text-xs text-gray-400 mt-1">You will see your trip here once dispatched.</p>
                    </div>

                    <!-- Delivery Details & Actions -->
                    <div v-if="activeDelivery" class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 transition-all duration-300 overflow-hidden" style="animation-delay:120ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4 text-white">
                            <div class="absolute -top-10 -right-10 h-28 w-28 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <p class="relative text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">Delivery #</p>
                            <p class="relative font-mono text-xl font-black">{{ activeDelivery.delivery_number }}</p>
                        </div>
                        <div class="relative p-6 space-y-4">
                            <!-- Trip Info -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex items-start gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-4">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex-shrink-0"><Users class="h-4 w-4 text-indigo-500" /></span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Driver</p>
                                        <p class="font-bold text-sm text-gray-900 dark:text-white">{{ activeDelivery.driver?.user?.name || '—' }}</p>
                                        <p class="text-xs text-gray-500">License: {{ activeDelivery.driver?.license_number }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-4">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex-shrink-0"><Navigation class="h-4 w-4 text-emerald-500" /></span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Route</p>
                                        <p class="font-bold text-sm text-gray-900 dark:text-white">{{ activeDelivery.route?.name || '—' }}</p>
                                        <p class="text-xs text-gray-500">{{ activeDelivery.route?.origin }} → {{ activeDelivery.route?.destination }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Packages Summary -->
                            <div class="p-4 bg-gray-50 dark:bg-zinc-800/50 rounded-2xl border border-gray-100 dark:border-zinc-800">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Packages on Board</p>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ activeDelivery.packages?.length || 0 }} package(s) · {{ totalQuantity(activeDelivery) }} pcs total</p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-wrap gap-3 pt-2">
                                <button v-if="activeDelivery.status === 'dispatched' && !simulating"
                                    @click="markInTransit(activeDelivery)"
                                    class="flex-1 py-3 bg-blue-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-700 hover:shadow-lg hover:-translate-y-0.5 active:scale-95 transition-all flex items-center justify-center gap-2">
                                    <Play class="h-4 w-4" /> Start Trip
                                </button>
                                <button v-if="activeDelivery.status === 'in_transit' && !simulating"
                                    @click="openProofModal(activeDelivery)"
                                    class="flex-1 py-3 bg-emerald-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 hover:shadow-lg hover:-translate-y-0.5 active:scale-95 transition-all flex items-center justify-center gap-2">
                                    <Camera class="h-4 w-4" /> Upload Proof &amp; Complete
                                </button>
                                <!-- Simulate Delivery Button -->
                                <button v-if="!simulating && activeDelivery.status !== 'delivered'"
                                    @click="startSimulation"
                                    :disabled="!routeGeometry || routeGeometry.length < 2"
                                    class="flex-1 py-3 bg-amber-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-amber-700 hover:shadow-lg hover:-translate-y-0.5 active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 flex items-center justify-center gap-2">
                                    <FlaskConical class="h-4 w-4" /> Simulate Delivery
                                </button>
                                <button v-if="simulating"
                                    disabled
                                    class="flex-1 py-3 bg-gray-400 text-white rounded-2xl text-[10px] font-black uppercase flex items-center justify-center gap-2">
                                    <Loader2 class="h-4 w-4 animate-spin" /> Simulating...
                                </button>
                            </div>
                            <p v-if="!routeGeometry || routeGeometry.length < 2" class="text-xs text-amber-600 text-center">
                                Route geometry not available – simulation disabled.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- My Profile Tab -->
                <div v-if="activeTab === 'profile'" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-xl transition-shadow duration-300 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <User class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Personal Information</h2>
                    </div>
                    <form @submit.prevent="submitProfile" class="p-6 max-w-2xl space-y-5">
                        <!-- Profile Photo -->
                        <div class="flex items-center gap-4">
                            <div class="relative">
                                <img v-if="userPhotoPreview" :src="userPhotoPreview" class="h-16 w-16 rounded-2xl object-cover ring-2 ring-indigo-200 shadow-lg" />
                                <div v-else class="h-16 w-16 rounded-2xl bg-gradient-to-br from-indigo-500 via-purple-600 to-fuchsia-600 flex items-center justify-center text-white text-xl font-black shadow-lg">
                                    {{ profileForm.name?.charAt(0) || user.name?.charAt(0) || '?' }}
                                </div>
                                <label class="absolute -bottom-1 -right-1 p-1.5 bg-white dark:bg-zinc-800 rounded-full shadow-lg ring-1 ring-gray-100 dark:ring-zinc-700 cursor-pointer hover:scale-110 transition-transform">
                                    <Camera class="h-3.5 w-3.5 text-gray-600 dark:text-gray-300" />
                                    <input type="file" @change="handlePhotoUpload" accept="image/*" class="hidden" />
                                </label>
                            </div>
                            <div class="text-xs text-gray-500">Click the camera icon to change photo</div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Full Name</label>
                            <input v-model="profileForm.name" type="text" required
                                class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Email</label>
                            <input v-model="profileForm.email" type="email" required
                                class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">New Password (optional)</label>
                            <input v-model="profileForm.password" type="password"
                                class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-1">Confirm Password</label>
                            <input v-model="profileForm.password_confirmation" type="password"
                                class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <button type="submit" :disabled="profileForm.processing"
                            class="px-6 py-3 bg-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-700 hover:shadow-lg active:scale-95 transition-all disabled:opacity-50 flex items-center gap-2">
                            <Loader2 v-if="profileForm.processing" class="h-4 w-4 animate-spin" />
                            Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Proof Upload Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showProofModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="closeProofModal">
                    <div class="modal-panel bg-white dark:bg-zinc-900 w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden">
                        <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 text-white flex justify-between items-center">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <h3 class="relative font-black text-lg">Complete Delivery</h3>
                            <button @click="closeProofModal" class="relative p-1.5 hover:bg-white/20 rounded-xl transition"><X class="h-5 w-5" /></button>
                        </div>
                        <form @submit.prevent="submitProof" class="p-6 space-y-5">
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Proof Photo *</label>
                                <input type="file" @change="handleProofImage" accept="image/*" required class="text-sm text-gray-500 dark:text-gray-400" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-wider mb-2">Notes (Optional)</label>
                                <textarea v-model="proofForm.notes" rows="3"
                                    class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500"
                                    placeholder="Any additional notes..."></textarea>
                            </div>
                            <button type="submit" :disabled="proofForm.processing"
                                class="w-full py-3 bg-emerald-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 hover:shadow-lg active:scale-95 transition-all flex items-center justify-center gap-2">
                                <Loader2 v-if="proofForm.processing" class="h-4 w-4 animate-spin" />
                                Submit &amp; Complete
                            </button>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-lg text-white font-bold text-sm"
                :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                {{ toast.message }}
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Truck, MapPin, Users, Navigation, Camera, Play, FlaskConical, X, Loader2, User, Sparkles } from 'lucide-vue-next';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    driver: { type: Object, default: () => ({}) },
    user: { type: Object, default: () => ({}) },
    montiLocation: { type: Object, default: null },
});

const activeTab = ref('trip');
const showProofModal = ref(false);
const proofImage = ref(null);
const toast = ref({ show: false, type: 'success', message: '' });
const userPhotoPreview = ref(props.user?.profile_photo_path ? `/storage/${props.user.profile_photo_path}` : null);

// Simulation state
const simulating = ref(false);
const truckMarker = ref(null);
const routeGeometry = ref(null);

// Active delivery (first one, as driver usually has one active trip)
const activeDelivery = computed(() => props.deliveries.length > 0 ? props.deliveries[0] : null);

// Profile form
const profileForm = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',
    profile_photo: null,
});

// Proof form
const proofForm = useForm({
    image: null,
    notes: '',
});

// Map instance
let map = null;
let routePolyline = null;
let markers = [];

// HQ coordinates (fallback)
const montiLat = props.montiLocation ? Number(props.montiLocation.latitude) : 14.3275;
const montiLng = props.montiLocation ? Number(props.montiLocation.longitude) : 120.9404;

const statusBadge = (status) => {
    const map = {
        dispatched: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        in_transit: 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        delivered: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    };
    return map[status] || 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
};

const formatStatus = (status) => {
    const map = { dispatched: 'Dispatched', in_transit: 'In Transit', delivered: 'Delivered' };
    return map[status] || status;
};

const totalQuantity = (delivery) => {
    return delivery.packages?.reduce((sum, p) => sum + (p.quantity || 0), 0) || 0;
};

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const handlePhotoUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        profileForm.profile_photo = file;
        userPhotoPreview.value = URL.createObjectURL(file);
    }
};

const submitProfile = () => {
    profileForm.post(route('logistics.driver.profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', 'Profile updated successfully.');
            profileForm.password = '';
            profileForm.password_confirmation = '';
        },
        onError: (errors) => {
            showToast('error', Object.values(errors)[0] || 'Update failed.');
        }
    });
};

const markInTransit = (delivery) => {
    router.post(route('logistics.driver.transit', delivery.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', 'Trip started.');
            // Reload to reflect status change
            router.reload({ only: ['deliveries'] });
        },
        onError: () => showToast('error', 'Failed to start trip.')
    });
};

const openProofModal = (delivery) => {
    proofForm.reset();
    proofImage.value = null;
    showProofModal.value = true;
};

const closeProofModal = () => {
    showProofModal.value = false;
};

const handleProofImage = (e) => {
    const file = e.target.files[0];
    if (file) {
        proofForm.image = file;
    }
};

const submitProof = () => {
    if (!proofForm.image) {
        showToast('error', 'Please select a proof image.');
        return;
    }
    proofForm.post(route('logistics.driver.proof', activeDelivery.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', 'Delivery completed.');
            closeProofModal();
            router.reload({ only: ['deliveries'] });
        },
        onError: (errors) => {
            showToast('error', Object.values(errors)[0] || 'Upload failed.');
        }
    });
};

// Map initialization
const initMap = () => {
    if (map) return;
    const el = document.getElementById('driver-map');
    if (!el) return;

    map = L.map('driver-map', { center: [montiLat, montiLng], zoom: 11 });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    // HQ marker
    L.marker([montiLat, montiLng], {
        icon: L.divIcon({
            html: `<div style="background:#3b82f6;color:white;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:3px solid white;font-size:14px;">🏭</div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15],
        })
    }).bindPopup('MontiTextiles HQ').addTo(map);

    drawRoute();
};

const drawRoute = () => {
    if (!map) return;

    // Clear previous layers
    markers.forEach(m => m.remove());
    markers = [];
    if (routePolyline) routePolyline.remove();
    if (truckMarker.value) truckMarker.value.remove();

    const delivery = activeDelivery.value;
    if (!delivery || !delivery.route) return;

    const route = delivery.route;

    // Parse geometry
    let geom = null;
    if (route.route_geometry) {
        if (typeof route.route_geometry === 'string') {
            try { geom = JSON.parse(route.route_geometry); } catch { geom = null; }
        } else if (Array.isArray(route.route_geometry)) {
            geom = route.route_geometry;
        }
    }
    routeGeometry.value = geom;

    // Draw polyline
    if (geom && geom.length > 1) {
        routePolyline = L.polyline(geom, { color: '#6366f1', weight: 5, opacity: 0.8 }).addTo(map);
        map.fitBounds(routePolyline.getBounds(), { padding: [50, 50] });
    } else if (route.origin_lat && route.destination_lat) {
        routePolyline = L.polyline(
            [
                [Number(route.origin_lat), Number(route.origin_lng)],
                [Number(route.destination_lat), Number(route.destination_lng)],
            ],
            { color: '#f59e0b', weight: 4, dashArray: '10, 6', opacity: 0.7 }
        ).addTo(map);
        map.fitBounds(routePolyline.getBounds(), { padding: [50, 50] });
    }

    // Destination marker
    if (route.destination_lat && route.destination_lng) {
        const destMarker = L.marker([Number(route.destination_lat), Number(route.destination_lng)], {
            icon: L.divIcon({
                html: `<div style="background:#10b981;color:white;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border:2px solid white;font-size:14px;">📍</div>`,
                iconSize: [28, 28],
                iconAnchor: [14, 28],
            })
        }).bindPopup(`<strong>Destination</strong><br>${route.destination}`).addTo(map);
        markers.push(destMarker);
    }

    // Initial truck marker at origin
    const truckLat = geom && geom.length > 0 ? geom[0][0] : (route.origin_lat || montiLat);
    const truckLng = geom && geom.length > 0 ? geom[0][1] : (route.origin_lng || montiLng);
    truckMarker.value = L.marker([Number(truckLat), Number(truckLng)], {
        icon: L.divIcon({
            html: `<div style="background:#f59e0b;color:white;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:3px solid white;font-size:16px;">🚚</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 16],
        })
    }).bindPopup(`<strong>${delivery.delivery_number}</strong><br>Driver: ${delivery.driver?.user?.name}`).addTo(map);
};

// Simulation animation
const startSimulation = () => {
    const geom = routeGeometry.value;
    if (!geom || geom.length < 2 || !truckMarker.value) {
        showToast('error', 'No route geometry available for simulation.');
        return;
    }

    simulating.value = true;
    const marker = truckMarker.value;
    const startTime = performance.now();
    const duration = 8000; // 8 seconds animation
    const totalPoints = geom.length;
    const totalDistance = geom.reduce((sum, point, i) => {
        if (i === 0) return 0;
        const prev = geom[i - 1];
        return sum + Math.sqrt((point[0] - prev[0]) ** 2 + (point[1] - prev[1]) ** 2);
    }, 0);

    let currentSegment = 0;
    let segmentProgress = 0;

    const animate = (now) => {
        const elapsed = now - startTime;
        const t = Math.min(elapsed / duration, 1);

        // Linear interpolation along the whole path
        const targetDistance = t * totalDistance;
        let distanceAccum = 0;
        let segStart = geom[0];
        let segEnd = geom[1];
        let segIndex = 0;

        for (let i = 0; i < totalPoints - 1; i++) {
            const p1 = geom[i];
            const p2 = geom[i + 1];
            const segDist = Math.sqrt((p2[0] - p1[0]) ** 2 + (p2[1] - p1[1]) ** 2);
            if (distanceAccum + segDist >= targetDistance) {
                segStart = p1;
                segEnd = p2;
                segIndex = i;
                break;
            }
            distanceAccum += segDist;
            if (i === totalPoints - 2) {
                // Past end – use last segment
                segStart = geom[totalPoints - 2];
                segEnd = geom[totalPoints - 1];
                segIndex = totalPoints - 2;
            }
        }

        const segDistance = Math.sqrt((segEnd[0] - segStart[0]) ** 2 + (segEnd[1] - segStart[1]) ** 2);
        const segT = segDistance > 0 ? (targetDistance - distanceAccum) / segDistance : 0;
        const lat = segStart[0] + (segEnd[0] - segStart[0]) * segT;
        const lng = segStart[1] + (segEnd[1] - segStart[1]) * segT;

        marker.setLatLng([lat, lng]);

        if (t < 1) {
            requestAnimationFrame(animate);
        } else {
            // Animation complete
            simulating.value = false;
            showToast('success', 'Simulation complete. You can now upload proof of delivery.');
            // Optionally open proof modal automatically
            openProofModal(activeDelivery.value);
        }
    };

    requestAnimationFrame(animate);
};

watch(activeDelivery, () => {
    nextTick(() => drawRoute());
}, { immediate: true });

onMounted(() => {
    nextTick(() => {
        if (activeTab.value === 'trip' && activeDelivery.value) {
            initMap();
        }
    });
});

watch(activeTab, (newTab) => {
    if (newTab === 'trip' && activeDelivery.value) {
        nextTick(() => initMap());
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
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-panel { opacity: 0; transform: translateY(24px) scale(0.97); }
.modal-leave-to .modal-panel { opacity: 0; transform: translateY(12px) scale(0.98); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
#driver-map { background: #eef2ff; }
</style>
