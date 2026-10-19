<template>
    <div class="space-y-5">
        <!-- Alerts -->
        <div v-if="alerts.length" class="rounded-3xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-700/40 dark:bg-amber-900/20">
            <p class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-amber-700 dark:text-amber-300">
                <AlertTriangle class="h-4 w-4" /> Document attention needed
            </p>
            <ul class="mt-2 space-y-1 text-xs text-amber-700 dark:text-amber-200">
                <li v-for="alert in alerts" :key="alert.id">• {{ alert.title }} — {{ alert.is_expired ? 'expired' : 'expires soon' }} on {{ formatDate(alert.expires_at) }}</li>
            </ul>
        </div>

        <!-- Driver credentials -->
        <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-lg font-black text-white">
                        {{ (driver.user?.name || '?').charAt(0) }}
                    </div>
                    <div>
                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ driver.user?.name }}</p>
                        <p class="text-xs text-gray-500">License {{ driver.license_number }} · Rating {{ Number(driver.rating || 0).toFixed(1) }} ★</p>
                    </div>
                </div>
                <span :class="driver.is_available ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'"
                    class="rounded-full px-3 py-1 text-[10px] font-black uppercase">{{ driver.is_available ? 'Available' : 'On trip' }}</span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <a v-if="driver.license_image" :href="`/storage/${driver.license_image}`" target="_blank"
                    class="rounded-xl bg-indigo-50 px-3 py-1.5 text-[10px] font-black uppercase text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">View license image</a>
                <a v-if="driver.medical_certificate" :href="`/storage/${driver.medical_certificate}`" target="_blank"
                    class="rounded-xl bg-emerald-50 px-3 py-1.5 text-[10px] font-black uppercase text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300">View medical certificate</a>
                <span v-if="!driver.license_image && !driver.medical_certificate" class="text-xs text-gray-400">No credential files uploaded by admin yet.</span>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section v-for="group in groups" :key="group.key"
                class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <h3 class="flex items-center gap-2 text-sm font-black uppercase tracking-widest text-gray-500">
                        <component :is="group.icon" class="h-4 w-4" /> {{ group.label }}
                    </h3>
                    <button type="button" @click="openAdd(group)"
                        class="rounded-xl bg-indigo-50 px-2.5 py-1.5 text-[10px] font-black uppercase text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300">
                        <Plus class="mr-1 inline h-3 w-3" />Add
                    </button>
                </div>

                <div class="mt-3 space-y-2">
                    <div v-for="doc in docsFor(group)" :key="doc.id"
                        class="flex items-center gap-3 rounded-2xl border border-gray-100 px-3 py-2.5 dark:border-zinc-800">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-50 dark:bg-zinc-800">
                            <FileText class="h-4 w-4 text-gray-400" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-black text-gray-900 dark:text-white">{{ doc.title }}</p>
                            <p class="text-[10px] text-gray-500">
                                {{ doc.number || 'No number' }}
                                <span v-if="doc.expires_at">· exp {{ formatDate(doc.expires_at) }}</span>
                            </p>
                        </div>
                        <span v-if="doc.is_expired" class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Expired</span>
                        <span v-else-if="doc.expiring_soon" class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Soon</span>
                        <a v-if="doc.file_path" :href="`/storage/${doc.file_path}`" target="_blank" class="text-[10px] font-black uppercase text-indigo-500">Open</a>
                        <button type="button" @click="remove(doc)" class="text-[10px] font-black uppercase text-rose-500 hover:text-rose-600">Delete</button>
                    </div>
                    <p v-if="!docsFor(group).length" class="py-6 text-center text-xs font-bold text-gray-400">No documents in this group.</p>
                </div>
            </section>
        </div>

        <!-- Add modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="close">
                    <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                        <div class="flex items-center justify-between bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 px-5 py-4 text-white">
                            <h3 class="text-lg font-black">Add document</h3>
                            <button type="button" @click="close" class="rounded-xl p-1.5 hover:bg-white/20"><X class="h-5 w-5" /></button>
                        </div>
                        <form @submit.prevent="submit" class="space-y-3 p-5">
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Type</label>
                                <select v-model="form.document_type" class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                    <option value="license">Driver's license</option>
                                    <option value="medical">Medical certificate</option>
                                    <option value="hazmat">Hazmat certification</option>
                                    <option value="vehicle_registration">Vehicle registration</option>
                                    <option value="vehicle_insurance">Vehicle insurance</option>
                                    <option value="vehicle_permit">Vehicle permit</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Title *</label>
                                <input v-model="form.title" type="text" required placeholder="e.g. Professional Driver's License"
                                    class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Number</label>
                                    <input v-model="form.number" type="text"
                                        class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Expires on</label>
                                    <input v-model="form.expires_at" type="date"
                                        class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Issued on</label>
                                <input v-model="form.issued_at" type="date"
                                    class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">File (image or PDF)</label>
                                <input type="file" accept="image/*,application/pdf" @change="onFile" class="w-full text-xs text-gray-500 dark:text-gray-400" />
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-500">Notes</label>
                                <textarea v-model="form.notes" rows="2"
                                    class="w-full rounded-xl border-gray-200 bg-gray-50 px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
                            </div>
                            <div class="flex gap-2 pt-1">
                                <button type="button" @click="close"
                                    class="flex-1 rounded-2xl bg-gray-100 py-3 text-[10px] font-black uppercase tracking-widest text-gray-600 hover:bg-gray-200 dark:bg-zinc-800 dark:text-gray-300">Cancel</button>
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 rounded-2xl bg-indigo-600 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-indigo-700 disabled:opacity-50">
                                    {{ form.processing ? 'Saving…' : 'Save document' }}
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
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { AlertTriangle, FileText, Plus, X, User, Truck } from 'lucide-vue-next';

const props = defineProps({
    documents: { type: Array, default: () => [] },
    driver: { type: Object, default: () => ({ user: {} }) },
    notify: { type: Function, required: true },
});

const showModal = ref(false);

const groups = [
    { key: 'personal', label: 'Personal documents', icon: User, types: ['license', 'medical', 'hazmat', 'other'] },
    { key: 'vehicle', label: 'Vehicle documents', icon: Truck, types: ['vehicle_registration', 'vehicle_insurance', 'vehicle_permit'] },
];

const form = useForm({
    document_type: 'license',
    title: '',
    number: '',
    issued_at: '',
    expires_at: '',
    notes: '',
    file: null,
});

const alerts = computed(() => props.documents.filter((doc) => doc.is_expired || doc.expiring_soon));

const docsFor = (group) => props.documents.filter((doc) => group.types.includes(doc.document_type));

const openAdd = (group) => {
    form.reset();
    form.clearErrors();
    form.document_type = group.key === 'vehicle' ? 'vehicle_registration' : 'license';
    showModal.value = true;
};

const close = () => { showModal.value = false; };

const onFile = (event) => { form.file = event.target.files[0] || null; };

const submit = () => {
    form.post(route('logistics.driver.document.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            props.notify('success', 'Document saved.');
            close();
        },
        onError: (errors) => props.notify('error', Object.values(errors)[0] || 'Could not save document.'),
    });
};

const remove = (doc) => {
    if (!confirm(`Delete "${doc.title}"?`)) return;
    router.delete(route('logistics.driver.document.destroy', doc.id), {
        preserveScroll: true,
        onSuccess: () => props.notify('success', 'Document deleted.'),
        onError: () => props.notify('error', 'Could not delete document.'),
    });
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
