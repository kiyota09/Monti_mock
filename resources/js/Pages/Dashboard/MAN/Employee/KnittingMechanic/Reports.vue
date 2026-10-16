<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { AlertTriangle, CheckCircle, Wrench, Sparkles, PlusCircle, ClipboardList, Flag, CheckCheck } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));

const props = defineProps({
    machines: Array,
    myReports: Array,
    incomingFlags: Array,
});

const showReportForm = ref(false);
const form = useForm({
    machine_id: '',
    issue: '',
});

const submitReport = () => {
    form.post(route('man.staff.knitting-mechanic.report-machine'), {
        preserveScroll: true,
        onSuccess: () => {
            showReportForm.value = false;
            form.reset();
        },
    });
};

const acknowledge = (flag) => router.patch(route('man.staff.knitting-mechanic.flag.acknowledge', flag.id), {}, { preserveScroll: true });
const resolve = (flag) => router.patch(route('man.staff.knitting-mechanic.flag.resolve', flag.id), {}, { preserveScroll: true });

const typeLabel = (t) => ({ machine_jam: 'Machine Jam', yarn_feeder: 'Yarn Feeder', needle_damage: 'Needle Damage', tension_issue: 'Tension Issue', quality_defect: 'Quality Defect', other: 'Other' }[t] ?? t);
const flagBadge = (s) => s === 'resolved'
    ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    : s === 'acknowledged' ? 'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30'
    : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
</script>

<template>
    <AuthenticatedLayout title="Knitting Mechanic Reports">
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

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
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Knitting Mechanic
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Machine Reports</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ myReports?.length ?? 0 }} report{{ (myReports?.length ?? 0) !== 1 ? 's' : '' }} · {{ machines?.length ?? 0 }} knitting machines</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button v-if="canEditProduction" @click="showReportForm = !showReportForm"
                                class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95 flex items-center gap-1.5">
                                <PlusCircle class="h-4 w-4" /> Report Issue
                            </button>
                        </div>
                    </div>
                </div>

                <Transition name="modal">
                    <div v-if="showReportForm" class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4">
                            <h2 class="flex items-center gap-2 text-lg font-black text-white tracking-tight"><AlertTriangle class="h-5 w-5" /> Report a Knitting Machine Issue</h2>
                            <p class="text-xs text-blue-100/80 mt-0.5">Faults beyond first-line repair go to Maintenance from here.</p>
                        </div>
                        <form @submit.prevent="submitReport" class="space-y-4 p-6">
                            <div>
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Machine</label>
                                <select v-model="form.machine_id" required
                                    class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm font-medium focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                    <option value="">Select Machine</option>
                                    <option v-for="machine in machines" :key="machine.id" :value="machine.id">
                                        {{ machine.machine_no }} ({{ machine.status }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Issue Description</label>
                                <textarea v-model="form.issue" rows="3" required
                                    class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm font-medium focus:ring-2 focus:ring-indigo-500 outline-none transition"
                                    placeholder="Describe the fault, jam, or worn component..."></textarea>
                            </div>
                            <div class="flex gap-2">
                                <button v-if="canEditProduction" type="submit" :disabled="form.processing || !canEditProduction"
                                    class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-700 transition-all active:scale-95 disabled:opacity-50">
                                    Submit Report
                                </button>
                                <button type="button" @click="showReportForm = false"
                                    class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-gray-600 dark:text-gray-300 transition active:scale-95">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 60ms">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-lg"><Flag class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Flags from Knitting Yarn Staff</h2>
                        <span class="ml-auto rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-300">{{ incomingFlags?.length ?? 0 }} total</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Code</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Issue / Reporter</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Status</th>
                                    <th class="px-6 py-3 text-right text-[10px] font-black uppercase tracking-wider text-gray-400">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="flag in incomingFlags" :key="flag.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4 font-mono text-xs font-bold">{{ flag.code }}</td>
                                    <td class="px-6 py-4 text-sm max-w-xs">
                                        <p class="font-bold text-gray-900 dark:text-white">{{ typeLabel(flag.issue_type) }}{{ flag.machine ? ` · ${flag.machine.machine_no}` : '' }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ flag.description }}</p>
                                        <p class="text-[11px] text-gray-400">by {{ flag.reporter?.name ?? '—' }} · {{ new Date(flag.created_at).toLocaleString() }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="flagBadge(flag.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">{{ flag.status }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <button v-if="flag.status === 'open' && canEditProduction" @click="acknowledge(flag)"
                                            class="inline-flex items-center gap-1 text-[11px] font-black text-indigo-700 hover:underline mr-3">
                                            <CheckCheck class="h-3.5 w-3.5" /> Acknowledge
                                        </button>
                                        <button v-if="flag.status !== 'resolved' && canEditProduction" @click="resolve(flag)"
                                            class="inline-flex items-center gap-1 text-[11px] font-black text-emerald-700 hover:underline">
                                            <CheckCircle class="h-3.5 w-3.5" /> Resolve
                                        </button>
                                        <span v-else class="text-[11px] text-gray-400">Done</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!incomingFlags || incomingFlags.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <Flag class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No flags from yarn staff</p>
                            <p class="text-xs text-gray-400 mt-1">Flags raised by Knitting Yarn staff will appear here.</p>
                        </div>
                    </div>
                </div>

                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 100ms">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><ClipboardList class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">My Reported Issues</h2>
                        <span class="ml-auto rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-300">{{ myReports?.length ?? 0 }} total</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Machine</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Issue</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Status</th>
                                    <th class="px-6 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="report in myReports" :key="report.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-white">{{ report.machine?.machine_no }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ report.issue }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            :class="report.status === 'pending' ? 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30' : 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'"
                                            class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 inline-flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ report.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ new Date(report.created_at).toLocaleString() }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!myReports || myReports.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <CheckCircle class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No reports submitted yet</p>
                            <p class="text-xs text-gray-400 mt-1">Machine issues you report will appear here.</p>
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
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
