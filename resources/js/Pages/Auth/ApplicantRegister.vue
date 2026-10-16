<script setup lang="ts">
// ============================================
// FILE LOCATION: resources/js/Pages/Auth/ApplicantRegister.vue
// STRUCTURE: follows Auth-1/Register.vue (email + password + OTP notice
// + lockout handling), aligned to AuthLayout and this project's
// `applicant.register.store` endpoint.
// ============================================

import { onMounted, onUnmounted, ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { Lock, Mail } from 'lucide-vue-next';

const page = usePage();

// Same fields as Auth-1/Register.vue. Profile details are completed
// later in the applicant portal after OTP verification.
const form = useForm({
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);
const isSubmitting = ref(false);
const lockoutMessage = ref('');
const isLocked = ref(false);
const lockoutSeconds = ref(0);
let lockoutInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    const flash = page.props.flash as any;
    if (flash?.lockout_seconds && flash.lockout_seconds > 0) {
        isLocked.value = true;
        lockoutSeconds.value = flash.lockout_seconds;
        lockoutMessage.value = flash.info || 'Account is temporarily locked. Please try again later.';
        startLockoutCountdown();
    }
});

onUnmounted(() => {
    if (lockoutInterval) clearInterval(lockoutInterval);
});

const togglePassword = () => {
    showPassword.value = !showPassword.value;
};

const toggleConfirmPassword = () => {
    showConfirmPassword.value = !showConfirmPassword.value;
};

const formatTime = (seconds: number) => {
    if (seconds <= 0) return '0s';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    if (mins > 0) {
        return `${mins}m ${secs}s`;
    }
    return `${secs}s`;
};

const submit = () => {
    if (isLocked.value) {
        toast.error(lockoutMessage.value || `Account is temporarily locked (${formatTime(lockoutSeconds.value)}).`);
        return;
    }

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailRegex.test(form.email)) {
        toast.error('Please enter a valid email address.');
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

    isSubmitting.value = true;

    form.post(route('applicant.register.store'), {
        preserveScroll: true,
        onSuccess: (response: any) => {
            const flash = response?.props?.flash || (page.props.flash as any);
            if (flash?.info) {
                toast.info(flash.info);
            } else {
                toast.success('Registration successful! Check your email for verification code.');
            }
            isSubmitting.value = false;
            form.reset('password', 'password_confirmation');
            isLocked.value = false;
            lockoutMessage.value = '';
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Registration failed. Please try again.';

            // Check if lockout message
            if (typeof errorMsg === 'string' && (errorMsg.includes('locked') || errorMsg.includes('temporarily locked'))) {
                isLocked.value = true;
                lockoutMessage.value = errorMsg;

                // Try to get lockout seconds from flash
                const flash = page.props.flash as any;
                if (flash?.lockout_seconds) {
                    lockoutSeconds.value = flash.lockout_seconds;
                    startLockoutCountdown();
                }

                toast.error(errorMsg);
            } else if (typeof errorMsg === 'string' && errorMsg.includes('already registered and verified')) {
                toast.error(errorMsg);
                setTimeout(() => {
                    router.visit(route('applicant.login'));
                }, 2000);
            } else {
                toast.error(errorMsg as string);
            }

            isSubmitting.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const startLockoutCountdown = () => {
    if (lockoutInterval) clearInterval(lockoutInterval);
    lockoutInterval = setInterval(() => {
        lockoutSeconds.value--;
        if (lockoutSeconds.value <= 0) {
            if (lockoutInterval) clearInterval(lockoutInterval);
            lockoutInterval = null;
            isLocked.value = false;
            lockoutMessage.value = '';
            toast.info('Lockout expired! You can now try registering again.');
        }
    }, 1000);
};
</script>

<template>
    <AuthLayout
        title="Applicant Registration"
        highlight="Register"
        subtitle="Register to apply for positions and track your applications."
        badge="Applicant Portal"
        accent="sky"
    >
        <Head title="Monti Textile - Applicant Registration" />

        <!-- Lockout Warning -->
        <div v-if="isLocked"
            class="mb-6 bg-red-500/20 border border-red-500/30 rounded-xl p-4 text-center">
            <div class="mb-1 flex justify-center"><Lock class="w-6 h-6 text-red-400" /></div>
            <p class="text-sm font-medium text-red-400">
                Account is temporarily locked
            </p>
            <p class="text-xs text-red-300/80 mt-1">
                {{ lockoutMessage }} <span v-if="lockoutSeconds > 0">({{ formatTime(lockoutSeconds) }})</span>
            </p>
            <div class="mt-3 h-1.5 w-full bg-white/10 rounded-full overflow-hidden">
                <div class="h-full bg-red-400 rounded-full transition-all duration-1000"
                    :style="{ width: `${((60 * 10 - lockoutSeconds) / (60 * 10)) * 100}%` }">
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="email" value="Email Address" class="text-white/90" />
                <TextInput id="email" type="email"
                    class="auth-input mt-1 block w-full"
                    v-model="form.email" required autofocus autocomplete="username" placeholder="applicant@example.com" />
                <InputError class="mt-1 text-red-300" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" class="text-white/90" />
                <div class="relative">
                    <TextInput id="password" :type="showPassword ? 'text' : 'password'"
                        class="auth-input mt-1 block w-full pr-12"
                        v-model="form.password" required autocomplete="new-password" placeholder="••••••••" />
                    <button type="button" @click="togglePassword"
                        class="absolute inset-y-0 right-4 flex items-center text-slate-300 hover:text-white transition-colors">
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
                <InputLabel for="password_confirmation" value="Confirm Password" class="text-white/90" />
                <div class="relative">
                    <TextInput id="password_confirmation"
                        :type="showConfirmPassword ? 'text' : 'password'"
                        class="auth-input mt-1 block w-full pr-12"
                        v-model="form.password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
                    <button type="button" @click="toggleConfirmPassword"
                        class="absolute inset-y-0 right-4 flex items-center text-slate-300 hover:text-white transition-colors">
                        <svg v-if="!showConfirmPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
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
                <InputError class="mt-1 text-red-300" :message="form.errors.password_confirmation" />
            </div>

            <div class="bg-blue-900/20 p-3 rounded-lg border border-blue-400/20 backdrop-blur-sm">
                <p class="text-[11px] text-blue-200/80 font-medium">
                    <span class="font-bold uppercase tracking-wider block mb-0.5 text-blue-300 flex items-center gap-1"><Mail class="w-4 h-4" /> Email Verification Required</span>
                    You will receive a verification code to confirm your email address.
                </p>
            </div>

            <p class="text-xs text-slate-400">
                Already applied via the public form with this email? Registering claims that
                application into your new portal account. Complete your profile after verification.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-6 border-t border-white/10 mt-6">
                <Link :href="route('applicant.login')"
                    class="group text-sm font-medium text-slate-300 hover:text-white transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2 transform group-hover:-translate-x-1 transition-transform"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M15 19l-7-7 7-7" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                    Already have an account?
                </Link>

                <PrimaryButton
                    class="auth-btn w-full px-5 py-2.5 text-xs font-bold text-white sm:w-auto"
                    :class="{ 'opacity-60 cursor-wait': form.processing || isSubmitting || isLocked }"
                    :disabled="form.processing || isSubmitting || isLocked">
                    <span v-if="isLocked" class="inline-flex items-center gap-1"><Lock class="w-4 h-4" /> Locked</span>
                    <span v-else-if="form.processing || isSubmitting">Processing...</span>
                    <span v-else>Sign Up</span>
                </PrimaryButton>
            </div>
        </form>
    </AuthLayout>
</template>
