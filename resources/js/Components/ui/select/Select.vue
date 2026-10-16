<script setup>
import { SelectRoot, useForwardPropsEmits } from 'radix-vue'

const props = defineProps(['open', 'defaultOpen', 'value', 'defaultValue', 'modelValue', 'dir', 'name', 'autocomplete', 'disabled', 'required'])
const emits = defineEmits(['update:modelValue', 'update:open'])

const forwarded = useForwardPropsEmits(props, emits)
</script>

<template>
    <SelectRoot v-bind="forwarded">
        <slot />
    </SelectRoot>
</template>