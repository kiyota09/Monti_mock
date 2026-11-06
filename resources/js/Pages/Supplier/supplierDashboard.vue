<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SupplierPageHeader from '@/Components/SupplierPageHeader.vue';
import {
    ArrowRight, Banknote, Boxes, CheckCircle2,
    ClipboardList, FileQuestion, MessageSquare, PackageCheck, Receipt,
    ShoppingBag, ShoppingCart, Truck,
} from 'lucide-vue-next';

const props = defineProps({
    auth: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    pendingRfqs: { type: Array, default: () => [] },
    recentOrders: { type: Array, default: () => [] },
    recentInvoices: { type: Array, default: () => [] },
});

const supplier = computed(() => props.auth?.supplier || props.auth?.user || {});
const displayName = computed(() => supplier.value.representative_name || supplier.value.business_name || 'Supplier');
const money = (value) => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const dateLabel = (value) => value ? new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
const orderStatus = (status) => ({
    sent: 'New order', production: 'In production', shipping: 'Shipping',
    delivered: 'Delivered', completed: 'Completed',
}[status] || String(status || 'Pending').replaceAll('_', ' '));
const statusClass = (status) => ({
    sent: 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
    production: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
    shipping: 'bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300',
    delivered: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    completed: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
}[status] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300');

const metrics = computed(() => [
    { label: 'RFQs needing a response', value: props.stats.pendingResponses || 0, icon: FileQuestion, tone: 'blue', href: route('supplier.rfqs') },
    { label: 'Open purchase orders', value: props.stats.openOrders || 0, icon: ShoppingCart, tone: 'indigo', href: route('supplier.orders') },
    { label: 'In production', value: props.stats.inProduction || 0, icon: Boxes, tone: 'amber', href: route('supplier.orders') },
    { label: 'Unpaid invoices', value: props.stats.unpaidInvoices || 0, icon: Receipt, tone: 'emerald', href: route('supplier.payments') },
]);

const metricTone = {
    blue: 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
    indigo: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300',
    amber: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
    emerald: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
};
</script>

<template>
    <Head title="Supplier Dashboard | Monti Textile" />
    <AuthenticatedLayout>
        <main class="w-full space-y-6 p-4 sm:p-6">
            <SupplierPageHeader :supplier-name="supplier.business_name || 'Supplier workspace'" :icon="ClipboardList" :title="`Good day, ${displayName}`" subtitle="Procurement and order overview">
                <template #aside>
                    <Link :href="route('supplier.rfqs')" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-extrabold text-emerald-900 shadow-sm transition hover:bg-white/90">
                        <FileQuestion class="h-4 w-4" /> Review RFQs <ArrowRight class="h-4 w-4" />
                    </Link>
                </template>
            </SupplierPageHeader>

            <section aria-label="Supplier performance overview" class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <Link v-for="metric in metrics" :key="metric.label" :href="metric.href" class="group rounded-3xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-bold leading-5 text-slate-500 dark:text-slate-400 sm:text-sm">{{ metric.label }}</p>
                        <span :class="metricTone[metric.tone]" class="rounded-xl p-2"><component :is="metric.icon" class="h-4 w-4" /></span>
                    </div>
                    <p class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">{{ metric.value }}</p>
                    <span class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-emerald-700 dark:text-emerald-400">View details <ArrowRight class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" /></span>
                </Link>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.8fr)]">
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">
                        <div>
                            <h2 class="font-black text-slate-900 dark:text-white">Purchase order activity</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Latest orders and fulfillment status</p>
                        </div>
                        <Link :href="route('supplier.orders')" class="shrink-0 text-xs font-extrabold text-emerald-700 hover:underline dark:text-emerald-400">All orders</Link>
                    </div>

                    <div v-if="recentOrders.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <Link v-for="order in recentOrders" :key="order.id" :href="route('supplier.orders')" class="flex items-center gap-3 px-4 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/50 sm:px-5">
                            <span class="rounded-xl bg-slate-100 p-2.5 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><PackageCheck class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-extrabold text-slate-900 dark:text-white">{{ order.po_number }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">Issued {{ dateLabel(order.issued_date) }}<template v-if="order.expected_delivery"> · Due {{ dateLabel(order.expected_delivery) }}</template></span>
                            </span>
                            <span class="text-right">
                                <span :class="statusClass(order.status)" class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black">{{ orderStatus(order.status) }}</span>
                                <span class="mt-1 block text-xs font-bold text-slate-700 dark:text-slate-300">{{ money(order.grand_total) }}</span>
                            </span>
                        </Link>
                    </div>
                    <div v-else class="px-5 py-10 text-center">
                        <ShoppingCart class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                        <p class="mt-2 text-sm font-bold text-slate-700 dark:text-slate-300">No purchase orders yet</p>
                        <p class="mt-1 text-xs text-slate-500">New orders from Monti Textile will appear here.</p>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-black text-slate-900 dark:text-white">Quotation requests</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Open opportunities that need your response</p>
                        </div>
                        <span class="rounded-xl bg-blue-50 p-2 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300"><ClipboardList class="h-4 w-4" /></span>
                    </div>
                    <div v-if="pendingRfqs.length" class="mt-4 space-y-3">
                        <div v-for="rfq in pendingRfqs" :key="rfq.id" class="rounded-xl border border-slate-100 p-3 dark:border-slate-800">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-extrabold text-slate-900 dark:text-white">{{ rfq.material_name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ rfq.rfq_number }} · {{ Number(rfq.required_qty).toLocaleString() }} {{ rfq.unit }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Due {{ dateLabel(rfq.deadline) }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
                        <CheckCircle2 class="mb-2 h-5 w-5" /> You’re caught up. No quotations are waiting for a response.
                    </div>
                    <Link :href="route('supplier.rfqs')" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-extrabold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                        Open RFQ page <ArrowRight class="h-4 w-4" />
                    </Link>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div><h2 class="font-black text-slate-900 dark:text-white">Invoice status</h2><p class="mt-0.5 text-xs text-slate-500">Recently submitted supplier invoices</p></div>
                        <Link :href="route('supplier.payments')" class="text-xs font-extrabold text-emerald-700 hover:underline dark:text-emerald-400">Payments</Link>
                    </div>
                    <div v-if="recentInvoices.length" class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                        <div v-for="invoice in recentInvoices" :key="invoice.id" class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                            <span class="rounded-lg bg-emerald-50 p-2 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"><Receipt class="h-4 w-4" /></span>
                            <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ invoice.invoice_number }}</p><p class="text-xs text-slate-500">{{ invoice.po_number || 'Purchase invoice' }} · {{ dateLabel(invoice.invoice_date) }}</p></div>
                            <div class="text-right"><p class="text-sm font-extrabold text-slate-900 dark:text-white">{{ money(invoice.amount) }}</p><p class="text-[10px] font-bold capitalize text-slate-500">{{ invoice.status }}</p></div>
                        </div>
                    </div>
                    <p v-else class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800/60">No supplier invoices have been submitted yet.</p>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                    <h2 class="font-black text-slate-900 dark:text-white">Supplier shortcuts</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Common tasks for your Monti Textile account</p>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <Link :href="route('supplier.messages')" class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm font-bold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-emerald-950/20"><MessageSquare class="h-4 w-4 text-emerald-600" /> Messages</Link>
                        <Link :href="route('supplier.products')" class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm font-bold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-emerald-950/20"><ShoppingBag class="h-4 w-4 text-emerald-600" /> My products</Link>
                        <Link :href="route('supplier.orders')" class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm font-bold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-emerald-950/20"><Truck class="h-4 w-4 text-emerald-600" /> Track orders</Link>
                        <Link :href="route('supplier.payments')" class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm font-bold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50/50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-emerald-950/20"><Banknote class="h-4 w-4 text-emerald-600" /> Payments</Link>
                    </div>
                </section>
            </div>
        </main>
    </AuthenticatedLayout>
</template>
