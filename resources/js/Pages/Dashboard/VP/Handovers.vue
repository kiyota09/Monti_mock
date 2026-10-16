<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Repeat, Sparkles } from 'lucide-vue-next';

defineProps({ latest: Object, history: Array });

const fmtDate = (v) => v ? new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>

<template>
    <Head title="Shift Handovers" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Repeat class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> VP · Continuity</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Shift Handovers</h1>
                            <p class="text-sm text-blue-100/90">What every department left unfinished — read each morning</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div v-for="(h, dept) in latest" :key="dept" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5">
                        <p class="text-[10px] font-black uppercase tracking-wider text-indigo-500 mb-2">{{ dept }}</p>
                        <div v-if="h">
                            <p class="text-xs text-gray-400">{{ fmtDate(h.shift_date) }}{{ h.shift_type ? ` · ${h.shift_type}` : '' }} · {{ h.author?.name }}</p>
                            <p v-if="h.unfinished_work" class="text-sm mt-2"><span class="font-bold">Unfinished:</span> {{ h.unfinished_work }}</p>
                            <p v-if="h.machine_notes" class="text-sm mt-1"><span class="font-bold">Machines:</span> {{ h.machine_notes }}</p>
                            <p v-if="h.hot_jobs" class="text-sm mt-1"><span class="font-bold">Hot jobs:</span> {{ h.hot_jobs }}</p>
                            <p v-if="h.safety_notes" class="text-sm mt-1"><span class="font-bold">Safety:</span> {{ h.safety_notes }}</p>
                        </div>
                        <p v-else class="text-xs text-gray-400 py-2">No handover logged yet for this department.</p>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800"><h2 class="text-sm font-black">Recent History</h2></div>
                    <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                        <li v-for="h in history" :key="h.id" class="px-6 py-3 text-sm">
                            <p class="font-bold">{{ h.department }} · {{ fmtDate(h.shift_date) }}{{ h.shift_type ? ` · ${h.shift_type}` : '' }} <span class="font-medium text-gray-400">· {{ h.author?.name }}</span></p>
                            <p v-if="h.unfinished_work" class="text-xs text-gray-500 mt-0.5">{{ h.unfinished_work }}</p>
                        </li>
                        <li v-if="!history?.length" class="px-6 py-8 text-center text-xs text-gray-400">No handovers on record.</li>
                    </ul>
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
