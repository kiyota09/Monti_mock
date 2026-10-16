<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ClipboardCheck, Sparkles, PlusCircle, FlaskConical, Printer, X } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));

const props = defineProps({ dips: Array });

const showForm = ref(false);
const form = useForm({ dip_request_id: '', test_type: 'wash', method: '', rating: '', result: '' });
const submit = () => form.post(route('man.staff.dyeing-lab-chemist.store-test'), { preserveScroll: true, onSuccess: () => { showForm.value = false; form.reset(); } });

const coa = ref(null);
const openCoa = (dip) => { coa.value = dip; };
const printCoa = () => window.print();

const typeLabel = (t) => ({ wash: 'Wash Fastness', rub_dry: 'Rubbing (Dry)', rub_wet: 'Rubbing (Wet)', light: 'Light Fastness', perspiration: 'Perspiration', ph: 'pH', absorbency: 'Absorbency', other: 'Other' }[t] ?? t);
</script>

<template>
    <Head title="Quality & Fastness Testing" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><ClipboardCheck class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> MAN · Lab Chemist</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Quality & Fastness Testing</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">Gray-scale ratings, lab logs, and Certificates of Analysis</p>
                        </div>
                        <button v-if="canEditProduction" @click="showForm = !showForm"
                            class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition flex items-center gap-1.5">
                            <PlusCircle class="h-4 w-4" /> Log Test
                        </button>
                    </div>
                </div>

                <Transition name="modal">
                    <div v-if="showForm" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4"><h2 class="text-lg font-black text-white">Log Test Result</h2></div>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Dip Request *</label>
                                <select v-model="form.dip_request_id" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Select dip</option>
                                    <option v-for="d in dips" :key="d.id" :value="d.id">{{ d.code }} · {{ d.pantone_code ?? d.customer_ref ?? '' }}</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Test Type *</label>
                                <select v-model="form.test_type" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="wash">Wash fastness</option><option value="rub_dry">Rubbing — dry</option><option value="rub_wet">Rubbing — wet</option>
                                    <option value="light">Light fastness</option><option value="perspiration">Perspiration</option><option value="ph">pH</option>
                                    <option value="absorbency">Absorbency</option><option value="other">Other</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Rating (Gray Scale 1–5 / value)</label>
                                <input v-model="form.rating" placeholder="e.g. 4-5" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Method</label>
                                <input v-model="form.method" placeholder="e.g. ISO 105-C06" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Result / Notes</label>
                                <input v-model="form.result" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2 flex gap-2">
                                <button v-if="canEditProduction" type="submit" :disabled="form.processing || !canEditProduction" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Save Result</button>
                                <button type="button" @click="showForm = false" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-gray-300 transition active:scale-95">Cancel</button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div v-for="dip in dips" :key="dip.id" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><FlaskConical class="h-4 w-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-xs font-bold">{{ dip.code }} <span class="font-sans text-[11px] text-gray-400">· {{ dip.tests?.length ?? 0 }} test(s) · {{ dip.trials?.length ?? 0 }} trial(s)</span></p>
                        </div>
                        <button @click="openCoa(dip)" class="inline-flex items-center gap-1 rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black uppercase text-white hover:bg-indigo-700 active:scale-95 transition"><Printer class="h-3 w-3" /> CoA</button>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Test</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rating</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Result</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="t in dip.tests" :key="t.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                <td class="px-6 py-3 text-sm font-bold">{{ typeLabel(t.test_type) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ t.method ?? '—' }}</td>
                                <td class="px-6 py-3"><span class="px-2.5 py-1 rounded-full text-[10px] font-black ring-1 bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30">{{ t.rating ?? '—' }}</span></td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ t.result ?? '—' }}</td>
                            </tr>
                            <tr v-if="!dip.tests?.length"><td colspan="4" class="px-6 py-6 text-center text-xs text-gray-400">No tests logged for this dip yet.</td></tr>
                        </tbody>
                    </table></div>
                </div>
                <div v-if="!dips?.length" class="text-center text-sm text-gray-400 py-8">No dip requests in testing.</div>
            </div>
        </div>

        <Transition name="modal">
            <div v-if="coa" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="coa = null" />
                <div class="relative w-full max-w-2xl max-h-[92vh] overflow-y-auto bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800">
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-3 print:border-0">
                        <div class="flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400">Certificate of Analysis</p>
                            <h2 class="font-mono text-lg font-black">{{ coa.code }}</h2>
                        </div>
                        <button @click="printCoa" class="inline-flex items-center gap-1 rounded-xl bg-indigo-600 px-3 py-2 text-[11px] font-black uppercase text-white hover:bg-indigo-700 print:hidden"><Printer class="h-3.5 w-3.5" /> Print</button>
                        <button @click="coa = null" class="print:hidden text-gray-400 hover:text-gray-600"><X class="h-5 w-5" /></button>
                    </div>
                    <div class="p-6 space-y-4 text-sm">
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                            <div><dt class="text-[10px] font-black uppercase text-gray-400">Target Shade</dt><dd class="font-bold">{{ coa.pantone_code ?? coa.customer_ref ?? '—' }}</dd></div>
                            <div><dt class="text-[10px] font-black uppercase text-gray-400">Swatch / Lab Values</dt><dd>{{ coa.swatch_id ?? coa.rgb_lab_values ?? '—' }}</dd></div>
                            <div><dt class="text-[10px] font-black uppercase text-gray-400">Fabric</dt><dd>{{ coa.fabric_details ?? '—' }}</dd></div>
                            <div><dt class="text-[10px] font-black uppercase text-gray-400">Status</dt><dd class="uppercase font-bold">{{ coa.status.replace('_', ' ') }}</dd></div>
                        </dl>
                        <div>
                            <p class="text-[10px] font-black uppercase text-gray-400 mb-1.5">Approved Trials</p>
                            <ul class="space-y-1">
                                <li v-for="t in (coa.trials ?? []).filter(t => t.status === 'passed')" :key="t.id" class="text-xs">Trial {{ t.trial_no }}{{ t.delta_e ? ` — ΔE ${t.delta_e}` : '' }} · {{ (t.formula?.dyestuffs ?? []).map(d => `${d.name} ${d.pct ?? ''}%`).join(', ') }}</li>
                                <li v-if="!(coa.trials ?? []).some(t => t.status === 'passed')" class="text-xs text-gray-400">No passed trials yet.</li>
                            </ul>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase text-gray-400 mb-1.5">Test Results</p>
                            <table class="w-full text-xs"><tbody>
                                <tr v-for="t in coa.tests" :key="t.id" class="border-t border-gray-100 dark:border-zinc-800">
                                    <td class="py-1.5 font-bold">{{ typeLabel(t.test_type) }}</td>
                                    <td class="py-1.5 text-gray-500">{{ t.method ?? '' }}</td>
                                    <td class="py-1.5 font-black">{{ t.rating ?? '' }}</td>
                                    <td class="py-1.5 text-gray-500">{{ t.result ?? '' }}</td>
                                </tr>
                                <tr v-if="!coa.tests?.length"><td class="py-1.5 text-gray-400">No tests recorded.</td></tr>
                            </tbody></table>
                        </div>
                        <p class="text-[11px] text-gray-400 pt-2">Generated from lab records on {{ new Date().toLocaleDateString() }} · Monti Textile Dye Laboratory</p>
                    </div>
                </div>
            </div>
        </Transition>
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
@media print { body * { visibility: hidden; } }
</style>
