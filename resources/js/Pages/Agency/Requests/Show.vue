<script setup lang="ts">
// Agency portal: request detail (read-only) + propose workers
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { toast } from '@/lib/feedback';
import { ArrowRight, BriefcaseBusiness, CalendarDays, Check, Clock3, MapPin, ShieldCheck, Users, UserPlus } from 'lucide-vue-next';

const props = defineProps<{
    request: {
        id: number; code: string; department: string; position_skill: string;
        agency?: string | null;
        needed: number; filled: number; start_date: string | null; end_date: string | null;
        shift: string | null; employment_type: string | null; notes: string | null; status: string; stored_status: string;
        accepted: Array<{ worker_code: string | null; worker_name: string | null }>;
    };
    proposals: Array<{
        id: number; worker_code: string | null; worker_name: string | null;
        position_skill: string | null; status: string; proposed_at: string | null; review_reason: string | null;
    }>;
    pickable_workers: Array<{ id: number; code: string; name: string; position_skill: string | null; department: string | null; worker_status: string }>;
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true, deep: true });

const proposeForm = useForm({ worker_id: '' as string | number });
const showProposeConfirm = ref(false);
const selectedWorker = computed(() => props.pickable_workers.find((worker) => String(worker.id) === String(proposeForm.worker_id)) || null);

function propose() {
    if (!proposeForm.worker_id) return;
    showProposeConfirm.value = true;
}

function confirmPropose() {
    if (!proposeForm.worker_id || proposeForm.processing) return;
    proposeForm.post(route('agency.requests.propose', props.request.id), {
        preserveScroll: true,
        onSuccess: () => { proposeForm.reset(); showProposeConfirm.value = false; },
        onError: (e: any) => toast.error(Object.values(e)[0] as string || 'Could not propose worker.'),
    });
}

function progressPct(): number {
    if (!props.request.needed) return 0;
    return Math.min(100, Math.round((props.request.filled / props.request.needed) * 100));
}
</script>

<template>
    <Head :title="request.code + ' - Monti ERP'" />
    <AgencyLayout>
        <main class="space-y-5 pb-8">
            <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                <Link :href="route('agency.requests.index')" class="transition hover:text-amber-700">Requests</Link><span class="text-slate-300">/</span><span class="text-slate-800 dark:text-slate-200">{{ request.code }}</span>
            </nav>

            <section class="agency-page-header relative isolate overflow-hidden !mb-4 !rounded-2xl !px-6 !py-5 text-white shadow-sm">
                <div class="pointer-events-none absolute -right-16 -top-32 -z-10 h-80 w-80 rounded-full bg-amber-400/20 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-40 left-1/3 -z-10 h-72 w-72 rounded-full bg-orange-600/15 blur-3xl"></div>
                <div class="flex flex-wrap items-start justify-between gap-5">
                    <div class="max-w-3xl">
                        <div class="flex flex-wrap items-center gap-2"><span class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-amber-200"><BriefcaseBusiness class="h-3.5 w-3.5" /> Workforce request</span><span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider" :class="request.status === 'Filled' ? 'bg-emerald-400/15 text-emerald-200' : 'bg-amber-300/15 text-amber-200'">{{ request.status }}</span></div>
                        <h1 class="!mt-3 !text-3xl">{{ request.position_skill }}</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-slate-300"><span>{{ request.code }}</span><span class="text-slate-500">·</span><span>{{ request.department }}</span><span v-if="request.agency" class="text-slate-500">·</span><span v-if="request.agency">{{ request.agency }}</span></p>
                    </div>
                    <div class="min-w-[190px] rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm">
                        <div class="flex items-center justify-between"><span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Staffing progress</span><span class="text-sm font-black text-amber-300">{{ progressPct() }}%</span></div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-300 transition-all duration-500" :style="{ width: progressPct() + '%' }"></div></div>
                        <div class="mt-3 flex items-baseline gap-1"><strong class="text-2xl font-black">{{ request.filled }}</strong><span class="text-sm text-slate-400">/ {{ request.needed }} filled</span></div>
                        <p class="mt-1 text-[11px] text-slate-400">{{ Math.max(0, request.needed - request.filled) }} open {{ request.needed - request.filled === 1 ? 'position' : 'positions' }}</p>
                    </div>
                </div>
                <div class="mt-4 grid gap-2 border-t border-white/10 pt-3 sm:grid-cols-3">
                    <div class="flex items-center gap-3 rounded-xl px-2 py-2"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-amber-200"><CalendarDays class="h-4 w-4" /></span><div><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Assignment window</p><p class="mt-0.5 text-xs font-semibold">{{ request.start_date || 'Start date unset' }} <span class="text-slate-500">→</span> {{ request.end_date || 'Ongoing' }}</p></div></div>
                    <div class="flex items-center gap-3 rounded-xl px-2 py-2"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-amber-200"><Clock3 class="h-4 w-4" /></span><div><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Shift</p><p class="mt-0.5 text-xs font-semibold">{{ request.shift || 'Not specified' }}</p></div></div>
                    <div class="flex items-center gap-3 rounded-xl px-2 py-2"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-amber-200"><Users class="h-4 w-4" /></span><div><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Agency allocation</p><p class="mt-0.5 text-xs font-semibold">{{ request.agency || 'Assigned agency' }}</p></div></div>
                </div>
            </section>

            <div class="grid gap-5 lg:grid-cols-12">
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 lg:col-span-5">
                    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700 dark:text-amber-400">Request specification</p><h2 class="mt-1 text-lg font-black text-slate-900 dark:text-white">Assignment brief</h2></div>
                    <div class="space-y-1 p-4 sm:p-5">
                        <div class="flex items-start gap-3 rounded-2xl px-3 py-3"><span class="mt-0.5 text-slate-400"><MapPin class="h-4 w-4" /></span><div class="min-w-0 flex-1"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Department</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-slate-100">{{ request.department }}</p></div></div>
                        <div class="flex items-start gap-3 rounded-2xl px-3 py-3"><span class="mt-0.5 text-slate-400"><BriefcaseBusiness class="h-4 w-4" /></span><div class="min-w-0 flex-1"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Position / skill</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-slate-100">{{ request.position_skill }}</p></div></div>
                        <div v-if="request.employment_type" class="flex items-start gap-3 rounded-2xl px-3 py-3"><span class="mt-0.5 text-slate-400"><BriefcaseBusiness class="h-4 w-4" /></span><div class="min-w-0 flex-1"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Employment type</p><p class="mt-1 text-sm font-bold text-slate-800 dark:text-slate-100">{{ request.employment_type }}</p></div></div>
                        <div class="grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 dark:border-slate-700">
                            <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-900/50"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Headcount</p><p class="mt-1 text-lg font-black text-slate-900 dark:text-white">{{ request.needed }}</p></div>
                            <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-900/50"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Current fill</p><p class="mt-1 text-lg font-black text-slate-900 dark:text-white">{{ progressPct() }}%</p></div>
                        </div>
                        <div v-if="request.notes" class="mt-2 rounded-2xl border border-amber-100 bg-amber-50/60 p-4 dark:border-amber-900/40 dark:bg-amber-900/10"><p class="text-[10px] font-black uppercase tracking-wider text-amber-800 dark:text-amber-300">Request notes</p><p class="mt-2 whitespace-pre-wrap break-words text-xs leading-relaxed text-slate-700 dark:text-slate-300">{{ request.notes }}</p></div>
                    </div>
                </section>

                <section class="relative overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm dark:border-amber-900/50 dark:bg-slate-800 lg:col-span-7">
                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-400 via-orange-400 to-amber-200"></div>
                    <div class="flex flex-wrap items-start justify-between gap-3 px-5 pb-3 pt-5 sm:px-6"><div><span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"><UserPlus class="h-3.5 w-3.5" /> Candidate matching</span><h2 class="mt-3 text-xl font-black text-slate-900 dark:text-white">Propose a worker</h2><p class="mt-1 max-w-lg text-xs leading-relaxed text-slate-500 dark:text-slate-400">Select an eligible worker whose profile matches this request’s department and skill.</p></div><div class="flex h-12 min-w-12 items-center justify-center rounded-2xl bg-slate-950 px-3 text-lg font-black text-amber-300">{{ pickable_workers.length }}</div></div>
                    <form @submit.prevent="propose" class="px-5 pb-5 sm:px-6 sm:pb-6">
                        <label for="request-worker-picker" class="mb-2 block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Eligible candidates</label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <select id="request-worker-picker" v-model="proposeForm.worker_id" :disabled="pickable_workers.length === 0 || proposeForm.processing" class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-sm font-semibold text-slate-700 shadow-inner transition focus:border-amber-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-amber-500/10 disabled:cursor-not-allowed disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                <option value="">{{ pickable_workers.length ? 'Choose a matching worker' : 'No matching workers available' }}</option><option v-for="w in pickable_workers" :key="w.id" :value="w.id">{{ w.name }} · {{ w.worker_status === 'Pending' ? 'Pending HR approval' : 'Available' }}</option>
                            </select>
                            <button type="submit" :disabled="!selectedWorker || proposeForm.processing" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-slate-900/10 transition hover:bg-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed disabled:opacity-40">{{ proposeForm.processing ? 'Submitting…' : 'Propose worker' }}<ArrowRight class="h-4 w-4" /></button>
                        </div>
                        <div v-if="selectedWorker" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-700 dark:bg-slate-900/60"><div class="flex min-w-0 items-center gap-3"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-sm font-black text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ selectedWorker.name.slice(0, 1).toUpperCase() }}</div><div class="min-w-0"><p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ selectedWorker.name }}</p><p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ selectedWorker.department }} · {{ selectedWorker.position_skill }}</p></div></div><span :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-black', selectedWorker.worker_status === 'Pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300']"><ShieldCheck class="h-3.5 w-3.5" />{{ selectedWorker.worker_status === 'Pending' ? 'Pending HR approval' : 'Eligible' }}</span></div>
                        <p v-if="selectedWorker?.worker_status === 'Pending'" class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">Your proposal can be submitted now. HR must approve the worker profile before assignment.</p>
                        <div v-if="pickable_workers.length === 0" class="mt-4 flex items-start gap-3 rounded-2xl border border-slate-200 border-dashed bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/50"><span class="rounded-xl bg-white p-2 text-slate-400 shadow-sm dark:bg-slate-800"><Users class="h-4 w-4" /></span><div><p class="text-xs font-bold text-slate-700 dark:text-slate-200">No matching candidates yet</p><p class="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">Workers must match the requested department and position, have an eligible status, and hold current clearance.</p></div></div>
                    </form>
                </section>

                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 lg:col-span-5">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-700"><div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Submission activity</p><h2 class="mt-1 text-base font-black text-slate-900 dark:text-white">My proposals</h2></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ proposals.length }}</span></div>
                    <div v-if="proposals.length === 0" class="m-4 rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center dark:border-slate-700"><p class="text-xs font-bold text-slate-600 dark:text-slate-300">No proposals submitted</p><p class="mt-1 text-[11px] text-slate-400">Your worker proposals will appear here.</p></div>
                    <ol v-else class="divide-y divide-slate-100 px-5 dark:divide-slate-700/70"><li v-for="p in proposals" :key="p.id" class="flex items-start gap-3 py-4"><span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl" :class="p.status === 'Accepted' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : p.status === 'Rejected' ? 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'"><Check v-if="p.status === 'Accepted'" class="h-4 w-4" /><Clock3 v-else class="h-4 w-4" /></span><div class="min-w-0 flex-1"><p class="text-sm font-bold text-slate-900 dark:text-white">{{ p.worker_name }}</p><p class="mt-1 text-[11px] text-slate-500">{{ p.proposed_at || 'Date unavailable' }}<span v-if="p.review_reason"> · {{ p.review_reason }}</span></p></div><span :class="['shrink-0 rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wider', p.status === 'Accepted' ? 'bg-emerald-100 text-emerald-700' : p.status === 'Rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700']">{{ p.status }}</span></li></ol>
                </section>

                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 lg:col-span-7">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-700"><div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">Confirmed allocation</p><h2 class="mt-1 text-base font-black text-slate-900 dark:text-white">Accepted workers</h2></div><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">{{ request.accepted.length }} / {{ request.needed }}</span></div>
                    <div v-if="request.accepted.length === 0" class="m-4 rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center dark:border-slate-700"><p class="text-xs font-bold text-slate-600 dark:text-slate-300">No workers accepted yet</p><p class="mt-1 text-[11px] text-slate-400">Accepted assignments will be listed here.</p></div>
                    <ul v-else class="grid gap-2 p-4 sm:grid-cols-2"><li v-for="(a, i) in request.accepted" :key="i" class="flex min-w-0 items-center gap-3 rounded-2xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-900/40"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-sm font-black text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">{{ (a.worker_name || 'W').slice(0, 1).toUpperCase() }}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ a.worker_name }}</p><p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Assigned to request</p></div><Check class="h-4 w-4 shrink-0 text-emerald-600" /></li></ul>
                </section>
            </div>
        </main>
        <div v-if="showProposeConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" @click.self="showProposeConfirm = false" @keydown.esc="showProposeConfirm = false">
            <section role="dialog" aria-modal="true" aria-labelledby="confirm-proposal-title" class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-800">
                <h2 id="confirm-proposal-title" class="text-base font-black text-slate-900 dark:text-white">Confirm worker proposal</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Propose <strong>{{ selectedWorker?.name }}</strong> for request <strong>{{ request.code }}</strong>?</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">HR will review this proposal before the worker is assigned.</p>
                <p v-if="selectedWorker?.worker_status === 'Pending'" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">This worker's profile is pending HR approval. HR must approve the profile before assignment.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" :disabled="proposeForm.processing" @click="showProposeConfirm = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">Back</button>
                    <button type="button" :disabled="proposeForm.processing || !selectedWorker" @click="confirmPropose" class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700 disabled:opacity-60">{{ proposeForm.processing ? 'Submitting…' : 'Confirm proposal' }}</button>
                </div>
            </section>
        </div>
    </AgencyLayout>
</template>
