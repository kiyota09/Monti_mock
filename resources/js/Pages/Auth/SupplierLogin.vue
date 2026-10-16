<script setup>
import { onMounted, ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const isLoaded = ref(false);
const showPassword = ref(false);

onMounted(() => {
    isLoaded.value = true;
});

const togglePassword = () => {
    showPassword.value = !showPassword.value;
};

const submit = () => {
    form.post(route('supplier.login.store'), {
        onSuccess: () => {
            toast.success('Successfully signed in to Supplier Hub!');
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Login failed. Please check your credentials.';
            toast.error(errorMsg);
        },
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthLayout
        title="Supplier Portal"
        highlight="Portal"
        subtitle="Welcome back! Access requests for quotation, manage purchase orders, and process invoices securely."
        badge="Supplier Gateway"
        accent="emerald"
    >
        <Head title="Supplier Portal Login | Monti Textile" />

        <div v-if="$page.props.status"
            class="auth-status mb-6 p-4 rounded-lg bg-emerald-500/20 border border-emerald-400/30 text-sm font-medium text-emerald-200 backdrop-blur-sm">
            {{ $page.props.status }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <InputLabel for="email" value="Business Email" class="text-white/90" />
                                <TextInput id="email" type="email"
                                    class="auth-input mt-1 block w-full"
                                    v-model="form.email" required autofocus autocomplete="username"
                                    placeholder="supplier@company.com" />
                                <InputError class="mt-1 text-red-300" :message="form.errors.email" />
                            </div>

                            <div>
                                <div class="flex justify-between items-baseline mb-1">
                                    <InputLabel for="password" value="Password" class="text-white/90" />
                                    <Link v-if="$page.props.canResetPassword" :href="route('password.request')"
                                        class="text-sm text-emerald-300 hover:text-emerald-200 font-medium transition-colors">
                                        Forgot password?
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
                                        class="w-5 h-5 border border-white/30 bg-white/15 rounded flex items-center justify-center transition-all duration-200 peer-checked:bg-emerald-500 peer-checked:border-emerald-500">
                                        <svg v-if="form.remember" class="h-3.5 w-3.5 text-white" fill="currentColor"
                                            viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <span
                                        class="ml-3 text-sm font-medium text-slate-300 group-hover:text-white transition-colors">
                                        Remember my account
                                    </span>
                                </label>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-8 border-t border-white/10 mt-6">
                                <div class="text-sm font-medium text-slate-300">
                                    Not registered?
                                    <Link :href="route('supplier.register')"
                                        class="text-white hover:text-emerald-300 font-semibold ml-1.5 transition-colors">
                                        Apply Here
                                    </Link>
                                </div>

                                <PrimaryButton
                                    class="auth-btn w-full px-5 py-2.5 text-xs font-bold text-white sm:w-auto"
                                    :class="{ 'opacity-60 cursor-wait': form.processing }" :disabled="form.processing">
                                    <span v-if="form.processing">Authenticating...</span>
                                    <span v-else>Sign In</span>
                                </PrimaryButton>
                            </div>
                        </form>
    </AuthLayout>
</template>