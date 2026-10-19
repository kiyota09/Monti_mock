<template>
    <div class="space-y-5">
        <!-- Summary -->
        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Open items</p>
                <p class="mt-1 text-2xl font-black text-rose-600 dark:text-rose-300">{{ returnTotals.open }}</p>
                <p class="text-[11px] text-gray-400">Awaiting warehouse action</p>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Open quantity</p>
                <p class="mt-1 text-2xl font-black text-gray-900 dark:text-white">{{ returnTotals.open_quantity }}</p>
                <p class="text-[11px] text-gray-400">Pieces / rolls to reconcile</p>
            </div>
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Resolved</p>
                <p class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-300">{{ returnTotals.resolved }}</p>
                <p class="text-[11px] text-gray-400">Closed by warehouse</p>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-5">
            <!-- Form -->
            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-2">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Log return / damage</h3>
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
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Package no.</label>
                            <input v-model="form.package_number" type="text" placeholder="Optional"
                                class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Quantity *</label>
                            <input v-model="form.quantity" type="number" min="1" required
                                class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Product / description</label>
                        <input v-model="form.product_name" type="text" placeholder="Fabric, shade, roll no..."
                            class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Reason *</label>
                            <select v-model="form.reason" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                <option value="damaged">Damaged</option>
                                <option value="wrong_item">Wrong item / shade</option>
                                <option value="shortage">Shortage</option>
                                <option value="overage">Overage</option>
                                <option value="refused">Refused by client</option>
                                <option value="expired">Expired</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Action</label>
                            <select v-model="form.action" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                <option value="return_to_warehouse">Return to warehouse</option>
                                <option value="replace">Replace</option>
                                <option value="credit">Credit / refund</option>
                                <option value="dispose">Dispose</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Notes</label>
                        <textarea v-model="form.notes" rows="2" placeholder="Describe the condition and client instruction..."
                            class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Photo evidence</label>
                        <input type="file" accept="image/*" @change="onPhoto" class="w-full text-xs text-gray-500 dark:text-gray-400" />
                    </div>
                    <button type="submit" :disabled="form.processing"
                        class="w-full rounded-2xl bg-rose-600 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-rose-700 disabled:opacity-50">
                        {{ form.processing ? 'Saving…' : 'Log return' }}
                    </button>
                </form>
            </section>

            <!-- List -->
            <section class="rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-3">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Return log</h3>
                    <span class="text-[10px] font-bold text-gray-400">{{ returnTotals.open }} open</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                    <div v-for="entry in returns" :key="entry.id" class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-black text-gray-900 dark:text-white">
                                {{ entry.product_name || entry.package_number || 'Return' }}
                                <span class="font-normal text-gray-400">· {{ entry.delivery?.delivery_number }}</span>
                            </p>
                            <p class="text-[11px] text-gray-500">
                                {{ reasonLabel(entry.reason) }} · {{ entry.quantity }} pc(s) · {{ actionLabel(entry.action) }}
                            </p>
                            <p class="text-[10px] text-gray-400">{{ entry.client?.company_name || entry.stop?.label || '—' }} · {{ formatDateTime(entry.reported_at) }}</p>
                            <a v-if="entry.photo_path" :href="`/storage/${entry.photo_path}`" target="_blank"
                                class="text-[10px] font-black uppercase text-indigo-500">Photo</a>
                        </div>
                        <div class="text-right">
                            <span :class="entry.status === 'resolved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'"
                                class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">{{ entry.status }}</span>
                        </div>
                        <button v-if="entry.status === 'open'" type="button" @click="resolve(entry)" :disabled="resolving === entry.id"
                            class="rounded-xl bg-emerald-50 px-2.5 py-1.5 text-[10px] font-black uppercase text-emerald-600 hover:bg-emerald-100 disabled:opacity-50 dark:bg-emerald-900/30 dark:text-emerald-300">
                            {{ resolving === entry.id ? '…' : 'Resolve' }}
                        </button>
                    </div>
                    <div v-if="!returns.length" class="px-5 py-10 text-center text-xs font-bold text-gray-400">No returns or damages logged.</div>
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
    returns: { type: Array, default: () => [] },
    returnTotals: { type: Object, default: () => ({ open: 0, resolved: 0, open_quantity: 0 }) },
    notify: { type: Function, required: true },
});

const resolving = ref(null);

const form = useForm({
    delivery_id: '',
    delivery_stop_id: '',
    package_number: '',
    product_name: '',
    quantity: 1,
    reason: 'damaged',
    action: 'return_to_warehouse',
    notes: '',
    photo: null,
});

const deliveryOptions = computed(() => [...props.deliveries, ...props.history]);

const selectedStops = computed(() => {
    const delivery = deliveryOptions.value.find((d) => Number(d.id) === Number(form.delivery_id));
    return delivery?.stops || [];
});

const onPhoto = (event) => {
    form.photo = event.target.files[0] || null;
};

const submit = () => {
    form.post(route('logistics.conductor.return.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            props.notify('success', 'Return logged.');
            form.reset('package_number', 'product_name', 'quantity', 'notes', 'photo', 'delivery_stop_id');
            form.quantity = 1;
        },
        onError: (errors) => props.notify('error', Object.values(errors)[0] || 'Could not log the return.'),
    });
};

const resolve = (entry) => {
    resolving.value = entry.id;
    router.post(route('logistics.conductor.return.resolve', entry.id), {}, {
        preserveScroll: true,
        onSuccess: () => props.notify('success', 'Return marked as resolved.'),
        onError: () => props.notify('error', 'Could not update the return.'),
        onFinish: () => { resolving.value = null; },
    });
};

const reasonLabel = (reason) => ({
    damaged: 'Damaged', wrong_item: 'Wrong item / shade', shortage: 'Shortage', overage: 'Overage',
    refused: 'Refused', expired: 'Expired', other: 'Other',
}[reason] || reason);

const actionLabel = (action) => ({
    return_to_warehouse: 'Return to warehouse', replace: 'Replace', credit: 'Credit / refund', dispose: 'Dispose',
}[action] || action);

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>
