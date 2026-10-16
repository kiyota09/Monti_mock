<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Shirt, AlertCircle, X, CheckSquare, Square, Sparkles, ClipboardList, ChevronRight } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));

const props = defineProps({
    fabrics: Array,
    machines: Array,
    softenerSupplies: Array, // from controller: { id, control_number, material_name, remaining_quantity, unit }
});

const selectedFabrics = ref([]);
const showSoftenerModal = ref(false);

const form = useForm({
    fabric_ids: [],
    machine_id: '',
    softener_inventory_id: '',
    softener_used: '',
    remarks: '',
});

// Toggle all fabrics selection
const toggleSelectAll = () => {
    if (selectedFabrics.value.length === props.fabrics.length) {
        selectedFabrics.value = [];
    } else {
        selectedFabrics.value = props.fabrics.map(f => f.id);
    }
};

// Toggle individual fabric selection
const toggleFabricSelection = (fabricId) => {
    const index = selectedFabrics.value.indexOf(fabricId);
    if (index > -1) {
        selectedFabrics.value.splice(index, 1);
    } else {
        selectedFabrics.value.push(fabricId);
    }
};

// Open modal with selected fabrics pre-filled
const openSoftenerModal = () => {
    if (selectedFabrics.value.length === 0) {
        alert('Please select at least one fabric.');
        return;
    }
    form.fabric_ids = [...selectedFabrics.value];
    form.machine_id = '';
    form.softener_inventory_id = '';
    form.softener_used = '';
    form.remarks = '';
    showSoftenerModal.value = true;
};

const closeModal = () => {
    showSoftenerModal.value = false;
    form.reset();
    form.clearErrors();
};

const submitSoftener = () => {
    form.post(route('man.staff.dyeing-fabric-softener.store-soften'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            selectedFabrics.value = [];
            router.reload({ only: ['fabrics'] });
        },
    });
};

// Compute total weight of selected fabrics
const totalWeight = computed(() => {
    return props.fabrics
        .filter(f => selectedFabrics.value.includes(f.id))
        .reduce((sum, f) => sum + parseFloat(f.weight), 0);
});

// Get selected fabric details for display in modal
const selectedFabricDetails = computed(() => {
    return props.fabrics.filter(f => selectedFabrics.value.includes(f.id));
});

// Get selected softener item for display
const selectedSoftenerItem = computed(() => {
    return props.softenerSupplies?.find(s => s.id == form.softener_inventory_id);
});
</script>

<template>
    <AuthenticatedLayout title="Dyeing Fabric Softener">
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Shirt class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Fabric Softener
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Fabric Softener Workspace</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ fabrics?.length ?? 0 }} fabric{{ (fabrics?.length ?? 0) !== 1 ? 's' : '' }} pending · {{ selectedFabrics.length }} selected</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ selectedFabrics.length }} selected
                            </span>
                            <Link :href="route('man.staff.dyeing-fabric-softener.reports')"
                                class="rounded-full bg-white px-4 py-1.5 text-xs font-black text-indigo-700 shadow-lg hover:scale-105 transition-all duration-200 active:scale-95 flex items-center gap-1.5">
                                <ClipboardList class="h-3.5 w-3.5" /> View Reports
                            </Link>
                        </div>
                    </div>

                    <!-- Selection controls inside hero -->
                    <div class="relative mt-6 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                        <button @click="toggleSelectAll" class="flex items-center gap-2 rounded-2xl bg-white/15 hover:bg-white/25 px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95 ring-1 ring-white/25">
                            <CheckSquare v-if="selectedFabrics.length === fabrics?.length && fabrics?.length" class="w-4 h-4" />
                            <Square v-else class="w-4 h-4" />
                            {{ selectedFabrics.length === fabrics?.length && fabrics?.length ? 'Deselect All' : 'Select All' }}
                        </button>
                        <button v-if="canEditProduction" @click="openSoftenerModal" :disabled="selectedFabrics.length === 0"
                            class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95 disabled:opacity-50"
                            :class="selectedFabrics.length ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white'">
                            Apply Softener to Selected ({{ selectedFabrics.length }})
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="!fabrics || fabrics.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 dark:to-violet-900/30 rounded-full mb-4 animate-bounce-soft">
                        <AlertCircle class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No fabrics pending softening</p>
                    <p class="text-xs text-gray-400 mt-1">Fabrics will appear here once dyeing is complete.</p>
                </div>

                <!-- Fabrics grid with checkboxes -->
                <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="(fabric, i) in fabrics" :key="fabric.id"
                        :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        @click="toggleFabricSelection(fabric.id)"
                        :class="selectedFabrics.includes(fabric.id) ? 'border-indigo-400 dark:border-indigo-600 ring-2 ring-indigo-200 dark:ring-indigo-800' : ''"
                        class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden cursor-pointer">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />

                        <div class="flex items-start gap-3">
                            <input type="checkbox" :checked="selectedFabrics.includes(fabric.id)" @click.stop="toggleFabricSelection(fabric.id)"
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2 mb-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-purple-500 via-fuchsia-600 to-indigo-700 flex items-center justify-center text-white text-lg font-black shadow-lg uppercase flex-shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                            {{ (fabric.code ?? '?').charAt(0) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-mono text-sm font-black text-gray-900 dark:text-white truncate tracking-tight group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">
                                                {{ fabric.code }}
                                            </p>
                                            <p class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase truncate">
                                                {{ fabric.yarn_type }} · {{ fabric.weight }} kg
                                            </p>
                                            <p v-if="fabric.sales_order" class="text-[10px] text-gray-400 font-medium truncate">
                                                JO: {{ fabric.sales_order.jo_number }} | {{ fabric.sales_order.color }}
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex-shrink-0 flex items-center gap-1"
                                        :class="selectedFabrics.includes(fabric.id) ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-yellow-100 text-yellow-700 ring-yellow-200 dark:bg-yellow-500/15 dark:text-yellow-300 dark:ring-yellow-500/30'">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ selectedFabrics.includes(fabric.id) ? 'Picked' : 'Pending' }}
                                    </span>
                                </div>
                                <div class="space-y-1.5 text-[12px] text-gray-500 dark:text-gray-400">
                                    <p><span class="font-bold text-gray-700 dark:text-gray-200">Machine:</span> <span class="font-medium">{{ fabric.machine?.machine_no || 'N/A' }}</span></p>
                                    <p><span class="font-bold text-gray-700 dark:text-gray-200">Operator:</span> <span class="font-medium">{{ fabric.operator?.name }}</span></p>
                                    <p><span class="font-bold text-gray-700 dark:text-gray-200">Shift:</span> <span class="font-medium">{{ fabric.shift }}</span></p>
                                    <p><span class="font-bold text-gray-700 dark:text-gray-200">Date:</span> <span class="font-medium">{{ new Date(fabric.processed_at).toLocaleString() }}</span></p>
                                </div>
                            </div>
                        </div>
                        <ChevronRight class="absolute bottom-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                </TransitionGroup>

            </div>
        </div>

        <!-- Softener Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showSoftenerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal" />
                    <div class="relative bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-100 dark:border-zinc-800 animate-pop max-h-[90vh] flex flex-col">
                        <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                            <div class="relative flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
                                        <Shirt class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-black tracking-tight">Record Softening</h3>
                                        <p class="text-xs text-blue-100">{{ selectedFabricDetails.length }} fabric{{ selectedFabricDetails.length !== 1 ? 's' : '' }} · {{ totalWeight }} kg total</p>
                                    </div>
                                </div>
                                <button @click="closeModal" class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition">
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <form @submit.prevent="submitSoftener" class="p-6 space-y-4 overflow-y-auto">
                            <div class="bg-indigo-50/60 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800/40 p-4 rounded-2xl">
                                <p class="text-xs font-black uppercase tracking-wide text-gray-600 dark:text-gray-300 mb-2">
                                    Selected Fabrics ({{ selectedFabricDetails.length }})
                                </p>
                                <div class="space-y-2 max-h-40 overflow-y-auto">
                                    <div v-for="fabric in selectedFabricDetails" :key="fabric.id" class="text-sm text-gray-600 dark:text-gray-300">
                                        <span class="font-mono font-bold">{{ fabric.code }}</span> -
                                        {{ fabric.yarn_type }} ({{ fabric.weight }} kg)
                                        <span v-if="fabric.sales_order" class="text-gray-500">
                                            | Color: {{ fabric.sales_order.color }}
                                        </span>
                                    </div>
                                </div>
                                <p class="text-sm font-black mt-2 text-gray-900 dark:text-white">Total Weight: {{ totalWeight }} kg</p>
                            </div>

                            <div>
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-600 dark:text-gray-300 mb-1.5">Machine No.</label>
                                <select v-model="form.machine_id" required
                                    class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                    <option value="">Select Machine</option>
                                    <option v-for="machine in machines" :key="machine.id" :value="machine.id">
                                        {{ machine.machine_no }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-600 dark:text-gray-300 mb-1.5">Softener Type (Control Number)</label>
                                <select v-model="form.softener_inventory_id" required
                                    class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                    <option value="">Select Softener</option>
                                    <option v-for="item in softenerSupplies" :key="item.id" :value="item.id">
                                        {{ item.material_name }} - {{ item.control_number }} ({{ item.remaining_quantity }} {{ item.unit }} available)
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-600 dark:text-gray-300 mb-1.5">Softener Used (kg)</label>
                                <input type="number" step="0.01" min="0.01" :max="selectedSoftenerItem?.remaining_quantity" v-model="form.softener_used" required
                                    class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                                    placeholder="Enter amount used" />
                                <p v-if="form.errors.softener_used" class="text-red-500 text-xs mt-1">{{ form.errors.softener_used }}</p>
                                <p v-if="selectedSoftenerItem" class="text-xs text-gray-500 mt-1">
                                    Available: {{ selectedSoftenerItem.remaining_quantity }} {{ selectedSoftenerItem.unit }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-600 dark:text-gray-300 mb-1.5">Remarks</label>
                                <textarea v-model="form.remarks" rows="2"
                                    class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                                    placeholder="Optional notes..."></textarea>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button v-if="canEditProduction" type="submit" :disabled="form.processing || !canEditProduction"
                                    class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white py-2.5 rounded-2xl font-bold transition active:scale-95 disabled:opacity-50">
                                    {{ form.processing ? 'Processing...' : 'Submit Softener Job' }}
                                </button>
                                <button type="button" @click="closeModal"
                                    class="flex-1 bg-gray-100 dark:bg-zinc-800 hover:bg-gray-200 dark:hover:bg-zinc-700 text-gray-700 dark:text-gray-200 py-2.5 rounded-2xl font-bold transition active:scale-95">
                                    Cancel
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
.modal-enter-active { transition: opacity 0.25s ease; }
.modal-enter-active .animate-pop { animation: pop 0.35s cubic-bezier(0.22,1,0.36,1) both; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-leave-active { transition: opacity 0.2s ease; }
</style>
