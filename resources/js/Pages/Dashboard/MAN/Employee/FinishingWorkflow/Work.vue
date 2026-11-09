<script setup>
import { reactive, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import WorkSubmissionDialogs from '@/Components/Manufacturing/WorkSubmissionDialogs.vue'

const props = defineProps({
    orders: { type: Array, default: () => [] },
    stage: String,
    methodLabel: String,
    routePrefix: String,
    chemicalMaterials: { type: Array, default: () => [] },
})

const forms = reactive({})
const pendingItem = ref(null)
const confirmWork = ref(false)
const workSubmitting = ref(false)
const workResult = ref(null)
props.orders.forEach((order) => order.items.forEach((item) => {
    forms[item.id] = { chemical_material_id: '', temperature_c: '', remarks: '' }
}))

function requestSubmit(item) {
    pendingItem.value = item
    workResult.value = null
    confirmWork.value = true
}

function submit() {
    if (!pendingItem.value || workSubmitting.value) return
    const item = pendingItem.value
    workSubmitting.value = true
    useForm({ ...forms[item.id] }).post(route(`${props.routePrefix}.work.complete`, item.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            workResult.value = { success: true, title: 'Work recorded', message: page.props.flash?.success || 'The completed roll was submitted successfully.' }
            confirmWork.value = false
        },
        onError: (errors) => {
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
    <Head :title="methodLabel" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-emerald-800 to-teal-600 p-6 text-white">
                <p class="text-xs font-black uppercase tracking-widest">Finishing Department</p>
                <h1 class="text-2xl font-black">{{ methodLabel }}</h1>
                <p class="mt-1 text-sm text-emerald-100">Process your supervisor-assigned JO and return each roll to the supervisor’s queue.</p>
            </header>

            <p v-if="!orders.length" class="rounded-2xl border border-dashed p-10 text-center text-slate-500">No fabrics are assigned to your station.</p>

            <section v-for="order in orders" :key="order.id" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-lg font-black">{{ order.jo_number }} · {{ order.method }}</h2>
                <p v-if="order.deadline" class="mt-1 text-xs font-bold text-amber-700 dark:text-amber-300">Assignment deadline: {{ new Date(`${order.deadline}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) }}</p>

                <div class="mt-3 rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 dark:border-indigo-500/20 dark:bg-indigo-500/10">
                    <p class="text-xs font-black uppercase text-indigo-800 dark:text-indigo-200">Recipe for this JO</p>
                    <template v-if="order.recipe">
                        <p class="mt-1 text-xs text-slate-700 dark:text-slate-200">
                            <b>{{ order.recipe.product || 'Product recipe' }}</b>
                            <span v-if="order.recipe.yarn_type"> · {{ order.recipe.yarn_type }}</span>
                            <span v-if="order.recipe.dye_color"> · {{ order.recipe.dye_color }}</span>
                            <span v-if="order.recipe.weave_design"> · {{ order.recipe.weave_design }}</span>
                            <span v-if="order.recipe.gsm"> · <b>GSM:</b> {{ order.recipe.gsm }}</span>
                            <span v-if="order.recipe.texture"> · <b>Texture:</b> {{ order.recipe.texture }}</span>
                        </p>
                        <div v-if="order.recipe.materials?.length" class="mt-2">
                            <p class="text-[10px] font-bold text-indigo-700 dark:text-indigo-300">Recipe materials</p>
                            <p v-for="(material, index) in order.recipe.materials" :key="`${material.name}-${index}`" class="mt-1 text-xs text-slate-700 dark:text-slate-200">
                                {{ material.name }}<span v-if="material.quantity !== null && material.quantity !== undefined"> · {{ material.quantity }} {{ material.unit || '' }}</span>
                            </p>
                        </div>
                        <div v-if="order.formula_source && order.formula_groups?.some((group) => group.materials?.length)" class="mt-2 border-t border-indigo-100 pt-2 dark:border-indigo-500/20">
                            <p class="text-[10px] font-bold text-indigo-700 dark:text-indigo-300">{{ order.formula_source }}</p>
                            <template v-for="group in order.formula_groups" :key="group.label">
                                <div v-if="group.materials?.length" class="mt-1">
                                    <p class="text-[10px] font-bold">{{ group.label }}</p>
                                    <p v-for="(material, index) in group.materials" :key="`${group.label}-${index}`" class="text-xs text-slate-700 dark:text-slate-200">
                                        {{ material.name }} · {{ material.dose }} {{ material.unit }}
                                    </p>
                                </div>
                            </template>
                        </div>
                    </template>
                    <p v-else class="mt-1 text-xs text-amber-700 dark:text-amber-300">No recipe is linked to this JO.</p>
                </div>

                <div v-for="item in order.items" :key="item.id" class="mt-4 grid gap-3 rounded-xl border p-4 md:grid-cols-4 dark:border-zinc-700">
                    <div>
                        <p class="font-mono font-black">{{ item.code }}</p>
                        <p class="text-xs text-slate-500">{{ item.yarn_type }} · {{ item.weight }} kg</p>
                    </div>
                    <label v-if="order.method === 'squeezer'" class="text-xs font-bold">Bio-processing enzyme
                        <select v-model="forms[item.id].chemical_material_id" required class="mt-1 w-full rounded-lg border p-2 dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="">Select enzyme material</option>
                            <option v-for="material in chemicalMaterials" :key="material.id" :value="material.id">{{ material.name }}</option>
                        </select>
                        <span v-if="!chemicalMaterials.length" class="mt-1 block text-[10px] font-medium text-amber-700">No Bio-processing Enzymes are available in Materials.</span>
                    </label>
                    <label v-if="stage === 'drying'" class="text-xs font-bold">Machine temperature (°C)
                        <input v-model="forms[item.id].temperature_c" type="number" min="0" max="400" class="mt-1 w-full rounded-lg border p-2 dark:border-zinc-700 dark:bg-zinc-800" :placeholder="order.method === 'heatsetting' ? 'e.g. 200' : 'e.g. 100'" />
                    </label>
                    <label class="text-xs font-bold">Remarks
                        <input v-model="forms[item.id].remarks" class="mt-1 w-full rounded-lg border p-2 dark:border-zinc-700 dark:bg-zinc-800" />
                    </label>
                    <button @click="requestSubmit(item)" :disabled="order.method === 'squeezer' && !chemicalMaterials.length" class="self-end rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Submit completed roll</button>
                </div>
            </section>
        </main>
        <WorkSubmissionDialogs :show-confirm="confirmWork" confirm-title="Confirm finishing work" :confirm-message="`Submit ${pendingItem?.code || 'this roll'} as completed for ${methodLabel}?`" confirm-detail="This will record your finishing work and move the roll to the next step." :submitting="workSubmitting" :result="workResult" @cancel="confirmWork = false" @confirm="submit" @close-result="workResult = null" />
    </AuthenticatedLayout>
</template>
