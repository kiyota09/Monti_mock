<template>
    <AuthenticatedLayout>
        <Head title="Order Returns" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 pb-16">

                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Undo2 class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <Sparkles class="h-3.5 w-3.5" /> ORD · RMA
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Returns & Claims</h1>
                            <p class="text-sm text-blue-100/90">Shortages, rejects and client returns with resolution tracking</p>
                        </div>
                        <button v-if="canEditReturns" @click="showCreate = true" class="rounded-2xl bg-white px-4 py-2.5 text-sm font-black text-indigo-700 shadow-lg transition-all hover:bg-blue-50 active:scale-95">+ File return</button>
                        <span v-else class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25">View only</span>
                    </div>
                </div>

                <div class="animate-fade-up overflow-hidden rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 shadow-sm" style="animation-delay: 80ms">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[56rem] text-sm">
                            <thead><tr class="text-left text-[10px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100 dark:border-zinc-800">
                                <th class="px-4 py-3">RMA</th><th class="px-4 py-3">Order</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Resolve</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="r in returns" :key="r.id" class="border-b border-gray-50 dark:border-zinc-800/60 hover:bg-indigo-50/40 transition-colors">
                                    <td class="px-4 py-3 font-black">{{ r.return_number }}<p class="text-[11px] font-medium text-gray-400">{{ r.created_at }} · {{ r.created_by }}</p></td>
                                    <td class="px-4 py-3">{{ r.jo_number }}<p class="text-[11px] text-gray-400">{{ r.client_name }}</p></td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase">{{ formatLabel(r.type) }}</span></td>
                                    <td class="px-4 py-3 text-right font-black">{{ r.quantity }} kg</td>
                                    <td class="px-4 py-3 text-xs text-gray-500 max-w-56 truncate" :title="r.reason">{{ r.reason }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase border" :class="returnBadge(r.status)">{{ formatLabel(r.status) }}</span>
                                        <p v-if="r.resolved_at" class="text-[11px] text-gray-400 mt-0.5">{{ r.resolved_at }} · {{ r.resolved_by }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <form v-if="canEditReturns" @submit.prevent="resolveReturn(r)" class="flex justify-end gap-1">
                                            <select v-model="resolveState[r.id]" class="rounded-xl border px-2 py-1.5 text-xs">
                                                <option v-for="s in openStatuses" :key="s" :value="s">{{ formatLabel(s) }}</option>
                                            </select>
                                            <button class="rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-black text-white hover:bg-emerald-500">Save</button>
                                        </form>
                                    </td>
                                </tr>
                                <tr v-if="!returns.length"><td colspan="7" class="px-4 py-10 text-center text-gray-400">No returns filed.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreate" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/40 backdrop-blur-sm" @click.self="showCreate = false">
                    <form @submit.prevent="submitReturn" class="bg-white dark:bg-zinc-900 w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl shadow-2xl p-5">
                        <h3 class="font-black text-lg mb-3">File a return</h3>
                        <div class="space-y-3">
                            <label class="block text-sm font-bold">Job order
                                <select v-model="retForm.sales_order_id" required class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-medium">
                                    <option value="">Select delivered order…</option>
                                    <option v-for="o in deliveredOrders" :key="o.id" :value="o.id">{{ o.jo_number }} · {{ o.client_name }} ({{ o.quantity }} kg)</option>
                                </select>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="block text-sm font-bold">Type
                                    <select v-model="retForm.type" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm">
                                        <option v-for="t in types" :key="t" :value="t">{{ formatLabel(t) }}</option>
                                    </select>
                                </label>
                                <label class="block text-sm font-bold">Quantity (kg)
                                    <input v-model.number="retForm.quantity" type="number" min="0.01" step="0.01" required class="mt-1 w-full rounded-xl border px-3 py-2 text-sm" />
                                </label>
                            </div>
                            <label class="block text-sm font-bold">Reason
                                <textarea v-model="retForm.reason" rows="3" required class="mt-1 w-full rounded-xl border px-3 py-2 text-sm"></textarea>
                            </label>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="showCreate = false" class="rounded-xl border px-4 py-2 text-sm font-bold">Cancel</button>
                            <button type="submit" :disabled="retForm.processing" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white disabled:opacity-50">File return</button>
                        </div>
                    </form>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePageAccess } from '@/composables/usePageAccess';
import { Sparkles, Undo2 } from 'lucide-vue-next';

const props = defineProps({ returns: Array, types: Array, statuses: Array, deliveredOrders: Array });

const { canEdit } = usePageAccess();
const canEditReturns = computed(() => canEdit('ORD', 'returns'));

const openStatuses = props.statuses.filter((s) => s !== 'pending');
const resolveState = reactive({});
for (const r of props.returns) resolveState[r.id] = r.status === 'pending' ? 'inspected' : r.status;

const resolveReturn = (r) => {
    router.post(route('ord.returns.resolve', { id: r.id }), { status: resolveState[r.id] }, { preserveScroll: true });
};

const formatLabel = (s) => String(s || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const returnBadge = (s) => ({
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    inspected: 'bg-blue-50 text-blue-700 border-blue-200',
    credited: 'bg-green-50 text-green-700 border-green-200',
    rejected: 'bg-gray-100 text-gray-500 border-gray-200',
    closed: 'bg-green-50 text-green-700 border-green-200',
}[s] || 'bg-gray-100 text-gray-600 border-gray-200');

const showCreate = ref(false);
const retForm = useForm({ sales_order_id: '', type: 'shortage', quantity: 0.01, reason: '' });
const submitReturn = () => retForm.post(route('ord.returns.store'), { onSuccess: () => { showCreate.value = false; retForm.reset(); } });
</script>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.modal-enter-active { transition: opacity 0.25s ease, transform 0.32s cubic-bezier(0.22,1,0.36,1); }
.modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: translateY(18px) scale(0.97); }
</style>
