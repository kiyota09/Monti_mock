<script setup lang="ts">
// ============================================
// FILE LOCATION: resources/js/Pages/Dashboard/APPLICANTS/Account/index.vue
// PURPOSE: Account settings page with modern UI
// ============================================

import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { User, CheckCircle2, AlertTriangle, BarChart3, Ban, LockKeyhole, KeyRound, Link2, Mail, Lightbulb, Briefcase, Bell, FileText } from 'lucide-vue-next';

// ============================================
// PROPS
// ============================================
const props = defineProps<{
    applicant?: {
        id: number;
        email: string;
        first_name: string;
        last_name: string;
        email_verified_at: string | null;
        created_at: string;
        updated_at: string;
    };
}>();

// ============================================
// STATE
// ============================================
const showPasswordModal = ref(false);
const showVerificationModal = ref(false);
const isSubmitting = ref(false);
const showNoticeModal = ref(false);
const showDeactivateModal = ref(false);
const noticeTitle = ref('');
const noticeMessage = ref('');
const noticeIsError = ref(false);

function showNotice(title: string, message: unknown, isError = false) {
    noticeTitle.value = title;
    noticeMessage.value = String(message || 'Please try again.');
    noticeIsError.value = isError;
    showNoticeModal.value = true;
}

// ============================================
// PASSWORD FORM
// ============================================
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// ============================================
// ACCOUNT INFO
// ============================================
const accountInfo = computed(() => ({
    email: props.applicant?.email || 'Not set',
    fullName: `${props.applicant?.first_name || ''} ${props.applicant?.last_name || ''}`.trim() || 'Not set',
    emailVerified: props.applicant?.email_verified_at !== null,
    memberSince: props.applicant?.created_at ? new Date(props.applicant.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A',
}));

// ============================================
// FUNCTIONS
// ============================================

function changePassword() {
    isSubmitting.value = true;
    
    passwordForm.post(route('applicant.account.password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            showPasswordModal.value = false;
            passwordForm.reset();
            showNotice('Password Updated', 'Your password was changed successfully.');
        },
        onError: (errors) => {
            isSubmitting.value = false;
            const errorMsg = Object.values(errors)[0] || 'Failed to update password. Please try again.';
            showNotice('Unable to Update Password', errorMsg, true);
        }
    });
}

function sendVerification() {
    router.post(route('applicant.account.email.verify'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showVerificationModal.value = false;
            showNotice('Verification Email Sent', 'Please check your inbox for the verification link.');
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Failed to send verification email.';
            showNotice('Unable to Send Email', errorMsg, true);
        }
    });
}

function deactivateAccount() {
    showDeactivateModal.value = true;
}

function confirmDeactivateAccount() {
    showDeactivateModal.value = false;
    router.post(route('applicant.account.deactivate'), {}, {
            preserveScroll: true,
            onSuccess: () => {
                showNotice('Account Deactivated', 'Your account has been deactivated.');
            },
            onError: (errors) => {
                const errorMsg = Object.values(errors)[0] || 'Failed to deactivate account.';
                showNotice('Unable to Deactivate Account', errorMsg, true);
            }
        });
}

function closePasswordModal() {
    showPasswordModal.value = false;
    passwordForm.reset();
    passwordForm.clearErrors();
}

function closeVerificationModal() {
    showVerificationModal.value = false;
}
</script>

<template>
    <Head title="Account Settings - Monti ERP" />

    <AuthenticatedLayout>
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Account <span class="text-blue-600">Settings</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Manage your account and security settings.</p>
            </div>
            <Link
                :href="route('applicant.profile.index')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-sm font-bold rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition"
            >
                <User class="w-4 h-4" /> Edit Profile
            </Link>
        </div>

                <!-- Account Settings Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    
                    <!-- LEFT COLUMN -->
                    <div class="space-y-4">
                        <!-- Account Information -->
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                                    <User class="w-5 h-5 text-slate-400" /> Account Information
                                </h3>
                            </div>
                            <div class="p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Full Name</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ accountInfo.fullName }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Email Address</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ accountInfo.email }}</p>
                                    </div>
                                    <span 
                                        v-if="accountInfo.emailVerified"
                                        class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-full text-[11px] font-bold border border-emerald-200 dark:border-emerald-800"
                                    >
                                        <CheckCircle2 class="w-4 h-4" /> Verified
                                    </span>
                                    <button 
                                        v-else
                                        @click="showVerificationModal = true"
                                        class="px-3 py-1 bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 rounded-full text-[11px] font-bold border border-amber-200 dark:border-amber-800 inline-flex items-center gap-1"
                                    >
                                        <AlertTriangle class="w-4 h-4" /> Verify
                                    </button>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Member Since</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ accountInfo.memberSince }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Account Status -->
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                                    <BarChart3 class="w-5 h-5 text-slate-400" /> Account Status
                                </h3>
                            </div>
                            <div class="p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">Active</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-full text-[11px] font-bold border border-emerald-200 dark:border-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Active
                                    </span>
                                </div>
                                <div class="pt-3 border-t border-slate-100 dark:border-slate-700">
                                    <button 
                                        @click="deactivateAccount"
                                        class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-rose-600 dark:text-rose-400 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1"
                                    >
                                        <Ban class="w-4 h-4" /> Deactivate Account
                                    </button>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-400 mt-1 text-center">You can reactivate your account anytime</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="space-y-4">
                        <!-- Security -->
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                                    <LockKeyhole class="w-5 h-5 text-slate-400" /> Security
                                </h3>
                            </div>
                            <div class="p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Password</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">••••••••••</p>
                                    </div>
                                    <button 
                                        @click="showPasswordModal = true"
                                        class="px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95 inline-flex items-center gap-1"
                                    >
                                        <KeyRound class="w-4 h-4" /> Change
                                    </button>
                                </div>
                                <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-700">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Email Verification</p>
<p class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                        <CheckCircle2 v-if="accountInfo.emailVerified" class="w-4 h-4" />
                                        <AlertTriangle v-else class="w-4 h-4" />
                                        {{ accountInfo.emailVerified ? 'Verified' : 'Not Verified' }}
                                    </p>
                                    </div>
                                    <button 
                                        v-if="!accountInfo.emailVerified"
                                        @click="showVerificationModal = true"
                                        class="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-blue-500/30 inline-flex items-center gap-1"
                                    >
                                        <Mail class="w-4 h-4" /> Verify
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                                    <Link2 class="w-5 h-5 text-slate-400" /> Quick Links
                                </h3>
                            </div>
                            <div class="p-4 space-y-2">
                                <Link 
                                    :href="route('applicant.profile.index')"
                                    class="flex items-center justify-between p-2.5 bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-700 transition group"
                                >
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><User class="w-4 h-4 inline-block" /> Edit Profile</span>
                                    <span class="text-xs text-slate-400 group-hover:text-blue-600 transition">→</span>
                                </Link>
                                <Link 
                                    :href="route('applicant.applications.index')"
                                    class="flex items-center justify-between p-2.5 bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-700 transition group"
                                >
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><FileText class="w-4 h-4 inline-block" /> My Applications</span>
                                    <span class="text-xs text-slate-400 group-hover:text-blue-600 transition">→</span>
                                </Link>
                                <Link 
                                    :href="route('applicant.jobs.index')"
                                    class="flex items-center justify-between p-2.5 bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-700 transition group"
                                >
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><Briefcase class="w-4 h-4 inline-block" /> Browse Jobs</span>
                                    <span class="text-xs text-slate-400 group-hover:text-blue-600 transition">→</span>
                                </Link>
                                <Link 
                                    :href="route('applicant.notifications.index')"
                                    class="flex items-center justify-between p-2.5 bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-700 transition group"
                                >
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><Bell class="w-4 h-4 inline-block" /> Notifications</span>
                                    <span class="text-xs text-slate-400 group-hover:text-blue-600 transition">→</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-4 text-xs text-slate-400 dark:text-slate-400">
                    © 2026 Monti Textile Manufacturing Corp. All rights reserved.
                </div>

        <!-- ============================================ -->
        <!-- ✅ CHANGE PASSWORD MODAL                     -->
        <!-- ============================================ -->
        <div 
            v-if="showPasswordModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="closePasswordModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md max-h-[90vh] overflow-y-auto p-6">
                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">Change Password</h3>
                    <button 
                        @click="closePasswordModal"
                        class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form @submit.prevent="changePassword" class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Current Password</label>
                        <input 
                            v-model="passwordForm.current_password"
                            type="password" 
                            class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-slate-700 dark:text-slate-200"
                            placeholder="Enter current password"
                            required
                        />
                        <p v-if="passwordForm.errors.current_password" class="text-xs text-rose-600 mt-1">
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">New Password</label>
                        <input 
                            v-model="passwordForm.password"
                            type="password" 
                            class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-slate-700 dark:text-slate-200"
                            placeholder="Enter new password"
                            required
                        />
                        <p v-if="passwordForm.errors.password" class="text-xs text-rose-600 mt-1">
                            {{ passwordForm.errors.password }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Confirm New Password</label>
                        <input 
                            v-model="passwordForm.password_confirmation"
                            type="password" 
                            class="mt-1 w-full px-3 py-2.5 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/30 text-slate-700 dark:text-slate-200"
                            placeholder="Confirm new password"
                            required
                        />
                    </div>

                    <!-- Password Requirements -->
                    <div class="bg-slate-50 dark:bg-slate-900 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
                        <p class="text-[10px] font-bold text-slate-500 uppercase mb-1.5">Password Requirements:</p>
                        <ul class="space-y-0.5 text-[10px] text-slate-500 dark:text-slate-400">
                            <li class="flex items-center gap-1.5">• At least 8 characters</li>
                            <li class="flex items-center gap-1.5">• Contains uppercase and lowercase letters</li>
                            <li class="flex items-center gap-1.5">• Contains at least one number</li>
                            <li class="flex items-center gap-1.5">• Contains at least one special character</li>
                        </ul>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-700">
                        <button 
                            type="button"
                            @click="closePasswordModal"
                            class="px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-sm font-bold transition"
                            :disabled="isSubmitting"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition active:scale-95 disabled:opacity-50"
                            :disabled="isSubmitting"
                        >
                            <span v-if="isSubmitting" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Updating...
                            </span>
                            <span v-else>Update Password</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ✅ EMAIL VERIFICATION MODAL                  -->
        <!-- ============================================ -->
        <div 
            v-if="showVerificationModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            @click.self="closeVerificationModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md max-h-[90vh] overflow-y-auto p-6">
                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">Verify Email</h3>
                    <button 
                        @click="closeVerificationModal"
                        class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800">
                        <AlertTriangle class="w-6 h-6 text-amber-600" />
                        <p class="text-sm text-amber-700 dark:text-amber-400">Your email <strong>{{ accountInfo.email }}</strong> is not verified yet.</p>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        We'll send a verification link to your email address. Please click the link to verify your account.
                    </p>
                    <div class="bg-slate-50 dark:bg-slate-900 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            <Lightbulb class="w-4 h-4 text-amber-500" /> Already verified? You can close this modal.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="pt-4 mt-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-3">
                    <button 
                        @click="closeVerificationModal"
                        class="px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-sm font-bold transition"
                    >
                        Later
                    </button>
                    <button 
                        @click="sendVerification"
                        class="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 transition active:scale-95 inline-flex items-center gap-2"
                    >
                        <Mail class="w-4 h-4" /> Send Verification
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showDeactivateModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="deactivate-title" @click.self="showDeactivateModal = false">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-800">
                <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400"><Ban class="h-5 w-5" /></div>
                <h3 id="deactivate-title" class="text-lg font-black text-slate-900 dark:text-white">Deactivate your account?</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">You can reactivate your account anytime. Are you sure you want to continue?</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showDeactivateModal = false" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 dark:border-slate-600 dark:text-slate-200">Keep Account</button>
                    <button type="button" @click="confirmDeactivateAccount" class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700">Deactivate</button>
                </div>
            </div>
        </div>

        <div v-if="showNoticeModal" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="notice-title" @click.self="showNoticeModal = false">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-800">
                <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl" :class="noticeIsError ? 'bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400' : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'">
                    <AlertTriangle v-if="noticeIsError" class="h-5 w-5" />
                    <CheckCircle2 v-else class="h-5 w-5" />
                </div>
                <h3 id="notice-title" class="text-lg font-black text-slate-900 dark:text-white">{{ noticeTitle }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ noticeMessage }}</p>
                <div class="mt-6 flex justify-end">
                    <button type="button" @click="showNoticeModal = false" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700">OK</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
</style>
