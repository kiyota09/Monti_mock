<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import WorkSubmissionDialogs from '@/Components/Manufacturing/WorkSubmissionDialogs.vue'

const props = defineProps({ orders: Array })
const pending = ref(null)
const confirmWork = ref(false)
const workSubmitting = ref(false)
const workResult = ref(null)

function check(order, decision) {
    pending.value = { order, decision }
    workResult.value = null
    confirmWork.value = true
}

function submitCheck() {
    if (!pending.value || workSubmitting.value) return
    const { order, decision } = pending.value
    workSubmitting.value = true
    useForm({ decision, remarks: '' }).post(route('man.staff.checker-quality.finishing.quality-check', order.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            workResult.value = { success: true, title: 'Quality decision recorded', message: page.props.flash?.success || (decision === 'pass' ? 'The JO passed final Quality Check.' : 'The JO was returned for Drying rework.') }
            confirmWork.value = false
        },
        onError: (errors) => {
            workResult.value = { success: false, title: 'Could not save quality decision', message: Object.values(errors || {}).flat().find(Boolean) || 'The server could not save this quality decision. Please try again.' }
            confirmWork.value = false
        },
        onHttpException: () => {
            workResult.value = { success: false, title: 'Could not save quality decision', message: 'The server returned an error while saving. Please try again.' }
            confirmWork.value = false
        },
        onFinish: () => { workSubmitting.value = false },
    })
}
</script>

<template>
    <Head title="Final Finishing Quality" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-blue-800 to-indigo-700 p-6 text-white">
                <p class="text-xs font-black uppercase tracking-widest">Finishing Quality Checker</p>
                <h1 class="text-2xl font-black">Final JO Quality Check</h1>
                <p class="mt-1 text-sm text-blue-100">Review shrinkage and GSM results, then approve the complete JO for the Finishing supervisor to push to Logistics.</p>
            </header>
            <p v-if="!orders.length" class="rounded-2xl border border-dashed p-10 text-center text-slate-500">No complete JO is waiting for final quality check.</p>
            <article v-for="order in orders" :key="order.id" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xl font-black">{{ order.sales_order?.jo_number }} · {{ order.items.length }} rolls</h2>
                <div class="my-3 flex flex-wrap gap-2">
                    <div v-for="item in order.items" :key="item.id" class="rounded-lg bg-slate-50 p-2 text-xs dark:bg-zinc-800"><b>{{ item.fabric?.code }}</b> · {{ item.records?.filter(r => r.stage === 'shrinkage').at(-1)?.gsm }} GSM · {{ item.records?.filter(r => r.stage === 'shrinkage').at(-1)?.shrinkage_percent }}% shrinkage</div>
                </div>
                <div class="flex gap-2">
                    <button @click="check(order, 'pass')" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Approve JO for Logistics</button>
                    <button @click="check(order, 'rework')" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white">Return JO for Drying rework</button>
                </div>
            </article>
        </main>
        <WorkSubmissionDialogs :show-confirm="confirmWork" confirm-title="Confirm final quality decision" :confirm-message="pending ? `${pending.decision === 'pass' ? 'Approve' : 'Return'} ${pending.order.sales_order?.jo_number || 'this JO'} ${pending.decision === 'pass' ? 'for Logistics' : 'for Drying rework'}?` : ''" confirm-detail="This decision updates the status of the complete Job Order." :submitting="workSubmitting" :result="workResult" @cancel="confirmWork = false" @confirm="submitCheck" @close-result="workResult = null" />
    </AuthenticatedLayout>
</template>
