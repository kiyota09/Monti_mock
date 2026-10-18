<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { KanbanSquare, Plus, X, Search, Sparkles, RotateCcw, Archive, StickyNote, Send, Trash2, Inbox, MessageSquare } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';
import StageColumn from '@/Components/crm/pipeline/StageColumn.vue';
import StageEditModal from '@/Components/crm/pipeline/StageEditModal.vue';
import ActivityModal from '@/Components/crm/pipeline/ActivityModal.vue';
import ActivityDetailModal from '@/Components/crm/pipeline/ActivityDetailModal.vue';
import CardDocumentsModal from '@/Components/crm/pipeline/CardDocumentsModal.vue';
import ModalShell from '@/Components/crm/pipeline/ModalShell.vue';
import { filterOpps, activityCounts, orderStages, peso } from '@/composables/crm/usePipeline';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const { canEdit } = usePageAccess();
const canEditOpp = computed(() => canEdit('CRM', 'opportunities'));

const props = defineProps({
    stages: { type: Array, default: () => [] },
    opportunities: { type: Array, default: () => [] },
    archived: { type: Array, default: () => [] },
    contacts: { type: Array, default: () => [] },
    salespeople: { type: Array, default: () => [] },
    notes: { type: Array, default: () => [] },
    feedback: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
});
const page = usePage();
const authUserId = computed(() => props.currentUserId ?? page.props.auth?.user?.id ?? null);

// ── Filters: show ALL deals by default (incl. leads just sent to pipeline) ──
// "My Pipeline" chip narrows to the current user's deals when enabled.
const myPipeline = ref(false);
const search = ref('');
const searchInput = ref(null);
const activityFilters = ref({}); // { [stageId]: 'planned'|'today'|'overdue'|'none'|null }

const filtered = computed(() =>
    filterOpps(props.opportunities, { mine: myPipeline.value, userId: authUserId.value, search: search.value }),
);
const oppsByStage = computed(() => {
    const map = {};
    for (const s of orderedStages.value) map[s.id] = [];
    for (const o of filtered.value) {
        if (o.stage_id && map[o.stage_id]) map[o.stage_id].push(o);
    }
    // Star rating drives the order inside each stage: 3★ at the top, then
    // 2★, 1★, and un-rated (0) deals at the bottom.
    for (const id in map) {
        map[id].sort((a, b) => (Number(b.priority) || 0) - (Number(a.priority) || 0));
    }
    return map;
});

// Board-wide summary + activity legend counts (from the filtered set).
const boardCounts = computed(() => activityCounts(filtered.value));
const hasActiveFilters = computed(
    () => search.value.trim() !== '' || myPipeline.value || Object.values(activityFilters.value).some(Boolean),
);
const resetFilters = () => {
    search.value = '';
    myPipeline.value = false;
    activityFilters.value = {};
};

// Press "/" to jump to search.
const onGlobalKey = (e) => {
    if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
        e.preventDefault();
        searchInput.value?.focus();
    }
};
onMounted(() => window.addEventListener('keydown', onGlobalKey));
onUnmounted(() => window.removeEventListener('keydown', onGlobalKey));

// ── Stage order: derived from each stage's default probability (lowest first);
//    Won/Lost are pinned to the far right. Columns are no longer draggable. ──
const orderedStages = computed(() => orderStages(props.stages));

// ── View tabs: live board vs. archived (totally-failed) deals ──
const activeView = ref('board');
const archivedList = computed(() => props.archived || []);

// ── Card drag between stages ──
// Forward-only rule: a deal may advance to a stage of equal/higher default
// probability, and may always be dropped into a Lost stage. Dropping it back
// into an earlier (lower-probability) stage is rejected.
const cardResetToken = ref(0);
const stageById = (id) => orderedStages.value.find((s) => Number(s.id) === Number(id)) || null;

const canDropInto = (opportunity, toStage) => {
    if (!toStage) return false;
    if (toStage.is_lost) return true;
    const from = stageById(opportunity.stage_id);
    if (!from) return true;
    return Number(toStage.default_probability ?? 0) >= Number(from.default_probability ?? 0);
};

const onCardMoved = ({ opportunity, toStage }) => {
    if (!canEditOpp.value) return;
    if (Number(opportunity.stage_id) === Number(toStage.id)) return;
    router.post(
        route('crm.opportunities.move', opportunity.id),
        { stage_id: toStage.id },
        { preserveScroll: true },
    );
};

// A blocked drop never hits the server, so nudge every column to re-read the
// unchanged props and snap the card back to where it started.
const onCardBlocked = ({ opportunity, toStage }) => {
    const from = stageById(opportunity.stage_id);
    toast.error(
        `“${opportunity.title}” can only move forward — ${toStage.name} is behind ${from?.name ?? 'its stage'}. Drop it into a Lost stage to close it.`,
    );
    cardResetToken.value++;
};

// ── Floating shared Notes / progress log ──
const showNotes = ref(false);
const noteForm = useForm({ body: '' });
const noteCount = computed(() => (props.notes || []).length);

const submitNote = () => {
    if (!noteForm.body.trim()) return;
    noteForm.post(route('crm.opportunities.notes.store'), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset('body'),
    });
};

const deleteNote = (note) => {
    router.delete(route('crm.opportunities.notes.destroy', note.id), { preserveScroll: true });
};

const fmtNoteTime = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    const minutes = Math.floor((Date.now() - d.getTime()) / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

// ── Stage modals (deals only arrive from the Leads inbox) ──
const showNewStage = ref(false);

const menuStageId = ref(null);
const editingStage = ref(null);
const deletingStage = ref(null);
const deleteTarget = ref('');

const toggleFold = (stage) => {
    menuStageId.value = null;
    router.patch(route('crm.stages.update', stage.id), { is_folded: !stage.is_folded }, { preserveScroll: true });
};

const newStageForm = useForm({ name: '' });
const createStage = () => {
    newStageForm.post(route('crm.stages.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showNewStage.value = false;
            newStageForm.reset();
        },
    });
};

const confirmDelete = () => {
    if (!deletingStage.value) return;
    router.delete(route('crm.stages.destroy', deletingStage.value.id), {
        data: deleteTarget.value ? { mode: 'move', target_stage_id: Number(deleteTarget.value) } : { mode: 'block' },
        preserveScroll: true,
        onSuccess: () => {
            deletingStage.value = null;
            deleteTarget.value = '';
        },
    });
};

// ── Activity scheduling (sends a meeting request email) ──
const schedulingOpp = ref(null);
const schedulingExisting = ref(null);
const schedulingPrefill = ref(null);
const openSchedule = (opp, prefill = null) => {
    schedulingExisting.value = null;
    schedulingPrefill.value = prefill;
    schedulingOpp.value = opp;
};
const closeSchedule = () => {
    schedulingOpp.value = null;
    schedulingExisting.value = null;
    schedulingPrefill.value = null;
};

// ── Scheduled-meeting detail (click a meeting on a card) ──
const detailSelection = ref(null); // { opportunity, activity }
const openActivity = (payload) => { detailSelection.value = payload; };
const closeDetail = () => { detailSelection.value = null; };

// ── Card files/images (stages with allows_uploads) ──
const documentsOppId = ref(null);
// Read the live opportunity from props so the list refreshes after an upload.
const documentsOpp = computed(
    () => props.opportunities.find((o) => Number(o.id) === Number(documentsOppId.value)) || null,
);
const openDocuments = (opp) => { documentsOppId.value = opp.id; };
const closeDocuments = () => { documentsOppId.value = null; };

// ── Floating client Feedback (meeting-request answers) ──
const showFeedback = ref(false);
const answeredFeedback = computed(() => (props.feedback || []).filter((f) => f.status !== 'pending'));
const feedbackCount = computed(() => answeredFeedback.value.length);

const FB_STATUS = {
    pending: { label: 'Awaiting', cls: 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-slate-300' },
    confirmed: { label: 'Confirmed', cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' },
    declined: { label: 'Declined', cls: 'bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300' },
    reschedule: { label: 'Reschedule', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' },
};
const fbMeta = (status) => FB_STATUS[status] || FB_STATUS.pending;

// Re-schedule: reopen the request form prefilled with the client's preference.
const rescheduleFeedback = (fb) => {
    if (!fb.opportunity_id) {
        toast.error('This request is not linked to a deal.');
        return;
    }
    const opp = props.opportunities.find((o) => Number(o.id) === Number(fb.opportunity_id))
        || { id: fb.opportunity_id, title: fb.opportunity_title || 'Opportunity' };
    openSchedule(opp, {
        type: fb.type,
        summary: fb.summary,
        meeting_link: fb.meeting_link,
        notes: fb.response_message || '',
        due: fb.preferred_at || fb.proposed_at,
    });
    showFeedback.value = false;
};

const LEGEND = [
    { key: 'planned', label: 'Planned', dot: 'bg-emerald-500' },
    { key: 'today', label: 'Due today', dot: 'bg-amber-400' },
    { key: 'overdue', label: 'Overdue', dot: 'bg-rose-500' },
    { key: 'none', label: 'No activity', dot: 'bg-slate-300 dark:bg-zinc-600' },
];
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Pipeline" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-[1700px] space-y-5 p-4 pb-16 sm:p-6">
                <!-- Header -->
                <div class="relative animate-fade-up overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white shadow-xl shadow-indigo-500/20 sm:p-8">
                    <div class="absolute -right-20 -top-20 h-64 w-64 animate-float rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 animate-pop items-center justify-center rounded-2xl bg-white/15 shadow-lg ring-1 ring-white/30 backdrop-blur">
                            <KanbanSquare class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">CRM · Pipeline</p>
                            <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Opportunities</h1>
                            <p class="text-sm text-blue-100/90">Deals arrive here from the Leads inbox — drag cards between stages, totals and activity bars update live.</p>
                            <span v-if="!canEditOpp" class="mt-2 inline-block rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700">View only</span>
                        </div>
                    </div>
                    <!-- Pipeline summary strip -->
                    <div class="relative mt-5 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/20 backdrop-blur">
                            <p class="text-[10px] font-black uppercase tracking-widest text-blue-100">Open deals</p>
                            <p class="text-xl font-black">{{ filtered.length }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/20 backdrop-blur">
                            <p class="text-[10px] font-black uppercase tracking-widest text-blue-100">Overdue</p>
                            <p :class="['text-xl font-black', boardCounts.overdue ? 'text-rose-200' : '']">{{ boardCounts.overdue }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/20 backdrop-blur">
                            <p class="text-[10px] font-black uppercase tracking-widest text-blue-100">Due today</p>
                            <p :class="['text-xl font-black', boardCounts.today ? 'text-amber-200' : '']">{{ boardCounts.today }}</p>
                        </div>
                    </div>
                </div>

                <!-- Search bar: My Pipeline chip + search + legend + reset -->
                <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-gray-100 bg-white/70 px-3 py-2.5 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/70">
                    <button
                        v-if="myPipeline"
                        @click="myPipeline = false"
                        class="inline-flex items-center gap-1.5 rounded-full bg-indigo-600 px-3 py-1.5 text-[11px] font-black text-white transition hover:bg-indigo-700"
                        title="Showing only my opportunities — click X to show all"
                    >
                        My Pipeline <X class="h-3.5 w-3.5" />
                    </button>
                    <button
                        v-else
                        @click="myPipeline = true"
                        class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-black text-slate-500 transition hover:bg-slate-200 dark:bg-zinc-800 dark:text-slate-300"
                        title="Show only my opportunities"
                    >
                        <Sparkles class="h-3.5 w-3.5" /> My Pipeline
                    </button>
                    <div class="relative min-w-[200px] flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-300" />
                        <input
                            ref="searchInput"
                            v-model="search"
                            placeholder="Search opportunities or contacts…  ( / )"
                            class="w-full rounded-xl bg-transparent py-1.5 pl-9 pr-8 text-sm outline-none placeholder:text-gray-300 dark:text-white"
                        />
                        <button
                            v-if="search"
                            @click="search = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-gray-300 hover:bg-gray-100 hover:text-gray-500"
                            title="Clear search"
                            aria-label="Clear search"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <div class="hidden items-center gap-2.5 border-l border-gray-100 pl-3 md:flex dark:border-zinc-700" title="Activity colors used on cards and stage bars">
                        <span v-for="l in LEGEND" :key="l.key" class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-400">
                            <span :class="['h-2 w-2 rounded-full', l.dot]" /> {{ l.label }} {{ boardCounts[l.key] }}
                        </span>
                    </div>
                    <button
                        v-if="hasActiveFilters"
                        @click="resetFilters"
                        class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-black text-slate-500 transition hover:bg-slate-200 dark:bg-zinc-800 dark:text-slate-300"
                        title="Reset search, My Pipeline and stage filters"
                    >
                        <RotateCcw class="h-3 w-3" /> Reset
                    </button>
                    <span class="px-1 text-[11px] font-bold text-gray-400">
                        {{ filtered.length }} of {{ opportunities.length }}
                    </span>
                </div>

                <!-- View tabs: live Board vs. Archives (totally-failed deals) -->
                <div class="flex items-center gap-2">
                    <button
                        @click="activeView = 'board'"
                        :class="[
                            'inline-flex items-center gap-2 rounded-2xl px-4 py-2 text-xs font-black uppercase tracking-wide transition',
                            activeView === 'board'
                                ? 'bg-indigo-600 text-white shadow'
                                : 'bg-white/70 text-slate-500 hover:bg-white dark:bg-zinc-900/70 dark:text-slate-300',
                        ]"
                    >
                        <KanbanSquare class="h-4 w-4" /> Board
                        <span :class="['rounded-full px-1.5 py-0.5 text-[10px]', activeView === 'board' ? 'bg-white/20' : 'bg-slate-100 dark:bg-zinc-800']">{{ opportunities.length }}</span>
                    </button>
                    <button
                        @click="activeView = 'archives'"
                        :class="[
                            'inline-flex items-center gap-2 rounded-2xl px-4 py-2 text-xs font-black uppercase tracking-wide transition',
                            activeView === 'archives'
                                ? 'bg-slate-900 text-white shadow'
                                : 'bg-white/70 text-slate-500 hover:bg-white dark:bg-zinc-900/70 dark:text-slate-300',
                        ]"
                    >
                        <Archive class="h-4 w-4" /> Archives
                        <span :class="['rounded-full px-1.5 py-0.5 text-[10px]', activeView === 'archives' ? 'bg-white/20' : 'bg-slate-100 dark:bg-zinc-800']">{{ archivedList.length }}</span>
                    </button>
                </div>

                <!-- Board: stage columns ordered by default probability + add-stage rail -->
                <div v-if="activeView === 'board'" class="overflow-x-auto pb-4">
                    <div v-if="!orderedStages.length" class="rounded-3xl border-2 border-dashed border-gray-200 py-16 text-center dark:border-zinc-700">
                        <KanbanSquare class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700" />
                        <p class="mt-3 font-black text-slate-500 dark:text-slate-300">No stages yet</p>
                        <p class="mt-1 text-xs text-gray-400">Create your first column — e.g. New, Qualified, Proposition, Won.</p>
                        <button
                            v-if="canEditOpp"
                            @click="showNewStage = true"
                            class="mt-4 inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-xs font-black uppercase text-white shadow transition hover:bg-indigo-700"
                        >
                            <Plus class="h-4 w-4" /> Add your first stage
                        </button>
                    </div>
                    <div v-else class="flex min-h-[420px] items-start gap-3">
                        <StageColumn
                            v-for="element in orderedStages"
                            :key="element.id"
                            :stage="element"
                            :opportunities="oppsByStage[element.id] || []"
                            :activity-filter="activityFilters[element.id] || null"
                            :can-edit="canEditOpp"
                            :menu-open="menuStageId === element.id"
                            :can-drop="canDropInto"
                            :reset-token="cardResetToken"
                            @schedule="openSchedule"
                            @open-activity="openActivity"
                            @upload-docs="openDocuments"
                            @menu="(id) => (menuStageId = menuStageId === id ? null : id)"
                            @fold="toggleFold"
                            @edit="(s) => { editingStage = s; menuStageId = null; }"
                            @ask-delete="(s) => { deletingStage = s; deleteTarget = ''; menuStageId = null; }"
                            @activity-filter="({ stageId, key }) => (activityFilters[stageId] = key)"
                            @card-moved="onCardMoved"
                            @card-blocked="onCardBlocked"
                        />

                        <button
                            v-if="canEditOpp"
                            @click="showNewStage = true"
                            class="flex w-14 shrink-0 flex-col items-center gap-2 rounded-3xl border-2 border-dashed border-gray-200 py-6 text-gray-300 transition hover:border-indigo-400 hover:text-indigo-500 dark:border-zinc-700"
                            title="Add a stage"
                        >
                            <Plus class="h-5 w-5" />
                            <span class="text-[10px] font-black uppercase" style="writing-mode: vertical-rl">Stage</span>
                        </button>
                    </div>
                </div>

                <!-- Archives: deals marked as totally failed -->
                <div v-else class="space-y-3">
                    <div v-if="!archivedList.length" class="rounded-3xl border-2 border-dashed border-gray-200 py-16 text-center dark:border-zinc-700">
                        <Archive class="mx-auto h-10 w-10 text-gray-200 dark:text-zinc-700" />
                        <p class="mt-3 font-black text-slate-500 dark:text-slate-300">No archived deals</p>
                        <p class="mt-1 text-xs text-gray-400">Deals you archive from the Lost stage land here.</p>
                    </div>
                    <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="a in archivedList" :key="a.id" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex items-start justify-between gap-2">
                                <Link :href="route('crm.opportunities.show', a.id)" class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-black hover:text-indigo-600">{{ a.title }}</p>
                                    <p class="truncate text-[11px] font-bold text-gray-400">
                                        {{ a.contact_name }}<span v-if="a.organization"> · {{ a.organization }}</span>
                                    </p>
                                </Link>
                                <span class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-[9px] font-black uppercase text-rose-600 dark:bg-rose-900/30 dark:text-rose-300">Archived</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between border-t border-slate-50 pt-2 text-[11px] dark:border-zinc-800">
                                <span class="font-black text-slate-500 dark:text-slate-300">{{ peso(a.value) }}</span>
                                <span class="font-bold text-gray-400">{{ a.probability }}%</span>
                            </div>
                            <p v-if="a.lost_reason" class="mt-2 rounded-lg bg-rose-50 px-2.5 py-1.5 text-[11px] text-rose-600 dark:bg-rose-900/20 dark:text-rose-300">
                                <span class="font-black uppercase">Reason:</span> {{ a.lost_reason }}
                            </p>
                            <p v-if="a.archived_at" class="mt-2 text-[10px] font-bold text-gray-400">
                                Archived {{ new Date(a.archived_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) }}
                                <span v-if="a.owner?.name"> · {{ a.owner.name }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <!-- Floating Notes trigger: viewport-fixed to the top-right, just
                 below the top nav bar. Teleported to <body> so no scrolling /
                 transformed ancestor can detach it from the viewport. -->
            <button
                @click="showFeedback = false; showNotes = true"
                class="fixed right-4 top-20 z-50 inline-flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 to-violet-700 text-white shadow-xl shadow-indigo-500/30 transition hover:scale-105 active:scale-95 sm:right-6"
                title="Pipeline notes — document progress"
                aria-label="Open pipeline notes"
            >
                <StickyNote class="h-6 w-6" />
                <span
                    v-if="noteCount"
                    class="absolute -right-1 -top-1 inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-black text-white ring-2 ring-white dark:ring-zinc-950"
                >
                    {{ noteCount }}
                </span>
            </button>

            <div v-if="showNotes" class="fixed inset-0 z-[60]">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showNotes = false" />
                <div class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-2xl dark:bg-zinc-900">
                    <!-- Header -->
                    <div class="flex items-center justify-between bg-gradient-to-r from-indigo-700 to-violet-800 px-5 py-4 text-white">
                        <div class="min-w-0">
                            <h3 class="flex items-center gap-2 font-black"><StickyNote class="h-4 w-4" /> Pipeline Notes</h3>
                            <p class="truncate text-xs text-indigo-100">Shared progress log for the Opportunities board</p>
                        </div>
                        <button @click="showNotes = false" aria-label="Close notes"><X class="h-5 w-5 shrink-0" /></button>
                    </div>

                    <!-- Compose -->
                    <form @submit.prevent="submitNote" class="border-b border-gray-100 p-4 dark:border-zinc-800">
                        <textarea
                            v-model="noteForm.body"
                            rows="3"
                            placeholder="Document what's happening and the progress…"
                            class="w-full resize-none rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white"
                        />
                        <p v-if="noteForm.errors.body" class="mt-1 text-[11px] font-bold text-rose-500">{{ noteForm.errors.body }}</p>
                        <button
                            :disabled="noteForm.processing || !noteForm.body.trim()"
                            class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase text-white transition hover:bg-indigo-700 disabled:opacity-50"
                        >
                            <Send class="h-3.5 w-3.5" /> {{ noteForm.processing ? 'Posting…' : 'Add note' }}
                        </button>
                    </form>

                    <!-- Log -->
                    <div class="flex-1 space-y-2.5 overflow-y-auto p-4">
                        <p v-if="!notes.length" class="py-10 text-center text-xs font-bold text-gray-400">
                            No notes yet — add the first update.
                        </p>
                        <div
                            v-for="n in notes"
                            :key="n.id"
                            class="group rounded-2xl border border-gray-100 bg-slate-50/60 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/40"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex min-w-0 items-center gap-1.5 text-[11px] font-black text-slate-600 dark:text-slate-300">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[9px] text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                        {{ (n.author || '?').charAt(0).toUpperCase() }}
                                    </span>
                                    <span class="truncate">{{ n.author }}</span>
                                </span>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <span class="text-[10px] font-bold text-gray-400">{{ fmtNoteTime(n.created_at) }}</span>
                                    <button
                                        v-if="canEditOpp"
                                        @click="deleteNote(n)"
                                        class="rounded-lg p-1 text-gray-300 opacity-0 transition hover:bg-rose-50 hover:text-rose-500 group-hover:opacity-100"
                                        title="Delete note"
                                        aria-label="Delete note"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                            <p class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-700 dark:text-slate-200">{{ n.body }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <!-- Floating Feedback trigger: sits just under the Notes button. -->
            <button
                @click="showNotes = false; showFeedback = true"
                class="fixed right-4 top-[9.5rem] z-50 inline-flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-sky-600 to-cyan-700 text-white shadow-xl shadow-sky-500/30 transition hover:scale-105 active:scale-95 sm:right-6"
                title="Client feedback — meeting request replies"
                aria-label="Open client feedback"
            >
                <Inbox class="h-6 w-6" />
                <span
                    v-if="feedbackCount"
                    class="absolute -right-1 -top-1 inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-black text-white ring-2 ring-white dark:ring-zinc-950"
                >
                    {{ feedbackCount }}
                </span>
            </button>

            <div v-if="showFeedback" class="fixed inset-0 z-[60]">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showFeedback = false" />
                <div class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-center justify-between bg-gradient-to-r from-sky-700 to-cyan-800 px-5 py-4 text-white">
                        <div class="min-w-0">
                            <h3 class="flex items-center gap-2 font-black"><MessageSquare class="h-4 w-4" /> Client Feedback</h3>
                            <p class="truncate text-xs text-sky-100">Replies to meeting &amp; call requests</p>
                        </div>
                        <button @click="showFeedback = false" aria-label="Close feedback"><X class="h-5 w-5 shrink-0" /></button>
                    </div>

                    <div class="flex-1 space-y-2.5 overflow-y-auto p-4">
                        <p v-if="!feedback.length" class="py-10 text-center text-xs font-bold text-gray-400">
                            No requests sent yet. Schedule a call or meeting to collect feedback.
                        </p>
                        <div
                            v-for="fb in feedback"
                            :key="fb.id"
                            class="rounded-2xl border border-gray-100 bg-slate-50/60 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/40"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex min-w-0 items-center gap-1.5 text-[11px] font-black text-slate-600 dark:text-slate-300">
                                    <span class="rounded bg-sky-100 px-1.5 py-0.5 text-[9px] uppercase text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">{{ fb.type }}</span>
                                    <span class="truncate">{{ fb.recipient_name || fb.opportunity_title || 'Client' }}</span>
                                </span>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black uppercase" :class="fbMeta(fb.status).cls">
                                    {{ fbMeta(fb.status).label }}
                                </span>
                            </div>

                            <p class="mt-2 text-sm font-bold text-slate-800 dark:text-slate-100">{{ fb.summary }}</p>
                            <p v-if="fb.opportunity_title" class="text-[11px] font-bold text-gray-400">{{ fb.opportunity_title }}</p>

                            <div class="mt-2 space-y-1 text-[11px] text-slate-600 dark:text-slate-300">
                                <p v-if="fb.proposed_at"><span class="font-black uppercase text-gray-400">Proposed:</span> {{ fmtNoteTime(fb.proposed_at) }}</p>
                                <p v-if="fb.preferred_at"><span class="font-black uppercase text-gray-400">Client wants:</span> {{ fmtNoteTime(fb.preferred_at) }}</p>
                                <p v-if="fb.response_place"><span class="font-black uppercase text-gray-400">Place:</span> {{ fb.response_place }}</p>
                                <p v-if="fb.response_message" class="whitespace-pre-wrap break-words rounded-lg bg-white px-2.5 py-1.5 italic text-slate-600 dark:bg-zinc-900 dark:text-slate-300">“{{ fb.response_message }}”</p>
                            </div>

                            <div class="mt-2.5 flex items-center justify-between gap-2 border-t border-slate-100 pt-2 text-[10px] font-bold text-gray-400 dark:border-zinc-800">
                                <span v-if="fb.requested_by">by {{ fb.requested_by }} · {{ fmtNoteTime(fb.created_at) }}</span>
                                <span v-else>{{ fmtNoteTime(fb.created_at) }}</span>
                                <button
                                    v-if="fb.status === 'reschedule'"
                                    @click="rescheduleFeedback(fb)"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-amber-500 px-2.5 py-1 text-[10px] font-black uppercase text-white transition hover:bg-amber-600"
                                >
                                    <RotateCcw class="h-3 w-3" /> Reschedule
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <StageEditModal v-if="editingStage" :stage="editingStage" @close="editingStage = null" />
        <ActivityModal
            v-if="schedulingOpp"
            :opportunity="schedulingOpp"
            :salespeople="salespeople"
            :current-user-id="authUserId"
            :existing="schedulingExisting"
            :prefill="schedulingPrefill"
            @close="closeSchedule"
            @scheduled="() => {}"
        />

        <ActivityDetailModal
            v-if="detailSelection"
            :opportunity="detailSelection.opportunity"
            :activity="detailSelection.activity"
            @close="closeDetail"
            @updated="closeDetail"
        />

        <CardDocumentsModal
            v-if="documentsOpp"
            :opportunity="documentsOpp"
            :can-edit="canEditOpp"
            @close="closeDocuments"
        />

        <!-- New stage prompt -->
        <ModalShell v-if="showNewStage" size="sm" @close="showNewStage = false">
            <form @submit.prevent="createStage" class="p-6">
                <h3 class="font-black dark:text-white">Add Stage</h3>
                <p class="mt-1 text-xs text-gray-400">It appears at the far right — drag it anywhere.</p>
                <input v-model="newStageForm.name" required placeholder="e.g. Second Proposition" autofocus class="mt-4 w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white" />
                <div class="mt-4 flex gap-2">
                    <button type="button" @click="showNewStage = false" class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 transition hover:bg-gray-200 dark:bg-zinc-800">Cancel</button>
                    <button :disabled="newStageForm.processing || !newStageForm.name.trim()" class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase text-white transition hover:bg-indigo-700 disabled:opacity-50">Create</button>
                </div>
            </form>
        </ModalShell>

        <!-- Delete stage confirm -->
        <ModalShell v-if="deletingStage" size="sm" @close="deletingStage = null">
            <div class="p-6">
                <h3 class="font-black dark:text-white">Delete “{{ deletingStage.name }}”?</h3>
                <p class="mt-1 text-xs text-gray-400">
                    {{ (oppsByStage[deletingStage.id] || []).length }} opportunitie(s) in this stage.
                    {{ (oppsByStage[deletingStage.id] || []).length ? 'Pick a new home for them — deletion is blocked otherwise.' : 'Nothing lives here, safe to remove.' }}
                </p>
                <div v-if="(oppsByStage[deletingStage.id] || []).length" class="mt-3">
                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400" for="del-target">Move deals to…</label>
                    <select id="del-target" v-model="deleteTarget" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white">
                        <option value="">— choose (required to delete) —</option>
                        <option v-for="s in orderedStages.filter((s) => s.id !== deletingStage.id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div class="mt-4 flex gap-2">
                    <button @click="deletingStage = null" class="flex-1 rounded-xl bg-gray-100 py-2.5 text-xs font-black uppercase text-gray-500 transition hover:bg-gray-200 dark:bg-zinc-800">Cancel</button>
                    <button @click="confirmDelete" :disabled="(oppsByStage[deletingStage.id] || []).length > 0 && !deleteTarget" class="flex-1 rounded-xl bg-rose-600 py-2.5 text-xs font-black uppercase text-white transition hover:bg-rose-700 disabled:opacity-50">Delete</button>
                </div>
            </div>
        </ModalShell>

    </AuthenticatedLayout>
</template>

<style scoped>
/* Single-token class for vuedraggable's chosen-class (classList.add
   rejects multi-class strings like "ring-2 ring-indigo-400"). */
.pipeline-chosen { box-shadow: 0 0 0 2px #818cf8; }
</style>
