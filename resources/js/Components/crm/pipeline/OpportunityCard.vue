<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Star, Phone, CalendarDays, Bell, ClipboardCheck, Clock, UserCheck, X, Archive, Plus, Upload, Mail } from 'lucide-vue-next';
import {
    activityStateOf, STATE_META, initialsOf,
} from '@/composables/crm/usePipeline';

const props = defineProps({
    opportunity: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canUpload: { type: Boolean, default: false },
    isWon: { type: Boolean, default: false },
    isLost: { type: Boolean, default: false },
});
const emit = defineEmits(['schedule', 'open-activity', 'upload-docs']);

const o = computed(() => props.opportunity);
// Won/Lost are closed deals: no more meetings/calls, only finalize or archive.
const terminal = computed(() => props.isWon || props.isLost);
const state = computed(() => activityStateOf(o.value));
const meta = computed(() => STATE_META[state.value]);

// All planned activities on this deal (the board lets a deal hold several).
const scheduled = computed(() => o.value.scheduled || []);

const typeIcon = (t) => {
    if (t === 'call') return Phone;
    if (t === 'meeting') return CalendarDays;
    if (t === 'reminder') return Bell;
    return ClipboardCheck;
};

const shortDue = (v) =>
    v ? new Date(v).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) : 'no date';

const REQ_DOT = {
    pending: 'bg-slate-400',
    confirmed: 'bg-emerald-500',
    declined: 'bg-rose-500',
    reschedule: 'bg-amber-500',
};

const setPriority = (n) => {
    if (!props.canEdit) return;
    router.post(
        route('crm.opportunities.priority', o.value.id),
        { priority: n },
        { preserveScroll: true },
    );
};

// ── Won → business client account ──
const showAccount = ref(false);
const accountForm = useForm({
    company_name: '', contact_person: '', email: '', phone: '',
    business_type: '', tin_number: '', company_address: '', password: '',
});
const openAccount = () => {
    if (!props.canEdit) return;
    accountForm.reset();
    accountForm.clearErrors();
    acctWarnings.value = {};
    // Company = organization (lead/client company); Contact = person's name.
    // contact_name falls back to the company when no person is known — in
    // that case leave Contact empty instead of duplicating the company.
    const person = o.value.contact_name && o.value.contact_name !== '—' ? o.value.contact_name : '';
    accountForm.company_name = o.value.organization || '';
    accountForm.contact_person = person && person !== o.value.organization ? person : '';
    accountForm.email = o.value.email || '';
    accountForm.phone = (o.value.phone || '').replace(/\D/g, '').substring(0, 11);
    showAccount.value = true;
};

// Inline warnings for the account modal (auto-clears after 3s)
const acctWarnings = ref({});
let acctWarningTimeouts = {};
const warnAcct = (field, message) => {
    acctWarnings.value[field] = message;
    if (acctWarningTimeouts[field]) clearTimeout(acctWarningTimeouts[field]);
    acctWarningTimeouts[field] = setTimeout(() => {
        acctWarnings.value[field] = '';
    }, 3000);
};

// Letters + spaces only (contact person, business type)
const blockLettersAcct = (e, field) => {
    if (e.key.length === 1 && !/^[a-zA-Z\s]$/.test(e.key)) {
        e.preventDefault();
        warnAcct(field, 'Numbers and special characters are not allowed.');
    }
};

// Digits only (phone, TIN — dashes auto-inserted for TIN)
const blockDigitsAcct = (e, field) => {
    if (e.key.length === 1 && !/^\d$/.test(e.key)) {
        e.preventDefault();
        warnAcct(field, 'Letters and special characters are not allowed. Numbers only.');
    }
};

// Email-safe characters only
const blockEmailAcct = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9@.\-]$/.test(e.key)) {
        e.preventDefault();
        warnAcct('email', 'Invalid character. Only letters, numbers, @, ., and - are allowed.');
    }
};

// Address: letters, numbers, spaces, dots
const blockAddressAcct = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9\s.]$/.test(e.key)) {
        e.preventDefault();
        warnAcct('company_address', 'Only letters, numbers, spaces, and dots (.) are allowed.');
    }
};

// Paste/autofill/mobile-keyboard safety nets
watch(() => accountForm.contact_person, (val) => {
    const filtered = (val || '').replace(/[^a-zA-Z\s]/g, '');
    if (val !== filtered) accountForm.contact_person = filtered;
});
watch(() => accountForm.business_type, (val) => {
    const filtered = (val || '').replace(/[^a-zA-Z\s]/g, '');
    if (val !== filtered) accountForm.business_type = filtered;
});
watch(() => accountForm.phone, (val) => {
    const filtered = (val || '').replace(/\D/g, '').substring(0, 11);
    if (val !== filtered) accountForm.phone = filtered;
});
watch(() => accountForm.tin_number, (val) => {
    if (!val) return;
    const digits = val.replace(/\D/g, '').substring(0, 12);
    const formatted = digits.match(/.{1,3}/g)?.join('-') || '';
    if (val !== formatted) accountForm.tin_number = formatted;
});
watch(() => accountForm.email, (val) => {
    const filtered = (val || '').replace(/[^a-zA-Z0-9@.\-]/g, '');
    if (val !== filtered) accountForm.email = filtered;
});
watch(() => accountForm.company_address, (val) => {
    const filtered = (val || '').replace(/[^a-zA-Z0-9\s.]/g, '');
    if (val !== filtered) accountForm.company_address = filtered;
});

const submitAccount = () => {
    if (!/^\d{11}$/.test(accountForm.phone || '')) {
        warnAcct('phone', 'Phone number must be exactly 11 digits (e.g. 09171234567).');
        return;
    }
    if (!/^\d{12}$/.test((accountForm.tin_number || '').replace(/\D/g, ''))) {
        warnAcct('tin_number', 'TIN must be exactly 12 digits (e.g. 098-678-345-123).');
        return;
    }
    accountForm.post(route('crm.opportunities.convert', o.value.id), {
        preserveScroll: true,
        onSuccess: () => { showAccount.value = false; },
    });
};

const inputCls = 'w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white';
const labelCls = 'mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400';

// ── Lost → confirm archive (deal marked totally failed, moves to Archives) ──
const confirmArchiving = ref(false);
const archiveDeal = () => {
    if (!props.canEdit) return;
    router.post(route('crm.opportunities.archive', o.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { confirmArchiving.value = false; },
    });
};
</script>

<template>
    <div
        class="cursor-pointer rounded-2xl border border-gray-100 bg-white p-3.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-900"
    >
        <Link :href="route('crm.opportunities.show', o.id)" class="block" :title="o.title">
            <p class="truncate text-sm font-black hover:text-indigo-600">{{ o.title }}</p>
        </Link>
        <p class="mt-0.5 truncate text-[11px] font-bold text-gray-400">
            {{ o.contact_name }}<span v-if="o.organization"> · {{ o.organization }}</span>
        </p>
        <p v-if="o.email" class="mt-0.5 flex items-center gap-1 text-[10px] font-bold text-gray-400" :title="o.email">
            <Mail class="h-3 w-3 shrink-0" />
            <span class="truncate">{{ o.email }}</span>
        </p>
        <p v-if="o.expected_close" class="mt-1.5 shrink-0 text-[10px] font-bold text-gray-400">
            closes {{ new Date(o.expected_close).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) }}
        </p>

        <!-- Scheduled activities/calls — click one to open its details, or add
             another. A deal can hold several meetings. Hidden for closed deals. -->
        <div v-if="!terminal" class="mt-1.5 space-y-1">
            <button
                v-for="act in scheduled"
                :key="act.id"
                @click.stop="emit('open-activity', { opportunity: o, activity: act })"
                class="flex w-full items-center gap-1.5 rounded-lg bg-slate-50 px-2 py-1.5 text-left text-[11px] font-bold text-slate-600 transition hover:bg-indigo-50 hover:text-indigo-700 dark:bg-zinc-800/60 dark:text-slate-300 dark:hover:bg-zinc-800"
                :title="`${act.summary || act.type} — click for details`"
            >
                <component :is="typeIcon(act.type)" class="h-3.5 w-3.5 shrink-0" />
                <span class="min-w-0 flex-1 truncate">{{ act.summary || act.type }}</span>
                <span class="shrink-0 text-[10px] font-bold text-gray-400">{{ shortDue(act.due) }}</span>
                <span
                    v-if="act.request_status"
                    class="h-1.5 w-1.5 shrink-0 rounded-full"
                    :class="REQ_DOT[act.request_status] || 'bg-slate-300'"
                    :title="`Client response: ${act.request_status}`"
                />
            </button>

            <button
                v-if="!scheduled.length"
                @click.stop="emit('schedule', o)"
                :class="['flex w-full items-center gap-1.5 truncate rounded-lg px-1.5 py-1 text-left text-[11px] font-bold transition hover:bg-slate-100 dark:hover:bg-zinc-800', meta.text]"
                title="No activity scheduled — click to schedule"
            >
                <Clock class="h-3.5 w-3.5 shrink-0" />
                <span class="truncate">No activity — click to schedule</span>
            </button>

            <button
                v-if="scheduled.length"
                @click.stop="emit('schedule', o)"
                class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-gray-200 py-1 text-[10px] font-black uppercase tracking-wide text-gray-400 transition hover:border-indigo-300 hover:text-indigo-500 dark:border-zinc-700"
                title="Schedule another call or meeting"
            >
                <Plus class="h-3 w-3" /> Set another schedule
            </button>
        </div>

        <!-- Stage-enabled uploads: files are linked to this deal's client -->
        <button
            v-if="canUpload && !terminal"
            @click.stop="emit('upload-docs', o)"
            class="mt-1.5 flex w-full items-center justify-center gap-1.5 rounded-lg bg-sky-50 py-1.5 text-[10px] font-black uppercase tracking-wide text-sky-600 transition hover:bg-sky-100 dark:bg-sky-900/20 dark:text-sky-300"
            title="Upload documents or images for this client"
        >
            <Upload class="h-3.5 w-3.5" /> Upload files / images
            <span v-if="(o.documents || []).length" class="rounded-full bg-sky-600 px-1.5 py-0.5 text-[9px] text-white">{{ o.documents.length }}</span>
        </button>

        <div class="mt-1.5 flex items-center justify-between border-t border-slate-50 pt-2 dark:border-zinc-800">
            <!-- Priority stars 0-3, clickable -->
            <div class="flex items-center gap-0.5" title="Priority — click to set">
                <button
                    v-for="n in [1, 2, 3]"
                    :key="n"
                    @click.stop="setPriority(n)"
                    :disabled="!canEdit"
                    :class="[
                        'transition hover:scale-125',
                        n <= (o.priority || 0) ? 'text-amber-400' : 'text-gray-200 dark:text-zinc-700',
                        canEdit ? '' : 'cursor-default',
                    ]"
                    :title="`Set priority ${n}`"
                    :aria-label="`Set priority ${n}`"
                >
                    <Star class="h-3.5 w-3.5" :fill="n <= (o.priority || 0) ? 'currentColor' : 'none'" />
                </button>
                <button
                    v-if="(o.priority || 0) > 0 && canEdit"
                    @click.stop="setPriority(0)"
                    class="ml-1 text-[9px] font-bold text-gray-300 hover:text-rose-400"
                    title="Clear priority"
                >
                    ✕
                </button>
            </div>

            <div class="flex items-center gap-1.5">
                <span
                    v-if="o.owner?.name"
                    :title="`Salesperson: ${o.owner.name}`"
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-[9px] font-black text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                >
                    {{ initialsOf(o.owner.name) }}
                </span>
                <span class="text-[10px] font-bold uppercase tracking-wide text-gray-300 dark:text-zinc-600">
                    {{ o.probability }}%
                </span>
            </div>
        </div>

        <!-- Won stage → create the business client account -->
        <button
            v-if="isWon && canEdit && !o.client_id"
            @click.stop="openAccount"
            class="mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 py-2 text-[10px] font-black uppercase tracking-wide text-white transition hover:bg-emerald-700 active:scale-95"
        >
            <UserCheck class="h-3.5 w-3.5" /> Create client account
        </button>
        <p
            v-if="isWon && o.client_id"
            class="mt-2 rounded-xl bg-emerald-50 py-1.5 text-center text-[10px] font-black uppercase tracking-wide text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300"
        >
            Client account created
        </p>

        <!-- Lost stage → archive the deal as totally failed -->
        <button
            v-if="isLost && canEdit"
            @click.stop="confirmArchiving = true"
            class="mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-rose-600 py-2 text-[10px] font-black uppercase tracking-wide text-white transition hover:bg-rose-700 active:scale-95"
        >
            <Archive class="h-3.5 w-3.5" /> Archive deal
        </button>

        <!-- Account creation modal (kept inside the single card root so the
             draggable marker attribute lands on the card and grabs work) -->
        <Teleport to="body">
            <div v-if="showAccount" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showAccount = false">
                <form @submit.prevent="submitAccount" class="max-h-[90vh] w-full max-w-md space-y-3 overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <h3 class="flex items-center gap-2 font-black dark:text-white"><UserCheck class="h-4 w-4" /> Client account — {{ o.title }}</h3>
                        <button type="button" @click="showAccount = false"><X class="h-5 w-5 text-gray-400" /></button>
                    </div>
                    <div><label :class="labelCls">Company *</label><input v-model="accountForm.company_name" required :class="inputCls" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label :class="labelCls">Contact *</label>
                            <input v-model="accountForm.contact_person" required :class="inputCls" @keypress="blockLettersAcct($event, 'contact_person')" />
                            <p v-if="acctWarnings.contact_person" class="mt-1 text-[11px] font-bold text-rose-500">{{ acctWarnings.contact_person }}</p>
                        </div>
                        <div>
                            <label :class="labelCls">Phone * (11 digits)</label>
                            <input v-model="accountForm.phone" required :class="inputCls" type="tel" inputmode="numeric" maxlength="11" minlength="11" placeholder="09171234567" @keypress="blockDigitsAcct($event, 'phone')" />
                            <p v-if="acctWarnings.phone" class="mt-1 text-[11px] font-bold text-rose-500">{{ acctWarnings.phone }}</p>
                            <p :class="accountForm.phone.length === 11 ? 'text-emerald-500' : 'text-gray-400'" class="mt-1 text-[11px] font-bold transition-colors">{{ accountForm.phone.length }} / 11 digits</p>
                        </div>
                    </div>
                    <div>
                        <label :class="labelCls">Email *</label>
                        <input v-model="accountForm.email" type="email" required :class="inputCls" @keypress="blockEmailAcct" />
                        <p v-if="acctWarnings.email" class="mt-1 text-[11px] font-bold text-rose-500">{{ acctWarnings.email }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label :class="labelCls">Business type *</label>
                            <input v-model="accountForm.business_type" required placeholder="e.g. Retailer" :class="inputCls" @keypress="blockLettersAcct($event, 'business_type')" />
                            <p v-if="acctWarnings.business_type" class="mt-1 text-[11px] font-bold text-rose-500">{{ acctWarnings.business_type }}</p>
                        </div>
                        <div>
                            <label :class="labelCls">TIN *</label>
                            <input v-model="accountForm.tin_number" required placeholder="000-000-000-000" maxlength="15" :class="inputCls" @keypress="blockDigitsAcct($event, 'tin_number')" />
                            <p v-if="acctWarnings.tin_number" class="mt-1 text-[11px] font-bold text-rose-500">{{ acctWarnings.tin_number }}</p>
                        </div>
                    </div>
                    <div>
                        <label :class="labelCls">Address *</label>
                        <input v-model="accountForm.company_address" required :class="inputCls" @keypress="blockAddressAcct" />
                        <p v-if="acctWarnings.company_address" class="mt-1 text-[11px] font-bold text-rose-500">{{ acctWarnings.company_address }}</p>
                    </div>
                    <div><label :class="labelCls">Portal password *</label><input v-model="accountForm.password" required minlength="8" placeholder="Min. 8 characters" :class="inputCls" /></div>
                    <p v-if="accountForm.errors.error" class="text-[11px] font-bold text-rose-500">{{ accountForm.errors.error }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="showAccount = false" class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 dark:bg-zinc-800">Cancel</button>
                        <button :disabled="accountForm.processing" class="flex-1 rounded-xl bg-emerald-600 py-2.5 text-xs font-black uppercase text-white hover:bg-emerald-700 disabled:opacity-50">Create account</button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Archive confirmation (single card root so drag markers stay intact) -->
        <Teleport to="body">
            <div v-if="confirmArchiving" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="confirmArchiving = false">
                <div class="w-full max-w-sm space-y-4 rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300">
                            <Archive class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="font-black dark:text-white">Archive this deal?</h3>
                            <p class="mt-1 text-xs text-gray-400">
                                “{{ o.title }}” will be marked as <strong>totally failed</strong>, removed from the Lost stage and moved to Archives.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="confirmArchiving = false" class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 hover:bg-gray-200 dark:bg-zinc-800">Cancel</button>
                        <button type="button" @click="archiveDeal" class="flex-1 rounded-xl bg-rose-600 py-2.5 text-xs font-black uppercase text-white hover:bg-rose-700">Archive</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
