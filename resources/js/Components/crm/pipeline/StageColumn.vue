<script setup>
import { ref, computed } from 'vue';
import draggable from 'vuedraggable';
import { Settings2, Plus, Trophy, X } from 'lucide-vue-next';
import OpportunityCard from './OpportunityCard.vue';
import ActivityBar from './ActivityBar.vue';
import { peso, stageTotal, activityStateOf } from '@/composables/crm/usePipeline';

const props = defineProps({
    stage: { type: Object, required: true },
    opportunities: { type: Array, default: () => [] },
    activityFilter: { type: String, default: null },
    canEdit: { type: Boolean, default: false },
    menuOpen: { type: Boolean, default: false },
});
const emit = defineEmits([
    'quick-create', 'schedule', 'menu', 'fold', 'edit', 'automations', 'ask-delete',
    'activity-filter', 'card-moved',
]);

const total = computed(() => stageTotal(props.opportunities));
const visible = computed(() =>
    props.activityFilter
        ? props.opportunities.filter((o) => activityStateOf(o) === props.activityFilter)
        : props.opportunities,
);
const isDraggingOver = ref(false);

const filterNames = { planned: 'Planned', today: 'Due today', overdue: 'Overdue', none: 'No activity' };

// vuedraggable shared group: server persistence happens in onChange
// (board moves the card + posts stage_id); totals recalc from fresh props.
const dragList = computed({
    get: () => [...visible.value],
    set: () => {},
});

const onCardChange = (e) => {
    isDraggingOver.value = false;
    if (e.added) emit('card-moved', { opportunity: e.added.element, toStage: props.stage });
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
        <span class="text-[10px] font-black text-indigo-500">{{ peso(total) }}</span>
    </button>

    <!-- Open column -->
    <div
        v-else
        :class="[
            'group w-72 shrink-0 rounded-3xl border p-3 backdrop-blur transition-colors',
            isDraggingOver
                ? 'border-indigo-400 bg-indigo-50/70 dark:border-indigo-500 dark:bg-indigo-950/30'
                : 'border-gray-100 bg-white/70 dark:border-zinc-800 dark:bg-zinc-900/70',
        ]"
    >
        <!-- Header: name + quick-create + gear -->
        <div class="px-1 pb-2">
            <div class="flex items-center gap-1">
                <p class="min-w-0 flex-1 truncate text-[11px] font-black uppercase tracking-widest" :title="stage.name">
                    {{ stage.name }}
                    <Trophy v-if="stage.is_won" class="ml-1 inline h-3 w-3 text-amber-400" />
                </p>
                <button
                    v-if="canEdit"
                    @click="emit('quick-create', stage)"
                    class="rounded-lg p-1 text-gray-400 transition hover:bg-indigo-50 hover:text-indigo-600"
                    :title="`New deal in ${stage.name}`"
                >
                    <Plus class="h-4 w-4" />
                </button>
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
                            <button @click="emit('automations', stage)" class="block w-full px-4 py-2 text-left text-xs font-bold hover:bg-slate-50 dark:hover:bg-zinc-800">Automations…</button>
                            <div class="my-1 border-t border-gray-100 dark:border-zinc-800" />
                            <button @click="emit('ask-delete', stage)" class="block w-full px-4 py-2 text-left text-xs font-bold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20">Delete…</button>
                        </div>
                    </template>
                </div>
            </div>
            <p class="mt-0.5 text-[10px] font-bold text-gray-400">
                {{ opportunities.length }} deal{{ opportunities.length === 1 ? '' : 's' }} · {{ peso(total) }}
                <span class="text-indigo-500">· {{ stage.default_probability }}%</span>
            </p>
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
            class="min-h-[80px] space-y-2.5 rounded-2xl"
            @change="onCardChange"
            @dragenter="isDraggingOver = true"
            @dragleave="isDraggingOver = false"
        >
            <template #item="{ element }">
                <OpportunityCard
                    :opportunity="element"
                    :can-edit="canEdit"
                    @schedule="(o) => emit('schedule', o)"
                />
            </template>
        </draggable>

        <div v-if="!visible.length" class="rounded-2xl border-2 border-dashed border-gray-100 px-3 py-6 text-center dark:border-zinc-800">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-300 dark:text-zinc-600">
                {{ activityFilter ? 'No match for filter' : 'Empty — drag a card here' }}
            </p>
        </div>

        <button
            v-if="canEdit"
            @click="emit('quick-create', stage)"
            class="mt-2 flex w-full items-center justify-center gap-1 rounded-xl py-2 text-[11px] font-black uppercase tracking-wide text-gray-300 transition hover:bg-indigo-50 hover:text-indigo-500 dark:text-zinc-600 dark:hover:bg-indigo-950/40"
        >
            <Plus class="h-3.5 w-3.5" /> Add deal
        </button>
    </div>
</template>

<style scoped>
/* Single-token class for vuedraggable's chosen-class (it uses classList.add,
   so multi-class Tailwind strings like "ring-2 ring-indigo-400" throw). */
.pipeline-chosen { box-shadow: 0 0 0 2px #818cf8; }
</style>
