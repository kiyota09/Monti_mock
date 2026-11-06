<script setup>
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({ squeezerJobs: Object, ironJobs: Object })
</script>

<template>
    <Head title="Finishing Inspection History" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6"><header class="rounded-3xl bg-gradient-to-r from-cyan-700 to-blue-800 p-7 text-white"><p class="text-xs font-bold uppercase tracking-widest">Finishing Department</p><h1 class="mt-2 text-3xl font-black">Inspection History</h1><p class="mt-2 text-sm text-cyan-100">Squeezer and heatsetting job records with their current fabric status.</p></header>
            <section v-for="group in [{title:'Squeezer jobs',rows:squeezerJobs?.data,relation:'fabric'},{title:'Heatsetting jobs',rows:ironJobs?.data,relation:'squeezer_job.fabric'}]" :key="group.title" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="mb-3 text-lg font-black">{{ group.title }}</h2><p v-if="!group.rows?.length" class="text-sm text-slate-500">No jobs recorded.</p><div v-for="job in group.rows" :key="job.id" class="flex justify-between border-t py-3 text-sm"><span class="font-mono font-bold">{{ job.code }} · {{ group.relation === 'fabric' ? job.fabric?.code : job.squeezer_job?.fabric?.code }}</span><span>{{ group.relation === 'fabric' ? job.fabric?.status : job.squeezer_job?.fabric?.status }}</span></div></section>
            <p class="text-xs text-slate-500">The current workflow stores fabric gate outcomes as status changes, not as separate inspector decision events.</p>
        </main>
    </AuthenticatedLayout>
</template>
