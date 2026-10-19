<template>
    <div class="space-y-5">
        <!-- Summary -->
        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Collected today</p>
                <p class="mt-1 text-2xl font-black text-gray-900 dark:text-white">{{ money(collectionTotals.today) }}</p>
                <p class="text-[11px] text-gray-400">All methods</p>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Pending remittance</p>
                <p class="mt-1 text-2xl font-black text-amber-600 dark:text-amber-300">{{ money(collectionTotals.pending) }}</p>
                <p class="text-[11px] text-gray-400">Hand over to treasury</p>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Lifetime collected</p>
                <p class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-300">{{ money(collectionTotals.lifetime) }}</p>
                <p class="text-[11px] text-gray-400">Excluding voided</p>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-5">
            <!-- Record form -->
            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-2">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Record collection</h3>
                <form @submit.prevent="submit" class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Trip *</label>
                        <select v-model="form.delivery_id" required class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="">Select trip</option>
                            <option v-for="delivery in deliveryOptions" :key="delivery.id" :value="delivery.id">{{ delivery.delivery_number }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Stop / client</label>
                        <select v-model="form.delivery_stop_id" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <option value="">No specific stop</option>
                            <option v-for="stop in selectedStops" :key="stop.id" :value="stop.id">
                                {{ stop.sequence }}. {{ stop.label || stop.client?.company_name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Method</label>
                            <select v-model="form.method" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="e_wallet">E-wallet</option>
                                <option value="credit">Credit (no cash)</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Amount *</label>
                            <input v-model="form.amount" type="number" step="0.01" min="0.01" required
                                class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Reference no.</label>
                        <input v-model="form.reference" type="text" placeholder="OR / cheque / GCash ref"
                            class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Remarks</label>
                        <input v-model="form.remarks" type="text" placeholder="Optional"
                            class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Receipt photo / PDF</label>
                        <input type="file" accept="image/*,application/pdf" @change="onReceipt" class="w-full text-xs text-gray-500 dark:text-gray-400" />
                    </div>
                    <button type="submit" :disabled="form.processing"
                        class="w-full rounded-2xl bg-emerald-600 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-emerald-700 disabled:opacity-50">
                        {{ form.processing ? 'Saving…' : 'Record collection' }}
                    </button>
                </form>
            </section>

            <!-- List -->
            <section class="rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-3">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Recent collections</h3>
                    <span class="text-[10px] font-bold text-gray-400">Pending {{ money(collectionTotals.pending) }}</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                    <div v-for="collection in collections" :key="collection.id" class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-black text-gray-900 dark:text-white">
                                {{ collection.client?.company_name || collection.stop?.label || 'Collection' }}
                                <span class="font-normal text-gray-400">· {{ collection.delivery?.delivery_number }}</span>
                            </p>
                            <p class="text-[11px] text-gray-500">
                                {{ methodLabel(collection.method) }}
                                <span v-if="collection.reference"> · {{ collection.reference }}</span>
                                · {{ formatDateTime(collection.collected_at) }}
                            </p>
                            <a v-if="collection.receipt_path" :href="`/storage/${collection.receipt_path}`" target="_blank"
                                class="text-[10px] font-black uppercase text-indigo-500">Receipt</a>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-black text-gray-900 dark:text-white">{{ money(collection.amount) }}</p>
                            <span :class="statusClass(collection.status)" class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">{{ collection.status }}</span>
                        </div>
                        <button v-if="collection.status === 'pending'" type="button" @click="remit(collection)" :disabled="remitting === collection.id"
                            class="rounded-xl bg-amber-50 px-2.5 py-1.5 text-[10px] font-black uppercase text-amber-600 hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-900/30 dark:text-amber-300">
                            {{ remitting === collection.id ? '…' : 'Remit' }}
                        </button>
                    </div>
                    <div v-if="!collections.length" class="px-5 py-10 text-center text-xs font-bold text-gray-400">No collections recorded.</div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    collections: { type: Array, default: () => [] },
    collectionTotals: { type: Object, default: () => ({ today: 0, pending: 0, remitted: 0, lifetime: 0 }) },
    notify: { type: Function, required: true },
});

const remitting = ref(null);

const form = useForm({
    delivery_id: '',
    delivery_stop_id: '',
    method: 'cash',
    reference: '',
    amount: '',
    remarks: '',
    receipt: null,
});

const deliveryOptions = computed(() => [...props.deliveries, ...props.history]);

const selectedStops = computed(() => {
    const delivery = deliveryOptions.value.find((d) => Number(d.id) === Number(form.delivery_id));
    return delivery?.stops || [];
});

const onReceipt = (event) => {
    form.receipt = event.target.files[0] || null;
};

const submit = () => {
    form.post(route('logistics.conductor.collection.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            props.notify('success', 'Collection recorded.');
            form.reset('amount', 'reference', 'remarks', 'receipt', 'delivery_stop_id');
        },
        onError: (errors) => props.notify('error', Object.values(errors)[0] || 'Could not record collection.'),
    });
};

const remit = (collection) => {
    remitting.value = collection.id;
    router.post(route('logistics.conductor.collection.remit', collection.id), {}, {
        preserveScroll: true,
        onSuccess: () => props.notify('success', 'Collection marked as remitted.'),
        onError: () => props.notify('error', 'Could not update the collection.'),
        onFinish: () => { remitting.value = null; },
    });
};

const money = (value) => {
    const amount = Number(value) || 0;
    return `₱${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const methodLabel = (method) => ({
    cash: 'Cash', cheque: 'Cheque', bank_transfer: 'Bank transfer', e_wallet: 'E-wallet', credit: 'Credit',
}[method] || method);

const statusClass = (status) => ({
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    remitted: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    verified: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    void: 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
}[status] || 'bg-gray-100 text-gray-500');

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>
