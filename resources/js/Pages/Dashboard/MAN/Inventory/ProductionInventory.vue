<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Package, Box, Layers, Edit, AlertCircle, CheckCircle2, Clock, Filter, Sparkles } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditInventory = computed(() => canEdit('MAN', 'inventory'));

const props = defineProps({
    items: Array,
});

// ── Modal State ──────────────────────────────────────────────────────────
const showContainerModal = ref(false);
const selectedItem       = ref(null);
const containerForm      = ref({ total_units: '', unit_type: 'roll', unit_weight: '' });
const containerErrors    = ref({});

const consumeQuantities  = ref({});
const consumeErrors      = ref({});

// ── Department filter ────────────────────────────────────────────────────
const activeDept = ref('all');
const departments = computed(() => {
    const depts = [...new Set((props.items || []).map(i => i.department))];
    return depts.sort();
});

const filteredItems = computed(() => {
    if (activeDept.value === 'all') return props.items;
    return (props.items || []).filter(i => i.department === activeDept.value);
});

// ── Open Container Modal ─────────────────────────────────────────────────
const openContainerModal = (item) => {
    selectedItem.value  = item;
    containerErrors.value = {};
    containerForm.value = {
        total_units: item.total_units || '',
        unit_type:   item.unit_type   || 'roll',
        unit_weight: item.unit_weight || '',
    };
    showContainerModal.value = true;
};

const saveContainer = () => {
    containerErrors.value = {};
    router.post(route('man.inventory.container.update', selectedItem.value.id), containerForm.value, {
        preserveScroll: true,
        onSuccess: () => { showContainerModal.value = false; },
        onError:   (errors) => { containerErrors.value = errors; },
    });
};

// ── Consume ──────────────────────────────────────────────────────────────
const consumeUnits = (item) => {
    const units = consumeQuantities.value[item.id];
    if (!units || units <= 0) return;
    consumeErrors.value[item.id] = null;
    router.post(route('man.inventory.consume', item.id), { units_used: parseInt(units, 10) }, {
        preserveScroll: true,
        onSuccess: () => { consumeQuantities.value[item.id] = ''; },
        onError:   (errors) => { consumeErrors.value[item.id] = errors?.units_used; },
    });
};

// ── Helpers ──────────────────────────────────────────────────────────────
const getCategoryIcon = (category) => {
    switch ((category || '').toLowerCase()) {
        case 'yarn':     return Layers;
        case 'dye':      return Package;
        case 'supplies': return Box;
        default:         return Package;
    }
};

const deptColor = (dept) => {
    const map = {
        knitting:    'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
        dyeing:      'bg-purple-100 text-purple-700 ring-purple-200 dark:bg-purple-500/15 dark:text-purple-300 dark:ring-purple-500/30',
        maintenance: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        packaging:   'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
    };
    return map[dept?.toLowerCase()] ?? 'bg-gray-100 text-gray-600';
};

const statusBadge = (status) => {
    if (status === 'available') return { icon: CheckCircle2, cls: 'text-emerald-500', label: 'Available' };
    if (status === 'partial')   return { icon: Clock,        cls: 'text-amber-500', label: 'Partial'   };
    return                             { icon: AlertCircle,  cls: 'text-rose-400',    label: 'Depleted'  };
};

const usagePercent = (item) => {
    if (!item.total_units) return 0;
    return Math.round((item.used_units / item.total_units) * 100);
};
</script>

<template>
    <AuthenticatedLayout title="Production Inventory">
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
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Inventory
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Production Inventory</h1>
                            <span v-if="!canEditInventory" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">Materials transferred from Warehouse — available for manufacturing use</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ items.length }} item{{ items.length !== 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>

                    <!-- Department tabs inside hero -->
                    <div v-if="departments.length > 1" class="relative mt-6 flex gap-2 flex-wrap">
                        <button
                            @click="activeDept = 'all'"
                            :class="activeDept === 'all' ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                            All Departments
                        </button>
                        <button
                            v-for="dept in departments" :key="dept"
                            @click="activeDept = dept"
                            :class="activeDept === dept ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                            class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95 capitalize">
                            {{ dept }}
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay: 100ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full" />
                    <div class="relative flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><Filter class="h-4 w-4" /></span>
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white capitalize">{{ activeDept === 'all' ? 'All Materials' : activeDept + ' Materials' }}</h2>
                        <span class="ml-auto rounded-full bg-indigo-50 dark:bg-indigo-900/20 px-3 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-300">{{ filteredItems.length }} items</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Material</th>
                                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Category</th>
                                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Control ID</th>
                                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Dept</th>
                                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Stock</th>
                                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Container</th>
                                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Status</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr
                                    v-for="(item, i) in filteredItems" :key="item.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                                    class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">

                                    <!-- Material name -->
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex-shrink-0"><component :is="getCategoryIcon(item.category)" class="w-4 h-4 text-indigo-500" /></span>
                                            <span class="font-bold text-gray-900 dark:text-white">{{ item.material_name }}</span>
                                        </div>
                                    </td>

                                    <!-- Category -->
                                    <td class="px-5 py-4">
                                        <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-gray-300">
                                            {{ item.category }}
                                        </span>
                                    </td>

                                    <!-- Control ID -->
                                    <td class="px-5 py-4 font-mono text-xs font-bold text-gray-600 dark:text-gray-400">
                                        {{ item.control_number }}
                                    </td>

                                    <!-- Department -->
                                    <td class="px-5 py-4">
                                        <span :class="['text-[10px] px-2.5 py-1 rounded-full font-black uppercase ring-1 capitalize inline-flex items-center gap-1', deptColor(item.department)]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ item.department }}
                                        </span>
                                    </td>

                                    <!-- Remaining stock -->
                                    <td class="px-5 py-4">
                                        <div class="font-black text-gray-900 dark:text-white">
                                            {{ item.remaining_quantity }} <span class="text-xs font-medium text-gray-500">{{ item.unit }}</span>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-0.5">of {{ item.initial_quantity }} {{ item.unit }} initial</div>
                                    </td>

                                    <!-- Container details -->
                                    <td class="px-5 py-4">
                                        <div v-if="item.total_units" class="text-xs">
                                            <!-- Usage bar -->
                                            <div class="flex items-center gap-2 mb-1">
                                                <div class="flex-1 bg-gray-100 dark:bg-zinc-700 rounded-full h-1.5 w-24 overflow-hidden">
                                                    <div
                                                        class="h-1.5 rounded-full bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 transition-all"
                                                        :style="{ width: usagePercent(item) + '%' }">
                                                    </div>
                                                </div>
                                                <span class="text-gray-500 font-bold">{{ usagePercent(item) }}%</span>
                                            </div>
                                            <div class="text-gray-600 dark:text-gray-300 font-medium">
                                                {{ item.used_units }} / {{ item.total_units }} {{ item.unit_type }}s used
                                            </div>
                                            <div v-if="item.unit_weight" class="text-gray-400">
                                                {{ item.unit_weight }} kg/{{ item.unit_type }}
                                            </div>
                                        </div>
                                        <button
                                            v-else-if="canEditInventory"
                                            @click="openContainerModal(item)"
                                            class="rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-300 hover:bg-indigo-600 hover:text-white px-3 py-1.5 flex items-center gap-1 text-xs font-black transition-all hover:-translate-y-0.5 active:scale-95">
                                            <Edit class="w-3.5 h-3.5" />
                                            Open Container
                                        </button>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-5 py-4">
                                        <component
                                            :is="statusBadge(item.status).icon"
                                            :class="['w-4 h-4', statusBadge(item.status).cls]"
                                            :title="statusBadge(item.status).label" />
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                        <!-- Empty state -->
                        <div v-if="filteredItems.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                                <Package class="h-9 w-9 text-indigo-400" />
                            </div>
                            <p class="text-sm font-black text-gray-700 dark:text-gray-200">No materials in production inventory</p>
                            <p class="text-xs text-gray-400 mt-1">
                                Release materials from the Warehouse Monitor to populate this list.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Container Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showContainerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="showContainerModal = false">
                    <div class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl w-full max-w-md shadow-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">

                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-5">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <h2 class="relative text-base font-black text-white tracking-tight">Open Container</h2>
                            <p class="relative text-sm text-blue-100/90 mt-0.5">{{ selectedItem?.material_name }}</p>
                        </div>

                        <form @submit.prevent="saveContainer" class="p-6 space-y-4">
                            <!-- Total units -->
                            <div>
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">
                                    Total Units
                                    <span class="text-gray-400 font-medium normal-case ml-1">
                                        (rolls for Yarn · boxes for Packaging · auto Kg for Dye/Supplies)
                                    </span>
                                </label>
                                <input
                                    v-model="containerForm.total_units"
                                    type="number" min="1" required
                                    class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
                                    placeholder="e.g. 24"
                                />
                                <p v-if="containerErrors.total_units" class="text-rose-500 text-xs mt-1">{{ containerErrors.total_units }}</p>
                            </div>

                            <!-- Unit type -->
                            <div>
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Unit Type</label>
                                <select
                                    v-model="containerForm.unit_type" required
                                    class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                    <option value="roll">Roll (for Yarn)</option>
                                    <option value="box">Box (for Packaging)</option>
                                </select>
                            </div>

                            <!-- Weight per unit — Yarn only -->
                            <div v-if="selectedItem?.category?.toLowerCase() === 'yarn'">
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">
                                    Weight per Roll <span class="text-gray-400 font-medium">(kg)</span>
                                </label>
                                <input
                                    v-model="containerForm.unit_weight"
                                    type="number" step="0.01" required
                                    class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition"
                                    placeholder="e.g. 2.5"
                                />
                                <p v-if="containerErrors.unit_weight" class="text-rose-500 text-xs mt-1">{{ containerErrors.unit_weight }}</p>
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button
                                    type="button" @click="showContainerModal = false"
                                    class="px-4 py-2.5 text-xs font-black uppercase tracking-wide rounded-2xl border border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-800 transition active:scale-95">
                                    Cancel
                                </button>
                                <button v-if="canEditInventory"
                                    type="submit"
                                    class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl text-xs font-black uppercase tracking-wide transition shadow-lg shadow-indigo-500/25 active:scale-95" :disabled="!canEditInventory">
                                    Save Container
                                </button>
                            </div>
                        </form>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
</style>
