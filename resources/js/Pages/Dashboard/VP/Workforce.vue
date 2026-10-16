<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Users, Sparkles, UserCheck, CalendarDays, FilePlus, Wallet } from 'lucide-vue-next';

const props = defineProps({ headcount: Object, attendanceToday: Object, leave: Object, applicants: Object, payrollMonth: Number, supervisors: Array });

const peso = (v) => '₱' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(v ?? 0);
const entries = (obj) => Object.entries(obj ?? {});
</script>

<template>
    <Head title="Workforce Overview" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Users class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> VP · People Pulse</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Workforce Overview</h1>
                            <p class="text-sm text-blue-100/90">Manpower, attendance, leave, hiring, cost — read-only, HR owns the transactions</p>
                        </div>
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">{{ peso(payrollMonth) }} payroll this month</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2"><UserCheck class="h-4 w-4 text-indigo-500" /><h2 class="text-sm font-black">Headcount by Module</h2></div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="[role, count] in entries(headcount)" :key="role" class="px-5 py-2.5 text-sm flex justify-between"><span class="font-bold">{{ role }}</span><span class="font-black">{{ count }}</span></li>
                            <li v-if="!entries(headcount).length" class="px-5 py-6 text-center text-xs text-gray-400">No active employees found.</li>
                        </ul>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2"><CalendarDays class="h-4 w-4 text-emerald-500" /><h2 class="text-sm font-black">Attendance Today</h2></div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="[status, count] in entries(attendanceToday)" :key="status" class="px-5 py-2.5 text-sm flex justify-between"><span class="capitalize text-gray-600 dark:text-gray-300">{{ String(status).replace('_', ' ') }}</span><span class="font-black">{{ count }}</span></li>
                            <li v-if="!entries(attendanceToday).length" class="px-5 py-6 text-center text-xs text-gray-400">No attendance logged yet today.</li>
                        </ul>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2"><FilePlus class="h-4 w-4 text-amber-500" /><h2 class="text-sm font-black">Leave by Status</h2></div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="[status, count] in entries(leave)" :key="status" class="px-5 py-2.5 text-sm flex justify-between"><span class="capitalize text-gray-600 dark:text-gray-300">{{ String(status).replace('_', ' ') }}</span><span class="font-black">{{ count }}</span></li>
                            <li v-if="!entries(leave).length" class="px-5 py-6 text-center text-xs text-gray-400">No leave requests on record.</li>
                        </ul>
                    </div>
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2"><Wallet class="h-4 w-4 text-violet-500" /><h2 class="text-sm font-black">Hiring Pipeline & Supervisors</h2></div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <li v-for="[status, count] in entries(applicants)" :key="status" class="px-5 py-2.5 text-sm flex justify-between"><span class="capitalize text-gray-600 dark:text-gray-300">Applicants · {{ String(status).replace('_', ' ') }}</span><span class="font-black">{{ count }}</span></li>
                        </ul>
                        <div class="px-5 py-3 border-t border-gray-100 dark:border-zinc-800">
                            <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Department supervisors in seat</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="s in supervisors" :key="s.id" class="rounded-full bg-indigo-50 dark:bg-indigo-500/15 px-2.5 py-1 text-[11px] font-bold text-indigo-700 dark:text-indigo-300">{{ s.supervisor_department }}</span>
                                <span v-if="!supervisors?.length" class="text-xs text-gray-400">None seated.</span>
                            </div>
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
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
</style>
