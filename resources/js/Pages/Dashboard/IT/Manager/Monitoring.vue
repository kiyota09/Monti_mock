<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { Plus, X, Trash2, Server, Wifi, AppWindow, Plug, ShieldCheck, Cpu, Monitor } from 'lucide-vue-next';

const props = defineProps({
    systems: { type: Array, default: () => [] },
    recentChecks: { type: Array, default: () => [] },
    isManager: { type: Boolean, default: true },
});

const showForm = ref(false);
const checking = ref(null);
const checkStatus = ref('operational');
const checkNotes = ref('');

const { canEdit } = usePageAccess();
const canEditMonitoring = computed(() => canEdit('IT', 'monitoring'));

const form = useForm({
    name: '', system_type: 'application', location: '', host: '',
    status: 'operational', notes: '',
});

const submitSystem = () => {
    form.post(route('it.monitoring.store'), {
        preserveScroll: true, onSuccess: () => { showForm.value = false; form.reset(); },
    });
};

const logCheck = () => {
    if (!checking.value) return;
    router.post(route('it.monitoring.check', checking.value.id), {
        status: checkStatus.value, notes: checkNotes.value,
    }, { preserveScroll: true, onSuccess: () => { checking.value = null; checkNotes.value = ''; } });
};

const removeSystem = (system) => {
    if (!confirm(`Stop monitoring ${system.name}? History will be removed.`)) return;
    router.delete(route('it.monitoring.destroy', system.id), { preserveScroll: true });
};

const typeIcon = (t) => ({
    server: Server, network: Wifi, application: AppWindow, power: Plug,
    security: ShieldCheck, plant_ot: Cpu, endpoint: Monitor,
}[t] || Server);

const statusDot = (s) => ({
    operational: 'bg-emerald-500', degraded: 'bg-amber-500',
    down: 'bg-red-500', maintenance: 'bg-blue-500',
}[s]);

const statusPill = (s) => ({
    operational: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    degraded: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    down: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    maintenance: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
}[s]);
</script>

<template>
    <Head title="Systems Monitoring | IT Department" />
    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Systems <span class="text-blue-600">Monitoring</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Servers, network, ERP, power and plant-floor systems.</p>
            </div>
            <button v-if="isManager && canEditMonitoring" @click="showForm = true"
                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 px-4 py-3 sm:py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95">
                <Plus class="w-4 h-4" /> Monitor System
            </button>
            <span v-else-if="!canEditMonitoring" class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div v-for="s in systems" :key="s.id"
                class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-start justify-between mb-3">
                    <div class="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-700">
                        <component :is="typeIcon(s.system_type)" class="h-5 w-5 text-slate-500 dark:text-slate-300" />
                    </div>
                    <span :class="['flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase', statusPill(s.status)]">
                        <span :class="['w-1.5 h-1.5 rounded-full', statusDot(s.status)]"></span>{{ s.status }}
                    </span>
                </div>
                <p class="text-sm font-black text-slate-900 dark:text-white">{{ s.name }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ s.location || '—' }}{{ s.host ? ` · ${s.host}` : '' }}</p>
                <p class="text-[11px] text-slate-400 mt-1">
                    Last check: {{ s.last_checked_at ? new Date(s.last_checked_at).toLocaleString() : 'never' }}
                </p>
                <div v-if="canEditMonitoring" class="flex gap-2 mt-4">
                    <button @click="checking = s; checkStatus = s.status"
                        class="flex-1 py-2 text-xs font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition">
                        Log Check
                    </button>
                    <button v-if="isManager" @click="removeSystem(s)" class="p-2 text-slate-400 hover:text-red-500"><Trash2 class="w-4 h-4" /></button>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-500">Recent Checks</h2>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-700/50">
                <li v-for="c in recentChecks" :key="c.id" class="px-4 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                    <span :class="['w-2 h-2 rounded-full flex-shrink-0', statusDot(c.status)]"></span>
                    <p class="text-sm text-slate-700 dark:text-slate-200 flex-1">
                        <span class="font-bold">{{ c.system?.name }}</span>
                        <span class="text-slate-400"> — {{ c.status }}{{ c.notes ? ` · ${c.notes}` : '' }}</span>
                    </p>
                    <p class="text-[11px] text-slate-400 sm:whitespace-nowrap w-full sm:w-auto sm:text-right">{{ c.checker?.name || 'system' }} · {{ new Date(c.created_at).toLocaleString() }}</p>
                </li>
            </ul>
            <div v-if="recentChecks.length === 0" class="p-8 text-center text-sm text-slate-400">No checks logged yet.</div>
        </div>

        <!-- Add system modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">Monitor System</h2>
                    <button @click="showForm = false" class="p-1.5 text-slate-400 hover:text-slate-600"><X class="w-5 h-5" /></button>
                </div>
                <form @submit.prevent="submitSystem" class="space-y-3">
                    <input v-model="form.name" required placeholder="System name (e.g. Plant-floor WiFi)" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    <div class="grid grid-cols-2 gap-3">
                        <select v-model="form.system_type" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                            <option value="server">Server</option><option value="network">Network</option>
                            <option value="application">Application</option><option value="power">Power</option>
                            <option value="security">Security</option><option value="plant_ot">Plant OT</option>
                            <option value="endpoint">Endpoint</option>
                        </select>
                        <select v-model="form.status" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200">
                            <option value="operational">Operational</option><option value="degraded">Degraded</option>
                            <option value="down">Down</option><option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <input v-model="form.location" placeholder="Location" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                        <input v-model="form.host" placeholder="IP / hostname" class="px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200" />
                    </div>
                    <textarea v-model="form.notes" rows="2" placeholder="Notes" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200"></textarea>
                    <button type="submit" :disabled="form.processing" class="w-full py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 active:scale-95 disabled:opacity-50">Start Monitoring</button>
                </form>
            </div>
        </div>

        <!-- Log check modal -->
        <div v-if="checking" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ checking.name }}</h2>
                    <button @click="checking = null" class="p-1.5 text-slate-400 hover:text-slate-600"><X class="w-5 h-5" /></button>
                </div>
                <select v-model="checkStatus" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 mb-3">
                    <option value="operational">Operational</option><option value="degraded">Degraded</option>
                    <option value="down">Down</option><option value="maintenance">Maintenance</option>
                </select>
                <textarea v-model="checkNotes" rows="2" placeholder="Observation (optional)" class="w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 mb-3"></textarea>
                <button @click="logCheck" class="w-full py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 active:scale-95">Log Check</button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
