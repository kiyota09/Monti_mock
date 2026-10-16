<script setup lang="ts">
// Agency portal: request detail (read-only) + propose workers
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps<{
    request: {
        id: number; code: string; department: string; position_skill: string;
        needed: number; filled: number; start_date: string | null; end_date: string | null;
        shift: string | null; notes: string | null; status: string; stored_status: string;
        accepted: Array<{ worker_code: string | null; worker_name: string | null }>;
    };
    proposals: Array<{
        id: number; worker_code: string | null; worker_name: string | null;
        position_skill: string | null; status: string; proposed_at: string | null; review_reason: string | null;
    }>;
    pickable_workers: Array<{ id: number; code: string; name: string; position_skill: string | null }>;
}>();

const page = usePage();
watch(() => (page.props as any).flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true, deep: true });

const proposeForm = useForm({ worker_id: '' as string | number });

function propose() {
    if (!proposeForm.worker_id) return;
    proposeForm.post(route('agency.requests.propose', props.request.id), {
        preserveScroll: true,
        onSuccess: () => proposeForm.reset(),
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
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('agency.requests.index')" class="hover:text-blue-600">Requests</Link>
            <span>/</span>
            <span class="font-semibold">{{ request.code }}</span>
        </nav>
        <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ request.code }} <span class="text-slate-400 font-bold">· {{ request.department }} · {{ request.position_skill }}</span></h1>
        <p class="text-sm text-slate-500 mt-0.5 mb-4">Status: <strong>{{ request.status }}</strong> · {{ request.filled }} of {{ request.needed }} filled · Start {{ request.start_date || '—' }}</p>

        <div class="h-2.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden mb-4">
            <div class="h-full rounded-full bg-blue-600 transition-all" :style="{ width: progressPct() + '%' }"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider mb-3">Propose a worker</h2>
                <p class="text-xs text-slate-500 mb-3">Pick from your Available workers. HR approves or rejects each proposal.</p>
                <form @submit.prevent="propose" class="flex items-center gap-2">
                    <select v-model="proposeForm.worker_id" class="flex-1 px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="">Select worker...</option>
                        <option v-for="w in pickable_workers" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }}){{ w.position_skill ? ' · ' + w.position_skill : '' }}</option>
                    </select>
                    <button type="submit" :disabled="!proposeForm.worker_id || proposeForm.processing" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold disabled:opacity-60 whitespace-nowrap">Propose</button>
                </form>
                <p v-if="pickable_workers.length === 0" class="mt-2 text-[11px] text-slate-400">No Available workers to propose right now.</p>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5">
                <h2 class="text-sm font-black uppercase tracking-wider mb-3">My proposals</h2>
                <div v-if="proposals.length === 0" class="text-xs text-slate-400">No proposals yet.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="p in proposals" :key="p.id" class="py-2 flex items-center justify-between gap-2 text-xs">
                        <div>
                            <span class="font-bold text-slate-900 dark:text-white">{{ p.worker_name }} <span class="font-mono text-slate-400">({{ p.worker_code }})</span></span>
                            <p class="text-slate-500">{{ p.proposed_at || '' }}{{ p.review_reason ? ' · ' + p.review_reason : '' }}</p>
                        </div>
                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', p.status === 'Accepted' ? 'bg-emerald-100 text-emerald-700' : p.status === 'Rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700']">{{ p.status }}</span>
                    </li>
                </ul>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5 lg:col-span-2">
                <h2 class="text-sm font-black uppercase tracking-wider mb-3">Accepted workers</h2>
                <div v-if="request.accepted.length === 0" class="text-xs text-slate-400">No accepted workers yet.</div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    <li v-for="(a, i) in request.accepted" :key="i" class="py-1.5 text-xs font-bold text-slate-800 dark:text-slate-100">
                        {{ a.worker_name }} <span class="font-mono text-slate-400">({{ a.worker_code }})</span>
                    </li>
                </ul>
            </div>
        </div>
    </AgencyLayout>
</template>
