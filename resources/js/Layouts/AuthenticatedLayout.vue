<script setup>
import Sidebar from './Sidebar.vue'
import MobileSidebar from './MobileSidebar.vue'
import TopBar from './Navbar/TopBar.vue'
import CallCenterOverlay from '@/Components/crm/messaging/CallCenterOverlay.vue'
import PartnerCallOverlay from '@/Components/crm/messaging/PartnerCallOverlay.vue'
import ForcePasswordChangeModal from '@/Components/Auth/ForcePasswordChangeModal.vue'
import { usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch, onErrorCaptured } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useSidebarToggle } from './composables/useSidebarToggle'

const page = usePage()
const { sidebarCollapsed } = useSidebarToggle()
const user = computed(() => page.props.auth.user)
const isLocating = ref(true)
const locationReady = ref(false)

// ─── ERROR HANDLING ────────────────────────────────────────────────────────
const hasError = ref(false)
const errorMessage = ref('')

onErrorCaptured((err, instance, info) => {
    console.error('Layout error captured:', err, info)
    errorMessage.value = err.message || 'An unexpected error occurred.'
    hasError.value = true
    // Prevent the error from propagating further
    return false
})

// ─── LOCATION TRACKING ────────────────────────────────────────────────────
// Monitor for security flash messages from the backend
watch(() => page.props.flash?.geofence_error, (message) => {
    if (message) {
        Swal.fire({
            title: 'Security Violation',
            text: message,
            icon: 'error',
            confirmButtonText: 'Acknowledge',
            confirmButtonColor: '#2563eb',
            allowOutsideClick: false,
            customClass: { popup: 'rounded-[2rem]' }
        });
    }
}, { immediate: true })

onMounted(() => {
    if (navigator.geolocation) {
        const options = {
            enableHighAccuracy: false, 
            maximumAge: 0 
        };

        const success = (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            isLocating.value = false;
            locationReady.value = true;

            axios.defaults.headers.common['X-User-Lat'] = lat;
            axios.defaults.headers.common['X-User-Lng'] = lng;
            page.props.auth.location = { lat, lng };
            console.log(`📡 GPS Synced: ${lat}, ${lng}`);
        };

        const error = (err) => {
            isLocating.value = false;
            console.warn("GPS Warning: Retrying in standard mode...");
            navigator.geolocation.getCurrentPosition(success, null, { enableHighAccuracy: false });
        };

        navigator.geolocation.watchPosition(success, error, options);
    }
});

// ─── RELOAD FUNCTION ──────────────────────────────────────────────────────
const reloadPage = () => {
    hasError.value = false
    window.location.reload()
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-zinc-950">
        <!-- Error Overlay -->
        <div v-if="hasError" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl p-8 max-w-md w-full text-center border border-gray-200 dark:border-gray-800">
                <div class="w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Something went wrong</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">{{ errorMessage }}</p>
                <div class="flex gap-3 justify-center">
                    <button @click="reloadPage" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition shadow-lg shadow-blue-500/20">
                        Reload Page
                    </button>
                    <button @click="hasError = false" class="px-6 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        Dismiss
                    </button>
                </div>
            </div>
        </div>

        <!-- Normal Layout -->
        <Sidebar v-if="!hasError" />
        <MobileSidebar v-if="!hasError" />
        <div :class="sidebarCollapsed ? 'md:pl-0' : 'md:pl-64'"
            class="pt-16 md:pt-0 flex flex-col flex-1 min-h-screen transition-all duration-300">
            <TopBar v-if="!hasError" />
            <main>
                <div>
                    <!-- Location status – always visible -->
                    <div class="sticky top-36 z-10 mb-4 flex items-center gap-4 md:top-20">
                        <div v-if="locationReady" class="text-[10px] text-emerald-500 font-bold uppercase tracking-widest bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100 flex items-center gap-2">
                            <span class="size-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            Secure Zone Verified
                        </div>
                        <div v-else role="status" class="text-[10px] text-blue-500 font-bold uppercase tracking-widest bg-blue-50 px-3 py-1 rounded-full animate-pulse border border-blue-100">
                            Searching Perimeter...
                        </div>
                    </div>

                    <!-- Page Content - with fallback background -->
                    <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-sm p-6 min-h-[400px]">
                        <slot />
                    </div>
                </div>
            </main>
        </div>

        <!-- Global incoming-call listener (visible on every dashboard page) -->
        <CallCenterOverlay v-if="!hasError" />
        <!-- Global partner-call listener (staff or portal, any page) -->
        <PartnerCallOverlay v-if="!hasError" />

        <!-- Forced password change for system-issued passwords (any page) -->
        <ForcePasswordChangeModal />
    </div>
</template>
