<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Star, Phone, CalendarDays, Bell, ClipboardCheck, Clock } from 'lucide-vue-next';
import {
    activityStateOf, STATE_META, peso, initialsOf, dueLabel,
} from '@/composables/crm/usePipeline';

const props = defineProps({
    opportunity: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
});
const emit = defineEmits(['schedule']);

const o = computed(() => props.opportunity);
const state = computed(() => activityStateOf(o.value));
const meta = computed(() => STATE_META[state.value]);
const due = computed(() => o.value.next_activity?.due ?? null);

const activityIcon = computed(() => {
    const t = o.value.next_activity?.type;
    if (!o.value.next_activity) return Clock;
    if (t === 'call') return Phone;
    if (t === 'meeting') return CalendarDays;
    if (t === 'reminder') return Bell;
    return ClipboardCheck; // todo + legacy note/task/email
});

const dueText = computed(() => {
    if (!o.value.next_activity) return 'No activity — click + to schedule';
    const kind = o.value.next_activity.type === 'todo' ? 'To-do' : o.value.next_activity.type;
    return `${kind} · ${dueLabel(due.value)}`;
});

const setPriority = (n) => {
    if (!props.canEdit) return;
    router.post(
        route('crm.opportunities.priority', o.value.id),
        { priority: n },
        { preserveScroll: true },
    );
};
</script>

<template>
    <div
        class="cursor-pointer rounded-2xl border border-gray-100 bg-white p-3.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-900"
    >
        <Link :href="route('crm.opportunities.show', o.id)" class="block" :title="o.title">
            <p class="truncate text-sm font-black hover:text-indigo-600">{{ o.title }}</p>
        </Link>
        <p class="mt-0.5 truncate text-[11px] font-bold text-gray-400">
            {{ o.contact_name }}<span v-if="o.organization"> · {{ o.organization }}</span>
        </p>
        <div class="mt-1.5 flex items-baseline justify-between gap-2">
            <p class="text-sm font-black text-indigo-600">{{ peso(o.value) }}</p>
            <p v-if="o.expected_close" class="shrink-0 text-[10px] font-bold text-gray-400">
                closes {{ new Date(o.expected_close).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) }}
            </p>
        </div>

        <!-- Next-activity context line -->
        <button
            @click.stop="emit('schedule', o)"
            :class="['mt-1.5 flex w-full items-center gap-1.5 truncate rounded-lg px-1.5 py-1 text-left text-[11px] font-bold transition hover:bg-slate-100 dark:hover:bg-zinc-800', meta.text]"
            :title="o.next_activity ? `${o.next_activity.summary ?? ''} — click to schedule` : 'No activity scheduled — click to schedule'"
        >
            <component :is="activityIcon" class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ dueText }}</span>
        </button>

        <div class="mt-1.5 flex items-center justify-between border-t border-slate-50 pt-2 dark:border-zinc-800">
            <!-- Priority stars 0-3, clickable -->
            <div class="flex items-center gap-0.5" title="Priority — click to set">
                <button
                    v-for="n in [1, 2, 3]"
                    :key="n"
                    @click.stop="setPriority(n)"
                    :disabled="!canEdit"
                    :class="[
                        'transition hover:scale-125',
                        n <= (o.priority || 0) ? 'text-amber-400' : 'text-gray-200 dark:text-zinc-700',
                        canEdit ? '' : 'cursor-default',
                    ]"
                    :title="`Set priority ${n}`"
                    :aria-label="`Set priority ${n}`"
                >
                    <Star class="h-3.5 w-3.5" :fill="n <= (o.priority || 0) ? 'currentColor' : 'none'" />
                </button>
                <button
                    v-if="(o.priority || 0) > 0 && canEdit"
                    @click.stop="setPriority(0)"
                    class="ml-1 text-[9px] font-bold text-gray-300 hover:text-rose-400"
                    title="Clear priority"
                >
                    ✕
                </button>
            </div>

            <div class="flex items-center gap-1.5">
                <span
                    v-if="o.owner?.name"
                    :title="`Salesperson: ${o.owner.name}`"
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-[9px] font-black text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                >
                    {{ initialsOf(o.owner.name) }}
                </span>
                <span class="text-[10px] font-bold uppercase tracking-wide text-gray-300 dark:text-zinc-600">
                    {{ o.probability }}%
                </span>
            </div>
        </div>
    </div>
</template>
