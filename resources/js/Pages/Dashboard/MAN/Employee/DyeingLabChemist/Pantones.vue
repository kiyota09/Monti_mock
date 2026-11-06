<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Search, Palette, FlaskConical, Beaker, Layers, Package, X, Clock3, Sparkles, Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps({ pantones: { type: Array, default: () => [] }, materials: { type: Array, default: () => [] } });
const search = ref('');
const selectedPantone = ref(null);
const fabricSearch = ref('');
const showCreate = ref(false);
const editingFormula = ref(null);
const editingPantone = ref(null);

const categories = [
    { key: 'dye_stuffs', label: 'Dye stuffs', icon: Palette, categories: ['Dye Stuff', 'Dye', 'Dyestuff'] },
    { key: 'auxiliary_agents', label: 'Auxiliary agents', icon: Beaker, categories: ['Auxillary Agent', 'Auxiliary Agent'] },
    { key: 'basic_chemicals', label: 'Basic chemicals', icon: FlaskConical, categories: ['Basic Chemicals', 'Basic Chemical'] },
    { key: 'post_treatment', label: 'Post-treatment', icon: Layers, categories: ['Post-Treatment', 'Post Treatment'] },
];

const newPantone = useForm({ name: '', hex: '#7C3AED', color_category: 'light', yarn_material_ids: [] });
const formulaForm = useForm({ combinations: { dye_stuffs: [], auxiliary_agents: [], basic_chemicals: [], post_treatment: [] } });
const pantoneEditForm = useForm({ name: '', hex: '#7C3AED', color_category: 'light', yarn_material_ids: [] });
const yarnMaterials = computed(() => props.materials.filter((material) => material.category === 'Yarn'));
const materialsFor = (group) => props.materials.filter((material) => group.categories.includes(material.category));
const addFormulaMaterial = (key) => formulaForm.combinations[key].push({ material_id: '', amount: '' });
const removeFormulaMaterial = (key, index) => formulaForm.combinations[key].splice(index, 1);
const submitPantone = () => newPantone.post(route('man.staff.dyeing-lab-chemist.pantones.store'), {
    preserveScroll: true,
    onSuccess: () => { showCreate.value = false; newPantone.reset(); newPantone.hex = '#7C3AED'; },
});
const openPantoneEdit = (pantone) => {
    editingPantone.value = pantone;
    pantoneEditForm.name = pantone.name || pantone.code;
    pantoneEditForm.hex = pantone.hex || '#7C3AED';
    pantoneEditForm.color_category = pantone.color_category || 'light';
    pantoneEditForm.yarn_material_ids = pantone.yarn_material_ids || (pantone.fabric_formulas || []).map((formula) => formula.yarn_material_id);
    pantoneEditForm.clearErrors();
};
const colorCategoryLabel = (category) => ({
    white: 'White color',
    light: 'Light color',
    dark: 'Dark color',
}[category] || 'Uncategorized');
const savePantoneEdit = () => pantoneEditForm.patch(route('man.staff.dyeing-lab-chemist.pantones.update', editingPantone.value.id), {
    preserveScroll: true,
    onSuccess: () => { editingPantone.value = null; },
});
const hasYarnFormula = (pantone, materialId) => (pantone.yarn_material_ids || (pantone.fabric_formulas || []).map((formula) => formula.yarn_material_id))
    .some((id) => Number(id) === Number(materialId));
const selectAllYarns = (form) => { form.yarn_material_ids = yarnMaterials.value.map((material) => material.id); };
const editFormula = (formula) => {
    editingFormula.value = formula;
    formulaForm.combinations = JSON.parse(JSON.stringify(formula.combinations || { dye_stuffs: [], auxiliary_agents: [], basic_chemicals: [], post_treatment: [] }));
};
const saveFormula = () => formulaForm.patch(route('man.staff.dyeing-lab-chemist.pantones.fabric-formula.update', { pantoneReference: selectedPantone.value.id, pantoneFabricFormula: editingFormula.value.id }), {
    preserveScroll: true,
    onSuccess: () => {
        const updated = { ...editingFormula.value, combinations: JSON.parse(JSON.stringify(formulaForm.combinations)), is_demo_data: false };
        selectedPantone.value = {
            ...selectedPantone.value,
            fabric_formulas: selectedPantone.value.fabric_formulas.map((fabric) => fabric.id === updated.id ? updated : fabric),
        };
        editingFormula.value = null;
    },
});

const filteredPantones = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (!query) return props.pantones;
    return props.pantones.filter((pantone) =>
        [pantone.code, pantone.name, pantone.swatch_id].some((value) => String(value || '').toLowerCase().includes(query))
    );
});

const withDefaultsCount = computed(() => props.pantones.filter((pantone) =>
    (pantone.fabric_formulas_count || 0) > 0
).length);

const filteredFabricFormulas = computed(() => {
    const formulas = selectedPantone.value?.fabric_formulas || [];
    const query = fabricSearch.value.trim().toLowerCase();
    if (!query) return formulas;
    return formulas.filter((fabric) => [fabric.yarn_name, fabric.yarn_base_name]
        .some((name) => String(name || '').toLowerCase().includes(query)));
});

const openPantone = async (pantone) => {
    fabricSearch.value = '';
    selectedPantone.value = { ...pantone, loading_formulas: !!pantone.id, formula_load_error: false, fabric_formulas: [] };
    if (!pantone.id) return;

    try {
        const response = await fetch(route('man.staff.dyeing-lab-chemist.pantones.show', pantone.id), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Could not load this Pantone formula.');
        const payload = await response.json();
        if (selectedPantone.value?.id === pantone.id) {
            selectedPantone.value = { ...selectedPantone.value, ...payload.pantone, loading_formulas: false };
        }
    } catch {
        if (selectedPantone.value?.id === pantone.id) {
            selectedPantone.value = { ...selectedPantone.value, loading_formulas: false, formula_load_error: true };
        }
    }
};
const closePantone = () => { selectedPantone.value = null; fabricSearch.value = ''; };
</script>

<template>
    <Head title="Pantone Library | Color Request" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-violet-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-7xl space-y-6 p-4 pb-16 sm:p-6">
                <section class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white shadow-xl shadow-indigo-500/20 sm:p-8">
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 shadow-lg ring-1 ring-white/30"><Palette class="h-7 w-7" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> MAN · Color Request</p>
                            <h1 class="text-2xl font-black tracking-tight sm:text-3xl">Pantone Library</h1>
                            <p class="text-sm text-blue-100/90">Pantone colors recorded in MontiTextile with their saved lab combinations.</p>
                        </div>
                        <div class="rounded-2xl bg-white/15 px-4 py-3 text-right ring-1 ring-white/25 backdrop-blur">
                            <p class="text-[10px] font-black uppercase tracking-widest text-blue-100">Stored colors</p>
                            <p class="text-2xl font-black">{{ pantones.length }}</p>
                        </div>
                        <button type="button" @click="showCreate = true" class="inline-flex items-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-black text-indigo-700 shadow-lg transition hover:-translate-y-0.5 hover:bg-indigo-50"><Plus class="h-4 w-4" /> Add Pantone Color</button>
                    </div>
                    <div class="relative mt-6 max-w-xl">
                        <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input v-model="search" type="search" placeholder="Search Pantone code, color name, or swatch ID..." class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-slate-900 shadow-lg outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-white/70" />
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <article class="rounded-2xl border border-white bg-white/85 p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/85">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Pantone records</p>
                        <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ pantones.length }}</p>
                    </article>
                    <article class="rounded-2xl border border-white bg-white/85 p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/85">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">With yarn formulas</p>
                        <p class="mt-1 text-2xl font-black text-violet-700 dark:text-violet-300">{{ withDefaultsCount }}</p>
                    </article>
                    <article class="rounded-2xl border border-white bg-white/85 p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/85">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Combination groups</p>
                        <p class="mt-1 text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ categories.length }}</p>
                    </article>
                </section>

                <section v-if="filteredPantones.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="pantone in filteredPantones" :key="pantone.code" class="group relative min-h-36 overflow-hidden rounded-3xl border border-slate-100 bg-white/90 shadow-sm transition hover:-translate-y-1 hover:border-violet-200 hover:shadow-xl hover:shadow-violet-500/10 dark:border-zinc-800 dark:bg-zinc-900/90 dark:hover:border-violet-800">
                        <button type="button" @click="openPantone(pantone)" class="flex min-h-36 w-full items-stretch gap-4 p-4 pr-16 text-left">
                            <div class="relative flex w-24 shrink-0 flex-col items-center justify-center overflow-hidden rounded-2xl ring-1 ring-black/5 dark:ring-white/10" :style="pantone.hex ? { backgroundColor: pantone.hex } : {}" :class="pantone.hex ? '' : 'bg-gradient-to-br from-slate-100 to-violet-100 dark:from-zinc-800 dark:to-violet-950/60'">
                                <Palette v-if="!pantone.hex" class="h-7 w-7 text-violet-400" />
                                <span class="absolute inset-x-0 bottom-0 bg-black/35 py-1 text-center text-[9px] font-black uppercase text-white backdrop-blur">{{ pantone.hex || 'Swatch pending' }}</span>
                            </div>
                            <span class="flex min-w-0 flex-1 flex-col justify-center">
                                <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-violet-600 dark:text-violet-300">{{ pantone.code }}</span>
                                <span class="mt-1 truncate text-base font-black text-slate-900 dark:text-white">{{ pantone.name }}</span>
                                <span v-if="pantone.color_category" class="mt-2 inline-flex w-fit rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-slate-600 dark:bg-zinc-800 dark:text-slate-300">{{ colorCategoryLabel(pantone.color_category) }}</span>
                                <span class="mt-2 flex flex-wrap items-center gap-2 text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                                    <span>{{ pantone.fabric_formulas_count || 0 }} yarn types</span>
                                </span>
                                <span class="mt-3 inline-flex w-fit items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Open color formula <span aria-hidden="true">→</span></span>
                            </span>
                        </button>
                        <button v-if="pantone.id" type="button" @click.stop="openPantoneEdit(pantone)" class="absolute right-3 top-3 inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white/95 px-2.5 py-2 text-[10px] font-black text-slate-700 shadow-sm transition hover:border-violet-300 hover:text-violet-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-slate-200"><Layers class="h-3.5 w-3.5" /> Edit</button>
                    </article>
                </section>

                <section v-else class="flex flex-col items-center rounded-3xl border border-dashed border-slate-200 bg-white/80 px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900/70">
                    <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><Palette class="h-7 w-7" /></span>
                    <h2 class="text-base font-black text-slate-800 dark:text-white">{{ search ? 'No Pantones match your search' : 'No Pantone colors are stored yet' }}</h2>
                    <p class="mt-1 max-w-lg text-sm text-slate-500 dark:text-slate-400">{{ search ? 'Try another code or clear your search.' : 'Pantone colors are added to this library when their codes are recorded on a Color Request.' }}</p>
                </section>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreate" class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm" @click.self="showCreate = false">
                    <form @submit.prevent="submitPantone" class="my-auto w-full max-w-3xl overflow-hidden rounded-[2rem] bg-white shadow-2xl dark:bg-zinc-900">
                        <header class="flex items-start gap-4 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15"><Palette class="h-6 w-6" /></span>
                            <div class="min-w-0 flex-1"><p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">Color Request · Reference setup</p><h2 class="mt-1 text-2xl font-black">Add Pantone color</h2><p class="mt-1 text-sm text-blue-100">Save a named color and its default inventory formula.</p></div>
                            <button type="button" @click="showCreate = false" aria-label="Close form" class="rounded-xl bg-white/15 p-2"><X class="h-5 w-5" /></button>
                        </header>
                        <div class="max-h-[65vh] space-y-5 overflow-y-auto p-5 sm:p-7">
                            <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                                <label class="text-xs font-bold text-slate-600 dark:text-slate-300">Pantone name
                                    <input v-model="newPantone.name" required maxlength="120" placeholder="e.g. Pantone 18-1664 TCX" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                    <span v-if="newPantone.errors.name" class="mt-1 block text-xs text-rose-600">{{ newPantone.errors.name }}</span>
                                </label>
                                <label class="text-xs font-bold text-slate-600 dark:text-slate-300">Pantone color
                                    <span class="mt-2 flex items-center gap-3 rounded-xl border border-slate-200 p-2 dark:border-zinc-700"><input v-model="newPantone.hex" type="color" class="h-10 w-12 cursor-pointer rounded-lg border-0 bg-transparent" /><span class="font-mono text-sm text-slate-800 dark:text-white">{{ newPantone.hex }}</span></span>
                                </label>
                            </div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Color category
                                <select v-model="newPantone.color_category" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-violet-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                    <option value="white">White color</option>
                                    <option value="light">Light color</option>
                                    <option value="dark">Dark color</option>
                                </select>
                                <span v-if="newPantone.errors.color_category" class="mt-1 block text-xs text-rose-600">{{ newPantone.errors.color_category }}</span>
                            </label>
                            <section class="overflow-hidden rounded-2xl border border-slate-200 dark:border-zinc-700">
                                <header class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 dark:border-zinc-800"><div class="min-w-0 flex-1"><h3 class="text-xs font-black uppercase tracking-wide text-slate-800 dark:text-white">Select yarn / fabric bases</h3><p class="mt-1 text-[10px] text-slate-500">Choose one or more inventory yarns. Each selection gets its own Pantone formula.</p></div><button type="button" @click="selectAllYarns(newPantone)" class="rounded-lg bg-violet-50 px-3 py-2 text-[10px] font-black text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Select all</button></header>
                                <div class="grid max-h-64 grid-cols-1 gap-2 overflow-y-auto p-3 sm:grid-cols-2">
                                    <label v-for="yarn in yarnMaterials" :key="yarn.id" class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs transition hover:border-violet-200 dark:border-zinc-800 dark:bg-zinc-800/50"><input v-model="newPantone.yarn_material_ids" type="checkbox" :value="yarn.id" class="rounded border-slate-300 text-violet-700 focus:ring-violet-500" /><span class="min-w-0 flex-1"><span class="block truncate font-bold text-slate-800 dark:text-slate-100">{{ yarn.display_name || yarn.name }}</span><span class="font-mono text-[9px] text-slate-400">{{ yarn.mat_id }}</span></span></label>
                                    <p v-if="!yarnMaterials.length" class="col-span-full p-4 text-center text-xs text-slate-500">No Yarn materials are currently in inventory.</p>
                                </div>
                                <p v-if="newPantone.errors.yarn_material_ids" class="px-4 pb-3 text-xs text-rose-600">{{ newPantone.errors.yarn_material_ids }}</p>
                            </section>
                        </div>
                        <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-800/30"><button type="button" @click="showCreate = false" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-slate-300">Cancel</button><button type="submit" :disabled="newPantone.processing" class="inline-flex items-center gap-2 rounded-xl bg-violet-700 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50"><Plus class="h-4 w-4" />{{ newPantone.processing ? 'Saving…' : 'Save Pantone' }}</button></footer>
                    </form>
                </div>
            </Transition>
            <Transition name="modal">
                <div v-if="editingPantone" class="fixed inset-0 z-[85] flex items-center justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm" @click.self="editingPantone = null">
                    <form @submit.prevent="savePantoneEdit" class="my-auto w-full max-w-3xl overflow-hidden rounded-[2rem] bg-white shadow-2xl dark:bg-zinc-900">
                        <header class="flex items-start gap-4 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15"><Palette class="h-5 w-5" /></span><div class="min-w-0 flex-1"><p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">Pantone library</p><h2 class="mt-1 text-xl font-black">Edit Pantone color</h2><p class="mt-1 text-xs text-blue-100">Update its name or swatch, and add more yarn-based formula types.</p></div><button type="button" @click="editingPantone = null" class="rounded-xl bg-white/15 p-2"><X class="h-5 w-5" /></button></header>
                        <div class="max-h-[65vh] space-y-5 overflow-y-auto p-5 sm:p-7">
                            <div class="grid gap-4 sm:grid-cols-[1fr_auto]"><label class="text-xs font-bold text-slate-600 dark:text-slate-300">Pantone name<input v-model="pantoneEditForm.name" required maxlength="120" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /><span v-if="pantoneEditForm.errors.name" class="mt-1 block text-xs text-rose-600">{{ pantoneEditForm.errors.name }}</span></label><label class="text-xs font-bold text-slate-600 dark:text-slate-300">Pantone color<span class="mt-2 flex items-center gap-3 rounded-xl border border-slate-200 p-2 dark:border-zinc-700"><input v-model="pantoneEditForm.hex" type="color" class="h-10 w-12 cursor-pointer rounded-lg border-0 bg-transparent" /><span class="font-mono text-sm text-slate-800 dark:text-white">{{ pantoneEditForm.hex }}</span></span></label></div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Color category
                                <select v-model="pantoneEditForm.color_category" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                    <option value="white">White color</option>
                                    <option value="light">Light color</option>
                                    <option value="dark">Dark color</option>
                                </select>
                                <span v-if="pantoneEditForm.errors.color_category" class="mt-1 block text-xs text-rose-600">{{ pantoneEditForm.errors.color_category }}</span>
                            </label>
                            <section class="overflow-hidden rounded-2xl border border-slate-200 dark:border-zinc-700"><header class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 dark:border-zinc-800"><div class="min-w-0 flex-1"><h3 class="text-xs font-black uppercase tracking-wide text-slate-800 dark:text-white">Yarn / fabric formula types</h3><p class="mt-1 text-[10px] text-slate-500">Existing bases stay selected. Select additional inventory yarns to create more separate formulas.</p></div><button type="button" @click="selectAllYarns(pantoneEditForm)" class="rounded-lg bg-violet-50 px-3 py-2 text-[10px] font-black text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Select all</button></header>
                                <div class="grid max-h-64 grid-cols-1 gap-2 overflow-y-auto p-3 sm:grid-cols-2"><label v-for="yarn in yarnMaterials" :key="yarn.id" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs dark:border-zinc-800 dark:bg-zinc-800/50"><input v-model="pantoneEditForm.yarn_material_ids" type="checkbox" :value="yarn.id" :disabled="hasYarnFormula(editingPantone, yarn.id)" class="rounded border-slate-300 text-violet-700 focus:ring-violet-500" /><span class="min-w-0 flex-1"><span class="block truncate font-bold text-slate-800 dark:text-slate-100">{{ yarn.display_name || yarn.name }}</span><span class="font-mono text-[9px] text-slate-400">{{ yarn.mat_id }}<span v-if="hasYarnFormula(editingPantone, yarn.id)"> · already added</span></span></span></label></div>
                                <p v-if="pantoneEditForm.errors.yarn_material_ids" class="px-4 pb-3 text-xs text-rose-600">{{ pantoneEditForm.errors.yarn_material_ids }}</p>
                            </section>
                        </div>
                        <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-800/30"><button type="button" @click="editingPantone = null" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-slate-300">Cancel</button><button type="submit" :disabled="pantoneEditForm.processing" class="rounded-xl bg-violet-700 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50">{{ pantoneEditForm.processing ? 'Saving…' : 'Save Pantone' }}</button></footer>
                    </form>
                </div>
            </Transition>
            <Transition name="modal">
                <div v-if="editingFormula" class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm" @click.self="editingFormula = null">
                    <form @submit.prevent="saveFormula" class="my-auto w-full max-w-3xl overflow-hidden rounded-[2rem] bg-white shadow-2xl dark:bg-zinc-900">
                        <header class="flex items-start gap-4 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15"><Beaker class="h-5 w-5" /></span><div class="min-w-0 flex-1"><p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">{{ selectedPantone?.code }}</p><h2 class="mt-1 text-xl font-black">{{ editingFormula.yarn_name }} formula</h2><p class="mt-1 text-xs text-blue-100">Pantone reference formula · independent of client-approved recipes</p></div><button type="button" @click="editingFormula = null" class="rounded-xl bg-white/15 p-2"><X class="h-5 w-5" /></button></header>
                        <div class="max-h-[65vh] space-y-4 overflow-y-auto p-5 sm:p-7">
                            <p v-if="formulaForm.errors.combinations" class="rounded-xl bg-rose-50 p-3 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ formulaForm.errors.combinations }}</p>
                            <section v-for="group in categories" :key="group.key" class="rounded-2xl border border-slate-200 dark:border-zinc-800">
                                <header class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 dark:border-zinc-800"><component :is="group.icon" class="h-4 w-4 text-violet-600" /><h3 class="flex-1 text-xs font-black uppercase tracking-wide text-slate-700 dark:text-slate-200">{{ group.label }}</h3><button type="button" @click="addFormulaMaterial(group.key)" class="inline-flex items-center gap-1 rounded-lg bg-violet-50 px-3 py-2 text-[10px] font-black text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><Plus class="h-3.5 w-3.5" /> Add material</button></header>
                                <div v-if="formulaForm.combinations[group.key].length" class="space-y-2 p-3"><div v-for="(row, index) in formulaForm.combinations[group.key]" :key="index" class="grid gap-2 sm:grid-cols-[1fr_130px_40px]"><select v-model="row.material_id" required class="min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"><option value="" disabled>Select inventory material</option><option v-for="material in materialsFor(group)" :key="material.id" :value="material.id">{{ material.display_name || material.name }} · {{ material.mat_id }}</option></select><input v-model="row.amount" type="number" min="0" step="0.001" placeholder="Amount (optional)" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /><button type="button" @click="removeFormulaMaterial(group.key, index)" aria-label="Remove material" class="flex items-center justify-center rounded-xl text-rose-500 hover:bg-rose-50"><Trash2 class="h-4 w-4" /></button></div></div>
                                <p v-else class="px-4 py-3 text-xs text-slate-400">No {{ group.label.toLowerCase() }} selected.</p>
                            </section>
                        </div>
                        <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-800/30"><button type="button" @click="editingFormula = null" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-slate-300">Cancel</button><button type="submit" :disabled="formulaForm.processing" class="rounded-xl bg-violet-700 px-5 py-2.5 text-xs font-black text-white disabled:opacity-50">{{ formulaForm.processing ? 'Saving…' : 'Save reference formula' }}</button></footer>
                    </form>
                </div>
            </Transition>
            <Transition name="modal">
                <div v-if="selectedPantone" class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm" @click.self="closePantone">
                    <section class="my-auto w-full max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-2xl ring-1 ring-black/5 dark:bg-zinc-900 dark:ring-white/10">
                        <header class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white sm:p-8">
                            <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10 blur-3xl" />
                            <div class="relative flex items-start gap-4">
                                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl border border-white/30 bg-white/15 shadow-lg" :style="selectedPantone.hex ? { backgroundColor: selectedPantone.hex } : {}"><span v-if="!selectedPantone.hex" class="flex h-full items-center justify-center"><Palette class="h-8 w-8 text-white/80" /></span></div>
                                <div class="min-w-0 flex-1 pt-1">
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">Pantone reference</p>
                                    <h2 class="mt-1 break-words text-2xl font-black tracking-tight sm:text-3xl">{{ selectedPantone.code }}</h2>
                                    <p class="mt-1 text-sm text-blue-100">{{ selectedPantone.name }}<span v-if="selectedPantone.hex"> · {{ selectedPantone.hex }}</span></p>
                                    <p v-if="selectedPantone.rgb_lab_values" class="mt-1 text-xs text-blue-100/80">RGB / Lab: {{ selectedPantone.rgb_lab_values }}</p>
                                </div>
                                <button type="button" aria-label="Close Pantone details" @click="closePantone" class="relative rounded-xl bg-white/15 p-2 ring-1 ring-white/25 transition hover:bg-white/25"><X class="h-5 w-5" /></button>
                            </div>
                            <div class="relative mt-5 flex flex-wrap gap-2">
                                <span v-if="selectedPantone.swatch_id" class="rounded-full bg-white/15 px-3 py-1 text-[10px] font-bold ring-1 ring-white/20">Swatch {{ selectedPantone.swatch_id }}</span>
                                <span v-if="selectedPantone.color_category" class="rounded-full bg-white/15 px-3 py-1 text-[10px] font-bold ring-1 ring-white/20">{{ colorCategoryLabel(selectedPantone.color_category) }}</span>
                                <span v-if="selectedPantone.trial_code" class="rounded-full bg-white/15 px-3 py-1 text-[10px] font-bold ring-1 ring-white/20">Default formula · {{ selectedPantone.trial_code }} · {{ selectedPantone.trial_status }}</span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-3 py-1 text-[10px] font-bold ring-1 ring-white/20"><Clock3 class="h-3 w-3" /> Updated {{ selectedPantone.last_updated || '—' }}</span>
                            </div>
                        </header>

                        <div class="max-h-[65vh] space-y-4 overflow-y-auto p-5 sm:p-7">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300"><Beaker class="h-5 w-5" /></span>
                                <div><h3 class="font-black text-slate-900 dark:text-white">Formulas by base yarn / fabric type</h3><p class="text-xs text-slate-500 dark:text-slate-400">Each yarn material has a separate reference formula, independent of client-approved Recipes.</p></div>
                            </div>
                            <label class="relative block">
                                <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input v-model="fabricSearch" type="search" placeholder="Search a yarn or fabric type..." class="w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm text-slate-800 outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:focus:ring-violet-500/20" />
                            </label>
                            <p v-if="selectedPantone.loading_formulas" class="rounded-2xl border border-indigo-100 bg-indigo-50 p-5 text-center text-sm font-semibold text-indigo-700 dark:border-indigo-900 dark:bg-indigo-500/10 dark:text-indigo-200">Loading yarn formulas...</p>
                            <div v-else-if="selectedPantone.formula_load_error" class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-center text-sm font-semibold text-rose-700 dark:border-rose-900 dark:bg-rose-500/10 dark:text-rose-200">
                                Could not load this Pantone’s formulas. <button type="button" @click="openPantone(selectedPantone)" class="ml-1 underline underline-offset-2">Try again</button>
                            </div>
                            <div v-else class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                <article v-for="fabric in filteredFabricFormulas" :key="fabric.id" class="overflow-hidden rounded-2xl border border-slate-100 bg-slate-50/70 dark:border-zinc-800 dark:bg-zinc-800/30">
                                    <header class="flex items-center gap-3 border-b border-slate-100 p-4 dark:border-zinc-800"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-violet-600 dark:bg-zinc-900 dark:text-violet-300"><Package class="h-4 w-4" /></span><div class="min-w-0 flex-1"><h4 class="truncate text-sm font-black text-slate-800 dark:text-white">{{ fabric.yarn_name || 'Yarn type unavailable' }}</h4><p class="text-[10px] text-slate-500">Base yarn material · specific formula</p></div><button type="button" @click="editFormula(fabric)" class="shrink-0 rounded-xl bg-violet-700 px-3 py-2 text-[10px] font-black text-white hover:bg-violet-600">Adjust formula</button></header>
                                    <div class="space-y-2 p-3"><div v-for="group in categories" :key="group.key" class="rounded-xl bg-white p-3 dark:bg-zinc-900"><div class="flex items-center justify-between gap-2"><p class="text-[9px] font-black uppercase tracking-wide text-slate-500">{{ group.label }}</p><span class="text-[9px] font-bold text-slate-400">{{ fabric.combinations?.[group.key]?.length || 0 }}</span></div><ul v-if="fabric.combinations?.[group.key]?.length" class="mt-1 space-y-1"><li v-for="(item, index) in fabric.combinations[group.key]" :key="`${item.material_id}-${index}`" class="flex items-center justify-between gap-2 text-[10px]"><span class="truncate font-semibold text-slate-700 dark:text-slate-200">{{ item.name }}</span><span v-if="item.amount" class="shrink-0 font-mono text-slate-500">{{ item.amount }} {{ item.unit }}</span></li></ul><p v-else class="mt-1 text-[10px] text-slate-400">No material selected</p></div></div>
                                </article>
                                <p v-if="!(selectedPantone.fabric_formulas || []).length" class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-4 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-200 lg:col-span-2">No yarn-based formula slots are configured for this Pantone reference yet.</p>
                                <p v-else-if="!filteredFabricFormulas.length" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-center text-xs text-slate-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-slate-400 lg:col-span-2">No yarn or fabric types match “{{ fabricSearch }}”.</p>
                            </div>
                        </div>
                        <footer class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-800/30 sm:px-7"><button type="button" @click="closePantone" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-black uppercase tracking-wide text-slate-600 transition hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-slate-300 dark:hover:bg-zinc-800">Close</button></footer>
                    </section>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-fade-up { animation: fadeUp .55s cubic-bezier(.22,1,.36,1) both; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
</style>
