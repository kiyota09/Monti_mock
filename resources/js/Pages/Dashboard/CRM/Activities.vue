<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { usePageAccess } from '@/composables/usePageAccess';
import { computed } from 'vue';
import {
    Activity, Plus, X, Check, Phone, Users, StickyNote, ListTodo, Mail, Clock,
    Building2, User as UserIcon, Link2, Calendar, CheckCircle2, RotateCcw, FileText,
} from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditAct = computed(() => canEdit('CRM', 'activities'));

const props = defineProps({ filter: String, activities: Array, openCount: Number, overdueCount: Number, clients: Array });

const setFilter = (f) => router.get(route('crm.activities'), { filter: f }, { preserveScroll: true, replace: true });
const showNew = ref(false);
const form = useForm({ client_id: '', type: 'note', subject: '', body: '', due_at: '' });
const submit = () => {
    form.post(route('crm.activities.store'), { preserveScroll: true, onSuccess: () => { showNew.value = false; form.reset(); } });
};
const remove = (id) => router.delete(route('crm.activities.destroy', id), { preserveScroll: true });
const fdate = (d) => d ? new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
const typeIcon = { call: Phone, meeting: Users, note: StickyNote, task: ListTodo, todo: ListTodo, reminder: Clock, email: Mail };

// ── Detail modal ─────────────────────────────────────────────
const selected = ref(null);
const showDetail = ref(false);
const openDetail = (a) => { selected.value = a; showDetail.value = true; };

const relatedLabel = (a) => a?.client?.company_name || a?.opportunity?.title || a?.lead?.company_name || 'Not linked';
const relatedKind = (a) => a?.client ? 'Account' : (a?.opportunity ? 'Deal' : (a?.lead ? 'Lead' : 'Unlinked'));
const statusOf = (a) => {
    if (!a) return { label: 'Open', cls: 'bg-amber-100 text-amber-700' };
    if (a.status === 'cancelled') return { label: 'Cancelled', cls: 'bg-gray-200 text-gray-600' };
    if (a.done_at) return { label: 'Done', cls: 'bg-emerald-100 text-emerald-700' };
    if (a.is_overdue) return { label: 'Overdue', cls: 'bg-rose-100 text-rose-700' };
    return { label: 'Open', cls: 'bg-amber-100 text-amber-700' };
};

// ── Mark done / not done (with confirmation) ──────────────────
const confirm = ref({ show: false, activity: null, action: 'done' });
const confirmProcessing = ref(false);
const askToggle = (a) => { confirm.value = { show: true, activity: a, action: a.done_at ? 'undone' : 'done' }; };
const runToggle = () => {
    const { activity, action } = confirm.value;
    if (!activity) return;
    confirmProcessing.value = true;
    const url = action === 'done'
        ? route('crm.activities.done', activity.id)
        : route('crm.activities.undone', activity.id);
    router.post(url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            const nowDone = action === 'done';
            activity.done_at = nowDone ? new Date().toISOString() : null;
            activity.status = nowDone ? 'done' : 'planned';
            activity.is_overdue = nowDone ? false : (activity.due_at ? new Date(activity.due_at) < new Date() : false);
        },
        onFinish: () => {
            confirmProcessing.value = false;
            confirm.value = { show: false, activity: null, action: 'done' };
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Activities" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-5xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Activity class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">CRM · Timeline</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Activities</h1>
                            <p class="text-sm text-blue-100/90">{{ openCount }} open · {{ overdueCount }} overdue.</p>
                            <span v-if="!canEditAct" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <button v-if="canEditAct" @click="showNew = true" class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-2.5 text-xs font-black uppercase text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition"><Plus class="h-4 w-4" /> Log activity</button>
                    </div>
                    <div class="relative mt-6 flex gap-2">
                        <button v-for="f in [['open','Open'],['overdue','Overdue'],['done','Done'],['all','All']]" :key="f[0]" @click="setFilter(f[0])"
                            :class="['rounded-2xl px-4 py-2.5 text-xs font-black uppercase transition', filter === f[0] ? 'bg-white text-indigo-700 shadow-lg' : 'bg-white/15 text-white hover:bg-white/25']">{{ f[1] }}</button>
                    </div>
                </div>

                <div class="space-y-3">
                    <div v-for="a in activities" :key="a.id" @click="openDetail(a)"
                        class="group cursor-pointer rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 shadow-sm flex gap-3 transition-all hover:shadow-lg hover:-translate-y-0.5"
                        :class="a.is_overdue && 'ring-2 ring-rose-300 dark:ring-rose-800'">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-900/30">
                            <component :is="typeIcon[a.type] || StickyNote" class="h-5 w-5 text-indigo-500" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-black text-sm">{{ a.subject }}</p>
                                <span class="rounded-full bg-gray-100 dark:bg-zinc-800 px-2 py-0.5 text-[10px] font-black uppercase text-gray-500">{{ a.type }}</span>
                                <span v-if="a.is_overdue" class="rounded-full bg-rose-100 text-rose-700 px-2 py-0.5 text-[10px] font-black uppercase">Overdue</span>
                                <span v-if="a.done_at" class="rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-[10px] font-black uppercase">Done</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ a.client?.company_name || a.opportunity?.title || a.lead?.company_name || '' }} · {{ a.owner?.name || '' }}</p>
                            <p v-if="a.body" class="text-xs text-gray-500 mt-1 whitespace-pre-line line-clamp-2">{{ a.body }}</p>
                            <p class="text-[10px] font-bold text-gray-400 mt-1">{{ a.due_at ? 'Due ' + fdate(a.due_at) : fdate(a.created_at) }}</p>
                        </div>
                        <div v-if="canEditAct" class="flex shrink-0 flex-col gap-1.5">
                            <button v-if="!a.done_at" @click.stop="askToggle(a)" title="Mark done" class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 p-2 text-emerald-600 hover:bg-emerald-600 hover:text-white transition"><Check class="h-4 w-4" /></button>
                            <button v-else @click.stop="askToggle(a)" title="Mark as not done" class="rounded-xl bg-amber-50 dark:bg-amber-900/20 p-2 text-amber-600 hover:bg-amber-600 hover:text-white transition"><RotateCcw class="h-4 w-4" /></button>
                            <button @click.stop="remove(a.id)" title="Remove" class="rounded-xl bg-gray-50 dark:bg-zinc-800 p-2 text-gray-300 hover:text-rose-500 transition"><X class="h-4 w-4" /></button>
                        </div>
                    </div>
                    <p v-if="!activities?.length" class="text-center text-sm font-bold text-gray-400 py-10">Nothing here.</p>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showNew" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showNew = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-6 py-5 text-white flex justify-between items-center">
                            <h3 class="font-black text-lg">Log Activity</h3>
                            <button @click="showNew = false"><X class="h-5 w-5" /></button>
                        </div>
                        <form @submit.prevent="submit" class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Type</label>
                                    <select v-model="form.type" class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white">
                                        <option value="note">Note</option><option value="call">Call</option><option value="meeting">Meeting</option>
                                        <option value="task">Task</option><option value="email">Email</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Account</label>
                                    <select v-model="form.client_id" class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white">
                                        <option value="">—</option>
                                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.company_name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Subject *</label>
                                <input v-model="form.subject" required placeholder="e.g. Follow-up call on sampling"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Details</label>
                                <textarea v-model="form.body" rows="3" class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm resize-none dark:text-white"></textarea>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Due (tasks)</label>
                                <input v-model="form.due_at" type="datetime-local" class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                            </div>
                            <button :disabled="form.processing" class="w-full py-3 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white text-xs font-black uppercase shadow-lg disabled:opacity-50 hover:scale-[1.01] active:scale-95 transition-all">Save</button>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Activity detail -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showDetail && selected" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showDetail = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[90vh] flex flex-col animate-fade-up">
                        <!-- Header -->
                        <div class="relative bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 px-6 py-5 text-white">
                            <div class="absolute -top-12 -right-8 h-36 w-36 rounded-full bg-white/10 blur-2xl" />
                            <div class="relative flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3 min-w-0">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
                                        <component :is="typeIcon[selected.type] || StickyNote" class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-100">Activity · {{ selected.type }}</p>
                                        <h3 class="font-black text-lg leading-tight break-words">{{ selected.subject }}</h3>
                                    </div>
                                </div>
                                <button @click="showDetail = false" class="shrink-0 flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 hover:bg-white/35 transition"><X class="h-4 w-4" /></button>
                            </div>
                            <div class="relative mt-3 flex flex-wrap gap-1.5">
                                <span :class="['rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase', statusOf(selected).cls]">{{ statusOf(selected).label }}</span>
                                <span class="rounded-full bg-white/15 px-2.5 py-0.5 text-[10px] font-black uppercase">#{{ selected.id }}</span>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="p-6 space-y-4 overflow-y-auto">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-2xl bg-gray-50 dark:bg-zinc-800/60 p-3.5">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 flex items-center gap-1"><Building2 class="h-3 w-3" /> Related to</p>
                                    <p class="mt-1 text-sm font-black break-words">{{ relatedLabel(selected) }}</p>
                                    <p class="text-[10px] font-bold text-gray-400">{{ relatedKind(selected) }}</p>
                                </div>
                                <div class="rounded-2xl bg-gray-50 dark:bg-zinc-800/60 p-3.5">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 flex items-center gap-1"><UserIcon class="h-3 w-3" /> Owner</p>
                                    <p class="mt-1 text-sm font-black break-words">{{ selected.owner?.name || '—' }}</p>
                                </div>
                                <div class="rounded-2xl bg-gray-50 dark:bg-zinc-800/60 p-3.5">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 flex items-center gap-1"><UserIcon class="h-3 w-3" /> Assigned to</p>
                                    <p class="mt-1 text-sm font-black break-words">{{ selected.assignee?.name || selected.owner?.name || '—' }}</p>
                                </div>
                                <div class="rounded-2xl bg-gray-50 dark:bg-zinc-800/60 p-3.5">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 flex items-center gap-1"><Calendar class="h-3 w-3" /> Due</p>
                                    <p class="mt-1 text-sm font-black break-words">{{ fdate(selected.due_at || selected.due_date) }}</p>
                                </div>
                            </div>

                            <div v-if="selected.body || selected.notes" class="rounded-2xl border border-gray-100 dark:border-zinc-800 p-4">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 flex items-center gap-1"><FileText class="h-3 w-3" /> Details</p>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line break-words">{{ selected.body || selected.notes }}</p>
                            </div>

                            <a v-if="selected.meeting_link" :href="selected.meeting_link" target="_blank" rel="noopener"
                                class="flex items-center gap-2 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-900 p-3.5 text-sm font-bold text-indigo-700 dark:text-indigo-300 break-all hover:bg-indigo-100 transition">
                                <Link2 class="h-4 w-4 shrink-0" /> {{ selected.meeting_link }}
                            </a>

                            <div class="grid grid-cols-2 gap-y-2 gap-x-3 text-[11px] font-bold text-gray-400 pt-1">
                                <p class="flex items-center gap-1"><Clock class="h-3 w-3" /> Created: {{ fdate(selected.created_at) }}</p>
                                <p class="flex items-center gap-1"><Clock class="h-3 w-3" /> Updated: {{ fdate(selected.updated_at) }}</p>
                                <p v-if="selected.done_at" class="flex items-center gap-1 col-span-2 text-emerald-600"><CheckCircle2 class="h-3 w-3" /> Completed: {{ fdate(selected.done_at) }}</p>
                            </div>
                        </div>

                        <!-- Footer actions -->
                        <div v-if="canEditAct" class="border-t border-gray-100 dark:border-zinc-800 p-4 flex gap-3">
                            <button type="button" @click="askToggle(selected)"
                                :class="['flex-1 inline-flex items-center justify-center gap-2 py-3 rounded-2xl text-xs font-black uppercase shadow-lg transition-all hover:scale-[1.01] active:scale-95',
                                    selected.done_at ? 'bg-gradient-to-r from-amber-500 to-orange-600 text-white' : 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white']">
                                <component :is="selected.done_at ? RotateCcw : CheckCircle2" class="h-4 w-4" />
                                {{ selected.done_at ? 'Mark as not done' : 'Mark as done' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Mark done / not done confirmation -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="confirm.show && confirm.activity" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="confirm.show = false">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden animate-fade-up">
                        <div class="p-6 text-center space-y-4">
                            <span :class="['mx-auto flex h-14 w-14 items-center justify-center rounded-2xl',
                                confirm.action === 'done' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600']">
                                <component :is="confirm.action === 'done' ? CheckCircle2 : RotateCcw" class="h-7 w-7" />
                            </span>
                            <div>
                                <h3 class="font-black text-lg dark:text-white">{{ confirm.action === 'done' ? 'Mark as done?' : 'Mark as not done?' }}</h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ confirm.action === 'done'
                                        ? 'This activity will be marked as completed.'
                                        : 'This activity will be reopened and shown as open.' }}
                                </p>
                                <p class="text-xs font-bold text-gray-400 mt-2 truncate">{{ confirm.activity.subject }}</p>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="confirm.show = false" class="flex-1 py-2.5 rounded-2xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="button" @click="runToggle" :disabled="confirmProcessing"
                                    :class="['flex-1 py-2.5 rounded-2xl text-white font-black text-sm shadow-lg disabled:opacity-60 hover:scale-[1.02] active:scale-95 transition-all inline-flex items-center justify-center gap-2',
                                        confirm.action === 'done' ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-gradient-to-r from-amber-500 to-orange-600']">
                                    <svg v-if="confirmProcessing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    {{ confirmProcessing ? 'Saving…' : (confirm.action === 'done' ? 'Yes, mark done' : 'Yes, reopen') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
