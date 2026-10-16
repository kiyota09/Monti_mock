<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { User as UserIcon, ShieldCheck, BadgeCheck, CalendarDays, Briefcase, IdCard, Images, GraduationCap, Users, Phone, MapPin, X, Eye, Download, Pencil, Expand } from 'lucide-vue-next';

const props = defineProps<{
    mustVerifyEmail?: Boolean;
    status?: String;
    application?: Record<string, any> | null;
}>();

const user = computed(() => usePage().props.auth.user);

const photoUrl = computed(() =>
    user.value?.profile_photo_path ? `/storage/${user.value.profile_photo_path}` : null
);
const initial = computed(() => (user.value?.name || '?').charAt(0).toUpperCase());
const verified = computed(() => !!user.value?.email_verified_at);
const active = computed(() => user.value?.is_active !== false);

const fmtDate = (d?: string | null) =>
    d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';

const details = computed(() => [
    { label: 'Employee ID', value: user.value?.employee_id || '—', mono: true },
    { label: 'Email', value: user.value?.email || '—' },
    { label: 'Role', value: user.value?.role || '—', badge: true },
    { label: 'Position', value: (user.value?.position || '—').replace(/_/g, ' ') },
    { label: 'Department', value: user.value?.department_name || user.value?.hrmDepartment?.name || user.value?.department || '—' },
    { label: 'Manufacturing role', value: (user.value?.manufacturing_role || '—').replace(/_/g, ' ') },
    {
        label: 'Supervisor seat',
        value: user.value?.is_manufacturing_supervisor
            ? `Supervisor · ${user.value?.supervisor_department || ''}`
            : '—',
        badge: !!user.value?.is_manufacturing_supervisor,
    },
    { label: 'Join date', value: fmtDate(user.value?.join_date) },
    { label: 'Member since', value: fmtDate(user.value?.created_at) },
]);

// ─── Hiring application (filled up during the hiring process) ─────────────
const application = computed(() => props.application || null);

const pretty = (v: any): string => {
    if (v === null || v === undefined || v === '') return '—';
    if (typeof v === 'boolean') return v ? 'Yes' : 'No';
    if (Array.isArray(v)) {
        if (!v.length) return '—';
        return v.map((i) => (i && typeof i === 'object'
            ? Object.values(i).filter((x) => x !== null && x !== undefined && x !== '').join(' · ')
            : String(i))).join('; ');
    }
    return String(v);
};
const personalTiles = computed(() => {
    const p = application.value?.personal || {};
    return [
        { label: 'First name', value: pretty(p.first_name) },
        { label: 'Middle name', value: pretty(p.middle_name) },
        { label: 'Last name', value: pretty(p.last_name) },
        { label: 'Suffix', value: pretty(p.suffix) },
        { label: 'Date of birth', value: fmtDate(p.date_of_birth) },
        { label: 'Place of birth', value: pretty(p.place_of_birth) },
        { label: 'Citizenship', value: pretty(p.citizenship) },
        { label: 'Age', value: pretty(p.age) },
        { label: 'Sex', value: pretty(p.sex) },
        { label: 'Civil status', value: pretty(p.civil_status) },
        { label: 'Weight (kg)', value: pretty(p.weight) },
        { label: 'Height (cm)', value: pretty(p.height) },
        { label: 'Religion', value: pretty(p.religion) },
        { label: 'Languages', value: pretty(p.languages) },
        { label: 'Email', value: pretty(p.email) },
        { label: 'Phone', value: pretty(p.phone_number) },
        { label: 'Contact no.', value: pretty(p.contact_number) },
    ];
});

const addressTiles = computed(() => {
    const a = application.value?.address || {};
    return [
        { label: 'Street address', value: pretty(a.street_address) },
        { label: 'Address line 2', value: pretty(a.street_address_line2) },
        { label: 'Barangay', value: pretty(a.barangay) },
        { label: 'City', value: pretty(a.city) },
        { label: 'Province/State', value: pretty(a.state_province) },
        { label: 'ZIP code', value: pretty(a.postal_zip_code) },
    ];
});

const familyTiles = computed(() => {
    const f = application.value?.family || {};
    return [
        { label: 'Spouse name', value: pretty(f.spouse_name) },
        { label: 'Spouse occupation', value: pretty(f.spouse_occupation) },
        { label: 'Spouse address', value: pretty(f.spouse_address) },
        { label: 'No. of children', value: pretty(f.number_of_children) },
        { label: 'Mother name', value: pretty(f.mother_name) },
        { label: 'Mother address', value: pretty(f.mother_address) },
        { label: 'Father name', value: pretty(f.father_name) },
        { label: 'Father address', value: pretty(f.father_address) },
        { label: 'Related employees', value: pretty(f.related_employees) },
    ];
});

const emergencyTiles = computed(() => {
    const e = application.value?.emergency || {};
    return [
        { label: 'Contact name', value: pretty(e.name) },
        { label: 'Relationship', value: pretty(e.relationship) },
        { label: 'Phone', value: pretty(e.phone) },
        { label: 'Address', value: pretty(e.address) },
    ];
});

const educationTiles = computed(() => {
    const e = application.value?.education || {};
    return [
        { label: 'Elementary', value: [pretty(e.elementary_school), pretty(e.elementary_year)].filter((x) => x !== '—').join(' · ') || '—' },
        { label: 'High school', value: [pretty(e.high_school), pretty(e.high_year)].filter((x) => x !== '—').join(' · ') || '—' },
        { label: 'College', value: [pretty(e.college), pretty(e.college_year)].filter((x) => x !== '—').join(' · ') || '—' },
        { label: 'Vocational', value: [pretty(e.vocational), pretty(e.vocational_year)].filter((x) => x !== '—').join(' · ') || '—' },
        { label: 'Highest education', value: pretty(e.highest_education) },
        { label: 'School', value: pretty(e.school) },
        { label: 'Course', value: pretty(e.course) },
        { label: 'Graduation year', value: pretty(e.graduation_year) },
        { label: 'Special skills', value: pretty(e.special_skills) },
        { label: 'Machine operation', value: pretty(e.machine_operation) },
    ];
});

const workTiles = computed(() => {
    const e = application.value?.employment || {};
    return [
        { label: 'Has employment record', value: pretty(e.has_employment_record) },
        { label: 'Previous company', value: pretty(e.previous_employment_company) },
        { label: 'When', value: pretty(e.previous_employment_when) },
        { label: 'Previous position', value: pretty(e.previous_employment_position) },
        { label: 'Previous department', value: pretty(e.previous_employment_department) },
        { label: 'Referred by', value: pretty(e.referred_by) },
        { label: 'Referrer address', value: pretty(e.referred_by_address) },
    ];
});

// ID card image viewer
const lightbox = ref<{ url: string; label: string } | null>(null);
const openLightbox = (url: string, label: string) => { lightbox.value = { url, label }; };
const closeLightbox = () => { lightbox.value = null; };

// Large profile photo preview
const showPhotoPreview = ref(false);
const openPhotoPreview = () => { showPhotoPreview.value = true; };
const closePhotoPreview = () => { showPhotoPreview.value = false; };

// ─── Self-service edit of application personal details ────────────────────
// Employees keep these current themselves (moved address, newly issued
// government IDs, changed civil status / emergency contact…). Names,
// birth details and hiring-process fields stay HR-owned and read-only.
const showEditApp = ref(false);
const appTab = ref('personal');
const appTabs = [
    { key: 'personal', label: 'Personal', icon: UserIcon },
    { key: 'ids', label: 'IDs', icon: IdCard },
    { key: 'emergency', label: 'Emergency', icon: Phone },
    { key: 'family', label: 'Family', icon: Users },
    { key: 'education', label: 'Education', icon: GraduationCap },
    { key: 'work', label: 'Work', icon: Briefcase },
];
const appForm = useForm<Record<string, any>>({
    first_name: '', middle_name: '', last_name: '', suffix: '',
    date_of_birth: '', place_of_birth: '', citizenship: '', age: '', sex: '',
    contact_number: '', phone_number: '', civil_status: '', religion: '', languages: '',
    weight: '', height: '',
    street_address: '', street_address_line2: '', barangay: '', city: '', state_province: '', postal_zip_code: '',
    spouse_name: '', spouse_occupation: '', spouse_address: '',
    number_of_children: '', children: [] as any[],
    mother_name: '', mother_address: '', father_name: '', father_address: '',
    emergency_name: '', emergency_relationship: '', emergency_phone: '', emergency_address: '',
    elementary_school: '', elementary_year: '', high_school: '', high_year: '',
    college: '', college_year: '', vocational: '', vocational_year: '',
    highest_education: '', school: '', course: '', graduation_year: '',
    special_skills: '', machine_operation: '',
    has_employment_record: false, employment_records: [] as any[],
    previous_employment_company: '', previous_employment_when: '',
    previous_employment_position: '', previous_employment_department: '',
    referred_by: '', referred_by_address: '',
    sss_number: '', philhealth_number: '', pagibig_number: '',
    sss_file: null, philhealth_file: null, pagibig_file: null,
});
const idPicked = ref<Record<string, string>>({ sss_file: '', philhealth_file: '', pagibig_file: '' });

// Normalize stored rows (strings from older records) into the row shapes
// the dynamic form binds to, so nothing already saved is lost on edit.
const asChildRows = (v: any): any[] => {
    if (!Array.isArray(v) || !v.length) return [];
    return v.map((c) => (c && typeof c === 'object'
        ? { name: c.name || '', dob: c.dob || c.birthdate || c.birthday || '' }
        : { name: String(c), dob: '' }));
};
const asWorkRows = (v: any): any[] => {
    if (!Array.isArray(v) || !v.length) return [];
    return v.map((r) => (r && typeof r === 'object'
        ? { company: r.company || '', years: r.years || '', salary: r.salary || '', position: r.position || '', reason: r.reason || '' }
        : { company: String(r), years: '', salary: '', position: '', reason: '' }));
};

const openEditApp = (tab = 'personal') => {
    const a = application.value;
    if (!a) return;
    const p = a.personal || {};
    const ad = a.address || {};
    const f = a.family || {};
    const e = a.emergency || {};
    const ed = a.education || {};
    const w = a.employment || {};
    const nums: Record<string, string> = {};
    (a.government_ids || []).forEach((g: any) => { nums[`${g.key}_number`] = g.number || ''; });
    appForm.first_name = p.first_name || '';
    appForm.middle_name = p.middle_name || '';
    appForm.last_name = p.last_name || '';
    appForm.suffix = p.suffix || '';
    appForm.date_of_birth = p.date_of_birth || '';
    appForm.place_of_birth = p.place_of_birth || '';
    appForm.citizenship = p.citizenship || '';
    appForm.age = p.age ?? '';
    appForm.sex = p.sex || '';
    appForm.contact_number = p.contact_number || '';
    appForm.phone_number = p.phone_number || '';
    appForm.civil_status = p.civil_status || '';
    appForm.religion = p.religion || '';
    appForm.languages = p.languages || '';
    appForm.weight = p.weight ?? '';
    appForm.height = p.height ?? '';
    appForm.street_address = ad.street_address || '';
    appForm.street_address_line2 = ad.street_address_line2 || '';
    appForm.barangay = ad.barangay || '';
    appForm.city = ad.city || '';
    appForm.state_province = ad.state_province || '';
    appForm.postal_zip_code = ad.postal_zip_code || '';
    appForm.spouse_name = f.spouse_name || '';
    appForm.spouse_occupation = f.spouse_occupation || '';
    appForm.spouse_address = f.spouse_address || '';
    appForm.number_of_children = f.number_of_children ?? '';
    appForm.children = asChildRows(f.children);
    appForm.mother_name = f.mother_name || '';
    appForm.mother_address = f.mother_address || '';
    appForm.father_name = f.father_name || '';
    appForm.father_address = f.father_address || '';
    appForm.emergency_name = e.name || '';
    appForm.emergency_relationship = e.relationship || '';
    appForm.emergency_phone = e.phone || '';
    appForm.emergency_address = e.address || '';
    appForm.elementary_school = ed.elementary_school || '';
    appForm.elementary_year = ed.elementary_year || '';
    appForm.high_school = ed.high_school || '';
    appForm.high_year = ed.high_year || '';
    appForm.college = ed.college || '';
    appForm.college_year = ed.college_year || '';
    appForm.vocational = ed.vocational || '';
    appForm.vocational_year = ed.vocational_year || '';
    appForm.highest_education = ed.highest_education || '';
    appForm.school = ed.school || '';
    appForm.course = ed.course || '';
    appForm.graduation_year = ed.graduation_year || '';
    appForm.special_skills = ed.special_skills || '';
    appForm.machine_operation = ed.machine_operation || '';
    appForm.has_employment_record = !!w.has_employment_record;
    appForm.employment_records = asWorkRows(w.employment_records);
    appForm.previous_employment_company = w.previous_employment_company || '';
    appForm.previous_employment_when = w.previous_employment_when || '';
    appForm.previous_employment_position = w.previous_employment_position || '';
    appForm.previous_employment_department = w.previous_employment_department || '';
    appForm.referred_by = w.referred_by || '';
    appForm.referred_by_address = w.referred_by_address || '';
    appForm.sss_number = nums.sss_number || '';
    appForm.philhealth_number = nums.philhealth_number || '';
    appForm.pagibig_number = nums.pagibig_number || '';
    appForm.sss_file = null;
    appForm.philhealth_file = null;
    appForm.pagibig_file = null;
    idPicked.value = { sss_file: '', philhealth_file: '', pagibig_file: '' };
    appForm.clearErrors();
    appTab.value = tab;
    showEditApp.value = true;
};

const onIdFile = (e: Event, field: string) => {
    const file = (e.target as HTMLInputElement).files?.[0] || null;
    (appForm as any)[field] = file;
    idPicked.value[field] = file?.name || '';
};

const submitAppForm = () => {
    appForm.post(route('profile.application.update'), {
        preserveScroll: true,
        onSuccess: () => { showEditApp.value = false; },
    });
};
</script>

<template>
    <Head title="My Profile" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="mx-auto max-w-5xl space-y-5 p-4 pb-16 sm:p-6">

                <!-- Hero -->
                <div class="relative overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-xl sm:p-7">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800" />
                    <div class="absolute -top-20 -right-16 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="relative shrink-0">
                            <button type="button" @click="openPhotoPreview" title="Preview profile photo"
                                class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-3xl bg-white/15 text-4xl font-black ring-1 ring-white/30 transition hover:ring-2 hover:ring-white/60 active:scale-95">
                                <img v-if="photoUrl" :src="photoUrl" alt="Profile photo" class="h-full w-full object-cover" />
                                <span v-else>{{ initial }}</span>
                            </button>
                            <button type="button" @click="openPhotoPreview" title="Preview larger"
                                class="absolute -bottom-2 -right-2 flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-white shadow-lg ring-2 ring-white transition hover:bg-indigo-500 active:scale-95">
                                <Expand class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <UserIcon class="h-3.5 w-3.5" /> My Profile
                            </p>
                            <h1 class="mt-0.5 truncate text-2xl font-extrabold tracking-tight sm:text-3xl">{{ user?.name }}</h1>
                            <p class="mt-0.5 truncate text-sm text-blue-100/90">{{ user?.email }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-[11px] font-black uppercase">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25">{{ user?.role }} · {{ (user?.position || '').replace(/_/g, ' ') }}</span>
                            <span :class="['rounded-full px-3 py-1.5', active ? 'bg-emerald-400/90 text-emerald-950' : 'bg-rose-400/90 text-rose-950']">
                                {{ active ? 'Active' : 'Inactive' }}
                            </span>
                            <span v-if="verified" class="inline-flex items-center gap-1 rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/25">
                                <BadgeCheck class="h-3.5 w-3.5" /> Verified
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Full details -->
                <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                    <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                        <Briefcase class="h-4 w-4 text-indigo-500" /> Account details
                    </h2>
                    <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="d in details" :key="d.label"
                            class="rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ d.label }}</dt>
                            <dd class="mt-1 truncate text-sm font-black text-slate-800 dark:text-zinc-100"
                                :class="[{ 'font-mono': d.mono }, d.badge && 'inline-block rounded-full bg-indigo-100 px-2.5 py-0.5 text-[11px] uppercase text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300']">
                                {{ d.value }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Personal information from the application -->
                <div v-if="application" class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                    <div class="flex items-center gap-2">
                        <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                            <UserIcon class="h-4 w-4 text-indigo-500" /> Personal information
                        </h2>
                        <button @click="openEditApp('personal')" title="Update personal information"
                            class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black uppercase text-white shadow hover:bg-indigo-500 active:scale-95">
                            <Pencil class="h-3.5 w-3.5" /> Update
                        </button>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">As filled up in the job application form. Moved house or got a new ID? Keep it current here.</p>
                    <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="d in personalTiles" :key="d.label"
                            class="rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ d.label }}</dt>
                            <dd class="mt-1 truncate text-sm font-black text-slate-800 dark:text-zinc-100">{{ d.value }}</dd>
                        </div>
                    </dl>
                    <h3 class="mt-5 flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                        <MapPin class="h-4 w-4 text-indigo-500" /> Home address
                    </h3>
                    <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="d in addressTiles" :key="d.label"
                            class="rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ d.label }}</dt>
                            <dd class="mt-1 truncate text-sm font-black text-slate-800 dark:text-zinc-100">{{ d.value }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Government ID cards submitted during application -->
                <div v-if="application" class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                    <div class="flex items-center gap-2">
                        <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                            <IdCard class="h-4 w-4 text-indigo-500" /> Government ID cards
                        </h2>
                        <button @click="openEditApp('ids')" title="Add or replace ID cards"
                            class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black uppercase text-white shadow hover:bg-indigo-500 active:scale-95">
                            <Pencil class="h-3.5 w-3.5" /> Update IDs
                        </button>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">Card images passed during the application. Click an image to enlarge.</p>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div v-for="idc in application.government_ids" :key="idc.key"
                            class="overflow-hidden rounded-2xl border border-slate-100 bg-slate-50/60 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <div class="flex items-center justify-between px-4 pt-3">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ idc.label }}</p>
                                <p class="font-mono text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ idc.number || '—' }}</p>
                            </div>
                            <div class="p-3">
                                <button v-if="idc.file?.is_image" type="button" @click="openLightbox(idc.file.url, `${idc.label} ID`)"
                                    class="group relative block h-44 w-full overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-700" :title="`Enlarge ${idc.label} ID`">
                                    <img :src="idc.file.url" :alt="`${idc.label} ID`" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy" />
                                    <span class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 bg-black/50 py-1 text-[10px] font-black uppercase text-white opacity-0 transition group-hover:opacity-100">
                                        <Eye class="h-3 w-3" /> Enlarge
                                    </span>
                                </button>
                                <a v-else-if="idc.file" :href="idc.file.url" target="_blank" rel="noopener"
                                    class="flex h-44 w-full flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-indigo-600 hover:bg-indigo-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800">
                                    <Download class="h-6 w-6" /> View {{ idc.label }} file
                                </a>
                                <div v-else class="flex h-44 w-full flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 text-xs font-bold text-slate-400 dark:border-zinc-700">
                                    <IdCard class="h-6 w-6" /> No file submitted
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Family & emergency contact -->
                <div v-if="application" class="grid gap-5 lg:grid-cols-2">
                    <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                        <div class="flex items-center gap-2">
                            <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                                <Users class="h-4 w-4 text-indigo-500" /> Family background
                            </h2>
                            <button @click="openEditApp('family')" title="Update family background"
                                class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black uppercase text-white shadow hover:bg-indigo-500 active:scale-95">
                                <Pencil class="h-3.5 w-3.5" /> Update
                            </button>
                        </div>
                        <dl class="mt-4 space-y-3">
                            <div v-for="d in familyTiles" :key="d.label"
                                class="rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ d.label }}</dt>
                                <dd class="mt-1 text-sm font-black text-slate-800 dark:text-zinc-100">{{ d.value }}</dd>
                            </div>
                        </dl>
                        <div v-if="application.family?.children?.length" class="mt-3 rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Children details</p>
                            <ul class="mt-1 space-y-1 text-sm font-bold text-slate-700 dark:text-slate-200">
                                <li v-for="(c, i) in application.family.children" :key="i">• {{ pretty(c) }}</li>
                            </ul>
                        </div>
                    </div>
                    <div class="space-y-5">
                        <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                            <div class="flex items-center gap-2">
                                <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                                    <Phone class="h-4 w-4 text-indigo-500" /> Emergency contact
                                </h2>
                                <button @click="openEditApp('emergency')" title="Update emergency contact"
                                    class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black uppercase text-white shadow hover:bg-indigo-500 active:scale-95">
                                    <Pencil class="h-3.5 w-3.5" /> Update
                                </button>
                            </div>
                            <dl class="mt-4 space-y-3">
                                <div v-for="d in emergencyTiles" :key="d.label"
                                    class="rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                                    <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ d.label }}</dt>
                                    <dd class="mt-1 text-sm font-black text-slate-800 dark:text-zinc-100">{{ d.value }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div v-if="application.documents?.length" class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                            <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                                <Images class="h-4 w-4 text-indigo-500" /> Application documents ({{ application.documents.length }})
                            </h2>
                            <ul class="mt-4 space-y-2">
                                <li v-for="doc in application.documents" :key="doc.id"
                                    class="flex items-center gap-3 rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-800/50">
                                    <button v-if="doc.file?.is_image" type="button" @click="openLightbox(doc.file.url, doc.original_name || doc.type)"
                                        class="h-11 w-11 shrink-0 overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-700" title="Enlarge">
                                        <img :src="doc.file.url" alt="" class="h-full w-full object-cover" loading="lazy" />
                                    </button>
                                    <span v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-500 dark:bg-indigo-900/30">
                                        <Download class="h-5 w-5" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-black text-slate-800 dark:text-zinc-100">{{ doc.original_name || doc.type }}</span>
                                        <span class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">{{ doc.type }} {{ doc.verified ? '· Verified' : '' }}</span>
                                    </span>
                                    <a v-if="doc.file" :href="doc.file.url" target="_blank" rel="noopener"
                                        class="shrink-0 rounded-xl p-2 text-indigo-500 hover:bg-indigo-50 dark:hover:bg-zinc-800" title="Open file">
                                        <Eye class="h-4 w-4" />
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Education & work history -->
                <div v-if="application" class="grid gap-5 lg:grid-cols-2">
                    <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                        <div class="flex items-center gap-2">
                            <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                                <GraduationCap class="h-4 w-4 text-indigo-500" /> Education
                            </h2>
                            <button @click="openEditApp('education')" title="Update education"
                                class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black uppercase text-white shadow hover:bg-indigo-500 active:scale-95">
                                <Pencil class="h-3.5 w-3.5" /> Update
                            </button>
                        </div>
                        <dl class="mt-4 space-y-3">
                            <div v-for="d in educationTiles" :key="d.label"
                                class="rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ d.label }}</dt>
                                <dd class="mt-1 text-sm font-black text-slate-800 dark:text-zinc-100">{{ d.value }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                        <div class="flex items-center gap-2">
                            <h2 class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">
                                <Briefcase class="h-4 w-4 text-indigo-500" /> Work history
                            </h2>
                            <button @click="openEditApp('work')" title="Update work history"
                                class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-[11px] font-black uppercase text-white shadow hover:bg-indigo-500 active:scale-95">
                                <Pencil class="h-3.5 w-3.5" /> Update
                            </button>
                        </div>
                        <dl class="mt-4 space-y-3">
                            <div v-for="d in workTiles" :key="d.label"
                                class="rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ d.label }}</dt>
                                <dd class="mt-1 text-sm font-black text-slate-800 dark:text-zinc-100">{{ d.value }}</dd>
                            </div>
                        </dl>
                        <div v-if="application.employment?.employment_records?.length" class="mt-3 space-y-2">
                            <div v-for="(r, i) in application.employment.employment_records" :key="i"
                                class="rounded-2xl border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-sm font-bold text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-900/10 dark:text-indigo-200">
                                {{ pretty(r) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-3xl border border-dashed border-gray-200 bg-white/60 p-5 text-center text-xs font-bold text-slate-400 dark:border-zinc-800 dark:bg-zinc-900/50">
                    No hiring application is linked to this account yet — personal details from the hiring process will appear here once HR links it.
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <!-- Edit -->
                    <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                        <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
                    </div>
                    <div class="space-y-5">
                        <!-- Security -->
                        <div class="rounded-3xl border border-gray-100 bg-white/80 p-5 shadow-sm backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80 sm:p-6">
                            <UpdatePasswordForm />
                        </div>
                        <!-- Account status policy -->
                        <div class="rounded-3xl border border-indigo-100 bg-indigo-50/60 p-5 dark:border-indigo-900/40 dark:bg-indigo-900/10 sm:p-6">
                            <h2 class="flex items-center gap-2 text-sm font-black text-indigo-900 dark:text-indigo-200">
                                <ShieldCheck class="h-4 w-4" /> Account status
                            </h2>
                            <p class="mt-2 flex items-start gap-2 text-xs leading-relaxed text-indigo-800/80 dark:text-indigo-200/70">
                                <CalendarDays class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                Accounts cannot be deleted by their owners. Only the IT department can suspend or
                                disable an account — please contact your IT administrator if your access needs to change.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Update personal information (tabbed sections, teleported to body so
             ancestor filters can never push it off-screen) -->
        <Teleport to="body">
        <Transition name="modal">
            <div v-if="showEditApp" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showEditApp = false">
                <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                    <div class="flex shrink-0 items-center gap-2 border-b border-gray-100 bg-white/95 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900/95">
                        <Pencil class="h-4 w-4 text-indigo-500" />
                        <p class="text-sm font-black text-slate-900 dark:text-white">Update personal information</p>
                        <button @click="showEditApp = false" title="Close"
                            class="ml-auto rounded-xl p-2 text-slate-400 hover:bg-gray-100 dark:hover:bg-zinc-800">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex shrink-0 gap-2 overflow-x-auto border-b border-gray-100 px-4 py-3 dark:border-zinc-800">
                        <button v-for="t in appTabs" :key="t.key" type="button" @click="appTab = t.key"
                            :class="['inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-[11px] font-black uppercase transition active:scale-95',
                                appTab === t.key ? 'bg-indigo-600 text-white shadow' : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-zinc-800 dark:text-slate-300 dark:hover:bg-zinc-700']">
                            <component :is="t.icon" class="h-3.5 w-3.5" /> {{ t.label }}
                        </button>
                    </div>
                    <form @submit.prevent="submitAppForm" class="flex min-h-0 flex-1 flex-col">
                        <div class="min-h-0 flex-1 space-y-6 overflow-y-auto p-5 sm:p-6">
                            <p class="rounded-2xl bg-indigo-50 px-4 py-3 text-xs leading-relaxed text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-200">
                                Changes save straight to your employee record. Pick a section above — Save applies everything at once.
                            </p>

                            <!-- Personal: names → address -->
                            <div v-show="appTab === 'personal'" class="space-y-6">
                                <div>
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Names</p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div v-for="f in [
                                            { k: 'first_name', l: 'First name' },
                                            { k: 'middle_name', l: 'Middle name' },
                                            { k: 'last_name', l: 'Last name' },
                                            { k: 'suffix', l: 'Suffix' },
                                        ]" :key="f.k">
                                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">{{ f.l }}</label>
                                            <input v-model="appForm[f.k]" type="text"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <p v-if="appForm.errors[f.k]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[f.k] }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Birth & civil details</p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        <div v-for="f in [
                                            { k: 'date_of_birth', l: 'Date of birth', t: 'date' },
                                            { k: 'place_of_birth', l: 'Place of birth', t: 'text' },
                                            { k: 'citizenship', l: 'Citizenship', t: 'text' },
                                            { k: 'age', l: 'Age', t: 'text' },
                                            { k: 'sex', l: 'Sex', t: 'text' },
                                            { k: 'civil_status', l: 'Civil status', t: 'text' },
                                            { k: 'contact_number', l: 'Contact number', t: 'text' },
                                            { k: 'phone_number', l: 'Phone number', t: 'text' },
                                            { k: 'religion', l: 'Religion', t: 'text' },
                                            { k: 'languages', l: 'Languages', t: 'text' },
                                            { k: 'weight', l: 'Weight (kg)', t: 'text' },
                                            { k: 'height', l: 'Height (cm)', t: 'text' },
                                        ]" :key="f.k">
                                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">{{ f.l }}</label>
                                            <input v-model="appForm[f.k]" :type="f.t"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <p v-if="appForm.errors[f.k]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[f.k] }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Home address</p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div v-for="f in [
                                            { k: 'street_address', l: 'Street address' },
                                            { k: 'street_address_line2', l: 'Address line 2' },
                                            { k: 'barangay', l: 'Barangay' },
                                            { k: 'city', l: 'City' },
                                            { k: 'state_province', l: 'Province / State' },
                                            { k: 'postal_zip_code', l: 'ZIP code' },
                                        ]" :key="f.k">
                                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">{{ f.l }}</label>
                                            <input v-model="appForm[f.k]" type="text"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <p v-if="appForm.errors[f.k]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[f.k] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Government IDs -->
                            <div v-show="appTab === 'ids'">
                                <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Government IDs — numbers & card images</p>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div v-for="g in (application?.government_ids || [])" :key="g.key" class="rounded-2xl border border-slate-200 p-3 dark:border-zinc-700">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ g.label }}</p>
                                        <input v-model="appForm[`${g.key}_number`]" type="text" :placeholder="`${g.label} number`"
                                            class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                        <p v-if="appForm.errors[`${g.key}_number`]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[`${g.key}_number`] }}</p>
                                        <img v-if="g.file?.is_image" :src="g.file.url" :alt="g.label" class="mt-2 h-20 w-full rounded-xl border border-slate-200 object-cover dark:border-zinc-700" loading="lazy" />
                                        <label class="mt-2 block cursor-pointer rounded-xl border border-dashed border-slate-300 px-3 py-2 text-center text-[11px] font-black uppercase text-indigo-500 hover:bg-indigo-50 dark:border-zinc-600 dark:hover:bg-zinc-800">
                                            {{ g.file || idPicked[`${g.key}_file`] ? 'Replace image' : 'Upload image' }}
                                            <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="hidden" @change="(e) => onIdFile(e, `${g.key}_file`)" />
                                        </label>
                                        <p v-if="idPicked[`${g.key}_file`]" class="mt-1 truncate text-[11px] font-bold text-emerald-600">{{ idPicked[`${g.key}_file`] }}</p>
                                        <p v-if="appForm.errors[`${g.key}_file`]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[`${g.key}_file`] }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Emergency contact -->
                            <div v-show="appTab === 'emergency'">
                                <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Emergency contact</p>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div v-for="f in [
                                        { k: 'emergency_name', l: 'Contact name' },
                                        { k: 'emergency_relationship', l: 'Relationship' },
                                        { k: 'emergency_phone', l: 'Phone' },
                                        { k: 'emergency_address', l: 'Address' },
                                    ]" :key="f.k">
                                        <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">{{ f.l }}</label>
                                        <input v-model="appForm[f.k]" type="text"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                        <p v-if="appForm.errors[f.k]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[f.k] }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Family background -->
                            <div v-show="appTab === 'family'" class="space-y-6">
                                <div>
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Spouse</p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        <div v-for="f in [
                                            { k: 'spouse_name', l: 'Spouse name' },
                                            { k: 'spouse_occupation', l: 'Spouse occupation' },
                                            { k: 'spouse_address', l: 'Spouse address' },
                                        ]" :key="f.k">
                                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">{{ f.l }}</label>
                                            <input v-model="appForm[f.k]" type="text"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <p v-if="appForm.errors[f.k]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[f.k] }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Children</p>
                                    <div class="mb-3 grid max-w-xs grid-cols-1 gap-3">
                                        <div>
                                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">No. of children</label>
                                            <input v-model="appForm.number_of_children" type="text" inputmode="numeric"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <p v-if="appForm.errors.number_of_children" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors.number_of_children }}</p>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <div v-for="(c, i) in appForm.children" :key="i"
                                            class="flex items-center gap-2 rounded-2xl border border-slate-200 p-2.5 dark:border-zinc-700">
                                            <input v-model="c.name" type="text" placeholder="Child name"
                                                class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <input v-model="c.dob" type="text" placeholder="Birthdate"
                                                class="w-32 shrink-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <button type="button" @click="appForm.children.splice(i, 1)" title="Remove child"
                                                class="shrink-0 rounded-xl p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-500 dark:hover:bg-rose-900/20">
                                                <X class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <button type="button" @click="appForm.children.push({ name: '', dob: '' })"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2 text-[11px] font-black uppercase text-slate-500 hover:bg-slate-200 active:scale-95 dark:bg-zinc-800 dark:text-slate-300">
                                            + Add child
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Parents</p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div v-for="f in [
                                            { k: 'mother_name', l: 'Mother name' },
                                            { k: 'mother_address', l: 'Mother address' },
                                            { k: 'father_name', l: 'Father name' },
                                            { k: 'father_address', l: 'Father address' },
                                        ]" :key="f.k">
                                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">{{ f.l }}</label>
                                            <input v-model="appForm[f.k]" type="text"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <p v-if="appForm.errors[f.k]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[f.k] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Education -->
                            <div v-show="appTab === 'education'" class="space-y-6">
                                <div>
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Schools attended</p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div v-for="f in [
                                            { k: 'elementary_school', l: 'Elementary school' },
                                            { k: 'elementary_year', l: 'Elementary year' },
                                            { k: 'high_school', l: 'High school' },
                                            { k: 'high_year', l: 'High school year' },
                                            { k: 'college', l: 'College' },
                                            { k: 'college_year', l: 'College year' },
                                            { k: 'vocational', l: 'Vocational' },
                                            { k: 'vocational_year', l: 'Vocational year' },
                                        ]" :key="f.k">
                                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">{{ f.l }}</label>
                                            <input v-model="appForm[f.k]" type="text"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <p v-if="appForm.errors[f.k]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[f.k] }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Higher education & skills</p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div v-for="f in [
                                            { k: 'highest_education', l: 'Highest education' },
                                            { k: 'school', l: 'School' },
                                            { k: 'course', l: 'Course' },
                                            { k: 'graduation_year', l: 'Graduation year' },
                                            { k: 'special_skills', l: 'Special skills' },
                                            { k: 'machine_operation', l: 'Machine operation' },
                                        ]" :key="f.k">
                                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">{{ f.l }}</label>
                                            <input v-model="appForm[f.k]" type="text"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <p v-if="appForm.errors[f.k]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[f.k] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Work history -->
                            <div v-show="appTab === 'work'" class="space-y-6">
                                <div>
                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200 px-3 py-2.5 dark:border-zinc-700">
                                        <input v-model="appForm.has_employment_record" type="checkbox" class="h-4 w-4 rounded accent-indigo-600" />
                                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">I have previous employment</span>
                                    </label>
                                </div>
                                <div>
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Employment records</p>
                                    <div class="space-y-2">
                                        <div v-for="(r, i) in appForm.employment_records" :key="i"
                                            class="rounded-2xl border border-slate-200 p-3 dark:border-zinc-700">
                                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                <input v-model="r.company" type="text" placeholder="Company"
                                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                                <input v-model="r.position" type="text" placeholder="Position"
                                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                                <input v-model="r.years" type="text" placeholder="Years (e.g. 2020-2021)"
                                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                                <input v-model="r.salary" type="text" placeholder="Salary"
                                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                                <input v-model="r.reason" type="text" placeholder="Reason for leaving"
                                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 sm:col-span-2" />
                                            </div>
                                            <button type="button" @click="appForm.employment_records.splice(i, 1)"
                                                class="mt-2 inline-flex items-center gap-1 rounded-xl px-2 py-1 text-[11px] font-black uppercase text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20">
                                                <X class="h-3.5 w-3.5" /> Remove
                                            </button>
                                        </div>
                                        <button type="button" @click="appForm.employment_records.push({ company: '', years: '', salary: '', position: '', reason: '' })"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2 text-[11px] font-black uppercase text-slate-500 hover:bg-slate-200 active:scale-95 dark:bg-zinc-800 dark:text-slate-300">
                                            + Add record
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Previous employment & referral</p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div v-for="f in [
                                            { k: 'previous_employment_company', l: 'Previous company' },
                                            { k: 'previous_employment_when', l: 'When' },
                                            { k: 'previous_employment_position', l: 'Previous position' },
                                            { k: 'previous_employment_department', l: 'Previous department' },
                                            { k: 'referred_by', l: 'Referred by' },
                                            { k: 'referred_by_address', l: 'Referrer address' },
                                        ]" :key="f.k">
                                            <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-slate-400">{{ f.l }}</label>
                                            <input v-model="appForm[f.k]" type="text"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100" />
                                            <p v-if="appForm.errors[f.k]" class="mt-1 text-[11px] font-bold text-rose-500">{{ appForm.errors[f.k] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-3 border-t border-gray-100 bg-white/95 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900/95">
                            <button type="button" @click="showEditApp = false"
                                class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-slate-500 hover:bg-gray-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                                Cancel
                            </button>
                            <button type="submit" :disabled="appForm.processing"
                                class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-sm font-black text-white shadow hover:bg-indigo-500 disabled:opacity-50 active:scale-95">
                                {{ appForm.processing ? 'Saving…' : 'Save changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
        </Teleport>

        <!-- Large profile photo preview (teleported to body) -->
        <Teleport to="body">
        <Transition name="modal">
            <div v-if="showPhotoPreview" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="closePhotoPreview">
                <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-center gap-2 border-b border-gray-100 bg-white/95 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900/95">
                        <Expand class="h-4 w-4 text-indigo-500" />
                        <p class="truncate text-sm font-black text-slate-900 dark:text-white">{{ user?.name }}</p>
                        <button @click="closePhotoPreview" title="Close"
                            class="ml-auto rounded-xl p-2 text-slate-400 hover:bg-gray-100 dark:hover:bg-zinc-800">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex items-center justify-center bg-slate-100 p-6 dark:bg-zinc-800">
                        <img v-if="photoUrl" :src="photoUrl" alt="Profile photo"
                            class="max-h-[65vh] w-full rounded-2xl object-cover shadow-lg" />
                        <span v-else
                            class="flex h-64 w-64 items-center justify-center rounded-3xl bg-gradient-to-br from-blue-700 to-violet-800 text-7xl font-black text-white">
                            {{ initial }}
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
        </Teleport>

        <!-- ID card image viewer (teleported to body, same as above) -->
        <Teleport to="body">
        <Transition name="modal">
            <div v-if="lightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="closeLightbox">
                <div class="w-full max-w-3xl overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-center gap-2 border-b border-gray-100 bg-white/95 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900/95">
                        <Eye class="h-4 w-4 text-indigo-500" />
                        <p class="text-sm font-black text-slate-900 dark:text-white">{{ lightbox.label }}</p>
                        <div class="ml-auto flex items-center gap-1.5">
                            <a :href="lightbox.url" target="_blank" rel="noopener" title="Open original"
                                class="rounded-xl p-2 text-indigo-500 hover:bg-indigo-50 dark:hover:bg-zinc-800">
                                <Eye class="h-4 w-4" />
                            </a>
                            <button @click="closeLightbox" title="Close"
                                class="rounded-xl p-2 text-slate-400 hover:bg-gray-100 dark:hover:bg-zinc-800">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <img :src="lightbox.url" :alt="lightbox.label" class="max-h-[75vh] w-full object-contain bg-slate-100 dark:bg-zinc-800" />
                </div>
            </div>
        </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.modal-enter-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.modal-enter-from { opacity: 0; transform: scale(0.97); }
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-leave-to { opacity: 0; }
</style>
