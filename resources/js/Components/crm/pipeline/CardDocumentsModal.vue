<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { X, Upload, Trash2, FileText, Download } from 'lucide-vue-next';
import ModalShell from './ModalShell.vue';

// Card files/images: only shown on stages whose "allow uploads" flag is on.
// Every file is stored against the deal and linked to its client.
const props = defineProps({
    opportunity: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const docs = computed(() => props.opportunity.documents || []);
const clientName = computed(() => props.opportunity.organization || props.opportunity.contact_name || null);

const form = useForm({ files: [] });
const fileInput = ref(null);
const picked = ref([]);

const onPick = (e) => {
    const list = Array.from(e.target.files || []);
    picked.value = list;
    form.files = list;
    form.clearErrors();
};

const clearPicked = () => {
    picked.value = [];
    form.files = [];
    if (fileInput.value) fileInput.value.value = '';
};

const submit = () => {
    if (!picked.value.length) {
        form.setError('files', 'Choose at least one file.');
        return;
    }
    form.post(route('crm.opportunities.documents.store', props.opportunity.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => clearPicked(),
    });
};

const remove = (doc) => {
    if (!props.canEdit) return;
    router.delete(route('crm.opportunities.documents.destroy', doc.id), { preserveScroll: true });
};

const humanSize = (bytes) => {
    const n = Number(bytes || 0);
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
};

// ── Image lightbox: view a large version in-app (no new tab) ──
const lightbox = ref(null);
// Capture-phase ESC so it closes the viewer, not the whole modal underneath.
const onLightboxKey = (e) => {
    if (e.key === 'Escape') {
        e.stopImmediatePropagation();
        e.preventDefault();
        lightbox.value = null;
    }
};
watch(lightbox, (open) => {
    if (open) window.addEventListener('keydown', onLightboxKey, true);
    else window.removeEventListener('keydown', onLightboxKey, true);
});
onUnmounted(() => window.removeEventListener('keydown', onLightboxKey, true));
</script>

<template>
    <ModalShell size="lg" @close="emit('close')">
        <template #header>
            <div class="flex items-center justify-between bg-gradient-to-r from-sky-700 to-cyan-800 px-6 py-4 text-white">
                <div class="min-w-0">
                    <h3 class="flex items-center gap-2 font-black"><Upload class="h-4 w-4" /> Files &amp; Images</h3>
                    <p class="truncate text-xs text-sky-100">{{ opportunity.title }}<span v-if="clientName"> · {{ clientName }}</span></p>
                </div>
                <button @click="emit('close')" aria-label="Close"><X class="h-5 w-5 shrink-0" /></button>
            </div>
        </template>

        <div class="space-y-4 p-6">
            <p class="rounded-xl bg-sky-50 px-3 py-2 text-[11px] font-bold text-sky-700 dark:bg-sky-900/20 dark:text-sky-300">
                Uploaded files are stored on this deal and linked to the client on this card.
            </p>

            <!-- Upload -->
            <div v-if="canEdit">
                <input ref="fileInput" type="file" multiple class="hidden" @change="onPick"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,image/*" />
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="fileInput?.click()"
                        class="inline-flex items-center gap-1.5 rounded-xl border-2 border-dashed border-gray-300 px-4 py-2.5 text-[11px] font-black uppercase text-gray-500 transition hover:border-sky-400 hover:text-sky-600 dark:border-zinc-700"
                    >
                        <Upload class="h-4 w-4" /> Choose files
                    </button>
                    <span v-if="picked.length" class="text-[11px] font-bold text-gray-500">{{ picked.length }} selected</span>
                    <button
                        v-if="picked.length"
                        type="button"
                        @click="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-sky-600 px-4 py-2.5 text-[11px] font-black uppercase text-white transition hover:bg-sky-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Uploading…' : 'Upload' }}
                    </button>
                    <button v-if="picked.length" type="button" @click="clearPicked" class="text-[11px] font-black uppercase text-gray-400 hover:text-rose-500">Clear</button>
                </div>
                <p v-if="form.errors.files" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.files }}</p>
                <p v-for="(err, i) in form.errors" :key="i" class="mt-1 text-[11px] font-bold text-rose-500" v-show="i !== 'files'">{{ err }}</p>
                <p class="mt-1 text-[10px] text-gray-400">PDF, Office docs, images or ZIP — up to 10 MB each, 10 at a time.</p>
            </div>
            <p v-else class="text-[11px] font-bold text-amber-600">You have view-only access — uploading is disabled.</p>

            <!-- Existing files -->
            <div class="space-y-2">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Uploaded ({{ docs.length }})</p>
                <p v-if="!docs.length" class="rounded-2xl border-2 border-dashed border-gray-100 py-8 text-center text-xs font-bold text-gray-400 dark:border-zinc-800">
                    No files yet.
                </p>

                <template v-else>
                    <!-- Image gallery — click to enlarge in the lightbox -->
                    <div v-if="docs.some((d) => d.is_image)" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        <button
                            v-for="d in docs.filter((x) => x.is_image)"
                            :key="'img-' + d.id"
                            type="button"
                            @click="lightbox = d"
                            class="group relative block aspect-square overflow-hidden rounded-xl border border-gray-100 dark:border-zinc-800"
                            :title="`View ${d.name}`"
                        >
                            <img :src="d.url" :alt="d.name" class="h-full w-full object-cover transition group-hover:scale-105" />
                            <button
                                v-if="canEdit"
                                type="button"
                                @click.stop="remove(d)"
                                class="absolute right-1 top-1 rounded-lg bg-black/60 p-1 text-white opacity-0 transition group-hover:opacity-100"
                                title="Remove"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </button>
                    </div>

                    <!-- Documents -->
                    <div
                        v-for="d in docs.filter((x) => !x.is_image)"
                        :key="'doc-' + d.id"
                        class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-slate-50/60 p-3 dark:border-zinc-800 dark:bg-zinc-800/40"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-900/30 dark:text-sky-300">
                            <FileText class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-700 dark:text-slate-200">{{ d.name }}</p>
                            <p class="text-[10px] font-bold text-gray-400">
                                {{ humanSize(d.size) }}<span v-if="d.uploaded_by"> · {{ d.uploaded_by }}</span>
                            </p>
                        </div>
                        <a :href="d.url" target="_blank" download class="rounded-lg p-2 text-sky-600 hover:bg-sky-50 dark:hover:bg-zinc-800" title="Open / download">
                            <Download class="h-4 w-4" />
                        </a>
                        <button v-if="canEdit" type="button" @click="remove(d)" class="rounded-lg p-2 text-gray-300 transition hover:bg-rose-50 hover:text-rose-500" title="Remove">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </div>

            <div class="flex justify-end pt-1">
                <button @click="emit('close')" class="rounded-xl bg-gray-100 px-5 py-2.5 text-[11px] font-black uppercase text-gray-500 transition hover:bg-gray-200 dark:bg-zinc-800">Close</button>
            </div>
        </div>
    </ModalShell>

    <!-- Large image viewer — stays in-app, no new tab -->
    <Teleport to="body">
        <div
            v-if="lightbox"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/90 p-4"
            @click.self="lightbox = null"
            role="dialog"
            aria-modal="true"
        >
            <button
                @click="lightbox = null"
                class="absolute right-4 top-4 rounded-full bg-white/10 p-2.5 text-white transition hover:bg-white/20"
                aria-label="Close image"
            >
                <X class="h-6 w-6" />
            </button>
            <img :src="lightbox.url" :alt="lightbox.name" class="max-h-[90vh] max-w-[92vw] rounded-xl object-contain shadow-2xl" />
            <p class="absolute bottom-4 left-1/2 max-w-[92vw] -translate-x-1/2 truncate rounded-full bg-black/50 px-3 py-1 text-xs font-bold text-white">
                {{ lightbox.name }}
            </p>
        </div>
    </Teleport>
</template>
