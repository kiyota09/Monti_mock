<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'empty' },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    actionLabel: { type: String, default: '' },
    retryLabel: { type: String, default: '' },
});
const emit = defineEmits(['action', 'retry']);
const content = computed(() => ({
    empty: ['Nothing here yet', 'Records will appear here when they become available.', ''],
    loading: ['Loading', 'Please wait while we retrieve this information.', ''],
    error: ['Unable to load this information', 'Something went wrong. Try again in a moment.', 'Try again'],
    offline: ['You are offline', 'Check your internet connection and try again.', 'Retry'],
    slow: ['Still working', 'Your connection is taking longer than usual. Please wait.', ''],
    'no-results': ['No search results', 'Try a different search term or clear your filters.', ''],
    permission: ['Access denied', 'Your account does not have permission to view this information.', ''],
    session: ['Session expired', 'Sign in again to continue.', 'Sign in'],
    success: ['Completed', 'Your changes were saved successfully.', ''],
}[props.type] || ['Information', '', '']));
</script>

<template>
    <section class="mx-auto flex min-h-52 w-full max-w-2xl flex-col items-center justify-center px-5 py-10 text-center" :aria-live="type === 'loading' ? 'polite' : 'assertive'">
        <span v-if="type === 'loading'" class="mb-4 h-8 w-8 animate-spin rounded-full border-4 border-emerald-600 border-r-transparent" role="status" aria-label="Loading" />
        <span v-else class="mb-3 grid h-11 w-11 place-items-center rounded-full bg-slate-100 text-xl font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-200" aria-hidden="true">{{ ({ success: '✓', error: '!', offline: '⌁', slow: '…', permission: '!', session: '⌛', empty: '○', 'no-results': '⌕' })[type] || 'i' }}</span>
        <h2 class="text-base font-semibold text-slate-900 dark:text-zinc-100">{{ title || content[0] }}</h2>
        <p class="mt-1 max-w-lg text-sm text-slate-600 dark:text-zinc-300">{{ message || content[1] }}</p>
        <div class="mt-4 flex gap-2">
            <button v-if="actionLabel || content[2]" type="button" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800" @click="emit(type === 'error' || type === 'offline' ? 'retry' : 'action')">{{ actionLabel || content[2] }}</button>
            <button v-if="retryLabel" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-zinc-600 dark:text-zinc-100" @click="emit('retry')">{{ retryLabel }}</button>
        </div>
    </section>
</template>
