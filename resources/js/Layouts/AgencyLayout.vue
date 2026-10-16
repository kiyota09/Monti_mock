<script setup>
import Sidebar from './Sidebar.vue'
import MobileSidebar from './MobileSidebar.vue'
import TopBar from './Navbar/TopBar.vue'
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
        <div class="md:pl-64 pt-16 md:pt-0 flex flex-col flex-1 min-h-screen transition-all duration-300">
            <TopBar v-if="!hasError" />
            <main class="py-8 pb-24 md:pb-8 flex-1 bg-gray-50 dark:bg-zinc-900">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-sm p-6 min-h-[400px]">
                        <slot />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
