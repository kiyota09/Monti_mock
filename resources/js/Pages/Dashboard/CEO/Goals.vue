<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Target, Sparkles, PlusCircle, Trash2 } from 'lucide-vue-next';

const props = defineProps({ period: String, metrics: Object, goals: Array });

const selectedPeriod = ref(props.period ?? new Date().toISOString().slice(0, 7));
const changePeriod = () => router.get(route('ceo.goals'), { period: selectedPeriod.value }, { preserveState: true, replace: true });

const showForm = ref(false);
const form = useForm({ department: 'company', metric_key: 'revenue', target: '', period: selectedPeriod.value, notes: '' });
const submit = () => { form.period = selectedPeriod.value; form.post(route('ceo.goals.store'), { preserveScroll: true, onSuccess: () => { showForm.value = false; form.reset(); } }); };
const pendingRemove = ref(null);
const removeGoal = (g) => {
    if (pendingRemove.value !== g.id) {
        pendingRemove.value = g.id;
        setTimeout(() => { if (pendingRemove.value === g.id) pendingRemove.value = null; }, 4000);
        return;
    }
    pendingRemove.value = null;
    router.delete(route('ceo.goals.destroy', g.id), { preserveScroll: true });
};

// Attainment vs target with direction awareness (lower-is-better supported).
const attainment = (g) => {
    if (!g.target || g.target <= 0) return 0;
    if (g.lower_is_better) {
        if (g.actual <= 0) return 100;
        return Math.max(0, Math.min(150, (g.target / g.actual) * 100));
    }
    return Math.max(0, Math.min(150, (g.actual / g.target) * 100));
};
const onTrack = (g) => attainment(g) >= 100;
const fmtVal = (g, v) => g.unit === '₱' ? '₱' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(v ?? 0) : `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 }).format(v ?? 0)}${g.unit === '%' ? '%' : ''}`;
</script>

<template>
    <Head title="Goals & Targets" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Target class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> CEO · Performance Contract</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Goals & Targets</h1>
                            <p class="text-sm text-blue-100/90">Actuals computed live from module data · {{ goals?.length ?? 0 }} targets this month</p>
                        </div>
                        <input v-model="selectedPeriod" @change="changePeriod" type="month" class="rounded-2xl bg-white/15 px-3 py-2.5 text-xs font-bold text-white ring-1 ring-white/25 backdrop-blur outline-none" />
                        <button @click="showForm = !showForm" class="rounded-2xl bg-white dark:bg-zinc-900 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 dark:text-indigo-300 shadow-lg hover:scale-105 active:scale-95 transition flex items-center gap-1.5"><PlusCircle class="h-4 w-4" /> Set Target</button>
                    </div>
                </div>

                <Transition name="modal">
                    <div v-if="showForm" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4"><h2 class="text-lg font-black text-white">Set Monthly Target</h2></div>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-zinc-400 mb-1.5">Department *</label>
                                <select v-model="form.department" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="d in ['company','knitting','dyeing','finishing','maintenance','boiler','sales','finance','hr']" :key="d" :value="d">{{ d }}</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-zinc-400 mb-1.5">Metric *</label>
                                <select v-model="form.metric_key" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="(m, k) in metrics" :key="k" :value="k">{{ m.label }} ({{ m.unit }})</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-zinc-400 mb-1.5">Target *</label>
                                <input v-model="form.target" type="number" step="0.01" min="0" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-zinc-400 mb-1.5">Notes</label>
                                <input v-model="form.notes" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2 flex gap-2">
                                <button type="submit" :disabled="form.processing" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Save Target</button>
                                <button type="button" @click="showForm = false" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-zinc-400 transition active:scale-95">Cancel</button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="g in goals" :key="g.id" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5">
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-black uppercase tracking-wider text-indigo-500">{{ g.department }} · {{ g.metric_label }}</p>
                                <p class="text-xl font-black mt-0.5">{{ fmtVal(g, g.actual) }} <span class="text-sm font-bold text-gray-400 dark:text-zinc-500">/ {{ fmtVal(g, g.target) }}</span></p>
                            </div>
                            <span :class="onTrack(g) ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 ring-emerald-200' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 ring-amber-200 dark:ring-amber-500/30'" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 shrink-0">{{ Math.round(attainment(g)) }}%</span>
                            <button @click="removeGoal(g)" :title="pendingRemove === g.id ? 'Click again to confirm removal' : 'Remove target'"
                                :class="['transition', pendingRemove === g.id ? 'rounded-lg bg-rose-600 px-2.5 py-1 text-[10px] font-black uppercase text-white shadow-lg active:scale-95' : 'text-gray-300 hover:text-rose-500']">
                                <span v-if="pendingRemove === g.id" class="inline-flex items-center gap-1"><Trash2 class="h-3.5 w-3.5" /> Sure?</span>
                                <Trash2 v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="mt-3 h-3 rounded-full bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700" :class="onTrack(g) ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-amber-500 to-orange-400'" :style="{ width: `${Math.min(100, attainment(g))}%` }" />
                        </div>
                        <p v-if="g.notes" class="text-[11px] text-gray-400 dark:text-zinc-500 mt-2">{{ g.notes }}</p>
                    </div>
                </div>
                <div v-if="!goals?.length" class="text-center text-sm text-gray-400 dark:text-zinc-500 py-8">No targets set for this month yet.</div>
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
