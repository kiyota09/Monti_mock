<script setup>
import { confirmModal } from '@/lib/feedback';
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SupplierPageHeader from '@/Components/SupplierPageHeader.vue';
import { Package, Plus, Trash2, Check, PauseCircle, PlayCircle } from 'lucide-vue-next';

const props = defineProps({
    auth: Object,
    products: { type: Array, default: () => [] },
    catalog: { type: Array, default: () => [] },
});

const supplierData = computed(() => props.auth?.supplier || props.auth?.user || {});

const CATEGORIES = [
    'Yarn',
    'Dye Stuff',
    'Auxillary Agent',
    'Basic Chemicals',
    'Post-Treatment',
    'Pre-Treatment',
    'Bio-Processing Enzymes',
    'Care Agents',
];
const LEGACY_CATEGORIES = ['Supplies', 'Packaging'];
const searchQuery = ref('');
const displayCategory = (category) => category === 'Dye' ? 'Dye Stuff' : category;

const categoryOptions = computed(() => {
    const usedLegacy = [...props.products, ...props.catalog]
        .map((material) => material.category)
        .filter((category) => LEGACY_CATEGORIES.includes(category));
    return [...CATEGORIES, ...new Set(usedLegacy)];
});

const categoryFilter = ref('');
const filteredCatalog = computed(() => {
    const listed = new Set((props.products || []).map((p) => Number(p.material_id)));
    const q = searchQuery.value.trim().toLowerCase();
    return (props.catalog || []).filter((m) => {
        const matchesCategory = !categoryFilter.value || displayCategory(m.category) === categoryFilter.value;
        const matchesSearch = !q || `${m.display_name || m.name} ${m.mat_id} ${m.category} ${m.yarn_tier || ''}`.toLowerCase().includes(q);
        return !listed.has(Number(m.id)) && matchesCategory && matchesSearch;
    });
});

const groupedProducts = computed(() => {
    const groups = {};
    categoryOptions.value.forEach((c) => { groups[c] = []; });
    (props.products || []).forEach((p) => {
        const key = displayCategory(p.category);
        if (!groups[key]) groups[key] = [];
        groups[key].push(p);
    });
    return Object.entries(groups)
        .filter(([, items]) => items.length)
        .map(([category, items]) => ({ category, items }));
});

const availableCount = computed(() => (props.products || []).filter((product) => product.is_available).length);

const addForm = useForm({ material_id: '', unit_price: '' });

const submitAdd = () => {
    addForm.post(route('supplier.products.store'), {
        preserveScroll: true,
        onSuccess: () => { addForm.reset(); categoryFilter.value = ''; },
    });
};

const toggleAvailable = (p) => {
    addForm.patch(route('supplier.products.toggle', p.id), { preserveScroll: true });
};

const removeProduct = async (p) => {
    if (!await confirmModal(`Remove ${p.name} from your catalog?`)) return;
    addForm.delete(route('supplier.products.destroy', p.id), { preserveScroll: true });
};

const formatPrice = (val) => val == null || val === '' ? '—' : '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });
</script>

<template>
    <Head title="My Products | Supplier Hub" />
    <AuthenticatedLayout>
        <SupplierPageHeader
            class="mb-6"
            :supplier-name="supplierData.business_name || 'Supplier workspace'"
            :icon="Package"
            title="My Products"
            subtitle="Manage your supplier catalog by inventory category. Yarn tiers are listed as separate materials."
        >
            <template #aside>
                <div class="flex gap-2">
                    <span class="supplier-page-header-chip rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold text-white ring-1 ring-white/25 backdrop-blur">{{ props.products?.length || 0 }} listed</span>
                    <span class="supplier-page-header-chip rounded-full bg-emerald-400/20 px-3 py-1.5 text-xs font-bold text-white ring-1 ring-white/30 backdrop-blur">{{ availableCount }} available</span>
                </div>
            </template>
        </SupplierPageHeader>

        <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5 sm:p-6 mb-6">
            <h2 class="text-sm font-black uppercase tracking-widest text-gray-500 mb-4 flex items-center gap-2"><Plus class="h-4 w-4" /> Add a product</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Category</label>
                    <select v-model="categoryFilter" class="w-full px-3 py-2.5 text-sm bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-gray-700 dark:text-gray-200 outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All categories</option>
                        <option v-for="c in categoryOptions" :key="c" :value="c">{{ c }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Find material</label>
                    <input v-model="searchQuery" type="search" placeholder="Search name, ID, or Yarn tier" class="w-full px-3 py-2.5 text-sm bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-gray-700 dark:text-gray-200 outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Material *</label>
                    <select v-model="addForm.material_id" class="w-full px-3 py-2.5 text-sm bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-gray-700 dark:text-gray-200 outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="" disabled>Select material…</option>
                        <option v-for="m in filteredCatalog" :key="m.id" :value="m.id">{{ m.display_name || m.name }} ({{ m.mat_id }}) · {{ m.unit }}</option>
                    </select>
                    <p v-if="addForm.errors.material_id" class="text-xs font-bold text-rose-600 mt-1">{{ addForm.errors.material_id }}</p>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Your price / unit (optional)</label>
                    <input v-model="addForm.unit_price" type="number" step="0.01" min="0" placeholder="₱" class="w-full px-3 py-2.5 text-sm bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-gray-700 dark:text-gray-200 outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div class="flex items-end">
                    <button @click="submitAdd" :disabled="addForm.processing || !addForm.material_id" class="w-full py-2.5 text-sm font-black text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 active:scale-95 disabled:opacity-50 transition">Add Product</button>
                </div>
            </div>
            <p v-if="filteredCatalog.length === 0" class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-500 dark:bg-zinc-800/70 dark:text-zinc-400">
                No unlisted materials match this category and search.
            </p>
        </div>

        <div v-for="g in groupedProducts" :key="g.category" class="mb-6">
            <h2 class="text-sm font-black uppercase tracking-widest text-gray-500 mb-3 flex items-center gap-2">
                <Package class="h-4 w-4" /> {{ g.category }}
                <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-zinc-800 text-gray-500 text-[10px]">{{ g.items.length }}</span>
            </h2>
            <div v-if="g.items.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div v-for="p in g.items" :key="p.id" class="bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 p-4 flex items-center gap-3" :class="!p.is_available && 'opacity-60'">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-black text-gray-900 dark:text-white truncate">{{ p.name }}</p>
                        <p class="text-[11px] text-gray-400 font-bold">{{ p.mat_id }} · per {{ p.unit }} · {{ formatPrice(p.unit_price) }}</p>
                        <span v-if="p.category === 'Yarn' && p.yarn_tier" class="mt-1 inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ p.yarn_tier }}</span>
                    </div>
                    <span v-if="p.is_available" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-[10px] font-black"><Check class="h-3 w-3" /> Live</span>
                    <span v-else class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-gray-100 dark:bg-zinc-800 text-gray-400 text-[10px] font-black">Paused</span>
                    <button @click="toggleAvailable(p)" :title="p.is_available ? 'Mark unavailable' : 'Mark available'" class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-zinc-800 text-gray-400 hover:text-indigo-600 transition">
                        <PauseCircle v-if="p.is_available" class="h-4 w-4" /><PlayCircle v-else class="h-4 w-4" />
                    </button>
                    <button @click="removeProduct(p)" title="Remove" class="p-2 rounded-xl hover:bg-rose-50 dark:hover:bg-rose-900/20 text-gray-400 hover:text-rose-600 transition"><Trash2 class="h-4 w-4" /></button>
                </div>
            </div>
            <p v-else class="text-xs text-gray-400 font-bold px-1">No {{ g.category.toLowerCase() }} products listed yet.</p>
        </div>
    </AuthenticatedLayout>
</template>
