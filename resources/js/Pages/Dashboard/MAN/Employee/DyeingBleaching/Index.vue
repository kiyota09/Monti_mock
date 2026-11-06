<script setup>
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ClipboardList, Droplets } from 'lucide-vue-next';

defineProps({ stats: Object, recentJobs: Array, efficiency: Object });
</script>

<template>
    <AuthenticatedLayout title="Dyeing Bleaching">
        <div class="mx-auto max-w-7xl space-y-6 p-6">
            <div class="rounded-3xl bg-gradient-to-br from-blue-700 to-violet-800 p-8 text-white shadow-xl">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4"><Droplets class="h-10 w-10"/><div><p class="text-xs font-bold uppercase tracking-widest text-blue-100">Dyeing Department</p><h1 class="text-3xl font-black">Bleaching</h1></div></div>
                    <Link :href="route('man.staff.dyeing-bleaching.page')" class="rounded-xl bg-white px-5 py-3 font-bold text-blue-800">Open work queue</Link>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-sm text-gray-500">Waiting for bleaching</p><p class="mt-2 text-3xl font-black">{{ stats?.pending ?? 0 }}</p></div>
                <div class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-sm text-gray-500">Bleached today</p><p class="mt-2 text-3xl font-black">{{ stats?.total_today ?? 0 }}</p></div>
                <Link :href="route('man.staff.dyeing-bleaching.history')" class="flex items-center gap-3 rounded-2xl border bg-white p-5 font-bold dark:border-zinc-800 dark:bg-zinc-900"><ClipboardList/> View work history</Link>
            </div>
            <section class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="mb-4 text-lg font-black">Recent bleaching work</h2><p v-if="!recentJobs?.length" class="text-sm text-gray-500">No bleaching work recorded yet.</p><div v-for="job in recentJobs" :key="job.id" class="flex justify-between border-t py-3 text-sm"><span class="font-mono font-bold">{{ job.code }} · {{ job.fabric?.code }}</span><span>{{ job.bleaching_method }}</span></div></section>
        </div>
    </AuthenticatedLayout>
</template>
