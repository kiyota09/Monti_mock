<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Search, X, ChevronRight, ChevronLeft, Package, Layers,
    Tag, Ruler, Weight, Palette, Clock,
    AlertTriangle, Boxes,
    ChevronDown, Info, Zap, Plus, Trash2,
    Pencil, Upload, ImageIcon, Check, ImageMinus, Sparkles
} from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProducts = computed(() => canEdit('INV', 'products'));

const ChevronRightIcon = ChevronRight;

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    products: { type: Array, default: () => [] },
    masterMaterials: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
});

const products = ref(props.products);
watch(() => props.products, v => (products.value = v), { deep: true });

// ── UI State ──────────────────────────────────────────────────────────────────
const isLoaded = ref(false);
const searchQuery = ref('');
const catFilter = ref('All');
const selectedProduct = ref(null);
const activeTab = ref('bom');
const expandedMat = ref(null);
const showAddProduct = ref(false);
const showEditProduct = ref(false);
const processing = ref(false);

// ── Confirmation States ───────────────────────────────────────────────────────
const showEditConfirm = ref(false);
const productToDelete = ref(null); 
const colorToDeleteInfo = ref(null);
const imageToDelete = ref(null);

// Per-card image slider index keyed by product.id
const cardSlide = ref({});
const slideIdx = (productId) => cardSlide.value[productId] ?? 0;

// ── Auto-slide ────────────────────────────────────────────────────────────────
const autoSlideIntervals = {};

const startAutoSlide = (productId, total) => {
    if (autoSlideIntervals[productId]) return;
    autoSlideIntervals[productId] = setInterval(() => {
        cardSlide.value[productId] = ((cardSlide.value[productId] ?? 0) + 1) % total;
    }, 3000);
};

const stopAutoSlide = (productId) => {
    if (autoSlideIntervals[productId]) {
        clearInterval(autoSlideIntervals[productId]);
        delete autoSlideIntervals[productId];
    }
};

const resetAutoSlide = (productId, total) => {
    stopAutoSlide(productId);
    startAutoSlide(productId, total);
};

const initAutoSlide = () => {
    products.value.forEach(p => {
        if (p.images && p.images.length > 1) {
            startAutoSlide(p.id, p.images.length);
        }
    });
};

const slideNext = (productId, total, e) => {
    e.stopPropagation();
    cardSlide.value[productId] = ((cardSlide.value[productId] ?? 0) + 1) % total;
    resetAutoSlide(productId, total);
};
const slidePrev = (productId, total, e) => {
    e.stopPropagation();
    cardSlide.value[productId] = ((cardSlide.value[productId] ?? 0) - 1 + total) % total;
    resetAutoSlide(productId, total);
};

onMounted(() => {
    setTimeout(() => (isLoaded.value = true), 60);
    initAutoSlide();
});

onUnmounted(() => {
    Object.values(autoSlideIntervals).forEach(id => clearInterval(id));
});

watch(() => products.value, () => initAutoSlide(), { deep: true });

// ── Color Management System ─────────────────────────────────────────────────
const newColorForm = ref({
    name: '',
    hex: '#3b82f6'
});

const addColor = () => {
    if (newColorForm.value.name && newColorForm.value.hex) {
        const addedColor = { 
            name: newColorForm.value.name, 
            hex: newColorForm.value.hex 
        };
        
        if (showAddProduct.value) {
            newProduct.value.colors.push(addedColor);
        } else if (showEditProduct.value) {
            editForm.value.colors.push(addedColor);
        }
        
        newColorForm.value.name = '';
        newColorForm.value.hex = '#3b82f6';
    }
};

const triggerDeleteColor = (index, isEdit = false) => {
    colorToDeleteInfo.value = { index, isEdit };
};

const confirmDeleteColor = () => {
    if (!colorToDeleteInfo.value) return;
    
    const { index, isEdit } = colorToDeleteInfo.value;
    if (isEdit) {
        editForm.value.colors.splice(index, 1);
    } else {
        newProduct.value.colors.splice(index, 1);
    }
    
    colorToDeleteInfo.value = null;
};

const isDarkColor = (hex) => {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    const brightness = (r * 299 + g * 587 + b * 114) / 1000;
    return brightness < 128;
};

// ── Add form ──────────────────────────────────────────────────────────────────
const blankForm = () => ({
    name: '',
    colors: [] 
});

const newProduct = ref(blankForm());

const addImageInput = ref(null);
const addImageFiles = ref([]);
const addImagePreviews = ref([]);

// FIX: Prevent duplicate image files
const onAddImageChange = (e) => {
    const files = Array.from(e.target.files || []);
    files.forEach(newFile => {
        // Check if this exact file already exists in the list
        const isDuplicate = addImageFiles.value.some(existingFile =>
            existingFile.name === newFile.name &&
            existingFile.size === newFile.size &&
            existingFile.lastModified === newFile.lastModified
        );
        if (!isDuplicate) {
            addImageFiles.value.push(newFile);
            const reader = new FileReader();
            reader.onload = ev => addImagePreviews.value.push(ev.target.result);
            reader.readAsDataURL(newFile);
        }
    });
    // Reset input so same file can be re-selected later if needed
    e.target.value = '';
};

const removeAddPreview = (i) => {
    addImageFiles.value.splice(i, 1);
    addImagePreviews.value.splice(i, 1);
};

const resetAddForm = () => {
    newProduct.value = blankForm();
    addImageFiles.value = [];
    addImagePreviews.value = [];
};

const submitProduct = () => {
    if (!newProduct.value.name || newProduct.value.colors.length === 0) return;
    processing.value = true;
    
    router.post(route('inv.manager.product.store'), {
        name: newProduct.value.name,
        colors: newProduct.value.colors,
        category: 'Uncategorized', 
        status: 'Active',
        images: addImageFiles.value,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { resetAddForm(); showAddProduct.value = false; },
        onFinish: () => (processing.value = false),
    });
};

// ── Edit form ─────────────────────────────────────────────────────────────────
const editForm = ref(blankForm());
const editProductId = ref(null);
const editExistingImages = ref([]); 
const editImageInput = ref(null);
const editImageFiles = ref([]);
const editImagePreviews = ref([]);

const onEditImageChange = (e) => {
    const files = Array.from(e.target.files || []);
    files.forEach(f => {
        editImageFiles.value.push(f);
        const reader = new FileReader();
        reader.onload = ev => editImagePreviews.value.push(ev.target.result);
        reader.readAsDataURL(f);
    });
    e.target.value = '';
};

const removeEditPreview = (i) => {
    editImageFiles.value.splice(i, 1);
    editImagePreviews.value.splice(i, 1);
};

const triggerDeleteImage = (imageId) => {
    imageToDelete.value = imageId;
};

const confirmDeleteImage = () => {
    if (!imageToDelete.value) return;
    processing.value = true;
    
    router.delete(route('inv.manager.product.image.destroy', { imageId: imageToDelete.value }), {
        preserveScroll: true,
        onSuccess: () => {
            editExistingImages.value = editExistingImages.value.filter(img => img.id !== imageToDelete.value);
            imageToDelete.value = null;
        },
        onFinish: () => (processing.value = false),
    });
};

const openEditModal = (product, e) => {
    e?.stopPropagation();
    editProductId.value = product.id;
    editExistingImages.value = [...(product.images ?? [])];
    editImageFiles.value = [];
    editImagePreviews.value = [];
    
    let existingColors = [];
    
    if (product.colors) {
        if (typeof product.colors === 'string') {
            try {
                existingColors = JSON.parse(product.colors);
            } catch(err) {
                console.error("Could not parse product colors:", err);
            }
        } 
        else if (Array.isArray(product.colors)) {
            existingColors = [...product.colors];
        }
        else if (typeof product.colors === 'object') {
            existingColors = Object.values(product.colors);
        }
    } 
    
    if (existingColors.length === 0 && (product.color_hex || product.colorHex)) {
        existingColors = [{ 
            name: product.color_name || product.colorName || 'Default Color', 
            hex: product.color_hex || product.colorHex 
        }];
    }

    editForm.value = {
        name: product.name,
        colors: existingColors
    };
    showEditProduct.value = true;
};

const triggerEditConfirm = () => {
    if (!editForm.value.name || editForm.value.colors.length === 0) return;
    showEditConfirm.value = true; 
};

const submitEdit = () => {
    processing.value = true;
    router.post(route('inv.manager.product.update', { id: editProductId.value }), {
        name: editForm.value.name,
        colors: editForm.value.colors, 
        new_images: editImageFiles.value,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showEditConfirm.value = false;
            showEditProduct.value = false;
            editProductId.value = null;
        },
        onFinish: () => (processing.value = false),
    });
};

// ── Delete product ────────────────────────────────────────────────────────────
const triggerDelete = (id, e) => {
    e?.stopPropagation();
    productToDelete.value = id; 
};

const confirmDeleteProduct = () => {
    if (!productToDelete.value) return;
    
    processing.value = true;
    if (selectedProduct.value?.id === productToDelete.value) {
        selectedProduct.value = null; 
    }
    
    router.delete(route('inv.manager.product.destroy', { id: productToDelete.value }), { 
        preserveScroll: true,
        onSuccess: () => {
            productToDelete.value = null; 
        },
        onFinish: () => (processing.value = false),
    });
};

// ── Computed ──────────────────────────────────────────────────────────────────
const categories = computed(() => ['All', ...new Set(products.value.map(p => p.category).filter(Boolean))]);

const filtered = computed(() => {
    let list = products.value;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(p =>
            p.name.toLowerCase().includes(q) ||
            p.sku?.toLowerCase().includes(q) ||
            p.product_id?.toLowerCase().includes(q)
        );
    }
    if (catFilter.value !== 'All') list = list.filter(p => p.category === catFilter.value);
    return [...list].sort((a, b) => {
        const aHas = a.images && a.images.length > 0 ? 0 : 1;
        const bHas = b.images && b.images.length > 0 ? 0 : 1;
        return aHas - bHas;
    });
});

const bomCost = (product) => product.materials?.reduce((s, m) => s + m.cost * m.qty, 0) || 0;
const margin = (product) => {
    if (!product.sellingPrice) return '0.0';
    return (((product.sellingPrice - product.unitCost) / product.sellingPrice) * 100).toFixed(1);
};
const bomHasAlert = (product) => product.materials?.some(m => m.stockStatus !== 'In Stock') || false;

const fmt = (n) => Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const openProduct = (product) => { selectedProduct.value = product; activeTab.value = 'bom'; expandedMat.value = null; };
const closeModal = () => { selectedProduct.value = null; };
</script>

<template>
    <Head title="Product Catalog | Monti Textile" />
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
                        <Sparkles class="h-3.5 w-3.5" /> Inventory · Catalog
                    </p>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Product Catalog <span v-if="!canEditProducts" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                    <p class="text-sm text-blue-100/90">Bill of materials, specifications, and raw material breakdown for every product.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                        {{ products.length }} Products
                    </span>
                    <button v-if="canEditProducts" @click="showAddProduct = true"
                        class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg transition-all duration-200 hover:scale-105 active:scale-95 inline-flex items-center gap-2">
                        <Plus class="w-4 h-4" /> Add Product
                    </button>
                </div>
            </div>

            <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="searchQuery" type="text" placeholder="Search product, SKU..."
                        class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                </div>
                <div class="flex gap-2">
                    <div class="relative">
                        <select v-model="catFilter"
                            class="appearance-none rounded-2xl bg-white/15 backdrop-blur px-4 py-3 pr-9 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25 outline-none hover:bg-white/25 transition [&>option]:text-gray-900">
                            <option v-for="c in categories" :key="c">{{ c }}</option>
                        </select>
                        <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-white/70 pointer-events-none" />
                    </div>
                    <button v-if="searchQuery || catFilter !== 'All'" @click="searchQuery = ''; catFilter = 'All'"
                        class="rounded-2xl bg-white/15 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25 backdrop-blur hover:bg-white/25 transition-all active:scale-95 flex items-center gap-1.5">
                        <X class="w-3.5 h-3.5" /> {{ filtered.length }}
                    </button>
                </div>
            </div>
        </div>

        <TransitionGroup name="card" tag="div" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="(product, i) in filtered" :key="product.id"
                class="group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 overflow-hidden shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 cursor-pointer flex flex-col"
                :style="`transition-delay: ${Math.min(i * 40, 400)}ms`"
                @click="openProduct(product)">
                <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-10" />
                <div class="absolute left-0 top-8 bottom-8 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300 z-10" />

                <div class="relative overflow-hidden flex-shrink-0 bg-slate-100 dark:bg-slate-800">

                    <template v-if="product.images && product.images.length > 1">
                        <div class="relative w-full aspect-square overflow-hidden"
                            @mouseenter="stopAutoSlide(product.id)"
                            @mouseleave="startAutoSlide(product.id, product.images.length)">
                            <div class="flex h-full transition-transform duration-300 ease-in-out"
                                :style="`transform: translateX(-${slideIdx(product.id) * 100}%)`">
                                <img v-for="img in product.images" :key="img.id" :src="img.url" :alt="product.name"
                                    class="h-full w-full object-cover flex-shrink-0" />
                            </div>

                            <button @click="slidePrev(product.id, product.images.length, $event)"
                                class="absolute left-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center hover:bg-black/70 transition z-10">
                                <ChevronLeft class="w-3.5 h-3.5" />
                            </button>
                            <button @click="slideNext(product.id, product.images.length, $event)"
                                class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center hover:bg-black/70 transition z-10">
                                <ChevronRightIcon class="w-3.5 h-3.5" />
                            </button>

                            <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1 z-10">
                                <button v-for="(_, di) in product.images" :key="di"
                                    @click.stop="cardSlide[product.id] = di"
                                    :class="['w-1.5 h-1.5 rounded-full transition-all', di === slideIdx(product.id) ? 'bg-white scale-125' : 'bg-white/50']" />
                            </div>
                        </div>
                    </template>

                    <template v-else-if="product.images && product.images.length === 1">
                        <img :src="product.images[0].url" :alt="product.name"
                            class="w-full aspect-square object-cover group-hover:scale-105 transition-transform duration-500" />
                    </template>

                    <template v-else>
                        <div class="h-2 w-full" :style="`background-color: ${(product.colors && product.colors.length > 0) ? (Array.isArray(product.colors) ? product.colors[0].hex : (JSON.parse(product.colors)[0]?.hex || '#64748b')) : (product.colorHex || '#64748b')}`" />
                    </template>

                    <div
                        class="absolute top-2 left-2 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10">
                        <button v-if="canEditProducts" @click="openEditModal(product, $event)"
                            class="w-7 h-7 rounded-lg bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm flex items-center justify-center shadow-sm hover:bg-blue-600 hover:text-white transition-all"
                            title="Edit product">
                            <Pencil class="w-3.5 h-3.5" />
                        </button>
                        
                        <button v-if="canEditProducts" @click="triggerDelete(product.id, $event)"
                            class="w-7 h-7 rounded-lg bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm flex items-center justify-center shadow-sm hover:bg-red-600 hover:text-white transition-all"
                            title="Delete product">
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <div class="p-5 flex flex-col gap-4 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                <span
                                    class="font-mono text-[10px] font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">
                                    {{ product.product_id }}
                                </span>
                                <span v-if="(product.subcategory || product.category) && (product.subcategory || product.category) !== 'Uncategorized'"
                                    class="text-[10px] font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">
                                    {{ product.subcategory || product.category }}
                                </span>
                            </div>
                            <h3
                                class="font-black text-slate-900 dark:text-white text-base leading-snug group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                {{ product.name }}
                            </h3>
                            <p class="font-mono text-[11px] text-slate-400 mt-0.5">{{ product.sku }}</p>
                        </div>
                        
                        <div class="flex -space-x-2 flex-shrink-0">
                            <template v-if="product.colors && product.colors.length > 0">
                                <div v-for="c in (Array.isArray(product.colors) ? product.colors : JSON.parse(product.colors)).slice(0, 3)" :key="c.hex" 
                                     class="w-6 h-6 rounded-full border-2 border-white dark:border-slate-900 shadow-sm"
                                     :style="`background-color: ${c.hex}`" :title="c.name" />
                                <div v-if="(Array.isArray(product.colors) ? product.colors : JSON.parse(product.colors)).length > 3" class="w-6 h-6 rounded-full border-2 border-white dark:border-slate-900 bg-slate-200 dark:bg-slate-800 text-[9px] font-bold text-slate-600 dark:text-slate-400 flex items-center justify-center">
                                    +{{ (Array.isArray(product.colors) ? product.colors : JSON.parse(product.colors)).length - 3 }}
                                </div>
                            </template>
                            <template v-else>
                                <div class="w-6 h-6 rounded-full border-2 border-white dark:border-slate-900 shadow-sm"
                                     :style="`background-color: ${product.colorHex || '#64748b'}`" />
                            </template>
                        </div>
                    </div>

                    <div
                        class="pt-3 mt-auto border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div v-if="product.materials?.length">
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">BOM Items</p>
                                <p class="text-sm font-black text-slate-800 dark:text-white">{{ product.materials.length }} materials</p>
                            </div>
                            <div v-if="product.sellingPrice">
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Margin</p>
                                <p class="text-sm font-black text-emerald-600">{{ margin(product) }}%</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-right" v-if="product.sellingPrice">
                                <p class="text-[10px] text-slate-400 font-bold">Selling Price</p>
                                <p class="text-sm font-black text-slate-900 dark:text-white">₱{{ fmt(product.sellingPrice) }}</p>
                            </div>
                            <div
                                class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center group-hover:bg-blue-600 transition-all">
                                <ChevronRight class="w-4 h-4 text-slate-400 group-hover:text-white" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="filtered.length === 0"
                class="col-span-full animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                    <Package class="h-9 w-9 text-indigo-400" />
                </div>
                <p class="text-sm font-black text-gray-700 dark:text-gray-200">No products match your filters.</p>
                <p class="text-xs text-gray-400 mt-1">Try a different search or category.</p>
                <button @click="searchQuery = ''; catFilter = 'All'"
                    class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
            </div>
        </TransitionGroup>
            </div>
        </div>

        <!-- Modal: Product Detail -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="selectedProduct"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/70 backdrop-blur-sm overflow-y-auto"
                @click.self="closeModal">
                <div
                    class="modal-panel relative overflow-hidden bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-4xl">

                    <div class="h-1.5 w-full bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800" />

                    <div v-if="selectedProduct.images && selectedProduct.images.length"
                        class="flex gap-2 p-4 border-b border-slate-100 dark:border-slate-800 overflow-x-auto">
                        <img v-for="img in selectedProduct.images" :key="img.id" :src="img.url"
                            :alt="selectedProduct.name"
                            class="h-24 w-24 object-cover rounded-xl flex-shrink-0 border border-slate-200 dark:border-slate-700" />
                    </div>

                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                <span
                                    class="font-mono text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 px-2 py-0.5 rounded-md">{{
                                        selectedProduct.product_id }}</span>
                                <span v-if="selectedProduct.category && selectedProduct.category !== 'Uncategorized'"
                                    class="text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 px-2 py-0.5 rounded-full">{{
                                        selectedProduct.category }}</span>
                            </div>
                            <h2 class="text-xl font-black text-slate-900 dark:text-white leading-tight">{{
                                selectedProduct.name }}</h2>
                            <p class="font-mono text-xs text-slate-400 mt-0.5">{{ selectedProduct.sku }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button v-if="canEditProducts" @click="openEditModal(selectedProduct); closeModal()"
                                class="p-2 rounded-xl text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"
                                title="Edit product">
                                <Pencil class="w-4 h-4" />
                            </button>
                            
                            <button v-if="canEditProducts" @click="triggerDelete(selectedProduct.id, $event)"
                                class="p-2 rounded-xl text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                <Trash2 class="w-4 h-4" />
                            </button>
                            
                            <button @click="closeModal"
                                class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-start gap-3">
                            <Palette class="w-5 h-5 text-slate-400 flex-shrink-0 mt-0.5" />
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-2">Available Colorways</p>
                                <div class="flex flex-wrap gap-2">
                                    <template v-if="selectedProduct.colors && selectedProduct.colors.length > 0">
                                        <div v-for="c in (Array.isArray(selectedProduct.colors) ? selectedProduct.colors : JSON.parse(selectedProduct.colors))" :key="c.hex" class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-800 pr-3 rounded-full border border-slate-200 dark:border-slate-700">
                                            <span class="w-5 h-5 rounded-full border border-black/10 dark:border-white/10" :style="`background-color: ${c.hex}`" />
                                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ c.name }}</span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-800 pr-3 rounded-full border border-slate-200 dark:border-slate-700">
                                            <span class="w-5 h-5 rounded-full" :style="`background-color: ${selectedProduct.colorHex || '#000000'}`" />
                                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ selectedProduct.colorName || 'No Color' }}</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- Modal: Add Product -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="showAddProduct"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/70 backdrop-blur-sm overflow-y-auto"
                @click.self="showAddProduct = false; resetAddForm()">
                <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-md my-auto overflow-hidden">

                    <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex items-center justify-between">
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                        <div class="relative">
                            <h3 class="text-lg font-black tracking-tight">Add New Product</h3>
                            <p class="text-xs text-blue-100/90 mt-0.5">Define product details and available colors.</p>
                        </div>
                        <button @click="showAddProduct = false; resetAddForm()"
                            class="relative p-2 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Product Name *</label>
                            <input v-model="newProduct.name" type="text"
                                placeholder="e.g. Premium Cotton Shirt"
                                class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200" />
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-800 pt-5">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Colors assigned *</p>
                                <span class="text-xs font-bold text-slate-400">
                                    {{ newProduct.colors.length }} added
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-4 mb-6 p-2 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 min-h-[80px] items-center">
                                <template v-if="newProduct.colors.length > 0">
                                    <div v-for="(color, index) in newProduct.colors" :key="index"
                                        class="relative group flex flex-col items-center">
                                        <div class="w-10 h-10 rounded-full border-2 border-slate-300 dark:border-slate-600 shadow-sm flex items-center justify-center transition-transform hover:scale-105"
                                            :style="{ backgroundColor: color.hex || '#CCCCCC' }" :title="color.name">
                                        </div>
                                        <span class="text-[9px] mt-1.5 font-bold text-slate-600 dark:text-slate-300 max-w-[50px] text-center truncate" :title="color.name">
                                            {{ color.name || 'Unnamed' }}
                                        </span>
                                        <button @click="triggerDeleteColor(index, false)" 
                                            class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 shadow-lg hover:bg-red-600 transition-all z-10 scale-90 group-hover:scale-100">
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>
                                </template>
                                <div v-else class="w-full text-center text-slate-400 text-xs italic py-2">
                                    No colors added yet.
                                </div>
                            </div>

                            <div class="bg-blue-50/50 dark:bg-blue-900/10 p-3 rounded-xl border border-blue-100 dark:border-blue-900/30 flex flex-col gap-3">
                                <p class="text-[9px] font-black text-blue-400 uppercase tracking-tighter">Add New Color</p>
                                <div class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <input v-model="newColorForm.name" type="text" placeholder="Color Name"
                                            class="w-full px-2 py-2 text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-slate-700 dark:text-slate-200" />
                                    </div>
                                    <div class="flex items-center gap-1 bg-white dark:bg-slate-800 p-1 rounded-lg border border-slate-200 dark:border-slate-700">
                                        <input v-model="newColorForm.hex" type="color"
                                            class="w-7 h-7 rounded border-none cursor-pointer bg-transparent p-0" />
                                        <input v-model="newColorForm.hex" type="text" placeholder="#000000"
                                            class="w-16 px-1 py-1 text-xs bg-transparent border-none focus:ring-0 text-slate-700 dark:text-slate-200 font-mono uppercase" />
                                    </div>
                                </div>
                                <button @click="addColor" :disabled="!newColorForm.name || !newColorForm.hex"
                                    class="w-full py-2 text-xs font-bold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                                    Add Color
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-800 pt-5">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Product Images</p>
                            <div v-if="addImagePreviews.length > 0" class="flex flex-wrap gap-2 mb-3">
                                <div v-for="(src, i) in addImagePreviews" :key="i" class="relative group">
                                    <img :src="src" class="w-16 h-16 object-cover rounded-lg border-2 border-white dark:border-slate-800 shadow-sm" />
                                    <button @click="removeAddPreview(i)"
                                        class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-slate-900 text-white rounded-full flex items-center justify-center shadow-lg hover:bg-red-500 transition">
                                        <X class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>

                            <input ref="addImageInput" type="file" multiple accept="image/*" class="hidden" @change="onAddImageChange" />
                            <button @click="$refs.addImageInput.click()"
                                class="flex flex-col items-center gap-1.5 py-6 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl text-slate-400 hover:border-blue-400 hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-all w-full">
                                <Upload class="w-5 h-5" />
                                <span class="text-[10px] font-bold">Upload Media</span>
                            </button>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex gap-3 rounded-b-2xl">
                        <button @click="showAddProduct = false; resetAddForm()"
                            class="flex-1 py-2.5 text-xs font-black uppercase tracking-widest rounded-xl border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-white transition">
                            Cancel
                        </button>
                        <button v-if="canEditProducts" @click="submitProduct"
                            :disabled="processing || !newProduct.name || newProduct.colors.length === 0"
                            class="flex-1 py-2.5 text-xs font-black uppercase tracking-widest rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition shadow-lg shadow-blue-500/20 disabled:opacity-30">
                            Create
                        </button>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- Modal: Edit Product -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="showEditProduct"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/70 backdrop-blur-sm overflow-y-auto"
                @click.self="showEditProduct = false">
                <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-md my-auto overflow-hidden">

                    <div class="relative overflow-hidden px-6 py-5 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 text-white flex items-center justify-between">
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                        <div class="relative">
                            <h3 class="text-lg font-black tracking-tight">Edit Product</h3>
                            <p class="text-xs text-blue-100/90 mt-0.5">Manage details and color availability.</p>
                        </div>
                        <button @click="showEditProduct = false"
                            class="relative p-2 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">

                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Product Name *</label>
                            <input v-model="editForm.name" type="text"
                                class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200" />
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-800 pt-5">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Colors assigned *</p>
                                <span class="text-xs font-bold text-slate-400">
                                    {{ editForm.colors?.length || 0 }} added
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-4 mb-6 p-2 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 min-h-[80px] items-center">
                                
                                <template v-if="editForm.colors && editForm.colors.length > 0">
                                    <div v-for="(color, index) in editForm.colors" :key="index"
                                        class="relative group flex flex-col items-center">
                                        <div class="w-10 h-10 rounded-full border-2 border-slate-300 dark:border-slate-600 shadow-sm flex items-center justify-center transition-transform hover:scale-105"
                                            :style="{ backgroundColor: color.hex || '#CCCCCC' }" :title="color.name">
                                        </div>
                                        <span class="text-[9px] mt-1.5 font-bold text-slate-600 dark:text-slate-300 max-w-[50px] text-center truncate" :title="color.name">
                                            {{ color.name || 'Unnamed' }}
                                        </span>

                                        <button @click="triggerDeleteColor(index, true)" 
                                            class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 shadow-lg hover:bg-red-600 transition-all z-10 scale-90 group-hover:scale-100">
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>
                                </template>

                                <div v-else class="w-full text-center text-slate-400 text-xs italic py-2">
                                    No colors currently assigned.
                                </div>
                            </div>

                            <div class="bg-blue-50/50 dark:bg-blue-900/10 p-3 rounded-xl border border-blue-100 dark:border-blue-900/30 flex flex-col gap-3">
                                <p class="text-[9px] font-black text-blue-400 uppercase tracking-tighter">Add New Color</p>
                                <div class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <input v-model="newColorForm.name" type="text" placeholder="Color Name"
                                            class="w-full px-2 py-2 text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-slate-700 dark:text-slate-200" />
                                    </div>
                                    <div class="flex items-center gap-1 bg-white dark:bg-slate-800 p-1 rounded-lg border border-slate-200 dark:border-slate-700">
                                        <input v-model="newColorForm.hex" type="color"
                                            class="w-7 h-7 rounded border-none cursor-pointer bg-transparent p-0" />
                                        <input v-model="newColorForm.hex" type="text" placeholder="#000000"
                                            class="w-16 px-1 py-1 text-xs bg-transparent border-none focus:ring-0 text-slate-700 dark:text-slate-200 font-mono uppercase" />
                                    </div>
                                </div>
                                <button @click="addColor" :disabled="!newColorForm.name || !newColorForm.hex"
                                    class="w-full py-2 text-xs font-bold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                                    Add Color
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-800 pt-5">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">
                                Product Images
                            </p>

                            <div v-if="editExistingImages.length > 0" class="mb-4">
                                <p class="text-[10px] text-slate-400 font-bold mb-2">Saved Images</p>
                                <div class="flex flex-wrap gap-2">
                                    <div v-for="img in editExistingImages" :key="img.id" class="relative">
                                        <img :src="img.url" class="w-16 h-16 object-cover rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm" />
                                        <button v-if="canEditProducts" @click="triggerDeleteImage(img.id)"
                                            class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center shadow-md hover:bg-red-600 transition z-10"
                                            title="Remove image">
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="editImagePreviews.length > 0" class="flex flex-wrap gap-2 mb-3">
                                <div v-for="(src, i) in editImagePreviews" :key="i" class="relative">
                                    <img :src="src" class="w-16 h-16 object-cover rounded-lg border-2 border-blue-400 dark:border-blue-500 shadow-sm" />
                                    <span class="absolute bottom-0.5 left-0.5 text-[8px] font-black bg-blue-600 text-white px-1 py-0.5 rounded shadow-sm">NEW</span>
                                    <button @click="removeEditPreview(i)"
                                        class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center shadow-md hover:bg-red-600 transition z-10"
                                        title="Remove">
                                        <X class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>

                            <input ref="editImageInput" type="file" multiple accept="image/*" class="hidden" @change="onEditImageChange" />
                            <button @click="$refs.editImageInput.click()"
                                class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl text-slate-500 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all w-full justify-center">
                                <Upload class="w-4 h-4" />
                                Upload additional images
                            </button>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex gap-3 bg-slate-50 dark:bg-slate-800/30 rounded-b-2xl">
                        <button @click="showEditProduct = false"
                            class="flex-1 py-2.5 text-sm font-bold rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        
                        <button v-if="canEditProducts" @click="triggerEditConfirm" :disabled="processing || !editForm.name || !editForm.colors || editForm.colors.length === 0"
                            class="flex-1 py-2.5 text-sm font-bold rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm shadow-blue-500/20 disabled:opacity-40 disabled:cursor-not-allowed">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>

        <!-- Confirmation Modals -->
        <Teleport to="body">
            <Transition name="modal">
            <div v-if="showEditConfirm" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" @click.self="showEditConfirm = false">
                <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-xl w-full max-w-sm overflow-hidden">
                    <div class="p-6 text-center">
                        <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 flex items-center justify-center mx-auto mb-4">
                            <Info class="w-6 h-6" />
                        </div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white mb-2">Save Changes?</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Are you sure you want to update this product's details and inventory data?</p>
                    </div>
                    <div class="p-4 bg-slate-50 dark:bg-slate-800/50 flex gap-3">
                        <button @click="showEditConfirm = false" class="flex-1 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700 rounded-xl transition">
                            Back
                        </button>
                        <button v-if="canEditProducts" @click="submitEdit" :disabled="processing" class="flex-1 py-2.5 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition shadow-sm disabled:opacity-50 flex justify-center items-center">
                            <span v-if="processing" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                            <span v-else>Confirm Save</span>
                        </button>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="modal">
            <div v-if="productToDelete" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" @click.self="productToDelete = null">
                <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden">
                    <div class="p-6 text-center">
                        <div class="w-16 h-16 rounded-full bg-red-50 dark:bg-red-900/20 text-red-500 flex items-center justify-center mx-auto mb-4 border-4 border-white dark:border-slate-900 shadow-sm">
                            <AlertTriangle class="w-8 h-8" />
                        </div>
                        <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Delete Product</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                            This action cannot be undone. This will permanently delete the product, its images, and remove it from all warehouse inventories.
                        </p>
                    </div>
                    <div class="p-4 bg-red-50/50 dark:bg-slate-800/50 flex gap-3 border-t border-red-100 dark:border-slate-800">
                        <button @click="productToDelete = null" :disabled="processing" class="flex-1 py-3 text-sm font-bold text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700 rounded-xl transition">
                            Cancel
                        </button>
                        <button v-if="canEditProducts" @click="confirmDeleteProduct" :disabled="processing" class="flex-1 py-3 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl transition shadow-sm shadow-red-500/20 disabled:opacity-50 flex justify-center items-center">
                            <span v-if="processing" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                            <span v-else>Yes, Delete It</span>
                        </button>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="modal">
            <div v-if="colorToDeleteInfo" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" @click.self="colorToDeleteInfo = null">
                <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden">
                    <div class="p-6 text-center">
                        <div class="w-16 h-16 rounded-full bg-red-50 dark:bg-red-900/20 text-red-500 flex items-center justify-center mx-auto mb-4 border-4 border-white dark:border-slate-900 shadow-sm">
                            <Palette class="w-8 h-8" />
                        </div>
                        <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Remove Color</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                            Are you sure you want to remove this color from the available options?
                        </p>
                    </div>
                    <div class="p-4 bg-red-50/50 dark:bg-slate-800/50 flex gap-3 border-t border-red-100 dark:border-slate-800">
                        <button @click="colorToDeleteInfo = null" class="flex-1 py-3 text-sm font-bold text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700 rounded-xl transition">
                            Cancel
                        </button>
                        <button @click="confirmDeleteColor" class="flex-1 py-3 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl transition shadow-sm shadow-red-500/20">
                            Remove Color
                        </button>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="modal">
            <div v-if="imageToDelete" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" @click.self="imageToDelete = null">
                <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden">
                    <div class="p-6 text-center">
                        <div class="w-16 h-16 rounded-full bg-red-50 dark:bg-red-900/20 text-red-500 flex items-center justify-center mx-auto mb-4 border-4 border-white dark:border-slate-900 shadow-sm">
                            <ImageMinus class="w-8 h-8" />
                        </div>
                        <h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">Delete Image</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                            Are you sure you want to permanently delete this image from the server? This action cannot be undone.
                        </p>
                    </div>
                    <div class="p-4 bg-red-50/50 dark:bg-slate-800/50 flex gap-3 border-t border-red-100 dark:border-slate-800">
                        <button @click="imageToDelete = null" :disabled="processing" class="flex-1 py-3 text-sm font-bold text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700 rounded-xl transition">
                            Cancel
                        </button>
                        <button v-if="canEditProducts" @click="confirmDeleteImage" :disabled="processing" class="flex-1 py-3 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl transition shadow-sm shadow-red-500/20 disabled:opacity-50 flex justify-center items-center">
                            <span v-if="processing" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                            <span v-else>Delete Image</span>
                        </button>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>

    </AuthenticatedLayout>
</template>

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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .modal-panel, .modal-leave-active .modal-panel { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from .modal-panel { opacity: 0; transform: translateY(20px) scale(0.97); }
.modal-leave-to .modal-panel { opacity: 0; transform: translateY(12px) scale(0.98); }
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>