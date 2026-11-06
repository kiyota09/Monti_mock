<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import JobOrderDetails from '@/Components/Manufacturing/JobOrderDetails.vue';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'dyeing_color'));
import {
    Droplet, X, AlertTriangle, CheckCircle2,
    FlaskConical, Palette, Layers, Blend, Sparkles, ChevronRight,
} from 'lucide-vue-next';

const props = defineProps({
    fabrics:  Array,   // status='dyeing', includes linked JO recipe
    assignedOrders: { type: Array, default: () => [] },
    machines: Array,
    dyes:     Array,   // ManufacturingInventoryItem where dept=dyeing, category=Dye
    steamReady: { type: Boolean, default: false },
});

const page = usePage();

// ─── Flash ────────────────────────────────────────────────────────────────────
const showFlash    = ref(!!page.props.flash?.message);
const flashMessage = ref(page.props.flash?.message ?? '');
const flashIsError = ref(false);

watch(() => page.props.flash?.message, (val) => {
    if (val) { flashMessage.value = val; flashIsError.value = false; showFlash.value = true; }
});
watch(() => page.props.errors, (errs) => {
    const first = Object.values(errs ?? {})[0];
    if (first) { flashMessage.value = first; flashIsError.value = true; showFlash.value = true; }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
const fmtNum       = (n)  => parseFloat(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ─── Recipe Modal ─────────────────────────────────────────────────────────────
const recipeModal      = ref(false);
const recipeModalFabric = ref(null);
const openRecipeModal  = (fabric) => { recipeModalFabric.value = fabric; recipeModal.value = true; };
const closeRecipeModal = () => { recipeModal.value = false; recipeModalFabric.value = null; };

// ─── Dye Modal ────────────────────────────────────────────────────────────────
const dyeModal      = ref(false);
const currentFabric = ref(null);
const selectedFabricIds = ref([]);
const currentGroup = ref(null);
const selectedBatchId = ref('');
const batchId = (fabric) => fabric.batch_id;
const joGroups = computed(() => {
    const groups = new Map();
    for (const fabric of props.fabrics) {
        const assignment = props.assignedOrders.find(order =>
            (fabric.manufacturing_order_id && Number(order.manufacturing_order_id) === Number(fabric.manufacturing_order_id))
            || (fabric.sales_order_id && Number(order.sales_order_id) === Number(fabric.sales_order_id))
        );
        const key = fabric.sales_order?.jo_number || assignment?.reference || (fabric.manufacturing_order_id ? `MO-${fabric.manufacturing_order_id}` : `Fabric ${fabric.code}`);
        if (!groups.has(key)) {
            groups.set(key, { key, reference: key, client: assignment?.client, recipe: assignment?.recipe, manufacturingOrder: assignment?.manufacturing_order, fabrics: [] });
        }
        groups.get(key).fabrics.push(fabric);
    }
    return [...groups.values()];
});
const batchOptions = computed(() => {
    const batches = new Map();
    for (const fabric of currentGroup.value?.fabrics ?? []) {
        if (fabric.batch_id && !batches.has(fabric.batch_id)) batches.set(fabric.batch_id, { id: fabric.batch_id, machine_id: fabric.batch_machine_id, machine: fabric.machine?.machine_no, capacity: Number(fabric.batch_capacity_kg || 0) });
    }
    return [...batches.values()];
});
const selectedBatch = computed(() => batchOptions.value.find(batch => String(batch.id) === String(selectedBatchId.value)) ?? null);
const groupedCandidates = computed(() => currentGroup.value?.fabrics.filter(f => String(batchId(f)) === String(selectedBatchId.value)) ?? []);
const selectedFabricWeight = computed(() => groupedCandidates.value.filter(f => selectedFabricIds.value.some(id => Number(id) === Number(f.id))).reduce((sum, f) => sum + Number(f.weight || 0), 0));
const exceedsBatchCapacity = computed(() => selectedBatch.value
    && selectedFabricWeight.value > selectedBatch.value.capacity + 0.001);

const dyeFormulaMaterials = computed(() =>
    currentGroup.value?.recipe?.formula_groups?.find((group) => group.label?.toLowerCase() === 'dye stuffs')?.materials ?? [],
);
const autoDyeUsage = computed(() => dyeFormulaMaterials.value.map((material) => {
    const materialId = Number(material.material_id);
    const percentage = Number(material.lab_quantity ?? 0);
    const quantity = selectedFabricIds.value.length > 0
        ? Math.round((selectedFabricWeight.value * percentage / 100) * 100) / 100
        : Number(material.quantity ?? 0);
    const available = props.dyes
        .filter((stock) => Number(stock.material_id) === materialId)
        .reduce((total, stock) => total + Number(stock.remaining_quantity ?? 0), 0);

    return { ...material, materialId, percentage, quantity, available };
}));
const manualDyeQuantities = ref({});
const dyeUsageForEntry = computed(() => autoDyeUsage.value.map((item) => ({
    ...item,
    quantity: manualDyeQuantities.value[item.materialId] ?? item.quantity,
})));
const hasAutoDyeFormula = computed(() => autoDyeUsage.value.length > 0
    && autoDyeUsage.value.every((item) => item.materialId > 0 && item.percentage > 0));
const hasValidDyeAmounts = computed(() => hasAutoDyeFormula.value
    && dyeUsageForEntry.value.every((item) => Number(item.quantity) >= 0.01));
const hasEnoughDyeStock = computed(() => {
    if (!hasAutoDyeFormula.value) return false;
    const requiredByMaterial = new Map();
    dyeUsageForEntry.value.forEach((item) => {
        requiredByMaterial.set(item.materialId, (requiredByMaterial.get(item.materialId) ?? 0) + Number(item.quantity));
    });

    return [...requiredByMaterial].every(([materialId, quantity]) => {
        const available = props.dyes
            .filter((stock) => Number(stock.material_id) === materialId)
            .reduce((total, stock) => total + Number(stock.remaining_quantity ?? 0), 0);
        return available + 0.001 >= quantity;
    });
});

const form = useForm({
    fabric_ids: [],
    machine_id: '',
    remarks:    '',
    dyes_used:  [],
});

const openDyeModal = (fabric) => {
    const group = fabric.fabrics ? fabric : joGroups.value.find(item => item.fabrics.some(row => row.id === fabric.id));
    currentGroup.value = group ?? null;
    currentFabric.value = group?.fabrics?.[0] ?? fabric;
    manualDyeQuantities.value = {};
    selectedBatchId.value = String(batchOptions.value[0]?.id ?? '');
    selectedFabricIds.value = [];
    form.fabric_ids = [];
    form.machine_id = batchOptions.value[0]?.machine_id ?? '';
    form.remarks    = '';
    dyeModal.value  = true;
};
const closeDyeModal = () => {
    dyeModal.value  = false;
    currentFabric.value = null;
    currentGroup.value = null;
    selectedBatchId.value = '';
    selectedFabricIds.value = [];
    manualDyeQuantities.value = {};
    form.reset();
};

watch(selectedFabricIds, () => {
    manualDyeQuantities.value = {};
}, { deep: true });

// ─── Dye Row Validation ───────────────────────────────────────────────────────
const canSubmit = computed(() =>
    selectedFabricIds.value.length > 0 &&
    !exceedsBatchCapacity.value &&
    form.machine_id &&
    hasValidDyeAmounts.value &&
    hasEnoughDyeStock.value &&
    !form.processing
);

// ─── Submit ───────────────────────────────────────────────────────────────────
const submitDye = () => {
    form.fabric_ids = selectedFabricIds.value;
    form.dyes_used = dyeUsageForEntry.value.map((item) => ({
        material_id: item.materialId,
        quantity_used: Number(item.quantity),
    }));
    form.post(route('man.staff.dyeing-color.store-dye'), {
        preserveScroll: true,
        onSuccess: () => {
            closeDyeModal();
            router.reload({ only: ['fabrics', 'dyes'] });
        },
    });
};
</script>

<template>
    <AuthenticatedLayout title="Dyeing Color">
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Droplet class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Dyeing Color
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Dyeing Color Workspace</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ fabrics?.length ?? 0 }} fabric{{ (fabrics?.length ?? 0) !== 1 ? 's' : '' }} pending dyeing</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ fabrics?.length ?? 0 }} pending
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Flash -->
                <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100" leave-to-class="opacity-0">
                    <div v-if="showFlash" class="animate-fade-up flex items-start gap-3 rounded-3xl px-4 py-3 shadow-sm border bg-white/80 dark:bg-zinc-900/80 backdrop-blur"
                         :class="flashIsError ? 'border-red-200 dark:border-red-800' : 'border-emerald-200 dark:border-emerald-800'">
                        <component :is="flashIsError ? AlertTriangle : CheckCircle2"
                            class="w-5 h-5 flex-shrink-0 mt-0.5"
                            :class="flashIsError ? 'text-red-500' : 'text-emerald-500'" />
                        <p class="text-sm flex-1 font-medium" :class="flashIsError ? 'text-red-700 dark:text-red-300' : 'text-gray-700 dark:text-gray-200'">{{ flashMessage }}</p>
                        <button @click="showFlash = false"><X class="w-4 h-4 text-gray-400 hover:text-gray-600" /></button>
                    </div>
                </transition>

                <section v-if="assignedOrders.length" class="rounded-2xl border bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="font-black text-slate-900 dark:text-white">Job Orders assigned to you</h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <article v-for="order in assignedOrders" :key="order.assignment_id" class="rounded-xl border p-3 dark:border-zinc-700">
                            <div class="flex items-start justify-between gap-2"><div><p class="font-mono text-sm font-black">{{ order.reference }}</p><p v-if="order.client" class="text-xs text-slate-500">{{ order.client }}</p></div><span class="rounded-full px-2 py-1 text-[9px] font-black uppercase" :class="order.stage_state === 'ready' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300'">{{ order.stage_state === 'ready' ? `${order.ready_fabric_count} fabric(s) ready at your stage` : order.stage_state === 'awaiting_review' ? `${order.review_fabric_count} awaiting supervisor review` : order.stage_state === 'awaiting_batch' ? 'First stage · waiting for batch setup' : 'Waiting for Pre Treatment' }}</span></div>
                            <p v-if="order.fabric_code" class="mt-1 text-xs font-bold text-cyan-800 dark:text-cyan-200">Assigned fabric: {{ order.fabric_code }}<span v-if="order.fabric_weight"> · {{ order.fabric_weight }} kg</span></p>
                            <p v-if="order.route" class="mt-2 text-[10px] text-slate-600 dark:text-zinc-300">Route: {{ order.route }}</p>
                            <JobOrderDetails :order="order" />
                        </article>
                    </div>
                </section>

                <!-- Empty state -->
                <div v-if="!fabrics || fabrics.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 dark:to-violet-900/30 rounded-full mb-4 animate-bounce-soft">
                        <FlaskConical class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">{{ assignedOrders.length ? 'No fabrics pending dyeing' : 'No Job Orders are assigned to you yet' }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ assignedOrders.length ? 'Your assigned Job Orders will appear here after the checker passes their fabrics to the dyeing stage.' : 'The dyeing supervisor will assign a Job Order and stage to you from JO Assignments.' }}</p>
                </div>

                <!-- One card and one record action per JO -->
                <section v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="group in joGroups" :key="group.key" class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-mono text-lg font-black">{{ group.reference }}</p><p v-if="group.client" class="text-xs text-slate-500">{{ group.client }}</p><p class="mt-1 text-xs text-slate-500">{{ group.fabrics.length }} fabric code(s) · {{ group.fabrics.reduce((sum,f)=>sum+Number(f.weight||0),0).toFixed(2) }} kg</p></div><span class="rounded-full bg-amber-100 px-2 py-1 text-[9px] font-black uppercase text-amber-800">Ready for color</span></div>
                        <details v-if="group.recipe" class="mt-3 rounded-xl bg-slate-50 p-3 dark:bg-zinc-800"><summary class="cursor-pointer text-sm font-bold">Full approved recipe</summary><p class="mt-2 text-xs"><b>Product:</b> {{ group.recipe.product||'—' }} · <b>Yarn:</b> {{ group.recipe.yarn_type||'—' }} · <b>Color:</b> {{ group.recipe.dye_color||'—' }} · <b>Design:</b> {{ group.recipe.weave_design||'—' }}</p><div class="mt-2 grid gap-2"><section v-for="section in group.recipe.material_groups||[]" :key="section.label" class="rounded-lg border bg-white p-2 text-xs dark:border-zinc-700 dark:bg-zinc-900"><p class="font-bold">{{ section.label }}</p><p v-if="!section.materials?.length" class="text-slate-500">None listed</p><p v-for="m in section.materials||[]" :key="m.material_id">{{ m.name }} · {{ m.quantity }} {{ m.unit||'' }}</p></section></div></details>
                        <div class="mt-3 flex flex-wrap gap-2"><span v-for="fabric in group.fabrics" :key="fabric.id" class="rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs dark:bg-indigo-500/10"><b class="font-mono">{{ fabric.code }}</b> · {{ fmtNum(fabric.weight) }} kg · {{ fabric.machine?.machine_no||'Machine' }}</span></div>
                        <button v-if="canEditProduction" @click="openDyeModal(group)" type="button" class="mt-4 w-full rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-3 py-2.5 text-sm font-bold text-white">Record Dyeing for this JO</button>
                    </article>
                </section>

            </div>
        </div>

        <!-- ═══════════ RECIPE MODAL ═══════════ -->
        <Transition name="modal">
            <div v-if="recipeModal && recipeModalFabric" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeRecipeModal" />
                <div class="relative w-full max-w-md bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800 animate-pop max-h-[90vh] flex flex-col">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 py-5 text-white">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="font-mono text-xs text-blue-100">{{ recipeModalFabric.code }}</p>
                                <h3 class="text-lg font-black tracking-tight mt-0.5">Production Recipe</h3>
                            </div>
                            <button @click="closeRecipeModal" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition">
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="overflow-y-auto flex-1 px-5 py-4 space-y-3">
                        <div v-if="recipeModalFabric.sales_order" class="grid grid-cols-2 gap-2">
                            <div class="bg-gray-50 dark:bg-zinc-800 rounded-2xl px-3 py-2.5">
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Job Order</p>
                                <p class="text-xs font-black text-gray-800 dark:text-gray-100 font-mono mt-0.5">{{ recipeModalFabric.sales_order.jo_number }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-zinc-800 rounded-2xl px-3 py-2.5">
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Color</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-100 mt-0.5">{{ recipeModalFabric.sales_order.color }}</p>
                            </div>
                        </div>

                        <div v-if="recipeModalFabric.recipe" class="space-y-2">
                            <div class="flex items-start gap-3 bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-500/20 rounded-2xl px-3 py-2.5">
                                <div class="w-7 h-7 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <Blend class="w-3.5 h-3.5 text-white" />
                                </div>
                                <div>
                                    <p class="text-[10px] text-blue-500 uppercase tracking-wide font-black">Yarn Composition</p>
                                    <p class="text-sm font-bold text-blue-900 dark:text-blue-200 mt-0.5">{{ recipeModalFabric.recipe.yarn_type }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-100 dark:border-amber-500/20 rounded-2xl px-3 py-2.5">
                                <div class="w-7 h-7 bg-amber-400 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <Palette class="w-3.5 h-3.5 text-gray-800" />
                                </div>
                                <div>
                                    <p class="text-[10px] text-amber-600 dark:text-amber-300 uppercase tracking-wide font-black">Dye Color Formula</p>
                                    <p class="text-sm font-bold text-amber-900 dark:text-amber-200 mt-0.5">{{ recipeModalFabric.recipe.dye_color }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5">
                                <div class="w-7 h-7 bg-gray-800 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <Layers class="w-3.5 h-3.5 text-white" />
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-wide font-black">Weave Design</p>
                                    <p class="text-sm font-bold text-gray-800 dark:text-gray-100 mt-0.5">{{ recipeModalFabric.recipe.weave_design }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-3 border-t border-gray-100 dark:border-zinc-800 bg-gray-50/60 dark:bg-zinc-800/40 flex gap-2">
                        <button @click="closeRecipeModal" type="button"
                            class="flex-1 py-2.5 border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-zinc-800 text-sm font-bold rounded-2xl transition active:scale-95">
                            Close
                        </button>
                        <button v-if="canEditProduction" @click="() => { openDyeModal(recipeModalFabric); closeRecipeModal(); }" type="button"
                            class="flex-1 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-bold rounded-2xl transition flex items-center justify-center gap-1.5 active:scale-95">
                            <Droplet class="w-4 h-4" /> Dye Fabric
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ═══════════ DYE MODAL ═══════════ -->
        <Transition name="modal">
            <div v-if="dyeModal && currentFabric" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeDyeModal" />

                <div class="relative w-full max-w-lg bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800 animate-pop max-h-[95vh] flex flex-col">

                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 py-5 text-white">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                        <div class="relative flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
                                    <Droplet class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="font-mono text-xs text-blue-100">{{ currentGroup?.reference }}</p>
                                    <h3 class="text-lg font-black tracking-tight mt-0.5">Record Dyeing</h3>
                                    <p class="text-xs text-blue-100/90 mt-0.5">{{ selectedFabricIds.length }} fabric code(s) selected · {{ selectedFabricWeight.toFixed(2) }} kg</p>
                                </div>
                            </div>
                            <button @click="closeDyeModal" class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition flex-shrink-0">
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div v-if="currentFabric.recipe" class="px-5 py-2.5 bg-amber-50 dark:bg-amber-500/10 border-b border-amber-100 dark:border-amber-500/20 flex items-center gap-2">
                        <Palette class="w-4 h-4 text-amber-600 flex-shrink-0" />
                        <div>
                            <p class="text-[10px] text-amber-600 dark:text-amber-300 font-black uppercase tracking-wide">Recipe — Dye Color Formula</p>
                            <p class="text-xs font-bold text-amber-900 dark:text-amber-200">{{ currentFabric.recipe.dye_color }}</p>
                        </div>
                    </div>

                    <div v-if="Object.keys(form.errors).length" class="mx-5 mt-4 flex items-start gap-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-2xl px-4 py-3">
                        <AlertTriangle class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                        <div class="text-sm text-red-700 dark:text-red-300 space-y-0.5">
                            <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                        </div>
                    </div>

                    <div class="overflow-y-auto flex-1 px-5 py-4 space-y-5">

                        <div v-if="batchOptions.length > 1" class="space-y-2">
                            <label class="block text-xs font-black uppercase tracking-wide text-gray-600 dark:text-gray-300">Machine batch<select v-model="selectedBatchId" @change="form.machine_id=selectedBatch?.machine_id??'';selectedFabricIds=[]" class="mt-1 w-full rounded-xl border p-3 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-800"><option v-for="batch in batchOptions" :key="batch.id" :value="String(batch.id)">{{ batch.machine }} · {{ batch.capacity }} kg capacity</option></select></label>
                        </div>
                        <div class="space-y-2">
                            <p class="text-xs font-black uppercase tracking-wide text-gray-600 dark:text-gray-300">Select fabric codes · {{ selectedFabricWeight.toFixed(2) }} / {{ selectedBatch?.capacity ?? 0 }} kg</p>
                            <label v-for="fabric in groupedCandidates" :key="fabric.id" class="flex items-center justify-between rounded-xl border p-3 text-sm dark:border-zinc-700">
                                <span class="flex items-center gap-2"><input v-model="selectedFabricIds" type="checkbox" :value="fabric.id"><span class="font-mono font-bold">{{ fabric.code }}</span></span><span>{{ fmtNum(fabric.weight) }} kg</span>
                            </label>
                            <p v-if="form.errors.fabric_ids" class="text-xs text-red-600">{{ form.errors.fabric_ids }}</p>
                            <p v-if="exceedsBatchCapacity" class="text-xs font-semibold text-red-600">Selected fabrics exceed this batch's {{ fmtNum(selectedBatch.capacity) }} kg capacity.</p>
                        </div>

                        <div>
                            <p class="text-xs font-bold text-gray-600 dark:text-gray-300">Assigned machine: {{ selectedBatch?.machine || 'Select a machine batch' }}</p>
                            <p v-if="!steamReady" class="mt-2 rounded-lg bg-amber-50 p-2 text-xs font-semibold text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Boiler steam is not available. The Boiler Operator must record an operating boiler and mark steam available before steam powered dyeing can start.</p>
                        </div>

                        <div class="border border-gray-200 dark:border-zinc-700 rounded-3xl overflow-hidden">
                            <div class="px-4 py-3 bg-gray-50/70 dark:bg-zinc-800/60 border-b border-gray-100 dark:border-zinc-700">
                                <p class="text-xs font-black text-gray-700 dark:text-gray-200 uppercase tracking-wide">Dye stuffs from this JO recipe</p>
                                <p class="text-xs text-gray-400 mt-0.5">Materials and quantities are calculated from the selected fabric codes. Matching inventory lots are consumed automatically.</p>
                            </div>
                            <div class="p-4 space-y-3">
                                <p v-if="!hasAutoDyeFormula" class="rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">This JO has no usable dye-stuff formula. Ask the supervisor or dyeing lab chemist to complete the recipe.</p>
                                <div v-for="dye in dyeUsageForEntry" :key="dye.materialId" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-100 bg-indigo-50/50 p-3 dark:border-indigo-900 dark:bg-indigo-950/20">
                                    <div>
                                        <p class="font-bold text-slate-800 dark:text-white">{{ dye.name }}</p>
                                        <p class="text-xs text-slate-500">{{ fmtNum(dye.percentage) }}% OWF · {{ fmtNum(dye.available) }} kg available</p>
                                    </div>
                                    <label class="flex items-center gap-2 text-xs font-bold text-indigo-700 dark:text-indigo-300">Required
                                        <input :value="dye.quantity" @input="manualDyeQuantities[dye.materialId] = $event.target.value === '' ? '' : Number($event.target.value)" type="number" min="0.01" step="0.01" class="w-28 rounded-lg border border-indigo-200 bg-white px-2 py-1.5 text-right text-sm font-black text-slate-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                                        <span>kg</span>
                                    </label>
                                </div>
                                <p v-if="selectedFabricIds.length === 0 && hasAutoDyeFormula" class="text-xs text-slate-500">Select fabric codes above to calculate dye quantities.</p>
                                <p v-if="hasAutoDyeFormula && !hasValidDyeAmounts" class="rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Enter a dye amount of at least 0.01 kg for each listed dye.</p>
                                <p v-else-if="hasValidDyeAmounts && !hasEnoughDyeStock" class="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">Available dye inventory does not cover these amounts. Reduce an amount or ask the supervisor to check stock.</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                Remarks <span class="text-gray-400 font-normal normal-case">(optional)</span>
                            </label>
                            <textarea v-model="form.remarks" rows="3"
                                placeholder="Any issues, color observations, or special notes…"
                                class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"></textarea>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t border-gray-100 dark:border-zinc-800 bg-gray-50/60 dark:bg-zinc-800/40 space-y-2">
                        <div class="flex gap-2">
                            <button type="button" @click="closeDyeModal"
                                class="flex-1 py-2.5 border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-zinc-800 text-sm font-bold rounded-2xl transition active:scale-95">
                                Cancel
                            </button>
                            <button v-if="canEditProduction" type="button" @click="submitDye" :disabled="!canSubmit"
                                class="flex-1 py-2.5 text-sm font-bold rounded-2xl transition disabled:opacity-50 disabled:cursor-not-allowed active:scale-95"
                                :class="canSubmit ? 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white shadow-lg shadow-indigo-500/20' : 'bg-gray-100 dark:bg-zinc-800 text-gray-400'">
                                {{ form.processing ? 'Recording…' : 'Submit Dye Job' }}
                            </button>
                        </div>
                        <div class="space-y-1 text-xs">
                            <p v-if="!selectedFabricIds.length" class="text-amber-600 flex items-center gap-1">
                                <AlertTriangle class="w-3 h-3" /> Select at least one fabric code.
                            </p>
                            <p v-if="!form.machine_id" class="text-amber-600 flex items-center gap-1">
                                <AlertTriangle class="w-3 h-3" /> Select a machine.
                            </p>
                            <p v-if="!hasAutoDyeFormula" class="text-amber-600 flex items-center gap-1">
                                <AlertTriangle class="w-3 h-3" /> A dye-stuff formula is required for this JO.
                            </p>
                            <p v-if="hasValidDyeAmounts && !hasEnoughDyeStock" class="text-red-600 flex items-center gap-1">
                                <AlertTriangle class="w-3 h-3" /> Not enough matching dye stock for the selected fabric codes.
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>

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
