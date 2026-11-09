<script setup lang="ts">
// Agency portal: add / edit own worker — full profile submission (Sections A-F).
// New submissions enter as Pending HR Approval and become Available only
// after HR approves and all required checks are satisfied.
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { confirmModal } from '@/lib/feedback';
import AgencyLayout from '@/Layouts/AgencyLayout.vue';

const props = defineProps<{
    worker: any | null;
    existing_workers: Array<{ id: number; code: string; name: string; birth_date: string | null; phone: string | null; gov_id_number: string | null }>;
    privacy_notice_version: string;
    document_types: Record<string, string>;
    document_statuses: string[];
    departments: Array<{ id: number; code: string; name: string }>;
    positions: Array<{ id: number; name: string; department_id: number | null }>;
}>();
const isEdit = !!props.worker;
const hasErrors = computed(() => Object.keys(form.errors as Record<string, string>).length > 0);
const birthDateCutoff = new Date();
birthDateCutoff.setFullYear(birthDateCutoff.getFullYear() - 18);
const birthDateMax = `${birthDateCutoff.getFullYear()}-${String(birthDateCutoff.getMonth() + 1).padStart(2, '0')}-${String(birthDateCutoff.getDate()).padStart(2, '0')}`;
const birthDateRejected = ref(false);
const currentDate = new Date();
currentDate.setDate(currentDate.getDate() + 3);
const availabilityMin = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(currentDate.getDate()).padStart(2, '0')}`;
const legacyGovIdTypes: Record<string, string> = {
    'Pag-IBIG': 'Pag-IBIG MID',
    'PhilHealth': 'PhilHealth PIN',
    'National ID': 'National ID (PCN)',
    "Voter's ID": "Voter's ID / VIN",
};

const form = useForm({
    name: props.worker?.name || '',
    birth_date: props.worker?.birth_date || '',
    gender: props.worker?.gender || '',
    phone: props.worker?.phone || '',
    email: props.worker?.email || '',
    address: props.worker?.address || '',
    resume: null as File | null,
    education: props.worker?.education || '',
    education_address: props.worker?.education_address || '',
    work_experience: props.worker?.work_experience || '',
    skills: props.worker?.skills || '',
    trainings_certifications: props.worker?.trainings_certifications || '',
    position_skill: props.worker?.position_skill || '',
    years_experience: props.worker?.years_experience == null || !Number.isFinite(Number(props.worker.years_experience))
        ? ''
        : String(Math.trunc(Number(props.worker.years_experience))),
    proposed_department: props.worker?.proposed_department || '',
    availability: props.worker?.availability || '',
    daily_rate: props.worker?.daily_rate ?? '',
    employment_arrangement: props.worker?.employment_arrangement || '',
    gov_id_type: legacyGovIdTypes[props.worker?.gov_id_type] || props.worker?.gov_id_type || '',
    gov_id_number: props.worker?.gov_id_number || '',
    documents: [] as File[],
    document_types: [] as string[],
    document_labels: [] as string[],
    document_starts: [] as string[],
    document_expiries: [] as string[],
    document_descriptions: [] as string[],
    emergency_name: props.worker?.emergency_name || '',
    emergency_relationship: props.worker?.emergency_relationship || '',
    emergency_phone: props.worker?.emergency_phone || '',
    consent_given: props.worker?.consent_given || false,
});
const educationOptions = [
    'Elementary level',
    'Elementary graduate',
    'Junior High School level',
    'Junior High School graduate',
    'Senior High School level',
    'Senior High School graduate',
    'High School Graduate',
    'Vocational / Technical graduate (TESDA)',
    'Some college',
    'College undergraduate',
    "Bachelor's degree graduate",
    'Postgraduate',
    'Other',
];
let qualificationRowId = 0;
const skillItems = ref<{ id: number; value: string }[]>(
    String(form.skills || '').split(',').map((value) => value.trim()).filter(Boolean).map((value) => ({ id: qualificationRowId++, value })),
);
if (skillItems.value.length === 0) skillItems.value.push({ id: qualificationRowId++, value: '' });
const workExperienceItems = ref<{ id: number; value: string }[]>(
    String(form.work_experience || '').split(/\n\s*\n/).map((value) => value.trim()).filter(Boolean).map((value) => ({ id: qualificationRowId++, value })),
);
if (workExperienceItems.value.length === 0) workExperienceItems.value.push({ id: qualificationRowId++, value: '' });
watch(skillItems, (items) => {
    form.skills = items.map((item) => item.value.trim()).filter(Boolean).join(', ');
}, { deep: true, immediate: true });
watch(workExperienceItems, (items) => {
    form.work_experience = items.map((item) => item.value.trim()).filter(Boolean).join('\n\n');
}, { deep: true, immediate: true });
function addSkill() { skillItems.value.push({ id: qualificationRowId++, value: '' }); }
function removeSkill(id: number) {
    skillItems.value = skillItems.value.filter((item) => item.id !== id);
    if (skillItems.value.length === 0) addSkill();
}
function addWorkExperience() { workExperienceItems.value.push({ id: qualificationRowId++, value: '' }); }
function removeWorkExperience(id: number) {
    workExperienceItems.value = workExperienceItems.value.filter((item) => item.id !== id);
    if (workExperienceItems.value.length === 0) addWorkExperience();
}
const duplicateMatch = computed(() => {
    const normalizeName = (value: unknown) => String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
    const normalizeDigits = (value: unknown) => String(value || '').replace(/\D/g, '');
    const normalizeId = (value: unknown) => String(value || '').replace(/[^a-z0-9]/gi, '').toUpperCase();
    const name = normalizeName(form.name);
    const phone = normalizeDigits(form.phone);
    const idNumber = normalizeId(form.gov_id_number);
    if (!name && !phone && !idNumber) return null;

    for (const existing of props.existing_workers || []) {
        const existingName = normalizeName(existing.name);
        const existingPhone = normalizeDigits(existing.phone);
        const existingId = normalizeId(existing.gov_id_number);
        if (idNumber && idNumber.length >= 6 && existingId === idNumber) return { ...existing, reason: 'Government ID number matches' };
        if (name && form.birth_date && name === existingName && form.birth_date === existing.birth_date) return { ...existing, reason: 'Name and birth date match' };
        if (name && phone.length >= 7 && name === existingName && phone === existingPhone) return { ...existing, reason: 'Name and phone number match' };
    }
    return null;
});

const resumeName = ref(props.worker?.resume_original_name || '');
const existingDocs = ref<Array<any>>(props.worker?.documents || []);
const expiredDocumentsToReplace = computed(() => (props.worker?.documents || []).filter((doc: any) => doc.status === 'Expired' && doc.doc_type !== 'resume'));
const replacementError = ref('');

// Keep visible uploaded list in sync when Inertia reloads props
// (e.g. after soft-delete returns back() with fresh documents).
watch(() => props.worker?.documents, (v) => {
    existingDocs.value = [...(v || [])];
}, { deep: true });
watch(() => props.worker?.resume_original_name, (v) => {
    if (!form.resume) resumeName.value = v || '';
});

// File input refs so X can reset the native "No file chosen" control.
const resumeInputRef = ref<HTMLInputElement | null>(null);

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

const govIdGuidance: Record<string, { format: string; valid: (value: string) => boolean }> = {
    'SSS': { format: 'XX-XXXXXXX-X (10 digits)', valid: (v) => /^\d{2}-?\d{7}-?\d$/.test(v) },
    'Pag-IBIG MID': { format: 'XXXX-XXXX-XXXX (12 digits)', valid: (v) => /^\d{4}-?\d{4}-?\d{4}$/.test(v) },
    'PhilHealth PIN': { format: 'XX-XXXXXXXXX-X (12 digits)', valid: (v) => /^\d{2}-?\d{9}-?\d$/.test(v) },
    'TIN': { format: 'XXX-XXX-XXX (9 or 12 digits)', valid: (v) => /^(?:\d{9}|\d{12})$/.test(v.replaceAll('-', '')) },
    "Driver's License": { format: 'Alphanumeric; hyphens allowed', valid: (v) => /^[A-Za-z0-9-]+$/.test(v) },
    "Voter's ID / VIN": { format: 'Alphanumeric; format varies', valid: (v) => /^[A-Za-z0-9]+$/.test(v) },
    'Passport': { format: 'Alphanumeric; format varies', valid: (v) => /^[A-Za-z0-9]+$/.test(v) },
    'National ID (PCN)': { format: 'XXXX-XXXX-XXXX-XXXX (16 digits)', valid: (v) => /^\d{4}-?\d{4}-?\d{4}-?\d{4}$/.test(v) },
};
function formatGovIdNumber(event?: Event) {
    const input = event?.target instanceof HTMLInputElement ? event.target : null;
    const current = form.gov_id_number;
    const caret = input?.selectionStart ?? current.length;
    const digitsBeforeCaret = current.slice(0, caret).replace(/\D/g, '').length;
    const grouping: Record<string, { sizes: number[]; max: number }> = {
        'SSS': { sizes: [2, 7, 1], max: 10 },
        'Pag-IBIG MID': { sizes: [4, 4, 4], max: 12 },
        'PhilHealth PIN': { sizes: [2, 9, 1], max: 12 },
        'TIN': { sizes: current.replace(/\D/g, '').length > 9 ? [3, 3, 3, 3] : [3, 3, 3], max: 12 },
        'National ID (PCN)': { sizes: [4, 4, 4, 4], max: 16 },
    };
    const format = grouping[form.gov_id_type];
    if (!format) return;

    const digits = current.replace(/\D/g, '').slice(0, format.max);
    const parts: string[] = [];
    let offset = 0;
    for (const size of format.sizes) {
        if (offset >= digits.length) break;
        parts.push(digits.slice(offset, offset + size));
        offset += size;
    }
    const formatted = parts.join('-');
    form.gov_id_number = formatted;
    if (input) {
        input.value = formatted;
        let nextCaret = 0;
        let seenDigits = 0;
        while (nextCaret < formatted.length && seenDigits < digitsBeforeCaret) {
            if (/\d/.test(formatted[nextCaret])) seenDigits++;
            nextCaret++;
        }
        if (digitsBeforeCaret > 0 && nextCaret < formatted.length && formatted[nextCaret] === '-') nextCaret++;
        input.setSelectionRange(nextCaret, nextCaret);
    }
}
const govIdHint = computed(() => govIdGuidance[form.gov_id_type]?.format || (form.gov_id_type ? 'Enter the number shown on the selected ID.' : 'Select an ID type to see its number format.'));
const govIdError = computed(() => {
    const number = form.gov_id_number.trim();
    if (!number) return '';
    if (!form.gov_id_type) return 'Select a Government ID type.';
    return govIdGuidance[form.gov_id_type]?.valid(number) ? '' : `Use this format: ${govIdGuidance[form.gov_id_type]?.format || 'valid ID number'}.`;
});
const availabilityError = computed(() => form.availability && form.availability < availabilityMin ? 'Availability date must be at least 3 days from today.' : '');

const departmentOptions = computed(() => {
    return props.departments ?? [];
});

const filteredPositions = computed(() => {
    // No department chosen → position list stays empty.
    if (!(form as any).proposed_department) {
        return [];
    }
    // Legacy / custom department → keep current value so edit doesn't lose data.
    if (selectedDepartmentId.value === null) return [];
    const all = (props.positions ?? []).filter((p) => p.department_id === selectedDepartmentId.value);
    return all;
});

// Existing worker data can contain a position from a different department.
// Keep the edit form aligned with the HR position master on initial load too.
if (!departmentOptions.value.some((department) => department.name === form.proposed_department)) {
    form.proposed_department = '';
    form.position_skill = '';
} else if (!filteredPositions.value.some((position) => position.name === form.position_skill)) {
    form.position_skill = '';
}

watch(() => (form as any).proposed_department, () => {
    (form as any).position_skill = '';
});

function onResumeChange(e: Event) {
    const files = (e.target as HTMLInputElement).files;
    form.resume = files && files[0] ? files[0] : null;
    resumeName.value = form.resume ? form.resume.name : (props.worker?.resume_original_name || '');
}

function sanitizeYearsExperience(event: Event) {
    const input = event.target as HTMLInputElement;
    const digitsOnly = input.value.replace(/\D/g, '').slice(0, 2);
    input.value = digitsOnly;
    form.years_experience = digitsOnly;
}

function limitPhoneDigits(event: Event) {
    const input = event.target as HTMLInputElement;
    let digitCount = 0;
    input.value = [...input.value].filter((char, index) => {
        if (/\d/.test(char)) return ++digitCount <= 11;
        return char === ' ' || (char === '+' && index === 0);
    }).join('');
    form.phone = input.value;
}

function enforceBirthDateLimit(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.value && input.value > birthDateMax) {
        form.birth_date = '';
        input.value = '';
        birthDateRejected.value = true;
        return;
    }
    birthDateRejected.value = false;
}

type PendingDocument = { type: string; name: string; file: File | null; description: string };
const pendingDocuments = ref<PendingDocument[]>([]);
const showAddDocument = ref(false);
const newDocument = ref<PendingDocument>({ type: 'other', name: '', file: null, description: '' });
const documentInput = ref<HTMLInputElement | null>(null);
const documentTypeOptions = computed(() => Object.entries(props.document_types || {}).filter(([key]) => key !== 'resume'));

function addDocument() {
    if (!newDocument.value.type || !newDocument.value.name.trim() || !newDocument.value.file) return;
    pendingDocuments.value.push({ ...newDocument.value });
    newDocument.value = { type: 'other', name: '', file: null, description: '' };
    if (documentInput.value) documentInput.value.value = '';
    showAddDocument.value = false;
    syncCategoryDocs();
}

function removePendingDocument(index: number) {
    pendingDocuments.value.splice(index, 1);
    syncCategoryDocs();
}

// X on a newly selected (not yet saved) file — client-side only, no backend call.
function removeNewResume() {
    form.resume = null;
    resumeName.value = props.worker?.resume_original_name || '';
    if (resumeInputRef.value) resumeInputRef.value.value = '';
}

// X on an already-uploaded file — SOFT DELETE ONLY (backend sets deleted_at,
// row + file stay recoverable). Removes the row from the visible list.
const deletingDocId = ref<number | null>(null);

async function deleteUploadedDocument(doc: any) {
    if (!isEdit || !props.worker) return;
    const name = doc.original_name || doc.label || doc.doc_type;
    if (!await confirmModal(`Remove "${name}"? It will be hidden but kept in the database (recoverable).`)) return;
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

function syncCategoryDocs() {
    form.documents = pendingDocuments.value.map((doc) => doc.file).filter(Boolean) as File[];
    form.document_types = pendingDocuments.value.map((doc) => doc.type);
    form.document_labels = pendingDocuments.value.map((doc) => doc.name.trim());
    form.document_descriptions = pendingDocuments.value.map((doc) => doc.description.trim());
    form.document_starts = pendingDocuments.value.map(() => '');
    form.document_expiries = pendingDocuments.value.map(() => '');
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
    return status === 'No expiry'
        ? 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'
        : status === 'Verified'
        ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
        : status === 'Rejected'
            ? 'bg-rose-100 text-rose-700 border-rose-200'
            : status === 'Expired'
                ? 'bg-slate-200 text-slate-600 border-slate-300'
                : 'bg-amber-100 text-amber-700 border-amber-200';
}

function documentStatus(doc: any): string {
    // A resume is a profile document and does not expire like a clearance or ID.
    return doc.doc_type === 'resume' && doc.status === 'Expired' ? 'No expiry' : doc.status;
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
const showDuplicateWarning = ref(false);
const showCancelConfirm = ref(false);
const pendingSidebarHref = ref<string | null>(null);

function interceptSidebarNavigation(event: MouseEvent) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const target = event.target as HTMLElement | null;
    const anchor = target?.closest('aside a[href]') as HTMLAnchorElement | null;
    if (!anchor || anchor.target === '_blank' || anchor.href === window.location.href) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    pendingSidebarHref.value = anchor.href;
    showCancelConfirm.value = true;
}

function discardForm() {
    const destination = pendingSidebarHref.value || route('agency.workers.index');
    pendingSidebarHref.value = null;
    showCancelConfirm.value = false;
    router.visit(destination);
}

onMounted(() => document.addEventListener('click', interceptSidebarNavigation, true));
onUnmounted(() => document.removeEventListener('click', interceptSidebarNavigation, true));

const reviewSections = computed(() => [
    { title: 'Personal Information', fields: [
        ['Full name', form.name], ['Birth date', form.birth_date], ['Gender', form.gender],
        ['Phone', form.phone], ['Email', form.email], ['Address', form.address],
    ] },
    { title: 'Emergency Contact', fields: [
        ['Contact name', form.emergency_name], ['Relationship', form.emergency_relationship], ['Phone', form.emergency_phone],
    ] },
    { title: 'Resume and Qualifications', fields: [
        ['Resume / CV', form.resume?.name || resumeName.value], ['Education', form.education],
        ['School address', form.education_address], ['Work experience', form.work_experience],
        ['Skills', form.skills], ['Training / certifications', form.trainings_certifications],
    ] },
    { title: 'Work and Deployment', fields: [
        ['Department / assignment', form.proposed_department], ['Position / skill', form.position_skill],
        ['Years of experience', form.years_experience], ['Availability date', form.availability],
        ['Daily rate', form.daily_rate === '' ? '' : `₱${form.daily_rate}`],
    ] },
    { title: 'Government ID', fields: [
        ['ID type', form.gov_id_type], ['ID number', form.gov_id_number],
    ] },
]);

function reviewValue(value: unknown): string {
    return value === null || value === undefined || value === '' ? '—' : String(value);
}

function openSaveConfirm() {
    syncCategoryDocs();
    const missingReplacement = expiredDocumentsToReplace.value.find((expired: any) => {
        if (existingDocs.value.some((current: any) => current.id === expired.id)) return true;
        const requiredName = String(expired.label || expired.original_name || '').trim().toLowerCase();
        return !pendingDocuments.value.some((replacement) =>
            replacement.type === expired.doc_type && replacement.name.trim().toLowerCase() === requiredName
        );
    });
    if (missingReplacement) {
        const label = missingReplacement.label || missingReplacement.original_name || missingReplacement.doc_type;
        replacementError.value = missingReplacement.doc_type !== 'resume' && existingDocs.value.some((current: any) => current.id === missingReplacement.id)
            ? `Remove the expired “${label}” file, then upload its replacement before saving.`
            : `Upload a replacement for “${label}” before saving.`;
        return;
    }
    replacementError.value = '';
    if (duplicateMatch.value) {
        showDuplicateWarning.value = true;
        return;
    }
    showSaveConfirm.value = true;
}

function continueWithPossibleDuplicate() {
    showDuplicateWarning.value = false;
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

// ─── Multi-step wizard ───────────────────────────────────────────────
const steps = [
    { key: 'personal', title: 'Personal', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { key: 'qualifications', title: 'Resume', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { key: 'work', title: 'Work', icon: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
    { key: 'documents', title: 'Documents', icon: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
    { key: 'consent', title: 'Review', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
];
const step = ref(0);
const maxStep = computed(() => steps.length - 1);
const progressWidth = computed(() => `${(step.value / maxStep.value) * 100}%`);

const stepErrors = ref<string[]>([]);

function validateStep(i: number): string[] {
    const f: any = form as any;
    const errs: string[] = [];
    const req = (val: any, label: string) => {
        if (val === '' || val === null || val === undefined) errs.push(label);
    };
    if (i === 0) {
        req(f.name, 'Full name');
        req(f.birth_date, 'Birth date');
        req(f.phone, 'Phone number');
        req(f.address, 'Complete address');
    } else if (i === 1) {
        if (!isEdit && !f.resume) errs.push('Resume / CV');
        req(f.education, 'Educational attainment');
        req(f.skills, 'Skills');
    } else if (i === 2) {
        req(f.position_skill, 'Position / skill');
        req(f.availability, 'Availability date');
        if (availabilityError.value) errs.push(availabilityError.value);
    } else if (i === 3 && govIdError.value) {
        errs.push(govIdError.value);
    } else if (i === 4) {
        if (!isEdit && !f.consent_given) errs.push('Consent');
    }
    return errs;
}

function nextStep() {
    const errs = validateStep(step.value);
    if (errs.length) { stepErrors.value = errs; return; }
    stepErrors.value = [];
    if (step.value < maxStep.value) step.value++;
}
function prevStep() {
    stepErrors.value = [];
    if (step.value > 0) step.value--;
}
function goToStep(i: number) {
    if (i <= step.value) { stepErrors.value = []; step.value = i; }
}
function stepDone(i: number): boolean {
    return i < step.value && validateStep(i).length === 0;
}

const inputCls = 'w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-amber-500/10 focus:border-amber-400 hover:border-slate-300 dark:hover:border-slate-600 transition disabled:opacity-50 disabled:cursor-not-allowed';
const labelCls = 'block mb-1.5 text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider';
const sectionCls = 'rounded-2xl border border-slate-200 dark:border-slate-700/70 bg-white dark:bg-slate-800/60 shadow-sm shadow-slate-200/50 dark:shadow-none p-5 sm:p-6';
const hintCls = 'mt-1.5 text-[11px] text-slate-400';

function fieldCls(key: string): string {
    const base = 'w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-slate-900/70 border rounded-xl text-slate-700 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-4 transition disabled:opacity-50 disabled:cursor-not-allowed';
    return err(key)
        ? `${base} border-rose-300 dark:border-rose-500/60 focus:border-rose-400 focus:ring-rose-500/10`
        : `${base} border-slate-200 dark:border-slate-700 focus:border-amber-400 focus:ring-amber-500/10 hover:border-slate-300 dark:hover:border-slate-600`;
}

const sectionIconCls = 'w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 text-white flex items-center justify-center shadow-md shadow-amber-500/25 shrink-0';
const errCls = 'mt-1.5 flex items-center gap-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400';
</script>

<template>
    <Head :title="(isEdit ? 'Edit Worker' : 'Add Worker') + ' - Monti ERP'" />
    <AgencyLayout>
        <nav class="mb-4 flex w-full items-center gap-1.5 text-xs font-medium text-slate-400">
            <Link :href="route('agency.workers.index')" class="inline-flex items-center gap-1 hover:text-amber-700 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10" /></svg>
                Workers
            </Link>
            <span class="text-slate-300 dark:text-slate-600">/</span>
            <span class="font-semibold text-slate-600 dark:text-slate-300">{{ isEdit ? 'Edit worker' : 'Add worker' }}</span>
        </nav>

        <!-- Standard form wizard progress -->
        <div class="agency-page-header relative mb-6 w-full">
            <div class="relative flex items-start justify-between gap-4">
                <div>
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-300">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        Agency · Workers
                    </span>
                    <p class="agency-page-eyebrow">Agency Portal · Workers</p>
                    <h1>{{ isEdit ? (form.name || 'Edit worker') : 'Add worker' }}</h1>
                    <p v-if="isEdit" class="mt-0.5 text-xs font-semibold text-amber-300">Worker profile · Edit details</p>
                    <p class="mt-1 max-w-xl text-xs leading-relaxed text-slate-300">
                        {{ isEdit ? 'Status changes stay with HR. You can edit worker details directly, including daily rate.' : 'New workers enter as Pending HR Approval and become Available only after HR approves them.' }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-col items-end pl-4">
                    <span class="rounded-md bg-white/10 px-2.5 py-1 text-xs font-semibold text-slate-200">Step {{ step + 1 }} of {{ steps.length }}</span>
                </div>
            </div>

            <div class="relative mt-7">
                <div class="absolute left-0 right-0 top-[18px] h-0.5 rounded-full bg-slate-200 dark:bg-slate-600"></div>
                <div class="absolute left-0 top-[18px] h-0.5 rounded-full bg-amber-600 transition-all duration-500 ease-out" :style="{ width: progressWidth }"></div>
                <div class="relative flex justify-between">
                    <button v-for="(s, i) in steps" :key="s.key" type="button" @click="goToStep(i)" :disabled="i > step"
                        class="group flex min-w-0 flex-1 flex-col items-center gap-2 bg-transparent"
                        :class="i <= step ? 'cursor-pointer' : 'cursor-not-allowed'">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full border text-xs font-semibold transition-colors"
                            :class="stepDone(i) ? 'border-amber-600 bg-amber-600 text-white' : i === step ? 'border-amber-600 bg-white text-amber-700 ring-2 ring-amber-100 dark:bg-zinc-800 dark:text-amber-400 dark:ring-amber-900/40' : 'border-slate-300 bg-white text-slate-500 dark:border-slate-600 dark:bg-zinc-800 dark:text-slate-400'">
                            <svg v-if="stepDone(i)" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            <span v-else>{{ i + 1 }}</span>
                        </span>
                        <span class="w-full truncate text-center text-[10px] font-medium sm:text-xs" :class="i <= step ? 'text-slate-800 dark:text-slate-200' : 'text-slate-400 dark:text-slate-500'">{{ s.title }}</span>
                    </button>
                </div>
            </div>
        </div>

        <form @submit.prevent="openSaveConfirm" class="w-full space-y-5">
            <div v-if="hasErrors" class="flex items-start gap-3 p-4 rounded-2xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800">
                <span class="w-9 h-9 rounded-xl bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </span>
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-rose-700 dark:text-rose-400">Could not save — fix the following</p>
                    <ul class="mt-1 text-sm text-rose-700 dark:text-rose-300 list-disc pl-5">
                        <li v-for="(msg, key) in (form.errors as Record<string,string>)" :key="key">{{ msg }}</li>
                    </ul>
                    <p class="mt-1 text-[11px] text-rose-500">Nothing was saved, so the form still shows the old data.</p>
                </div>
            </div>
            <div v-if="isEdit && props.worker?.status === 'For Correction' && props.worker?.pending_note" class="flex items-start gap-3 p-4 rounded-2xl bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800">
                <span class="w-9 h-9 rounded-xl bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.062 18a2 2 0 001.732 3h10.412a2 2 0 001.732-3L13.732 4a2 2 0 00-3.464 0L5.062 18z" /></svg>
                </span>
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-orange-700 dark:text-orange-400">HR requested corrections</p>
                    <p class="mt-1 text-sm text-orange-800 dark:text-orange-300 whitespace-pre-line">{{ props.worker.pending_note }}</p>
                    <p class="mt-1 text-[11px] text-orange-600 dark:text-orange-400">Update the requested details, then use Send corrections to Monti on the final step — the worker returns to Pending HR Approval.</p>
                </div>
            </div>
            <div v-if="expiredDocumentsToReplace.length" class="rounded-2xl border border-rose-300 bg-rose-50 p-4 dark:border-rose-900 dark:bg-rose-950/30">
                <p class="text-xs font-black uppercase tracking-wider text-rose-800 dark:text-rose-300">HR marked documents Expired — replacement required</p>
                <p class="mt-1 text-xs text-rose-700 dark:text-rose-300">Upload a new file for every item below before saving. For supporting documents, remove the expired file with its trash button, then add a replacement using the same document type and name.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-rose-800 dark:text-rose-200">
                    <li v-for="doc in expiredDocumentsToReplace" :key="doc.id">
                        {{ doc.label || doc.original_name || doc.doc_type }}<span v-if="doc.review_note"> — HR note: {{ doc.review_note }}</span>
                    </li>
                </ul>
                <p v-if="replacementError" class="mt-2 text-xs font-bold text-rose-700 dark:text-rose-300">{{ replacementError }}</p>
            </div>
            <!-- Personal Information -->
            <div v-show="step === 0" :class="sectionCls">
                <div class="flex items-start gap-3 mb-6 pb-5 border-b border-slate-100 dark:border-slate-700/60">
                    <span :class="sectionIconCls">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Personal Information</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Full legal name, birth date, phone, and complete address are required.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
                    <div>
                        <label :class="labelCls">Full name <span class="text-rose-500">*</span></label>
                        <input v-model="form.name" type="text" placeholder="Enter full legal name" :class="fieldCls('name')" />
                        <p v-if="err('name')" :class="errCls">{{ err('name') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Birth date <span class="text-rose-500">*</span></label>
                        <input v-model="form.birth_date" type="date" :max="birthDateMax" :class="fieldCls('birth_date')" @input="enforceBirthDateLimit" />
                        <p :class="hintCls">Workers must be at least 18 years old.</p>
                        <p v-if="birthDateRejected" :class="errCls">Please enter a birth date for someone 18 or older.</p>
                        <p v-if="err('birth_date')" :class="errCls">{{ err('birth_date') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Gender</label>
                        <select v-model="form.gender" :class="fieldCls('gender')">
                            <option value="">Select...</option>
                            <option>Male</option><option>Female</option>
                        </select>
                        <p v-if="err('gender')" :class="errCls">{{ err('gender') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Phone number <span class="text-rose-500">*</span></label>
                        <input v-model="form.phone" type="tel" inputmode="tel" maxlength="13" autocomplete="tel" placeholder="09XX XXX XXXX" :class="fieldCls('phone')" @input="limitPhoneDigits" />
                        <p v-if="err('phone')" :class="errCls">{{ err('phone') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Email address</label>
                        <input v-model="form.email" type="email" placeholder="name@example.com" :class="fieldCls('email')" />
                        <p v-if="err('email')" :class="errCls">{{ err('email') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Complete address <span class="text-rose-500">*</span></label>
                        <textarea v-model="form.address" rows="2" placeholder="House no., street, barangay, city, province" :class="fieldCls('address')"></textarea>
                        <p v-if="err('address')" :class="errCls">{{ err('address') }}</p>
                    </div>
                </div>
                <div class="mt-7 border-t border-slate-100 pt-5 dark:border-slate-700/60">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Emergency Contact</h3>
                    <p class="mb-4 mt-0.5 text-[11px] text-slate-400">Optional initially — may be required before deployment.</p>
                    <div class="grid grid-cols-1 gap-x-5 gap-y-4 sm:grid-cols-3">
                        <div><label :class="labelCls">Contact name</label><input v-model="form.emergency_name" type="text" :class="inputCls" /></div>
                        <div><label :class="labelCls">Relationship</label><select v-model="form.emergency_relationship" :class="inputCls"><option value="">Select...</option><option>Spouse</option><option>Parent</option><option>Sibling</option><option>Child</option><option>Relative</option><option>Friend</option><option>Other</option></select></div>
                        <div><label :class="labelCls">Phone number</label><input v-model="form.emergency_phone" type="text" :class="inputCls" /></div>
                    </div>
                </div>
            </div>

            <!-- Resume and Qualifications -->
            <div v-show="step === 1" :class="sectionCls">
                <div class="flex items-start gap-3 mb-6 pb-5 border-b border-slate-100 dark:border-slate-700/60">
                    <span :class="sectionIconCls">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Resume and Qualifications</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Resume, educational attainment, and skills are required. Experience and certifications are optional.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Upload Resume / CV <span v-if="!isEdit" class="text-rose-500">*</span></label>
                        <input ref="resumeInputRef" type="file" accept=".pdf,.doc,.docx" :class="[fieldCls('resume'), 'file:mr-3 file:rounded-lg file:border-0 file:bg-amber-600 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white file:transition-all file:duration-300 hover:file:bg-amber-700 hover:file:shadow-md focus-visible:file:ring-2 focus-visible:file:ring-amber-400']" @change="onResumeChange" />
                        <p :class="hintCls">PDF or DOCX · max 10MB · Required for full profile submission</p>
                        <div v-if="form.resume" class="mt-2 p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between gap-2">
                            <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 truncate">Selected: {{ (form.resume as File).name }}</p>
                            <div class="shrink-0 flex items-center gap-1">
                                <button type="button" @click="openLocalPreview(form.resume as File)" class="text-[11px] font-bold text-amber-700 hover:underline">View</button>
                                <button type="button" @click="removeNewResume" title="Remove selected file" class="w-6 h-6 inline-flex items-center justify-center rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 font-black">×</button>
                            </div>
                        </div>
                        <div v-else-if="resumeName" class="mt-2 p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between gap-2">
                            <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 truncate">Uploaded: {{ resumeName }}</p>
                            <div class="shrink-0 flex items-center gap-1">
                                <button v-if="props.worker?.resume_url" type="button" @click="openRemotePreview(props.worker.resume_url, resumeName || 'Resume')" class="text-[11px] font-bold text-amber-700 hover:underline">View</button>
                                <button type="button" @click="removeUploadedResume" :disabled="deletingDocId !== null" title="Remove uploaded resume (kept in database)" class="w-6 h-6 inline-flex items-center justify-center rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 font-black disabled:opacity-40">×</button>
                            </div>
                        </div>
                        <p v-if="err('resume')" :class="errCls">{{ err('resume') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Educational attainment <span class="text-rose-500">*</span></label>
                        <select v-model="form.education" :class="fieldCls('education')">
                            <option value="" disabled>Select educational attainment...</option>
                            <option v-if="form.education && !educationOptions.includes(form.education)" :value="form.education">{{ form.education }}</option>
                            <option v-for="option in educationOptions" :key="option" :value="option">{{ option }}</option>
                        </select>
                        <p v-if="err('education')" :class="errCls">{{ err('education') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">School address</label>
                        <input v-model="form.education_address" type="text" maxlength="2000" placeholder="School or training center address" :class="inputCls" />
                    </div>
                    <div class="sm:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label :class="labelCls" class="mb-0">Previous work experience</label>
                            <button type="button" @click="addWorkExperience" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-[11px] font-bold text-amber-800 shadow-sm transition hover:border-amber-300 hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-900/50">
                                <span aria-hidden="true" class="flex h-4 w-4 items-center justify-center rounded bg-amber-200/70 text-xs leading-none dark:bg-amber-800/70">+</span>
                                Add experience
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div v-for="(experience, index) in workExperienceItems" :key="experience.id" class="flex items-start gap-2">
                                <input v-model="experience.value" type="text" maxlength="2000" :aria-label="`Previous work experience ${index + 1}`" :placeholder="index === 0 ? 'Company, role, and dates...' : 'Another employer, role, and dates...'" :class="inputCls" />
                                <button type="button" @click="removeWorkExperience(experience.id)" :aria-label="`Remove work experience ${index + 1}`" class="mt-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/20">×</button>
                            </div>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label :class="labelCls" class="mb-0">Skills <span class="text-rose-500">*</span></label>
                            <button type="button" @click="addSkill" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-[11px] font-bold text-amber-800 shadow-sm transition hover:border-amber-300 hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-900/50">
                                <span aria-hidden="true" class="flex h-4 w-4 items-center justify-center rounded bg-amber-200/70 text-xs leading-none dark:bg-amber-800/70">+</span>
                                Add skill
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div v-for="(skill, index) in skillItems" :key="skill.id" class="flex items-center gap-2">
                                <input v-model="skill.value" type="text" :aria-label="`Skill ${index + 1}`" :placeholder="index === 0 ? 'e.g. Equipment operation' : 'Add another skill'" :class="fieldCls('skills')" />
                                <button type="button" @click="removeSkill(skill.id)" :aria-label="`Remove skill ${index + 1}`" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/20">×</button>
                            </div>
                        </div>
                        <p v-if="err('skills')" :class="errCls">{{ err('skills') }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Training and certifications</label>
                        <textarea v-model="form.trainings_certifications" rows="2" placeholder="e.g. TESDA NC II (2024), Safety training..." :class="inputCls"></textarea>
                    </div>
                </div>
            </div>

            <!-- Work and Deployment Details -->
            <div v-show="step === 2" :class="sectionCls">
                <div class="flex items-start gap-3 mb-6 pb-5 border-b border-slate-100 dark:border-slate-700/60">
                    <span :class="sectionIconCls">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Work and Deployment Details</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Propose the position and availability. Monti confirms the final department, assignment, and agreed terms.</p>
                    </div>
                </div>

                <div class="mb-6 flex items-start gap-2.5 p-3.5 rounded-xl bg-amber-50/70 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/50">
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-relaxed">Fields marked <span class="text-rose-500 font-bold">*</span> are required to continue. You can refine these details later — HR sets the final terms.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
                    <p class="sm:col-span-2 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-400">
                        <span class="w-1 h-1 rounded-full bg-amber-500"></span> Assignment
                    </p>
                    <div class="sm:col-span-2">
                        <label :class="labelCls">Proposed department / assignment</label>
                        <select v-model="form.proposed_department" :class="inputCls">
                            <option value="">Select department...</option>
                            <option v-for="d in departmentOptions" :key="d.id + '-' + d.name" :value="d.name">{{ d.name }}</option>
                        </select>
                        <p v-if="departmentOptions.length === 0" class="mt-1 text-xs text-amber-600">No departments — HR adds them under HRM → Departments.</p>
                    </div>
                    <div>
                        <label :class="labelCls">Position / skill <span class="text-rose-500">*</span></label>
                        <select v-model="form.position_skill" :class="fieldCls('position_skill')" :disabled="!form.proposed_department">
                            <option value="">{{ !form.proposed_department ? 'Select department first...' : 'Select position...' }}</option>
                            <option v-for="p in filteredPositions" :key="p.id + '-' + p.name" :value="p.name">{{ p.name }}</option>
                        </select>
                        <p v-if="err('position_skill')" :class="errCls">{{ err('position_skill') }}</p>
                        <p v-else-if="!form.proposed_department" class="mt-1 text-xs text-slate-400">Select a department first to see its positions.</p>
                        <p v-else-if="filteredPositions.length === 0" class="mt-1 text-xs text-amber-600">No positions under this department — HR adds them under HRM → Positions.</p>
                    </div>
                    <div>
                        <label :class="labelCls">Years of experience</label>
                        <input v-model="form.years_experience" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2" placeholder="e.g. 2" :class="fieldCls('years_experience')" @input="sanitizeYearsExperience" />
                        <p v-if="err('years_experience')" :class="errCls">{{ err('years_experience') }}</p>
                    </div>

                    <p class="sm:col-span-2 mt-2 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-400">
                        <span class="w-1 h-1 rounded-full bg-amber-500"></span> Availability &amp; terms
                    </p>
                    <div>
                        <label :class="labelCls">Availability date <span class="text-rose-500">*</span></label>
                        <input v-model="form.availability" type="date" :min="availabilityMin" :class="fieldCls('availability')" />
                        <p :class="hintCls">Choose a date at least 3 days from today.</p>
                        <p v-if="legacyAvailability" class="mt-1 text-[11px] text-slate-400">Previous value: {{ legacyAvailability }} — pick a calendar date to update it.</p>
                        <p v-if="err('availability') || availabilityError" :class="errCls">{{ err('availability') || availabilityError }}</p>
                    </div>
                    <div>
                        <label :class="labelCls">Daily rate (₱)</label>
                        <input v-model="form.daily_rate" type="number" min="0" step="0.01" placeholder="e.g. 650" :class="fieldCls('daily_rate')" />
                        <p v-if="err('daily_rate')" :class="errCls">{{ err('daily_rate') }}</p>
                    </div>
                </div>
            </div>

            <!-- Government and Supporting Documents -->
            <div v-show="step === 3" :class="sectionCls">
                <div class="flex items-start gap-3 mb-6 pb-5 border-b border-slate-100 dark:border-slate-700/60">
                    <span :class="sectionIconCls">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Government and Supporting Documents</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Optional at registration — HR collects what the assignment requires. HR verifies each file as Pending, Verified, Rejected, or Expired.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
                    <div>
                        <label :class="labelCls">Government ID type</label>
                        <select v-model="form.gov_id_type" :class="inputCls" @change="formatGovIdNumber()">
                            <option value="">Select...</option>
                            <option>SSS</option><option>Pag-IBIG MID</option><option>PhilHealth PIN</option>
                            <option>TIN</option><option>Driver's License</option>
                            <option>Voter's ID / VIN</option><option>Passport</option><option>National ID (PCN)</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelCls">Government ID number</label>
                        <input v-model="form.gov_id_number" type="text" :placeholder="govIdGuidance[form.gov_id_type]?.format || 'ID number'" :class="fieldCls('gov_id_number')" @input="formatGovIdNumber" />
                        <p :class="hintCls">{{ govIdHint }}</p>
                        <p v-if="err('gov_id_number') || govIdError" :class="errCls">{{ err('gov_id_number') || govIdError }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Worker Documents</h3>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Upload additional documents anytime.</p>
                            </div>
                            <button type="button" @click="showAddDocument = !showAddDocument" class="rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-amber-700 hover:shadow-md">+ Add Document</button>
                        </div>
                        <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-zinc-900">
                            <li v-if="form.resume" @click="openLocalPreview(form.resume)" class="grid grid-cols-[minmax(0,1fr)_7rem_2.5rem] items-center gap-3 px-3 py-2.5 transition-colors hover:bg-slate-50 dark:hover:bg-zinc-800">
                                <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">Resume / CV</p><p class="truncate text-xs text-slate-500">{{ form.resume.name }} · Pending upload</p></div>
                                <button type="button" @click.stop="removeNewResume" title="Remove resume" aria-label="Remove resume" class="shrink-0 rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 hover:text-rose-800 dark:hover:bg-rose-900/20"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6h18m-2 0-.9 14H5.9L5 6m4 0V4h6v2m-5 4v6m4-6v6" /></svg></button>
                            </li>
                            <li v-for="doc in existingDocs" :key="`saved-${doc.id}`" @click="openRemotePreview(doc.url, doc.original_name || doc.label || doc.doc_type)" class="grid cursor-pointer grid-cols-[minmax(0,1fr)_7rem_2.5rem] items-center gap-3 px-3 py-2.5 transition-colors hover:bg-slate-50 dark:hover:bg-zinc-800">
                                <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ doc.label || doc.original_name || doc.doc_type }}</p><p v-if="doc.label && doc.original_name && doc.label !== doc.original_name" class="truncate text-xs text-slate-500">{{ doc.original_name }}</p><p v-if="doc.description" class="truncate text-xs text-slate-500">{{ doc.description }}</p><p v-if="doc.review_note" class="mt-1 text-xs font-semibold text-rose-700 dark:text-rose-300">HR note: {{ doc.review_note }}</p></div>
                                <span class="justify-self-center rounded-full border px-2 py-0.5 text-center text-[10px] font-bold" :class="docStatusCls(doc.status)">{{ documentStatus(doc) }}</span>
                                <button type="button" @click.stop="deleteUploadedDocument(doc)" :disabled="deletingDocId === doc.id" title="Remove document" aria-label="Remove document" class="justify-self-end rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 hover:text-rose-800 disabled:opacity-40 dark:hover:bg-rose-900/20"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6h18m-2 0-.9 14H5.9L5 6m4 0V4h6v2m-5 4v6m4-6v6" /></svg></button>
                            </li>
                            <li v-for="(doc, i) in pendingDocuments" :key="`pending-${i}-${doc.name}`" @click="doc.file && openLocalPreview(doc.file)" class="grid cursor-pointer grid-cols-[minmax(0,1fr)_7rem_2.5rem] items-center gap-3 px-3 py-2.5 transition-colors hover:bg-slate-50 dark:hover:bg-zinc-800">
                                <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ doc.name }}</p><p class="truncate text-xs text-slate-500">{{ doc.file?.name }} · Pending upload</p><p v-if="doc.description" class="truncate text-xs text-slate-500">{{ doc.description }}</p></div>
                                <button type="button" @click.stop="removePendingDocument(i)" title="Remove document" aria-label="Remove document" class="shrink-0 rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 hover:text-rose-800 dark:hover:bg-rose-900/20"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6h18m-2 0-.9 14H5.9L5 6m4 0V4h6v2m-5 4v6m4-6v6" /></svg></button>
                            </li>
                            <li v-if="!existingDocs.length && !pendingDocuments.length && !form.resume" class="px-3 py-6 text-center text-xs text-slate-400">No documents added yet.</li>
                        </ul>
                        <div v-if="showAddDocument" class="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900 dark:bg-amber-950/20">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div><label :class="labelCls">Document Type</label><select v-model="newDocument.type" :class="inputCls"><option v-for="[key, label] in documentTypeOptions" :key="key" :value="key">{{ label }}</option></select></div>
                                <div><label :class="labelCls">Document Name</label><input v-model="newDocument.name" type="text" maxlength="255" placeholder="e.g. Valid ID" :class="inputCls" /></div>
                                <div><label :class="labelCls">Upload File</label><input ref="documentInput" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" :class="[inputCls, 'file:mr-3 file:rounded-lg file:border-0 file:bg-amber-600 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white file:transition-all file:duration-300 hover:file:bg-amber-700']" @change="newDocument.file = ($event.target as HTMLInputElement).files?.[0] || null" /></div>
                                <div><label :class="labelCls">Description <span class="font-normal text-slate-400">(Optional)</span></label><textarea v-model="newDocument.description" rows="2" maxlength="1000" placeholder="Add details about this document" :class="inputCls"></textarea></div>
                            </div>
                            <div class="mt-3 flex justify-end gap-2"><button type="button" @click="showAddDocument = false" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-white dark:border-slate-600 dark:text-slate-300 dark:hover:bg-zinc-800">Cancel</button><button type="button" @click="addDocument" :disabled="!newDocument.type || !newDocument.name.trim() || !newDocument.file" class="rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50">Upload</button></div>
                        </div>
                    </div>
                    <p v-if="docsError()" class="sm:col-span-2 -mt-2 text-xs text-rose-600">{{ docsError() }}</p>
                </div>
            </div>

            <!-- Review all worker details before consent and submission -->
            <section v-show="step === 4" :class="sectionCls">
                <div class="mb-5 border-b border-slate-100 pb-4 dark:border-slate-700/60">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Review Worker Information</h2>
                    <p class="mt-1 text-[11px] text-slate-400">Check the details below before submitting.</p>
                </div>
                <div class="space-y-4">
                    <section v-for="group in reviewSections" :key="group.title" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <h3 class="mb-3 text-xs font-bold text-slate-700 dark:text-slate-200">{{ group.title }}</h3>
                        <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            <div v-for="field in group.fields" :key="field[0]" class="min-w-0">
                                <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-400">{{ field[0] }}</dt>
                                <dd class="mt-0.5 whitespace-pre-wrap break-words text-xs font-semibold text-slate-800 dark:text-slate-200">{{ reviewValue(field[1]) }}</dd>
                            </div>
                        </dl>
                    </section>
                    <section class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <h3 class="mb-3 text-xs font-bold text-slate-700 dark:text-slate-200">Supporting Documents</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[540px] text-left text-xs">
                                <thead><tr class="border-b border-slate-200 text-[10px] uppercase tracking-wide text-slate-400 dark:border-slate-700"><th class="px-3 py-2 font-semibold">Document</th><th class="px-3 py-2 font-semibold">File</th><th class="px-3 py-2 font-semibold">Status</th></tr></thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-if="form.resume" tabindex="0" @click="openLocalPreview(form.resume)" @keydown.enter="openLocalPreview(form.resume)" class="cursor-pointer transition-colors hover:bg-slate-50 focus:bg-slate-50 dark:hover:bg-zinc-800 dark:focus:bg-zinc-800"><td class="px-3 py-2.5 font-semibold text-slate-800 dark:text-slate-200">Resume / CV</td><td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ form.resume.name }}</td><td class="px-3 py-2.5 text-amber-700">Pending upload</td></tr>
                                    <tr v-for="doc in existingDocs" :key="`review-saved-${doc.id}`" tabindex="0" @click="openRemotePreview(doc.url, doc.original_name || doc.label || doc.doc_type)" @keydown.enter="openRemotePreview(doc.url, doc.original_name || doc.label || doc.doc_type)" class="cursor-pointer transition-colors hover:bg-slate-50 focus:bg-slate-50 dark:hover:bg-zinc-800 dark:focus:bg-zinc-800"><td class="px-3 py-2.5"><p class="font-semibold text-slate-800 dark:text-slate-200">{{ doc.label || doc.original_name || doc.doc_type }}</p><p v-if="doc.description" class="mt-0.5 text-[11px] text-slate-500">{{ doc.description }}</p></td><td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ doc.original_name || '—' }}</td><td class="px-3 py-2.5 text-slate-500">{{ doc.status }}</td></tr>
                                    <tr v-for="(doc, i) in pendingDocuments" :key="`review-pending-${i}`" tabindex="0" @click="doc.file && openLocalPreview(doc.file)" @keydown.enter="doc.file && openLocalPreview(doc.file)" class="cursor-pointer transition-colors hover:bg-slate-50 focus:bg-slate-50 dark:hover:bg-zinc-800 dark:focus:bg-zinc-800"><td class="px-3 py-2.5"><p class="font-semibold text-slate-800 dark:text-slate-200">{{ doc.name }}</p><p v-if="doc.description" class="mt-0.5 text-[11px] text-slate-500">{{ doc.description }}</p></td><td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ doc.file?.name }}</td><td class="px-3 py-2.5 text-amber-700">Pending upload</td></tr>
                                    <tr v-if="!existingDocs.length && !pendingDocuments.length && !form.resume"><td colspan="3" class="px-3 py-6 text-center text-slate-400">No supporting documents added.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>

            <!-- Submission and Consent -->
            <div v-show="step === 4" :class="sectionCls">
                <div class="flex items-start gap-3 mb-6 pb-5 border-b border-slate-100 dark:border-slate-700/60">
                    <span :class="sectionIconCls">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622C17.176 19.29 21 14.591 21 9c0-1.042-.133-2.052-.382-3.016z" /></svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Submission and Consent <span class="text-rose-500">*</span></h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Initial status: Pending HR Approval.</p>
                    </div>
                </div>
                <label class="flex items-start gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-600 transition cursor-pointer">
                    <input v-model="form.consent_given" type="checkbox" :true-value="true" :false-value="false" class="mt-0.5 w-4 h-4 rounded accent-indigo-600" />
                    <span class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        I confirm that the agency is authorized to submit this worker's information and supporting files,
                        and that the worker has been informed of the privacy notice.
                    </span>
                </label>
                <p v-if="err('consent_given')" :class="errCls">{{ err('consent_given') }}</p>
            </div>

            <div v-if="stepErrors.length" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-900/60 p-4" @click.self="stepErrors = []">
                <section role="alertdialog" aria-modal="true" aria-labelledby="step-error-title" class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800">
                    <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                        <h2 id="step-error-title" class="text-sm font-black text-slate-900 dark:text-white">Please complete before continuing</h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-amber-700 dark:text-amber-300">
                            <li v-for="e in stepErrors" :key="e">{{ e }}</li>
                        </ul>
                    </div>
                    <div class="flex justify-end px-5 py-4">
                        <button type="button" @click="stepErrors = []" class="rounded-xl bg-slate-800 px-5 py-2 text-xs font-bold text-white transition hover:bg-slate-700">OK</button>
                    </div>
                </section>
            </div>

            <div class="flex items-center justify-between gap-3 pb-8 pt-2">
                <button v-if="step > 0" type="button" @click="prevStep"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-700 hover:-translate-x-0.5 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    Back
                </button>
                <span v-else class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Step {{ step + 1 }} of {{ steps.length }}
                </span>

                <div class="flex items-center gap-2">
                    <button type="button" @click="showCancelConfirm = true" class="px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-sm">Cancel</button>
                    <button v-if="step < maxStep" type="button" @click="nextStep"
                        class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm font-bold shadow-md hover:translate-x-0.5 transition">
                        Next
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>
                    <button v-else-if="!(isEdit && props.worker?.status === 'For Correction')" type="submit" :disabled="form.processing"
                        class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-sm font-bold shadow-md disabled:opacity-60 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        {{ form.processing ? 'Saving...' : isEdit ? 'Save changes' : 'Submit' }}
                    </button>
                    <button v-if="isEdit && props.worker?.status === 'For Correction' && step === maxStep" type="button" @click="openSaveConfirm" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-orange-600 px-5 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-orange-700 disabled:opacity-60">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" /></svg>
                        Send corrections to Monti
                    </button>
                    <button v-if="isEdit && props.worker?.status !== 'For Correction'" type="button" @click="openSaveConfirm" :disabled="form.processing" class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold transition shadow-sm disabled:opacity-60">Finish</button>
                </div>
            </div>
        </form>

        <div v-if="showDuplicateWarning && duplicateMatch" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-900/60 p-4" @click.self="showDuplicateWarning = false">
            <section role="alertdialog" aria-modal="true" aria-labelledby="duplicate-worker-title" class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800">
                <div class="border-b border-amber-100 px-5 py-4 dark:border-amber-900/50">
                    <h2 id="duplicate-worker-title" class="text-sm font-black text-slate-900 dark:text-white">Possible duplicate worker</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ duplicateMatch.reason }} with an existing worker in your agency.</p>
                    <div class="mt-3 rounded-xl bg-amber-50 p-3 text-xs dark:bg-amber-950/30">
                        <p class="font-bold text-slate-800 dark:text-slate-200">{{ duplicateMatch.name }}</p>
                        <p class="mt-0.5 text-slate-600 dark:text-slate-400">{{ duplicateMatch.code }}<span v-if="duplicateMatch.birth_date"> · Birth date {{ duplicateMatch.birth_date }}</span></p>
                    </div>
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Check the information before continuing to avoid creating a duplicate record.</p>
                </div>
                <div class="flex justify-end gap-2 px-5 py-4">
                    <button type="button" @click="showDuplicateWarning = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Review details</button>
                    <button type="button" @click="continueWithPossibleDuplicate" class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-amber-700">Continue anyway</button>
                </div>
            </section>
        </div>

        <!-- Submission confirmation -->
        <div v-if="showSaveConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" @click.self="showSaveConfirm = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                    <h2 class="text-sm font-black text-slate-900 dark:text-white">{{ isEdit && props.worker?.status === 'For Correction' ? 'Send corrections to Monti?' : isEdit ? 'Save worker changes?' : 'Submit this worker?' }}</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ isEdit && props.worker?.status === 'For Correction' ? 'Your updated worker details will be sent to HR for review. Any assignment preview will use the latest saved details.' : isEdit ? 'Confirm that you want to save these changes.' : 'Confirm that you want to submit this worker for HR approval.' }}</p>
                </div>
                <div class="flex justify-end gap-2 px-5 py-4">
                    <button type="button" @click="showSaveConfirm = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Back</button>
                    <button type="button" @click="confirmSave" :disabled="form.processing" class="rounded-xl bg-slate-800 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-700 disabled:opacity-50">{{ form.processing ? 'Saving...' : (isEdit && props.worker?.status === 'For Correction' ? 'Send corrections' : 'Confirm') }}</button>
                </div>
            </div>
        </div>

        <div v-if="form.processing" class="fixed inset-0 z-[130] flex items-center justify-center bg-slate-900/60 p-4" role="status" aria-live="polite">
            <section class="flex w-full max-w-sm flex-col items-center rounded-2xl bg-white px-8 py-7 text-center shadow-2xl dark:bg-slate-800">
                <svg class="h-10 w-10 animate-spin text-amber-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0110 10h-4a6 6 0 00-6-6V2z" /></svg>
                <h2 class="mt-4 text-sm font-black text-slate-900 dark:text-white">{{ isEdit ? 'Saving worker changes…' : 'Submitting worker…' }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Please wait while the worker information is saved.</p>
            </section>
        </div>

        <div v-if="showCancelConfirm" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/60 p-4" @click.self="showCancelConfirm = false">
            <section role="alertdialog" aria-modal="true" aria-labelledby="discard-worker-title" class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                    <h2 id="discard-worker-title" class="text-sm font-black text-slate-900 dark:text-white">Discard worker form?</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">All information and files you entered will be lost.</p>
                </div>
                <div class="flex justify-end gap-2 px-5 py-4">
                    <button type="button" @click="showCancelConfirm = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Keep editing</button>
                    <button type="button" @click="discardForm" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-rose-700">Discard data</button>
                </div>
            </section>
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
