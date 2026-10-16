<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { usePageAccess } from '@/composables/usePageAccess';
import { ClipboardList, Send, X, AlertCircle, CheckCircle, Sparkles, Package } from 'lucide-vue-next';

const { canEdit } = usePageAccess();
const canEditProcurement = computed(() => canEdit('SCM', 'procurement'));

const props = defineProps({ procurementRequests: Array });

// Modal State
const showConfirmModal = ref(false);
const selectedRequestId = ref(null);
const selectedRequestName = ref('');

// Trigger the modal instead of the alert
const confirmSend = (req) => {
    selectedRequestId.value = req.id;
    selectedRequestName.value = req.material_name;
    showConfirmModal.value = true;
};

// Execute the actual post request
const handleSendAction = () => {
    if (!selectedRequestId.value) return;

    router.post(route('scm.procurement-order.send', selectedRequestId.value), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showConfirmModal.value = false;
            selectedRequestId.value = null;
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="SCM Procurement Orders" />

        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <ClipboardList class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> SCM · Procurement
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Procurement Requests</h1>
                            <p class="text-sm text-blue-100/90">Direct materials queue for SCM processing.</p>
                            <span v-if="!canEditProcurement" class="mt-2 inline-block text-xs font-bold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full">View only</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> {{ procurementRequests.length }} queued
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="procurementRequests.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <ClipboardList class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No pending procurement requests.</p>
                    <p class="text-xs text-gray-400 mt-1">New material requests will appear here.</p>
                </div>

                <TransitionGroup v-else name="card" tag="div" class="grid gap-4">
                    <div v-for="(req, i) in procurementRequests" :key="req.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }"
                        class="group relative flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 hover:-translate-y-1.5 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-300 p-5 sm:p-6 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                        <div class="absolute left-0 top-5 bottom-5 w-1 bg-gradient-to-b from-indigo-500 to-fuchsia-500 rounded-r-full scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-300" />
                        <div class="relative flex items-center gap-3 min-w-0">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-blue-600 to-cyan-600 text-white shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0">
                                <Package class="h-6 w-6" />
                            </div>
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center gap-1 text-[9px] font-black px-2 py-0.5 rounded-full ring-1 bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30 uppercase tracking-wider">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" />{{ req.urgency }}
                                    </span>
                                    <p class="font-black text-lg text-gray-900 dark:text-white tracking-tight truncate">{{ req.material_name }}</p>
                                </div>
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">
                                    Volume Order: <span class="text-gray-900 dark:text-white">{{ req.required_qty }} {{ req.unit }}</span>
                                </p>
                            </div>
                        </div>

                        <button v-if="canEditProcurement" @click="confirmSend(req)"
                            class="relative shrink-0 px-6 py-3 bg-gradient-to-br from-indigo-600 to-violet-700 hover:from-indigo-700 hover:to-violet-800 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-2 transition shadow-lg active:scale-95">
                            <Send class="w-4 h-4" /> Send to Procurement
                        </button>
                    </div>
                </TransitionGroup>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="modal">
            <div v-if="showConfirmModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm" @click.self="showConfirmModal = false">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl w-full max-w-md shadow-2xl border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-8 py-6 text-white text-center">
                        <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative flex flex-col items-center">
                            <div class="h-16 w-16 rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur flex items-center justify-center mb-3 animate-pop">
                                <AlertCircle class="w-8 h-8" />
                            </div>
                            <h3 class="text-xl font-black tracking-tight">Verify Transfer</h3>
                            <p class="text-xs text-blue-100/90 mt-1">Confirm procurement handoff</p>
                        </div>
                        <button @click="showConfirmModal = false" class="absolute top-4 right-4 p-2 bg-white/15 hover:bg-white/25 ring-1 ring-white/25 rounded-xl transition backdrop-blur">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="p-8">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 leading-relaxed text-center">
                            Are you sure you want to move <span class="font-black text-indigo-600 dark:text-indigo-300">"{{ selectedRequestName }}"</span> to the Procurement Module for official order creation?
                        </p>

                        <div class="mt-8 flex w-full gap-3">
                            <button
                                @click="showConfirmModal = false"
                                class="flex-1 px-6 py-4 bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-zinc-700 transition active:scale-95"
                            >
                                Cancel
                            </button>
                            <button
                                v-if="canEditProcurement"
                                @click="handleSendAction"
                                class="flex-[1.5] px-6 py-4 bg-gradient-to-br from-indigo-600 to-violet-700 hover:from-indigo-700 hover:to-violet-800 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg transition-all active:scale-95"
                            >
                                Confirm Transfer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            </Transition>
        </Teleport>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-active > div, .modal-leave-active > div { transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from > div, .modal-leave-to > div { opacity: 0; transform: scale(0.95) translateY(10px); }
</style>
