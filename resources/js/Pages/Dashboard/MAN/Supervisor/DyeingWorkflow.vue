<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({ orders: Array, machines: Array, staff: Array, knittingStaff: Array, batches: Array, reviews: Array, remakeRequests: Array })
const form = useForm({ manufacturing_order_id: '', machine_id: '', assigned_to: '', color: '', fabric_ids: [] })
const order = computed(() => props.orders.find(x => x.id === Number(form.manufacturing_order_id)))
const selectable = computed(() => (order.value?.fabrics || []).filter(f => !f.batch_id))
function queueBatch() { form.post(route('man.dyeing-workflow.batches.store'), { onSuccess: () => form.reset() }) }
function review(record, decision) { useForm({ decision }).post(route('man.dyeing-workflow.review', record.id), { preserveScroll: true }) }
function releaseRemake(id) { useForm({}).post(route('man.dyeing-workflow.remakes.release', id)) }
</script>

<template>
    <Head title="Dyeing Workflow" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-indigo-800 to-cyan-700 p-6 text-white">
                <p class="text-xs font-bold uppercase tracking-widest text-cyan-100">Dyeing Supervisor</p>
                <h1 class="mt-1 text-3xl font-black">Dyeing Workflow</h1>
                <p class="mt-2 text-sm text-indigo-100">Review each fabric code and pass it to the next stage after approval.</p>
            </header>
            <section class="rounded-2xl border bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xl font-black">Queue fabrics into a machine batch</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <label class="text-sm font-semibold">Job Order<select v-model="form.manufacturing_order_id" class="mt-1 w-full rounded-lg border p-2 text-slate-900"><option value="">Select JO</option><option v-for="o in orders" :key="o.id" :value="o.id">{{ o.sales_order_number }} · {{ o.quantity }} kg</option></select></label>
                    <label class="text-sm font-semibold">Dyeing Machine<select v-model="form.machine_id" class="mt-1 w-full rounded-lg border p-2 text-slate-900"><option value="">Select machine</option><option v-for="m in machines" :key="m.id" :value="m.id">{{ m.machine_no }} · {{ m.load_capacity_kg || 'capacity missing' }} kg</option></select></label>
                    <label class="text-sm font-semibold">Color group<input v-model="form.color" class="mt-1 w-full rounded-lg border p-2" placeholder="Color"></label>
                    <label class="text-sm font-semibold">Assigned color staff<select v-model="form.assigned_to" class="mt-1 w-full rounded-lg border p-2 text-slate-900"><option value="">Select employee</option><option v-for="person in staff" :key="person.id" :value="person.id">{{ person.name }}</option></select></label>
                </div>
                <div v-if="order" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"><label v-for="fabric in selectable" :key="fabric.id" class="flex items-center gap-2 rounded-xl border p-3 text-sm dark:border-zinc-700"><input v-model="form.fabric_ids" type="checkbox" :value="fabric.id"><span class="font-mono font-bold">{{ fabric.code }}</span><span>{{ fabric.weight }} kg</span></label><p v-if="!selectable.length" class="text-sm text-slate-500">No unbatched fabrics are waiting from Knitting.</p></div>
                <button @click="queueBatch" :disabled="form.processing || !order || order.bleaching_required === null" class="mt-4 rounded-xl bg-indigo-700 px-5 py-2.5 font-bold text-white disabled:opacity-50">Create dyeing batch</button>
                <p v-if="form.errors.fabric_ids" class="mt-2 text-sm text-red-600">{{ form.errors.fabric_ids }}</p>
            </section>
            <section class="rounded-2xl border bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xl font-black">Stage inspections</h2>
                <p v-if="!reviews.length" class="mt-3 text-sm text-slate-500">No stage work is waiting for inspection.</p>
                <article v-for="r in reviews" :key="r.id" class="mt-3 rounded-xl border p-4 dark:border-zinc-700">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-bold capitalize">{{ r.stage.replaceAll('_',' ') }} · attempt {{ r.attempt }}</p><p class="text-xs text-slate-500">{{ r.batch?.code }} / {{ r.batch?.machine?.machine_no }} · {{ r.operator?.name }}</p><p class="mt-2 rounded bg-slate-100 px-2 py-1 text-xs dark:bg-zinc-800">Fabric {{ r.fabric?.code }} · {{ r.fabric?.weight }} kg</p></div><div class="flex gap-2"><button @click="review(r,'pass')" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white">Pass this fabric</button><button @click="review(r,'remake')" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white">Reprocess</button></div></div>
                    <div v-if="r.materials_used?.length" class="mt-3 border-t pt-3 dark:border-zinc-700"><p class="text-xs font-bold">Materials used</p><div class="mt-1 flex flex-wrap gap-2"><span v-for="(m,i) in r.materials_used" :key="i" class="rounded bg-indigo-50 px-2 py-1 text-xs dark:bg-indigo-500/10">{{ m.name }} · {{ m.quantity_used }} {{ m.unit }}</span></div></div>
                </article>
            </section>
            <section class="rounded-2xl border bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"><h2 class="text-xl font-black">Knitting remake requests</h2><p v-if="!remakeRequests?.length" class="mt-3 text-sm text-slate-500">No rejected Dyeing batches are waiting for Knitting.</p><div v-for="request in remakeRequests" :key="request.id" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 dark:border-zinc-700"><div><p class="font-bold">{{ request.code }} · {{ request.sales_order?.jo_number }} · {{ request.requested_weight_kg }} kg</p><p class="text-xs text-slate-500">Assigned to {{ request.assignee?.name }} · {{ request.status }} · {{ request.fabrics?.filter(x => x.replacement_fabric_id).length }}/{{ request.fabrics?.length }} replacement rolls produced</p></div><div class="flex flex-wrap gap-2"><span v-for="item in request.fabrics" :key="item.id" class="rounded bg-slate-100 px-2 py-1 text-xs dark:bg-zinc-800">{{ item.original_fabric?.code }} → {{ item.replacement_fabric?.code || 'waiting' }}</span><button v-if="request.status === 'ready_for_dyeing'" @click="releaseRemake(request.id)" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-bold text-white">Return replacements to Dyeing</button></div></div></section>
            <section class="rounded-2xl border bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"><h2 class="text-xl font-black">Active and completed batches</h2><div v-for="b in batches" :key="b.id" class="mt-3 rounded-xl border p-4 dark:border-zinc-700"><p class="font-bold">{{ b.code }} · {{ b.color }} · {{ b.total_weight_kg }}/{{ b.load_capacity_kg }} kg</p><p class="text-xs text-slate-500">{{ b.machine?.machine_no }} · {{ b.operator?.name }} · {{ b.status }}</p><div class="mt-2 flex flex-wrap gap-2"><span v-for="item in b.fabrics" :key="item.id" class="rounded bg-slate-100 px-2 py-1 text-xs dark:bg-zinc-800">{{ item.fabric?.code }}: {{ item.current_stage }} / {{ item.status }}</span></div></div></section>
        </main>
    </AuthenticatedLayout>
</template>
