<script setup>
defineProps({
    order: { type: Object, required: true },
    showRecipe: { type: Boolean, default: true },
});
</script>

<template>
    <details open class="mt-3 rounded-lg bg-slate-50 p-3 text-xs dark:bg-zinc-800">
        <summary class="cursor-pointer font-bold">JO details and linked recipe <span v-if="order.recipe">· Recipe #{{ order.recipe.id }}</span></summary>
        <div v-if="order.job_order" class="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-2">
            <p v-if="order.job_order.control_number"><b>Control number:</b> {{ order.job_order.control_number }}</p>
            <p v-if="order.job_order.recipe_id"><b>Recipe link:</b> #{{ order.job_order.recipe_id }}</p>
            <p><b>JO quantity:</b> {{ order.job_order.quantity }} kg</p>
            <p><b>Color:</b> {{ order.job_order.color || '—' }}</p>
            <p><b>Yarn:</b> {{ order.job_order.yarn_type || '—' }}</p>
            <p><b>Design:</b> {{ order.job_order.design || '—' }}</p>
            <p v-if="order.job_order.expected_ship_date"><b>Expected ship date:</b> {{ order.job_order.expected_ship_date }}</p>
            <p v-if="order.job_order.priority"><b>Priority:</b> {{ order.job_order.priority }}</p>
            <p v-if="order.manufacturing_order"><b>Production quantity:</b> {{ order.manufacturing_order.total_quantity }} kg</p>
            <p v-if="order.manufacturing_order"><b>Production status:</b> {{ order.manufacturing_order.status }}</p>
            <p v-if="order.manufacturing_order?.notes" class="sm:col-span-2"><b>Production notes:</b> {{ order.manufacturing_order.notes }}</p>
        </div>
        <div v-if="showRecipe && order.recipe" class="mt-3 space-y-2 border-t border-slate-200 pt-3 dark:border-zinc-700">
            <p v-if="order.recipe.product"><b>Product:</b> {{ order.recipe.product }}</p>
            <p v-if="order.recipe.yarn_type"><b>Recipe yarn:</b> {{ order.recipe.yarn_type }}</p>
            <p v-if="order.recipe.dye_color"><b>Recipe dye color:</b> {{ order.recipe.dye_color }}</p>
            <p v-if="order.recipe.weave_design"><b>Recipe design:</b> {{ order.recipe.weave_design }}</p>
            <div v-for="group in order.recipe.material_groups || []" :key="group.label" class="rounded-md border border-slate-200 p-2 dark:border-zinc-700">
                <p class="mb-1 font-bold">{{ group.label }}</p>
                <p v-for="material in group.materials" :key="material.material_id" class="flex justify-between gap-3">
                    <span>{{ material.name }}<small v-if="material.calculation" class="block text-[10px] font-normal text-slate-500">Lab: {{ material.lab_quantity }} {{ material.lab_unit }} · {{ material.calculation }}</small></span>
                    <span class="shrink-0 font-semibold">{{ material.quantity }} {{ material.unit || '' }}</span>
                </p>
                <p v-if="!group.materials.length" class="text-slate-500">None listed</p>
            </div>
        </div>
        <p v-else-if="showRecipe" class="mt-2 text-slate-500">No recipe is linked to this Job Order.</p>
    </details>
</template>
