<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TemplateWidget from '@/Components/crm/TemplateWidget.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import {
    Mail, Search, Send, Loader2, ChevronLeft, Plus, X,
    SendHorizonal, FileSignature, Inbox, CircleAlert, CheckCircle2, Clock,
    RefreshCw,
} from 'lucide-vue-next';

const props = defineProps({
    threads: { type: Array, default: () => [] },
    selected: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const { canEdit } = usePageAccess();
const canReply = computed(() => canEdit('CRM', 'email'));

const page = usePage();
const syncError = computed(() => page.props.errors?.sync || '');
const flashMessage = computed(() => page.props.flash?.message || '');
const syncing = ref(false);

const search = ref('');
const mobileView = ref(props.selected ? 'thread' : 'list');
const showCompose = ref(false);
const conversationEl = ref(null);

const replyForm = useForm({ subject: '', body: '' });
const composeForm = useForm({ to_email: '', to_name: '', subject: '', body: '' });
const registrationForm = useForm({});

const filteredThreads = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.threads;
    return props.threads.filter((t) =>
        (t.from_name ?? '').toLowerCase().includes(q)
        || (t.from_email ?? '').toLowerCase().includes(q)
        || (t.company_name ?? '').toLowerCase().includes(q)
        || (t.subject ?? '').toLowerCase().includes(q)
        || (t.last_message ?? '').toLowerCase().includes(q),
    );
});

const selected = computed(() => props.selected);
const threadMessages = computed(() => props.messages);

const scrollToBottom = () => {
    nextTick(() => {
        const el = conversationEl.value;
        if (el) el.scrollTop = el.scrollHeight;
    });
};

watch(() => props.selected?.id, () => {
    mobileView.value = 'thread';
    replyForm.reset();
    registrationForm.reset();
    scrollToBottom();
});

watch(() => props.messages, () => scrollToBottom(), { deep: true });

const selectThread = (thread) => {
    mobileView.value = 'thread';
    if (selected.value?.id === thread.id) return;
    router.get(route('crm.email.index'), { thread: thread.hash_key }, {
        preserveScroll: true,
        preserveState: false,
    });
};

const syncInbox = () => {
    syncing.value = true;
    router.post(route('crm.email.sync'), {}, {
        preserveScroll: true,
        onFinish: () => { syncing.value = false; },
    });
};

const openCompose = () => {
    composeForm.reset();
    composeForm.clearErrors();
    showCompose.value = true;
};

const submitReply = () => {
    if (!selected.value || !replyForm.body.trim()) return;
    replyForm.post(route('crm.email.reply', selected.value.hash_key), {
        preserveScroll: true,
        onSuccess: () => replyForm.reset('body'),
    });
};

const submitCompose = () => {
    composeForm.post(route('crm.email.compose'), {
        preserveScroll: true,
        onSuccess: () => {
            showCompose.value = false;
            composeForm.reset();
        },
    });
};

const sendRegistration = () => {
    if (!selected.value) return;
    registrationForm.post(route('crm.email.registration-form', selected.value.hash_key), {
        preserveScroll: true,
    });
};

const initials = (name, email) => {
    const source = (name || email || '?').trim();
    return source.charAt(0).toUpperCase();
};

const formatDateTime = (ts) => {
    if (!ts) return '';
    return new Date(ts).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatTime = (ts) => {
    if (!ts) return '';
    return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const isOutbound = (m) => m.direction === 'outbound';
</script>

<template>
    <Head title="CRM Email" />
    <AuthenticatedLayout>
        <div class="bg-slate-50 dark:bg-zinc-950">
            <div class="mx-auto flex h-[calc(100vh-4rem)] max-w-[100rem] flex-col gap-3 p-3 sm:p-4">
                <!-- Header -->
                <div class="relative flex-none overflow-hidden rounded-2xl bg-gradient-to-br from-sky-700 via-indigo-700 to-violet-800 px-5 py-4 text-white shadow-lg shadow-indigo-500/20">
                    <div class="absolute -top-16 -right-16 h-40 w-40 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
                            <Mail class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-sky-100">Client Correspondence</p>
                            <h1 class="text-xl font-black tracking-tight">Email</h1>
                        </div>

                        <div class="flex items-center gap-2 text-center">
                            <div class="rounded-xl bg-white/10 px-3 py-1.5 ring-1 ring-white/20">
                                <p class="text-lg font-black leading-none">{{ stats.unread ?? 0 }}</p>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-sky-100">Unread</p>
                            </div>
                            <div class="hidden rounded-xl bg-white/10 px-3 py-1.5 ring-1 ring-white/20 sm:block">
                                <p class="text-lg font-black leading-none">{{ stats.registrations ?? 0 }}</p>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-sky-100">Invites</p>
                            </div>
                            <div class="hidden rounded-xl bg-white/10 px-3 py-1.5 ring-1 ring-white/20 sm:block">
                                <p class="text-lg font-black leading-none">{{ stats.received_today ?? 0 }}</p>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-sky-100">Today</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="syncInbox"
                            :disabled="syncing"
                            class="flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-bold text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/25 disabled:opacity-60"
                            title="Fetch new messages from the mailbox"
                        >
                            <RefreshCw class="h-4 w-4" :class="syncing ? 'animate-spin' : ''" />
                            <span class="hidden sm:inline">{{ syncing ? 'Syncing…' : 'Sync' }}</span>
                        </button>
                        <button
                            v-if="canReply"
                            type="button"
                            @click="openCompose"
                            class="flex items-center gap-2 rounded-xl bg-white px-3.5 py-2 text-sm font-black text-indigo-700 shadow transition hover:bg-indigo-50"
                        >
                            <Plus class="h-4 w-4" /> Compose
                        </button>
                    </div>
                </div>

                <p v-if="syncError" class="rounded-xl bg-rose-50 px-4 py-2.5 text-xs font-bold text-rose-600 dark:bg-rose-500/10">
                    {{ syncError }}
                </p>
                <p v-else-if="flashMessage" class="rounded-xl bg-emerald-50 px-4 py-2.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    {{ flashMessage }}
                </p>

                <!-- Workspace -->
                <div class="flex min-h-0 flex-1 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <!-- Thread list -->
                    <aside :class="mobileView === 'thread' ? 'hidden md:flex' : 'flex'" class="w-full flex-col overflow-hidden border-r border-gray-100 dark:border-zinc-800 md:w-96 md:flex-shrink-0 md:flex">
                        <div class="flex-none border-b border-gray-100 p-3 dark:border-zinc-800">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input v-model="search" type="text" placeholder="Search client emails..."
                                    class="w-full rounded-xl border-0 bg-slate-100 py-2.5 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                            </div>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <button v-for="t in filteredThreads" :key="t.id" @click="selectThread(t)"
                                :class="selected?.id === t.id ? 'bg-indigo-50 dark:bg-indigo-950/40' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                class="flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3 text-left transition-colors dark:border-zinc-800/60">
                                <div class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-sm font-black text-white">
                                    {{ initials(t.from_name, t.from_email) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="truncate text-sm font-black text-slate-800 dark:text-slate-100">
                                            {{ t.company_name || t.from_name || t.from_email }}
                                        </p>
                                        <span class="flex-none text-[10px] font-bold text-gray-400">{{ formatDateTime(t.last_message_at) }}</span>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs font-bold text-slate-500 dark:text-slate-300">{{ t.subject }}</p>
                                    <p class="mt-0.5 truncate text-xs text-gray-400">{{ t.last_message }}</p>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <span v-if="t.unread_count" class="inline-flex items-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-black text-white">
                                            {{ t.unread_count }} new
                                        </span>
                                        <span v-if="t.registration_sent_at" class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-black text-violet-700 dark:bg-violet-500/20 dark:text-violet-300">
                                            <FileSignature class="h-3 w-3" /> Form sent
                                        </span>
                                    </div>
                                </div>
                            </button>
                            <p v-if="!filteredThreads.length" class="px-4 py-16 text-center text-xs font-bold text-gray-400">
                                No client emails yet.
                            </p>
                        </div>
                    </aside>

                    <!-- Conversation -->
                    <section :class="mobileView === 'list' ? 'hidden md:flex' : 'flex'" class="min-w-0 flex-1 flex-col">
                        <template v-if="selected">
                            <!-- Thread header -->
                            <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-zinc-800">
                                <button class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 md:hidden dark:hover:bg-zinc-800" @click="mobileView = 'list'">
                                    <ChevronLeft class="h-5 w-5" />
                                </button>
                                <div class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-sm font-black text-white">
                                    {{ initials(selected.from_name, selected.from_email) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-black text-slate-800 dark:text-slate-100">{{ selected.company_name || selected.from_name || selected.from_email }}</p>
                                    <p class="truncate text-xs text-gray-400">{{ selected.from_email }}</p>
                                </div>
                                <button
                                    v-if="canReply"
                                    type="button"
                                    @click="sendRegistration"
                                    :disabled="registrationForm.processing"
                                    class="hidden items-center gap-2 rounded-xl bg-violet-600 px-3.5 py-2 text-xs font-black uppercase text-white transition hover:bg-violet-700 disabled:opacity-50 sm:inline-flex"
                                    title="Email this client the partner registration form"
                                >
                                    <Loader2 v-if="registrationForm.processing" class="h-4 w-4 animate-spin" />
                                    <FileSignature v-else class="h-4 w-4" />
                                    Send Registration Form
                                </button>
                            </div>
                            <p v-if="selected.registration_sent_at" class="border-b border-gray-100 bg-violet-50/60 px-4 py-1.5 text-[11px] font-bold text-violet-700 dark:border-zinc-800 dark:bg-violet-500/10 dark:text-violet-300">
                                Registration form last sent {{ formatDateTime(selected.registration_sent_at) }}
                            </p>

                            <!-- Messages -->
                            <div ref="conversationEl" class="min-h-0 flex-1 space-y-4 overflow-y-auto bg-slate-50/60 px-4 py-5 dark:bg-zinc-950/40">
                                <div v-for="m in threadMessages" :key="m.id" :class="isOutbound(m) ? 'flex justify-end' : 'flex justify-start'">
                                    <div class="max-w-[85%] sm:max-w-[70%]">
                                        <div class="mb-1 flex items-center gap-2 px-1 text-[10px] font-bold uppercase tracking-wide text-gray-400" :class="isOutbound(m) ? 'justify-end' : ''">
                                            <span>{{ isOutbound(m) ? (m.user_name || 'CRM') : (m.from_name || m.from_email) }}</span>
                                            <span>{{ formatDateTime(m.created_at) }}</span>
                                        </div>
                                        <div
                                            :class="isOutbound(m)
                                                ? 'rounded-2xl rounded-br-sm bg-indigo-600 text-white'
                                                : 'rounded-2xl rounded-bl-sm border border-gray-100 bg-white text-slate-700 dark:border-zinc-800 dark:bg-zinc-800 dark:text-slate-200'"
                                            class="px-4 py-3 shadow-sm"
                                        >
                                            <p v-if="m.subject" class="mb-1.5 flex items-center gap-1.5 text-xs font-black" :class="isOutbound(m) ? 'text-indigo-100' : 'text-slate-500 dark:text-slate-300'">
                                                <FileSignature v-if="m.type === 'registration'" class="h-3.5 w-3.5" />
                                                {{ m.subject }}
                                            </p>
                                            <p class="whitespace-pre-wrap break-words text-sm">{{ m.body }}</p>
                                        </div>
                                        <div class="mt-1 flex items-center gap-1.5 px-1 text-[10px] font-bold" :class="isOutbound(m) ? 'justify-end' : ''">
                                            <template v-if="isOutbound(m)">
                                                <span v-if="m.status === 'failed'" class="inline-flex items-center gap-1 text-rose-500">
                                                    <CircleAlert class="h-3 w-3" /> Failed to send
                                                </span>
                                                <span v-else class="inline-flex items-center gap-1 text-emerald-600">
                                                    <CheckCircle2 class="h-3 w-3" /> Sent {{ formatTime(m.sent_at || m.created_at) }}
                                                </span>
                                            </template>
                                            <span v-else class="inline-flex items-center gap-1 text-gray-400">
                                                <Clock class="h-3 w-3" /> Received {{ formatTime(m.created_at) }}
                                            </span>
                                        </div>
                                        <p v-if="m.error" class="mt-1 px-1 text-right text-[10px] font-bold text-rose-400">{{ m.error }}</p>
                                    </div>
                                </div>
                                <p v-if="!threadMessages.length" class="py-10 text-center text-xs font-bold text-gray-400">
                                    No messages in this thread yet.
                                </p>
                            </div>

                            <!-- Reply composer -->
                            <form v-if="canReply" @submit.prevent="submitReply" class="flex-none border-t border-gray-100 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <p v-if="replyForm.errors.body" class="mb-1 px-1 text-[11px] font-bold text-rose-500">{{ replyForm.errors.body }}</p>
                                <p v-if="replyForm.errors.email" class="mb-1 px-1 text-[11px] font-bold text-rose-500">{{ replyForm.errors.email }}</p>
                                <div class="flex items-end gap-2">
                                    <textarea
                                        v-model="replyForm.body"
                                        rows="2"
                                        placeholder="Write a reply to this client…"
                                        class="flex-1 resize-none rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white"
                                        @keydown.enter.exact.prevent="submitReply"
                                    />
                                    <button
                                        type="submit"
                                        :disabled="replyForm.processing || !replyForm.body.trim()"
                                        class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-indigo-600 text-white transition hover:bg-indigo-700 disabled:opacity-50"
                                        title="Send reply"
                                    >
                                        <Loader2 v-if="replyForm.processing" class="h-5 w-5 animate-spin" />
                                        <Send v-else class="h-5 w-5" />
                                    </button>
                                </div>
                                <p class="mt-1.5 px-1 text-[10px] text-gray-400">Press Enter to send · replying to {{ selected.from_email }}</p>
                            </form>
                        </template>

                        <div v-else class="flex flex-1 flex-col items-center justify-center gap-3 text-gray-300">
                            <Inbox class="h-12 w-12" />
                            <p class="text-sm font-bold text-gray-400">Select a conversation to read and reply.</p>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- Compose modal -->
        <div v-if="showCompose" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showCompose = false" />
            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between rounded-t-2xl bg-gradient-to-r from-sky-700 to-indigo-800 px-5 py-4 text-white">
                    <h3 class="flex items-center gap-2 font-black"><SendHorizonal class="h-4 w-4" /> New Email</h3>
                    <button @click="showCompose = false" aria-label="Close"><X class="h-5 w-5" /></button>
                </div>
                <form @submit.prevent="submitCompose" class="space-y-3 p-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-[11px] font-black uppercase tracking-wide text-gray-500">To email *</label>
                            <input v-model="composeForm.to_email" type="email" required placeholder="client@example.com"
                                class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                            <p v-if="composeForm.errors.to_email" class="mt-1 text-[11px] font-bold text-rose-500">{{ composeForm.errors.to_email }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-black uppercase tracking-wide text-gray-500">Recipient name</label>
                            <input v-model="composeForm.to_name" type="text" placeholder="Optional"
                                class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-black uppercase tracking-wide text-gray-500">Subject *</label>
                        <input v-model="composeForm.subject" type="text" required placeholder="Subject"
                            class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                        <p v-if="composeForm.errors.subject" class="mt-1 text-[11px] font-bold text-rose-500">{{ composeForm.errors.subject }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-black uppercase tracking-wide text-gray-500">Message *</label>
                        <textarea v-model="composeForm.body" rows="6" required placeholder="Write your message…"
                            class="w-full resize-none rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                        <p v-if="composeForm.errors.body" class="mt-1 text-[11px] font-bold text-rose-500">{{ composeForm.errors.body }}</p>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" @click="showCompose = false"
                            class="rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-black uppercase text-gray-500 transition hover:bg-gray-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            Cancel
                        </button>
                        <button type="submit" :disabled="composeForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-black uppercase text-white transition hover:bg-indigo-700 disabled:opacity-50">
                            <Loader2 v-if="composeForm.processing" class="h-4 w-4 animate-spin" />
                            <Send v-else class="h-4 w-4" />
                            Send email
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <TemplateWidget page-key="emails" />
    </AuthenticatedLayout>
</template>
