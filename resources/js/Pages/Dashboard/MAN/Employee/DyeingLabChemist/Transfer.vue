<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowRightLeft, Sparkles, Calculator, PenLine, CheckCircle2 } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));

const props = defineProps({ dips: Array });

const selectedDip = ref(null);
const selectDip = (dip) => { selectedDip.value = dip; signForm.dip_request_id = dip.id; signForm.trial_id = ''; };

const scale = ref({ labWeight: '5', labUnit: 'g', bulkWeight: '500', bulkUnit: 'kg', correction: '1' });
const scaled = computed(() => {
    const lw = parseFloat(scale.value.labWeight) || 0;
    let bw = parseFloat(scale.value.bulkWeight) || 0;
    if (scale.value.bulkUnit === 'kg') bw *= 1000;
    if (scale.value.labUnit === 'kg') return 0;
    const factor = lw > 0 ? (bw / lw) * (parseFloat(scale.value.correction) || 1) : 0;
    return factor;
});

const signForm = useForm({ dip_request_id: '', trial_id: '', correction_factor: '1' });
const signOff = () => signForm.post(route('man.staff.dyeing-lab-chemist.sign-off'), { preserveScroll: true, onSuccess: () => { selectedDip.value = null; signForm.reset(); } });
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Bulk Recipe Transfer" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><ArrowRightLeft class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> MAN · Lab Chemist</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Bulk Recipe Transfer</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">Scale lab recipes and sign off to the dye-house floor</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-6">
                        <h2 class="text-sm font-black flex items-center gap-2 mb-4"><Calculator class="h-4 w-4 text-indigo-500" /> Lab-to-Bulk Scaling Calculator</h2>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Lab Sample Weight</label>
                                <div class="flex gap-2"><input v-model="scale.labWeight" type="number" step="0.01" min="0" class="flex-1 rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                                <select v-model="scale.labUnit" class="rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none"><option value="g">g</option><option value="kg">kg</option></select></div></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Bulk Batch Weight</label>
                                <div class="flex gap-2"><input v-model="scale.bulkWeight" type="number" step="0.01" min="0" class="flex-1 rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                                <select v-model="scale.bulkUnit" class="rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none"><option value="g">g</option><option value="kg">kg</option></select></div></div>
                            <div class="col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Correction Factor</label>
                                <input v-model="scale.correction" type="number" step="0.01" min="0.01" max="10" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                        </div>
                        <div class="mt-4 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/30 p-4 text-center">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Scale factor (×)</p>
                            <p class="text-3xl font-black text-indigo-700 dark:text-indigo-300">{{ scaled.toLocaleString(undefined, { maximumFractionDigits: 2 }) }}</p>
                            <p class="text-[11px] text-gray-400 mt-1">Multiply every lab %OWF / g/L entry by this factor for the bulk vessel</p>
                        </div>
                    </div>

                    <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                            <h2 class="text-sm font-black flex items-center gap-2"><PenLine class="h-4 w-4 text-indigo-500" /> Approved Dips Awaiting Hand-off</h2>
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-zinc-800 max-h-96 overflow-y-auto">
                            <li v-for="dip in dips" :key="dip.id">
                                <button @click="selectDip(dip)" :class="['w-full text-left px-6 py-3.5 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors', selectedDip?.id === dip.id ? 'bg-indigo-50/70 dark:bg-indigo-950/30' : '']">
                                    <p class="font-mono text-xs font-bold">{{ dip.code }}</p>
                                    <p class="text-xs text-gray-500">{{ dip.sales_order ? `JO ${dip.sales_order.jo_number} · ${dip.sales_order.color}` : 'No linked JO' }} · {{ dip.passed_trials?.length ?? 0 }} passed trial(s){{ dip.sales_order?.has_recipe ? ' · recipe linked' : '' }}</p>
                                </button>
                            </li>
                            <li v-if="!dips?.length" class="px-6 py-8 text-center text-xs text-gray-400">No approved dips awaiting transfer.</li>
                        </ul>
                    </div>
                </div>

                <div v-if="selectedDip" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-6">
                    <h2 class="text-sm font-black mb-1">Sign Off to Dye-House Floor</h2>
                    <p class="text-xs text-gray-500 mb-4">{{ selectedDip.code }} → {{ selectedDip.sales_order ? `JO ${selectedDip.sales_order.jo_number}` : 'no JO linked' }}. This writes the recipe the dyeing floor works from.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Passed Trial *</label>
                            <select v-model="signForm.trial_id" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select trial</option>
                                <option v-for="t in selectedDip.passed_trials" :key="t.id" :value="t.id">Trial {{ t.trial_no }}{{ t.delta_e ? ` — ΔE ${t.delta_e}` : '' }}</option>
                            </select></div>
                        <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Correction ×</label>
                            <input v-model="signForm.correction_factor" type="number" step="0.01" min="0.01" max="10" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                    </div>
                    <div v-if="selectedDip.passed_trials?.length" class="mt-3 rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 text-xs space-y-2">
                        <div v-for="t in selectedDip.passed_trials" :key="t.id">
                            <p class="font-bold">Trial {{ t.trial_no }} formula</p>
                            <p class="text-gray-500">Dyes: {{ (t.formula?.dyestuffs ?? []).map(d => `${d.name} ${d.pct ?? ''}%`).join(', ') || '—' }}</p>
                            <p class="text-gray-500">Aux: {{ (t.formula?.auxiliaries ?? []).map(a => `${a.name} ${a.gpl ?? ''} g/L`).join(', ') || '—' }} · Liquor {{ t.formula?.liquor_ratio ?? '—' }}</p>
                        </div>
                    </div>
                    <button v-if="canEditProduction" @click="signOff" :disabled="!signForm.trial_id || signForm.processing || !selectedDip.sales_order"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-40">
                        <CheckCircle2 class="h-4 w-4" /> Sign Off Recipe
                    </button>
                    <p v-if="!selectedDip.sales_order" class="text-[11px] text-amber-600 mt-2">Link a job order to this dip (Shade Development) before signing off.</p>
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
