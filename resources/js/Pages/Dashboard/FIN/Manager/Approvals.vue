<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Stamp, Search, X, Check, Ban, Package } from 'lucide-vue-next';

const props = defineProps({
    pending: { type: Array, default: () => [] },
    decided: { type: Array, default: () => [] },
});

const peso = (v) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v ?? 0));

const searchQuery = ref('');
const filteredPending = (list) => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return list;
    return (list || []).filter((po) => [po.po_number, po.supplier_name, po.rfq_ref]
        .filter(Boolean).some((v) => String(v).toLowerCase().includes(q)));
};

const decideForm = useForm({ remarks: '' });
const declineTarget = ref(null);
const approveTarget = ref(null);
const approving = ref(false);

const openApprove = (po) => {
    approveTarget.value = po;
};

const submitApprove = () => {
    if (!approveTarget.value || approving.value) return;
    approving.value = true;
    router.post(route('fin.manager.approvals.approve', approveTarget.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { approveTarget.value = null; },
        onFinish: () => { approving.value = false; },
    });
};

const openDecline = (po) => {
    declineTarget.value = po;
    decideForm.reset();
    decideForm.clearErrors();
};

const submitDecline = () => {
    if (!declineTarget.value) return;
    decideForm.post(route('fin.manager.approvals.decline', declineTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => { declineTarget.value = null; },
    });
};

const finBadge = (s) => {
    switch (s) {
        case 'approved': return 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30';
        case 'declined': return 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30';
        default: return 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30';
    }
};
</script>

<template>
    <Head title="PO Approvals" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Stamp class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">FIN · Manager · PO Approvals</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Purchase Order Approvals</h1>
                            <p class="text-sm text-blue-100/90">{{ pending.length }} awaiting decision · accepted PRO quotations land here</p>
                        </div>
                        <div class="relative flex-1 sm:flex-none sm:min-w-[260px]">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="searchQuery" type="text" placeholder="Search PO no., supplier, RFQ…"
                                class="w-full rounded-2xl border-0 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-gray-900 shadow-lg placeholder:text-gray-400 focus:ring-2 focus:ring-white/70 outline-none transition" />
                        </div>
                    </div>
                </div>

                <div class="animate-fade-up rounded-3xl bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 80ms">
                    <div class="p-5 pb-3">
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Awaiting decision</h2>
                        <p class="text-[11px] text-gray-400 font-medium">Approving unlocks the Send PO button in PRO Receipts · declining notifies PRO with your reason</p>
                    </div>
                    <div v-if="filteredPending(pending).length === 0" class="flex flex-col items-center justify-center py-14 text-center">
                        <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4">
                            <Package class="h-9 w-9 text-indigo-400" />
                        </div>
                        <p class="text-sm font-black text-gray-700 dark:text-gray-200">No pending approvals</p>
                        <p class="text-xs text-gray-400 mt-1">Accepted quotations will appear here.</p>
                    </div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-zinc-800">
                        <div v-for="po in filteredPending(pending)" :key="po.id" class="p-5 flex flex-col lg:flex-row gap-4 lg:items-center">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-sm font-black text-gray-900 dark:text-white">{{ po.po_number }}</span>
                                    <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1 bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-300">RFQ {{ po.rfq_ref }}</span>
                                </div>
                                <p class="font-black text-gray-900 dark:text-white mt-1">{{ po.supplier_name }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">Issued {{ po.issued_date }} · {{ po.items.map(i => `${i.material_name} × ${i.qty}${i.unit}`).join(' · ') }}</p>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <p class="font-black text-emerald-600 dark:text-emerald-400 text-lg whitespace-nowrap">{{ peso(po.grand_total) }}</p>
                                <button @click="openDecline(po)" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-300 text-xs font-black uppercase tracking-widest hover:bg-rose-100 transition active:scale-95"><Ban class="h-3.5 w-3.5" /> Decline</button>
                                <button @click="openApprove(po)" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-emerald-600 text-white text-xs font-black uppercase tracking-widest hover:bg-emerald-700 transition active:scale-95"><Check class="h-3.5 w-3.5" /> Approve</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="animate-fade-up rounded-3xl bg-white/80 dark:bg-zinc-900/80 backdrop-blur border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden" style="animation-delay: 160ms">
                    <div class="p-5 pb-3">
                        <h2 class="text-sm font-black tracking-tight text-gray-900 dark:text-white">Recent decisions</h2>
                    </div>
                    <div v-if="!decided?.length" class="px-5 pb-6 text-xs text-gray-400">Nothing decided yet.</div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-zinc-800">
                        <div v-for="po in decided" :key="po.id" class="px-5 py-3.5 flex flex-wrap items-center gap-3">
                            <span class="font-mono text-xs font-black text-gray-900 dark:text-white">{{ po.po_number }}</span>
                            <span class="text-xs text-gray-500 font-bold truncate">{{ po.supplier_name }}</span>
                            <span :class="finBadge(po.finance_status)" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full ring-1">{{ po.finance_status }}</span>
                            <span v-if="po.finance_remarks" class="text-xs text-gray-400 italic truncate">“{{ po.finance_remarks }}”</span>
                            <span class="ml-auto text-xs font-black text-gray-700 dark:text-gray-200">{{ peso(po.grand_total) }}</span>
                        </div>
                    </div>
                </div>

            </div>

            <Transition name="modal">
                <div v-if="approveTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="approveTarget = null" />
                    <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl">
                        <div class="bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 p-5 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-100">Confirm approval</p>
                                    <h3 class="text-lg font-black tracking-tight">Approve this PO?</h3>
                                </div>
                                <button @click="approveTarget = null" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition active:scale-95">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="rounded-2xl bg-slate-50 dark:bg-zinc-950/60 border border-gray-100 dark:border-zinc-800 p-4 text-xs">
                                <p class="font-black text-gray-900 dark:text-white">{{ approveTarget.po_number }} · {{ approveTarget.supplier_name }}</p>
                                <p v-for="item in approveTarget.items" :key="item.material_name" class="text-gray-500 font-medium mt-1">{{ item.material_name }}: {{ item.qty }} {{ item.unit }} @ {{ peso(item.unit_price) }}</p>
                                <p class="mt-2 font-black text-emerald-600 dark:text-emerald-400 text-base">Total: {{ peso(approveTarget.grand_total) }}</p>
                                <p class="text-gray-400 font-medium mt-1">PRO will be notified and the Send PO button unlocks in Receipts.</p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="approveTarget = null" class="flex-1 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-zinc-700 transition active:scale-95">Cancel</button>
                                <button @click="submitApprove" :disabled="approving" class="flex-1 inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white bg-gradient-to-br from-emerald-600 to-teal-600 shadow-lg hover:opacity-95 transition active:scale-95 disabled:opacity-50"><Check class="h-4 w-4" /> {{ approving ? 'Approving…' : 'Yes, approve' }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <Transition name="modal">
                <div v-if="declineTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm" @click="declineTarget = null" />
                    <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 shadow-2xl">
                        <div class="bg-gradient-to-br from-rose-600 via-red-600 to-orange-600 p-5 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-rose-100">Decline {{ declineTarget.po_number }}</p>
                                    <h3 class="text-lg font-black tracking-tight">Reason for PRO</h3>
                                </div>
                                <button @click="declineTarget = null" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition active:scale-95">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div class="p-5 space-y-4">
                            <label class="block">
                                <span class="text-[11px] font-black uppercase tracking-widest text-gray-400">Remarks *</span>
                                <textarea v-model="decideForm.remarks" rows="3" placeholder="e.g. Over budget — re-quote with a lower spec…"
                                    class="mt-1.5 w-full rounded-2xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-rose-500 transition"></textarea>
                                <p v-if="decideForm.errors.remarks" class="text-[11px] font-bold text-rose-600 mt-1">{{ decideForm.errors.remarks }}</p>
                            </label>
                            <div class="flex gap-2">
                                <button @click="declineTarget = null" class="flex-1 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-300 hover:bg-gray-200 transition active:scale-95">Cancel</button>
                                <button @click="submitDecline" :disabled="decideForm.processing" class="flex-1 rounded-2xl px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white bg-gradient-to-br from-rose-600 to-orange-600 shadow-lg hover:opacity-95 transition active:scale-95 disabled:opacity-50">{{ decideForm.processing ? 'Saving…' : 'Confirm decline' }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
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
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(12px) scale(0.98); }
</style>
