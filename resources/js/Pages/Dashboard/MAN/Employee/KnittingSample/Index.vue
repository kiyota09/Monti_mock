<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Scissors, Play, CheckCircle2, CalendarDays, Gauge, Palette, PackageCheck, Weight } from 'lucide-vue-next';
import { holdSuccessFeedback } from '@/lib/feedback';

const props = defineProps({ requests: Array, machines: Array, recent: Array });
const forms = Object.fromEntries((props.requests || []).map((item) => [item.id, useForm({ machine_id: '', produced_weight: item.requested_weight, remarks: '' })]));
const formFor = (item) => forms[item.id];
const selectedToStart = ref(null);
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
const complete = (item) => formFor(item).post(route('man.staff.knitting-sample.complete', item.id), { preserveScroll: true });
</script>

<template>
    <Head title="Knitting Sample" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <header class="rounded-3xl bg-gradient-to-r from-teal-700 to-emerald-600 p-6 text-white shadow-lg"><div class="flex items-center gap-3"><Scissors class="h-7 w-7"/><div><p class="text-xs font-black uppercase tracking-widest text-teal-100">Knitting Department</p><h1 class="text-2xl font-black">Knitting Sample Queue</h1><p class="mt-1 text-sm text-teal-50">Small knit fabric samples requested by the Dyeing Lab Chemist.</p></div></div></header>
            <section class="space-y-4">
                <div v-if="!requests?.length" class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm font-semibold text-slate-500 dark:border-zinc-700">No sample requests are waiting.</div>
                <article v-for="item in requests" :key="item.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-mono text-sm font-black text-teal-700">{{ item.code }} <span class="ml-2 rounded-full bg-amber-100 px-2 py-1 text-[10px] uppercase text-amber-800">{{ item.status.replace('_',' ') }}</span></p><h2 class="mt-2 text-lg font-bold text-slate-900 dark:text-white">{{ item.fabric_design || item.fabric_sample_request?.fabric_name }}</h2><p class="text-sm text-slate-600 dark:text-zinc-300">Parent request: {{ item.fabric_sample_request?.code }} · {{ item.yarn_type }} · {{ item.texture || 'Texture unspecified' }} · {{ item.gsm || '—' }} GSM</p><p class="mt-1 text-sm font-semibold text-slate-700 dark:text-zinc-200">{{ item.pantone_name }} <span v-if="item.pantone_hex" class="ml-1 inline-block h-3 w-3 rounded-full ring-1 ring-black/10 align-middle" :style="{ backgroundColor: item.pantone_hex }"/> · {{ item.requested_weight }} kg requested</p></div><button v-if="item.status === 'requested'" @click="start(item)" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-bold text-white"><Play class="h-4 w-4"/>Start sample</button></div>
                    <form v-if="item.status === 'in_progress'" @submit.prevent="complete(item)" class="mt-5 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-4 dark:border-zinc-800">
                        <label class="text-xs font-bold text-slate-600 dark:text-zinc-300">Knitting machine<select v-model="formFor(item).machine_id" required class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"><option value="">Choose machine</option><option v-for="machine in machines" :key="machine.id" :value="machine.id">{{ machine.machine_no }}</option></select></label>
                        <label class="text-xs font-bold text-slate-600 dark:text-zinc-300">Produced weight (kg)<input v-model="formFor(item).produced_weight" type="number" min="0.001" max="1" step="0.001" required class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"/></label>
                        <label class="text-xs font-bold text-slate-600 dark:text-zinc-300 sm:col-span-1">Remarks<input v-model="formFor(item).remarks" class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-zinc-700 dark:bg-zinc-800"/></label>
                        <button type="submit" :disabled="formFor(item).processing" class="self-end rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"><CheckCircle2 class="mr-1 inline h-4 w-4"/>Record completed</button>
                    </form>
                </article>
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
    </AuthenticatedLayout>
</template>
