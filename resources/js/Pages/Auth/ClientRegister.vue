<script setup>
import { onMounted, ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const form = useForm({
    company_name: '',
    business_type: '',
    tin_number: '',
    contact_person: '',
    email: '',
    phone_country: '+63',
    phone_raw: '',
    phone: '',
    password: '',
    password_confirmation: '',
    company_address: '',
    logo: null,
});

const logoPreview = ref(null);

const handleLogoChange = (e) => {
    const file = e.target.files[0] || null;
    form.logo = file;
    logoPreview.value = file ? URL.createObjectURL(file) : null;
};

const isLoaded = ref(false);
const showPassword = ref(false);
const showConfirmPassword = ref(false);

const inputWarnings = ref({
    company_name: '',
    contact_person: '',
    company_address: '',
    email: '',
    tin_number: '',
    phone_raw: ''
});

let warningTimeouts = {};

onMounted(() => {
    isLoaded.value = true;
});

const togglePassword = () => {
    showPassword.value = !showPassword.value;
};

const toggleConfirmPassword = () => {
    showConfirmPassword.value = !showConfirmPassword.value;
};

const triggerWarning = (field, message) => {
    inputWarnings.value[field] = message;
    if (warningTimeouts[field]) clearTimeout(warningTimeouts[field]);
    warningTimeouts[field] = setTimeout(() => {
        inputWarnings.value[field] = '';
    }, 3000);
};

const blockNumbersAndSpecial = (e, field) => {
    if (e.key.length === 1 && !/^[a-zA-Z\s]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning(field, 'Numbers and special characters are not allowed.');
    }
};

const blockNonNumeric = (e, field) => {
    if (e.key.length === 1 && !/^\d$/.test(e.key)) {
        e.preventDefault();
        triggerWarning(field, 'Letters and special characters are not allowed. Numbers only.');
    }
};

const blockSpecialForAddress = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9\s.]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning('company_address', 'Only letters, numbers, spaces, and dots (.) are allowed.');
    }
};

const blockSpecialForEmail = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9@.\-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning('email', 'Invalid character. Only letters, numbers, @, ., and - are allowed.');
    }
};

watch(() => form.company_name, (val) => {
    const filtered = val.replace(/[^a-zA-Z\s]/g, '');
    if (val !== filtered) form.company_name = filtered;
});

watch(() => form.contact_person, (val) => {
    const filtered = val.replace(/[^a-zA-Z\s]/g, '');
    if (val !== filtered) form.contact_person = filtered;
});

watch(() => form.company_address, (val) => {
    const filtered = val.replace(/[^a-zA-Z0-9\s.]/g, '');
    if (val !== filtered) form.company_address = filtered;
});

watch(() => form.email, (val) => {
    const filtered = val.replace(/[^a-zA-Z0-9@.\-]/g, '');
    if (val !== filtered) form.email = filtered;
});

watch(() => form.tin_number, (val) => {
    if (!val) return;
    const digits = val.replace(/\D/g, '').substring(0, 12);
    const formatted = digits.match(/.{1,3}/g)?.join('-') || '';
    if (val !== formatted) form.tin_number = formatted;
});

watch(() => form.phone_raw, (val) => {
    const filtered = val.replace(/\D/g, '').substring(0, 12);
    if (val !== filtered) form.phone_raw = filtered;
});

const submit = () => {
    form.phone = form.phone_country + form.phone_raw;

    form.post(route('client.register.store'), {
        forceFormData: true,
        onSuccess: () => {
            toast.success('Registration submitted successfully! Please wait for approval.');
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Registration failed. Please check the form.';
            toast.error(errorMsg);
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout
        title="Apply for Partnership"
        highlight="Partnership"
        subtitle="Please fill in your company details to request access to the Monti Textile B2B Partner Portal."
        badge="Partner Registration"
        accent="sky"
        wide
    >
        <Head title="Monti Textile - Partner Registration" />

        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="company_name" value="Company Name" class="text-white/90" />
                                    <TextInput id="company_name" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.company_name" required
                                        @keypress="blockNumbersAndSpecial($event, 'company_name')"
                                        placeholder="Your Company Name" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.company_name" />
                                    <p v-if="inputWarnings.company_name" class="mt-1 text-xs text-red-300">{{
                                        inputWarnings.company_name }}</p>
                                </div>

                                <div>
                                    <InputLabel for="business_type" value="Business Type" class="text-white/90" />
                                    <TextInput id="business_type" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.business_type" required
                                        placeholder="e.g. Garment Manufacturer, Retailer, Exporter" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.business_type" />
                                </div>

                                <div>
                                    <InputLabel for="tin_number" value="TIN Number" class="text-white/90" />
                                    <TextInput id="tin_number" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.tin_number" maxlength="14" placeholder="000-000-000-000" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.tin_number" />
                                </div>

                                <div>
                                    <InputLabel for="contact_person" value="Contact Person" class="text-white/90" />
                                    <TextInput id="contact_person" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.contact_person" required
                                        @keypress="blockNumbersAndSpecial($event, 'contact_person')"
                                        placeholder="Full Name" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.contact_person" />
                                    <p v-if="inputWarnings.contact_person" class="mt-1 text-xs text-red-300">{{
                                        inputWarnings.contact_person }}</p>
                                </div>

                                <div>
                                    <InputLabel for="email" value="Business Email" class="text-white/90" />
                                    <TextInput id="email" type="email"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.email" required autocomplete="username"
                                        placeholder="company@example.com" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.email" />
                                </div>

                                <div>
                                    <InputLabel for="phone_raw" value="Phone Number" class="text-white/90" />
                                    <div class="flex">
                                        <span
                                            class="inline-flex items-center px-4 py-2 bg-white/10 border border-r-0 border-white/20 rounded-l-lg text-sm text-white">
                                            {{ form.phone_country }}
                                        </span>
                                        <TextInput id="phone_raw" type="tel"
                                            class="auth-input flex-1 block"
                                            style="border-top-left-radius: 0; border-bottom-left-radius: 0;"
                                            v-model="form.phone_raw" @keypress="blockNonNumeric($event, 'phone_raw')"
                                            placeholder="9171234567" maxlength="10" />
                                    </div>
                                    <InputError class="mt-1 text-red-300" :message="form.errors.phone" />
                                    <p v-if="inputWarnings.phone_raw" class="mt-1 text-xs text-red-300">{{
                                        inputWarnings.phone_raw }}</p>
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="company_address" value="Company Address" class="text-white/90" />
                                    <TextInput id="company_address" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.company_address" required @keypress="blockSpecialForAddress"
                                        placeholder="Full company address" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.company_address" />
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="logo" value="Company Logo (optional)" class="text-white/90" />
                                    <div class="mt-1 flex items-center gap-4">
                                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/10 border border-white/20">
                                            <img v-if="logoPreview" :src="logoPreview" alt="Logo preview" class="h-full w-full object-cover" />
                                            <span v-else class="text-xs font-bold text-slate-300">Logo</span>
                                        </div>
                                        <input id="logo" type="file" accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml"
                                            @change="handleLogoChange"
                                            class="block w-full text-sm text-slate-200 file:mr-4 file:rounded-lg file:border-0 file:bg-sky-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white hover:file:bg-sky-500 file:transition" />
                                    </div>
                                    <p class="mt-1 text-xs text-slate-300">PNG, JPG, WEBP or SVG · max 5MB. Shown on your partner profile across MontiERP.</p>
                                    <InputError class="mt-1 text-red-300" :message="form.errors.logo" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                                <div>
                                    <InputLabel for="password" value="Password" class="text-white/90" />
                                    <div class="relative">
                                        <TextInput id="password" :type="showPassword ? 'text' : 'password'"
                                            class="auth-input mt-1 block w-full pr-12"
                                            v-model="form.password" required autocomplete="new-password"
                                            placeholder="••••••••" />
                                        <button type="button" @click="togglePassword"
                                            class="absolute inset-y-0 right-4 flex items-center text-slate-300 hover:text-white">
                                            <svg v-if="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        </button>
                                    </div>
                                    <InputError class="mt-1 text-red-300" :message="form.errors.password" />
                                </div>

                                <div>
                                    <InputLabel for="password_confirmation" value="Confirm Password"
                                        class="text-white/90" />
                                    <div class="relative">
                                        <TextInput id="password_confirmation"
                                            :type="showConfirmPassword ? 'text' : 'password'"
                                            class="auth-input mt-1 block w-full pr-12"
                                            v-model="form.password_confirmation" required placeholder="••••••••"
                                            autocomplete="new-password" />
                                        <button type="button" @click="toggleConfirmPassword"
                                            class="absolute inset-y-0 right-4 flex items-center text-slate-300 hover:text-white">
                                            <svg v-if="!showConfirmPassword" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        </button>
                                    </div>
                                    <InputError class="mt-1 text-red-300"
                                        :message="form.errors.password_confirmation" />
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-8 border-t border-white/10 mt-6">
                                <Link :href="route('client.login')"
                                    class="group text-sm font-medium text-slate-300 hover:text-white transition-colors flex items-center">
                                    <svg class="w-4 h-4 mr-2 transform group-hover:-translate-x-1 transition-transform"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M15 19l-7-7 7-7" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                    Back to Sign in
                                </Link>

                                <PrimaryButton
                                    class="auth-btn w-full px-5 py-2.5 text-xs font-bold text-white sm:w-auto"
                                    :class="{ 'opacity-60 cursor-wait': form.processing }" :disabled="form.processing">
                                    <span v-if="form.processing">Submitting...</span>
                                    <span v-else>Apply for Partnership</span>
                                </PrimaryButton>
                            </div>
                        </form>
    </AuthLayout>
</template>