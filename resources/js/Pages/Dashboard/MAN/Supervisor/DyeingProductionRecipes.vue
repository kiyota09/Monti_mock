<script setup>
import { Head, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({ orders: { type: Array, default: () => [] }, groupDefinitions: { type: Array, default: () => [] }, materials: { type: Array, default: () => [] } })
const editing = ref(null)
const drafts = reactive({})
const busy = ref(false)
const doseFor = (key) => key === 'dyestuffs' ? 'pct' : 'gpl'
const editOrder = (order) => {
    drafts[order.id] = {
        liquor_ratio: Number(order.liquor_ratio),
        groups: Object.fromEntries(props.groupDefinitions.map(({ key }) => [key, (order.groups[key] || []).map(row => ({ material_id: row.material_id, [doseFor(key)]: Number(row.lab_amount) }))])),
    }
    editing.value = order.id
}
const addRow = (order, key) => drafts[order.id].groups[key].push({ material_id: null, [doseFor(key)]: 0 })
const saveOrder = (order) => {
    busy.value = true
    router.put(route('man.dyeing-production-recipes.update', order.id), drafts[order.id], { preserveScroll: true, onFinish: () => { busy.value = false; editing.value = null } })
}
</script>

<template>
    <Head title="Dyeing Production Recipes" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-indigo-700 to-violet-700 p-6 text-white">
                <p class="text-xs font-bold uppercase tracking-widest">Dyeing Department · Supervisor</p>
                <h1 class="mt-1 text-2xl font-black">Production Recipes</h1>
                <p class="mt-2 max-w-3xl text-sm text-indigo-100">Approved Dyeing Lab Chemist formulas are automatically converted using each Job Order’s total fabric weight. Each generated quantity includes its calculation description.</p>
            </header>

            <section v-if="!orders.length" class="rounded-2xl border bg-white p-8 text-center text-sm text-slate-500 dark:border-zinc-800 dark:bg-zinc-900">
                No dyeing Job Orders with an approved linked recipe have entered production yet.
            </section>

            <details v-for="order in orders" :key="order.id" class="group overflow-hidden rounded-2xl border bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 p-5 [&::-webkit-details-marker]:hidden">
                    <div>
                        <h2 class="font-mono text-lg font-black">{{ order.jo_number }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ order.client || 'Client not listed' }}<span v-if="order.product"> · {{ order.product }}</span></p>
                    </div>
                    <div class="rounded-xl bg-indigo-50 px-4 py-2 text-right dark:bg-indigo-500/10">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-300">Production batch</p>
                        <p class="text-lg font-black">{{ order.production_weight_kg }} kg</p>
                    </div>
                    <button type="button" class="rounded-lg border px-3 py-2 text-xs font-bold" @click.stop="editing === order.id ? editing = null : editOrder(order)">{{ editing === order.id ? 'Close editor' : 'Edit recipe' }}</button>
                    <span class="w-full text-xs font-semibold text-indigo-700 dark:text-indigo-300 sm:w-auto sm:pl-2">Click to view conversion</span>
                </summary>

                <div class="space-y-4 border-t p-5 dark:border-zinc-800">
                    <section v-if="editing === order.id" class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900 dark:bg-indigo-950/20">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="font-black">Edit this JO’s production recipe</h3>
                            <label class="text-xs font-bold">Liquor ratio (L/kg) <input v-model.number="drafts[order.id].liquor_ratio" type="number" min="0.01" step="0.1" class="ml-2 w-24 rounded-lg border p-2 text-sm text-slate-900" /></label>
                        </div>
                        <div v-for="group in groupDefinitions" :key="group.key" class="mt-4 rounded-lg border bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-center justify-between"><h4 class="text-sm font-black">{{ group.label }}</h4><button type="button" class="text-xs font-bold text-indigo-600" @click="addRow(order, group.key)">+ Add material</button></div>
                            <div v-for="(row, idx) in drafts[order.id].groups[group.key]" :key="idx" class="mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_auto]">
                                <select v-model="row.material_id" class="rounded-lg border p-2 text-sm dark:bg-zinc-800"><option :value="null">Choose material</option><option v-for="material in materials" :key="material.id" :value="material.id">{{ material.name }}</option></select>
                                <label class="flex items-center gap-2"><input v-model.number="row[doseFor(group.key)]" type="number" min="0" step="0.0001" class="w-full rounded-lg border p-2 text-sm dark:bg-zinc-800"/><span class="text-xs text-slate-500">{{ group.key === 'dyestuffs' ? '% OWF' : 'g/L' }}</span></label>
                                <button type="button" class="rounded-lg px-2 text-xs font-bold text-rose-600" @click="drafts[order.id].groups[group.key].splice(idx, 1)">Remove</button>
                            </div>
                            <p v-if="!drafts[order.id].groups[group.key].length" class="mt-2 text-xs text-slate-500">No materials. Use Add material to create a row.</p>
                        </div>
                        <div class="mt-4 flex justify-end"><button type="button" :disabled="busy" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50" @click="saveOrder(order)">{{ busy ? 'Saving…' : 'Save recipe for this JO' }}</button></div>
                    </section>
                    <section v-for="group in groupDefinitions" :key="group.key" class="overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-700">
                        <h3 class="border-b bg-slate-50 px-4 py-3 text-sm font-black dark:border-zinc-700 dark:bg-zinc-800">{{ group.label }}</h3>
                        <div v-if="order.groups[group.key]?.length" class="divide-y dark:divide-zinc-800">
                            <div class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_8rem_14rem] sm:items-center">
                                <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Material</span>
                                <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Lab sample</span>
                                <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Generated formulation · {{ order.production_weight_kg }} kg</span>
                            </div>
                            <div v-for="(row, index) in order.groups[group.key]" :key="`${row.material_id}-${index}`" class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_8rem_14rem] sm:items-center">
                                <span class="text-sm font-semibold">{{ row.name }}</span>
                                <span class="text-sm text-slate-600 dark:text-zinc-300">{{ row.lab_amount }} {{ row.lab_unit }}</span>
                                <span class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">{{ row.calculated_amount }} kg<span class="block text-[10px] font-normal text-slate-500">{{ row.calculation }}</span></span>
                            </div>
                        </div>
                        <p v-else class="px-4 py-3 text-sm text-slate-500">No materials listed in this section.</p>
                    </section>
                </div>
            </details>
        </main>
    </AuthenticatedLayout>
</template>
