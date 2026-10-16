<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Boxes, Sparkles, PlusCircle, FlaskConical, Trash2, ExternalLink } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));

const props = defineProps({ dyes: Array, solutions: Array });

const showForm = ref(false);
const form = useForm({ material_name: '', concentration: '', lot_no: '', prepared_at: '', expiry_date: '', sds_url: '', remarks: '' });
const submit = () => form.post(route('man.staff.dyeing-lab-chemist.store-solution'), { preserveScroll: true, onSuccess: () => { showForm.value = false; form.reset(); } });
const removeSolution = (s) => { if (confirm(`Remove solution record for ${s.material_name}?`)) router.delete(route('man.staff.dyeing-lab-chemist.destroy-solution', s.id), { preserveScroll: true }); };

const expired = (date) => date && new Date(date) < new Date(new Date().toDateString());
const fmtDate = (v) => v ? new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Lab Inventory" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Boxes class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> MAN · Lab Chemist</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Lab Inventory</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">Dye lots (read-only, owned by production inventory) + stock-solution prep log</p>
                        </div>
                        <button v-if="canEditProduction" @click="showForm = !showForm"
                            class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition flex items-center gap-1.5">
                            <PlusCircle class="h-4 w-4" /> Log Solution
                        </button>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><Boxes class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black text-gray-900 dark:text-white">Dye Lots in Production Inventory</h2>
                        <span class="ml-auto rounded-full bg-gray-100 dark:bg-zinc-800 px-3 py-1 text-[11px] font-black text-gray-500">read-only</span>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Control No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Material</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remaining</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="d in dyes" :key="d.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                <td class="px-6 py-3 font-mono text-xs font-bold">{{ d.control_number }}</td>
                                <td class="px-6 py-3 text-sm">{{ d.material_name }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ d.remaining_quantity }} {{ d.unit }}</td>
                            </tr>
                            <tr v-if="!dyes?.length"><td colspan="3" class="px-6 py-8 text-center text-xs text-gray-400">No dye lots in production inventory.</td></tr>
                        </tbody>
                    </table></div>
                </div>

                <Transition name="modal">
                    <div v-if="showForm" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4"><h2 class="text-lg font-black text-white">Log Stock Solution Prep</h2></div>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Material *</label>
                                <input v-model="form.material_name" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Concentration</label>
                                <input v-model="form.concentration" placeholder="e.g. 1%, 0.1%" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Lot No</label>
                                <input v-model="form.lot_no" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">SDS Link</label>
                                <input v-model="form.sds_url" placeholder="https://…" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Prepared At</label>
                                <input v-model="form.prepared_at" type="date" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Expiry Date</label>
                                <input v-model="form.expiry_date" type="date" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Remarks</label>
                                <textarea v-model="form.remarks" rows="2" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                            <div class="sm:col-span-2 flex gap-2">
                                <button v-if="canEditProduction" type="submit" :disabled="form.processing || !canEditProduction" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Save</button>
                                <button type="button" @click="showForm = false" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-gray-300 transition active:scale-95">Cancel</button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><FlaskConical class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black text-gray-900 dark:text-white">Stock Solution Prep Log</h2>
                        <span class="ml-auto rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-300">{{ solutions?.length ?? 0 }} records</span>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Material</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conc. / Lot</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expiry</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="s in solutions" :key="s.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                <td class="px-6 py-4 text-sm font-bold">{{ s.material_name }}
                                    <a v-if="s.sds_url" :href="s.sds_url" target="_blank" class="ml-1 inline-flex items-center gap-0.5 text-[11px] font-bold text-indigo-700 hover:underline">SDS <ExternalLink class="h-3 w-3" /></a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ s.concentration ?? '—' }} · {{ s.lot_no ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm" :class="expired(s.expiry_date) ? 'text-rose-600 font-bold' : 'text-gray-500'">{{ fmtDate(s.expiry_date) }}</td>
                                <td class="px-6 py-4 text-right"><button v-if="canEditProduction" @click="removeSolution(s)" class="text-rose-500 hover:text-rose-700"><Trash2 class="h-4 w-4" /></button></td>
                            </tr>
                            <tr v-if="!solutions?.length"><td colspan="4" class="px-6 py-8 text-center text-xs text-gray-400">No solutions logged yet.</td></tr>
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
