<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Calendar, X, CheckCircle, XCircle, Plus, MessageSquare, AlertCircle } from 'lucide-vue-next';

const props = defineProps({
    pendingClients: {
        type: Array,
        default: () => []
    },
    permissions: {
        type: Object,
        default: () => ({})
    }
});

const canEdit = computed(() => props.permissions?.approvals === 'edit');

const meetingForm = useForm({ scheduled_at: '', meeting_type: '', location: '', notes: '' });
const noteForm    = useForm({ note: '' });
const rejectForm  = useForm({ reason: '' });

const selectedClient  = ref(null);
const showMeetingModal = ref(false);
const showNoteModal    = ref(false);
const showRejectModal  = ref(false);
const showAcceptModal  = ref(false);
const accepting = ref(false);

// ── Meeting ────────────────────────────────────────────────────────────────────
const openMeetingModal = (client) => {
    if (!canEdit.value) return;
    selectedClient.value = client;
    meetingForm.reset();
    showMeetingModal.value = true;
};
const scheduleMeeting = () => {
    meetingForm.post(route('crm.approval.meeting', selectedClient.value.id), {
        preserveScroll: true,
        onSuccess: () => { showMeetingModal.value = false; }
    });
};

// ── Note ───────────────────────────────────────────────────────────────────────
const openNoteModal = (client) => {
    if (!canEdit.value) return;
    selectedClient.value = client;
    noteForm.reset();
    showNoteModal.value = true;
};
const submitNote = () => {
    noteForm.post(route('crm.approval.note', selectedClient.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showNoteModal.value = false;
            noteForm.reset();
        }
    });
};

// ── Accept / Reject ────────────────────────────────────────────────────────────
const openAcceptModal = (client) => {
    if (!canEdit.value) return;
    selectedClient.value = client;
    showAcceptModal.value = true;
};
const acceptClient = () => {
    if (!canEdit.value || !selectedClient.value || accepting.value) return;
    accepting.value = true;
    router.post(route('crm.approval.accept', selectedClient.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { showAcceptModal.value = false; },
        onFinish: () => { accepting.value = false; },
    });
};
const openRejectModal = (client) => {
    if (!canEdit.value) return;
    selectedClient.value = client;
    rejectForm.reset();
    showRejectModal.value = true;
};
const rejectClient = () => {
    rejectForm.post(route('crm.approval.reject', selectedClient.value.id), {
        preserveScroll: true,
        onSuccess: () => { showRejectModal.value = false; }
    });
};

// ── Meeting status ─────────────────────────────────────────────────────────────
const updateMeetingStatus = (meetingId, status) => {
    if (!canEdit.value) return;
    router.patch(route('crm.approval.meeting.update', meetingId), { status });
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <CheckCircle class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Calendar class="h-3.5 w-3.5" /> CRM · Approvals
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Pending Client Approvals</h1>
                            <p class="text-sm text-blue-100/90">{{ pendingClients.length }} registration{{ pendingClients.length !== 1 ? 's' : '' }} awaiting review</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ pendingClients.length }} pending
                            </span>
                            <span v-if="canEdit" class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">Full access</span>
                            <span v-else-if="!canEdit && permissions.approvals === 'view'" class="rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">View only</span>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="pendingClients.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-900/30 dark:to-teal-900/20 rounded-full mb-4 animate-bounce-soft">
                        <CheckCircle class="h-9 w-9 text-emerald-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">All caught up</p>
                    <p class="text-xs text-gray-400 mt-1">No pending client registrations.</p>
                </div>

                <!-- Approval cards -->
                <div v-else class="space-y-4">
                    <div
                        v-for="(client, i) in pendingClients"
                        :key="client.id"
                        :style="{ animationDelay: `${Math.min(i * 70, 400)}ms` }"
                        class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 sm:p-6 overflow-hidden"
                    >
                        <!-- hover glow + accent -->
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />

                        <div class="relative flex flex-col sm:flex-row justify-between items-start gap-4">
                            <div class="flex items-start gap-3 min-w-0">
                                <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl overflow-hidden text-white text-lg font-black shadow-lg uppercase group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300', client.logo?.logo_url ? 'bg-white' : 'bg-gradient-to-br from-amber-500 via-orange-600 to-rose-600']">
                                    <img v-if="client.logo?.logo_url" :src="client.logo.logo_url" :alt="client.company_name" class="h-full w-full object-cover" />
                                    <span v-else>{{ (client.company_name ?? '?').charAt(0) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-lg font-black tracking-tight text-gray-900 dark:text-white group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ client.company_name }}</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium truncate">{{ client.contact_person }} | {{ client.email }}</p>
                                    <p class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-zinc-800 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:text-slate-300">TIN: {{ client.tin_number }}</p>
                                </div>
                            </div>
                            <div v-if="canEdit" class="flex gap-2 shrink-0">
                                <button @click="openAcceptModal(client)" class="flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:scale-105 active:scale-95 transition-all">
                                    <CheckCircle class="w-4 h-4" /> Accept
                                </button>
                                <button @click="openRejectModal(client)" class="flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-rose-500 to-red-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-lg shadow-rose-500/30 hover:shadow-xl hover:scale-105 active:scale-95 transition-all">
                                    <XCircle class="w-4 h-4" /> Reject
                                </button>
                            </div>
                            <div v-else class="shrink-0 rounded-full bg-amber-100 dark:bg-amber-900/30 px-3 py-1.5 text-[11px] font-bold text-amber-700 dark:text-amber-300 italic">View only</div>
                        </div>

                        <!-- Notes display -->
                        <div v-if="client.notes" class="relative mt-4 flex gap-2.5 rounded-2xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50/60 dark:bg-indigo-900/10 p-4 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">
                            <MessageSquare class="w-4 h-4 mt-0.5 shrink-0 text-indigo-500" />
                            <span>{{ client.notes }}</span>
                        </div>

                        <div class="relative mt-4 border-t border-gray-100 dark:border-zinc-800 pt-4">
                            <div v-if="canEdit" class="flex flex-wrap gap-2">
                                <button
                                    @click="openMeetingModal(client)"
                                    class="flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-black uppercase tracking-wide text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all"
                                >
                                    <Plus class="w-4 h-4" /> Schedule Meeting
                                </button>
                                <button
                                    @click="openNoteModal(client)"
                                    class="flex items-center gap-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 px-3.5 py-2 text-xs font-black uppercase tracking-wide text-slate-600 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-zinc-700 hover:scale-105 active:scale-95 transition-all"
                                >
                                    <MessageSquare class="w-4 h-4" /> Add Note
                                </button>
                            </div>
                            <div v-else class="text-xs text-gray-400 italic">Manage meetings and notes – edit permission required</div>

                            <!-- Meetings timeline -->
                            <div v-if="client.meetings && client.meetings.length" class="mt-4">
                                <h3 class="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-widest text-gray-400"><Calendar class="w-3.5 h-3.5" /> Meetings · {{ client.meetings.length }}</h3>
                                <div class="relative ml-2 mt-3 space-y-4 before:absolute before:left-[7px] before:top-2 before:bottom-2 before:w-0.5 before:bg-gradient-to-b before:from-indigo-300 before:via-blue-200 before:to-transparent dark:before:from-indigo-800">
                                    <div
                                        v-for="(meeting, mi) in client.meetings"
                                        :key="meeting.id"
                                        class="animate-fade-up relative pl-8 group/m"
                                        :style="{ animationDelay: `${Math.min(mi * 60, 300)}ms` }"
                                    >
                                        <span class="absolute left-0 top-1.5 h-4 w-4 rounded-full bg-amber-400 shadow-lg shadow-amber-400/40 ring-4 ring-white dark:ring-zinc-900 group-hover/m:scale-125 transition-transform" />
                                        <div class="rounded-2xl border border-gray-100 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40 p-3.5 text-sm hover:shadow-lg hover:-translate-y-0.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all">
                                            <div class="font-bold text-gray-900 dark:text-white">{{ new Date(meeting.scheduled_at).toLocaleString() }} – {{ meeting.meeting_type }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ meeting.location }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ meeting.notes }}</div>
                                            <div v-if="canEdit" class="flex gap-2 mt-2">
                                                <button
                                                    v-if="meeting.status !== 'done'"
                                                    @click="updateMeetingStatus(meeting.id, 'done')"
                                                    class="flex items-center gap-1 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 px-2.5 py-1 text-[11px] font-black uppercase text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 active:scale-95 transition"
                                                ><CheckCircle class="w-3.5 h-3.5" /> Mark Done</button>
                                                <button
                                                    v-if="meeting.status !== 'cancelled'"
                                                    @click="updateMeetingStatus(meeting.id, 'cancelled')"
                                                    class="flex items-center gap-1 rounded-lg bg-rose-100 dark:bg-rose-900/30 px-2.5 py-1 text-[11px] font-black uppercase text-rose-700 dark:text-rose-300 hover:bg-rose-200 active:scale-95 transition"
                                                ><XCircle class="w-3.5 h-3.5" /> Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Meeting Modal ─────────────────────────────────────────────── -->
            <Transition name="modal">
                <div
                    v-if="showMeetingModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="showMeetingModal = false"
                >
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 p-5 text-white flex justify-between items-center relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <h2 class="relative font-black flex items-center gap-2"><Calendar class="w-4 h-4" /> Schedule Meeting</h2>
                            <button @click="showMeetingModal = false" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 hover:bg-white/35 hover:rotate-90 transition-all"><X class="w-4 h-4" /></button>
                        </div>
                        <form @submit.prevent="scheduleMeeting" class="p-6 space-y-4">
                            <input
                                type="datetime-local"
                                v-model="meetingForm.scheduled_at"
                                required
                                class="w-full rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900 outline-none px-4 py-2.5 text-sm transition"
                            />
                            <select
                                v-model="meetingForm.meeting_type"
                                required
                                class="w-full rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900 outline-none px-4 py-2.5 text-sm transition"
                            >
                                <option value="">Select type</option>
                                <option value="phone">Phone Call</option>
                                <option value="video">Video Call</option>
                                <option value="onsite">On‑site Meeting</option>
                            </select>
                            <input
                                type="text"
                                v-model="meetingForm.location"
                                placeholder="Location"
                                class="w-full rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900 outline-none px-4 py-2.5 text-sm transition"
                            />
                            <textarea
                                v-model="meetingForm.notes"
                                placeholder="Notes"
                                class="w-full rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900 outline-none px-4 py-2.5 text-sm transition resize-none"
                            ></textarea>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showMeetingModal = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="submit" :disabled="meetingForm.processing" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black text-sm shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-60">Schedule</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- ── Add Note Modal ────────────────────────────────────────────── -->
            <Transition name="modal">
                <div
                    v-if="showNoteModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="showNoteModal = false"
                >
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-600 via-blue-600 to-violet-600 p-5 text-white flex justify-between items-center relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <h2 class="relative font-black flex items-center gap-2">
                                <MessageSquare class="w-4 h-4" />
                                Add Note — {{ selectedClient?.company_name }}
                            </h2>
                            <button @click="showNoteModal = false" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 hover:bg-white/35 hover:rotate-90 transition-all"><X class="w-4 h-4" /></button>
                        </div>
                        <form @submit.prevent="submitNote" class="p-6 space-y-4">
                            <textarea
                                v-model="noteForm.note"
                                placeholder="Write your note here..."
                                required
                                rows="4"
                                class="w-full rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-900 outline-none px-4 py-2.5 text-sm transition resize-none"
                            ></textarea>
                            <p v-if="noteForm.errors.note" class="text-red-500 text-xs">{{ noteForm.errors.note }}</p>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showNoteModal = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="submit" :disabled="noteForm.processing" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-black text-sm shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-60">Save Note</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>

            <!-- ── Accept Modal ──────────────────────────────────────────────── -->
            <Transition name="modal">
                <div
                    v-if="showAcceptModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="showAcceptModal = false"
                >
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                        <div class="bg-gradient-to-r from-emerald-500 via-teal-600 to-cyan-600 p-5 text-white flex justify-between items-center relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <h2 class="relative font-black flex items-center gap-2"><CheckCircle class="w-4 h-4" /> Accept Registration</h2>
                            <button @click="showAcceptModal = false" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 hover:bg-white/35 hover:rotate-90 transition-all"><X class="w-4 h-4" /></button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex items-start gap-3 rounded-2xl border border-emerald-100 dark:border-emerald-900/50 bg-emerald-50/60 dark:bg-emerald-900/10 p-4">
                                <CheckCircle class="w-5 h-5 shrink-0 text-emerald-500 mt-0.5" />
                                <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                    Accept <span class="font-black text-gray-900 dark:text-white">{{ selectedClient?.company_name }}</span>
                                    <span v-if="selectedClient?.contact_person" class="text-gray-500 dark:text-gray-400">({{ selectedClient.contact_person }} · {{ selectedClient.email }})</span>
                                    as an active partner? This will grant them full client access.
                                </p>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showAcceptModal = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="button" @click="acceptClient" :disabled="accepting" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white font-black text-sm shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-60">Confirm Accept</button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- ── Reject Modal ──────────────────────────────────────────────── -->
            <Transition name="modal">
                <div
                    v-if="showRejectModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                    @click.self="showRejectModal = false"
                >
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                        <div class="bg-gradient-to-r from-rose-600 via-red-600 to-orange-600 p-5 text-white flex justify-between items-center relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <h2 class="relative font-black flex items-center gap-2"><AlertCircle class="w-4 h-4" /> Reject Registration</h2>
                            <button @click="showRejectModal = false" class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 hover:bg-white/35 hover:rotate-90 transition-all"><X class="w-4 h-4" /></button>
                        </div>
                        <form @submit.prevent="rejectClient" class="p-6 space-y-4">
                            <textarea
                                v-model="rejectForm.reason"
                                placeholder="Reason for rejection"
                                required
                                rows="3"
                                class="w-full rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-rose-400 focus:ring-2 focus:ring-rose-200 dark:focus:ring-rose-900 outline-none px-4 py-2.5 text-sm transition resize-none"
                            ></textarea>
                            <p v-if="rejectForm.errors.reason" class="text-red-500 text-xs">{{ rejectForm.errors.reason }}</p>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="showRejectModal = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="submit" :disabled="rejectForm.processing" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 text-white font-black text-sm shadow-lg shadow-rose-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-60">Confirm Reject</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
