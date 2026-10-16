<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import {
    MonitorSmartphone, Server, Laptop, ShieldAlert, QrCode, Printer,
    Search, Plus, X, Clock, UserPlus, Undo2, Pencil, Trash2,
} from 'lucide-vue-next';

const props = defineProps({
    assets: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    isManager: { type: Boolean, default: true },
});

const showForm = ref(false);
const editing = ref(null);
const assigning = ref(null);
const assignUserId = ref('');

const { canEdit } = usePageAccess();
const canEditAssets = computed(() => canEdit('IT', 'assets'));

const form = useForm({
    name: '', category: 'hardware', type: 'laptop', serial_number: '',
    specs: '', location: '', status: 'available', purchase_date: '',
    warranty_end: '', vendor: '', cost: '', notes: '',
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.category = 'hardware';
    form.status = 'available';
    showForm.value = true;
};

const openEdit = (asset) => {
    editing.value = asset;
    form.name = asset.name;
    form.category = asset.category;
    form.type = asset.type;
    form.serial_number = asset.serial_number || '';
    form.specs = asset.specs || '';
    form.location = asset.location || '';
    form.status = asset.status;
    form.purchase_date = asset.purchase_date || '';
    form.warranty_end = asset.warranty_end || '';
    form.vendor = asset.vendor || '';
    form.cost = asset.cost || '';
    form.notes = asset.notes || '';
    showForm.value = true;
};

const submitForm = () => {
    if (editing.value) {
        form.put(route('it.assets.update', editing.value.id), {
            preserveScroll: true, onSuccess: () => { showForm.value = false; },
        });
    } else {
        form.post(route('it.assets.store'), {
            preserveScroll: true, onSuccess: () => { showForm.value = false; },
        });
    }
};

const doAssign = () => {
    if (!assigning.value || !assignUserId.value) return;
    router.post(route('it.assets.assign', assigning.value.id), { user_id: assignUserId.value }, {
        preserveScroll: true, onSuccess: () => { assigning.value = null; assignUserId.value = ''; },
    });
};

const doReturn = (asset) => {
    if (!confirm(`Return ${asset.asset_code} to the pool?`)) return;
    router.post(route('it.assets.return', asset.id), {}, { preserveScroll: true });
};

const doDelete = (asset) => {
    if (!confirm(`Delete ${asset.asset_code}?`)) return;
    router.delete(route('it.assets.destroy', asset.id), { preserveScroll: true });
};

const applyFilters = (patch) => {
    router.get(route('it.assets'), { ...props.filters, ...patch }, { preserveScroll: true, replace: true });
};

const rows = computed(() => props.assets.data || []);

const statCards = computed(() => ([
    { title: 'Total Assets', value: props.stats.total ?? 0, icon: MonitorSmartphone, color: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-900/20' },
    { title: 'In Use', value: props.stats.inUse ?? 0, icon: Laptop, color: 'text-emerald-600', bg: 'bg-emerald-50 dark:bg-emerald-900/20' },
    { title: 'Available', value: props.stats.available ?? 0, icon: Server, color: 'text-indigo-600', bg: 'bg-indigo-50 dark:bg-indigo-900/20' },
    { title: 'Expiring ≤30d', value: props.stats.expiring ?? 0, icon: Clock, color: 'text-amber-600', bg: 'bg-amber-50 dark:bg-amber-900/20' },
]));

const getStatusClass = (status) => ({
    in_use: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    available: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    maintenance: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    retired: 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
}[status]);

const getTypeIcon = (type) => {
    if (['laptop', 'desktop'].includes(type)) return Laptop;
    if (type === 'server') return Server;
    if (type === 'license') return ShieldAlert;
    if (type === 'barcode_scanner') return QrCode;
    if (type === 'printer') return Printer;
    return MonitorSmartphone;
};

const warrantyClass = (asset) => {
    if (!asset.warranty_end) return 'text-slate-400';
    const end = new Date(asset.warranty_end);
    if (end < new Date()) return 'text-red-500 font-bold';
    const in30 = new Date();
    in30.setDate(in30.getDate() + 30);
    if (end <= in30) return 'text-amber-600 font-bold';
    return 'text-slate-600 dark:text-slate-300';
};
</script>

<template>
    <Head title="Asset Management | IT Department" />
    <AuthenticatedLayout>
        <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Asset <span class="text-blue-600">Management</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Hardware, software licenses, warranties, and assignments.</p>
            </div>
            <button v-if="isManager && canEditAssets" @click="openCreate"
                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-4 py-3 sm:py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95">
                <Plus class="w-4 h-4" /> Add Asset
            </button>
            <span v-else-if="!canEditAssets" class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <div v-for="stat in statCards" :key="stat.title"
                class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div :class="['p-2.5 rounded-xl', stat.bg]"><component :is="stat.icon" :class="['h-5 w-5', stat.color]" /></div>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ stat.title }}</p>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ stat.value }}</p>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input :value="filters.search || ''" @input="applyFilters({ search: $event.target.value })" type="text"
                        placeholder="Search by code, name, serial, location..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400" />
                </div>
                <div class="flex flex-wrap gap-2 [&>select]:flex-1 [&>select]:sm:flex-none [&>select]:min-w-[130px]">
                    <select :value="filters.category || ''" @change="applyFilters({ category: $event.target.value })"
                        class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="">All categories</option>
                        <option value="hardware">Hardware</option>
                        <option value="software">Software</option>
                        <option value="network">Network</option>
                        <option value="peripheral">Peripheral</option>
                    </select>
                    <select :value="filters.status || ''" @change="applyFilters({ status: $event.target.value })"
                        class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="">All statuses</option>
                        <option value="available">Available</option>
                        <option value="in_use">In Use</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="retired">Retired</option>
                        <option value="expiring">Warranty ≤ 30 days</option>
                        <option value="expired">Warranty expired</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[760px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700">
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Asset Details</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Assigned To</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Location</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Warranty</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th v-if="isManager" class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="asset in rows" :key="asset.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                            <td class="px-4 sm:px-6 py-3 sm:py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center border border-slate-200 dark:border-slate-600">
                                        <component :is="getTypeIcon(asset.type)" class="h-5 w-5 text-slate-500 dark:text-slate-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ asset.name }}</p>
                                        <p class="text-[10px] font-mono font-black text-slate-400 uppercase">{{ asset.asset_code }} · {{ asset.type }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm font-bold" :class="asset.assignee ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400 italic'">
                                {{ asset.assignee?.name || 'Unassigned' }}
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4 text-sm text-slate-600 dark:text-slate-300">{{ asset.location || '—' }}</td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4"><span :class="['text-sm font-mono', warrantyClass(asset)]">{{ asset.warranty_end || '—' }}</span></td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4"><span :class="['px-2.5 py-1 rounded-full text-[10px] font-black uppercase', getStatusClass(asset.status)]">{{ asset.status.replace('_', ' ') }}</span></td>
                            <td v-if="isManager" class="px-4 sm:px-6 py-3 sm:py-4 text-right whitespace-nowrap">
                                <button v-if="asset.status !== 'in_use' && asset.status !== 'retired' && canEditAssets" @click="assigning = asset" title="Assign"
                                    class="p-2.5 sm:p-1.5 text-slate-400 hover:text-emerald-600"><UserPlus class="w-4 h-4" /></button>
                                <button v-if="asset.status === 'in_use' && canEditAssets" @click="doReturn(asset)" title="Return to pool"
                                    class="p-2.5 sm:p-1.5 text-slate-400 hover:text-amber-600"><Undo2 class="w-4 h-4" /></button>
                                <button v-if="canEditAssets" @click="openEdit(asset)" title="Edit" class="p-2.5 sm:p-1.5 text-slate-400 hover:text-blue-600"><Pencil class="w-4 h-4" /></button>
                                <button v-if="canEditAssets" @click="doDelete(asset)" title="Delete" class="p-2.5 sm:p-1.5 text-slate-400 hover:text-red-500"><Trash2 class="w-4 h-4" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="rows.length === 0" class="py-12 text-center text-sm text-slate-400 font-bold">No assets found.</div>
            </div>
        </div>

        <!-- Create / edit modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ editing ? 'Edit Asset' : 'Register Asset' }}</h2>
                    <button @click="showForm = false" class="p-1.5 text-slate-400 hover:text-slate-600"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitForm" class="space-y-3">
                    <input v-model="form.name" required placeholder="Asset name" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <select v-model="form.category" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                            <option value="hardware">Hardware</option><option value="software">Software</option>
                            <option value="network">Network</option><option value="peripheral">Peripheral</option>
                        </select>
                        <select v-model="form.type" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                            <option value="laptop">Laptop</option><option value="desktop">Desktop</option>
                            <option value="server">Server</option><option value="switch">Switch / Network</option>
                            <option value="barcode_scanner">Barcode Scanner</option><option value="printer">Printer</option>
                            <option value="license">Software License</option><option value="other">Other</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input v-model="form.serial_number" placeholder="Serial number" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                        <input v-model="form.location" placeholder="Location" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div><label class="text-[11px] font-bold text-slate-400 uppercase">Purchased</label>
                            <input v-model="form.purchase_date" type="date" class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" /></div>
                        <div><label class="text-[11px] font-bold text-slate-400 uppercase">Warranty End</label>
                            <input v-model="form.warranty_end" type="date" class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" /></div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input v-model="form.vendor" placeholder="Vendor" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                        <input v-model="form.cost" type="number" min="0" step="0.01" placeholder="Cost (₱)" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    </div>
                    <select v-if="editing" v-model="form.status" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="available">Available</option><option value="in_use">In Use</option>
                        <option value="maintenance">Maintenance</option><option value="retired">Retired</option>
                    </select>
                    <textarea v-model="form.specs" rows="2" placeholder="Specs / notes" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
                    <button type="submit" :disabled="form.processing" class="w-full py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50">
                        {{ editing ? 'Save Changes' : 'Register Asset' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Assign modal -->
        <div v-if="assigning" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">Assign {{ assigning.asset_code }}</h2>
                    <button @click="assigning = null" class="p-1.5 text-slate-400 hover:text-slate-600"><X class="w-5 h-5" /></button>
                </div>
                <select v-model="assignUserId" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 mb-3">
                    <option value="">Select user…</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <button @click="doAssign" :disabled="!assignUserId" class="w-full py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 active:scale-95 disabled:opacity-50">
                    Confirm Assignment
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
