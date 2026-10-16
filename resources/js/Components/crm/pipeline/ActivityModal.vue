<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, CalendarDays } from 'lucide-vue-next';
import ModalShell from './ModalShell.vue';
import CalendarPicker from './CalendarPicker.vue';
import { ACTIVITY_TYPES, toLocalInput, nextWeekday } from '@/composables/crm/usePipeline';

// Schedule Activity: type dropdown (Call/Meeting/Reminder/To-Do),
// summary, quick due-date chips + picker, assigned-to, notes.
// Footer: Open Calendar | Schedule | Mark as Done | Done & Schedule Next | Cancel.
const props = defineProps({
    opportunity: { type: Object, required: true },
    salespeople: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
    existing: { type: Object, default: null }, // view an existing activity (read-only unless planned)
});
const emit = defineEmits(['close', 'scheduled']);

const showCalendar = ref(false);

const form = useForm({
    opportunity_id: props.opportunity.id,
    type: props.existing?.type ?? 'call',
    summary: props.existing?.summary ?? '',
    due_date: toLocalInput(props.existing?.due),
    assigned_to: props.existing?.assigned_to ?? props.currentUserId ?? null,
    notes: props.existing?.notes ?? '',
    mark_done: false,
});

const typeLabel = computed(() => ACTIVITY_TYPES.find((t) => t.key === form.type)?.label ?? form.type);
const isReadonly = computed(() => !!props.existing && props.existing.status !== 'planned');
const summaryBlank = computed(() => !form.summary || !String(form.summary).trim());

const at9 = (d) => {
    d.setHours(9, 0, 0, 0);
    return d;
};
const dueChips = computed(() => {
    const tomorrow = at9(new Date());
    tomorrow.setDate(tomorrow.getDate() + 1);
    const in3 = at9(new Date());
    in3.setDate(in3.getDate() + 3);
    return [
        { label: 'Tomorrow 9am', at: tomorrow },
        { label: 'In 3 days', at: in3 },
        { label: 'Next Mon 9am', at: nextWeekday(1) },
    ];
});

const post = (done, reopen) => {
    if (summaryBlank.value) {
        form.setError('summary', 'Give the activity a summary first.');
        return;
    }
    form.clearErrors('summary');
    form.mark_done = done;
    form.post(route('crm.activities.store'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('scheduled');
            if (reopen) {
                form.reset('summary', 'due_date', 'notes');
                form.mark_done = false;
                form.type = 'call';
            } else {
                emit('close');
            }
        },
    });
};

const applyCalendarSlot = ({ at }) => {
    form.due_date = toLocalInput(at);
    showCalendar.value = false;
};
</script>

<template>
    <ModalShell size="lg" @close="emit('close')">
        <template #header>
            <div class="flex items-center justify-between bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-6 py-4 text-white">
                <div class="min-w-0">
                    <h3 class="font-black">{{ existing ? 'Activity' : 'Schedule Activity' }}</h3>
                    <p class="truncate text-xs text-blue-100">{{ opportunity.title }} · <span class="capitalize">{{ typeLabel }}</span></p>
                </div>
                <button @click="emit('close')" aria-label="Close"><X class="h-5 w-5 shrink-0" /></button>
            </div>
        </template>

        <div class="space-y-4 p-6">
            <div v-if="existing" class="rounded-xl bg-slate-50 px-4 py-3 text-xs dark:bg-zinc-800 dark:text-slate-200">
                <p><strong>Type:</strong> <span class="capitalize">{{ existing.type }}</span> · <strong>Status:</strong> {{ existing.status }}</p>
                <p v-if="existing.due" class="text-gray-400">Due {{ new Date(existing.due).toLocaleString() }}</p>
            </div>
            <template v-if="!isReadonly">
                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="am-type">Activity type</label>
                    <select id="am-type" v-model="form.type" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white">
                        <option v-for="t in ACTIVITY_TYPES" :key="t.key" :value="t.key">{{ t.label }}</option>
                    </select>
                    <p class="mt-1 text-[10px] text-gray-400">
                        <template v-if="form.type === 'call'">Call shows summary, due date, assignee and notes.</template>
                        <template v-else-if="form.type === 'meeting'">Meetings usually need a calendar slot — try Open Calendar.</template>
                        <template v-else>A lightweight nudge — summary plus an optional due date is enough.</template>
                    </p>
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="am-summary">Summary *</label>
                    <input
                        id="am-summary"
                        v-model="form.summary"
                        autofocus
                        :placeholder="form.type === 'call' ? 'e.g. Follow-up call on swatches' : form.type === 'meeting' ? 'e.g. Tech-pack review' : 'e.g. Send revised quotation'"
                        class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white"
                    />
                    <p v-if="form.errors.summary" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.summary }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="am-due">Due date</label>
                        <input id="am-due" v-model="form.due_date" type="datetime-local" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            <button
                                v-for="c in dueChips"
                                :key="c.label"
                                type="button"
                                @click="form.due_date = toLocalInput(c.at)"
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-500 transition hover:bg-indigo-100 hover:text-indigo-700 dark:bg-zinc-800 dark:text-slate-300 dark:hover:bg-indigo-900/40"
                            >
                                {{ c.label }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="am-who">Assigned to</label>
                        <select id="am-who" v-model="form.assigned_to" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white">
                            <option :value="null">—</option>
                            <option v-for="u in salespeople" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="am-notes">Notes</label>
                    <textarea id="am-notes" v-model="form.notes" rows="3" placeholder="Context for the assignee…" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                </div>
            </template>

            <CalendarPicker v-if="showCalendar && !isReadonly" @pick="applyCalendarSlot" @close="showCalendar = false" />

            <div class="flex flex-wrap gap-2 pt-1">
                <button v-if="!isReadonly" @click="showCalendar = !showCalendar" :class="['inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-[11px] font-black uppercase transition', showCalendar ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-zinc-800 dark:text-slate-300']">
                    <CalendarDays class="h-4 w-4" /> {{ showCalendar ? 'Hide Calendar' : 'Open Calendar' }}
                </button>
                <button v-if="!isReadonly" @click="post(false, false)" :disabled="form.processing || summaryBlank" class="flex-1 rounded-xl bg-indigo-600 px-3 py-2.5 text-[11px] font-black uppercase text-white transition hover:bg-indigo-700 disabled:opacity-50" title="Add a summary first">Schedule</button>
                <button v-if="!isReadonly" @click="post(true, false)" :disabled="form.processing || summaryBlank" class="flex-1 rounded-xl bg-emerald-600 px-3 py-2.5 text-[11px] font-black uppercase text-white transition hover:bg-emerald-700 disabled:opacity-50" title="Add a summary first">Mark as Done</button>
                <button v-if="!isReadonly" @click="post(true, true)" :disabled="form.processing || summaryBlank" class="flex-1 rounded-xl bg-amber-500 px-3 py-2.5 text-[11px] font-black uppercase text-white transition hover:bg-amber-600 disabled:opacity-50" title="Log this one done and immediately plan the follow-up">Done &amp; Next</button>
                <button @click="emit('close')" class="flex-1 rounded-xl bg-gray-100 px-3 py-2.5 text-[11px] font-black uppercase text-gray-500 transition hover:bg-gray-200 dark:bg-zinc-800">Cancel</button>
            </div>
        </div>
    </ModalShell>
</template>
