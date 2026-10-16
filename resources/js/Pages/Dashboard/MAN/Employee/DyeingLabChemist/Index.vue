<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FlaskConical, Clock, AlertTriangle, Sparkles, ChevronRight, ArrowUpRight, ListOrdered, Target } from 'lucide-vue-next';

const props = defineProps({
    stats: Object,
    recentDips: Array,
    efficiency: Object,
    labMetrics: Object,
    nextQueue: Array,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Lab Chemist Dashboard" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <FlaskConical class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Dyeing Lab Chemist
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Lab Chemist Dashboard</h1>
                            <p class="text-sm text-blue-100/90">{{ stats.pending }} open dips · FTR {{ labMetrics.ftr_rate }}% · {{ stats.total_today }} logged today</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="route('man.staff.dyeing-lab-chemist.page')"
                                class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition-all duration-200">
                                Shade Development
                            </Link>
                            <Link :href="route('man.staff.dyeing-lab-chemist.transfer')"
                                class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25 backdrop-blur hover:bg-white/25 active:scale-95 transition-all duration-200">
                                Bulk Transfer
                            </Link>
                            <Link :href="route('man.staff.dyeing-lab-chemist.history')"
                                class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25 backdrop-blur hover:bg-white/25 active:scale-95 transition-all duration-200">
                                My History
                            </Link>
                        </div>
                    </div>
                    <div class="relative mt-6 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse" /> {{ stats.pending }} open
                        </span>
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                            <Target class="h-3.5 w-3.5 text-emerald-300" /> FTR {{ labMetrics.ftr_rate }}%
                        </span>
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                            <Clock class="h-3.5 w-3.5 text-blue-200" /> {{ labMetrics.avg_turn_hours }}h avg turn
                        </span>
                    </div>
                </div>

                <div v-if="efficiency" class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5" style="animation-delay: 120ms">
                    <h2 class="text-sm font-black text-gray-900 dark:text-white tracking-tight mb-3">My Efficiency</h2>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-center">
                        <div class="rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 p-3"><p class="text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ efficiency.my_today }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Mine today</p></div>
                        <div class="rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 p-3"><p class="text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ efficiency.my_week }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Mine this week</p></div>
                        <div class="rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 p-3"><p class="text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ efficiency.my_total }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Mine total</p></div>
                        <div class="rounded-2xl bg-emerald-50/70 dark:bg-emerald-950/30 p-3"><p class="text-2xl font-black text-emerald-700 dark:text-emerald-300">{{ labMetrics.trials_total }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Lab trials</p></div>
                        <div class="rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 p-3"><p class="text-2xl font-black text-amber-700 dark:text-amber-300">{{ labMetrics.avg_turn_hours }}h</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Avg turn</p></div>
                    </div>
                    <div v-if="nextQueue?.length" class="mt-4 rounded-2xl border border-dashed border-indigo-200 dark:border-indigo-800 p-3">
                        <p class="text-[10px] font-black uppercase tracking-wider text-indigo-500 mb-2">Up next in queue ({{ stats.pending }} waiting)</p>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="item in nextQueue" :key="item.id" class="rounded-full bg-indigo-600/10 dark:bg-indigo-500/15 px-3 py-1.5 font-mono text-xs font-bold text-indigo-700 dark:text-indigo-300">{{ item.code }}</span>
                        </div>
                    </div>
                </div>

                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div key="pending" class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300 p-5 overflow-hidden">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform duration-300">
                                <FlaskConical class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Open Dip Requests</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.pending }}</p>
                            </div>
                            <span class="ml-auto flex h-8 w-8 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-400 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                                <ChevronRight class="h-4 w-4" />
                            </span>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                    <div key="ftr" class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300 p-5 overflow-hidden">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform duration-300">
                                <Target class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">First-Time-Right</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ labMetrics.ftr_rate }}%</p>
                            </div>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                    <div key="today" class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:-translate-y-1.5 transition-all duration-300 p-5 overflow-hidden">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white shadow-lg group-hover:scale-110 transition-transform duration-300">
                                <Clock class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Logged Today</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.total_today }}</p>
                            </div>
                            <span class="ml-auto text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Live
                            </span>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                </TransitionGroup>

                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 160ms">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20"><ListOrdered class="h-4 w-4 text-amber-500" /></span>
                        <h2 class="text-sm font-black text-gray-900 dark:text-white tracking-tight">My Recent Dip Requests</h2>
                        <span class="ml-auto text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30 flex items-center gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ recentDips.length }} dips
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50/80 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Target</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Urgency</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="d in recentDips" :key="d.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4 font-mono text-sm text-gray-900 dark:text-white">{{ d.code }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ d.pantone_code ?? d.customer_ref ?? '—' }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300 uppercase text-xs font-bold">{{ d.urgency }}</td>
                                    <td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30">{{ d.status.replace('_', ' ') }}</span></td>
                                </tr>
                                <tr v-if="recentDips.length === 0">
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="p-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-3 animate-bounce-soft">
                                                <FlaskConical class="h-7 w-7 text-indigo-400" />
                                            </div>
                                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No dip requests logged yet.</p>
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
