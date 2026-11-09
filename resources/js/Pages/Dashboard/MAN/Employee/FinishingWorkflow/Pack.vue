<script setup>
import { reactive, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import WorkSubmissionDialogs from '@/Components/Manufacturing/WorkSubmissionDialogs.vue'

const props = defineProps({ orders: Array })
const selected = reactive({})
const names = reactive({})
const pendingOrder = ref(null)
const confirmWork = ref(false)
const workSubmitting = ref(false)
const workResult = ref(null)

props.orders.forEach((order) => { selected[order.id] = []; names[order.id] = '' })

function pack(order) {
    pendingOrder.value = order
    workResult.value = null
    confirmWork.value = true
}

function submitPack() {
    if (!pendingOrder.value || workSubmitting.value) return
    const order = pendingOrder.value
    workSubmitting.value = true
    useForm({ work_item_ids: selected[order.id], group_name: names[order.id] }).post(route('man.staff.finishing-packaging.package-group.store', order.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            selected[order.id] = []
            names[order.id] = ''
            workResult.value = { success: true, title: 'Package group saved', message: page.props.flash?.success || 'The selected rolls were packed successfully.' }
            confirmWork.value = false
        },
        onError: (errors) => {
            workResult.value = { success: false, title: 'Could not pack rolls', message: Object.values(errors || {}).flat().find(Boolean) || 'The server could not save this package group. Review the selected rolls and try again.' }
            confirmWork.value = false
        },
        onHttpException: () => {
            workResult.value = { success: false, title: 'Could not pack rolls', message: 'The server returned an error while saving. Your selections are still available so you can try again.' }
            confirmWork.value = false
        },
        onFinish: () => { workSubmitting.value = false },
    })
}
</script>

<template>
    <Head title="Finishing Packing" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-amber-700 to-orange-600 p-6 text-white">
                <p class="text-xs font-black uppercase tracking-widest">Finishing Packer</p>
                <h1 class="text-2xl font-black">Pack by JO and Fabric Code Group</h1>
                <p class="mt-1 text-sm text-amber-100">Select the rolls that belong together. Every roll must be included in a packed group before supervisor handoff.</p>
            </header>
            <p v-if="!orders.length" class="rounded-2xl border border-dashed p-10 text-center text-slate-500">No quality-approved JO is waiting for packing.</p>
            <section v-for="order in orders" :key="order.id" class="rounded-2xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xl font-black">{{ order.jo_number }} · {{ order.items.length }} rolls ready</h2>
                <p v-if="order.deadline" class="mt-1 text-xs font-bold text-amber-700 dark:text-amber-300">Assignment deadline: {{ new Date(`${order.deadline}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) }}</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <label v-for="item in order.items" :key="item.id" class="flex items-center gap-2 rounded-lg border p-3 text-sm dark:border-zinc-700"><input v-model="selected[order.id]" type="checkbox" :value="item.id" /><span class="font-mono font-bold">{{ item.code }}</span><span>{{ item.weight }} kg · {{ item.yarn_type }}</span></label>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <input v-model="names[order.id]" placeholder="Group name (optional)" class="rounded-lg border p-2 text-sm" />
                    <button @click="pack(order)" :disabled="!selected[order.id].length" class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-40">Pack selected rolls together</button>
                </div>
            </section>
        </main>
        <WorkSubmissionDialogs :show-confirm="confirmWork" confirm-title="Confirm package group" :confirm-message="`Pack ${selected[pendingOrder?.id]?.length || 0} selected roll(s) for ${pendingOrder?.jo_number || 'this JO'}?`" confirm-detail="This will mark the selected fabric codes as packed." :submitting="workSubmitting" :result="workResult" @cancel="confirmWork = false" @confirm="submitPack" @close-result="workResult = null" />
    </AuthenticatedLayout>
</template>
