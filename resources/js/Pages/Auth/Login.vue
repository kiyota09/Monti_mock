<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import Swal from 'sweetalert2';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const page = usePage();
const isLoaded = ref(false);
const showPassword = ref(false);

const inputWarnings = ref({
    identity: '',
});
let warningTimeout: ReturnType<typeof setTimeout>;

onMounted(() => {
    isLoaded.value = true;
});

watch(() => (page.props.flash as any)?.geofence_error, (message) => {
    if (message) {
        Swal.fire({
            title: 'Security Perimeter Block',
            text: message,
            icon: 'error',
            confirmButtonColor: '#2563eb',
            background: '#fff',
            backdrop: `rgba(15, 23, 42, 0.9)`,
            allowOutsideClick: false,
            customClass: {
                popup: 'rounded-[2rem] border-4 border-red-50/10 shadow-2xl',
                title: 'font-black uppercase tracking-tight text-slate-900',
            }
        });
    }
}, { immediate: true });

const form = useForm({
    identity: '',
    password: '',
    remember: false,
});

const togglePassword = () => {
    showPassword.value = !showPassword.value;
};

const triggerWarning = (msg: string) => {
    inputWarnings.value.identity = msg;
    if (warningTimeout) clearTimeout(warningTimeout);
    warningTimeout = setTimeout(() => {
        inputWarnings.value.identity = '';
    }, 3000);
};

const blockInvalidChars = (e: KeyboardEvent) => {
    if (e.key === ' ' || /[^a-zA-Z0-9@.\-_]/.test(e.key)) {
        e.preventDefault();
        triggerWarning('Spaces and special characters are not allowed.');
    }
};

// Pasted text bypasses keypress filters, so sanitize clipboard content
// directly. Without this, pasting an email with spaces/tabs/newlines would
// silently fail validation or look broken.
const sanitizePaste = (e: ClipboardEvent) => {
    e.preventDefault();
    const raw = e.clipboardData?.getData('text') ?? '';
    const cleaned = raw.replace(/\s+/g, '').replace(/[^a-zA-Z0-9@.\-_]/g, '');
    if (!cleaned) {
        triggerWarning('Nothing valid to paste.');
        return;
    }
    if (cleaned !== raw) {
        triggerWarning('Pasted text was cleaned up.');
    }
    form.identity = (form.identity + cleaned).slice(0, 255);
};

watch(() => form.identity, (val) => {
    let filtered = val.replace(/[^a-zA-Z0-9@.\-_]/g, '');

    if (filtered.toLowerCase().startsWith('monti')) {
        filtered = filtered.toUpperCase();
    }

    if (val !== filtered) {
        form.identity = filtered;
        triggerWarning('Invalid character removed.');
    }
});

const submit = () => {
    if (!form.identity) {
        toast.error('Identification is required.');
        triggerWarning('Required field.');
        return;
    }

    if (!form.password) {
        toast.error('Security key is required.');
        return;
    }

    const isEmail = form.identity.includes('@');

    if (isEmail) {
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        if (!emailRegex.test(form.identity)) {
            toast.error('Invalid email format.');
            triggerWarning('Enter a valid email.');
            return;
        }
    } else {
        if (!form.identity.startsWith('MONTI')) {
            toast.error('Employee ID must start with MONTI (e.g. MONTI-1234-5).');
            triggerWarning('Must start with MONTI.');
            return;
        }
    }

    const routeName = isEmail ? 'login' : 'employee.login.store';

    const data = {
        password: form.password,
        remember: form.remember,
        [isEmail ? 'email' : 'employee_id']: form.identity
    };

    form.transform(() => data).post(route(routeName), {
        onSuccess: () => {
            toast.success('Access Granted! Initializing session...');
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Authorization failed. Invalid credentials.';
            toast.error(errorMsg as string);
        },
        onFinish: () => {
            form.reset('password');
        },
    });
};

const identityPlaceholder = computed(() => "user@monticorp.com or EMP-XXXX-X");
</script>

<template>
    <AuthLayout
        title="System Access"
        highlight="Access"
        subtitle="Provide your employee ID or corporate email to initialize access."
        badge="Employee Portal"
        accent="blue"
    >
        <Head title="ERP Secure Authorization | Monti Corp" />

                        <div v-if="status"
                            class="auth-status mb-6 p-4 rounded-lg bg-emerald-500/20 border border-emerald-400/30 text-sm font-medium text-emerald-200 backdrop-blur-sm">
                            {{ status }}
                        </div>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <InputLabel for="identity" value="Identification" class="text-white/90" />
                                <TextInput id="identity" type="text"
                                    class="auth-input mt-1 block w-full"
                                    v-model="form.identity" required autofocus autocomplete="username"
                                    :placeholder="identityPlaceholder" @keypress="blockInvalidChars"
                                    @paste="sanitizePaste" aria-describedby="identity-warning" spellcheck="false" />
                                <p v-if="inputWarnings.identity" id="identity-warning"
                                    class="text-xs text-red-300 font-bold mt-1 ml-1 animate-pulse">{{
                                        inputWarnings.identity }}</p>
                                <InputError class="mt-1 text-red-300" :message="form.errors.identity || (form.errors as any).email || (form.errors as any).employee_id" />
                            </div>

                            <div>
                                <div class="flex justify-between items-baseline mb-1">
                                    <InputLabel for="password" value="Password" class="text-white/90" />
                                    <Link v-if="canResetPassword" :href="route('password.request')"
                                        class="text-sm text-blue-300 hover:text-blue-200 font-medium transition-colors">
                                        Forgot Key?
                                    </Link>
                                </div>

                                <div class="relative">
                                    <TextInput id="password" :type="showPassword ? 'text' : 'password'"
                                        class="auth-input mt-1 block w-full pr-12"
                                        v-model="form.password" required autocomplete="current-password"
                                        placeholder="••••••••" />

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

                            <div class="flex items-center">
                                <label class="flex items-center cursor-pointer group">
                                    <input type="checkbox" v-model="form.remember" class="sr-only peer" />
                                    <div
                                        class="w-5 h-5 border border-white/30 bg-white/15 rounded flex items-center justify-center transition-all duration-200 peer-checked:bg-blue-600 peer-checked:border-blue-600">
                                        <svg v-if="form.remember" class="h-3.5 w-3.5 text-white" fill="currentColor"
                                            viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <span
                                        class="ml-3 text-sm font-medium text-slate-300 group-hover:text-white transition-colors">
                                        Remember me
                                    </span>
                                </label>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-8 border-t border-white/10 mt-6">
                                <div class="text-sm font-medium text-slate-300">
                                    Want to be part of MontiTextile?
                                    <Link :href="route('apply')"
                                        class="text-white hover:text-blue-300 font-semibold ml-1.5 transition-colors">
                                        Apply Now
                                    </Link>
                                </div>

                                <PrimaryButton
                                    class="auth-btn w-full px-5 py-2.5 text-xs font-bold text-white sm:w-auto"
                                    :class="{ 'opacity-60 cursor-wait': form.processing }" :disabled="form.processing">
                                    <span v-if="form.processing">Processing...</span>
                                    <span v-else>Login</span>
                                </PrimaryButton>
                            </div>
                        </form>
    </AuthLayout>
</template>