<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    Users, UserPlus, Trash2, Loader2, ShieldCheck, Mail, Phone,
    Briefcase, AlertCircle, CheckCircle2, Clock, Star, Send,
} from 'lucide-vue-next';

const props = defineProps({
    representatives: { type: Array, default: () => [] },
});

const page = usePage();
const toast = ref({ show: false, type: 'success', message: '' });
const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 4000);
};

watch(() => page.props.flash?.message, (msg) => { if (msg) showToast('success', msg); });

const form = useForm({
    name: '',
    title: '',
    email: '',
    phone: '',
    notes: '',
    is_decision_maker: false,
});

const showForm = ref(false);

const onPhoneInput = (e) => {
    form.phone = e.target.value.replace(/\D/g, '').slice(0, 10);
};

const submit = () => {
    if (form.phone && form.phone.length !== 10) {
        showToast('error', 'Phone number must be exactly 10 digits after +63.');
        return;
    }

    form
        .transform((data) => ({
            ...data,
            phone: data.phone ? `+63${data.phone}` : null,
        }))
        .post(route('client.representatives.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                showForm.value = false;
            },
            onError: (errors) => showToast('error', Object.values(errors)[0] || 'Please check the form.'),
        });
};

const remove = (rep) => {
    if (!confirm(`Remove ${rep.name}?`)) return;
    router.delete(route('client.representatives.destroy', rep.id), { preserveScroll: true });
};

const statusMeta = (rep) => {
    if (rep.status === 'rejected') return { label: 'Rejected', cls: 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30', icon: AlertCircle };
    if (rep.status === 'pending') return { label: 'Pending approval', cls: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30', icon: Clock };
    if (rep.has_account) return { label: 'Account active', cls: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30', icon: CheckCircle2 };
    return { label: 'Contact', cls: 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-zinc-800 dark:text-gray-300 dark:ring-zinc-700', icon: Users };
};

const counts = computed(() => ({
    total: props.representatives.length,
    pending: props.representatives.filter((r) => r.status === 'pending').length,
    active: props.representatives.filter((r) => r.has_account).length,
}));
</script>

<template>
    <Head title="Representatives" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-5xl mx-auto space-y-6 pb-16">

                <!-- Hero -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <Users class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <ShieldCheck class="h-3.5 w-3.5" /> Account Management · Representatives
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Delegated Representatives</h1>
                            <p class="text-sm text-blue-100/90">Register your company's officers. CRM reviews each one and decides whether to grant a portal account.</p>
                        </div>
                        <button @click="showForm = !showForm"
                            class="flex items-center gap-2 rounded-2xl bg-white px-4 py-3 text-xs font-black uppercase tracking-wide text-indigo-700 shadow-lg hover:scale-105 active:scale-95 transition">
                            <UserPlus class="h-4 w-4" /> {{ showForm ? 'Close' : 'Add representative' }}
                        </button>
                    </div>

                    <div class="relative mt-6 grid grid-cols-3 gap-3">
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Total</p>
                            <p class="mt-1 text-lg font-black">{{ counts.total }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">Pending</p>
                            <p class="mt-1 text-lg font-black">{{ counts.pending }}</p>
                        </div>
                        <div class="rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur p-4">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-blue-100">With accounts</p>
                            <p class="mt-1 text-lg font-black">{{ counts.active }}</p>
                        </div>
                    </div>
                </div>

                <!-- Add form -->
                <Transition name="modal">
                    <div v-if="showForm" class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                            <UserPlus class="w-5 h-5 text-indigo-600" />
                            <h2 class="text-sm font-black uppercase tracking-widest">Register a representative</h2>
                        </div>
                        <form @submit.prevent="submit" class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Full name *</label>
                                <input v-model="form.name" required placeholder="e.g. Maria Santos"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 outline-none text-sm dark:text-white" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Title / role</label>
                                <input v-model="form.title" placeholder="e.g. Purchasing Head"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Email</label>
                                <input v-model="form.email" type="email" placeholder="name@company.com"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm dark:text-white" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Phone</label>
                                <div class="flex items-center rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus-within:border-indigo-400 overflow-hidden"
                                    :class="{ 'border-rose-400': form.errors.phone }">
                                    <span class="pl-4 pr-1 text-sm font-bold text-gray-400 select-none">+63</span>
                                    <input v-model="form.phone" @input="onPhoneInput" inputmode="numeric" maxlength="10"
                                        placeholder="9171234567"
                                        class="w-full py-2.5 pr-4 bg-transparent outline-none text-sm dark:text-white" />
                                </div>
                                <p v-if="form.errors.phone" class="mt-1 text-[10px] font-bold text-rose-500">{{ form.errors.phone }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Notes</label>
                                <textarea v-model="form.notes" rows="2" placeholder="Scope of duties, department…"
                                    class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-zinc-800 border border-transparent outline-none text-sm resize-none dark:text-white"></textarea>
                            </div>
                            <label class="sm:col-span-2 flex items-center gap-2 text-xs font-bold text-gray-600 dark:text-gray-300">
                                <input type="checkbox" v-model="form.is_decision_maker" class="rounded border-gray-300 text-indigo-600" /> Decision maker
                            </label>
                            <div class="sm:col-span-2 flex gap-3">
                                <button type="button" @click="showForm = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 dark:bg-zinc-800 text-gray-500 font-bold text-sm hover:bg-gray-200 active:scale-95 transition">Cancel</button>
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-black text-sm shadow-lg disabled:opacity-60 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2">
                                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" /> <Send v-else class="h-4 w-4" />
                                    {{ form.processing ? 'Submitting…' : 'Submit for approval' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <!-- List -->
                <div class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex items-center gap-2 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-900/10">
                        <Users class="w-5 h-5 text-blue-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Your representatives</h2>
                        <span class="ml-auto rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-[11px] font-black px-2.5 py-1">{{ counts.total }}</span>
                    </div>
                    <div class="p-6 space-y-3">
                        <div v-for="rep in representatives" :key="rep.id"
                            class="rounded-2xl border p-4 transition-all hover:shadow-lg"
                            :class="rep.is_primary ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/50 dark:bg-indigo-900/10' : 'border-gray-100 dark:border-zinc-800'">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 via-blue-600 to-violet-700 text-sm font-black text-white uppercase">
                                    {{ (rep.name || '?').charAt(0) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-black text-sm flex items-center gap-1.5 flex-wrap">
                                        {{ rep.name }}
                                        <Star v-if="rep.is_decision_maker" class="h-3.5 w-3.5 text-amber-500" />
                                        <span v-if="rep.is_primary" class="rounded-full bg-indigo-600 text-white px-2 py-0.5 text-[9px] font-black uppercase">Primary</span>
                                    </p>
                                    <p class="text-[11px] font-bold text-gray-400">{{ rep.title || 'No title' }}</p>
                                    <p class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-3">
                                        <span class="flex items-center gap-1"><Mail class="h-3 w-3" /> {{ rep.email || '—' }}</span>
                                        <span class="flex items-center gap-1"><Phone class="h-3 w-3" /> {{ rep.phone || '—' }}</span>
                                    </p>
                                </div>
                                <span :class="['shrink-0 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-black uppercase ring-1', statusMeta(rep).cls]">
                                    <component :is="statusMeta(rep).icon" class="h-3 w-3" /> {{ statusMeta(rep).label }}
                                </span>
                            </div>
                            <div v-if="!rep.is_primary" class="mt-3 flex justify-end">
                                <button @click="remove(rep)" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[10px] font-black uppercase text-gray-300 hover:text-rose-500">
                                    <Trash2 class="h-3 w-3" /> Remove
                                </button>
                            </div>
                            <p v-else class="mt-2 text-[11px] text-indigo-500 font-bold flex items-center gap-1"><Briefcase class="h-3 w-3" /> This is your account's primary contact.</p>
                        </div>
                        <p v-if="!representatives.length" class="text-center text-sm font-bold text-gray-400 py-10">No representatives yet — add your delegated officers above.</p>
                    </div>
                </div>
            </div>

            <Transition name="toast">
                <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-xl text-white font-bold text-sm ring-1 ring-white/20"
                    :class="toast.type === 'success' ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-gradient-to-r from-rose-600 to-red-600'">
                    {{ toast.message }}
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.modal-enter-active, .modal-leave-active { transition: opacity 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
