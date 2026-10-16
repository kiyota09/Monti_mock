<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Zap, Sparkles, Flame } from 'lucide-vue-next';

defineProps({ totals: Object, byDay: Array, boilers: Array });
</script>

<template>
    <Head title="Utilities Brief" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Zap class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> VP · Energy & Steam</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Utilities Brief</h1>
                            <p class="text-sm text-blue-100/90">Fuel, steam hours, and blowdown discipline — last 30 days</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <p class="text-2xl font-black">{{ totals.fuel_30d.toLocaleString() }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Fuel used (L/kg)</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <p class="text-2xl font-black">{{ totals.hours_30d.toLocaleString() }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Steam hours</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <p class="text-2xl font-black">{{ totals.blowdown_rate }}%</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Blowdown compliance</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <p class="text-2xl font-black">{{ totals.logs_count }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Log entries</p>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5">
                    <h2 class="text-sm font-black mb-4">Daily Fuel Consumption</h2>
                    <div class="h-40 flex items-end gap-1.5">
                        <div v-for="d in byDay" :key="d.day" class="flex-1 flex flex-col items-center gap-1 min-w-0">
                            <div class="w-full max-w-8 bg-gradient-to-t from-amber-500 to-orange-400 rounded-t" :style="{ height: `${Math.max(3, (d.fuel / Math.max(...byDay.map(x => x.fuel), 1)) * 100)}%` }" :title="`${d.day}: ${d.fuel}`" />
                            <span class="text-[8px] text-gray-400">{{ d.day.slice(5) }}</span>
                        </div>
                    </div>
                    <p v-if="!byDay?.length" class="text-xs text-gray-400 text-center py-4">No boiler logs in the last 30 days.</p>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2">
                        <Flame class="h-4 w-4 text-orange-500" /><h2 class="text-sm font-black">Boiler Units</h2>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                        <li v-for="b in boilers" :key="b.id" class="px-6 py-3 text-sm flex justify-between">
                            <span class="font-mono font-bold">{{ b.machine_no }}</span>
                            <span :class="b.status === 'available' ? 'bg-emerald-100 text-emerald-700 ring-emerald-200' : 'bg-rose-100 text-rose-700 ring-rose-200'" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">{{ b.status.replace('_', ' ') }}</span>
                        </li>
                        <li v-if="!boilers?.length" class="px-6 py-6 text-center text-xs text-gray-400">No boiler units registered. Register them under Maintenance → Machines (type: boiler).</li>
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
