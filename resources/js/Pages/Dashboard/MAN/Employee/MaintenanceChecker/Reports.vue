<script setup>import { computed } from 'vue';

import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CheckCircle, AlertTriangle, Wrench, Sparkles, ClipboardList, Cog } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));

const props = defineProps({
    reports: Array,
});

const resolveReport = (reportId) => {
    if (confirm('Mark this report as resolved?')) {
        router.patch(route('man.staff.maintenance-checker.resolve-report', reportId), {
            preserveScroll: true,
        });
    }
};

const getStatusBadgeClass = (status) => {
    return status === 'pending'
        ? 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'
        : 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
};
</script>

<template>
    <AuthenticatedLayout title="Maintenance Reports">
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Wrench class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Maintenance Checker
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Machine Reports</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ reports?.length ?? 0 }} report{{ (reports?.length ?? 0) !== 1 ? 's' : '' }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ reports?.filter(r => r.status === 'pending').length ?? 0 }} pending
                            </span>
                            <Link :href="route('man.staff.maintenance-checker.page')"
                                class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95 flex items-center gap-1.5">
                                <Cog class="h-4 w-4" /> Manage Machines
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Reports table -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay: 100ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full" />
                    <div class="relative flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><ClipboardList class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">All Machine Reports</h2>
                        <span class="ml-auto rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-300">{{ reports?.length ?? 0 }} total</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Machine</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Reported By</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Issue</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Status</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Date</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="(report, i) in reports" :key="report.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ report.machine?.machine_no }}</p>
                                            <p class="text-[11px] text-indigo-500 dark:text-indigo-400 font-bold uppercase capitalize">{{ report.machine?.type }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-600 dark:text-gray-300">{{ report.reporter?.name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-md">{{ report.issue }}</td>
                                    <td class="px-6 py-4">
                                        <span :class="getStatusBadgeClass(report.status)"
                                            class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ report.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ new Date(report.created_at).toLocaleString() }}</td>
                                    <td class="px-6 py-4">
                                        <button v-if="report.status === 'pending' && canEditProduction" @click="resolveReport(report.id)"
                                            class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 text-xs font-black flex items-center gap-1 shadow-lg shadow-emerald-500/25 hover:-translate-y-0.5 transition-all active:scale-95">
                                            <CheckCircle class="w-4 h-4" /> Resolve
                                        </button>
                                        <span v-else class="text-gray-400 text-xs font-medium italic">Resolved</span>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <div v-if="!reports || reports.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <AlertTriangle class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No machine reports found</p>
                            <p class="text-xs text-gray-400 mt-1">New reports from operators will appear here.</p>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
</style>
