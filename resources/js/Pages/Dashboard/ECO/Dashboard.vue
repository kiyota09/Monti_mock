<template>
    <Head title="ECO Dashboard" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <ShoppingBag class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <TrendingUp class="h-3.5 w-3.5" /> ECO · Control Center
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">ECO Dashboard</h1>
                            <p class="text-sm text-blue-100/90">Real-time overview of client inquiries, quotations, and sales orders.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <Calendar class="h-3.5 w-3.5" /> {{ formattedDate }}
                            </span>
                            <span class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950 flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-950 animate-pulse" /> Live
                            </span>
                        </div>
                    </div>

                    <!-- Mini stat strip -->
                    <div class="relative mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:80ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><MessageSquare class="h-3.5 w-3.5" /> New inquiries</p>
                            <p class="mt-1 text-lg font-black">{{ stats.inquiries }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:160ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><FileText class="h-3.5 w-3.5" /> Quotations sent</p>
                            <p class="mt-1 text-lg font-black">{{ stats.quotations }}</p>
                        </div>
                        <div class="animate-fade-up rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4 hover:bg-white/20 hover:scale-[1.02] transition-all" style="animation-delay:240ms">
                            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-100"><ShoppingBag class="h-3.5 w-3.5" /> Sales orders</p>
                            <p class="mt-1 text-lg font-black">{{ stats.salesOrders }}</p>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">New Inquiries</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white mt-1 tracking-tight">{{ stats.inquiries }}</p>
                            </div>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 via-indigo-600 to-violet-700 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <MessageSquare class="w-5 h-5" />
                            </div>
                        </div>
                        <div class="mt-3 text-xs text-blue-600 dark:text-blue-400 flex items-center gap-1 font-bold">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> <TrendingUp class="w-3 h-3" /> last 30 days
                        </div>
                    </div>

                    <div class="animate-fade-up group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-cyan-400/20 to-blue-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-cyan-500 to-blue-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Quotations Sent</p>
                                <p class="text-3xl font-black text-gray-900 dark:text-white mt-1 tracking-tight">{{ stats.quotations }}</p>
                            </div>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-violet-600 to-fuchsia-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <FileText class="w-5 h-5" />
                            </div>
                        </div>
                        <div class="mt-3 text-xs text-indigo-600 dark:text-indigo-400 font-bold flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> this month
                        </div>
                    </div>

                    <div class="animate-fade-up group relative flex flex-col rounded-3xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 p-5 text-white shadow-lg shadow-emerald-500/20 hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 transition-all duration-300 overflow-hidden" style="animation-delay:240ms">
                        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl group-hover:scale-125 transition-transform duration-500" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-emerald-100">Sales Orders</p>
                                <p class="text-3xl font-black mt-1 tracking-tight">{{ stats.salesOrders }}</p>
                            </div>
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                <ShoppingBag class="w-5 h-5" />
                            </div>
                        </div>
                        <div class="relative mt-3 text-xs text-emerald-100 font-bold flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse" /> converted from quotations
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-100 dark:border-zinc-800 hover:shadow-xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay:120ms">
                    <div class="flex items-center gap-2 mb-6">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg"><BarChart3 class="w-5 h-5" /></span>
                        <h3 class="text-sm font-black text-gray-700 dark:text-gray-200 uppercase tracking-widest">Monthly Performance</h3>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Live</span>
                    </div>
                    <div class="h-64 flex items-end gap-3 sm:gap-4">
                        <div v-for="(item, idx) in monthlyData" :key="idx" class="flex-1 flex flex-col items-center group/bar">
                            <div class="w-full max-w-[56px] bg-gradient-to-t from-indigo-600 via-indigo-500 to-fuchsia-400 rounded-t-xl transition-all duration-500 group-hover/bar:from-indigo-500 group-hover/bar:to-fuchsia-500 shadow-sm group-hover/bar:shadow-lg group-hover/bar:shadow-indigo-500/30"
                                :style="{ height: `${(item.value / maxValue) * 100}%`, minHeight: '4px', animationDelay: `${idx * 60}ms` }">
                            </div>
                            <span class="text-[10px] sm:text-xs mt-3 text-gray-500 dark:text-gray-400 font-bold">{{ item.month }}</span>
                            <span class="text-[9px] font-black text-gray-600 dark:text-gray-300 mt-1">{{ item.value }}</span>
                        </div>
                    </div>
                    <div class="flex justify-center gap-6 mt-6 text-xs font-bold text-gray-500 dark:text-gray-400">
                        <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-gradient-to-br from-indigo-500 to-fuchsia-500"></span> Inquiries</div>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-indigo-300"></span> Quotations</div>
                    </div>
                </div>

                <!-- Recent Activity Feed -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-100 dark:border-zinc-800 hover:shadow-xl transition-shadow duration-300" style="animation-delay:200ms">
                    <div class="flex items-center gap-2 mb-6">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-slate-600 to-zinc-800 text-white shadow-lg"><Activity class="w-5 h-5" /></span>
                        <h3 class="text-sm font-black text-gray-700 dark:text-gray-200 uppercase tracking-widest">Recent Activity</h3>
                    </div>
                    <TransitionGroup name="card" tag="div" class="space-y-3">
                        <div key="a1" class="group flex items-start gap-3 text-sm rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 hover:shadow-lg hover:-translate-y-0.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20 shrink-0 group-hover:scale-110 transition-transform"><MessageSquare class="w-4 h-4 text-blue-600 dark:text-blue-400" /></span>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 dark:text-gray-200 text-sm">New inquiry from <span class="font-bold">QuantumLeap Inc</span></p>
                                <p class="text-xs text-gray-400 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse" /> 2 hours ago</p>
                            </div>
                        </div>
                        <div key="a2" class="group flex items-start gap-3 text-sm rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 hover:shadow-lg hover:-translate-y-0.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20 shrink-0 group-hover:scale-110 transition-transform"><FileText class="w-4 h-4 text-indigo-600 dark:text-indigo-400" /></span>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 dark:text-gray-200 text-sm">Quotation <span class="font-mono">QT-2026-0042</span> issued to <span class="font-bold">SilverLine Software</span></p>
                                <p class="text-xs text-gray-400 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-pulse" /> 5 hours ago</p>
                            </div>
                        </div>
                        <div key="a3" class="group flex items-start gap-3 text-sm rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 hover:shadow-lg hover:-translate-y-0.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20 shrink-0 group-hover:scale-110 transition-transform"><ShoppingBag class="w-4 h-4 text-emerald-600 dark:text-emerald-400" /></span>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 dark:text-gray-200 text-sm">Sales order <span class="font-mono">PO-2026-0103</span> pushed to SCM</p>
                                <p class="text-xs text-gray-400 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" /> Yesterday</p>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    ShoppingBag,
    MessageSquare,
    FileText,
    Calendar,
    TrendingUp,
    BarChart3,
    Activity
} from 'lucide-vue-next';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            inquiries: 0,
            quotations: 0,
            salesOrders: 0
        })
    }
});

// Dummy monthly data – in real implementation, this would come from the controller
// For demonstration, we create a placeholder. You can replace with real data from backend.
const monthlyData = [
    { month: 'Jan', value: 12 },
    { month: 'Feb', value: 19 },
    { month: 'Mar', value: 15 },
    { month: 'Apr', value: 22 },
    { month: 'May', value: 28 },
    { month: 'Jun', value: 24 }
];

const maxValue = computed(() => Math.max(...monthlyData.map(d => d.value), 1));

const formattedDate = computed(() => {
    return new Date().toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
});
</script>

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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
</style>
