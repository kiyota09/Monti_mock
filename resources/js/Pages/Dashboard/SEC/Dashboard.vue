<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { LayoutDashboard, Sparkles, FileText, CalendarDays, Megaphone, AlertTriangle, Clock, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    stats: Object,
    todaysMeetings: Array,
    urgentDocuments: Array,
    latestMemos: Array,
});

const fmtDate = (v) => v ? new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>

<template>
    <Head title="Secretary Dashboard" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-amber-300/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <LayoutDashboard class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> SEC · Executive Office
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Secretary Workspace</h1>
                            <p class="text-sm text-blue-100/90">Correspondence, schedule, and office issuances at a glance</p>
                        </div>
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse" /> {{ stats.pending_documents }} pending documents
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <CalendarDays class="h-5 w-5 mx-auto text-indigo-600" /><p class="text-2xl font-black mt-1">{{ stats.meetings_today }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Meetings today</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <Clock class="h-5 w-5 mx-auto text-indigo-600" /><p class="text-2xl font-black mt-1">{{ stats.meetings_week }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">This week</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <FileText class="h-5 w-5 mx-auto text-blue-600" /><p class="text-2xl font-black mt-1">{{ stats.pending_documents }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Pending docs</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm">
                        <AlertTriangle class="h-5 w-5 mx-auto text-rose-600" /><p class="text-2xl font-black mt-1">{{ stats.overdue_documents }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Overdue docs</p>
                    </div>
                    <div class="rounded-3xl bg-white/80 dark:bg-zinc-900/80 border border-gray-100 dark:border-zinc-800 p-4 text-center shadow-sm col-span-2 md:col-span-1">
                        <Megaphone class="h-5 w-5 mx-auto text-amber-600" /><p class="text-2xl font-black mt-1">{{ stats.active_memos }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Active memos</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2">
                            <CalendarDays class="h-4 w-4 text-indigo-600" /><h2 class="text-sm font-black">Today's Meetings</h2>
                            <Link :href="route('secretary.meetings')" class="ml-auto text-[11px] font-bold text-indigo-700 hover:underline flex items-center gap-0.5">All <ChevronRight class="h-3 w-3" /></Link>
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="m in todaysMeetings" :key="m.id" class="px-5 py-3 text-sm">
                                <p class="font-bold text-gray-900 dark:text-white">{{ m.title }}</p>
                                <p class="text-xs text-gray-500">{{ m.start_time ?? '' }}{{ m.end_time ? ` – ${m.end_time}` : '' }} · {{ m.venue ?? 'No venue' }}</p>
                            </li>
                            <li v-if="!todaysMeetings?.length" class="px-5 py-8 text-center text-xs text-gray-400">No meetings today.</li>
                        </ul>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2">
                            <FileText class="h-4 w-4 text-blue-600" /><h2 class="text-sm font-black">Needs Attention</h2>
                            <Link :href="route('secretary.documents')" class="ml-auto text-[11px] font-bold text-indigo-700 hover:underline flex items-center gap-0.5">All <ChevronRight class="h-3 w-3" /></Link>
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="d in urgentDocuments" :key="d.id" class="px-5 py-3 text-sm">
                                <p class="font-mono font-bold text-gray-900 dark:text-white text-xs">{{ d.ref_no }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ d.subject }}</p>
                                <p class="text-[11px]" :class="d.deadline && new Date(d.deadline) < new Date(new Date().toDateString()) ? 'text-rose-600 font-bold' : 'text-gray-400'">{{ d.status.replace('_', ' ') }} · {{ d.deadline ? fmtDate(d.deadline) : 'no deadline' }}</p>
                            </li>
                            <li v-if="!urgentDocuments?.length" class="px-5 py-8 text-center text-xs text-gray-400">Document queue is clear.</li>
                        </ul>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2">
                            <Megaphone class="h-4 w-4 text-amber-600" /><h2 class="text-sm font-black">Active Memos</h2>
                            <Link :href="route('secretary.memos')" class="ml-auto text-[11px] font-bold text-indigo-700 hover:underline flex items-center gap-0.5">All <ChevronRight class="h-3 w-3" /></Link>
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="m in latestMemos" :key="m.id" class="px-5 py-3 text-sm">
                                <p class="font-bold text-gray-900 dark:text-white">{{ m.title }}</p>
                                <p class="text-[11px] text-gray-400">{{ m.ref_no }} · {{ m.audience }} · {{ m.published_at ? fmtDate(m.published_at) : '' }}</p>
                            </li>
                            <li v-if="!latestMemos?.length" class="px-5 py-8 text-center text-xs text-gray-400">No published memos.</li>
                        </ul>
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
</style>
