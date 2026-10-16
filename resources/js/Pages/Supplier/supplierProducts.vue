<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Package, Plus, Trash2, Check, PauseCircle, PlayCircle } from 'lucide-vue-next';

const props = defineProps({
    auth: Object,
    products: { type: Array, default: () => [] },
    catalog: { type: Array, default: () => [] },
});

const supplierData = computed(() => props.auth?.supplier || props.auth?.user || {});

const CATEGORIES = ['Yarn', 'Dye', 'Supplies', 'Packaging'];

const categoryFilter = ref('');
const filteredCatalog = computed(() => {
    const listed = new Set((props.products || []).map((p) => Number(p.material_id)));
    return (props.catalog || []).filter((m) => !listed.has(Number(m.id)) && (!categoryFilter.value || m.category === categoryFilter.value));
});

const groupedProducts = computed(() => {
    const groups = {};
    CATEGORIES.forEach((c) => { groups[c] = []; });
    (props.products || []).forEach((p) => {
        const key = CATEGORIES.includes(p.category) ? p.category : 'Supplies';
        groups[key].push(p);
    });
    return CATEGORIES.map((c) => ({ category: c, items: groups[c] }));
});

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

const removeProduct = (p) => {
    if (!confirm(`Remove ${p.name} from your catalog?`)) return;
    addForm.delete(route('supplier.products.destroy', p.id), { preserveScroll: true });
};

const formatPrice = (val) => val == null || val === '' ? '—' : '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2 });
</script>

<template>
    <Head title="My Products | Supplier Hub" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900 dark:text-white">My <span class="text-indigo-600">Products</span></h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Raw materials you carry, grouped by Monti categories. Procurement matches requests to suppliers carrying the exact material.</p>
            </div>
            <span class="px-3 py-1.5 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-xs font-black">{{ props.products?.length || 0 }} products listed</span>
        </div>

        <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5 sm:p-6 mb-6">
            <h2 class="text-sm font-black uppercase tracking-widest text-gray-500 mb-4 flex items-center gap-2"><Plus class="h-4 w-4" /> Add a product</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Category</label>
                    <select v-model="categoryFilter" class="w-full px-3 py-2.5 text-sm bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-gray-700 dark:text-gray-200 outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">All categories</option>
                        <option v-for="c in CATEGORIES" :key="c" :value="c">{{ c }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Material *</label>
                    <select v-model="addForm.material_id" class="w-full px-3 py-2.5 text-sm bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl text-gray-700 dark:text-gray-200 outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="" disabled>Select material…</option>
                        <option v-for="m in filteredCatalog" :key="m.id" :value="m.id">{{ m.name }} ({{ m.mat_id }}) · {{ m.unit }}</option>
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
        </div>

        <div v-for="g in groupedProducts" :key="g.category" class="mb-6">
            <h2 class="text-sm font-black uppercase tracking-widest text-gray-500 mb-3 flex items-center gap-2">
                <Package class="h-4 w-4" /> {{ g.category }}
                <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-zinc-800 text-gray-500 text-[10px]">{{ g.items.length }}</span>
            </h2>
            <div v-if="g.items.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div v-for="p in g.items" :key="p.id" class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-100 dark:border-zinc-800 p-4 flex items-center gap-3" :class="!p.is_available && 'opacity-60'">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-black text-gray-900 dark:text-white truncate">{{ p.name }}</p>
                        <p class="text-[11px] text-gray-400 font-bold">{{ p.mat_id }} · per {{ p.unit }} · {{ formatPrice(p.unit_price) }}</p>
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
