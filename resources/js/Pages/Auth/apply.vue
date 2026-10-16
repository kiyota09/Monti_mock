<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { useTheme, initTheme } from '@/composables/useTheme';
import {
    FileCheck, Upload, Trash2, ShieldCheck, Save, CheckCircle2, Plus, X, User, Briefcase,
    Calendar, MapPin, Users, Heart, Phone, Mail, BookOpen, Award, ChevronDown, ChevronUp
} from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const isLoaded = ref(false);
const showSuccessModal = ref(false); 

// Dynamic arrays for children and employment records
const children = ref([]);
const employmentRecords = ref([]);
const showEmploymentSection = ref(false);

// Helper to add child row
const addChild = () => {
    children.value.push({ name: '', dob: '' });
};
const removeChild = (index) => { 
    children.value.splice(index, 1);
};

// Helper to add employment record row
const addEmployment = () => {
    employmentRecords.value.push({ company: '', years: '', salary: '', position: '', reason: '' });
};
const removeEmployment = (index) => {
    employmentRecords.value.splice(index, 1);
};

const form = useForm({
    // Existing fields
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    phone_country: '+63',
    phone_raw: '',
    phone_number: '',
    street_address: '',
    city: '',
    state_province: '',
    postal_zip_code: '',
    position_applied: '',

    notice_period: 'Immediate',

    sss_file: null,
    philhealth_file: null,
    pagibig_file: null,
    status: 'pending',

    // New personal fields
    image: null,
    date_of_birth: '',
    place_of_birth: '',
    citizenship: '',
    weight: '',
    height: '',
    civil_status: '',
    sex: '',
    religion: '',
    contact_number: '',
    sss_number: '',
    philhealth_number: '',
    pagibig_number: '',

    // Family
    spouse_name: '',
    spouse_occupation: '',
    spouse_address: '',
    number_of_children: 0,
    mother_name: '',
    mother_address: '',
    father_name: '',
    father_address: '',
    languages: '',
    
    // Arrays needed for Inertia to track them
    children: [],
    employment_records: [],

    // Emergency
    emergency_name: '',
    emergency_relationship: '',
    emergency_phone: '',
    emergency_address: '',

    // Education & Skills
    elementary_school: '',
    elementary_year: '',
    high_school: '',
    high_year: '',
    college: '',
    college_year: '',
    vocational: '',
    vocational_year: '',
    special_skills: '',
    has_employment_record: false,
    machine_operation: '',
    referred_by: '',
    referred_by_address: '',
    previous_employment_company: '',
    previous_employment_when: '',
    previous_employment_position: '',
    previous_employment_department: '',
});

// Watch for toggle of employment section
watch(showEmploymentSection, (val) => {
    form.has_employment_record = val;
});

// Helper: sanitize but never empty a non‑empty field
const sanitizeWithFallback = (original, pattern, field) => {
    const filtered = original.replace(pattern, '');
    if (filtered === '' && original !== '') {
        triggerWarning(field, 'Invalid characters detected – please use only allowed characters.');
        return original;
    }
    return filtered;
};

// Prepare data before submit
const submitForm = () => {
    // Basic validations
    if (!/^[a-zA-Z\sñÑ-]+$/.test(form.first_name) || !/^[a-zA-Z\sñÑ-]+$/.test(form.last_name)) {
        toast.error('First Name and Last Name must only contain letters.');
        return;
    }

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailRegex.test(form.email)) {
        toast.error('Please enter a valid email address.');
        return;
    }

    if (form.phone_raw.length < 10 || !/^\d{10,12}$/.test(form.phone_raw)) {
        toast.error('Phone number must be valid (10-12 digits). e.g. 09123456789');
        return;
    }

    if (!form.position_applied) {
        toast.error('Please select the position you are applying for.');
        return;
    }

    // Weight and Height Backend Fallback Validation
    if (form.weight !== '' && form.weight !== null && parseFloat(form.weight) <= 0) {
        toast.error('Weight must be greater than 0.');
        return;
    }
    if (form.height !== '' && form.height !== null && parseFloat(form.height) <= 0) {
        toast.error('Height must be greater than 0.');
        return;
    }

    // Trim and check for emptiness
    const street = form.street_address?.trim() || '';
    const city = form.city?.trim() || '';
    const state = form.state_province?.trim() || '';
    if (!street || !city || !state) {
        let missing = [];
        if (!street) missing.push('Street Address');
        if (!city) missing.push('City');
        if (!state) missing.push('State/Province');
        toast.error(`Complete residential details are required. Missing: ${missing.join(', ')}`);
        return;
    }

    if (form.postal_zip_code.length !== 4) {
        toast.error('Postal/Zip code must be exactly 4 digits.');
        return;
    }

    // Combine phone
    form.phone_number = `${form.phone_country}${form.phone_raw}`;

    // Prepare arrays for children and employment records
    form.children = children.value;
    form.employment_records = employmentRecords.value;
    form.number_of_children = children.value.length;

    // Convert weight/height to numbers
    if (form.weight) form.weight = parseFloat(form.weight);
    if (form.height) form.height = parseFloat(form.height);

    // Send the form with forceFormData to handle file uploads
    form.post(route('applicants.public.store'), {
        forceFormData: true,
        onSuccess: () => {
            showSuccessModal.value = true;
            setTimeout(() => {
                router.visit('/');
            }, 3000);
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Application submission failed. Please check your inputs.';
            toast.error(errorMsg);
        }
    });
};

// Image upload handler
const handleImageUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.image = file;
    }
};

// Existing file handlers
const handleFileUpload = (e, type) => {
    const file = e.target.files[0];
    if (file) {
        form[type + '_file'] = file;
    }
};

const removeFile = (type) => {
    form[type + '_file'] = null;
};

// Input sanitization
const inputWarnings = ref({});
let warningTimeouts = {};

const triggerWarning = (field, message) => {
    inputWarnings.value[field] = message;
    if (warningTimeouts[field]) clearTimeout(warningTimeouts[field]);
    warningTimeouts[field] = setTimeout(() => {
        inputWarnings.value[field] = '';
    }, 3000);
};

const blockNumbersAndSpecial = (e, field) => {
    if (e.key.length === 1 && !/^[a-zA-Z\sñÑ-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning(field, 'Numbers and special characters are not allowed.');
    }
};

const blockNonNumeric = (e, field) => {
    if (e.key.length === 1 && !/^\d$/.test(e.key)) {
        e.preventDefault();
        triggerWarning(field, 'Letters and special characters are not allowed.');
    }
};

const blockSpecialForAddress = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9\sñÑ.,#-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning('street_address', 'Invalid character. Use alphanumeric and basic punctuation.');
    }
};

const blockSpecialForEmail = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9@.\-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning('email', 'Invalid character. Only letters, numbers, @, ., and - are allowed.');
    }
};

// NEW: Blocks negative signs and exponential letters on number inputs
const blockNegative = (e) => {
    if (e.key === '-' || e.key === '+' || e.key === 'e' || e.key === 'E') {
        e.preventDefault();
    }
};

// Name sanitization (letters, spaces, ñ, -)
const sanitizeName = (val, field) => {
    const filtered = sanitizeWithFallback(val, /[^a-zA-Z\sñÑ-]/g, field);
    if (val !== filtered) {
        form[field] = filtered;
        triggerWarning(field, 'Invalid characters removed.');
    }
};

watch(() => form.first_name, (val) => sanitizeName(val, 'first_name'));
watch(() => form.middle_name, (val) => sanitizeName(val, 'middle_name'));
watch(() => form.last_name, (val) => sanitizeName(val, 'last_name'));
watch(() => form.city, (val) => sanitizeName(val, 'city'));
watch(() => form.state_province, (val) => sanitizeName(val, 'state_province'));

watch(() => form.street_address, (val) => {
    const filtered = sanitizeWithFallback(val, /[^a-zA-Z0-9\sñÑ.,#-]/g, 'street_address');
    if (val !== filtered) form.street_address = filtered;
});

watch(() => form.email, (val) => {
    const filtered = val.replace(/[^a-zA-Z0-9@.\-]/g, '');
    if (val !== filtered) {
        form.email = filtered;
        triggerWarning('email', 'Invalid characters removed.');
    }
});

watch(() => form.phone_raw, (val) => {
    const filtered = val.replace(/\D/g, '').substring(0, 12);
    if (val !== filtered) {
        form.phone_raw = filtered;
        triggerWarning('phone_raw', 'Numbers only.');
    }
});

watch(() => form.postal_zip_code, (val) => {
    const filtered = val.replace(/\D/g, '').substring(0, 4);
    if (val !== filtered) {
        form.postal_zip_code = filtered;
        triggerWarning('postal_zip_code', 'Only digits allowed.');
    }
});

const enforceNumbersOnly = (event) => {
    const cleanedValue = event.target.value.replace(/[^0-9]/g, '');
    event.target.value = cleanedValue;
    form.phone_raw = cleanedValue;
};

const blockNonNumbers = (event) => {
    if (!/[0-9]/.test(event.key)) {
        event.preventDefault();
    }
};

const activePositions = ref([]);

const fetchActivePositions = async () => {
    try {
        const response = await fetch('/active-positions');
        
        if (response.ok) {
            const data = await response.json();
            activePositions.value = data;
        }
    } catch (error) {
        console.error("Error fetching active positions:", error);
    }
};

const { currentTheme } = useTheme();
const THEME_ACCENT = { blue: 'blue', red: 'red', green: 'emerald' };
const authAccent = computed(() => THEME_ACCENT[currentTheme.value] ?? 'blue');

onMounted(() => {
    initTheme();
    isLoaded.value = true;
    fetchActivePositions();
});

</script>

<template>
    <Head title="Join Our Team | Monti Corp Careers" />

    <div class="auth-scope relative flex min-h-screen w-full flex-col overflow-y-auto font-sans"
        :data-accent="authAccent"
        style="background-image: url('/images/landingTheme.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed; background-color: #2a2a3e;">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-[1px]"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-black/10 pointer-events-none"></div>

        <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="showSuccessModal"
                class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-black/60 backdrop-blur-md">
                <div
                    class="bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl p-6 sm:p-8 max-w-sm w-full shadow-2xl shadow-black text-center">
                    <div
                        class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-emerald-500/20 mb-6 ring-4 ring-emerald-500/10">
                        <CheckCircle2 class="w-10 h-10 text-emerald-400" />
                    </div>
                    <h3 class="text-2xl font-black text-white mb-2 tracking-tight">Application Received</h3>
                    <p class="text-slate-300 mb-8 leading-relaxed font-medium">Your professional profile has been
                        securely sent to our HR node. Redirecting you shortly...</p>
                    <Link href="/"
                        class="auth-btn inline-flex items-center justify-center w-full px-5 py-2.5 text-xs font-bold text-white">
                        Return Home Now</Link>
                </div>
            </div>
        </Transition>

        <header class="relative z-30 w-full shrink-0 border-b border-white/10 bg-black/20 backdrop-blur-md">
            <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-4 py-3">
                <Link href="/" class="flex items-center gap-3 group">
                    <div
                        class="p-1.5 bg-white/10 backdrop-blur-md rounded-lg border border-white/20 shadow-lg group-hover:scale-105 transition-transform duration-300">
                        <img src="/images/applogo.png" alt="Monti Textile Logo" class="h-7 w-7 sm:h-8 sm:w-8 object-contain" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-base sm:text-lg font-black tracking-tight leading-none text-white uppercase drop-shadow-md">
                            Monti<span class="text-blue-400">Textile</span>
                        </span>
                        <span class="text-[7px] sm:text-[8px] font-bold uppercase tracking-[0.2em] text-slate-300 mt-0.5">
                            Manufacturing ERP
                        </span>
                    </div>
                </Link>
                <nav class="flex flex-wrap items-center gap-1.5 sm:gap-3">
                    <Link href="/"
                        class="text-[9px] sm:text-xs font-semibold px-2 py-1 rounded-lg text-slate-300 hover:text-white hover:bg-white/5 transition-all">
                        Home
                    </Link>
                </nav>
            </div>
        </header>

        <div class="relative z-10 flex-grow flex items-center justify-center px-5 pb-12 pt-4">
            <div class="w-full max-w-5xl"
                :class="{ 'opacity-0 translate-y-8': !isLoaded, 'opacity-100 translate-y-0': isLoaded }"
                style="transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.1s;">
                <div
                    class="auth-card overflow-hidden">
                    <div class="p-6 sm:p-8 space-y-10">
                        <div class="text-center mb-10">
                            <p class="auth-badge mb-4">
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" style="background: var(--auth-pill-dot)"></span>
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full" style="background: var(--auth-pill-dot)"></span>
                                </span>
                                Careers
                            </p>
                            <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-white drop-shadow-2xl">
                                Careers <span class="auth-gradient-word">Application</span></h1>
                            <p class="mt-3 text-xs sm:text-sm text-slate-300 leading-relaxed max-w-2xl mx-auto">Fill out the details below to
                                apply for a position. All applications are securely processed by our human resources
                                department.</p>
                        </div>

                        <form @submit.prevent="submitForm" class="space-y-10">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="md:col-span-3">
                                    <h3
                                        class="text-[10px] font-bold uppercase tracking-widest text-blue-400 border-b border-white/10 pb-2 mb-2">
                                        Personal Identity</h3>
                                </div>

                                <div>
                                    <InputLabel for="image" value="Profile Photo (Optional)"
                                        class="text-white/90 font-semibold" />
                                    <div
                                        class="relative h-32 rounded-lg border-2 border-dashed border-white/20 bg-white/5 hover:border-blue-400/50 transition-all group overflow-hidden">
                                        <template v-if="!form.image">
                                            <Upload
                                                class="h-5 w-5 text-white/50 group-hover:text-blue-300 transition-colors mb-2 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
                                            <input type="file" @change="handleImageUpload"
                                                class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*" />
                                        </template>
                                        <template v-else>
                                            <div class="flex flex-col items-center justify-center h-full">
                                                <div class="p-1.5 bg-emerald-500/20 rounded-full mb-1">
                                                    <FileCheck class="h-5 w-5 text-emerald-300" />
                                                </div>
                                                <p class="text-[10px] font-black text-emerald-200 truncate w-24">{{
                                                    form.image.name }}</p>
                                                <button @click="form.image = null" type="button"
                                                    class="mt-2 p-1.5 bg-red-500/20 text-red-300 rounded-lg hover:bg-red-500/40 transition-colors">
                                                    <Trash2 class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <InputError class="mt-1 text-red-300" :message="form.errors.image" />
                                </div>

                                <div>
                                    <InputLabel for="first_name" value="First Name"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="first_name" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.first_name" required autofocus placeholder="Juan"
                                        @keypress="blockNumbersAndSpecial($event, 'first_name')" />
                                    <p v-if="inputWarnings.first_name"
                                        class="text-xs text-red-300 font-bold mt-1 ml-1 animate-pulse">{{
                                            inputWarnings.first_name }}</p>
                                    <InputError class="mt-1 text-red-300" :message="form.errors.first_name" />
                                </div>
                                <div>
                                    <InputLabel for="middle_name" value="Middle Name (Optional)"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="middle_name" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.middle_name" placeholder="Santos"
                                        @keypress="blockNumbersAndSpecial($event, 'middle_name')" />
                                    <p v-if="inputWarnings.middle_name"
                                        class="text-xs text-red-300 font-bold mt-1 ml-1 animate-pulse">{{
                                            inputWarnings.middle_name }}</p>
                                    <InputError class="mt-1 text-red-300" :message="form.errors.middle_name" />
                                </div>
                                <div>
                                    <InputLabel for="last_name" value="Last Name" class="text-white/90 font-semibold" />
                                    <TextInput id="last_name" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.last_name" required placeholder="Dela Cruz"
                                        @keypress="blockNumbersAndSpecial($event, 'last_name')" />
                                    <p v-if="inputWarnings.last_name"
                                        class="text-xs text-red-300 font-bold mt-1 ml-1 animate-pulse">{{
                                            inputWarnings.last_name }}</p>
                                    <InputError class="mt-1 text-red-300" :message="form.errors.last_name" />
                                </div>

                                <div class="md:col-span-3 mt-4 border-t border-white/10 pt-4">
                                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-blue-400 pb-2 mb-2">
                                        Professional & Contact Details
                                    </h3>
                                </div>

                                <div>
                                    <InputLabel for="email" value="Email Address" class="text-white/90 font-semibold" />
                                    <TextInput id="email" type="email"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.email" required placeholder="juan@example.com"
                                        @keypress="blockSpecialForEmail($event)" />
                                    <p v-if="inputWarnings.email"
                                        class="text-xs text-red-300 font-bold mt-1 ml-1 animate-pulse">{{
                                        inputWarnings.email
                                        }}</p>
                                </div>

                                <div>
                                    <InputLabel for="phone_raw" value="Phone Number (10-12 digits)"
                                        class="text-white/90 font-semibold" />
                                    <div class="flex gap-2 mt-1">
                                        <select v-model="form.phone_country"
                                            class="auth-input w-[35%] cursor-pointer text-center">
                                            <option value="+63">+63 (PH)</option>
                                            <option value="+1">+1 (US/CA)</option>
                                        </select>
                                        
                                        <TextInput id="phone_raw" type="tel" maxlength="12"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.phone_raw" 
                                            @input="enforceNumbersOnly"
                                            @keypress="blockNonNumbers"
                                            required placeholder="09123456789" />
                                        
                                    </div>
                                    <p v-if="inputWarnings.phone_raw"
                                        class="text-xs text-red-300 font-bold mt-1 ml-1 animate-pulse">
                                        {{ inputWarnings.phone_raw }}
                                    </p>
                                </div>

                                <div>
                                    <InputLabel for="position_applied" value="Position Applied For" class="text-white/90 font-semibold" />
                                    <select 
                                        id="position_applied" 
                                        v-model="form.position_applied" 
                                        required
                                        class="auth-input mt-1 block w-full"
                                    >
                                        <option value="" disabled>Select Position</option>
                                        
                                        <option 
                                            v-for="pos in activePositions" 
                                            :key="pos.id" 
                                            :value="pos.position"
                                        >
                                            {{ pos.position }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <InputLabel for="notice_period" value="Notice Period"
                                        class="text-white/90 font-semibold" />
                                    <select id="notice_period" v-model="form.notice_period" required
                                        class="auth-input mt-1 block w-full">
                                        <option value="Immediate">Immediate</option>
                                        <option value="15_Days">15 Days</option>
                                        <option value="30_Days">30 Days</option>
                                        <option value="60_Days">60 Days</option>
                                    </select>
                                </div>

                                <div class="md:col-span-3">
                                    <InputLabel for="street_address" value="Street Address"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="street_address" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.street_address" required placeholder="123 Main St, Brgy. San Jose"
                                        @keypress="blockSpecialForAddress($event)" />
                                    <p v-if="inputWarnings.street_address"
                                        class="text-xs text-red-300 font-bold mt-1 ml-1 animate-pulse">{{
                                        inputWarnings.street_address }}</p>
                                </div>
                                <div>
                                    <InputLabel for="city" value="City/Municipality"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="city" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.city" required placeholder="General Trias" />
                                </div>
                                <div>
                                    <InputLabel for="state_province" value="State/Province"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="state_province" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.state_province" required placeholder="Cavite" />
                                </div>
                                <div>
                                    <InputLabel for="postal_zip_code" value="Postal/Zip Code"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="postal_zip_code" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.postal_zip_code" required placeholder="4107" />
                                    <p v-if="inputWarnings.postal_zip_code"
                                        class="text-xs text-red-300 font-bold mt-1 ml-1 animate-pulse">{{
                                        inputWarnings.postal_zip_code }}</p>
                                </div>

                                <div class="md:col-span-3 mt-4 border-t border-white/10 pt-4">
                                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-blue-400 pb-2 mb-2">
                                        Other Details
                                    </h3>
                                </div>

                                <div>
                                    <InputLabel for="date_of_birth" value="Date of Birth"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="date_of_birth" type="date"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.date_of_birth" />
                                </div>
                                <div>
                                    <InputLabel for="place_of_birth" value="Place of Birth"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="place_of_birth" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.place_of_birth" placeholder="City, Province" />
                                </div>
                                <div>
                                    <InputLabel for="citizenship" value="Citizenship"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="citizenship" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.citizenship" placeholder="Filipino" />
                                </div>

                                <div>
                                    <InputLabel for="weight" value="Weight (kg)" class="text-white/90 font-semibold" />
                                    <TextInput id="weight" type="number" step="0.1" min="0"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.weight" placeholder="65.5" 
                                        @keypress="blockNegative" />
                                </div>
                                
                                <div>
                                    <InputLabel for="height" value="Height (cm)" class="text-white/90 font-semibold" />
                                    <TextInput id="height" type="number" step="0.1" min="0"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.height" placeholder="170" 
                                        @keypress="blockNegative" />
                                </div>

                                <div>
                                    <InputLabel for="civil_status" value="Civil Status"
                                        class="text-white/90 font-semibold" /><select id="civil_status"
                                        v-model="form.civil_status"
                                        class="auth-input mt-1 block w-full">
                                        <option value="">Select</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                    </select>
                                </div>

                                <div>
                                    <InputLabel for="sex" value="Sex" class="text-white/90 font-semibold" /><select
                                        id="sex" v-model="form.sex"
                                        class="auth-input mt-1 block w-full">
                                        <option value="">Select</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="religion" value="Religion" class="text-white/90 font-semibold" />
                                    <TextInput id="religion" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.religion" placeholder="Roman Catholic" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <h3
                                        class="text-[10px] font-bold uppercase tracking-widest text-blue-400 border-b border-white/10 pb-2 mb-2">
                                        Government IDs</h3>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <InputLabel for="sss_number" value="SSS Number"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="sss_number" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.sss_number" placeholder="XX-XXXXXXX-X" />
                                    </div>
                                    <div>
                                        <InputLabel for="philhealth_number" value="PhilHealth Number"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="philhealth_number" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.philhealth_number" placeholder="XX-XXXXXXXXX-X" />
                                    </div>
                                    <div>
                                        <InputLabel for="pagibig_number" value="Pag-IBIG Number"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="pagibig_number" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.pagibig_number" placeholder="XXXX-XXXX-XXXX" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div v-for="type in ['sss', 'philhealth', 'pagibig']" :key="type" class="space-y-2">
                                        <p class="text-[10px] font-black text-white/70 uppercase tracking-[0.2em] ml-1">
                                            {{ type.toUpperCase() }} ID
                                            Image</p>
                                        <div :class="form[type + '_file'] ? 'border-emerald-400/50 bg-emerald-500/10' : 'border-white/20 bg-white/5 hover:border-blue-400/50'"
                                            class="relative h-32 rounded-lg border-2 border-dashed flex flex-col items-center justify-center p-4 transition-all group overflow-hidden">
                                            <template v-if="!form[type + '_file']">
                                                <Upload
                                                    class="h-5 w-5 text-white/50 group-hover:text-blue-300 transition-colors mb-2" />
                                                <p
                                                    class="text-[10px] text-white/70 font-bold uppercase tracking-widest">
                                                    Select Image</p>
                                                <input type="file" @change="(e) => handleFileUpload(e, type)"
                                                    class="absolute inset-0 opacity-0 cursor-pointer"
                                                    accept=".jpg,.jpeg,.png,.pdf">
                                            </template>
                                            <template v-else>
                                                <div class="flex flex-col items-center text-center">
                                                    <div class="p-1.5 bg-emerald-500/20 rounded-full mb-1">
                                                        <FileCheck class="h-5 w-5 text-emerald-300" />
                                                    </div>
                                                    <p class="text-[10px] font-black text-emerald-200 truncate w-24">{{
                                                        form[type + '_file'].name }}</p>
                                                    <button @click="removeFile(type)" type="button"
                                                        class="mt-2 p-1.5 bg-red-500/20 text-red-300 rounded-lg hover:bg-red-500/40 transition-colors">
                                                        <Trash2 class="h-3 w-3" />
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <h3
                                        class="text-[10px] font-bold uppercase tracking-widest text-blue-400 border-b border-white/10 pb-2 mb-2">
                                        Spouse
                                        Information (if married)</h3>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="spouse_name" value="Spouse's Full Name"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="spouse_name" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.spouse_name" placeholder="Juan Dela Cruz" />
                                    </div>
                                    <div>
                                        <InputLabel for="spouse_occupation" value="Spouse's Occupation"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="spouse_occupation" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.spouse_occupation" placeholder="Engineer" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel for="spouse_address" value="Spouse's Address"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="spouse_address" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.spouse_address" placeholder="Complete address" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <h3
                                        class="text-[10px] font-bold uppercase tracking-widest text-blue-400 border-b border-white/10 pb-2 mb-2">
                                        Children</h3>
                                </div>
                                <div>
                                    <button type="button" @click="addChild"
                                        class="inline-flex items-center gap-1 px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition-all active:scale-95 shadow-lg shadow-blue-600/30">+
                                        Add Child</button>
                                </div>
                                <div v-for="(child, idx) in children" :key="idx"
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-white/5 border border-white/10 rounded-lg relative">
                                    <button type="button" @click="removeChild(idx)"
                                        class="absolute top-2 right-2 p-1 text-red-400 hover:text-red-600">
                                        <X class="h-4 w-4" />
                                    </button>
                                    <div>
                                        <InputLabel :for="`child_name_${idx}`" value="Name"
                                            class="text-white/80 text-xs" />
                                        <TextInput :id="`child_name_${idx}`" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="child.name" placeholder="Full name" />
                                    </div>
                                    <div>
                                        <InputLabel :for="`child_dob_${idx}`" value="Date of Birth"
                                            class="text-white/80 text-xs" />
                                        <TextInput :id="`child_dob_${idx}`" type="date"
                                            class="auth-input mt-1 block w-full"
                                            v-model="child.dob" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <h3
                                        class="text-[10px] font-bold uppercase tracking-widest text-blue-400 border-b border-white/10 pb-2 mb-2">
                                        Parents Information</h3>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="mother_name" value="Mother's Name"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="mother_name" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.mother_name" placeholder="Full name" />
                                    </div>
                                    <div>
                                        <InputLabel for="mother_address" value="Mother's Address"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="mother_address" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.mother_address" placeholder="Address" />
                                    </div>
                                    <div>
                                        <InputLabel for="father_name" value="Father's Name"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="father_name" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.father_name" placeholder="Full name" />
                                    </div>
                                    <div>
                                        <InputLabel for="father_address" value="Father's Address"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="father_address" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.father_address" placeholder="Address" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <InputLabel for="languages" value="Language(s) You Can Speak or Write"
                                    class="text-white/90 font-semibold" />
                                <TextInput id="languages" type="text"
                                    class="auth-input mt-1 block w-full"
                                    v-model="form.languages" placeholder="Tagalog, English, etc." />
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <h3
                                        class="text-[10px] font-bold uppercase tracking-widest text-blue-400 border-b border-white/10 pb-2 mb-2">
                                        Emergency Contact</h3>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="emergency_name" value="Name"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="emergency_name" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.emergency_name" placeholder="Full name" />
                                    </div>
                                    <div>
                                        <InputLabel for="emergency_relationship" value="Relationship"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="emergency_relationship" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.emergency_relationship" placeholder="Spouse, Parent, etc." />
                                    </div>
                                    <div>
                                        <InputLabel for="emergency_phone" value="Telephone Number"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="emergency_phone" type="tel"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.emergency_phone" placeholder="09123456789" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel for="emergency_address" value="Address"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="emergency_address" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.emergency_address" placeholder="Complete address" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <h3
                                        class="text-[10px] font-bold uppercase tracking-widest text-blue-400 border-b border-white/10 pb-2 mb-2">
                                        Educational Background</h3>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="elementary_school" value="Elementary School"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="elementary_school" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.elementary_school" placeholder="School name" />
                                    </div>
                                    <div>
                                        <InputLabel for="elementary_year" value="Year Graduated"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="elementary_year" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.elementary_year" placeholder="YYYY" />
                                    </div>
                                    <div>
                                        <InputLabel for="high_school" value="High School"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="high_school" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.high_school" placeholder="School name" />
                                    </div>
                                    <div>
                                        <InputLabel for="high_year" value="Year Graduated"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="high_year" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.high_year" placeholder="YYYY" />
                                    </div>
                                    <div>
                                        <InputLabel for="college" value="College" class="text-white/90 font-semibold" />
                                        <TextInput id="college" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.college" placeholder="Course & School" />
                                    </div>
                                    <div>
                                        <InputLabel for="college_year" value="Year Graduated"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="college_year" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.college_year" placeholder="YYYY" />
                                    </div>
                                    <div>
                                        <InputLabel for="vocational" value="Vocational"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="vocational" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.vocational" placeholder="Course & School" />
                                    </div>
                                    <div>
                                        <InputLabel for="vocational_year" value="Year Graduated"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="vocational_year" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.vocational_year" placeholder="YYYY" />
                                    </div>
                                </div>
                                <div>
                                    <InputLabel for="special_skills" value="Special Skills"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="special_skills" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.special_skills" placeholder="e.g., Microsoft Office, Sewing" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="has_employment_record" v-model="showEmploymentSection"
                                        class="rounded border-white/30 bg-white/5 text-blue-600 focus:ring-blue-500" />
                                    <label for="has_employment_record" class="text-white/90 font-semibold text-sm">I
                                        have previous employment
                                        record(s)</label>
                                </div>
                                <div v-if="showEmploymentSection">
                                    <div class="flex justify-between items-center mb-2">
                                        <h4 class="text-xs font-black text-blue-300 uppercase">Employment Records (from
                                            present to previous)</h4>
                                        <button type="button" @click="addEmployment"
                                            class="inline-flex items-center gap-1 px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition-all active:scale-95 shadow-lg shadow-blue-600/30">+
                                            Add Record</button>
                                    </div>
                                    <div v-for="(rec, idx) in employmentRecords" :key="idx"
                                        class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-white/5 border border-white/10 rounded-lg relative mb-4">
                                        <button type="button" @click="removeEmployment(idx)"
                                            class="absolute top-2 right-2 p-1 text-red-400 hover:text-red-600">
                                            <X class="h-4 w-4" />
                                        </button>
                                        <div>
                                            <InputLabel :for="`emp_company_${idx}`" value="Company"
                                                class="text-white/80 text-xs" />
                                            <TextInput :id="`emp_company_${idx}`" type="text"
                                                class="auth-input mt-1 block w-full"
                                                v-model="rec.company" placeholder="Company name" />
                                        </div>
                                        <div>
                                            <InputLabel :for="`emp_years_${idx}`" value="Years of Service"
                                                class="text-white/80 text-xs" />
                                            <TextInput :id="`emp_years_${idx}`" type="text"
                                                class="auth-input mt-1 block w-full"
                                                v-model="rec.years" placeholder="e.g., 2 years" />
                                        </div>
                                        <div>
                                            <InputLabel :for="`emp_salary_${idx}`" value="Salary"
                                                class="text-white/80 text-xs" />
                                            <TextInput :id="`emp_salary_${idx}`" type="text"
                                                class="auth-input mt-1 block w-full"
                                                v-model="rec.salary" placeholder="Monthly" />
                                        </div>
                                        <div>
                                            <InputLabel :for="`emp_position_${idx}`" value="Position"
                                                class="text-white/80 text-xs" />
                                            <TextInput :id="`emp_position_${idx}`" type="text"
                                                class="auth-input mt-1 block w-full"
                                                v-model="rec.position" placeholder="Job title" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <InputLabel :for="`emp_reason_${idx}`" value="Reason for Leaving"
                                                class="text-white/80 text-xs" />
                                            <TextInput :id="`emp_reason_${idx}`" type="text"
                                                class="auth-input mt-1 block w-full"
                                                v-model="rec.reason" placeholder="Reason" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <InputLabel for="machine_operation" value="Any machine you can operate?"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="machine_operation" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.machine_operation" placeholder="e.g., Sewing machine, Forklift" />
                                </div>
                                <div>
                                    <InputLabel for="referred_by" value="Who referred you to this company?"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="referred_by" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.referred_by" placeholder="Name" />
                                </div>
                                <div>
                                    <InputLabel for="referred_by_address" value="His/Her address"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="referred_by_address" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.referred_by_address" placeholder="Address" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <h3
                                        class="text-[10px] font-bold uppercase tracking-widest text-blue-400 border-b border-white/10 pb-2 mb-2">
                                        Previous Employment with Monti Textile?</h3>
                                </div>
                                <div>
                                    <InputLabel for="previous_employment_company"
                                        value="Have you ever been employed in this company?"
                                        class="text-white/90 font-semibold" /><select id="previous_employment_company"
                                        v-model="form.previous_employment_company"
                                        class="auth-input mt-1 block w-full">
                                        <option value="">Select</option>
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>
                                <div v-if="form.previous_employment_company === 'yes'"
                                    class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <InputLabel for="previous_employment_when" value="When?"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="previous_employment_when" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.previous_employment_when" placeholder="e.g., 2020-2021" />
                                    </div>
                                    <div>
                                        <InputLabel for="previous_employment_position" value="Position"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="previous_employment_position" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.previous_employment_position" placeholder="Position held" />
                                    </div>
                                    <div>
                                        <InputLabel for="previous_employment_department" value="Department"
                                            class="text-white/90 font-semibold" />
                                        <TextInput id="previous_employment_department" type="text"
                                            class="auth-input mt-1 block w-full"
                                            v-model="form.previous_employment_department" placeholder="Department" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <h3
                                        class="text-[10px] font-bold uppercase tracking-widest text-blue-400 border-b border-white/10 pb-2 mb-2">
                                        Related Employees</h3>
                                </div>
                                <div>
                                    <p class="text-xs text-white/70 mb-2">Are you at present or in the past related (by
                                        consanguinity or affinity)
                                        with any employee/worker of Monti Textile? If yes, please list:</p>
                                </div>
                                <div>
                                    <InputLabel for="related_employees" value="Name — Relationship"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="related_employees" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.related_employees" placeholder="e.g., Juan Dela Cruz - Cousin" />
                                </div>
                            </div>

                            <div
                                class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-8 border-t border-white/10 mt-6">
                                <div
                                    class="flex items-center text-[10px] font-black uppercase tracking-widest text-white/70">
                                    <ShieldCheck class="h-5 w-5 text-blue-300 mr-2" /> Data Encryption Active
                                </div>
                                <PrimaryButton
                                    class="auth-btn w-full sm:w-auto px-5 py-2.5 text-xs font-bold text-white"
                                    :class="{ 'opacity-60 cursor-wait': form.processing }" :disabled="form.processing">
                                    <span v-if="form.processing">Processing...</span>
                                    <span v-else class="flex items-center gap-2">
                                        <Save class="w-4 h-4" /> Submit Application
                                    </span>
                                </PrimaryButton>
                            </div>
                            <div class="text-center mt-4 border-t border-white/10 pt-6">
                                <Link href="/"
                                    class="text-sm font-semibold text-slate-300 hover:text-white transition-colors">
                                    &larr; Return to Home
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>