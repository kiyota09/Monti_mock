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
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    position: '',
});

const isLoaded = ref(false);
const showPassword = ref(false);
const showConfirmPassword = ref(false);

const inputWarnings = ref({
    name: '',
    email: '',
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

// --- 1. PHYSICAL KEYPRESS BLOCKS ---
const blockNumbersAndSpecial = (e, field) => {
    if (e.key.length === 1 && !/^[a-zA-Z\s]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning(field, 'Numbers and special characters are not allowed.');
    }
};

const blockSpecialForEmail = (e) => {
    if (e.key.length === 1 && !/^[a-zA-Z0-9@.\-]$/.test(e.key)) {
        e.preventDefault();
        triggerWarning('email', 'Invalid character. Only letters, numbers, @, ., and - are allowed.');
    }
};

// --- 2. PASTE SANITIZATION (paste bypasses keypress, so clean + warn) ---
const sanitizeNamePaste = (e) => {
    e.preventDefault();
    const raw = e.clipboardData?.getData('text') ?? '';
    const cleaned = raw.replace(/[^a-zA-Z\s]/g, '').slice(0, 255);
    if (!cleaned) {
        triggerWarning('name', 'Nothing valid to paste.');
        return;
    }
    if (cleaned !== raw) triggerWarning('name', 'Pasted text was cleaned up.');
    form.name = (form.name + cleaned).slice(0, 255);
};

const sanitizeEmailPaste = (e) => {
    e.preventDefault();
    const raw = e.clipboardData?.getData('text') ?? '';
    const cleaned = raw.replace(/\s+/g, '').replace(/[^a-zA-Z0-9@.\-]/g, '').slice(0, 255);
    if (!cleaned) {
        triggerWarning('email', 'Nothing valid to paste.');
        return;
    }
    if (cleaned !== raw) triggerWarning('email', 'Pasted text was cleaned up.');
    form.email = (form.email + cleaned).slice(0, 255);
};

watch(() => form.name, (val) => {
    const filtered = val.replace(/[^a-zA-Z\s]/g, '');
    if (val !== filtered) {
        form.name = filtered;
        triggerWarning('name', 'Invalid character removed.');
    }
});

watch(() => form.email, (val) => {
    const filtered = val.replace(/[^a-zA-Z0-9@.\-]/g, '');
    if (val !== filtered) {
        form.email = filtered;
        triggerWarning('email', 'Invalid character removed.');
    }
});

// --- 3. HARD SUBMIT LOCKS ---
const submit = () => {
    if (!/^[a-zA-Z\s]+$/.test(form.name)) {
        toast.error('Full Name is required and can only contain letters and spaces.');
        triggerWarning('name', 'Invalid format.');
        return;
    }

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailRegex.test(form.email)) {
        toast.error('Please enter a valid email address.');
        triggerWarning('email', 'Invalid email format.');
        return;
    }

    if (!form.role) {
        toast.error('Please select a Department / Role.');
        return;
    }

    if (!form.position) {
        toast.error('Please select a Position Level.');
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

    form.post(route('register'), {
        onSuccess: () => {
            toast.success('Account registered successfully!');
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
        title="Personnel Registration"
        highlight="Registration"
        subtitle="Provide accurate details for your employee record. All accounts are subject to administrative approval."
        badge="Employee Registration"
        accent="blue"
        wide
    >
        <Head title="Employee Registration | Monti Corp" />

        <form @submit.prevent="submit" class="space-y-6">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <div>
                                    <InputLabel for="name" value="Full Name (Legal)" class="text-white/90" />
                                    <TextInput id="name" type="text"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.name" required autofocus autocomplete="name"
                                        @keypress="blockNumbersAndSpecial($event, 'name')" @paste="sanitizeNamePaste"
                                        spellcheck="false" placeholder="John Doe" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.name" />
                                    <p v-if="inputWarnings.name" class="mt-1 text-xs text-red-300 animate-pulse">{{
                                        inputWarnings.name }}</p>
                                </div>

                                <div>
                                    <InputLabel for="email" value="Corporate Email Address" class="text-white/90" />
                                    <TextInput id="email" type="email"
                                        class="auth-input mt-1 block w-full"
                                        v-model="form.email" required autocomplete="username"
                                        @keypress="blockSpecialForEmail" @paste="sanitizeEmailPaste"
                                        spellcheck="false" placeholder="employee@monticorp.com" />
                                    <InputError class="mt-1 text-red-300" :message="form.errors.email" />
                                    <p v-if="inputWarnings.email" class="mt-1 text-xs text-red-300 animate-pulse">{{
                                        inputWarnings.email }}</p>
                                </div>

                                <div>
                                    <InputLabel for="role" value="Department / Role" class="text-white/90" />
                                    <select id="role" v-model="form.role"
                                        class="auth-input mt-1 block w-full"
                                        required>
                                        <option value="" disabled selected>Select Department...</option>
                                        <option value="HRM">HRM (Human Resources)</option>
                                        <option value="SCM">SCM (Supply Chain)</option>
                                        <option value="FIN">FIN (Finance)</option>
                                        <option value="MAN">MAN (Manufacturing)</option>
                                        <option value="INV">INV (Inventory)</option>
                                        <option value="ORD">ORD (Orders)</option>
                                        <option value="WAR">WAR (Warehouse)</option>
                                        <option value="CRM">CRM (Customer Relations)</option>
                                        <option value="ECO">ECO (E-Commerce)</option>
                                        <option value="PRO">PRO (Procurement)</option>
                                        <option value="PROJ">PROJ (Projects)</option>
                                        <option value="IT">IT (Systems)</option>
                                        <option value="HRM">General / Trainee</option>
                                    </select>
                                    <InputError class="mt-1 text-red-300" :message="form.errors.role" />
                                </div>

                                <div>
                                    <InputLabel for="position" value="Position Level" class="text-white/90" />
                                    <select id="position" v-model="form.position"
                                        class="auth-input mt-1 block w-full"
                                        required>
                                        <option value="" disabled selected>Select Level...</option>
                                        <option value="manager">Department Manager</option>
                                        <option value="staff">Department Staff</option>
                                        <option value="trainee">Trainee</option>
                                    </select>
                                    <InputError class="mt-1 text-red-300" :message="form.errors.position" />
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

                            <div class="bg-blue-900/30 p-4 rounded-lg border border-blue-400/30 mt-6 backdrop-blur-sm">
                                <p class="text-xs text-blue-200 font-medium">
                                    <span class="font-black uppercase tracking-widest block mb-1">⚠️ Approval
                                        Required</span>
                                    Your account will require administrative approval before you can access modules
                                    matching your role.
                                </p>
                            </div>

                            <div
                                class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-8 border-t border-white/10 mt-6">
                                <Link :href="route('login')"
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
                                    <span v-if="form.processing">Processing...</span>
                                    <span v-else>Register Account</span>
                                </PrimaryButton>
                            </div>

                        </form>
    </AuthLayout>
</template>