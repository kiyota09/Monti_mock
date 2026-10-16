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
    business_name: '',
    representative_name: '',
    address: '',
    email: '',
    phone_country: '+63',
    phone_raw: '',
    phone_number: '',
    password: '',
    password_confirmation: '',
});

const isLoaded = ref(false);
const showPassword = ref(false);
const showConfirmPassword = ref(false);

const inputWarnings = ref({
    business_name: '',
    representative_name: '',
    address: '',
    email: '',
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
    if (e.key.length === 1 && !/^[a-zA-Z0-9\s.,#-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning('address', 'Only letters, numbers, spaces, and basic punctuation are allowed.');
    }
};

const blockSpecialForEmail = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9@.\-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning('email', 'Invalid character. Only letters, numbers, @, ., and - are allowed.');
    }
};

watch(() => form.business_name, (val) => {
    const filtered = val.replace(/[^a-zA-Z\s]/g, '');
    if (val !== filtered) {
        form.business_name = filtered;
        triggerWarning('business_name', 'Numbers and special characters are not allowed.');
    }
});

watch(() => form.representative_name, (val) => {
    const filtered = val.replace(/[^a-zA-Z\s]/g, '');
    if (val !== filtered) {
        form.representative_name = filtered;
        triggerWarning('representative_name', 'Numbers and special characters are not allowed.');
    }
});

watch(() => form.address, (val) => {
    const filtered = val.replace(/[^a-zA-Z0-9\s.,#-]/g, '');
    if (val !== filtered) {
        form.address = filtered;
        triggerWarning('address', 'Invalid characters removed.');
    }
});

watch(() => form.email, (val) => {
    const filtered = val.replace(/[^a-zA-Z0-9@.\-]/g, '');
    if (val !== filtered) {
        form.email = filtered;
        triggerWarning('email', 'Invalid character removed.');
    }
});

watch(() => form.phone_raw, (val) => {
    if (/[^\d]/.test(val)) {
        triggerWarning('phone_raw', 'Letters and special characters are not allowed. Numbers only.');
    }
    const filtered = val.replace(/\D/g, '').substring(0, 12);
    if (val !== filtered) form.phone_raw = filtered;
});

const submit = () => {
    if (!/^[a-zA-Z\s]+$/.test(form.business_name)) {
        toast.error('Business Name is required and can only contain letters and spaces.');
        triggerWarning('business_name', 'Invalid format.');
        return;
    }

    if (!/^[a-zA-Z\s]+$/.test(form.representative_name)) {
        toast.error('Representative Name is required and can only contain letters and spaces.');
        triggerWarning('representative_name', 'Invalid format.');
        return;
    }

    if (!/^[a-zA-Z0-9\s.,#-]+$/.test(form.address)) {
        toast.error('Business Address is required and contains invalid characters.');
        triggerWarning('address', 'Invalid format.');
        return;
    }

    if (form.phone_raw.length !== 12 || !/^\d{12}$/.test(form.phone_raw)) {
        toast.error('Phone number must be exactly 12 digits.');
        triggerWarning('phone_raw', 'Must be exactly 12 digits.');
        return;
    }

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailRegex.test(form.email)) {
        toast.error('Please enter a valid email address.');
        triggerWarning('email', 'Invalid email format.');
        return;
    }

    if (form.password.length < 8) {
        toast.error('Password must be at least 8 characters long.');
        return;
    }

    if (form.password !== form.password_confirmation) {
        toast.error('Passwords do not match.');
        return;
    }

    form.phone_number = `${form.phone_country}${form.phone_raw}`;

    form.post(route('supplier.register.store'), {
        onSuccess: () => {
            toast.success('Supplier Business Profile registered successfully!');
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Registration failed. Please check your inputs.';
            toast.error(errorMsg);
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout
        title="Vendor Registration"
        highlight="Registration"
        subtitle="Register as an official vendor for Monti Textile. Submit quotations, receive purchase orders, and grow your business with us."
        badge="Vendor Registration"
        accent="emerald"
        wide
    >
        <Head title="Join Monti ERP - Vendor Registration" />

        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <h3
                                        class="text-sm font-black uppercase tracking-widest text-emerald-300 border-b border-white/20 pb-2 mb-2">
                                        Business Details</h3>
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="business_name" value="Business Name"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="business_name" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.business_name" required autofocus
                                        @keypress="blockNumbersAndSpecial($event, 'business_name')"
                                        placeholder="e.g. Global Threads Inc." />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.business_name" />
                                    <p v-if="inputWarnings.business_name"
                                        class="mt-1 text-xs text-red-300 animate-pulse">{{ inputWarnings.business_name
                                        }}</p>
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="address" value="Business Address"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="address" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.address" required @keypress="blockSpecialForAddress"
                                        placeholder="Complete physical address" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.address" />
                                    <p v-if="inputWarnings.address" class="mt-1 text-xs text-red-300 animate-pulse">{{
                                        inputWarnings.address }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                <div class="md:col-span-2">
                                    <h3
                                        class="text-sm font-black uppercase tracking-widest text-emerald-300 border-b border-white/20 pb-2 mb-2 mt-2">
                                        Account & Contact Info</h3>
                                </div>

                                <div>
                                    <InputLabel for="representative_name" value="Representative Name"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="representative_name" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.representative_name" required
                                        @keypress="blockNumbersAndSpecial($event, 'representative_name')"
                                        placeholder="John Doe" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.representative_name" />
                                    <p v-if="inputWarnings.representative_name"
                                        class="mt-1 text-xs text-red-300 animate-pulse">{{
                                        inputWarnings.representative_name }}</p>
                                </div>

                                <div>
                                    <InputLabel for="phone_raw" value="Phone Number (12 Digits)"
                                        class="text-white/90 font-semibold" />
                                    <div class="flex gap-2 mt-1">
                                        <select v-model="form.phone_country"
                                            class="auth-input w-[35%] text-center">
                                            <option value="+63">+63 (PH)</option>
                                            <option value="+1">+1 (US/CA)</option>
                                            <option value="+44">+44 (UK)</option>
                                            <option value="+61">+61 (AU)</option>
                                            <option value="+81">+81 (JP)</option>
                                            <option value="+65">+65 (SG)</option>
                                            <option value="+971">+971 (AE)</option>
                                        </select>
                                        <TextInput id="phone_raw" type="text" maxlength="12"
                                            class="auth-input w-[65%]"
                                            v-model="form.phone_raw" required
                                            @keypress="blockNonNumeric($event, 'phone_raw')"
                                            placeholder="912345678901" />
                                    </div>
                                    <div class="flex justify-between items-center mt-1 ml-1 pr-1">
                                        <p v-if="inputWarnings.phone_raw"
                                            class="text-xs text-red-300 font-bold animate-pulse">{{
                                            inputWarnings.phone_raw }}</p>
                                        <p v-else class="text-xs text-slate-300 transition-opacity">Numbers only.</p>
                                        <p :class="form.phone_raw.length === 12 ? 'text-emerald-300' : 'text-slate-300'"
                                            class="text-xs font-bold transition-colors">{{ form.phone_raw.length }} / 12
                                        </p>
                                    </div>
                                    <InputError class="mt-1 text-red-300" :message="form.errors.phone_number" />
                                </div>

                                <div class="md:col-span-2">
                                    <InputLabel for="email" value="Business Email (Used for Login)"
                                        class="text-white/90 font-semibold" />
                                    <TextInput id="email" type="email"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.email" required autocomplete="username"
                                        @keypress="blockSpecialForEmail" placeholder="vendor@domain.com" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.email" />
                                    <p v-if="inputWarnings.email" class="mt-1 text-xs text-red-300 animate-pulse">{{
                                        inputWarnings.email }}</p>
                                </div>

                                <div>
                                    <InputLabel for="password" value="Password" class="text-white/90 font-semibold" />
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
                                        class="text-white/90 font-semibold" />
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
                                <Link :href="route('supplier.login')"
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
                                    <span v-else>Register Business</span>
                                </PrimaryButton>
                            </div>
                        </form>
    </AuthLayout>
</template>