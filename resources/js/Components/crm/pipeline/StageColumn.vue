<script setup>
import { ref, computed, watch } from 'vue';
import draggable from 'vuedraggable';
import { Settings2, Trophy, X, Ban } from 'lucide-vue-next';
import OpportunityCard from './OpportunityCard.vue';
import ActivityBar from './ActivityBar.vue';
import { activityStateOf } from '@/composables/crm/usePipeline';

const props = defineProps({
    stage: { type: Object, required: true },
    opportunities: { type: Array, default: () => [] },
    activityFilter: { type: String, default: null },
    canEdit: { type: Boolean, default: false },
    menuOpen: { type: Boolean, default: false },
    // (opportunity, toStage) => boolean — parent decides if a drop is allowed.
    canDrop: { type: Function, default: null },
    // Bumped by the parent to force every column to re-read props after a
    // rejected drop (the server was never called, so props are unchanged).
    resetToken: { type: [Number, String], default: 0 },
});
const emit = defineEmits([
    'schedule', 'open-activity', 'upload-docs', 'menu', 'fold', 'edit', 'ask-delete',
    'activity-filter', 'card-moved', 'card-blocked',
]);

const visible = computed(() =>
    props.activityFilter
        ? props.opportunities.filter((o) => activityStateOf(o) === props.activityFilter)
        : props.opportunities,
);
const filterNames = { planned: 'Planned', today: 'Due today', overdue: 'Overdue', none: 'No activity' };

// Local mutable mirror of the (possibly activity-filtered) visible list.
// Sortable writes the new order here on drop, so Vue's DOM never fights
// the drag mid-gesture; the server refresh after each move re-syncs it.
const dragList = ref([]);
watch(
    visible,
    (list) => {
        dragList.value = [...(list || [])];
    },
    { immediate: true },
);
// After a rejected drop (no server round-trip) the parent bumps the token so
// the card snaps back to its original column/order.
watch(
    () => props.resetToken,
    () => {
        dragList.value = [...(visible.value || [])];
    },
);

const onCardChange = (e) => {
    // Guard: ignore reorder/move events that carry no dropped item
    // (prevents null-element crashes from stale Sortable nodes).
    const added = e?.added;
    if (!added?.element) return;
    // Sequential-stage rule lives in the parent; reject before hitting the server.
    if (props.canDrop && props.canDrop(added.element, props.stage) === false) {
        emit('card-blocked', { opportunity: added.element, toStage: props.stage });
        return;
    }
    emit('card-moved', { opportunity: added.element, toStage: props.stage });
};
</script>

<template>
    <!-- Folded: narrow vertical strip -->
    <button
        v-if="stage.is_folded"
        @click="emit('fold', stage)"
        class="flex w-14 shrink-0 flex-col items-center gap-3 rounded-3xl border border-gray-100 bg-white/70 py-4 backdrop-blur transition hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900/70"
        :title="`${stage.name} is folded — click to unfold (${opportunities.length} deals)`"
    >
        <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">
            {{ opportunities.length }}
        </span>
        <span
            class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-300"
            style="writing-mode: vertical-rl"
        >
            {{ stage.name }}
        </span>
    </button>

    <!-- Open column -->
    <div
        v-else
        class="group flex max-h-[calc(100vh-13rem)] w-72 shrink-0 flex-col rounded-3xl border border-gray-100 bg-white/70 p-3 backdrop-blur transition-colors dark:border-zinc-800 dark:bg-zinc-900/70"
    >
        <!-- Header: name + gear -->
        <div class="shrink-0 px-1 pb-2">
            <div class="flex items-center gap-1">
                <p class="min-w-0 flex-1 truncate text-[11px] font-black uppercase tracking-widest" :title="stage.name">
                    {{ stage.name }}
                    <Trophy v-if="stage.is_won" class="ml-1 inline h-3 w-3 text-amber-400" />
                    <Ban v-if="stage.is_lost" class="ml-1 inline h-3 w-3 text-rose-400" />
                </p>
                <div v-if="canEdit" class="relative">
                    <button
                        @click.stop="emit('menu', stage.id)"
                        class="rounded-lg p-1 text-gray-400 opacity-0 transition hover:bg-slate-100 hover:text-slate-600 focus:opacity-100 group-hover:opacity-100"
                        :class="{ 'opacity-100': menuOpen }"
                        title="Stage settings"
                        aria-label="Stage settings"
                    >
                        <Settings2 class="h-4 w-4" />
                    </button>
                    <template v-if="menuOpen">
                        <!-- click-away layer -->
                        <div class="fixed inset-0 z-20 cursor-default" @click.stop="emit('menu', stage.id)" />
                        <div
                            class="absolute right-0 z-30 w-44 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                            @click.stop
                        >
                            <button @click="emit('fold', stage)" class="block w-full px-4 py-2 text-left text-xs font-bold hover:bg-slate-50 dark:hover:bg-zinc-800">
                                {{ stage.is_folded ? 'Unfold' : 'Fold' }}
                            </button>
                            <button @click="emit('edit', stage)" class="block w-full px-4 py-2 text-left text-xs font-bold hover:bg-slate-50 dark:hover:bg-zinc-800">Edit…</button>
                            <div class="my-1 border-t border-gray-100 dark:border-zinc-800" />
                            <button @click="emit('ask-delete', stage)" class="block w-full px-4 py-2 text-left text-xs font-bold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20">Delete…</button>
                        </div>
                    </template>
                </div>
            </div>
            <p class="mt-0.5 text-[10px] font-bold text-gray-400">
                {{ opportunities.length }} deal{{ opportunities.length === 1 ? '' : 's' }}
                <span class="text-indigo-500">· {{ stage.default_probability }}%</span>
            </p>
            <!-- Won/Lost are terminal: no more activities, so no activity bar. -->
            <template v-if="!stage.is_won && !stage.is_lost">
                <ActivityBar
                    :opportunities="opportunities"
                    :active="activityFilter"
                    @select="(k) => emit('activity-filter', { stageId: stage.id, key: k })"
                    class="mt-2"
                />
                <!-- active segment filter chip -->
                <button
                    v-if="activityFilter"
                    @click="emit('activity-filter', { stageId: stage.id, key: null })"
                    class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-1 text-[10px] font-black text-indigo-700 hover:bg-indigo-200 dark:bg-indigo-900/40 dark:text-indigo-300"
                    title="Clear activity filter"
                >
                    {{ filterNames[activityFilter] }} <X class="h-3 w-3" />
                </button>
            </template>
            <p v-else class="mt-2 rounded-lg bg-slate-50 px-2 py-1 text-[9px] font-black uppercase tracking-widest text-slate-400 dark:bg-zinc-800 dark:text-zinc-500">
                {{ stage.is_won ? 'Won — create client account' : 'Lost — archive to close' }}
            </p>
        </div>

        <draggable
            v-model="dragList"
            group="opps"
            item-key="id"
            :disabled="!canEdit"
            ghost-class="opacity-40"
            chosen-class="pipeline-chosen"
            drag-class="rotate-2"
            animation="180"
            class="min-h-[80px] flex-1 touch-pan-y space-y-2.5 overflow-y-auto rounded-2xl pb-1 pr-1"
            @change="onCardChange"
        >
                            <template #item="{ element }">
                                <OpportunityCard
                                    :opportunity="element"
                                    :can-edit="canEdit"
                                    :can-upload="!!stage.allows_uploads"
                                    :is-won="!!stage.is_won"
                                    :is-lost="!!stage.is_lost"
                                    @schedule="(o) => emit('schedule', o)"
                                    @open-activity="(payload) => emit('open-activity', payload)"
                                    @upload-docs="(o) => emit('upload-docs', o)"
                                />
                            </template>
        </draggable>

        <div v-if="!visible.length" class="rounded-2xl border-2 border-dashed border-gray-100 px-3 py-6 text-center dark:border-zinc-800">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-300 dark:text-zinc-600">
                {{ activityFilter ? 'No match for filter' : 'Empty — drag a card here' }}
            </p>
        </div>
    </div>
</template>

<style scoped>
/* Single-token class for vuedraggable's chosen-class (it uses classList.add,
   so multi-class Tailwind strings like "ring-2 ring-indigo-400" throw). */
.pipeline-chosen { box-shadow: 0 0 0 2px #818cf8; }
</style>
