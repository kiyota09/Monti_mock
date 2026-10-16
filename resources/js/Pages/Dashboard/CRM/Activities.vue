<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { usePageAccess } from '@/composables/usePageAccess';
import { computed } from 'vue';
import { Activity, Plus, X, Check, Phone, Users, StickyNote, ListTodo, Mail } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditAct = computed(() => canEdit('CRM', 'activities'));

const props = defineProps({ filter: String, activities: Array, openCount: Number, overdueCount: Number, clients: Array });

const setFilter = (f) => router.get(route('crm.activities'), { filter: f }, { preserveScroll: true, replace: true });
const showNew = ref(false);
const form = useForm({ client_id: '', type: 'note', subject: '', body: '', due_at: '' });
const submit = () => {
    form.post(route('crm.activities.store'), { preserveScroll: true, onSuccess: () => { showNew.value = false; form.reset(); } });
};
const done = (id) => router.post(route('crm.activities.done', id), {}, { preserveScroll: true });
const remove = (id) => router.delete(route('crm.activities.destroy', id), { preserveScroll: true });
const fdate = (d) => d ? new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
const typeIcon = { call: Phone, meeting: Users, note: StickyNote, task: ListTodo, email: Mail };
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
                    <div v-for="a in activities" :key="a.id"
                        class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 shadow-sm flex gap-3"
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
                            <p v-if="a.body" class="text-xs text-gray-500 mt-1 whitespace-pre-line">{{ a.body }}</p>
                            <p class="text-[10px] font-bold text-gray-400 mt-1">{{ a.due_at ? 'Due ' + fdate(a.due_at) : fdate(a.created_at) }}</p>
                        </div>
                        <div v-if="canEditAct" class="flex shrink-0 flex-col gap-1.5">
                            <button v-if="!a.done_at" @click="done(a.id)" title="Mark done" class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 p-2 text-emerald-600 hover:bg-emerald-600 hover:text-white transition"><Check class="h-4 w-4" /></button>
                            <button @click="remove(a.id)" title="Remove" class="rounded-xl bg-gray-50 dark:bg-zinc-800 p-2 text-gray-300 hover:text-rose-500 transition"><X class="h-4 w-4" /></button>
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
    </AuthenticatedLayout>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
