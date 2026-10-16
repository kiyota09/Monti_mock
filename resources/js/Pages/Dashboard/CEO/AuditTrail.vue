<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { History, Sparkles, Search, ShieldCheck, Stamp } from 'lucide-vue-next';

const props = defineProps({ logs: Object, filters: Object, executiveDecisions: Array });

const search = ref(props.filters?.search ?? '');
let timer = null;
const reload = () => router.get(route('ceo.audit'), { search: search.value }, { preserveState: true, preserveScroll: true, replace: true });
watch(search, () => { clearTimeout(timer); timer = setTimeout(reload, 400); });

const fmtDate = (v) => v ? new Date(v).toLocaleString() : '—';
</script>

<template>
    <Head title="Audit Trail" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><History class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> CEO · Governance</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Audit Trail</h1>
                            <p class="text-sm text-blue-100/90">{{ logs?.total ?? 0 }} logged actions · read-only</p>
                        </div>
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5"><ShieldCheck class="h-3.5 w-3.5" /> Tamper-proof log</span>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm p-4">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-zinc-500" />
                        <input v-model="search" placeholder="Search action, reason, person…" class="w-full rounded-2xl border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 pl-9 pr-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">When</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Performed By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Target</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Reason</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="log in logs?.data ?? []" :key="log.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                <td class="px-6 py-4 text-xs text-gray-500 dark:text-zinc-400 whitespace-nowrap">{{ fmtDate(log.created_at) }}</td>
                                <td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 ring-indigo-200 dark:text-indigo-300 dark:ring-indigo-500/30">{{ log.action }}</span></td>
                                <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-zinc-100">{{ log.admin?.name ?? `#${log.admin_id}` }}<p class="text-[11px] font-medium text-gray-400 dark:text-zinc-500">{{ log.admin?.email ?? '' }}</p></td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-zinc-400">{{ log.target?.name ?? log.target_name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-zinc-400 max-w-xs truncate" :title="log.reason">{{ log.reason ?? '—' }}</td>
                            </tr>
                            <tr v-if="!logs?.data?.length"><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-400 dark:text-zinc-500">No audit entries found.</td></tr>
                        </tbody>
                    </table></div>
                    <div v-if="logs?.links?.length > 3" class="flex flex-wrap gap-2 px-6 py-4 border-t border-gray-100 dark:border-zinc-800">
                        <Link v-for="link in logs.links" :key="link.label" :href="link.url ?? '#'" v-text="link.label.replace('&laquo;', '«').replace('&raquo;', '»')" :class="['px-3 py-1.5 rounded-xl text-xs font-bold ring-1 transition', link.active ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white dark:bg-zinc-900 text-gray-600 dark:text-zinc-400 ring-gray-200 dark:ring-zinc-700']" preserve-scroll />
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2">
                        <Stamp class="h-4 w-4 text-indigo-500" />
                        <h2 class="text-sm font-black">Executive Decisions</h2>
                        <span class="ml-auto text-[11px] text-gray-400 dark:text-zinc-500">from Approvals Center · tamper-proof</span>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead class="bg-gray-50/80 dark:bg-zinc-800/50"><tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">When</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Decision</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase">By</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="d in executiveDecisions ?? []" :key="d.id" class="hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition-colors">
                                <td class="px-6 py-3 text-xs text-gray-500 dark:text-zinc-400 whitespace-nowrap">{{ fmtDate(d.created_at) }}</td>
                                <td class="px-6 py-3"><span :class="d.decision === 'approved' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 ring-emerald-200' : 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 ring-rose-200 dark:ring-rose-500/30'" class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ring-1">{{ d.action_type }} · {{ d.decision }}</span></td>
                                <td class="px-6 py-3 text-sm font-bold">{{ d.subject_label }}<p v-if="d.reason" class="text-[11px] font-medium text-gray-400 dark:text-zinc-500">Reason: {{ d.reason }}</p></td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-zinc-400">{{ d.actor?.name ?? '—' }}</td>
                            </tr>
                            <tr v-if="!executiveDecisions?.length"><td colspan="4" class="px-6 py-8 text-center text-xs text-gray-400 dark:text-zinc-500">No executive decisions recorded yet.</td></tr>
                        </tbody>
                    </table></div>
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
</style>
