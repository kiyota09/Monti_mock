<script setup>
import { reactive, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import WorkSubmissionDialogs from '@/Components/Manufacturing/WorkSubmissionDialogs.vue'

const props = defineProps({ items: Array })
const forms = reactive({})
const pendingItem = ref(null)
const confirmWork = ref(false)
const workSubmitting = ref(false)
const workResult = ref(null)

props.items.forEach((item) => {
    forms[item.id] = { gsm: '', shrinkage_percent: '', decision: 'pass', remarks: '' }
})

function submit(item) {
    pendingItem.value = item
    workResult.value = null
    confirmWork.value = true
}

function saveInspection() {
    if (!pendingItem.value || workSubmitting.value) return
    const item = pendingItem.value
    workSubmitting.value = true
    useForm({ ...forms[item.id] }).post(route('man.staff.finishing-inspector.shrinkage.inspect', item.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            workResult.value = { success: true, title: 'Inspection recorded', message: page.props.flash?.success || 'The shrinkage and GSM inspection was saved.' }
            confirmWork.value = false
        },
        onError: (errors) => {
            workResult.value = { success: false, title: 'Could not save inspection', message: Object.values(errors || {}).flat().find(Boolean) || 'The server could not save this inspection. Review the form and try again.' }
            confirmWork.value = false
        },
        onHttpException: () => {
            workResult.value = { success: false, title: 'Could not save inspection', message: 'The server returned an error while saving. Your form is still open so you can try again.' }
            confirmWork.value = false
        },
        onFinish: () => { workSubmitting.value = false },
    })
}
</script>

<template>
    <Head title="Shrinkage Inspection" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-violet-800 to-indigo-700 p-6 text-white">
                <p class="text-xs font-black uppercase tracking-widest">Finishing Inspector</p>
                <h1 class="text-2xl font-black">Shrinkage and GSM Inspection</h1>
                <p class="mt-1 text-sm text-indigo-100">Check each roll against its product standards. Failed rolls return to the selected Drying route.</p>
            </header>
            <p v-if="!items.length" class="rounded-2xl border border-dashed p-10 text-center text-slate-500">No rolls are waiting for shrinkage inspection.</p>
            <article v-for="item in items" :key="item.id" class="grid gap-3 rounded-2xl border bg-white p-5 md:grid-cols-5 dark:border-zinc-800 dark:bg-zinc-900">
                <div>
                    <p class="font-mono font-black">{{ item.fabric?.code }}</p>
                    <p class="text-xs text-slate-500">{{ item.work_order?.sales_order?.jo_number }} · {{ item.fabric?.yarn_type }} · {{ item.fabric?.weight }} kg</p>
                </div>
                <label class="text-xs font-bold">Measured GSM<input v-model="forms[item.id].gsm" type="number" min="1" step="0.01" class="mt-1 w-full rounded-lg border p-2" /></label>
                <label class="text-xs font-bold">Shrinkage (%)<input v-model="forms[item.id].shrinkage_percent" type="number" step="0.001" class="mt-1 w-full rounded-lg border p-2" /></label>
                <label class="text-xs font-bold">Decision<select v-model="forms[item.id].decision" class="mt-1 w-full rounded-lg border p-2"><option value="pass">Meets standard</option><option value="rework">Return to drying</option></select></label>
                <div>
                    <label class="text-xs font-bold">Remarks<input v-model="forms[item.id].remarks" class="mt-1 w-full rounded-lg border p-2" /></label>
                    <button @click="submit(item)" class="mt-2 w-full rounded-lg bg-indigo-700 px-4 py-2 text-sm font-bold text-white">Save inspection</button>
                </div>
            </article>
        </main>
        <WorkSubmissionDialogs :show-confirm="confirmWork" confirm-title="Confirm shrinkage inspection" :confirm-message="`Save the inspection for ${pendingItem?.fabric?.code || 'this roll'}?`" :confirm-detail="`Decision: ${pendingItem ? (forms[pendingItem.id].decision === 'pass' ? 'Meets standard' : 'Return to drying') : ''}.`" :submitting="workSubmitting" :result="workResult" @cancel="confirmWork = false" @confirm="saveInspection" @close-result="workResult = null" />
    </AuthenticatedLayout>
</template>
