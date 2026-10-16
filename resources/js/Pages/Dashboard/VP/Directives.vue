<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Megaphone, Sparkles, PlusCircle, Trash2, CheckCircle2, Play } from 'lucide-vue-next';

const props = defineProps({ directives: Object, filter: String, counts: Object });

const status = ref(props.filter ?? 'all');
watch(status, () => router.get(route('vp.directives'), { status: status.value === 'all' ? '' : status.value }, { preserveState: true, preserveScroll: true, replace: true }));

const showForm = ref(false);
const form = useForm({ title: '', body: '', assigned_to: '', due_date: '', priority: 'normal' });
const submit = () => form.post(route('vp.directives.store'), { preserveScroll: true, onSuccess: () => { showForm.value = false; form.reset(); } });

const advance = (d, next, notes = '') => router.patch(route('vp.directives.update', d.id), { status: next, completion_notes: notes || undefined }, { preserveScroll: true });
const removeDirective = (d) => { if (confirm(`Remove directive "${d.title}"?`)) router.delete(route('vp.directives.destroy', d.id), { preserveScroll: true }); };

const badge = (s) => s === 'done'
    ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    : s === 'in_progress' ? 'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30'
    : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
const prio = (p) => ['high', 'urgent'].includes(p)
    ? 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30'
    : 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700';
const overdue = (d) => d.due_date && ['open', 'in_progress'].includes(d.status) && new Date(d.due_date) < new Date(new Date().toDateString());
const fmtDate = (v) => v ? new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>

<template>
    <Head title="Directives Tracker" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Megaphone class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> VP · Execution Arm</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Directives Tracker</h1>
                            <p class="text-sm text-blue-100/90">{{ counts.open }} open · {{ counts.in_progress }} in progress · {{ counts.done }} done</p>
                        </div>
                        <button @click="showForm = !showForm" class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition flex items-center gap-1.5"><PlusCircle class="h-4 w-4" /> Issue Directive</button>
                    </div>
                    <div class="relative mt-6 flex gap-1.5 rounded-2xl bg-white/15 p-1 ring-1 ring-white/25 backdrop-blur w-fit">
                        <button v-for="s in ['all', 'open', 'in_progress', 'done']" :key="s" @click="status = s"
                            :class="['px-3 py-1.5 rounded-xl text-xs font-black transition', status === s ? 'bg-white text-indigo-700 shadow' : 'text-white/80 hover:text-white']">{{ s.replace('_', ' ') }}</button>
                    </div>
                </div>

                <Transition name="modal">
                    <div v-if="showForm" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4"><h2 class="text-lg font-black text-white">Issue Directive</h2></div>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Title *</label>
                                <input v-model="form.title" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Details</label>
                                <textarea v-model="form.body" rows="3" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Assign To *</label>
                                <input v-model="form.assigned_to" required placeholder="e.g. MAN-dyeing, HRM, LOG" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Priority</label>
                                <select v-model="form.priority" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
                                </select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Due Date</label>
                                <input v-model="form.due_date" type="date" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="flex items-end gap-2">
                                <button type="submit" :disabled="form.processing" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Issue</button>
                                <button type="button" @click="showForm = false" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-gray-300 transition active:scale-95">Cancel</button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div class="space-y-3">
                    <div v-for="d in directives?.data ?? []" :key="d.id" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5">
                        <div class="flex flex-wrap items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <h3 class="font-black text-gray-900 dark:text-white">{{ d.title }}</h3>
                                <p v-if="d.body" class="text-sm text-gray-500 mt-0.5 whitespace-pre-line">{{ d.body }}</p>
                                <p class="text-[11px] text-gray-400 mt-1.5">To: <span class="font-bold">{{ d.assigned_to }}</span> · Due: {{ fmtDate(d.due_date) }} · by {{ d.creator?.name ?? '—' }}</p>
                            </div>
                            <span :class="badge(d.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 shrink-0">{{ overdue(d) ? 'overdue' : d.status.replace('_', ' ') }}</span>
                            <span :class="prio(d.priority)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 shrink-0">{{ d.priority }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <button v-if="d.status === 'open'" @click="advance(d, 'in_progress')" class="inline-flex items-center gap-1 rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black uppercase text-white hover:bg-indigo-700 active:scale-95 transition"><Play class="h-3 w-3" /> Start</button>
                            <button v-if="d.status !== 'done'" @click="advance(d, 'done')" class="inline-flex items-center gap-1 rounded-xl bg-emerald-600 px-3 py-1.5 text-[11px] font-black uppercase text-white hover:bg-emerald-700 active:scale-95 transition"><CheckCircle2 class="h-3 w-3" /> Done</button>
                            <button @click="removeDirective(d)" class="inline-flex items-center gap-1 rounded-xl px-3 py-1.5 text-[11px] font-black uppercase text-rose-500 hover:text-rose-700 transition"><Trash2 class="h-3 w-3" /> Remove</button>
                        </div>
                        <p v-if="d.completion_notes" class="text-[11px] text-gray-400 mt-2">Notes: {{ d.completion_notes }}</p>
                    </div>
                    <div v-if="!directives?.data?.length" class="text-center text-sm text-gray-400 py-8">No directives in this view.</div>
                </div>

                <div v-if="directives?.links?.length > 3" class="flex flex-wrap gap-2 justify-center">
                    <Link v-for="link in directives.links" :key="link.label" :href="link.url ?? '#'" v-text="link.label.replace('&laquo;', '«').replace('&raquo;', '»')" :class="['px-3 py-1.5 rounded-xl text-xs font-bold ring-1 transition', link.active ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white dark:bg-zinc-800 text-gray-600 dark:text-gray-300 ring-gray-200 dark:ring-zinc-700']" preserve-scroll />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
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
</style>
