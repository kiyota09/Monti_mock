<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import JobOrderDetails from '@/Components/Manufacturing/JobOrderDetails.vue';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'dyeing_color'));
import {
    Droplet, X, AlertTriangle, CheckCircle2, ClipboardList, Scale, Layers,
    FlaskConical, Palette, Blend, Cog, Eye, Info,
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

const stageLabel = (order) => {
    if (order.stage_state === 'ready') return `${order.ready_fabric_count} fabric(s) ready`;
    if (order.stage_state === 'awaiting_review') return `${order.review_fabric_count} awaiting review`;
    if (order.stage_state === 'awaiting_batch') return 'Waiting for batch setup';
    return 'Waiting for Pre Treatment';
};
const stageClass = (order) => order.stage_state === 'ready'
    ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20'
    : 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20';

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
const totalPendingWeight = computed(() => (props.fabrics ?? []).reduce((sum, f) => sum + Number(f.weight || 0), 0));
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
const capacityPercent = computed(() => {
    const cap = Number(selectedBatch.value?.capacity || 0);
    if (!cap) return 0;
    return Math.min(100, Math.round((selectedFabricWeight.value / cap) * 100));
});

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
        <div class="min-h-screen bg-slate-50 dark:bg-zinc-950">
            <div class="mx-auto max-w-7xl space-y-6 p-4 pb-16 sm:p-6 lg:p-8">

                <!-- ═══════════ Page header ═══════════ -->
                <header class="animate-fade-up flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm shadow-indigo-600/30">
                            <Droplet class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">Manufacturing · Dyeing</p>
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Dyeing Color Workspace</h1>
                        </div>
                    </div>
                    <span v-if="!canEditProduction"
                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">
                        <Eye class="h-3.5 w-3.5" /> View only
                    </span>
                </header>

                <!-- ═══════════ Summary stats ═══════════ -->
                <section class="animate-fade-up grid grid-cols-1 gap-4 sm:grid-cols-3" style="animation-delay: .05s">
                    <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300"><ClipboardList class="h-5 w-5" /></div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-zinc-400">Job orders</p>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ joGroups.length }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300"><Layers class="h-5 w-5" /></div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-zinc-400">Fabrics pending dyeing</p>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ fabrics?.length ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300"><Scale class="h-5 w-5" /></div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-zinc-400">Total weight</p>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ fmtNum(totalPendingWeight) }} <span class="text-sm font-medium text-slate-400">kg</span></p>
                        </div>
                    </div>
                </section>

                <!-- ═══════════ Flash ═══════════ -->
                <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100" leave-to-class="opacity-0">
                    <div v-if="showFlash"
                        class="flex items-start gap-3 rounded-xl border-l-4 bg-white px-4 py-3 shadow-sm dark:bg-zinc-900"
                        :class="flashIsError ? 'border-red-500' : 'border-emerald-500'">
                        <component :is="flashIsError ? AlertTriangle : CheckCircle2"
                            class="mt-0.5 h-5 w-5 flex-shrink-0"
                            :class="flashIsError ? 'text-red-500' : 'text-emerald-500'" />
                        <p class="flex-1 text-sm font-medium" :class="flashIsError ? 'text-red-700 dark:text-red-300' : 'text-slate-700 dark:text-zinc-200'">{{ flashMessage }}</p>
                        <button type="button" @click="showFlash = false" class="rounded-md p-0.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-zinc-800"><X class="h-4 w-4" /></button>
                    </div>
                </transition>

                <!-- ═══════════ Assigned job orders ═══════════ -->
                <section v-if="assignedOrders.length" class="animate-fade-up rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-zinc-800">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Job Orders assigned to you</h2>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">Your current assignments and their stage status</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ assignedOrders.length }}</span>
                    </div>
                    <div class="grid gap-3 p-5 sm:grid-cols-2">
                        <article v-for="order in assignedOrders" :key="order.assignment_id"
                            class="rounded-xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:shadow-sm dark:border-zinc-700 dark:hover:border-indigo-500/40">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-mono text-sm font-bold text-slate-900 dark:text-white">{{ order.reference }}</p>
                                    <p v-if="order.client" class="truncate text-xs text-slate-500 dark:text-zinc-400">{{ order.client }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold ring-1 ring-inset" :class="stageClass(order)">{{ stageLabel(order) }}</span>
                            </div>
                            <p v-if="order.fabric_code" class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                <Layers class="h-3.5 w-3.5" />{{ order.fabric_code }}<span v-if="order.fabric_weight"> · {{ order.fabric_weight }} kg</span>
                            </p>
                            <p v-if="order.route" class="mt-2 text-[11px] text-slate-500 dark:text-zinc-400"><span class="font-semibold text-slate-600 dark:text-zinc-300">Route:</span> {{ order.route }}</p>
                            <JobOrderDetails :order="order" />
                        </article>
                    </div>
                </section>

                <!-- ═══════════ Empty state ═══════════ -->
                <div v-if="!fabrics || fabrics.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-500/10">
                        <FlaskConical class="h-8 w-8 text-indigo-500" />
                    </div>
                    <p class="text-base font-semibold text-slate-800 dark:text-zinc-100">{{ assignedOrders.length ? 'No fabrics pending dyeing' : 'No Job Orders are assigned to you yet' }}</p>
                    <p class="mt-1 max-w-md text-sm text-slate-500 dark:text-zinc-400">{{ assignedOrders.length ? 'Your assigned Job Orders will appear here after the checker passes their fabrics to the dyeing stage.' : 'The dyeing supervisor will assign a Job Order and stage to you from JO Assignments.' }}</p>
                </div>

                <!-- ═══════════ One card per JO ═══════════ -->
                <section v-else>
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Ready for color</h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">{{ joGroups.length }} job order{{ joGroups.length !== 1 ? 's' : '' }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="group in joGroups" :key="group.key"
                            class="animate-fade-up flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">

                            <div class="flex items-start justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-800/40">
                                <div class="min-w-0">
                                    <p class="truncate font-mono text-lg font-bold text-slate-900 dark:text-white">{{ group.reference }}</p>
                                    <p v-if="group.client" class="truncate text-xs text-slate-500 dark:text-zinc-400">{{ group.client }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">Ready for color</span>
                            </div>

                            <div class="flex flex-1 flex-col gap-4 p-5">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-zinc-800">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Fabric codes</p>
                                        <p class="text-lg font-bold text-slate-900 dark:text-white">{{ group.fabrics.length }}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-zinc-800">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Total weight</p>
                                        <p class="text-lg font-bold text-slate-900 dark:text-white">{{ fmtNum(group.fabrics.reduce((sum,f)=>sum+Number(f.weight||0),0)) }} <span class="text-xs font-medium text-slate-400">kg</span></p>
                                    </div>
                                </div>

                                <details v-if="group.recipe" class="group rounded-xl border border-slate-200 dark:border-zinc-700">
                                    <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2.5 text-sm font-semibold text-slate-700 dark:text-zinc-200">
                                        <span class="flex items-center gap-2"><Palette class="h-4 w-4 text-indigo-500" /> Approved recipe</span>
                                        <span class="text-xs font-medium text-indigo-600 group-open:hidden dark:text-indigo-400">View</span>
                                        <span class="hidden text-xs font-medium text-slate-400 group-open:inline">Hide</span>
                                    </summary>
                                    <div class="space-y-3 border-t border-slate-100 p-3 dark:border-zinc-700">
                                        <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                                            <div><dt class="text-slate-400">Product</dt><dd class="font-semibold text-slate-800 dark:text-zinc-100">{{ group.recipe.product||'—' }}</dd></div>
                                            <div><dt class="text-slate-400">Yarn</dt><dd class="font-semibold text-slate-800 dark:text-zinc-100">{{ group.recipe.yarn_type||'—' }}</dd></div>
                                            <div><dt class="text-slate-400">Color</dt><dd class="font-semibold text-slate-800 dark:text-zinc-100">{{ group.recipe.dye_color||'—' }}</dd></div>
                                            <div><dt class="text-slate-400">Design</dt><dd class="font-semibold text-slate-800 dark:text-zinc-100">{{ group.recipe.weave_design||'—' }}</dd></div>
                                        </dl>
                                        <div class="grid gap-2">
                                            <section v-for="section in group.recipe.material_groups||[]" :key="section.label" class="rounded-lg bg-slate-50 p-2.5 text-xs dark:bg-zinc-800">
                                                <p class="mb-1 font-semibold text-slate-700 dark:text-zinc-200">{{ section.label }}</p>
                                                <p v-if="!section.materials?.length" class="text-slate-400">None listed</p>
                                                <p v-for="m in section.materials||[]" :key="m.material_id" class="flex justify-between gap-2 text-slate-600 dark:text-zinc-300">
                                                    <span>{{ m.name }}</span><span class="font-medium tabular-nums">{{ m.quantity }} {{ m.unit||'' }}</span>
                                                </p>
                                            </section>
                                        </div>
                                    </div>
                                </details>

                                <div>
                                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Fabric codes</p>
                                    <div class="flex flex-wrap gap-2">
                                        <span v-for="fabric in group.fabrics" :key="fabric.id"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-100 bg-indigo-50/70 px-2.5 py-1.5 text-xs text-indigo-900 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-200">
                                            <b class="font-mono">{{ fabric.code }}</b>
                                            <span class="text-indigo-300">|</span>
                                            <span class="tabular-nums">{{ fmtNum(fabric.weight) }} kg</span>
                                            <span class="text-indigo-300">|</span>
                                            <span class="inline-flex items-center gap-1"><Cog class="h-3 w-3" />{{ fabric.machine?.machine_no||'Machine' }}</span>
                                        </span>
                                    </div>
                                </div>

                                <button v-if="canEditProduction" @click="openDyeModal(group)" type="button"
                                    class="mt-auto inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:scale-[0.98] dark:focus:ring-offset-zinc-900">
                                    <Droplet class="h-4 w-4" /> Record Dyeing for this JO
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

            </div>
        </div>

        <!-- ═══════════ RECIPE MODAL ═══════════ -->
        <Transition name="modal">
            <div v-if="recipeModal && recipeModalFabric" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeRecipeModal" />
                <div class="animate-pop relative flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-zinc-800">
                        <div>
                            <p class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ recipeModalFabric.code }}</p>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Production Recipe</h3>
                        </div>
                        <button @click="closeRecipeModal" type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-zinc-800">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                        <div v-if="recipeModalFabric.sales_order" class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-zinc-800">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Job Order</p>
                                <p class="mt-0.5 font-mono text-xs font-bold text-slate-800 dark:text-zinc-100">{{ recipeModalFabric.sales_order.jo_number }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-zinc-800">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Color</p>
                                <p class="mt-0.5 text-xs font-bold text-slate-800 dark:text-zinc-100">{{ recipeModalFabric.sales_order.color }}</p>
                            </div>
                        </div>

                        <div v-if="recipeModalFabric.recipe" class="space-y-2">
                            <div class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50/70 px-3 py-2.5 dark:border-blue-500/20 dark:bg-blue-500/10">
                                <div class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-600"><Blend class="h-4 w-4 text-white" /></div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-300">Yarn Composition</p>
                                    <p class="text-sm font-semibold text-blue-950 dark:text-blue-100">{{ recipeModalFabric.recipe.yarn_type }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 rounded-xl border border-amber-100 bg-amber-50/70 px-3 py-2.5 dark:border-amber-500/20 dark:bg-amber-500/10">
                                <div class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-amber-500"><Palette class="h-4 w-4 text-white" /></div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Dye Color Formula</p>
                                    <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">{{ recipeModalFabric.recipe.dye_color }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-800">
                                <div class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-slate-700"><Layers class="h-4 w-4 text-white" /></div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">Weave Design</p>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-zinc-100">{{ recipeModalFabric.recipe.weave_design }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3 dark:border-zinc-800 dark:bg-zinc-800/40">
                        <button @click="closeRecipeModal" type="button"
                            class="flex-1 rounded-xl border border-slate-200 bg-white py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 active:scale-[0.98] dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                            Close
                        </button>
                        <button v-if="canEditProduction" @click="() => { openDyeModal(recipeModalFabric); closeRecipeModal(); }" type="button"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-indigo-600 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 active:scale-[0.98]">
                            <Droplet class="h-4 w-4" /> Dye Fabric
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ═══════════ DYE MODAL ═══════════ -->
        <Transition name="modal">
            <div v-if="dyeModal && currentFabric" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeDyeModal" />

                <div class="animate-pop relative flex max-h-[95vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">

                    <!-- Header -->
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-4 dark:border-zinc-800">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-white"><Droplet class="h-5 w-5" /></div>
                            <div>
                                <p class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ currentGroup?.reference }}</p>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Record Dyeing</h3>
                            </div>
                        </div>
                        <button @click="closeDyeModal" type="button" class="flex-shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-zinc-800">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Recipe strip -->
                    <div v-if="currentFabric.recipe" class="flex items-center gap-3 border-b border-amber-100 bg-amber-50 px-6 py-2.5 dark:border-amber-500/20 dark:bg-amber-500/10">
                        <Palette class="h-4 w-4 flex-shrink-0 text-amber-600" />
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Recipe — Dye Color Formula</p>
                            <p class="truncate text-sm font-semibold text-amber-950 dark:text-amber-100">{{ currentFabric.recipe.dye_color }}</p>
                        </div>
                    </div>

                    <!-- Errors -->
                    <div v-if="Object.keys(form.errors).length" class="mx-6 mt-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-500/20 dark:bg-red-500/10">
                        <AlertTriangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500" />
                        <div class="space-y-0.5 text-sm text-red-700 dark:text-red-300">
                            <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                        </div>
                    </div>

                    <div class="flex-1 space-y-6 overflow-y-auto px-6 py-5">

                        <!-- Step 1 · Batch & fabrics -->
                        <section class="space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">1</span>
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Batch &amp; fabric codes</h4>
                            </div>

                            <label v-if="batchOptions.length > 1" class="block text-xs font-semibold text-slate-600 dark:text-zinc-300">Machine batch
                                <select v-model="selectedBatchId" @change="form.machine_id=selectedBatch?.machine_id??'';selectedFabricIds=[]"
                                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 text-sm font-normal text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                    <option v-for="batch in batchOptions" :key="batch.id" :value="String(batch.id)">{{ batch.machine }} · {{ batch.capacity }} kg capacity</option>
                                </select>
                            </label>

                            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2.5 text-xs dark:bg-zinc-800">
                                <span class="flex items-center gap-1.5 font-semibold text-slate-600 dark:text-zinc-300"><Cog class="h-4 w-4 text-slate-400" /> {{ selectedBatch?.machine || 'Select a machine batch' }}</span>
                                <span class="font-semibold tabular-nums text-slate-700 dark:text-zinc-200">{{ selectedFabricIds.length }} selected · {{ selectedFabricWeight.toFixed(2) }} / {{ selectedBatch?.capacity ?? 0 }} kg</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                                <div class="h-full rounded-full transition-all duration-300" :class="exceedsBatchCapacity ? 'bg-red-500' : 'bg-indigo-500'" :style="{ width: capacityPercent + '%' }" />
                            </div>

                            <div class="space-y-2">
                                <label v-for="fabric in groupedCandidates" :key="fabric.id"
                                    class="flex cursor-pointer items-center justify-between rounded-xl border p-3 text-sm transition"
                                    :class="selectedFabricIds.some(id => Number(id) === Number(fabric.id))
                                        ? 'border-indigo-400 bg-indigo-50/60 dark:border-indigo-500/50 dark:bg-indigo-500/10'
                                        : 'border-slate-200 hover:border-slate-300 dark:border-zinc-700 dark:hover:border-zinc-600'">
                                    <span class="flex items-center gap-3">
                                        <input v-model="selectedFabricIds" type="checkbox" :value="fabric.id" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="font-mono font-semibold text-slate-800 dark:text-zinc-100">{{ fabric.code }}</span>
                                    </span>
                                    <span class="tabular-nums text-slate-600 dark:text-zinc-300">{{ fmtNum(fabric.weight) }} kg</span>
                                </label>
                                <p v-if="form.errors.fabric_ids" class="text-xs text-red-600">{{ form.errors.fabric_ids }}</p>
                                <p v-if="exceedsBatchCapacity" class="flex items-center gap-1.5 text-xs font-semibold text-red-600"><AlertTriangle class="h-3.5 w-3.5" /> Selected fabrics exceed this batch's {{ fmtNum(selectedBatch.capacity) }} kg capacity.</p>
                            </div>

                            <p v-if="!steamReady" class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                                <Info class="mt-0.5 h-4 w-4 flex-shrink-0" />
                                Boiler steam is not available. The Boiler Operator must record an operating boiler and mark steam available before steam powered dyeing can start.
                            </p>
                        </section>

                        <!-- Step 2 · Dye stuffs -->
                        <section class="space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">2</span>
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Dye stuffs from this JO recipe</h4>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">Quantities are calculated from the selected fabric codes. Matching inventory lots are consumed automatically.</p>

                            <p v-if="!hasAutoDyeFormula" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-medium text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">This JO has no usable dye-stuff formula. Ask the supervisor or dyeing lab chemist to complete the recipe.</p>

                            <div v-for="dye in dyeUsageForEntry" :key="dye.materialId"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800 dark:text-white">{{ dye.name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">{{ fmtNum(dye.percentage) }}% OWF · {{ fmtNum(dye.available) }} kg available</p>
                                </div>
                                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-zinc-300">Required
                                    <input :value="dye.quantity" @input="manualDyeQuantities[dye.materialId] = $event.target.value === '' ? '' : Number($event.target.value)" type="number" min="0.01" step="0.01"
                                        class="w-28 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-right text-sm font-bold tabular-nums text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                                    <span class="text-slate-400">kg</span>
                                </label>
                            </div>

                            <p v-if="selectedFabricIds.length === 0 && hasAutoDyeFormula" class="text-xs text-slate-500 dark:text-zinc-400">Select fabric codes above to calculate dye quantities.</p>
                            <p v-if="hasAutoDyeFormula && !hasValidDyeAmounts" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-medium text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">Enter a dye amount of at least 0.01 kg for each listed dye.</p>
                            <p v-else-if="hasValidDyeAmounts && !hasEnoughDyeStock" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">Available dye inventory does not cover these amounts. Reduce an amount or ask the supervisor to check stock.</p>
                        </section>

                        <!-- Step 3 · Remarks -->
                        <section class="space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">3</span>
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Remarks <span class="font-normal text-slate-400">(optional)</span></h4>
                            </div>
                            <textarea v-model="form.remarks" rows="3"
                                placeholder="Any issues, color observations, or special notes…"
                                class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"></textarea>
                        </section>
                    </div>

                    <!-- Footer -->
                    <div class="space-y-3 border-t border-slate-100 bg-slate-50/70 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-800/40">
                        <div class="space-y-1 text-xs">
                            <p v-if="!selectedFabricIds.length" class="flex items-center gap-1.5 text-amber-600"><AlertTriangle class="h-3.5 w-3.5" /> Select at least one fabric code.</p>
                            <p v-if="!form.machine_id" class="flex items-center gap-1.5 text-amber-600"><AlertTriangle class="h-3.5 w-3.5" /> Select a machine.</p>
                            <p v-if="!hasAutoDyeFormula" class="flex items-center gap-1.5 text-amber-600"><AlertTriangle class="h-3.5 w-3.5" /> A dye-stuff formula is required for this JO.</p>
                            <p v-if="hasValidDyeAmounts && !hasEnoughDyeStock" class="flex items-center gap-1.5 text-red-600"><AlertTriangle class="h-3.5 w-3.5" /> Not enough matching dye stock for the selected fabric codes.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="closeDyeModal"
                                class="flex-1 rounded-xl border border-slate-200 bg-white py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 active:scale-[0.98] dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                Cancel
                            </button>
                            <button v-if="canEditProduction" type="button" @click="submitDye" :disabled="!canSubmit"
                                class="flex-1 rounded-xl py-2.5 text-sm font-semibold transition active:scale-[0.98] disabled:cursor-not-allowed"
                                :class="canSubmit ? 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-700' : 'bg-slate-200 text-slate-400 dark:bg-zinc-800 dark:text-zinc-500'">
                                {{ form.processing ? 'Recording…' : 'Submit Dye Job' }}
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>

    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes pop { 0% { transform: scale(0.96); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-pop { animation: pop 0.3s cubic-bezier(0.22,1,0.36,1) both; }
.modal-enter-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-leave-active { transition: opacity 0.15s ease; }
details > summary::-webkit-details-marker { display: none; }
</style>