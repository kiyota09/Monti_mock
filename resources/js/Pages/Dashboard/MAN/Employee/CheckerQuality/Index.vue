<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Package, Layers, TrendingUp, CheckCircle, XCircle, Sparkles, ArrowUpRight, BadgeCheck } from 'lucide-vue-next';

const props = defineProps({
    stats: Object,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Quality Checker Dashboard" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <BadgeCheck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Quality Control
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Quality Control Dashboard</h1>
                            <p class="text-sm text-blue-100/90">{{ stats.pending_orders }} pending orders · {{ stats.fabrics_pending }} fabrics awaiting check</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="route('man.staff.checker-quality.production')"
                                class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition-all duration-200">
                                Go to Production Control
                            </Link>
                        </div>
                    </div>
                    <div class="relative mt-6 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-cyan-300 animate-pulse" /> {{ stats.pending_orders }} orders
                        </span>
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse" /> {{ stats.dye_pending }} dye pending
                        </span>
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ stats.packages_pending }} packages
                        </span>
                    </div>
                </div>

                <!-- Stat cards row 1 -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div key="orders" style="transition-delay: 0ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Package class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Pending Orders</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.pending_orders }}</p>
                            </div>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                    <div key="fabrics" style="transition-delay: 40ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-violet-500 via-purple-600 to-fuchsia-700 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <Layers class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Fabrics Pending</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.fabrics_pending }}</p>
                            </div>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                    <div key="dye" style="transition-delay: 80ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <TrendingUp class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Dye Pending</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.dye_pending }}</p>
                            </div>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                    <div key="soft" style="transition-delay: 120ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <CheckCircle class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Softener/Squeezer</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight">{{ stats.softener_pending + stats.squeezer_pending }}</p>
                            </div>
                        </div>
                        <ArrowUpRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                </TransitionGroup>

                <!-- Stat cards row 2 -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div key="iron" style="transition-delay: 0ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Iron Pending</p>
                        <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight mt-1">{{ stats.iron_pending }}</p>
                        <span class="mt-2 text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 w-fit bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Queued</span>
                    </div>
                    <div key="form" style="transition-delay: 40ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Form Pending</p>
                        <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight mt-1">{{ stats.form_pending }}</p>
                        <span class="mt-2 text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 w-fit bg-violet-100 text-violet-700 ring-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/30 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Queued</span>
                    </div>
                    <div key="pkg" style="transition-delay: 80ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Packages Pending</p>
                        <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight mt-1">{{ stats.packages_pending }}</p>
                        <span class="mt-2 text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 w-fit bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Live</span>
                    </div>
                    <div key="rej" style="transition-delay: 120ms"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-center gap-2">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Quality Rejections</p>
                            <XCircle class="h-4 w-4 text-rose-400" />
                        </div>
                        <p class="text-3xl font-black text-gray-900 dark:text-white tracking-tight mt-1">0</p>
                        <span class="mt-2 text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 w-fit bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Clear</span>
                    </div>
                </TransitionGroup>

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
