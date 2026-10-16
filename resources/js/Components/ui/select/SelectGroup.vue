<script setup>
import { computed } from "vue";
import { SelectGroup } from "radix-vue";
import { cn } from "@/lib/utils";

const props = defineProps({
    asChild: { type: Boolean, required: false },
    as: { type: null, required: false },
    class: { type: null, required: false },
});

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;
    return delegated;
});
</script>

<template>
    <SelectGroup v-bind="delegatedProps" :class="cn('p-1 w-full', props.class)">
        <slot />
    </SelectGroup>
</template>