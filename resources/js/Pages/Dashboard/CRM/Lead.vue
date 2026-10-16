<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    Plus, X, Search, Inbox, KanbanSquare, ArrowRight, UserCheck,
    Building2, Check, Ban,
} from 'lucide-vue-next';

// Slim intake inbox: qualify here, work deals in Opportunities.
// Negotiation/approvals/close happen on the pipeline — never duplicated here.
const props = defineProps({
    leads: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({}) },
});
const canEdit = computed(() => props.permissions?.leads === 'edit');

const searchQuery = ref('');
const inPipeline = (l) => (l.opportunities_count || 0) > 0 || (l.opportunities?.length || 0) > 0;
const firstOpp = (l) => (l.opportunities?.length ? l.opportunities[0] : null);

const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    let list = props.leads || [];
    if (q) {
        list = list.filter((l) =>
            (l.company_name || '').toLowerCase().includes(q) ||
            (l.contact_person || '').toLowerCase().includes(q) ||
            (l.email || '').toLowerCase().includes(q),
        );
    }
    return list;
});
const freshLeads = computed(() => filtered.value.filter((l) => !inPipeline(l) && l.status !== 'Lost'));
const pipedLeads = computed(() => filtered.value.filter((l) => inPipeline(l)));
const lostLeads = computed(() => filtered.value.filter((l) => !inPipeline(l) && l.status === 'Lost'));

const peso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });

// ── New lead ──
const showCreate = ref(false);
const form = useForm({
    company_name: '', contact_person: '', email: '', phone: '',
    interest_fabric: '', estimated_value: null,
});
const submitCreate = () => {
    form.post(route('crm.lead.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
};

// ── BANT qualify ──
const showQualify = ref(false);
const current = ref(null);
const qualifyForm = useForm({
    budget: '', authority: '', need_summary: '', timeline: '', next_step: '', next_step_due: '',
});
const openQualify = (lead) => {
    if (!canEdit.value) return;
    current.value = lead;
    qualifyForm.reset();
    qualifyForm.clearErrors();
    for (const k of ['budget', 'authority', 'need_summary', 'timeline', 'next_step', 'next_step_due']) {
        qualifyForm[k] = lead[k] || '';
    }
    showQualify.value = true;
};
const submitQualify = () => {
    qualifyForm.post(route('crm.lead.qualify', current.value.id), {
        preserveScroll: true,
        onSuccess: () => { showQualify.value = false; },
    });
};

// ── Send to pipeline ──
const showPipeline = ref(false);
const pipeForm = useForm({ title: '', value: null });
const openPipeline = (lead) => {
    if (!canEdit.value) return;
    current.value = lead;
    pipeForm.reset();
    pipeForm.clearErrors();
    pipeForm.title = `${lead.company_name}${lead.interest_fabric ? ` — ${lead.interest_fabric}` : ''}`;
    pipeForm.value = lead.estimated_value ?? null;
    showPipeline.value = true;
};
const submitPipeline = () => {
    pipeForm.post(route('crm.lead.pipeline', current.value.id), {
        preserveScroll: true,
        onSuccess: () => { showPipeline.value = false; },
    });
};

// ── Convert to client ──
const showConvert = ref(false);
const conversionForm = useForm({
    lead_id: null, company_name: '', contact_person: '', email: '', phone: '',
    business_type: 'wholesaler', tin_number: '', company_address: '', password: 'password123',
});
const openConvert = (lead) => {
    if (!canEdit.value) return;
    current.value = lead;
    conversionForm.reset();
    conversionForm.clearErrors();
    conversionForm.lead_id = lead.id;
    conversionForm.company_name = lead.company_name || '';
    conversionForm.contact_person = lead.contact_person || '';
    conversionForm.email = lead.email || '';
    conversionForm.phone = lead.phone || '';
    showConvert.value = true;
};
const submitConvert = () => {
    conversionForm.post(route('crm.lead.convert'), {
        preserveScroll: true,
        onSuccess: () => { showConvert.value = false; },
    });
};

// ── Mark lost ──
const showLost = ref(false);
const lostForm = useForm({ reject_reason: '' });
const openLost = (lead) => {
    if (!canEdit.value) return;
    current.value = lead;
    lostForm.reset();
    lostForm.clearErrors();
    showLost.value = true;
};
const submitLost = () => {
    lostForm.post(route('crm.lead.reject', current.value.id), {
        preserveScroll: true,
        onSuccess: () => { showLost.value = false; },
    });
};

const inputCls = 'w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white';
const labelCls = 'mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400';
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Leads — Intake Inbox" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-6xl space-y-6 p-4 pb-16 sm:p-6">
                <!-- Header -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white shadow-xl shadow-indigo-500/20 sm:p-8">
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 shadow-lg ring-1 ring-white/30 backdrop-blur">
                            <Inbox class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">CRM · Intake</p>
                            <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Leads</h1>
                            <p class="text-sm text-blue-100/90">Qualify here — deals are worked in <Link :href="route('crm.opportunities')" class="font-bold underline">Opportunities</Link>.</p>
                        </div>
                        <button
                            v-if="canEdit"
                            @click="showCreate = true"
                            class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition hover:scale-105 active:scale-95"
                        >
                            <Plus class="h-4 w-4" /> New Lead
                        </button>
                    </div>
                    <div class="relative mt-4">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-blue-200" />
                        <input
                            v-model="searchQuery"
                            placeholder="Search company, contact, email…"
                            class="w-full rounded-xl bg-white/15 py-2.5 pl-9 pr-3 text-sm text-white outline-none ring-1 ring-white/25 placeholder:text-blue-200 focus:bg-white/20"
                        />
                    </div>
                </div>

                <!-- Fresh leads -->
                <section>
                    <h2 class="mb-3 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                        New — needs qualification ({{ freshLeads.length }})
                    </h2>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div
                            v-for="l in freshLeads"
                            :key="l.id"
                            class="rounded-3xl border border-gray-100 bg-white/70 p-5 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/70"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-black dark:text-white">{{ l.company_name }}</p>
                                    <p class="text-xs text-gray-400">{{ l.contact_person }} · {{ l.email }} · {{ l.phone }}</p>
                                </div>
                                <span v-if="l.estimated_value" class="shrink-0 rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-black text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">{{ peso(l.estimated_value) }}</span>
                            </div>
                            <p v-if="l.interest_fabric" class="mt-1 text-xs font-bold text-gray-500">Interested: {{ l.interest_fabric }}</p>
                            <p v-if="l.need_summary || l.next_step" class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-gray-500 dark:bg-zinc-800 dark:text-slate-300">
                                <span v-if="l.need_summary">Need: {{ l.need_summary }}</span>
                                <span v-if="l.next_step" class="block">Next: {{ l.next_step }}{{ l.next_step_due ? ` (due ${l.next_step_due})` : '' }}</span>
                            </p>
                            <p class="mt-1 text-[10px] text-gray-300">Assigned: {{ l.assigned_staff?.name ?? '—' }}</p>
                            <div v-if="canEdit" class="mt-3 flex flex-wrap gap-1.5">
                                <button @click="openQualify(l)" class="rounded-lg bg-slate-100 px-3 py-1.5 text-[10px] font-black uppercase text-slate-600 hover:bg-slate-200 dark:bg-zinc-800 dark:text-slate-300">Qualify</button>
                                <button @click="openPipeline(l)" class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-[10px] font-black uppercase text-white hover:bg-indigo-700">
                                    To Pipeline <ArrowRight class="h-3 w-3" />
                                </button>
                                <button @click="openConvert(l)" class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-3 py-1.5 text-[10px] font-black uppercase text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-300">
                                    <UserCheck class="h-3 w-3" /> Convert
                                </button>
                                <button @click="openLost(l)" class="rounded-lg px-3 py-1.5 text-[10px] font-black uppercase text-gray-300 hover:bg-rose-50 hover:text-rose-500">Lost</button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!freshLeads.length" class="rounded-2xl border border-dashed border-gray-200 py-8 text-center text-xs font-bold text-gray-300">Inbox zero — every lead is qualified or in the pipeline.</p>
                </section>

                <!-- In pipeline -->
                <section v-if="pipedLeads.length">
                    <h2 class="mb-3 flex items-center gap-1.5 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                        <KanbanSquare class="h-3.5 w-3.5" /> In pipeline ({{ pipedLeads.length }}) — worked in Opportunities
                    </h2>
                    <div class="grid gap-3 md:grid-cols-2">
                        <Link
                            v-for="l in pipedLeads"
                            :key="l.id"
                            :href="firstOpp(l) ? route('crm.opportunities.show', firstOpp(l).id) : route('crm.opportunities')"
                            class="flex items-center gap-3 rounded-3xl border border-emerald-100 bg-emerald-50/50 p-4 transition hover:shadow-md dark:border-emerald-900/30 dark:bg-emerald-950/20"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white"><Check class="h-4 w-4" /></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-black dark:text-white">{{ l.company_name }}</span>
                                <span class="block truncate text-[11px] text-gray-400">→ {{ firstOpp(l)?.title ?? 'pipeline deal' }}</span>
                            </span>
                        </Link>
                    </div>
                </section>

                <!-- Lost -->
                <section v-if="lostLeads.length">
                    <h2 class="mb-3 flex items-center gap-1.5 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                        <Ban class="h-3.5 w-3.5" /> Lost ({{ lostLeads.length }})
                    </h2>
                    <div class="space-y-2">
                        <div v-for="l in lostLeads" :key="l.id" class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-2.5 text-xs opacity-70 dark:bg-zinc-800/60">
                            <span class="font-bold dark:text-slate-300">{{ l.company_name }} <span class="font-normal text-gray-400">· {{ l.lost_reason || 'no reason' }}</span></span>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- New lead -->
        <Teleport to="body">
            <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showCreate = false">
                <form @submit.prevent="submitCreate" class="w-full max-w-md space-y-4 rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <h3 class="font-black dark:text-white">New Lead</h3>
                        <button type="button" @click="showCreate = false"><X class="h-5 w-5 text-gray-400" /></button>
                    </div>
                    <div><label :class="labelCls">Company *</label><input v-model="form.company_name" required :class="inputCls" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label :class="labelCls">Contact person *</label><input v-model="form.contact_person" required :class="inputCls" /></div>
                        <div><label :class="labelCls">Phone *</label><input v-model="form.phone" required :class="inputCls" /></div>
                    </div>
                    <div><label :class="labelCls">Email *</label><input v-model="form.email" type="email" required :class="inputCls" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label :class="labelCls">Interest / fabric</label><input v-model="form.interest_fabric" :class="inputCls" /></div>
                        <div><label :class="labelCls">Est. value (₱)</label><input v-model.number="form.estimated_value" type="number" min="0" :class="inputCls" /></div>
                    </div>
                    <p v-if="form.errors.error" class="text-[11px] font-bold text-rose-500">{{ form.errors.error }}</p>
                    <button :disabled="form.processing" class="w-full rounded-xl bg-indigo-600 py-3 text-xs font-black uppercase text-white hover:bg-indigo-700 disabled:opacity-50">Add to inbox</button>
                </form>
            </div>
        </Teleport>

        <!-- BANT qualify -->
        <Teleport to="body">
            <div v-if="showQualify && current" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showQualify = false">
                <form @submit.prevent="submitQualify" class="max-h-[90vh] w-full max-w-md space-y-3 overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <h3 class="font-black dark:text-white">Qualify — {{ current.company_name }}</h3>
                        <button type="button" @click="showQualify = false"><X class="h-5 w-5 text-gray-400" /></button>
                    </div>
                    <div><label :class="labelCls">Budget</label><input v-model="qualifyForm.budget" :class="inputCls" /></div>
                    <div><label :class="labelCls">Authority</label><input v-model="qualifyForm.authority" :class="inputCls" /></div>
                    <div><label :class="labelCls">Need summary</label><textarea v-model="qualifyForm.need_summary" rows="2" :class="inputCls" /></div>
                    <div><label :class="labelCls">Timeline</label><input v-model="qualifyForm.timeline" :class="inputCls" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label :class="labelCls">Next step</label><input v-model="qualifyForm.next_step" :class="inputCls" /></div>
                        <div><label :class="labelCls">Next step due</label><input v-model="qualifyForm.next_step_due" type="date" :class="inputCls" /></div>
                    </div>
                    <button :disabled="qualifyForm.processing" class="w-full rounded-xl bg-slate-900 py-3 text-xs font-black uppercase text-white hover:bg-slate-700 disabled:opacity-50">Save qualification</button>
                </form>
            </div>
        </Teleport>

        <!-- Send to pipeline -->
        <Teleport to="body">
            <div v-if="showPipeline && current" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showPipeline = false">
                <form @submit.prevent="submitPipeline" class="w-full max-w-sm space-y-4 rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                    <h3 class="font-black dark:text-white">Send to Pipeline</h3>
                    <p class="text-xs text-gray-400">Creates the deal in the first stage, linked to this lead. Further work happens in Opportunities.</p>
                    <div><label :class="labelCls">Deal title</label><input v-model="pipeForm.title" :class="inputCls" /></div>
                    <div><label :class="labelCls">Value (₱)</label><input v-model.number="pipeForm.value" type="number" min="0" :class="inputCls" /></div>
                    <p v-if="pipeForm.errors.error" class="text-[11px] font-bold text-rose-500">{{ pipeForm.errors.error }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="showPipeline = false" class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 dark:bg-zinc-800">Cancel</button>
                        <button :disabled="pipeForm.processing" class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase text-white hover:bg-indigo-700 disabled:opacity-50">Create deal</button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Convert to client -->
        <Teleport to="body">
            <div v-if="showConvert && current" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showConvert = false">
                <form @submit.prevent="submitConvert" class="max-h-[90vh] w-full max-w-md space-y-3 overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <h3 class="flex items-center gap-2 font-black dark:text-white"><Building2 class="h-4 w-4" /> Convert to Client</h3>
                        <button type="button" @click="showConvert = false"><X class="h-5 w-5 text-gray-400" /></button>
                    </div>
                    <div><label :class="labelCls">Company *</label><input v-model="conversionForm.company_name" required :class="inputCls" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label :class="labelCls">Contact *</label><input v-model="conversionForm.contact_person" required :class="inputCls" /></div>
                        <div><label :class="labelCls">Phone *</label><input v-model="conversionForm.phone" required :class="inputCls" /></div>
                    </div>
                    <div><label :class="labelCls">Email *</label><input v-model="conversionForm.email" type="email" required :class="inputCls" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label :class="labelCls">Business type *</label><input v-model="conversionForm.business_type" required :class="inputCls" /></div>
                        <div><label :class="labelCls">TIN *</label><input v-model="conversionForm.tin_number" required :class="inputCls" /></div>
                    </div>
                    <div><label :class="labelCls">Address *</label><input v-model="conversionForm.company_address" required :class="inputCls" /></div>
                    <div><label :class="labelCls">Portal password *</label><input v-model="conversionForm.password" required minlength="8" :class="inputCls" /></div>
                    <p v-if="conversionForm.errors.error" class="text-[11px] font-bold text-rose-500">{{ conversionForm.errors.error }}</p>
                    <button :disabled="conversionForm.processing" class="w-full rounded-xl bg-emerald-600 py-3 text-xs font-black uppercase text-white hover:bg-emerald-700 disabled:opacity-50">Convert</button>
                </form>
            </div>
        </Teleport>

        <!-- Mark lost -->
        <Teleport to="body">
            <div v-if="showLost && current" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showLost = false">
                <form @submit.prevent="submitLost" class="w-full max-w-sm space-y-4 rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                    <h3 class="font-black dark:text-white">Mark lost — {{ current.company_name }}</h3>
                    <div><label :class="labelCls">Reason *</label><input v-model="lostForm.reject_reason" required :class="inputCls" /></div>
                    <div class="flex gap-2">
                        <button type="button" @click="showLost = false" class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 dark:bg-zinc-800">Cancel</button>
                        <button :disabled="lostForm.processing" class="flex-1 rounded-xl bg-rose-600 py-2.5 text-xs font-black uppercase text-white hover:bg-rose-700 disabled:opacity-50">Mark lost</button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
