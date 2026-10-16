<script setup>
import { computed } from 'vue';
import { activityCounts } from '@/composables/crm/usePipeline';

// Segmented per-stage activity bar: green planned / amber due-today / red overdue / gray none.
// Clicking a segment filters that stage's cards (emits select; click again to clear).
// Zero-count segments are not rendered so they never intercept clicks.
const props = defineProps({
    opportunities: { type: Array, default: () => [] },
    active: { type: String, default: null }, // 'planned'|'today'|'overdue'|'none'|null
});
const emit = defineEmits(['select']);

const counts = computed(() => activityCounts(props.opportunities));
const total = computed(() =>
    counts.value.planned + counts.value.today + counts.value.overdue + counts.value.none,
);
const pct = (n) => (total.value ? `${Math.max(Math.round((n / total.value) * 100), 8)}%` : '0%');
const toggle = (key) => emit('select', props.active === key ? null : key);

const SEGMENTS = [
    { key: 'planned', label: 'Planned', cls: 'bg-emerald-500' },
    { key: 'today', label: 'Due today', cls: 'bg-amber-400' },
    { key: 'overdue', label: 'Overdue', cls: 'bg-rose-500' },
    { key: 'none', label: 'No activity', cls: 'bg-slate-300 dark:bg-zinc-600' },
];
</script>

<template>
    <div
        class="flex h-1.5 w-full gap-px overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800"
        role="img"
        :aria-label="`Activities: ${counts.planned} planned, ${counts.today} due today, ${counts.overdue} overdue, ${counts.none} without activity`"
        :title="`Planned ${counts.planned} · Due today ${counts.today} · Overdue ${counts.overdue} · None ${counts.none} — click a segment to filter`"
    >
        <button
            v-for="s in SEGMENTS.filter((s) => counts[s.key] > 0)"
            :key="s.key"
            @click.stop="toggle(s.key)"
            :style="{ width: pct(counts[s.key]) }"
            :class="[
                s.cls,
                'h-full transition-all hover:opacity-80 focus:outline-none focus-visible:ring-1 focus-visible:ring-indigo-500',
                active === s.key ? 'ring-1 ring-inset ring-indigo-600' : '',
            ]"
            :title="`${s.label}: ${counts[s.key]} — click to filter`"
            :aria-pressed="active === s.key"
        />
    </div>
</template>
