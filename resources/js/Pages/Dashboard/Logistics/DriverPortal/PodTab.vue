<template>
    <div class="space-y-5">
        <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Proof of Delivery</p>
                    <h2 class="mt-1 text-lg font-black text-gray-900 dark:text-white">Stops awaiting completion</h2>
                </div>
                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                    {{ pendingStops.length }} pending
                </span>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-2">
            <article v-for="item in pendingStops" :key="item.stop.id"
                class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ item.delivery.delivery_number }} · Stop {{ item.stop.sequence }}</p>
                        <p class="mt-0.5 truncate text-sm font-black text-gray-900 dark:text-white">{{ item.stop.label || item.stop.client?.company_name || 'Drop-off' }}</p>
                        <p class="truncate text-xs text-gray-500">{{ item.stop.address || 'No address' }}</p>
                    </div>
                    <span :class="item.stop.status === 'arrived' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400'"
                        class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ item.stop.status }}</span>
                </div>
                <button type="button" @click="open(item)"
                    class="mt-3 w-full rounded-2xl bg-emerald-600 py-2.5 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-emerald-700 active:scale-95">
                    Complete / Record POD
                </button>
            </article>

            <div v-if="!pendingStops.length" class="rounded-3xl border border-dashed border-gray-200 bg-white py-14 text-center dark:border-zinc-800 dark:bg-zinc-900 lg:col-span-2">
                <CheckCircle2 class="mx-auto h-8 w-8 text-emerald-400" />
                <p class="mt-2 text-sm font-bold text-gray-500 dark:text-gray-400">All assigned stops are handled.</p>
            </div>
        </div>

        <!-- Completed -->
        <div class="rounded-3xl border border-gray-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-zinc-800">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Recent POD records</h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-zinc-800">
                <div v-for="item in completedStops" :key="item.stop.id" class="flex flex-wrap items-center gap-3 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ item.stop.label || item.stop.client?.company_name }} <span class="text-xs font-normal text-gray-400">· {{ item.delivery.delivery_number }}</span></p>
                        <p class="text-[11px] text-gray-500">{{ item.stop.recipient_name || 'Recipient' }} · {{ formatDateTime(item.stop.completed_at) }}</p>
                    </div>
                    <span :class="outcomePill(item.stop.outcome)" class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase">{{ item.stop.outcome }}</span>
                    <a v-if="item.stop.signature_path" :href="`/storage/${item.stop.signature_path}`" target="_blank" class="text-[10px] font-black uppercase text-indigo-500 hover:text-indigo-600">Signature</a>
                    <a v-if="item.stop.pod_photo_path" :href="`/storage/${item.stop.pod_photo_path}`" target="_blank" class="text-[10px] font-black uppercase text-indigo-500 hover:text-indigo-600">Photo</a>
                </div>
                <div v-if="!completedStops.length" class="px-5 py-8 text-center text-xs font-bold text-gray-400">No completions recorded yet.</div>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="close">
                    <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                        <div class="sticky top-0 z-10 flex items-center justify-between bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 px-5 py-4 text-white">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-100">Proof of Delivery</p>
                                <h3 class="text-lg font-black">{{ form.outcome === 'refused' ? 'Record refusal' : 'Complete stop' }}</h3>
                            </div>
                            <button type="button" @click="close" class="rounded-xl p-1.5 hover:bg-white/20"><X class="h-5 w-5" /></button>
                        </div>

                        <form @submit.prevent="submit" class="space-y-4 p-5">
                            <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-zinc-800/60">
                                <p class="text-xs font-black text-gray-900 dark:text-white">{{ selected?.stop?.label || selected?.stop?.client?.company_name }}</p>
                                <p class="text-[11px] text-gray-500">{{ selected?.stop?.address }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Outcome *</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button v-for="opt in outcomes" :key="opt.value" type="button" @click="form.outcome = opt.value"
                                        :class="form.outcome === opt.value ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700'"
                                        class="rounded-xl py-2 text-[10px] font-black uppercase tracking-wide ring-1 transition">
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Recipient name</label>
                                    <input v-model="form.recipient_name" type="text" placeholder="Who received it?"
                                        class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Package / QR code</label>
                                    <input v-model="form.package_code" type="text" placeholder="Scan or type code"
                                        class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Notes</label>
                                <textarea v-model="form.pod_notes" rows="2" placeholder="Damage, partial items, refusal reason..."
                                    class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            </div>

                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Photo (cargo / BOL / damage)</label>
                                <input ref="photoInput" type="file" accept="image/*" @change="onPhoto"
                                    class="w-full text-xs text-gray-500 dark:text-gray-400" />
                            </div>

                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Recipient signature</label>
                                <SignaturePad v-model="form.signature" />
                            </div>

                            <div class="flex gap-2 pt-1">
                                <button type="button" @click="close"
                                    class="flex-1 rounded-2xl bg-gray-100 py-3 text-[10px] font-black uppercase tracking-widest text-gray-600 hover:bg-gray-200 dark:bg-zinc-800 dark:text-gray-300">Cancel</button>
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 rounded-2xl bg-emerald-600 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-emerald-700 disabled:opacity-50">
                                    {{ form.processing ? 'Saving…' : 'Save POD' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CheckCircle2, X } from 'lucide-vue-next';
import SignaturePad from './SignaturePad.vue';

const props = defineProps({
    deliveries: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    podTarget: { type: Object, default: null },
    notify: { type: Function, required: true },
});

const emit = defineEmits(['consumed']);

const outcomes = [
    { value: 'delivered', label: 'Delivered' },
    { value: 'partial', label: 'Partial' },
    { value: 'attempted', label: 'Attempted' },
    { value: 'refused', label: 'Refused' },
];

const showModal = ref(false);
const selected = ref(null);
const photoInput = ref(null);

const form = useForm({
    outcome: 'delivered',
    recipient_name: '',
    pod_notes: '',
    package_code: '',
    signature: '',
    photo: null,
});

const pendingStops = computed(() => {
    const items = [];
    props.deliveries.forEach((delivery) => {
        (delivery.stops || []).forEach((stop) => {
            if ((stop.outcome || 'pending') === 'pending') {
                items.push({ delivery, stop });
            }
        });
    });
    return items;
});

const completedStops = computed(() => {
    const items = [];
    [...props.deliveries, ...props.history].forEach((delivery) => {
        (delivery.stops || []).forEach((stop) => {
            if (stop.completed_at || (stop.outcome && stop.outcome !== 'pending')) {
                items.push({ delivery, stop });
            }
        });
    });
    return items.slice(0, 20);
});

const open = (item) => {
    selected.value = item;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

watch(() => props.podTarget, (target) => {
    if (target?.delivery && target?.stop) {
        open(target);
        emit('consumed');
    }
}, { immediate: true });

const close = () => {
    showModal.value = false;
    selected.value = null;
};

const onPhoto = (event) => {
    form.photo = event.target.files[0] || null;
};

const submit = () => {
    if (!selected.value) return;
    form.transform((data) => ({
        ...data,
        pod_notes: [data.pod_notes, data.package_code ? `Scanned: ${data.package_code}` : ''].filter(Boolean).join(' | '),
    }));

    form.post(route('logistics.driver.stop.update', [selected.value.delivery.id, selected.value.stop.id]), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            props.notify('success', 'Proof of delivery saved.');
            if (photoInput.value) photoInput.value.value = '';
            close();
        },
        onError: (errors) => props.notify('error', Object.values(errors)[0] || 'Could not save POD.'),
    });
};

const formatDateTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const outcomePill = (outcome) => ({
    delivered: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    partial: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
    attempted: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    refused: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
}[outcome] || 'bg-gray-100 text-gray-500');
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
