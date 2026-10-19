<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import axios from 'axios';
import { initTheme } from '@/composables/useTheme';
import {
    MessagesSquare, Send, Search, Plus, ChevronLeft, Settings, Paperclip,
    ImagePlus, X, Trash2, Users, ArrowLeft, RefreshCw, Check, Inbox, Star,
    ListChecks, Phone, Video, MoreVertical, Smile, Download, FileText,
    ChevronDown, PanelRight, Music, FileSpreadsheet,
} from 'lucide-vue-next';

const page = usePage();
const me = computed(() => page.props.auth?.user || {});
const loginName = computed(() => me.value?.name || 'You');
const displaySub = computed(() => [me.value?.role, me.value?.position].filter(Boolean).join(' · '));
const myPhoto = computed(() => me.value?.profile_photo_path ? `/storage/${me.value.profile_photo_path}` : null);
const myInitial = computed(() => (loginName.value || '?').trim().charAt(0).toUpperCase() || '?');

// ─── Panels ─────────────────────────────────────────────────────────
// Left = thread list, right = chat / settings / compose. On phones only one
// pane shows at a time, tracked by mobileView.
const mobileView = ref('list');
// Far-left slim nav + right profile panel.
const activeNav = ref('inbox'); // inbox | starred | tasks | settings
const profileOpen = ref(false);

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

// ─── Starred (local favourites) ─────────────────────────────────────
const STARRED_KEY = 'monti-msg-starred';
const starred = ref(new Set());
try {
    const raw = localStorage.getItem(STARRED_KEY);
    if (raw) starred.value = new Set(JSON.parse(raw));
} catch { /* private mode */ }
const isStarred = (id) => starred.value.has(id);
const toggleStar = (id) => {
    const s = new Set(starred.value);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    starred.value = s;
    try { localStorage.setItem(STARRED_KEY, JSON.stringify([...s])); } catch { /* noop */ }
};

// List shown depends on the active far-left nav item.
const navTitle = computed(() => ({
    inbox: 'Chats',
    starred: 'Starred',
    tasks: 'Tasks',
    settings: 'Settings',
}[activeNav.value] || 'Chats'));
const listThreads = computed(() => {
    if (activeNav.value === 'starred') return filteredThreads.value.filter((t) => starred.value.has(t.id));
    return filteredThreads.value;
});
const navEmptyMessage = computed(() => ({
    starred: 'No starred conversations yet. Tap the star on a chat to save it here.',
    tasks: 'No tasks assigned to you right now.',
    settings: 'Conversation settings appear here.',
}[activeNav.value] || (listQuery.value ? 'No conversations match your search.' : 'No conversations yet.')));

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

// ─── Emoji picker ───────────────────────────────────────────────────
const EMOJIS = ['😀', '😁', '😂', '😂', '😊', '😍', '😎', '🤔', '😅', '🙌', '👍', '🙏', '👏', '🔥', '🎉', '❤️', '😢', '😡'];
const emojiOpen = ref(false);
const insertEmoji = (e) => { draft.value += e; emojiOpen.value = false; };

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

// Deterministic gradient per name so every avatar is colourful yet stable.
const AVATAR_GRADIENTS = [
    'from-[#6366F1] to-[#8B5CF6]',
    'from-[#7C3AED] to-[#EC4899]',
    'from-[#EC4899] to-[#F97316]',
    'from-[#F43F5E] to-[#FB923C]',
    'from-[#10B981] to-[#0EA5E9]',
    'from-[#06B6D4] to-[#3B82F6]',
    'from-[#F59E0B] to-[#EC4899]',
    'from-[#8B5CF6] to-[#4C1D95]',
];
const avatarGradient = (name) => {
    const s = String(name || '?');
    let h = 0;
    for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
    return AVATAR_GRADIENTS[h % AVATAR_GRADIENTS.length];
};

// Message-run helpers: day dividers + tidy sender grouping.
const sameDay = (a, b) => new Date(a).toDateString() === new Date(b).toDateString();
const showDay = (i) => {
    const m = messages.value[i];
    if (!m) return false;
    if (i === 0) return true;
    return !sameDay(m.at, messages.value[i - 1].at);
};
const dayLabel = (v) => {
    if (!v) return '';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return '';
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
};
const showAvatar = (i) => {
    const m = messages.value[i];
    if (!m || m.mine) return false;
    const next = messages.value[i + 1];
    return !next || next.mine || next.sender !== m.sender;
};
const showMineAvatar = (i) => {
    const m = messages.value[i];
    if (!m || !m.mine) return false;
    const next = messages.value[i + 1];
    return !next || !next.mine;
};
const showSender = (i) => {
    const m = messages.value[i];
    if (!m || m.mine) return false;
    if (i === 0) return true;
    const prev = messages.value[i - 1];
    return prev.mine || prev.sender !== m.sender;
};

// Total unread across threads (used in the nav + list badges).
const totalUnread = computed(() => threads.value.reduce((n, t) => n + (t.unread || 0), 0));

// Files shared in the active conversation (right profile panel).
const attachments = computed(() =>
    messages.value
        .filter((m) => m.file_url)
        .map((m) => ({ id: m.id, url: m.file_url, name: m.file_name || 'Attachment', is_image: m.is_image })));
const fileMeta = (a) => {
    const name = (a?.name || '').toLowerCase();
    const ext = name.includes('.') ? name.split('.').pop() : '';
    if (['pdf'].includes(ext)) return { bg: 'linear-gradient(135deg,#F97316,#FB923C)', icon: FileText };
    if (['doc', 'docx', 'txt', 'rtf'].includes(ext)) return { bg: 'linear-gradient(135deg,#7C3AED,#4C1D95)', icon: FileText };
    if (['xls', 'xlsx', 'csv'].includes(ext)) return { bg: 'linear-gradient(135deg,#10B981,#047857)', icon: FileSpreadsheet };
    if (['mp3', 'wav', 'ogg', 'm4a', 'aac'].includes(ext)) return { bg: 'linear-gradient(135deg,#EC4899,#BE185D)', icon: Music };
    if (['zip', 'rar', '7z'].includes(ext)) return { bg: 'linear-gradient(135deg,#F59E0B,#B45309)', icon: Download };
    return { bg: 'linear-gradient(135deg,#6366F1,#312E81)', icon: Download };
};
const myFileMeta = (a) => fileMeta(a).bg;

// Far-left navigation model.
const navItems = computed(() => ([
    { key: 'inbox', label: 'Inbox', icon: Inbox, badge: totalUnread.value },
    { key: 'starred', label: 'Starred', icon: Star, badge: starred.value.size },
    { key: 'tasks', label: 'Tasks', icon: ListChecks, badge: 0 },
]));

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
    // Reflect the theme the user picked (stored choice, else OS preference) so
    // the messenger surfaces, text, borders and accents follow it. The page
    // renders as a standalone layout, so it must initialise the theme itself.
    initTheme();
    loadThreads();
    pollTimer = setInterval(() => {
        if (activeThread.value) pollActive();
        else loadThreads(true);
    }, 3000);
});
onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <Head title="Messenger" />

    <div class="mm-root flex h-screen overflow-hidden bg-[var(--mm-bg)] font-sans text-[var(--mm-text)]">
        <!-- ═══ Far-left slim nav ═══ -->
        <nav class="hidden w-[76px] shrink-0 flex-col items-center border-r border-[var(--mm-border)] bg-[var(--mm-panel)] py-4 md:flex">
            <!-- Profile avatar + dropdown -->
            <button @click="profileOpen = !profileOpen" title="Your account"
                class="group flex flex-col items-center gap-1">
                <span class="relative">
                    <img v-if="myPhoto" :src="myPhoto" alt="" class="h-11 w-11 rounded-2xl object-cover ring-2 ring-[var(--mm-ring-40)]" />
                    <span v-else class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[var(--mm-grad-nav-a)] to-[var(--mm-grad-b)] text-sm font-black text-white shadow-lg shadow-[var(--mm-shadow-a30)]">{{ myInitial }}</span>
                    <span class="absolute -bottom-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-[var(--mm-panel)]">
                        <ChevronDown class="h-3 w-3 text-[var(--mm-muted)] transition group-hover:text-[var(--mm-text)]" />
                    </span>
                </span>
            </button>

            <div class="my-5 h-px w-8 bg-[var(--mm-surface-2)]" />

            <!-- Navigation icons -->
            <div class="flex flex-col items-center gap-3">
                <button v-for="nav in navItems" :key="nav.key" @click="activeNav = nav.key" :title="nav.label"
                    class="relative flex h-11 w-11 items-center justify-center rounded-2xl transition active:scale-95"
                    :class="activeNav === nav.key
                        ? 'bg-gradient-to-br from-[var(--mm-grad-nav-a)] to-[var(--mm-grad-nav-b)] text-white shadow-lg shadow-[var(--mm-shadow-a40)]'
                        : 'text-[var(--mm-muted)] hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)]'">
                    <component :is="nav.icon" class="h-5 w-5" />
                    <span v-if="nav.badge > 0" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-[var(--mm-accent-2)] px-1 text-[10px] font-black text-white ring-2 ring-[var(--mm-panel)]">{{ nav.badge > 99 ? '99+' : nav.badge }}</span>
                </button>
            </div>

            <!-- Settings at the bottom -->
            <button @click="activeNav = 'settings'" title="Settings"
                class="mt-auto flex h-11 w-11 items-center justify-center rounded-2xl transition active:scale-95"
                :class="activeNav === 'settings' ? 'bg-gradient-to-br from-[var(--mm-grad-nav-a)] to-[var(--mm-grad-nav-b)] text-white shadow-lg shadow-[var(--mm-shadow-a40)]' : 'text-[var(--mm-muted)] hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)]'">
                <Settings class="h-5 w-5" />
            </button>
        </nav>

        <!-- ═══ Left sidebar: chat list & search ═══ -->
        <aside :class="mobileView === 'list' ? 'flex' : 'hidden md:flex'"
            class="w-full flex-col border-r border-[var(--mm-border)] bg-[var(--mm-panel)] md:w-80 lg:w-[340px]">
            <!-- Header -->
            <div class="shrink-0 px-4 pb-3 pt-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-baseline gap-2">
                        <h1 class="text-lg font-black tracking-tight text-[var(--mm-text)]">{{ navTitle }}</h1>
                        <span v-if="activeNav === 'inbox' && totalUnread > 0" class="rounded-full bg-[var(--mm-accent-2)] px-2 py-0.5 text-[10px] font-black text-white">{{ totalUnread > 99 ? '99+' : totalUnread }} new</span>
                    </div>
                    <Link :href="route('dashboard')" title="Back to dashboard"
                        class="rounded-xl border border-[var(--mm-border-2)] p-2 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)]">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </div>
                <!-- Search -->
                <div class="relative mt-4">
                    <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--mm-muted)]" />
                    <input v-model="listQuery" placeholder="Search in your Inbox"
                        class="w-full rounded-full border border-[var(--mm-border-2)] bg-[var(--mm-surface)] py-2.5 pl-10 pr-4 text-sm text-[var(--mm-text)] placeholder-[var(--mm-muted)] outline-none transition focus:border-[var(--mm-accent-60)] focus:bg-[var(--mm-surface-2)] focus:ring-2 focus:ring-[var(--mm-ring-25)]" />
                </div>
            </div>

            <!-- Threads -->
            <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                <button v-for="t in listThreads" :key="t.id" @click="openThread(t)"
                    :class="['group relative mb-1.5 flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-left transition',
                        activeThread?.id === t.id
                            ? 'bg-gradient-to-r from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] text-white shadow-lg shadow-[var(--mm-shadow-b25)]'
                            : 'hover:bg-[var(--mm-surface)]']">
                    <!-- Avatar + online dot -->
                    <span class="relative shrink-0">
                        <img v-if="threadAvatar(t)" :src="threadAvatar(t)" alt="" class="h-12 w-12 rounded-full object-cover" />
                        <span v-else class="flex h-12 w-12 items-center justify-center rounded-full text-sm font-black text-white" :class="`bg-gradient-to-br ${avatarGradient(t.title)}`">{{ initialOf(t.title) }}</span>
                        <span class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full border-2 border-[var(--mm-panel)] bg-[var(--mm-online)]" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="truncate text-sm font-bold" :class="activeThread?.id === t.id ? 'text-white' : 'text-[var(--mm-text)]'">{{ t.title }}</span>
                            <span class="ml-auto shrink-0 text-[10px] font-semibold" :class="activeThread?.id === t.id ? 'text-white/80' : 'text-[var(--mm-muted)]'">{{ fmtTime(t.at) }}</span>
                        </span>
                        <span class="mt-1 flex items-center gap-2">
                            <span class="min-w-0 flex-1 truncate text-xs" :class="activeThread?.id === t.id ? 'text-white/85' : 'text-[var(--mm-muted)]'">
                                {{ t.preview_by ? t.preview_by + ': ' : '' }}{{ t.preview || 'No messages yet' }}
                            </span>
                            <span v-if="t.unread > 0" class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1 text-[10px] font-black"
                                :class="activeThread?.id === t.id ? 'bg-white text-[var(--mm-accent-2)]' : 'bg-[var(--mm-accent-2)] text-white'">{{ t.unread > 99 ? '99+' : t.unread }}</span>
                            <span v-else-if="t.kind === 'group'" class="shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-black uppercase"
                                :class="activeThread?.id === t.id ? 'bg-[var(--mm-surface-4)] text-white' : 'bg-[var(--mm-accent-20)] text-[var(--mm-accent-soft)]'">Group</span>
                        </span>
                    </span>

                    <!-- Star toggle -->
                    <button @click.stop="toggleStar(t.id)" :title="isStarred(t.id) ? 'Unstar' : 'Star'"
                        class="absolute right-2 top-1/2 hidden -translate-y-1/2 rounded-lg p-1.5 transition group-hover:block"
                        :class="activeThread?.id === t.id ? 'text-white/90 hover:text-white' : 'text-[var(--mm-muted)] hover:text-[var(--mm-star)]'">
                        <Star class="h-3.5 w-3.5" :class="isStarred(t.id) ? 'text-[var(--mm-star)]' : ''" :fill="isStarred(t.id) ? 'currentColor' : 'none'" />
                    </button>
                </button>

                <!-- Loading skeletons -->
                <div v-if="threadsLoading && !listThreads.length" class="space-y-2 px-0.5 py-2">
                    <div v-for="n in 6" :key="n" class="flex animate-pulse items-center gap-3 rounded-2xl px-3 py-3">
                        <span class="h-12 w-12 shrink-0 rounded-full bg-[var(--mm-surface)]" />
                        <span class="flex-1 space-y-2">
                            <span class="block h-3 w-1/2 rounded bg-[var(--mm-surface)]" />
                            <span class="block h-2.5 w-3/4 rounded bg-[var(--mm-surface)]" />
                        </span>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="!threadsLoading && !listThreads.length" class="px-4 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[var(--mm-grad-a-30)] to-[var(--mm-grad-b-30)]">
                        <component :is="activeNav === 'starred' ? Star : activeNav === 'tasks' ? ListChecks : Users" class="h-7 w-7 text-[var(--mm-accent-soft)]" />
                    </div>
                    <p class="mt-3 text-xs font-semibold text-[var(--mm-muted)]">{{ navEmptyMessage }}</p>
                    <button v-if="activeNav === 'inbox' && !listQuery" @click="startCompose"
                        class="mt-3 rounded-full bg-gradient-to-r from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] px-4 py-2 text-xs font-black uppercase text-white shadow-lg shadow-[var(--mm-shadow-b25)] transition hover:opacity-90">
                        Start one
                    </button>
                </div>
            </div>

            <!-- Compose button -->
            <div class="shrink-0 border-t border-[var(--mm-border)] p-3">
                <button @click="startCompose"
                    class="flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] py-3 text-xs font-black uppercase text-white shadow-lg shadow-[var(--mm-shadow-b25)] transition hover:opacity-90 active:scale-[0.98]">
                    <Plus class="h-4 w-4" /> New conversation
                </button>
            </div>
        </aside>

        <!-- ═══ Main chat view ═══ -->
        <section :class="mobileView === 'chat' ? 'flex' : 'hidden md:flex'" class="chat-canvas relative min-w-0 flex-1 flex-col bg-[var(--mm-bg)]">
            <!-- Empty state -->
            <div v-if="!activeThread && !composing" class="flex h-full flex-col items-center justify-center p-8 text-center">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-0 rounded-full bg-[var(--mm-accent-glow)] blur-3xl" />
                    <span class="relative z-10 flex h-20 w-20 items-center justify-center rounded-[1.75rem] bg-gradient-to-br from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] text-white shadow-2xl shadow-[var(--mm-shadow-a40)]">
                        <MessagesSquare class="h-9 w-9" />
                    </span>
                </div>
                <h2 class="mt-5 text-xl font-black tracking-tight text-[var(--mm-text)]">Your messages</h2>
                <p class="mt-1.5 max-w-xs text-sm text-[var(--mm-muted)]">Pick a conversation, or start a new one with any Monti employee.</p>
                <button @click="startCompose"
                    class="mt-6 inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] px-5 py-2.5 text-xs font-black uppercase text-white shadow-lg shadow-[var(--mm-shadow-b25)] transition hover:opacity-90 active:scale-95">
                    <Plus class="h-4 w-4" /> New conversation
                </button>
            </div>

            <!-- Compose -->
            <div v-if="composing" class="flex min-h-0 flex-1 flex-col bg-[var(--mm-bg)]">
                <div class="flex items-center gap-2 border-b border-[var(--mm-border)] px-4 py-3.5">
                    <button @click="backToList(); composing = false"
                        class="rounded-xl p-2 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)]">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <p class="flex-1 text-sm font-black text-[var(--mm-text)]">New conversation</p>
                    <span v-if="picked.length" class="rounded-full bg-[var(--mm-accent-25)] px-2.5 py-0.5 text-[10px] font-black text-[var(--mm-accent-soft)]">{{ picked.length }}/8 selected</span>
                </div>
                <div class="border-b border-[var(--mm-border)] p-3">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--mm-muted)]" />
                        <input v-model="dirQuery" placeholder="Search people by name, email, role…"
                            class="w-full rounded-full border border-[var(--mm-border-2)] bg-[var(--mm-surface)] py-2.5 pl-10 pr-4 text-sm text-[var(--mm-text)] placeholder-[var(--mm-muted)] outline-none transition focus:border-[var(--mm-accent-60)] focus:ring-2 focus:ring-[var(--mm-ring-25)]" />
                    </div>
                    <div v-if="picked.length" class="mt-2.5 flex flex-wrap gap-1.5">
                        <span v-for="p in picked" :key="p.id" class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-[var(--mm-grad-a-40)] to-[var(--mm-grad-b-40)] px-2.5 py-1 text-[11px] font-black text-white">
                            {{ p.name }} <button @click="togglePick(p)" class="transition hover:text-[var(--mm-danger)]"><X class="h-3 w-3" /></button>
                        </span>
                    </div>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <button v-for="u in filteredDir" :key="u.id" @click="togglePick(u)"
                        :class="['flex w-full items-center gap-3 px-4 py-2.5 text-left transition hover:bg-[var(--mm-surface)]', picked.some((p) => p.id === u.id) && 'bg-[var(--mm-accent-15)]']">
                        <img v-if="u.photo" :src="u.photo" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover" />
                        <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-black text-white" :class="`bg-gradient-to-br ${avatarGradient(u.name)}`">{{ initialOf(u.name) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[13px] font-black text-[var(--mm-text)]">{{ u.name }}</span>
                            <span class="block truncate text-[11px] text-[var(--mm-muted)]">{{ u.role }} · {{ u.position }}</span>
                        </span>
                        <span v-if="picked.some((p) => p.id === u.id)" class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] text-white">
                            <Check class="h-3 w-3" />
                        </span>
                    </button>
                    <p v-if="!filteredDir.length" class="px-4 py-10 text-center text-xs font-bold text-[var(--mm-muted)]">No people found.</p>
                </div>
                <div class="space-y-2 border-t border-[var(--mm-border)] p-3">
                    <input v-model="newTitle" placeholder="Group title (optional, for 2+ people)…"
                        class="w-full rounded-full border border-[var(--mm-border-2)] bg-[var(--mm-surface)] px-4 py-2.5 text-sm text-[var(--mm-text)] placeholder-[var(--mm-muted)] outline-none transition focus:border-[var(--mm-accent-60)] focus:ring-2 focus:ring-[var(--mm-ring-25)]" />
                    <div class="flex gap-2">
                        <input v-model="newBody" @keyup.enter="createThread" placeholder="First message…"
                            class="flex-1 rounded-full border border-[var(--mm-border-2)] bg-[var(--mm-surface)] px-4 py-2.5 text-sm text-[var(--mm-text)] placeholder-[var(--mm-muted)] outline-none transition focus:border-[var(--mm-accent-60)] focus:ring-2 focus:ring-[var(--mm-ring-25)]" />
                        <button @click="createThread" :disabled="creating || !picked.length || !newBody.trim()"
                            class="rounded-full bg-gradient-to-r from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] px-5 py-2.5 text-xs font-black uppercase text-white shadow-lg shadow-[var(--mm-shadow-b25)] transition hover:opacity-90 disabled:opacity-40 active:scale-95">
                            Send
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active conversation -->
            <template v-if="activeThread && !composing">
                <!-- Header -->
                <div class="flex items-center gap-3 border-b border-[var(--mm-border)] bg-[var(--mm-header)] px-4 py-3 backdrop-blur">
                    <button @click="backToList" class="rounded-xl p-1.5 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)] md:hidden">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="relative shrink-0">
                        <img v-if="activeThread.photo" :src="activeThread.photo" alt="" class="h-10 w-10 rounded-full object-cover" />
                        <span v-else class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-black text-white" :class="`bg-gradient-to-br ${avatarGradient(activeThread.title)}`">{{ initialOf(activeThread.title) }}</span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-black text-[var(--mm-text)]">{{ activeThread.title }}</p>
                        <p class="flex items-center gap-1.5 text-[11px] font-semibold text-[var(--mm-online)]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[var(--mm-online)] shadow-[0_0_8px_var(--mm-online)]" /> Active Now
                        </p>
                    </div>
                    <button title="Audio call" class="rounded-xl p-2 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)]">
                        <Phone class="h-4 w-4" />
                    </button>
                    <button title="Video call" class="rounded-xl p-2 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)]">
                        <Video class="h-4 w-4" />
                    </button>
                    <button v-if="activeThread.kind === 'group' && msgView !== 'settings'" @click="openSettings" title="Group settings"
                        class="rounded-xl p-2 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)]">
                        <Settings class="h-4 w-4" />
                    </button>
                    <button v-if="msgView === 'settings'" @click="msgView = 'chat'" title="Back to chat"
                        class="rounded-xl p-2 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)]">
                        <MessagesSquare class="h-4 w-4" />
                    </button>
                    <button @click="profileOpen = !profileOpen" title="Profile & details"
                        class="rounded-xl p-2 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)]"
                        :class="profileOpen && 'bg-[var(--mm-accent-20)] text-[var(--mm-text)]'">
                        <PanelRight class="h-4 w-4" />
                    </button>
                </div>

                <!-- Chat -->
                <div v-show="msgView === 'chat'" class="flex min-h-0 flex-1 flex-col">
                    <div ref="msgBox" class="min-h-0 flex-1 space-y-1.5 overflow-y-auto px-4 py-4 sm:px-6">
                        <p v-if="loadingThread" class="py-8 text-center text-xs font-bold text-[var(--mm-muted)]">Loading…</p>
                        <template v-for="(m, i) in messages" :key="m.id">
                            <div v-if="showDay(i)" class="flex justify-center py-3">
                                <span class="rounded-full bg-[var(--mm-surface)] px-3 py-1 text-[10px] font-black uppercase tracking-wider text-[var(--mm-muted)]">{{ dayLabel(m.at) }}</span>
                            </div>
                            <div :class="['flex items-end gap-2', m.mine ? 'justify-end' : 'justify-start']">
                                <!-- received avatar (left) -->
                                <template v-if="!m.mine">
                                    <img v-if="showAvatar(i) && m.sender_photo" :src="m.sender_photo" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover" />
                                    <span v-else-if="showAvatar(i)" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-black text-white" :class="`bg-gradient-to-br ${avatarGradient(m.sender)}`">{{ initialOf(m.sender) }}</span>
                                    <span v-else class="w-7 shrink-0" />
                                </template>
                                <!-- bubble -->
                                <div :class="['max-w-[78%] rounded-2xl px-3.5 py-2 text-[13px] leading-relaxed',
                                    m.mine ? 'rounded-br-md bg-gradient-to-r from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] text-white shadow-lg shadow-[var(--mm-shadow-b20)]' : 'rounded-bl-md bg-gradient-to-r from-[var(--mm-recv-from)] to-[var(--mm-recv-to)] text-white shadow-lg shadow-[var(--mm-recv-shadow)]']">
                                    <p v-if="showSender(i)" class="mb-0.5 text-[10px] font-black uppercase tracking-wide text-white/80">{{ m.sender }}</p>
                                    <button v-if="m.is_image && m.file_url" type="button" @click="openLightbox(m.file_url)" class="mb-1.5 block overflow-hidden rounded-xl" title="View image">
                                        <img :src="m.file_url" alt="" class="max-h-64 w-full object-cover transition hover:opacity-90" loading="lazy" />
                                    </button>
                                    <a v-else-if="m.file_url" :href="m.file_url" target="_blank"
                                        class="mb-1.5 flex items-center gap-2 rounded-xl bg-[var(--mm-surface-3)] px-2.5 py-2 text-xs font-bold text-white transition hover:bg-[var(--mm-surface-5)]">
                                        <Paperclip class="h-3.5 w-3.5 shrink-0" />
                                        <span class="truncate">{{ m.file_name || 'Attachment' }}</span>
                                    </a>
                                    <p v-if="m.body" class="whitespace-pre-wrap">{{ m.body }}</p>
                                    <p class="mt-1 text-right text-[9px] font-bold text-white/70">{{ fmtTime(m.at) }}</p>
                                </div>
                                <!-- sent avatar (right) -->
                                <template v-if="m.mine">
                                    <img v-if="showMineAvatar(i) && myPhoto" :src="myPhoto" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover" />
                                    <span v-else-if="showMineAvatar(i)" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[var(--mm-grad-nav-a)] to-[var(--mm-grad-b)] text-[10px] font-black text-white">{{ myInitial }}</span>
                                    <span v-else class="w-7 shrink-0" />
                                </template>
                            </div>
                        </template>
                        <p v-if="!loadingThread && !messages.length" class="py-10 text-center text-xs font-bold text-[var(--mm-muted)]">
                            Say hello to start the conversation.
                        </p>
                    </div>

                    <!-- Attachment preview -->
                    <div v-if="attachFile" class="flex items-center gap-2 border-t border-[var(--mm-border)] bg-[var(--mm-surface)] px-3 py-2">
                        <img v-if="attachUrl" :src="attachUrl" alt="" class="h-10 w-10 rounded-lg object-cover" />
                        <span v-else class="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--mm-surface)]"><Paperclip class="h-4 w-4 text-[var(--mm-accent-soft)]" /></span>
                        <span class="min-w-0 flex-1 truncate text-xs font-bold text-[var(--mm-text)]">{{ attachFile.name }}</span>
                        <button @click="clearAttach" class="rounded-lg p-1 text-[var(--mm-muted)] transition hover:text-[var(--mm-danger)]"><X class="h-3.5 w-3.5" /></button>
                    </div>

                    <!-- Input bar -->
                    <div class="border-t border-[var(--mm-border)] p-3">
                        <div class="relative flex items-center gap-1.5 rounded-full border border-[var(--mm-border-2)] bg-[var(--mm-raised)] px-2 py-1.5">
                            <!-- Emoji -->
                            <div class="relative">
                                <button @click="emojiOpen = !emojiOpen" title="Emoji"
                                    class="rounded-full p-2 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-star)]">
                                    <Smile class="h-5 w-5" />
                                </button>
                                <Transition name="pop">
                                    <div v-if="emojiOpen" class="absolute bottom-12 left-0 z-20 grid w-56 grid-cols-6 gap-1 rounded-2xl border border-[var(--mm-border-2)] bg-[var(--mm-raised)] p-2 shadow-2xl">
                                        <button v-for="(e, i) in EMOJIS" :key="i" @click="insertEmoji(e)"
                                            class="rounded-lg p-1.5 text-lg transition hover:bg-[var(--mm-surface-2)]">{{ e }}</button>
                                    </div>
                                </Transition>
                            </div>
                            <input ref="attachInput" type="file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" @change="onAttach" class="hidden" />
                            <button @click="pickAttach" title="Attach a file"
                                class="rounded-full p-2 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-accent-soft)]">
                                <Paperclip class="h-5 w-5" />
                            </button>
                            <input v-model="draft" @keyup.enter="sendMessage" @focus="emojiOpen = false" placeholder="Type a message..."
                                class="min-w-0 flex-1 bg-transparent px-1 py-2 text-sm text-[var(--mm-text)] placeholder-[var(--mm-muted)] outline-none" />
                            <button @click="sendMessage" :disabled="sending || (!draft.trim() && !attachFile)" title="Send"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] text-white shadow-lg shadow-[var(--mm-shadow-b30)] transition hover:opacity-90 disabled:opacity-40 active:scale-95">
                                <Send class="h-4 w-4 -translate-x-px" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Group settings -->
                <div v-if="msgView === 'settings' && activeThread.kind === 'group'" class="min-h-0 flex-1 overflow-y-auto bg-[var(--mm-bg)]">
                    <div class="relative overflow-hidden bg-gradient-to-r from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] px-5 py-6">
                        <span class="pointer-events-none absolute -right-8 -top-10 h-32 w-32 rounded-full bg-[var(--mm-surface-3)] blur-2xl" />
                        <div class="relative flex items-center gap-4">
                            <img v-if="activeThread.photo" :src="activeThread.photo" alt="" class="h-16 w-16 rounded-2xl object-cover ring-2 ring-white/40" />
                            <span v-else class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[var(--mm-surface-3)] text-xl font-black text-white ring-1 ring-inset ring-white/25">
                                {{ initialOf(activeThread.title) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-black text-[var(--mm-text)]">{{ activeThread.title }}</p>
                                <p class="text-[11px] font-bold text-white/75">{{ activeThread.members?.length || 0 }} members</p>
                            </div>
                        </div>
                    </div>
                    <div class="mx-auto max-w-2xl space-y-4 p-5">
                        <p v-if="groupError" class="rounded-xl bg-[var(--mm-accent2-15)] px-3 py-2 text-xs font-bold text-[var(--mm-danger)]">{{ groupError }}</p>

                        <div class="rounded-2xl border border-[var(--mm-border-2)] bg-[var(--mm-surface)] p-4">
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-[var(--mm-muted)]">Group name</label>
                            <div class="flex gap-2">
                                <input v-model="groupName" maxlength="120" placeholder="Group name…"
                                    class="flex-1 rounded-xl border border-[var(--mm-border-2)] bg-[var(--mm-surface)] px-3 py-2.5 text-sm text-[var(--mm-text)] placeholder-[var(--mm-muted)] outline-none transition focus:border-[var(--mm-accent-60)]" />
                                <button @click="saveGroupName" :disabled="groupBusy || !groupName.trim()"
                                    class="rounded-xl bg-gradient-to-r from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] px-4 text-xs font-black uppercase text-[var(--mm-text)] transition hover:opacity-90 disabled:opacity-40 active:scale-95">
                                    Save
                                </button>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-[var(--mm-border-2)] bg-[var(--mm-surface)] p-4">
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-[var(--mm-muted)]">Group photo</label>
                            <input ref="photoInput" type="file" accept="image/*" @change="changePhoto" class="hidden" />
                            <div class="flex gap-2">
                                <button @click="photoInput.click()" :disabled="groupBusy"
                                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-[var(--mm-border-2)] bg-[var(--mm-surface)] px-3 py-2.5 text-xs font-black uppercase text-[var(--mm-text)] transition hover:bg-[var(--mm-surface-2)] disabled:opacity-40">
                                    <ImagePlus class="h-4 w-4" /> {{ activeThread.photo ? 'Change photo' : 'Upload photo' }}
                                </button>
                                <button v-if="activeThread.photo" @click="removePhoto" :disabled="groupBusy" title="Remove photo"
                                    class="rounded-xl border border-[var(--mm-border-2)] bg-[var(--mm-surface)] px-3 text-[var(--mm-danger)] transition hover:bg-[var(--mm-accent2-10)] disabled:opacity-40">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-[var(--mm-border-2)] bg-[var(--mm-surface)] p-4">
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-[var(--mm-muted)]">Members ({{ activeThread.members?.length || 0 }})</label>
                            <div class="space-y-1.5">
                                <div v-for="m in activeThread.members" :key="m.id"
                                    class="flex items-center gap-2.5 rounded-xl bg-[var(--mm-surface)] px-3 py-2">
                                    <img v-if="m.photo" :src="m.photo" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover" />
                                    <span v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[10px] font-black text-white" :class="`bg-gradient-to-br ${avatarGradient(m.name)}`">{{ initialOf(m.name) }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-xs font-black text-[var(--mm-text)]">{{ m.name }}</span>
                                        <span class="block truncate text-[10px] text-[var(--mm-muted)]">{{ m.role }} · {{ m.position }}</span>
                                    </span>
                                    <button @click="removeMember(m)" :disabled="groupBusy" title="Remove from group"
                                        class="rounded-lg p-1.5 text-[var(--mm-muted)] transition hover:bg-[var(--mm-accent2-10)] hover:text-[var(--mm-danger)] disabled:opacity-40">
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-[var(--mm-border-2)] bg-[var(--mm-surface)] p-4">
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-[var(--mm-muted)]">Add employee</label>
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--mm-muted)]" />
                                <input v-model="addQuery" @input="searchAdd" @focus="searchAdd" placeholder="Search name or email…"
                                    class="w-full rounded-xl border border-[var(--mm-border-2)] bg-[var(--mm-surface)] py-2.5 pl-9 pr-3 text-sm text-[var(--mm-text)] placeholder-[var(--mm-muted)] outline-none transition focus:border-[var(--mm-accent-60)]" />
                            </div>
                            <div v-if="addResults.length" class="mt-1.5 max-h-48 space-y-1 overflow-y-auto">
                                <button v-for="u in addResults" :key="u.id" @click="addMember(u)" :disabled="addingId === u.id"
                                    class="flex w-full items-center gap-2 rounded-xl px-2.5 py-2 text-left transition hover:bg-[var(--mm-surface)] disabled:opacity-50">
                                    <img v-if="u.photo" :src="u.photo" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover" />
                                    <span v-else class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-black text-white" :class="`bg-gradient-to-br ${avatarGradient(u.name)}`">{{ initialOf(u.name) }}</span>
                                    <span class="min-w-0 flex-1 truncate text-xs font-bold text-[var(--mm-text)]">{{ u.name }} <span class="font-normal text-[var(--mm-muted)]">· {{ u.role }}</span></span>
                                    <Plus class="h-3.5 w-3.5 shrink-0 text-[var(--mm-accent-soft)]" />
                                </button>
                            </div>
                        </div>

                        <button @click="leaveGroup" :disabled="groupBusy"
                            class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-[var(--mm-accent2-30)] py-2.5 text-xs font-black uppercase text-[var(--mm-danger)] transition hover:bg-[var(--mm-accent2-10)] disabled:opacity-40">
                            <X class="h-4 w-4" /> Leave this group
                        </button>
                    </div>
                </div>
            </template>
        </section>

        <!-- ═══ Right sidebar: Profile & details ═══ -->
        <!-- Backdrop (mobile / tablet overlay only) -->
        <div v-if="profileOpen" class="fixed inset-0 z-30 bg-black/50 backdrop-blur-sm xl:hidden" @click="profileOpen = false" />

        <aside class="fixed right-0 top-0 z-40 h-full w-80 max-w-[85vw] shrink-0 transform border-l border-[var(--mm-border)] bg-[var(--mm-panel)] transition-transform duration-300 xl:static xl:z-auto xl:h-auto xl:max-w-none xl:translate-x-0"
            :class="profileOpen ? 'translate-x-0' : 'translate-x-full xl:translate-x-0'">
            <div class="flex h-full flex-col overflow-y-auto">
                <div class="flex items-center justify-between border-b border-[var(--mm-border)] px-5 py-4">
                    <h2 class="text-sm font-black text-[var(--mm-text)]">Profile Info</h2>
                    <button @click="profileOpen = false" class="rounded-xl p-1.5 text-[var(--mm-muted)] transition hover:bg-[var(--mm-surface)] hover:text-[var(--mm-text)] xl:hidden">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Profile overview -->
                <div class="flex flex-col items-center px-5 py-6 text-center">
                    <span class="relative">
                        <span class="pointer-events-none absolute inset-0 rounded-full bg-[var(--mm-accent-glow)] blur-2xl" />
                        <img v-if="activeThread?.photo" :src="activeThread.photo" alt="" class="relative h-24 w-24 rounded-full object-cover ring-4 ring-[var(--mm-ring-30)]" />
                        <span v-else-if="activeThread" class="relative flex h-24 w-24 items-center justify-center rounded-full text-3xl font-black text-white ring-4 ring-[var(--mm-ring-30)]" :class="`bg-gradient-to-br ${avatarGradient(activeThread.title)}`">{{ initialOf(activeThread.title) }}</span>
                        <img v-else-if="myPhoto" :src="myPhoto" alt="" class="relative h-24 w-24 rounded-full object-cover ring-4 ring-[var(--mm-ring-30)]" />
                        <span v-else class="relative flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-[var(--mm-grad-a)] to-[var(--mm-grad-b)] text-3xl font-black text-white ring-4 ring-[var(--mm-ring-30)]">{{ myInitial }}</span>
                    </span>
                    <p class="mt-4 text-base font-black text-[var(--mm-text)]">{{ activeThread?.title || loginName }}</p>
                    <p class="mt-1 flex items-center gap-1.5 text-[11px] font-semibold text-[var(--mm-online)]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[var(--mm-online)] shadow-[0_0_8px_var(--mm-online)]" /> Active Now
                    </p>
                    <p v-if="displaySub" class="mt-1 text-[11px] font-semibold text-[var(--mm-muted)]">{{ displaySub }}</p>
                </div>

                <!-- Attachments -->
                <div class="border-t border-[var(--mm-border)] px-5 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-widest text-[var(--mm-muted)]">Attachment</h3>
                        <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-gradient-to-br from-[var(--mm-grad-b)] to-[var(--mm-grad-nav-b)] px-1.5 text-[10px] font-black text-white">{{ attachments.length }}</span>
                    </div>
                    <div v-if="attachments.length" class="mt-3 grid grid-cols-3 gap-2">
                        <template v-for="a in attachments.slice(0, 6)" :key="a.id">
                            <button v-if="a.is_image" type="button" @click="openLightbox(a.url)"
                                class="group relative aspect-square overflow-hidden rounded-xl border border-[var(--mm-border)]" title="View image">
                                <img :src="a.url" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-110" />
                            </button>
                            <a v-else :href="a.url" target="_blank" title="Download"
                                class="group flex aspect-square flex-col items-center justify-center gap-1 overflow-hidden rounded-xl p-1.5 text-center"
                                :style="{ background: fileMeta(a).bg }">
                                <component :is="fileMeta(a).icon" class="h-5 w-5 text-white" />
                                <span class="line-clamp-2 text-[8px] font-bold leading-tight text-white/90">{{ a.name }}</span>
                                <Download class="h-3 w-3 text-white/80 transition group-hover:translate-y-0.5" />
                            </a>
                        </template>
                    </div>
                    <p v-else class="mt-3 rounded-xl bg-[var(--mm-surface)] px-3 py-6 text-center text-[11px] font-semibold text-[var(--mm-muted)]">
                        No attachments yet.
                    </p>
                </div>

                <!-- Members (groups) -->
                <div v-if="activeThread?.kind === 'group'" class="border-t border-[var(--mm-border)] px-5 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-widest text-[var(--mm-muted)]">Members</h3>
                        <span class="text-[10px] font-black text-[var(--mm-muted)]">{{ activeThread.members?.length || 0 }}</span>
                    </div>
                    <div class="mt-3 space-y-1.5">
                        <div v-for="m in (activeThread.members || []).slice(0, 8)" :key="m.id" class="flex items-center gap-2.5">
                            <img v-if="m.photo" :src="m.photo" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover" />
                            <span v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[10px] font-black text-white" :class="`bg-gradient-to-br ${avatarGradient(m.name)}`">{{ initialOf(m.name) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-bold text-[var(--mm-text)]">{{ m.name }}</span>
                                <span class="block truncate text-[10px] text-[var(--mm-muted)]">{{ m.role }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Image lightbox -->
        <Teleport to="body">
            <div v-if="lightbox" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/90 p-4 sm:p-10" @click.self="closeLightbox">
                <button @click="closeLightbox" title="Close"
                    class="absolute right-5 top-5 rounded-xl bg-[var(--mm-surface-2)] p-2.5 text-white transition hover:bg-[var(--mm-surface-4)] active:scale-95">
                    <X class="h-5 w-5" />
                </button>
                <img :src="lightbox" alt="" class="max-h-full max-w-full rounded-2xl object-contain shadow-2xl" />
            </div>
        </Teleport>
    </div>
</template>

<style>
/* ─────────────────────────────────────────────────────────────────────────
   Monti Messenger theme tokens. The page is a standalone layout, so it
   defines its own palette per `data-theme` (set by useTheme/initTheme) and
   every surface references these variables. `dark` is the base palette.
   ───────────────────────────────────────────────────────────────────────── */
.mm-root {
    --mm-bg: #0b0b12;
    --mm-panel: #101019;
    --mm-raised: #161622;
    --mm-header: rgba(15, 15, 24, 0.9);
    --mm-surface: rgba(255, 255, 255, 0.05);
    --mm-surface-2: rgba(255, 255, 255, 0.1);
    --mm-surface-3: rgba(255, 255, 255, 0.15);
    --mm-surface-4: rgba(255, 255, 255, 0.2);
    --mm-surface-5: rgba(255, 255, 255, 0.25);
    --mm-border: rgba(255, 255, 255, 0.05);
    --mm-border-2: rgba(255, 255, 255, 0.1);
    --mm-text: #ffffff;
    --mm-muted: #8b8fa3;
    --mm-accent-soft: #c4b5fd;
    --mm-online: #22c55e;
    --mm-star: #fbbf24;
    --mm-danger: #fca5a5;
    --mm-accent-2: #ec4899;
    --mm-grad-a: #7c3aed;
    --mm-grad-b: #ec4899;
    --mm-grad-nav-a: #8b5cf6;
    --mm-grad-nav-b: #7c3aed;
    --mm-recv-from: #f97316;
    --mm-recv-to: #fdba74;
    --mm-accent-15: rgba(124, 58, 237, 0.15);
    --mm-accent-20: rgba(124, 58, 237, 0.2);
    --mm-accent-25: rgba(124, 58, 237, 0.25);
    --mm-accent-60: rgba(139, 92, 246, 0.6);
    --mm-accent-glow: rgba(139, 92, 246, 0.25);
    --mm-accent2-10: rgba(236, 72, 153, 0.1);
    --mm-accent2-15: rgba(236, 72, 153, 0.15);
    --mm-accent2-30: rgba(236, 72, 153, 0.3);
    --mm-grad-a-30: rgba(124, 58, 237, 0.3);
    --mm-grad-a-40: rgba(124, 58, 237, 0.4);
    --mm-grad-b-30: rgba(236, 72, 153, 0.3);
    --mm-grad-b-40: rgba(236, 72, 153, 0.4);
    --mm-ring-25: rgba(139, 92, 246, 0.25);
    --mm-ring-30: rgba(139, 92, 246, 0.3);
    --mm-ring-40: rgba(139, 92, 246, 0.4);
    --mm-shadow-a30: rgba(139, 92, 246, 0.3);
    --mm-shadow-a40: rgba(139, 92, 246, 0.4);
    --mm-shadow-b20: rgba(236, 72, 153, 0.2);
    --mm-shadow-b25: rgba(236, 72, 153, 0.25);
    --mm-shadow-b30: rgba(236, 72, 153, 0.3);
    --mm-recv-shadow: rgba(249, 115, 22, 0.2);
    --mm-grid: rgba(139, 92, 246, 0.1);
    --mm-scroll: rgba(139, 92, 246, 0.35);
    --mm-scroll-hover: rgba(139, 92, 246, 0.6);
}
html[data-theme='dark'] .mm-root {
    --mm-bg: #0b0b12;
    --mm-panel: #101019;
}
html[data-theme='light'] .mm-root {
    --mm-bg: #eef2f7;
    --mm-panel: #ffffff;
    --mm-raised: #ffffff;
    --mm-header: rgba(255, 255, 255, 0.9);
    --mm-surface: rgba(15, 23, 42, 0.04);
    --mm-surface-2: rgba(15, 23, 42, 0.07);
    --mm-surface-3: rgba(15, 23, 42, 0.1);
    --mm-surface-4: rgba(15, 23, 42, 0.14);
    --mm-surface-5: rgba(15, 23, 42, 0.18);
    --mm-border: rgba(15, 23, 42, 0.08);
    --mm-border-2: rgba(15, 23, 42, 0.12);
    --mm-text: #0f172a;
    --mm-muted: #64748b;
    --mm-accent-soft: #6d28d9;
    --mm-online: #16a34a;
    --mm-star: #d97706;
    --mm-danger: #dc2626;
    --mm-accent-2: #db2777;
    --mm-grad-a: #6366f1;
    --mm-grad-b: #8b5cf6;
    --mm-grad-nav-a: #6366f1;
    --mm-grad-nav-b: #4f46e5;
    --mm-accent-15: rgba(99, 102, 241, 0.15);
    --mm-accent-20: rgba(99, 102, 241, 0.2);
    --mm-accent-25: rgba(99, 102, 241, 0.25);
    --mm-accent-60: rgba(99, 102, 241, 0.6);
    --mm-accent-glow: rgba(99, 102, 241, 0.2);
    --mm-accent2-10: rgba(219, 39, 119, 0.1);
    --mm-accent2-15: rgba(219, 39, 119, 0.15);
    --mm-accent2-30: rgba(219, 39, 119, 0.3);
    --mm-grad-a-30: rgba(99, 102, 241, 0.3);
    --mm-grad-a-40: rgba(99, 102, 241, 0.4);
    --mm-grad-b-30: rgba(139, 92, 246, 0.3);
    --mm-grad-b-40: rgba(139, 92, 246, 0.4);
    --mm-ring-25: rgba(99, 102, 241, 0.25);
    --mm-ring-30: rgba(99, 102, 241, 0.3);
    --mm-ring-40: rgba(99, 102, 241, 0.4);
    --mm-shadow-a30: rgba(99, 102, 241, 0.25);
    --mm-shadow-a40: rgba(99, 102, 241, 0.3);
    --mm-shadow-b20: rgba(219, 39, 119, 0.2);
    --mm-shadow-b25: rgba(219, 39, 119, 0.25);
    --mm-shadow-b30: rgba(219, 39, 119, 0.3);
    --mm-grid: rgba(99, 102, 241, 0.1);
    --mm-scroll: rgba(99, 102, 241, 0.3);
    --mm-scroll-hover: rgba(99, 102, 241, 0.55);
}
html[data-theme='blue'] .mm-root {
    --mm-bg: #f0f2f5;
    --mm-panel: #ffffff;
    --mm-raised: #ffffff;
    --mm-header: rgba(255, 255, 255, 0.9);
    --mm-surface: rgba(15, 23, 42, 0.04);
    --mm-surface-2: rgba(15, 23, 42, 0.07);
    --mm-surface-3: rgba(15, 23, 42, 0.1);
    --mm-surface-4: rgba(15, 23, 42, 0.14);
    --mm-surface-5: rgba(15, 23, 42, 0.18);
    --mm-border: rgba(17, 37, 90, 0.08);
    --mm-border-2: rgba(17, 37, 90, 0.14);
    --mm-text: #0b1b4f;
    --mm-muted: #4a5b8c;
    --mm-accent-soft: #1a73e8;
    --mm-online: #22c55e;
    --mm-star: #f59e0b;
    --mm-danger: #dc2626;
    --mm-accent-2: #0ea5e9;
    --mm-grad-a: #1a73e8;
    --mm-grad-b: #0ea5e9;
    --mm-grad-nav-a: #1a73e8;
    --mm-grad-nav-b: #0b3d91;
    --mm-accent-15: rgba(26, 115, 232, 0.15);
    --mm-accent-20: rgba(26, 115, 232, 0.2);
    --mm-accent-25: rgba(26, 115, 232, 0.25);
    --mm-accent-60: rgba(26, 115, 232, 0.6);
    --mm-accent-glow: rgba(26, 115, 232, 0.2);
    --mm-accent2-10: rgba(14, 165, 233, 0.1);
    --mm-accent2-15: rgba(14, 165, 233, 0.15);
    --mm-accent2-30: rgba(14, 165, 233, 0.3);
    --mm-grad-a-30: rgba(26, 115, 232, 0.3);
    --mm-grad-a-40: rgba(26, 115, 232, 0.4);
    --mm-grad-b-30: rgba(14, 165, 233, 0.3);
    --mm-grad-b-40: rgba(14, 165, 233, 0.4);
    --mm-ring-25: rgba(26, 115, 232, 0.25);
    --mm-ring-30: rgba(26, 115, 232, 0.3);
    --mm-ring-40: rgba(26, 115, 232, 0.4);
    --mm-shadow-a30: rgba(26, 115, 232, 0.25);
    --mm-shadow-a40: rgba(26, 115, 232, 0.3);
    --mm-shadow-b20: rgba(14, 165, 233, 0.2);
    --mm-shadow-b25: rgba(14, 165, 233, 0.25);
    --mm-shadow-b30: rgba(14, 165, 233, 0.3);
    --mm-grid: rgba(26, 115, 232, 0.1);
    --mm-scroll: rgba(26, 115, 232, 0.3);
    --mm-scroll-hover: rgba(26, 115, 232, 0.55);
}
html[data-theme='red'] .mm-root {
    --mm-bg: #f9f9f9;
    --mm-panel: #ffffff;
    --mm-raised: #ffffff;
    --mm-header: rgba(255, 255, 255, 0.9);
    --mm-surface: rgba(15, 23, 42, 0.04);
    --mm-surface-2: rgba(15, 23, 42, 0.07);
    --mm-surface-3: rgba(15, 23, 42, 0.1);
    --mm-surface-4: rgba(15, 23, 42, 0.14);
    --mm-surface-5: rgba(15, 23, 42, 0.18);
    --mm-border: rgba(76, 5, 25, 0.08);
    --mm-border-2: rgba(76, 5, 25, 0.14);
    --mm-text: #4c0519;
    --mm-muted: #9b6b76;
    --mm-accent-soft: #e11d48;
    --mm-online: #22c55e;
    --mm-star: #f59e0b;
    --mm-danger: #dc2626;
    --mm-accent-2: #f43f5e;
    --mm-grad-a: #e11d48;
    --mm-grad-b: #fb7185;
    --mm-grad-nav-a: #e11d48;
    --mm-grad-nav-b: #9f1239;
    --mm-accent-15: rgba(225, 29, 72, 0.15);
    --mm-accent-20: rgba(225, 29, 72, 0.2);
    --mm-accent-25: rgba(225, 29, 72, 0.25);
    --mm-accent-60: rgba(225, 29, 72, 0.6);
    --mm-accent-glow: rgba(225, 29, 72, 0.2);
    --mm-accent2-10: rgba(251, 113, 133, 0.1);
    --mm-accent2-15: rgba(251, 113, 133, 0.15);
    --mm-accent2-30: rgba(251, 113, 133, 0.3);
    --mm-grad-a-30: rgba(225, 29, 72, 0.3);
    --mm-grad-a-40: rgba(225, 29, 72, 0.4);
    --mm-grad-b-30: rgba(251, 113, 133, 0.3);
    --mm-grad-b-40: rgba(251, 113, 133, 0.4);
    --mm-ring-25: rgba(225, 29, 72, 0.25);
    --mm-ring-30: rgba(225, 29, 72, 0.3);
    --mm-ring-40: rgba(225, 29, 72, 0.4);
    --mm-shadow-a30: rgba(225, 29, 72, 0.25);
    --mm-shadow-a40: rgba(225, 29, 72, 0.3);
    --mm-shadow-b20: rgba(251, 113, 133, 0.2);
    --mm-shadow-b25: rgba(251, 113, 133, 0.25);
    --mm-shadow-b30: rgba(251, 113, 133, 0.3);
    --mm-grid: rgba(225, 29, 72, 0.1);
    --mm-scroll: rgba(225, 29, 72, 0.3);
    --mm-scroll-hover: rgba(225, 29, 72, 0.55);
}
html[data-theme='green'] .mm-root {
    --mm-bg: #121212;
    --mm-panel: #191414;
    --mm-raised: #1f1f1f;
    --mm-header: rgba(25, 20, 20, 0.9);
    --mm-surface: rgba(255, 255, 255, 0.05);
    --mm-surface-2: rgba(255, 255, 255, 0.1);
    --mm-surface-3: rgba(255, 255, 255, 0.15);
    --mm-surface-4: rgba(255, 255, 255, 0.2);
    --mm-surface-5: rgba(255, 255, 255, 0.25);
    --mm-border: rgba(255, 255, 255, 0.06);
    --mm-border-2: rgba(255, 255, 255, 0.1);
    --mm-text: #ffffff;
    --mm-muted: #a7a7a7;
    --mm-accent-soft: #7ddfa0;
    --mm-online: #1db954;
    --mm-star: #fbbf24;
    --mm-danger: #fca5a5;
    --mm-accent-2: #1db954;
    --mm-grad-a: #1db954;
    --mm-grad-b: #40916c;
    --mm-grad-nav-a: #1db954;
    --mm-grad-nav-b: #168a3f;
    --mm-accent-15: rgba(29, 185, 84, 0.15);
    --mm-accent-20: rgba(29, 185, 84, 0.2);
    --mm-accent-25: rgba(29, 185, 84, 0.25);
    --mm-accent-60: rgba(29, 185, 84, 0.6);
    --mm-accent-glow: rgba(29, 185, 84, 0.25);
    --mm-accent2-10: rgba(64, 145, 108, 0.1);
    --mm-accent2-15: rgba(64, 145, 108, 0.15);
    --mm-accent2-30: rgba(64, 145, 108, 0.3);
    --mm-grad-a-30: rgba(29, 185, 84, 0.3);
    --mm-grad-a-40: rgba(29, 185, 84, 0.4);
    --mm-grad-b-30: rgba(64, 145, 108, 0.3);
    --mm-grad-b-40: rgba(64, 145, 108, 0.4);
    --mm-ring-25: rgba(29, 185, 84, 0.25);
    --mm-ring-30: rgba(29, 185, 84, 0.3);
    --mm-ring-40: rgba(29, 185, 84, 0.4);
    --mm-shadow-a30: rgba(29, 185, 84, 0.3);
    --mm-shadow-a40: rgba(29, 185, 84, 0.4);
    --mm-shadow-b20: rgba(64, 145, 108, 0.2);
    --mm-shadow-b25: rgba(64, 145, 108, 0.25);
    --mm-shadow-b30: rgba(64, 145, 108, 0.3);
    --mm-grid: rgba(29, 185, 84, 0.1);
    --mm-scroll: rgba(29, 185, 84, 0.3);
    --mm-scroll-hover: rgba(29, 185, 84, 0.55);
}
/* The ERP-wide themes repaint every `aside > div`; cancel that inside the
   messenger so its own panels/text colours win. */
html[data-theme] .mm-root aside > div {
    background: transparent !important;
    -webkit-backdrop-filter: none !important;
    backdrop-filter: none !important;
    border-color: var(--mm-border) !important;
    color: inherit !important;
}
</style>

<style scoped>
/* Faint dot-grid behind the conversation so bubbles feel lifted. */
.chat-canvas {
    background-image: radial-gradient(circle at 1px 1px, var(--mm-grid) 1px, transparent 0);
    background-size: 22px 22px;
}
/* Slim, unobtrusive scrollbars. */
.chat-canvas ::-webkit-scrollbar,
aside ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.chat-canvas ::-webkit-scrollbar-thumb,
aside ::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background-color: var(--mm-scroll);
}
.chat-canvas ::-webkit-scrollbar-thumb:hover,
aside ::-webkit-scrollbar-thumb:hover {
    background-color: var(--mm-scroll-hover);
}
.chat-canvas ::-webkit-scrollbar-track,
aside ::-webkit-scrollbar-track {
    background: transparent;
}
/* Emoji popover */
.pop-enter-active,
.pop-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.pop-enter-from,
.pop-leave-to {
    opacity: 0;
    transform: translateY(6px) scale(0.96);
}
</style>
