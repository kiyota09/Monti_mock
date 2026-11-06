<script setup>
import Sidebar from './Sidebar.vue'
import MobileSidebar from './MobileSidebar.vue'
import TopBar from './Navbar/TopBar.vue'
import ForcePasswordChangeModal from '@/Components/Auth/ForcePasswordChangeModal.vue'
import { usePage } from '@inertiajs/vue3'
import { computed, ref, onErrorCaptured } from 'vue'

const page = usePage()
const agencyUser = computed(() => page.props.auth.agency)

const hasError = ref(false)
const errorMessage = ref('')

onErrorCaptured((err) => {
    console.error('Agency layout error captured:', err)
    errorMessage.value = err.message || 'An unexpected error occurred.'
    hasError.value = true
    return false
})

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
        <div class="md:pl-64 pt-16 md:pt-0 flex flex-col flex-1 h-[100dvh] overflow-hidden transition-all duration-300">
            <TopBar v-if="!hasError" />
<<<<<<< HEAD
            <main class="flex-1 min-h-0 overflow-hidden bg-gray-50 dark:bg-zinc-900">
                <div class="h-full w-full">
                    <div class="app-viewport h-full w-full overflow-y-auto overflow-x-hidden p-3 sm:p-4 lg:p-5 bg-slate-50 dark:bg-zinc-950">
=======
            <main>
                <div>
                    <div class="agency-motion bg-slate-50 dark:bg-zinc-900 rounded-2xl p-4 sm:p-6 min-h-[400px]">
>>>>>>> 4a5038e28b6a55ea65129e3176825edb8849a073
                        <slot />
                    </div>
                </div>
            </main>
        </div>

        <!-- Forced password change for system-issued passwords (any page) -->
        <ForcePasswordChangeModal />
    </div>
</template>

<<<<<<< HEAD
<style>
/* Agency workspace: pages fill the viewport and scroll internally. Wrapper
   min-height / centered max-width clamps are neutralised at the outer two
   levels only, and modals are excluded so they keep their designed width. */
.app-viewport > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"]) {
    min-height: 100% !important;
}
.app-viewport > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"])[class*="mx-auto"],
.app-viewport > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"])
    > *:not(.fixed):not([role="dialog"]):not([aria-modal="true"]):not([class*="modal"])[class*="mx-auto"] {
    max-width: none !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
=======
<style scoped>
.agency-motion :deep(button),
.agency-motion :deep(a),
.agency-motion :deep(input),
.agency-motion :deep(select),
.agency-motion :deep(textarea),
.agency-motion :deep(tr) {
    transition-property: color, background-color, border-color, box-shadow, opacity, transform;
    transition-duration: 280ms;
    transition-timing-function: cubic-bezier(0.22, 1, 0.36, 1);
}

.agency-motion :deep(button:not(:disabled):hover) {
    transform: translateY(-1px);
}

.agency-motion :deep(button:not(:disabled):active) {
    transform: translateY(0) scale(0.985);
}

.agency-motion :deep(.fixed.inset-0) {
    animation: agency-overlay-in 320ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

.agency-motion :deep(.fixed.inset-0 > section),
.agency-motion :deep(.fixed.inset-0 > div) {
    animation: agency-panel-in 420ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes agency-overlay-in {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes agency-panel-in {
    from { opacity: 0; transform: translateY(10px) scale(0.99); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@media (prefers-reduced-motion: reduce) {
    .agency-motion :deep(*) {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
>>>>>>> 4a5038e28b6a55ea65129e3176825edb8849a073
}
</style>
