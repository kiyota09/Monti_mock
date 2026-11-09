<template>
    <Head :title="`Order ${order.po_number}`" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-6xl space-y-6 p-4 pb-16 sm:p-6">
                <section class="rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white shadow-xl sm:p-8">
                    <Link :href="route('client.orders')" class="mb-5 inline-flex items-center gap-2 text-sm font-bold text-blue-100 hover:text-white">
                        <ArrowLeft class="h-4 w-4" /> Back to orders
                    </Link>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">Purchase order details</p>
                            <h1 class="mt-1 font-mono text-2xl font-black sm:text-3xl">{{ order.po_number }}</h1>
                            <p class="mt-2 text-sm text-blue-100">Placed {{ formatDate(order.created_at) }}</p>
                        </div>
                        <span class="rounded-full bg-white/15 px-4 py-2 text-xs font-black uppercase ring-1 ring-white/25">{{ formatStatus(order.status) }}</span>
                    </div>
                </section>

                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="summary in summaries" :key="summary.label" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ summary.label }}</p>
                        <p class="mt-2 text-xl font-black text-gray-900 dark:text-white">{{ summary.value }}</p>
                    </div>
                </section>

                <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-zinc-800">
                        <h2 class="text-sm font-black uppercase tracking-widest">Order items</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-6 py-4">Item</th><th class="px-6 py-4">Specifications</th>
                                    <th class="px-6 py-4 text-right">Quantity</th><th class="px-6 py-4 text-right">Unit price</th><th class="px-6 py-4 text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                                <tr v-for="item in order.items" :key="item.id">
                                    <td class="px-6 py-5 font-bold">{{ item.product?.name || item.fabric || item.design || 'Order item' }}</td>
                                    <td class="px-6 py-5 text-sm text-gray-500 dark:text-gray-400">
                                        <span v-if="item.fabric_type">{{ item.fabric_type }}</span><span v-if="item.color"> · {{ item.color }}</span>
                                        <span v-if="item.recipe"> · {{ item.recipe.name || item.recipe.code || `Recipe #${item.recipe_id}` }}</span>
                                        <span v-if="!item.fabric_type && !item.color && !item.recipe">—</span>
                                    </td>
                                    <td class="px-6 py-5 text-right">{{ item.quantity }}<span v-if="item.kilos"> ({{ item.kilos }} kg)</span></td>
                                    <td class="px-6 py-5 text-right">₱{{ money(item.unit_price || item.price) }}</td>
                                    <td class="px-6 py-5 text-right font-bold">₱{{ money((Number(item.quantity) || 0) * (Number(item.unit_price || item.price) || 0)) }}</td>
                                </tr>
                                <tr v-if="!order.items.length"><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-400">No item details are available for this order.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-3 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="mb-4 text-sm font-black uppercase tracking-widest">Order information</h2>
                        <InfoRow label="Client PO number" :value="order.client_po_number || '—'" />
                        <InfoRow label="Delivery date" :value="formatDate(order.delivery_date)" />
                        <InfoRow label="Expected ship date" :value="formatDate(order.expected_ship_date)" />
                        <InfoRow label="Priority" :value="order.priority || 'Standard'" />
                        <InfoRow label="Payment status" :value="order.payment_status || 'Pending'" />
                        <p v-if="order.notes" class="border-t border-gray-100 pt-3 text-sm text-gray-600 dark:border-zinc-800 dark:text-gray-300">{{ order.notes }}</p>
                    </div>
                    <div class="space-y-3 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="mb-4 text-sm font-black uppercase tracking-widest">Amount summary</h2>
                        <InfoRow label="Subtotal" :value="`₱${money(order.subtotal)}`" />
                        <InfoRow label="Discount" :value="`₱${money(order.discount_amount)}`" />
                        <InfoRow :label="`VAT (${Number(order.tax_rate || 0)}%)`" :value="`₱${money(order.tax_amount)}`" />
                        <InfoRow label="Total amount" :value="`₱${money(order.total_amount)}`" strong />
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, defineComponent, h } from 'vue';
import { ArrowLeft } from 'lucide-vue-next';

const props = defineProps({ order: { type: Object, required: true } });
const money = (value) => Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatDate = (value) => value ? new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
const formatStatus = (value) => (value || 'unknown').replaceAll('_', ' ');
const summaries = computed(() => [
    { label: 'PO number', value: props.order.po_number },
    { label: 'Status', value: formatStatus(props.order.status) },
    { label: 'Items', value: props.order.items.length },
    { label: 'Total amount', value: `₱${money(props.order.total_amount)}` },
]);

const InfoRow = defineComponent({
    props: { label: String, value: String, strong: Boolean },
    setup(rowProps) {
        return () => h('div', { class: 'flex items-start justify-between gap-4 text-sm' }, [
            h('span', { class: 'font-bold text-gray-400' }, rowProps.label),
            h('span', { class: rowProps.strong ? 'text-right font-black text-indigo-600' : 'text-right font-bold text-gray-800 dark:text-gray-100' }, rowProps.value),
        ]);
    },
});
</script>
