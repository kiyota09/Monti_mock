<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, CalendarDays, Phone, Bell, ClipboardCheck, Video, User, Clock, Ban, RotateCcw, Check } from 'lucide-vue-next';
import ModalShell from './ModalShell.vue';
import CalendarPicker from './CalendarPicker.vue';
import { toLocalInput } from '@/composables/crm/usePipeline';

// Meeting/activity detail: shows one scheduled activity and lets you
// reschedule it (re-sends the request) or cancel it with a reason (emails the
// client). Opened by clicking a scheduled meeting on an opportunity card.
const props = defineProps({
    // Either an opportunity (pipeline card) OR a client (Approval card).
    opportunity: { type: Object, default: null },
    client: { type: Object, default: null },
    activity: { type: Object, required: true },
});
const emit = defineEmits(['close', 'updated']);

const subjectTitle = computed(
    () => props.opportunity?.title || props.client?.company_name || '',
);
const a = computed(() => props.activity);
const isRequest = computed(() => a.value.type === 'call' || a.value.type === 'meeting');

const iconFor = (t) =>
    t === 'call' ? Phone : t === 'meeting' ? CalendarDays : t === 'reminder' ? Bell : ClipboardCheck;

const fmt = (v) =>
    v
        ? new Date(v).toLocaleString('en-PH', {
              weekday: 'long',
              year: 'numeric',
              month: 'long',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          })
        : 'Not set';

const REQ_STATUS = {
    pending: { label: 'Awaiting client', cls: 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-slate-300' },
    confirmed: { label: 'Client confirmed', cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' },
    declined: { label: 'Client declined', cls: 'bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300' },
    reschedule: { label: 'Client wants a change', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' },
};
const reqMeta = computed(() => (a.value.request_status ? REQ_STATUS[a.value.request_status] : null));

// null → details only · 'reschedule' → new-slot form · 'cancel' → reason form
const mode = ref(null);
const showPicker = ref(false);

const rescheduleForm = useForm({ due_date: '', summary: '', notes: '', meeting_link: '', reason: '' });
const startReschedule = () => {
    mode.value = 'reschedule';
    rescheduleForm.clearErrors();
    rescheduleForm.due_date = toLocalInput(a.value.due);
    rescheduleForm.summary = a.value.summary || '';
    rescheduleForm.notes = a.value.notes || '';
    rescheduleForm.meeting_link = a.value.meeting_link || '';
    rescheduleForm.reason = '';
    showPicker.value = true;
};

const applySlot = ({ at }) => {
    rescheduleForm.due_date = toLocalInput(at);
    showPicker.value = false;
};

const submitReschedule = () => {
    if (!rescheduleForm.due_date) {
        rescheduleForm.setError('due_date', 'Pick the new date & time first.');
        return;
    }
    rescheduleForm.patch(route('crm.activities.update', a.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated');
            emit('close');
        },
    });
};

const cancelForm = useForm({ reason: '' });
const startCancel = () => {
    mode.value = 'cancel';
    cancelForm.clearErrors();
    cancelForm.reason = '';
};
const submitCancel = () => {
    if (!cancelForm.reason.trim()) {
        cancelForm.setError('reason', 'Please give a reason — it is sent to the client.');
        return;
    }
    cancelForm.post(route('crm.activities.cancel', a.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated');
            emit('close');
        },
    });
};

const inputCls = 'w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white';
const labelCls = 'mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400';
</script>

<template>
    <ModalShell size="lg" @close="emit('close')">
        <template #header>
            <div class="flex items-center justify-between bg-gradient-to-r from-indigo-700 via-violet-700 to-purple-800 px-6 py-4 text-white">
                <div class="min-w-0">
                    <h3 class="flex items-center gap-2 font-black">
                        <component :is="iconFor(a.type)" class="h-4 w-4" />
                        {{ isRequest ? 'Scheduled' : 'Activity' }} <span class="capitalize">{{ a.type }}</span>
                    </h3>
                    <p class="truncate text-xs text-indigo-100">{{ subjectTitle }}</p>
                </div>
                <button @click="emit('close')" aria-label="Close"><X class="h-5 w-5 shrink-0" /></button>
            </div>
        </template>

        <div class="space-y-4 p-6">
            <!-- Summary + status -->
            <div class="flex items-start justify-between gap-3">
                <p class="text-base font-black text-slate-800 dark:text-slate-100">{{ a.summary || '—' }}</p>
                <span
                    v-if="reqMeta"
                    class="shrink-0 rounded-full px-2.5 py-1 text-[9px] font-black uppercase"
                    :class="reqMeta.cls"
                >
                    {{ reqMeta.label }}
                </span>
            </div>

            <!-- Details grid -->
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-gray-100 bg-slate-50/60 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/40">
                    <p class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-gray-400"><Clock class="h-3.5 w-3.5" /> Date &amp; time</p>
                    <p class="mt-1 text-sm font-bold text-slate-700 dark:text-slate-200">{{ fmt(a.due) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-slate-50/60 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/40">
                    <p class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-gray-400"><User class="h-3.5 w-3.5" /> Assigned to</p>
                    <p class="mt-1 text-sm font-bold text-slate-700 dark:text-slate-200">{{ a.assigned_to_name || a.owner_name || '—' }}</p>
                </div>
            </div>

            <div v-if="a.meeting_link" class="rounded-2xl border border-gray-100 bg-slate-50/60 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/40">
                <p class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-gray-400"><Video class="h-3.5 w-3.5" /> Meeting link</p>
                <a :href="a.meeting_link" target="_blank" class="mt-1 block break-all text-sm font-bold text-indigo-600 hover:underline dark:text-indigo-300">{{ a.meeting_link }}</a>
            </div>

            <div v-if="a.notes" class="rounded-2xl border border-gray-100 bg-slate-50/60 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/40">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Notes</p>
                <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-700 dark:text-slate-200">{{ a.notes }}</p>
            </div>

            <!-- Reschedule form -->
            <template v-if="mode === 'reschedule'">
                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900/40 dark:bg-amber-900/10">
                    <p class="text-sm font-black text-amber-800 dark:text-amber-200">Reschedule this {{ a.type }}</p>
                    <p class="mt-0.5 text-[11px] font-bold text-amber-700/80 dark:text-amber-300/80">
                        A new request email is sent so the client confirms the new slot.
                    </p>

                    <div class="mt-3">
                        <label :class="labelCls">New date &amp; time *</label>
                        <button
                            type="button"
                            @click="showPicker = !showPicker"
                            class="w-full rounded-xl bg-white px-4 py-2.5 text-left text-sm font-bold text-slate-700 outline-none ring-1 ring-gray-200 hover:ring-indigo-300 dark:bg-zinc-900 dark:text-slate-200 dark:ring-zinc-700"
                        >
                            {{ rescheduleForm.due_date ? new Date(rescheduleForm.due_date).toLocaleString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : 'Choose a new slot…' }}
                        </button>
                        <p v-if="rescheduleForm.errors.due_date" class="mt-1 text-[11px] font-bold text-rose-500">{{ rescheduleForm.errors.due_date }}</p>
                        <div v-if="showPicker" class="mt-2">
                            <CalendarPicker :hide-close="true" @pick="applySlot" />
                        </div>
                    </div>

                    <div class="mt-3">
                        <label :class="labelCls">Reason for change</label>
                        <textarea v-model="rescheduleForm.reason" rows="2" placeholder="Optional — e.g. the client requested a later slot" :class="inputCls" />
                    </div>

                    <div class="mt-3 flex gap-2">
                        <button type="button" @click="mode = null" class="flex-1 rounded-xl bg-white py-2.5 text-[11px] font-black uppercase text-gray-500 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-zinc-900 dark:ring-zinc-700">Back</button>
                        <button
                            type="button"
                            @click="submitReschedule"
                            :disabled="rescheduleForm.processing"
                            class="flex-1 rounded-xl bg-amber-600 py-2.5 text-[11px] font-black uppercase text-white transition hover:bg-amber-700 disabled:opacity-50"
                        >
                            {{ rescheduleForm.processing ? 'Sending…' : 'Reschedule & email' }}
                        </button>
                    </div>
                </div>
            </template>

            <!-- Cancel form -->
            <template v-else-if="mode === 'cancel'">
                <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 dark:border-rose-900/40 dark:bg-rose-900/10">
                    <p class="text-sm font-black text-rose-700 dark:text-rose-200">Cancel this {{ a.type }}?</p>
                    <p class="mt-0.5 text-[11px] font-bold text-rose-600/80 dark:text-rose-300/80">
                        The client is emailed this cancellation together with your reason.
                    </p>
                    <div class="mt-3">
                        <label :class="labelCls">Reason * (sent to the client)</label>
                        <textarea v-model="cancelForm.reason" rows="3" placeholder="e.g. The client is unavailable; we'll find a new date." :class="inputCls" />
                        <p v-if="cancelForm.errors.reason" class="mt-1 text-[11px] font-bold text-rose-500">{{ cancelForm.errors.reason }}</p>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button type="button" @click="mode = null" class="flex-1 rounded-xl bg-white py-2.5 text-[11px] font-black uppercase text-gray-500 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-zinc-900 dark:ring-zinc-700">Back</button>
                        <button
                            type="button"
                            @click="submitCancel"
                            :disabled="cancelForm.processing || !cancelForm.reason.trim()"
                            class="flex-1 rounded-xl bg-rose-600 py-2.5 text-[11px] font-black uppercase text-white transition hover:bg-rose-700 disabled:opacity-50"
                        >
                            {{ cancelForm.processing ? 'Cancelling…' : 'Cancel & email client' }}
                        </button>
                    </div>
                </div>
            </template>

            <!-- Actions -->
            <div v-else class="flex flex-wrap gap-2 pt-1">
                <button
                    v-if="isRequest"
                    @click="startReschedule"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-500 px-3 py-2.5 text-[11px] font-black uppercase text-white transition hover:bg-amber-600"
                >
                    <RotateCcw class="h-3.5 w-3.5" /> Reschedule
                </button>
                <button
                    v-if="isRequest"
                    @click="startCancel"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-3 py-2.5 text-[11px] font-black uppercase text-white transition hover:bg-rose-700"
                >
                    <Ban class="h-3.5 w-3.5" /> Cancel meeting
                </button>
                <button
                    v-if="!isRequest"
                    @click="startCancel"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-500 px-3 py-2.5 text-[11px] font-black uppercase text-white transition hover:bg-slate-600"
                >
                    <Ban class="h-3.5 w-3.5" /> Cancel
                </button>
                <button @click="emit('close')" class="flex-1 rounded-xl bg-gray-100 px-3 py-2.5 text-[11px] font-black uppercase text-gray-500 transition hover:bg-gray-200 dark:bg-zinc-800">Close</button>
            </div>

            <p v-if="mode === null && isRequest" class="flex items-start gap-1.5 rounded-xl bg-indigo-50 px-3 py-2 text-[11px] font-bold text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300">
                <Check class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                Rescheduling re-sends a confirmation email; cancelling emails the client your reason.
            </p>
        </div>
    </ModalShell>
</template>
