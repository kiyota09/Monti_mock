<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, Star } from 'lucide-vue-next';
import ModalShell from './ModalShell.vue';

// Quick-create: Organization/Contact autocomplete (autofills email+phone),
// title, revenue, priority. Add / Add & New / Discard.
const props = defineProps({
    stage: { type: Object, required: true },
    contacts: { type: Array, default: () => [] },
});
const emit = defineEmits(['close']);

const contactQuery = ref('');
const pickedContact = ref(null);
const showSuggestions = ref(false);

const suggestions = computed(() => {
    const q = contactQuery.value.trim().toLowerCase();
    if (!q) return [];
    return (props.contacts || [])
        .filter(
            (c) =>
                (c.name || '').toLowerCase().includes(q) ||
                (c.organization || '').toLowerCase().includes(q),
        )
        .slice(0, 6);
});

const pick = (c) => {
    pickedContact.value = c;
    contactQuery.value = c.organization ? `${c.name} — ${c.organization}` : c.name;
    form.email = c.email || '';
    form.phone = c.phone || '';
    showSuggestions.value = false;
};
const onContactInput = () => {
    pickedContact.value = null;
    showSuggestions.value = true;
};
const clearContact = () => {
    pickedContact.value = null;
    contactQuery.value = '';
    form.email = '';
    form.phone = '';
};

const form = useForm({
    title: '',
    value: null,
    priority: 0,
    contact_id: null,
    contact_name: '',
    organization: '',
    email: '',
    phone: '',
    stage_id: props.stage.id,
});

watch(
    () => props.stage.id,
    (id) => {
        form.stage_id = id;
    },
);

const fillContactPayload = () => {
    form.contact_id = pickedContact.value?.id ?? null;
    if (!pickedContact.value) {
        const [namePart, ...orgParts] = contactQuery.value.split('—').map((s) => s.trim());
        form.contact_name = namePart || '';
        form.organization = orgParts.join(' — ');
    } else {
        form.contact_name = '';
        form.organization = '';
    }
};

const resetAll = () => {
    form.reset();
    clearContact();
};

const submit = (andNew = false) => {
    if (!contactQuery.value.trim() || !form.title.trim()) {
        form.setError('contact', !contactQuery.value.trim() ? 'Pick or type a contact.' : '');
        form.setError('title', !form.title.trim() ? 'Give the deal a title.' : '');
        return;
    }
    form.clearErrors();
    fillContactPayload();
    form.post(route('crm.opportunities.store'), {
        preserveScroll: true,
        onSuccess: () => {
            if (andNew) {
                const stageId = form.stage_id;
                resetAll();
                form.stage_id = stageId;
            } else {
                emit('close');
            }
        },
    });
};

const discard = () => {
    resetAll();
    emit('close');
};

const canSubmit = computed(() => !form.processing && contactQuery.value.trim() && form.title.trim());
</script>

<template>
    <ModalShell size="md" @close="discard">
        <template #header>
            <div class="flex items-center justify-between bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-6 py-4 text-white">
                <div>
                    <h3 class="font-black">New Opportunity</h3>
                    <p class="text-xs text-blue-100">
                        in <span class="rounded-full bg-white/20 px-2 py-0.5 font-bold">{{ stage.name }}</span>
                    </p>
                </div>
                <button @click="discard" aria-label="Discard and close"><X class="h-5 w-5" /></button>
            </div>
        </template>

        <form @submit.prevent="submit(false)" class="space-y-4 p-6">
            <div class="relative">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="qc-contact">Organization / Contact *</label>
                <div class="relative">
                    <input
                        id="qc-contact"
                        v-model="contactQuery"
                        @input="onContactInput"
                        @focus="showSuggestions = true"
                        @keydown.escape="showSuggestions = false"
                        placeholder="Type to search, or enter a new name…"
                        autocomplete="off"
                        autofocus
                        class="w-full rounded-xl bg-gray-50 px-4 py-2.5 pr-9 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white"
                    />
                    <button
                        v-if="contactQuery"
                        type="button"
                        @click="clearContact"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-gray-300 hover:bg-gray-200 hover:text-gray-500"
                        title="Clear contact"
                        aria-label="Clear contact"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div
                    v-if="showSuggestions && suggestions.length"
                    class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-gray-100 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
                    role="listbox"
                >
                    <button
                        v-for="c in suggestions"
                        :key="c.id"
                        type="button"
                        @click="pick(c)"
                        class="block w-full px-4 py-2 text-left text-sm transition hover:bg-indigo-50 dark:hover:bg-zinc-700"
                    >
                        <span class="font-bold dark:text-white">{{ c.name }}</span>
                        <span v-if="c.organization" class="text-gray-400"> — {{ c.organization }}</span>
                        <span class="block text-[11px] text-gray-400">{{ c.email }} · {{ c.phone }}</span>
                    </button>
                </div>
                <p v-if="form.errors.contact" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.contact }}</p>
            </div>
            <div v-if="!pickedContact" class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="qc-email">Email (new contact)</label>
                    <input id="qc-email" v-model="form.email" type="email" placeholder="name@company.com" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="qc-phone">Phone (new contact)</label>
                    <input id="qc-phone" v-model="form.phone" placeholder="+63…" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                </div>
            </div>
            <p v-else class="rounded-xl bg-emerald-50 px-3 py-2 text-[11px] font-bold text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">
                ✓ {{ form.email || 'no email' }} · {{ form.phone || 'no phone' }} (auto-filled)
            </p>
            <div>
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="qc-title">Opportunity title *</label>
                <input id="qc-title" v-model="form.title" placeholder="e.g. Abby wants 200 lamps" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white" />
                <p v-if="form.errors.title" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.title }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="qc-value">Expected revenue (₱) *</label>
                    <input id="qc-value" v-model.number="form.value" type="number" min="0" step="0.01" placeholder="2000" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Priority</label>
                    <div class="flex items-center gap-1 pt-2" role="radiogroup" aria-label="Priority">
                        <button v-for="n in [1, 2, 3]" :key="n" type="button" @click="form.priority = n" :class="n <= form.priority ? 'text-amber-400' : 'text-gray-200 dark:text-zinc-700'" class="transition hover:scale-125" :title="`${n} star${n > 1 ? 's' : ''}`">
                            <Star class="h-5 w-5" :fill="n <= form.priority ? 'currentColor' : 'none'" />
                        </button>
                        <button v-if="form.priority > 0" type="button" @click="form.priority = 0" class="ml-1 text-[10px] font-bold text-gray-300 hover:text-rose-400" title="Clear priority">✕</button>
                    </div>
                </div>
            </div>
            <p v-if="form.errors.value" class="text-[11px] font-bold text-rose-500">{{ form.errors.value }}</p>
            <div class="flex gap-2 pt-1">
                <button type="button" @click="discard" class="flex-1 rounded-xl bg-gray-100 py-3 text-xs font-black uppercase text-gray-500 transition hover:bg-gray-200 dark:bg-zinc-800">Discard</button>
                <button type="button" @click="submit(true)" :disabled="!canSubmit" class="flex-1 rounded-xl bg-indigo-100 py-3 text-xs font-black uppercase text-indigo-700 transition hover:bg-indigo-200 disabled:opacity-50 dark:bg-indigo-900/30 dark:text-indigo-300" title="Save and start another in this stage">Add &amp; New</button>
                <button type="submit" :disabled="!canSubmit" class="flex-1 rounded-xl bg-indigo-600 py-3 text-xs font-black uppercase text-white transition hover:bg-indigo-700 disabled:opacity-50">Add</button>
            </div>
        </form>
    </ModalShell>
</template>
