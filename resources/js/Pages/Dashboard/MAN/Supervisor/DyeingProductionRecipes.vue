<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { AlertTriangle, ChevronDown, Droplets, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    orders: { type: Array, default: () => [] },
    groupDefinitions: { type: Array, default: () => [] },
    materials: { type: Array, default: () => [] },
})

/* ------------------------------------------------------------------ state */
const editing = ref(null)
const drafts = reactive({})
const expanded = reactive({})
const busy = ref(false)
const query = ref('')
const liquorRatioModal = ref({ show: false, order: null, value: 10 })
const ratioInputEl = ref(null)
const ratioPresets = [5, 8, 10, 12, 15]

/* --------------------------------------------------------------- helpers */
const doseFor = (key) => (key === 'yarns' ? 'qty' : 'grams')
const doseUnit = (key) =>
    key === 'yarns'
          ? { short: 'kg/kg', long: 'kilograms per kilogram of fabric' }
          : { short: 'g', long: 'grams used on the lab fabric sample' }

const makeOrderDraft = (order) => ({
    liquor_ratio: Number(order.liquor_ratio),
    sample_weight_g: Number(order.sample_weight_g) || 100,
    groups: Object.fromEntries(
        props.groupDefinitions.map(({ key }) => [
            key,
            (order.groups?.[key] || []).map((row) => ({
                material_id: row.material_id,
                [doseFor(key)]: Number(row.lab_amount),
            })),
        ]),
    ),
})

/* --------------------------------------------------------------- derived */
const filteredOrders = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return props.orders
    return props.orders.filter((order) =>
        [order.jo_number, order.client, order.product, order.po_number, order.client_po_number, order.sample_code, order.color]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(q)),
    )
})
const readyCount = computed(() => props.orders.filter((order) => order.manufacturing_order_id).length)
const canSaveRatio = computed(() => Number(liquorRatioModal.value.value) > 0)

const orderMeta = (order) =>
    [
        { label: 'Purchase order', value: order.po_number },
        { label: 'Client purchase order', value: order.client_po_number },
        { label: 'Approved sample', value: order.sample_code },
        { label: 'Recipe', value: order.recipe_id ? `#${order.recipe_id}` : null },
        { label: 'Formula version', value: order.approved_formula_version },
        { label: 'Color', value: order.color },
        { label: 'Yarn', value: order.yarn_type },
        { label: 'Design', value: order.design },
    ].filter((item) => item.value)

/* --------------------------------------------------------------- actions */
const toggleOrder = (order) => {
    expanded[order.sales_order_id] = !expanded[order.sales_order_id]
    if (!expanded[order.sales_order_id] && editing.value === order.id) editing.value = null
}

const editOrder = (order) => {
    if (!order?.manufacturing_order_id) return
    drafts[order.id] = makeOrderDraft(order)
    editing.value = order.id
    expanded[order.sales_order_id] = true
}

const closeEditor = () => {
    editing.value = null
}

const toggleEditor = (order) => (editing.value === order.id ? closeEditor() : editOrder(order))

const addRow = (order, key) => drafts[order.id].groups[key].push({ material_id: null, [doseFor(key)]: 0 })
const removeRow = (order, key, idx) => drafts[order.id].groups[key].splice(idx, 1)

const saveOrder = (order) => {
    busy.value = true
    router.put(route('man.dyeing-production-recipes.update', order.id), drafts[order.id], {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null
        },
        onFinish: () => {
            busy.value = false
        },
    })
}

const openLiquorRatioModal = (order) => {
    liquorRatioModal.value = { show: true, order, value: Number(order.liquor_ratio) || 10 }
}
const closeLiquorRatioModal = () => {
    liquorRatioModal.value.show = false
}
const saveLiquorRatio = () => {
    const order = liquorRatioModal.value.order
    if (!order || busy.value || !canSaveRatio.value) return
    const draft = makeOrderDraft(order)
    draft.liquor_ratio = Number(liquorRatioModal.value.value)
    busy.value = true
    router.put(route('man.dyeing-production-recipes.update', order.id), draft, {
        preserveScroll: true,
        onSuccess: () => {
            liquorRatioModal.value.show = false
        },
        onFinish: () => {
            busy.value = false
        },
    })
}

/* Modal: focus the input on open and lock page scroll behind the sheet */
watch(
    () => liquorRatioModal.value.show,
    async (show) => {
        document.body.classList.toggle('overflow-hidden', show)
        if (show) {
            await nextTick()
            ratioInputEl.value?.select()
        }
    },
)
onBeforeUnmount(() => document.body.classList.remove('overflow-hidden'))
</script>

<template>
    <Head title="Dyeing Production Recipes" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-5xl space-y-4 px-4 py-4 pb-24 sm:space-y-6 sm:px-6 sm:py-6">
            <!-- Page header -->
            <header class="rounded-2xl bg-indigo-900 p-5 text-white shadow-sm sm:p-7">
                <p class="text-xs font-medium text-indigo-200">Dyeing department · Supervisor</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Production recipes</h1>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-indigo-100">
                    Enter each chemical’s lab grams and the lab fabric sample weight. The page calculates % WOF, then scales that percentage to the job order fabric weight.
                </p>
                <dl v-if="orders.length" class="mt-5 grid grid-cols-2 gap-3 sm:max-w-sm">
                    <div class="rounded-xl bg-white/10 px-4 py-3">
                        <dt class="text-xs text-indigo-200">Job orders</dt>
                        <dd class="text-xl font-extrabold tabular-nums">{{ orders.length }}</dd>
                    </div>
                    <div class="rounded-xl bg-white/10 px-4 py-3">
                        <dt class="text-xs text-indigo-200">Ready for production</dt>
                        <dd class="text-xl font-extrabold tabular-nums">{{ readyCount }}</dd>
                    </div>
                </dl>
            </header>

            <!-- Empty state -->
            <section
                v-if="!orders.length"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-zinc-700 dark:bg-zinc-900"
            >
                <Droplets class="mx-auto h-8 w-8 text-slate-400" aria-hidden="true" />
                <h2 class="mt-3 text-base font-bold text-slate-900 dark:text-white">No recipes to show yet</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-zinc-400">
                    Client purchase orders with an approved dyeing formula appear here after they are sent to the ECO Push Center.
                </p>
            </section>

            <template v-else>
                <!-- Search -->
                <div class="relative">
                    <label for="order-search" class="sr-only">Search job orders</label>
                    <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input
                        id="order-search"
                        v-model="query"
                        type="search"
                        placeholder="Search by job order, client, product or purchase order"
                        class="w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                    />
                </div>

                <p v-if="!filteredOrders.length" class="rounded-2xl border bg-white p-6 text-center text-sm text-slate-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                    No job orders match “{{ query }}”. Try a different job order number, client or purchase order.
                </p>

                <!-- Job order cards -->
                <article
                    v-for="order in filteredOrders"
                    :key="order.sales_order_id"
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="p-4 sm:p-5">
                        <!-- Toggle: title area -->
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            :aria-expanded="!!expanded[order.sales_order_id]"
                            :aria-controls="`order-panel-${order.sales_order_id}`"
                            @click="toggleOrder(order)"
                        >
                            <span class="min-w-0">
                                <span class="block break-words font-mono text-lg font-extrabold text-slate-900 dark:text-white">{{ order.jo_number }}</span>
                                <span class="mt-0.5 block text-sm text-slate-600 dark:text-zinc-300">
                                    {{ order.client || 'Client not listed' }}<span v-if="order.product" class="text-slate-400"> · </span>{{ order.product }}
                                </span>
                            </span>
                            <span class="mt-1 flex shrink-0 items-center gap-1 text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                                <span class="hidden sm:inline">{{ expanded[order.sales_order_id] ? 'Hide conversion' : 'View conversion' }}</span>
                                <ChevronDown
                                    class="h-5 w-5 transition-transform duration-200 motion-reduce:transition-none"
                                    :class="expanded[order.sales_order_id] ? 'rotate-180' : ''"
                                    aria-hidden="true"
                                />
                            </span>
                        </button>

                        <!-- Key facts -->
                        <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-stretch">
                            <div class="flex flex-wrap content-start gap-2">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    PO&nbsp;<strong class="font-semibold text-slate-900 dark:text-white">{{ order.po_number }}</strong>
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-cyan-50 px-2.5 py-1 text-xs text-cyan-800 dark:bg-cyan-500/10 dark:text-cyan-200">
                                    Liquor ratio&nbsp;<strong class="font-semibold tabular-nums">1:{{ order.liquor_ratio }}</strong>
                                    <span v-if="order.liquor_ratio_defaulted" class="font-semibold text-amber-700 dark:text-amber-300">(default)</span>
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="order.manufacturing_order_id
                                        ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="order.manufacturing_order_id ? 'bg-emerald-500' : 'bg-slate-400'" aria-hidden="true"></span>
                                    {{ order.manufacturing_order_id ? 'Production order created' : 'Production order not created yet' }}
                                </span>
                            </div>

                            <div class="rounded-xl bg-indigo-50 px-4 py-3 dark:bg-indigo-500/10 sm:min-w-[11rem] sm:text-right">
                                <p class="text-xs font-medium text-indigo-700 dark:text-indigo-300">Calculation weight</p>
                                <p class="text-xl font-extrabold tabular-nums text-slate-900 dark:text-white">{{ order.production_weight_kg }} <span class="text-sm font-semibold">kg</span></p>
                                <p class="text-xs text-slate-500 dark:text-zinc-400">{{ order.production_weight_source }}</p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div v-if="order.manufacturing_order_id" class="mt-4 grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                            <button
                                type="button"
                                :disabled="busy || editing === order.id"
                                class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-cyan-200 px-4 text-sm font-semibold text-cyan-800 hover:bg-cyan-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-cyan-900 dark:text-cyan-200 dark:hover:bg-cyan-950/40"
                                @click="openLiquorRatioModal(order)"
                            >
                                <Droplets class="h-4 w-4" aria-hidden="true" /> Liquor ratio
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-800"
                                @click="toggleEditor(order)"
                            >
                                <component :is="editing === order.id ? X : Pencil" class="h-4 w-4" aria-hidden="true" />
                                {{ editing === order.id ? 'Close editor' : 'Edit recipe' }}
                            </button>
                        </div>
                    </div>

                    <!-- Expanded panel -->
                    <div
                        v-if="expanded[order.sales_order_id]"
                        :id="`order-panel-${order.sales_order_id}`"
                        class="space-y-4 border-t border-slate-200 p-4 dark:border-zinc-800 sm:p-5"
                    >
                        <!-- Order details -->
                        <dl v-if="orderMeta(order).length" class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-4">
                            <div v-for="item in orderMeta(order)" :key="item.label" class="min-w-0">
                                <dt class="text-xs text-slate-500 dark:text-zinc-400">{{ item.label }}</dt>
                                <dd class="break-words text-sm font-semibold text-slate-900 dark:text-white">{{ item.value }}</dd>
                            </div>
                        </dl>

                        <!-- Editor -->
                        <section
                            v-if="order.manufacturing_order_id && editing === order.id"
                            class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900 dark:bg-indigo-950/20 sm:p-5"
                            :aria-label="`Edit production recipe for ${order.jo_number}`"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Edit production recipe</h3>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">Changes apply to {{ order.jo_number }} only.</p>
                                </div>
                                <label class="block text-sm font-semibold text-slate-700 dark:text-zinc-200">
                                    Liquor ratio (liters per kilogram)
                                    <input
                                        v-model.number="drafts[order.id].liquor_ratio"
                                        type="number"
                                        inputmode="decimal"
                                        min="0.01"
                                        step="0.1"
                                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-base text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white sm:w-40"
                                    />
                                </label>
                                <label class="block text-sm font-semibold text-slate-700 dark:text-zinc-200">
                                    Lab fabric sample weight (grams)
                                    <input
                                        v-model.number="drafts[order.id].sample_weight_g"
                                        type="number"
                                        inputmode="decimal"
                                        min="0.01"
                                        step="0.01"
                                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-base text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white sm:w-48"
                                    />
                                </label>
                            </div>

                            <div
                                v-for="group in groupDefinitions"
                                :key="group.key"
                                class="mt-4 rounded-xl border border-slate-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900 sm:p-4"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ group.label }}</h4>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-[36px] items-center gap-1 rounded-lg px-2.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-300 dark:hover:bg-indigo-500/10"
                                        @click="addRow(order, group.key)"
                                    >
                                        <Plus class="h-4 w-4" aria-hidden="true" /> Add material
                                    </button>
                                </div>

                                <ul v-if="drafts[order.id].groups[group.key].length" class="mt-3 space-y-3">
                                    <li
                                        v-for="(row, idx) in drafts[order.id].groups[group.key]"
                                        :key="idx"
                                        class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2 rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/60 sm:grid-cols-[minmax(0,1fr)_11rem_auto] sm:bg-transparent sm:p-0 sm:dark:bg-transparent"
                                    >
                                        <select
                                            v-model="row.material_id"
                                            aria-label="Material"
                                            class="col-span-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                        >
                                            <option :value="null">Choose material</option>
                                            <option v-for="material in materials" :key="material.id" :value="material.id">{{ material.name }}</option>
                                        </select>

                                        <button
                                            type="button"
                                            :aria-label="`Remove ${group.label} row ${idx + 1}`"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 dark:text-rose-400 dark:hover:bg-rose-500/10 sm:order-3"
                                            @click="removeRow(order, group.key, idx)"
                                        >
                                            <Trash2 class="h-4 w-4" aria-hidden="true" />
                                        </button>

                                        <div class="col-span-2 sm:col-span-1 sm:order-2">
                                            <div class="flex items-center overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/30 dark:border-zinc-700 dark:bg-zinc-800">
                                                <input
                                                    v-model.number="row[doseFor(group.key)]"
                                                    type="number"
                                                    inputmode="decimal"
                                                    min="0"
                                                    step="0.0001"
                                                    :aria-label="`Dose in ${doseUnit(group.key).long}`"
                                                    class="min-w-0 flex-1 border-0 bg-transparent p-2.5 text-sm tabular-nums text-slate-900 focus:outline-none focus:ring-0 dark:text-white"
                                                />
                                                <span class="border-l border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">{{ doseUnit(group.key).short }}</span>
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400 sm:hidden">{{ doseUnit(group.key).long }}</p>
                                        </div>
                                    </li>
                                </ul>
                                <p v-else class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                                    No materials yet. Choose Add material to create a row.
                                </p>
                            </div>

                            <!-- Sticky save bar on mobile -->
                            <div class="sticky bottom-0 -mx-4 -mb-4 mt-4 flex gap-2 rounded-b-xl border-t border-indigo-200 bg-white/95 p-4 backdrop-blur dark:border-indigo-900 dark:bg-zinc-900/95 sm:static sm:-mx-5 sm:-mb-5 sm:justify-end sm:p-5">
                                <button
                                    type="button"
                                    class="min-h-[44px] flex-1 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800 sm:flex-none"
                                    @click="closeEditor"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    :disabled="busy"
                                    class="min-h-[44px] flex-[2] rounded-xl bg-indigo-700 px-5 text-sm font-bold text-white hover:bg-indigo-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
                                    @click="saveOrder(order)"
                                >
                                    {{ busy ? 'Saving…' : 'Save recipe' }}
                                </button>
                            </div>
                        </section>

                        <!-- Generated formulation, one block per group -->
                        <section
                            v-for="group in groupDefinitions"
                            :key="group.key"
                            class="overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-700"
                        >
                            <h3 class="flex items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                {{ group.label }}
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-500 dark:bg-zinc-900 dark:text-zinc-400">{{ order.groups[group.key]?.length || 0 }}</span>
                            </h3>

                            <div v-if="order.groups[group.key]?.length">
                                <!-- Column headings (tablet and up) -->
                                <div class="hidden gap-4 border-b border-slate-100 px-4 py-2 text-xs font-semibold text-slate-500 dark:border-zinc-800 dark:text-zinc-400 sm:grid sm:grid-cols-[minmax(0,1fr)_8rem_minmax(0,16rem)]">
                                    <span>Material</span>
                                    <span>Lab dose</span>
                                    <span>Amount for {{ order.production_weight_kg }} kg</span>
                                </div>

                                <ul class="divide-y divide-slate-100 dark:divide-zinc-800">
                                    <li
                                        v-for="(row, index) in order.groups[group.key]"
                                        :key="`${row.material_id}-${index}`"
                                        class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_8rem_minmax(0,16rem)] sm:gap-4"
                                    >
                                        <p class="break-words text-sm font-semibold text-slate-900 dark:text-white">{{ row.name }}</p>

                                        <p class="flex items-baseline justify-between gap-2 text-sm text-slate-600 dark:text-zinc-300 sm:block">
                                            <span class="text-xs text-slate-500 dark:text-zinc-400 sm:hidden">Lab dose</span>
                                            <span class="tabular-nums">{{ row.lab_amount }} {{ row.lab_unit }}</span>
                                        </p>

                                        <div class="rounded-lg bg-indigo-50 p-3 dark:bg-indigo-500/10 sm:bg-transparent sm:p-0 sm:dark:bg-transparent">
                                            <p class="flex items-baseline justify-between gap-2 sm:block">
                                                <span class="text-xs text-indigo-700 dark:text-indigo-300 sm:hidden">Amount for {{ order.production_weight_kg }} kg</span>
                                                <span class="text-sm font-bold tabular-nums text-indigo-700 dark:text-indigo-300">{{ row.calculated_amount }} kg</span>
                                            </p>
                                            <p class="mt-1 break-words font-mono text-[11px] leading-relaxed text-slate-600 dark:text-zinc-400">{{ row.calculation_description }}</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <p v-else class="px-4 py-3 text-sm text-slate-500 dark:text-zinc-400">No materials listed in this section.</p>
                        </section>
                    </div>
                </article>
            </template>

            <!-- Liquor ratio: bottom sheet on mobile, centered dialog on larger screens -->
            <Teleport to="body">
                <div
                    v-if="liquorRatioModal.show"
                    class="fixed inset-0 z-[120] flex items-end justify-center bg-slate-950/60 sm:items-center sm:p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="liquor-ratio-title"
                    @click.self="closeLiquorRatioModal"
                    @keydown.esc="closeLiquorRatioModal"
                >
                    <section class="w-full max-w-md rounded-t-3xl border border-slate-200 bg-white p-5 pb-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 sm:rounded-2xl sm:pb-5">
                        <header class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300">
                                    <Droplets class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <h2 id="liquor-ratio-title" class="font-extrabold text-slate-900 dark:text-white">Set liquor ratio</h2>
                                    <p class="mt-0.5 font-mono text-xs text-slate-500">{{ liquorRatioModal.order?.jo_number }}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                aria-label="Close"
                                class="flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 dark:hover:bg-zinc-800"
                                @click="closeLiquorRatioModal"
                            >
                                <X class="h-5 w-5" aria-hidden="true" />
                            </button>
                        </header>

                        <div class="mt-5 flex flex-wrap gap-2" role="group" aria-label="Common liquor ratios">
                            <button
                                v-for="preset in ratioPresets"
                                :key="preset"
                                type="button"
                                class="min-h-[40px] rounded-full border px-4 text-sm font-semibold tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500"
                                :class="Number(liquorRatioModal.value) === preset
                                    ? 'border-cyan-700 bg-cyan-700 text-white'
                                    : 'border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800'"
                                @click="liquorRatioModal.value = preset"
                            >
                                1:{{ preset }}
                            </button>
                        </div>

                        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-zinc-200">
                            Liters per kilogram of dry fabric
                            <input
                                ref="ratioInputEl"
                                v-model.number="liquorRatioModal.value"
                                type="number"
                                inputmode="decimal"
                                min="0.01"
                                max="1000"
                                step="0.1"
                                required
                                placeholder="For 1:10, enter 10"
                                class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white p-3 text-base tabular-nums text-slate-900 focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                @keydown.enter.prevent="saveLiquorRatio"
                            />
                        </label>
                        <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">For a 1:10 ratio, enter 10. Saving recalculates every ingredient measured in g/L.</p>

                        <footer class="mt-5 grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                            <button
                                type="button"
                                class="min-h-[44px] rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                @click="closeLiquorRatioModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                :disabled="busy || !canSaveRatio"
                                class="min-h-[44px] rounded-xl bg-cyan-700 px-5 text-sm font-bold text-white hover:bg-cyan-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="saveLiquorRatio"
                            >
                                {{ busy ? 'Saving…' : 'Save ratio' }}
                            </button>
                        </footer>
                    </section>
                </div>
            </Teleport>
        </main>
    </AuthenticatedLayout>
</template>
