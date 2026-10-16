<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Bell, Sparkles, CheckCheck, Inbox as InboxIcon } from 'lucide-vue-next';

const props = defineProps({ notifications: Object, filter: String, unreadCount: Number });

const filter = ref(props.filter ?? 'all');
watch(filter, () => router.get(route('ceo.inbox'), { filter: filter.value }, { preserveState: true, preserveScroll: true, replace: true }));

const openItem = (n) => {
    const go = () => {
        try {
            if (n.link_route) router.visit(route(n.link_route));
        } catch (e) { /* unknown route — stay */ }
    };
    if (!n.is_read) {
        router.patch(route('ceo.inbox.read', n.id), {}, { preserveScroll: true, onSuccess: go, onError: go });
    } else {
        go();
    }
};
const markAll = () => router.post(route('ceo.inbox.read-all'), {}, { preserveScroll: true });

const typeBadge = (t) => ({
    payroll: 'bg-indigo-100 text-indigo-700 dark:text-indigo-300 ring-indigo-200',
    vendor: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 ring-blue-200',
    credit: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 ring-amber-200',
    incident: 'bg-rose-100 text-rose-700 ring-rose-200',
    compliance: 'bg-orange-100 text-orange-700 ring-orange-200',
}[t] ?? 'bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-400 ring-gray-200');
const fmtDate = (v) => v ? new Date(v).toLocaleString() : '—';
</script>

<template>
    <Head title="Executive Inbox" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-5xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><Bell class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100"><Sparkles class="h-3.5 w-3.5" /> CEO · Push Alerts</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Executive Inbox</h1>
                            <p class="text-sm text-blue-100/90">{{ unreadCount }} unread · approvals, incidents, compliance</p>
                        </div>
                        <button @click="markAll" class="inline-flex items-center gap-1.5 rounded-2xl bg-white dark:bg-zinc-900 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-indigo-700 dark:text-indigo-300 shadow-lg hover:scale-105 active:scale-95 transition"><CheckCheck class="h-4 w-4" /> Mark all read</button>
                    </div>
                    <div class="relative mt-6 flex gap-1.5 rounded-2xl bg-white/15 p-1 ring-1 ring-white/25 backdrop-blur w-fit">
                        <button @click="filter = 'all'" :class="['px-3 py-1.5 rounded-xl text-xs font-black transition', filter === 'all' ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow' : 'text-white/80 hover:text-white']">All</button>
                        <button @click="filter = 'unread'" :class="['px-3 py-1.5 rounded-xl text-xs font-black transition', filter === 'unread' ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow' : 'text-white/80 hover:text-white']">Unread</button>
                    </div>
                </div>

                <div class="space-y-3">
                    <button v-for="n in notifications?.data ?? []" :key="n.id" @click="openItem(n)"
                        :class="['w-full text-left bg-white/80 dark:bg-zinc-900/80 rounded-3xl border shadow-sm p-5 flex gap-4 items-start hover:shadow-xl transition-all', n.is_read ? 'border-gray-100 dark:border-zinc-800 opacity-75' : 'border-indigo-200 ring-1 ring-indigo-100 dark:ring-indigo-900']">
                        <span :class="['mt-1 h-2.5 w-2.5 rounded-full shrink-0', n.is_read ? 'bg-gray-300 dark:bg-zinc-600' : 'bg-indigo-500 animate-pulse']" />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span :class="typeBadge(n.type)" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase ring-1">{{ n.type }}</span>
                                <span class="text-[11px] text-gray-400 dark:text-zinc-500">{{ fmtDate(n.created_at) }}</span>
                            </div>
                            <p class="font-black text-gray-900 dark:text-zinc-100 mt-1">{{ n.title }}</p>
                            <p v-if="n.body" class="text-sm text-gray-500 dark:text-zinc-400 mt-0.5">{{ n.body }}</p>
                        </div>
                    </button>
                    <div v-if="!notifications?.data?.length" class="flex flex-col items-center py-16 text-center bg-white/80 dark:bg-zinc-900/80 rounded-3xl border border-gray-100 dark:border-zinc-800">
                        <InboxIcon class="h-9 w-9 text-indigo-300 mb-3" />
                        <p class="text-sm font-black text-gray-700 dark:text-zinc-300">Inbox clear. Nothing needs the executive eye right now.</p>
                    </div>
                </div>

                <div v-if="notifications?.links?.length > 3" class="flex flex-wrap gap-2 justify-center">
                    <Link v-for="link in notifications.links" :key="link.label" :href="link.url ?? '#'" v-text="link.label.replace('&laquo;', '«').replace('&raquo;', '»')" :class="['px-3 py-1.5 rounded-xl text-xs font-bold ring-1 transition', link.active ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white dark:bg-zinc-900 text-gray-600 dark:text-zinc-400 ring-gray-200 dark:ring-zinc-700']" preserve-scroll />
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
