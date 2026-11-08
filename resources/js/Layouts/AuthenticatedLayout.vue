<script setup>
import Sidebar from './Sidebar.vue'
import MobileSidebar from './MobileSidebar.vue'
import TopBar from './Navbar/TopBar.vue'
import CallCenterOverlay from '@/Components/crm/messaging/CallCenterOverlay.vue'
import PartnerCallOverlay from '@/Components/crm/messaging/PartnerCallOverlay.vue'
import ForcePasswordChangeModal from '@/Components/Auth/ForcePasswordChangeModal.vue'
import { usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch, onErrorCaptured } from 'vue'
import axios from 'axios'
import { dialog } from '@/lib/feedback';
import { useSidebarToggle } from './composables/useSidebarToggle'

const page = usePage()
const { sidebarCollapsed } = useSidebarToggle()
const user = computed(() => page.props.auth.user)

// Every module gets a full-viewport workspace: the content area fills the
// screen below the top nav and scrolls internally instead of scrolling the
// document. CRM pages already follow this pattern, so they only need the
// shared padding removed (their own pages provide it).
const isCrmPage = computed(() => (page.url || '').startsWith('/dashboard/crm'))
// IT and Applicant portal pages render several sibling sections in the slot.
// Forcing every direct child to viewport height stretched each section, so let
// their sections follow content instead.
const isNoStretchPage = computed(() => {
    const url = page.url || ''
    return url.startsWith('/dashboard/it') || url.startsWith('/applicant')
})
const isLocating = ref(true)
const locationReady = ref(false)
let locationWatchId = null
let lastLocationSyncAt = 0
let lastSyncedCoordinates = null
let fallbackLocationRequested = false
let bannerObserver = null
let bannerScanTimer = null

const markThemedBanners = () => {
    const viewport = document.querySelector('.app-viewport')
    if (!viewport) return
    if (page.component === 'Dashboard/Inventory/Products') return

    viewport.querySelectorAll('h1').forEach((heading) => {
        if (heading.closest('.supplier-page-header')) return

        let node = heading.parentElement
        let fallback = null
        while (node && node !== viewport) {
            const classes = typeof node.className === 'string' ? node.className : ''
            const isGradientBanner = classes.includes('rounded-3xl') && classes.includes('bg-gradient')
            const isSemanticBanner = node.tagName === 'HEADER' || classes.includes('it-page-hero')
            if (isGradientBanner || isSemanticBanner) {
                node.setAttribute('data-erp-page-banner', isGradientBanner ? 'surface' : 'fallback')
                return
            }
            if (!fallback && classes.includes('mb-') && node.querySelectorAll('h1').length === 1 && node.textContent.length < 450) fallback = node
            node = node.parentElement
        }
        fallback?.setAttribute('data-erp-page-banner', 'fallback')
    })
}

onMounted(() => {
    const viewport = document.querySelector('.app-viewport')
    if (!viewport) return

    markThemedBanners()
    bannerObserver = new MutationObserver(() => {
        window.clearTimeout(bannerScanTimer)
        bannerScanTimer = window.setTimeout(markThemedBanners, 80)
    })
    bannerObserver.observe(viewport, { childList: true, subtree: true })
})

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
        dialog.fire({
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
            maximumAge: 30000,
            timeout: 20000,
        };

        const success = (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            isLocating.value = false;
            locationReady.value = true;
            fallbackLocationRequested = false;

            const now = Date.now();
            if (lastSyncedCoordinates) {
                const elapsed = now - lastLocationSyncAt;
                const latMeters = (lat - lastSyncedCoordinates.lat) * 111320;
                const lngMeters = (lng - lastSyncedCoordinates.lng) * 111320
                    * Math.cos(((lat + lastSyncedCoordinates.lat) / 2) * Math.PI / 180);
                const movedMeters = Math.hypot(latMeters, lngMeters);
                // Ignore noisy GPS callbacks. Refresh at least every 30s, or
                // sooner when movement is significant, without reactive churn.
                if (elapsed < 30000 && (movedMeters < 20 || elapsed < 5000)) return;
            }

            lastLocationSyncAt = now;
            lastSyncedCoordinates = { lat, lng };

            axios.defaults.headers.common['X-User-Lat'] = lat;
            axios.defaults.headers.common['X-User-Lng'] = lng;
            page.props.auth.location = { lat, lng };
            console.log(`📡 GPS Synced: ${lat}, ${lng}`);
        };

        const error = () => {
            isLocating.value = false;
            if (fallbackLocationRequested) return;
            fallbackLocationRequested = true;
            navigator.geolocation.getCurrentPosition(success, null, {
                enableHighAccuracy: false,
                maximumAge: 30000,
                timeout: 20000,
            });
        };

        locationWatchId = navigator.geolocation.watchPosition(success, error, options);
    }
});

onUnmounted(() => {
    if (locationWatchId !== null && navigator.geolocation) {
        navigator.geolocation.clearWatch(locationWatchId);
    }
    bannerObserver?.disconnect()
    if (bannerScanTimer) window.clearTimeout(bannerScanTimer)
});

// ─── RELOAD FUNCTION ──────────────────────────────────────────────────────
const reloadPage = () => {
    hasError.value = false
    window.location.reload()
}
</script>

<template>
    <div class="flex h-[100dvh] overflow-hidden bg-gray-50 dark:bg-zinc-950">
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
            class="pt-16 md:pt-0 flex min-h-0 min-w-0 flex-1 flex-col h-full overflow-hidden transition-all duration-300">
            <TopBar v-if="!hasError" />
            <main class="flex min-h-0 flex-1 flex-col overflow-hidden bg-gray-50 dark:bg-zinc-900">
                <div class="flex min-h-0 flex-1 flex-col">
                    <!-- Location status – fixed floating badge just below the
                         top nav, upper-left. Takes no layout space. -->
                    <div :class="sidebarCollapsed ? 'md:left-4' : 'md:left-64'"
                        class="pointer-events-none fixed top-[4.5rem] left-4 z-30 flex items-center gap-2">
                        <div v-if="locationReady" class="text-[10px] text-emerald-500 font-bold uppercase tracking-widest bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100 flex items-center gap-2 shadow-sm">
                            <span class="size-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            Secure Zone Verified
                        </div>
                        <div v-else class="text-[10px] text-blue-500 font-bold uppercase tracking-widest bg-blue-50 px-3 py-1 rounded-full animate-pulse border border-blue-100 shadow-sm">
                            Searching Perimeter...
                        </div>
                    </div>

                    <!-- Page content owns the full viewport and scrolls internally.
                         CRM pages already provide their own padding. -->
                    <div :class="[
                        'app-viewport min-h-0 w-full flex-1 overflow-y-auto overflow-x-hidden bg-slate-50 dark:bg-zinc-950',
                        isCrmPage ? 'pt-9' : 'px-3 pb-3 pt-9 sm:px-4 sm:pb-4 sm:pt-9 lg:px-5 lg:pb-5 lg:pt-9',
                        {
                            'hrm-natural-sections': (page.url || '').startsWith('/dashboard/hrm'),
                            'no-stretch-sections': isNoStretchPage,
                        },
                    ]">
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

<style>
/* Workspace sizing: every module page fills the viewport below the top nav.
   Page wrappers often clamp themselves with min-h-screen / max-w-* / mx-auto,
   so neutralise those at the outer two levels only. Only centered wrappers
   (mx-auto) are widened, and fixed/dialog elements (modals) are excluded so
   they keep their designed width and are never stretched. */
.app-viewport:not(.hrm-natural-sections) > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"]):not(.it-page-hero) {
    min-height: 100% !important;
}
/* IT and supplier pages render sibling sections in the layout slot. Let each
   section follow its content instead of assigning every sibling a viewport height. */
html[data-module="it"] .app-viewport > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"]),
html[data-module="supplier"] .app-viewport > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"]),
.app-viewport.hrm-natural-sections > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"]) {
    min-height: 0 !important;
}
.app-viewport:not(.hrm-natural-sections) > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"])[class*="mx-auto"],
.app-viewport:not(.hrm-natural-sections) > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"])
    > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"])[class*="mx-auto"] {
    max-width: none !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

/* IT and Applicant portal pages: sections follow their content instead of each
   being stretched to a full viewport. A page with a single root wrapper still
   fills the viewport. */
.app-viewport.no-stretch-sections > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"]):not(.it-page-hero) {
    min-height: 0 !important;
}
.app-viewport.no-stretch-sections > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"]):not(.it-page-hero):only-child {
    min-height: 100% !important;
}
</style>
