<template>
    <Head :title="`Conversation with ${supplier?.business_name}`" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <Link :href="route('eco.suppliers')" class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg hover:bg-white/25 transition active:scale-95">
                            <ArrowLeft class="h-5 w-5" />
                        </Link>
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <MessagesSquare class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> ECO · Supplier Chat
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight truncate">
                                {{ supplier?.business_name }}
                            </h1>
                            <p class="text-sm text-blue-100/90 truncate">{{ supplier?.representative_name }} · {{ supplier?.email }}</p>
                            <span v-if="!canEditSupplier" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5" :title="connectionLabel">
                                <span class="h-1.5 w-1.5 rounded-full animate-pulse" :class="connectionDot" /> {{ approvalStatus }} · {{ connectionLabel }}
                            </span>
                            <button @click="checkSupplierCredit" class="flex items-center gap-1.5 rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950 hover:bg-amber-200 transition active:scale-95">
                                <Shield class="h-3.5 w-3.5" /> Credit Check
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <!-- Main conversation area -->
                    <div class="animate-fade-up lg:col-span-2 group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden flex flex-col h-[calc(100vh-250px)]" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="p-5 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                            <MessagesSquare class="h-5 w-5 text-indigo-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Conversation</h2>
                            <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ threadMessages.length }} messages</span>
                        </div>
                        <div v-if="!isApproved" class="px-5 pt-4">
                            <p class="text-xs font-bold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-2 rounded-xl text-center">Vendor {{ approvalStatus }} — approval is managed in SCM · Vendors. Chat unlocks after approval.</p>
                        </div>
                        <!-- Messages -->
                        <div ref="messagesContainer" class="relative flex-1 overflow-y-auto p-6 space-y-4">
                            <div v-if="threadMessages.length === 0" class="flex flex-col items-center justify-center py-10 text-center">
                                <MessagesSquare class="h-8 w-8 text-indigo-200 mb-2" />
                                <p class="text-sm font-bold text-gray-500">No messages yet — say hello to start the thread.</p>
                            </div>
                            <TransitionGroup name="card" tag="div" class="space-y-4">
                                <div v-for="msg in threadMessages" :key="msg.id" class="flex" :class="msg.sender_type === 'supplier' ? 'justify-start' : 'justify-end'">
                                    <div :class="msg.sender_type === 'supplier'
                                        ? 'bg-gray-100 dark:bg-zinc-800 text-gray-800 dark:text-gray-200'
                                        : 'bg-gradient-to-br from-indigo-600 to-violet-700 text-white shadow-lg shadow-indigo-500/25'"
                                        class="max-w-[70%] rounded-2xl px-4 py-2.5 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                                        <p class="text-sm font-medium">{{ msg.message }}</p>
                                        <div v-if="msg.meeting_data" class="mt-2 text-xs border-t border-white/20 pt-1.5 flex items-center gap-1">
                                            <Calendar class="h-3 w-3 shrink-0" />
                                            Meeting: {{ msg.meeting_data.type }} at {{ msg.meeting_data.location }} on {{ formatDateTime(msg.meeting_data.scheduled_at) }}
                                        </div>
                                        <div v-if="msg.attachment" class="mt-1">
                                            <a :href="msg.attachment_url || msg.attachment" target="_blank" class="text-xs underline flex items-center gap-1">
                                                <Paperclip class="h-3 w-3" /> Attachment
                                            </a>
                                        </div>
                                        <p class="text-[10px] opacity-70 mt-1 text-right">{{ formatTime(msg.created_at) }}</p>
                                    </div>
                                </div>
                            </TransitionGroup>
                            <div v-if="pollError" class="flex justify-center">
                                <p class="text-[11px] font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-full">Reconnecting… live feed paused ({{ pollError }})</p>
                            </div>
                        </div>

                        <!-- Message input -->
                        <div class="border-t border-gray-100 dark:border-zinc-800 p-4 bg-white/60 dark:bg-zinc-900/60">
                            <p v-if="sendError" class="mb-2 text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-2 rounded-xl">{{ sendError }}</p>
                            <form v-if="canEditSupplier" @submit.prevent="sendMessage" class="flex gap-3">
                                <input v-model="newMessage" type="text" placeholder="Type your message..."
                                    class="flex-1 rounded-2xl border-0 bg-gray-100 dark:bg-zinc-800 px-4 py-3 text-sm font-medium text-gray-900 dark:text-white placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 outline-none transition" />
                                <button type="button" @click="triggerFileUpload" class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-500 hover:bg-indigo-100 hover:text-indigo-600 dark:hover:bg-indigo-900/30 transition active:scale-95">
                                    <Paperclip class="h-5 w-5" />
                                </button>
                                <input ref="fileInput" type="file" class="hidden" @change="uploadAttachment" />
                                <button type="submit" :disabled="sending" class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white shadow-lg shadow-indigo-500/25 hover:scale-105 active:scale-95 transition disabled:opacity-50">
                                    <Send v-if="!sending" class="h-5 w-5" />
                                    <Loader2 v-else class="h-5 w-5 animate-spin" />
                                </button>
                            </form>
                            <p v-else class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-2 rounded-xl text-center">View only — messaging disabled.</p>
                        </div>
                    </div>

                    <!-- Sidebar actions -->
                    <div class="space-y-5">
                        <!-- Set Meeting Card -->
                        <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 overflow-hidden" style="animation-delay:160ms">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <h3 class="relative text-sm font-black uppercase tracking-widest mb-4 flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20"><Calendar class="h-4 w-4 text-indigo-600" /></span> Schedule Meeting
                            </h3>
                            <form @submit.prevent="scheduleMeeting" class="relative">
                                <div class="space-y-3">
                                    <input v-model="meetingData.scheduled_at" type="datetime-local" required
                                        class="w-full rounded-2xl border-0 bg-gray-100 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none" />
                                    <input v-model="meetingData.location" type="text" placeholder="Location (e.g., Zoom, Office)" required
                                        class="w-full rounded-2xl border-0 bg-gray-100 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 outline-none" />
                                    <select v-model="meetingData.type" required class="w-full rounded-2xl border-0 bg-gray-100 dark:bg-zinc-800 p-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                                        <option value="onsite">On-site</option>
                                        <option value="video">Video Call</option>
                                        <option value="phone">Phone Call</option>
                                    </select>
                                    <button v-if="canEditSupplier" type="submit" :disabled="scheduling" class="w-full py-2.5 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-2xl font-bold text-sm hover:bg-indigo-600 hover:text-white transition active:scale-95 disabled:opacity-50">
                                        {{ scheduling ? 'Scheduling...' : 'Send Meeting Invite' }}
                                    </button>
                                    <p v-else class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-2 rounded-xl text-center">View only</p>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import { usePageAccess } from '@/composables/usePageAccess';
import { ArrowLeft, Shield, Calendar, Paperclip, Send, Loader2, Sparkles, MessagesSquare } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
// View-only alignment with SCM: messaging only when the vendor
// registration is approved (SCM is sole approver) + user has edit grant.
// (Quotation requests live in the procurement module, not here.)
const props = defineProps({
    supplier: {
        type: Object,
        required: true
    },
    messages: {
        type: Array,
        default: () => []
    },
    requests: {
        type: Array,
        default: () => []
    },
    registration: {
        type: Object,
        default: null,
    }
});

const approvalStatus = computed(() => props.registration?.status ?? props.supplier?.approval_status ?? props.supplier?.status ?? 'approved');
const isApproved = computed(() => approvalStatus.value === 'approved');
const canEditSupplier = computed(() => canEdit('ECO', 'supplier') && isApproved.value);

// ─── Real-time thread state (polling, no page reload) ───
const threadMessages = ref([...(props.messages ?? [])]);
const messagesContainer = ref(null);
const newMessage = ref('');
const sending = ref(false);
const sendError = ref('');
const scheduling = ref(false);
const fileInput = ref(null);
const pollError = ref('');
const liveState = ref('live'); // live | offline | error
let pollTimer = null;

const lastMessageId = computed(() => threadMessages.value.reduce((m, x) => Math.max(m, x.id ?? 0), 0));
const connectionLabel = computed(() => liveState.value === 'live' ? 'Live' : liveState.value === 'offline' ? 'Offline' : 'Reconnecting');
const connectionDot = computed(() => liveState.value === 'live' ? 'bg-emerald-300' : liveState.value === 'offline' ? 'bg-gray-300' : 'bg-rose-300');

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const isNearBottom = () => {
    const el = messagesContainer.value;
    if (!el) return true;
    return el.scrollHeight - el.scrollTop - el.clientHeight < 120;
};

const scrollToBottom = (force = false) => {
    nextTick(() => {
        const el = messagesContainer.value;
        if (el && (force || isNearBottom())) {
            el.scrollTop = el.scrollHeight;
        }
    });
};

const mergeMessages = (incoming, forceScroll = false) => {
    const seen = new Set(threadMessages.value.map((m) => m.id));
    // Drop optimistic temp rows once the real row arrives (same text, temp id).
    let added = 0;
    for (const m of incoming ?? []) {
        if (m.id == null || seen.has(m.id)) continue;
        const tempIdx = threadMessages.value.findIndex((x) => String(x.id).startsWith('temp-') && x.message === m.message && x.sender_type === m.sender_type);
        if (tempIdx !== -1) threadMessages.value.splice(tempIdx, 1);
        threadMessages.value.push(m);
        seen.add(m.id);
        added++;
    }
    if (added > 0) {
        threadMessages.value.sort((a, b) => (a.id ?? 0) - (b.id ?? 0));
        scrollToBottom(forceScroll);
    }
};

const pollFeed = async () => {
    if (document.hidden) return;
    try {
        const res = await fetch(`${route('eco.supplier.feed', props.supplier.id)}?after=${lastMessageId.value}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        mergeMessages(data.messages);
        pollError.value = '';
        liveState.value = 'live';
    } catch (e) {
        pollError.value = e?.message ?? 'feed failed';
        liveState.value = 'error';
    }
};

const startPolling = () => {
    stopPolling();
    pollTimer = setInterval(pollFeed, 2500);
};

const stopPolling = () => {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
};

const sendMessage = async () => {
    const text = newMessage.value.trim();
    if (!text || sending.value) return;
    sending.value = true;
    sendError.value = '';
    // Optimistic row so sending feels instant.
    const tempId = `temp-${Date.now()}`;
    threadMessages.value.push({ id: tempId, supplier_id: props.supplier.id, sender_type: 'eco', message: text, created_at: new Date().toISOString() });
    scrollToBottom(true);
    newMessage.value = '';
    try {
        const res = await fetch(route('eco.supplier.message', props.supplier.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ message: text }),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message ?? `Send failed (HTTP ${res.status})`);
        }
        const data = await res.json();
        mergeMessages(data.message ? [data.message] : [], true);
        pollFeed();
    } catch (e) {
        // Roll back optimistic row on failure.
        threadMessages.value = threadMessages.value.filter((m) => m.id !== tempId);
        sendError.value = e?.message ?? 'Failed to send. Please try again.';
    } finally {
        sending.value = false;
    }
};

// Meeting form
const meetingData = ref({
    scheduled_at: '',
    location: '',
    type: 'video'
});

const triggerFileUpload = () => {
    fileInput.value.click();
};

const uploadAttachment = async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    sendError.value = '';
    const tempId = `temp-${Date.now()}`;
    threadMessages.value.push({ id: tempId, supplier_id: props.supplier.id, sender_type: 'eco', message: `Sending ${file.name}…`, created_at: new Date().toISOString() });
    scrollToBottom(true);
    try {
        const formData = new FormData();
        formData.append('attachment', file);
        formData.append('message', `Sent an attachment: ${file.name}`);
        const res = await fetch(route('eco.supplier.message', props.supplier.id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: formData,
        });
        if (!res.ok) throw new Error(`Upload failed (HTTP ${res.status})`);
        const data = await res.json();
        mergeMessages(data.message ? [data.message] : [], true);
        pollFeed();
    } catch (err) {
        threadMessages.value = threadMessages.value.filter((m) => m.id !== tempId);
        sendError.value = err?.message ?? 'Attachment upload failed.';
    } finally {
        if (fileInput.value) fileInput.value.value = '';
    }
};

const scheduleMeeting = async () => {
    scheduling.value = true;
    sendError.value = '';
    try {
        const res = await fetch(route('eco.supplier.meeting', props.supplier.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(meetingData.value),
        });
        if (!res.ok) throw new Error(`Meeting invite failed (HTTP ${res.status})`);
        const data = await res.json();
        mergeMessages(data.message ? [data.message] : [], true);
        meetingData.value = { scheduled_at: '', location: '', type: 'video' };
    } catch (e) {
        sendError.value = e?.message ?? 'Failed to schedule meeting.';
    } finally {
        scheduling.value = false;
    }
};

const checkSupplierCredit = async () => {
    const res = await axios.get(route('eco.supplier.credit-check', props.supplier.id));
    alert(`Credit Status: ${res.data.is_good_payer ? 'Good Payer' : 'High Risk'}\nOutstanding Balance: ₱${res.data.outstanding}`);
};

const formatDateTime = (date) => new Date(date).toLocaleString('en-PH');
const formatTime = (date) => new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

watch(() => props.messages, (incoming) => {
    // Server-driven reload (e.g. after request POST): merge, don't replace
    // local optimistic rows.
    mergeMessages(incoming ?? []);
}, { deep: true });

watch(threadMessages, () => {
    scrollToBottom();
}, { deep: true });

onMounted(() => {
    scrollToBottom(true);
    startPolling();
    window.addEventListener('online', () => { liveState.value = 'live'; pollFeed(); });
    window.addEventListener('offline', () => { liveState.value = 'offline'; });
});

onUnmounted(() => {
    stopPolling();
});
</script>

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
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
