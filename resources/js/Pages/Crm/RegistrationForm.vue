<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import {
    Building2, UserRound, Phone, MapPin, FileText, Hash, Send, Loader2,
    CheckCircle2, CalendarX, ShieldCheck, ArrowRight,
} from 'lucide-vue-next';

const props = defineProps({
    token: { type: String, required: true },
    invite: { type: Object, default: () => ({}) },
    registered: { type: Boolean, default: false },
    expired: { type: Boolean, default: false },
    opportunity: { type: Object, default: null },
});

const flash = computed(() => usePage().props.flash || {});

const form = useForm({
    company_name: props.invite.company_name || '',
    business_type: '',
    contact_person: props.invite.name || '',
    phone: '',
    company_address: '',
    tin_number: '',
    notes: '',
});

const submit = () => {
    form.post(route('crm.registration.store', props.token));
};

const expiresLabel = computed(() => {
    if (!props.invite.expires_at) return '';
    return new Date(props.invite.expires_at).toLocaleDateString([], {
        year: 'numeric', month: 'long', day: 'numeric',
    });
});
</script>

<template>
    <Head title="Monti Textiles — Partner Registration" />

    <div class="min-h-screen bg-gradient-to-br from-slate-100 via-indigo-50 to-sky-100 py-8 px-4 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950">
        <div class="mx-auto w-full max-w-2xl">
            <!-- Brand bar -->
            <div class="mb-5 flex items-center justify-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-600 to-indigo-700 text-white shadow-lg shadow-indigo-500/30">
                    <Building2 class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-lg font-black tracking-tight text-slate-800 dark:text-white">Monti Textiles</p>
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-indigo-500">Partner Registration</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-3xl border border-white/60 bg-white shadow-xl shadow-indigo-500/10 dark:border-zinc-800 dark:bg-zinc-900">
                <!-- Success -->
                <div v-if="registered" class="px-6 py-12 text-center sm:px-12">
                    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15">
                        <CheckCircle2 class="h-8 w-8" />
                    </div>
                    <h1 class="text-2xl font-black text-slate-800 dark:text-white">Registration submitted</h1>
                    <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">
                        Thank you! Your business details are now with our CRM team. A representative will reach out to you shortly to continue the process.
                    </p>
                    <p v-if="opportunity?.title" class="mx-auto mt-4 inline-flex items-center gap-2 rounded-full bg-indigo-50 px-4 py-2 text-xs font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                        <ArrowRight class="h-3.5 w-3.5" /> Reference: {{ opportunity.title }}
                    </p>
                </div>

                <!-- Expired -->
                <div v-else-if="expired" class="px-6 py-12 text-center sm:px-12">
                    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/15">
                        <CalendarX class="h-8 w-8" />
                    </div>
                    <h1 class="text-2xl font-black text-slate-800 dark:text-white">This link has expired</h1>
                    <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">
                        For your security this registration link is no longer active. Please contact our CRM team to request a new one.
                    </p>
                </div>

                <!-- Form -->
                <div v-else>
                    <div class="bg-gradient-to-r from-sky-700 to-indigo-800 px-6 py-6 text-white sm:px-8">
                        <h1 class="text-xl font-black tracking-tight">Complete your business registration</h1>
                        <p class="mt-1 text-sm text-sky-100">
                            Fill in your company details below. This form was shared with
                            <span class="font-bold">{{ invite.email }}</span>.
                        </p>
                        <p v-if="invite.sent_by" class="mt-1 text-xs text-sky-200">Invited by {{ invite.sent_by }}</p>
                    </div>

                    <div v-if="form.errors.error || flash.error" class="mx-6 mt-5 rounded-xl bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600 sm:mx-8 dark:bg-rose-500/10">
                        {{ form.errors.error || flash.error }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-5 px-6 py-6 sm:px-8">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    <Building2 class="h-3.5 w-3.5" /> Company name *
                                </label>
                                <input v-model="form.company_name" type="text" required placeholder="e.g. ABC Garments Trading"
                                    class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                                <p v-if="form.errors.company_name" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.company_name }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    <FileText class="h-3.5 w-3.5" /> Business type
                                </label>
                                <input v-model="form.business_type" type="text" placeholder="e.g. Retailer, Garment factory"
                                    class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                                <p v-if="form.errors.business_type" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.business_type }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    <Hash class="h-3.5 w-3.5" /> TIN number
                                </label>
                                <input v-model="form.tin_number" type="text" placeholder="Optional"
                                    class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                                <p v-if="form.errors.tin_number" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.tin_number }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    <UserRound class="h-3.5 w-3.5" /> Contact person *
                                </label>
                                <input v-model="form.contact_person" type="text" required placeholder="Full name"
                                    class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                                <p v-if="form.errors.contact_person" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.contact_person }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    <Phone class="h-3.5 w-3.5" /> Phone number *
                                </label>
                                <input v-model="form.phone" type="text" required placeholder="e.g. 0917 000 0000"
                                    class="w-full rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                                <p v-if="form.errors.phone" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.phone }}</p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="mb-1.5 flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    <MapPin class="h-3.5 w-3.5" /> Company address *
                                </label>
                                <textarea v-model="form.company_address" rows="2" required placeholder="Street, city, province"
                                    class="w-full resize-none rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                                <p v-if="form.errors.company_address" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.company_address }}</p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="mb-1.5 flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wide text-gray-500">
                                    <FileText class="h-3.5 w-3.5" /> What are you looking for?
                                </label>
                                <textarea v-model="form.notes" rows="3" placeholder="Fabrics, quantities, timelines — anything we should know"
                                    class="w-full resize-none rounded-xl border-0 bg-slate-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-zinc-800 dark:text-white" />
                                <p v-if="form.errors.notes" class="mt-1 text-[11px] font-bold text-rose-500">{{ form.errors.notes }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 rounded-xl bg-slate-50 px-4 py-3 text-[11px] font-semibold text-gray-500 dark:bg-zinc-800/60">
                            <ShieldCheck class="h-4 w-4 flex-none text-emerald-500" />
                            Your details are used only to set up your Monti Textiles partner account.
                        </div>

                        <button type="submit" :disabled="form.processing"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-700 py-3.5 text-sm font-black uppercase tracking-wide text-white shadow-lg shadow-indigo-500/30 transition hover:from-sky-700 hover:to-indigo-800 disabled:opacity-50">
                            <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" />
                            <Send v-else class="h-5 w-5" />
                            {{ form.processing ? 'Submitting…' : 'Submit registration' }}
                        </button>
                        <p v-if="expiresLabel" class="text-center text-[11px] font-semibold text-gray-400">
                            Please submit before {{ expiresLabel }}.
                        </p>
                    </form>
                </div>
            </div>

            <p class="mt-6 text-center text-[11px] font-semibold text-gray-400">
                &copy; {{ new Date().getFullYear() }} Monti Textiles · Fabric &amp; Garment Solutions
            </p>
        </div>
    </div>
</template>
