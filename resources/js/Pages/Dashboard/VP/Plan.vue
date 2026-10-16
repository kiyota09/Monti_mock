<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ClipboardList, Sparkles } from 'lucide-vue-next';

defineProps({ orders: Array });

const fmtDate = (v) => v ? new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const shipBadge = (o) => {
    if (!o.expected_ship_date) return 'bg-gray-100 text-gray-500 ring-gray-200';
    const late = new Date(o.expected_ship_date) < new Date(new Date().toDateString()) && o.progress < 100;
    return late ? 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30'
        : 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
};
</script>

<template>
    <Head title="Plan vs Actual" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><ClipboardList class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> VP · Delivery Tracking</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Plan vs Actual</h1>
                            <p class="text-sm text-blue-100/90">Open job orders against delivered output — are we on track?</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div v-for="o in orders" :key="o.id" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-mono text-sm font-bold">{{ o.jo_number }}</p>
                            <p class="text-xs text-gray-500">{{ o.color ?? '' }}</p>
                            <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full ring-1 bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700">{{ o.status.replace('_', ' ') }}</span>
                            <span :class="shipBadge(o)" class="ml-auto px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">Ship: {{ fmtDate(o.expected_ship_date) }}</span>
                        </div>
                        <div class="mt-3 flex items-center gap-3">
                            <div class="flex-1 h-3 rounded-full bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 transition-all duration-700" :style="{ width: `${o.progress}%` }" />
                            </div>
                            <span class="text-sm font-black w-24 text-right">{{ o.delivered.toLocaleString() }} / {{ o.quantity.toLocaleString() }} ({{ o.progress }}%)</span>
                        </div>
                    </div>
                    <div v-if="!orders?.length" class="text-center text-sm text-gray-400 py-8">No open job orders. All caught up.</div>
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
