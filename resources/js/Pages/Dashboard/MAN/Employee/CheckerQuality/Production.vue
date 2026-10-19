<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';

const { canEdit } = usePageAccess();
const canEditProduction = computed(() => canEdit('MAN', 'production'));
import {
    Package, CheckCircle, XCircle, AlertTriangle, Sparkles, BadgeCheck,
} from 'lucide-vue-next';

const props = defineProps({
    orders:   Array,
    packages: Array, // packaging outputs awaiting inspection
});

// ─── Confirm modal ────────────────────────────────────────────────────────────
const confirmModal  = ref(false);
const confirmLabel  = ref('');
const confirmSub    = ref('');
const confirmOk     = ref('Confirm');
const confirmAction = ref(null);

const askConfirm = (label, sub, action, okLabel = 'Confirm') => {
    confirmLabel.value  = label;
    confirmSub.value    = sub;
    confirmOk.value     = okLabel;
    confirmAction.value = action;
    confirmModal.value  = true;
};
const runConfirm = () => {
    if (confirmAction.value) confirmAction.value();
    confirmModal.value  = false;
    confirmAction.value = null;
};
const cancelConfirm = () => {
    confirmModal.value  = false;
    confirmAction.value = null;
};

// ─── Actions ──────────────────────────────────────────────────────────────────
const inspect = (pkgId, action, remarks = null) => {
    const data = { action };
    if (action === 'reject' && remarks) data.remarks = remarks;
    router.post(route('man.staff.checker-quality.inspect-package', pkgId), data, { preserveScroll: true });
};

const approve = (pkg) => {
    askConfirm(
        'Pass Quality Check?',
        `Mark package ${pkg.code} as passed. It becomes ready for the Finishing supervisor to push to logistics.`,
        () => inspect(pkg.id, 'approve'),
        'Yes, Pass'
    );
};

const reject = (pkg) => {
    const reason = prompt('Please enter the reason this package failed inspection:');
    if (reason !== null && reason.trim() !== '') {
        inspect(pkg.id, 'reject', reason.trim());
    } else if (reason !== null) {
        alert('A rejection reason is required.');
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
                            <BadgeCheck class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> MAN · Finishing
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Package Inspection</h1>
                            <span v-if="!canEditProduction" class="mt-2 inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-amber-800">View only</span>
                            <p class="text-sm text-blue-100/90">Check each packaging output before the finishing supervisor pushes it to logistics</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur">
                                {{ packages?.length ?? 0 }} awaiting
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="!packages?.length"
                     class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <Package class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No packages awaiting inspection</p>
                    <p class="text-xs text-gray-400 mt-1">Packaging outputs from the Finishing department will appear here.</p>
                </div>

                <!-- Package grid -->
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
                            <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30 ring-1 text-[10px] font-black uppercase px-2.5 py-1 rounded-full">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Awaiting Check
                            </span>
                        </div>
                        <div class="mb-4 space-y-1">
                            <p class="text-sm font-black text-gray-900 dark:text-white tracking-tight">{{ pkg.product_name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ pkg.product_sku || '—' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Fabric: {{ pkg.fabric_code }} ({{ pkg.yarn_type }}, {{ pkg.weight }} kg)</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Quantity: <span class="font-black text-gray-900 dark:text-white">{{ pkg.quantity }} rolls/pcs</span></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Operator: {{ pkg.operator }}</p>
                        </div>
                        <div class="mt-auto flex flex-col sm:flex-row gap-2">
                            <button v-if="canEditProduction"
                                @click="approve(pkg)"
                                class="flex-1 flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black px-3 py-2.5 rounded-2xl shadow-lg shadow-emerald-500/25 transition hover:-translate-y-0.5 active:scale-95">
                                <CheckCircle class="w-3.5 h-3.5" /> Pass Check
                            </button>
                            <button v-if="canEditProduction"
                                @click="reject(pkg)"
                                class="flex-1 flex items-center justify-center gap-1.5 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-rose-600 dark:text-rose-300 hover:bg-rose-100 text-xs font-black px-3 py-2.5 rounded-2xl transition active:scale-95">
                                <XCircle class="w-3.5 h-3.5" /> Reject
                            </button>
                        </div>
                    </div>
                </TransitionGroup>

            </div>
        </div>

        <!-- Confirm modal -->
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
                        <button @click="cancelConfirm" type="button"
                            class="flex-1 py-2.5 rounded-2xl border border-gray-200 dark:border-zinc-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-zinc-800 text-xs font-black uppercase tracking-wide transition active:scale-95">
                            Cancel
                        </button>
                        <button @click="runConfirm" type="button"
                            class="flex-1 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wide shadow-lg shadow-emerald-500/25 transition active:scale-95">
                            {{ confirmOk }}
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
