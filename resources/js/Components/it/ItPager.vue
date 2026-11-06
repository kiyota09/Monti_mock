<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    paginator: { type: Object, default: () => ({}) },
    routeName: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
});

const current = computed(() => props.paginator.current_page || 1);
const last = computed(() => props.paginator.last_page || 1);
const total = computed(() => props.paginator.total || 0);
const from = computed(() => props.paginator.from || 0);
const to = computed(() => props.paginator.to || 0);

const go = (page) => {
    if (page < 1 || page > last.value || page === current.value) return;
    router.get(route(props.routeName), { ...props.filters, page }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <div v-if="total > 0"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/40 sm:px-6">
        <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">
            {{ from }}–{{ to }} of {{ total }}
        </p>
        <div class="flex items-center gap-1.5">
            <button @click="go(current - 1)" :disabled="current <= 1" title="Previous page"
                class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <ChevronLeft class="h-3.5 w-3.5" /> Prev
            </button>
            <span class="rounded-xl bg-indigo-50 px-3 py-1.5 font-mono text-xs font-black text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                {{ current }} / {{ last }}
            </span>
            <button @click="go(current + 1)" :disabled="current >= last" title="Next page"
                class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                Next <ChevronRight class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>
</template>
