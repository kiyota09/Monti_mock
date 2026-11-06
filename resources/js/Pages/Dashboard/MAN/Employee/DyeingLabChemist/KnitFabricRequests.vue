<script setup>
import { router, Head, Link } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Search, Scissors } from 'lucide-vue-next'

const props = defineProps({
    requests: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')
let searchTimer
const applyFilters = () => router.get(route('man.staff.dyeing-lab-chemist.knit-fabric-requests'), {
    search: search.value || undefined,
    status: status.value || undefined,
}, { preserveState: true, preserveScroll: true, replace: true })
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 300)
})
watch(status, applyFilters)

const statusClass = (value) => ({
    requested: 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
    in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-500/10 dark:text-blue-300',
    completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300',
}[value] ?? 'bg-slate-100 text-slate-700')
</script>

<template>
    <Head title="Receive Fabric Sample Knit" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="flex flex-wrap items-center gap-4 rounded-3xl bg-gradient-to-r from-teal-700 to-cyan-800 p-7 text-white shadow-lg">
                <span class="rounded-2xl bg-white/15 p-3"><Scissors class="h-8 w-8" /></span>
                <div><p class="text-xs font-bold uppercase tracking-widest text-teal-100">Dyeing Lab Chemist</p><h1 class="mt-1 text-3xl font-black">Receive Fabric Sample Knit</h1><p class="mt-1 text-sm text-teal-100">Track every knit fabric sample request sent to Knitting Sample staff.</p></div>
            </header>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="metric in [{label:'All requests',value:stats.total},{label:'Requested',value:stats.requested},{label:'In progress',value:stats.in_progress},{label:'Completed',value:stats.completed}]" :key="metric.label" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-sm text-slate-500">{{ metric.label }}</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-white">{{ metric.value ?? 0 }}</p></article>
            </section>

            <section class="flex flex-col gap-3 rounded-2xl border bg-white p-4 sm:flex-row dark:border-zinc-800 dark:bg-zinc-900">
                <label class="relative flex-1"><Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"/><input v-model="search" type="search" placeholder="Search request, fabric, or Pantone" class="w-full rounded-xl border-slate-200 pl-10 dark:border-zinc-700 dark:bg-zinc-800" /></label>
                <select v-model="status" class="rounded-xl border-slate-200 sm:w-52 dark:border-zinc-700 dark:bg-zinc-800"><option value="">All statuses</option><option value="requested">Requested</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select>
            </section>

            <section class="overflow-hidden rounded-2xl border bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div v-if="!requests?.data?.length" class="p-10 text-center"><Scissors class="mx-auto h-8 w-8 text-slate-400"/><p class="mt-3 font-bold">No knit fabric requests found</p><p class="mt-1 text-sm text-slate-500">Requests created from the Shades page will appear here with status updates from Knitting Sample.</p></div>
                <div v-else class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-zinc-800"><tr><th class="p-4">Knit request</th><th class="p-4">Source sample</th><th class="p-4">Fabric details</th><th class="p-4">Yarn / Pantone</th><th class="p-4">Progress</th><th class="p-4">Knitting staff</th></tr></thead><tbody><tr v-for="item in requests.data" :key="item.id" class="border-t dark:border-zinc-800"><td class="p-4"><p class="font-mono font-bold">{{ item.code }}</p><p class="mt-1 text-xs text-slate-500">Requested {{ item.created_at }}</p></td><td class="p-4"><p class="font-mono font-semibold">{{ item.fabric_sample_request?.code ?? '—' }}</p><p class="mt-1 text-xs text-slate-500">{{ item.fabric_sample_request?.fabric_name ?? 'Fabric sample' }}</p></td><td class="p-4"><p>{{ item.fabric_design }}</p><p class="mt-1 text-xs text-slate-500">{{ item.texture ?? '—' }} · {{ item.gsm ? `${item.gsm} GSM` : 'GSM —' }} · {{ item.requested_weight }} kg requested</p></td><td class="p-4"><p>{{ item.yarn_type ?? 'Yarn not set' }}</p><p class="mt-1 flex items-center gap-2 text-xs text-slate-500"><span v-if="item.pantone_hex" class="h-3 w-3 rounded-full border" :style="{ backgroundColor: item.pantone_hex }"/>{{ item.pantone_name ?? 'No Pantone' }}</p></td><td class="p-4"><span :class="['rounded-full px-3 py-1 text-xs font-black uppercase', statusClass(item.status)]">{{ item.status?.replace('_', ' ') }}</span><p v-if="item.status === 'completed'" class="mt-2 text-xs text-slate-500">{{ item.produced_weight ?? '—' }} kg produced · {{ item.processed_at }}</p><p v-else-if="item.status === 'in_progress'" class="mt-2 text-xs text-slate-500">Started {{ item.updated_at }}</p></td><td class="p-4"><p class="font-medium">{{ item.operator?.name ?? 'Not assigned' }}</p><p class="mt-1 text-xs text-slate-500">{{ item.machine?.machine_no ? `Machine ${item.machine.machine_no}` : 'No machine recorded' }}</p><p v-if="item.remarks" class="mt-1 max-w-48 truncate text-xs text-slate-500" :title="item.remarks">{{ item.remarks }}</p></td></tr></tbody></table></div>
            </section>
            <nav v-if="requests?.links" class="flex flex-wrap gap-2"><Link v-for="link in requests.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['rounded-lg border px-3 py-2 text-sm', link.active ? 'bg-teal-700 text-white' : '']" /></nav>
        </main>
    </AuthenticatedLayout>
</template>
