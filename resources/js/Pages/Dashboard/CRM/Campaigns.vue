<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { usePageAccess } from '@/composables/usePageAccess';
import { Megaphone, Plus, X } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditCamp = computed(() => canEdit('CRM', 'campaigns'));

const props = defineProps({ campaigns: Array, openLeads: Array });

const showNew = ref(false);
const form = useForm({ name: '', channel: 'social', audience: '', cost: 0, starts_at: '', ends_at: '', status: 'draft' });
const submit = () => {
    form.post(route('crm.campaigns.store'), { preserveScroll: true, onSuccess: () => { showNew.value = false; form.reset(); } });
};
const setStatus = (c, s) => router.post(route('crm.campaigns.status', c.id), { status: s }, { preserveScroll: true });

const attaching = ref(null);
const attachLeadId = ref('');
const attach = (c) => {
    router.post(route('crm.campaigns.attach-lead', c.id), { lead_id: attachLeadId.value },
        { preserveScroll: true, onSuccess: () => { attaching.value = null; attachLeadId.value = ''; } });
};
const peso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });
const statusBadge = (s) => ({
    draft: 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-300',
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    completed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
}[s] || 'bg-gray-100 text-gray-600');
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Campaigns" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-6xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Megaphone class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">CRM · Demand</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Campaigns</h1>
                            <p class="text-sm text-blue-100/90">{{ campaigns?.length ?? 0 }} campaigns · leads attributed per campaign.</p>
                            <span v-if="!canEditCamp" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <button v-if="canEditCamp" @click="showNew = true" class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-2.5 text-xs font-black uppercase text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition"><Plus class="h-4 w-4" /> New campaign</button>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div v-for="c in campaigns" :key="c.id" class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-black">{{ c.name }}</h3>
                                <p class="text-[11px] font-bold text-gray-400 uppercase">{{ c.channel }} · {{ c.audience || 'general audience' }} · by {{ c.creator?.name || '—' }}</p>
                            </div>
                            <span :class="['rounded-full px-2.5 py-1 text-[10px] font-black uppercase', statusBadge(c.status)]">{{ c.status }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-2xl bg-slate-50 dark:bg-zinc-800 p-3"><p class="text-lg font-black">{{ c.leads_count }}</p><p class="text-[10px] font-bold uppercase text-gray-400">Leads</p></div>
                            <div class="rounded-2xl bg-slate-50 dark:bg-zinc-800 p-3"><p class="text-lg font-black">{{ peso(c.cost) }}</p><p class="text-[10px] font-bold uppercase text-gray-400">Cost</p></div>
                            <div class="rounded-2xl bg-slate-50 dark:bg-zinc-800 p-3"><p class="text-lg font-black">{{ c.cost_per_lead !== null ? peso(c.cost_per_lead) : '—' }}</p><p class="text-[10px] font-bold uppercase text-gray-400">Cost/lead</p></div>
                        </div>
                        <div v-if="canEditCamp" class="mt-3 flex flex-wrap items-center gap-2">
                            <button v-for="s in ['draft','active','completed']" :key="s" @click="setStatus(c, s)" :disabled="s === c.status"
                                :class="['rounded-lg px-3 py-1.5 text-[10px] font-black uppercase transition', s === c.status ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-zinc-800 text-gray-500 hover:bg-indigo-100 hover:text-indigo-700']">{{ s }}</button>
                            <button @click="attaching = attaching === c.id ? null : c.id" class="ml-auto rounded-lg px-3 py-1.5 text-[10px] font-black uppercase bg-gray-100 dark:bg-zinc-800 text-gray-500 hover:text-indigo-700">+ Attribute lead</button>
                        </div>
                        <div v-if="attaching === c.id && canEditCamp" class="mt-2 flex gap-2">
                            <select v-model="attachLeadId" class="flex-1 px-3 py-2 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-xs dark:text-white">
                                <option value="">Select lead…</option>
                                <option v-for="l in openLeads" :key="l.id" :value="l.id">{{ l.company_name }}</option>
                            </select>
                            <button @click="attach(c)" :disabled="!attachLeadId" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-[10px] font-black uppercase disabled:opacity-50">Add</button>
                        </div>
                    </div>
                </div>
                <p v-if="!campaigns?.length" class="text-center text-sm font-bold text-gray-400 py-10">No campaigns yet.</p>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showNew" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showNew = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-6 py-5 text-white flex justify-between items-center">
                            <h3 class="font-black text-lg">New Campaign</h3>
                            <button @click="showNew = false"><X class="h-5 w-5" /></button>
                        </div>
                        <form @submit.prevent="submit" class="p-6 space-y-4">
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Name *</label>
                                <input v-model="form.name" required placeholder="e.g. Holiday collection push"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Channel</label>
                                    <select v-model="form.channel" class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white">
                                        <option value="social">Social</option><option value="email">Email</option>
                                        <option value="tradeshow">Tradeshow</option><option value="referral">Referral</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Status</label>
                                    <select v-model="form.status" class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white">
                                        <option value="draft">Draft</option><option value="active">Active</option><option value="completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Audience</label>
                                <input v-model="form.audience" placeholder="e.g. Metro Manila retailers"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Cost (₱)</label>
                                <input v-model.number="form.cost" type="number" min="0" step="0.01"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                            </div>
                            <button :disabled="form.processing" class="w-full py-3 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white text-xs font-black uppercase shadow-lg disabled:opacity-50 hover:scale-[1.01] active:scale-95 transition-all">Save campaign</button>
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
