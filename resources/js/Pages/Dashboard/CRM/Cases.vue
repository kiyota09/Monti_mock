<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { usePageAccess } from '@/composables/usePageAccess';
import { Briefcase, Plus, X } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditCases = computed(() => canEdit('CRM', 'cases'));

const props = defineProps({ status: String, cases: Array, counts: Object, clients: Array, complaints: Array });

const setStatus = (s) => router.get(route('crm.cases'), { status: s }, { preserveScroll: true, replace: true });
const showNew = ref(false);
const form = useForm({ client_id: '', feedback_id: '', subject: '', category: 'other', severity: 'medium' });
const submit = () => {
    form.post(route('crm.cases.store'), { preserveScroll: true, onSuccess: () => { showNew.value = false; form.reset(); } });
};
const resolving = ref(null);
const resolution = ref('');
const setCaseStatus = (c, s) => {
    router.post(route('crm.cases.status', c.id), { status: s, resolution_notes: resolution.value || null },
        { preserveScroll: true, onSuccess: () => { resolving.value = null; resolution.value = ''; } });
};
const sevBadge = (s) => ({
    low: 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-300',
    medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    high: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    urgent: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
}[s] || 'bg-gray-100 text-gray-600');
const fdate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Cases" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-6xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Briefcase class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">CRM · Service</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Cases</h1>
                            <p class="text-sm text-blue-100/90">{{ counts.open }} open · {{ counts.in_progress }} in progress · {{ counts.urgent }} urgent.</p>
                            <span v-if="!canEditCases" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <button v-if="canEditCases" @click="showNew = true" class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-2.5 text-xs font-black uppercase text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition"><Plus class="h-4 w-4" /> Open case</button>
                    </div>
                    <div class="relative mt-6 flex flex-wrap gap-2">
                        <button v-for="s in [['open','Open'],['in_progress','In progress'],['resolved','Resolved'],['closed','Closed'],['','All']]" :key="s[0]" @click="setStatus(s[0])"
                            :class="['rounded-2xl px-4 py-2.5 text-xs font-black uppercase transition', status === s[0] || (s[0] === '' && !['open','in_progress','resolved','closed'].includes(status)) ? 'bg-white text-indigo-700 shadow-lg' : 'bg-white/15 text-white hover:bg-white/25']">{{ s[1] }}</button>
                    </div>
                </div>

                <div v-if="complaints?.length && canEditCases" class="rounded-3xl border border-amber-200 dark:border-amber-800 bg-amber-50/60 dark:bg-amber-900/10 p-5">
                    <p class="text-[11px] font-black uppercase tracking-widest text-amber-600 mb-2">Unlinked client complaints — open a case</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="c in complaints" :key="c.id"
                            @click="form.client_id = c.client_id; form.feedback_id = c.id; form.subject = c.subject; showNew = true"
                            class="rounded-xl bg-white dark:bg-zinc-900 px-3 py-2 text-xs font-bold shadow-sm hover:shadow transition text-left">
                            {{ c.client?.company_name }} — {{ c.subject }}
                        </button>
                    </div>
                </div>

                <div class="space-y-3">
                    <div v-for="c in cases" :key="c.id" class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-black text-sm flex-1 min-w-[180px]">{{ c.subject }}</p>
                            <span :class="['rounded-full px-2.5 py-1 text-[10px] font-black uppercase', sevBadge(c.severity)]">{{ c.severity }}</span>
                            <span class="rounded-full bg-gray-100 dark:bg-zinc-800 px-2.5 py-1 text-[10px] font-black uppercase text-gray-500">{{ c.category }}</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">{{ c.client?.company_name }} · owner {{ c.owner?.name || '—' }} · opened {{ fdate(c.created_at) }}</p>
                        <p v-if="c.resolution_notes" class="mt-2 text-xs rounded-xl bg-emerald-50 dark:bg-emerald-900/10 p-3"><span class="font-black text-emerald-600">Resolution:</span> {{ c.resolution_notes }}</p>
                        <div v-if="canEditCases" class="mt-3">
                            <div v-if="resolving !== c.id" class="flex flex-wrap gap-1.5">
                                <button v-for="s in ['open','in_progress','resolved','closed']" :key="s" @click="s === c.status ? null : (['resolved','closed'].includes(s) ? resolving = c.id : setCaseStatus(c, s))"
                                    :disabled="s === c.status"
                                    :class="['rounded-lg px-3 py-1.5 text-[10px] font-black uppercase transition', s === c.status ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-zinc-800 text-gray-500 hover:bg-indigo-100 hover:text-indigo-700']">{{ s.replace('_',' ') }}</button>
                            </div>
                            <div v-else class="flex gap-2">
                                <input v-model="resolution" placeholder="Resolution notes…"
                                    class="flex-1 px-4 py-2 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-xs dark:text-white" />
                                <button @click="setCaseStatus(c, c.status === 'closed' ? 'closed' : 'resolved')" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-[10px] font-black uppercase">Save</button>
                                <button @click="resolving = null" class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-zinc-800 text-[10px] font-black uppercase text-gray-500">Cancel</button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!cases?.length" class="text-center text-sm font-bold text-gray-400 py-10">No cases in this state.</p>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showNew" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showNew = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-6 py-5 text-white flex justify-between items-center">
                            <h3 class="font-black text-lg">Open Case</h3>
                            <button @click="showNew = false"><X class="h-5 w-5" /></button>
                        </div>
                        <form @submit.prevent="submit" class="p-6 space-y-4">
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Account *</label>
                                <select v-model="form.client_id" required class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white">
                                    <option value="">—</option>
                                    <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Subject *</label>
                                <input v-model="form.subject" required placeholder="e.g. Shade variation on JO-2026-0012"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Category</label>
                                    <select v-model="form.category" class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white">
                                        <option value="defect">Fabric defect</option><option value="shortage">Short delivery</option>
                                        <option value="delay">Late delivery</option><option value="billing">Billing</option><option value="other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Severity</label>
                                    <select v-model="form.severity" class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white">
                                        <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                            <button :disabled="form.processing" class="w-full py-3 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white text-xs font-black uppercase shadow-lg disabled:opacity-50 hover:scale-[1.01] active:scale-95 transition-all">Open case</button>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
