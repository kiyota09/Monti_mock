<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { Search, Plus, X, Check, Ban, Trash2, Play, Flag } from 'lucide-vue-next';

const props = defineProps({
    changes: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    isManager: { type: Boolean, default: false },
});

const showForm = ref(false);

const { canEdit } = usePageAccess();
const canEditChanges = computed(() => canEdit('IT', 'changes'));

const form = useForm({
    title: '', description: '', change_type: 'normal', risk: 'medium',
    scheduled_start: '', scheduled_end: '', rollback_plan: '',
});

const submitChange = () => {
    form.post(route('it.changes.store'), {
        preserveScroll: true, onSuccess: () => { showForm.value = false; form.reset(); },
    });
};

const approve = (id) => router.post(route('it.changes.approve', id), {}, { preserveScroll: true });
const reject = (id) => {
    if (!confirm('Reject this change request?')) return;
    router.post(route('it.changes.reject', id), {}, { preserveScroll: true });
};
const advance = (id, status) => router.post(route('it.changes.status', id), { status }, { preserveScroll: true });
const remove = (id) => {
    if (!confirm('Delete this change request?')) return;
    router.delete(route('it.changes.destroy', id), { preserveScroll: true });
};

const applyFilters = (patch) => {
    router.get(route('it.changes'), { ...props.filters, ...patch }, { preserveScroll: true, replace: true });
};

const rows = computed(() => props.changes.data || []);

const statusClass = (s) => ({
    draft: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    pending_approval: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    approved: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    in_progress: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    rolled_back: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
}[s]);

const riskClass = (r) => ({
    low: 'text-emerald-600', medium: 'text-amber-600', high: 'text-red-600',
}[r]);
</script>

<template>
    <Head title="Change Enablement | IT Department" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Change <span class="text-blue-600">Enablement</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">RFCs with CAB approval and maintenance windows — never surprise production.</p>
            </div>
            <button v-if="canEditChanges" @click="showForm = true"
                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-4 py-3 sm:py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95">
                <Plus class="w-4 h-4" /> File Change
            </button>
            <span v-else class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex flex-col lg:flex-row gap-3 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input :value="filters.search || ''" @input="applyFilters({ search: $event.target.value })" type="text"
                        placeholder="Search change no or title..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-slate-700 dark:text-slate-200 placeholder-slate-400" />
                </div>
                <div class="flex flex-wrap gap-2 [&>select]:flex-1 [&>select]:sm:flex-none [&>select]:min-w-[130px]">
                    <select :value="filters.status || ''" @change="applyFilters({ status: $event.target.value })"
                        class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="">All statuses</option>
                        <option value="open">Open pipeline</option>
                        <option value="pending_approval">Pending CAB</option>
                        <option value="approved">Approved</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                    <select :value="filters.change_type || ''" @change="applyFilters({ change_type: $event.target.value })"
                        class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                        <option value="">All types</option>
                        <option value="standard">Standard</option>
                        <option value="normal">Normal</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[680px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700">
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Change</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Type / Risk</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Window</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="px-4 sm:px-6 py-3 sm:py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <tr v-for="c in rows" :key="c.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                            <td class="px-4 sm:px-6 py-3 sm:py-4">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ c.change_no }} — {{ c.title }}</p>
                                <p class="text-[11px] text-slate-400 truncate max-w-md">{{ c.description }}</p>
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase">{{ c.change_type }}</span>
                                <span :class="['ml-2 inline-flex items-center gap-1 text-[11px] font-black uppercase', riskClass(c.risk)]">
                                    <Flag class="w-3 h-3" />{{ c.risk }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4 text-xs text-slate-500 whitespace-nowrap">
                                {{ c.scheduled_start ? new Date(c.scheduled_start).toLocaleString() : '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4"><span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', statusClass(c.status)]">{{ c.status.replace('_', ' ') }}</span></td>
                            <td class="px-4 sm:px-6 py-3 sm:py-4 text-right whitespace-nowrap">
                                <template v-if="isManager && canEditChanges && ['draft', 'pending_approval'].includes(c.status)">
                                    <button @click="approve(c.id)" title="Approve" class="p-2.5 sm:p-1.5 text-slate-400 hover:text-emerald-600"><Check class="w-4 h-4" /></button>
                                    <button @click="reject(c.id)" title="Reject" class="p-2.5 sm:p-1.5 text-slate-400 hover:text-red-500"><Ban class="w-4 h-4" /></button>
                                </template>
                                <button v-if="c.status === 'approved' && canEditChanges" @click="advance(c.id, 'in_progress')" title="Start work"
                                    class="px-3 py-2 sm:py-1.5 text-xs font-bold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg">Start</button>
                                <template v-if="c.status === 'in_progress' && canEditChanges">
                                    <button @click="advance(c.id, 'completed')" class="px-3 py-2 sm:py-1.5 text-xs font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg">Complete</button>
                                    <button @click="advance(c.id, 'rolled_back')" class="px-3 py-2 sm:py-1.5 text-xs font-bold text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20 rounded-lg">Roll back</button>
                                </template>
                                <button v-if="isManager && canEditChanges && ['draft', 'rejected'].includes(c.status)" @click="remove(c.id)" class="p-2.5 sm:p-1.5 text-slate-400 hover:text-red-500"><Trash2 class="w-4 h-4" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="rows.length === 0" class="py-12 text-center text-sm text-slate-400 font-bold">No changes filed.</div>
            </div>
        </div>

        <!-- RFC modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">File Change Request</h2>
                    <button @click="showForm = false" class="p-1.5 text-slate-400 hover:text-slate-600"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitChange" class="space-y-3">
                    <input v-model="form.title" required placeholder="Change title" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    <textarea v-model="form.description" required rows="3" placeholder="What will change, impact on production, affected systems..."
                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <select v-model="form.change_type" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                            <option value="standard">Standard (pre-approved)</option>
                            <option value="normal">Normal (needs CAB)</option>
                            <option value="emergency">Emergency</option>
                        </select>
                        <select v-model="form.risk" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                            <option value="low">Risk: Low</option><option value="medium">Risk: Medium</option><option value="high">Risk: High</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div><label class="text-[11px] font-bold text-slate-400 uppercase">Window Start</label>
                            <input v-model="form.scheduled_start" type="datetime-local" class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" /></div>
                        <div><label class="text-[11px] font-bold text-slate-400 uppercase">Window End</label>
                            <input v-model="form.scheduled_end" type="datetime-local" class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" /></div>
                    </div>
                    <textarea v-model="form.rollback_plan" rows="2" placeholder="Rollback plan (how to undo if it fails)..."
                        class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
                    <div class="flex items-center gap-2 text-xs text-slate-500"><Play class="w-4 h-4" /> Standard changes auto-approve; normal/emergency go to CAB.</div>
                    <button type="submit" :disabled="form.processing" class="w-full py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50">File Request</button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
