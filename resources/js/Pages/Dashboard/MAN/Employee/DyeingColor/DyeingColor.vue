<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));
import {
    Droplet, X, Plus, Trash2, AlertTriangle, CheckCircle2,
    FlaskConical, Palette, Layers, Package, Blend, Sparkles, ChevronRight,
} from 'lucide-vue-next';

const props = defineProps({
    fabrics:  Array,   // status='dyeing', includes linked JO recipe
    machines: Array,
    dyes:     Array,   // ManufacturingInventoryItem where dept=dyeing, category=Dye
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
const getDyeById   = (id) => props.dyes.find(d => d.id == id) ?? null;
const fmtNum       = (n)  => parseFloat(n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ─── Recipe Modal ─────────────────────────────────────────────────────────────
const recipeModal      = ref(false);
const recipeModalFabric = ref(null);
const openRecipeModal  = (fabric) => { recipeModalFabric.value = fabric; recipeModal.value = true; };
const closeRecipeModal = () => { recipeModal.value = false; recipeModalFabric.value = null; };

// ─── Dye Modal ────────────────────────────────────────────────────────────────
const dyeModal      = ref(false);
const currentFabric = ref(null);

// Each dye row: { inventory_item_id, quantity_used }
const dyeRows = ref([]);

const addDyeRow    = () => dyeRows.value.push({ inventory_item_id: '', quantity_used: '' });
const removeDyeRow = (i) => dyeRows.value.splice(i, 1);

const onDyeItemChange = (row) => {
    row.quantity_used = '';
};

const form = useForm({
    fabric_id:  null,
    machine_id: '',
    remarks:    '',
    dyes_used:  [],
});

const openDyeModal = (fabric) => {
    currentFabric.value = fabric;
    form.fabric_id  = fabric.id;
    form.machine_id = '';
    form.remarks    = '';
    dyeRows.value   = [{ inventory_item_id: '', quantity_used: '' }];   // start with one empty row
    dyeModal.value  = true;
};
const closeDyeModal = () => {
    dyeModal.value  = false;
    currentFabric.value = null;
    dyeRows.value   = [];
    form.reset();
};

// ─── Dye Row Validation ───────────────────────────────────────────────────────
const dyeRowErrors = computed(() =>
    dyeRows.value.map(row => {
        if (!row.inventory_item_id || !row.quantity_used) return null;
        const dye = getDyeById(row.inventory_item_id);
        if (!dye) return null;
        return parseFloat(row.quantity_used) > parseFloat(dye.remaining_quantity)
            ? `Only ${fmtNum(dye.remaining_quantity)} ${dye.unit} available`
            : null;
    })
);

const hasInvalidDyeRows = computed(() => dyeRowErrors.value.some(e => e !== null));

const totalChemicalsKg = computed(() => {
    return dyeRows.value.reduce((sum, row) => {
        const q = parseFloat(row.quantity_used);
        return sum + (isNaN(q) ? 0 : q);
    }, 0);
});

const canSubmit = computed(() =>
    form.machine_id &&
    dyeRows.value.length > 0 &&
    dyeRows.value.every(r => r.inventory_item_id && parseFloat(r.quantity_used) > 0) &&
    !hasInvalidDyeRows.value &&
    !form.processing
);

// ─── Submit ───────────────────────────────────────────────────────────────────
const submitDye = () => {
    form.dyes_used = dyeRows.value
        .filter(r => r.inventory_item_id && parseFloat(r.quantity_used) > 0)
        .map(r => ({
            inventory_item_id: parseInt(r.inventory_item_id),
            quantity_used:     parseFloat(r.quantity_used),
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

                <!-- Empty state -->
                <div v-if="!fabrics || fabrics.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 dark:to-violet-900/30 rounded-full mb-4 animate-bounce-soft">
                        <FlaskConical class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No fabrics pending dyeing</p>
                    <p class="text-xs text-gray-400 mt-1">Fabrics arrive here after the checker passes them from knitting.</p>
                </div>

                <!-- Fabric Cards -->
                <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div v-for="(fabric, i) in fabrics" :key="fabric.id"
                         :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                         class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />

                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-500 via-indigo-600 to-violet-700 flex items-center justify-center text-white text-lg font-black shadow-lg uppercase flex-shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                    {{ (fabric.code ?? '?').charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-black text-gray-900 dark:text-white truncate tracking-tight group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">
                                        {{ fabric.code }}
                                    </p>
                                    <p class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase truncate">{{ fabric.yarn_type }}</p>
                                </div>
                            </div>
                            <span class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full ring-1 flex-shrink-0 ml-2 flex items-center gap-1 bg-yellow-100 text-yellow-700 ring-yellow-200 dark:bg-yellow-500/15 dark:text-yellow-300 dark:ring-yellow-500/30">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Pending
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs mb-3">
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Weight</p>
                                <p class="font-black text-gray-800 dark:text-gray-100">{{ fmtNum(fabric.weight) }} kg</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Machine</p>
                                <p class="font-black text-gray-800 dark:text-gray-100">{{ fabric.machine?.machine_no ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Operator</p>
                                <p class="font-black text-gray-800 dark:text-gray-100 truncate">{{ fabric.operator?.name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-bold">Shift</p>
                                <p class="font-black text-gray-800 dark:text-gray-100">{{ fabric.shift }}</p>
                            </div>
                        </div>

                        <div v-if="fabric.sales_order" class="bg-indigo-50/70 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800/40 rounded-2xl px-3 py-2 mb-2">
                            <p class="text-[10px] text-indigo-400 font-black uppercase tracking-wide">Linked Job Order</p>
                            <p class="text-xs font-black text-indigo-800 dark:text-indigo-200 mt-0.5 font-mono">{{ fabric.sales_order.jo_number }}</p>
                            <p class="text-[10px] text-indigo-500 mt-0.5">
                                Color: {{ fabric.sales_order.color }} · Qty: {{ fmtNum(fabric.sales_order.quantity) }} kg
                            </p>
                        </div>

                        <div v-if="fabric.recipe" class="bg-amber-50 dark:bg-amber-500/10 border border-amber-100 dark:border-amber-500/20 rounded-2xl px-3 py-2 space-y-1 mb-2">
                            <p class="text-[10px] text-amber-600 dark:text-amber-300 font-black uppercase tracking-wide flex items-center gap-1">
                                <Palette class="w-3 h-3" /> Dye Color Formula
                            </p>
                            <p class="text-sm font-black text-amber-900 dark:text-amber-200">{{ fabric.recipe.dye_color }}</p>
                            <p class="text-[10px] text-amber-600 dark:text-amber-400 truncate">Weave: {{ fabric.recipe.weave_design }}</p>
                        </div>
                        <div v-else class="bg-gray-50 dark:bg-zinc-800 border border-dashed border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2 text-center mb-2">
                            <p class="text-[10px] text-gray-400">No recipe linked to this fabric</p>
                        </div>

                        <p v-if="fabric.remarks" class="text-[10px] text-gray-400 italic truncate mb-2">{{ fabric.remarks }}</p>

                        <div class="mt-auto flex gap-2 pt-3 border-t border-gray-100 dark:border-zinc-800">
                            <button v-if="fabric.recipe" @click="openRecipeModal(fabric)" type="button"
                                class="flex-1 flex items-center justify-center gap-1.5 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-500/10 text-xs font-bold px-3 py-2.5 rounded-2xl transition active:scale-95">
                                <Layers class="w-3.5 h-3.5" /> View Recipe
                            </button>
                            <button v-if="canEditProduction" @click="openDyeModal(fabric)" type="button"
                                class="flex-1 flex items-center justify-center gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-xs font-bold px-3 py-2.5 rounded-2xl shadow-lg shadow-indigo-500/20 transition active:scale-95">
                                <Droplet class="w-3.5 h-3.5" /> Dye Fabric
                            </button>
                        </div>
                        <ChevronRight class="absolute top-4 right-4 h-4 w-4 text-indigo-300 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                </TransitionGroup>

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
                                    <p class="font-mono text-xs text-blue-100">{{ currentFabric.code }}</p>
                                    <h3 class="text-lg font-black tracking-tight mt-0.5">Record Dyeing</h3>
                                    <p class="text-xs text-blue-100/90 mt-0.5">{{ currentFabric.yarn_type }} · {{ fmtNum(currentFabric.weight) }} kg</p>
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

                        <div>
                            <label class="block text-xs font-black text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">
                                Machine No. <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.machine_id" required
                                class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                <option value="">— Select Machine —</option>
                                <option v-for="machine in machines" :key="machine.id" :value="machine.id">
                                    {{ machine.machine_no }}
                                </option>
                            </select>
                        </div>

                        <div class="border border-gray-200 dark:border-zinc-700 rounded-3xl overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-3 bg-gray-50/70 dark:bg-zinc-800/60 border-b border-gray-100 dark:border-zinc-700">
                                <div>
                                    <p class="text-xs font-black text-gray-700 dark:text-gray-200 uppercase tracking-wide">Dye Chemicals Used</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Each lot is deducted from inventory on save</p>
                                </div>
                                <button type="button" @click="addDyeRow"
                                    class="flex items-center gap-1.5 bg-gray-900 hover:bg-gray-700 text-white text-xs font-bold px-3 py-2 rounded-2xl transition active:scale-95">
                                    <Plus class="w-3.5 h-3.5" /> Add Dye
                                </button>
                            </div>

                            <div class="p-4 space-y-4">
                                <div v-for="(row, index) in dyeRows" :key="index"
                                     class="rounded-2xl border p-4 space-y-3"
                                     :class="index === 0 ? 'border-indigo-100 dark:border-indigo-800 bg-indigo-50/40 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-zinc-700 bg-gray-50/40 dark:bg-zinc-800/40'">

                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 h-5 rounded-full text-[10px] font-black flex items-center justify-center"
                                                  :class="index === 0 ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-white'">
                                                {{ index + 1 }}
                                            </span>
                                            <p class="text-xs font-black uppercase tracking-wide"
                                               :class="index === 0 ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300'">
                                                {{ index === 0 ? 'Primary Dye' : 'Additional Dye' }}
                                            </p>
                                        </div>
                                        <button v-if="dyeRows.length > 1" type="button" @click="removeDyeRow(index)"
                                            class="flex items-center gap-1 text-red-500 hover:text-red-700 text-xs font-bold">
                                            <Trash2 class="w-3.5 h-3.5" /> Remove
                                        </button>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">
                                            Dye Type <span class="text-red-500">*</span>
                                        </label>
                                        <select v-model="row.inventory_item_id" @change="onDyeItemChange(row)"
                                            class="w-full border border-gray-200 dark:border-zinc-700 rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                            <option value="">— Select Dye Lot —</option>
                                            <option v-for="dye in dyes" :key="dye.id" :value="dye.id">
                                                {{ dye.material_name }} ({{ dye.control_number }}) — {{ fmtNum(dye.remaining_quantity) }} {{ dye.unit }} left
                                            </option>
                                        </select>
                                    </div>

                                    <div v-if="getDyeById(row.inventory_item_id)" class="flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300">
                                        <span class="inline-flex items-center gap-1.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 px-2.5 py-1 rounded-lg">
                                            <Package class="w-3 h-3 text-gray-400" /> {{ getDyeById(row.inventory_item_id).control_number }}
                                        </span>
                                        <span class="inline-flex items-center gap-1.5 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 px-2.5 py-1 rounded-lg">
                                            🧪 {{ fmtNum(getDyeById(row.inventory_item_id).remaining_quantity) }} {{ getDyeById(row.inventory_item_id).unit }} remaining
                                        </span>
                                    </div>

                                    <div v-if="row.inventory_item_id">
                                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">
                                            Chemicals Used ({{ getDyeById(row.inventory_item_id)?.unit ?? 'kg' }})
                                            <span class="text-red-500">*</span>
                                            <span class="text-gray-400 font-normal normal-case ml-1">
                                                max {{ fmtNum(getDyeById(row.inventory_item_id)?.remaining_quantity) }}
                                            </span>
                                        </label>
                                        <input v-model.number="row.quantity_used" type="number" step="0.01" min="0.01"
                                            :max="getDyeById(row.inventory_item_id)?.remaining_quantity"
                                            class="w-full border rounded-2xl px-3 py-2.5 bg-white dark:bg-zinc-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                                            :class="dyeRowErrors[index] ? 'border-red-300' : 'border-gray-200 dark:border-zinc-700'"
                                            placeholder="e.g. 5.00" />
                                        <p v-if="dyeRowErrors[index]" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                                            <AlertTriangle class="w-3 h-3" /> {{ dyeRowErrors[index] }}
                                        </p>
                                        <p v-else-if="parseFloat(row.quantity_used) > 0" class="text-xs text-emerald-600 mt-1">
                                            ✓ {{ fmtNum(getDyeById(row.inventory_item_id).remaining_quantity - parseFloat(row.quantity_used)) }}
                                            {{ getDyeById(row.inventory_item_id).unit }} will remain.
                                        </p>
                                    </div>
                                </div>

                                <div v-if="dyeRows.length > 1 && totalChemicalsKg > 0"
                                     class="flex items-center justify-between bg-gray-50 dark:bg-zinc-800 border border-gray-100 dark:border-zinc-700 rounded-2xl px-4 py-2.5">
                                    <p class="text-xs text-gray-500 font-medium">Total chemicals used</p>
                                    <p class="text-sm font-black text-gray-900 dark:text-white">{{ fmtNum(totalChemicalsKg) }} kg</p>
                                </div>
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
                            <p v-if="!form.machine_id" class="text-amber-600 flex items-center gap-1">
                                <AlertTriangle class="w-3 h-3" /> Select a machine.
                            </p>
                            <p v-if="dyeRows.some(r => !r.inventory_item_id || !parseFloat(r.quantity_used))" class="text-amber-600 flex items-center gap-1">
                                <AlertTriangle class="w-3 h-3" /> Fill in all dye type and quantity fields.
                            </p>
                            <p v-if="hasInvalidDyeRows" class="text-red-600 flex items-center gap-1">
                                <AlertTriangle class="w-3 h-3" /> One or more dye quantities exceed available inventory.
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
