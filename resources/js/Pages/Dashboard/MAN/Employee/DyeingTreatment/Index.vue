<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import WorkSubmissionDialogs from '@/Components/Manufacturing/WorkSubmissionDialogs.vue'

const props = defineProps({ fabrics: { type: Array, default: () => [] }, materials: { type: Array, default: () => [] }, assignedOrders: { type: Array, default: () => [] }, treatmentLabel: { type: String, default: 'Dyeing Treatment' }, routePrefix: String })
const selectedGroup = ref(null), selectedBatchId = ref(''), selectedIds = ref([])
const usageRows = ref([{ inventory_item_id: '', quantity_used: '' }])
const form = useForm({ fabric_ids: [], materials_used: [] })
const confirmWork = ref(false), workSubmitting = ref(false), workResult = ref(null)
const uniqueAssignedOrders = computed(() => {
    const seen = new Set()
    return props.assignedOrders.filter(order => {
        const key = `${order.stage || ''}:${order.manufacturing_order_id || order.sales_order_id || order.reference}`
        if (seen.has(key)) return false
        seen.add(key)
        return true
    })
})
const batchId = f => f.dyeing_batch_fabric?.dyeing_batch_id ?? f.dyeing_batch_fabric?.batch?.id
const joGroups = computed(() => {
    const groups = new Map()
    for (const fabric of props.fabrics) {
        const key = fabric.sales_order_id ?? fabric.manufacturing_order_id
        if (!groups.has(key)) {
            const jo = fabric.sales_order?.jo_number
            const assignment = props.assignedOrders.find(order => order.reference === jo)
            groups.set(key, { key, reference: jo ?? `MO-${fabric.manufacturing_order_id}`, client: assignment?.client, recipe: assignment?.recipe, fabrics: [] })
        }
        groups.get(key).fabrics.push(fabric)
    }
    return [...groups.values()]
})
const batchOptions = computed(() => {
    if (!selectedGroup.value) return []
    const batches = new Map()
    for (const fabric of selectedGroup.value.fabrics) {
        const id = batchId(fabric)
        if (id && !batches.has(id)) batches.set(id, { id, machine: fabric.dyeing_batch_fabric?.batch?.machine?.machine_no, capacity: Number(fabric.dyeing_batch_fabric?.batch?.load_capacity_kg ?? 0) })
    }
    return [...batches.values()]
})
const selectedMachine = computed(() => batchOptions.value.find(batch => String(batch.id) === String(selectedBatchId.value))?.machine)
const candidates = computed(() => selectedGroup.value?.fabrics.filter(f => !selectedMachine.value || f.dyeing_batch_fabric?.batch?.machine?.machine_no === selectedMachine.value) ?? [])
const selectedWeight = computed(() => candidates.value.filter(f => selectedIds.value.includes(f.id)).reduce((sum, f) => sum + Number(f.weight || 0), 0))
const selectedCapacity = computed(() => batchOptions.value.filter(batch => batch.machine === selectedMachine.value).reduce((sum, batch) => sum + batch.capacity, 0))
function batchesFor(group) { return new Set(group.fabrics.map(batchId).filter(Boolean)).size }
function open(group) { selectedGroup.value = group; selectedBatchId.value = String(batchOptions.value[0]?.id ?? ''); selectedIds.value = []; usageRows.value = [{ inventory_item_id: '', quantity_used: '' }]; form.clearErrors() }
function close() { selectedGroup.value = null; selectedIds.value = []; form.reset(); form.clearErrors() }
function requestSubmit() {
    workResult.value = null
    confirmWork.value = true
}
function saveWork() {
    if (workSubmitting.value) return
    workSubmitting.value = true
    form.fabric_ids = selectedIds.value
    form.materials_used = usageRows.value.filter(row => row.inventory_item_id && Number(row.quantity_used) > 0).map(row => ({ inventory_item_id: Number(row.inventory_item_id), quantity_used: Number(row.quantity_used) }))
    form.post(route(`${props.routePrefix}.submit`), {
        preserveScroll: true,
        onSuccess: page => {
            const error = page.props.flash?.error
            workResult.value = error
                ? { success: false, title: 'Could not record work', message: error }
                : { success: true, title: 'Work recorded', message: page.props.flash?.message || `${props.treatmentLabel} was recorded successfully.` }
            confirmWork.value = false
            if (!error) close()
        },
        onError: errors => {
            workResult.value = { success: false, title: 'Could not record work', message: Object.values(errors || {}).flat().find(Boolean) || 'The server could not save this work record. Review the form and try again.' }
            confirmWork.value = false
        },
        onHttpException: () => {
            workResult.value = { success: false, title: 'Could not record work', message: 'The server returned an error while saving. Your form is still open so you can try again.' }
            confirmWork.value = false
        },
        onFinish: () => { workSubmitting.value = false },
    })
}
</script>

<template>
    <Head :title="treatmentLabel" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-indigo-700 to-violet-700 p-6 text-white"><p class="text-xs font-bold uppercase tracking-widest">Dyeing Department</p><h1 class="text-2xl font-black">{{ treatmentLabel }}</h1><p class="mt-1 text-sm">Process fabric codes together from each assigned Job Order.</p></header>
            <section v-if="uniqueAssignedOrders.some(order => order.deadline)" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20"><h2 class="text-sm font-black text-amber-900 dark:text-amber-200">Assignment deadlines</h2><p v-for="order in uniqueAssignedOrders.filter(item => item.deadline)" :key="`${order.stage}:${order.manufacturing_order_id || order.sales_order_id || order.reference}`" class="mt-2 flex justify-between gap-3 text-xs text-amber-800 dark:text-amber-100"><span class="font-mono font-bold">{{ order.reference }}</span><span class="font-bold">{{ new Date(`${order.deadline}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) }}</span></p></section>
            <section v-if="uniqueAssignedOrders.length" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="font-black">Assigned Job Orders</h2><div class="mt-3 grid gap-3 sm:grid-cols-2"><article v-for="order in uniqueAssignedOrders" :key="`${order.stage}:${order.manufacturing_order_id || order.sales_order_id || order.reference}`" class="rounded-xl border p-3 dark:border-zinc-700"><p class="font-mono text-sm font-black">{{ order.reference }}</p><p v-if="order.client" class="text-xs text-slate-500">{{ order.client }}</p><div v-if="order.recipe" class="mt-2"><p class="text-xs"><b>Product:</b> {{ order.recipe.product || '—' }} · <b>Yarn:</b> {{ order.recipe.yarn_type || '—' }}</p><p class="text-xs"><b>Color:</b> {{ order.recipe.dye_color || '—' }} · <b>Design:</b> {{ order.recipe.weave_design || '—' }}</p><details class="mt-2"><summary class="cursor-pointer text-xs font-bold">Full recipe</summary><div class="mt-2 space-y-2"><section v-for="section in order.recipe.material_groups || []" :key="section.label" class="rounded-lg bg-slate-50 p-2 text-xs dark:bg-zinc-800"><p class="font-bold">{{ section.label }}</p><p v-if="!section.materials?.length" class="text-slate-500">No materials listed</p><p v-for="material in section.materials || []" :key="material.material_id">{{ material.name }} · {{ material.quantity }} {{ material.unit || '' }}</p></section></div></details></div><p v-else class="mt-2 text-xs text-slate-500">No linked recipe found.</p></article></div></section>
            <section class="space-y-3"><h2 class="px-1 font-black">Fabric batches ready for {{ treatmentLabel }}</h2><p v-if="!joGroups.length" class="rounded-2xl border border-dashed p-8 text-center text-sm text-slate-500">{{ assignedOrders.length ? `Your assigned Job Orders have no batches ready for ${treatmentLabel} yet.` : `No Job Orders are assigned to your ${treatmentLabel} stage yet. The supervisor can assign one from JO Assignments.` }}</p>
                <article v-for="group in joGroups" :key="group.key" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-mono text-lg font-black">{{ group.reference }}</p><p v-if="group.client" class="text-sm text-slate-500">{{ group.client }}</p><p class="mt-1 text-sm text-slate-500">{{ group.fabrics.length }} fabric code(s) · {{ group.fabrics.reduce((sum,f)=>sum+Number(f.weight||0),0).toFixed(2) }} kg across {{ batchesFor(group) }} machine batch(es)</p></div><button @click="open(group)" class="rounded-lg bg-indigo-700 px-4 py-2.5 text-sm font-bold text-white">Record {{ treatmentLabel }} for this JO</button></div>
                    <details v-if="group.recipe" class="mt-3 rounded-xl bg-slate-50 p-3 dark:bg-zinc-800"><summary class="cursor-pointer text-sm font-bold">Full approved recipe</summary><p class="mt-2 text-xs"><b>Product:</b> {{ group.recipe.product || '—' }} · <b>Yarn:</b> {{ group.recipe.yarn_type || '—' }} · <b>Color:</b> {{ group.recipe.dye_color || '—' }} · <b>Design:</b> {{ group.recipe.weave_design || '—' }}</p><div class="mt-2 grid gap-2 sm:grid-cols-2"><section v-for="section in group.recipe.material_groups || []" :key="section.label" class="rounded-lg border bg-white p-2 text-xs dark:border-zinc-700 dark:bg-zinc-900"><p class="font-bold">{{ section.label }}</p><p v-if="!section.materials?.length" class="text-slate-500">None listed</p><p v-for="material in section.materials || []" :key="material.material_id">{{ material.name }} · {{ material.quantity }} {{ material.unit || '' }}</p></section></div></details>
                    <div class="mt-3 flex flex-wrap gap-2"><span v-for="fabric in group.fabrics" :key="fabric.id" class="rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs dark:bg-indigo-500/10"><b class="font-mono">{{ fabric.code }}</b> · {{ fabric.weight }} kg · {{ fabric.dyeing_batch_fabric?.batch?.machine?.machine_no }}</span></div>
                </article>
            </section>
        </main>
        <div v-if="selectedGroup" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="close"><form class="max-h-[90vh] w-full max-w-2xl space-y-4 overflow-y-auto rounded-2xl bg-white p-5 shadow-xl dark:bg-zinc-900" @submit.prevent="requestSubmit"><header><h2 class="text-lg font-black">Record {{ treatmentLabel }} · {{ selectedGroup.reference }}</h2><p class="mt-1 text-sm text-slate-500">Choose a machine batch, then select fabric codes that fit its load capacity.</p></header>
            <label v-if="batchOptions.length > 1" class="block text-sm font-bold">Machine batch<select v-model="selectedBatchId" @change="selectedIds=[]" required class="mt-1 w-full rounded-lg border p-3 dark:border-zinc-700 dark:bg-zinc-800"><option v-for="batch in batchOptions" :key="batch.id" :value="String(batch.id)">{{ batch.machine }} · {{ batch.capacity }} kg capacity</option></select></label>
            <p class="text-xs text-slate-500">Machine {{ selectedMachine }} · Selected load {{ selectedWeight.toFixed(2) }} / {{ selectedCapacity }} kg across this machine's batches</p>
            <section class="space-y-2"><label v-for="fabric in candidates" :key="fabric.id" class="flex items-center justify-between rounded-lg border p-3 text-sm dark:border-zinc-700"><span class="flex items-center gap-2"><input v-model="selectedIds" type="checkbox" :value="fabric.id"><span class="font-mono font-bold">{{ fabric.code }}</span></span><span>{{ fabric.weight }} kg</span></label><p v-if="form.errors.fabric_ids" class="text-xs text-rose-600">{{ form.errors.fabric_ids }}</p></section>
            <section><div class="mb-2 flex items-center justify-between"><h3 class="font-bold">{{ routePrefix.includes('pre-treatment') ? 'Pre-treatment materials used (kg)' : 'Post-treatment materials used (kg)' }}</h3><button type="button" @click="usageRows.push({ inventory_item_id: '', quantity_used: '' })" class="text-sm font-bold text-indigo-700">+ Add material</button></div><div v-for="(row,index) in usageRows" :key="index" class="mb-2 grid grid-cols-[1fr_130px_auto] gap-2"><select v-model="row.inventory_item_id" required class="rounded-lg border p-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"><option value="">Select {{ routePrefix.includes('pre-treatment') ? 'pre-treatment' : 'post-treatment' }} material</option><option v-for="material in materials" :key="material.id" :value="material.id">{{ material.name }} · {{ material.control_number }} ({{ material.remaining_quantity }} kg)</option></select><input v-model="row.quantity_used" required type="number" min="0.01" step="0.01" class="rounded-lg border p-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" placeholder="kg used"><button v-if="usageRows.length>1" type="button" @click="usageRows.splice(index,1)" class="px-2 text-rose-600">Remove</button></div><p v-if="form.errors.materials_used" class="text-xs text-rose-600">{{ form.errors.materials_used }}</p></section>
            <footer class="flex justify-end gap-2"><button type="button" @click="close" class="rounded-lg border px-4 py-2 text-sm font-bold dark:border-zinc-700">Cancel</button><button type="submit" :disabled="form.processing || !selectedIds.length || (selectedCapacity > 0 && selectedWeight > selectedCapacity)" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Submit for supervisor review</button></footer></form></div>
        <WorkSubmissionDialogs :show-confirm="confirmWork" :confirm-title="`Confirm ${treatmentLabel}`" :confirm-message="`Record ${treatmentLabel} for ${selectedGroup?.reference || 'this JO'}?`" :confirm-detail="`${selectedIds.length} fabric code(s) selected.`" :submitting="workSubmitting" :result="workResult" @cancel="confirmWork = false" @confirm="saveWork" @close-result="workResult = null" />
    </AuthenticatedLayout>
</template>
