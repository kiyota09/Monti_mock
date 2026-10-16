<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Users, Sparkles, HandCoins, Building2 } from 'lucide-vue-next';

const props = defineProps({
    payroll: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    isDummy: { type: Boolean, default: false },
});

const peso = (v) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));

const totalHeadcount = computed(() => props.payroll.reduce((s, p) => s + Number(p.headcount ?? 0), 0));
const totalGross = computed(() => props.payroll.reduce((s, p) => s + Number(p.gross ?? 0), 0));
const totalDeductions = computed(() => props.payroll.reduce((s, p) => s + Number(p.deductions ?? 0), 0));
const totalNet = computed(() => props.payroll.reduce((s, p) => s + Number(p.net ?? 0), 0));

const statusClass = (status) => {
    switch (status) {
        case 'paid': return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'processing': return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
        case 'pending': return 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
        default: return 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
    }
};
</script>

<template>
    <Head title="Payroll" />
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
                            <HandCoins class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> Finance · Payroll
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Department Payroll</h1>
                            <p class="text-sm text-blue-100/90">{{ payroll.length }} department{{ payroll.length !== 1 ? 's' : '' }} · {{ totalHeadcount }} employees on run</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="isDummy" class="rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">Demo data</span>
                        </div>
                    </div>

                    <!-- Headcount / gross / net pills -->
                    <div class="relative mt-6 flex flex-wrap gap-2">
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur ring-1 ring-white/25 flex items-center gap-2">
                            <Users class="h-4 w-4" /> {{ totalHeadcount }} headcount
                        </span>
                        <span class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur ring-1 ring-white/25">
                            Gross · {{ peso(totalGross) }}
                        </span>
                        <span class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg scale-105">
                            Net · {{ peso(totalNet) }}
                        </span>
                    </div>
                </div>

                <!-- Department cards -->
                <TransitionGroup name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="(p, i) in payroll" :key="p.department"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />

                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white text-lg font-black shadow-lg uppercase flex-shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                    {{ (p.department ?? '?').charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-black text-gray-900 dark:text-white truncate tracking-tight">{{ p.department }}</p>
                                    <p class="flex items-center gap-1 text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase truncate">
                                        <Building2 class="h-3 w-3" /> {{ p.headcount }} employee{{ Number(p.headcount) !== 1 ? 's' : '' }}
                                    </p>
                                </div>
                            </div>
                            <span :class="statusClass(p.status)"
                                class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex-shrink-0 ml-2 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ p.status }}
                            </span>
                        </div>

                        <div class="space-y-1.5 mb-4 text-[12px]">
                            <div class="flex items-center justify-between">
                                <span class="font-bold uppercase text-[10px] tracking-widest text-gray-400">Gross</span>
                                <span class="font-black text-gray-900 dark:text-white">{{ peso(p.gross) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-bold uppercase text-[10px] tracking-widest text-gray-400">Deductions</span>
                                <span class="font-black text-rose-500">−{{ peso(p.deductions) }}</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all duration-500"
                                    :style="{ width: `${p.gross ? Math.max(4, (Number(p.net) / Number(p.gross)) * 100) : 0}%` }" />
                            </div>
                            <div class="flex items-center justify-between pt-1 border-t border-gray-100 dark:border-zinc-800">
                                <span class="font-bold uppercase text-[10px] tracking-widest text-gray-400">Net pay</span>
                                <span class="text-base font-black text-indigo-700 dark:text-indigo-300">{{ peso(p.net) }}</span>
                            </div>
                        </div>

                        <div class="mt-auto flex items-center justify-between pt-3 border-t border-gray-100 dark:border-zinc-800">
                            <span class="text-[10px] font-bold uppercase text-gray-400">{{ peso(Math.round(Number(p.net ?? 0) / Math.max(1, Number(p.headcount ?? 1)))) }} avg / head</span>
                            <span class="flex h-8 items-center rounded-xl bg-gray-100 dark:bg-zinc-800 px-3 text-[10px] font-black uppercase text-gray-500 dark:text-gray-300 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                                {{ p.headcount }} heads
                            </span>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Payroll totals footer -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative grid grid-cols-2 sm:grid-cols-4 gap-4 p-6 sm:p-8">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Headcount</p>
                            <p class="text-2xl font-black tracking-tight">{{ totalHeadcount }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Gross</p>
                            <p class="text-2xl font-black tracking-tight">{{ peso(totalGross) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Deductions</p>
                            <p class="text-2xl font-black tracking-tight">−{{ peso(totalDeductions) }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur p-3">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Net payout</p>
                            <p class="text-2xl font-black tracking-tight">{{ peso(totalNet) }}</p>
                        </div>
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
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
</style>
