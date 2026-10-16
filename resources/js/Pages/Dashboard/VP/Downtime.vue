<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Wrench, Sparkles, Clock, Gauge } from 'lucide-vue-next';

const props = defineProps({ openReports: Array, mttr: Array });

const ageBadge = (h) => h >= 48
    ? 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30'
    : h >= 24 ? 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'
    : 'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30';
</script>

<template>
    <Head title="Downtime Board" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Wrench class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> VP · Maintenance Oversight</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Downtime Board</h1>
                            <p class="text-sm text-blue-100/90">{{ openReports?.length ?? 0 }} open faults · read-only, Maintenance owns resolution</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5">
                    <h2 class="text-sm font-black flex items-center gap-2 mb-3"><Gauge class="h-4 w-4 text-indigo-500" /> Mean Time To Repair (last 200 resolved)</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
                        <div v-for="m in mttr" :key="m.type" class="rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 p-3">
                            <p class="text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ m.avg_hours }}h</p>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">{{ m.type }} · {{ m.count }} fixed</p>
                        </div>
                        <div v-if="!mttr?.length" class="col-span-full text-xs text-gray-400 py-2">No resolved reports to measure yet.</div>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2">
                        <Clock class="h-4 w-4 text-amber-500" /><h2 class="text-sm font-black">Open Faults by Age</h2>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Machine</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issue / Reporter</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Open For</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="r in openReports" :key="r.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                <td class="px-6 py-3 text-sm font-bold font-mono">{{ r.machine_no }} <span class="font-sans font-medium text-gray-400">({{ r.machine_type }})</span></td>
                                <td class="px-6 py-3 text-sm max-w-xs"><p class="truncate">{{ r.issue }}</p><p class="text-[11px] text-gray-400">by {{ r.reporter }}</p></td>
                                <td class="px-6 py-3"><span :class="ageBadge(r.age_hours)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">{{ r.age_hours }}h</span></td>
                            </tr>
                            <tr v-if="!openReports?.length"><td colspan="3" class="px-6 py-10 text-center text-sm text-gray-400">No open faults. Plant is green.</td></tr>
                        </tbody>
                    </table></div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
</style>
