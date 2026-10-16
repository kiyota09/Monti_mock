<script setup lang="ts">
// Agency portal: add / edit own worker — full profile submission (Sections A-F).
// New submissions enter as Pending HR Approval and become Available only
// after HR approves and all required checks are satisfied.
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';

const props = defineProps<{
    worker: any | null;
    privacy_notice_version: string;
    document_types: Record<string, string>;
    document_statuses: string[];
    departments: Array<{ id: number; code: string; name: string }>;
    positions: Array<{ id: number; name: string; department_id: number | null }>;
}>();
const isEdit = !!props.worker;
const hasErrors = computed(() => Object.keys(form.errors as Record<string, string>).length > 0);

const form = useForm({
    name: props.worker?.name || '',
    birth_date: props.worker?.birth_date || '',
    gender: props.worker?.gender || '',
    phone: props.worker?.phone || '',
    email: props.worker?.email || '',
    address: props.worker?.address || '',
    resume: null as File | null,
    education: props.worker?.education || '',
    work_experience: props.worker?.work_experience || '',
    skills: props.worker?.skills || '',
    trainings_certifications: props.worker?.trainings_certifications || '',
    position_skill: props.worker?.position_skill || '',
    years_experience: props.worker?.years_experience ?? '',
    proposed_department: props.worker?.proposed_department || '',
    availability: props.worker?.availability || '',
    daily_rate: props.worker?.daily_rate ?? '',
    employment_arrangement: props.worker?.employment_arrangement || '',
    gov_id_type: props.worker?.gov_id_type || '',
    gov_id_number: props.worker?.gov_id_number || '',
    documents: [] as File[],
    document_types: [] as string[],
    document_labels: [] as string[],
    document_starts: [] as string[],
    document_expiries: [] as string[],
    emergency_name: props.worker?.emergency_name || '',
    emergency_relationship: props.worker?.emergency_relationship || '',
    emergency_phone: props.worker?.emergency_phone || '',
    consent_given: props.worker?.consent_given || false,
});

const resumeName = ref(props.worker?.resume_original_name || '');
const existingDocs = ref<Array<any>>(props.worker?.documents || []);

// Keep visible uploaded list in sync when Inertia reloads props
// (e.g. after soft-delete returns back() with fresh documents).
watch(() => props.worker?.documents, (v) => {
    existingDocs.value = [...(v || [])];
}, { deep: true });
watch(() => props.worker?.resume_original_name, (v) => {
    if (!form.resume) resumeName.value = v || '';
});

// File input refs so X can reset the native "No file chosen" control.
// (FileList is read-only — we keep the real selection in categoryFiles/form.)
const resumeInputRef = ref<HTMLInputElement | null>(null);
const categoryInputRefs = ref<Record<string, HTMLInputElement | null>>({});

const legacyAvailability = ref('');
// Availability is now a calendar date (YYYY-MM-DD). Normalize legacy phrase values
// like "Immediately available" or "Available on a specific date (2026-10-20)".
(function initAvailabilityDate() {
    const v: string = (form as any).availability || '';
    if (!v) return;
    if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return;
    const m = v.match(/(\d{4}-\d{2}-\d{2})/);
    if (m) {
        legacyAvailability.value = v;
        (form as any).availability = m[1];
    } else {
        // Phrase value can't be shown in a date input — keep it as a note and
        // let the user pick a calendar date to replace it.
        legacyAvailability.value = v;
        (form as any).availability = '';
    }
})();

// Department (from HRM module) → Position cascading.
// Department list aligns with HRM → Departments; positions filter by department_id.
const selectedDepartmentId = computed(() => {
    const found = (props.departments ?? []).find((d) => d.name === (form as any).proposed_department);
    return found ? found.id : null;
});

const departmentOptions = computed(() => {
    const list = [...(props.departments ?? [])];
    if ((form as any).proposed_department && !list.some((d) => d.name === (form as any).proposed_department)) {
        list.unshift({ id: -1, code: '', name: (form as any).proposed_department });
    }
    return list;
});

const filteredPositions = computed(() => {
    // No department chosen → position list stays empty.
    if (!(form as any).proposed_department) {
        return [];
    }
    // Legacy / custom department → keep current value so edit doesn't lose data.
    if (selectedDepartmentId.value === null) {
        if ((form as any).position_skill) {
            return [{ id: -1, name: (form as any).position_skill, department_id: null }];
        }
        return [];
    }
    const all = (props.positions ?? []).filter((p) => p.department_id === selectedDepartmentId.value);
    if ((form as any).position_skill && !all.some((p) => p.name === (form as any).position_skill)) {
        return [{ id: -1, name: (form as any).position_skill, department_id: selectedDepartmentId.value }, ...all];
    }
    return all;
});

// Changing department clears the position when it no longer belongs to it.
watch(() => (form as any).proposed_department, () => {
    if (!(form as any).proposed_department) {
        (form as any).position_skill = '';
        return;
    }
    const names = filteredPositions.value.map((p) => p.name);
    if ((form as any).position_skill && !names.includes((form as any).position_skill)) {
        (form as any).position_skill = '';
    }
});

function onResumeChange(e: Event) {
    const files = (e.target as HTMLInputElement).files;
    form.resume = files && files[0] ? files[0] : null;
    resumeName.value = form.resume ? form.resume.name : (props.worker?.resume_original_name || '');
}

const docCategories = [
    { key: 'clearance', title: 'Clearances', hint: 'NBI, police, or barangay clearance' },
    { key: 'medical', title: 'Medical Certificate', hint: 'Medical certificate, drug test, fit-to-work' },
    { key: 'position_requirement', title: 'Position requirements', hint: 'Licenses, TESDA certs, position-specific documents' },
];
const categoryFiles = ref<Record<string, File[]>>({ clearance: [], medical: [], position_requirement: [] });
const categoryMeta = ref<Record<string, Array<{ start: string; expiry: string }>>>({ clearance: [], medical: [], position_requirement: [] });

function onCategoryChange(key: string, e: Event) {
    const files = Array.from((e.target as HTMLInputElement).files || []);
    categoryFiles.value[key] = files;
    categoryMeta.value[key] = files.map(() => ({ start: '', expiry: '' }));
    syncCategoryDocs();
}

// X on a newly selected (not yet saved) file — client-side only, no backend call.
function removeNewResume() {
    form.resume = null;
    resumeName.value = props.worker?.resume_original_name || '';
    if (resumeInputRef.value) resumeInputRef.value.value = '';
}

function removeNewCategoryFile(key: string, index: number) {
    categoryFiles.value[key].splice(index, 1);
    categoryMeta.value[key].splice(index, 1);
    // Reset native input so its FileList can't re-introduce the removed file.
    // The real pending selection stays in categoryFiles (rendered below).
    const input = categoryInputRefs.value[key];
    if (input) input.value = '';
    syncCategoryDocs();
}

// X on an already-uploaded file — SOFT DELETE ONLY (backend sets deleted_at,
// row + file stay recoverable). Removes the row from the visible list.
const deletingDocId = ref<number | null>(null);

function deleteUploadedDocument(doc: any) {
    if (!isEdit || !props.worker) return;
    const name = doc.original_name || doc.label || doc.doc_type;
    if (!confirm(`Remove "${name}"? It will be hidden but kept in the database (recoverable).`)) return;
    deletingDocId.value = doc.id;
    router.delete(route('agency.workers.documents.destroy', [props.worker.id, doc.id]), {
        preserveScroll: true,
        onFinish: () => { deletingDocId.value = null; },
        onSuccess: () => {
            existingDocs.value = (existingDocs.value || []).filter((d: any) => d.id !== doc.id);
            if (doc.doc_type === 'resume') {
                resumeName.value = '';
            }
        },
    });
}

function removeUploadedResume() {
    const resumeDocs = (existingByType.value['resume'] || []) as Array<any>;
    if (resumeDocs.length > 0) {
        deleteUploadedDocument(resumeDocs[0]);
    } else {
        // Legacy worker with resume columns but no mirror doc — just hide it
        // locally; uploading a new resume overwrites it on Save.
        resumeName.value = '';
    }
}

function categoryTitle(key: string): string {
    return docCategories.find((c) => c.key === key)?.title || key;
}

function syncCategoryDocs() {
    const docs: File[] = [];
    const types: string[] = [];
    const labels: string[] = [];
    const starts: string[] = [];
    const expiries: string[] = [];
    for (const c of docCategories) {
        categoryFiles.value[c.key].forEach((f, i) => {
            docs.push(f);
            types.push(c.key);
            labels.push(c.title);
            starts.push(categoryMeta.value[c.key][i]?.start || '');
            expiries.push(categoryMeta.value[c.key][i]?.expiry || '');
        });
    }
    form.documents = docs as any;
    form.document_types = types;
    form.document_labels = labels;
    form.document_starts = starts;
    form.document_expiries = expiries;
}

function docsError(): string {
    const bag = form.errors as Record<string, string>;
    if (bag.documents) return bag.documents;
    const first = Object.keys(bag).filter((k) => k.startsWith('documents.')).sort()[0];
    return first ? bag[first] : '';
}

// Already-uploaded files grouped by category so Edit shows each file
// under the Clearances / Medical / Position block where it was uploaded.
// (File inputs always reset to "No file chosen" on page load — this list
// is how the user sees persisted uploads.)
const existingByType = computed(() => {
    const map: Record<string, Array<any>> = {};
    for (const d of existingDocs.value || []) {
        const k = d.doc_type || 'other';
        if (!map[k]) map[k] = [];
        map[k].push(d);
    }
    return map;
});

function docStatusCls(status: string): string {
    return status === 'Verified'
        ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
        : status === 'Rejected'
            ? 'bg-rose-100 text-rose-700 border-rose-200'
            : status === 'Expired'
                ? 'bg-slate-200 text-slate-600 border-slate-300'
                : 'bg-amber-100 text-amber-700 border-amber-200';
}

function doSubmit() {
    syncCategoryDocs();
    // File uploads must spoof PUT via POST (_method). onError closes the
    // confirm dialog and scrolls to the top banner so a failed validation
    // is never mistaken for "saved but old".
    const opts = {
        preserveScroll: true,
        forceFormData: true,
        onError: () => {
            showSaveConfirm.value = false;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    } as any;
    if (isEdit) {
        form.transform((data: any) => ({ ...data, _method: 'put' })).post(route('agency.workers.update', props.worker.id), opts);
    } else {
        form.post(route('agency.workers.store'), opts);
    }
}

// ─── Save confirmation (shows changes before submitting) ─────────────
const showSaveConfirm = ref(false);

const saveDiff = computed(() => {
    const w: any = props.worker || {};
    const f: any = form as any;
    const rows: Array<{ label: string; old: string; next: string }> = [];
    const put = (key: string, label: string) => {
        const o = (w[key] ?? '') === null ? '' : String(w[key] ?? '');
        const n = (f[key] ?? '') === null ? '' : String(f[key] ?? '');
        if (o !== n) rows.push({ label, old: o === '' ? '—' : o, next: n === '' ? '—' : n });
    };
    put('name', 'Full name'); put('birth_date', 'Birth date'); put('gender', 'Gender');
    put('phone', 'Phone'); put('email', 'Email'); put('address', 'Address');
    put('education', 'Education'); put('work_experience', 'Experience'); put('skills', 'Skills');
    put('trainings_certifications', 'Training'); put('position_skill', 'Position');
    put('years_experience', 'Years exp.'); put('proposed_department', 'Department');
    put('availability', 'Availability'); put('daily_rate', 'Daily rate');
    put('employment_arrangement', 'Arrangement'); put('gov_id_type', 'Gov ID type');
    put('gov_id_number', 'Gov ID no.');
    put('emergency_name', 'Emergency name'); put('emergency_relationship', 'Emergency relation');
    put('emergency_phone', 'Emergency phone');
    const oc = w.consent_given ? 'Yes' : 'No';
    const nc = f.consent_given ? 'Yes' : 'No';
    if (oc !== nc) rows.push({ label: 'Consent', old: oc, next: nc });
    return rows;
});

const newUploads = computed(() => {
    const list: string[] = [];
    const f: any = form as any;
    if (f.resume) list.push(`Resume: ${(f.resume as File).name}`);
    for (const c of docCategories) {
        (categoryFiles.value[c.key] || []).forEach((file: File) => list.push(`${c.title}: ${file.name}`));
    }
    return list;
});

function openSaveConfirm() {
    syncCategoryDocs();
    showSaveConfirm.value = true;
}

function confirmSave() {
    showSaveConfirm.value = false;
    doSubmit();
}

function err(key: string): string {
    const bag = form.errors as Record<string, string>;
    return bag[key] || '';
}

// In-page file viewer (no new tab). Works for newly selected Files (blob URL)
// and already-uploaded remote URLs.
const previewUrl = ref<string | null>(null);
const previewName = ref('');
const previewKind = ref<'image' | 'pdf' | 'other'>('other');
let previewBlobUrl: string | null = null;

function kindOfFile(name: string, mime = ''): 'image' | 'pdf' | 'other' {
    const n = (name || '').toLowerCase();
    if (mime.startsWith('image/') || /\.(jpg|jpeg|png|gif|webp|bmp)$/.test(n)) return 'image';
    if (mime === 'application/pdf' || /\.pdf$/.test(n)) return 'pdf';
    return 'other';
}

function openLocalPreview(file: File) {
    if (previewBlobUrl) URL.revokeObjectURL(previewBlobUrl);
    previewBlobUrl = URL.createObjectURL(file);
    previewUrl.value = previewBlobUrl;
    previewName.value = file.name;
    previewKind.value = kindOfFile(file.name, file.type);
}

function openRemotePreview(url: string, name: string) {
    if (previewBlobUrl) {
        URL.revokeObjectURL(previewBlobUrl);
        previewBlobUrl = null;
    }
    previewUrl.value = url;
    previewName.value = name;
    previewKind.value = kindOfFile(name);
}

function closePreview() {
    if (previewBlobUrl) {
        URL.revokeObjectURL(previewBlobUrl);
        previewBlobUrl = null;
    }
    previewUrl.value = null;
    previewName.value = '';
}

const inputCls = 'w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200';
const labelCls = 'block mb-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase';
const sectionCls = 'bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5';
const hintCls = 'mt-1 text-[11px] text-slate-400';
</script>

<template>
    <Head :title="(isEdit ? 'Edit Worker' : 'Add Worker') + ' - Monti ERP'" />
    <AgencyLayout>
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2">
            <Link :href="route('agency.workers.index')" class="hover:text-blue-600">Workers</Link>
            <span>/</span>
            <span class="font-semibold">{{ isEdit ? 'Edit' : 'Add' }}</span>
        </nav>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight mb-1">
            {{ isEdit ? 'Edit worker' : 'Add worker' }}
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
            {{ isEdit ? 'Status changes stay with HR. You can edit worker details directly, including daily rate.' : 'New workers enter as Pending HR Approval and become Available only after HR approves them.' }}
        </p>

        <form @submit.prevent="openSaveConfirm" class="space-y-4 max-w-3xl">
            <div v-if="hasErrors" class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
                <p class="text-xs font-black uppercase tracking-wider text-rose-700 dark:text-rose-400">Could not save — fix the following</p>
                <ul class="mt-1 text-sm text-rose-700 dark:text-rose-300 list-disc pl-5">
                    <li v-for="(msg, key) in (form.errors as Record<string,string>)" :key="key">{{ msg }}</li>
                </ul>
                <p class="mt-1 text-[11px] text-rose-500">Nothing was saved, so the form still shows the old data.</p>
            </div>
            <div v-if="isEdit && props.worker?.status === 'For Correction' && props.worker?.pending_note" class="p-4 rounded-2xl bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
                <p class="text-xs font-black uppercase tracking-wider text-orange-700 dark:text-orange-400">HR requested corrections</p>
                <p class="mt-1 text-sm text-orange-800 dark:text-orange-300 whitespace-pre-line">{{ props.worker.pending_note }}</p>
                <p class="mt-1 text-[11px] text-orange-600 dark:text-orange-400">Update the details below and save — the worker returns to Pending HR Approval.</p>
            </div>
            <!-- Personal Information -->
            <div :class="sectionCls">
                <h2 class="text-sm font-black uppercase tracking-wider mb-1">Personal Information</h2>
                <p class="text-[11px] text-slate-400 mb-4">Full legal name, birth date, phone, and complete address are required.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Full name <span class="text-red-500">*</span></label>
                        <input v-model="form.name" type="text" placeholder="Enter full legal name" :class="inputCls" />
                        <p v-if="err('name')" class="mt-1 text-xs text-rose-600">{{ err('name') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Birth date <span class="text-red-500">*</span></label>
                        <input v-model="form.birth_date" type="date" :class="inputCls" />
                        <p v-if="err('birth_date')" class="mt-1 text-xs text-rose-600">{{ err('birth_date') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Gender</label>
                        <select v-model="form.gender" :class="inputCls">
                            <option value="">Select...</option>
                            <option>Male</option><option>Female</option>
                        </select>
                        <p v-if="err('gender')" class="mt-1 text-xs text-rose-600">{{ err('gender') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Phone number <span class="text-red-500">*</span></label>
                        <input v-model="form.phone" type="text" placeholder="09XX XXX XXXX" :class="inputCls" />
                        <p v-if="err('phone')" class="mt-1 text-xs text-rose-600">{{ err('phone') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Email address</label>
                        <input v-model="form.email" type="email" placeholder="name@example.com" :class="inputCls" />
                        <p v-if="err('email')" class="mt-1 text-xs text-rose-600">{{ err('email') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Complete address <span class="text-red-500">*</span></label>
                        <textarea v-model="form.address" rows="2" placeholder="House no., street, barangay, city, province" :class="inputCls"></textarea>
                        <p v-if="err('address')" class="mt-1 text-xs text-rose-600">{{ err('address') }}</p>
                    </div>
                </div>
            </div>

            <!-- Resume and Qualifications -->
            <div :class="sectionCls">
                <h2 class="text-sm font-black uppercase tracking-wider mb-1">Resume and Qualifications</h2>
                <p class="text-[11px] text-slate-400 mb-4">Resume, educational attainment, and skills are required. Experience and certifications are optional.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Upload Resume / CV <span v-if="!isEdit" class="text-red-500">*</span></label>
                        <input ref="resumeInputRef" type="file" accept=".pdf,.doc,.docx" :class="inputCls" @change="onResumeChange" />
                        <p :class="hintCls">PDF or DOCX · max 10MB · Required for full profile submission</p>
                        <div v-if="form.resume" class="mt-2 p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between gap-2">
                            <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 truncate">Selected: {{ (form.resume as File).name }}</p>
                            <div class="shrink-0 flex items-center gap-1">
                                <button type="button" @click="openLocalPreview(form.resume as File)" class="text-[11px] font-bold text-blue-600 hover:underline">View</button>
                                <button type="button" @click="removeNewResume" title="Remove selected file" class="w-6 h-6 inline-flex items-center justify-center rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 font-black">×</button>
                            </div>
                        </div>
                        <div v-else-if="resumeName" class="mt-2 p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between gap-2">
                            <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 truncate">Uploaded: {{ resumeName }}</p>
                            <div class="shrink-0 flex items-center gap-1">
                                <button v-if="props.worker?.resume_url" type="button" @click="openRemotePreview(props.worker.resume_url, resumeName || 'Resume')" class="text-[11px] font-bold text-blue-600 hover:underline">View</button>
                                <button type="button" @click="removeUploadedResume" :disabled="deletingDocId !== null" title="Remove uploaded resume (kept in database)" class="w-6 h-6 inline-flex items-center justify-center rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 font-black disabled:opacity-40">×</button>
                            </div>
                        </div>
                        <p v-if="err('resume')" class="mt-1 text-xs text-rose-600">{{ err('resume') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Educational attainment <span class="text-red-500">*</span></label>
                        <textarea v-model="form.education" rows="2" placeholder="e.g. High school graduate, TESDA NC II..." :class="inputCls"></textarea>
                        <p v-if="err('education')" class="mt-1 text-xs text-rose-600">{{ err('education') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Previous work experience</label>
                        <textarea v-model="form.work_experience" rows="2" placeholder="Previous employers, roles, inclusive dates..." :class="inputCls"></textarea>
                    </div>
                    <div>
                        <label :class="labelCls">Skills <span class="text-red-500">*</span></label>
                        <textarea v-model="form.skills" rows="2" placeholder="e.g. Sewing, packing, machine operation..." :class="inputCls"></textarea>
                        <p v-if="err('skills')" class="mt-1 text-xs text-rose-600">{{ err('skills') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Training and certifications</label>
                        <textarea v-model="form.trainings_certifications" rows="2" placeholder="e.g. TESDA NC II (2024), Safety training..." :class="inputCls"></textarea>
                    </div>
                </div>
            </div>

            <!-- Work and Deployment Details -->
            <div :class="sectionCls">
                <h2 class="text-sm font-black uppercase tracking-wider mb-1">Work and Deployment Details</h2>
                <p class="text-[11px] text-slate-400 mb-4">Propose the position and availability. Monti confirms the final department, assignment, and agreed terms.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Proposed department / assignment</label>
                        <select v-model="form.proposed_department" :class="inputCls">
                            <option value="">Select department...</option>
                            <option v-for="d in departmentOptions" :key="d.id + '-' + d.name" :value="d.name">{{ d.name }}</option>
                        </select>
                        <p v-if="departmentOptions.length === 0" class="mt-1 text-xs text-amber-600">No departments — HR adds them under HRM → Departments.</p>
                    </div>
                    <div>
                        <label :class="labelCls">Position / skill <span class="text-red-500">*</span></label>
                        <select v-model="form.position_skill" :class="inputCls" :disabled="!form.proposed_department">
                            <option value="">{{ !form.proposed_department ? 'Select department first...' : 'Select position...' }}</option>
                            <option v-for="p in filteredPositions" :key="p.id + '-' + p.name" :value="p.name">{{ p.name }}</option>
                        </select>
                        <p v-if="err('position_skill')" class="mt-1 text-xs text-rose-600">{{ err('position_skill') }}</p>
                        <p v-else-if="!form.proposed_department" class="mt-1 text-xs text-slate-400">Select a department first to see its positions.</p>
                        <p v-else-if="form.proposed_department && filteredPositions.length === 0" class="mt-1 text-xs text-amber-600">No positions under this department — HR adds them under HRM → Positions.</p>
                    </div>
                    <div>
                        <label :class="labelCls">Years of experience</label>
                        <input v-model="form.years_experience" type="number" min="0" max="60" step="0.5" placeholder="e.g. 2.5" :class="inputCls" />
                        <p v-if="err('years_experience')" class="mt-1 text-xs text-rose-600">{{ err('years_experience') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Availability date <span class="text-red-500">*</span></label>
                        <input v-model="form.availability" type="date" :class="inputCls" />
                        <p v-if="legacyAvailability" class="mt-1 text-[11px] text-slate-400">Previous value: {{ legacyAvailability }} — pick a calendar date to update it.</p>
                        <p v-if="err('availability')" class="mt-1 text-xs text-rose-600">{{ err('availability') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Daily rate (₱)</label>
                        <input v-model="form.daily_rate" type="number" min="0" step="0.01" placeholder="e.g. 650" :class="inputCls" />
                        <p v-if="err('daily_rate')" class="mt-1 text-xs text-rose-600">{{ err('daily_rate') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Employment arrangement</label>
                        <select v-model="form.employment_arrangement" :class="inputCls">
                            <option value="">Select...</option>
                            <option>Full-time</option><option>Part-time</option>
                            <option>Contractual</option><option>Project-based</option>
                            <option>Seasonal</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Government and Supporting Documents -->
            <div :class="sectionCls">
                <h2 class="text-sm font-black uppercase tracking-wider mb-1">Government and Supporting Documents</h2>
                <p class="text-[11px] text-slate-400 mb-4">Optional at registration — HR collects what the assignment requires. HR verifies each file as Pending, Verified, Rejected, or Expired.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label :class="labelCls">Government ID type</label>
                        <select v-model="form.gov_id_type" :class="inputCls">
                            <option value="">Select...</option>
                            <option>SSS</option><option>PhilHealth</option><option>Pag-IBIG</option>
                            <option>TIN</option><option>Driver's License</option>
                            <option>Passport</option><option>National ID</option><option>Voter's ID</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelCls">Government ID number</label>
                        <input v-model="form.gov_id_number" type="text" placeholder="ID number" :class="inputCls" />
                        <p v-if="err('gov_id_number')" class="mt-1 text-xs text-rose-600">{{ err('gov_id_number') }}</p>
                    </div>
                    <div v-for="c in docCategories" :key="c.key" class="sm:col-span-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700">
                        <label :class="labelCls">{{ c.title }}</label>
                        <input type="file" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" :class="inputCls" :ref="(el: any) => { categoryInputRefs[c.key] = el as HTMLInputElement | null; }" @change="onCategoryChange(c.key, $event)" />
                        <p :class="hintCls">{{ c.hint }} · PDF, DOCX, JPG, PNG · max 10MB each</p>
                        <div v-if="(existingByType[c.key] || []).length" class="mt-2 rounded-lg bg-white dark:bg-slate-800 border border-emerald-200 dark:border-emerald-800 overflow-hidden">
                            <p class="px-2 pt-2 text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Uploaded ({{ (existingByType[c.key] || []).length }}) — saved</p>
                            <ul class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs px-2 pb-1">
                                <li v-for="d in (existingByType[c.key] || [])" :key="d.id" class="py-1.5 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <button type="button" @click="openRemotePreview(d.url, d.original_name || d.label || d.doc_type)" class="font-semibold text-blue-600 hover:underline truncate">{{ d.original_name || d.label || d.doc_type }}</button>
                                        <p v-if="d.issue_date || d.expiry_date" class="text-[11px] text-slate-400">{{ d.issue_date ? 'Start: ' + d.issue_date : '' }}{{ d.issue_date && d.expiry_date ? ' · ' : '' }}{{ d.expiry_date ? 'Expiry: ' + d.expiry_date : '' }}</p>
                                    </div>
                                    <div class="shrink-0 flex items-center gap-1">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase border" :class="docStatusCls(d.status)">{{ d.status }}</span>
                                        <button type="button" @click="deleteUploadedDocument(d)" :disabled="deletingDocId === d.id" title="Remove (kept in database)" class="w-6 h-6 inline-flex items-center justify-center rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 font-black disabled:opacity-40">×</button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div v-for="(f, i) in categoryFiles[c.key]" :key="f.name + i" class="mt-2 p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[11px] font-semibold text-slate-600 dark:text-slate-300 truncate">{{ f.name }}</p>
                                <div class="shrink-0 flex items-center gap-1">
                                    <button type="button" @click="openLocalPreview(f)" class="text-[11px] font-bold text-blue-600 hover:underline">View</button>
                                    <button type="button" @click="removeNewCategoryFile(c.key, i)" title="Remove selected file" class="w-6 h-6 inline-flex items-center justify-center rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 font-black">×</button>
                                </div>
                            </div>
                            <div class="mt-1.5 grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block mb-1 text-[10px] font-bold text-slate-400 uppercase">Start date</label>
                                    <input v-model="categoryMeta[c.key][i].start" type="date" title="When the certificate was obtained" :class="inputCls" @change="syncCategoryDocs" />
                                </div>
                                <div>
                                    <label class="block mb-1 text-[10px] font-bold text-slate-400 uppercase">Expiry date</label>
                                    <input v-model="categoryMeta[c.key][i].expiry" type="date" title="Expiry (optional)" :class="inputCls" @change="syncCategoryDocs" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-if="docsError()" class="sm:col-span-2 -mt-2 text-xs text-rose-600">{{ docsError() }}</p>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div :class="sectionCls">
                <h2 class="text-sm font-black uppercase tracking-wider mb-1">Emergency Contact</h2>
                <p class="text-[11px] text-slate-400 mb-4">Optional initially — may be required before deployment.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label :class="labelCls">Contact name</label>
                        <input v-model="form.emergency_name" type="text" :class="inputCls" />
                    </div>
                    <div>
                        <label :class="labelCls">Relationship</label>
                        <select v-model="form.emergency_relationship" :class="inputCls">
                            <option value="">Select...</option>
                            <option>Spouse</option><option>Parent</option><option>Sibling</option>
                            <option>Child</option><option>Relative</option><option>Friend</option><option>Other</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelCls">Phone number</label>
                        <input v-model="form.emergency_phone" type="text" :class="inputCls" />
                    </div>
                </div>
            </div>

            <!-- Submission and Consent -->
            <div :class="sectionCls">
                <h2 class="text-sm font-black uppercase tracking-wider mb-1">Submission and Consent <span class="text-red-500">*</span></h2>
                <p class="text-[11px] text-slate-400 mb-4">Initial status: Pending HR Approval.</p>
                <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 cursor-pointer">
                    <input v-model="form.consent_given" type="checkbox" :true-value="true" :false-value="false" class="mt-1 w-4 h-4 accent-blue-600" />
                    <span class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        I confirm that the agency is authorized to submit this worker's information and supporting files,
                        and that the worker has been informed of the privacy notice ({{ privacy_notice_version }}).
                        <span v-if="!isEdit" class="font-bold text-slate-900 dark:text-white">Required to submit.</span>
                    </span>
                </label>
                <p v-if="err('consent_given')" class="mt-1 text-xs text-rose-600">{{ err('consent_given') }}</p>
            </div>

            <div class="flex items-center justify-end gap-3 pb-8">
                <Link :href="route('agency.workers.index')" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-50 transition">Cancel</Link>
                <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-blue-500/30 disabled:opacity-60">
                    {{ form.processing ? 'Saving...' : isEdit ? 'Save changes' : 'Submit for HR approval' }}
                </button>
            </div>
        </form>

        <!-- Save confirmation — lists changes before submitting -->
        <div v-if="showSaveConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" @click.self="showSaveConfirm = false">
            <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 shadow-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                    <p class="text-sm font-black text-slate-900 dark:text-white">{{ isEdit ? 'Confirm save changes?' : 'Confirm submission?' }}</p>
                    <p class="text-[11px] text-slate-400">{{ isEdit ? 'Review what will be updated.' : 'Review what will be sent for HR approval.' }}</p>
                </div>
                <div class="px-5 py-4 max-h-[60vh] overflow-y-auto space-y-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Field changes (old → new) — {{ saveDiff.length }}</p>
                        <ul v-if="saveDiff.length" class="text-xs space-y-1">
                            <li v-for="r in saveDiff" :key="r.label" class="flex flex-wrap gap-x-1.5"><span class="font-bold text-slate-700 dark:text-slate-200">{{ r.label }}:</span><span class="text-slate-400 line-through">{{ r.old }}</span><span class="text-slate-400">→</span><span class="font-bold text-emerald-600">{{ r.next }}</span></li>
                        </ul>
                        <p v-else class="text-[11px] text-slate-400">No field edits.</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">New files to upload — {{ newUploads.length }}</p>
                        <ul v-if="newUploads.length" class="text-xs space-y-1">
                            <li v-for="u in newUploads" :key="u" class="text-slate-600 dark:text-slate-300">{{ u }}</li>
                        </ul>
                        <p v-else class="text-[11px] text-slate-400">No new files selected.</p>
                    </div>
                    <p v-if="isEdit && props.worker?.status === 'For Correction'" class="text-[11px] text-orange-600 dark:text-orange-400">Saving resubmits the worker to Pending HR Approval.</p>
                    <p v-if="!saveDiff.length && !newUploads.length" class="text-[11px] font-bold text-amber-600">No changes detected — nothing will be updated.</p>
                </div>
                <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-2">
                    <button type="button" @click="showSaveConfirm = false" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Back</button>
                    <button type="button" @click="confirmSave" :disabled="form.processing" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition disabled:opacity-50">{{ form.processing ? 'Saving…' : isEdit ? 'Confirm & save' : 'Confirm & submit' }}</button>
                </div>
            </div>
        </div>

        <!-- In-page file viewer (full screen, stays on this page, no new tab) -->
        <div v-if="previewUrl" class="fixed inset-0 z-50 flex flex-col bg-slate-900/80" @click.self="closePreview">
            <div class="w-full h-full flex flex-col bg-white dark:bg-slate-800 overflow-hidden">
                <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-200 truncate">{{ previewName }}</p>
                    <button type="button" @click="closePreview" class="shrink-0 px-4 py-2 text-sm font-bold bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-lg">Close</button>
                </div>
                <div class="flex-1 overflow-auto p-0 bg-slate-900">
                    <img v-if="previewKind === 'image'" :src="previewUrl" :alt="previewName" class="mx-auto max-h-full max-w-full object-contain" />
                    <iframe v-else-if="previewKind === 'pdf'" :src="previewUrl" class="w-full h-full min-h-[calc(100vh-57px)] bg-white border-0" title="File preview"></iframe>
                    <div v-else class="p-6 text-center bg-slate-50 dark:bg-slate-900 h-full">
                        <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Preview not available for this file type (e.g. DOC / DOCX).</p>
                        <p class="mt-1 text-xs text-slate-400">The file is already selected — no need to open another tab. Use Close to return to the form.</p>
                    </div>
                </div>
            </div>
        </div>
    </AgencyLayout>
</template>
