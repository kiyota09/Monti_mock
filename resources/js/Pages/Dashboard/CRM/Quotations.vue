<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';

defineProps({ quotations: Array, summary: Object, permissions: { type: Object, default: () => ({}) } });

const peso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });
const fdate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const badge = (s) => ({
    sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    accepted: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
}[s] || 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-300');
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Quotations" />
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-6xl mx-auto space-y-6 pb-16">
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop"><FileText class="h-7 w-7" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">CRM · Issued</p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Quotations</h1>
                            <p class="text-sm text-blue-100/90">{{ summary.sent }} awaiting client · {{ summary.accepted }} accepted · {{ peso(summary.value) }} accepted value. Issued from Client Inquiries.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div v-for="q in quotations" :key="q.id" class="rounded-3xl border border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-mono font-black text-sm">{{ q.quotation_number }}</p>
                            <span :class="['rounded-full px-2.5 py-1 text-[10px] font-black uppercase', badge(q.status)]">{{ q.status }}</span>
                            <span class="ml-auto text-lg font-black text-indigo-600">{{ peso(q.grand_total) }}</span>
                        </div>
                        <p class="text-xs font-bold text-gray-400 mt-1">{{ q.client?.company_name }} · {{ q.payment_terms || '' }} · {{ q.vat_type || '' }} · {{ fdate(q.created_at) }}</p>
                        <div v-if="q.items?.length" class="mt-2 space-y-1">
                            <div v-for="i in q.items" :key="i.id" class="flex justify-between text-xs rounded-xl bg-slate-50 dark:bg-zinc-800 px-3 py-2">
                                <span class="font-bold">{{ i.fabric || i.design || 'Item' }} · {{ i.color || '' }} · {{ i.kilos }}kg</span>
                                <span class="font-black">₱{{ Number(i.unit_price || 0).toLocaleString() }}/kg</span>
                            </div>
                        </div>
                    </div>
                    <p v-if="!quotations?.length" class="text-center text-sm font-bold text-gray-400 py-10">No quotations on record.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
