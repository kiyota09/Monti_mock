<script setup>
import { SelectValue } from 'radix-vue'

const props = defineProps(['placeholder'])
</script>

<template>
    <SelectValue v-bind="props">
        <slot />
    </SelectValue>
</template>