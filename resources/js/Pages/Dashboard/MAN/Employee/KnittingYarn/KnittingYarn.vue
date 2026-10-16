<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));
import {
    Scissors, Plus, Trash2, ClipboardList, AlertTriangle,
    CheckCircle2, X, Package, Layers, Palette, Blend,
    CheckCheck, RotateCcw, Clock, Link2, Sparkles, ChevronRight,
} from 'lucide-vue-next';

const props = defineProps({
    machines:         Array,
    yarns:            Array,
    jobOrders:        Array,
    availableFabrics: Array,   // pending, unlinked fabrics — shown in fabric-link modal
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
const dismissFlash = () => { showFlash.value = false; };

// ─── Job Order Tab Filter ─────────────────────────────────────────────────────
const activeTab = ref('all');
const filteredOrders = computed(() => {
    if (activeTab.value === 'pending') return props.jobOrders.filter(o => !o.is_knitting_done);
    if (activeTab.value === 'done')    return props.jobOrders.filter(o =>  o.is_knitting_done);
    return props.jobOrders;
});
const pendingCount = computed(() => props.jobOrders.filter(o => !o.is_knitting_done).length);
const doneCount    = computed(() => props.jobOrders.filter(o =>  o.is_knitting_done).length);
const progress     = computed(() =>
    props.jobOrders.length ? Math.round((doneCount.value / props.jobOrders.length) * 100) : 0
);

// ─── Helpers ──────────────────────────────────────────────────────────────────
const getYarnById = (id) => props.yarns.find(y => y.id == id) ?? null;
const fmtNum      = (n)  => parseFloat(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ─── Fabric-Link Modal (Mark Done) ────────────────────────────────────────────
// Replaces the old simple confirm for the "done" action.
// The operator picks which pending fabrics belong to this JO, then submits.
const fabricLinkModal    = ref(false);
const fabricLinkTarget   = ref(null);    // the JO being marked done
const selectedFabricIds  = ref([]);      // checkbox selections
const fabricLinkProcessing = ref(false);

const openFabricLink = (order) => {
    fabricLinkTarget.value  = order;
    selectedFabricIds.value = [];
    fabricLinkModal.value   = true;
};
const closeFabricLink = () => {
    fabricLinkModal.value  = false;
    fabricLinkTarget.value = null;
    selectedFabricIds.value = [];
};

const toggleFabricSelection = (id) => {
    const idx = selectedFabricIds.value.indexOf(id);
    if (idx === -1) selectedFabricIds.value.push(id);
    else            selectedFabricIds.value.splice(idx, 1);
};

const isFabricSelected = (id) => selectedFabricIds.value.includes(id);

const confirmFabricLink = () => {
    if (!fabricLinkTarget.value || fabricLinkProcessing.value) return;
    if (selectedFabricIds.value.length === 0) return;
    fabricLinkProcessing.value = true;

    router.post(
        route('man.staff.knitting-yarn.mark-done', fabricLinkTarget.value.id),
        { fabric_ids: selectedFabricIds.value },
        {
            preserveScroll: true,
            onFinish: () => { fabricLinkProcessing.value = false; closeFabricLink(); },
        }
    );
};

// ─── Undo Confirm Modal ───────────────────────────────────────────────────────
const undoModal      = ref(false);
const undoTarget     = ref(null);
const undoProcessing = ref(false);

const openUndo  = (order) => { undoTarget.value = order; undoModal.value = true; };
const closeUndo = () => { undoModal.value = false; undoTarget.value = null; };

const confirmUndo = () => {
    if (!undoTarget.value || undoProcessing.value) return;
    undoProcessing.value = true;
    router.post(route('man.staff.knitting-yarn.unmark-done', undoTarget.value.id), {}, {
        preserveScroll: true,
        onFinish: () => { undoProcessing.value = false; closeUndo(); },
    });
};

// ─── Recipe Modal ─────────────────────────────────────────────────────────────
const recipeModal           = ref(false);
const selectedRecipeOrderId = ref(null);
const selectedRecipeOrder   = computed(() =>
    selectedRecipeOrderId.value !== null
        ? props.jobOrders.find(o => o.id === selectedRecipeOrderId.value) ?? null
        : null
);
const openRecipe  = (order) => { selectedRecipeOrderId.value = order.id; recipeModal.value = true; };
const closeRecipe = () => { recipeModal.value = false; selectedRecipeOrderId.value = null; };

// ─── Main Yarn ────────────────────────────────────────────────────────────────
const selectedMainYarnId = ref('');
const mainRollsUsed      = ref('');
const selectedMainYarn   = computed(() => getYarnById(selectedMainYarnId.value));

watch(selectedMainYarnId, () => {
    mainRollsUsed.value = '';
    form.yarn_type = selectedMainYarn.value?.material_name ?? '';
    recalcWeight();
});

// ─── Additional Yarns ─────────────────────────────────────────────────────────
const additionalYarnItems = ref([]);

const addYarnRow = () => additionalYarnItems.value.push({ inventory_item_id: '', units_used: '' });
const removeYarnRow = (index) => {
    additionalYarnItems.value.splice(index, 1);
    recalcWeight();
};
const clampAdditionalRow = (row) => {
    const yarn = getYarnById(row.inventory_item_id);
    if (!yarn) return;
    if (row.units_used < 1)                   row.units_used = 1;
    if (row.units_used > yarn.available_units) row.units_used = yarn.available_units;
    recalcWeight();
};
const onAdditionalYarnChange = (row) => { row.units_used = ''; recalcWeight(); };

// ─── Weight Computation ───────────────────────────────────────────────────────
const weightIsPartial = ref(false);

const recalcWeight = () => {
    let total = 0;
    let allHaveUnitWeight = true;

    const mainYarn  = selectedMainYarn.value;
    const mainRolls = parseInt(mainRollsUsed.value);
    if (mainYarn && mainRolls >= 1) {
        if (mainYarn.unit_weight) total += parseFloat(mainYarn.unit_weight) * mainRolls;
        else allHaveUnitWeight = false;
    }

    for (const row of additionalYarnItems.value) {
        const yarn  = getYarnById(row.inventory_item_id);
        const rolls = parseInt(row.units_used);
        if (yarn && rolls >= 1) {
            if (yarn.unit_weight) total += parseFloat(yarn.unit_weight) * rolls;
            else allHaveUnitWeight = false;
        }
    }

    const hasEntry = (mainYarn && mainRolls >= 1) ||
        additionalYarnItems.value.some(r => getYarnById(r.inventory_item_id) && parseInt(r.units_used) >= 1);

    form.weight = hasEntry ? total.toFixed(2) : '';
    weightIsPartial.value = hasEntry && !allHaveUnitWeight;
};

const weightBreakdown = computed(() => {
    const parts = [];
    const mainYarn = selectedMainYarn.value;
    const mainRolls = parseInt(mainRollsUsed.value);
    if (mainYarn && mainRolls >= 1 && mainYarn.unit_weight)
        parts.push(`${mainYarn.material_name}: ${mainRolls} × ${mainYarn.unit_weight} kg = ${(parseFloat(mainYarn.unit_weight) * mainRolls).toFixed(2)} kg`);
    for (const row of additionalYarnItems.value) {
        const yarn = getYarnById(row.inventory_item_id);
        const rolls = parseInt(row.units_used);
        if (yarn && rolls >= 1 && yarn.unit_weight)
            parts.push(`${yarn.material_name}: ${rolls} × ${yarn.unit_weight} kg = ${(parseFloat(yarn.unit_weight) * rolls).toFixed(2)} kg`);
    }
    return parts;
});

watch(mainRollsUsed, recalcWeight);
watch(additionalYarnItems, recalcWeight, { deep: true });

// ─── Form ─────────────────────────────────────────────────────────────────────
const form = useForm({ machine_id: '', yarn_type: '', weight: '', remarks: '', yarns_used: [] });

const mainYarnExceedsAvailable = computed(() =>
    selectedMainYarn.value && mainRollsUsed.value
        ? parseInt(mainRollsUsed.value) > selectedMainYarn.value.available_units
        : false
);
const additionalRowErrors = computed(() =>
    additionalYarnItems.value.map(row => {
        if (!row.inventory_item_id || !row.units_used) return null;
        const yarn = getYarnById(row.inventory_item_id);
        return yarn && parseInt(row.units_used) > yarn.available_units
            ? `Only ${yarn.available_units} rolls available` : null;
    })
);
const hasInvalidAdditionalUnits = computed(() => additionalRowErrors.value.some(e => e !== null));
const canSubmit = computed(() =>
    form.machine_id && selectedMainYarnId.value && parseInt(mainRollsUsed.value) >= 1 &&
    !mainYarnExceedsAvailable.value && !hasInvalidAdditionalUnits.value &&
    parseFloat(form.weight) > 0 && !form.processing
);

const submitFabric = () => {
    form.yarns_used = [
        { inventory_item_id: parseInt(selectedMainYarnId.value), units_used: parseInt(mainRollsUsed.value) },
        ...additionalYarnItems.value
            .filter(item => item.inventory_item_id && parseInt(item.units_used) >= 1)
            .map(item => ({ inventory_item_id: parseInt(item.inventory_item_id), units_used: parseInt(item.units_used) })),
    ];
    form.post(route('man.staff.knitting-yarn.store-fabric'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedMainYarnId.value = ''; mainRollsUsed.value = '';
            additionalYarnItems.value = []; weightIsPartial.value = false;
        },
    });
};
</script>

<template>
    <AuthenticatedLayout title="Knitting Yarn">
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Scissors class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Knitting Yarn
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Knitting Yarn Workspace</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ doneCount }} of {{ jobOrders.length }} orders done · {{ progress }}% complete</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ pendingCount }} pending
                            </span>
                            <Link :href="route('man.staff.knitting-yarn.reports')"
                                class="rounded-full bg-white px-4 py-1.5 text-xs font-black text-indigo-700 shadow-lg hover:scale-105 transition-all duration-200 active:scale-95 flex items-center gap-1.5">
                                <ClipboardList class="h-3.5 w-3.5" /> Machine Reports
                            </Link>
                        </div>
                    </div>

                    <!-- Progress + tabs -->
                    <div class="relative mt-6 flex flex-col gap-3">
                        <div v-if="jobOrders.length" class="flex items-center gap-3">
                            <div class="flex-1 h-2 bg-white/20 rounded-full overflow-hidden">
                                <div class="h-full bg-white rounded-full transition-all duration-500" :style="`width: ${progress}%`"></div>
                            </div>
                            <span class="text-xs font-black">{{ progress }}%</span>
                        </div>
                        <div v-if="jobOrders.length" class="flex gap-2">
                            <button v-for="tab in [
                                { key: 'all', label: 'All', count: jobOrders.length },
                                { key: 'pending', label: 'Pending', count: pendingCount },
                                { key: 'done', label: 'Done', count: doneCount },
                            ]" :key="tab.key" @click="activeTab = tab.key" type="button"
                                :class="activeTab === tab.key ? 'bg-white text-indigo-700 shadow-lg scale-105' : 'bg-white/15 text-white hover:bg-white/25'"
                                class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide backdrop-blur transition-all duration-200 active:scale-95">
                                {{ tab.label }} · {{ tab.count }}
                            </button>
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
                        <button @click="dismissFlash"><X class="w-4 h-4 text-gray-400 hover:text-gray-600" /></button>
                    </div>
                </transition>

                <!-- ══ PRODUCTION TRACKER ══ -->
                <section>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-5 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-full"></div>
                        <h2 class="text-base font-black text-gray-800 dark:text-gray-100 tracking-tight">Production Job Orders</h2>
                    </div>

                    <!-- Job Order Cards -->
                    <TransitionGroup v-if="filteredOrders.length" name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div v-for="(order, i) in filteredOrders" :key="order.id"
                             :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                             class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                             :class="order.is_knitting_done ? 'border-emerald-200 dark:border-emerald-800 opacity-90' : 'border-gray-100 dark:border-zinc-800 hover:border-indigo-200 dark:hover:border-indigo-800'">
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />

                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-12 w-12 rounded-2xl bg-gradient-to-br flex items-center justify-center text-white text-lg font-black shadow-lg uppercase flex-shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300"
                                         :class="order.is_knitting_done ? 'from-emerald-500 via-teal-600 to-cyan-700' : 'from-blue-600 via-indigo-600 to-violet-700'">
                                        {{ (order.jo_number ?? '?').charAt(0) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-mono text-sm font-black tracking-tight truncate"
                                           :class="order.is_knitting_done ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-900 dark:text-white group-hover:text-indigo-700 dark:group-hover:text-indigo-300'">
                                            {{ order.jo_number }}
                                        </p>
                                        <p class="text-[10px] text-gray-400 font-mono truncate">{{ order.control_number }}</p>
                                    </div>
                                </div>
                                <div v-if="order.is_knitting_done"
                                     class="flex items-center gap-1 bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30 text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex-shrink-0 ml-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Done
                                </div>
                                <div v-else class="flex items-center gap-1 bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30 text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex-shrink-0 ml-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Pending
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs mb-3">
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Color</p>
                                    <p class="font-black text-gray-800 dark:text-gray-100 truncate">{{ order.color }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Quantity</p>
                                    <p class="font-black text-gray-800 dark:text-gray-100">{{ fmtNum(order.quantity) }} kg</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Yarn Type</p>
                                    <p class="font-black text-gray-800 dark:text-gray-100 truncate">{{ order.yarn_type }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Design</p>
                                    <p class="font-black text-gray-800 dark:text-gray-100 truncate">{{ order.design }}</p>
                                </div>
                            </div>

                            <div v-if="order.is_knitting_done && order.knitting_done_at"
                                 class="flex items-center gap-1.5 text-[10px] text-emerald-600 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-500/10 rounded-2xl px-2.5 py-1.5 mb-2">
                                <CheckCircle2 class="w-3 h-3 flex-shrink-0" />
                                Completed {{ order.knitting_done_at }}
                            </div>

                            <div v-if="order.linked_fabrics?.length"
                                 class="flex items-center gap-1.5 text-[10px] text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-500/10 rounded-2xl px-2.5 py-1.5 mb-2">
                                <Link2 class="w-3 h-3 flex-shrink-0" />
                                {{ order.linked_fabrics.length }} fabric{{ order.linked_fabrics.length !== 1 ? 's' : '' }} linked
                                <span class="ml-1 text-indigo-400 truncate">({{ order.linked_fabrics.map(f => f.code).join(', ') }})</span>
                            </div>

                            <div v-if="order.recipe" class="bg-blue-50/70 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-500/20 rounded-2xl px-3 py-2 mb-2">
                                <p class="text-[10px] text-blue-400 font-black uppercase tracking-wide">Recipe</p>
                                <p class="text-xs font-bold text-blue-800 dark:text-blue-200 mt-0.5 truncate">{{ order.recipe.yarn_type }}</p>
                                <p class="text-[10px] text-blue-500 mt-0.5 truncate">
                                    Dye: {{ order.recipe.dye_color }} · Weave: {{ order.recipe.weave_design }}
                                </p>
                            </div>

                            <div class="mt-auto flex gap-2 pt-3 border-t border-gray-100 dark:border-zinc-800">
                                <button v-if="order.recipe" @click="openRecipe(order)" type="button"
                                    class="flex-1 flex items-center justify-center gap-1.5 border border-blue-200 dark:border-blue-800 text-blue-600 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-500/10 text-xs font-bold px-3 py-2.5 rounded-2xl transition active:scale-95">
                                    <Layers class="w-3.5 h-3.5" /> Full Recipe
                                </button>
                                <button v-if="!order.is_knitting_done && canEditProduction"
                                    @click="openFabricLink(order)" type="button"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-gray-900 hover:bg-gray-700 text-white text-xs font-bold px-3 py-2.5 rounded-2xl transition active:scale-95">
                                    <CheckCheck class="w-3.5 h-3.5" /> Mark Done
                                </button>
                                <button v-else-if="canEditProduction" @click="openUndo(order)" type="button"
                                    class="flex-1 flex items-center justify-center gap-1.5 border border-gray-200 dark:border-zinc-700 text-gray-500 dark:text-gray-300 hover:text-gray-700 text-xs font-bold px-3 py-2.5 rounded-2xl transition active:scale-95">
                                    <RotateCcw class="w-3.5 h-3.5" /> Undo
                                </button>
                            </div>
                            <ChevronRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                        </div>
                    </TransitionGroup>

                    <div v-else class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 px-6 py-10 text-center">
                        <component :is="activeTab === 'done' ? CheckCheck : ClipboardList" class="w-10 h-10 text-indigo-300 mx-auto mb-3 animate-bounce-soft" />
                        <p class="text-sm font-bold text-gray-500 dark:text-gray-300">
                            {{ activeTab === 'done' ? 'No job orders marked as done yet.'
                               : activeTab === 'pending' ? 'All job orders are done — great work!'
                               : 'No active job orders at the moment.' }}
                        </p>
                    </div>
                </section>

                <!-- ══ RECORD FABRIC FORM ══ -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2">
                        <div class="bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                            <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 sm:px-6 py-5 text-white">
                                <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                                <div class="relative flex items-center gap-3">
                                    <div class="w-11 h-11 bg-white/15 ring-1 ring-white/30 rounded-2xl flex items-center justify-center flex-shrink-0 animate-pop">
                                        <Scissors class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-black tracking-tight">Record New Fabric</h2>
                                        <p class="text-xs text-blue-100/90">Enter details from the knitting machine output</p>
                                    </div>
                                </div>
                            </div>

                            <form @submit.prevent="submitFabric" class="px-5 sm:px-6 py-5 space-y-5">
                                <div v-if="Object.keys(form.errors).length"
                                     class="flex items-start gap-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-2xl px-4 py-3">
                                    <AlertTriangle class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                                    <div class="text-sm text-red-700 dark:text-red-300 space-y-0.5">
                                        <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-black text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                        Machine No. <span class="text-red-500">*</span>
                                    </label>
                                    <select v-model="form.machine_id" required
                                        class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                        <option value="">— Select Machine —</option>
                                        <option v-for="machine in machines" :key="machine.id" :value="machine.id">{{ machine.machine_no }}</option>
                                    </select>
                                </div>

                                <div class="border border-gray-200 dark:border-zinc-700 rounded-3xl overflow-hidden">
                                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50/70 dark:bg-zinc-800/60 border-b border-gray-100 dark:border-zinc-700">
                                        <div>
                                            <p class="text-xs font-black text-gray-700 dark:text-gray-200 uppercase tracking-wide">Yarns Consumed</p>
                                            <p class="text-xs text-gray-400 mt-0.5">Each lot is deducted from inventory on save</p>
                                        </div>
                                        <button type="button" @click="addYarnRow"
                                            class="flex items-center gap-1.5 bg-gray-900 hover:bg-gray-700 text-white text-xs font-bold px-3 py-2 rounded-2xl transition active:scale-95">
                                            <Plus class="w-3.5 h-3.5" /> Add Lot
                                        </button>
                                    </div>

                                    <div class="p-4 space-y-4">
                                        <div class="rounded-2xl border border-indigo-100 dark:border-indigo-800 bg-indigo-50/40 dark:bg-indigo-900/20 p-4 space-y-3">
                                            <div class="flex items-center gap-2">
                                                <span class="w-5 h-5 bg-indigo-600 text-white rounded-full text-[10px] font-black flex items-center justify-center">1</span>
                                                <p class="text-xs font-black text-indigo-700 dark:text-indigo-300 uppercase tracking-wide">Primary Yarn</p>
                                            </div>
                                            <select v-model="selectedMainYarnId" required
                                                class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                                <option value="">— Select Yarn Lot —</option>
                                                <option v-for="yarn in yarns" :key="yarn.id" :value="yarn.id">
                                                    {{ yarn.material_name }} ({{ yarn.control_number }}) — {{ yarn.available_units }} rolls left
                                                </option>
                                            </select>
                                            <div v-if="selectedMainYarn" class="flex flex-wrap gap-2">
                                                <span class="inline-flex items-center gap-1.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-xs text-gray-600 dark:text-gray-300 px-2.5 py-1 rounded-lg">
                                                    <Package class="w-3 h-3 text-gray-400" /> {{ selectedMainYarn.control_number }}
                                                </span>
                                                <span class="inline-flex items-center gap-1.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-xs text-gray-600 dark:text-gray-300 px-2.5 py-1 rounded-lg">
                                                    🧶 {{ selectedMainYarn.available_units }} rolls left
                                                </span>
                                                <span v-if="selectedMainYarn.unit_weight" class="inline-flex items-center gap-1.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-xs text-gray-600 dark:text-gray-300 px-2.5 py-1 rounded-lg">
                                                    ⚖️ {{ selectedMainYarn.unit_weight }} kg / roll
                                                </span>
                                            </div>
                                            <div v-if="selectedMainYarn">
                                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">
                                                    Rolls Used <span class="text-red-500">*</span>
                                                    <span class="text-gray-400 font-normal normal-case ml-1">(max {{ selectedMainYarn.available_units }})</span>
                                                </label>
                                                <input v-model.number="mainRollsUsed" type="number" min="1"
                                                    :max="selectedMainYarn.available_units" required
                                                    class="w-full border rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                                                    :class="mainYarnExceedsAvailable ? 'border-red-300' : 'border-gray-200 dark:border-zinc-700'"
                                                    placeholder="e.g. 30" />
                                                <p v-if="mainYarnExceedsAvailable" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                                    <AlertTriangle class="w-3 h-3" /> Only {{ selectedMainYarn.available_units }} rolls available.
                                                </p>
                                                <p v-else-if="parseInt(mainRollsUsed) >= 1" class="text-xs text-emerald-600 mt-1">
                                                    ✓ {{ selectedMainYarn.available_units - parseInt(mainRollsUsed) }} rolls will remain.
                                                </p>
                                            </div>
                                        </div>

                                        <div v-for="(row, index) in additionalYarnItems" :key="index"
                                             class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-gray-50/40 dark:bg-zinc-800/40 p-4 space-y-3">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-5 h-5 bg-gray-700 text-white rounded-full text-[10px] font-black flex items-center justify-center">{{ index + 2 }}</span>
                                                    <p class="text-xs font-black text-gray-600 dark:text-gray-300 uppercase tracking-wide">Additional Yarn</p>
                                                </div>
                                                <button type="button" @click="removeYarnRow(index)"
                                                    class="flex items-center gap-1 text-red-500 hover:text-red-700 text-xs font-bold">
                                                    <Trash2 class="w-3.5 h-3.5" /> Remove
                                                </button>
                                            </div>
                                            <select v-model="row.inventory_item_id" @change="onAdditionalYarnChange(row)"
                                                class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                                <option value="">— Select Yarn Lot —</option>
                                                <option v-for="yarn in yarns" :key="yarn.id" :value="yarn.id">
                                                    {{ yarn.material_name }} ({{ yarn.control_number }}) — {{ yarn.available_units }} rolls left
                                                </option>
                                            </select>
                                            <div v-if="row.inventory_item_id && getYarnById(row.inventory_item_id)" class="flex flex-wrap gap-2">
                                                <span class="inline-flex items-center gap-1.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-xs text-gray-600 dark:text-gray-300 px-2.5 py-1 rounded-lg">
                                                    🧶 {{ getYarnById(row.inventory_item_id).available_units }} rolls left
                                                </span>
                                                <span v-if="getYarnById(row.inventory_item_id).unit_weight" class="inline-flex items-center gap-1.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-xs text-gray-600 dark:text-gray-300 px-2.5 py-1 rounded-lg">
                                                    ⚖️ {{ getYarnById(row.inventory_item_id).unit_weight }} kg / roll
                                                </span>
                                            </div>
                                            <div v-if="row.inventory_item_id">
                                                <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">
                                                    Rolls Used <span class="text-red-500">*</span>
                                                </label>
                                                <input v-model.number="row.units_used" type="number" min="1"
                                                    :max="getYarnById(row.inventory_item_id)?.available_units || 1"
                                                    @input="clampAdditionalRow(row)"
                                                    class="w-full border rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                                                    :class="additionalRowErrors[index] ? 'border-red-300' : 'border-gray-200 dark:border-zinc-700'"
                                                    placeholder="e.g. 10" />
                                                <p v-if="additionalRowErrors[index]" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                                    <AlertTriangle class="w-3 h-3" /> {{ additionalRowErrors[index] }}
                                                </p>
                                                <p v-else-if="parseInt(row.units_used) >= 1" class="text-xs text-emerald-600 mt-1">
                                                    ✓ {{ getYarnById(row.inventory_item_id).available_units - parseInt(row.units_used) }} rolls will remain.
                                                </p>
                                            </div>
                                        </div>

                                        <p v-if="additionalYarnItems.length === 0" class="text-xs text-gray-400 italic px-1">
                                            Click "Add Lot" if more than one yarn lot was used for this fabric.
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-black text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                        Total Fabric Weight (kg) <span class="text-red-500">*</span>
                                        <span v-if="weightBreakdown.length" class="text-indigo-500 font-normal normal-case ml-1">(auto-calculated)</span>
                                    </label>
                                    <input type="number" step="0.01" v-model="form.weight" required
                                        class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                                        placeholder="0.00" />
                                    <div v-if="weightBreakdown.length" class="mt-2 text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl px-3 py-2 space-y-0.5">
                                        <p v-for="(line, i) in weightBreakdown" :key="i" class="pl-1">+ {{ line }}</p>
                                        <p class="pl-1 font-black text-gray-700 dark:text-gray-200 border-t border-gray-200 dark:border-zinc-700 pt-1 mt-1">= {{ form.weight }} kg total</p>
                                    </div>
                                    <p v-if="weightIsPartial" class="text-xs text-amber-600 mt-1.5 flex items-center gap-1">
                                        <AlertTriangle class="w-3 h-3" /> Some yarns have no unit weight — verify total manually.
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-xs font-black text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                        Remarks <span class="text-gray-400 font-normal normal-case">(optional)</span>
                                    </label>
                                    <textarea v-model="form.remarks" rows="3" placeholder="Any issues, special notes, or observations…"
                                        class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"></textarea>
                                </div>

                                <div class="pt-1 space-y-2">
                                    <button v-if="canEditProduction" type="submit" :disabled="!canSubmit || !canEditProduction"
                                        class="w-full py-3 rounded-2xl text-sm font-bold transition active:scale-95"
                                        :class="canSubmit ? 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white shadow-lg shadow-indigo-500/20' : 'bg-gray-100 dark:bg-zinc-800 text-gray-400 cursor-not-allowed'">
                                        {{ form.processing ? 'Recording…' : 'Record Fabric' }}
                                    </button>
                                    <div class="space-y-1 text-xs">
                                        <p v-if="!selectedMainYarnId && form.machine_id" class="text-amber-600 flex items-center gap-1">
                                            <AlertTriangle class="w-3 h-3" /> Select a primary yarn lot.
                                        </p>
                                        <p v-if="selectedMainYarnId && parseInt(mainRollsUsed) < 1" class="text-amber-600 flex items-center gap-1">
                                            <AlertTriangle class="w-3 h-3" /> Enter rolls used.
                                        </p>
                                        <p v-if="mainYarnExceedsAvailable" class="text-red-600 flex items-center gap-1">
                                            <AlertTriangle class="w-3 h-3" /> Rolls exceed available inventory.
                                        </p>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1 space-y-4">
                        <div class="bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2">
                                <Scissors class="w-4 h-4 text-indigo-500" />
                                <p class="text-sm font-black text-gray-800 dark:text-gray-100">Knitting Guide</p>
                            </div>
                            <div class="px-4 py-4">
                                <ol class="space-y-3">
                                    <li v-for="(step, i) in [
                                        'Select the machine currently in use',
                                        'Choose primary yarn lot and enter rolls consumed',
                                        'Click Add Lot for each additional lot used',
                                        'Total weight auto-calculates (rolls × unit weight)',
                                        'All lots are deducted from inventory on save',
                                        'Click Mark Done on a JO to link fabrics and complete the order',
                                    ]" :key="i" class="flex items-start gap-2.5">
                                        <span class="w-5 h-5 bg-gradient-to-br from-blue-600 to-violet-600 text-white rounded-full text-[10px] font-black flex items-center justify-center flex-shrink-0 mt-0.5">{{ i + 1 }}</span>
                                        <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">{{ step }}</p>
                                    </li>
                                </ol>
                            </div>
                        </div>
                        <div v-if="yarns.length" class="bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Package class="w-4 h-4 text-amber-500" />
                                    <p class="text-sm font-black text-gray-800 dark:text-gray-100">Yarn Inventory</p>
                                </div>
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">{{ yarns.length }} lots</span>
                            </div>
                            <div class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <div v-for="yarn in yarns" :key="yarn.id" class="px-4 py-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-bold text-gray-800 dark:text-gray-100 truncate">{{ yarn.material_name }}</p>
                                            <p class="text-[10px] text-gray-400 font-mono mt-0.5">{{ yarn.control_number }}</p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <p class="text-xs font-black text-indigo-600 dark:text-indigo-300">{{ yarn.available_units }} rolls</p>
                                            <p class="text-[10px] text-gray-400">{{ yarn.remaining_quantity }} {{ yarn.unit }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-1.5 h-1.5 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-indigo-500 to-fuchsia-500 rounded-full"
                                             :style="`width: ${Math.min(100, (yarn.available_units / yarn.total_units) * 100)}%`"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ═══════════ FABRIC-LINK MODAL ═══════════ -->
        <Transition name="modal">
            <div v-if="fabricLinkModal && fabricLinkTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeFabricLink" />

                <div class="relative w-full max-w-lg bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800 animate-pop max-h-[90vh] flex flex-col">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 py-5 text-white">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="text-xs text-blue-100 font-mono font-medium">{{ fabricLinkTarget.jo_number }}</p>
                                <h3 class="text-lg font-black tracking-tight mt-0.5">Link Fabrics & Mark Done</h3>
                                <p class="text-xs text-blue-100/90 mt-0.5">Select the fabric(s) produced for this job order</p>
                            </div>
                            <button @click="closeFabricLink" type="button"
                                class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition flex-shrink-0">
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="px-5 py-3 bg-gray-50/70 dark:bg-zinc-800/60 border-b border-gray-100 dark:border-zinc-700 flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-300">
                        <span><strong>Color:</strong> {{ fabricLinkTarget.color }}</span>
                        <span><strong>Qty:</strong> {{ fmtNum(fabricLinkTarget.quantity) }} kg</span>
                        <span><strong>Yarn:</strong> {{ fabricLinkTarget.yarn_type }}</span>
                    </div>

                    <div class="overflow-y-auto flex-1 px-5 py-4">
                        <p class="text-xs font-black text-gray-500 dark:text-gray-300 uppercase tracking-wide mb-3">
                            Available Fabrics
                            <span class="ml-1 bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 px-1.5 py-0.5 rounded-md font-bold">
                                {{ availableFabrics.length }}
                            </span>
                        </p>

                        <div v-if="availableFabrics.length === 0" class="text-center py-10 text-sm text-gray-400">
                            <Scissors class="w-10 h-10 text-indigo-200 mx-auto mb-2 animate-bounce-soft" />
                            No pending fabrics available. Record a fabric first.
                        </div>

                        <div v-else class="space-y-2">
                            <label v-for="fabric in availableFabrics" :key="fabric.id"
                                   class="flex items-start gap-3 p-3 rounded-2xl border cursor-pointer transition"
                                   :class="isFabricSelected(fabric.id)
                                       ? 'border-indigo-400 dark:border-indigo-600 bg-indigo-50/60 dark:bg-indigo-900/20'
                                       : 'border-gray-200 dark:border-zinc-700 hover:border-indigo-200 dark:hover:border-indigo-700 hover:bg-gray-50 dark:hover:bg-zinc-800/60'">
                                <input type="checkbox" :value="fabric.id"
                                    :checked="isFabricSelected(fabric.id)"
                                    @change="toggleFabricSelection(fabric.id)"
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-black text-gray-900 dark:text-white font-mono">{{ fabric.code }}</p>
                                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-300 flex-shrink-0">{{ fabric.weight }} kg</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ fabric.yarn_type }} · {{ fabric.machine_no }} · {{ fabric.shift }}</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ fabric.processed_at }}</p>
                                    <p v-if="fabric.remarks" class="text-[10px] text-gray-400 italic mt-0.5 truncate">{{ fabric.remarks }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t border-gray-100 dark:border-zinc-800 bg-gray-50/60 dark:bg-zinc-800/40 space-y-3">
                        <p v-if="selectedFabricIds.length" class="text-xs text-indigo-600 dark:text-indigo-300 font-bold text-center">
                            {{ selectedFabricIds.length }} fabric{{ selectedFabricIds.length !== 1 ? 's' : '' }} selected
                        </p>
                        <div class="flex gap-2">
                            <button @click="closeFabricLink" type="button"
                                class="flex-1 py-2.5 border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-zinc-800 text-sm font-bold rounded-2xl transition active:scale-95">
                                Cancel
                            </button>
                            <button v-if="canEditProduction" @click="confirmFabricLink" type="button"
                                :disabled="selectedFabricIds.length === 0 || fabricLinkProcessing"
                                class="flex-1 py-2.5 text-sm font-bold rounded-2xl transition disabled:opacity-50 disabled:cursor-not-allowed active:scale-95"
                                :class="selectedFabricIds.length > 0 ? 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white shadow-lg shadow-indigo-500/20' : 'bg-gray-100 dark:bg-zinc-800 text-gray-400'">
                                {{ fabricLinkProcessing ? 'Saving…' : `Confirm & Mark Done` }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ══ UNDO CONFIRM MODAL ══ -->
        <Transition name="modal">
            <div v-if="undoModal && undoTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeUndo" />
                <div class="relative w-full max-w-sm bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800 animate-pop">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 pt-6 pb-5 text-center text-white">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                        <div class="relative flex flex-col items-center">
                            <div class="w-12 h-12 rounded-2xl bg-white/15 ring-1 ring-white/30 flex items-center justify-center mb-3 animate-pop">
                                <RotateCcw class="w-6 h-6" />
                            </div>
                            <h3 class="text-base font-black tracking-tight">Re-open Job Order?</h3>
                            <p class="text-xs text-blue-100/90 mt-1">This removes the done mark and unlinks any pending fabrics.</p>
                        </div>
                    </div>
                    <div class="mx-6 my-4 bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-2xl px-4 py-3">
                        <p class="text-xs text-gray-400 font-medium">Job Order</p>
                        <p class="text-sm font-black text-gray-900 dark:text-white font-mono mt-0.5">{{ undoTarget.jo_number }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ undoTarget.color }} · {{ fmtNum(undoTarget.quantity) }} kg</p>
                    </div>
                    <div class="flex gap-2 px-6 pb-6">
                        <button @click="closeUndo" type="button"
                            class="flex-1 py-2.5 border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-zinc-800 text-sm font-bold rounded-2xl transition active:scale-95">
                            Cancel
                        </button>
                        <button v-if="canEditProduction" @click="confirmUndo" type="button" :disabled="undoProcessing"
                            class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-2xl transition disabled:opacity-60 active:scale-95">
                            {{ undoProcessing ? 'Processing…' : 'Yes, Re-open' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ══ RECIPE MODAL ══ -->
        <Transition name="modal">
            <div v-if="recipeModal && selectedRecipeOrder" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeRecipe" />
                <div class="relative w-full max-w-lg bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800 animate-pop max-h-[90vh] flex flex-col">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 py-5 text-white">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl animate-float" />
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="text-xs text-blue-100 font-mono font-medium">{{ selectedRecipeOrder.jo_number }}</p>
                                <h3 class="text-lg font-black tracking-tight mt-0.5">Production Recipe</h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <div v-if="selectedRecipeOrder.is_knitting_done"
                                     class="flex items-center gap-1 bg-emerald-400/90 text-emerald-950 text-[10px] font-black px-2 py-1 rounded-full">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Done
                                </div>
                                <div v-else class="flex items-center gap-1 bg-amber-300/90 text-amber-950 text-[10px] font-black px-2 py-1 rounded-full">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Pending
                                </div>
                                <button @click="closeRecipe" type="button"
                                    class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition">
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-y-auto flex-1 px-5 py-4 space-y-5">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-gray-50 dark:bg-zinc-800 rounded-2xl px-3 py-2.5">
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Control No.</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-100 font-mono mt-0.5">{{ selectedRecipeOrder.control_number }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-zinc-800 rounded-2xl px-3 py-2.5">
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Color</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-100 mt-0.5">{{ selectedRecipeOrder.color }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-zinc-800 rounded-2xl px-3 py-2.5">
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Quantity</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-100 mt-0.5">{{ fmtNum(selectedRecipeOrder.quantity) }} kg</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-zinc-800 rounded-2xl px-3 py-2.5">
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Design</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-100 mt-0.5">{{ selectedRecipeOrder.design }}</p>
                            </div>
                        </div>
                        <div v-if="selectedRecipeOrder.recipe" class="grid grid-cols-1 gap-2">
                            <div class="flex items-start gap-3 bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-500/20 rounded-2xl px-3 py-2.5">
                                <div class="w-7 h-7 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <Blend class="w-3.5 h-3.5 text-white" />
                                </div>
                                <div>
                                    <p class="text-[10px] text-blue-500 uppercase tracking-wide font-black">Yarn Composition</p>
                                    <p class="text-sm font-bold text-blue-900 dark:text-blue-200 mt-0.5">{{ selectedRecipeOrder.recipe.yarn_type }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-100 dark:border-amber-500/20 rounded-2xl px-3 py-2.5">
                                <div class="w-7 h-7 bg-amber-400 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <Palette class="w-3.5 h-3.5 text-gray-800" />
                                </div>
                                <div>
                                    <p class="text-[10px] text-amber-600 dark:text-amber-300 uppercase tracking-wide font-black">Dye Color Formula</p>
                                    <p class="text-sm font-bold text-amber-900 dark:text-amber-200 mt-0.5">{{ selectedRecipeOrder.recipe.dye_color }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-if="selectedRecipeOrder.is_knitting_done"
                             class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-2xl px-4 py-3">
                            <CheckCheck class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                            <div>
                                <p class="text-xs font-black text-emerald-700 dark:text-emerald-300">Knitting Completed</p>
                                <p class="text-[10px] text-emerald-500 mt-0.5">{{ selectedRecipeOrder.knitting_done_at }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-3 border-t border-gray-100 dark:border-zinc-800 bg-gray-50/60 dark:bg-zinc-800/40 flex gap-2">
                        <button @click="closeRecipe" type="button"
                            class="flex-1 py-2.5 border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-zinc-800 text-sm font-bold rounded-2xl transition active:scale-95">
                            Close
                        </button>
                        <button v-if="!selectedRecipeOrder.is_knitting_done && canEditProduction"
                            @click="() => { openFabricLink(selectedRecipeOrder); closeRecipe(); }" type="button"
                            class="flex-1 py-2.5 bg-gray-900 hover:bg-gray-700 text-white text-sm font-bold rounded-2xl transition flex items-center justify-center gap-1.5 active:scale-95">
                            <CheckCheck class="w-4 h-4" /> Mark Done
                        </button>
                        <button v-else-if="canEditProduction"
                            @click="() => { openUndo(selectedRecipeOrder); closeRecipe(); }" type="button"
                            class="flex-1 py-2.5 border border-amber-200 dark:border-amber-800 text-amber-600 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-500/10 text-sm font-bold rounded-2xl transition flex items-center justify-center gap-1.5 active:scale-95">
                            <RotateCcw class="w-4 h-4" /> Undo Done
                        </button>
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
