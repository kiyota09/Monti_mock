<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { LayoutDashboard, Sparkles, Factory, Users, Wrench, Truck, Siren, FlaskConical, Package, Megaphone, Leaf } from 'lucide-vue-next';

const props = defineProps({ pulse: Object, attendance: Object, deliveries: Object, criticalIncidents: Array });

const attnTotal = Object.values(props.attendance ?? {}).reduce((a, b) => a + Number(b), 0);
</script>

<template>
    <Head title="Operations Command" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><LayoutDashboard class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> VP · Daily Execution</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Operations Command</h1>
                            <p class="text-sm text-blue-100/90">Today's plant pulse — drive action through Directives</p>
                        </div>
                        <Link :href="route('vp.directives')" class="inline-flex items-center gap-1.5 rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition"><Megaphone class="h-4 w-4" /> Issue Directive</Link>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <Factory class="h-5 w-5 mx-auto text-indigo-600" /><p class="text-2xl font-black mt-1">{{ pulse.fabrics_today }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Fabrics today</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <Package class="h-5 w-5 mx-auto text-blue-600" /><p class="text-2xl font-black mt-1">{{ pulse.packages_today }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Packed today</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <Wrench class="h-5 w-5 mx-auto text-rose-600" /><p class="text-2xl font-black mt-1">{{ pulse.machines_down }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Machines down</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <Users class="h-5 w-5 mx-auto text-emerald-600" /><p class="text-2xl font-black mt-1">{{ attnTotal }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Attendance logs</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <Truck class="h-5 w-5 mx-auto text-amber-600" /><p class="text-2xl font-black mt-1">{{ pulse.jobs_in_production }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Jobs in production</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <Siren class="h-5 w-5 mx-auto text-rose-600" /><p class="text-2xl font-black mt-1">{{ pulse.open_incidents }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Open incidents</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <Leaf class="h-5 w-5 mx-auto text-teal-600" /><p class="text-2xl font-black mt-1">{{ pulse.non_compliant }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Non-compliant</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <FlaskConical class="h-5 w-5 mx-auto text-violet-600" /><p class="text-2xl font-black mt-1">{{ pulse.pending_lab_dips }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Lab dips open</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800"><h2 class="text-sm font-black">Attendance Today</h2></div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="(count, status) in attendance" :key="status" class="px-5 py-2.5 text-sm flex justify-between"><span class="capitalize text-gray-600 dark:text-gray-300">{{ String(status).replace('_', ' ') }}</span><span class="font-black">{{ count }}</span></li>
                            <li v-if="!Object.keys(attendance ?? {}).length" class="px-5 py-6 text-center text-xs text-gray-400">No attendance logged yet today.</li>
                        </ul>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800"><h2 class="text-sm font-black">Deliveries by Status</h2></div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="(count, status) in deliveries" :key="status" class="px-5 py-2.5 text-sm flex justify-between"><span class="capitalize text-gray-600 dark:text-gray-300">{{ String(status).replace('_', ' ') }}</span><span class="font-black">{{ count }}</span></li>
                            <li v-if="!Object.keys(deliveries ?? {}).length" class="px-5 py-6 text-center text-xs text-gray-400">No deliveries on record.</li>
                        </ul>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800"><h2 class="text-sm font-black">Critical Open Incidents</h2></div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="i in criticalIncidents" :key="i.id" class="px-5 py-2.5 text-sm"><p class="font-bold font-mono text-xs">{{ i.code }} · {{ i.severity }}</p><p class="text-xs text-gray-500">{{ i.incident_type.replace('_', ' ') }} — {{ i.location ?? 'plant' }}</p></li>
                            <li v-if="!criticalIncidents?.length" class="px-5 py-6 text-center text-xs text-gray-400">No critical open incidents. Plant is green.</li>
                        </ul>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-center">
                    <div class="rounded-2xl bg-gray-50 dark:bg-zinc-800/60 p-3"><p class="text-xl font-black">{{ pulse.open_directives }}</p><p class="text-[10px] font-bold uppercase text-gray-500">Open directives</p></div>
                    <div class="rounded-2xl bg-gray-50 dark:bg-zinc-800/60 p-3"><p class="text-xl font-black text-rose-600">{{ pulse.overdue_directives }}</p><p class="text-[10px] font-bold uppercase text-gray-500">Overdue directives</p></div>
                    <Link :href="route('vp.directives')" class="rounded-2xl bg-indigo-600 p-3 text-white hover:bg-indigo-700 transition"><p class="text-xl font-black">→</p><p class="text-[10px] font-bold uppercase">Manage</p></Link>
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
