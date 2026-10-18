<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    MessagesSquare, Send, Loader2, Paperclip, X, Smile, CornerUpLeft, MoreVertical,
    Phone, Video, History, FileText, Download, Trash2, PhoneCall, PhoneIncoming,
    PhoneOutgoing, PhoneMissed, Ban,
} from 'lucide-vue-next';
import { usePartnerCallCenter } from '@/composables/crm/usePartnerCallCenter';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    callLogs: { type: Array, default: () => [] },
});

const SELF = 'client';
const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

const threadMessages = ref([...(props.messages ?? [])]);
const messagesContainer = ref(null);
const panelRef = ref(null);
const panelHeight = ref(0);
const newMessage = ref('');
const sending = ref(false);
const uploading = ref(false);
const sendError = ref('');
const liveState = ref('live');
const replyTo = ref(null);
const reactionPickerId = ref(null);
const openMenuId = ref(null);
const imageViewer = ref(null);
const showCallLog = ref(false);
const fileInput = ref(null);
let pollTimer = null;

// Calls are handled by the global PartnerCallOverlay; this page only kicks off
// an outgoing call.
const partnerCall = usePartnerCallCenter();

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
    for (const m of incoming ?? []) {
        if (m.id == null) continue;
        const idx = threadMessages.value.findIndex((x) => String(x.id) === String(m.id));
        if (idx !== -1) {
            threadMessages.value[idx] = { ...threadMessages.value[idx], ...m };
        } else {
            const tempIdx = threadMessages.value.findIndex((x) => String(x.id).startsWith('temp-') && x.sender_type === m.sender_type);
            if (tempIdx !== -1) threadMessages.value.splice(tempIdx, 1);
            threadMessages.value.push(m);
            added = true;
        }
    }
    if (added) {
        threadMessages.value.sort((a, b) => (Number(a.id) || 0) - (Number(b.id) || 0));
        scrollToBottom();
    }
};

const findMessage = (id) => threadMessages.value.find((m) => String(m.id) === String(id));

const previewOf = (m) => {
    if (!m) return '';
    if (m.is_unsent) return 'This message was deleted';
    if (m.type === 'call') return m.call?.type === 'video' ? 'Video call' : 'Voice call';
    if (m.attachment) return m.attachment.is_image ? 'Photo' : (m.attachment.name || 'Attachment');
    return m.body || '';
};

const measurePanel = () => {
    const el = panelRef.value;
    if (!el) return;
    const top = el.getBoundingClientRect().top;
    const isMobile = window.matchMedia('(max-width: 767px)').matches;
    const reserve = isMobile ? 120 : 56;
    panelHeight.value = Math.max(360, Math.floor(window.innerHeight - top - reserve));
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

const pollFeed = async () => {
    if (document.hidden) return;
    try {
        const res = await fetch(`${route('client.messages.feed')}?after=${lastMessageId.value}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        mergeMessages(data.messages);
        liveState.value = 'live';
    } catch {
        liveState.value = 'error';
    }
};

const sendMessage = async () => {
    const text = newMessage.value.trim();
    if (!text || sending.value) return;
    sending.value = true;
    sendError.value = '';
    const reply = replyTo.value;
    const tempId = `temp-${Date.now()}`;
    threadMessages.value.push({
        id: tempId, sender_type: SELF, sender_name: 'You', type: 'text', body: text,
        created_at: new Date().toISOString(), is_unsent: false,
        reply_to: reply ? { id: reply.id, sender_name: 'Monti Textiles', preview: reply.preview, is_unsent: false } : null,
        reactions: [],
    });
    scrollToBottom();
    newMessage.value = '';
    replyTo.value = null;
    try {
        const form = new FormData();
        form.append('body', text);
        if (reply?.id) form.append('reply_to_id', reply.id);
        const res = await fetch(route('client.messages.store'), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: form,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message ?? `Send failed (HTTP ${res.status})`);
        mergeMessages(data.messages ?? (data.message ? [data.message] : []));
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
    if (!files.length || uploading.value) return;
    const reply = replyTo.value;
    uploading.value = true;
    sendError.value = '';
    try {
        const form = new FormData();
        files.forEach((f) => form.append('files[]', f));
        if (reply?.id) form.append('reply_to_id', reply.id);
        const res = await fetch(route('client.messages.store'), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: form,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message ?? `Upload failed (HTTP ${res.status})`);
        mergeMessages(data.messages ?? []);
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
    try {
        const data = await postJson(route('client.messages.react', m.id), { emoji });
        const target = findMessage(data.message_id ?? m.id);
        if (target) target.reactions = data.reactions ?? [];
    } catch (e) {
        sendError.value = e?.message ?? 'Could not react.';
    }
};

const unsend = async (m, scope) => {
    openMenuId.value = null;
    const label = scope === 'everyone' ? 'Unsend for everyone? This cannot be undone.' : 'Remove this message for you?';
    if (!window.confirm(label)) return;
    try {
        if (scope === 'everyone') {
            const data = await postJson(route('client.messages.unsend-for-everyone', m.id));
            const target = findMessage(m.id);
            if (target && data.message) Object.assign(target, data.message);
        } else {
            await postJson(route('client.messages.unsend-for-me', m.id));
            threadMessages.value = threadMessages.value.filter((x) => String(x.id) !== String(m.id));
        }
    } catch (e) {
        sendError.value = e?.message ?? 'Could not unsend.';
    }
};

const startCall = async (type) => {
    sendError.value = '';
    const message = await partnerCall.startOutgoing({ type });
    if (message) mergeMessages([message]);
};

const callStatusMeta = (m) => {
    const status = m.call?.status;
    if (status === 'missed') return { label: 'Missed', tone: 'text-rose-500' };
    if (status === 'declined') return { label: 'Declined', tone: 'text-amber-500' };
    if (status === 'failed') return { label: 'Failed', tone: 'text-rose-500' };
    if (status === 'ringing') return { label: 'Ringing', tone: 'text-indigo-500' };
    if (status === 'answered' || status === 'ongoing') return { label: 'Ongoing', tone: 'text-emerald-500' };
    return { label: formatDuration(m.call?.duration), tone: 'text-gray-500' };
};

const callDirection = (m) => (m.sender_type === SELF ? 'Outgoing' : 'Incoming');

const formatTime = (ts) => {
    if (!ts) return '';
    const d = new Date(ts);
    const now = new Date();
    return d.toDateString() === now.toDateString()
        ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatDateTime = (ts) => (ts ? new Date(ts).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '');

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

const closePopovers = () => { reactionPickerId.value = null; openMenuId.value = null; };

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
    <Head title="Messages" />
    <AuthenticatedLayout>
        <div class="bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div ref="panelRef" :style="{ height: panelHeight > 0 ? panelHeight + 'px' : 'calc(100vh - 220px)' }"
                class="mx-auto flex max-w-4xl flex-col gap-3 overflow-hidden p-3 sm:p-4">
                <!-- Header -->
                <div class="relative flex-none overflow-hidden rounded-2xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 py-4 text-white shadow-lg shadow-indigo-500/20">
                    <div class="absolute -top-16 -right-16 h-40 w-40 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
                            <MessagesSquare class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">Direct Line</p>
                            <h1 class="text-xl font-black tracking-tight">Messages</h1>
                        </div>
                        <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25">
                            <span :class="liveState === 'error' ? 'bg-rose-300' : 'bg-emerald-300'" class="h-1.5 w-1.5 rounded-full animate-pulse" />
                            {{ liveState === 'error' ? 'Reconnecting' : 'Live' }}
                        </span>
                    </div>
                </div>

                <!-- Chat -->
                <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <header class="flex flex-none items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-zinc-800">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-sm font-black text-white">M</div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-900 dark:text-white">Monti Textiles</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">CRM Support Team</p>
                        </div>
                        <button @click="startCall('audio')" title="Voice call"
                            class="flex h-9 w-9 items-center justify-center rounded-full text-indigo-600 transition hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-950/50">
                            <Phone class="h-4 w-4" />
                        </button>
                        <button @click="startCall('video')" title="Video call"
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

                    <!-- Call log -->
                    <div v-if="showCallLog" class="min-h-0 flex-1 overflow-y-auto bg-slate-50/60 p-4 dark:bg-zinc-950/40">
                        <p class="mb-3 text-xs font-black uppercase tracking-wide text-gray-400">Call history</p>
                        <div v-if="callHistory.length === 0" class="pt-10 text-center text-sm text-gray-400">No calls yet.</div>
                        <div class="space-y-2">
                            <div v-for="m in callHistory" :key="'log-' + m.id"
                                class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <component :is="m.call?.type === 'video' ? Video : Phone" class="h-4 w-4 flex-none text-indigo-500" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-gray-800 dark:text-white">{{ callDirection(m) }} {{ m.call?.type === 'video' ? 'video call' : 'voice call' }}</p>
                                    <p class="text-xs text-gray-500">{{ formatDateTime(m.created_at) }}</p>
                                </div>
                                <span :class="callStatusMeta(m).tone" class="text-xs font-bold">{{ callStatusMeta(m).label }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div v-else ref="messagesContainer" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50/60 p-4 dark:bg-zinc-950/40">
                        <template v-for="m in threadMessages" :key="m.id">
                            <div v-if="m.type === 'call'" class="flex justify-center">
                                <div class="flex items-center gap-2 rounded-full bg-white px-4 py-2 text-xs shadow-sm ring-1 ring-black/5 dark:bg-zinc-800 dark:text-gray-200">
                                    <component :is="m.call?.type === 'video' ? Video : Phone" class="h-3.5 w-3.5 text-indigo-500" />
                                    <component :is="m.sender_type === SELF ? PhoneOutgoing : PhoneIncoming" class="h-3.5 w-3.5 text-gray-400" />
                                    <span class="font-semibold">{{ callDirection(m) }} {{ m.call?.type === 'video' ? 'video' : 'voice' }} call</span>
                                    <span :class="callStatusMeta(m).tone" class="font-bold">{{ callStatusMeta(m).label }}</span>
                                    <span class="text-gray-400">· {{ formatTime(m.created_at) }}</span>
                                </div>
                            </div>

                            <div v-else :class="m.sender_type === SELF ? 'justify-end' : 'justify-start'" class="group flex">
                                <div class="relative max-w-[78%]">
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

                                    <div v-if="reactionPickerId === m.id" @click.stop
                                        :class="m.sender_type === SELF ? 'right-0' : 'left-0'"
                                        class="absolute -top-12 z-20 flex items-center gap-1 rounded-full border border-gray-100 bg-white px-2 py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                        <button v-for="e in REACTIONS" :key="e" @click="react(m, e)" class="text-lg leading-none transition hover:scale-125">{{ e }}</button>
                                    </div>

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
                                        <div v-if="m.reply_to" :class="m.sender_type === SELF ? 'bg-black/15' : 'bg-slate-100 dark:bg-zinc-700/60'"
                                            class="mx-2 mt-2 rounded-lg border-l-2 border-indigo-400 px-3 py-1.5">
                                            <p class="text-[10px] font-black uppercase tracking-wide opacity-80">{{ m.reply_to.sender_name }}</p>
                                            <p class="truncate text-xs opacity-90">{{ m.reply_to.preview }}</p>
                                        </div>

                                        <img v-if="m.attachment && m.attachment.is_image" :src="m.attachment.url" :alt="m.attachment.name"
                                            @click="imageViewer = m.attachment.url" class="max-h-64 w-full cursor-pointer object-cover" />

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
                        <p v-if="threadMessages.length === 0" class="pt-10 text-center text-sm text-gray-400">No messages yet. Send the team a note 👋</p>
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
                </div>
            </div>
        </div>

        <div v-if="imageViewer" @click="imageViewer = null" class="fixed inset-0 z-[55] flex items-center justify-center bg-black/80 p-4 backdrop-blur">
            <img :src="imageViewer" class="max-h-[90vh] max-w-[92vw] rounded-xl shadow-2xl" />
            <button class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25">
                <X class="h-5 w-5" />
            </button>
        </div>
    </AuthenticatedLayout>
</template>
