<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { usePage, Link, router, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import axios from 'axios';
import { useTheme, initTheme, THEMES } from '@/composables/useTheme';
import {
    MessageSquare, Megaphone, Bell, Settings, X, Send, Search,
    User, LogOut, Palette, Check, ChevronLeft, Plus, CheckCheck,
    Users, Paperclip, ImagePlus, LogOut as LeaveIcon, Trash2, Pencil,
    PanelLeft, ChevronRight, LifeBuoy, Info, KeyRound, Clock, Maximize2,
} from 'lucide-vue-next';
import { useSidebarToggle } from '@/Layouts/composables/useSidebarToggle';

const page = usePage();
const user = computed(() => page.props.auth.user);
const client = computed(() => page.props.auth.client);
const representative = computed(() => page.props.auth.representative || null);
const supplier = computed(() => page.props.auth.supplier || (page.props.auth.user?.business_name ? page.props.auth.user : null));
const applicantUser = computed(() => page.props.auth.applicant);
const agencyUser = computed(() => page.props.auth.agency);
const currentUrl = computed(() => page.url);

const isClient = computed(() => !!client.value);
const isRepresentative = computed(() => isClient.value && !!representative.value);
const clientPortal = computed(() => page.props.auth.client_portal || null);
// Any non-primary partner login — a delegated representative or the owner
// account — only sees the "My Profile" entry when the Profile page was granted
// to it. The primary is always unrestricted.
const canViewProfile = computed(() => {
    if (!isClient.value) return true;
    const portal = clientPortal.value;
    if (!portal || portal.is_primary || portal.access_pages === null) return true;
    return portal.access_pages.includes('profile');
});
const isSupplier = computed(() => !!supplier.value || currentUrl.value.startsWith('/supplier'));
const isApplicant = computed(() => !!applicantUser.value || currentUrl.value.startsWith('/applicant'));
const isAgency = computed(() => !!agencyUser.value || currentUrl.value.startsWith('/agency'));
const isInternal = computed(() => !!user.value && !isClient.value && !isSupplier.value && !isApplicant.value && !isAgency.value);
const isCEO = computed(() => user.value?.role === 'CEO');
const ceoUnread = computed(() => page.props.notifications_unread || 0);

const displayName = computed(() => {
    if (isSupplier.value) return supplier.value?.representative_name || supplier.value?.business_name || 'Vendor';
    if (isClient.value) return client.value?.company_name || 'Client';
    if (isApplicant.value) return applicantUser.value?.first_name ? `${applicantUser.value.first_name} ${applicantUser.value.last_name || ''}`.trim() : applicantUser.value?.email || 'Applicant';
    if (isAgency.value) return agencyUser.value?.name || agencyUser.value?.email || 'Agency';
    return user.value?.name || 'Account';
});
const displaySub = computed(() => {
    if (isSupplier.value) return supplier.value?.business_name || 'Supplier portal';
    if (isClient.value) return client.value?.email || 'Client portal';
    if (isApplicant.value) return applicantUser.value?.email || 'Applicant portal';
    if (isAgency.value) return agencyUser.value?.email || 'Agency portal';
    const bits = [user.value?.role, user.value?.position].filter(Boolean);
    return bits.join(' · ') || user.value?.email || '';
});
const profileHref = computed(() => {
    if (isClient.value) return route('client.profile.edit');
    if (isApplicant.value) return route('applicant.profile.index');
    if (isInternal.value) return route('profile.edit');
    return null;
});
const isEmployeePortal = computed(() => (currentUrl.value || '').startsWith('/dashboard/employee-ui'));
const attendanceHref = computed(() => {
    try {
        if (isEmployeePortal.value) return route('employee.ui.clock');
        if (isInternal.value) return route('hrm.workforce.my-attendance.index');
    } catch { return null; }
    return null;
});
const logoutHref = computed(() => {
    if (isClient.value) return route('client.logout');
    if (isSupplier.value) return route('supplier.logout');
    if (isApplicant.value) return route('applicant.logout');
    if (isAgency.value) return route('agency.logout');
    return route('logout');
});

// ─── Themes (class + data-theme strategy; persisted) ───────────────
// light/dark = fully working defaults; blue = Ice-Blue & Navy,
// red = Rose & Wine, green = Soft Sage & Eucalyptus — all ERP-wide,
// crafted from the IT-module base via data-theme CSS.
const { currentTheme, setTheme } = useTheme();

// Module marker: kept for potential per-module tweaks. The rich themes now
// apply ERP-wide (no longer gated on this), driven via html[data-theme].
const isITModule = computed(() => currentUrl.value.startsWith('/dashboard/it'));
const syncModuleScope = () => {
    try {
        if (isITModule.value) document.documentElement.dataset.module = 'it';
        else delete document.documentElement.dataset.module;
    } catch { /* noop */ }
};
watch(currentUrl, syncModuleScope);

// ─── Logout confirmation ────────────────────────────────────────
const showLogoutConfirm = ref(false);
const loggingOut = ref(false);
const logoutForm = ref(null);

const refreshCsrfToken = () => {
    csrfToken.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || csrfToken.value;
};

// Logout via fetch (instead of a raw native submit) so a stale CSRF token /
// expired session (HTTP 419) reloads into the true auth state instead of
// stranding the user on an error page while still logged in. Falls back to
// the native form submit if the request itself cannot be made (offline…).
const submitLogout = async () => {
    if (loggingOut.value) return;
    loggingOut.value = true;
    refreshCsrfToken();
    try {
        const res = await fetch(logoutHref.value, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken.value, 'Accept': 'text/html' },
            credentials: 'same-origin',
            redirect: 'follow',
        });
        if (res.ok) {
            showLogoutConfirm.value = false;
            window.location.href = '/';
            return;
        }
        if (res.status === 419) {
            window.location.reload();
            return;
        }
        throw new Error(`Logout failed with status ${res.status}`);
    } catch {
        // Last resort: plain native submit (previous behavior).
        logoutForm.value?.submit();
    } finally {
        loggingOut.value = false;
    }
};

// ─── Change password (partner portal) ───────────────────────────────
// Always reachable for signed-in client users — including representatives
// whose Profile page has been removed from their granted pages.
const showPasswordModal = ref(false);
const pwForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const openPasswordModal = () => {
    pwForm.reset();
    pwForm.clearErrors();
    showPasswordModal.value = true;
    closePanels();
};
const closePasswordModal = () => {
    showPasswordModal.value = false;
    pwForm.reset();
    pwForm.clearErrors();
};
const submitPassword = () => {
    pwForm.post(route('client.password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            closePasswordModal();
            showToastMsg('Password updated successfully.');
        },
    });
};

// Lightweight transient toast (password-change confirmation).
const toastMsg = ref('');
const showToastMsg = (msg) => {
    toastMsg.value = msg;
    setTimeout(() => { if (toastMsg.value === msg) toastMsg.value = ''; }, 4000);
};

// ─── Panel plumbing ─────────────────────────────────────────────────
const openPanel = ref(null); // messenger | memo | notif | settings
const toggle = (name) => { openPanel.value = openPanel.value === name ? null : name; };
const closePanels = () => { openPanel.value = null; };

// ─── Polling (internal accounts only) ───────────────────────────────
const counts = ref({ notifications_unread: 0, messages_unread: 0, memos_count: 0 });
let pollTimer = null;
const fetchSummary = async () => {
    if (!isInternal.value) return;
    try {
        const { data } = await axios.get(route('topbar.summary'));
        counts.value = data;
    } catch { /* offline — badges keep last values */ }
};

// ─── Notifications ──────────────────────────────────────────────────
const notifs = ref([]);
const notifsUnread = ref(0);
const loadNotifs = async () => {
    try {
        const { data } = await axios.get(route('topbar.notifications'));
        notifs.value = data.items || [];
        notifsUnread.value = data.unread || 0;
        counts.value.notifications_unread = data.unread || 0;
    } catch { /* noop */ }
};
const openNotif = async (n) => {
    try { await axios.patch(route('topbar.notifications.read', n.id)); } catch { /* noop */ }
    n.is_read = true;
    notifsUnread.value = Math.max(0, notifsUnread.value - 1);
    counts.value.notifications_unread = notifsUnread.value;
    // Memo alerts deep-link straight to the memo itself.
    if (n.memo_id) {
        openPanel.value = 'memo';
        await loadMemos();
        memoOpen.value = n.memo_id;
        memoFlash.value = n.memo_id;
        setTimeout(() => { if (memoFlash.value === n.memo_id) memoFlash.value = null; }, 2500);
        nextTick(() => {
            document.querySelector(`[data-memo-id="${n.memo_id}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
        return;
    }
    if (n.link_route) {
        closePanels();
        try { router.visit(route(n.link_route)); } catch { /* unknown route — stay */ }
    }
};
const readAllNotifs = async () => {
    try { await axios.post(route('topbar.notifications.read-all')); } catch { /* noop */ }
    notifs.value.forEach((n) => { n.is_read = true; });
    notifsUnread.value = 0;
    counts.value.notifications_unread = 0;
};

// ─── Agency notifications (bell next to Settings) ───────────────
// Source: agency_activities for own agency. Read state is client-side
// (localStorage last-seen id) so no new columns are needed.
const agencyNotifs = ref([]);
const agencyNotifsUnread = ref(0);
const agencyNotifLoading = ref(false);
const loadAgencyNotifs = async () => {
    if (!isAgency.value) return;
    agencyNotifLoading.value = true;
    try {
        const { data } = await axios.get(route('agency.notifications.index'));
        agencyNotifs.value = data.items || [];
        const lastSeen = Number(localStorage.getItem('agency_notif_seen') || 0);
        if (!lastSeen && data.unread) {
            agencyNotifsUnread.value = data.unread || 0;
        } else {
            const maxId = agencyNotifs.value.length ? Math.max(...agencyNotifs.value.map((n) => Number(n.id) || 0)) : 0;
            agencyNotifsUnread.value = agencyNotifs.value.filter((n) => Number(n.id) > lastSeen).length;
            if (!lastSeen && maxId) localStorage.setItem('agency_notif_seen', String(maxId));
        }
    } catch { /* offline — keep last list */ }
    agencyNotifLoading.value = false;
};
const openAgencyNotifPanel = async () => {
    if (openPanel.value === 'agency_notif') { openPanel.value = null; return; }
    openPanel.value = 'agency_notif';
    await loadAgencyNotifs();
};
const readAllAgencyNotifs = () => {
    const maxId = agencyNotifs.value.length ? Math.max(...agencyNotifs.value.map((n) => Number(n.id) || 0)) : 0;
    if (maxId) localStorage.setItem('agency_notif_seen', String(maxId));
    agencyNotifsUnread.value = 0;
};

// ─── Applicant notifications (bell next to Settings) ───────────────
// Source: applicant_notifications for the logged-in applicant. Read
// state is server-side; clicking an item opens the full Notifications
// page where the detail modal lives.
const applicantNotifs = ref([]);
const applicantNotifsUnread = ref(0);
const applicantNotifLoading = ref(false);
const loadApplicantNotifs = async () => {
    if (!isApplicant.value) return;
    applicantNotifLoading.value = true;
    try {
        const { data } = await axios.get(route('applicant.notifications.tab', 'all'));
        applicantNotifs.value = (data.data || []).slice(0, 12);
    } catch { /* offline — keep last list */ }
    applicantNotifLoading.value = false;
    loadApplicantNotifCount();
};
const loadApplicantNotifCount = async () => {
    if (!isApplicant.value) return;
    try {
        const { data } = await axios.get(route('applicant.notifications.count'));
        applicantNotifsUnread.value = data.count || 0;
    } catch { /* noop */ }
};
const openApplicantNotifPanel = async () => {
    if (openPanel.value === 'applicant_notif') { openPanel.value = null; return; }
    openPanel.value = 'applicant_notif';
    await loadApplicantNotifs();
};
const openApplicantNotif = async (n) => {
    if (!n.read_at) {
        try { await axios.post(route('applicant.notifications.mark-read', n.id)); } catch { /* noop */ }
        n.read_at = new Date().toISOString();
        applicantNotifsUnread.value = Math.max(0, applicantNotifsUnread.value - 1);
    }
    closePanels();
    router.visit(route('applicant.notifications.index'));
};
const readAllApplicantNotifs = async () => {
    try { await axios.post(route('applicant.notifications.mark-all-read')); } catch { /* noop */ }
    applicantNotifs.value.forEach((n) => { n.read_at = new Date().toISOString(); });
    applicantNotifsUnread.value = 0;
};

// ─── Memos ──────────────────────────────────────────────────────────
const memos = ref([]);
const memoOpen = ref(null);
const memoFlash = ref(null);
const loadMemos = async () => {
    try {
        const { data } = await axios.get(route('topbar.memos'));
        memos.value = data.items || [];
        counts.value.memos_count = memos.value.length;
    } catch { /* noop */ }
};

// ─── Messenger ──────────────────────────────────────────────────────
const threads = ref([]);
// List preview: image/file-only messages carry an empty body, so show a
// 📷/📎 label instead of "No messages yet" (backend already sends the
// label; this is the cached/legacy fallback).
const previewText = (t) => {
    const p = (t?.preview ?? '').trim();
    if (p) return p;
    return 'No messages yet';
};
const activeThread = ref(null);
const messages = ref([]);
const draft = ref('');
const sending = ref(false);
const composing = ref(false);
const dirQuery = ref('');
const directory = ref([]);
const picked = ref([]);
const newTitle = ref('');
const newBody = ref('');
const creating = ref(false);
const msgBox = ref(null);

// Image lightbox (in-chat viewer — clicking a sent image opens it here,
// never a new page).
const lightbox = ref(null);
const openLightbox = (url) => { lightbox.value = url; };
const closeLightbox = () => { lightbox.value = null; };

const loadThreads = async () => {
    try {
        const { data } = await axios.get(route('topbar.threads'));
        threads.value = data.threads || [];
        counts.value.messages_unread = threads.value.reduce((n, t) => n + (t.unread || 0), 0);
    } catch { /* noop */ }
};
const scrollChat = (force = false) => nextTick(() => {
    if (!msgBox.value) return;
    if (force || isNearBottom()) msgBox.value.scrollTop = msgBox.value.scrollHeight;
});
const isNearBottom = () => {
    if (!msgBox.value) return true;
    const el = msgBox.value;
    return el.scrollHeight - el.scrollTop - el.clientHeight < 120;
};
const openThread = async (t) => {
    activeThread.value = t;
    messages.value = [];
    msgView.value = 'chat';
    try {
        const { data } = await axios.get(route('topbar.threads.show', t.id));
        // Stale response guard: user may have switched threads mid-flight.
        if (!activeThread.value || Number(activeThread.value.id) !== Number(t.id)) return;
        messages.value = data.messages || [];
        activeThread.value = { ...t, ...(data.thread || {}) };
        scrollChat(true);
        loadThreads();
    } catch { /* noop */ }
};

// Silent background refresh: appends only new messages, never touches the
// current view (settings stay open), never yanks scroll unless the user is
// already at the bottom. This is what makes the chat feel live.
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
        // Merge meta (title/photo/members) without touching drafts or view.
        activeThread.value = { ...activeThread.value, ...(data.thread || {}) };
        loadThreads();
    } catch { /* noop */ }
    polling = false;
};
const backToThreads = () => { activeThread.value = null; messages.value = []; msgView.value = 'chat'; loadThreads(); };
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
        loadThreads();
    } catch { /* noop */ }
    sending.value = false;
};

// ─── Attachments ────────────────────────────────────────────────
const attachInput = ref(null);
const attachFile = ref(null);
const attachUrl = ref(null);
const pickAttach = () => attachInput.value?.click();
const onAttach = (e) => {
    const f = (e.target.files || [])[0] || null;
    if (!f) return;
    if (f.size > 10 * 1024 * 1024) return;
    attachFile.value = f;
    attachUrl.value = f.type.startsWith('image/') ? URL.createObjectURL(f) : null;
};
const clearAttach = () => {
    attachFile.value = null;
    if (attachUrl.value) URL.revokeObjectURL(attachUrl.value);
    attachUrl.value = null;
    if (attachInput.value) attachInput.value.value = '';
};

// ─── Group settings ─────────────────────────────────────────────
const msgView = ref('chat'); // chat | settings
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
const refreshThread = async () => {
    if (!activeThread.value) return;
    try {
        const { data } = await axios.get(route('topbar.threads.show', activeThread.value.id));
        messages.value = data.messages || [];
        activeThread.value = { ...activeThread.value, ...data.thread };
        groupName.value = data.thread?.title || '';
    } catch { /* noop */ }
};
// ─── Group change confirmations ───────────────────────────────────
// Every mutating group action (rename, photo, remove, leave) asks first.
const confirmBox = ref(null); // { title, message, confirmLabel, danger, run }
const askConfirm = (cfg) => { confirmBox.value = { danger: false, ...cfg }; };
const closeConfirm = () => { confirmBox.value = null; };
const pendingPhoto = ref(null);
const pendingPhotoUrl = ref(null);

const saveGroupName = () => {
    const name = groupName.value.trim();
    if (!name || groupBusy.value || name === (activeThread.value?.title || '')) return;
    askConfirm({
        title: 'Rename group?',
        message: `Change the group name to “${name}”?`,
        confirmLabel: 'Rename',
        run: async () => {
            groupBusy.value = true;
            groupError.value = '';
            try {
                const { data } = await axios.patch(route('topbar.threads.update', activeThread.value.id), { title: name });
                activeThread.value = { ...activeThread.value, title: data.title };
                loadThreads();
            } catch (e) { groupError.value = e?.response?.data?.message || 'Could not rename group.'; }
            groupBusy.value = false;
        },
    });
};
const changePhoto = (e) => {
    const f = (e.target.files || [])[0] || null;
    if (photoInput.value) photoInput.value.value = '';
    if (!f || groupBusy.value) return;
    if (pendingPhotoUrl.value) URL.revokeObjectURL(pendingPhotoUrl.value);
    pendingPhoto.value = f;
    pendingPhotoUrl.value = f.type.startsWith('image/') ? URL.createObjectURL(f) : null;
    askConfirm({
        title: 'Change group photo?',
        message: `Use “${f.name}” as the new group photo?`,
        confirmLabel: 'Change photo',
        run: async () => {
            groupBusy.value = true;
            groupError.value = '';
            try {
                // NOTE: browsers/PHP can't send multipart on PATCH, so spoof it.
                const fd = new FormData();
                fd.append('_method', 'PATCH');
                fd.append('photo', pendingPhoto.value);
                await axios.post(route('topbar.threads.update', activeThread.value.id), fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                await refreshThread();
                loadThreads();
            } catch { groupError.value = 'Could not update group photo.'; }
            groupBusy.value = false;
            pendingPhoto.value = null;
            if (pendingPhotoUrl.value) URL.revokeObjectURL(pendingPhotoUrl.value);
            pendingPhotoUrl.value = null;
        },
    });
};
const removePhoto = () => {
    if (groupBusy.value) return;
    askConfirm({
        title: 'Remove group photo?',
        message: 'The group will go back to its default avatar. Continue?',
        confirmLabel: 'Remove',
        danger: true,
        run: async () => {
            groupBusy.value = true;
            try {
                await axios.patch(route('topbar.threads.update', activeThread.value.id), { remove_photo: true });
                await refreshThread();
                loadThreads();
            } catch { /* noop */ }
            groupBusy.value = false;
        },
    });
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
const addMember = (u) => {
    if (addingId.value) return;
    askConfirm({
        title: `Add ${u.name}?`,
        message: `${u.name} (${u.role || 'employee'}) will join this group and see new messages from here on. Continue?`,
        confirmLabel: 'Add',
        run: async () => {
            addingId.value = u.id;
            groupError.value = '';
            try {
                await axios.post(route('topbar.threads.members.add', activeThread.value.id), { user_ids: [u.id] });
                await refreshThread();
                loadThreads();
                searchAdd();
            } catch (e) { groupError.value = e?.response?.data?.message || 'Could not add employee.'; }
            addingId.value = null;
        },
    });
};
const removeMember = (u) => {
    if (groupBusy.value) return;
    askConfirm({
        title: `Remove ${u.name}?`,
        message: `${u.name} will no longer see new messages in this group. Continue?`,
        confirmLabel: 'Remove',
        danger: true,
        run: async () => {
            groupBusy.value = true;
            groupError.value = '';
            try {
                await axios.delete(route('topbar.threads.members.remove', [activeThread.value.id, u.id]));
                await refreshThread();
                loadThreads();
            } catch (e) { groupError.value = e?.response?.data?.message || 'Could not remove employee.'; }
            groupBusy.value = false;
        },
    });
};
const leaveGroup = () => {
    if (groupBusy.value) return;
    askConfirm({
        title: 'Leave this group?',
        message: 'You will stop receiving its messages. If you are the last member, the group closes permanently. Continue?',
        confirmLabel: 'Leave group',
        danger: true,
        run: async () => {
            groupBusy.value = true;
            try {
                await axios.post(route('topbar.threads.leave', activeThread.value.id));
                backToThreads();
            } catch (e) { groupError.value = e?.response?.data?.message || 'Could not leave group.'; }
            groupBusy.value = false;
        },
    });
};
const searchDirectory = async () => {
    try {
        const { data } = await axios.get(route('topbar.directory'));
        directory.value = data.users || [];
    } catch { /* noop */ }
};
const filteredDir = computed(() => {
    const q = dirQuery.value.trim().toLowerCase();
    if (!q) return directory.value.slice(0, 30);
    return directory.value.filter((u) =>
        (u.name || '').toLowerCase().includes(q) ||
        (u.email || '').toLowerCase().includes(q) ||
        (u.role || '').toLowerCase().includes(q)).slice(0, 30);
});
const togglePick = (u) => {
    const i = picked.value.findIndex((p) => p.id === u.id);
    if (i > -1) picked.value.splice(i, 1);
    else if (picked.value.length < 8) picked.value.push(u);
};
const startCompose = () => {
    composing.value = true;
    picked.value = [];
    newTitle.value = '';
    newBody.value = '';
    dirQuery.value = '';
    searchDirectory();
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
        await loadThreads();
        const t = threads.value.find((x) => Number(x.id) === Number(data.thread_id));
        if (t) openThread(t);
    } catch { /* noop */ }
    creating.value = false;
};

// ─── Lifecycle ──────────────────────────────────────────────────────
let msgTimer = null;
const csrfToken = ref('');
onMounted(() => {
    csrfToken.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    initTheme();
    syncModuleScope();
    if (isInternal.value) {
        fetchSummary();
        pollTimer = setInterval(fetchSummary, 30000);
    }
    if (isAgency.value) {
        loadAgencyNotifs();
        pollTimer = setInterval(loadAgencyNotifs, 30000);
    }
    if (isApplicant.value) {
        loadApplicantNotifCount();
        pollTimer = setInterval(loadApplicantNotifCount, 30000);
    }
});
onBeforeUnmount(() => {
    if (clockTimer) clearInterval(clockTimer);
    if (pollTimer) clearInterval(pollTimer);
    if (msgTimer) clearInterval(msgTimer);
});
watch(openPanel, (panel) => {
    if (msgTimer) { clearInterval(msgTimer); msgTimer = null; }
    if (panel === 'agency_notif' && isAgency.value) { loadAgencyNotifs(); return; }
    if (panel === 'applicant_notif' && isApplicant.value) { loadApplicantNotifs(); return; }
    if (!isInternal.value) return;
    if (panel === 'notif') loadNotifs();
    if (panel === 'memo') loadMemos();
    if (panel === 'messenger') {
        loadThreads();
        // Near-real-time feel without sockets: fast silent polls that only
        // append new messages. Settings edits and scroll position are safe.
        msgTimer = setInterval(() => {
            if (activeThread.value) pollActive();
            else loadThreads();
        }, 2500);
    }
});

const fmtTime = (d) => d ? new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

// ─── Sidebar toggle + breadcrumbs (replaces the old avatar tile) ─────────
const { toggleSidebar } = useSidebarToggle();

const CRUMB_LABELS = {
    dashboard: 'Dashboard', crm: 'CRM', eco: 'ECO', inquiries: 'Inquiries',
    it: 'IT & Systems', tickets: 'Service Desk',
    assets: 'Assets', monitoring: 'Monitoring', knowledge: 'Knowledge Base',
    location: 'Geolocation', changes: 'Changes', 'access-control': 'Access Control',
    'access-logs': 'Access Logs', access: 'Access', hrm: 'HRM', crm: 'CRM',
    scm: 'SCM', fin: 'Finance', eco: 'Ecology', ord: 'Orders', log: 'Logistics',
    logistics: 'Logistics', man: 'Manufacturing', pro: 'Production', inv: 'Inventory',
    inventory: 'Inventory', war: 'Warehouse', warehouse: 'Warehouse', wrf: 'Workforce',
    workforce: 'Workforce', apl: 'Applicants', applicants: 'Applicants',
    'employee-ui': 'Employee', employee: 'Employee', ceo: 'CEO', coo: 'COO',
    vp: 'VP', secretary: 'Secretary', supplier: 'Supplier', client: 'Client',
    applicant: 'Applicant', profile: 'Profile', edit: 'Edit', create: 'Create',
    show: 'Details',
};
const humanizeSeg = (s) => s.replace(/[-_]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const breadcrumbs = computed(() => {
    const path = (currentUrl.value || '/').split('?')[0].split('#')[0];
    const crumbs = [];
    let href = '';
    for (const seg of path.split('/').filter(Boolean)) {
        href += `/${seg}`;
        if (/^\d+$/.test(seg)) continue; // skip bare IDs, keep path for links
        crumbs.push({ label: CRUMB_LABELS[seg.toLowerCase()] || humanizeSeg(seg), href });
    }
    return crumbs;
});

// Personal chats show the other employee's photo; groups show the group photo.
const threadAvatar = (t) => {
    if (!t) return null;
    if (t.photo) return t.photo;
    if (t.kind !== 'group') {
        const other = (t.participants || []).find((p) => Number(p.id) !== Number(user.value?.id));
        return other?.photo || null;
    }
    return null;
};
const initialOf = (name) => ((name || '?').charAt(0) || '?').toUpperCase();

// ─── Live clock ───────────────────────────────────────────────────
const now = ref(new Date());
let clockTimer = null;
const clockTime = computed(() => now.value.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }));
const clockDate = computed(() => now.value.toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }));
onMounted(() => {
    clockTimer = setInterval(() => { now.value = new Date(); }, 1000);
});
</script>

<template>
    <header class="sticky top-16 md:top-0 z-40 border-b border-gray-200/70 bg-white/90 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/90">
        <div class="topbar-row mx-auto flex h-16 max-w-7xl items-center gap-2 px-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <!-- Sidebar toggle (drawer on mobile, collapse on desktop) -->
                <button @click="toggleSidebar" title="Toggle sidebar"
                    class="shrink-0 rounded-xl p-2.5 text-gray-500 transition hover:bg-gray-100 hover:text-indigo-600 active:scale-95 dark:text-gray-400 dark:hover:bg-zinc-800">
                    <PanelLeft class="h-5 w-5" />
                </button>
                <!-- Breadcrumbs -->
                <nav class="flex min-w-0 flex-1 items-center gap-1 text-sm" aria-label="Breadcrumb">
                    <template v-for="(c, i) in breadcrumbs" :key="c.href + i">
                        <span :class="['flex min-w-0 items-center gap-1', i < breadcrumbs.length - 1 ? 'hidden md:flex' : 'flex']">
                            <ChevronRight class="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-zinc-600" />
                            <Link v-if="i < breadcrumbs.length - 1" :href="c.href"
                                class="truncate whitespace-nowrap text-[13px] font-semibold text-gray-500 transition hover:text-indigo-600 dark:text-gray-400">
                                {{ c.label }}
                            </Link>
                            <span v-else class="truncate text-[13px] font-bold text-gray-900 dark:text-white">
                                {{ c.label }}
                            </span>
                        </span>
                    </template>
                </nav>
                <!-- Live date & time -->
                <div class="hidden min-w-0 flex-1 items-center justify-center sm:flex" title="Server time">
                    <div class="flex items-center gap-2.5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                        <span class="font-mono text-sm font-black tabular-nums text-gray-800 dark:text-zinc-100">{{ clockTime }}</span>
                        <span class="hidden text-[11px] font-bold uppercase tracking-widest text-gray-400 lg:inline">{{ clockDate }}</span>
                    </div>
                </div>
                <div class="flex min-w-0 flex-1 items-center justify-center sm:hidden" title="Server time">
                    <span class="font-mono text-sm font-black tabular-nums text-gray-800 dark:text-zinc-100">{{ clockTime }}</span>
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <!-- Messenger (internal only) -->
                <button v-if="isInternal" @click="toggle('messenger')" title="Messenger"
                    :class="['relative rounded-xl p-2.5 transition active:scale-95', openPanel === 'messenger' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'text-gray-500 hover:bg-gray-100 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-zinc-800']">
                    <MessageSquare class="h-5 w-5" />
                    <span v-if="counts.messages_unread > 0" class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-black text-white ring-2 ring-white dark:ring-zinc-900">{{ counts.messages_unread > 99 ? '99+' : counts.messages_unread }}</span>
                </button>
                <!-- Memos (internal only) -->
                <button v-if="isInternal" @click="toggle('memo')" title="Memos"
                    :class="['relative rounded-xl p-2.5 transition active:scale-95', openPanel === 'memo' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'text-gray-500 hover:bg-gray-100 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-zinc-800']">
                    <Megaphone class="h-5 w-5" />
                </button>
                <!-- Notifications (internal only) -->
                <button v-if="isInternal" @click="toggle('notif')" title="Notifications"
                    :class="['relative rounded-xl p-2.5 transition active:scale-95', openPanel === 'notif' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'text-gray-500 hover:bg-gray-100 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-zinc-800']">
                    <Bell class="h-5 w-5" />
                    <span v-if="counts.notifications_unread > 0" class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-black text-white ring-2 ring-white dark:ring-zinc-900">{{ counts.notifications_unread > 99 ? '99+' : counts.notifications_unread }}</span>
                </button>
                <!-- Agency notifications (agency only, left of Settings) -->
                <button v-if="isAgency" @click="openAgencyNotifPanel" title="Notifications"
                    :class="['relative rounded-xl p-2.5 transition active:scale-95', openPanel === 'agency_notif' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'text-gray-500 hover:bg-gray-100 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-zinc-800']">
                    <Bell class="h-5 w-5" />
                    <span v-if="agencyNotifsUnread > 0" class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-black text-white ring-2 ring-white dark:ring-zinc-900">{{ agencyNotifsUnread > 99 ? '99+' : agencyNotifsUnread }}</span>
                </button>
                <!-- Applicant notifications (applicant only, left of Settings) -->
                <button v-if="isApplicant" @click="openApplicantNotifPanel" title="Notifications"
                    :class="['relative rounded-xl p-2.5 transition active:scale-95', openPanel === 'applicant_notif' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'text-gray-500 hover:bg-gray-100 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-zinc-800']">
                    <Bell class="h-5 w-5" />
                    <span v-if="applicantNotifsUnread > 0" class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-black text-white ring-2 ring-white dark:ring-zinc-900">{{ applicantNotifsUnread > 99 ? '99+' : applicantNotifsUnread }}</span>
                </button>
                <!-- Settings -->
                <button @click="toggle('settings')" title="Settings"
                    :class="['rounded-xl p-2.5 transition active:scale-95', openPanel === 'settings' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'text-gray-500 hover:bg-gray-100 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-zinc-800']">
                    <Settings class="h-5 w-5" />
                </button>
            </div>
        </div>

        <!-- ═══ Dropdown panels ═══ -->
        <div v-if="openPanel" class="topbar-panels absolute inset-x-0 top-16 flex justify-end px-4 sm:px-6 lg:px-8">
            <div class="topbar-panel w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900">

                <!-- SETTINGS -->
                <div v-if="openPanel === 'settings'">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                        <!-- Partner portal: show exactly which business account is
                             being accessed (name + email), plus the signed-in
                             representative when the owner delegated access. -->
                        <div v-if="isClient" class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-violet-700 text-sm font-black text-white">
                                {{ (displayName || '?').charAt(0).toUpperCase() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Business account</p>
                                <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ displayName }}</p>
                                <p class="truncate text-[11px] font-bold text-indigo-500">{{ displaySub }}</p>
                            </div>
                        </div>
                        <div v-else>
                            <p class="text-sm font-black text-gray-900 dark:text-white">{{ displayName }}</p>
                            <p class="text-[11px] font-bold uppercase tracking-widest text-gray-400">{{ displaySub }}</p>
                        </div>
                        <div v-if="isRepresentative" class="mt-3 rounded-xl bg-gray-50 px-3 py-2.5 dark:bg-zinc-800/60">
                            <p class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                Signed in as
                                <span v-if="representative.is_primary" class="rounded-full bg-indigo-600 px-1.5 py-0.5 text-[8px] font-black uppercase tracking-normal text-white">Primary</span>
                            </p>
                            <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ representative.name }}</p>
                            <p class="truncate text-[11px] font-bold text-gray-400">{{ representative.email }}</p>
                        </div>
                    </div>
                    <div class="space-y-1 p-3">
                        <Link v-if="profileHref && canViewProfile" :href="profileHref" @click="closePanels"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-zinc-800">
                            <User class="h-4 w-4 text-indigo-500" /> My Profile
                        </Link>
                        <Link v-if="attendanceHref" :href="attendanceHref" @click="closePanels"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-zinc-800">
                            <Clock class="h-4 w-4 text-indigo-500" /> My Attendance
                        </Link>
                        <!-- Help Center + About (internal accounts; portal users keep Profile/Theme/Logout) -->
                        <Link v-if="isInternal" :href="route('help.index')" @click="closePanels"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-zinc-800">
                            <LifeBuoy class="h-4 w-4 text-indigo-500" /> Help Center
                        </Link>
                        <Link v-if="isInternal" :href="route('about.index')" @click="closePanels"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-zinc-800">
                            <Info class="h-4 w-4 text-indigo-500" /> About
                        </Link>
                        <!-- THEME picker: Light / Dark (fully working) + Blue / Red / Green -->
                        <div class="rounded-xl px-3 py-2.5">
                            <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                <Palette class="h-3.5 w-3.5" /> Theme
                            </p>
                            <div class="mt-2 space-y-1">
                                <button v-for="t in THEMES" :key="t.key" @click="setTheme(t.key)"
                                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-bold transition hover:bg-gray-100 dark:hover:bg-zinc-800"
                                    :class="currentTheme === t.key ? 'text-gray-900 dark:text-white ring-1 ring-inset ring-indigo-500/40 bg-indigo-50/60 dark:bg-indigo-900/20' : 'text-gray-700 dark:text-gray-200'">
                                    <span class="h-4 w-4 shrink-0 rounded-full ring-1 ring-inset ring-black/10" :style="{ backgroundColor: t.dot }" />
                                    <span class="min-w-0 flex-1 text-left">
                                        <span class="block leading-tight">{{ t.label }} theme</span>
                                        <span class="block text-[10px] font-semibold text-gray-400">{{ t.desc }}</span>
                                    </span>
                                    <Check v-if="currentTheme === t.key" class="h-4 w-4 shrink-0 text-indigo-600" />
                                </button>
                            </div>
                            <p class="mt-2 text-[10px] font-semibold text-gray-400">Applies across the whole ERP system.</p>
                        </div>
                        <button v-if="isClient" @click="openPasswordModal"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-zinc-800">
                            <KeyRound class="h-4 w-4 text-indigo-500" /> Change Password
                        </button>
                        <button @click="showLogoutConfirm = true"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20">
                            <LogOut class="h-4 w-4" /> Log Out
                        </button>
                    </div>
                </div>

                <!-- NOTIFICATIONS -->
                <div v-if="openPanel === 'notif'">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 dark:border-zinc-800">
                        <p class="text-sm font-black text-gray-900 dark:text-white">Notifications</p>
                        <div class="flex items-center gap-2">
                            <button @click="readAllNotifs" class="inline-flex items-center gap-1 text-[11px] font-black uppercase text-indigo-600 hover:underline">
                                <CheckCheck class="h-3.5 w-3.5" /> Read all
                            </button>
                            <button @click="closePanels" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
                        </div>
                    </div>
                    <Link v-if="isCEO" :href="route('ceo.inbox')" @click="closePanels"
                        class="flex items-center justify-between bg-indigo-50 px-5 py-3 text-xs font-black uppercase tracking-wide text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-300">
                        <span>Executive inbox</span>
                        <span class="rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] text-white">{{ ceoUnread }} unread</span>
                    </Link>
                    <div class="max-h-96 overflow-y-auto">
                        <button v-for="n in notifs" :key="n.id" @click="openNotif(n)"
                            :class="['flex w-full items-start gap-3 border-b border-gray-50 px-5 py-3.5 text-left transition last:border-0 hover:bg-gray-50 dark:border-zinc-800/60 dark:hover:bg-zinc-800/60', !n.is_read && 'bg-indigo-50/50 dark:bg-indigo-900/10']">
                            <span :class="['mt-1.5 h-2 w-2 shrink-0 rounded-full', n.is_read ? 'bg-gray-300 dark:bg-zinc-600' : 'bg-indigo-500 animate-pulse']" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-black text-gray-900 dark:text-white">{{ n.title }}</span>
                                <span v-if="n.body" class="block truncate text-xs text-gray-500">{{ n.body }}</span>
                                <span class="mt-0.5 block text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ n.type }} · {{ fmtTime(n.created_at) }}</span>
                            </span>
                        </button>
                        <p v-if="!notifs.length" class="px-5 py-10 text-center text-xs font-bold text-gray-400">No notifications yet.</p>
                    </div>
                </div>

                <!-- AGENCY NOTIFICATIONS -->
                <div v-if="openPanel === 'agency_notif'">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 dark:border-zinc-800">
                        <p class="text-sm font-black text-gray-900 dark:text-white">Notifications</p>
                        <div class="flex items-center gap-2">
                            <button @click="readAllAgencyNotifs" class="inline-flex items-center gap-1 text-[11px] font-black uppercase text-indigo-600 hover:underline">
                                <CheckCheck class="h-3.5 w-3.5" /> Read all
                            </button>
                            <button @click="closePanels" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
                        </div>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <div v-for="n in agencyNotifs" :key="n.id"
                            class="flex w-full items-start gap-3 border-b border-gray-50 px-5 py-3.5 text-left last:border-0 dark:border-zinc-800/60">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-black text-gray-900 dark:text-white">{{ n.title }}</span>
                                <span v-if="n.details" class="block truncate text-xs text-gray-500">{{ n.details }}</span>
                                <span v-if="n.reason" class="mt-0.5 block truncate text-xs italic text-orange-500">“{{ n.reason }}”</span>
                                <span class="mt-0.5 block text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ n.worker_code || n.request_code || n.action }} · {{ n.date }}</span>
                            </span>
                        </div>
                        <p v-if="agencyNotifLoading" class="px-5 py-6 text-center text-xs font-bold text-gray-400">Loading…</p>
                        <p v-else-if="!agencyNotifs.length" class="px-5 py-10 text-center text-xs font-bold text-gray-400">No notifications yet.</p>
                    </div>
                </div>

                <!-- APPLICANT NOTIFICATIONS -->
                <div v-if="openPanel === 'applicant_notif'">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 dark:border-zinc-800">
                        <p class="text-sm font-black text-gray-900 dark:text-white">Notifications</p>
                        <div class="flex items-center gap-2">
                            <button v-if="applicantNotifsUnread > 0" @click="readAllApplicantNotifs" class="inline-flex items-center gap-1 text-[11px] font-black uppercase text-indigo-600 hover:underline">
                                <CheckCheck class="h-3.5 w-3.5" /> Read all
                            </button>
                            <button @click="closePanels" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
                        </div>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <button v-for="n in applicantNotifs" :key="n.id" @click="openApplicantNotif(n)"
                            :class="['flex w-full items-start gap-3 border-b border-gray-50 px-5 py-3.5 text-left transition last:border-0 hover:bg-gray-50 dark:border-zinc-800/60 dark:hover:bg-zinc-800/60', !n.read_at && 'bg-indigo-50/50 dark:bg-indigo-900/10']">
                            <span :class="['mt-1.5 h-2 w-2 shrink-0 rounded-full', n.read_at ? 'bg-gray-300 dark:bg-zinc-600' : 'bg-indigo-500 animate-pulse']" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-black text-gray-900 dark:text-white">{{ n.title }}</span>
                                <span v-if="n.message" class="block truncate text-xs text-gray-500">{{ n.message }}</span>
                                <span class="mt-0.5 block text-[10px] font-bold uppercase tracking-widest text-gray-400">{{ n.type }} · {{ fmtTime(n.created_at) }}</span>
                            </span>
                        </button>
                        <p v-if="applicantNotifLoading" class="px-5 py-6 text-center text-xs font-bold text-gray-400">Loading…</p>
                        <p v-else-if="!applicantNotifs.length" class="px-5 py-10 text-center text-xs font-bold text-gray-400">No notifications yet.</p>
                    </div>
                    <Link :href="route('applicant.notifications.index')" @click="closePanels"
                        class="flex items-center justify-center gap-2 border-t border-gray-100 px-5 py-3 text-xs font-black uppercase tracking-wide text-indigo-600 hover:bg-indigo-50 dark:border-zinc-800 dark:hover:bg-indigo-900/20">
                        View all notifications
                    </Link>
                </div>

                <!-- MEMOS -->
                <div v-if="openPanel === 'memo'">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 dark:border-zinc-800">
                        <p class="text-sm font-black text-gray-900 dark:text-white">Company Memos</p>
                        <button @click="closePanels" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <div v-for="m in memos" :key="m.id" :data-memo-id="m.id"
                            :class="['border-b border-gray-50 px-5 py-3.5 last:border-0 transition dark:border-zinc-800/60', memoFlash === m.id && 'bg-indigo-50 dark:bg-indigo-900/20 ring-2 ring-inset ring-indigo-400']">
                            <div class="flex items-center gap-2">
                                <span :class="['rounded-full px-2 py-0.5 text-[10px] font-black uppercase', m.priority === 'urgent' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' : 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-300']">{{ m.priority }}</span>
                                <span class="font-mono text-[10px] font-bold text-gray-400">{{ m.ref_no }}</span>
                                <span class="ml-auto text-[10px] font-bold text-gray-400">{{ fmtTime(m.published_at) }}</span>
                            </div>
                            <button @click="memoOpen = memoOpen === m.id ? null : m.id" class="mt-1 block w-full text-left text-sm font-black text-gray-900 hover:text-indigo-600 dark:text-white">
                                {{ m.title }}
                            </button>
                            <p class="text-[11px] font-bold text-gray-400">From {{ m.creator?.name || 'Office of the Secretary' }} · To {{ m.audience_label || m.audience }}</p>
                            <p v-if="memoOpen === m.id" class="mt-2 whitespace-pre-wrap text-xs leading-relaxed text-gray-600 dark:text-gray-300">{{ m.body }}</p>
                        </div>
                        <p v-if="!memos.length" class="px-5 py-10 text-center text-xs font-bold text-gray-400">No published memos for you.</p>
                    </div>
                </div>

                <!-- MESSENGER -->
                <div v-if="openPanel === 'messenger'" class="relative">
                    <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-3 dark:border-zinc-800">
                        <button v-if="activeThread || composing" @click="activeThread = null; composing = false; messages = []; msgView = 'chat'" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800">
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <img v-if="activeThread?.photo" :src="activeThread.photo" alt="" class="h-8 w-8 rounded-full object-cover" />
                        <img v-else-if="activeThread && threadAvatar(activeThread)" :src="threadAvatar(activeThread)" alt="" class="h-8 w-8 rounded-full object-cover" />
                        <p class="min-w-0 flex-1 truncate text-sm font-black text-gray-900 dark:text-white">
                            {{ composing ? 'New message' : activeThread ? activeThread.title : 'Messenger' }}
                            <span v-if="activeThread" :class="['ml-1.5 rounded-full px-1.5 py-0.5 align-middle text-[9px] font-black uppercase', activeThread.kind === 'group' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-slate-400']">
                                {{ activeThread.kind === 'group' ? `Group · ${activeThread.members?.length || ''}` : 'Personal' }}
                            </span>
                        </p>
                        <button v-if="activeThread?.kind === 'group' && !composing && msgView !== 'settings'" @click="openSettings" title="Group settings"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-zinc-800">
                            <Settings class="h-4 w-4" />
                        </button>
                        <button v-if="msgView === 'settings'" @click="msgView = 'chat'" title="Back to chat"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-zinc-800">
                            <MessageSquare class="h-4 w-4" />
                        </button>
                        <button v-if="!composing && !activeThread" @click="startCompose" class="rounded-lg p-1.5 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-zinc-800" title="New conversation">
                            <Plus class="h-4 w-4" />
                        </button>
                        <a :href="route('messenger.index')" target="_blank" rel="noopener noreferrer"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-zinc-800" title="Open full messenger in a new tab">
                            <Maximize2 class="h-4 w-4" />
                        </a>
                        <button @click="closePanels" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
                    </div>

                    <!-- Thread list -->
                    <div v-if="!activeThread && !composing" class="max-h-96 overflow-y-auto">
                        <button v-for="t in threads" :key="t.id" @click="openThread(t)"
                            class="flex w-full items-center gap-3 border-b border-gray-50 px-4 py-3 text-left transition last:border-0 hover:bg-gray-50 dark:border-zinc-800/60 dark:hover:bg-zinc-800/60">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs font-black text-white"
                                :class="threadAvatar(t) ? '' : 'bg-gradient-to-br from-blue-600 to-violet-700'">
                                <img v-if="threadAvatar(t)" :src="threadAvatar(t)" alt="" class="h-full w-full object-cover" />
                                <span v-else>{{ initialOf(t.title) }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5">
                                    <span class="truncate text-sm font-black text-gray-900 dark:text-white">{{ t.title }}</span>
                                    <span :class="['shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-black uppercase', t.kind === 'group' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-slate-400']">
                                        {{ t.kind === 'group' ? `Group · ${t.member_count}` : 'Personal' }}
                                    </span>
                                    <span v-if="t.unread > 0" class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-black text-white">{{ t.unread }}</span>
                                </span>
                                <span class="block truncate text-xs text-gray-500">{{ t.preview_by ? t.preview_by + ': ' : '' }}{{ t.preview || 'No messages yet' }}</span>
                            </span>
                        </button>
                        <div v-if="!threads.length" class="px-4 py-10 text-center">
                            <p class="text-xs font-bold text-gray-400">No conversations yet.</p>
                            <button @click="startCompose" class="mt-3 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-black uppercase text-white hover:bg-indigo-700">Start one</button>
                        </div>
                    </div>

                    <!-- Conversation -->
                    <div v-if="activeThread && !composing && msgView === 'chat'">
                        <div ref="msgBox" class="h-80 space-y-2 overflow-y-auto bg-gray-50/60 px-4 py-3 dark:bg-zinc-800/30">
                            <div v-for="m in messages" :key="m.id" :class="['flex items-end gap-1.5', m.mine ? 'justify-end' : 'justify-start']">
                                <img v-if="!m.mine && m.sender_photo" :src="m.sender_photo" alt="" class="h-6 w-6 shrink-0 rounded-full object-cover" />
                                <span v-else-if="!m.mine" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[9px] font-black text-gray-500 dark:bg-zinc-700 dark:text-gray-300">{{ initialOf(m.sender) }}</span>
                                <div :class="['max-w-[80%] rounded-2xl px-3.5 py-2 text-[13px] leading-relaxed', m.mine ? 'rounded-br-md bg-indigo-600 text-white' : 'rounded-bl-md bg-white text-gray-800 shadow-sm dark:bg-zinc-800 dark:text-gray-200']">
                                    <p v-if="!m.mine" class="mb-0.5 text-[10px] font-black uppercase tracking-wide opacity-60">{{ m.sender }}</p>
                                    <button v-if="m.is_image && m.file_url" type="button" @click="openLightbox(m.file_url)" class="mb-1.5 block overflow-hidden rounded-xl" title="View image">
                                        <img :src="m.file_url" alt="" class="max-h-48 w-full object-cover hover:opacity-90" loading="lazy" />
                                    </button>
                                    <a v-else-if="m.file_url" :href="m.file_url" target="_blank"
                                        :class="['mb-1.5 flex items-center gap-2 rounded-xl px-2.5 py-2 text-xs font-bold', m.mine ? 'bg-white/15 hover:bg-white/25' : 'bg-gray-100 hover:bg-gray-200 dark:bg-zinc-700 dark:hover:bg-zinc-600']">
                                        <Paperclip class="h-3.5 w-3.5 shrink-0" />
                                        <span class="truncate">{{ m.file_name || 'Attachment' }}</span>
                                    </a>
                                    <p v-if="m.body" class="whitespace-pre-wrap">{{ m.body }}</p>
                                </div>
                            </div>
                            <p v-if="!messages.length" class="py-8 text-center text-xs font-bold text-gray-400">Say hello to start the conversation.</p>
                        </div>
                        <div v-if="attachFile" class="flex items-center gap-2 border-t border-gray-100 bg-indigo-50/50 px-3 py-2 dark:border-zinc-800 dark:bg-indigo-900/10">
                            <img v-if="attachUrl" :src="attachUrl" alt="" class="h-10 w-10 rounded-lg object-cover" />
                            <Paperclip v-else class="h-4 w-4 shrink-0 text-indigo-500" />
                            <span class="min-w-0 flex-1 truncate text-xs font-bold text-gray-700 dark:text-gray-200">{{ attachFile.name }}</span>
                            <button @click="clearAttach" class="rounded-lg p-1 text-gray-400 hover:text-rose-500"><X class="h-3.5 w-3.5" /></button>
                        </div>
                        <div class="flex items-center gap-2 border-t border-gray-100 p-3 dark:border-zinc-800">
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
                    <div v-if="activeThread && !composing && msgView === 'settings' && activeThread.kind === 'group'" class="max-h-[480px] overflow-y-auto">
                        <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-4 dark:border-zinc-800">
                            <img v-if="activeThread.photo" :src="activeThread.photo" alt="" class="h-14 w-14 rounded-2xl object-cover" />
                            <span v-else class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-700 text-lg font-black text-white">
                                {{ (activeThread.title || '?').charAt(0).toUpperCase() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-black text-gray-900 dark:text-white">{{ activeThread.title }}</p>
                                <p class="text-[11px] font-bold text-gray-400">{{ activeThread.members?.length || 0 }} members</p>
                            </div>
                        </div>
                        <div class="space-y-4 p-4">
                            <p v-if="groupError" class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-600 dark:bg-rose-900/20">{{ groupError }}</p>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">Group name</label>
                                <div class="flex gap-2">
                                    <input v-model="groupName" maxlength="120" placeholder="Group name…"
                                        class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm outline-none focus:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                    <button @click="saveGroupName" :disabled="groupBusy || !groupName.trim()"
                                        class="rounded-xl bg-indigo-600 px-3.5 text-xs font-black uppercase text-white hover:bg-indigo-700 disabled:opacity-40 active:scale-95">
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
                                        <img v-if="m.photo" :src="m.photo" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover" />
                                        <span v-else class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[10px] font-black text-gray-600 dark:bg-zinc-700 dark:text-gray-300">
                                            {{ initialOf(m.name) }}
                                        </span>
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
                                <div v-if="addResults.length" class="mt-1.5 max-h-40 space-y-1 overflow-y-auto">
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
                                <LeaveIcon class="h-4 w-4" /> Leave this group
                            </button>
                        </div>
                    </div>

                    <!-- Group change confirmation -->
                    <div v-if="confirmBox" class="absolute inset-0 z-10 flex items-center justify-center bg-black/50 p-6 backdrop-blur-[1px]" @click.self="closeConfirm">
                        <div class="w-full max-w-xs overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                            <div class="px-5 pb-3 pt-5 text-center">
                                <h4 class="text-sm font-black text-gray-900 dark:text-white">{{ confirmBox.title }}</h4>
                                <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ confirmBox.message }}</p>
                                <img v-if="pendingPhotoUrl && confirmBox.title === 'Change group photo?'" :src="pendingPhotoUrl" alt=""
                                    class="mx-auto mt-3 h-20 w-20 rounded-2xl object-cover" />
                            </div>
                            <div class="flex gap-2 px-4 pb-4">
                                <button @click="closeConfirm(); pendingPhoto = null;"
                                    class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 hover:bg-gray-200 dark:bg-zinc-800 dark:text-gray-300">
                                    Cancel
                                </button>
                                <button @click="confirmBox.run(); closeConfirm();"
                                    :class="['flex-1 rounded-xl py-2.5 text-xs font-black uppercase text-white active:scale-95', confirmBox.danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-indigo-600 hover:bg-indigo-700']">
                                    {{ confirmBox.confirmLabel || 'Confirm' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- New conversation -->
                    <div v-if="composing">
                        <div class="border-b border-gray-100 p-3 dark:border-zinc-800">
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
                        <div class="max-h-56 overflow-y-auto">
                            <button v-for="u in filteredDir" :key="u.id" @click="togglePick(u)"
                                :class="['flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-zinc-800/60', picked.some((p) => p.id === u.id) && 'bg-indigo-50/60 dark:bg-indigo-900/20']">
                                <img v-if="u.photo" :src="u.photo" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover" />
                                <span v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[11px] font-black text-gray-600 dark:bg-zinc-700 dark:text-gray-300">{{ initialOf(u.name) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[13px] font-black text-gray-900 dark:text-white">{{ u.name }}</span>
                                    <span class="block truncate text-[11px] text-gray-400">{{ u.role }} · {{ u.position }}</span>
                                </span>
                            </button>
                        </div>
                        <div class="space-y-2 border-t border-gray-100 p-3 dark:border-zinc-800">
                            <input v-model="newTitle" placeholder="Group title (optional, for 2+ people)…"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm outline-none focus:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                            <div class="flex gap-2">
                                <input v-model="newBody" @keyup.enter="createThread" placeholder="First message…"
                                    class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm outline-none focus:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                <button @click="createThread" :disabled="creating || !picked.length || !newBody.trim()"
                                    class="rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white hover:bg-indigo-700 disabled:opacity-40 active:scale-95">
                                    Send
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Image lightbox (fullscreen viewer, no redirects) -->
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

                <!-- Logout confirmation (outside the messenger panel so it
                     renders from any tab — it previously only existed while
                     the messenger panel was open) -->
                <Teleport to="body">
                    <div v-if="showLogoutConfirm" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showLogoutConfirm = false">
                        <div class="w-full max-w-xs overflow-hidden rounded-3xl bg-white text-center shadow-2xl dark:bg-zinc-900">
                            <div class="px-6 pb-2 pt-6">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-100 dark:bg-rose-900/30">
                                    <LogOut class="h-6 w-6 text-rose-600" />
                                </div>
                                <h4 class="mt-3 text-base font-black text-gray-900 dark:text-white">Log out?</h4>
                                <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    Are you sure you want to sign out of your account{{ displayName ? `, ${displayName}` : '' }}?
                                </p>
                            </div>
                            <div class="flex gap-2 p-4">
                                <button type="button" @click="showLogoutConfirm = false"
                                    class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 hover:bg-gray-200 dark:bg-zinc-800 dark:text-gray-300">
                                    Cancel
                                </button>
                                <!-- Fetch POST with 419 recovery + native fallback:
                                     the session is destroyed server-side for every
                                     guard, then a full reload lands on /. -->
                                <form ref="logoutForm" :action="logoutHref" method="POST" class="flex-1" @submit.prevent="submitLogout">
                                    <input type="hidden" name="_token" :value="csrfToken" />
                                    <button type="submit" :disabled="loggingOut"
                                        class="w-full rounded-xl bg-rose-600 py-2.5 text-xs font-black uppercase text-white hover:bg-rose-700 active:scale-95 disabled:opacity-60">
                                        {{ loggingOut ? 'Logging out…' : 'Log Out' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </Teleport>

                <!-- Change password (partner portal) -->
                <Teleport to="body">
                    <div v-if="showPasswordModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="closePasswordModal">
                        <div class="w-full max-w-sm overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                                <h4 class="text-sm font-black text-gray-900 dark:text-white">Change Password</h4>
                                <button type="button" @click="closePasswordModal" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
                            </div>
                            <form @submit.prevent="submitPassword" class="space-y-3.5 p-5">
                                <p v-if="isRepresentative" class="rounded-xl bg-indigo-50 px-3 py-2 text-[11px] font-bold text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-300">
                                    Changing the password for {{ representative.email }}.
                                </p>
                                <div>
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">Current password</label>
                                    <input v-model="pwForm.current_password" type="password" autocomplete="current-password" required
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                    <p v-if="pwForm.errors.current_password" class="mt-1 text-[11px] font-bold text-rose-500">{{ pwForm.errors.current_password }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">New password</label>
                                    <input v-model="pwForm.password" type="password" autocomplete="new-password" required
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                    <p v-if="pwForm.errors.password" class="mt-1 text-[11px] font-bold text-rose-500">{{ pwForm.errors.password }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">Confirm new password</label>
                                    <input v-model="pwForm.password_confirmation" type="password" autocomplete="new-password" required
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                </div>
                                <div class="flex gap-2 pt-1">
                                    <button type="button" @click="closePasswordModal"
                                        class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 hover:bg-gray-200 dark:bg-zinc-800 dark:text-gray-300">
                                        Cancel
                                    </button>
                                    <button type="submit" :disabled="pwForm.processing"
                                        class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase text-white hover:bg-indigo-700 active:scale-95 disabled:opacity-60">
                                        {{ pwForm.processing ? 'Saving…' : 'Update password' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </Teleport>

                <!-- Transient success toast -->
                <Teleport to="body">
                    <div v-if="toastMsg" class="fixed right-5 top-20 z-[210] flex items-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-2xl">
                        <Check class="h-4 w-4" /> {{ toastMsg }}
                    </div>
                </Teleport>

            </div>
        </div>
    </header>
</template>
