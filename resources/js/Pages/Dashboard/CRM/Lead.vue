<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TemplateWidget from '@/Components/crm/TemplateWidget.vue';
import { Head } from '@inertiajs/vue3';
import {
    Plus, X, Search, Inbox, ArrowRight,
    Ban, Mail, FileSearch, Flag, StickyNote, Paperclip, Trash2,
} from 'lucide-vue-next';

// Intake inbox: new leads live here, deals are worked in Opportunities.
// Negotiation/approvals/close happen on the pipeline — never duplicated here.
const props = defineProps({
    leads: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({}) },
});
const canEdit = computed(() => props.permissions?.leads === 'edit');

const searchQuery = ref('');
const inPipeline = (l) => (l.opportunities_count || 0) > 0 || (l.opportunities?.length || 0) > 0;

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
const lostLeads = computed(() => filtered.value.filter((l) => !inPipeline(l) && l.status === 'Lost'));

// ── Tabs: New / Lost (pipeline deals live only in Opportunities) ──
const activeTab = ref('new');

// ── New lead ──
const showCreate = ref(false);
const form = useForm({
    company_name: '', contact_person: '', email: '', phone: '',
});

// Inline warnings for the New Lead modal (auto-clears after 3s)
const createWarnings = ref({});
let createWarningTimeouts = {};
const warnCreate = (field, message) => {
    createWarnings.value[field] = message;
    if (createWarningTimeouts[field]) clearTimeout(createWarningTimeouts[field]);
    createWarningTimeouts[field] = setTimeout(() => {
        createWarnings.value[field] = '';
    }, 3000);
};

// Letters + spaces only (contact person)
const blockNumbersAndSpecialCreate = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z\s]$/.test(e.key)) {
        e.preventDefault();
        warnCreate('contact_person', 'Numbers and special characters are not allowed.');
    }
};

// Digits only, max 11 (phone, e.g. 09171234567)
const blockNonNumericCreate = (e) => {
    if (e.key.length === 1 && !/^\d$/.test(e.key)) {
        e.preventDefault();
        warnCreate('phone', 'Letters and special characters are not allowed. Numbers only.');
    }
};

// Email-safe characters only
const blockSpecialForEmailCreate = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9@.\-]$/.test(e.key)) {
        e.preventDefault();
        warnCreate('email', 'Invalid character. Only letters, numbers, @, ., and - are allowed.');
    }
};

// Paste/autofill/mobile-keyboard safety nets
watch(() => form.contact_person, (val) => {
    const filtered = (val || '').replace(/[^a-zA-Z\s]/g, '');
    if (val !== filtered) form.contact_person = filtered;
});
watch(() => form.phone, (val) => {
    const filtered = (val || '').replace(/\D/g, '').substring(0, 11);
    if (val !== filtered) form.phone = filtered;
});
watch(() => form.email, (val) => {
    const filtered = (val || '').replace(/[^a-zA-Z0-9@.\-]/g, '');
    if (val !== filtered) form.email = filtered;
    if (form.errors.email_conflict) form.clearErrors('email_conflict');
});

const submitCreate = () => {
    if (!/^\d{11}$/.test(form.phone || '')) {
        warnCreate('phone', 'Phone number must be exactly 11 digits (e.g. 09171234567).');
        return;
    }
    form.post(route('crm.lead.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; form.reset(); createWarnings.value = {}; },
    });
};

// ── Send to pipeline (confirm → deal lands in Opportunities' New stage) ──
const showPipeline = ref(false);
const current = ref(null);
const pipeForm = useForm({});
const openPipeline = (lead) => {
    if (!canEdit.value) return;
    current.value = lead;
    pipeForm.clearErrors();
    showPipeline.value = true;
};
const submitPipeline = () => {
    pipeForm.post(route('crm.lead.pipeline', current.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPipeline.value = false;
            router.visit(route('crm.opportunities'));
        },
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

const inputCls = 'w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm text-gray-900 outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white';
const labelCls = 'mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400';

// ── Investigation log: notes / flags / files gathered before the pipeline ──
const showInvestigation = ref(false);
const investigationId = ref(null);
const investigationLead = computed(() =>
    (props.leads || []).find((l) => l.id === investigationId.value) || null,
);
const investigations = computed(() => investigationLead.value?.investigations || []);

const noteForm = useForm({ type: 'note', note: '' });
const fileForm = useForm({ files: [] });
const fileInput = ref(null);
const imagePreview = ref(null);

const openInvestigation = (lead) => {
    investigationId.value = lead.id;
    noteForm.reset();
    noteForm.clearErrors();
    noteForm.type = 'note';
    fileForm.reset();
    fileForm.clearErrors();
    if (fileInput.value) fileInput.value.value = '';
    imagePreview.value = null;
    showInvestigation.value = true;
};

const submitNote = () => {
    if (!noteForm.note.trim()) return;
    noteForm.post(route('crm.lead.investigations.store', investigationId.value), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => noteForm.reset('note'),
    });
};

const onFilePick = (e) => {
    fileForm.files = Array.from(e.target.files || []);
};

const submitFiles = () => {
    if (!fileForm.files.length) return;
    fileForm.post(route('crm.lead.investigations.files', investigationId.value), {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: () => {
            fileForm.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const removeInvestigation = (entry) => {
    router.delete(route('crm.lead.investigations.destroy', entry.id), {
        preserveScroll: true,
        preserveState: true,
    });
};

const whenLabel = (v) =>
    v ? new Date(v).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';
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
                            <p class="text-sm text-blue-100/90">New leads live here — deals are worked in <Link :href="route('crm.opportunities')" class="font-bold underline">Opportunities</Link>.</p>
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

                <!-- Tabs -->
                <div class="flex gap-1 rounded-2xl border border-gray-100 bg-white/70 p-1.5 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/70">
                    <button
                        @click="activeTab = 'new'"
                        :class="activeTab === 'new' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-zinc-800'"
                        class="flex-1 rounded-xl px-4 py-2 text-[11px] font-black uppercase tracking-widest transition"
                    >
                        New ({{ freshLeads.length }})
                    </button>
                    <button
                        @click="activeTab = 'lost'"
                        :class="activeTab === 'lost' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-zinc-800'"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl px-4 py-2 text-[11px] font-black uppercase tracking-widest transition"
                    >
                        <Ban class="h-3.5 w-3.5" /> Lost ({{ lostLeads.length }})
                    </button>
                </div>

                <!-- Fresh leads -->
                <section v-if="activeTab === 'new'">
                    <h2 class="mb-3 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                        New — needs qualification ({{ freshLeads.length }})
                    </h2>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div
                            v-for="l in freshLeads"
                            :key="l.id"
                            class="rounded-3xl border border-gray-100 bg-white/70 p-5 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/70"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-black dark:text-white">{{ l.company_name }}</p>
                                <p class="text-xs text-gray-400">{{ l.contact_person }} · {{ l.email }} · {{ l.phone }}</p>
                            </div>
                            <p v-if="l.interest_fabric" class="mt-1 text-xs font-bold text-gray-500">Interested: {{ l.interest_fabric }}</p>
                            <p v-if="l.need_summary || l.next_step" class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-gray-500 dark:bg-zinc-800 dark:text-slate-300">
                                <span v-if="l.need_summary">Need: {{ l.need_summary }}</span>
                                <span v-if="l.next_step" class="block">Next: {{ l.next_step }}{{ l.next_step_due ? ` (due ${l.next_step_due})` : '' }}</span>
                            </p>
                            <p class="mt-1 text-[10px] text-gray-300">Assigned: {{ l.assigned_staff?.name ?? '—' }}</p>
                            <div v-if="canEdit" class="mt-3 flex flex-wrap gap-1.5">
                                <button @click="openPipeline(l)" class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-[10px] font-black uppercase text-white hover:bg-indigo-700">
                                    To Pipeline <ArrowRight class="h-3 w-3" />
                                </button>
                                <button @click="openInvestigation(l)" class="inline-flex items-center gap-1 rounded-lg bg-amber-500 px-3 py-1.5 text-[10px] font-black uppercase text-white hover:bg-amber-600">
                                    <FileSearch class="h-3 w-3" /> Investigation
                                    <span v-if="(l.investigations || []).length" class="rounded-full bg-white/25 px-1.5 py-0.5 text-[9px]">{{ l.investigations.length }}</span>
                                </button>
                                <button @click="openLost(l)" class="rounded-lg px-3 py-1.5 text-[10px] font-black uppercase text-gray-300 hover:bg-rose-50 hover:text-rose-500">Lost</button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!freshLeads.length" class="rounded-2xl border border-dashed border-gray-200 py-8 text-center text-xs font-bold text-gray-300">Inbox zero — every lead is qualified or in the pipeline.</p>
                </section>

                <!-- Lost -->
                <section v-if="activeTab === 'lost'">
                    <h2 class="mb-3 flex items-center gap-1.5 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                        <Ban class="h-3.5 w-3.5" /> Lost ({{ lostLeads.length }})
                    </h2>
                    <p v-if="!lostLeads.length" class="rounded-2xl border border-dashed border-gray-200 py-8 text-center text-xs font-bold text-gray-300">No lost leads.</p>
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
                        <div>
                            <label :class="labelCls">Contact person *</label>
                            <input v-model="form.contact_person" required :class="inputCls" @keypress="blockNumbersAndSpecialCreate" />
                            <p v-if="createWarnings.contact_person" class="mt-1 text-[11px] font-bold text-rose-500">{{ createWarnings.contact_person }}</p>
                        </div>
                        <div>
                            <label :class="labelCls">Phone * (11 digits)</label>
                            <input v-model="form.phone" required :class="inputCls" type="tel" inputmode="numeric" maxlength="11" minlength="11" placeholder="09171234567" @keypress="blockNonNumericCreate" />
                            <p v-if="createWarnings.phone" class="mt-1 text-[11px] font-bold text-rose-500">{{ createWarnings.phone }}</p>
                            <p :class="form.phone.length === 11 ? 'text-emerald-500' : 'text-gray-400'" class="mt-1 text-[11px] font-bold transition-colors">{{ form.phone.length }} / 11 digits</p>
                        </div>
                    </div>
                    <div>
                        <label :class="labelCls">Email *</label>
                        <input v-model="form.email" type="email" required :class="inputCls" @keypress="blockSpecialForEmailCreate" />
                        <p v-if="createWarnings.email" class="mt-1 text-[11px] font-bold text-rose-500">{{ createWarnings.email }}</p>
                    </div>
                    <p v-if="form.errors.error" class="text-[11px] font-bold text-rose-500">{{ form.errors.error }}</p>
                    <button :disabled="form.processing" class="w-full rounded-xl bg-indigo-600 py-3 text-xs font-black uppercase text-white hover:bg-indigo-700 disabled:opacity-50">Add to inbox</button>
                </form>
            </div>
        </Teleport>

        <!-- Duplicate email: friendly modal instead of a raw SQL error -->
        <Teleport to="body">
            <div v-if="form.errors.email_conflict" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="form.clearErrors('email_conflict')">
                <div class="w-full max-w-sm space-y-4 rounded-3xl bg-white p-6 text-center shadow-2xl dark:bg-zinc-900">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300">
                        <Mail class="h-6 w-6" />
                    </div>
                    <h3 class="font-black dark:text-white">Email already in use</h3>
                    <p class="text-xs text-gray-400">{{ form.errors.email_conflict }}</p>
                    <button type="button" @click="form.clearErrors('email_conflict')" class="w-full rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase text-white hover:bg-indigo-700">Got it</button>
                </div>
            </div>
        </Teleport>

        <!-- Send to pipeline (confirmation — no deal fields) -->
        <Teleport to="body">
            <div v-if="showPipeline && current" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showPipeline = false">
                <form @submit.prevent="submitPipeline" class="w-full max-w-sm space-y-4 rounded-3xl bg-white p-6 text-center shadow-2xl dark:bg-zinc-900">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                        <ArrowRight class="h-6 w-6" />
                    </div>
                    <h3 class="font-black dark:text-white">Send to Pipeline?</h3>
                    <p class="text-xs text-gray-400">Are you sure you want to send <span class="font-black text-gray-600 dark:text-slate-200">{{ current.company_name }}</span> to the pipeline? It will appear as a <span class="font-black">New</span> deal in Opportunities.</p>
                    <p v-if="pipeForm.errors.error" class="text-[11px] font-bold text-rose-500">{{ pipeForm.errors.error }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="showPipeline = false" class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 dark:bg-zinc-800">Cancel</button>
                        <button :disabled="pipeForm.processing" class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase text-white hover:bg-indigo-700 disabled:opacity-50">Confirm</button>
                    </div>
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

        <!-- Investigation log — document the prospect before the pipeline -->
        <Teleport to="body">
            <div v-if="showInvestigation && investigationLead" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showInvestigation = false">
                <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                    <!-- Header -->
                    <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-zinc-800">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300">
                            <FileSearch class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-black text-gray-900 dark:text-white">Investigation — {{ investigationLead.company_name }}</h3>
                            <p class="truncate text-[11px] font-bold text-gray-500 dark:text-slate-400">Notes, flags &amp; files gathered before this lead enters the pipeline.</p>
                        </div>
                        <button type="button" @click="showInvestigation = false"><X class="h-5 w-5 text-gray-400" /></button>
                    </div>

                    <!-- Compose -->
                    <div class="space-y-3 border-b border-gray-100 px-6 py-4 dark:border-zinc-800">
                        <div class="flex gap-1.5">
                            <button type="button" @click="noteForm.type = 'note'"
                                :class="noteForm.type === 'note' ? 'bg-slate-700 text-white' : 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-slate-300'"
                                class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-[10px] font-black uppercase tracking-wide transition">
                                <StickyNote class="h-3.5 w-3.5" /> Note
                            </button>
                            <button type="button" @click="noteForm.type = 'flag'"
                                :class="noteForm.type === 'flag' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-slate-300'"
                                class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-[10px] font-black uppercase tracking-wide transition">
                                <Flag class="h-3.5 w-3.5" /> Flag
                            </button>
                        </div>
                        <textarea v-model="noteForm.note" rows="3"
                            :placeholder="noteForm.type === 'flag' ? 'What should the team watch out for?' : 'Document your findings on this prospect…'"
                            :class="inputCls" class="resize-none"></textarea>
                        <p v-if="noteForm.errors.note" class="text-[11px] font-bold text-rose-500">{{ noteForm.errors.note }}</p>
                        <div class="flex justify-end">
                            <button type="button" @click="submitNote" :disabled="noteForm.processing || !noteForm.note.trim()"
                                class="rounded-xl bg-indigo-600 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-white transition hover:bg-indigo-700 disabled:opacity-50">
                                {{ noteForm.type === 'flag' ? 'Add flag' : 'Add note' }}
                            </button>
                        </div>

                        <!-- Files / images -->
                        <div class="flex flex-wrap items-center gap-2 rounded-2xl bg-slate-50 p-3 dark:bg-zinc-800/60">
                            <input ref="fileInput" type="file" multiple @change="onFilePick"
                                accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip"
                                class="flex-1 text-[11px] text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-[10px] file:font-black file:uppercase file:text-gray-500 dark:file:bg-zinc-700" />
                            <button type="button" @click="submitFiles" :disabled="fileForm.processing || !fileForm.files.length"
                                class="inline-flex items-center gap-1 rounded-xl bg-sky-600 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-white transition hover:bg-sky-700 disabled:opacity-50">
                                <Paperclip class="h-3.5 w-3.5" /> Upload
                            </button>
                        </div>
                        <p v-if="fileForm.errors.files" class="text-[11px] font-bold text-rose-500">{{ fileForm.errors.files }}</p>
                        <p v-else-if="fileForm.errors['files.0']" class="text-[11px] font-bold text-rose-500">{{ fileForm.errors['files.0'] }}</p>
                    </div>

                    <!-- Timeline -->
                    <div class="flex-1 space-y-2 overflow-y-auto px-6 py-4">
                        <p v-if="!investigations.length" class="rounded-2xl border border-dashed border-gray-200 py-8 text-center text-xs font-bold text-gray-400 dark:border-zinc-700 dark:text-slate-500">No investigation entries yet — document your findings above.</p>
                        <div v-for="entry in investigations" :key="entry.id"
                            class="group flex gap-3 rounded-2xl border border-gray-100 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl"
                                :class="entry.type === 'flag' ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/30' : entry.type === 'file' ? 'bg-sky-100 text-sky-600 dark:bg-sky-900/30' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800'">
                                <Flag v-if="entry.type === 'flag'" class="h-4 w-4" />
                                <Paperclip v-else-if="entry.type === 'file'" class="h-4 w-4" />
                                <StickyNote v-else class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black uppercase tracking-wide"
                                        :class="entry.type === 'flag' ? 'text-amber-600' : entry.type === 'file' ? 'text-sky-600' : 'text-slate-500'">
                                        {{ entry.type === 'file' ? 'File' : entry.type }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 dark:text-slate-500">{{ whenLabel(entry.created_at) }}</span>
                                </div>

                                <!-- Note / flag body -->
                                <p v-if="entry.type !== 'file'" class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-700 dark:text-slate-200">{{ entry.note }}</p>

                                <!-- File -->
                                <template v-else>
                                    <button v-if="entry.is_image" type="button" @click="imagePreview = entry.file_url"
                                        class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:underline">
                                        <Paperclip class="h-3.5 w-3.5" /> {{ entry.original_name }}
                                    </button>
                                    <a v-else :href="entry.file_url" target="_blank" class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:underline">
                                        <Paperclip class="h-3.5 w-3.5" /> {{ entry.original_name }}
                                    </a>
                                    <button v-if="entry.is_image" type="button" @click="imagePreview = entry.file_url" class="mt-2 block" title="Click to enlarge">
                                        <img :src="entry.file_url" :alt="entry.original_name" class="max-h-40 rounded-xl border border-gray-100 object-contain dark:border-zinc-800" />
                                    </button>
                                </template>

                                <p class="mt-1 text-[10px] font-bold text-gray-400 dark:text-slate-500">by {{ entry.user?.name || 'System' }}</p>
                            </div>
                            <button type="button" @click="removeInvestigation(entry)"
                                class="h-6 w-6 shrink-0 self-start rounded-lg text-gray-400 opacity-0 transition hover:bg-rose-50 hover:text-rose-500 group-hover:opacity-100"
                                title="Remove entry">
                                <Trash2 class="mx-auto h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Image lightbox — larger view without leaving the investigation modal -->
        <Teleport to="body">
            <div v-if="imagePreview" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm" @click.self="imagePreview = null">
                <button type="button" @click="imagePreview = null"
                    class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white transition hover:rotate-90 hover:bg-white/30">
                    <X class="h-5 w-5" />
                </button>
                <img :src="imagePreview" alt="Image preview" class="max-h-[88vh] max-w-[92vw] rounded-2xl object-contain shadow-2xl" />
            </div>
        </Teleport>

        <TemplateWidget page-key="leads" />
    </AuthenticatedLayout>
</template>
