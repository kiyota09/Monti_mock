<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Cog, Sparkles, PlusCircle, ClipboardList, ArrowRight } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));

const props = defineProps({
    machines: Array,
    jobOrders: Array,
    fabrics: Array,
    myOpenSetups: Array,
});

const showForm = ref(false);
const form = useForm({
    machine_id: '', task_type: 'setup', sales_order_id: '', fabric_id: '',
    stitch_length: '', yarn_tension: '', takeup_pressure: '', gsm_target: '',
    cams_config: '', pattern_ref: '', remarks: '',
});

const submit = () => {
    form.transform((data) => ({ ...data, sales_order_id: data.sales_order_id || null, fabric_id: data.fabric_id || null }))
        .post(route('man.staff.knitting-mechanic.store-setup'), {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
};

const advance = (setup) => {
    const next = setup.status === 'pending' ? 'in_progress' : 'done';
    router.patch(route('man.staff.knitting-mechanic.update-status', setup.id), { status: next }, { preserveScroll: true });
};

const taskLabel = (t) => ({ setup: 'Setup', changeover: 'Changeover', calibration: 'Calibration', preventive: 'Preventive', repair: 'Repair' }[t] ?? t);
const badge = (s) => s === 'done'
    ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    : s === 'in_progress' ? 'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30'
    : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Setup & Changeover" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Cog class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> MAN · Knitting Mechanic</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Setup & Changeover</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ myOpenSetups?.length ?? 0 }} open sheets · knitting machines only</p>
                        </div>
                        <button v-if="canEditProduction" @click="showForm = !showForm"
                            class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition flex items-center gap-1.5">
                            <PlusCircle class="h-4 w-4" /> New Setup Sheet
                        </button>
                    </div>
                </div>

                <Transition name="modal">
                    <div v-if="showForm" class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4"><h2 class="text-lg font-black text-white">Record Setup Sheet</h2></div>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Machine *</label>
                                <select v-model="form.machine_id" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Select knitting machine</option>
                                    <option v-for="m in machines" :key="m.id" :value="m.id">{{ m.machine_no }} ({{ m.status }})</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Task Type *</label>
                                <select v-model="form.task_type" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="setup">Machine setup</option>
                                    <option value="changeover">Style changeover</option>
                                    <option value="calibration">Calibration / QC tuning</option>
                                    <option value="preventive">Preventive maintenance</option>
                                    <option value="repair">Troubleshoot / repair</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Job Order (optional)</label>
                                <select v-model="form.sales_order_id" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">None</option>
                                    <option v-for="o in jobOrders" :key="o.id" :value="o.id">{{ o.jo_number }} · {{ o.color }}</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Fabric (optional)</label>
                                <select v-model="form.fabric_id" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">None</option>
                                    <option v-for="f in fabrics" :key="f.id" :value="f.id">{{ f.code }} · {{ f.yarn_type }}</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Stitch Length</label>
                                <input v-model="form.stitch_length" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Yarn Tension</label>
                                <input v-model="form.yarn_tension" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Take-up Pressure</label>
                                <input v-model="form.takeup_pressure" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">GSM Target</label>
                                <input v-model="form.gsm_target" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Cams / Needles / Sinkers Config</label>
                                <input v-model="form.cams_config" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Pattern File Ref</label>
                                <input v-model="form.pattern_ref" placeholder="e.g. JACQUARD-042.json" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Remarks</label>
                                <textarea v-model="form.remarks" rows="2" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                            <div class="sm:col-span-2 flex gap-2">
                                <button v-if="canEditProduction" type="submit" :disabled="form.processing || !canEditProduction" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Save Sheet</button>
                                <button type="button" @click="showForm = false" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-gray-300 transition active:scale-95">Cancel</button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><ClipboardList class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black text-gray-900 dark:text-white">My Open Setup Sheets</h2>
                        <span class="ml-auto rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-300">{{ myOpenSetups?.length ?? 0 }} open</span>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Task / Machine</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="s in myOpenSetups" :key="s.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                <td class="px-6 py-4 font-mono text-sm font-bold">{{ s.code }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ taskLabel(s.task_type) }} · {{ s.machine?.machine_no }}</td>
                                <td class="px-6 py-4"><span :class="badge(s.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">{{ s.status.replace('_', ' ') }}</span></td>
                                <td class="px-6 py-4 text-right">
                                    <button v-if="canEditProduction" @click="advance(s)" class="inline-flex items-center gap-1 text-[11px] font-black text-indigo-700 hover:underline">
                                        {{ s.status === 'pending' ? 'Start' : 'Mark Done' }} <ArrowRight class="h-3 w-3" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!myOpenSetups?.length"><td colspan="4" class="px-6 py-12 text-center text-sm text-gray-400">No open sheets. Record one above to get started.</td></tr>
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
