<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Scissors, Play, CheckCircle2, CalendarDays, Gauge, Palette, PackageCheck, Weight, X, ChevronDown } from 'lucide-vue-next';
import { holdSuccessFeedback, successModal } from '@/lib/feedback';

const props = defineProps({ requests: Array, discarded: { type: Array, default: () => [] }, machines: Array, recent: Array });
const forms = Object.fromEntries((props.requests || []).map((item) => [item.id, useForm({ machine_id: '', produced_weight: item.requested_weight, remarks: '' })]));
const formFor = (item) => forms[item.id];
const selectedToStart = ref(null);
const queueTab = ref('active');
const expandedQueueGroups = ref({});
const selectedBulkGroup = ref(null);
const bulkForm = useForm({ machine_id: '', items: [] });
const startProcessing = ref(false);
const start = (item) => { selectedToStart.value = item; };
const confirmStart = () => {
    if (!selectedToStart.value || startProcessing.value) return;
    startProcessing.value = true;
    const releaseSuccessFeedback = holdSuccessFeedback();
    router.patch(route('man.staff.knitting-sample.start', selectedToStart.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { selectedToStart.value = null; },
        onFinish: () => {
            startProcessing.value = false;
            // The shared Inertia feedback listener schedules a generic success
            // dialog after the visit finishes; keep it suppressed until that
            // delayed callback has passed for this action.
            window.setTimeout(releaseSuccessFeedback, 100);
        },
    });
};
const complete = (item) => formFor(item).post(route('man.staff.knitting-sample.complete', item.id), {
    preserveScroll: true,
    onSuccess: () => successModal('Record submitted successfully.', { title: 'Record Completed' }),
});
const discard = (item) => {
    if (!window.confirm(`Cancel knit fabric request ${item.code}? It will move to the Discarded tab.`)) return;
    router.patch(route('man.staff.knitting-sample.discard', item.id), {}, { preserveScroll: true });
};
const groupRequests = (items) => {
    const groups = new Map();
    (items || []).forEach((item) => {
        const parent = item.fabric_sample_request || {};
        const inquiry = parent.inquiry || {};
        const pantoneId = parent.pantone_reference_id || item.pantone_name || 'unknown-color';
        const key = [parent.inquiry_id || 'no-inquiry', parent.product_id || 'no-product', pantoneId, item.yarn_material_id || item.yarn_type || 'no-yarn'].join(':');
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                inquiryId: inquiry.hash_key || parent.inquiry_id || 'Unlinked inquiry',
                clientName: inquiry.client?.company_name || 'Business client',
                productName: parent.product?.name || parent.fabric_name || 'Fabric design',
                pantoneName: item.pantone_name || parent.color_description || 'Color not specified',
                pantoneHex: item.pantone_hex || parent.pantone_reference?.hex || '',
                yarnName: item.yarn_type || 'Yarn not specified',
                items: [],
            });
        }
        groups.get(key).items.push(item);
    });
    return [...groups.values()];
};
const activeGroups = computed(() => groupRequests(props.requests));
const discardedGroups = computed(() => groupRequests(props.discarded));
const visibleGroups = computed(() => queueTab.value === 'discarded' ? discardedGroups.value : activeGroups.value);
const statusLabel = (status) => status === 'in_progress' ? 'In progress' : status === 'completed' ? 'Done' : status === 'discarded' ? 'Cancelled' : 'Waiting';
const toggleQueueGroup = (key) => { expandedQueueGroups.value[key] = !expandedQueueGroups.value[key]; };
const canonicalFabricDesign = (item) => {
    const rawDesign = String(item.fabric_design || '').trim();
    const parent = item.fabric_sample_request || {};
    const parentDesign = String(parent.fabric_name || '').trim();
    return rawDesign && rawDesign.toLocaleLowerCase() !== parentDesign.toLocaleLowerCase()
        ? rawDesign
        : (parent.product?.name || rawDesign || parentDesign || 'Fabric design');
};
const bulkGroups = computed(() => {
    const groups = new Map();
    (props.requests || []).forEach((item) => {
        const parent = item.fabric_sample_request || {};
        const design = canonicalFabricDesign(item);
        const gsm = item.gsm === null || item.gsm === '' ? '' : Number(item.gsm).toFixed(3);
        const key = [parent.product_id || 0, design.replace(/\s+/g, ' ').toLocaleLowerCase(), item.yarn_material_id || 0, gsm].join(':');
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                productName: parent.product?.name || parent.fabric_name || design,
                design,
                yarnName: item.yarn_type || 'Yarn not specified',
                gsm: item.gsm,
                items: [],
            });
        }
        groups.get(key).items.push(item);
    });
    return [...groups.values()].filter((group) => group.items.length > 1).map((group) => ({
        ...group,
        colorVariants: [...new Map(group.items.map((item) => [item.pantone_name || item.pantone_hex || item.id, { name: item.pantone_name || 'Color not specified', hex: item.pantone_hex }])).values()],
        inquiryCount: new Set(group.items.map((item) => item.fabric_sample_request?.inquiry_id).filter(Boolean)).size,
    }));
});
const openBulkRecord = (group) => {
    selectedBulkGroup.value = group;
    bulkForm.machine_id = '';
    bulkForm.items = group.items.map((item) => ({
        id: item.id,
        code: item.code,
        pantone_name: item.pantone_name || 'Color not specified',
        pantone_hex: item.pantone_hex,
        requested_weight: item.requested_weight,
        produced_weight: item.requested_weight,
        remarks: '',
    }));
};
const submitBulkRecord = () => {
    if (!selectedBulkGroup.value || bulkForm.processing) return;
    bulkForm.post(route('man.staff.knitting-sample.bulk-complete'), {
        preserveScroll: true,
        onSuccess: () => { selectedBulkGroup.value = null; bulkForm.reset(); },
    });
};
</script>

<template>
    <Head title="Knitting Sample" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-teal-700 to-emerald-600 p-6 text-white shadow-lg"><div class="flex items-center gap-3"><Scissors class="h-7 w-7"/><div><p class="text-xs font-black uppercase tracking-widest text-teal-100">Knitting Department</p><h1 class="text-2xl font-black">Knitting Sample Queue</h1><p class="mt-1 text-sm text-teal-50">Small knit fabric samples requested by the Dyeing Lab Chemist.</p></div></div></header>
            <section class="space-y-4">
                <div class="flex gap-2 border-b border-slate-200 dark:border-zinc-800" role="tablist" aria-label="Knit sample queue status">
                    <button type="button" role="tab" :aria-selected="queueTab === 'active'" @click="queueTab = 'active'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition" :class="queueTab === 'active' ? 'border-teal-600 text-teal-700 dark:text-teal-300' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white'">Active queue <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-zinc-800">{{ requests?.length || 0 }}</span></button>
                    <button type="button" role="tab" :aria-selected="queueTab === 'discarded'" @click="queueTab = 'discarded'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition" :class="queueTab === 'discarded' ? 'border-rose-600 text-rose-700 dark:text-rose-300' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white'">Discarded <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-zinc-800">{{ discarded?.length || 0 }}</span></button>
                </div>
                <div v-if="!visibleGroups.length" class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm font-semibold text-slate-500 dark:border-zinc-700">{{ queueTab === 'discarded' ? 'No knit sample requests have been discarded.' : 'No sample requests are waiting.' }}</div>
                <details v-if="queueTab === 'active' && bulkGroups.length" class="rounded-xl border border-teal-200 bg-teal-50/60 dark:border-teal-900/60 dark:bg-teal-950/20">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-bold text-teal-900 dark:text-teal-100"><span>Bulk record available · {{ bulkGroups.length }} matching group(s)</span><span class="text-xs font-medium text-teal-700 dark:text-teal-300">Same design, yarn, and GSM across colors</span></summary>
                    <div class="space-y-2 border-t border-teal-200 p-3 dark:border-teal-900/60">
                        <div v-for="group in bulkGroups" :key="group.key" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-3 py-2.5 dark:bg-zinc-900">
                            <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ group.productName }} · {{ group.yarnName }}<span v-if="group.gsm"> · {{ group.gsm }} GSM</span></p><p class="mt-0.5 truncate text-xs text-slate-500 dark:text-zinc-400">{{ group.colorVariants.map((color) => color.name).join(' · ') }} · {{ group.items.length }} requests</p></div>
                            <button type="button" @click="openBulkRecord(group)" class="shrink-0 rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-teal-800">Record together</button>
                        </div>
                    </div>
                </details>
                <section v-for="group in visibleGroups" :key="group.key" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <button type="button" :aria-expanded="Boolean(expandedQueueGroups[group.key])" @click="toggleQueueGroup(group.key)" class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-50 dark:hover:bg-zinc-800/60 sm:px-5">
                        <span class="h-8 w-8 shrink-0 rounded-lg border border-black/10 shadow-inner" :style="{ backgroundColor: group.pantoneHex || '#e2e8f0' }" />
                        <span class="min-w-0 flex-1"><span class="block truncate text-xs font-semibold text-slate-600 dark:text-zinc-300">{{ group.clientName }} · Inquiry {{ group.inquiryId }}</span><span class="mt-0.5 block truncate text-sm font-black text-slate-900 dark:text-white">{{ group.productName }}</span><span class="mt-0.5 block truncate text-xs font-semibold text-slate-600 dark:text-zinc-300">{{ group.pantoneName }} · Yarn: {{ group.yarnName }}</span></span>
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ group.items.length }}</span>
                        <ChevronDown class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="expandedQueueGroups[group.key] ? 'rotate-180' : ''" />
                    </button>
                    <div v-if="expandedQueueGroups[group.key]" class="space-y-2 border-t border-slate-100 p-3 dark:border-zinc-800 sm:p-4">
                        <article v-for="item in group.items" :key="item.id" class="rounded-lg border border-slate-200 p-3 dark:border-zinc-700">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="font-mono text-xs font-black text-teal-800 dark:text-teal-300">{{ item.code }}</p><span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="item.status === 'discarded' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300' : item.status === 'in_progress' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-200'">{{ statusLabel(item.status) }}</span></div><p class="mt-1 text-xs text-slate-600 dark:text-zinc-300">{{ item.fabric_design || item.fabric_sample_request?.fabric_name || 'Fabric sample' }} · {{ item.requested_weight }} kg · {{ item.texture || 'Texture unspecified' }}<span v-if="item.gsm"> · {{ item.gsm }} GSM</span></p></div>
                                <div v-if="queueTab === 'active'" class="flex shrink-0 flex-wrap gap-1.5">
                                    <button v-if="item.status === 'requested'" type="button" @click="start(item)" class="inline-flex items-center gap-1.5 rounded-lg bg-teal-700 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-teal-800"><Play class="h-3.5 w-3.5"/>Start</button>
                                    <button v-if="['requested', 'in_progress'].includes(item.status)" type="button" @click="discard(item)" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50 dark:border-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-950/30"><X class="h-3.5 w-3.5"/>Discard</button>
                                </div>
                            </div>
                            <form v-if="item.status === 'in_progress' && queueTab === 'active'" @submit.prevent="complete(item)" class="mt-3 grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-4 dark:border-zinc-800">
                                <label class="text-xs font-bold text-slate-600 dark:text-zinc-300">Knitting machine<select v-model="formFor(item).machine_id" required class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"><option value="">Choose machine</option><option v-for="machine in machines" :key="machine.id" :value="machine.id">{{ machine.machine_no }}</option></select></label>
                                <label class="text-xs font-bold text-slate-600 dark:text-zinc-300">Produced weight (kg)<input v-model="formFor(item).produced_weight" type="number" min="0.001" max="1" step="0.001" required class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"/></label>
                                <label class="text-xs font-bold text-slate-600 dark:text-zinc-300">Remarks<input v-model="formFor(item).remarks" class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"/></label>
                                <button type="submit" :disabled="formFor(item).processing" class="self-end rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"><CheckCircle2 class="mr-1 inline h-4 w-4"/>Record completed</button>
                            </form>
                        </article>
                    </div>
                </section>
            </section>
            <section v-if="recent?.length" class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-teal-700 dark:text-teal-300">Production record</p><h2 class="mt-1 text-xl font-black text-slate-900 dark:text-white">Recently completed</h2><p class="mt-1 text-sm text-slate-500">Completed knit samples with their request, yarn, color, and production details.</p></div><span class="rounded-full border border-teal-200 bg-teal-50 px-3 py-1.5 text-xs font-bold text-teal-800 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200">{{ recent.length }} latest records</span></div>
                <div class="grid gap-4 xl:grid-cols-2">
                    <article v-for="item in recent" :key="item.id" class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-teal-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-teal-800">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-teal-500 via-emerald-500 to-cyan-500" />
                        <div class="space-y-5 p-5 sm:p-6">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-3"><span class="rounded-2xl bg-teal-50 p-3 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300"><PackageCheck class="h-5 w-5" /></span><div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Knit sample request</p><h3 class="mt-1 truncate font-mono text-lg font-black text-slate-900 dark:text-white">{{ item.code }}</h3><p class="mt-1 text-sm font-semibold text-slate-700 dark:text-zinc-200">{{ item.fabric_design || item.fabric_sample_request?.fabric_name || 'Fabric sample' }}</p></div></div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black uppercase text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><CheckCircle2 class="h-3.5 w-3.5"/> Completed</span>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl bg-slate-50 p-3.5 dark:bg-zinc-800/80"><p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider text-slate-400"><Scissors class="h-3.5 w-3.5"/> Yarn</p><p class="mt-1.5 text-sm font-bold text-slate-800 dark:text-zinc-100">{{ item.yarn_type || 'Not specified' }}</p></div>
                                <div class="rounded-2xl bg-slate-50 p-3.5 dark:bg-zinc-800/80"><p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider text-slate-400"><Gauge class="h-3.5 w-3.5"/> Fabric construction</p><p class="mt-1.5 text-sm font-bold text-slate-800 dark:text-zinc-100">{{ item.texture || 'Texture not specified' }}<span v-if="item.gsm"> · {{ item.gsm }} GSM</span><span v-if="item.fabric_sample_request?.urgency"> · {{ item.fabric_sample_request.urgency }}</span></p></div>
                                <div class="rounded-2xl bg-slate-50 p-3.5 dark:bg-zinc-800/80"><p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider text-slate-400"><Palette class="h-3.5 w-3.5"/> Color details</p><p class="mt-1.5 flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-zinc-100"><span v-if="item.pantone_hex" class="h-3.5 w-3.5 shrink-0 rounded-full border border-black/10" :style="{ backgroundColor: item.pantone_hex }"/>{{ item.pantone_name || item.fabric_sample_request?.color_description || 'No color details' }}</p><p v-if="item.fabric_sample_request?.color_description && item.pantone_name" class="mt-1 text-xs text-slate-500">{{ item.fabric_sample_request.color_description }}</p></div>
                                <div class="rounded-2xl bg-slate-50 p-3.5 dark:bg-zinc-800/80"><p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider text-slate-400"><Weight class="h-3.5 w-3.5"/> Sample weight</p><p class="mt-1.5 text-sm font-bold text-slate-800 dark:text-zinc-100">{{ item.produced_weight ?? item.requested_weight }} kg produced <span class="font-medium text-slate-500">· {{ item.requested_weight }} kg requested</span></p></div>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 text-xs dark:border-zinc-800"><div><p class="font-bold text-slate-500">Source fabric request</p><p class="mt-1 font-mono font-black text-teal-700 dark:text-teal-300">{{ item.fabric_sample_request?.code || '—' }}</p></div><div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-slate-500"><span v-if="item.fabric_sample_request?.request_method" class="rounded-full border px-2.5 py-1 font-semibold dark:border-zinc-700">{{ item.fabric_sample_request.request_method }}</span><span v-if="item.machine?.machine_no" class="rounded-full border px-2.5 py-1 font-semibold dark:border-zinc-700">Machine {{ item.machine.machine_no }}</span><span class="inline-flex items-center gap-1"><CalendarDays class="h-3.5 w-3.5"/>{{ item.processed_at ? new Date(item.processed_at).toLocaleString() : 'Completion date unavailable' }}</span></div></div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
        <div v-if="selectedToStart" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="!startProcessing && (selectedToStart = null)">
            <section role="dialog" aria-modal="true" aria-labelledby="start-sample-title" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                <h2 id="start-sample-title" class="text-xl font-black text-slate-900 dark:text-white">Start this sample?</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">Starting <span class="font-mono font-bold">{{ selectedToStart.code }}</span> assigns it to you and changes its status to in progress.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" :disabled="startProcessing" @click="selectedToStart = null" class="rounded-xl border px-4 py-2.5 text-sm font-bold disabled:opacity-50 dark:border-zinc-700">Cancel</button>
                    <button type="button" :disabled="startProcessing" @click="confirmStart" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">{{ startProcessing ? 'Starting…' : 'Confirm start' }}</button>
                </div>
            </section>
        </div>
        <div v-if="selectedBulkGroup" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4" @click.self="!bulkForm.processing && (selectedBulkGroup = null)">
            <section class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900" role="dialog" aria-modal="true" aria-labelledby="bulk-knit-title">
                <header class="border-b border-slate-100 p-5 dark:border-zinc-800"><h2 id="bulk-knit-title" class="text-lg font-black text-slate-900 dark:text-white">Record matching fabric samples</h2><p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">{{ selectedBulkGroup.productName }} · {{ selectedBulkGroup.yarnName }}<span v-if="selectedBulkGroup.gsm"> · {{ selectedBulkGroup.gsm }} GSM</span>. Enter each color’s produced weight and select the Flatbed machine used.</p></header>
                <form class="min-h-0 flex-1 overflow-y-auto p-4" @submit.prevent="submitBulkRecord">
                    <label class="mb-4 block text-sm font-semibold text-slate-700 dark:text-zinc-200">Flatbed Knitting Machine
                        <select v-model="bulkForm.machine_id" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-800"><option value="">Choose assigned machine</option><option v-for="machine in machines" :key="machine.id" :value="machine.id">{{ machine.machine_no }}</option></select>
                    </label>
                    <div class="space-y-3">
                        <article v-for="row in bulkForm.items" :key="row.id" class="grid gap-3 rounded-xl border border-slate-200 p-3 dark:border-zinc-700 sm:grid-cols-[minmax(0,1fr)_10rem]">
                            <div class="flex min-w-0 items-center gap-3"><span class="h-10 w-10 shrink-0 rounded-lg border border-black/10" :style="{ backgroundColor: row.pantone_hex || '#e2e8f0' }"/><div class="min-w-0"><p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ row.pantone_name }}</p><p class="font-mono text-xs text-slate-500 dark:text-zinc-400">{{ row.code }} · {{ row.requested_weight }} kg requested</p></div></div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-zinc-300">Produced weight (kg)<input v-model="row.produced_weight" type="number" min="0.001" max="1" step="0.001" required class="mt-1 w-full rounded-lg border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"/></label>
                            <label class="text-xs font-semibold text-slate-600 dark:text-zinc-300 sm:col-span-2">Remarks <span class="font-normal text-slate-400">(optional)</span><input v-model="row.remarks" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"/></label>
                        </article>
                    </div>
                    <p v-if="Object.values(bulkForm.errors).length" class="mt-3 text-sm font-semibold text-rose-600">{{ Object.values(bulkForm.errors)[0] }}</p>
                    <footer class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-zinc-800"><button type="button" :disabled="bulkForm.processing" @click="selectedBulkGroup = null" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold dark:border-zinc-700">Cancel</button><button type="submit" :disabled="bulkForm.processing || !bulkForm.machine_id" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">{{ bulkForm.processing ? 'Saving…' : `Complete ${bulkForm.items.length} requests` }}</button></footer>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
