<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import { KanbanSquare, Plus, X, Search, Sparkles, RotateCcw } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';
import StageColumn from '@/Components/crm/pipeline/StageColumn.vue';
import QuickCreateForm from '@/Components/crm/pipeline/QuickCreateForm.vue';
import StageEditModal from '@/Components/crm/pipeline/StageEditModal.vue';
import ActivityModal from '@/Components/crm/pipeline/ActivityModal.vue';
import ModalShell from '@/Components/crm/pipeline/ModalShell.vue';
import { filterOpps, peso, activityCounts } from '@/composables/crm/usePipeline';

const { canEdit } = usePageAccess();
const canEditOpp = computed(() => canEdit('CRM', 'opportunities'));

const props = defineProps({
    stages: { type: Array, default: () => [] },
    opportunities: { type: Array, default: () => [] },
    contacts: { type: Array, default: () => [] },
    salespeople: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
});
const page = usePage();
const authUserId = computed(() => props.currentUserId ?? page.props.auth?.user?.id ?? null);

// ── Filters: My Pipeline ON by default (removable chip), plus search ──
const myPipeline = ref(true);
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
    return map;
});

// Board-wide summary + activity legend counts (from the filtered set).
const boardCounts = computed(() => activityCounts(filtered.value));
const boardTotal = computed(() => Math.round(filtered.value.reduce((n, o) => n + Number(o.value || 0), 0)));
const hasActiveFilters = computed(
    () => search.value.trim() !== '' || !myPipeline.value || Object.values(activityFilters.value).some(Boolean),
);
const resetFilters = () => {
    search.value = '';
    myPipeline.value = true;
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

// ── Stage order (drag columns) ──
const orderedStages = ref([...(props.stages || [])].sort((a, b) => a.sequence - b.sequence));
watch(
    () => props.stages,
    (s) => {
        orderedStages.value = [...(s || [])].sort((a, b) => a.sequence - b.sequence);
    },
    { deep: true },
);
const onStageOrder = () => {
    if (!canEditOpp.value) return;
    router.post(route('crm.stages.reorder'), { ordered_ids: orderedStages.value.map((s) => s.id) }, { preserveScroll: true });
};

// ── Card drag between stages ──
const onCardMoved = ({ opportunity, toStage }) => {
    if (!canEditOpp.value) return;
    if (Number(opportunity.stage_id) === Number(toStage.id)) return;
    router.post(
        route('crm.opportunities.move', opportunity.id),
        { stage_id: toStage.id },
        { preserveScroll: true },
    );
};

// ── Quick create / stage modals ──
const quickStage = ref(null);
const openQuick = (stage) => (quickStage.value = stage);
const showNewStage = ref(false);
const openNew = () => {
    // No columns yet → guide the user to create the first stage instead of nothing.
    if (!orderedStages.value.length) {
        showNewStage.value = true;
        return;
    }
    openQuick(orderedStages.value[0]);
};

const menuStageId = ref(null);
const editingStage = ref(null);
const deletingStage = ref(null);
const deleteTarget = ref('');
const showAutomations = ref(null);

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

// ── Activity scheduling ──
const schedulingOpp = ref(null);
const schedulingExisting = ref(null);
const openSchedule = (opp) => {
    schedulingExisting.value = null;
    schedulingOpp.value = opp;
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
                            <p class="text-sm text-blue-100/90">Drag cards between stages — totals and activity bars update live.</p>
                            <span v-if="!canEditOpp" class="mt-2 inline-block rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700">View only</span>
                        </div>
                        <button
                            v-if="canEditOpp"
                            @click="openNew"
                            class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition hover:scale-105 active:scale-95"
                        >
                            <Plus class="h-4 w-4" /> New
                        </button>
                    </div>
                    <!-- Pipeline summary strip -->
                    <div class="relative mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/20 backdrop-blur">
                            <p class="text-[10px] font-black uppercase tracking-widest text-blue-100">Pipeline value</p>
                            <p class="text-xl font-black">{{ peso(boardTotal) }}</p>
                        </div>
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

                <!-- Kanban: draggable stage columns + add-stage rail -->
                <div class="overflow-x-auto pb-4">
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
                        <draggable
                            v-model="orderedStages"
                            group="stages"
                            item-key="id"
                            :disabled="!canEditOpp"
                            ghost-class="opacity-40"
                            chosen-class="pipeline-chosen"
                            animation="200"
                            class="flex items-start gap-3"
                            @change="onStageOrder"
                        >
                            <template #item="{ element }">
                                <StageColumn
                                    :stage="element"
                                    :opportunities="oppsByStage[element.id] || []"
                                    :activity-filter="activityFilters[element.id] || null"
                                    :can-edit="canEditOpp"
                                    :menu-open="menuStageId === element.id"
                                    @quick-create="openQuick"
                                    @schedule="openSchedule"
                                    @menu="(id) => (menuStageId = menuStageId === id ? null : id)"
                                    @fold="toggleFold"
                                    @edit="(s) => { editingStage = s; menuStageId = null; }"
                                    @automations="(s) => { showAutomations = s; menuStageId = null; }"
                                    @ask-delete="(s) => { deletingStage = s; deleteTarget = ''; menuStageId = null; }"
                                    @activity-filter="({ stageId, key }) => (activityFilters[stageId] = key)"
                                    @card-moved="onCardMoved"
                                />
                            </template>
                        </draggable>

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
            </div>
        </div>

        <QuickCreateForm v-if="quickStage" :stage="quickStage" :contacts="contacts" @close="quickStage = null" />
        <StageEditModal v-if="editingStage" :stage="editingStage" @close="editingStage = null" />
        <ActivityModal
            v-if="schedulingOpp"
            :opportunity="schedulingOpp"
            :salespeople="salespeople"
            :current-user-id="authUserId"
            :existing="schedulingExisting"
            @close="schedulingOpp = null"
            @scheduled="() => {}"
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

        <!-- Automations placeholder -->
        <ModalShell v-if="showAutomations" size="sm" @close="showAutomations = null">
            <div class="p-6 text-center">
                <p class="text-3xl">⚙️</p>
                <h3 class="mt-2 font-black dark:text-white">Automations — coming soon</h3>
                <p class="mt-1 text-xs text-gray-400">Per-stage rules (auto-create activities, notify, set fields on enter/exit) will live here. The data model already supports them.</p>
                <button @click="showAutomations = null" class="mt-4 w-full rounded-xl bg-indigo-600 py-2.5 text-xs font-black uppercase text-white transition hover:bg-indigo-700">Got it</button>
            </div>
        </ModalShell>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Single-token class for vuedraggable's chosen-class (classList.add
   rejects multi-class strings like "ring-2 ring-indigo-400"). */
.pipeline-chosen { box-shadow: 0 0 0 2px #818cf8; }
</style>
