<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Waves } from 'lucide-vue-next';

defineProps({ stationLabel: { type: String, required: true } });
</script>

<template>
    <Head :title="stationLabel" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-cyan-700 to-blue-700 p-6 text-white shadow-lg">
                <div class="flex items-center gap-4">
                    <span class="rounded-2xl bg-white/15 p-3"><Waves class="h-7 w-7" /></span>
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-cyan-100">Finishing Department</p>
                        <h1 class="text-2xl font-black">{{ stationLabel }}</h1>
                        <p class="mt-1 text-sm text-cyan-100">Finishing station workspace.</p>
                    </div>
                </div>
            </header>
        </div>
    </AuthenticatedLayout>
</template>
