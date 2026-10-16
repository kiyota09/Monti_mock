<script setup>
// ============================================
// FILE LOCATION: resources/js/Pages/Auth/VerifyOtp.vue
// PURPOSE: Applicant OTP verification, ported from Auth-1/VerifyOtp.vue
// and aligned to AuthLayout + the applicant OTP backend
// (applicant.verify-otp.store / applicant.resend-otp).
// ============================================

import { ref, onMounted, watch, onUnmounted, computed, nextTick } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Mail, Lock, CheckCircle2, PartyPopper } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const page = usePage();

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    lockout_seconds: {
        type: Number,
        default: 0,
    },
    is_locked: {
        type: Boolean,
        default: false,
    },
    otp_attempts: {
        type: Number,
        default: 0,
    },
    max_attempts: {
        type: Number,
        default: 5,
    },
    lockout_cycle: {
        type: Number,
        default: 0,
    },
    lockout_minutes: {
        type: Number,
        default: 5,
    },
    show_success_modal: {
        type: Boolean,
        default: false,
    },
});

const goDashboard = () => {
    router.visit(route('applicant.dashboard'));
};

// ============================================
// REACTIVE STATE (ported from Auth-1)
// ============================================
const otpDigits = ref(['', '', '', '', '', '']);
const otpError = ref('');
const isVerifying = ref(false);
const isResending = ref(false);
const countdown = ref(0);
const attempts = ref(0);
const isLocked = ref(false);
const lockoutSeconds = ref(0);
const lockoutInitialSeconds = ref(0);
const maxAttempts = ref(5);
const lockoutCycle = ref(0);
const lockoutMinutes = ref(5);

// Success Modal State
const showSuccessModal = ref(false);
const successMessage = ref('');

let countdownInterval = null;
let lockoutInterval = null;
let inputRefs = [];

// ============================================
// COMPUTED
// ============================================
const formattedLockoutTime = computed(() => {
    const seconds = lockoutSeconds.value;
    if (seconds <= 0) return '0s';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    if (mins > 0) {
        return `${mins}m ${secs.toString().padStart(2, '0')}s`;
    }
    return `${secs}s`;
});

const lockoutProgress = computed(() => {
    if (lockoutInitialSeconds.value <= 0) return 0;
    const elapsed = lockoutInitialSeconds.value - lockoutSeconds.value;
    const progress = Math.min((elapsed / lockoutInitialSeconds.value) * 100, 100);
    return Math.round(progress);
});

const lockoutCycleDisplay = computed(() => {
    const cycle = lockoutCycle.value;
    if (cycle === 0) return 'No lockouts';
    const suffix = cycle === 1 ? 'st' : cycle === 2 ? 'nd' : cycle === 3 ? 'rd' : 'th';
    return `${cycle}${suffix} lockout (${lockoutMinutes.value} min)`;
});

const showSuccess = (message) => {
    successMessage.value = message || 'Email verified successfully!';
    showSuccessModal.value = true;
};

const closeSuccessModal = () => {
    showSuccessModal.value = false;
};

// ============================================
// INITIALIZE STATE FROM PROPS
// ============================================
const initializeState = () => {
    attempts.value = props.otp_attempts || 0;
    maxAttempts.value = props.max_attempts || 5;
    lockoutCycle.value = props.lockout_cycle || 0;
    lockoutMinutes.value = props.lockout_minutes || 5;

    if (page.props.flash && page.props.flash.show_success_modal) {
        setTimeout(() => {
            showSuccess('Email verified successfully!');
            setTimeout(() => {
                closeSuccessModal();
                goDashboard();
            }, 3000);
        }, 500);
    }

    if (props.is_locked && props.lockout_seconds > 0) {
        isLocked.value = true;
        lockoutSeconds.value = props.lockout_seconds;
        lockoutInitialSeconds.value = props.lockout_seconds;
        startLockoutCountdown();
    } else {
        isLocked.value = false;
        lockoutSeconds.value = 0;
        lockoutInitialSeconds.value = 0;
    }
};

// ============================================
// LIFECYCLE
// ============================================
onMounted(() => {
    initializeState();

    if (page.props.errors && page.props.errors.otp) {
        otpError.value = page.props.errors.otp;
        if (otpError.value.includes('Lock #')) {
            isLocked.value = true;
            const cycleMatch = otpError.value.match(/Lock #(\d+)/);
            if (cycleMatch) {
                lockoutCycle.value = parseInt(cycleMatch[1]);
            }
            const match = otpError.value.match(/(\d+)\s*m/);
            if (match) {
                const minutes = parseInt(match[1]);
                lockoutSeconds.value = minutes * 60;
                lockoutInitialSeconds.value = minutes * 60;
                lockoutMinutes.value = minutes;
                startLockoutCountdown();
            }
        }
    }

    if (page.props.flash) {
        if (page.props.flash.lockout_seconds && page.props.flash.lockout_seconds > 0) {
            isLocked.value = true;
            lockoutSeconds.value = page.props.flash.lockout_seconds;
            lockoutInitialSeconds.value = page.props.flash.lockout_seconds;
            startLockoutCountdown();
        }
        if (page.props.flash.lockout_cycle) {
            lockoutCycle.value = page.props.flash.lockout_cycle;
        }
        if (page.props.flash.lockout_minutes) {
            lockoutMinutes.value = page.props.flash.lockout_minutes;
        }
        if (page.props.flash.is_locked) {
            isLocked.value = true;
        }
        if (page.props.flash.otp_attempts) {
            attempts.value = page.props.flash.otp_attempts;
        }
    }

    if (!isLocked.value && inputRefs[0]) {
        nextTick(() => {
            inputRefs[0].focus();
        });
    }
});

onUnmounted(() => {
    if (countdownInterval) clearInterval(countdownInterval);
    if (lockoutInterval) clearInterval(lockoutInterval);
});

// ============================================
// LIVE LOCKOUT COUNTDOWN
// ============================================
const startLockoutCountdown = () => {
    if (lockoutInterval) {
        clearInterval(lockoutInterval);
        lockoutInterval = null;
    }

    if (lockoutSeconds.value <= 0) {
        lockoutSeconds.value = lockoutMinutes.value * 60;
        lockoutInitialSeconds.value = lockoutMinutes.value * 60;
    }

    lockoutInterval = setInterval(() => {
        lockoutSeconds.value--;

        if (lockoutSeconds.value <= 0) {
            clearInterval(lockoutInterval);
            lockoutInterval = null;
            isLocked.value = false;
            otpError.value = '';
            toast.success('Lockout expired! You can now try again.');
            if (inputRefs[0]) {
                nextTick(() => {
                    inputRefs[0].focus();
                });
            }
        }
    }, 1000);
};

// ============================================
// OTP INPUT HANDLING
// ============================================
const handleOtpInput = (index, event) => {
    const value = event.target.value;

    if (value && !/^\d$/.test(value)) {
        event.target.value = '';
        return;
    }

    otpDigits.value[index] = value;

    if (otpError.value) {
        otpError.value = '';
    }

    if (value && index < 5) {
        const nextInput = inputRefs[index + 1];
        if (nextInput) {
            nextInput.focus();
        }
    }
};

const handleOtpKeydown = (index, event) => {
    if (event.key === 'Backspace' && !otpDigits.value[index] && index > 0) {
        const prevInput = inputRefs[index - 1];
        if (prevInput) {
            prevInput.focus();
        }
    }
};

const handleOtpPaste = (event) => {
    event.preventDefault();
    const pasteData = event.clipboardData.getData('text');
    const digits = pasteData.replace(/\D/g, '').slice(0, 6);

    if (digits.length > 0) {
        const digitArray = digits.split('');
        for (let i = 0; i < 6; i++) {
            otpDigits.value[i] = digitArray[i] || '';
        }
        const nextIndex = Math.min(digits.length, 5);
        if (inputRefs[nextIndex]) {
            inputRefs[nextIndex].focus();
        }
    }
};

// ============================================
// VERIFY OTP (aligned to system routes)
// ============================================
const verifyOtp = () => {
    if (isLocked.value) {
        otpError.value = `Account is locked. Please wait ${formattedLockoutTime.value}.`;
        return;
    }

    const fullOtp = otpDigits.value.join('');

    if (fullOtp.length !== 6) {
        otpError.value = 'Please enter a valid 6-digit code.';
        return;
    }

    isVerifying.value = true;

    router.post(route('applicant.verify-otp.store'), {
        email: props.email,
        otp: fullOtp,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isVerifying.value = false;
            showSuccess('Email verified successfully!');
            setTimeout(() => {
                closeSuccessModal();
                goDashboard();
            }, 3000);
        },
        onError: (errors) => {
            const errorMsg = errors.otp || 'Invalid verification code. Please try again.';
            otpError.value = errorMsg;

            if (errorMsg.includes('Lock #') || errorMsg.includes('locked')) {
                isLocked.value = true;

                const cycleMatch = errorMsg.match(/Lock #(\d+)/);
                if (cycleMatch) {
                    lockoutCycle.value = parseInt(cycleMatch[1]);
                }

                const match = errorMsg.match(/(\d+)\s*m/);
                if (match) {
                    const minutes = parseInt(match[1]);
                    lockoutSeconds.value = minutes * 60;
                    lockoutInitialSeconds.value = minutes * 60;
                    lockoutMinutes.value = minutes;
                } else {
                    lockoutSeconds.value = 300;
                    lockoutInitialSeconds.value = 300;
                    lockoutMinutes.value = 5;
                }
                startLockoutCountdown();
            }

            if (page.props.flash && page.props.flash.otp_attempts) {
                attempts.value = page.props.flash.otp_attempts;
            } else {
                attempts.value++;
            }

            otpDigits.value = ['', '', '', '', '', ''];
            if (!isLocked.value && inputRefs[0]) {
                nextTick(() => {
                    inputRefs[0].focus();
                });
            }

            isVerifying.value = false;
        },
    });
};

// ============================================
// RESEND OTP (aligned to system routes)
// ============================================
const resendOtp = () => {
    if (isResending.value) return;

    if (isLocked.value) {
        toast.error(`Cannot request new code. Account is locked for ${formattedLockoutTime.value}.`);
        return;
    }

    if (countdown.value > 0) {
        toast.error(`Please wait ${countdown.value} seconds before requesting another code.`);
        return;
    }

    isResending.value = true;

    router.post(route('applicant.resend-otp'), {
        email: props.email,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.success('New verification code sent to your email.');
            startResendCountdown();
            isResending.value = false;
            otpError.value = '';
            otpDigits.value = ['', '', '', '', '', ''];
            if (inputRefs[0]) {
                nextTick(() => {
                    inputRefs[0].focus();
                });
            }
            isLocked.value = false;
            lockoutSeconds.value = 0;
            lockoutInitialSeconds.value = 0;
            if (lockoutInterval) {
                clearInterval(lockoutInterval);
                lockoutInterval = null;
            }
        },
        onError: (errors) => {
            const errorMsg = errors.email || 'Failed to resend code. Please try again.';
            toast.error(errorMsg);
            isResending.value = false;

            if (errorMsg.includes('Lock #') || errorMsg.includes('locked')) {
                isLocked.value = true;
                const match = errorMsg.match(/(\d+)\s*m/);
                if (match) {
                    const minutes = parseInt(match[1]);
                    lockoutSeconds.value = minutes * 60;
                    lockoutInitialSeconds.value = minutes * 60;
                    lockoutMinutes.value = minutes;
                } else {
                    lockoutSeconds.value = 300;
                    lockoutInitialSeconds.value = 300;
                    lockoutMinutes.value = 5;
                }
                startLockoutCountdown();
            }

            if (errorMsg.includes('wait') && !errorMsg.includes('locked')) {
                const match = errorMsg.match(/(\d+)\s*seconds/);
                if (match) {
                    countdown.value = parseInt(match[1]);
                    startResendCountdown();
                }
            }
        },
    });
};

// ============================================
// RESEND COUNTDOWN (60 seconds)
// ============================================
const startResendCountdown = () => {
    countdown.value = 60;
    if (countdownInterval) clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }, 1000);
};

// ============================================
// WATCH OTP
// ============================================
watch(otpDigits, (newVal) => {
    const fullOtp = newVal.join('');
    if (fullOtp.length === 6 && !isVerifying.value && !isLocked.value && !showSuccessModal.value) {
        verifyOtp();
    }
}, { deep: true });

watch(() => props.lockout_seconds, (newVal) => {
    if (newVal > 0 && !isLocked.value) {
        isLocked.value = true;
        lockoutSeconds.value = newVal;
        lockoutInitialSeconds.value = newVal;
        startLockoutCountdown();
    }
});

watch(() => props.is_locked, (newVal) => {
    if (newVal && props.lockout_seconds > 0) {
        isLocked.value = true;
        lockoutSeconds.value = props.lockout_seconds;
        lockoutInitialSeconds.value = props.lockout_seconds;
        startLockoutCountdown();
    }
});
</script>

<template>
    <AuthLayout
        title="Verify Your Email"
        highlight="Email"
        subtitle="Enter the 6-digit verification code we sent to your email address."
        badge="Applicant Portal"
        accent="sky"
    >
        <Head title="Verify Email | Monti Textile" />

        <p class="text-center text-white font-semibold text-sm -mt-4 mb-6">{{ email }}</p>

        <div class="space-y-6">
            <!-- PROGRESSIVE LOCKOUT WARNING (ported from Auth-1) -->
            <div v-if="isLocked"
                class="bg-red-500/20 border border-red-500/30 rounded-xl p-4 text-center">
                <div class="mb-2 flex justify-center"><Lock class="w-6 h-6 text-red-400" /></div>
                <p class="text-sm font-bold text-red-400">
                    Account Locked
                </p>
                <p class="text-sm text-red-300/80 mt-1">
                    {{ lockoutCycleDisplay }}
                </p>
                <p class="text-xs text-red-300/60 mt-1">
                    Too many failed attempts
                </p>
                <p class="text-3xl font-bold text-white mt-2 font-mono">
                    {{ formattedLockoutTime }}
                </p>
                <p class="text-xs text-red-300/60 mt-1">
                    Please wait before trying again
                </p>
                <div class="mt-3 h-2 w-full bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-red-500 to-orange-400 rounded-full transition-all duration-1000"
                        :style="{ width: `${lockoutProgress}%` }">
                    </div>
                </div>
                <p class="text-xs text-red-300/50 mt-1">
                    {{ lockoutProgress }}% complete
                </p>
            </div>

            <!-- OTP Input -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-3 text-center">
                    Enter 6-digit verification code
                </label>
                <div class="flex justify-center gap-2 sm:gap-3">
                    <input
                        v-for="(digit, index) in 6"
                        :key="index"
                        :ref="el => { if (el) inputRefs[index] = el }"
                        type="text"
                        maxlength="1"
                        inputmode="numeric"
                        v-model="otpDigits[index]"
                        @input="handleOtpInput(index, $event)"
                        @keydown="handleOtpKeydown(index, $event)"
                        @paste="handleOtpPaste"
                        class="otp-input w-10 h-12 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-mono tracking-wider rounded-lg border-2 transition-all duration-200 bg-white/10 text-white placeholder:text-slate-500 focus:ring-2 focus:ring-sky-500 focus:border-transparent focus:bg-white/20 outline-none"
                        :class="{
                            'border-white/30': !otpError,
                            'border-red-500 ring-2 ring-red-500/50': otpError,
                            'border-sky-500 bg-sky-500/10': otpDigits[index] && !otpError,
                            'opacity-50 cursor-not-allowed': isVerifying || isLocked
                        }"
                        :disabled="isVerifying || isLocked"
                    />
                </div>
                <p v-if="otpError" class="text-xs text-red-300 mt-3 text-center animate-pulse">
                    {{ otpError }}
                </p>
                <p v-if="attempts > 0" class="text-[11px] text-slate-400 mt-2 text-center">
                    Attempts: {{ attempts }} / {{ maxAttempts }}
                </p>
            </div>

            <!-- Resend -->
            <div class="text-center">
                <p class="text-sm text-slate-400">
                    Didn't receive the code?
                </p>
                <button
                    @click="resendOtp"
                    :disabled="isResending || countdown > 0 || isLocked"
                    class="text-sm text-sky-300 hover:text-sky-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium mt-1"
                >
                    <span v-if="isLocked" class="inline-flex items-center gap-1.5">
                        <Lock class="w-4 h-4" /> Locked ({{ formattedLockoutTime }})
                    </span>
                    <span v-else-if="isResending">Sending...</span>
                    <span v-else-if="countdown > 0">Resend in {{ countdown }}s</span>
                    <span v-else>Resend Code</span>
                </button>
            </div>

            <!-- Verify Button -->
            <PrimaryButton
                @click="verifyOtp"
                :disabled="otpDigits.join('').length !== 6 || isVerifying || isLocked || showSuccessModal"
                class="auth-btn w-full py-3 text-sm"
                :class="{ 'opacity-60 cursor-wait': isVerifying || isLocked }"
            >
                <span v-if="isLocked" class="inline-flex items-center gap-1.5"><Lock class="w-4 h-4" /> Locked ({{ formattedLockoutTime }})</span>
                <span v-else-if="isVerifying">Verifying...</span>
                <span v-else-if="showSuccessModal" class="inline-flex items-center gap-1.5"><CheckCircle2 class="w-4 h-4" /> Verified!</span>
                <span v-else>Verify Email</span>
            </PrimaryButton>

            <div class="text-center pt-4 border-t border-white/10">
                <Link :href="route('applicant.register')" class="text-sm text-slate-400 hover:text-white transition-colors">
                    ← Back to Registration
                </Link>
            </div>
        </div>

        <!-- SUCCESS MODAL (ported from Auth-1) -->
        <div v-if="showSuccessModal"
            class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-fadeIn">

                <div class="px-6 py-4 border-b border-gray-200 bg-green-50 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <CheckCircle2 class="w-6 h-6 text-green-600" />
                        <h3 class="text-lg font-bold text-green-700">Success!</h3>
                    </div>
                    <button @click="closeSuccessModal" class="p-1.5 hover:bg-gray-200 rounded-lg text-gray-500 hover:text-gray-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-8 text-center">
                    <div class="mb-4 flex justify-center"><PartyPopper class="w-8 h-8 text-green-600" /></div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">
                        Email Verified Successfully!
                    </h2>
                    <p class="text-gray-600 text-sm">
                        {{ successMessage }}
                    </p>

                    <div class="mt-6 h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-green-500 rounded-full"
                            style="width: 100%; animation: progressBar 3s linear forwards;">
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        Redirecting to dashboard...
                    </p>
                </div>
            </div>
        </div>
    </AuthLayout>
</template>

<style scoped>
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus,
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 30px rgba(255, 255, 255, 0.08) inset !important;
    -webkit-text-fill-color: white !important;
}

.otp-input {
    transition: all 0.3s ease-in-out;
}

.otp-input:focus {
    transform: scale(1.05);
}

input[type="text"]::-webkit-outer-spin-button,
input[type="text"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="text"] {
    -moz-appearance: textfield;
}

.transition-all {
    transition: width 1s linear;
}

.font-mono {
    font-family: 'Courier New', monospace;
}

@keyframes progressBar {
    from {
        width: 0%;
    }
    to {
        width: 100%;
    }
}

.animate-fadeIn {
    animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
</style>
