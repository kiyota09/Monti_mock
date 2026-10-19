<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import axios from 'axios';
import { initTheme } from '@/composables/useTheme';
import {
    MessagesSquare, Send, Search, Plus, ChevronLeft, Settings, Paperclip,
    ImagePlus, X, Trash2, Users, ArrowLeft, RefreshCw,
} from 'lucide-vue-next';

const page = usePage();
const me = computed(() => page.props.auth?.user || {});
const loginName = computed(() => me.value?.name || 'You');
const displaySub = computed(() => [me.value?.role, me.value?.position].filter(Boolean).join(' · '));

// ─── Panels ─────────────────────────────────────────────────────────
// Left = thread list, right = chat / settings / compose. On phones only one
// pane shows at a time, tracked by mobileView.
const mobileView = ref('list');

// ─── Threads ────────────────────────────────────────────────────────
const threads = ref([]);
const threadsLoading = ref(false);
const listQuery = ref('');
const activeThread = ref(null);
const messages = ref([]);
const loadingThread = ref(false);
const msgView = ref('chat'); // chat | settings
const composing = ref(false);

const filteredThreads = computed(() => {
    const q = listQuery.value.trim().toLowerCase();
    if (!q) return threads.value;
    return threads.value.filter((t) =>
        (t.title || '').toLowerCase().includes(q) ||
        (t.preview || '').toLowerCase().includes(q));
});

const loadThreads = async (silent = false) => {
    if (!silent) threadsLoading.value = true;
    try {
        const { data } = await axios.get(route('topbar.threads'));
        threads.value = data.threads || [];
    } catch { /* offline — keep last */ }
    threadsLoading.value = false;
};

const openThread = async (t) => {
    activeThread.value = t;
    messages.value = [];
    msgView.value = 'chat';
    composing.value = false;
    loadingThread.value = true;
    mobileView.value = 'chat';
    try {
        const { data } = await axios.get(route('topbar.threads.show', t.id));
        if (!activeThread.value || Number(activeThread.value.id) !== Number(t.id)) return;
        messages.value = data.messages || [];
        activeThread.value = { ...t, ...(data.thread || {}) };
        scrollChat(true);
        loadThreads(true);
    } catch { /* noop */ }
    loadingThread.value = false;
};

const refreshActive = async () => {
    if (!activeThread.value) return;
    const id = activeThread.value.id;
    try {
        const { data } = await axios.get(route('topbar.threads.show', id));
        if (!activeThread.value || Number(activeThread.value.id) !== Number(id)) return;
        messages.value = data.messages || [];
        activeThread.value = { ...activeThread.value, ...(data.thread || {}) };
        groupName.value = data.thread?.title || '';
        scrollChat(true);
        loadThreads(true);
    } catch { /* noop */ }
};

const backToList = () => {
    activeThread.value = null;
    messages.value = [];
    composing.value = false;
    msgView.value = 'chat';
    mobileView.value = 'list';
    loadThreads(true);
};

// ─── Messages ───────────────────────────────────────────────────────
const msgBox = ref(null);
const draft = ref('');
const sending = ref(false);

const isNearBottom = () => {
    const el = msgBox.value;
    if (!el) return true;
    return el.scrollHeight - el.scrollTop - el.clientHeight < 140;
};
const scrollChat = (force = false) => nextTick(() => {
    const el = msgBox.value;
    if (!el) return;
    if (force || isNearBottom()) el.scrollTop = el.scrollHeight;
});

const sendMessage = async () => {
    const body = draft.value.trim();
    if ((!body && !attachFile.value) || sending.value || !activeThread.value) return;
    sending.value = true;
    try {
        const payload = new FormData();
        if (body) payload.append('body', body);
        if (attachFile.value) payload.append('file', attachFile.value);
        const { data } = await axios.post(route('topbar.threads.send', activeThread.value.id), payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        messages.value.push(data.message);
        draft.value = '';
        clearAttach();
        scrollChat(true);
        loadThreads(true);
    } catch { /* noop */ }
    sending.value = false;
};

// ─── Attachments ────────────────────────────────────────────────────
const attachInput = ref(null);
const attachFile = ref(null);
const attachUrl = ref(null);
const pickAttach = () => attachInput.value?.click();
const onAttach = (e) => {
    const f = (e.target.files || [])[0] || null;
    if (!f || f.size > 10 * 1024 * 1024) return;
    attachFile.value = f;
    attachUrl.value = f.type.startsWith('image/') ? URL.createObjectURL(f) : null;
};
const clearAttach = () => {
    attachFile.value = null;
    if (attachUrl.value) URL.revokeObjectURL(attachUrl.value);
    attachUrl.value = null;
    if (attachInput.value) attachInput.value.value = '';
};

// ─── Lightbox ───────────────────────────────────────────────────────
const lightbox = ref(null);
const openLightbox = (url) => { lightbox.value = url; };
const closeLightbox = () => { lightbox.value = null; };

// ─── Group settings ─────────────────────────────────────────────────
const groupName = ref('');
const groupBusy = ref(false);
const groupError = ref('');
const photoInput = ref(null);
const addQuery = ref('');
const addResults = ref([]);
const addingId = ref(null);

const openSettings = () => {
    groupName.value = activeThread.value?.title || '';
    groupError.value = '';
    addQuery.value = '';
    addResults.value = [];
    msgView.value = 'settings';
};

const saveGroupName = async () => {
    const name = groupName.value.trim();
    if (!name || groupBusy.value || name === (activeThread.value?.title || '')) return;
    groupBusy.value = true;
    groupError.value = '';
    try {
        const { data } = await axios.patch(route('topbar.threads.update', activeThread.value.id), { title: name });
        activeThread.value = { ...activeThread.value, title: data.title };
        loadThreads(true);
    } catch (e) {
        groupError.value = e?.response?.data?.message || 'Could not rename group.';
    }
    groupBusy.value = false;
};

const changePhoto = async (e) => {
    const f = (e.target.files || [])[0] || null;
    if (photoInput.value) photoInput.value.value = '';
    if (!f || groupBusy.value) return;
    groupBusy.value = true;
    groupError.value = '';
    try {
        const fd = new FormData();
        fd.append('_method', 'PATCH');
        fd.append('photo', f);
        await axios.post(route('topbar.threads.update', activeThread.value.id), fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        await refreshActive();
    } catch { groupError.value = 'Could not update group photo.'; }
    groupBusy.value = false;
};

const removePhoto = async () => {
    if (groupBusy.value) return;
    groupBusy.value = true;
    try {
        await axios.patch(route('topbar.threads.update', activeThread.value.id), { remove_photo: true });
        await refreshActive();
    } catch { /* noop */ }
    groupBusy.value = false;
};

const searchAdd = async () => {
    try {
        const { data } = await axios.get(route('topbar.directory'));
        const memberIds = new Set((activeThread.value?.members || []).map((m) => m.id));
        const q = addQuery.value.trim().toLowerCase();
        addResults.value = (data.users || [])
            .filter((u) => !memberIds.has(u.id))
            .filter((u) => !q || (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q))
            .slice(0, 20);
    } catch { /* noop */ }
};

const addMember = async (u) => {
    if (addingId.value) return;
    addingId.value = u.id;
    groupError.value = '';
    try {
        await axios.post(route('topbar.threads.members.add', activeThread.value.id), { user_ids: [u.id] });
        await refreshActive();
        searchAdd();
    } catch (e) {
        groupError.value = e?.response?.data?.message || 'Could not add employee.';
    }
    addingId.value = null;
};

const removeMember = async (u) => {
    if (groupBusy.value) return;
    groupBusy.value = true;
    groupError.value = '';
    try {
        await axios.delete(route('topbar.threads.members.remove', [activeThread.value.id, u.id]));
        await refreshActive();
    } catch (e) {
        groupError.value = e?.response?.data?.message || 'Could not remove employee.';
    }
    groupBusy.value = false;
};

const leaveGroup = async () => {
    if (groupBusy.value) return;
    groupBusy.value = true;
    try {
        await axios.post(route('topbar.threads.leave', activeThread.value.id));
        backToList();
    } catch (e) {
        groupError.value = e?.response?.data?.message || 'Could not leave group.';
    }
    groupBusy.value = false;
};

// ─── Compose ────────────────────────────────────────────────────────
const directory = ref([]);
const dirQuery = ref('');
const picked = ref([]);
const newTitle = ref('');
const newBody = ref('');
const creating = ref(false);

const startCompose = async () => {
    composing.value = true;
    activeThread.value = null;
    messages.value = [];
    msgView.value = 'chat';
    picked.value = [];
    newTitle.value = '';
    newBody.value = '';
    dirQuery.value = '';
    mobileView.value = 'chat';
    try {
        const { data } = await axios.get(route('topbar.directory'));
        directory.value = data.users || [];
    } catch { /* noop */ }
};

const filteredDir = computed(() => {
    const q = dirQuery.value.trim().toLowerCase();
    if (!q) return directory.value.slice(0, 40);
    return directory.value.filter((u) =>
        (u.name || '').toLowerCase().includes(q) ||
        (u.email || '').toLowerCase().includes(q) ||
        (u.role || '').toLowerCase().includes(q)).slice(0, 40);
});

const togglePick = (u) => {
    const i = picked.value.findIndex((p) => p.id === u.id);
    if (i > -1) picked.value.splice(i, 1);
    else if (picked.value.length < 8) picked.value.push(u);
};

const createThread = async () => {
    if (!picked.value.length || !newBody.value.trim() || creating.value) return;
    creating.value = true;
    try {
        const { data } = await axios.post(route('topbar.threads.store'), {
            user_ids: picked.value.map((p) => p.id),
            title: newTitle.value.trim() || null,
            body: newBody.value.trim(),
        });
        composing.value = false;
        await loadThreads(true);
        const t = threads.value.find((x) => Number(x.id) === Number(data.thread_id));
        if (t) openThread(t);
    } catch { /* noop */ }
    creating.value = false;
};

// ─── Helpers ────────────────────────────────────────────────────────
const initialOf = (name) => (name || '?').trim().charAt(0).toUpperCase() || '?';
const threadAvatar = (t) => t?.photo || null;
const fmtTime = (v) => {
    if (!v) return '';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return '';
    const today = new Date();
    const sameDay = d.toDateString() === today.toDateString();
    return sameDay
        ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleDateString([], { month: 'short', day: 'numeric' });
};
const fmtFull = (v) => {
    if (!v) return '';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// ─── Polling ────────────────────────────────────────────────────────
let pollTimer = null;
let polling = false;
const pollActive = async () => {
    if (polling || !activeThread.value) return;
    const id = activeThread.value.id;
    polling = true;
    try {
        const { data } = await axios.get(route('topbar.threads.show', id));
        if (!activeThread.value || Number(activeThread.value.id) !== Number(id)) return;
        const seen = new Set(messages.value.map((m) => m.id));
        const fresh = (data.messages || []).filter((m) => !seen.has(m.id));
        const wasBottom = isNearBottom();
        if (fresh.length) {
            messages.value.push(...fresh);
            if (msgView.value === 'chat' && wasBottom) scrollChat();
        }
        activeThread.value = { ...activeThread.value, ...(data.thread || {}) };
        loadThreads(true);
    } catch { /* noop */ }
    polling = false;
};

onMounted(() => {
    initTheme();
    loadThreads();
    pollTimer = setInterval(() => {
        if (activeThread.value) pollActive();
        else loadThreads(true);
    }, 3000);
});
onBeforeUnmount(() => { if (pollTimer) clearInterval(pollTimer); });
</script>

<template>
    <Head title="Messenger" />

    <div class="flex h-screen flex-col overflow-hidden bg-gray-100 dark:bg-zinc-950">
        <!-- Brand bar -->
        <header class="flex h-14 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4 dark:border-zinc-800 dark:bg-zinc-900">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white shadow-sm">
                <MessagesSquare class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="truncate text-sm font-black text-gray-900 dark:text-white">Monti Messenger</p>
                <p class="truncate text-[11px] font-bold text-gray-400">Signed in as {{ loginName }}{{ displaySub ? ` · ${displaySub}` : '' }}</p>
            </div>

            <div class="relative ml-auto hidden md:block">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="listQuery" placeholder="Search conversations…"
                    class="w-64 rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
            </div>

            <button @click="loadThreads()" title="Refresh"
                class="rounded-xl p-2 text-gray-400 hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-zinc-800">
                <RefreshCw class="h-4 w-4" :class="threadsLoading && 'animate-spin'" />
            </button>
            <button @click="startCompose" title="New conversation"
                class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-black uppercase text-white shadow hover:bg-indigo-700 active:scale-95">
                <Plus class="h-4 w-4" /> <span class="hidden sm:inline">New</span>
            </button>
            <Link :href="route('dashboard')" title="Back to dashboard"
                class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-3.5 py-2 text-xs font-black uppercase text-gray-600 hover:bg-gray-50 dark:border-zinc-700 dark:text-gray-300 dark:hover:bg-zinc-800">
                <ArrowLeft class="h-4 w-4" /> <span class="hidden sm:inline">Dashboard</span>
            </Link>
        </header>

        <div class="flex min-h-0 flex-1">
            <!-- Thread list -->
            <aside :class="mobileView === 'list' ? 'flex' : 'hidden sm:flex'"
                class="w-full flex-col border-r border-gray-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 sm:w-80 lg:w-96">
                <div class="border-b border-gray-100 p-3 dark:border-zinc-800 md:hidden">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input v-model="listQuery" placeholder="Search conversations…"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm outline-none focus:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <button v-for="t in filteredThreads" :key="t.id" @click="openThread(t)"
                        :class="['flex w-full items-center gap-3 border-b border-gray-50 px-4 py-3 text-left transition dark:border-zinc-800/60',
                            activeThread?.id === t.id ? 'bg-indigo-50 dark:bg-indigo-900/20' : 'hover:bg-gray-50 dark:hover:bg-zinc-800/60']">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm font-black text-white"
                            :class="threadAvatar(t) ? '' : 'bg-gradient-to-br from-blue-600 to-violet-700'">
                            <img v-if="threadAvatar(t)" :src="threadAvatar(t)" alt="" class="h-full w-full object-cover" />
                            <span v-else>{{ initialOf(t.title) }}</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span class="truncate text-sm font-black text-gray-900 dark:text-white">{{ t.title }}</span>
                                <span class="ml-auto shrink-0 text-[10px] font-bold text-gray-400">{{ fmtTime(t.at) }}</span>
                            </span>
                            <span class="mt-0.5 flex items-center gap-1.5">
                                <span class="min-w-0 flex-1 truncate text-xs text-gray-500">
                                    {{ t.preview_by ? t.preview_by + ': ' : '' }}{{ t.preview || 'No messages yet' }}
                                </span>
                                <span v-if="t.unread > 0" class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-black text-white">{{ t.unread }}</span>
                            </span>
                            <span class="mt-1 inline-flex items-center gap-1">
                                <span :class="['rounded-full px-1.5 py-0.5 text-[9px] font-black uppercase', t.kind === 'group' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-slate-400']">
                                    {{ t.kind === 'group' ? `Group · ${t.member_count}` : 'Personal' }}
                                </span>
                            </span>
                        </span>
                    </button>

                    <div v-if="!threadsLoading && !filteredThreads.length" class="px-4 py-16 text-center">
                        <Users class="mx-auto h-8 w-8 text-gray-300 dark:text-zinc-600" />
                        <p class="mt-3 text-xs font-bold text-gray-400">
                            {{ listQuery ? 'No conversations match your search.' : 'No conversations yet.' }}
                        </p>
                        <button v-if="!listQuery" @click="startCompose"
                            class="mt-3 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-black uppercase text-white hover:bg-indigo-700">
                            Start one
                        </button>
                    </div>
                </div>
            </aside>

            <!-- Chat / settings / compose -->
            <section :class="mobileView === 'chat' ? 'flex' : 'hidden sm:flex'" class="min-w-0 flex-1 flex-col bg-gray-50 dark:bg-zinc-950">
                <!-- Empty state -->
                <div v-if="!activeThread && !composing" class="flex h-full flex-col items-center justify-center p-8 text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white shadow-lg">
                        <MessagesSquare class="h-8 w-8" />
                    </span>
                    <h2 class="mt-4 text-lg font-black text-gray-900 dark:text-white">Your messages</h2>
                    <p class="mt-1 max-w-xs text-sm text-gray-500 dark:text-gray-400">
                        Pick a conversation on the left, or start a new one with any Monti employee.
                    </p>
                    <button @click="startCompose"
                        class="mt-5 inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-black uppercase text-white shadow hover:bg-indigo-700 active:scale-95">
                        <Plus class="h-4 w-4" /> New conversation
                    </button>
                </div>

                <!-- Compose -->
                <div v-if="composing" class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-center gap-2 border-b border-gray-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
                        <button @click="backToList(); composing = false"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800">
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <p class="flex-1 text-sm font-black text-gray-900 dark:text-white">New conversation</p>
                    </div>
                    <div class="border-b border-gray-100 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input v-model="dirQuery" placeholder="Search people by name, email, role…"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div v-if="picked.length" class="mt-2 flex flex-wrap gap-1.5">
                            <span v-for="p in picked" :key="p.id" class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-black text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                {{ p.name }} <button @click="togglePick(p)" class="hover:text-rose-500"><X class="h-3 w-3" /></button>
                            </span>
                        </div>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto bg-white dark:bg-zinc-900">
                        <button v-for="u in filteredDir" :key="u.id" @click="togglePick(u)"
                            :class="['flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-zinc-800/60', picked.some((p) => p.id === u.id) && 'bg-indigo-50/60 dark:bg-indigo-900/20']">
                            <img v-if="u.photo" :src="u.photo" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover" />
                            <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[11px] font-black text-gray-600 dark:bg-zinc-700 dark:text-gray-300">{{ initialOf(u.name) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-black text-gray-900 dark:text-white">{{ u.name }}</span>
                                <span class="block truncate text-[11px] text-gray-400">{{ u.role }} · {{ u.position }}</span>
                            </span>
                        </button>
                        <p v-if="!filteredDir.length" class="px-4 py-10 text-center text-xs font-bold text-gray-400">No people found.</p>
                    </div>
                    <div class="space-y-2 border-t border-gray-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                        <input v-model="newTitle" placeholder="Group title (optional, for 2+ people)…"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm outline-none focus:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        <div class="flex gap-2">
                            <input v-model="newBody" @keyup.enter="createThread" placeholder="First message…"
                                class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm outline-none focus:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                            <button @click="createThread" :disabled="creating || !picked.length || !newBody.trim()"
                                class="rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-black uppercase text-white hover:bg-indigo-700 disabled:opacity-40 active:scale-95">
                                Send
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Active conversation -->
                <template v-if="activeThread && !composing">
                    <!-- Header -->
                    <div class="flex items-center gap-2 border-b border-gray-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
                        <button @click="backToList" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800 sm:hidden">
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <img v-if="activeThread.photo" :src="activeThread.photo" alt="" class="h-9 w-9 rounded-full object-cover" />
                        <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-600 to-violet-700 text-xs font-black text-white">{{ initialOf(activeThread.title) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ activeThread.title }}</p>
                            <p class="truncate text-[11px] font-bold text-gray-400">
                                {{ activeThread.kind === 'group' ? `${activeThread.members?.length || activeThread.member_count || 0} members` : 'Personal conversation' }}
                            </p>
                        </div>
                        <button v-if="activeThread.kind === 'group' && msgView !== 'settings'" @click="openSettings" title="Group settings"
                            class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-zinc-800">
                            <Settings class="h-4 w-4" />
                        </button>
                        <button v-if="msgView === 'settings'" @click="msgView = 'chat'" title="Back to chat"
                            class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-zinc-800">
                            <MessagesSquare class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Chat -->
                    <div v-show="msgView === 'chat'" class="flex min-h-0 flex-1 flex-col">
                        <div ref="msgBox" class="min-h-0 flex-1 space-y-2 overflow-y-auto px-4 py-4 sm:px-6">
                            <p v-if="loadingThread" class="py-8 text-center text-xs font-bold text-gray-400">Loading…</p>
                            <div v-for="m in messages" :key="m.id" :class="['flex items-end gap-1.5', m.mine ? 'justify-end' : 'justify-start']">
                                <img v-if="!m.mine && m.sender_photo" :src="m.sender_photo" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover" />
                                <span v-else-if="!m.mine" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[10px] font-black text-gray-500 dark:bg-zinc-700 dark:text-gray-300">{{ initialOf(m.sender) }}</span>
                                <div :class="['max-w-[75%] rounded-2xl px-3.5 py-2 text-[13px] leading-relaxed', m.mine ? 'rounded-br-md bg-indigo-600 text-white' : 'rounded-bl-md bg-white text-gray-800 shadow-sm dark:bg-zinc-800 dark:text-gray-200']">
                                    <p v-if="!m.mine" class="mb-0.5 text-[10px] font-black uppercase tracking-wide opacity-60">{{ m.sender }}</p>
                                    <button v-if="m.is_image && m.file_url" type="button" @click="openLightbox(m.file_url)" class="mb-1.5 block overflow-hidden rounded-xl" title="View image">
                                        <img :src="m.file_url" alt="" class="max-h-64 w-full object-cover hover:opacity-90" loading="lazy" />
                                    </button>
                                    <a v-else-if="m.file_url" :href="m.file_url" target="_blank"
                                        :class="['mb-1.5 flex items-center gap-2 rounded-xl px-2.5 py-2 text-xs font-bold', m.mine ? 'bg-white/15 hover:bg-white/25' : 'bg-gray-100 hover:bg-gray-200 dark:bg-zinc-700 dark:hover:bg-zinc-600']">
                                        <Paperclip class="h-3.5 w-3.5 shrink-0" />
                                        <span class="truncate">{{ m.file_name || 'Attachment' }}</span>
                                    </a>
                                    <p v-if="m.body" class="whitespace-pre-wrap">{{ m.body }}</p>
                                    <p :class="['mt-1 text-right text-[9px] font-bold', m.mine ? 'text-white/70' : 'text-gray-400']">{{ fmtTime(m.at) }}</p>
                                </div>
                            </div>
                            <p v-if="!loadingThread && !messages.length" class="py-10 text-center text-xs font-bold text-gray-400">
                                Say hello to start the conversation.
                            </p>
                        </div>

                        <div v-if="attachFile" class="flex items-center gap-2 border-t border-gray-200 bg-indigo-50/50 px-3 py-2 dark:border-zinc-800 dark:bg-indigo-900/10">
                            <img v-if="attachUrl" :src="attachUrl" alt="" class="h-10 w-10 rounded-lg object-cover" />
                            <Paperclip v-else class="h-4 w-4 shrink-0 text-indigo-500" />
                            <span class="min-w-0 flex-1 truncate text-xs font-bold text-gray-700 dark:text-gray-200">{{ attachFile.name }}</span>
                            <button @click="clearAttach" class="rounded-lg p-1 text-gray-400 hover:text-rose-500"><X class="h-3.5 w-3.5" /></button>
                        </div>
                        <div class="flex items-center gap-2 border-t border-gray-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                            <input ref="attachInput" type="file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" @change="onAttach" class="hidden" />
                            <button @click="pickAttach" title="Send file or image"
                                class="rounded-xl p-2.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-zinc-800">
                                <Paperclip class="h-4 w-4" />
                            </button>
                            <input v-model="draft" @keyup.enter="sendMessage" placeholder="Write a message…"
                                class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                            <button @click="sendMessage" :disabled="sending || (!draft.trim() && !attachFile)"
                                class="rounded-xl bg-indigo-600 p-2.5 text-white shadow hover:bg-indigo-700 disabled:opacity-40 active:scale-95">
                                <Send class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Group settings -->
                    <div v-if="msgView === 'settings' && activeThread.kind === 'group'" class="min-h-0 flex-1 overflow-y-auto">
                        <div class="flex items-center gap-3 border-b border-gray-100 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                            <img v-if="activeThread.photo" :src="activeThread.photo" alt="" class="h-16 w-16 rounded-2xl object-cover" />
                            <span v-else class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-700 text-xl font-black text-white">
                                {{ initialOf(activeThread.title) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ activeThread.title }}</p>
                                <p class="text-[11px] font-bold text-gray-400">{{ activeThread.members?.length || 0 }} members</p>
                            </div>
                        </div>
                        <div class="mx-auto max-w-2xl space-y-5 p-5">
                            <p v-if="groupError" class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-600 dark:bg-rose-900/20">{{ groupError }}</p>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">Group name</label>
                                <div class="flex gap-2">
                                    <input v-model="groupName" maxlength="120" placeholder="Group name…"
                                        class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm outline-none focus:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                    <button @click="saveGroupName" :disabled="groupBusy || !groupName.trim()"
                                        class="rounded-xl bg-indigo-600 px-4 text-xs font-black uppercase text-white hover:bg-indigo-700 disabled:opacity-40 active:scale-95">
                                        Save
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">Group photo</label>
                                <input ref="photoInput" type="file" accept="image/*" @change="changePhoto" class="hidden" />
                                <div class="flex gap-2">
                                    <button @click="photoInput.click()" :disabled="groupBusy"
                                        class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-gray-200 px-3 py-2.5 text-xs font-black uppercase text-gray-600 hover:bg-gray-50 disabled:opacity-40 dark:border-zinc-700 dark:text-gray-300 dark:hover:bg-zinc-800">
                                        <ImagePlus class="h-4 w-4" /> {{ activeThread.photo ? 'Change photo' : 'Upload photo' }}
                                    </button>
                                    <button v-if="activeThread.photo" @click="removePhoto" :disabled="groupBusy" title="Remove photo"
                                        class="rounded-xl border border-gray-200 px-3 text-rose-500 hover:bg-rose-50 disabled:opacity-40 dark:border-zinc-700">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">Members ({{ activeThread.members?.length || 0 }})</label>
                                <div class="space-y-1.5">
                                    <div v-for="m in activeThread.members" :key="m.id"
                                        class="flex items-center gap-2.5 rounded-xl bg-gray-50 px-3 py-2 dark:bg-zinc-800/60">
                                        <img v-if="m.photo" :src="m.photo" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover" />
                                        <span v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[10px] font-black text-gray-600 dark:bg-zinc-700 dark:text-gray-300">{{ initialOf(m.name) }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-xs font-black text-gray-900 dark:text-white">{{ m.name }}</span>
                                            <span class="block truncate text-[10px] text-gray-400">{{ m.role }} · {{ m.position }}</span>
                                        </span>
                                        <button @click="removeMember(m)" :disabled="groupBusy" title="Remove from group"
                                            class="rounded-lg p-1.5 text-gray-300 hover:bg-rose-50 hover:text-rose-500 disabled:opacity-40 dark:hover:bg-rose-900/20">
                                            <X class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">Add employee</label>
                                <div class="relative">
                                    <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                    <input v-model="addQuery" @input="searchAdd" @focus="searchAdd" placeholder="Search name or email…"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                </div>
                                <div v-if="addResults.length" class="mt-1.5 max-h-48 space-y-1 overflow-y-auto">
                                    <button v-for="u in addResults" :key="u.id" @click="addMember(u)" :disabled="addingId === u.id"
                                        class="flex w-full items-center gap-2 rounded-xl px-2.5 py-2 text-left hover:bg-indigo-50 disabled:opacity-50 dark:hover:bg-indigo-900/20">
                                        <img v-if="u.photo" :src="u.photo" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover" />
                                        <span v-else class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[10px] font-black text-gray-600 dark:bg-zinc-700 dark:text-gray-300">{{ initialOf(u.name) }}</span>
                                        <span class="min-w-0 flex-1 truncate text-xs font-bold text-gray-800 dark:text-gray-200">{{ u.name }} <span class="font-normal text-gray-400">· {{ u.role }}</span></span>
                                        <Plus class="h-3.5 w-3.5 shrink-0 text-indigo-500" />
                                    </button>
                                </div>
                            </div>
                            <button @click="leaveGroup" :disabled="groupBusy"
                                class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-rose-200 py-2.5 text-xs font-black uppercase text-rose-600 hover:bg-rose-50 disabled:opacity-40 dark:border-rose-900 dark:hover:bg-rose-900/20">
                                <X class="h-4 w-4" /> Leave this group
                            </button>
                        </div>
                    </div>
                </template>
            </section>
        </div>

        <!-- Image lightbox -->
        <Teleport to="body">
            <div v-if="lightbox" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/90 p-4 sm:p-10" @click.self="closeLightbox">
                <button @click="closeLightbox" title="Close"
                    class="absolute right-5 top-5 rounded-xl bg-white/10 p-2.5 text-white hover:bg-white/20 active:scale-95">
                    <X class="h-5 w-5" />
                </button>
                <img :src="lightbox" alt="" class="max-h-full max-w-full rounded-2xl object-contain shadow-2xl" />
            </div>
        </Teleport>
    </div>
</template>
