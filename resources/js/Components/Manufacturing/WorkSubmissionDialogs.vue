<script setup>
defineProps({
    showConfirm: { type: Boolean, default: false },
    confirmTitle: { type: String, default: 'Confirm submission' },
    confirmMessage: { type: String, default: 'Save this work record?' },
    confirmDetail: { type: String, default: '' },
    submitting: { type: Boolean, default: false },
    result: { type: Object, default: null },
});

const emit = defineEmits(['cancel', 'confirm', 'close-result']);
</script>

<template>
    <div v-if="showConfirm" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="work-confirm-title">
        <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
            <h2 id="work-confirm-title" class="text-lg font-black text-slate-900 dark:text-white">{{ confirmTitle }}</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">{{ confirmMessage }}</p>
            <p v-if="confirmDetail" class="mt-2 text-xs text-slate-500 dark:text-zinc-400">{{ confirmDetail }}</p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" :disabled="submitting" @click="emit('cancel')" class="rounded-lg border px-4 py-2 text-sm font-bold text-slate-600 disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-300">Cancel</button>
                <button type="button" :disabled="submitting" @click="emit('confirm')" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-black text-white disabled:opacity-50">{{ submitting ? 'Saving…' : 'Confirm and save' }}</button>
            </div>
        </section>
    </div>

    <div v-if="result" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-4" role="alertdialog" aria-modal="true" aria-labelledby="work-result-title">
        <section class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl dark:bg-zinc-900">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full text-2xl font-black" :class="result.success ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'">{{ result.success ? '✓' : '!' }}</div>
            <h2 id="work-result-title" class="mt-3 text-lg font-black text-slate-900 dark:text-white">{{ result.title || (result.success ? 'Work recorded' : 'Could not record work') }}</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">{{ result.message }}</p>
            <button type="button" @click="emit('close-result')" class="mt-5 rounded-lg bg-indigo-700 px-5 py-2 text-sm font-black text-white">Close</button>
        </section>
    </div>
</template>
