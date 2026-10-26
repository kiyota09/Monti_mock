<script setup>
import { computed } from 'vue';
import { AlertTriangle, CheckCircle2, Info } from 'lucide-vue-next';
import ModalShell from '@/Components/crm/pipeline/ModalShell.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm action' },
    message: { type: String, default: '' },
    details: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    tone: { type: String, default: 'primary' },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const toneMeta = computed(() => ({
    primary: { icon: Info, ring: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300', btn: 'bg-indigo-600 hover:bg-indigo-500' },
    warning: { icon: AlertTriangle, ring: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300', btn: 'bg-amber-600 hover:bg-amber-500' },
    danger: { icon: AlertTriangle, ring: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300', btn: 'bg-rose-600 hover:bg-rose-500' },
    success: { icon: CheckCircle2, ring: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300', btn: 'bg-emerald-600 hover:bg-emerald-500' },
}[props.tone] || {
    icon: Info, ring: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300', btn: 'bg-indigo-600 hover:bg-indigo-500',
}));
</script>

<template>
    <ModalShell v-if="open" size="sm" :dismissible="!processing" @close="emit('cancel')">
        <div class="p-6">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" :class="toneMeta.ring">
                    <component :is="toneMeta.icon" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-black text-gray-900 dark:text-white">{{ title }}</h3>
                    <p v-if="message" class="mt-1 text-sm text-gray-600 dark:text-zinc-300">{{ message }}</p>
                    <p v-if="details" class="mt-2 rounded-xl bg-gray-50 p-2.5 text-xs font-medium text-gray-500 dark:bg-zinc-800 dark:text-zinc-400">{{ details }}</p>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4 dark:border-zinc-800">
            <button type="button" :disabled="processing" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800" @click="emit('cancel')">
                {{ cancelLabel }}
            </button>
            <button type="button" :disabled="processing" class="rounded-xl px-4 py-2 text-sm font-black text-white disabled:opacity-50" :class="toneMeta.btn" @click="emit('confirm')">
                {{ processing ? 'Working…' : confirmLabel }}
            </button>
        </div>
    </ModalShell>
</template>
