<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, Video, Check } from 'lucide-vue-next';
import ModalShell from './ModalShell.vue';
import CalendarPicker from './CalendarPicker.vue';
import { toLocalInput } from '@/composables/crm/usePipeline';

// Schedule Activity: type (Call | Meeting), optional meeting link for calls,
// summary, due date (inline calendar), assigned-to and notes.
// Footer: Schedule | Cancel.
const props = defineProps({
    // Either an opportunity (pipeline card) OR a client (Approval card).
    opportunity: { type: Object, default: null },
    client: { type: Object, default: null },
    salespeople: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
    existing: { type: Object, default: null }, // view an existing activity (read-only unless planned)
    // Seeds the form (used when re-scheduling a client's reschedule request).
    prefill: { type: Object, default: null },
});
const emit = defineEmits(['close', 'scheduled']);

// Only two activity types are offered here.
const TYPES = [
    { key: 'call', label: 'Call' },
    { key: 'meeting', label: 'Meeting' },
];

const subjectTitle = computed(
    () => props.opportunity?.title || props.client?.company_name || 'Client',
);

const form = useForm({
    opportunity_id: props.opportunity?.id ?? null,
    client_id: props.client?.id ?? null,
    type: props.prefill?.type ?? props.existing?.type ?? 'call',
    meeting_link: props.prefill?.meeting_link ?? props.existing?.meeting_link ?? '',
    summary: props.prefill?.summary ?? props.existing?.summary ?? '',
    due_date: toLocalInput(props.prefill?.due ?? props.existing?.due),
    assigned_to: props.prefill?.assigned_to ?? props.existing?.assigned_to ?? props.currentUserId ?? null,
    notes: props.prefill?.notes ?? props.existing?.notes ?? '',
});

const typeLabel = computed(() => TYPES.find((t) => t.key === form.type)?.label ?? form.type);
const isReadonly = computed(() => !!props.existing && props.existing.status !== 'planned');
const summaryBlank = computed(() => !form.summary || !String(form.summary).trim());

const dueDisplay = computed(() =>
    form.due_date
        ? new Date(form.due_date).toLocaleString('en-PH', {
              weekday: 'short',
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          })
        : 'No slot chosen yet',
);

const justSet = ref(false);
let justSetTimer = null;
const applySlot = ({ at }) => {
    form.due_date = toLocalInput(at);
    // Flash a confirmation so the user knows the date & time were saved.
    justSet.value = true;
    clearTimeout(justSetTimer);
    justSetTimer = setTimeout(() => {
        justSet.value = false;
    }, 4000);
};

const schedule = () => {
    if (summaryBlank.value) {
        form.setError('summary', 'Give the activity a summary first.');
        return;
    }
    form.clearErrors('summary');
    form.post(route('crm.activities.store'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('scheduled');
            emit('close');
        },
    });
};

// reset the meeting link when switching away from a call
const onTypeChange = () => {
    if (form.type !== 'call') form.meeting_link = '';
};
</script>

<template>
    <ModalShell size="lg" @close="emit('close')">
        <template #header>
            <div class="flex items-center justify-between bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-6 py-4 text-white">
                <div class="min-w-0">
                    <h3 class="font-black">{{ existing ? 'Activity' : 'Schedule Activity' }}</h3>
                    <p class="truncate text-xs text-blue-100">{{ subjectTitle }} · <span class="capitalize">{{ typeLabel }}</span></p>
                </div>
                <button @click="emit('close')" aria-label="Close"><X class="h-5 w-5 shrink-0" /></button>
            </div>
        </template>

        <div class="space-y-4 p-6">
            <div v-if="existing" class="rounded-xl bg-slate-50 px-4 py-3 text-xs dark:bg-zinc-800 dark:text-slate-200">
                <p><strong>Type:</strong> <span class="capitalize">{{ existing.type }}</span> · <strong>Status:</strong> {{ existing.status }}</p>
                <p v-if="existing.meeting_link" class="mt-1 break-all text-gray-500">Link: {{ existing.meeting_link }}</p>
                <p v-if="existing.due" class="text-gray-400">Due {{ new Date(existing.due).toLocaleString() }}</p>
            </div>

            <template v-if="!isReadonly">
                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="am-type">Activity type</label>
                    <select id="am-type" v-model="form.type" @change="onTypeChange" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white">
                        <option v-for="t in TYPES" :key="t.key" :value="t.key">{{ t.label }}</option>
                    </select>
                    <p class="mt-1 text-[10px] text-gray-400">
                        <template v-if="form.type === 'call'">Add the meeting link below, plus a summary, due date, assignee and notes.</template>
                        <template v-else>The client is met in person — summary, due date, assignee and notes.</template>
                    </p>
                </div>

                <!-- Call → meeting link -->
                <div v-if="form.type === 'call'">
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="am-link">Meeting link</label>
                    <div class="relative">
                        <Video class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-300" />
                        <input
                            id="am-link"
                            v-model="form.meeting_link"
                            type="url"
                            placeholder="https://meet.google.com/…"
                            class="w-full rounded-xl bg-gray-50 py-2.5 pl-9 pr-4 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white"
                        />
                    </div>
                    <p v-if="form.errors.meeting_link" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.meeting_link }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="am-summary">Summary *</label>
                    <input
                        id="am-summary"
                        v-model="form.summary"
                        autofocus
                        :placeholder="form.type === 'call' ? 'e.g. Follow-up call on swatches' : 'e.g. Factory visit / tech-pack review'"
                        class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white"
                    />
                    <p v-if="form.errors.summary" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.summary }}</p>
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Due date &amp; time</label>
                        <span class="text-[11px] font-bold text-indigo-600 dark:text-indigo-300">{{ dueDisplay }}</span>
                    </div>
                    <CalendarPicker :hide-close="true" @pick="applySlot" />
                    <transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="-translate-y-1 opacity-0"
                        leave-active-class="transition duration-200 ease-in"
                        leave-to-class="-translate-y-1 opacity-0"
                    >
                        <p
                            v-if="form.due_date"
                            :class="[
                                'mt-2 flex items-center gap-1.5 rounded-xl px-3 py-2 text-[11px] font-bold transition-colors',
                                justSet
                                    ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300 dark:bg-emerald-900/40 dark:text-emerald-200'
                                    : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300',
                            ]"
                        >
                            <Check class="h-3.5 w-3.5 shrink-0" />
                            Date &amp; time set — {{ dueDisplay }}
                        </p>
                    </transition>
                    <button
                        v-if="form.due_date"
                        type="button"
                        @click="form.due_date = ''"
                        class="mt-1.5 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-500"
                    >
                        Clear due date
                    </button>
                </div>

                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="am-who">Assigned to</label>
                    <select id="am-who" v-model="form.assigned_to" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white">
                        <option :value="null">—</option>
                        <option v-for="u in salespeople" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="am-notes">Notes</label>
                    <textarea id="am-notes" v-model="form.notes" rows="3" placeholder="Details to send to the client…" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                </div>
            </template>

            <p v-if="!isReadonly" class="rounded-xl bg-indigo-50 px-3 py-2 text-[11px] font-bold text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300">
                This sends a <strong>{{ typeLabel.toLowerCase() }}</strong> request by email. The client confirms, declines, or asks for a new schedule — their answer appears in the feedback panel.
            </p>

            <div class="flex flex-wrap gap-2 pt-1">
                <button
                    v-if="!isReadonly"
                    @click="schedule"
                    :disabled="form.processing || summaryBlank"
                    class="flex-1 rounded-xl bg-indigo-600 px-3 py-2.5 text-[11px] font-black uppercase text-white transition hover:bg-indigo-700 disabled:opacity-50"
                    title="Add a summary first"
                >
                    {{ form.processing ? 'Sending…' : 'Send Request' }}
                </button>
                <button @click="emit('close')" class="flex-1 rounded-xl bg-gray-100 px-3 py-2.5 text-[11px] font-black uppercase text-gray-500 transition hover:bg-gray-200 dark:bg-zinc-800">Cancel</button>
            </div>
        </div>
    </ModalShell>
</template>
