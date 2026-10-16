<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FileText, Sparkles, Search, PlusCircle, X, Paperclip, Trash2 } from 'lucide-vue-next';

const props = defineProps({ documents: Object, filters: Object });

const search = ref(props.filters?.search ?? '');
const direction = ref(props.filters?.direction ?? '');
const status = ref(props.filters?.status ?? '');
let timer = null;
const reload = () => router.get(route('secretary.documents'), { search: search.value, direction: direction.value, status: status.value }, { preserveState: true, preserveScroll: true, replace: true });
watch([search, direction, status], () => { clearTimeout(timer); timer = setTimeout(reload, 400); });

const showForm = ref(false);
const form = useForm({ direction: 'incoming', sender: '', recipient: '', subject: '', concerned_module: '', received_date: new Date().toISOString().slice(0, 10), deadline: '', remarks: '', attachment: null });
const submit = () => form.post(route('secretary.documents.store'), { preserveScroll: true, forceFormData: true, onSuccess: () => { showForm.value = false; form.reset(); } });

const advance = (doc, next) => router.patch(route('secretary.documents.status', doc.id), { status: next }, { preserveScroll: true });
const removeDoc = (doc) => { if (confirm(`Remove ${doc.ref_no} from the registry?`)) router.delete(route('secretary.documents.destroy', doc.id), { preserveScroll: true }); };

const FLOW = ['logged', 'forwarded', 'in_progress', 'filed', 'closed'];
const nextStatus = (s) => FLOW[Math.min(FLOW.indexOf(s) + 1, FLOW.length - 1)];
const badge = (s) => s === 'closed' || s === 'filed'
    ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    : s === 'logged' ? 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'
    : 'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30';
const fmtDate = (v) => v ? new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>

<template>
    <Head title="Document & Correspondence Log" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><FileText class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> SEC · Document Control</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Correspondence Log</h1>
                            <p class="text-sm text-blue-100/90">{{ documents?.total ?? 0 }} registered documents</p>
                        </div>
                        <button @click="showForm = !showForm" class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition flex items-center gap-1.5"><PlusCircle class="h-4 w-4" /> Log Document</button>
                    </div>
                </div>

                <Transition name="modal">
                    <div v-if="showForm" class="animate-fade-up bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4"><h2 class="text-lg font-black text-white">Log Incoming / Outgoing Document</h2></div>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Direction</label>
                                <select v-model="form.direction" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"><option value="incoming">Incoming</option><option value="outgoing">Outgoing</option></select></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Module Concerned</label>
                                <input v-model="form.concerned_module" placeholder="e.g. HRM, MAN, FIN" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Sender</label>
                                <input v-model="form.sender" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Recipient</label>
                                <input v-model="form.recipient" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Subject *</label>
                                <input v-model="form.subject" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Received Date *</label>
                                <input v-model="form.received_date" type="date" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Deadline</label>
                                <input v-model="form.deadline" type="date" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Attachment (optional, 10MB)</label>
                                <input type="file" @input="form.attachment = $event.target.files[0]" class="w-full text-sm text-gray-500 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white file:px-4 file:py-2 file:text-xs file:font-black" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Remarks</label>
                                <textarea v-model="form.remarks" rows="2" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                            <div class="sm:col-span-2 flex gap-2">
                                <button type="submit" :disabled="form.processing" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Save Entry</button>
                                <button type="button" @click="showForm = false" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-gray-300 transition active:scale-95">Cancel</button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-4 flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px] relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input v-model="search" placeholder="Search ref no, subject, sender…" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 pl-9 pr-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <select v-model="direction" class="rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2.5 text-sm outline-none"><option value="">All directions</option><option value="incoming">Incoming</option><option value="outgoing">Outgoing</option></select>
                    <select v-model="status" class="rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2.5 text-sm outline-none"><option value="">All statuses</option><option v-for="s in ['logged','forwarded','in_progress','filed','closed']" :key="s" :value="s">{{ s.replace('_',' ') }}</option></select>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ref No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject / Parties</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dates</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="d in documents?.data ?? []" :key="d.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                <td class="px-6 py-4"><p class="font-mono text-xs font-bold">{{ d.ref_no }}</p><p class="text-[11px] text-gray-400 uppercase">{{ d.direction }}{{ d.concerned_module ? ` · ${d.concerned_module}` : '' }}</p></td>
                                <td class="px-6 py-4 text-sm max-w-xs"><p class="font-bold text-gray-900 dark:text-white truncate">{{ d.subject }}</p><p class="text-xs text-gray-500 truncate">{{ d.sender || '—' }} → {{ d.recipient || '—' }}</p>
                                    <a v-if="d.file_path" :href="`/storage/${d.file_path}`" target="_blank" class="text-[11px] font-bold text-indigo-700 hover:underline inline-flex items-center gap-1"><Paperclip class="h-3 w-3" /> Attachment</a></td>
                                <td class="px-6 py-4 text-xs text-gray-500">Recv: {{ fmtDate(d.received_date) }}<br/>Due: {{ fmtDate(d.deadline) }}</td>
                                <td class="px-6 py-4"><span :class="badge(d.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">{{ d.status.replace('_',' ') }}</span></td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <button v-if="d.status !== 'closed'" @click="advance(d, nextStatus(d.status))" class="text-[11px] font-black text-indigo-700 hover:underline mr-3">Advance →</button>
                                    <button @click="removeDoc(d)" class="text-rose-500 hover:text-rose-700"><Trash2 class="h-4 w-4" /></button>
                                </td>
                            </tr>
                            <tr v-if="!documents?.data?.length"><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-400">No documents found.</td></tr>
                        </tbody>
                    </table></div>
                    <div v-if="documents?.links?.length > 3" class="flex flex-wrap gap-2 px-6 py-4 border-t border-gray-100 dark:border-zinc-800">
                        <Link v-for="link in documents.links" :key="link.label" :href="link.url ?? '#'" v-text="link.label.replace('&laquo;', '«').replace('&raquo;', '»')" :class="['px-3 py-1.5 rounded-xl text-xs font-bold ring-1 transition', link.active ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white dark:bg-zinc-800 text-gray-600 dark:text-gray-300 ring-gray-200 dark:ring-zinc-700']" preserve-scroll />
                    </div>
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
