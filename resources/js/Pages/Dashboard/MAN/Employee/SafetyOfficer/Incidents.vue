<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ShieldCheck, Sparkles, PlusCircle, Siren } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));

const props = defineProps({ incidents: Array });

const showForm = ref(false);
const form = useForm({
    incident_type: 'near_miss', location: '', department: '', incident_date: new Date().toISOString().slice(0, 10),
    severity: 'minor', description: '', corrective_action: '',
});
const submit = () => form.post(route('man.staff.safety-officer.store-incident'), {
    preserveScroll: true,
    onSuccess: () => { showForm.value = false; form.reset(); },
});

const advance = (incident, next) => router.patch(
    route('man.staff.safety-officer.update-status', incident.id),
    { status: next },
    { preserveScroll: true }
);

const badge = (s) => s === 'closed'
    ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    : s === 'investigating' ? 'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30'
    : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
const sevBadge = (s) => ['major', 'critical'].includes(s)
    ? 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30'
    : 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Incident Reports" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><ShieldCheck class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> MAN · Safety Office</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Incident Reports</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">Injuries, near-misses, fire, spills — file fast, follow up faster</p>
                        </div>
                        <button v-if="canEditProduction" @click="showForm = !showForm"
                            class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition flex items-center gap-1.5">
                            <PlusCircle class="h-4 w-4" /> File Incident
                        </button>
                    </div>
                </div>

                <Transition name="modal">
                    <div v-if="showForm" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4"><h2 class="text-lg font-black text-white">File Incident Report</h2></div>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Type *</label>
                                <select v-model="form.incident_type" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="injury">Injury</option><option value="near_miss">Near miss</option><option value="fire">Fire</option>
                                    <option value="chemical_spill">Chemical spill</option><option value="equipment">Equipment-related</option><option value="other">Other</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Severity *</label>
                                <select v-model="form.severity" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="minor">Minor</option><option value="moderate">Moderate</option><option value="major">Major</option><option value="critical">Critical</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Location</label>
                                <input v-model="form.location" placeholder="e.g. Dyeing floor, Boiler house" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Department</label>
                                <select v-model="form.department" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">—</option>
                                    <option value="knitting">Knitting</option><option value="dyeing">Dyeing</option><option value="finishing">Finishing</option>
                                    <option value="maintenance">Maintenance</option><option value="warehouse">Warehouse</option><option value="other">Other</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Incident Date *</label>
                                <input v-model="form.incident_date" type="date" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Immediate Corrective Action</label>
                                <input v-model="form.corrective_action" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Description *</label>
                                <textarea v-model="form.description" rows="3" required placeholder="What happened, who was involved, conditions at the time…"
                                    class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                            <div class="sm:col-span-2 flex gap-2">
                                <button v-if="canEditProduction" type="submit" :disabled="form.processing || !canEditProduction" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">File Report</button>
                                <button type="button" @click="showForm = false" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-gray-300 transition active:scale-95">Cancel</button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600 text-white shadow-lg"><Siren class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black text-gray-900 dark:text-white">Case Log</h2>
                        <span class="ml-auto rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-300">{{ incidents?.length ?? 0 }} cases</span>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code / Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">What / Where</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="i in incidents" :key="i.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                <td class="px-6 py-4"><p class="font-mono text-xs font-bold">{{ i.code }}</p><p class="text-[11px] text-gray-400">{{ new Date(i.incident_date).toLocaleDateString() }}</p></td>
                                <td class="px-6 py-4 text-sm max-w-xs"><p class="font-bold">{{ i.incident_type.replace('_', ' ') }}</p><p class="text-xs text-gray-500 truncate">{{ i.location ?? '—' }} · {{ i.department ?? '—' }}</p></td>
                                <td class="px-6 py-4"><span :class="sevBadge(i.severity)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">{{ i.severity }}</span></td>
                                <td class="px-6 py-4"><span :class="badge(i.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">{{ i.status }}</span></td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <button v-if="i.status === 'open' && canEditProduction" @click="advance(i, 'investigating')" class="text-[11px] font-black text-indigo-700 hover:underline mr-3">Investigate</button>
                                    <button v-if="i.status !== 'closed' && canEditProduction" @click="advance(i, 'closed')" class="text-[11px] font-black text-emerald-700 hover:underline">Close</button>
                                    <span v-else class="text-[11px] text-gray-400">Closed</span>
                                </td>
                            </tr>
                            <tr v-if="!incidents?.length"><td colspan="5" class="px-6 py-8 text-center text-xs text-gray-400">No incidents filed.</td></tr>
                        </tbody>
                    </table></div>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
