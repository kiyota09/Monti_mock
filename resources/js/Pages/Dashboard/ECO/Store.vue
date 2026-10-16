<template>
    <Head title="Product Catalog - ECO" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Package class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                ECO · Inventory Feed
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Product Store</h1>
                            <p class="text-sm text-blue-100/90">Live product catalog synchronized with the Inventory module.</p>
                        </div>
                        <button @click="exportCatalog"
                            class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:bg-indigo-50 active:scale-95 transition flex items-center gap-2">
                            <Download class="h-4 w-4" /> Export Catalog
                        </button>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" @input="debouncedSearch" type="text"
                                placeholder="Search by name, SKU, product ID..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="relative">
                            <select v-model="selectedCategory" @change="filterProducts"
                                class="appearance-none rounded-2xl bg-white/15 pl-4 pr-10 py-3 text-xs font-black uppercase tracking-wide text-white backdrop-blur ring-1 ring-white/25 outline-none hover:bg-white/25 transition cursor-pointer [&>option]:text-gray-900">
                                <option v-for="cat in categories" :key="cat">{{ cat }}</option>
                            </select>
                            <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-white/70 pointer-events-none" />
                        </div>
                    </div>
                </div>

                <!-- Stat cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur p-5 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden" style="animation-delay:80ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Products</p>
                        <p class="text-3xl font-black text-gray-900 dark:text-white mt-1 tracking-tight">{{ products.length }}</p>
                    </div>
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur p-5 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden" style="animation-delay:140ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-cyan-400/20 to-blue-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Categories</p>
                        <p class="text-3xl font-black text-gray-900 dark:text-white mt-1 tracking-tight">{{ categories.length - 1 }}</p>
                    </div>
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur p-5 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden" style="animation-delay:200ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-emerald-400/20 to-teal-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">Active SKUs <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" /></p>
                        <p class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1 tracking-tight">{{ products.filter(p => p.status === 'Active').length }}</p>
                    </div>
                    <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur p-5 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden" style="animation-delay:260ms">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-amber-400/20 to-rose-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">Low Stock Alert <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" /></p>
                        <p class="text-3xl font-black text-amber-600 mt-1 tracking-tight">{{ lowStockCount }}</p>
                    </div>
                </div>

                <!-- Results meta -->
                <div v-if="searchQuery || selectedCategory !== 'All'" class="animate-fade-up flex items-center gap-2 text-xs font-bold text-gray-500 dark:text-gray-400">
                    <span class="rounded-full bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 px-3 py-1">{{ filteredProducts.length }} results</span>
                    <button @click="clearFilters" class="flex items-center gap-1 rounded-full bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-800 px-3 py-1 hover:border-indigo-200 hover:text-indigo-600 transition">
                        <X class="h-3.5 w-3.5" /> Clear filters
                    </button>
                </div>

                <!-- Product grid -->
                <TransitionGroup v-if="filteredProducts.length > 0" name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div v-for="(product, i) in filteredProducts" :key="product.id"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 overflow-hidden shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 flex flex-col cursor-pointer"
                        @click="openDetail(product)">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-10" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300 z-10" />

                        <div class="relative overflow-hidden bg-gray-100 dark:bg-zinc-800 h-56">
                            <div v-if="productImages(product).length > 0" class="relative w-full h-full">
                                <img :src="productImages(product)[currentImageIndex[product.id] || 0]" :alt="product.name"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                                <!-- Prev / Next arrows (2+ images) -->
                                <template v-if="productImages(product).length > 1">
                                    <button @click.stop="prevImage(product.id, productImages(product).length)"
                                        class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/45 p-1.5 text-white opacity-0 backdrop-blur transition group-hover:opacity-100 hover:bg-black/65 active:scale-95" title="Previous photo">
                                        <ChevronLeft class="h-4 w-4" />
                                    </button>
                                    <button @click.stop="nextImage(product.id, productImages(product).length)"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/45 p-1.5 text-white opacity-0 backdrop-blur transition group-hover:opacity-100 hover:bg-black/65 active:scale-95" title="Next photo">
                                        <ChevronRight class="h-4 w-4" />
                                    </button>
                                    <span class="absolute right-2 top-2 rounded-full bg-black/55 px-2 py-0.5 text-[10px] font-black text-white backdrop-blur">
                                        {{ (currentImageIndex[product.id] || 0) + 1 }}/{{ productImages(product).length }}
                                    </span>
                                </template>
                                <div v-if="productImages(product).length > 1" class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1">
                                    <button v-for="(_, idx) in productImages(product)" :key="idx"
                                        @click.stop="setImageIndex(product.id, idx)"
                                        :class="['h-1.5 rounded-full transition-all', ((currentImageIndex[product.id] || 0) === idx) ? 'w-4 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/80']" />
                                </div>
                            </div>
                            <div v-else class="h-full w-full flex items-center justify-center text-gray-300 dark:text-zinc-700">
                                <Package class="h-16 w-16 animate-bounce-soft" />
                            </div>

                            <div class="absolute top-3 right-3 z-10">
                                <span :class="product.status === 'Active' ? 'bg-emerald-500/90 text-white' : 'bg-slate-500/90 text-white'"
                                    class="text-[10px] font-black px-2.5 py-1 rounded-full backdrop-blur-sm uppercase inline-flex items-center gap-1 ring-1 ring-white/30">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ product.status }}
                                </span>
                            </div>
                        </div>

                        <div class="p-5 flex flex-col gap-3 flex-1">
                            <div>
                                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                    <span class="font-mono text-[10px] font-bold text-gray-400 bg-gray-100 dark:bg-zinc-800 px-2 py-0.5 rounded">
                                        {{ product.sku }}
                                    </span>
                                    <span v-if="product.category && product.category !== 'Uncategorized'" class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-2 py-0.5 rounded-full">
                                        {{ product.category }}
                                    </span>
                                </div>
                                <h3 class="font-black text-gray-900 dark:text-white text-base leading-tight tracking-tight group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                    {{ product.name }}
                                </h3>
                            </div>

                            <!-- <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="flex items-center gap-1.5 text-gray-500">
                                    <Weight class="w-3.5 h-3.5" />
                                    <span>{{ product.weight || '—' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-gray-500">
                                    <Ruler class="w-3.5 h-3.5" />
                                    <span class="truncate">{{ product.dimensions || '—' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-gray-500">
                                    <Boxes class="w-3.5 h-3.5" />
                                    <span>{{ product.stock_on_hand?.toLocaleString() || 0 }} units</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-gray-500">
                                    <Tag class="w-3.5 h-3.5" />
                                    <span>MOQ {{ product.moq || '—' }}</span>
                                </div>
                            </div> -->

                            <!-- <div class="pt-3 mt-auto border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase">Unit Price</p>
                                    <p class="text-lg font-black text-indigo-600 dark:text-indigo-400">₱{{ formatPrice(product.selling_price) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-gray-400 font-bold uppercase">Margin</p>
                                    <p class="text-sm font-black text-emerald-600">{{ marginPercent(product) }}%</p>
                                </div>
                            </div> -->
                        </div>
                    </div>
                </TransitionGroup>

                <div v-else
                    class="animate-fade-up col-span-full flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <Package class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No products match your filters.</p>
                    <p class="text-xs text-gray-400 mt-1">Try a different search or category.</p>
                    <button @click="clearFilters" class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">
                        Clear filters
                    </button>
                </div>
            </div>

            <Teleport to="body">
                <Transition name="modal">
                    <div v-if="showDetailModal && selectedProduct"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                        @click.self="showDetailModal = false">
                        <div class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur w-full max-w-4xl rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
                            <div class="relative overflow-hidden px-8 py-6 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex justify-between items-center flex-shrink-0">
                                <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                                <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                                <div class="relative flex items-center gap-4">
                                    <div class="h-12 w-12 rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur flex items-center justify-center animate-pop">
                                        <Eye class="h-6 w-6" />
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-black uppercase tracking-tight">Product Details</h3>
                                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">{{ selectedProduct.sku }}</p>
                                    </div>
                                </div>
                                <button @click="showDetailModal = false" class="relative p-2 bg-white/15 ring-1 ring-white/25 rounded-xl hover:bg-white/25 transition-all active:scale-95">
                                    <X class="h-5 w-5" />
                                </button>
                            </div>

                            <div class="p-6 sm:p-8 overflow-y-auto flex-1">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                    <div class="space-y-4">
                                        <div class="relative bg-gray-100 dark:bg-zinc-800 rounded-3xl overflow-hidden border border-gray-100 dark:border-zinc-800">
                                            <img v-if="productImages(selectedProduct).length" :src="productImages(selectedProduct)[detailImageIndex]" :alt="selectedProduct.name"
                                                class="w-full object-cover max-h-80" />
                                            <div v-else class="h-64 flex items-center justify-center text-gray-400">
                                                <Package class="h-16 w-16" />
                                            </div>
                                            <template v-if="productImages(selectedProduct).length > 1">
                                                <button @click="detailPrev" class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-black/45 p-2 text-white backdrop-blur hover:bg-black/65 active:scale-95">
                                                    <ChevronLeft class="h-4 w-4" />
                                                </button>
                                                <button @click="detailNext" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-black/45 p-2 text-white backdrop-blur hover:bg-black/65 active:scale-95">
                                                    <ChevronRight class="h-4 w-4" />
                                                </button>
                                                <span class="absolute bottom-3 right-3 rounded-full bg-black/55 px-2.5 py-1 text-[10px] font-black text-white">{{ detailImageIndex + 1 }}/{{ productImages(selectedProduct).length }}</span>
                                            </template>
                                        </div>
                                        <div v-if="productImages(selectedProduct).length > 1" class="flex gap-2 overflow-x-auto pb-2">
                                            <img v-for="(img, idx) in productImages(selectedProduct)" :key="img + idx" :src="img" @click="detailImageIndex = idx"
                                                :class="['h-20 w-20 rounded-2xl object-cover border-2 cursor-pointer transition-all hover:-translate-y-0.5', detailImageIndex === idx ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200 dark:border-zinc-700 hover:border-indigo-300']" />
                                        </div>
                                    </div>

                                    <div class="space-y-6">
                                        <div>
                                            <h2 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">{{ selectedProduct.name }}</h2>
                                            <div class="flex items-center gap-2 mt-2">
                                                <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ selectedProduct.product_id }}</span>
                                                <span v-if="selectedProduct.category && selectedProduct.category !== 'Uncategorized'" class="text-xs text-gray-400">•</span>
                                                <span v-if="selectedProduct.category && selectedProduct.category !== 'Uncategorized'" class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ selectedProduct.category }}</span>
                                            </div>
                                        </div>

                                        <!-- <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-5 space-y-3">
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Unit Price</span>
                                                <span class="text-2xl font-black text-indigo-600">₱{{ formatPrice(selectedProduct.selling_price) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Cost</span>
                                                <span class="font-bold text-gray-700 dark:text-gray-300">₱{{ formatPrice(selectedProduct.unit_cost) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Stock On Hand</span>
                                                <span :class="selectedProduct.stock_on_hand < 50 ? 'text-red-600' : 'text-emerald-600'" class="font-bold">
                                                    {{ selectedProduct.stock_on_hand?.toLocaleString() || 0 }} units
                                                </span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">MOQ</span>
                                                <span class="font-bold">{{ selectedProduct.moq || '—' }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Lead Time</span>
                                                <span class="font-bold">{{ selectedProduct.lead_time || '—' }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Certification</span>
                                                <span class="font-bold">{{ selectedProduct.certification || '—' }}</span>
                                            </div>
                                        </div> -->

                                        <div v-if="selectedProduct.description" class="rounded-2xl border border-indigo-100 dark:border-indigo-900/40 bg-indigo-50/50 dark:bg-indigo-900/10 pl-4 pr-4 py-3 italic text-sm text-gray-600 dark:text-gray-400 border-l-4 !border-l-indigo-500">
                                            {{ selectedProduct.description }}
                                        </div>

                                        <div v-if="selectedProduct.sizes && selectedProduct.sizes.length">
                                            <h4 class="text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">Available Sizes</h4>
                                            <div class="flex flex-wrap gap-2">
                                                <span v-for="sz in selectedProduct.sizes" :key="sz"
                                                    class="px-3 py-1 rounded-full bg-gray-100 dark:bg-zinc-800 text-xs font-bold text-gray-700 dark:text-gray-300 ring-1 ring-gray-200 dark:ring-zinc-700">
                                                    {{ sz }}
                                                </span>
                                            </div>
                                        </div>

                                        <div v-if="selectedProduct.specs && selectedProduct.specs.length">
                                            <h4 class="text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">Technical Specifications</h4>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div v-for="spec in selectedProduct.specs" :key="spec.label" class="flex justify-between rounded-xl border border-gray-100 dark:border-zinc-800 px-3 py-2">
                                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ spec.label }}</span>
                                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ spec.value }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="px-6 sm:px-8 py-5 border-t border-gray-100 dark:border-zinc-800 bg-gray-50/50 dark:bg-zinc-800/30 flex justify-end gap-3 flex-shrink-0">
                                <button @click="showDetailModal = false"
                                    class="px-6 py-3 rounded-2xl bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-zinc-800 hover:-translate-y-0.5 transition-all active:scale-95">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </Teleport>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import {
    Package,
    Search,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    X,
    Eye,
    Weight,
    Ruler,
    Boxes,
    Tag,
    Download
} from 'lucide-vue-next';

const props = defineProps({
    products: {
        type: Array,
        default: () => []
    }
});

// State
const searchQuery = ref('');
const selectedCategory = ref('All');
const showDetailModal = ref(false);
const selectedProduct = ref(null);
const currentImageIndex = ref({});
const detailImageIndex = ref(0);

// Normalize images: backend sends string[] but tolerate {url} objects.
const productImages = (product) => {
    if (!product || !product.images) return [];
    return product.images
        .map((img) => (typeof img === 'string' ? img : img?.url))
        .filter(Boolean);
};

// Derived categories
const categories = computed(() => {
    const cats = ['All', ...new Set(props.products.map(p => p.category).filter(Boolean))];
    return cats;
});

// Filtered products
const filteredProducts = computed(() => {
    let list = props.products;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(p =>
            p.name.toLowerCase().includes(q) ||
            p.sku.toLowerCase().includes(q) ||
            (p.product_id && p.product_id.toLowerCase().includes(q))
        );
    }
    if (selectedCategory.value !== 'All') {
        list = list.filter(p => p.category === selectedCategory.value);
    }
    return list;
});

// Low stock count
const lowStockCount = computed(() => {
    return props.products.filter(p => p.stock_on_hand < 100).length;
});

// Helper functions
const formatPrice = (value) => {
    return Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const marginPercent = (product) => {
    if (!product.selling_price || !product.unit_cost) return '0.0';
    const margin = ((product.selling_price - product.unit_cost) / product.selling_price) * 100;
    return margin.toFixed(1);
};

const setImageIndex = (id, idx) => {
    currentImageIndex.value = { ...currentImageIndex.value, [id]: idx };
};

const nextImage = (id, total) => {
    currentImageIndex.value = { ...currentImageIndex.value, [id]: ((currentImageIndex.value[id] || 0) + 1) % total };
};

const prevImage = (id, total) => {
    currentImageIndex.value = { ...currentImageIndex.value, [id]: ((currentImageIndex.value[id] || 0) - 1 + total) % total };
};

const detailNext = () => {
    const total = productImages(selectedProduct.value).length;
    if (total > 1) detailImageIndex.value = (detailImageIndex.value + 1) % total;
};

const detailPrev = () => {
    const total = productImages(selectedProduct.value).length;
    if (total > 1) detailImageIndex.value = (detailImageIndex.value - 1 + total) % total;
};

const openDetail = (product) => {
    selectedProduct.value = product;
    detailImageIndex.value = currentImageIndex.value[product.id] || 0;
    showDetailModal.value = true;
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedCategory.value = 'All';
};

let debounceTimer;
const debouncedSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        // Search logic is handled by the computed property
    }, 300);
};

const exportCatalog = () => {
    alert('Export functionality will be implemented.');
};

onMounted(() => {
    // Initialize image indices for all products
    props.products.forEach(p => {
        if (p.images && p.images.length) {
            currentImageIndex.value[p.id] = 0;
        }
    });
});
</script>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
</style>
