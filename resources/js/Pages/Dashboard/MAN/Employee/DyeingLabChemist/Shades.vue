<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FlaskConical, Sparkles, PlusCircle, ClipboardList, Beaker, ArrowRight, X, ChevronDown, Search, Check, Palette, Trash2, Scissors } from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'dyeing_lab_chemist'));

const props = defineProps({ dips: Array, jobOrders: Array, sampleRequests: { type: Array, default: () => [] }, materials: { type: Array, default: () => [] }, pantoneReferences: { type: Array, default: () => [] } });

const showDipForm = ref(false);
const dipForm = useForm({ sales_order_id: '', customer_ref: '', pantone_code: '', rgb_lab_values: '', swatch_id: '', fabric_details: '', urgency: 'normal', remarks: '' });
const submitDip = () => {
    dipForm.transform((d) => ({ ...d, sales_order_id: d.sales_order_id || null }))
        .post(route('man.staff.dyeing-lab-chemist.store-dip'), { preserveScroll: true, onSuccess: () => { showDipForm.value = false; dipForm.reset(); } });
};

// Visual only: live swatch preview when the typed Pantone code matches a library color.
const dipPantoneMatch = computed(() => {
    const typed = String(dipForm.pantone_code || '').trim().toLowerCase();
    if (!typed) return null;
    return (props.pantoneReferences || []).find((p) => String(p.name || '').trim().toLowerCase() === typed) || null;
});

const expanded = ref(null);
const trialForm = useForm({ dip_request_id: null, dyestuffs: [{ material_id: '', name: '', pct: '' }], auxiliaries: [{ material_id: '', name: '', gpl: '' }], liquor_ratio: '1:10', curve: '', adjustments: '', delta_e: '', status: 'pending' });
const openTrial = (dip) => { expanded.value = dip.id; trialForm.dip_request_id = dip.id; trialForm.reset(); trialForm.dip_request_id = dip.id; };
const addRow = (list) => list.push(list === trialForm.dyestuffs ? { material_id: '', name: '', pct: '' } : { material_id: '', name: '', gpl: '' });
const removeRow = (list, i) => list.splice(i, 1);
const submitTrial = () => trialForm.transform((data) => {
    let curve = [];
    if (typeof data.curve === 'string' && data.curve.trim() !== '') {
        try { curve = JSON.parse(data.curve); } catch (e) { curve = [{ note: data.curve }]; }
    } else if (Array.isArray(data.curve)) {
        curve = data.curve;
    }
    return stripEmptyRows({ ...data, curve });
}).post(route('man.staff.dyeing-lab-chemist.store-trial'), { preserveScroll: true, onSuccess: () => { expanded.value = null; } });

const advanceDip = (dip, next) => router.patch(route('man.staff.dyeing-lab-chemist.update-dip-status', dip.id), { status: next }, { preserveScroll: true });

// ── CRM fabric sample requests (from inquiry conversations) ──
const sampleExpanded = ref(null);
const formulationPantone = ref(null);
const sampleForm = useForm({
    dyestuffs: [{ material_id: '', name: '', pct: '' }],
    auxiliary_agents: [{ material_id: '', name: '', gpl: '' }],
    basic_chemicals: [{ material_id: '', name: '', gpl: '' }],
    post_treatment: [{ material_id: '', name: '', gpl: '' }],
    notes: '', sample_photo: null,
});
const openSampleFormulate = (s) => {
    sampleExpanded.value = s.id;
    sampleForm.reset();
    const pantone = (s.pantone_colors || []).find((color) => color.pantone_reference_id && color.formula)
        || (s.pantone_colors || []).find((color) => color.formula)
        || s.pantone_colors?.[0]
        || null;
    formulationPantone.value = pantone;
    const defaults = pantone?.formula || s.pantone_formula || {};
    const dyes = defaults.dye_stuffs || defaults.dyestuffs || [];
    const rows = (items, quantityKey) => (items || []).map((row) => ({ material_id: row.material_id || '', name: row.name || '', [quantityKey]: row.amount ?? row[quantityKey] ?? '' }));
    if (dyes.length) sampleForm.dyestuffs = rows(dyes, 'pct');
    if (defaults.auxiliary_agents?.length) sampleForm.auxiliary_agents = rows(defaults.auxiliary_agents, 'gpl');
    if (defaults.basic_chemicals?.length) sampleForm.basic_chemicals = rows(defaults.basic_chemicals, 'gpl');
    if (defaults.post_treatment?.length) sampleForm.post_treatment = rows(defaults.post_treatment, 'gpl');
    else if (defaults.auxiliaries?.length) sampleForm.auxiliary_agents = rows(defaults.auxiliaries, 'gpl');
};
const addSampleRow = (list) => {
    if (list === sampleForm.dyestuffs) return list.push({ material_id: '', name: '', pct: '' });
    return list.push({ material_id: '', name: '', gpl: '' });
};
const removeSampleRow = (list, i) => list.splice(i, 1);
const startSample = (s) => router.patch(route('man.staff.dyeing-lab-chemist.sample.start', s.id), {}, { preserveScroll: true });
const unlinkPantone = (sample, colorIndex) => router.delete(route('man.staff.dyeing-lab-chemist.sample.pantone.unlink', sample.id), { data: { color_index: colorIndex }, preserveScroll: true });
const knitRequestTarget = ref(null);
const knitRequestForm = useForm({ color_index: 0, fabric_design: '', texture: '', gsm: '', requested_weight: 0.25 });
const openKnitRequest = (sample, color, colorIndex) => {
    knitRequestTarget.value = sample;
    knitRequestForm.reset();
    knitRequestForm.color_index = colorIndex;
    knitRequestForm.fabric_design = sample.fabric_name || '';
    knitRequestForm.texture = color.texture || sample.texture || '';
    knitRequestForm.gsm = color.gsm || sample.gsm || '';
};
const submitKnitRequest = () => knitRequestForm.post(route('man.staff.dyeing-lab-chemist.sample.request-knit-fabric', knitRequestTarget.value.id), {
    preserveScroll: true, onSuccess: () => { knitRequestTarget.value = null; knitRequestForm.reset(); },
});
const relatedColors = (sample) => (props.sampleRequests || []).filter((candidate) => {
    if (sample.parent_id) return Number(candidate.parent_id) === Number(sample.parent_id);
    if (sample.request_method === 'pantone') {
        return !candidate.parent_id && candidate.request_method === 'pantone'
            && Number(candidate.inquiry_id) === Number(sample.inquiry_id)
            && Number(candidate.product_id) === Number(sample.product_id);
    }
    return Number(candidate.id) === Number(sample.id);
});
const openColorCount = computed(() => (props.sampleRequests || []).reduce((total, sample) => total + Math.max(1, sample.pantone_colors?.length || 1), 0));
const formulaSections = (sample, color = null) => {
    const formula = color?.formula || (sample.pantone_colors?.length === 1 ? sample.pantone_formula || sample.formula : {}) || {};
    return [
        { label: 'Dye stuffs', rows: formula.dye_stuffs || formula.dyestuffs || [], amountKey: 'amount', unit: '%' },
        { label: 'Auxiliary agents', rows: formula.auxiliary_agents || [], amountKey: 'amount', unit: 'g/L' },
        { label: 'Basic chemicals', rows: formula.basic_chemicals || [], amountKey: 'amount', unit: 'g/L' },
        { label: 'Post-treatment', rows: formula.post_treatment || [], amountKey: 'amount', unit: 'g/L' },
        { label: 'Other auxiliaries', rows: formula.auxiliaries || [], amountKey: 'gpl', unit: 'g/L' },
    ].filter((section) => section.rows.length);
};
const formulaAmount = (row, section) => {
    const amount = row[section.amountKey] ?? row.pct ?? row.gpl;
    return amount === null || amount === undefined || amount === '' ? '' : `${amount} ${row.unit || section.unit}`;
};
const formulationSummary = (formula) => [
    ...(formula?.dyestuffs || []).map((row) => `${row.name} ${row.pct ?? row.amount ?? ''}%`),
    ...['auxiliary_agents', 'basic_chemicals', 'post_treatment', 'auxiliaries'].flatMap((key) => (formula?.[key] || []).map((row) => `${row.name} ${row.gpl ?? row.amount ?? ''} g/L`)),
].join(' · ');
const submitSampleFormulate = () => sampleForm
    .transform((data) => stripEmptyRows(data))
    .post(route('man.staff.dyeing-lab-chemist.sample.formulate', sampleExpanded.value), {
        preserveScroll: true, forceFormData: true,
        onSuccess: () => { sampleExpanded.value = null; formulationPantone.value = null; },
    });

// ── Pantone swatch picker (visual replacement for the native <select>) ──
// A Pantone selection is completed after the chemist chooses its yarn formula.
const pantoneOpen = ref(null);
const pantoneSearch = ref('');
const pendingPantoneSelections = ref({});
const yarnSearches = ref({});
const yarnDropdownOpen = ref(null);
const pantoneHex = (p) => p?.hex || '#e2e8f0';
const filteredPantones = computed(() => {
    const q = pantoneSearch.value.trim().toLowerCase();
    const list = props.pantoneReferences || [];
    if (!q) return list;
    return list.filter((p) => String(p.name || '').toLowerCase().includes(q) || String(p.hex || '').toLowerCase().includes(q) || String(p.code || '').toLowerCase().includes(q));
});
const pantonePickerKey = (s, colorIndex) => `${s.id}:${colorIndex}`;
const togglePantone = (s, colorIndex) => {
    if (!canEditProduction.value) return;
    const key = pantonePickerKey(s, colorIndex);
    pantoneSearch.value = '';
    pantoneOpen.value = pantoneOpen.value === key ? null : key;
    if (pantoneOpen.value) nextTick(() => document.getElementById(`pantone-search-${s.id}-${colorIndex}`)?.focus());
};
const pickPantone = (s, colorIndex, pantoneReferenceId) => {
    pantoneOpen.value = null;
    if (pantoneReferenceId !== '') {
        pendingPantoneSelections.value[pantonePickerKey(s, colorIndex)] = {
            pantone_reference_id: String(pantoneReferenceId),
            yarn_material_id: '',
        };
    }
};
const pantoneYarnValue = (s, colorIndex, color) => pendingPantoneSelections.value[pantonePickerKey(s, colorIndex)]?.yarn_material_id ?? color.yarn_material_id ?? '';
const choosePantoneYarn = (s, colorIndex, color, yarnMaterialId) => {
    const key = pantonePickerKey(s, colorIndex);
    const pending = pendingPantoneSelections.value[key] || {
        pantone_reference_id: String(color.pantone_reference_id),
        yarn_material_id: '',
    };
    pending.yarn_material_id = String(yarnMaterialId);
    pendingPantoneSelections.value[key] = pending;
    yarnDropdownOpen.value = null;
    if (!pending.yarn_material_id) return;
    router.patch(route('man.staff.dyeing-lab-chemist.sample.pantone', s.id), {
        color_index: colorIndex,
        pantone_reference_id: pending.pantone_reference_id,
        yarn_material_id: pending.yarn_material_id,
    }, {
        preserveScroll: true,
        onSuccess: () => { delete pendingPantoneSelections.value[key]; },
    });
};
const filteredYarnOptions = (s, colorIndex) => {
    const query = String(yarnSearches.value[pantonePickerKey(s, colorIndex)] || '').trim().toLowerCase();
    if (!query) return yarnOptions.value;
    return yarnOptions.value.filter((yarn) => [yarn.name, yarn.display_name, yarn.mat_id, yarn.yarn_tier]
        .some((value) => String(value || '').toLowerCase().includes(query)));
};
const selectedYarnOption = (s, colorIndex, color) => yarnOptions.value.find((yarn) =>
    Number(yarn.id) === Number(pantoneYarnValue(s, colorIndex, color))
) || null;
const toggleYarnDropdown = (s, colorIndex) => {
    const key = pantonePickerKey(s, colorIndex);
    yarnDropdownOpen.value = yarnDropdownOpen.value === key ? null : key;
    if (yarnDropdownOpen.value) yarnSearches.value[key] = '';
};
const closePantoneOnOutsideClick = (e) => {
    if (pantoneOpen.value !== null && !e.target.closest('[data-pantone-picker]')) pantoneOpen.value = null;
    if (yarnDropdownOpen.value !== null && !e.target.closest('[data-yarn-picker]')) yarnDropdownOpen.value = null;
};
onMounted(() => document.addEventListener('click', closePantoneOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('click', closePantoneOnOutsideClick));

// ── Inventory-connected material picker ─────────────────────────────
// Dyestuff / auxiliary names must come from the materials table —
// free-typed names can never reach recipes unconnected (they broke
// stock checks with "Unknown material"). Picking a suggestion stores
// the material ID; unmatched text blocks submit and the backend
// rejects it as a final guard (exists:materials,id).
const dyeOptions = computed(() => (props.materials || []).filter((m) => ['dye', 'dye stuff'].includes(String(m.category || '').toLowerCase())));
const auxOptions = computed(() => (props.materials || []).filter((m) => ['dye', 'dye stuff', 'supplies', 'auxillary agent', 'basic chemicals', 'post-treatment', 'pre-treatment', 'bio-processing enzymes', 'care agents'].includes(String(m.category || '').toLowerCase())));
const formulationInputGroups = [
    { key: 'dyestuffs', label: 'Dye stuffs', listId: 'sample-dye-list', quantityKey: 'pct', quantityLabel: '%', placeholder: 'Pick a dye…', options: dyeOptions },
    { key: 'auxiliary_agents', label: 'Auxiliary agents', listId: 'sample-aux-agents-list', quantityKey: 'gpl', quantityLabel: 'g/L', placeholder: 'Pick an agent…', options: auxOptions },
    { key: 'basic_chemicals', label: 'Basic chemicals', listId: 'sample-basic-chemicals-list', quantityKey: 'gpl', quantityLabel: 'g/L', placeholder: 'Pick a chemical…', options: auxOptions },
    { key: 'post_treatment', label: 'Post-treatment', listId: 'sample-post-treatment-list', quantityKey: 'gpl', quantityLabel: 'g/L', placeholder: 'Pick a treatment…', options: auxOptions },
];
const yarnOptions = computed(() => (props.materials || []).filter((m) => String(m.category || '').toLowerCase() === 'yarn'));
const materialOptionLabel = (m) => `${m.display_name || m.name} (${m.mat_id})`;

const syncMaterialId = (row, pool) => {
    const hit = findMaterialByName(pool, row.name);
    row.material_id = hit ? hit.id : '';
    if (hit) row.name = hit.display_name || hit.name;
};

const findMaterialByName = (pool, typedName) => {
    const typed = String(typedName || '').trim().toLowerCase();
    if (!typed) return null;
    // Datalist picks "Name (MAT-003)" — match the bare name too.
    const bare = typed.replace(/\s*\([^)]*\)\s*$/, '');
    const exact = (pool || []).filter((m) => String(m.display_name || m.name || '').toLowerCase() === bare);
    if (exact.length === 1) return exact[0];
    const base = (pool || []).filter((m) => String(m.name || '').toLowerCase() === bare);
    return base.length === 1 ? base[0] : null;
};

const rowUnmatched = (row) => String(row.name || '').trim() !== '' && !row.material_id;
const unmatchedIn = (form) => [...(form.dyestuffs || []), ...(form.auxiliaries || [])].filter(rowUnmatched);
const trialUnmatched = computed(() => unmatchedIn(trialForm));
const sampleUnmatched = computed(() => ['dyestuffs', 'auxiliary_agents', 'basic_chemicals', 'post_treatment'].flatMap((key) => sampleForm[key] || []).filter(rowUnmatched));

// Drop fully-empty rows so untouched blank lines never trip validation.
const stripEmptyRows = (data) => {
    const clean = (rows, qtyKey) => (rows || []).filter((r) => r.material_id || String(r.name || '').trim() !== '' || Number(r[qtyKey] ?? 0) > 0);
    const out = { ...data, dyestuffs: clean(data.dyestuffs, 'pct'), auxiliaries: clean(data.auxiliaries, 'gpl') };
    for (const key of ['auxiliary_agents', 'basic_chemicals', 'post_treatment']) {
        if (data[key] !== undefined) out[key] = clean(data[key], 'gpl');
    }
    if (data.yarns !== undefined) out.yarns = clean(data.yarns, 'qty');
    return out;
};

const statusBadge = (s) => s === 'approved'
    ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    : s === 'rejected' ? 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30'
    : ['pending', 'awaiting_crm_review', 'crm_adjustment_review'].includes(s) ? 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'
    : 'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30';
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Color Request" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><FlaskConical class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> MAN · Color Request</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Color Request</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">{{ dips?.length ?? 0 }} open dip requests · trial iterations below</p>
                        </div>
                        <!-- Pantone chip strip: decorative, uses the real library colors -->
                        <div v-if="pantoneReferences?.length" class="hidden md:flex items-center -space-x-2" aria-hidden="true">
                            <span v-for="p in pantoneReferences.slice(0, 6)" :key="p.id" class="h-9 w-9 rounded-full ring-2 ring-white/70 shadow-lg" :style="{ backgroundColor: pantoneHex(p) }" />
                        </div>
                        <button v-if="canEditProduction" @click="showDipForm = !showDipForm"
                            class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition flex items-center gap-1.5">
                            <PlusCircle class="h-4 w-4" /> New Dip Request
                        </button>
                    </div>
                </div>

                <Transition name="modal">
                    <div v-if="showDipForm" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4"><h2 class="text-lg font-black text-white">Log Lab Dip Request</h2></div>
                        <form @submit.prevent="submitDip" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Job Order (optional)</label>
                                <select v-model="dipForm.sales_order_id" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">None</option>
                                    <option v-for="o in jobOrders" :key="o.id" :value="o.id">{{ o.jo_number }} · {{ o.color }}</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Urgency *</label>
                                <select v-model="dipForm.urgency" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Customer Ref</label>
                                <input v-model="dipForm.customer_ref" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Pantone Code</label>
                                <div class="relative">
                                    <input v-model="dipForm.pantone_code" placeholder="e.g. PANTONE 19-4052" :class="dipPantoneMatch ? 'pr-14' : ''" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                                    <span v-if="dipPantoneMatch" class="pointer-events-none absolute right-2.5 top-1/2 h-8 w-8 -translate-y-1/2 rounded-xl ring-1 ring-black/10 shadow-inner" :style="{ backgroundColor: pantoneHex(dipPantoneMatch) }" :title="dipPantoneMatch.name" />
                                </div></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">RGB / Lab Values</label>
                                <input v-model="dipForm.rgb_lab_values" placeholder="e.g. L* 32.4 a* 4.1 b* -28.9" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Swatch ID</label>
                                <input v-model="dipForm.swatch_id" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Fabric Details</label>
                                <input v-model="dipForm.fabric_details" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Remarks</label>
                                <textarea v-model="dipForm.remarks" rows="2" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                            <div class="sm:col-span-2 flex gap-2">
                                <button v-if="canEditProduction" type="submit" :disabled="dipForm.processing || !canEditProduction" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Save Request</button>
                                <button type="button" @click="showDipForm = false" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-gray-300 transition active:scale-95">Cancel</button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div class="animate-fade-up flex items-center gap-3 px-2 pt-2">
                    <h2 class="text-sm font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Client Color Requests</h2>
                    <span class="rounded-full bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 text-[11px] font-black px-2.5 py-1">{{ openColorCount }} colors open</span>
                </div>

                <div v-if="!(sampleRequests?.length)" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-700 px-6 py-8 text-center text-sm text-gray-400 font-bold">
                    No color requests are waiting for the Dyeing Lab Chemist.
                </div>

                <!-- NOTE: no overflow-hidden here so the Pantone dropdown can extend past the card edge -->
                <div v-for="s in sampleRequests" :key="s.id" class="relative bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm" :class="pantoneOpen === s.id || yarnDropdownOpen?.startsWith(`${s.id}:`) ? 'z-30' : ''">
                    <!-- Color-coded accent edge: shows the linked Pantone at a glance -->
                    <div class="absolute inset-x-0 top-0 h-1.5 rounded-t-3xl" :style="{ backgroundColor: s.pantone_colors?.[0]?.hex || s.pantone_reference?.hex || 'transparent' }" />
                    <div class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 via-emerald-600 to-green-600 text-white shadow-lg"><FlaskConical class="h-4 w-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-xs font-bold">{{ s.code }} <span class="font-sans text-[11px] text-gray-400">· {{ s.urgency }} · {{ s.fabric_name }}</span></p>
                            <p class="text-xs text-gray-500 truncate">Request {{ s.inquiry?.hash_key || s.inquiry_id }} · {{ s.inquiry?.client?.company_name ?? 'Client' }} · {{ s.product?.name || s.fabric_name }} · {{ s.color_description }}</p>
                        </div>
                        <span :class="statusBadge(s.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">{{ String(s.status).replace('_', ' ') }}</span>
                        <div class="flex gap-1.5">
                            <button v-if="s.status === 'requested' && canEditProduction" @click="startSample(s)" class="text-[11px] font-black text-teal-700 hover:underline">Start</button>
                            <button v-if="['requested', 'in_progress', 'adjustment_requested'].includes(s.status) && canEditProduction" @click="openSampleFormulate(s)" class="inline-flex items-center gap-1 text-[11px] font-black text-indigo-700 hover:underline">{{ s.status === 'adjustment_requested' ? 'Reformulate' : 'Formulate' }} <ArrowRight class="h-3 w-3" /></button>
                        </div>
                    </div>
                    <div v-if="relatedColors(s).length > 1" class="px-6 pt-3">
                        <p class="mb-1.5 text-[10px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400">All colors for this fabric request · {{ relatedColors(s).length }} colors</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="color in relatedColors(s)" :key="color.id" class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-[11px]" :class="color.id === s.id ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-800 dark:text-indigo-200' : 'border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-300'">
                                <span class="h-4 w-4 rounded-md ring-1 ring-black/10" :style="{ backgroundColor: color.pantone_reference?.hex || '#e2e8f0' }" />{{ color.pantone_reference?.name || color.color_description }}
                            </span>
                        </div>
                    </div>

                    <article v-for="(color, colorIndex) in s.pantone_colors" :key="`${s.id}-pantone-${colorIndex}`" class="mx-6 my-3 rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white/80 dark:bg-zinc-900/70 p-4 shadow-sm">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="h-10 w-10 shrink-0 rounded-xl border border-black/10 shadow-inner" :style="{ backgroundColor: color.color_hex || color.hex || '#e2e8f0' }" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ color.pantone_name || color.label }}</p>
                                <p v-if="color.pantone_code" class="font-mono text-[11px] text-slate-500 dark:text-zinc-400">{{ color.pantone_code }}</p>
                                <p v-if="color.texture || color.gsm" class="text-[11px] text-slate-500 dark:text-zinc-400">
                                    <span v-if="color.texture">Texture: {{ color.texture }}</span>
                                    <span v-if="color.texture && color.gsm"> · </span>
                                    <span v-if="color.gsm">{{ color.gsm }} GSM</span>
                                </p>
                                <a v-if="color.color_image_path" :href="`/storage/${color.color_image_path}`" target="_blank" class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 underline dark:text-indigo-300">
                                    <img :src="`/storage/${color.color_image_path}`" alt="Client color reference" class="h-8 w-8 rounded border border-slate-200 object-cover dark:border-zinc-700" />
                                    View client color image
                                </a>
                            </div>
                            <div class="relative" data-pantone-picker @keydown.esc="pantoneOpen = null">
                                <button type="button" :disabled="!canEditProduction" @click="togglePantone(s, colorIndex)" aria-haspopup="listbox"
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-left text-xs font-semibold text-slate-700 dark:text-zinc-200 shadow-sm hover:border-indigo-300 disabled:opacity-60">
                                    <span class="h-5 w-5 rounded-md ring-1 ring-black/10" :style="{ backgroundColor: color.hex || '#e2e8f0' }" />
                                    {{ color.pantone_reference_id ? 'Change Pantone formula' : 'Link Pantone formula' }}
                                    <ChevronDown class="h-3.5 w-3.5" />
                                </button>
                                <Transition name="pop-down">
                                    <div v-if="pantoneOpen === pantonePickerKey(s, colorIndex)" class="absolute right-0 top-full z-40 mt-2 w-[22rem] max-w-[85vw] rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3 shadow-2xl">
                                        <div class="relative mb-2.5">
                                            <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                            <input :id="`pantone-search-${s.id}-${colorIndex}`" v-model="pantoneSearch" type="text" autocomplete="off" placeholder="Search Pantone by name or hex" class="w-full rounded-xl border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 py-2 pl-9 pr-3 text-xs outline-none focus:ring-2 focus:ring-indigo-500" />
                                        </div>
                                        <div role="listbox" class="grid max-h-72 grid-cols-2 gap-2 overflow-y-auto pr-1">
                                            <button v-for="pantone in filteredPantones" :key="pantone.id" type="button" role="option" :aria-selected="Number(pendingPantoneSelections[pantonePickerKey(s, colorIndex)]?.pantone_reference_id || color.pantone_reference_id) === Number(pantone.id)" @click="pickPantone(s, colorIndex, pantone.id)" class="overflow-hidden rounded-xl border text-left hover:shadow-md" :class="Number(pendingPantoneSelections[pantonePickerKey(s, colorIndex)]?.pantone_reference_id || color.pantone_reference_id) === Number(pantone.id) ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-slate-200 dark:border-zinc-700'">
                                                <span class="block h-10" :style="{ backgroundColor: pantoneHex(pantone) }" />
                                                <span class="block truncate px-2 py-1.5 text-[11px] font-semibold text-slate-800 dark:text-zinc-100">{{ pantone.name }}</span>
                                            </button>
                                        </div>
                                        <p v-if="!filteredPantones.length" class="py-4 text-center text-xs text-slate-400">No matching Pantone colors.</p>
                                    </div>
                                </Transition>
                            </div>
                            <div v-if="pendingPantoneSelections[pantonePickerKey(s, colorIndex)] || color.pantone_reference_id" class="relative min-w-[13rem] space-y-1 text-[10px] font-bold text-slate-500 dark:text-zinc-400" data-yarn-picker>
                                Yarn / fabric type for formula
                                <button type="button" :disabled="!canEditProduction" @click="toggleYarnDropdown(s, colorIndex)" :aria-expanded="yarnDropdownOpen === pantonePickerKey(s, colorIndex)" class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 text-left text-xs font-semibold text-slate-700 disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                    <span class="truncate">{{ selectedYarnOption(s, colorIndex, color) ? materialOptionLabel(selectedYarnOption(s, colorIndex, color)) : 'Select the correct yarn type' }}</span>
                                    <ChevronDown class="h-3.5 w-3.5 shrink-0" />
                                </button>
                                <div v-if="yarnDropdownOpen === pantonePickerKey(s, colorIndex)" class="absolute left-0 top-full z-50 mt-1 w-full min-w-[18rem] rounded-xl border border-slate-200 bg-white p-2.5 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                                    <input v-model="yarnSearches[pantonePickerKey(s, colorIndex)]" type="search" placeholder="Search yarn type or code" class="mb-2 block w-full rounded-lg border-slate-200 bg-slate-50 px-3 py-2 text-xs font-normal text-slate-700 placeholder:text-slate-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                                    <div class="max-h-56 space-y-1 overflow-y-auto">
                                        <button v-for="yarn in filteredYarnOptions(s, colorIndex)" :key="yarn.id" type="button" @click="choosePantoneYarn(s, colorIndex, color, yarn.id)" class="block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50 dark:text-zinc-200 dark:hover:bg-indigo-500/10">{{ materialOptionLabel(yarn) }}</button>
                                        <p v-if="!filteredYarnOptions(s, colorIndex).length" class="px-3 py-3 text-center text-xs font-normal text-slate-400">No matching yarn types.</p>
                                    </div>
                                </div>
                            </div>
                            <button v-if="color.pantone_reference_id && canEditProduction" type="button" @click="unlinkPantone(s, colorIndex)" :aria-label="`Remove Pantone link for ${color.pantone_name || color.label}`" title="Remove linked Pantone formula" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-950/40">
                                <Trash2 class="h-3.5 w-3.5" /> Remove link
                            </button>
                            <button v-if="color.pantone_reference_id && color.yarn_material_id && canEditProduction" type="button" @click="openKnitRequest(s, color, colorIndex)" class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-teal-700">
                                <Scissors class="h-3.5 w-3.5" /> Request Fabric
                            </button>
                        </div>
                        <div class="mt-3 rounded-xl bg-indigo-50/70 dark:bg-indigo-500/5 p-3">
                            <p class="text-[10px] font-black uppercase tracking-wide text-indigo-800 dark:text-indigo-200">Formula for {{ color.pantone_name || color.label }}<span v-if="color.yarn_type"> · {{ color.yarn_type }}</span></p>
                            <div v-if="formulaSections(s, color).length" class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                <div v-for="section in formulaSections(s, color)" :key="section.label" class="rounded-lg bg-white/80 dark:bg-zinc-900/80 p-2.5">
                                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ section.label }}</p>
                                    <ul class="mt-1 space-y-1">
                                        <li v-for="(row, index) in section.rows" :key="row.material_id || row.name || index" class="flex justify-between gap-2 text-[11px] text-slate-700 dark:text-zinc-200">
                                            <span class="min-w-0 truncate">{{ row.name || row.material_name || 'Material' }}</span><span class="shrink-0 font-semibold">{{ formulaAmount(row, section) }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <p v-else class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Link this Pantone to load its fabric-specific formula.</p>
                        </div>
                    </article>

                    <div v-if="false" class="px-6 pt-3 flex flex-wrap items-center gap-3">
                        <!-- Pantone swatch picker -->
                        <div class="relative" data-pantone-picker @keydown.esc="pantoneOpen = null">
                            <button type="button" :disabled="!canEditProduction" @click="togglePantone(s)" aria-haspopup="listbox" :aria-expanded="pantoneOpen === s.id"
                                class="group flex items-center gap-3 rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1.5 pl-1.5 pr-3 text-left shadow-sm transition hover:border-indigo-300 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                                :class="pantoneOpen === s.id ? 'border-indigo-400 ring-2 ring-indigo-200 dark:ring-indigo-500/30' : ''">
                                <span v-if="s.pantone_reference" class="h-10 w-10 shrink-0 rounded-xl ring-1 ring-black/10 shadow-inner" :style="{ backgroundColor: pantoneHex(s.pantone_reference) }" />
                                <span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border-2 border-dashed border-slate-300 dark:border-zinc-600 text-slate-400"><Palette class="h-4 w-4" /></span>
                                <span class="min-w-0 leading-tight">
                                    <span class="block text-[10px] font-bold text-slate-400 dark:text-zinc-500">Library color</span>
                                    <span class="block max-w-[13rem] truncate text-xs font-black text-slate-800 dark:text-zinc-100">{{ s.pantone_reference?.name || 'Choose Pantone' }}</span>
                                    <span v-if="s.pantone_reference?.hex" class="block font-mono text-[10px] text-slate-400">{{ s.pantone_reference.hex }}</span>
                                </span>
                                <ChevronDown class="ml-1 h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="pantoneOpen === s.id ? 'rotate-180' : ''" />
                            </button>

                            <Transition name="pop-down">
                                <div v-if="pantoneOpen === s.id" class="absolute left-0 top-full z-30 mt-2 w-[22rem] max-w-[85vw] rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3 shadow-2xl shadow-indigo-900/10">
                                    <div class="relative mb-2.5">
                                        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                        <input :id="`pantone-search-${s.id}`" v-model="pantoneSearch" type="text" autocomplete="off" placeholder="Search by name or hex…"
                                            class="w-full rounded-xl border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 py-2 pl-9 pr-3 text-xs outline-none focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <div role="listbox" class="grid max-h-72 grid-cols-2 gap-2 overflow-y-auto pr-1">
                                        <button type="button" role="option" :aria-selected="!s.pantone_reference_id" @click="pickPantone(s, '')"
                                            class="flex flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-slate-200 dark:border-zinc-700 px-2 py-3 text-[11px] font-bold text-slate-500 transition hover:border-rose-300 hover:text-rose-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                            <X class="h-4 w-4" /> No color
                                        </button>
                                        <button v-for="p in filteredPantones" :key="p.id" type="button" role="option" :aria-selected="Number(s.pantone_reference_id) === Number(p.id)" @click="pickPantone(s, p.id)"
                                            class="group/chip relative overflow-hidden rounded-xl border bg-white dark:bg-zinc-800 text-left transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                            :class="Number(s.pantone_reference_id) === Number(p.id) ? 'border-indigo-500 ring-2 ring-indigo-200 dark:ring-indigo-500/30' : 'border-slate-200 dark:border-zinc-700'">
                                            <span class="block h-12 w-full" :style="{ backgroundColor: pantoneHex(p) }" />
                                            <span class="block px-2 py-1.5">
                                                <span class="block truncate text-[11px] font-black text-slate-800 dark:text-zinc-100">{{ p.name }}</span>
                                                <span class="block font-mono text-[10px] text-slate-400">{{ p.hex || 'no hex' }}</span>
                                            </span>
                                            <span v-if="Number(s.pantone_reference_id) === Number(p.id)" class="absolute right-1.5 top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-white text-indigo-600 shadow ring-1 ring-black/10"><Check class="h-3 w-3" /></span>
                                        </button>
                                    </div>
                                    <p v-if="!filteredPantones.length" class="py-4 text-center text-xs font-bold text-slate-400">No Pantone colors match “{{ pantoneSearch }}”.</p>
                                </div>
                            </Transition>
                        </div>
                        <a v-if="s.client_reference_path" :href="`/storage/${s.client_reference_path}`" target="_blank" class="text-xs font-semibold text-indigo-600 underline">Open client reference</a>
                    </div>

                    <section v-if="false" class="mx-6 mt-3 rounded-2xl border border-indigo-100 dark:border-indigo-500/20 bg-indigo-50/50 dark:bg-indigo-500/5 p-4" aria-label="Pantone formula for this color">
                        <div class="flex flex-wrap items-center gap-2">
                            <span v-if="s.pantone_reference?.hex" class="h-6 w-6 rounded-lg ring-1 ring-black/10 shadow-inner" :style="{ backgroundColor: s.pantone_reference.hex }" />
                            <h3 class="text-xs font-black uppercase tracking-wide text-indigo-800 dark:text-indigo-200">{{ s.pantone_reference?.name || s.color_description }} · formula</h3>
                            <span class="text-[10px] text-indigo-600 dark:text-indigo-300">Saved for this fabric color request</span>
                        </div>
                        <div v-if="formulaSections(s).length" class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                            <div v-for="section in formulaSections(s)" :key="section.label" class="rounded-xl bg-white/80 dark:bg-zinc-900/80 p-3">
                                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ section.label }}</p>
                                <ul class="mt-1.5 space-y-1">
                                    <li v-for="(row, index) in section.rows" :key="row.material_id || row.name || index" class="flex justify-between gap-2 text-xs text-slate-700 dark:text-zinc-200">
                                        <span class="min-w-0 truncate">{{ row.name || row.material_name || 'Material' }}</span><span class="shrink-0 font-semibold">{{ formulaAmount(row, section) }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-xs text-slate-500 dark:text-zinc-400">No Pantone formula is linked yet. Choose a library color above to load its fabric formula.</p>
                    </section>
                    <div v-if="s.notes" class="px-6 pt-3 text-xs text-gray-500 italic">Client notes: {{ s.notes }}</div>
                    <div v-if="s.adjustment_notes" class="mx-6 mt-3 rounded-2xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 px-4 py-2.5 text-xs text-amber-800 dark:text-amber-200">
                        <span class="font-black uppercase tracking-widest text-[10px]">Client adjustment requested:</span>
                        <span class="italic"> {{ s.adjustment_notes }}</span>
                    </div>
                    <div v-if="s.formula" class="px-6 pt-3 text-xs text-gray-500">
                        Formula: {{ formulationSummary(s.formula) || '—' }}
                    </div>
                    <section v-if="s.formulations?.length" class="mx-6 mb-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="mb-3 flex items-center justify-between gap-3"><div><h3 class="text-xs font-black uppercase tracking-wide text-slate-800 dark:text-zinc-100">Formulation history</h3><p class="mt-1 text-[11px] text-slate-500">Each saved attempt stays attached to this client color request.</p></div><span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[10px] font-black text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-200">{{ s.formulations.length }} version(s)</span></div>
                        <div class="space-y-2"><article v-for="attempt in s.formulations" :key="attempt.id" class="rounded-xl border border-slate-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"><div class="flex flex-wrap items-center justify-between gap-2"><div><p class="text-xs font-black text-slate-900 dark:text-white">Version {{ attempt.version }} <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 font-bold capitalize text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ String(attempt.status).replaceAll('_', ' ') }}</span></p><p class="mt-1 text-[11px] text-slate-500">{{ attempt.submitter?.name || 'Lab chemist' }} · {{ attempt.created_at }}</p></div><a :href="`/storage/${attempt.attachment_path}`" target="_blank" class="max-w-56 truncate text-xs font-bold text-indigo-600 underline dark:text-indigo-300">{{ attempt.attachment_name }}</a></div><p class="mt-2 text-xs text-slate-600 dark:text-zinc-300">{{ formulationSummary(attempt.formula) || 'No chemicals listed' }}</p><p v-if="attempt.review_notes" class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">CRM note: {{ attempt.review_notes }}</p></article></div>
                    </section>
                    <div class="h-4" />

                    <!-- Formulate form -->
                    <Teleport to="body">
                    <div v-if="sampleExpanded === s.id" class="fixed inset-0 z-[140] flex items-center justify-center bg-slate-950/60 p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="sample-formulation-title" @click.self="!sampleForm.processing && (sampleExpanded = null)">
                    <form @submit.prevent="submitSampleFormulate" class="flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900">
                        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 bg-gradient-to-r from-indigo-700 to-violet-700 px-5 py-4 text-white dark:border-zinc-800 sm:px-7">
                            <div><p class="text-[10px] font-black uppercase tracking-[0.18em] text-indigo-100">CRM review · {{ s.code }}</p><h2 id="sample-formulation-title" class="mt-1 text-xl font-black">Formulate {{ s.fabric_name }}</h2><p class="mt-1 text-xs text-indigo-100">Pantone: {{ formulationPantone?.pantone_name || formulationPantone?.label || s.color_description }}</p></div>
                            <button type="button" @click="sampleExpanded = null" class="rounded-xl bg-white/15 p-2 text-white hover:bg-white/25" aria-label="Close formulation modal"><X class="h-5 w-5" /></button>
                        </header>
                        <div class="min-h-0 space-y-5 overflow-y-auto p-5 sm:p-7">
                        <section class="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-4 dark:border-indigo-900/60 dark:bg-indigo-950/30">
                            <div class="flex flex-wrap items-center justify-between gap-2"><div><h3 class="text-xs font-black uppercase tracking-wide text-indigo-900 dark:text-indigo-100">Default Pantone formula</h3><p class="mt-1 text-xs text-indigo-700 dark:text-indigo-300">Starting reference for {{ formulationPantone?.pantone_name || formulationPantone?.label || s.color_description }}. Adjusted values are entered below.</p></div><span v-if="formulationPantone?.hex" class="h-7 w-7 rounded-lg border border-black/10" :style="{ backgroundColor: formulationPantone.hex }" /></div>
                            <div v-if="formulaSections(s, formulationPantone).length" class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4"><div v-for="section in formulaSections(s, formulationPantone)" :key="section.label" class="rounded-xl bg-white p-3 dark:bg-zinc-900"><p class="text-[10px] font-black uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ section.label }}</p><ul class="mt-2 space-y-1"><li v-for="(row, index) in section.rows" :key="row.material_id || row.name || index" class="flex justify-between gap-2 text-xs"><span class="truncate text-slate-700 dark:text-zinc-200">{{ row.name || row.material_name || 'Material' }}</span><span class="shrink-0 font-bold text-slate-600 dark:text-zinc-300">{{ formulaAmount(row, section) }}</span></li></ul></div></div>
                            <p v-else class="mt-3 rounded-xl bg-white/80 p-3 text-xs text-amber-800 dark:bg-zinc-900 dark:text-amber-200">No default formula is linked to this Pantone yet. Add the formula values below before submitting.</p>
                        </section>
                        <div class="rounded-2xl border border-slate-200 p-4 dark:border-zinc-700"><p class="mb-3 text-xs font-black uppercase tracking-wide text-slate-700 dark:text-zinc-200">Adjusted formulation</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <section v-for="group in formulationInputGroups" :key="group.key" class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-800/60">
                                <label class="mb-2 block text-xs font-black uppercase tracking-wide text-gray-600 dark:text-zinc-300">{{ group.label }}</label>
                                <div v-for="(row, i) in sampleForm[group.key]" :key="i" class="mb-1.5 flex gap-1.5">
                                    <input v-model="row.name" :list="group.listId" :placeholder="group.placeholder" autocomplete="off" @input="syncMaterialId(row, group.options)" :class="rowUnmatched(row) ? 'ring-2 ring-rose-400 border-rose-400' : ''" class="min-w-0 flex-1 rounded-xl border-gray-200 bg-white p-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800" />
                                    <input v-model="row[group.quantityKey]" :placeholder="group.quantityLabel" type="number" min="0" step="0.01" :aria-label="`${group.label} amount`" class="w-20 rounded-xl border-gray-200 bg-white p-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800" />
                                    <button type="button" @click="removeSampleRow(sampleForm[group.key], i)" class="px-1 font-black text-rose-500" :aria-label="`Remove ${group.label} row`">×</button>
                                </div>
                                <datalist :id="group.listId"><option v-for="m in group.options.value" :key="m.id" :value="materialOptionLabel(m)" /></datalist>
                                <button type="button" @click="addSampleRow(sampleForm[group.key])" class="text-[11px] font-black text-indigo-600 hover:underline">+ Add {{ group.label.toLowerCase() }}</button>
                            </section>
                        </div>
                        <div class="mt-4"><label class="mb-1 block text-xs font-black uppercase tracking-wide text-gray-500">Notes</label>
                            <textarea v-model="sampleForm.notes" rows="2" class="w-full rounded-xl border-gray-200 bg-white p-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800"></textarea></div>
                        </div>
                        <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1">Attach sample image or PDF *</label>
                            <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required @input="sampleForm.sample_photo = $event.target.files[0]"
                                class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white file:px-3 file:py-1.5 file:text-xs file:font-black" /></div>
                        <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4 dark:border-zinc-800">
                            <button v-if="canEditProduction" type="submit" :disabled="sampleForm.processing || sampleUnmatched.length > 0" title="Names must be picked from inventory suggestions" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">{{ sampleForm.processing ? 'Saving…' : 'Save & send to CRM review' }}</button>
                            <button type="button" :disabled="sampleForm.processing" @click="sampleExpanded = null" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-gray-300 transition active:scale-95 disabled:opacity-50">Cancel</button>
                        </div>
                        <p v-if="sampleUnmatched.length" class="text-xs font-bold text-rose-600">“{{ sampleUnmatched[0].name }}” is not in inventory — pick a suggested material so the recipe stays connected to stock data.</p>
                        </div>
                    </form>
                    </div>
                    </Teleport>
                </div>

                <div class="animate-fade-up flex items-center gap-3 px-2 pt-4">
                    <h2 class="text-sm font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Lab Dip Requests</h2>
                </div>

                <div v-for="dip in dips" :key="dip.id" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-zinc-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 text-white shadow-lg"><Beaker class="h-4 w-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-xs font-bold">{{ dip.code }} <span class="font-sans text-[11px] text-gray-400">· {{ dip.urgency }} · {{ dip.pantone_code ?? dip.customer_ref ?? 'no ref' }}</span></p>
                            <p class="text-xs text-gray-500">{{ dip.trials?.length ?? 0 }} trial(s)</p>
                        </div>
                        <span :class="statusBadge(dip.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">{{ dip.status.replace('_', ' ') }}</span>
                        <div class="flex gap-1.5">
                            <button v-if="dip.status === 'pending' && canEditProduction" @click="advanceDip(dip, 'in_progress')" class="text-[11px] font-black text-indigo-700 hover:underline">Start</button>
                            <button v-if="dip.status === 'in_progress' && canEditProduction" @click="advanceDip(dip, 'awaiting_spectro')" class="text-[11px] font-black text-indigo-700 hover:underline">To Spectro</button>
                            <button v-if="dip.status === 'awaiting_spectro' && canEditProduction" @click="advanceDip(dip, 'approved')" class="text-[11px] font-black text-emerald-700 hover:underline">Approve</button>
                            <button v-if="['in_progress', 'awaiting_spectro'].includes(dip.status) && canEditProduction" @click="advanceDip(dip, 'rejected')" class="text-[11px] font-black text-rose-500 hover:underline">Reject</button>
                            <button v-if="canEditProduction" @click="openTrial(dip)" class="inline-flex items-center gap-1 text-[11px] font-black text-indigo-700 hover:underline">Trial <ArrowRight class="h-3 w-3" /></button>
                        </div>
                    </div>
                    <div class="px-6 py-3 space-y-2">
                        <div v-for="t in dip.trials" :key="t.id" class="rounded-2xl border border-gray-100 dark:border-zinc-800 px-4 py-2.5 text-sm flex flex-wrap gap-x-4 gap-y-1 items-center">
                            <span class="font-mono font-bold text-xs">Trial {{ t.trial_no }}</span>
                            <span class="text-xs text-gray-500">{{ (t.formula?.dyestuffs ?? []).map(d => `${d.name} ${d.pct ?? ''}%`).join(' · ') || 'no formula' }}</span>
                            <span v-if="t.delta_e" class="text-xs font-bold text-indigo-600">ΔE {{ t.delta_e }}</span>
                            <span :class="statusBadge(t.status === 'passed' ? 'approved' : t.status === 'failed' ? 'rejected' : 'pending')" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase ring-1">{{ t.status }}</span>
                            <span v-if="t.adjustments" class="text-xs text-gray-400 w-full">{{ t.adjustments }}</span>
                        </div>
                        <p v-if="!dip.trials?.length" class="text-xs text-gray-400 py-1">No trials yet — record the first one.</p>
                    </div>
                    <Transition name="modal">
                        <div v-if="expanded === dip.id" class="border-t border-gray-100 dark:border-zinc-800 bg-gray-50/60 dark:bg-zinc-800/40 p-6">
                            <div class="flex items-center gap-2 mb-3">
                                <h3 class="text-sm font-black">Record Trial {{ (dip.trials?.length ?? 0) + 1 }}</h3>
                                <button @click="expanded = null" class="ml-auto text-gray-400 hover:text-gray-600"><X class="h-4 w-4" /></button>
                            </div>
                            <form @submit.prevent="submitTrial" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Dyestuffs (%OWF)</label>
                                    <div v-for="(row, i) in trialForm.dyestuffs" :key="i" class="flex gap-2 mb-2">
                                        <input v-model="row.name" list="trial-dye-list" placeholder="Pick a dye…" autocomplete="off" @input="syncMaterialId(row, dyeOptions)" :class="rowUnmatched(row) ? 'ring-2 ring-rose-400 border-rose-400' : ''" class="flex-1 rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                                        <input v-model="row.pct" type="number" step="0.01" min="0" placeholder="%" class="w-24 rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                                        <button type="button" @click="removeRow(trialForm.dyestuffs, i)" class="text-rose-500 px-2"><X class="h-4 w-4" /></button>
                                    </div>
                                    <datalist id="trial-dye-list"><option v-for="m in dyeOptions" :key="m.id" :value="materialOptionLabel(m)" /></datalist>
                                    <button type="button" @click="addRow(trialForm.dyestuffs)" class="text-[11px] font-black text-indigo-700 hover:underline">+ Add dyestuff</button>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Auxiliaries (g/L)</label>
                                    <div v-for="(row, i) in trialForm.auxiliaries" :key="i" class="flex gap-2 mb-2">
                                        <input v-model="row.name" list="trial-aux-list" placeholder="Pick a chemical…" autocomplete="off" @input="syncMaterialId(row, auxOptions)" :class="rowUnmatched(row) ? 'ring-2 ring-rose-400 border-rose-400' : ''" class="flex-1 rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                                        <input v-model="row.gpl" type="number" step="0.01" min="0" placeholder="g/L" class="w-24 rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                                        <button type="button" @click="removeRow(trialForm.auxiliaries, i)" class="text-rose-500 px-2"><X class="h-4 w-4" /></button>
                                    </div>
                                    <datalist id="trial-aux-list"><option v-for="m in auxOptions" :key="m.id" :value="materialOptionLabel(m)" /></datalist>
                                    <button type="button" @click="addRow(trialForm.auxiliaries)" class="text-[11px] font-black text-indigo-700 hover:underline">+ Add auxiliary</button>
                                </div>
                                <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Liquor Ratio</label>
                                    <input v-model="trialForm.liquor_ratio" placeholder="1:10" class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                                <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">ΔE (measured)</label>
                                    <input v-model="trialForm.delta_e" type="number" step="0.01" min="0" class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                                <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Dyeing Curve (steps: temp, rate °C/min, hold, dosing)</label>
                                    <textarea v-model="trialForm.curve" rows="2" placeholder='[{"step":"heat","temp":60,"rate":1.5,"hold":10,"dosing":"salt first"}]' class="w-full font-mono rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2.5 text-xs outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                                <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Adjustments vs Previous Trial</label>
                                    <textarea v-model="trialForm.adjustments" rows="2" class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                                <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Trial Result</label>
                                    <select v-model="trialForm.status" class="w-full rounded-xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="pending">Pending</option><option value="passed">Passed</option><option value="failed">Failed</option>
                                    </select></div>
                                <div class="flex items-end"><button v-if="canEditProduction" type="submit" :disabled="trialForm.processing || !canEditProduction || trialUnmatched.length > 0" title="Names must be picked from inventory suggestions" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Save Trial</button></div>
                                <p v-if="trialUnmatched.length" class="sm:col-span-2 text-xs font-bold text-rose-600">“{{ trialUnmatched[0].name }}” is not in inventory — pick a suggested material so the recipe stays connected to stock data.</p>
                            </form>
                        </div>
                    </Transition>
                </div>
                <div v-if="!dips?.length" class="text-center text-sm text-gray-400 py-8">No open dip requests.</div>
            </div>
        </div>
    </AuthenticatedLayout>
    <div v-if="knitRequestTarget" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4" @click.self="knitRequestTarget = null">
        <form @submit.prevent="submitKnitRequest" class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div><p class="text-xs font-black uppercase tracking-widest text-teal-600">Knitting Sample</p><h2 class="mt-1 text-xl font-black text-slate-900 dark:text-white">Request knit fabric</h2><p class="mt-1 text-xs text-slate-500">{{ knitRequestTarget.code }} · {{ knitRequestTarget.pantone_colors?.[knitRequestForm.color_index]?.pantone_name || knitRequestTarget.pantone_colors?.[knitRequestForm.color_index]?.label }}</p></div>
                <button type="button" @click="knitRequestTarget = null" class="rounded-xl p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-zinc-800"><X class="h-5 w-5" /></button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-xs font-bold text-slate-600 dark:text-zinc-300">Yarn type<input :value="knitRequestTarget.pantone_colors?.[knitRequestForm.color_index]?.yarn_type" readonly class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 text-sm dark:border-zinc-700 dark:bg-zinc-800" /></label>
                <label class="text-xs font-bold text-slate-600 dark:text-zinc-300">Fabric design<input v-model="knitRequestForm.fabric_design" required class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800" /></label>
                <label class="text-xs font-bold text-slate-600 dark:text-zinc-300">Texture<select v-model="knitRequestForm.texture" class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"><option value="">Select texture</option><option>Hard</option><option>Medium</option><option>Soft</option></select></label>
                <label class="text-xs font-bold text-slate-600 dark:text-zinc-300">GSM<input v-model="knitRequestForm.gsm" type="number" min="1" max="2000" class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800" /></label>
                <label class="text-xs font-bold text-slate-600 dark:text-zinc-300">Requested weight (kg, max 1 kg)<input v-model="knitRequestForm.requested_weight" type="number" min="0.01" max="1" step="0.001" required class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800" /></label>
                <div class="rounded-xl bg-slate-50 p-3 text-xs dark:bg-zinc-800"><span class="font-bold">Pantone:</span> {{ knitRequestTarget.pantone_colors?.[knitRequestForm.color_index]?.pantone_name }}<br><span class="font-bold">Color:</span> {{ knitRequestTarget.pantone_colors?.[knitRequestForm.color_index]?.hex }}</div>
            </div>
            <p v-if="knitRequestForm.errors.requested_weight || knitRequestForm.errors.fabric_design" class="mt-3 text-xs font-semibold text-rose-600">{{ knitRequestForm.errors.requested_weight || knitRequestForm.errors.fabric_design }}</p>
            <div class="mt-6 flex justify-end gap-2"><button type="button" @click="knitRequestTarget = null" class="rounded-xl px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button><button type="submit" :disabled="knitRequestForm.processing" class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">{{ knitRequestForm.processing ? 'Sending…' : 'Request Knit Fabric' }}</button></div>
        </form>
    </div>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.pop-down-enter-active, .pop-down-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; transform-origin: top left; }
.pop-down-enter-from, .pop-down-leave-to { opacity: 0; transform: translateY(-6px) scale(0.98); }
@media (prefers-reduced-motion: reduce) {
    .animate-float, .animate-fade-up, .animate-pop { animation: none; }
    .pop-down-enter-active, .pop-down-leave-active { transition: none; }
}
</style>
