<template>
    <Head title="Product Catalog" />
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
                                <Sparkles class="h-3.5 w-3.5" /> B2B Catalog
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Premium Fabrics</h1>
                            <p class="text-sm text-blue-100/90">{{ filteredProducts.length }} product{{ filteredProducts.length !== 1 ? 's' : '' }} showing · select multiple for a bulk inquiry</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ selectedProducts.length }} selected
                            </span>
                            <button
                                v-if="selectedProducts.length >= 2"
                                @click="openBulkInquiryModal"
                                class="rounded-full bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition-all flex items-center gap-1.5"
                            >
                                <Send class="h-3.5 w-3.5" /> Inquire ({{ selectedProducts.length }})
                            </button>
                        </div>
                    </div>

                    <!-- Search + filters -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" @input="debouncedSearch" type="text" placeholder="Search by name or SKU..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                        <div class="relative">
                            <select v-model="selectedCategory" @change="filterProducts"
                                class="appearance-none rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/25 py-3 pl-4 pr-10 text-xs font-black uppercase tracking-wide text-white outline-none cursor-pointer hover:bg-white/25 transition [&>option]:text-gray-900">
                                <option value="All">All Categories</option>
                                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                            <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-white/70 pointer-events-none" />
                        </div>
                        <button v-if="searchQuery || selectedCategory !== 'All'" @click="clearFilters"
                            class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide bg-white/15 text-white hover:bg-white/25 backdrop-blur transition-all active:scale-95 flex items-center gap-1.5">
                            <X class="h-3.5 w-3.5" /> Clear
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="filteredProducts.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <Package class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No products match your filters</p>
                    <p class="text-xs text-gray-400 mt-1">Try a different search or category.</p>
                    <button @click="clearFilters"
                        class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
                </div>

                <!-- Products Grid with Checkboxes (no stock restrictions) -->
                <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div v-for="(product, i) in filteredProducts" :key="product.id"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        role="button" tabindex="0" @click="openProductDetails(product)" @keydown.enter="openProductDetails(product)"
                        class="group relative flex cursor-pointer flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden">

                        <!-- hover glow -->
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300 z-10" />

                        <!-- Selection Checkbox (always enabled) -->
                        <div class="absolute top-3 left-3 z-10">
                            <input
                                type="checkbox"
                                @click.stop
                                :value="product.id"
                                v-model="selectedProducts"
                                class="bulk-product-checkbox h-5 w-5 cursor-pointer shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                            />
                        </div>

                        <div class="relative overflow-hidden bg-gray-100 dark:bg-zinc-800 h-56">
                            <template v-if="productImages(product).length > 0">
                                <img :src="productImages(product)[cardIndex(product.id)]" :alt="product.name"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                                <template v-if="productImages(product).length > 1">
                                    <button @click.stop="prevCardImage(product.id, productImages(product).length)"
                                        class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/45 p-1.5 text-white opacity-0 backdrop-blur transition group-hover:opacity-100 hover:bg-black/65 active:scale-95" title="Previous photo">
                                        <ChevronLeft class="h-4 w-4" />
                                    </button>
                                    <button @click.stop="nextCardImage(product.id, productImages(product).length)"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/45 p-1.5 text-white opacity-0 backdrop-blur transition group-hover:opacity-100 hover:bg-black/65 active:scale-95" title="Next photo">
                                        <ChevronRight class="h-4 w-4" />
                                    </button>
                                    <span class="absolute bottom-2 right-2 rounded-full bg-black/55 px-2 py-0.5 text-[10px] font-black text-white">{{ cardIndex(product.id) + 1 }}/{{ productImages(product).length }}</span>
                                    <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1">
                                        <button v-for="(_, idx) in productImages(product)" :key="idx" @click.stop="setCardImage(product.id, idx)"
                                            :class="['h-1.5 rounded-full transition-all', cardIndex(product.id) === idx ? 'w-4 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/80']" />
                                    </div>
                                </template>
                            </template>
                            <div v-else class="h-full w-full flex items-center justify-center text-gray-300 dark:text-zinc-600">
                                <Package class="h-16 w-16" />
                            </div>
                            <span v-if="selectedProducts.includes(product.id)" class="absolute top-3 right-3 rounded-full bg-indigo-600 px-2.5 py-1 text-[9px] font-black uppercase text-white shadow-lg flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse" /> Selected
                            </span>
                        </div>

                        <div class="p-5 flex flex-col flex-1">
                            <div class="mb-3">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Available Colors:</p>
                                <template v-if="getParsedColors(product.colors).length > 0">
                                    <div v-if="getParsedColors(product.colors).length <= 3" class="flex items-center gap-1.5 flex-wrap">
                                        <div v-for="c in getParsedColors(product.colors)" :key="c.hex"
                                             class="w-5 h-5 rounded-full border border-gray-200 dark:border-zinc-700 shadow-sm group-hover:scale-110 transition-transform"
                                             :style="`background-color: ${c.hex}`" :title="c.name" />
                                    </div>
                                    <div v-else class="relative w-full max-w-[200px]">
                                        <select @click.stop class="w-full appearance-none pl-3 pr-8 py-1.5 text-xs bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-200 text-gray-700 dark:text-gray-300 font-medium cursor-pointer">
                                            <option v-for="c in getParsedColors(product.colors)" :key="c.hex" :value="c.hex">
                                                {{ c.name }}
                                            </option>
                                        </select>
                                        <ChevronDown class="absolute right-2.5 top-1/2 -translate-y-1/2 h-3 w-3 text-gray-400 pointer-events-none" />
                                    </div>
                                </template>
                                <template v-else>
                                    <div v-if="product.colorHex" class="flex items-center gap-1.5">
                                        <div class="w-5 h-5 rounded-full border border-gray-200 dark:border-zinc-700 shadow-sm"
                                             :style="`background-color: ${product.colorHex}`" :title="product.colorName" />
                                        <span class="text-xs text-gray-500 font-medium">{{ product.colorName }}</span>
                                    </div>
                                    <p v-else class="text-[10px] text-gray-400 italic">No colors specified</p>
                                </template>
                            </div>

                            <div class="mb-2">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-mono text-[10px] font-bold text-gray-400 bg-gray-100 dark:bg-zinc-800 px-2 py-0.5 rounded">{{ product.sku }}</span>
                                    <span v-if="product.category && product.category !== 'Uncategorized'" class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 px-2 py-0.5 rounded-full uppercase">{{ product.category }}</span>
                                </div>
                                <h3 class="font-black text-gray-900 dark:text-white text-base leading-tight tracking-tight group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ product.name }}</h3>
                            </div>

                            <div class="mt-auto flex items-center justify-end pt-3 border-t border-gray-100 dark:border-zinc-800">
                                <!-- Inquire button always enabled -->
                                <button @click.stop="openInquiryModal(product)"
                                    class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-105 active:scale-95 transition-all">
                                    Inquire
                                </button>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>
            </div>
        </div>

        <!-- Read-only product details modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showProductDetailModal && selectedProduct" class="fixed inset-0 z-[55] flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm" @click.self="showProductDetailModal = false">
                    <div class="relative my-auto w-full max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-2xl ring-1 ring-black/5 dark:bg-zinc-900 dark:ring-white/10">
                        <button @click="showProductDetailModal = false" title="Close" class="absolute right-4 top-4 z-30 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-500 shadow-md ring-1 ring-black/5 backdrop-blur transition hover:rotate-90 hover:bg-slate-900 hover:text-white dark:bg-zinc-800/90 dark:text-slate-300 dark:ring-white/10 dark:hover:bg-white dark:hover:text-slate-900">
                            <X class="h-4 w-4" />
                        </button>

                        <div class="grid md:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
                            <div class="relative flex min-h-[320px] flex-col gap-3 overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-4 sm:p-5">
                                <div class="pointer-events-none absolute -top-16 -left-16 h-56 w-56 rounded-full bg-white/10 blur-3xl animate-float" />
                                <div class="pointer-events-none absolute -bottom-20 -right-10 h-56 w-56 rounded-full bg-fuchsia-400/25 blur-3xl animate-float-delayed" />
                                <div class="pointer-events-none absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 20px 20px;" />
                                <template v-if="productImages(selectedProduct).length">
                                    <div class="relative aspect-square w-full overflow-hidden rounded-3xl bg-black/20 shadow-2xl shadow-indigo-950/40 ring-1 ring-white/25">
                                        <img :src="productImages(selectedProduct)[detailImageIndex] || productImages(selectedProduct)[0]" :alt="selectedProduct.name" class="absolute inset-0 h-full w-full object-cover" />
                                        <template v-if="productImages(selectedProduct).length > 1">
                                            <button @click="detailImageIndex = (detailImageIndex - 1 + productImages(selectedProduct).length) % productImages(selectedProduct).length" aria-label="Previous image" class="absolute left-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-800 shadow-lg backdrop-blur transition hover:scale-110"><ChevronLeft class="h-4 w-4" /></button>
                                            <button @click="detailImageIndex = (detailImageIndex + 1) % productImages(selectedProduct).length" aria-label="Next image" class="absolute right-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-800 shadow-lg backdrop-blur transition hover:scale-110"><ChevronRight class="h-4 w-4" /></button>
                                        </template>
                                        <span class="absolute bottom-3 left-3 rounded-full bg-black/50 px-3 py-1 text-[11px] font-bold text-white backdrop-blur">{{ detailImageIndex + 1 }} / {{ productImages(selectedProduct).length }}</span>
                                    </div>
                                    <div v-if="productImages(selectedProduct).length > 1" class="relative flex gap-2 overflow-x-auto pb-1">
                                        <button v-for="(image, index) in productImages(selectedProduct)" :key="`${image}-${index}`" @click="detailImageIndex = index" :class="['h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl transition-all', index === detailImageIndex ? 'ring-2 ring-white ring-offset-2 ring-offset-indigo-700' : 'opacity-60 ring-1 ring-white/30 hover:opacity-100']"><img :src="image" :alt="`${selectedProduct.name} image ${index + 1}`" class="h-full w-full object-cover" /></button>
                                    </div>
                                </template>
                                <div v-else class="relative flex min-h-[280px] flex-1 flex-col justify-between rounded-3xl bg-white/10 p-4 ring-1 ring-white/25 backdrop-blur">
                                    <div class="grid grid-cols-5 gap-2">
                                        <span v-for="(color, index) in getParsedColors(selectedProduct.colors)" :key="`${color.hex}-${index}`" class="aspect-square rounded-xl shadow-lg ring-2 ring-white/70" :style="{ backgroundColor: color.hex || '#CBD5E1' }" :title="color.name" />
                                        <span v-if="!getParsedColors(selectedProduct.colors).length" class="aspect-square rounded-xl shadow-lg ring-2 ring-white/70" :style="{ backgroundColor: selectedProduct.colorHex || '#64748b' }" />
                                    </div>
                                    <div class="mt-6 flex items-center gap-3 text-white"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30"><Package class="h-5 w-5" /></span><div><p class="text-sm font-black">Product colorways</p><p class="text-xs text-blue-100/90">{{ getParsedColors(selectedProduct.colors).length || 1 }} available variants</p></div></div>
                                </div>
                            </div>

                            <div class="flex min-w-0 flex-col gap-6 p-6 sm:p-8">
                                <div>
                                    <div class="mb-2.5 flex flex-wrap items-center gap-2">
                                        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-500 dark:bg-zinc-800 dark:text-slate-400">{{ selectedProduct.product_id }}</span>
                                        <span v-if="selectedProduct.category && selectedProduct.category !== 'Uncategorized'" class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-bold text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300"><Tag class="h-3 w-3" /> {{ selectedProduct.category }}</span>
                                    </div>
                                    <h2 class="text-2xl font-black leading-tight tracking-tight text-slate-900 dark:text-white sm:text-3xl">{{ selectedProduct.name }}</h2>
                                    <p v-if="selectedProduct.sku" class="mt-1 font-mono text-xs text-slate-400">{{ selectedProduct.sku }}</p>
                                </div>

                                <div>
                                    <div class="mb-3 flex items-center justify-between"><div class="flex items-center gap-2"><Palette class="h-4 w-4 text-slate-400" /><h3 class="text-sm font-black text-slate-800 dark:text-slate-100">Available colorways</h3></div><span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-zinc-800 dark:text-slate-400">{{ getParsedColors(selectedProduct.colors).length || (selectedProduct.colorHex ? 1 : 0) }}</span></div>
                                    <div class="grid max-h-56 grid-cols-2 gap-2.5 overflow-y-auto pr-1 sm:grid-cols-3">
                                        <template v-if="getParsedColors(selectedProduct.colors).length">
                                            <div v-for="(color, index) in getParsedColors(selectedProduct.colors)" :key="`${color.hex}-${index}`" class="flex min-w-0 items-center gap-2 rounded-2xl border border-slate-100 bg-slate-50/70 p-2 dark:border-zinc-800 dark:bg-zinc-800/50"><span class="h-10 w-10 flex-shrink-0 rounded-xl shadow-inner ring-1 ring-black/10 dark:ring-white/10" :style="{ backgroundColor: color.hex || '#CBD5E1' }" /><div class="min-w-0"><p class="truncate text-xs font-bold text-slate-800 dark:text-slate-100">{{ color.name }}</p><p class="font-mono text-[10px] uppercase text-slate-400">{{ color.hex }}</p></div></div>
                                        </template>
                                        <div v-else-if="selectedProduct.colorHex" class="flex items-center gap-2 rounded-2xl border border-slate-100 bg-slate-50/70 p-2 dark:border-zinc-800 dark:bg-zinc-800/50"><span class="h-10 w-10 flex-shrink-0 rounded-xl shadow-inner ring-1 ring-black/10" :style="{ backgroundColor: selectedProduct.colorHex }" /><div class="min-w-0"><p class="truncate text-xs font-bold text-slate-800 dark:text-slate-100">{{ selectedProduct.colorName || 'Color' }}</p><p class="font-mono text-[10px] uppercase text-slate-400">{{ selectedProduct.colorHex }}</p></div></div>
                                        <p v-else class="col-span-full rounded-2xl border border-dashed border-slate-200 p-4 text-center text-xs text-slate-400 dark:border-zinc-700">No colorways have been listed.</p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-violet-100 bg-violet-50/40 p-4 dark:border-violet-900/40 dark:bg-violet-950/20">
                                    <div class="mb-3 flex items-center justify-between gap-3"><div class="flex items-center gap-2"><Layers class="h-4 w-4 text-violet-600 dark:text-violet-300" /><div><h3 class="text-sm font-black text-slate-800 dark:text-slate-100">Eligible yarns</h3><p class="text-[10px] text-slate-500 dark:text-slate-400">Compatible inventory materials</p></div></div><span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-violet-700 shadow-sm dark:bg-zinc-900 dark:text-violet-300">{{ selectedProduct.yarnMaterials?.length || 0 }}</span></div>
                                    <div v-if="selectedProduct.yarnMaterials?.length" class="grid max-h-44 grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                                        <div v-for="material in selectedProduct.yarnMaterials" :key="material.id" class="flex min-w-0 items-center gap-2.5 rounded-xl border border-violet-100 bg-white p-2.5 dark:border-violet-900/40 dark:bg-zinc-900"><span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><Layers class="h-4 w-4" /></span><div class="min-w-0 flex-1"><p class="truncate text-xs font-bold text-slate-800 dark:text-slate-100">{{ material.display_name || material.name }}</p><p class="truncate font-mono text-[10px] text-slate-400">{{ material.mat_id }}<template v-if="material.unit"> · {{ material.unit }}</template></p></div><span class="rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-black text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ material.yarn_tier }}</span></div>
                                    </div>
                                    <p v-else class="rounded-xl border border-dashed border-violet-200 bg-white/60 px-3 py-3 text-xs text-slate-500 dark:border-violet-900/50 dark:bg-zinc-900/50 dark:text-slate-400">No eligible yarns have been assigned yet.</p>
                                </div>

                                <div class="mt-auto flex flex-wrap items-center gap-2.5 border-t border-slate-100 pt-5 dark:border-zinc-800">
                                    <button @click="showProductDetailModal = false; openInquiryModal(selectedProduct)" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-500/25 transition hover:-translate-y-0.5 hover:shadow-xl active:scale-95"><Send class="h-4 w-4" /> Request a quote</button>
                                    <button @click="showProductDetailModal = false" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-bold text-slate-500 transition hover:bg-slate-100 active:scale-95 dark:text-slate-300 dark:hover:bg-zinc-800">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Single Product Inquiry Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showSingleModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-3 backdrop-blur-sm sm:p-5" @click.self="showSingleModal = false">
                    <div class="flex max-h-[94vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                        <div class="relative flex flex-shrink-0 items-center gap-3 overflow-hidden bg-gradient-to-r from-indigo-600 via-blue-600 to-violet-600 p-5 text-white">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <span class="relative flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30"><Package class="h-5 w-5" /></span>
                            <div class="relative min-w-0 flex-1"><h3 class="font-black">Product inquiry</h3><p class="truncate text-[11px] text-blue-100">{{ selectedProduct?.name }} · {{ selectedProduct?.sku }}</p></div>
                            <button type="button" aria-label="Close inquiry" @click="showSingleModal = false" class="relative rounded-xl bg-white/15 p-2 hover:bg-white/25"><X class="h-4 w-4" /></button>
                        </div>
                        <div class="grid min-h-0 flex-1 gap-4 overflow-y-auto p-4 sm:grid-cols-2 sm:p-5">
                            <section class="min-w-0 rounded-2xl border border-violet-100 p-3 dark:border-violet-900/40">
                                <div class="mb-2 flex items-center justify-between gap-2"><div><h4 class="text-sm font-black text-slate-900 dark:text-white">Pantone colors</h4><p class="text-[10px] text-slate-500">Select one or more product colorways.</p></div><span class="rounded-full bg-violet-50 px-2 py-1 text-[10px] font-bold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ inquiryPantoneIds.length }} selected</span></div>
                                <input v-model="pantoneSearch" type="search" placeholder="Search Pantone name or hex" class="mb-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                <p v-if="getParsedColors(selectedProduct?.colors).length > 150 && !pantoneSearch" class="mb-1 text-[10px] text-slate-400">Showing the first 150 colorways. Search to find others.</p>
                                <div class="max-h-64 space-y-1 overflow-y-auto">
                                    <label v-for="pantone in filteredInquiryPantones" :key="pantone.pantone_id || `${pantone.name}-${pantone.hex}`" class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-100 p-2 hover:bg-violet-50 dark:border-zinc-800 dark:hover:bg-violet-950/30">
                                        <input v-model="inquiryPantoneIds" type="checkbox" :value="Number(pantone.pantone_id)" class="rounded border-slate-300 text-violet-600" />
                                        <span class="h-7 w-7 shrink-0 rounded-md border border-black/10" :style="{ backgroundColor: pantone.hex }" />
                                        <span class="min-w-0 flex-1 truncate text-[11px] font-bold text-slate-800 dark:text-zinc-100">{{ pantone.name }}</span>
                                        <span class="shrink-0 text-[9px] text-slate-400">{{ inquiryCategoryLabel(pantone.color_category) }}</span>
                                    </label>
                                    <p v-if="!filteredInquiryPantones.length" class="p-4 text-center text-xs text-slate-500">No Pantone colorways available for this product.</p>
                                </div>
                            </section>
                            <section class="min-w-0 rounded-2xl border border-blue-100 p-3 dark:border-blue-900/40">
                                <div class="mb-2 flex items-center justify-between gap-2"><div><h4 class="text-sm font-black text-slate-900 dark:text-white">Yarn types</h4><p class="text-[10px] text-slate-500">Select one or more compatible yarns.</p></div><span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-bold text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">{{ inquiryYarnIds.length }} selected</span></div>
                                <input v-model="yarnSearch" type="search" placeholder="Search yarn name, tier, or ID" class="mb-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                <div class="max-h-64 space-y-1 overflow-y-auto">
                                    <label v-for="yarn in filteredInquiryYarns" :key="yarn.id" class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-100 p-2 hover:bg-blue-50 dark:border-zinc-800 dark:hover:bg-blue-950/30">
                                        <input v-model="inquiryYarnIds" type="checkbox" :value="Number(yarn.id)" class="rounded border-slate-300 text-blue-600" />
                                        <span class="min-w-0 flex-1"><span class="block truncate text-[11px] font-bold text-slate-800 dark:text-zinc-100">{{ yarn.display_name || yarn.name }}</span><span class="block text-[9px] text-slate-400">{{ yarn.mat_id }} · {{ yarn.yarn_tier || 'Yarn' }}</span></span>
                                    </label>
                                    <p v-if="!filteredInquiryYarns.length" class="p-4 text-center text-xs text-slate-500">No compatible yarns are assigned.</p>
                                </div>
                            </section>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300">GSM <span class="font-normal text-slate-400">(optional)</span><input v-model="inquiryGsm" type="number" inputmode="numeric" min="1" max="10000" step="1" placeholder="e.g. 180" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></label>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300">Fabric texture <span class="font-normal text-slate-400">(optional)</span><select v-model="inquiryTexture" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"><option value="">Choose texture</option><option value="Hard">Hard</option><option value="Medium">Medium</option><option value="Soft">Soft</option></select></label>
                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300 sm:col-span-2">Note for the sales team <span class="font-normal text-slate-400">(optional)</span><textarea v-model="clientInquiryNote" rows="2" maxlength="800" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" placeholder="Quantity, delivery needs, or other details"></textarea></label>
                            <p v-if="inquiryError" class="text-xs font-semibold text-rose-600 sm:col-span-2">{{ inquiryError }}</p>
                        </div>
                        <div class="flex flex-shrink-0 gap-3 border-t border-slate-100 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <button type="button" @click="showSingleModal = false" :disabled="submitting" class="flex-1 rounded-xl bg-white py-2.5 text-sm font-bold text-slate-600 dark:bg-zinc-900 dark:text-zinc-300">Cancel</button>
                            <button type="button" @click="submitSingleInquiry" :disabled="submitting || !inquiryPantoneIds.length || !inquiryYarnIds.length" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 py-2.5 text-sm font-black text-white shadow-lg disabled:opacity-50"><Loader2 v-if="submitting" class="h-4 w-4 animate-spin" /><Send v-else class="h-4 w-4" />Send inquiry</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Bulk Inquiry Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showBulkModal" class="fixed inset-0 z-[60] flex items-center justify-center p-3 bg-black/60 backdrop-blur-sm sm:p-5" @click.self="closeBulkInquiryModal">
                    <div class="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                        <div class="bg-gradient-to-r from-indigo-600 via-blue-600 to-violet-600 p-5 relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <div class="relative flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30"><Send class="w-5 h-5 text-white" /></span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-black text-white">Bulk fabric inquiry</h3>
                                    <p class="text-[11px] text-blue-100">Configure Pantone colors, compatible yarns, GSM, and texture for each of your {{ selectedProducts.length }} selected designs.</p>
                                </div>
                                <button type="button" aria-label="Close bulk inquiry" @click="closeBulkInquiryModal" class="rounded-xl bg-white/15 p-2 text-white hover:bg-white/25"><X class="h-4 w-4" /></button>
                            </div>
                        </div>
                        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4 sm:p-5">
                            <article v-for="product in selectedProductRecords" :key="product.id" class="rounded-2xl border border-slate-200 p-4 dark:border-zinc-700">
                                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    <div class="min-w-0"><h4 class="truncate text-sm font-black text-slate-900 dark:text-white">{{ product.name }}</h4><p class="text-[10px] text-slate-500 dark:text-zinc-400">{{ product.sku }}<span v-if="product.category"> · {{ product.category }}</span></p></div>
                                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">{{ bulkSelections[product.id]?.pantoneIds.length || 0 }} colors · {{ bulkSelections[product.id]?.yarnIds.length || 0 }} yarns</span>
                                </div>
                                <div class="grid gap-4 lg:grid-cols-2">
                                    <section class="min-w-0 rounded-xl bg-violet-50/60 p-3 dark:bg-violet-950/20">
                                        <div class="mb-2 flex items-center justify-between"><h5 class="text-xs font-black text-slate-800 dark:text-zinc-100">Pantone colors <span class="font-normal text-slate-500">(select one or more)</span></h5><span class="text-[10px] text-slate-500">{{ getBulkPantones(product).length }}</span></div>
                                        <input v-model="bulkSelections[product.id].pantoneSearch" type="search" placeholder="Search colors by name or hex" class="mb-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                        <div class="max-h-44 space-y-1 overflow-y-auto">
                                            <label v-for="pantone in filteredBulkPantones(product)" :key="pantone.pantone_id" class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-100 bg-white p-2 hover:bg-violet-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-violet-950/30">
                                                <input v-model="bulkSelections[product.id].pantoneIds" type="checkbox" :value="Number(pantone.pantone_id)" class="bulk-option-checkbox" />
                                                <span class="h-6 w-6 shrink-0 rounded-md border border-black/10" :style="{ backgroundColor: pantone.hex }" />
                                                <span class="min-w-0 flex-1 truncate text-[11px] font-bold text-slate-800 dark:text-zinc-100">{{ pantone.name }}</span>
                                                <span class="text-[9px] text-slate-400">{{ inquiryCategoryLabel(pantone.color_category) }}</span>
                                            </label>
                                            <p v-if="!getBulkPantones(product).length" class="rounded-lg border border-dashed border-slate-200 p-3 text-center text-[11px] text-slate-500 dark:border-zinc-700">No Pantone colorways are available for this design.</p>
                                            <p v-else-if="!filteredBulkPantones(product).length" class="p-3 text-center text-[11px] text-slate-500">No colors match your search.</p>
                                        </div>
                                    </section>
                                    <section class="min-w-0 rounded-xl bg-blue-50/60 p-3 dark:bg-blue-950/20">
                                        <div class="mb-2 flex items-center justify-between"><h5 class="text-xs font-black text-slate-800 dark:text-zinc-100">Compatible yarns <span class="font-normal text-slate-500">(select one or more)</span></h5><span class="text-[10px] text-slate-500">{{ product.yarnMaterials?.length || 0 }}</span></div>
                                        <div class="max-h-52 space-y-1 overflow-y-auto">
                                            <label v-for="yarn in product.yarnMaterials || []" :key="yarn.id" class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-100 bg-white p-2 hover:bg-blue-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-blue-950/30">
                                                <input v-model="bulkSelections[product.id].yarnIds" type="checkbox" :value="Number(yarn.id)" class="bulk-option-checkbox" />
                                                <span class="min-w-0 flex-1"><span class="block truncate text-[11px] font-bold text-slate-800 dark:text-zinc-100">{{ yarn.display_name || yarn.name }}</span><span class="block text-[9px] text-slate-400">{{ yarn.mat_id }} · {{ yarn.yarn_tier || 'Yarn' }}</span></span>
                                            </label>
                                            <p v-if="!product.yarnMaterials?.length" class="rounded-lg border border-dashed border-slate-200 p-3 text-center text-[11px] text-slate-500 dark:border-zinc-700">No compatible yarns are assigned to this design.</p>
                                        </div>
                                    </section>
                                    <label class="text-xs font-bold text-slate-700 dark:text-zinc-200">GSM <span class="font-normal text-slate-400">(optional)</span><input v-model="bulkSelections[product.id].gsm" type="number" min="1" max="10000" step="1" placeholder="e.g. 180" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></label>
                                    <label class="text-xs font-bold text-slate-700 dark:text-zinc-200">Fabric texture <span class="font-normal text-slate-400">(optional)</span><select v-model="bulkSelections[product.id].texture" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"><option value="">Choose texture</option><option value="Hard">Hard</option><option value="Medium">Medium</option><option value="Soft">Soft</option></select></label>
                                </div>
                                <p v-if="!bulkSelections[product.id].pantoneIds.length || !bulkSelections[product.id].yarnIds.length" class="mt-3 text-[10px] font-semibold text-amber-700 dark:text-amber-300">Choose at least one available Pantone color and yarn for this design.</p>
                            </article>
                            <label class="block text-xs font-bold text-slate-700 dark:text-zinc-200">Note for the sales team <span class="font-normal text-slate-400">(optional)</span><textarea v-model="bulkNotes" rows="2" maxlength="1000" placeholder="Quantity, delivery needs, or other details" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></label>
                            <p v-if="bulkError" class="text-xs font-semibold text-rose-600">{{ bulkError }}</p>
                        </div>
                        <div class="flex flex-shrink-0 gap-3 border-t border-slate-100 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <button type="button" @click="closeBulkInquiryModal" :disabled="bulkSubmitting" class="flex-1 rounded-xl bg-white py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 dark:bg-zinc-900 dark:text-zinc-300">
                                Cancel
                            </button>
                            <button type="button" @click="submitBulkInquiry" :disabled="bulkSubmitting || !canSubmitBulkInquiry" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 py-2.5 text-sm font-black text-white shadow-lg shadow-indigo-500/30 transition-all hover:shadow-xl disabled:opacity-50">
                                <Loader2 v-if="bulkSubmitting" class="h-4 w-4 animate-spin" />
                                <template v-else>
                                    <Send class="h-4 w-4" />
                                    <span>Send bulk inquiry</span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <FabricInquiryNotice v-model="showInquiryNotice" @close="continueToInquiryConversation" />

        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-xl text-white font-bold text-sm"
                :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-red-600'">
                {{ toast.message }}
            </div>
        </Transition>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Package, Search, ChevronDown, ChevronLeft, ChevronRight, X, Check, Send, Loader2, Sparkles, Palette, Tag, Layers } from 'lucide-vue-next';
import FabricInquiryNotice from '@/Components/FabricInquiryNotice.vue';

const props = defineProps({
    products: {
        type: Array,
        default: () => []
    }
});

// Helper: parse colors
const getParsedColors = (colors) => {
    if (!colors) return [];
    if (Array.isArray(colors)) return colors;
    try {
        return JSON.parse(colors) || [];
    } catch (e) {
        return [];
    }
};

// ── Image carousel (supports string[] and {url}[] shapes) ──
const cardImageIndex = ref({});
const productImages = (product) => {
    if (!product?.images) return [];
    return product.images.map((img) => (typeof img === 'string' ? img : img?.url)).filter(Boolean);
};
const cardIndex = (id) => cardImageIndex.value[id] || 0;
const setCardImage = (id, idx) => { cardImageIndex.value = { ...cardImageIndex.value, [id]: idx }; };
const nextCardImage = (id, total) => { cardImageIndex.value = { ...cardImageIndex.value, [id]: (cardIndex(id) + 1) % total }; };
const prevCardImage = (id, total) => { cardImageIndex.value = { ...cardImageIndex.value, [id]: (cardIndex(id) - 1 + total) % total }; };
onMounted(() => {
    props.products.forEach((p) => { if (productImages(p).length) cardImageIndex.value[p.id] = 0; });
});

// Filter state
const searchQuery = ref('');
const selectedCategory = ref('All');

// Selection state
const selectedProducts = ref([]);
const selectedProductRecords = computed(() => selectedProducts.value
    .map((id) => props.products.find((product) => Number(product.id) === Number(id)))
    .filter(Boolean));

// Modal states
const showSingleModal = ref(false);
const showBulkModal = ref(false);
const showProductDetailModal = ref(false);
const selectedProduct = ref(null);
const detailImageIndex = ref(0);
const submitting = ref(false);
const inquiryPantoneIds = ref([]);
const inquiryYarnIds = ref([]);
const pantoneSearch = ref('');
const yarnSearch = ref('');
const clientInquiryNote = ref('');
const inquiryGsm = ref('');
const inquiryTexture = ref('');
const inquiryError = ref('');
const bulkSubmitting = ref(false);
const bulkSelections = ref({});
const bulkError = ref('');
const showInquiryNotice = ref(false);
const inquiryConversationUrl = ref('');
const toast = ref({ show: false, type: 'success', message: '' });

// Categories
const categories = computed(() => {
    const cats = new Set(props.products.map(p => p.category).filter(Boolean));
    return Array.from(cats);
});

// Filtered products
const filteredProducts = computed(() => {
    let list = props.products;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(p => p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q));
    }
    if (selectedCategory.value !== 'All') {
        list = list.filter(p => p.category === selectedCategory.value);
    }
    return list;
});

const getProductName = (id) => {
    const product = props.products.find(p => p.id === id);
    return product ? product.name : 'Unknown product';
};

const getBulkPantones = (product) => getParsedColors(product?.colors).filter((color) => color?.pantone_id);
const filteredBulkPantones = (product) => {
    const query = bulkSelections.value[product.id]?.pantoneSearch?.trim().toLowerCase() || '';
    const pantones = getBulkPantones(product);
    return (query ? pantones.filter((color) => `${color.name} ${color.hex}`.toLowerCase().includes(query)) : pantones).slice(0, 150);
};
const canSubmitBulkInquiry = computed(() => selectedProductRecords.value.length >= 2
    && selectedProductRecords.value.length === selectedProducts.value.length
    && selectedProductRecords.value.every((product) => {
        const selection = bulkSelections.value[product.id];
        return selection?.pantoneIds?.length && selection?.yarnIds?.length;
    }));

const filteredInquiryPantones = computed(() => {
    const query = pantoneSearch.value.trim().toLowerCase();
    const pantones = getParsedColors(selectedProduct.value?.colors).filter((color) => color.pantone_id);
    return (query ? pantones.filter((color) => `${color.name} ${color.hex}`.toLowerCase().includes(query)) : pantones).slice(0, 150);
});
const filteredInquiryYarns = computed(() => {
    const query = yarnSearch.value.trim().toLowerCase();
    const yarns = selectedProduct.value?.yarnMaterials || [];
    return query ? yarns.filter((yarn) => `${yarn.display_name} ${yarn.name} ${yarn.mat_id} ${yarn.yarn_tier}`.toLowerCase().includes(query)) : yarns;
});
const inquiryCategoryLabel = (category) => ({ white: 'White', light: 'Light', dark: 'Dark' }[category] || '');

let debounceTimer;
const debouncedSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {}, 300);
};

const filterProducts = () => {};
const clearFilters = () => {
    searchQuery.value = '';
    selectedCategory.value = 'All';
};

// Single inquiry – no stock check
const openInquiryModal = (product) => {
    selectedProduct.value = product;
    inquiryPantoneIds.value = [];
    inquiryYarnIds.value = [];
    pantoneSearch.value = '';
    yarnSearch.value = '';
    clientInquiryNote.value = '';
    inquiryGsm.value = '';
    inquiryTexture.value = '';
    inquiryError.value = '';
    showSingleModal.value = true;
};

const openProductDetails = (product) => {
    selectedProduct.value = product;
    detailImageIndex.value = cardIndex(product.id);
    showProductDetailModal.value = true;
};

const submitSingleInquiry = async () => {
    if (!selectedProduct.value || !inquiryPantoneIds.value.length || !inquiryYarnIds.value.length) return;
    submitting.value = true;
    inquiryError.value = '';
    const note = clientInquiryNote.value.trim();
    router.post(route('client.products.inquire', selectedProduct.value.id), {
        message: `I would like to request a formal quotation for ${selectedProduct.value.name}.${note ? ` Note: ${note}` : ''}`,
        pantone_ids: inquiryPantoneIds.value,
        yarn_material_ids: inquiryYarnIds.value,
        gsm: inquiryGsm.value === '' ? null : Number(inquiryGsm.value),
        texture: inquiryTexture.value || null,
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            showSingleModal.value = false;
            inquiryConversationUrl.value = page.props.flash?.inquiry_redirect_url || '';
            showInquiryNotice.value = true;
        },
        onError: (errors) => { inquiryError.value = Object.values(errors)[0] || 'Failed to send inquiry. Please try again.'; },
        onFinish: () => { submitting.value = false; },
    });
};

// Bulk inquiry
const bulkNotes = ref('');

const openBulkInquiryModal = () => {
    if (selectedProducts.value.length < 2) return;
    bulkNotes.value = '';
    bulkError.value = '';
    bulkSelections.value = Object.fromEntries(selectedProductRecords.value.map((product) => [product.id, {
        pantoneIds: [],
        yarnIds: [],
        pantoneSearch: '',
        gsm: '',
        texture: '',
    }]));
    showBulkModal.value = true;
};

const closeBulkInquiryModal = () => {
    if (bulkSubmitting.value) return;
    showBulkModal.value = false;
    bulkError.value = '';
};

const submitBulkInquiry = async () => {
    if (!canSubmitBulkInquiry.value || bulkSubmitting.value) return;
    bulkSubmitting.value = true;
    bulkError.value = '';
    router.post(route('client.products.bulk-inquire'), {
        product_ids: selectedProducts.value,
        selections: selectedProductRecords.value.map((product) => ({
            product_id: Number(product.id),
            pantone_ids: bulkSelections.value[product.id].pantoneIds,
            yarn_material_ids: bulkSelections.value[product.id].yarnIds,
            gsm: bulkSelections.value[product.id].gsm === '' ? null : Number(bulkSelections.value[product.id].gsm),
            texture: bulkSelections.value[product.id].texture || null,
        })),
        ...(bulkNotes.value.trim() ? { message: bulkNotes.value.trim() } : {}),
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            showBulkModal.value = false;
            selectedProducts.value = [];
            bulkNotes.value = '';
            bulkSelections.value = {};
            inquiryConversationUrl.value = page.props.flash?.inquiry_redirect_url || '';
            showInquiryNotice.value = true;
        },
        onError: (errors) => {
            bulkError.value = Object.values(errors)[0] || 'Failed to send bulk inquiry. Please review each product and try again.';
        },
        onFinish: () => { bulkSubmitting.value = false; },
    });
};

const continueToInquiryConversation = () => {
    const destination = inquiryConversationUrl.value;
    inquiryConversationUrl.value = '';
    if (destination) router.visit(destination);
};

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};
</script>

<style scoped>
.bulk-product-checkbox,
.bulk-option-checkbox {
    -webkit-appearance: none;
    appearance: none;
    flex: none;
    border: 2px solid #64748b;
    border-radius: 0.35rem;
    background-color: #fff;
    cursor: pointer;
    transition: background-color 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
}
.bulk-product-checkbox:checked,
.bulk-option-checkbox:checked {
    border-color: #15803d;
    background-color: #15803d;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='m3 8 3.2 3.2L13 4.5' fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2.2'/%3E%3C/svg%3E");
    background-position: center;
    background-repeat: no-repeat;
    background-size: 85%;
}
.bulk-option-checkbox { width: 1rem; height: 1rem; }
.bulk-product-checkbox:focus-visible,
.bulk-option-checkbox:focus-visible { outline: 2px solid #16a34a; outline-offset: 2px; }
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
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
