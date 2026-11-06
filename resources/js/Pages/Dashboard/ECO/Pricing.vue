<script setup>
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { BadgeDollarSign, Check, Layers, Package, Search, Settings, Tags, X } from 'lucide-vue-next'

const props = defineProps({
    products: { type: Array, default: () => [] },
    yarns: { type: Array, default: () => [] },
    colorGroups: { type: Array, default: () => [] },
})

const selectedProductId = ref(props.products[0]?.id ?? null)
const search = ref('')
const yarnSearch = ref('')
const selectedProduct = computed(() => props.products.find(product => Number(product.id) === Number(selectedProductId.value)) || null)
const inventoryYarns = computed(() => props.yarns)
const filteredYarns = computed(() => {
    const query = yarnSearch.value.trim().toLowerCase()
    if (!query) return inventoryYarns.value
    return inventoryYarns.value.filter(yarn => [yarn.name, yarn.mat_id, yarn.yarn_tier]
        .some(value => String(value || '').toLowerCase().includes(query)))
})

const form = useForm({ product_id: selectedProductId.value, prices: [] })
const settingsForm = useForm({ product_id: '', minimum_order_quantity: '' })
const showSettings = ref(false)

const loadPricing = (product) => {
    if (!product) {
        form.product_id = null
        form.prices = []
        return
    }

    form.product_id = product.id
    const existing = new Map((product.prices || []).map(price => [`${price.material_id}:${price.color_group}`, price.price ?? '']))
    form.prices = inventoryYarns.value.flatMap(yarn => props.colorGroups.map(color => ({
        material_id: yarn.id,
        color_group: color.key,
        price: existing.get(`${yarn.id}:${color.key}`) ?? '',
    })))
    form.defaults()
}

watch(selectedProduct, loadPricing, { immediate: true })

const filteredProducts = computed(() => {
    const query = search.value.trim().toLowerCase()
    if (!query) return props.products
    return props.products.filter(product => [product.name, product.sku, product.product_id]
        .some(value => String(value || '').toLowerCase().includes(query)))
})

const priceIndexes = computed(() => new Map(form.prices.map((row, index) => [`${row.material_id}:${row.color_group}`, index])))
const priceAt = (yarnId, colorKey) => priceIndexes.value.get(`${yarnId}:${colorKey}`)

const savedVariantCount = computed(() => form.prices.filter(row => row.price !== '' && row.price !== null).length)
const productPricedCount = (product) => (product.prices || []).filter(row => row.price !== '' && row.price !== null).length

const chooseProduct = (productId) => {
    if (form.isDirty && !window.confirm('Discard unsaved price changes for this product?')) return
    selectedProductId.value = productId
}

const save = () => form.post(route('eco.pricing.update'), {
    preserveScroll: true,
    onSuccess: () => form.defaults(),
})

const openSettings = () => {
    if (!selectedProduct.value) return
    settingsForm.product_id = selectedProduct.value.id
    settingsForm.minimum_order_quantity = selectedProduct.value.minimum_order_quantity ?? ''
    settingsForm.clearErrors()
    showSettings.value = true
}

const saveSettings = () => settingsForm.post(route('eco.pricing.settings'), {
    preserveScroll: true,
    onSuccess: () => { showSettings.value = false; settingsForm.defaults(); },
})
</script>

<template>
    <Head title="Product Pricing | E-Commerce" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-indigo-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/20">
            <div class="mx-auto max-w-7xl space-y-6 p-4 pb-16 sm:p-6">
                <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-700 via-violet-700 to-fuchsia-800 p-5 text-white shadow-xl sm:p-7">
                    <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 sm:h-14 sm:w-14"><BadgeDollarSign class="h-6 w-6 sm:h-7 sm:w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-violet-100 sm:text-[11px]">E-Commerce · Product setup</p>
                            <h1 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">Product Pricing</h1>
                            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-violet-100 sm:text-sm">Set a price for each product, yarn material, and fabric color group.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center">
                            <div class="rounded-xl bg-white/10 px-3 py-2 ring-1 ring-white/15"><p class="text-lg font-black">{{ products.length }}</p><p class="text-[9px] font-bold uppercase tracking-wider text-violet-100">Products</p></div>
                            <div class="rounded-xl bg-white/10 px-3 py-2 ring-1 ring-white/15"><p class="text-lg font-black">{{ inventoryYarns.length }}</p><p class="text-[9px] font-bold uppercase tracking-wider text-violet-100">Yarn types</p></div>
                        </div>
                    </div>
                </header>

                <div class="grid gap-5 lg:grid-cols-[minmax(250px,0.8fr)_minmax(0,2fr)]">
                    <aside class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="border-b border-slate-100 p-4 dark:border-zinc-800">
                            <div class="flex items-center justify-between gap-2"><h2 class="text-sm font-black text-slate-900 dark:text-white">Products</h2><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-zinc-800 dark:text-slate-300">{{ filteredProducts.length }} / {{ products.length }}</span></div>
                            <label class="relative mt-3 block">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input v-model="search" type="search" placeholder="Find a product" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:ring-indigo-900/40" />
                            </label>
                        </div>
                        <div class="max-h-[65vh] space-y-1 overflow-y-auto p-2">
                            <button v-for="product in filteredProducts" :key="product.id" type="button" @click="chooseProduct(product.id)"
                                :class="['w-full rounded-xl border p-3 text-left transition', Number(selectedProductId) === Number(product.id) ? 'border-indigo-200 bg-indigo-50 ring-1 ring-indigo-200 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:ring-indigo-500/30' : 'border-transparent hover:border-slate-100 hover:bg-slate-50 dark:hover:border-zinc-700 dark:hover:bg-zinc-800']">
                                <span class="flex items-start gap-2.5">
                                    <Package class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold text-slate-800 dark:text-slate-100">{{ product.name }}</span>
                                        <span class="mt-0.5 block truncate font-mono text-[10px] text-slate-400">{{ product.sku || product.product_id || 'No product code' }}</span>
                                        <span class="mt-1 block text-[10px] font-semibold text-slate-400">{{ productPricedCount(product) }} prices saved</span>
                                    </span>
                                    <span v-if="product.status !== 'Active'" class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold uppercase text-slate-500 dark:bg-zinc-700 dark:text-slate-300">{{ product.status }}</span>
                                </span>
                            </button>
                            <p v-if="!filteredProducts.length" class="p-5 text-center text-xs text-slate-400">No matching products.</p>
                        </div>
                    </aside>

                    <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <template v-if="selectedProduct">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4 dark:border-zinc-800 sm:p-6">
                                <div class="min-w-0">
                                    <p class="truncate text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-300">{{ selectedProduct.category || 'Product' }} · {{ selectedProduct.sku || selectedProduct.product_id }}</p>
                                    <h2 class="mt-1 truncate text-xl font-black text-slate-900 dark:text-white">{{ selectedProduct.name }}</h2>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span v-if="form.isDirty" class="rounded-full bg-amber-50 px-3 py-1.5 text-[10px] font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Unsaved changes</span>
                                    <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ savedVariantCount }} / {{ form.prices.length }} priced</span>
                                    <button type="button" @click="openSettings" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-indigo-300 hover:text-indigo-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:border-indigo-500"><Settings class="h-4 w-4" /> MOQ settings</button>
                                </div>
                            </div>

                            <div class="mx-4 mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-100 bg-amber-50/70 px-4 py-3 text-xs dark:border-amber-900/40 dark:bg-amber-950/20 sm:mx-6">
                                <span class="font-semibold text-amber-900 dark:text-amber-200">Global minimum order quantity</span>
                                <span class="font-black text-amber-800 dark:text-amber-100">{{ selectedProduct.minimum_order_quantity ? `${Number(selectedProduct.minimum_order_quantity).toLocaleString()} kg` : 'Not set' }}</span>
                            </div>

                            <div v-if="inventoryYarns.length" class="p-4 sm:p-6">
                                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400"><Layers class="h-4 w-4 shrink-0 text-violet-500" /><span>Pricing is unique to this product, yarn, and color group.</span></div>
                                    <label class="relative block w-full sm:max-w-xs">
                                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                        <input v-model="yarnSearch" type="search" :aria-label="`Search yarn materials for ${selectedProduct.name}`" placeholder="Filter yarn materials" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-xs text-slate-800 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:ring-violet-900/40" />
                                    </label>
                                </div>
                                <div class="mb-2 text-[10px] font-semibold text-slate-400">Showing {{ filteredYarns.length }} of {{ inventoryYarns.length }} yarn materials</div>
                                <div class="max-h-[62vh] overflow-auto rounded-xl border border-slate-200 dark:border-zinc-700">
                                    <table class="w-full min-w-[680px] text-left">
                                        <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm dark:bg-zinc-800">
                                            <tr>
                                                <th class="sticky left-0 z-20 bg-slate-50 px-4 py-3 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:bg-zinc-800">Fabric type / yarn used</th>
                                                <th v-for="color in colorGroups" :key="color.key" class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-wider text-slate-500">{{ color.label }} <span class="block pt-0.5 text-[9px] font-medium normal-case tracking-normal text-slate-400">Price per kg</span></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                            <tr v-for="yarn in filteredYarns" :key="yarn.id" class="transition-colors hover:bg-violet-50/50 dark:hover:bg-violet-500/5">
                                                <td class="sticky left-0 bg-white px-4 py-3 dark:bg-zinc-900">
                                                    <p class="max-w-[240px] truncate text-xs font-bold text-slate-800 dark:text-slate-100">{{ yarn.name }}</p>
                                                    <p class="mt-0.5 font-mono text-[10px] text-slate-400">{{ yarn.mat_id }}<span v-if="yarn.yarn_tier"> · {{ yarn.yarn_tier }}</span></p>
                                                </td>
                                                <td v-for="color in colorGroups" :key="color.key" class="px-3 py-2.5">
                                                    <label class="relative ml-auto block max-w-40">
                                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">₱</span>
                                                        <input v-model="form.prices[priceAt(yarn.id, color.key)].price" type="number" min="0" step="0.01" :aria-label="`${selectedProduct.name}, ${yarn.name}, ${color.label} price`" :placeholder="`${color.label} price`" class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-7 pr-3 text-right text-sm font-bold text-slate-800 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:ring-indigo-900/40" />
                                                    </label>
                                                </td>
                                            </tr>
                                            <tr v-if="!filteredYarns.length"><td :colspan="colorGroups.length + 1" class="px-4 py-10 text-center text-xs text-slate-400">No yarn materials match “{{ yarnSearch }}”.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-if="form.errors.prices" class="mt-3 text-xs font-semibold text-red-600">{{ form.errors.prices }}</p>
                                <p class="mt-3 text-[11px] leading-relaxed text-slate-400">Leave a field blank when that combination is unavailable or has no price. Values are saved per kilogram in Philippine pesos.</p>
                                <div class="sticky bottom-0 -mx-4 mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-white/95 px-4 py-3 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95 sm:-mx-6 sm:px-6">
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><span v-if="form.isDirty" class="font-bold text-amber-600 dark:text-amber-400">Unsaved changes</span><span v-else>All changes saved</span></p>
                                    <button type="button" @click="save" :disabled="form.processing || !form.isDirty" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                                        <Check class="h-4 w-4" /> {{ form.processing ? 'Saving…' : 'Save pricing' }}
                                    </button>
                                </div>
                            </div>

                            <div v-else class="mt-6 rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-200">
                                <div class="flex items-start gap-3"><Tags class="mt-0.5 h-5 w-5 shrink-0" /><div><p class="font-bold">No yarn materials found in Inventory</p><p class="mt-1 text-xs leading-relaxed">Add yarn materials in the Inventory module. They will become available as fabric type options here for every product.</p></div></div>
                            </div>
                        </template>
                        <div v-else class="flex min-h-64 flex-col items-center justify-center text-center text-slate-400">
                            <Package class="h-10 w-10" /><p class="mt-3 text-sm font-bold">No products available.</p>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div v-if="showSettings" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" @click.self="showSettings = false">
            <form @submit.prevent="saveSettings" class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900">
                <header class="flex items-start gap-3 bg-gradient-to-br from-indigo-700 via-violet-700 to-fuchsia-800 p-5 text-white">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15"><Settings class="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1"><h2 class="font-black">Quotation settings</h2><p class="mt-1 truncate text-xs text-violet-100">Applies across all products</p></div>
                    <button type="button" aria-label="Close settings" @click="showSettings = false" class="rounded-lg bg-white/15 p-2 hover:bg-white/25"><X class="h-4 w-4" /></button>
                </header>
                <div class="space-y-2 p-5">
                    <label for="minimum-order-quantity" class="text-xs font-bold text-slate-700 dark:text-zinc-200">Default minimum order quantity (kg)</label>
                    <input id="minimum-order-quantity" v-model="settingsForm.minimum_order_quantity" type="number" min="0.01" step="0.01" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" placeholder="Enter MOQ in kilograms" />
                    <p class="text-[11px] leading-relaxed text-slate-500 dark:text-zinc-400">This global MOQ prefills every product quotation line, including every selected Pantone color and yarn combination. It also applies when quoting older inquiries.</p>
                    <p v-if="settingsForm.errors.minimum_order_quantity" class="text-xs font-semibold text-rose-600">{{ settingsForm.errors.minimum_order_quantity }}</p>
                </div>
                <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/40">
                    <button type="button" @click="showSettings = false" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">Cancel</button>
                    <button type="submit" :disabled="settingsForm.processing" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50">{{ settingsForm.processing ? 'Saving…' : 'Save MOQ' }}</button>
                </footer>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
