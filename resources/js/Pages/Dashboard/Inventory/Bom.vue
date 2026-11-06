<script setup>
import { showNotice, confirmModal } from '@/lib/feedback';
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Package,
    Trash2,
    X,
    Edit2,
    Search,
    Sparkles,
} from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditBom = computed(() => canEdit('INV', 'bom'));

const props = defineProps({
    boms: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    materials: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    auth: Object,
});

// UI State
const searchQuery = ref('');
const showForm = ref(false);
const selectedRecipe = ref(null);
const samplePreview = ref(null);
const editingId = ref(null);
const processing = ref(false);

// Form for creating/editing recipe.
// materials = { material_id: kg-per-unit qty } — the stock check multiplies
// each qty straight by the order quantity.
const form = useForm({
    client_id: '',
    product_id: '',
    yarn_type: '',
    dye_color: '',
    weave_design: '',
    materials: {},
    order_id: '',
});

// Material picker (id + per-unit qty in kg)
const selectedMaterialId = ref('');
const newMaterialQty = ref(1);

// Add material to recipe
const addMaterialToRecipe = () => {
    if (!selectedMaterialId.value) return;
    const matId = parseInt(selectedMaterialId.value);
    if (form.materials[matId] !== undefined) {
        showNotice('Material already added — adjust its quantity below.');
        return;
    }
    const qty = Number(newMaterialQty.value);
    if (!(qty > 0)) {
        showNotice('Quantity must be above zero (kg per 1 unit ordered).');
        return;
    }
    form.materials[matId] = qty;
    selectedMaterialId.value = '';
    newMaterialQty.value = 1;
};

// Remove material from recipe
const removeMaterialFromRecipe = (materialId) => {
    delete form.materials[materialId];
};

// Orders of the chosen client — a formula can be pinned to one specific order.
const clientOrders = computed(() => {
    if (!form.client_id) return [];
    return (props.orders ?? []).filter((o) => Number(o.client_id) === Number(form.client_id));
});

// Orders currently using a recipe (used-by column + edit context).
const recipeOrders = (recipeId) => (props.orders ?? []).filter((o) => Number(o.recipe_id) === Number(recipeId));

// Reset form
const resetForm = () => {
    form.client_id = '';
    form.product_id = '';
    form.yarn_type = '';
    form.dye_color = '';
    form.weave_design = '';
    form.materials = {};
    form.order_id = '';
    form.clearErrors();
    editingId.value = null;
};

// Open form to edit existing recipe
const openEdit = (recipe) => {
    editingId.value = recipe.id;
    form.client_id = recipe.client_id;
    form.product_id = recipe.product_id;
    form.yarn_type = recipe.yarn_type;
    form.dye_color = recipe.dye_color;
    form.weave_design = recipe.weave_design;
    // Keep stored per-material quantities (kg per unit ordered).
    form.materials = { ...(recipe.materials || {}) };
    form.order_id = '';
    form.clearErrors();
    showForm.value = true;
};

// Submit form (create or update)
const submitForm = () => {
    if (!form.client_id || !form.product_id || !form.yarn_type || !form.dye_color || !form.weave_design) {
        showNotice('Please fill all required fields.');
        return;
    }
    const ids = Object.keys(form.materials);
    if (ids.length === 0) {
        showNotice('Please add at least one material to the recipe.');
        return;
    }
    for (const id of ids) {
        if (!(Number(form.materials[id]) > 0)) {
            showNotice('Every material needs a quantity above zero (kg per 1 unit ordered).');
            return;
        }
    }
    processing.value = true;

    const payload = {
        ...form.data(),
        materials: Object.fromEntries(ids.map((id) => [id, Number(form.materials[id])])),
        order_id: form.order_id || undefined,
    };

    const url = editingId.value ? route('inv.bom.update', editingId.value) : route('inv.bom.store');
    const method = editingId.value ? 'put' : 'post';

    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            resetForm();
        },
        onFinish: () => (processing.value = false),
    });
};

// Delete recipe
const deleteRecipe = async (id) => {
    if (!await confirmModal('Delete this recipe? This action cannot be undone.')) return;
    router.delete(route('inv.bom.destroy', id), { preserveScroll: true });
};

// Filter recipes by search
const sortedRecipes = computed(() => [...(props.boms ?? [])].sort((a, b) => {
    const aTime = new Date(a.updated_at || a.created_at || 0).getTime();
    const bTime = new Date(b.updated_at || b.created_at || 0).getTime();
    return bTime - aTime || Number(b.id) - Number(a.id);
}));

const filteredRecipes = computed(() => {
    if (!searchQuery.value) return sortedRecipes.value;
    const q = searchQuery.value.toLowerCase();
    return sortedRecipes.value.filter(recipe =>
        recipe.client?.company_name?.toLowerCase().includes(q) ||
        recipe.product?.name?.toLowerCase().includes(q) ||
        String(recipe.yarn_type || '').toLowerCase().includes(q) ||
        String(recipe.dye_color || '').toLowerCase().includes(q) ||
        String(recipe.weave_design || '').toLowerCase().includes(q) ||
        String(recipe.sample_info?.code || '').toLowerCase().includes(q)
    );
});

const approvedFormulaSections = (recipe) => {
    const formula = recipe.approved_formula || {};
    return [
        { label: 'Dye stuffs', rows: formula.dyestuffs || formula.dye_stuffs || [], amount: (row) => `${row.pct ?? row.amount ?? '—'} % OWF` },
        { label: 'Auxiliary agents', rows: formula.auxiliary_agents || [], amount: (row) => `${row.gpl ?? row.amount ?? '—'} g/L` },
        { label: 'Basic chemicals', rows: formula.basic_chemicals || [], amount: (row) => `${row.gpl ?? row.amount ?? '—'} g/L` },
        { label: 'Post-treatment', rows: formula.post_treatment || [], amount: (row) => `${row.gpl ?? row.amount ?? '—'} g/L` },
        { label: 'Other auxiliaries', rows: formula.auxiliaries || [], amount: (row) => `${row.gpl ?? row.amount ?? '—'} g/L` },
        { label: 'Yarns', rows: formula.yarns || [], amount: (row) => `${row.qty ?? row.amount ?? '—'} kg/kg` },
    ].map((section) => ({
        ...section,
        rows: section.rows.map((row) => ({
            ...row,
            production_quantity: recipe.formula_materials?.find((material) => Number(material.material_id) === Number(row.material_id))?.quantity,
        })),
    })).filter((section) => section.rows.length);
};

const formatDateTime = (value) => value ? new Date(value).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const recipeCardTitle = (recipe) => recipe.sample_info?.fabric_design?.split(' · ')[0]
    || recipe.product?.name
    || getProductName(recipe.product_id);

const openRecipe = (recipe) => { selectedRecipe.value = recipe; };
const openSamplePreview = (sample) => { samplePreview.value = sample; };

// Helper: get client name by id
const getClientName = (id) => {
    const client = props.clients.find(c => c.id === id);
    return client ? client.company_name : '—';
};

// Helper: get product name by id
const getProductName = (id) => {
    const product = props.products.find(p => p.id === id);
    return product ? product.name : '—';
};

// Helper: get material name by id
const getMaterialName = (id) => {
    const mat = props.materials.find(m => m.id === id);
    return mat ? `${mat.mat_id} - ${mat.display_name || mat.name}` : 'Unknown';
};
</script>

<template>
    <Head title="Recipes | Manufacturing" />
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
                                <Sparkles class="h-3.5 w-3.5" /> Inventory · Formulas
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Recipes <span v-if="!canEditBom" class="ml-2 inline-block rounded-full bg-amber-400/90 px-3 py-1 align-middle text-xs font-black uppercase tracking-widest text-amber-950">View only</span></h1>
                            <p class="text-sm text-blue-100/90">Client‑specific fabric formulas (yarn, dye, design, materials). · {{ filteredRecipes.length }} showing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ filteredRecipes.length }} recipes
                            </span>
                        </div>
                    </div>
                    <!-- "Add Recipe" button removed – recipes are created via ECO conversation -->

                    <!-- Search -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search by client, product, or yarn type..."
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition"
                            />
                        </div>
                    </div>
                </div>

                <!-- Recipe List Table -->
                <div class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 80ms">
                    <div v-if="filteredRecipes.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Package class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No recipes found.</p>
                        <p class="text-xs text-gray-400 mt-1">Try a different search.</p>
                        <button v-if="searchQuery" @click="searchQuery=''" class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition active:scale-95">Clear filters</button>
                    </div>
                    <TransitionGroup v-else name="card" tag="div" class="grid gap-4 p-4 sm:p-5 xl:grid-cols-2">
                        <article v-for="recipe in filteredRecipes" :key="recipe.id" role="button" tabindex="0" @click="openRecipe(recipe)" @keydown.enter="openRecipe(recipe)" @keydown.space.prevent="openRecipe(recipe)" class="group cursor-pointer overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-indigo-700">
                            <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-indigo-50/80 to-white px-4 py-4 dark:border-zinc-800 dark:from-indigo-950/30 dark:to-zinc-900 sm:px-5">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="truncate text-base font-black text-slate-900 dark:text-white">{{ recipeCardTitle(recipe) }}</h2>
                                        <span v-if="recipe.sample_info" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">Client approved</span>
                                    </div>
                                    <p class="mt-1 text-xs font-semibold text-slate-600 dark:text-zinc-300">{{ recipe.client?.company_name || getClientName(recipe.client_id) }}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">Updated {{ formatDateTime(recipe.updated_at || recipe.created_at) }}<span v-if="recipe.sample_info?.code"> · {{ recipe.sample_info.code }}</span></p>
                                </div>
                                <div v-if="canEditBom" class="flex shrink-0 gap-1.5">
                                    <button type="button" @click.stop="openEdit(recipe)" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm ring-1 ring-slate-200 hover:bg-indigo-600 hover:text-white dark:bg-zinc-800 dark:ring-zinc-700" title="Edit recipe"><Edit2 class="h-4 w-4" /></button>
                                    <button type="button" @click.stop="deleteRecipe(recipe.id)" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-rose-500 shadow-sm ring-1 ring-slate-200 hover:bg-rose-600 hover:text-white dark:bg-zinc-800 dark:ring-zinc-700" title="Delete recipe"><Trash2 class="h-4 w-4" /></button>
                                </div>
                            </header>

                            <div class="space-y-3 p-4 sm:p-5">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60"><p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Yarn type</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-zinc-100">{{ recipe.yarn_type || '—' }}</p></div>
                                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60"><p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Dye color</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-zinc-100">{{ recipe.dye_color || '—' }}</p></div>
                                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60"><p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Weave / fabric design</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-zinc-100">{{ recipe.weave_design || recipe.sample_info?.fabric_design || '—' }}</p></div>
                                    <div v-if="recipe.sample_info" class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60"><p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Sample specifications</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-zinc-100"><span v-if="recipe.sample_info.gsm">{{ recipe.sample_info.gsm }} GSM</span><span v-if="recipe.sample_info.gsm && recipe.sample_info.texture"> · </span><span v-if="recipe.sample_info.texture">{{ recipe.sample_info.texture }} texture</span><span v-if="!recipe.sample_info.gsm && !recipe.sample_info.texture">—</span></p></div>
                                </div>

                                <div v-if="recipe.sample_info?.image_path" class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-zinc-700" @click.stop>
                                    <img v-if="!String(recipe.sample_info.image_path).toLowerCase().endsWith('.pdf')" :src="`/storage/${recipe.sample_info.image_path}`" alt="Approved fabric sample" class="h-14 w-14 rounded-lg border border-slate-200 bg-white object-cover dark:border-zinc-700 dark:bg-zinc-800" />
                                    <span class="min-w-0 flex-1 text-xs font-semibold text-slate-600 dark:text-zinc-300">{{ recipe.sample_info.code }} approved swatch</span>
                                    <button type="button" @click.stop="openSamplePreview(recipe.sample_info)" class="rounded-lg bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300">{{ String(recipe.sample_info.image_path).toLowerCase().endsWith('.pdf') ? 'View sample PDF' : 'View sample' }}</button>
                                </div>
                                <p class="text-right text-[11px] font-bold text-indigo-600 transition group-hover:text-indigo-700 dark:text-indigo-300">View full recipe <span aria-hidden="true">→</span></p>
                            </div>
                        </article>
                    </TransitionGroup>
                    <div class="px-5 py-3 border-t border-gray-100 dark:border-zinc-800 text-xs text-slate-400 dark:text-gray-500 font-medium">
                        Total: {{ filteredRecipes.length }} recipes
                    </div>
                </div>
            </div>
        </div>

        <!-- Full recipe details -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="selectedRecipe" class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm" @click.self="selectedRecipe = null" @keydown.esc="selectedRecipe = null">
                    <section class="modal-panel my-6 w-full max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900" role="dialog" aria-modal="true" aria-labelledby="recipe-modal-title">
                        <header class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-5 text-white sm:p-6">
                            <div class="absolute -right-12 -top-16 h-48 w-48 rounded-full bg-white/10 blur-3xl" />
                            <div class="relative flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2"><h2 id="recipe-modal-title" class="text-xl font-black">{{ recipeCardTitle(selectedRecipe) }}</h2><span v-if="selectedRecipe.sample_info" class="rounded-full bg-emerald-300/20 px-2.5 py-1 text-[10px] font-black uppercase text-emerald-100">Client approved</span></div>
                                    <p class="mt-1 text-sm text-blue-100">{{ selectedRecipe.client?.company_name || getClientName(selectedRecipe.client_id) }}<span v-if="selectedRecipe.sample_info?.code"> · {{ selectedRecipe.sample_info.code }}</span></p>
                                </div>
                                <button type="button" @click="selectedRecipe = null" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 transition hover:bg-white/25" aria-label="Close recipe details"><X class="h-4 w-4" /></button>
                            </div>
                        </header>
                        <div class="max-h-[75vh] space-y-5 overflow-y-auto p-4 sm:p-6">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60"><p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Yarn type</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-zinc-100">{{ selectedRecipe.yarn_type || '—' }}</p></div>
                                <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60"><p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Dye color</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-zinc-100">{{ selectedRecipe.dye_color || '—' }}</p></div>
                                <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60"><p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Weave / fabric design</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-zinc-100">{{ selectedRecipe.weave_design || selectedRecipe.sample_info?.fabric_design || '—' }}</p></div>
                                <div v-if="selectedRecipe.sample_info" class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60"><p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Sample specifications</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-zinc-100"><span v-if="selectedRecipe.sample_info.gsm">{{ selectedRecipe.sample_info.gsm }} GSM</span><span v-if="selectedRecipe.sample_info.gsm && selectedRecipe.sample_info.texture"> · </span><span v-if="selectedRecipe.sample_info.texture">{{ selectedRecipe.sample_info.texture }} texture</span><span v-if="!selectedRecipe.sample_info.gsm && !selectedRecipe.sample_info.texture">—</span></p></div>
                            </div>

                            <div v-if="selectedRecipe.sample_info?.image_path" class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-zinc-700">
                                <img v-if="!String(selectedRecipe.sample_info.image_path).toLowerCase().endsWith('.pdf')" :src="`/storage/${selectedRecipe.sample_info.image_path}`" alt="Approved fabric sample" class="h-20 w-20 rounded-lg border border-slate-200 bg-white object-cover dark:border-zinc-700 dark:bg-zinc-800" />
                                <span class="min-w-0 flex-1 text-xs font-semibold text-slate-600 dark:text-zinc-300">{{ selectedRecipe.sample_info.code }} approved swatch</span>
                                <button type="button" @click="openSamplePreview(selectedRecipe.sample_info)" class="rounded-lg bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300">{{ String(selectedRecipe.sample_info.image_path).toLowerCase().endsWith('.pdf') ? 'View sample PDF' : 'View sample' }}</button>
                            </div>

                            <section v-if="approvedFormulaSections(selectedRecipe).length" class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 dark:border-indigo-900/50 dark:bg-indigo-950/20">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><div><h3 class="text-sm font-black uppercase tracking-wide text-indigo-900 dark:text-indigo-200">Approved lab recipe</h3><p class="mt-1 text-xs text-indigo-700 dark:text-indigo-300">The exact formulation approved for this sample.</p></div><span v-if="selectedRecipe.approved_formula?.liquor_ratio" class="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-zinc-900 dark:text-zinc-300">Liquor ratio {{ selectedRecipe.approved_formula.liquor_ratio }}</span></div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div v-for="section in approvedFormulaSections(selectedRecipe)" :key="section.label" class="rounded-xl bg-white p-3 dark:bg-zinc-900">
                                        <h4 class="text-[10px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ section.label }}</h4>
                                        <div class="mt-2 space-y-2">
                                            <div v-for="(row, index) in section.rows" :key="row.material_id || row.name || index" class="flex items-start justify-between gap-3 border-b border-slate-100 pb-2 text-xs last:border-0 last:pb-0 dark:border-zinc-800"><span class="min-w-0 font-semibold text-slate-700 dark:text-zinc-200">{{ row.name || row.material_name || 'Material' }}<span v-if="row.production_quantity != null" class="mt-1 block font-mono text-[10px] font-medium text-slate-500 dark:text-zinc-400">{{ Number(row.production_quantity).toFixed(4) }} kg / unit for production</span></span><span class="shrink-0 font-mono font-bold text-indigo-700 dark:text-indigo-300">{{ section.amount(row) }}</span></div>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="selectedRecipe.approved_formula?.notes" class="mt-3 rounded-xl bg-white/80 p-3 text-xs text-slate-600 dark:bg-zinc-900/80 dark:text-zinc-300"><span class="font-bold">Lab notes:</span> {{ selectedRecipe.approved_formula.notes }}</p>
                                <p v-if="!approvedFormulaSections(selectedRecipe).length" class="text-xs text-slate-500">No formulation details were saved for this recipe.</p>
                            </section>
                            <section v-else class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">No approved lab formula is linked to this recipe.</section>

                            <section v-if="!approvedFormulaSections(selectedRecipe).length && selectedRecipe.formula_materials?.length" class="rounded-2xl border border-slate-200 p-4 dark:border-zinc-700">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><div><h3 class="text-sm font-black uppercase tracking-wide text-slate-800 dark:text-zinc-100">Production material quantities</h3><p class="mt-1 text-xs text-slate-500">Normalized kg of each material per unit of fabric.</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ selectedRecipe.formula_materials?.length || 0 }} materials</span></div>
                                <div v-if="selectedRecipe.formula_materials?.length" class="divide-y divide-slate-100 dark:divide-zinc-800">
                                    <div v-for="material in selectedRecipe.formula_materials" :key="material.material_id" class="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0"><div class="min-w-0"><p class="text-xs font-bold text-slate-800 dark:text-zinc-100">{{ material.name }}</p><p v-if="material.mat_id" class="mt-0.5 font-mono text-[10px] text-slate-400">{{ material.mat_id }}</p></div><p class="font-mono text-xs font-black text-slate-700 dark:text-zinc-200">{{ Number(material.quantity).toFixed(4) }} kg / unit</p></div>
                                </div>
                                <p v-else class="text-xs text-slate-400">No material quantities were recorded.</p>
                            </section>
                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-zinc-800"><div v-if="recipeOrders(selectedRecipe.id).length" class="flex flex-wrap items-center gap-1.5"><span class="text-[10px] font-black uppercase tracking-wide text-slate-400">Used by</span><span v-for="order in recipeOrders(selectedRecipe.id)" :key="order.id" class="rounded-full bg-indigo-50 px-2.5 py-1 font-mono text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ order.jo_number }}</span></div><span v-else class="text-[11px] italic text-slate-400">Not assigned to an order</span><span class="text-[10px] text-slate-400">Updated {{ formatDateTime(selectedRecipe.updated_at || selectedRecipe.created_at) }}<span v-if="selectedRecipe.sample_info?.approved_at"> · Approved {{ formatDateTime(selectedRecipe.sample_info.approved_at) }}</span></span></div>
                        </div>
                    </section>
                </div>
            </Transition>
        </Teleport>

        <!-- Fabric sample preview -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="samplePreview" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/80 p-3 backdrop-blur-sm sm:p-6" @click.self="samplePreview = null" @keydown.esc="samplePreview = null">
                    <section class="modal-panel relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/15 bg-slate-900 shadow-2xl" role="dialog" aria-modal="true" aria-label="Fabric sample preview">
                        <header class="flex items-center justify-between gap-4 border-b border-white/10 px-4 py-3 text-white sm:px-5">
                            <div class="min-w-0"><h2 class="truncate text-sm font-black">Fabric sample</h2><p class="truncate text-xs text-slate-300">{{ samplePreview.code }} approved swatch</p></div>
                            <button type="button" @click="samplePreview = null" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/10 transition hover:bg-white/20" aria-label="Close sample preview"><X class="h-4 w-4" /></button>
                        </header>
                        <div class="flex min-h-0 flex-1 items-center justify-center overflow-auto bg-black/30 p-2 sm:p-4">
                            <iframe v-if="String(samplePreview.image_path).toLowerCase().endsWith('.pdf')" :src="`/storage/${samplePreview.image_path}`" title="Fabric sample PDF" class="h-[75vh] w-full rounded-lg bg-white" />
                            <img v-else :src="`/storage/${samplePreview.image_path}`" :alt="`${samplePreview.code} fabric sample`" class="max-h-[78vh] max-w-full rounded-lg object-contain shadow-xl" />
                        </div>
                    </section>
                </div>
            </Transition>
        </Teleport>

        <!-- Edit Recipe Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/70 backdrop-blur-sm overflow-y-auto" @click.self="showForm = false">
                    <div class="modal-panel bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl border border-gray-100 dark:border-zinc-800 w-full max-w-3xl my-8 overflow-hidden">
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white">
                            <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 18px 18px;" />
                            <div class="relative flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg font-black tracking-tight">{{ editingId ? 'Edit Recipe' : 'Create Recipe' }}</h3>
                                    <p class="text-xs text-blue-100/90">Client‑specific fabric formula.</p>
                                </div>
                                <button @click="showForm = false; resetForm()" class="p-2 rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition"><X class="w-4 h-4" /></button>
                            </div>
                        </div>
                        <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">
                            <!-- Client & Product -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Client *</label>
                                    <select v-model="form.client_id" class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/30">
                                        <option value="">Select client...</option>
                                        <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.company_name }}</option>
                                    </select>
                                    <div v-if="form.errors.client_id" class="text-rose-500 text-xs mt-1">{{ form.errors.client_id }}</div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Product *</label>
                                    <select v-model="form.product_id" class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/30">
                                        <option value="">Select product...</option>
                                        <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }} ({{ product.sku }})</option>
                                    </select>
                                    <div v-if="form.errors.product_id" class="text-red-500 text-xs mt-1">{{ form.errors.product_id }}</div>
                                </div>
                            </div>

                            <!-- Yarn, Dye, Weave -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Yarn Type *</label>
                                    <input v-model="form.yarn_type" type="text" placeholder="e.g. Cotton 20s" class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/30" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Dye Color *</label>
                                    <input v-model="form.dye_color" type="text" placeholder="e.g. Navy Blue" class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/30" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Weave Design *</label>
                                    <input v-model="form.weave_design" type="text" placeholder="e.g. Twill 2/1" class="mt-1 w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/30" />
                                </div>
                            </div>

                            <!-- Materials Formula (detailed quantities) -->
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1 block">Formula · kg per 1 unit ordered *</label>
                                <p class="text-[11px] text-slate-400 mb-2">Each quantity is multiplied straight by the order qty in stock checks — enter kg of raw material per 1 unit (e.g. 1 kg yarn / 0.02 kg dye per kg fabric).</p>
                                <div class="bg-slate-50 dark:bg-zinc-800/50 rounded-3xl p-4 border border-gray-100 dark:border-zinc-800">
                                    <!-- Add new material -->
                                    <div class="flex flex-col sm:flex-row gap-2 mb-4">
                                        <select v-model="selectedMaterialId" class="flex-1 px-3 py-2.5 text-sm bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-700 rounded-2xl text-gray-900 dark:text-white outline-none">
                                            <option value="">Select material...</option>
                                            <option v-for="mat in materials" :key="mat.id" :value="mat.id">{{ mat.mat_id }} – {{ mat.display_name || mat.name }} ({{ mat.unit }})</option>
                                        </select>
                                        <input v-model.number="newMaterialQty" type="number" step="0.0001" min="0.0001" placeholder="kg/unit"
                                            class="w-full sm:w-32 px-3 py-2.5 text-sm bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-700 rounded-2xl text-gray-900 dark:text-white outline-none font-mono font-bold" />
                                        <button @click="addMaterialToRecipe" :disabled="!selectedMaterialId" class="px-4 py-2 bg-indigo-600 text-white rounded-2xl text-sm font-bold hover:bg-indigo-700 hover:scale-105 active:scale-95 transition-all shadow-lg shadow-indigo-500/20 disabled:opacity-50">Add</button>
                                    </div>

                                    <!-- List of added materials -->
                                    <div v-if="Object.keys(form.materials).length === 0" class="text-center text-slate-400 py-4 text-sm">No materials added yet.</div>
                                    <div v-for="(qty, matId) in form.materials" :key="matId" class="flex items-center gap-2 py-2 border-b border-gray-100 dark:border-zinc-800 last:border-0">
                                        <div class="flex-1 min-w-0">
                                            <span class="font-mono text-sm font-bold text-gray-900 dark:text-white">{{ getMaterialName(parseInt(matId)) }}</span>
                                        </div>
                                        <input v-model.number="form.materials[matId]" type="number" step="0.0001" min="0.0001"
                                            class="w-28 px-3 py-2 text-sm bg-white dark:bg-zinc-900 border border-gray-100 dark:border-zinc-700 rounded-xl text-gray-900 dark:text-white outline-none font-mono font-bold focus:ring-2 focus:ring-indigo-500/30" />
                                        <span class="text-[10px] font-bold text-slate-400 w-14">kg/unit</span>
                                        <button @click="removeMaterialFromRecipe(parseInt(matId))" class="flex h-8 w-8 items-center justify-center rounded-xl text-rose-500 hover:bg-rose-600 hover:text-white transition-all">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <div v-if="form.errors.materials" class="text-rose-500 text-xs mt-1">{{ form.errors.materials }}</div>
                            </div>

                            <!-- Pin to a specific client order -->
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1 block">Apply To Specific Order (optional)</label>
                                <p class="text-[11px] text-slate-400 mb-2">Pin this formula to one order of the chosen client. Empty = shared formulation for the client + product.</p>
                                <select v-model="form.order_id" :disabled="!form.client_id"
                                    class="w-full px-3 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:opacity-50">
                                    <option value="">Shared — no specific order</option>
                                    <option v-for="o in clientOrders" :key="o.id" :value="o.id">{{ o.jo_number }} · {{ o.quantity }} units · {{ o.status }}</option>
                                </select>
                                <div v-if="editingId && recipeOrders(editingId).length" class="mt-2 flex flex-wrap gap-1">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider w-full">Currently used by:</span>
                                    <span v-for="o in recipeOrders(editingId)" :key="o.id" class="text-[10px] font-mono font-bold bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-300 px-2 py-0.5 rounded-full">{{ o.jo_number }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 py-4 border-t border-gray-100 dark:border-zinc-800 flex gap-3 bg-slate-50/80 dark:bg-zinc-800/40">
                            <button @click="showForm = false; resetForm()" class="flex-1 py-2.5 text-sm font-bold rounded-2xl border border-gray-200 dark:border-zinc-700 text-slate-500 dark:text-gray-300 hover:bg-white dark:hover:bg-zinc-800 transition">Cancel</button>
                            <button v-if="canEditBom" @click="submitForm" :disabled="processing" class="flex-1 py-2.5 text-sm font-black uppercase tracking-wide rounded-2xl bg-indigo-600 text-white hover:bg-indigo-700 hover:scale-[1.01] active:scale-95 transition-all shadow-lg shadow-indigo-500/25 disabled:opacity-50">
                                {{ processing ? 'Saving...' : (editingId ? 'Update Recipe' : 'Create Recipe') }}
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
</style>
