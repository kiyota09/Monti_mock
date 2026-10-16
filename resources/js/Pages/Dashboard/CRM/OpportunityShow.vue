<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft, ChevronRight, Star, Trophy, Phone, CalendarDays, Bell,
    ClipboardCheck, Clock, Plus, Check, Ban,
} from 'lucide-vue-next';
import { usePageAccess } from '@/composables/usePageAccess';
import ActivityModal from '@/Components/crm/pipeline/ActivityModal.vue';
import { peso, activityStateOf } from '@/composables/crm/usePipeline';

const { canEdit } = usePageAccess();
const canEditOpp = computed(() => canEdit('CRM', 'opportunities'));

const props = defineProps({
    opportunity: { type: Object, required: true },
    stages: { type: Array, default: () => [] },
    contacts: { type: Array, default: () => [] },
    salespeople: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
});
const o = computed(() => props.opportunity);
const orderedStages = computed(() => [...(props.stages || [])].sort((a, b) => a.sequence - b.sequence));
const state = computed(() => activityStateOf(o.value));

const fdate = (d) => (d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—');

const form = useForm({
    title: o.value.title,
    contact_id: o.value.contact_id ?? null,
    email: o.value.email ?? '',
    phone: o.value.phone ?? '',
    value: Number(o.value.value ?? 0),
    probability: Number(o.value.probability ?? 0),
    priority: Number(o.value.priority ?? 0),
    owner_id: o.value.owner_id ?? null,
    expected_close: o.value.expected_close ? String(o.value.expected_close).slice(0, 10) : '',
    internal_notes: o.value.internal_notes ?? '',
    source: o.value.source ?? '',
    medium: o.value.medium ?? '',
    campaign: o.value.campaign ?? '',
    referred_by: o.value.referred_by ?? '',
});

const save = () => {
    form.patch(route('crm.opportunities.update', o.value.id), { preserveScroll: true });
};

const moveTo = (stage) => {
    if (!canEditOpp.value || Number(stage.id) === Number(o.value.stage_id)) return;
    router.post(route('crm.opportunities.move', o.value.id), { stage_id: stage.id }, { preserveScroll: true });
};

const activeTab = ref('notes');
const showActivity = ref(false);

const actIcon = (a) => {
    if (a.type === 'call') return Phone;
    if (a.type === 'meeting') return CalendarDays;
    if (a.type === 'reminder') return Bell;
    if (a.type === 'todo' || a.type === 'task') return ClipboardCheck;
    return Clock;
};
const markDone = (a) => router.post(route('crm.activities.done', a.id), {}, { preserveScroll: true });
const cancelAct = (a) => router.post(route('crm.activities.cancel', a.id), {}, { preserveScroll: true });
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="o.title" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-5xl space-y-6 p-4 pb-16 sm:p-6">
                <!-- Header -->
                <div class="relative animate-fade-up overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white shadow-xl shadow-indigo-500/20 sm:p-8">
                    <div class="relative flex flex-wrap items-center gap-4">
                        <Link :href="route('crm.opportunities')" class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 transition hover:bg-white/25">
                            <ChevronLeft class="h-5 w-5" />
                        </Link>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                Deal · {{ o.stageRef?.name ?? o.stage }} · <span class="capitalize">{{ state }}</span>
                            </p>
                            <h1 class="truncate text-2xl font-black tracking-tight sm:text-3xl">{{ o.title }}</h1>
                            <p class="text-sm text-blue-100/90">{{ o.contact?.name ?? o.client?.company_name ?? o.lead?.company_name ?? '—' }} · owner {{ o.owner?.name ?? '—' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-black">{{ peso(o.value) }}</p>
                            <p class="text-xs font-bold text-blue-100">{{ o.probability }}% · {{ '★'.repeat(o.priority || 0) || 'no stars' }}</p>
                        </div>
                    </div>

                    <!-- Stage status bar (clickable chevrons, incl. newly added stages) -->
                    <div class="relative mt-5 flex items-stretch gap-1 overflow-x-auto">
                        <button
                            v-for="(s, i) in orderedStages"
                            :key="s.id"
                            @click="moveTo(s)"
                            :disabled="!canEditOpp"
                            :title="`Move to ${s.name}`"
                            :class="[
                                'flex shrink-0 items-center gap-1 px-4 py-2.5 text-[11px] font-black uppercase tracking-wide transition',
                                Number(s.id) === Number(o.stage_id)
                                    ? 'bg-white text-indigo-700 shadow'
                                    : 'bg-white/15 text-white hover:bg-white/25',
                                i === 0 ? 'rounded-l-2xl' : '',
                                i === orderedStages.length - 1 ? 'rounded-r-2xl' : '',
                            ]"
                        >
                            <Trophy v-if="s.is_won" class="h-3.5 w-3.5 text-amber-300" />
                            {{ s.name }}
                            <ChevronRight v-if="i < orderedStages.length - 1" class="h-3.5 w-3.5 opacity-60" />
                        </button>
                    </div>
                </div>

                <!-- Editable fields -->
                <form @submit.prevent="save" class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Title *</label>
                            <input v-model="form.title" required :disabled="!canEditOpp" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm font-bold outline-none focus:border-indigo-400 dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Contact</label>
                            <select v-model="form.contact_id" :disabled="!canEditOpp" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white">
                                <option :value="null">—</option>
                                <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}{{ c.organization ? ` — ${c.organization}` : '' }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Salesperson</label>
                            <select v-model="form.owner_id" :disabled="!canEditOpp" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white">
                                <option :value="null">—</option>
                                <option v-for="u in salespeople" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Email</label>
                            <input v-model="form.email" type="email" :disabled="!canEditOpp" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Phone</label>
                            <input v-model="form.phone" :disabled="!canEditOpp" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Expected revenue (₱) * — updates the stage total</label>
                            <input v-model.number="form.value" type="number" min="0" step="0.01" required :disabled="!canEditOpp" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm font-black text-indigo-600 outline-none dark:bg-zinc-800" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Probability — {{ form.probability }}% (stage default, editable)</label>
                            <input v-model.number="form.probability" type="range" min="0" max="100" :disabled="!canEditOpp" class="w-full accent-indigo-600" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Priority</label>
                            <div class="flex items-center gap-1 pt-1">
                                <button v-for="n in [1, 2, 3]" :key="n" type="button" @click="form.priority = n" :disabled="!canEditOpp" :class="n <= form.priority ? 'text-amber-400' : 'text-gray-200 dark:text-zinc-700'" class="transition hover:scale-125">
                                    <Star class="h-5 w-5" :fill="n <= form.priority ? 'currentColor' : 'none'" />
                                </button>
                                <button v-if="form.priority > 0 && canEditOpp" type="button" @click="form.priority = 0" class="ml-1 text-[10px] font-bold text-gray-300 hover:text-rose-400">✕</button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Expected closing date</label>
                            <input v-model="form.expected_close" type="date" :disabled="!canEditOpp" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                        </div>
                    </div>
                    <button v-if="canEditOpp" :disabled="form.processing" class="mt-4 w-full rounded-2xl bg-indigo-600 py-3 text-xs font-black uppercase text-white hover:bg-indigo-700 disabled:opacity-50">Save changes</button>
                </form>

                <!-- Tabs: Internal Notes / Extra Info -->
                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex gap-2 border-b border-gray-100 pb-3 dark:border-zinc-800">
                        <button @click="activeTab = 'notes'" :class="['rounded-xl px-4 py-2 text-xs font-black uppercase', activeTab === 'notes' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-gray-600']">Internal Notes</button>
                        <button @click="activeTab = 'extra'" :class="['rounded-xl px-4 py-2 text-xs font-black uppercase', activeTab === 'extra' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-gray-600']">Extra Info</button>
                    </div>
                    <div v-if="activeTab === 'notes'" class="pt-4">
                        <textarea v-model="form.internal_notes" :disabled="!canEditOpp" rows="4" placeholder="Notes for the sales team…" class="w-full rounded-xl bg-gray-50 px-4 py-3 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                        <button v-if="canEditOpp" @click="save" :disabled="form.processing" class="mt-3 rounded-xl bg-slate-900 px-5 py-2.5 text-[11px] font-black uppercase text-white hover:bg-slate-700 disabled:opacity-50">Save notes</button>
                    </div>
                    <div v-else class="grid gap-4 pt-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Source</label>
                            <input v-model="form.source" :disabled="!canEditOpp" placeholder="e.g. tradeshow, referral, web" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Medium</label>
                            <input v-model="form.medium" :disabled="!canEditOpp" placeholder="e.g. manual, email, social" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Campaign</label>
                            <input v-model="form.campaign" :disabled="!canEditOpp" placeholder="e.g. Q4-linen-push" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-gray-400">Referred by</label>
                            <input v-model="form.referred_by" :disabled="!canEditOpp" placeholder="Who referred this deal?" class="w-full rounded-xl bg-gray-50 px-4 py-2.5 text-sm outline-none dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <button v-if="canEditOpp" @click="save" :disabled="form.processing" class="rounded-xl bg-slate-900 px-5 py-2.5 text-[11px] font-black uppercase text-white hover:bg-slate-700 disabled:opacity-50 sm:col-span-2">Save extra info</button>
                    </div>
                </div>

                <!-- Chatter: scheduled + completed activities -->
                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Chatter · {{ o.activities?.length ?? 0 }} activities</h3>
                        <button v-if="canEditOpp" @click="showActivity = true" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-[11px] font-black uppercase text-white hover:bg-indigo-700">
                            <Plus class="h-3.5 w-3.5" /> Schedule activity
                        </button>
                    </div>
                    <div v-if="o.activities?.length" class="space-y-2">
                        <div v-for="a in o.activities" :key="a.id" class="flex items-start gap-3 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-zinc-800">
                            <component :is="actIcon(a)" class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-black dark:text-white">{{ a.summary ?? a.subject }}</p>
                                <p class="text-[11px] text-gray-400">
                                    <span class="capitalize">{{ a.type }}</span> · {{ a.owner?.name ?? '—' }} ·
                                    {{ a.status }}<span v-if="(a.due_date ?? a.due_at)"> · due {{ fdate(a.due_date ?? a.due_at) }}</span>
                                </p>
                                <p v-if="a.notes ?? a.body" class="mt-1 text-xs text-gray-500">{{ a.notes ?? a.body }}</p>
                            </div>
                            <div v-if="canEditOpp && a.status === 'planned'" class="flex shrink-0 gap-1">
                                <button @click="markDone(a)" title="Mark done" class="rounded-lg p-1.5 text-emerald-500 hover:bg-emerald-50"><Check class="h-4 w-4" /></button>
                                <button @click="cancelAct(a)" title="Cancel" class="rounded-lg p-1.5 text-gray-300 hover:bg-rose-50 hover:text-rose-500"><Ban class="h-4 w-4" /></button>
                            </div>
                            <span v-else :class="['shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black uppercase', a.status === 'done' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500']">{{ a.status }}</span>
                        </div>
                    </div>
                    <p v-else class="py-4 text-center text-xs font-bold text-gray-400">No activities yet — schedule the first one.</p>
                </div>
            </div>
        </div>

        <ActivityModal
            v-if="showActivity"
            :opportunity="{ id: o.id, title: o.title }"
            :salespeople="salespeople"
            :current-user-id="currentUserId"
            @close="showActivity = false"
            @scheduled="() => {}"
        />
    </AuthenticatedLayout>
</template>
