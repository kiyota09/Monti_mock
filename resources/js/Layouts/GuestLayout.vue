<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { initTheme } from '@/composables/useTheme';

onMounted(() => {
    initTheme();
});
</script>

<template>
    <div class="relative min-h-screen flex flex-col bg-gray-50">
        <!-- Background with subtle overlay -->
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat"
            style="background-image: url('/images/threads.jpg');">
            <!-- Light overlay: enough contrast for readability, textile still shines through -->
            <div class="absolute inset-0 bg-black/30"></div>
        </div>

        <!-- Top navigation bar – clean & readable -->
        <nav class="relative z-30 px-6 py-5 flex items-center justify-between">
            <Link href="/" class="flex items-center gap-3 group">
                <div
                    class="size-10 sm:size-11 p-2.5 bg-white/90 backdrop-blur-sm rounded-xl shadow-md group-hover:scale-105 transition-transform duration-300">
                    <img src="/images/applogo.png" alt="Monti Logo" class="h-full w-full object-contain" />
                </div>
                <span class="font-black text-2xl tracking-tight text-white drop-shadow-md">
                    Monti<span class="text-blue-300">Textile</span>
                </span>
            </Link>
        </nav>

        <!-- Centered login form area -->
        <div class="relative z-20 flex-grow flex items-center justify-center px-5 py-10 sm:py-16">
            <div
                class="w-full max-w-md lg:max-w-lg bg-white/85 backdrop-blur-md rounded-3xl shadow-2xl border border-white/30 overflow-hidden">
                <!-- Inner padding – more generous for better UX -->
                <div class="p-8 sm:p-10 md:p-12">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>