<script setup lang="ts">
// Agency portal: create an invoice from unbilled attendance
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';
import { ChevronLeft, Eye, Upload, X } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps<{
    agency: { id: number; code: string; name: string };
    from: string;
    to: string;
    today?: string;
    attachmentHistory: Array<{ id: number | string; name: string; uploaded_at: string; size: number; period: string; url: string }>;
}>();

const from = ref(props.from);
const to = ref(props.to);
const showUploadModal = ref(false);
const showUploadConfirmation = ref(false);
const historyCollapsed = ref(false);
const selectedUpload = ref<{ id: number | string; name: string; uploaded_at: string; size: number; period: string; url: string } | null>(props.attachmentHistory[0] || null);
watch(() => props.attachmentHistory, (files) => {
    selectedUpload.value = files[0] || null;
}, { deep: true });
const selectedUploadKind = computed(() => {
    const name = selectedUpload.value?.name.toLowerCase() || '';
    if (/\.pdf$/.test(name)) return 'pdf';
    if (/\.(png|jpe?g|gif|webp|bmp|svg)$/.test(name)) return 'image';
    if (/\.csv$/.test(name)) return 'csv';
    if (/\.(txt|log)$/.test(name)) return 'text';
    return 'unsupported';
});
const textPreview = ref('');
const textPreviewError = ref('');
const textPreviewLoading = ref(false);
function parseCsv(text: string): string[][] {
    const source = text.replace(/^\uFEFF/, '');
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = '';
    let quoted = false;

    for (let i = 0; i < source.length; i++) {
        const char = source[i];
        if (char === '"') {
            if (quoted && source[i + 1] === '"') {
                cell += '"';
                i++;
            } else {
                quoted = !quoted;
            }
        } else if (!quoted && char === ',') {
            row.push(cell);
            cell = '';
        } else if (!quoted && (char === '\n' || char === '\r')) {
            row.push(cell);
            rows.push(row);
            row = [];
            cell = '';
            if (char === '\r' && source[i + 1] === '\n') i++;
        } else {
            cell += char;
        }
    }
    if (cell !== '' || row.length > 0 || source.endsWith(',')) {
        row.push(cell);
        rows.push(row);
    }
    return rows;
}
const csvRows = computed(() => parseCsv(textPreview.value));
function spreadsheetColumn(index: number): string {
    let value = index + 1;
    let label = '';
    while (value > 0) {
        const remainder = (value - 1) % 26;
        label = String.fromCharCode(65 + remainder) + label;
        value = Math.floor((value - 1) / 26);
    }
    return label;
}
const csvColumnWidths = ref<Record<number, number>>({});
const csvRowHeights = ref<Record<number, number>>({});
const csvResize = ref<{ axis: 'column' | 'row'; index: number; start: number; size: number } | null>(null);
function startCsvResize(event: PointerEvent, axis: 'column' | 'row', index: number) {
    event.preventDefault();
    event.stopPropagation();
    csvResize.value = {
        axis,
        index,
        start: axis === 'column' ? event.clientX : event.clientY,
        size: axis === 'column' ? (csvColumnWidths.value[index] || 120) : (csvRowHeights.value[index] || 30),
    };
    window.addEventListener('pointermove', moveCsvResize);
    window.addEventListener('pointerup', stopCsvResize, { once: true });
}
function moveCsvResize(event: PointerEvent) {
    if (!csvResize.value) return;
    const resize = csvResize.value;
    const current = resize.axis === 'column' ? event.clientX : event.clientY;
    const nextSize = resize.size + current - resize.start;
    if (resize.axis === 'column') {
        csvColumnWidths.value = { ...csvColumnWidths.value, [resize.index]: Math.max(80, Math.min(700, nextSize)) };
    } else {
        csvRowHeights.value = { ...csvRowHeights.value, [resize.index]: Math.max(28, Math.min(400, nextSize)) };
    }
}
function stopCsvResize() {
    csvResize.value = null;
    window.removeEventListener('pointermove', moveCsvResize);
}
watch(selectedUpload, async (file) => {
    textPreview.value = '';
    textPreviewError.value = '';
    textPreviewLoading.value = false;
    if (!file || !/\.(csv|txt|log)$/i.test(file.name)) return;
    textPreviewLoading.value = true;
    try {
        const response = await fetch(file.url, { credentials: 'same-origin' });
        if (!response.ok) throw new Error('Unable to load this document.');
        textPreview.value = await response.text();
    } catch {
        textPreviewError.value = 'This document could not be previewed.';
    } finally {
        textPreviewLoading.value = false;
    }
}, { immediate: true });
// Today in the app timezone (Asia/Manila), supplied by the backend rule.
const today = props.today || new Date().toLocaleDateString('en-CA');

const form = useForm({ attachments: [] as File[], period_start: props.from, period_end: props.to });
const fileInput = ref<HTMLInputElement | null>(null);

function addAttachments(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files) form.attachments = [...form.attachments, ...Array.from(input.files)];
    input.value = '';
}

function viewAttachment(file: File) {
    const url = URL.createObjectURL(file);
    window.open(url, '_blank', 'noopener,noreferrer');
    window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
}

function uploadAttachments() {
    if (!form.attachments.length || form.processing || dateError.value) return;
    showUploadConfirmation.value = false;
    form.transform(() => ({
        period_start: from.value,
        period_end: to.value,
        attachments: form.attachments,
    }));
    form.post(route('agency.billing.uploads.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.attachments = [];
            showUploadModal.value = false;
            toast.success('Invoice documents uploaded.');
        },
    });
}

// Client-side mirror of the backend date rules. Past periods are allowed;
// future dates and reversed ranges are not.
const dateError = computed(() => {
    if (!from.value || !to.value) return 'Both Period from and Period to are required.';
    if (from.value > today) return 'Period from cannot be later than today.';
    if (to.value > today) return 'Period to cannot be later than today.';
    if (to.value < from.value) return 'Period to must be on or after Period from.';
    return '';
});

// Keeping Period to on/after Period from is automatic: a new Period from
// pushes Period to forward when it would otherwise fall before it.
watch(from, (v) => {
    if (v && to.value && to.value < v) {
        to.value = v;
    }
});

</script>

<template>
    <Head title="Create Invoice - Monti ERP" />
    <AgencyLayout>
        <header class="agency-page-header flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="agency-page-eyebrow">Agency Portal</p>
                <nav class="mt-1 flex items-center gap-2 text-xs font-medium">
                    <Link :href="route('agency.billing.index')">Billing</Link><span>/</span><span class="font-semibold">Create Invoice</span>
                </nav>
                <h1>Create Invoice</h1>
            </div>
            <input ref="fileInput" type="file" multiple class="hidden" @change="addAttachments" />
            <div class="flex items-center gap-2">
                <button type="button" @click="showUploadModal = true" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    <Upload class="h-4 w-4" /> Upload files
                </button>
            </div>
        </header>

        <section class="mb-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div :class="['grid grid-cols-1', historyCollapsed ? 'md:grid-cols-[minmax(0,1fr)_56px]' : 'md:grid-cols-[minmax(0,2fr)_minmax(260px,0.85fr)]']">
                <div class="flex h-[calc(100vh-180px)] min-h-[420px] flex-col bg-slate-100 dark:bg-slate-950 md:h-[calc(100vh-180px)]">
                    <div v-if="selectedUpload" class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-2 dark:border-slate-700 dark:bg-slate-800">
                        <p class="truncate text-xs font-bold text-slate-700 dark:text-slate-200">{{ selectedUpload.name }}</p>
                    </div>
                    <iframe v-if="selectedUpload && selectedUploadKind === 'pdf'" :key="selectedUpload.id" :src="selectedUpload.url" :title="`Preview ${selectedUpload.name}`" class="min-h-0 flex-1 bg-white dark:bg-slate-900"></iframe>
                    <div v-else-if="selectedUpload && selectedUploadKind === 'image'" class="flex min-h-0 flex-1 items-center justify-center overflow-auto p-4">
                        <img :src="selectedUpload.url" :alt="selectedUpload.name" class="max-h-full max-w-full object-contain" />
                    </div>
                    <div v-else-if="selectedUpload && (selectedUploadKind === 'csv' || selectedUploadKind === 'text')" class="min-h-0 flex-1 overflow-auto bg-white dark:bg-slate-900">
                        <p v-if="textPreviewError" class="p-4 text-sm text-rose-600">{{ textPreviewError }}</p>
                        <div v-else-if="textPreviewLoading" class="flex min-h-[320px] flex-col items-center justify-center gap-3 p-6 text-slate-500 dark:text-slate-400">
                            <div class="h-10 w-10 animate-spin rounded-full border-4 border-blue-100 border-t-blue-600 dark:border-slate-700 dark:border-t-blue-400" aria-hidden="true"></div>
                            <p class="text-sm font-semibold">Loading preview...</p>
                        </div>
                        <template v-else-if="selectedUploadKind === 'csv'">
                            <table class="w-max min-w-full table-fixed border-collapse text-left text-xs">
                                <thead class="sticky top-0 z-10 bg-slate-100 text-[10px] font-bold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                    <tr><th class="sticky left-0 z-20 w-10 border border-slate-200 bg-slate-200 px-1.5 py-1 text-center dark:border-slate-700 dark:bg-slate-700">#</th><th v-for="(cell, column) in csvRows[0] || []" :key="column" :style="{ width: (csvColumnWidths[column] || 120) + 'px', minWidth: (csvColumnWidths[column] || 120) + 'px' }" class="relative border border-slate-200 px-2 py-1 dark:border-slate-700">{{ spreadsheetColumn(column) }}<span @pointerdown="startCsvResize($event, 'column', column)" class="absolute right-0 top-0 z-30 h-full w-1.5 cursor-col-resize touch-none hover:bg-blue-500/60"></span></th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, rowIndex) in csvRows" :key="rowIndex" :style="{ height: (csvRowHeights[rowIndex] || 30) + 'px' }" class="hover:bg-blue-50/70 dark:hover:bg-blue-900/10">
                                        <th class="sticky left-0 relative border border-slate-200 bg-slate-50 px-1.5 py-1 text-center font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">{{ rowIndex + 1 }}<span @pointerdown="startCsvResize($event, 'row', rowIndex)" class="absolute bottom-0 left-0 z-20 h-1.5 w-full cursor-row-resize touch-none hover:bg-blue-500/60"></span></th>
                                        <td v-for="(cell, column) in row" :key="column" :style="{ width: (csvColumnWidths[column] || 120) + 'px', minWidth: (csvColumnWidths[column] || 120) + 'px' }" class="border border-slate-200 p-0 align-top text-slate-700 dark:border-slate-700 dark:text-slate-200"><div :style="{ height: (csvRowHeights[rowIndex] || 30) + 'px' }" class="overflow-auto whitespace-nowrap px-2 py-1">{{ cell }}</div></td>
                                    </tr>
                                </tbody>
                            </table>
                        </template>
                        <pre v-else class="whitespace-pre-wrap break-words p-4 text-xs leading-relaxed text-slate-700 dark:text-slate-200">{{ textPreview }}</pre>
                    </div>
                    <div v-else-if="selectedUpload" class="flex flex-1 items-center justify-center p-6 text-center text-sm text-slate-500 dark:text-slate-400">Preview isn’t available for this file type. Selecting it won’t download the file.</div>
                    <div v-else class="flex flex-1 items-center justify-center p-6 text-center text-sm text-slate-500 dark:text-slate-400">Uploaded documents will appear here. Choose one from the history list to preview it.</div>
                </div>
                <aside :class="['border-t border-slate-100 dark:border-slate-700 md:border-l md:border-t-0', historyCollapsed ? 'min-h-0 overflow-hidden' : 'max-h-[42vh] min-h-[220px] overflow-hidden md:max-h-[58vh]']">
                    <div class="sticky top-0 z-10 flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
                        <p v-if="!historyCollapsed" class="truncate text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">History ({{ attachmentHistory.length }})</p>
                        <button type="button" @click="historyCollapsed = !historyCollapsed" :aria-label="historyCollapsed ? 'Expand document history' : 'Collapse document history'" :title="historyCollapsed ? 'Expand history' : 'Collapse history'" :aria-expanded="!historyCollapsed" class="ml-auto flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">
                            <ChevronLeft :class="['h-4 w-4 transition-transform', historyCollapsed && 'rotate-180']" aria-hidden="true" />
                        </button>
                    </div>
                    <div v-if="!historyCollapsed" class="max-h-[calc(42vh-42px)] overflow-y-auto md:max-h-[calc(58vh-42px)]">
                        <p v-if="!attachmentHistory.length" class="p-4 text-xs text-slate-500 dark:text-slate-400">No documents uploaded yet.</p>
                        <button v-for="file in attachmentHistory" :key="file.id" type="button" @click="selectedUpload = file" :class="['block w-full border-b border-slate-100 px-4 py-3 text-left transition dark:border-slate-700', selectedUpload?.id === file.id ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-slate-50 dark:hover:bg-slate-900']">
                            <span class="block truncate text-xs font-bold text-slate-800 dark:text-slate-100">{{ file.name }}</span>
                            <span class="mt-1 block text-[10px] text-slate-500 dark:text-slate-400">{{ file.period ? file.period + ' · ' : '' }}{{ file.uploaded_at }} · {{ (file.size / 1024 / 1024).toFixed(2) }} MB</span>
                        </button>
                    </div>
                </aside>
            </div>
        </section>

        <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" @click.self="showUploadModal = false">
            <section class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-800" role="dialog" aria-modal="true" aria-labelledby="invoice-upload-title">
                <header class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                    <h2 id="invoice-upload-title" class="text-base font-black text-slate-900 dark:text-white">Upload invoice documents</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Attach supporting files. Maximum 10 MB each.</p>
                </header>
                <div class="space-y-4 p-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400">Period from <span class="text-rose-500">*</span></label>
                            <input v-model="from" type="date" :max="today" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400">Period to <span class="text-rose-500">*</span></label>
                            <input v-model="to" type="date" :max="today" :min="from || undefined" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                        </div>
                    </div>
                    <p v-if="dateError" class="text-[11px] font-semibold text-rose-600">{{ dateError }}</p>
                    <p class="text-[10px] text-slate-400">Supports CSV, PDF, Word, Excel, and other file types; maximum 10 MB each.</p>
                    <button type="button" @click="fileInput?.click()" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                        <Upload class="h-4 w-4" /> Choose documents
                    </button>
                    <ul v-if="form.attachments.length" class="max-h-40 space-y-2 overflow-y-auto">
                        <li v-for="(file, index) in form.attachments" :key="`${file.name}-${index}`" class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-slate-900">
                            <span class="truncate text-slate-700 dark:text-slate-200">{{ file.name }} <span class="text-slate-400">({{ (file.size / 1024 / 1024).toFixed(2) }} MB)</span></span>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" :aria-label="`View ${file.name}`" @click="viewAttachment(file)" class="rounded p-1 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-slate-800"><Eye class="h-4 w-4" /></button>
                                <button type="button" :aria-label="`Remove ${file.name}`" @click="form.attachments.splice(index, 1)" class="rounded p-1 text-slate-400 hover:text-rose-600"><X class="h-4 w-4" /></button>
                            </div>
                        </li>
                    </ul>
                </div>
                <footer class="flex justify-end border-t border-slate-100 px-5 py-3 dark:border-slate-700">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showUploadModal = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">Close</button>
                        <button type="button" @click="showUploadConfirmation = true" :disabled="!form.attachments.length || form.processing || !!dateError" class="rounded-xl bg-slate-800 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50">Upload documents</button>
                    </div>
                </footer>
            </section>
        </div>

        <div v-if="showUploadConfirmation" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm" role="presentation" @click.self="showUploadConfirmation = false">
            <section class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-800" role="alertdialog" aria-modal="true" aria-labelledby="confirm-invoice-upload-title">
                <div class="p-5">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                        <Upload class="h-5 w-5" />
                    </div>
                    <h2 id="confirm-invoice-upload-title" class="mt-4 text-base font-black text-slate-900 dark:text-white">Confirm document upload</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Upload {{ form.attachments.length }} {{ form.attachments.length === 1 ? 'document' : 'documents' }} for the selected billing period?</p>
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-900">
                        <p class="font-bold text-slate-800 dark:text-slate-200">{{ from }} – {{ to }}</p>
                        <ul class="mt-2 max-h-28 space-y-1 overflow-y-auto text-slate-600 dark:text-slate-400">
                            <li v-for="(file, index) in form.attachments" :key="`${file.name}-${index}`" class="truncate">{{ file.name }}</li>
                        </ul>
                    </div>
                </div>
                <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/50">
                    <button type="button" @click="showUploadConfirmation = false" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                    <button type="button" @click="uploadAttachments" class="rounded-xl bg-blue-700 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-blue-800">Confirm upload</button>
                </footer>
            </section>
        </div>

        <div v-if="form.processing" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" role="status" aria-live="polite">
            <div class="w-full max-w-xs rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-2xl dark:border-slate-700 dark:bg-slate-800">
                <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600 dark:border-slate-600 dark:border-t-blue-400"></div>
                <p class="mt-4 text-sm font-black text-slate-900 dark:text-white">Uploading invoice documents…</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Please wait while the files are saved.</p>
            </div>
        </div>

        <div v-if="form.attachments.length" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-5 mb-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-black text-slate-900 dark:text-white">Invoice attachments</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Attach supporting files such as CSV, PDF, Word, or Excel documents (up to 10 MB each).</p>
                </div>
            </div>
            <ul v-if="form.attachments.length" class="mt-3 space-y-2">
                <li v-for="(file, index) in form.attachments" :key="`${file.name}-${index}`" class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-slate-900">
                    <span class="truncate text-slate-700 dark:text-slate-200">{{ file.name }} <span class="text-slate-400">({{ (file.size / 1024 / 1024).toFixed(2) }} MB)</span></span>
                    <button type="button" :aria-label="`Remove ${file.name}`" @click="form.attachments.splice(index, 1)" class="rounded p-1 text-slate-400 hover:text-rose-600"><X class="h-4 w-4" /></button>
                </li>
            </ul>
            <p v-if="form.errors.attachments || form.errors['attachments.0']" class="mt-2 text-xs font-semibold text-rose-600">{{ form.errors.attachments || form.errors['attachments.0'] }}</p>
        </div>

    </AgencyLayout>
</template>
