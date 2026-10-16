<template>
    <Head title="Support & Feedback" />
    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30">
            <div class="p-4 sm:p-6 max-w-4xl mx-auto space-y-6 pb-16">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <HelpCircle class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <HelpCircle class="h-3.5 w-3.5" /> Client Care · CRM Team
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Support &amp; Feedback</h1>
                            <p class="text-sm text-blue-100/90">Submit complaints or share your feedback with our CRM team.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 backdrop-blur flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse" /> Online
                            </span>
                            <span class="rounded-full bg-white px-3 py-1.5 text-xs font-black text-indigo-700 shadow-lg">2-day reply</span>
                        </div>
                    </div>
                </div>

                <!-- Contact Info Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="animate-fade-up group relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white shadow-xl shadow-indigo-500/20 hover:-translate-y-1.5 hover:shadow-2xl transition-all duration-300" style="animation-delay:80ms">
                        <div class="absolute -top-16 -right-16 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur group-hover:scale-110 transition-transform"><Phone class="h-6 w-6" /></span>
                            <h3 class="mt-3 text-lg font-black tracking-tight">Hotline</h3>
                            <p class="text-2xl font-black mt-1">+63 (2) 8888-MONTI</p>
                            <p class="text-xs text-blue-100 mt-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Mon-Fri, 8AM – 6PM</p>
                        </div>
                    </div>
                    <div class="animate-fade-up group relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 p-6 text-white shadow-xl shadow-emerald-500/20 hover:-translate-y-1.5 hover:shadow-2xl transition-all duration-300" style="animation-delay:160ms">
                        <div class="absolute -bottom-16 -left-10 h-40 w-40 rounded-full bg-white/10 blur-3xl animate-float-delayed" />
                        <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                        <div class="relative">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur group-hover:scale-110 transition-transform"><Mail class="h-6 w-6" /></span>
                            <h3 class="mt-3 text-lg font-black tracking-tight">Email Support</h3>
                            <p class="text-xl font-black mt-1 break-all">support@montitextile.ph</p>
                            <p class="text-xs text-emerald-50 mt-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> Response within 24 hours</p>
                        </div>
                    </div>
                </div>

                <!-- Complaint/Feedback Form -->
                <div class="animate-fade-up group relative bg-white/80 dark:bg-zinc-900/80 backdrop-blur rounded-3xl border border-gray-100 dark:border-zinc-800 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/15 transition-all duration-300 overflow-hidden" style="animation-delay:240ms">
                    <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-gradient-to-br from-indigo-400/20 to-fuchsia-400/20 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <Send class="w-5 h-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">New Message to CRM</h2>
                        <span class="ml-auto rounded-full px-3 py-1.5 text-[11px] font-black uppercase tracking-wide ring-1 backdrop-blur flex items-center gap-1.5"
                            :class="form.type === 'complaint' ? 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' : 'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30'">
                            <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse" /> {{ form.type }}
                        </span>
                    </div>
                    <form @submit.prevent="submitComplaint" class="p-6 sm:p-8 space-y-6">
                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2">Type *</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label :class="form.type==='complaint' ? 'ring-2 ring-rose-500 bg-rose-50 dark:bg-rose-900/20' : 'bg-gray-50 dark:bg-zinc-800 hover:bg-gray-100 dark:hover:bg-zinc-700'"
                                    class="flex cursor-pointer items-center justify-center gap-2 rounded-2xl px-3 py-3 text-sm font-black transition-all active:scale-95">
                                    <input type="radio" v-model="form.type" value="complaint" class="hidden" /> ⚠️ Complaint
                                </label>
                                <label :class="form.type==='feedback' ? 'ring-2 ring-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'bg-gray-50 dark:bg-zinc-800 hover:bg-gray-100 dark:hover:bg-zinc-700'"
                                    class="flex cursor-pointer items-center justify-center gap-2 rounded-2xl px-3 py-3 text-sm font-black transition-all active:scale-95">
                                    <input type="radio" v-model="form.type" value="feedback" class="hidden" /> 💬 Feedback
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Subject *</label>
                            <input v-model="form.subject" type="text" required
                                class="w-full px-4 py-3 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 outline-none text-sm font-medium transition"
                                placeholder="Brief summary of your issue or suggestion">
                        </div>

                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Message *</label>
                            <textarea v-model="form.message" rows="6" required
                                class="w-full px-4 py-3 rounded-2xl bg-gray-50 dark:bg-zinc-800 border border-transparent focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 outline-none text-sm transition resize-none"
                                placeholder="Please provide as much detail as possible..."></textarea>
                        </div>

                        <div>
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1.5">Attachment (Optional)</label>
                            <div class="flex items-center gap-3 rounded-2xl border border-dashed border-gray-200 dark:border-zinc-700 bg-gray-50/50 dark:bg-zinc-800/50 px-4 py-3 hover:border-indigo-300 dark:hover:border-indigo-700 transition">
                                <input type="file" @change="handleFileUpload" accept="image/*,application/pdf"
                                    class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 file:transition">
                                <span v-if="form.attachment" class="text-xs font-black text-emerald-600 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" /> File selected</span>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1 font-medium">Max 5MB. Accepted: JPG, PNG, PDF</p>
                        </div>

                        <div class="rounded-2xl border border-blue-100 dark:border-blue-900 bg-blue-50/70 dark:bg-blue-900/10 p-4">
                            <p class="text-xs font-medium text-blue-800 dark:text-blue-200 flex items-start gap-2">
                                <Info class="h-4 w-4 mt-0.5 flex-shrink-0" />
                                Your message will be sent directly to our Customer Relationship Management (CRM) team.
                                A representative will respond within 2 business days.
                            </p>
                        </div>

                        <button type="submit" :disabled="form.processing"
                            class="w-full py-4 bg-gradient-to-r from-blue-700 via-indigo-700 to-violet-800 text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-xl shadow-indigo-500/25 hover:shadow-2xl hover:scale-[1.01] active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <Send v-else class="h-4 w-4" />
                            {{ form.processing ? 'Sending...' : 'Submit to CRM' }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Toast Notification -->
            <Transition name="toast">
                <div v-if="toast.show" class="fixed bottom-8 right-8 z-50 px-6 py-3 rounded-2xl shadow-xl text-white font-bold text-sm ring-1 ring-white/20"
                    :class="toast.type === 'success' ? 'bg-gradient-to-r from-emerald-600 to-teal-600' : 'bg-gradient-to-r from-rose-600 to-red-600'">
                    {{ toast.message }}
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { HelpCircle, Phone, Mail, Info, Loader2, Send } from 'lucide-vue-next';

const form = useForm({
    type: 'complaint',
    subject: '',
    message: '',
    attachment: null
});

const toast = ref({ show: false, type: 'success', message: '' });

const showToast = (type, message) => {
    toast.value = { show: true, type, message };
    setTimeout(() => { toast.value.show = false; }, 4000);
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (file && file.size > 5 * 1024 * 1024) {
        showToast('error', 'File size exceeds 5MB limit.');
        event.target.value = '';
        form.attachment = null;
        return;
    }
    form.attachment = file;
};

const submitComplaint = () => {
    if (!form.subject.trim() || !form.message.trim()) {
        showToast('error', 'Please fill in all required fields.');
        return;
    }
    
    form.post(route('client.support.complaint'), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('success', 'Your message has been sent to our CRM team.');
            form.reset('subject', 'message', 'attachment');
            form.type = 'complaint';
            // Clear file input
            const fileInput = document.querySelector('input[type="file"]');
            if (fileInput) fileInput.value = '';
        },
        onError: (errors) => {
            const errorMsg = Object.values(errors)[0] || 'Failed to send. Please try again.';
            showToast('error', errorMsg);
        }
    });
};
</script>

<style scoped>
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes pop { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounceSoft { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.animate-fade-up { animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both; }
.animate-float { animation: float 7s ease-in-out infinite; }
.animate-float-delayed { animation: float 8s ease-in-out 1.2s infinite; }
.animate-pop { animation: pop 0.5s cubic-bezier(0.22,1,0.36,1) both; }
.animate-bounce-soft { animation: bounceSoft 2.4s ease-in-out infinite; }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
