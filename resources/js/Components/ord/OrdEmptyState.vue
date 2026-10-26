<script setup>
import { Inbox } from 'lucide-vue-next';

defineProps({
    title: { type: String, default: 'Nothing here yet' },
    message: { type: String, default: '' },
    icon: { type: Object, default: () => Inbox },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-zinc-800 dark:text-zinc-500">
            <component :is="icon" class="h-7 w-7" />
        </div>
        <p class="mt-3 text-sm font-black text-gray-700 dark:text-zinc-200">{{ title }}</p>
        <p v-if="message" class="mt-1 max-w-sm text-xs text-gray-500 dark:text-zinc-400">{{ message }}</p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
