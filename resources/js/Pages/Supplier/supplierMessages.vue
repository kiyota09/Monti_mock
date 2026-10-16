<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { MessagesSquare, Send, Loader2, Paperclip, Calendar, CheckCheck } from 'lucide-vue-next';

const props = defineProps({
    auth: Object,
    messages: { type: Array, default: () => [] },
});

const supplierData = computed(() => props.auth?.supplier || props.auth?.user || {});

const threadMessages = ref([...(props.messages ?? [])]);
const messagesContainer = ref(null);
const fileInput = ref(null);
const newMessage = ref('');
const sending = ref(false);
const sendError = ref('');
const pollError = ref('');
const liveState = ref('live');
let pollTimer = null;

const lastMessageId = computed(() => threadMessages.value.reduce((m, x) => Math.max(m, typeof x.id === 'number' ? x.id : 0), 0));
const connectionLabel = computed(() => liveState.value === 'live' ? 'Live' : liveState.value === 'offline' ? 'Offline' : 'Reconnecting');

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const isNearBottom = () => {
    const el = messagesContainer.value;
    if (!el) return true;
    return el.scrollHeight - el.scrollTop - el.clientHeight < 120;
};

const scrollToBottom = (force = false) => {
    nextTick(() => {
        const el = messagesContainer.value;
        if (el && (force || isNearBottom())) el.scrollTop = el.scrollHeight;
    });
};

const mergeMessages = (incoming, forceScroll = false) => {
    const seen = new Set(threadMessages.value.map((m) => m.id));
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
        const res = await fetch(`${route('supplier.chat.feed')}?after=${lastMessageId.value}`, {
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

const sendMessage = async () => {
    const text = newMessage.value.trim();
    if (!text || sending.value) return;
    sending.value = true;
    sendError.value = '';
    const tempId = `temp-${Date.now()}`;
    threadMessages.value.push({ id: tempId, sender_type: 'supplier', message: text, created_at: new Date().toISOString() });
    scrollToBottom(true);
    newMessage.value = '';
    try {
        const res = await fetch(route('supplier.chat.send'), {
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
        threadMessages.value = threadMessages.value.filter((m) => m.id !== tempId);
        sendError.value = e?.message ?? 'Failed to send. Please try again.';
    } finally {
        sending.value = false;
    }
};

const uploadAttachment = async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    sendError.value = '';
    const tempId = `temp-${Date.now()}`;
    threadMessages.value.push({ id: tempId, sender_type: 'supplier', message: `Sending ${file.name}…`, created_at: new Date().toISOString() });
    scrollToBottom(true);
    try {
        const formData = new FormData();
        formData.append('attachment', file);
        formData.append('message', `Sent an attachment: ${file.name}`);
        const res = await fetch(route('supplier.chat.send'), {
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

const formatTime = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const formatDateTime = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleString('en-PH');
};

onMounted(() => {
    scrollToBottom(true);
    pollTimer = setInterval(pollFeed, 2500);
    window.addEventListener('online', () => { liveState.value = 'live'; pollFeed(); });
    window.addEventListener('offline', () => { liveState.value = 'offline'; });
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <Head title="Messages | Monti Textile" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                    <MessagesSquare class="w-7 h-7 text-emerald-500" /> Messages
                </h1>
                <p class="text-slate-500 text-sm mt-0.5">Direct line with the Monti Textile buying team — replies appear here in real time.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full border"
                    :class="liveState === 'live' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : liveState === 'offline' ? 'bg-slate-100 text-slate-500 border-slate-200' : 'bg-rose-50 text-rose-600 border-rose-200'">
                    <span class="h-1.5 w-1.5 rounded-full animate-pulse"
                        :class="liveState === 'live' ? 'bg-emerald-500' : liveState === 'offline' ? 'bg-slate-400' : 'bg-rose-500'" />
                    {{ connectionLabel }}
                </span>
                <div class="flex items-center gap-3 bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="h-10 w-10 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg flex items-center justify-center font-black text-lg">
                        {{ (supplierData.business_name || 'S').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ supplierData.business_name }}</p>
                        <p class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest">Official Vendor</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col h-[calc(100vh-280px)] min-h-[420px]">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/60 flex items-center gap-2">
                <MessagesSquare class="w-5 h-5 text-emerald-600" />
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-700 dark:text-slate-200">Conversation with Monti Textile</h2>
                <span class="ml-auto rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-black px-2.5 py-1">{{ threadMessages.length }} messages</span>
            </div>

            <div ref="messagesContainer" class="flex-1 overflow-y-auto p-5 space-y-3 bg-slate-50/50 dark:bg-slate-900">
                <div v-if="threadMessages.length === 0" class="flex flex-col items-center justify-center py-14 text-center">
                    <MessagesSquare class="w-10 h-10 text-slate-300 mb-2" />
                    <p class="text-sm font-bold text-slate-500">No messages yet.</p>
                    <p class="text-xs text-slate-400 mt-1">Send your first message to the buying team below.</p>
                </div>
                <div v-for="msg in threadMessages" :key="msg.id">
                    <!-- System event (meeting scheduled, request sent) -->
                    <div v-if="msg.is_system_event" class="flex justify-center">
                        <p class="text-[11px] font-bold text-slate-500 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded-full max-w-[90%] text-center">
                            {{ msg.message }}
                        </p>
                    </div>
                    <!-- Supplier's own message (right) -->
                    <div v-else-if="msg.sender_type === 'supplier'" class="flex justify-end">
                        <div class="max-w-[75%] rounded-2xl rounded-br-md px-4 py-2.5 bg-emerald-600 text-white shadow-sm">
                            <p class="text-sm font-medium whitespace-pre-wrap">{{ msg.message }}</p>
                            <div v-if="msg.meeting_data" class="mt-2 text-xs border-t border-white/20 pt-1.5 flex items-center gap-1">
                                <Calendar class="h-3 w-3 shrink-0" />
                                Meeting: {{ msg.meeting_data.type }} at {{ msg.meeting_data.location }} on {{ formatDateTime(msg.meeting_data.scheduled_at) }}
                            </div>
                            <div v-if="msg.attachment" class="mt-1">
                                <a :href="msg.attachment_url || msg.attachment" target="_blank" class="text-xs underline flex items-center gap-1 text-emerald-50">
                                    <Paperclip class="h-3 w-3" /> Attachment
                                </a>
                            </div>
                            <p class="text-[10px] opacity-70 mt-1 text-right flex items-center justify-end gap-1">
                                {{ formatTime(msg.created_at) }} <CheckCheck class="h-3 w-3" />
                            </p>
                        </div>
                    </div>
                    <!-- ECO / Monti team message (left) -->
                    <div v-else class="flex justify-start">
                        <div class="max-w-[75%] rounded-2xl rounded-bl-md px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 shadow-sm">
                            <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600 mb-1">Monti Textile</p>
                            <p class="text-sm font-medium whitespace-pre-wrap">{{ msg.message }}</p>
                            <div v-if="msg.meeting_data" class="mt-2 text-xs border-t border-slate-100 dark:border-slate-700 pt-1.5 flex items-center gap-1 text-slate-500">
                                <Calendar class="h-3 w-3 shrink-0" />
                                Meeting: {{ msg.meeting_data.type }} at {{ msg.meeting_data.location }} on {{ formatDateTime(msg.meeting_data.scheduled_at) }}
                            </div>
                            <div v-if="msg.attachment" class="mt-1">
                                <a :href="msg.attachment_url || msg.attachment" target="_blank" class="text-xs underline flex items-center gap-1 text-emerald-700">
                                    <Paperclip class="h-3 w-3" /> Attachment
                                </a>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1">{{ formatTime(msg.created_at) }}</p>
                        </div>
                    </div>
                </div>
                <div v-if="pollError" class="flex justify-center">
                    <p class="text-[11px] font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-full">Reconnecting… live feed paused</p>
                </div>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800 p-4 bg-white dark:bg-slate-900">
                <p v-if="sendError" class="mb-2 text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-2 rounded-xl">{{ sendError }}</p>
                <form @submit.prevent="sendMessage" class="flex gap-3">
                    <input v-model="newMessage" type="text" placeholder="Type your reply to Monti Textile…"
                        class="flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-4 py-3 text-sm font-medium text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none transition" />
                    <button type="button" @click="fileInput.click()" title="Attach a file"
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-emerald-100 hover:text-emerald-600 transition active:scale-95">
                        <Paperclip class="h-5 w-5" />
                    </button>
                    <input ref="fileInput" type="file" class="hidden" @change="uploadAttachment" />
                    <button type="submit" :disabled="sending || !newMessage.trim()"
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-lg shadow-emerald-500/20 hover:bg-emerald-700 active:scale-95 transition disabled:opacity-40">
                        <Send v-if="!sending" class="h-5 w-5" />
                        <Loader2 v-else class="h-5 w-5 animate-spin" />
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
