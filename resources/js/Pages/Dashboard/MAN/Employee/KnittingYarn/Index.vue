<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Package, Clock, AlertTriangle, Sparkles, ChevronRight, ArrowUpRight, ListOrdered } from 'lucide-vue-next';

const props = defineProps({
    stats: Object,
    recentFabrics: Array,
    efficiency: Object,
    nextQueue: Array,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Knitting Yarn Dashboard" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Package class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Knitting Yarn
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Knitting Yarn Dashboard</h1>
                            <p class="text-sm text-blue-100/90">{{ stats.pending }} pending fabrics · {{ stats.total_today }} recorded today</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="route('man.staff.knitting-yarn.page')"
                                class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition-all duration-200">
                                Record Fabric
                            </Link>
                            <Link :href="route('man.staff.knitting-yarn.reports')"
                                class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25 backdrop-blur hover:bg-white/25 active:scale-95 transition-all duration-200">
                                View Reports
                            </Link>
                            <Link :href="route('man.staff.knitting-yarn.history')"
                                class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25 backdrop-blur hover:bg-white/25 active:scale-95 transition-all duration-200">
                                My History
                            </Link>
                        </div>
                    </div>
                    <div class="relative mt-6 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse" /> {{ stats.pending }} pending
                        </span>
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ stats.total_today }} today
                        </span>
                    </div>
                </div>

                <!-- Stat cards -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div key="pending" style="transition-delay: 0ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Package class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Pending Fabrics</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.pending }}</p>
                            </div>
                            <span class="ml-auto flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-indigo-600 group-hover:text-white group-hover:translate-x-1 transition-all duration-300">
                                <ChevronRight class="h-4 w-4" />
                            </span>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                    <div key="today" style="transition-delay: 40ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Clock class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Total Today</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.total_today }}</p>
                            </div>
                            <span class="ml-auto text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Live
                            </span>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                    <div key="reports" style="transition-delay: 80ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-rose-500 via-red-600 to-orange-600 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <AlertTriangle class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Machine Reports</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">0</p>
                            </div>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                </TransitionGroup>

                <!-- Personal efficiency (own output only — role-guarded on backend) -->
                <div v-if="efficiency" class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5" style="animation-delay: 120ms">
                    <h2 class="text-sm font-black text-gray-900 dark:text-white tracking-tight mb-3">My Efficiency</h2>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-center">
                        <div class="rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 p-3"><p class="text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ efficiency.my_today }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Mine today</p></div>
                        <div class="rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 p-3"><p class="text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ efficiency.my_week }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Mine this week</p></div>
                        <div class="rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 p-3"><p class="text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ efficiency.my_total }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Mine total</p></div>
                        <div class="rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 p-3"><p class="text-2xl font-black text-amber-700 dark:text-amber-300">{{ efficiency.my_open_reports }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Open reports</p></div>
                        <div class="rounded-2xl bg-rose-50/70 dark:bg-rose-950/30 p-3"><p class="text-2xl font-black text-rose-700 dark:text-rose-300">{{ efficiency.machines?.down ?? 0 }}/{{ efficiency.machines?.total ?? 0 }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Machines down</p></div>
                    </div>
                    <div v-if="nextQueue?.length" class="mt-4 rounded-2xl border border-dashed border-indigo-200 dark:border-indigo-800 p-3">
                        <p class="text-[10px] font-black uppercase tracking-wider text-indigo-500 mb-2">Up next in queue ({{ stats.pending }} waiting)</p>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="item in nextQueue" :key="item.id" class="rounded-full bg-indigo-600/10 dark:bg-indigo-500/15 px-3 py-1.5 font-mono text-xs font-bold text-indigo-700 dark:text-indigo-300">{{ item.code }}</span>
                        </div>
                    </div>
                </div>

                <!-- Recent fabrics -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 160ms">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20"><ListOrdered class="h-4 w-4 text-amber-500" /></span>
                        <h2 class="text-sm font-black text-gray-900 dark:text-white tracking-tight">Recent Fabrics</h2>
                        <span class="ml-auto text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30 flex items-center gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ recentFabrics.length }} fabrics
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50/80 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Machine</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Weight (kg)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="fabric in recentFabrics" :key="fabric.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4 font-mono text-sm text-gray-900 dark:text-white">{{ fabric.code }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ fabric.machine?.machine_no }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ fabric.weight }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ new Date(fabric.processed_at).toLocaleString() }}</td>
                                </tr>
                                <tr v-if="recentFabrics.length === 0">
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="p-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-3 animate-bounce-soft">
                                                <Package class="h-7 w-7 text-indigo-400" />
                                            </div>
                                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No fabrics recorded yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
</style>
