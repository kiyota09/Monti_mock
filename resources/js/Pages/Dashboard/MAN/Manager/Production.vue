<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));
import {
    Package, Shirt, Droplet, Factory,
    CheckCircle, XCircle, ArrowRight,
    Wind, Flame, Truck,
    AlertTriangle, X, Sparkles,
} from 'lucide-vue-next';

const props = defineProps({
    fabrics:      Array,
    dyeJobs:      Array,
    // softenerJobs: Array,
    squeezerJobs: Array,
    ironJobs:     Array,
    packages:     Array, // Now includes product_name, product_sku, quantity, fabric_code, yarn_type, weight, operator
});

// ─── Active Tab ───────────────────────────────────────────────────────────────
const activeTab = ref('fabrics');

const tabs = computed(() => [
    { key: 'fabrics',  label: 'Fabrics',   icon: Shirt,    count: props.fabrics?.length      ?? 0 },
    { key: 'dye',      label: 'Dye',       icon: Droplet,  count: props.dyeJobs?.length      ?? 0 },
    { key: 'softener', label: 'Softener',  icon: Wind,     count: props.softenerJobs?.length ?? 0 },
    { key: 'squeezer', label: 'Squeezer',  icon: Factory,  count: props.squeezerJobs?.length ?? 0 },
    { key: 'iron',     label: 'Iron',      icon: Flame,    count: props.ironJobs?.length     ?? 0 },
    { key: 'packed',   label: 'Packed',    icon: Package,  count: props.packages?.length     ?? 0 },
]);

// ─── Confirm Modal ────────────────────────────────────────────────────────────
const confirmModal   = ref(false);
const confirmLabel   = ref('');
const confirmSub     = ref('');
const confirmAction  = ref(null);

const askConfirm = (label, sub, action) => {
    confirmLabel.value   = label;
    confirmSub.value     = sub;
    confirmAction.value  = action;
    confirmModal.value   = true;
};
const runConfirm = () => {
    if (confirmAction.value) confirmAction.value();
    confirmModal.value   = false;
    confirmAction.value  = null;
};
const cancelConfirm = () => {
    confirmModal.value   = false;
    confirmAction.value  = null;
};

// ─── Actions ──────────────────────────────────────────────────────────────────
const passFabric = (fabricId, destination) =>
    router.post(route('man.staff.checker-quality.pass-fabric', fabricId), { destination });

const passDye = (dyeId, action, rejectionReason = null) => {
    const data = { action };
    if (action === 'reject' && rejectionReason) {
        data.rejection_reason = rejectionReason;
    }
    router.post(route('man.staff.checker-quality.pass-dye', dyeId), data, {
        preserveScroll: true,
    });
};

const rejectDyeWithReason = (dye) => {
    const reason = prompt('Please enter rejection reason:');
    if (reason !== null && reason.trim() !== '') {
        passDye(dye.id, 'reject', reason.trim());
    } else if (reason !== null) {
        alert('Rejection reason is required.');
    }
};

const passSoftener = (softenerId, action) =>
    router.post(route('man.staff.checker-quality.pass-softener', softenerId), { action });

const passSqueezer = (squeezerId) =>
    router.post(route('man.staff.checker-quality.pass-squeezer', squeezerId));

const passIron = (ironId) =>
    router.post(route('man.staff.checker-quality.pass-iron', ironId), { action: 'pack' });

const assignToOrder = (packageId) => {
    const orderId = prompt('Enter Manufacturing Order ID to assign this package to:');
    if (orderId && !isNaN(orderId)) {
        router.post(route('man.staff.checker-quality.assign-package', packageId), { manufacturing_order_id: parseInt(orderId) });
    } else {
        alert('Please enter a valid order ID.');
    }
};

const pushToLogistics = (packageId) => {
    if (confirm('Send this package to logistics?')) {
        router.post(route('man.staff.checker-quality.push-to-logistics', packageId), {}, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Factory class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Manager
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Production Pipeline</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">Track and advance items through each stage</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ (fabrics?.length ?? 0) + (dyeJobs?.length ?? 0) + (packages?.length ?? 0) }} items
                            </span>
                            <span class="hidden sm:flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25 backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse"></span>
                                <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Tab Navigation inside hero -->
                    <div class="relative mt-6 overflow-x-auto pb-1 -mx-1 px-1">
                        <div class="flex gap-2 w-max sm:w-full min-w-full">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                @click="activeTab = tab.key"
                                type="button"
                                class="flex items-center gap-1.5 px-4 py-2.5 rounded-2xl text-xs font-black uppercase tracking-wide whitespace-nowrap transition-all duration-200 flex-1 justify-center active:scale-95"
                                :class="activeTab === tab.key
                                    ? 'bg-white text-indigo-700 shadow-lg scale-105'
                                    : 'bg-white/15 text-white hover:bg-white/25 backdrop-blur'"
                            >
                                <component :is="tab.icon" class="w-3.5 h-3.5 flex-shrink-0" />
                                <span class="hidden sm:inline">{{ tab.label }}</span>
                                <span
                                    class="rounded-full px-1.5 py-0.5 text-[10px] font-black leading-none"
                                    :class="activeTab === tab.key
                                        ? 'bg-indigo-100 text-indigo-700'
                                        : 'bg-white/20 text-white'"
                                >
                                    {{ tab.count }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- FABRICS TAB -->
                <div v-if="activeTab === 'fabrics'">
                    <div v-if="!fabrics?.length"
                         class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Shirt class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No fabrics awaiting quality check</p>
                        <p class="text-xs text-gray-400 mt-1">New fabrics will appear here.</p>
                    </div>

                    <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div
                            v-for="(fabric, i) in fabrics"
                            :key="fabric.id"
                            :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                        >
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-black text-gray-900 dark:text-white font-mono tracking-wide">{{ fabric.code }}</span>
                                <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30 ring-1 text-[10px] font-black uppercase px-2.5 py-1 rounded-full">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Fabric
                                </span>
                            </div>
                            <div class="mb-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Weight: <span class="font-black text-gray-900 dark:text-white">{{ fabric.weight }} kg</span>
                                </p>
                            </div>
                            <div class="mt-auto flex flex-col sm:flex-row gap-2">
                                <button v-if="canEditProduction"
                                    @click="passFabric(fabric.id, 'dyeing')"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black px-3 py-2.5 rounded-2xl shadow-lg shadow-indigo-500/25 transition hover:-translate-y-0.5 active:scale-95"
                                >
                                    <Droplet class="w-3.5 h-3.5" /> Pass to Dyeing
                                </button>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>

                <!-- DYE TAB -->
                <div v-if="activeTab === 'dye'">
                    <div v-if="!dyeJobs?.length"
                         class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Droplet class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No dye jobs in progress</p>
                        <p class="text-xs text-gray-400 mt-1">New dye jobs will appear here.</p>
                    </div>

                    <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div
                            v-for="(dye, i) in dyeJobs"
                            :key="dye.id"
                            :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                        >
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-black text-gray-900 dark:text-white font-mono tracking-wide">{{ dye.code }}</span>
                                <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30 ring-1 text-[10px] font-black uppercase px-2.5 py-1 rounded-full">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Dyeing
                                </span>
                            </div>
                            <div class="mb-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Fabric: <span class="font-bold text-gray-800 dark:text-gray-200 font-mono">{{ dye.fabric?.code }}</span>
                                </p>
                            </div>
                            <div class="mt-auto flex flex-col sm:flex-row gap-2">
                                <button v-if="canEditProduction"
                                    @click="passDye(dye.id, 'quality')"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black px-3 py-2.5 rounded-2xl shadow-lg shadow-indigo-500/25 transition hover:-translate-y-0.5 active:scale-95"
                                >
                                    <CheckCircle class="w-3.5 h-3.5" /> Quality Pass
                                </button>
                                <button v-if="canEditProduction"
                                    @click="rejectDyeWithReason(dye)"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-rose-600 dark:text-rose-300 hover:bg-rose-100 text-xs font-black px-3 py-2.5 rounded-2xl transition active:scale-95"
                                >
                                    <XCircle class="w-3.5 h-3.5" /> Reject
                                </button>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>

                <!-- SOFTENER TAB -->
                <div v-if="activeTab === 'softener'">
                    <div v-if="!softenerJobs?.length"
                         class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Wind class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No softener jobs awaiting quality check</p>
                        <p class="text-xs text-gray-400 mt-1">New softener jobs will appear here.</p>
                    </div>

                    <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div
                            v-for="(job, i) in softenerJobs"
                            :key="job.id"
                            :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                        >
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-black text-gray-900 dark:text-white font-mono tracking-wide">{{ job.code }}</span>
                                <span
                                    class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 inline-flex items-center gap-1"
                                    :class="job.status === 'softened'
                                        ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
                                        : 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ job.status }}
                                </span>
                            </div>
                            <div class="mb-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Fabric: <span class="font-bold text-gray-800 dark:text-gray-200 font-mono">{{ job.fabric?.code }}</span>
                                </p>
                            </div>
                            <div v-if="job.status === 'softened'" class="mt-auto flex flex-col sm:flex-row gap-2">
                                <button v-if="canEditProduction"
                                    @click="passSoftener(job.id, 'quality')"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black px-3 py-2.5 rounded-2xl shadow-lg shadow-indigo-500/25 transition hover:-translate-y-0.5 active:scale-95"
                                >
                                    <CheckCircle class="w-3.5 h-3.5" /> Pass to Squeezer
                                </button>
                                <button v-if="canEditProduction"
                                    @click="passSoftener(job.id, 'resoften')"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-amber-400 hover:bg-amber-500 text-amber-950 text-xs font-black px-3 py-2.5 rounded-2xl transition active:scale-95"
                                >
                                    <Wind class="w-3.5 h-3.5" /> Resoften
                                </button>
                            </div>
                            <div v-else class="mt-auto">
                                <p class="text-xs text-gray-400 italic text-center py-1">Awaiting softening…</p>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>

                <!-- SQUEEZER TAB -->
                <div v-if="activeTab === 'squeezer'">
                    <div v-if="!squeezerJobs?.length"
                         class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Factory class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No squeezer jobs in progress</p>
                        <p class="text-xs text-gray-400 mt-1">New squeezer jobs will appear here.</p>
                    </div>

                    <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div
                            v-for="(job, i) in squeezerJobs"
                            :key="job.id"
                            :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                        >
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-black text-gray-900 dark:text-white font-mono tracking-wide">{{ job.code }}</span>
                                <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30 ring-1 text-[10px] font-black uppercase px-2.5 py-1 rounded-full">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Squeezer
                                </span>
                            </div>
                            <div class="mb-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Fabric: <span class="font-bold text-gray-800 dark:text-gray-200 font-mono">{{ job.softenerJob?.fabric?.code }}</span>
                                </p>
                            </div>
                            <div class="mt-auto flex flex-col sm:flex-row gap-2">
                                <button v-if="canEditProduction"
                                    @click="passSqueezer(job.id)"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black px-3 py-2.5 rounded-2xl shadow-lg shadow-indigo-500/25 transition hover:-translate-y-0.5 active:scale-95"
                                >
                                    <CheckCircle class="w-3.5 h-3.5" /> Pass to Iron
                                </button>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>

                <!-- IRON TAB -->
                <div v-if="activeTab === 'iron'">
                    <div v-if="!ironJobs?.length"
                         class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Flame class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No iron jobs in progress</p>
                        <p class="text-xs text-gray-400 mt-1">New iron jobs will appear here.</p>
                    </div>

                    <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div
                            v-for="(iron, i) in ironJobs"
                            :key="iron.id"
                            :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                        >
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-black text-gray-900 dark:text-white font-mono tracking-wide">{{ iron.code }}</span>
                                <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:ring-orange-500/30 ring-1 text-[10px] font-black uppercase px-2.5 py-1 rounded-full">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Ironing
                                </span>
                            </div>
                            <div class="mb-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Fabric: <span class="font-bold text-gray-800 dark:text-gray-200 font-mono">{{ iron.squeezerJob?.softenerJob?.fabric?.code }}</span>
                                </p>
                            </div>
                            <div class="mt-auto">
                                <button v-if="canEditProduction"
                                    @click="passIron(iron.id)"
                                    class="w-full flex items-center justify-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black px-3 py-2.5 rounded-2xl shadow-lg shadow-indigo-500/25 transition hover:-translate-y-0.5 active:scale-95"
                                >
                                    <Package class="w-3.5 h-3.5" /> Pass to Pack
                                </button>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>

                <!-- PACKED TAB -->
                <div v-if="activeTab === 'packed'">
                    <div v-if="!packages?.length"
                         class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                            <Package class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No packages ready for assignment</p>
                        <p class="text-xs text-gray-400 mt-1">Packed items will appear here.</p>
                    </div>

                    <TransitionGroup v-else name="card" tag="div" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div
                            v-for="(pkg, i) in packages"
                            :key="pkg.id"
                            :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                            class="group relative flex flex-col bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 hover:scale-[1.01] transition-all duration-300 p-5 overflow-hidden"
                        >
                            <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                            <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-black text-gray-900 dark:text-white font-mono tracking-wide">{{ pkg.code }}</span>
                                <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30 ring-1 text-[10px] font-black uppercase px-2.5 py-1 rounded-full">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Packed
                                </span>
                            </div>
                            <div class="mb-4 space-y-1">
                                <p class="text-sm font-black text-gray-900 dark:text-white tracking-tight">{{ pkg.product_name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ pkg.product_sku }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Fabric: {{ pkg.fabric_code }} ({{ pkg.yarn_type }}, {{ pkg.weight }} kg)</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Quantity: <span class="font-black text-gray-900 dark:text-white">{{ pkg.quantity }} rolls/pcs</span></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Operator: {{ pkg.operator }}</p>
                            </div>
                            <div class="mt-auto flex flex-col sm:flex-row gap-2">
                                <button v-if="canEditProduction"
                                    @click="assignToOrder(pkg.id)"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-gray-900 hover:bg-gray-700 text-white text-xs font-black px-3 py-2.5 rounded-2xl transition hover:-translate-y-0.5 active:scale-95"
                                >
                                    <ArrowRight class="w-3.5 h-3.5" /> Assign to Order
                                </button>
                                <button v-if="canEditProduction"
                                    @click="pushToLogistics(pkg.id)"
                                    class="flex-1 flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black px-3 py-2.5 rounded-2xl shadow-lg shadow-emerald-500/25 transition hover:-translate-y-0.5 active:scale-95"
                                >
                                    <Truck class="w-3.5 h-3.5" /> Push to Logistics
                                </button>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>

            </div>
        </div>

        <!-- CONFIRM MODAL -->
        <Transition name="modal">
            <div
                v-if="confirmModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="cancelConfirm"
            >
                <div class="relative w-full max-w-sm bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-zinc-800">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-6 pt-5 pb-4 text-center">
                        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                        <div class="relative w-12 h-12 mx-auto mb-3 rounded-2xl bg-white/15 ring-1 ring-white/30 flex items-center justify-center">
                            <AlertTriangle class="w-6 h-6 text-white" />
                        </div>
                        <h3 class="relative text-base font-black text-white tracking-tight">{{ confirmLabel }}</h3>
                        <p class="relative text-sm text-blue-100/90 mt-1.5 leading-relaxed">{{ confirmSub }}</p>
                    </div>

                    <div class="flex gap-2 px-6 py-5">
                        <button
                            @click="cancelConfirm"
                            type="button"
                            class="flex-1 py-2.5 rounded-2xl border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-zinc-800 text-xs font-black uppercase tracking-wide transition active:scale-95"
                        >
                            Cancel
                        </button>
                        <button
                            @click="runConfirm"
                            type="button"
                            class="flex-1 py-2.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-black uppercase tracking-wide shadow-lg shadow-rose-500/25 transition active:scale-95"
                        >
                            Yes, Reject
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(16px) scale(0.98); }
</style>
