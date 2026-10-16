<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Megaphone, Sparkles, Search, PlusCircle, Trash2, Send, Archive } from 'lucide-vue-next';

const props = defineProps({ memos: Object, filters: Object, employees: { type: Array, default: () => [] } });

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
let timer = null;
const reload = () => router.get(route('secretary.memos'), { search: search.value, status: status.value }, { preserveState: true, preserveScroll: true, replace: true });
watch([search, status], () => { clearTimeout(timer); timer = setTimeout(reload, 400); });

const showForm = ref(false);
const form = useForm({ title: '', body: '', audience: 'all', employee_ids: [], priority: 'normal' });
const empSearch = ref('');
const filteredEmployees = computed(() => {
    const q = empSearch.value.trim().toLowerCase();
    const list = props.employees || [];
    if (!q) return list.slice(0, 50);
    return list.filter((e) =>
        (e.name || '').toLowerCase().includes(q) ||
        (e.role || '').toLowerCase().includes(q) ||
        (e.position || '').toLowerCase().includes(q)).slice(0, 50);
});
const toggleEmployee = (id) => {
    const i = form.employee_ids.indexOf(id);
    if (i > -1) form.employee_ids.splice(i, 1);
    else form.employee_ids.push(id);
};
const audienceLabel = (a) => {
    const known = {
        all: 'Everyone', managers: 'All Managers', supervisors: 'All Supervisors',
        staffs: 'All Staff', special_officers: 'Special Officers',
    };
    if (known[a]) return known[a];
    const ids = String(a || '').startsWith('users:') ? String(a).slice(6).split(',').map(Number).filter(Boolean)
        : String(a || '').startsWith('user:') ? [Number(String(a).slice(5))] : [];
    if (!ids.length) return a;
    const names = ids.map((id) => props.employees.find((e) => Number(e.id) === id)?.name || `#${id}`);
    return names.length <= 2 ? names.join(', ') : `${names.slice(0, 2).join(', ')} +${names.length - 2} more`;
};
const submit = () => {
    form.transform((data) => ({
        ...data,
        audience: data.audience === 'specific' ? `users:${data.employee_ids.join(',')}` : data.audience,
    })).post(route('secretary.memos.store'), {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false; form.reset(); empSearch.value = ''; },
        onError: () => form.transform((data) => data),
    });
};

const publish = (m) => { if (confirm(`Publish ${m.ref_no}? It becomes visible as an active office memo.`)) router.post(route('secretary.memos.publish', m.id), {}, { preserveScroll: true }); };
const archive = (m) => router.post(route('secretary.memos.archive', m.id), {}, { preserveScroll: true });
const removeMemo = (m) => { if (confirm(`Remove ${m.ref_no}?`)) router.delete(route('secretary.memos.destroy', m.id), { preserveScroll: true }); };

const badge = (s) => s === 'published' ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
    : s === 'archived' ? 'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-zinc-800 dark:text-gray-400 dark:ring-zinc-700'
    : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
const fmtDate = (v) => v ? new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>

<template>
    <Head title="Memos & Announcements" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Megaphone class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> SEC · Office Issuances</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Memos & Announcements</h1>
                            <p class="text-sm text-blue-100/90">{{ memos?.total ?? 0 }} memos on record</p>
                        </div>
                        <button @click="showForm = !showForm" class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition flex items-center gap-1.5"><PlusCircle class="h-4 w-4" /> Draft Memo</button>
                    </div>
                </div>

                <Transition name="modal">
                    <div v-if="showForm" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 py-4"><h2 class="text-lg font-black text-white">Draft a Memo</h2></div>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-6">
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Title *</label>
                                <input v-model="form.title" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" /></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Body *</label>
                                <textarea v-model="form.body" rows="5" required class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea></div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Audience *</label>
                                <select v-model="form.audience" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="all">Everyone (all employees)</option>
                                    <option value="managers">All Managers</option>
                                    <option value="supervisors">All Supervisors</option>
                                    <option value="staffs">All Staff</option>
                                    <option value="special_officers">Special Officers</option>
                                    <option value="specific">Specific employee…</option>
                                </select>
                                <p v-if="form.errors.audience" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.audience }}</p>
                            </div>
                            <div v-if="form.audience === 'specific'">
                                <label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Employees * ({{ form.employee_ids.length }} selected)</label>
                                <div class="rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                                    <div class="p-2 border-b border-gray-100 dark:border-zinc-700">
                                        <input v-model="empSearch" placeholder="Search name, role, position…"
                                            class="w-full rounded-xl bg-gray-50 dark:bg-zinc-900 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <div class="max-h-44 overflow-y-auto p-1.5">
                                        <label v-for="e in filteredEmployees" :key="e.id"
                                            class="flex cursor-pointer items-center gap-2.5 rounded-xl px-2.5 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                                            <input type="checkbox" :checked="form.employee_ids.includes(e.id)" @change="toggleEmployee(e.id)"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate font-bold text-gray-800 dark:text-gray-200">{{ e.name }}</span>
                                                <span class="block truncate text-[11px] text-gray-400">{{ e.role }} · {{ (e.position || '').replace('_', ' ') }}</span>
                                            </span>
                                        </label>
                                        <p v-if="!filteredEmployees.length" class="px-2 py-4 text-center text-xs font-bold text-gray-400">No matches.</p>
                                    </div>
                                </div>
                                <div v-if="form.employee_ids.length" class="mt-1.5 flex flex-wrap gap-1.5">
                                    <span v-for="id in form.employee_ids" :key="id"
                                        class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-black text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                        {{ (employees.find((e) => e.id === id) || {}).name || `#${id}` }}
                                        <button type="button" @click="toggleEmployee(id)" class="hover:text-rose-500">✕</button>
                                    </span>
                                </div>
                            </div>
                            <div class="sm:col-span-2 rounded-2xl bg-indigo-50 dark:bg-indigo-900/20 px-4 py-2.5 text-[11px] font-bold text-indigo-700 dark:text-indigo-300">
                                CEO &amp; COO are always notified and can see the target audience ({{ audienceLabel(form.audience === 'specific' ? `users:${form.employee_ids.join(',')}` : form.audience) }}).
                            </div>
                            <div><label class="block text-xs font-black uppercase tracking-wide text-gray-500 mb-1.5">Priority</label>
                                <select v-model="form.priority" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"><option value="normal">Normal</option><option value="urgent">Urgent</option></select></div>
                            <div class="sm:col-span-2 flex gap-2">
                                <button type="submit" :disabled="form.processing" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg hover:bg-indigo-700 active:scale-95 transition disabled:opacity-50">Save Draft</button>
                                <button type="button" @click="showForm = false" class="rounded-2xl bg-gray-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-black uppercase text-gray-600 dark:text-gray-300 transition active:scale-95">Cancel</button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-4 flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px] relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input v-model="search" placeholder="Search ref no or title…" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 pl-9 pr-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <select v-model="status" class="rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2.5 text-sm outline-none"><option value="">All statuses</option><option v-for="s in ['draft','published','archived']" :key="s" :value="s">{{ s }}</option></select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="m in memos?.data ?? []" :key="m.id" class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-5 flex flex-col gap-3">
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="font-mono text-[11px] font-bold text-gray-400">{{ m.ref_no }}</p>
                                <h3 class="font-black text-gray-900 dark:text-white leading-snug">{{ m.title }}</h3>
                            </div>
                            <span :class="badge(m.status)" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 shrink-0">{{ m.status }}</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line line-clamp-4">{{ m.body }}</p>
                        <p class="text-[11px] text-gray-400">To: {{ audienceLabel(m.audience) }} · {{ m.priority }} · {{ m.published_at ? fmtDate(m.published_at) : 'not published' }}</p>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button v-if="m.status === 'draft'" @click="publish(m)" class="inline-flex items-center gap-1 rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black uppercase text-white hover:bg-indigo-700 active:scale-95 transition"><Send class="h-3 w-3" /> Publish</button>
                            <button v-if="m.status === 'published'" @click="archive(m)" class="inline-flex items-center gap-1 rounded-xl bg-gray-100 dark:bg-zinc-800 px-3 py-1.5 text-[11px] font-black uppercase text-gray-600 dark:text-gray-300 hover:bg-gray-200 active:scale-95 transition"><Archive class="h-3 w-3" /> Archive</button>
                            <button @click="removeMemo(m)" class="inline-flex items-center gap-1 rounded-xl px-3 py-1.5 text-[11px] font-black uppercase text-rose-500 hover:text-rose-700 transition"><Trash2 class="h-3 w-3" /> Remove</button>
                        </div>
                    </div>
                </div>
                <div v-if="!memos?.data?.length" class="text-center text-sm text-gray-400 py-8">No memos found.</div>
                <div v-if="memos?.links?.length > 3" class="flex flex-wrap gap-2 justify-center">
                    <Link v-for="link in memos.links" :key="link.label" :href="link.url ?? '#'" v-text="link.label.replace('&laquo;', '«').replace('&raquo;', '»')" :class="['px-3 py-1.5 rounded-xl text-xs font-bold ring-1 transition', link.active ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white dark:bg-zinc-800 text-gray-600 dark:text-gray-300 ring-gray-200 dark:ring-zinc-700']" preserve-scroll />
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
.line-clamp-4 { display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
</style>
