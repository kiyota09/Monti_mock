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
                                v-if="selectedProducts.length > 0"
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
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 overflow-hidden">

                        <!-- hover glow -->
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300 z-10" />

                        <!-- Selection Checkbox (always enabled) -->
                        <div class="absolute top-3 left-3 z-10">
                            <input
                                type="checkbox"
                                :value="product.id"
                                v-model="selectedProducts"
                                class="w-5 h-5 rounded-lg border-gray-300 text-indigo-600 focus:ring-indigo-500 shadow-lg cursor-pointer"
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
                                        <select class="w-full appearance-none pl-3 pr-8 py-1.5 text-xs bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-200 text-gray-700 dark:text-gray-300 font-medium cursor-pointer">
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
                                <button @click="openInquiryModal(product)"
                                    class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-105 active:scale-95 transition-all">
                                    Inquire
                                </button>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>
            </div>
        </div>

        <!-- Single Product Inquiry Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showSingleModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showSingleModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-600 via-blue-600 to-violet-600 p-5 relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <div class="relative flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30"><Package class="w-5 h-5 text-white" /></span>
                                <div>
                                    <h3 class="font-black text-white">Confirm Inquiry</h3>
                                    <p class="text-[11px] text-blue-100">{{ selectedProduct?.sku }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 text-center">
                            <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                Would you like to request a formal quotation for <strong class="text-gray-700 dark:text-gray-300">{{ selectedProduct?.name }}</strong>? Our ECO team will reach out to you within 24 hours.
                            </p>
                        </div>
                        <div class="p-4 bg-gray-50 dark:bg-zinc-800/50 flex gap-3 border-t border-gray-100 dark:border-zinc-800">
                            <button @click="showSingleModal = false" :disabled="submitting" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">
                                Cancel
                            </button>
                            <button @click="submitSingleInquiry" :disabled="submitting" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-black text-sm shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-50 flex justify-center items-center gap-2">
                                <span v-if="submitting" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                <template v-else>
                                    <Check class="w-4 h-4" />
                                    <span>Confirm</span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Bulk Inquiry Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showBulkModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showBulkModal = false">
                    <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-600 via-blue-600 to-violet-600 p-5 relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/15 blur-2xl" />
                            <div class="relative flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30"><Send class="w-5 h-5 text-white" /></span>
                                <div>
                                    <h3 class="font-black text-white">Confirm Bulk Inquiry</h3>
                                    <p class="text-[11px] text-blue-100">{{ selectedProducts.length }} product(s) selected</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <ul class="text-left text-sm text-gray-700 dark:text-gray-300 list-disc list-inside max-h-40 overflow-y-auto space-y-1">
                                <li v-for="pid in selectedProducts" :key="pid">
                                    {{ getProductName(pid) }}
                                </li>
                            </ul>
                            <p class="mt-3 text-sm text-gray-500">Our ECO team will contact you within 24 hours.</p>
                            <!-- Optional notes field -->
                            <div class="mt-4 text-left">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">
                                    Additional Notes <span class="font-medium normal-case text-gray-400">(optional)</span>
                                </label>
                                <textarea
                                    v-model="bulkNotes"
                                    rows="3"
                                    placeholder="e.g. preferred delivery date, volume requirements, special instructions..."
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 outline-none text-sm transition resize-none"
                                ></textarea>
                            </div>
                        </div>
                        <div class="p-4 bg-gray-50 dark:bg-zinc-800/50 flex gap-3 border-t border-gray-100 dark:border-zinc-800">
                            <button @click="showBulkModal = false" :disabled="bulkSubmitting" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">
                                Cancel
                            </button>
                            <button @click="submitBulkInquiry" :disabled="bulkSubmitting" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-black text-sm shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-50 flex justify-center items-center gap-2">
                                <span v-if="bulkSubmitting" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                <template v-else>
                                    <Send class="w-4 h-4" />
                                    <span>Send Inquiry</span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

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
import { Package, Search, ChevronDown, ChevronLeft, ChevronRight, X, Check, Send, Sparkles } from 'lucide-vue-next';

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

// Modal states
const showSingleModal = ref(false);
const showBulkModal = ref(false);
const selectedProduct = ref(null);
const submitting = ref(false);
const bulkSubmitting = ref(false);
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
    showSingleModal.value = true;
};

const submitSingleInquiry = async () => {
    if (!selectedProduct.value) return;
    submitting.value = true;
    try {
        await router.post(route('client.products.inquire', selectedProduct.value.id), {
            message: `I would like to request more information and a formal quotation for ${selectedProduct.value.name}.`
        });
        showSingleModal.value = false;
        showToast('success', 'Inquiry sent! You can continue the conversation in "My Conversations".');
    } catch (error) {
        showToast('error', 'Failed to send inquiry. Please try again.');
    } finally {
        submitting.value = false;
    }
};

// Bulk inquiry
const bulkNotes = ref('');

const openBulkInquiryModal = () => {
    if (selectedProducts.value.length === 0) return;
    bulkNotes.value = '';
    showBulkModal.value = true;
};

const submitBulkInquiry = async () => {
    if (selectedProducts.value.length === 0) return;
    bulkSubmitting.value = true;
    try {
        // Only send `message` when the client typed an optional note.
        // When empty the backend builds the full detailed product message.
        await router.post(route('client.products.bulk-inquire'), {
            product_ids: selectedProducts.value,
            ...(bulkNotes.value.trim() ? { message: bulkNotes.value.trim() } : {}),
        });
        showBulkModal.value = false;
        selectedProducts.value = [];
        bulkNotes.value = '';
        showToast('success', 'Bulk inquiry sent! Our team will contact you shortly.');
    } catch (error) {
        showToast('error', 'Failed to send bulk inquiry. Please try again.');
    } finally {
        bulkSubmitting.value = false;
    }
};

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 3000);
};
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
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from { opacity: 0; }
.modal-enter-from > div { transform: scale(0.92) translateY(14px); opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
