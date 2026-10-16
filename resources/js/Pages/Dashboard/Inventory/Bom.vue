<script setup>
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
        alert('Material already added — adjust its quantity below.');
        return;
    }
    const qty = Number(newMaterialQty.value);
    if (!(qty > 0)) {
        alert('Quantity must be above zero (kg per 1 unit ordered).');
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
        alert('Please fill all required fields.');
        return;
    }
    const ids = Object.keys(form.materials);
    if (ids.length === 0) {
        alert('Please add at least one material to the recipe.');
        return;
    }
    for (const id of ids) {
        if (!(Number(form.materials[id]) > 0)) {
            alert('Every material needs a quantity above zero (kg per 1 unit ordered).');
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
const deleteRecipe = (id) => {
    if (!confirm('Delete this recipe? This action cannot be undone.')) return;
    router.delete(route('inv.bom.destroy', id), { preserveScroll: true });
};

// Filter recipes by search
const filteredRecipes = computed(() => {
    if (!searchQuery.value) return props.boms;
    const q = searchQuery.value.toLowerCase();
    return props.boms.filter(recipe =>
        recipe.client?.company_name?.toLowerCase().includes(q) ||
        recipe.product?.name?.toLowerCase().includes(q) ||
        recipe.yarn_type.toLowerCase().includes(q)
    );
});

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
    return mat ? `${mat.mat_id} - ${mat.name}` : 'Unknown';
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
                    <TransitionGroup v-else name="card" tag="div" class="overflow-x-auto">
                        <table key="bom-table" class="w-full text-left text-sm">
                            <thead class="bg-slate-50/80 dark:bg-zinc-800/60 border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="px-5 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-gray-400">Client</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-gray-400">Product</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-gray-400">Yarn Type</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-gray-400">Dye Color</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-gray-400">Weave Design</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-gray-400">Formula · kg/unit</th>
                                    <th class="px-5 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-gray-400">Used By Orders</th>
                                    <th class="px-5 py-3.5 text-center text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-gray-400 w-24">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="recipe in filteredRecipes" :key="recipe.id" class="hover:bg-indigo-50/40 dark:hover:bg-indigo-950/10 transition-colors">
                                    <td class="px-5 py-4 font-bold text-gray-900 dark:text-white">{{ recipe.client?.company_name || getClientName(recipe.client_id) }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ recipe.product?.name || getProductName(recipe.product_id) }}</td>
                                    <td class="px-5 py-4"><span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-100 text-blue-700 ring-1 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30 rounded-full text-[11px] font-bold"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ recipe.yarn_type }}</span></td>
                                    <td class="px-5 py-4"><span class="inline-flex items-center gap-1 px-2.5 py-1 bg-violet-100 text-violet-700 ring-1 ring-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/30 rounded-full text-[11px] font-bold"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ recipe.dye_color }}</span></td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ recipe.weave_design }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="(qty, matId) in recipe.materials" :key="matId" class="text-[10px] font-mono font-bold bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-gray-300 px-2 py-0.5 rounded-full">
                                                {{ getMaterialName(parseInt(matId)) }} × {{ qty }}/unit
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div v-if="recipeOrders(recipe.id).length" class="flex flex-wrap gap-1">
                                            <span v-for="o in recipeOrders(recipe.id)" :key="o.id" class="text-[10px] font-mono font-bold bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-300 px-2 py-0.5 rounded-full ring-1 ring-indigo-100 dark:ring-indigo-800">
                                                {{ o.jo_number }}
                                            </span>
                                        </div>
                                        <span v-else class="text-[11px] text-slate-400 italic">Shared formulation</span>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button v-if="canEditBom" @click="openEdit(recipe)" class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-400 hover:bg-indigo-600 hover:text-white hover:scale-110 transition-all" title="Edit">
                                                <Edit2 class="w-4 h-4" />
                                            </button>
                                            <button v-if="canEditBom" @click="deleteRecipe(recipe.id)" class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 dark:bg-rose-900/20 text-rose-400 hover:bg-rose-600 hover:text-white hover:scale-110 transition-all" title="Delete">
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </TransitionGroup>
                    <div class="px-5 py-3 border-t border-gray-100 dark:border-zinc-800 text-xs text-slate-400 dark:text-gray-500 font-medium">
                        Total: {{ filteredRecipes.length }} recipes
                    </div>
                </div>
            </div>
        </div>

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
                                            <option v-for="mat in materials" :key="mat.id" :value="mat.id">{{ mat.mat_id }} – {{ mat.name }} ({{ mat.unit }})</option>
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
