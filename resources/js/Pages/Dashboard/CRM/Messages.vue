<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    MessagesSquare, Send, Loader2, Search, ChevronLeft, Paperclip, X, Smile,
    CornerUpLeft, MoreVertical, Phone, Video, History, FileText, Download,
    Trash2, PhoneCall, PhoneIncoming, PhoneOutgoing, PhoneMissed, Ban, Users, Settings2,
} from 'lucide-vue-next';
import { useCallCenter } from '@/composables/crm/useCallCenter';
import { usePartnerCallCenter } from '@/composables/crm/usePartnerCallCenter';

const props = defineProps({
    auth: Object,
    clients: { type: Array, default: () => [] },
    selected: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
    callLogs: { type: Array, default: () => [] },
    totalUnread: { type: Number, default: 0 },
    callsEnabled: { type: Boolean, default: true },
});

const SELF = 'crm';
const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

const clients = ref((props.clients ?? []).map((c) => ({ ...c })));
const activeClient = ref(props.selected ? { ...props.selected } : null);
const threadMessages = ref([...(props.messages ?? [])]);
const messagesContainer = ref(null);
const panelRef = ref(null);
const panelHeight = ref(0);
const newMessage = ref('');
const sending = ref(false);
const uploading = ref(false);
const sendError = ref('');
const search = ref('');
const mobileView = ref(props.selected ? 'thread' : 'list');
const replyTo = ref(null);
const reactionPickerId = ref(null);
const openMenuId = ref(null);
const imageViewer = ref(null);
const showCallLog = ref(false);
const showCallSettings = ref(false);
const callsEnabled = ref(props.callsEnabled !== false);
const callSettingsForm = useForm({ calls_enabled: callsEnabled.value });
const fileInput = ref(null);
let pollTimer = null;

// Partner calls are handled by the global PartnerCallOverlay; this page only
// kicks off an outgoing call for the active thread.
const partnerCall = usePartnerCallCenter();

const filteredClients = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return clients.value;
    return clients.value.filter((c) =>
        (c.company_name ?? '').toLowerCase().includes(q) ||
        (c.contact_person ?? '').toLowerCase().includes(q) ||
        (c.email ?? '').toLowerCase().includes(q),
    );
});

const lastMessageId = computed(() => threadMessages.value.reduce((m, x) => Math.max(m, typeof x.id === 'number' ? x.id : 0), 0));
const callHistory = computed(() => threadMessages.value.filter((m) => m.type === 'call').slice().sort((a, b) => (b.id ?? 0) - (a.id ?? 0)));

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const scrollToBottom = () => {
    nextTick(() => {
        const el = messagesContainer.value;
        if (el) el.scrollTop = el.scrollHeight;
    });
};

const mergeMessages = (incoming) => {
    let added = false;
    let touched = false;
    for (const m of incoming ?? []) {
        if (m.id == null) continue;
        const idx = threadMessages.value.findIndex((x) => String(x.id) === String(m.id));
        if (idx !== -1) {
            threadMessages.value[idx] = { ...threadMessages.value[idx], ...m };
            touched = true;
        } else {
            const tempIdx = threadMessages.value.findIndex((x) => String(x.id).startsWith('temp-') && x.sender_type === m.sender_type);
            if (tempIdx !== -1) threadMessages.value.splice(tempIdx, 1);
            threadMessages.value.push(m);
            added = true;
            touched = true;
        }
    }
    if (added) {
        threadMessages.value.sort((a, b) => (Number(a.id) || 0) - (Number(b.id) || 0));
        scrollToBottom();
    }
    return touched;
};

const findMessage = (id) => threadMessages.value.find((m) => String(m.id) === String(id));

const previewOf = (m) => {
    if (!m) return '';
    if (m.is_unsent) return 'This message was deleted';
    if (m.type === 'call') return m.call?.type === 'video' ? 'Video call' : 'Voice call';
    if (m.attachment) return m.attachment.is_image ? 'Photo' : (m.attachment.name || 'Attachment');
    return m.body || '';
};

const selectClient = (client) => {
    mobileView.value = 'thread';
    if (activeClient.value?.id === client.id) return;
    replyTo.value = null;
    if (client.hash_key) {
        const found = clients.value.find((c) => c.id === client.id);
        if (found) found.unread = 0;
        router.get(route('crm.messages'), { client: client.hash_key }, { preserveScroll: true, preserveState: false });
        return;
    }
    activeClient.value = { ...client };
    threadMessages.value = [];
};

const measurePanel = () => {
    const el = panelRef.value;
    if (!el) return;
    const top = el.getBoundingClientRect().top;
    const isMobile = window.matchMedia('(max-width: 767px)').matches;
    const reserve = isMobile ? 120 : 56;
    panelHeight.value = Math.max(360, Math.floor(window.innerHeight - top - reserve));
};

const pollFeed = async () => {
    if (document.hidden || !activeClient.value) return;
    const client = activeClient.value;
    try {
        const res = await fetch(`${route('crm.messages.feed', client.hash_key)}?after=${lastMessageId.value}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        if (activeClient.value?.id !== client.id) return;
        if (data.messages?.length) {
            mergeMessages(data.messages);
            const found = clients.value.find((c) => c.id === client.id);
            if (found) found.unread = 0;
        }
    } catch {
        /* ignore */
    }
};

const postJson = async (url, body) => {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body ?? {}),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message ?? `Request failed (HTTP ${res.status})`);
    return data;
};

const sendMessage = async () => {
    const text = newMessage.value.trim();
    if (!text || sending.value || !activeClient.value) return;
    sending.value = true;
    sendError.value = '';
    const client = activeClient.value;
    const reply = replyTo.value;
    const tempId = `temp-${Date.now()}`;
    threadMessages.value.push({
        id: tempId, sender_type: SELF, sender_name: 'You', type: 'text', body: text,
        created_at: new Date().toISOString(), is_unsent: false,
        reply_to: reply ? { id: reply.id, sender_name: reply.sender_name, preview: reply.preview, is_unsent: false } : null,
        reactions: [],
    });
    scrollToBottom();
    newMessage.value = '';
    replyTo.value = null;
    try {
        const form = new FormData();
        form.append('body', text);
        if (reply?.id) form.append('reply_to_id', reply.id);
        const res = await fetch(route('crm.messages.store', client.hash_key), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: form,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message ?? `Send failed (HTTP ${res.status})`);
        mergeMessages(data.messages ?? (data.message ? [data.message] : []));
        const found = clients.value.find((c) => c.id === client.id);
        const last = (data.messages ?? []).slice(-1)[0] ?? data.message;
        if (found && last) {
            found.last_message = previewOf(last);
            found.last_sender = SELF;
            found.last_id = last.id;
        }
    } catch (e) {
        threadMessages.value = threadMessages.value.filter((m) => m.id !== tempId);
        sendError.value = e?.message ?? 'Failed to send. Please try again.';
        newMessage.value = text;
    } finally {
        sending.value = false;
    }
};

const triggerFilePick = () => fileInput.value?.click();

const onFilesChosen = async (event) => {
    const files = Array.from(event.target.files ?? []);
    event.target.value = '';
    if (!files.length || !activeClient.value || uploading.value) return;
    const client = activeClient.value;
    const reply = replyTo.value;
    uploading.value = true;
    sendError.value = '';
    try {
        const form = new FormData();
        files.forEach((f) => form.append('files[]', f));
        if (reply?.id) form.append('reply_to_id', reply.id);
        const res = await fetch(route('crm.messages.store', client.hash_key), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: form,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message ?? `Upload failed (HTTP ${res.status})`);
        mergeMessages(data.messages ?? []);
        const found = clients.value.find((c) => c.id === client.id);
        const last = (data.messages ?? []).slice(-1)[0];
        if (found && last) {
            found.last_message = previewOf(last);
            found.last_sender = SELF;
            found.last_id = last.id;
        }
        replyTo.value = null;
    } catch (e) {
        sendError.value = e?.message ?? 'Upload failed.';
    } finally {
        uploading.value = false;
    }
};

const setReply = (m) => {
    replyTo.value = { id: m.id, sender_name: m.sender_type === SELF ? 'You' : m.sender_name, preview: previewOf(m) };
    openMenuId.value = null;
    nextTick(() => document.querySelector('textarea')?.focus());
};

const clearReply = () => { replyTo.value = null; };

const toggleReactionPicker = (m) => {
    openMenuId.value = null;
    reactionPickerId.value = reactionPickerId.value === m.id ? null : m.id;
};

const react = async (m, emoji) => {
    reactionPickerId.value = null;
    if (!activeClient.value) return;
    try {
        const data = await postJson(route('crm.messages.react', { client: activeClient.value.hash_key, message: m.id }), { emoji });
        const target = findMessage(data.message_id ?? m.id);
        if (target) target.reactions = data.reactions ?? [];
    } catch (e) {
        sendError.value = e?.message ?? 'Could not react.';
    }
};

const unsend = async (m, scope) => {
    openMenuId.value = null;
    if (!activeClient.value) return;
    const label = scope === 'everyone' ? 'Unsend for everyone? This cannot be undone.' : 'Remove this message for you?';
    if (!window.confirm(label)) return;
    try {
        if (scope === 'everyone') {
            const data = await postJson(route('crm.messages.unsend-for-everyone', { client: activeClient.value.hash_key, message: m.id }));
            const target = findMessage(m.id);
            if (target && data.message) Object.assign(target, data.message);
        } else {
            await postJson(route('crm.messages.unsend-for-me', { client: activeClient.value.hash_key, message: m.id }));
            threadMessages.value = threadMessages.value.filter((x) => String(x.id) !== String(m.id));
        }
    } catch (e) {
        sendError.value = e?.message ?? 'Could not unsend.';
    }
};

const startCall = async (type) => {
    if (!activeClient.value || !callsEnabled.value) return;
    sendError.value = '';
    const message = await partnerCall.startOutgoing({
        type,
        clientKey: activeClient.value.hash_key,
        peerLabel: activeClient.value.company_name || 'Business Partner',
    });
    if (message) mergeMessages([message]);
};

const callStatusMeta = (m) => {
    const status = m.call?.status;
    if (status === 'missed') return { label: 'Missed', icon: PhoneMissed, tone: 'text-rose-500' };
    if (status === 'declined') return { label: 'Declined', icon: Ban, tone: 'text-amber-500' };
    if (status === 'failed') return { label: 'Failed', icon: PhoneMissed, tone: 'text-rose-500' };
    if (status === 'ringing') return { label: 'Ringing', icon: PhoneCall, tone: 'text-indigo-500' };
    if (status === 'answered' || status === 'ongoing') return { label: 'Ongoing', icon: PhoneCall, tone: 'text-emerald-500' };
    return { label: formatDuration(m.call?.duration), icon: null, tone: 'text-gray-500' };
};

const callDirection = (m) => (m.sender_type === SELF ? 'Outgoing' : 'Incoming');

const formatTime = (ts) => {
    if (!ts) return '';
    const d = new Date(ts);
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    return sameDay
        ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

const formatDateTime = (ts) => {
    if (!ts) return '';
    return new Date(ts).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatDuration = (total) => {
    const s = Math.max(0, Math.floor(total || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const mm = String(m).padStart(2, '0');
    const ss = String(sec).padStart(2, '0');
    return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
};

const formatSize = (bytes) => {
    if (!bytes) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let n = Number(bytes);
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return `${n.toFixed(n >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
};

const closePopovers = () => { reactionPickerId.value = null; openMenuId.value = null; showTeam.value = false; };

// Internal team calling (voice / video) via the global call centre.
const { state: callCenterState, call: placeCall } = useCallCenter();
const showTeam = ref(false);
const teamContacts = ref([]);
const teamHistory = ref([]);
const loadingTeam = ref(false);

const loadTeam = async () => {
    loadingTeam.value = true;
    try {
        const [contacts, history] = await Promise.all([
            fetch(route('crm.calls.contacts'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((r) => (r.ok ? r.json() : { contacts: [] })),
            fetch(route('crm.calls.history'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((r) => (r.ok ? r.json() : { calls: [] })),
        ]);
        teamContacts.value = contacts.contacts ?? [];
        teamHistory.value = (history.calls ?? []).slice(0, 6);
    } catch {
        teamContacts.value = [];
        teamHistory.value = [];
    } finally {
        loadingTeam.value = false;
    }
};

const toggleTeam = () => {
    showTeam.value = !showTeam.value;
    if (showTeam.value) loadTeam();
};

const openCallSettings = () => {
    callSettingsForm.calls_enabled = callsEnabled.value;
    showCallSettings.value = true;
};

const saveCallSettings = () => {
    callSettingsForm.patch(route('crm.messages.call-settings'), {
        preserveScroll: true,
        onSuccess: () => {
            callsEnabled.value = callSettingsForm.calls_enabled;
            showCallSettings.value = false;
            showTeam.value = false;
            if (!callsEnabled.value) showCallLog.value = false;
        },
    });
};

const callTeammate = (contact, type) => {
    if (!callsEnabled.value) return;
    showTeam.value = false;
    if (callCenterState.active || callCenterState.starting) return;
    placeCall(contact, type);
};

const peerOfHistory = (h) => (h.direction === 'outgoing' ? h.callee?.name : h.caller?.name) || 'Monti User';
const historyIcon = (h) => (h.direction === 'outgoing' ? PhoneOutgoing : PhoneIncoming);
const historyTone = (h) => ({
    completed: 'text-emerald-600',
    answered: 'text-emerald-600',
    declined: 'text-rose-500',
    missed: 'text-amber-600',
    failed: 'text-rose-500',
}[h.status] ?? 'text-gray-400');

onMounted(() => {
    nextTick(measurePanel);
    window.addEventListener('resize', measurePanel);
    document.addEventListener('click', closePopovers);
    scrollToBottom();
    pollTimer = setInterval(pollFeed, 4000);
});
onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
    window.removeEventListener('resize', measurePanel);
    document.removeEventListener('click', closePopovers);
});
</script>

<template>
    <Head title="Messaging" />
    <AuthenticatedLayout>
        <div class="bg-slate-50 dark:bg-zinc-950">
            <div ref="panelRef" :style="{ height: panelHeight > 0 ? panelHeight + 'px' : 'calc(100vh - 220px)' }"
                class="mx-auto flex max-w-7xl flex-col gap-3 overflow-hidden p-3 sm:p-4">
                <!-- Header -->
                <div class="relative flex-none overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-700 via-violet-700 to-fuchsia-800 px-5 py-4 text-white shadow-lg shadow-indigo-500/20">
                    <div class="absolute -top-16 -right-16 h-40 w-40 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
                            <MessagesSquare class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-indigo-100">Direct Line</p>
                            <h1 class="text-xl font-black tracking-tight">Messaging</h1>
                        </div>
                        <span v-if="totalUnread > 0" class="rounded-full bg-white px-3 py-1.5 text-xs font-black text-indigo-700 shadow">
                            {{ totalUnread }} unread
                        </span>

                        <div class="relative flex items-center gap-2">
                            <button v-if="callsEnabled" type="button" @click.stop="toggleTeam"
                                class="flex items-center gap-2 rounded-xl bg-white/15 px-3.5 py-2 text-sm font-bold text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/25">
                                <Users class="h-4 w-4" /> Call team
                            </button>
                            <button type="button" @click.stop="openCallSettings" title="Call settings"
                                aria-label="Call settings"
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/25">
                                <Settings2 class="h-4 w-4" />
                            </button>

                            <div v-if="showTeam && callsEnabled" @click.stop
                                class="absolute right-0 top-full z-40 mt-2 w-80 overflow-hidden rounded-2xl border border-gray-100 bg-white text-gray-900 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5 dark:border-zinc-800">
                                    <p class="text-xs font-black uppercase tracking-wider text-gray-500">Call a teammate</p>
                                    <Loader2 v-if="loadingTeam" class="h-3.5 w-3.5 animate-spin text-indigo-500" />
                                </div>

                                <div class="max-h-56 overflow-y-auto">
                                    <div v-for="c in teamContacts" :key="c.id"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-zinc-800/60">
                                        <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-xs font-black text-white">
                                            {{ (c.name || '?').charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-bold">{{ c.name }}</p>
                                            <p class="truncate text-[11px] text-gray-400">{{ c.position || c.role || 'CRM' }}</p>
                                        </div>
                                        <button type="button" @click="callTeammate(c, 'audio')" title="Voice call"
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100 dark:bg-emerald-500/10">
                                            <Phone class="h-4 w-4" />
                                        </button>
                                        <button type="button" @click="callTeammate(c, 'video')" title="Video call"
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100 dark:bg-indigo-500/10">
                                            <Video class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <p v-if="!loadingTeam && teamContacts.length === 0" class="px-4 py-6 text-center text-xs text-gray-400">
                                        No teammates available to call.
                                    </p>
                                </div>

                                <div v-if="teamHistory.length" class="border-t border-gray-100 dark:border-zinc-800">
                                    <p class="px-4 pb-1 pt-2.5 text-[10px] font-black uppercase tracking-wider text-gray-400">Recent calls</p>
                                    <div class="max-h-40 overflow-y-auto pb-1">
                                        <div v-for="h in teamHistory" :key="h.id" class="flex items-center gap-2 px-4 py-1.5 text-xs">
                                            <component :is="historyIcon(h)" class="h-3.5 w-3.5 flex-none" :class="historyTone(h)" />
                                            <span class="min-w-0 flex-1 truncate font-semibold text-gray-700 dark:text-gray-200">{{ peerOfHistory(h) }}</span>
                                            <span class="flex-none text-gray-400">{{ formatTime(h.started_at) }}</span>
                                            <span class="flex-none font-mono text-gray-500">{{ h.duration ? formatDuration(h.duration) : '--:--' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Workspace -->
                <div class="flex min-h-0 flex-1 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <!-- Client list -->
                    <aside :class="mobileView === 'thread' ? 'hidden md:flex' : 'flex'" class="w-full flex-col overflow-hidden border-r border-gray-100 dark:border-zinc-800 md:w-80 md:flex-shrink-0 md:flex">
                        <div class="flex-none border-b border-gray-100 p-3 dark:border-zinc-800">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input v-model="search" type="text" placeholder="Search partners..."
                                    class="w-full rounded-xl border-0 bg-slate-100 py-2.5 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 outline-none dark:bg-zinc-800 dark:text-white" />
                            </div>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <button v-for="c in filteredClients" :key="c.id" @click="selectClient(c)"
                                :class="activeClient?.id === c.id ? 'bg-indigo-50 dark:bg-indigo-950/40' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                class="flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3 text-left transition-colors dark:border-zinc-800/60">
                                <div class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-sm font-black text-white">
                                    {{ (c.company_name || '?').charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ c.company_name }}</p>
                                        <span class="flex-none text-[10px] text-gray-400">{{ formatTime(c.last_at) }}</span>
                                    </div>
                                    <p class="flex items-center gap-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                        <span v-if="c.last_sender === 'crm'" class="font-semibold text-indigo-500">You: </span>
                                        <Phone v-if="c.last_type === 'call'" class="h-3 w-3 flex-none text-indigo-400" />
                                        <span class="truncate">{{ c.last_message || 'No messages yet' }}</span>
                                    </p>
                                </div>
                                <span v-if="c.unread > 0" class="mt-1 flex h-5 min-w-5 flex-none items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-black text-white">{{ c.unread }}</span>
                            </button>
                            <p v-if="filteredClients.length === 0" class="p-6 text-center text-sm text-gray-400">No partners found.</p>
                        </div>
                    </aside>

                    <!-- Thread -->
                    <section :class="mobileView === 'list' ? 'hidden md:flex' : 'flex'" class="relative min-w-0 flex-1 flex-col overflow-hidden">
                        <template v-if="activeClient">
                            <header class="flex flex-none items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-zinc-800">
                                <button @click="mobileView = 'list'" class="text-gray-500 hover:text-gray-800 md:hidden dark:hover:text-white">
                                    <ChevronLeft class="h-5 w-5" />
                                </button>
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-sm font-black text-white">
                                    {{ (activeClient.company_name || '?').charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ activeClient.company_name }}</p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ activeClient.contact_person || activeClient.email || 'Business Partner' }}
                                    </p>
                                </div>
                                <button v-if="callsEnabled" @click="startCall('audio')" title="Voice call"
                                    class="flex h-9 w-9 items-center justify-center rounded-full text-indigo-600 transition hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-950/50">
                                    <Phone class="h-4 w-4" />
                                </button>
                                <button v-if="callsEnabled" @click="startCall('video')" title="Video call"
                                    class="flex h-9 w-9 items-center justify-center rounded-full text-indigo-600 transition hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-950/50">
                                    <Video class="h-4 w-4" />
                                </button>
                                <button @click="showCallLog = !showCallLog" title="Call log"
                                    :class="showCallLog ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-slate-100 dark:hover:bg-zinc-800'"
                                    class="relative flex h-9 w-9 items-center justify-center rounded-full transition">
                                    <History class="h-4 w-4" />
                                    <span v-if="callHistory.length" class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-black text-white">{{ callHistory.length }}</span>
                                </button>
                            </header>

                            <!-- Call log panel -->
                            <div v-if="showCallLog" class="min-h-0 flex-1 overflow-y-auto bg-slate-50/60 p-4 dark:bg-zinc-950/40">
                                <p class="mb-3 text-xs font-black uppercase tracking-wide text-gray-400">Call history · {{ activeClient.company_name }}</p>
                                <div v-if="callHistory.length === 0" class="pt-10 text-center text-sm text-gray-400">No calls yet.</div>
                                <div class="space-y-2">
                                    <div v-for="m in callHistory" :key="'log-' + m.id"
                                        class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                        <component :is="m.call?.type === 'video' ? Video : Phone" class="h-4 w-4 flex-none text-indigo-500" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-gray-800 dark:text-white">
                                                {{ callDirection(m) }} {{ m.call?.type === 'video' ? 'video call' : 'voice call' }}
                                            </p>
                                            <p class="text-xs text-gray-500">{{ formatDateTime(m.created_at) }} · {{ m.sender_name }}</p>
                                        </div>
                                        <span :class="callStatusMeta(m).tone" class="text-xs font-bold">{{ callStatusMeta(m).label }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages -->
                            <div v-else ref="messagesContainer" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50/60 p-4 dark:bg-zinc-950/40">
                                <template v-for="m in threadMessages" :key="m.id">
                                    <!-- Call record -->
                                    <div v-if="m.type === 'call'" class="flex justify-center">
                                        <div class="flex items-center gap-2 rounded-full bg-white px-4 py-2 text-xs shadow-sm ring-1 ring-black/5 dark:bg-zinc-800 dark:text-gray-200">
                                            <component :is="m.call?.type === 'video' ? Video : Phone" class="h-3.5 w-3.5 text-indigo-500" />
                                            <component :is="m.sender_type === SELF ? PhoneOutgoing : PhoneIncoming" class="h-3.5 w-3.5 text-gray-400" />
                                            <span class="font-semibold">{{ callDirection(m) }} {{ m.call?.type === 'video' ? 'video' : 'voice' }} call</span>
                                            <span :class="callStatusMeta(m).tone" class="font-bold">{{ callStatusMeta(m).label }}</span>
                                            <span class="text-gray-400">· {{ formatTime(m.created_at) }}</span>
                                        </div>
                                    </div>

                                    <!-- Regular / attachment message -->
                                    <div v-else :class="m.sender_type === SELF ? 'justify-end' : 'justify-start'" class="group flex">
                                        <div class="relative max-w-[78%]">
                                            <!-- hover actions -->
                                            <div :class="m.sender_type === SELF ? 'right-0' : 'left-0'"
                                                class="absolute -top-3 z-10 hidden items-center gap-0.5 rounded-full border border-gray-100 bg-white p-0.5 shadow-md group-hover:flex dark:border-zinc-700 dark:bg-zinc-800">
                                                <button @click.stop="toggleReactionPicker(m)" class="flex h-7 w-7 items-center justify-center rounded-full text-gray-500 hover:bg-slate-100 dark:hover:bg-zinc-700" title="React">
                                                    <Smile class="h-4 w-4" />
                                                </button>
                                                <button @click.stop="setReply(m)" class="flex h-7 w-7 items-center justify-center rounded-full text-gray-500 hover:bg-slate-100 dark:hover:bg-zinc-700" title="Reply">
                                                    <CornerUpLeft class="h-4 w-4" />
                                                </button>
                                                <button @click.stop="openMenuId = openMenuId === m.id ? null : m.id" class="flex h-7 w-7 items-center justify-center rounded-full text-gray-500 hover:bg-slate-100 dark:hover:bg-zinc-700" title="More">
                                                    <MoreVertical class="h-4 w-4" />
                                                </button>
                                            </div>

                                            <!-- emoji picker -->
                                            <div v-if="reactionPickerId === m.id" @click.stop
                                                :class="m.sender_type === SELF ? 'right-0' : 'left-0'"
                                                class="absolute -top-12 z-20 flex items-center gap-1 rounded-full border border-gray-100 bg-white px-2 py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                                <button v-for="e in REACTIONS" :key="e" @click="react(m, e)"
                                                    class="text-lg leading-none transition hover:scale-125">{{ e }}</button>
                                            </div>

                                            <!-- unsend menu -->
                                            <div v-if="openMenuId === m.id" @click.stop
                                                :class="m.sender_type === SELF ? 'right-0' : 'left-0'"
                                                class="absolute -top-3 z-20 mt-6 w-48 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
                                                <button @click="unsend(m, 'me')" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold text-gray-700 hover:bg-slate-100 dark:text-gray-200 dark:hover:bg-zinc-700">
                                                    <Trash2 class="h-3.5 w-3.5" /> Unsend for me
                                                </button>
                                                <button v-if="m.sender_type === SELF" @click="unsend(m, 'everyone')" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40">
                                                    <Ban class="h-3.5 w-3.5" /> Unsend for everyone
                                                </button>
                                            </div>

                                            <div :class="m.sender_type === SELF ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-white text-gray-800 rounded-bl-sm dark:bg-zinc-800 dark:text-gray-100'"
                                                class="overflow-hidden rounded-2xl shadow-sm ring-1 ring-black/5">
                                                <!-- reply quote -->
                                                <div v-if="m.reply_to" :class="m.sender_type === SELF ? 'bg-black/15' : 'bg-slate-100 dark:bg-zinc-700/60'"
                                                    class="mx-2 mt-2 rounded-lg border-l-2 border-indigo-400 px-3 py-1.5">
                                                    <p class="text-[10px] font-black uppercase tracking-wide opacity-80">{{ m.reply_to.sender_name }}</p>
                                                    <p class="truncate text-xs opacity-90">{{ m.reply_to.preview }}</p>
                                                </div>

                                                <!-- image attachment -->
                                                <img v-if="m.attachment && m.attachment.is_image" :src="m.attachment.url" :alt="m.attachment.name"
                                                    @click="imageViewer = m.attachment.url"
                                                    class="max-h-64 w-full cursor-pointer object-cover" />

                                                <!-- file attachment -->
                                                <a v-else-if="m.attachment" :href="m.attachment.url" target="_blank" download
                                                    :class="m.sender_type === SELF ? 'hover:bg-white/10' : 'hover:bg-slate-50 dark:hover:bg-zinc-700/60'"
                                                    class="flex items-center gap-3 px-4 py-3">
                                                    <span :class="m.sender_type === SELF ? 'bg-white/20' : 'bg-indigo-50 dark:bg-zinc-700'" class="flex h-9 w-9 flex-none items-center justify-center rounded-lg">
                                                        <FileText class="h-4 w-4" />
                                                    </span>
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-sm font-semibold">{{ m.attachment.name }}</span>
                                                        <span class="block text-[10px] opacity-70">{{ formatSize(m.attachment.size) }}</span>
                                                    </span>
                                                    <Download class="ml-1 h-4 w-4 flex-none opacity-60" />
                                                </a>

                                                <div class="px-4 pb-2.5 pt-2">
                                                    <p v-if="m.is_unsent" class="text-sm italic opacity-70">This message was deleted</p>
                                                    <p v-else-if="m.body" class="whitespace-pre-wrap break-words text-sm">{{ m.body }}</p>
                                                    <p :class="m.sender_type === SELF ? 'text-indigo-200' : 'text-gray-400'" class="mt-1 text-right text-[10px]">{{ formatTime(m.created_at) }}</p>
                                                </div>
                                            </div>

                                            <!-- reactions -->
                                            <div v-if="m.reactions && m.reactions.length" :class="m.sender_type === SELF ? 'justify-end' : 'justify-start'" class="mt-1 flex flex-wrap gap-1">
                                                <button v-for="r in m.reactions" :key="r.emoji" @click="react(m, r.emoji)"
                                                    :class="r.mine ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-950/50' : 'border-gray-200 bg-white dark:border-zinc-700 dark:bg-zinc-800'"
                                                    class="flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs shadow-sm">
                                                    <span>{{ r.emoji }}</span>
                                                    <span class="font-bold text-gray-500 dark:text-gray-300">{{ r.count }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <p v-if="threadMessages.length === 0" class="pt-10 text-center text-sm text-gray-400">No messages yet. Say hello 👋</p>
                            </div>

                            <!-- Composer -->
                            <footer v-if="!showCallLog" class="flex-none border-t border-gray-100 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <p v-if="sendError" class="mb-2 text-xs font-semibold text-rose-500">{{ sendError }}</p>
                                <div v-if="replyTo" class="mb-2 flex items-center gap-2 rounded-xl border-l-4 border-indigo-500 bg-slate-50 px-3 py-2 dark:bg-zinc-800">
                                    <CornerUpLeft class="h-4 w-4 flex-none text-indigo-500" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[10px] font-black uppercase tracking-wide text-indigo-500">Replying to {{ replyTo.sender_name }}</p>
                                        <p class="truncate text-xs text-gray-600 dark:text-gray-300">{{ replyTo.preview }}</p>
                                    </div>
                                    <button @click="clearReply" class="text-gray-400 hover:text-gray-700 dark:hover:text-white"><X class="h-4 w-4" /></button>
                                </div>
                                <div class="flex items-end gap-2">
                                    <input ref="fileInput" type="file" multiple class="hidden"
                                        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" @change="onFilesChosen" />
                                    <button @click="triggerFilePick" :disabled="uploading" title="Attach file or image"
                                        class="flex h-11 w-11 flex-none items-center justify-center rounded-2xl bg-slate-100 text-gray-600 transition hover:bg-slate-200 disabled:opacity-50 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700">
                                        <Loader2 v-if="uploading" class="h-5 w-5 animate-spin" />
                                        <Paperclip v-else class="h-5 w-5" />
                                    </button>
                                    <textarea v-model="newMessage" rows="1" placeholder="Type a message..."
                                        @keydown.enter.exact.prevent="sendMessage"
                                        class="max-h-32 flex-1 resize-none rounded-2xl border-0 bg-slate-100 px-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-500 outline-none dark:bg-zinc-800 dark:text-white"></textarea>
                                    <button @click="sendMessage" :disabled="sending || !newMessage.trim()"
                                        class="flex h-11 w-11 flex-none items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                                        <Loader2 v-if="sending" class="h-5 w-5 animate-spin" />
                                        <Send v-else class="h-5 w-5" />
                                    </button>
                                </div>
                            </footer>
                        </template>
                        <div v-else class="flex flex-1 items-center justify-center text-sm text-gray-400">Select a partner to start chatting.</div>
                    </section>
                </div>
            </div>
        </div>

        <!-- Call settings -->
        <div v-if="showCallSettings" @click.self="showCallSettings = false"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-3xl border border-gray-100 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white">Call settings</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Turn voice and video calling on or off for CRM and business partners.
                        </p>
                    </div>
                    <button @click="showCallSettings = false" aria-label="Close call settings"
                        class="rounded-lg p-1 text-gray-400 transition hover:bg-slate-100 hover:text-gray-700 dark:hover:bg-zinc-800 dark:hover:text-white">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <button type="button" @click="callSettingsForm.calls_enabled = !callSettingsForm.calls_enabled"
                    class="mt-5 flex w-full items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-slate-50 px-4 py-3.5 text-left transition hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                    <span>
                        <span class="block text-sm font-bold text-gray-900 dark:text-white">Allow calls</span>
                        <span class="mt-0.5 block text-[11px] text-gray-500 dark:text-gray-400">
                            {{ callSettingsForm.calls_enabled ? 'Call and video buttons are visible.' : 'All call buttons are hidden.' }}
                        </span>
                    </span>
                    <span :class="callSettingsForm.calls_enabled ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-zinc-600'"
                        class="relative inline-flex h-6 w-11 flex-none items-center rounded-full transition">
                        <span :class="callSettingsForm.calls_enabled ? 'translate-x-6' : 'translate-x-0.5'"
                            class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition" />
                    </span>
                </button>

                <div class="mt-5 flex gap-2">
                    <button type="button" @click="showCallSettings = false"
                        class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 transition hover:bg-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700">
                        Cancel
                    </button>
                    <button type="button" @click="saveCallSettings" :disabled="callSettingsForm.processing"
                        class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                        Save
                    </button>
                </div>
            </div>
        </div>

        <!-- Image viewer -->
        <div v-if="imageViewer" @click="imageViewer = null" class="fixed inset-0 z-[55] flex items-center justify-center bg-black/80 p-4 backdrop-blur">
            <img :src="imageViewer" class="max-h-[90vh] max-w-[92vw] rounded-xl shadow-2xl" />
            <button class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25">
                <X class="h-5 w-5" />
            </button>
        </div>
    </AuthenticatedLayout>
</template>
