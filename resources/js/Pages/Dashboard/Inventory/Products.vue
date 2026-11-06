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
    yarnMaterials: { type: Array, default: () => [] },
    pantoneColors: { type: Array, default: () => [] },
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
const yarnSearch = ref('');
const pantoneSearch = ref('');
const showAddProduct = ref(false);
const showEditProduct = ref(false);
const processing = ref(false);

// UI only: which image is shown in the product detail modal gallery
const detailImg = ref(0);

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
const emptyColorways = () => [];
const productColors = (product) => {
    if (Array.isArray(product?.colors)) return product.colors;
    if (typeof product?.colors === 'string') {
        try {
            const parsed = JSON.parse(product.colors);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }
    if (product?.colorHex) return [{ name: product.colorName || 'Default Color', hex: product.colorHex }];
    return [];
};

const filteredPantoneColors = computed(() => {
    const query = pantoneSearch.value.trim().toLowerCase();
    const colors = query
        ? props.pantoneColors.filter((color) => color.name.toLowerCase().includes(query) || color.hex.toLowerCase().includes(query))
        : props.pantoneColors;
    return colors.slice(0, 100);
});

const hasPantoneColor = (colors, pantone) => colors.some((color) =>
    Number(color.pantone_id) === Number(pantone.id)
    || (color.name === pantone.name && String(color.hex).toLowerCase() === String(pantone.hex).toLowerCase())
);

const addColor = (pantone) => {
    if (!pantone) return;
    const colorList = showAddProduct.value ? newProduct.value.colors : editForm.value.colors;
    if (!hasPantoneColor(colorList, pantone)) {
        colorList.push({ pantone_id: pantone.id, name: pantone.name, hex: pantone.hex });
    }
};

const openAddModal = () => {
    resetAddForm();
    pantoneSearch.value = '';
    showAddProduct.value = true;
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
    colors: emptyColorways(),
    yarn_material_ids: [],
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
    pantoneSearch.value = '';
    addImageFiles.value = [];
    addImagePreviews.value = [];
};

const submitProduct = () => {
    if (!newProduct.value.name || newProduct.value.colors.length < 10) return;
    processing.value = true;
    
    router.post(route('inv.manager.product.store'), {
        name: newProduct.value.name,
        colors: newProduct.value.colors,
        yarn_material_ids: newProduct.value.yarn_material_ids,
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
        colors: existingColors,
        yarn_material_ids: (product.yarnMaterials || []).map((material) => material.id),
    };
    yarnSearch.value = '';
    pantoneSearch.value = '';
    showEditProduct.value = true;
};

const triggerEditConfirm = () => {
    if (!editForm.value.name || editForm.value.colors.length < 10) return;
    showEditConfirm.value = true; 
};

const submitEdit = () => {
    processing.value = true;
    router.post(route('inv.manager.product.update', { id: editProductId.value }), {
        name: editForm.value.name,
        colors: editForm.value.colors, 
        yarn_material_ids: editForm.value.yarn_material_ids,
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
const activeProductCount = computed(() => products.value.filter((product) => product.status === 'Active').length);
const totalColorways = computed(() => products.value.reduce((total, product) => total + productColors(product).length, 0));
const filteredYarnMaterials = computed(() => {
    const query = yarnSearch.value.trim().toLowerCase();
    if (!query) return props.yarnMaterials;
    return props.yarnMaterials.filter((material) =>
        [material.display_name, material.name, material.mat_id, material.yarn_tier]
            .some((value) => String(value || '').toLowerCase().includes(query))
    );
});

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
const openProduct = (product) => { selectedProduct.value = product; activeTab.value = 'bom'; expandedMat.value = null; detailImg.value = 0; };
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
                    <button v-if="canEditProducts" @click="openAddModal"
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

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-3" aria-label="Product catalog summary">
            <article class="group rounded-2xl border border-blue-100 bg-gradient-to-br from-white to-blue-50/80 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-blue-900/40 dark:from-zinc-900 dark:to-blue-950/30">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Catalog products</p>
                        <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ products.length }}</p>
                    </div>
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 transition group-hover:scale-105 dark:bg-blue-500/15 dark:text-blue-300"><Package class="h-5 w-5" /></span>
                </div>
            </article>
            <article class="group rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50/80 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-emerald-900/40 dark:from-zinc-900 dark:to-emerald-950/30">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Active products</p>
                        <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ activeProductCount }}</p>
                    </div>
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 transition group-hover:scale-105 dark:bg-emerald-500/15 dark:text-emerald-300"><Check class="h-5 w-5" /></span>
                </div>
            </article>
            <article class="group rounded-2xl border border-violet-100 bg-gradient-to-br from-white to-violet-50/80 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-violet-900/40 dark:from-zinc-900 dark:to-violet-950/30">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Available colorways</p>
                        <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ totalColorways }}</p>
                    </div>
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 transition group-hover:scale-105 dark:bg-violet-500/15 dark:text-violet-300"><Palette class="h-5 w-5" /></span>
                </div>
            </article>
        </section>

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
                        <div class="relative grid aspect-[16/9] grid-cols-5 grid-rows-2 gap-1.5 overflow-hidden bg-slate-100 p-2.5 dark:bg-zinc-800">
                            <span v-for="color in productColors(product)" :key="`${product.id}-${color.name}`" class="rounded-lg shadow-inner ring-1 ring-black/5 transition duration-300 group-hover:scale-[1.02]" :style="{ backgroundColor: color.hex || '#CBD5E1' }" :title="color.name" />
                            <div class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center justify-between bg-gradient-to-t from-slate-950/70 to-transparent px-3 pb-2 pt-8 text-white">
                                <span class="text-[10px] font-black uppercase tracking-[0.16em]">Color palette</span>
                                <span class="rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-black backdrop-blur">{{ productColors(product).length }} colors</span>
                            </div>
                        </div>
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
                            <template v-if="productColors(product).length > 0">
                                <div v-for="c in productColors(product).slice(0, 3)" :key="c.hex"
                                     class="w-6 h-6 rounded-full border-2 border-white dark:border-slate-900 shadow-sm"
                                     :style="`background-color: ${c.hex}`" :title="c.name" />
                                <div v-if="productColors(product).length > 3" class="w-6 h-6 rounded-full border-2 border-white dark:border-slate-900 bg-slate-200 dark:bg-slate-800 text-[9px] font-bold text-slate-600 dark:text-slate-400 flex items-center justify-center">
                                    +{{ productColors(product).length - 3 }}
                                </div>
                            </template>
                            <template v-else>
                                <div class="w-6 h-6 rounded-full border-2 border-white dark:border-slate-900 shadow-sm"
                                     :class="product.colorHex ? '' : 'bg-slate-500'"
                                     :style="product.colorHex ? { backgroundColor: product.colorHex } : {}" />
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
                class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden bg-slate-900/60 p-2 backdrop-blur-sm dark:bg-black/70 sm:p-4"
                @click.self="closeModal">
                <div
                    class="modal-panel relative h-[calc(100dvh-1rem)] max-h-[900px] w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-zinc-900 dark:ring-white/10 sm:h-[calc(100dvh-2rem)] sm:rounded-[2rem]">

                    <!-- Close -->
                    <button @click="closeModal" title="Close"
                        class="absolute right-4 top-4 z-30 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-500 shadow-md ring-1 ring-black/5 backdrop-blur transition hover:rotate-90 hover:bg-slate-900 hover:text-white dark:bg-zinc-800/90 dark:text-slate-300 dark:ring-white/10 dark:hover:bg-white dark:hover:text-slate-900">
                        <X class="h-4 w-4" />
                    </button>

                    <div class="grid h-full min-h-0 grid-rows-[minmax(0,0.8fr)_minmax(0,1.2fr)] md:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] md:grid-rows-1">

                        <!-- ── Left: gallery ── -->
                        <div class="relative flex min-h-0 flex-col gap-3 overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-3 sm:p-5">
                            <div class="pointer-events-none absolute -top-16 -left-16 h-56 w-56 rounded-full bg-white/10 blur-3xl animate-float" />
                            <div class="pointer-events-none absolute -bottom-20 -right-10 h-56 w-56 rounded-full bg-fuchsia-400/25 blur-3xl animate-float-delayed" />
                            <div class="pointer-events-none absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 20px 20px;" />

                            <!-- With photos -->
                            <template v-if="selectedProduct.images && selectedProduct.images.length">
                                <div class="relative min-h-0 flex-1 overflow-hidden rounded-2xl bg-black/20 shadow-2xl shadow-indigo-950/40 ring-1 ring-white/25 sm:rounded-3xl">
                                    <Transition name="fade-img" mode="out-in">
                                        <img :key="(selectedProduct.images[detailImg] || selectedProduct.images[0]).id"
                                            :src="(selectedProduct.images[detailImg] || selectedProduct.images[0]).url"
                                            :alt="selectedProduct.name"
                                            class="absolute inset-0 h-full w-full object-cover" />
                                    </Transition>

                                    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-slate-950/50 to-transparent" />

                                    <template v-if="selectedProduct.images.length > 1">
                                        <button @click="detailImg = (detailImg - 1 + selectedProduct.images.length) % selectedProduct.images.length"
                                            class="absolute left-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-800 shadow-lg backdrop-blur transition hover:scale-110 active:scale-95">
                                            <ChevronLeft class="h-4 w-4" />
                                        </button>
                                        <button @click="detailImg = (detailImg + 1) % selectedProduct.images.length"
                                            class="absolute right-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-800 shadow-lg backdrop-blur transition hover:scale-110 active:scale-95">
                                            <ChevronRightIcon class="h-4 w-4" />
                                        </button>
                                    </template>

                                    <span class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1 text-[11px] font-bold text-white backdrop-blur">
                                        <ImageIcon class="h-3.5 w-3.5" />
                                        {{ Math.min(detailImg, selectedProduct.images.length - 1) + 1 }} / {{ selectedProduct.images.length }}
                                    </span>
                                </div>

                                <!-- Thumbnails -->
                                <div v-if="selectedProduct.images.length > 1" class="relative flex gap-2 overflow-x-auto pb-1">
                                    <button v-for="(img, ti) in selectedProduct.images" :key="img.id"
                                        @click="detailImg = ti"
                                        :class="['h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl transition-all duration-200',
                                            ti === detailImg
                                                ? 'ring-2 ring-white ring-offset-2 ring-offset-indigo-700 scale-100'
                                                : 'opacity-60 ring-1 ring-white/30 hover:opacity-100 hover:scale-105']">
                                        <img :src="img.url" :alt="selectedProduct.name" class="h-full w-full object-cover" />
                                    </button>
                                </div>
                            </template>

                            <!-- No photos: color mosaic -->
                            <template v-else>
                                <div class="relative flex min-h-0 flex-1 flex-col justify-between rounded-3xl bg-white/10 p-4 ring-1 ring-white/25 backdrop-blur">
                                    <div class="grid grid-cols-5 gap-2">
                                        <template v-if="productColors(selectedProduct).length > 0">
                                            <span v-for="(c, ci) in productColors(selectedProduct)" :key="`${c.hex}-${ci}`"
                                                class="aspect-square rounded-xl shadow-lg ring-2 ring-white/70 transition hover:scale-110"
                                                :style="{ backgroundColor: c.hex || '#CBD5E1' }" :title="c.name" />
                                        </template>
                                        <span v-else class="aspect-square rounded-xl shadow-lg ring-2 ring-white/70"
                                            :class="selectedProduct.colorHex ? '' : 'bg-slate-500'"
                                            :style="selectedProduct.colorHex ? { backgroundColor: selectedProduct.colorHex } : {}" />
                                    </div>
                                    <div class="mt-6 flex items-center gap-3 text-white">
                                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
                                            <ImageIcon class="h-5 w-5" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-black">No photos yet</p>
                                            <p class="text-xs text-blue-100/90">Showing the colorway palette instead.</p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- ── Right: details ── -->
                        <div class="min-h-0 min-w-0 overflow-y-auto overscroll-contain p-4 sm:p-8">
                            <div class="flex min-w-0 flex-col gap-6">

                            <!-- Title block -->
                            <div class="pr-10">
                                <div class="mb-2.5 flex flex-wrap items-center gap-2">
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-500 dark:bg-zinc-800 dark:text-slate-400">{{ selectedProduct.product_id }}</span>
                                    <span v-if="selectedProduct.category && selectedProduct.category !== 'Uncategorized'"
                                        class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-bold text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                                        <Tag class="h-3 w-3" /> {{ selectedProduct.category }}
                                    </span>
                                    <span v-if="selectedProduct.status"
                                        :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-bold',
                                            selectedProduct.status === 'Active'
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-slate-400']">
                                        <span :class="['h-1.5 w-1.5 rounded-full', selectedProduct.status === 'Active' ? 'bg-emerald-500' : 'bg-slate-400']" />
                                        {{ selectedProduct.status }}
                                    </span>
                                </div>
                                <h2 class="text-2xl font-black leading-tight tracking-tight text-slate-900 dark:text-white sm:text-3xl">{{ selectedProduct.name }}</h2>
                                <p v-if="selectedProduct.sku" class="mt-1 font-mono text-xs text-slate-400">{{ selectedProduct.sku }}</p>
                            </div>

                            <!-- Stat tiles -->
                            <div class="grid grid-cols-2 gap-3">
                                <div v-if="selectedProduct.sellingPrice"
                                    class="group rounded-2xl border border-blue-100 bg-gradient-to-br from-white to-blue-50/80 p-3.5 transition hover:-translate-y-0.5 hover:shadow-md dark:border-blue-900/40 dark:from-zinc-900 dark:to-blue-950/30">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-bold text-slate-500 dark:text-slate-400">Selling price</p>
                                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300"><Tag class="h-4 w-4" /></span>
                                    </div>
                                    <p class="mt-1.5 text-xl font-black text-slate-900 dark:text-white">₱{{ fmt(selectedProduct.sellingPrice) }}</p>
                                </div>

                                <div v-if="selectedProduct.sellingPrice"
                                    class="group rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50/80 p-3.5 transition hover:-translate-y-0.5 hover:shadow-md dark:border-emerald-900/40 dark:from-zinc-900 dark:to-emerald-950/30">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-bold text-slate-500 dark:text-slate-400">Margin</p>
                                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"><Zap class="h-4 w-4" /></span>
                                    </div>
                                    <p class="mt-1.5 text-xl font-black text-emerald-600 dark:text-emerald-400">{{ margin(selectedProduct) }}%</p>
                                </div>

                                <div v-if="selectedProduct.materials?.length"
                                    class="group rounded-2xl border border-violet-100 bg-gradient-to-br from-white to-violet-50/80 p-3.5 transition hover:-translate-y-0.5 hover:shadow-md dark:border-violet-900/40 dark:from-zinc-900 dark:to-violet-950/30">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-bold text-slate-500 dark:text-slate-400">Bill of materials</p>
                                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><Layers class="h-4 w-4" /></span>
                                    </div>
                                    <p class="mt-1.5 text-xl font-black text-slate-900 dark:text-white">{{ selectedProduct.materials.length }} <span class="text-sm font-bold text-slate-400">materials</span></p>
                                </div>

                                <div
                                    class="group rounded-2xl border border-fuchsia-100 bg-gradient-to-br from-white to-fuchsia-50/80 p-3.5 transition hover:-translate-y-0.5 hover:shadow-md dark:border-fuchsia-900/40 dark:from-zinc-900 dark:to-fuchsia-950/30">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-bold text-slate-500 dark:text-slate-400">Colorways</p>
                                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300"><Palette class="h-4 w-4" /></span>
                                    </div>
                                    <p class="mt-1.5 text-xl font-black text-slate-900 dark:text-white">{{ productColors(selectedProduct).length || 1 }} <span class="text-sm font-bold text-slate-400">available</span></p>
                                </div>
                            </div>

                            <!-- Eligible yarn materials -->
                            <div class="rounded-2xl border border-violet-100 bg-violet-50/40 p-4 dark:border-violet-900/40 dark:bg-violet-950/20">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><Layers class="h-4 w-4" /></span>
                                        <div>
                                            <h3 class="text-sm font-black text-slate-800 dark:text-slate-100">Eligible yarns</h3>
                                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Yarn options that can be used for this product</p>
                                        </div>
                                    </div>
                                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-violet-700 shadow-sm dark:bg-zinc-900 dark:text-violet-300">{{ selectedProduct.yarnMaterials?.length || 0 }}</span>
                                </div>
                                <div v-if="selectedProduct.yarnMaterials?.length" class="flex flex-wrap gap-2">
                                    <span v-for="material in selectedProduct.yarnMaterials" :key="material.id" class="inline-flex items-center gap-2 rounded-xl border border-violet-100 bg-white px-3 py-2 dark:border-violet-900/40 dark:bg-zinc-900">
                                        <span class="h-2 w-2 rounded-full bg-violet-500" />
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ material.display_name || material.name }}</span>
                                        <span v-if="material.mat_id" class="font-mono text-[9px] text-slate-400">{{ material.mat_id }}</span>
                                    </span>
                                </div>
                                <p v-else class="rounded-xl border border-dashed border-violet-200 bg-white/60 px-3 py-3 text-xs text-slate-500 dark:border-violet-900/50 dark:bg-zinc-900/50 dark:text-slate-400">No yarn options assigned. Edit this product to select compatible yarns.</p>
                            </div>

                            <!-- Stock alert -->
                            <div v-if="bomHasAlert(selectedProduct)"
                                class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 dark:border-amber-500/30 dark:bg-amber-500/10">
                                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300"><AlertTriangle class="h-4 w-4" /></span>
                                <p class="text-xs font-medium leading-relaxed text-amber-800 dark:text-amber-200">Some materials in this product's bill of materials are not fully in stock.</p>
                            </div>

                            <!-- Colorways -->
                            <div>
                                <div class="mb-3 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Palette class="h-4 w-4 text-slate-400" />
                                        <h3 class="text-sm font-black text-slate-800 dark:text-slate-100">Available colorways</h3>
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-zinc-800 dark:text-slate-400">{{ productColors(selectedProduct).length || 1 }}</span>
                                </div>

                                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                                    <template v-if="productColors(selectedProduct).length > 0">
                                        <div v-for="(c, ci) in productColors(selectedProduct)" :key="`${c.hex}-${ci}`"
                                            class="group flex items-center gap-2.5 rounded-2xl border border-slate-100 bg-slate-50/70 p-2 transition duration-200 hover:-translate-y-0.5 hover:border-indigo-200 hover:bg-white hover:shadow-md dark:border-zinc-800 dark:bg-zinc-800/50 dark:hover:border-indigo-700 dark:hover:bg-zinc-800">
                                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl shadow-inner ring-1 ring-black/10 transition group-hover:scale-105 dark:ring-white/10"
                                                :style="`background-color: ${c.hex}`">
                                                <Check class="h-4 w-4 opacity-0 transition group-hover:opacity-100"
                                                :class="isDarkColor(c.hex) ? 'text-white' : 'text-slate-900'" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-xs font-bold text-slate-800 dark:text-slate-100" :title="c.name">{{ c.name }}</p>
                                                <p class="font-mono text-[10px] uppercase text-slate-400">{{ c.hex }}</p>
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <p class="col-span-full rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-zinc-700 dark:text-slate-400">No Pantone colorways selected.</p>
                                    </template>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="sticky bottom-0 z-20 -mx-4 mt-auto flex flex-wrap items-center gap-2.5 border-t border-slate-100 bg-white/95 px-4 py-3 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95 sm:-mx-8 sm:px-8">
                                <button v-if="canEditProducts" @click="openEditModal(selectedProduct); closeModal()"
                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-500/25 transition hover:-translate-y-0.5 hover:shadow-xl active:scale-95"
                                    title="Edit product">
                                    <Pencil class="h-4 w-4" /> Edit product
                                </button>

                                <button v-if="canEditProducts" @click="triggerDelete(selectedProduct.id, $event)"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 px-4 py-2.5 text-sm font-bold text-red-600 transition hover:bg-red-600 hover:text-white active:scale-95 dark:border-red-500/30 dark:text-red-400 dark:hover:bg-red-600 dark:hover:text-white"
                                    title="Delete product">
                                    <Trash2 class="h-4 w-4" /> Delete
                                </button>

                                <button @click="closeModal"
                                    class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-bold text-slate-500 transition hover:bg-slate-100 active:scale-95 dark:text-slate-300 dark:hover:bg-zinc-800">
                                    Close
                                </button>
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

                        <div class="rounded-2xl border border-violet-100 bg-violet-50/50 p-4 dark:border-violet-900/40 dark:bg-violet-950/20">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div><p class="text-[10px] font-black uppercase tracking-widest text-violet-700 dark:text-violet-300">Compatible yarns</p><p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Choose yarn materials that can be used for this product.</p></div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-violet-700 dark:bg-zinc-900 dark:text-violet-300">{{ newProduct.yarn_material_ids.length }} selected</span>
                            </div>
                            <input v-model="yarnSearch" type="search" placeholder="Search yarn name, tier, or material ID..." class="mb-2 w-full rounded-xl border border-violet-100 bg-white px-3 py-2 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-slate-200" />
                            <div v-if="yarnMaterials.length" class="max-h-44 space-y-1 overflow-y-auto rounded-xl border border-violet-100 bg-white/70 p-2 dark:border-zinc-800 dark:bg-zinc-900/60">
                                <label v-for="material in filteredYarnMaterials" :key="material.id" class="flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 transition hover:bg-violet-50 dark:hover:bg-violet-950/40">
                                    <input v-model="newProduct.yarn_material_ids" type="checkbox" :value="material.id" class="h-4 w-4 rounded border-violet-300 text-violet-600 focus:ring-violet-500" />
                                    <span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold text-slate-700 dark:text-slate-200">{{ material.display_name || material.name }}</span><span class="block text-[10px] text-slate-400">{{ material.mat_id }}<template v-if="material.unit"> · {{ material.unit }}</template></span></span>
                                    <span class="rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-black text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ material.yarn_tier || 'Tier not set' }}</span>
                                </label>
                                <p v-if="!filteredYarnMaterials.length" class="px-2 py-4 text-center text-xs text-slate-400">No yarn materials match your search.</p>
                            </div>
                            <p v-else class="rounded-xl border border-dashed border-violet-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-violet-900/50 dark:text-slate-400">Add Yarn materials in Inventory Materials before assigning them here.</p>
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-800 pt-5">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Colors assigned *</p>
                                <span class="text-xs font-bold" :class="newProduct.colors.length >= 10 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                                    {{ newProduct.colors.length }} / 10 colors
                                </span>
                            </div>
                            <p v-if="newProduct.colors.length < 10" class="-mt-4 mb-4 text-[11px] font-medium text-amber-600 dark:text-amber-400">
                                Add {{ 10 - newProduct.colors.length }} more Pantone color{{ 10 - newProduct.colors.length === 1 ? '' : 's' }} to create this product.
                            </p>

                            <div class="flex flex-wrap gap-4 mb-6 p-2 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 min-h-[80px] items-center">
                                <template v-if="newProduct.colors.length > 0">
                                    <div v-for="(color, index) in newProduct.colors" :key="index"
                                        class="relative group flex flex-col items-center">
                                        <div class="w-10 h-10 rounded-full border-2 border-slate-300 dark:border-slate-600 shadow-sm flex items-center justify-center transition-transform hover:scale-105"
                                            :style="{ backgroundColor: color.hex }" :title="color.name">
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

                            <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 dark:border-blue-900/30 dark:bg-blue-900/10">
                                <p class="mb-2 text-[9px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-300">Add Pantone colorway</p>
                                <input v-model="pantoneSearch" type="search" placeholder="Search Pantone name or hex code..." class="mb-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" />
                                <div v-if="filteredPantoneColors.length" class="max-h-44 space-y-1 overflow-y-auto rounded-lg border border-blue-100 bg-white/70 p-2 dark:border-blue-900/30 dark:bg-zinc-900/60">
                                    <button v-for="pantone in filteredPantoneColors" :key="pantone.id" type="button" @click="addColor(pantone)" :disabled="hasPantoneColor(newProduct.colors, pantone)" class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-blue-50 disabled:cursor-default disabled:opacity-50 dark:hover:bg-blue-950/40">
                                        <span class="h-7 w-7 shrink-0 rounded-md border border-black/10" :style="{ backgroundColor: pantone.hex }" />
                                        <span class="min-w-0 flex-1 truncate text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ pantone.name }}</span>
                                        <span class="font-mono text-[9px] text-slate-400">{{ pantone.hex }}</span>
                                        <Check v-if="hasPantoneColor(newProduct.colors, pantone)" class="h-3.5 w-3.5 text-emerald-600" />
                                    </button>
                                </div>
                                <p v-else class="rounded-lg border border-dashed border-blue-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-blue-900/50 dark:text-slate-400">{{ pantoneColors.length ? 'No Pantone colors match your search.' : 'No Pantone references are available.' }}</p>
                                <p v-if="pantoneColors.length > 100 && !pantoneSearch" class="mt-1 text-[10px] text-slate-400">Showing the first 100 colors. Search to find other Pantone references.</p>
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
                            :disabled="processing || !newProduct.name || newProduct.colors.length < 10"
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

                        <div class="rounded-2xl border border-violet-100 bg-violet-50/50 p-4 dark:border-violet-900/40 dark:bg-violet-950/20">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div><p class="text-[10px] font-black uppercase tracking-widest text-violet-700 dark:text-violet-300">Compatible yarns</p><p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Choose yarn materials that can be used for this product.</p></div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-violet-700 dark:bg-zinc-900 dark:text-violet-300">{{ editForm.yarn_material_ids?.length || 0 }} selected</span>
                            </div>
                            <input v-model="yarnSearch" type="search" placeholder="Search yarn name, tier, or material ID..." class="mb-2 w-full rounded-xl border border-violet-100 bg-white px-3 py-2 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-violet-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-slate-200" />
                            <div v-if="yarnMaterials.length" class="max-h-44 space-y-1 overflow-y-auto rounded-xl border border-violet-100 bg-white/70 p-2 dark:border-zinc-800 dark:bg-zinc-900/60">
                                <label v-for="material in filteredYarnMaterials" :key="material.id" class="flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 transition hover:bg-violet-50 dark:hover:bg-violet-950/40">
                                    <input v-model="editForm.yarn_material_ids" type="checkbox" :value="material.id" class="h-4 w-4 rounded border-violet-300 text-violet-600 focus:ring-violet-500" />
                                    <span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold text-slate-700 dark:text-slate-200">{{ material.display_name || material.name }}</span><span class="block text-[10px] text-slate-400">{{ material.mat_id }}<template v-if="material.unit"> · {{ material.unit }}</template></span></span>
                                    <span class="rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-black text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ material.yarn_tier || 'Tier not set' }}</span>
                                </label>
                                <p v-if="!filteredYarnMaterials.length" class="px-2 py-4 text-center text-xs text-slate-400">No yarn materials match your search.</p>
                            </div>
                            <p v-else class="rounded-xl border border-dashed border-violet-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-violet-900/50 dark:text-slate-400">Add Yarn materials in Inventory Materials before assigning them here.</p>
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-800 pt-5">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Colors assigned *</p>
                                <span class="text-xs font-bold" :class="(editForm.colors?.length || 0) >= 10 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                                    {{ editForm.colors?.length || 0 }} / 10 colors
                                </span>
                            </div>
                            <p v-if="(editForm.colors?.length || 0) < 10" class="-mt-4 mb-4 text-[11px] font-medium text-amber-600 dark:text-amber-400">
                                Add {{ 10 - (editForm.colors?.length || 0) }} more Pantone color{{ 10 - (editForm.colors?.length || 0) === 1 ? '' : 's' }} before saving.
                            </p>

                            <div class="flex flex-wrap gap-4 mb-6 p-2 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 min-h-[80px] items-center">
                                
                                <template v-if="editForm.colors && editForm.colors.length > 0">
                                    <div v-for="(color, index) in editForm.colors" :key="index"
                                        class="relative group flex flex-col items-center">
                                        <div class="w-10 h-10 rounded-full border-2 border-slate-300 dark:border-slate-600 shadow-sm flex items-center justify-center transition-transform hover:scale-105"
                                            :style="{ backgroundColor: color.hex }" :title="color.name">
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

                            <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 dark:border-blue-900/30 dark:bg-blue-900/10">
                                <p class="mb-2 text-[9px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-300">Add Pantone colorway</p>
                                <input v-model="pantoneSearch" type="search" placeholder="Search Pantone name or hex code..." class="mb-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" />
                                <div v-if="filteredPantoneColors.length" class="max-h-44 space-y-1 overflow-y-auto rounded-lg border border-blue-100 bg-white/70 p-2 dark:border-blue-900/30 dark:bg-zinc-900/60">
                                    <button v-for="pantone in filteredPantoneColors" :key="pantone.id" type="button" @click="addColor(pantone)" :disabled="hasPantoneColor(editForm.colors, pantone)" class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-blue-50 disabled:cursor-default disabled:opacity-50 dark:hover:bg-blue-950/40">
                                        <span class="h-7 w-7 shrink-0 rounded-md border border-black/10" :style="{ backgroundColor: pantone.hex }" />
                                        <span class="min-w-0 flex-1 truncate text-[11px] font-bold text-slate-700 dark:text-slate-200">{{ pantone.name }}</span>
                                        <span class="font-mono text-[9px] text-slate-400">{{ pantone.hex }}</span>
                                        <Check v-if="hasPantoneColor(editForm.colors, pantone)" class="h-3.5 w-3.5 text-emerald-600" />
                                    </button>
                                </div>
                                <p v-else class="rounded-lg border border-dashed border-blue-200 px-3 py-4 text-center text-xs text-slate-500 dark:border-blue-900/50 dark:text-slate-400">{{ pantoneColors.length ? 'No Pantone colors match your search.' : 'No Pantone references are available.' }}</p>
                                <p v-if="pantoneColors.length > 100 && !pantoneSearch" class="mt-1 text-[10px] text-slate-400">Showing the first 100 colors. Search to find other Pantone references.</p>
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
                        
                        <button v-if="canEditProducts" @click="triggerEditConfirm" :disabled="processing || !editForm.name || !editForm.colors || editForm.colors.length < 10"
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
.fade-img-enter-active, .fade-img-leave-active { transition: opacity 0.2s ease, transform 0.25s ease; }
.fade-img-enter-from { opacity: 0; transform: scale(1.03); }
.fade-img-leave-to { opacity: 0; transform: scale(0.99); }
@media (prefers-reduced-motion: reduce) {
    .animate-float, .animate-float-delayed, .animate-bounce-soft { animation: none; }
    .fade-img-enter-active, .fade-img-leave-active { transition: none; }
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
