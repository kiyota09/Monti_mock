<template>
    <Head title="Logistics Trainee Development" />

    <AuthenticatedLayout>
        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="showToast" class="fixed top-4 left-1/2 -translate-x-1/2 z-[100] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl border max-w-xs w-full mx-4"
                :class="toastType === 'error' ? 'bg-rose-600 border-rose-500 text-white' : 'bg-slate-900 border-slate-700 text-white'">
                <CheckCircle v-if="toastType !== 'error'" class="h-4 w-4 text-emerald-400 shrink-0" />
                <XCircle v-else class="h-4 w-4 text-rose-300 shrink-0" />
                <p class="text-xs font-semibold tracking-wide flex-1">{{ toastMessage }}</p>
            </div>
        </Transition>

        <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-blue-50/40 dark:from-zinc-950 dark:via-zinc-950 dark:to-indigo-950/30 pb-24">
            <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">

                <!-- Hero header -->
                <div class="animate-fade-up relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 sm:p-8 text-white shadow-xl shadow-indigo-500/20">
                    <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl animate-float" />
                    <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl animate-float-delayed" />
                    <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;" />
                    <div class="relative flex flex-wrap items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/30 shadow-lg animate-pop">
                            <GraduationCap class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-100">
                                <GraduationCap class="h-3.5 w-3.5" /> Logistics · Development
                            </p>
                            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Logistics Trainees</h1>
                            <p class="text-sm text-blue-100/90">{{ trainees.length }} trainee{{ trainees.length !== 1 ? 's' : '' }} in development</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="canEdit" class="flex items-center gap-1.5 rounded-full bg-emerald-400/90 px-3 py-1.5 text-xs font-black text-emerald-950">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-950 animate-pulse"></span> Full Access
                            </span>
                            <span v-else class="flex items-center gap-1.5 rounded-full bg-amber-300/90 px-3 py-1.5 text-xs font-black text-amber-950">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-900 animate-pulse"></span> View Only
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Trainees Table -->
                <div v-if="trainees.length === 0"
                    class="animate-fade-up flex flex-col items-center justify-center py-20 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-dashed border-gray-200 dark:border-zinc-800 shadow-sm">
                    <div class="p-5 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-indigo-900/30 rounded-full mb-4 animate-bounce-soft">
                        <GraduationCap class="h-9 w-9 text-indigo-400" />
                    </div>
                    <p class="text-sm font-black text-gray-700 dark:text-gray-200">No trainees yet</p>
                    <p class="text-xs text-gray-400 mt-1">When applicants pass the interview, they will appear here as trainees.</p>
                </div>

                <div v-else class="animate-fade-up bg-white dark:bg-zinc-900 rounded-3xl shadow-sm border border-gray-100 dark:border-zinc-800 overflow-hidden hover:shadow-xl transition-shadow duration-300" style="animation-delay:120ms">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/20 flex items-center gap-2">
                        <GraduationCap class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-black uppercase tracking-widest">Trainees in Development</h2>
                        <span class="ml-auto rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[11px] font-black px-2.5 py-1">{{ trainees.length }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-zinc-800">
                                <tr>
                                    <th class="text-left px-5 py-4">Trainee</th>
                                    <th class="text-left px-5 py-4">Join Date</th>
                                    <th class="text-left px-5 py-4">Grade</th>
                                    <th class="text-left px-5 py-4">Status</th>
                                    <th class="text-right px-5 py-4">Actions</th>
                                </tr>
                            </thead>
                            <TransitionGroup name="card" tag="tbody" class="divide-y divide-gray-50 dark:divide-zinc-800">
                                <tr v-for="(trainee, i) in trainees" :key="trainee.id" :style="{ transitionDelay: `${Math.min(i * 40, 400)}ms` }" class="group hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 flex items-center justify-center text-white text-sm font-black shadow-lg uppercase group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300">
                                                {{ getInitials(trainee.name) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-black text-slate-800 dark:text-white truncate group-hover:text-indigo-700 dark:group-hover:text-indigo-300 transition-colors">{{ trainee.name }}</p>
                                                <p class="text-[11px] text-slate-500 truncate">{{ trainee.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300 text-xs">{{ formatDate(trainee.join_date) }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-16 bg-slate-200 dark:bg-zinc-700 rounded-full h-1.5">
                                                <div class="h-1.5 rounded-full transition-all duration-500"
                                                    :class="getGradeColor(trainee.grade_percentage)"
                                                    :style="{ width: `${trainee.grade_percentage}%` }"></div>
                                            </div>
                                            <span class="text-xs font-black" :class="getGradeTextColor(trainee.grade_percentage)">
                                                {{ trainee.grade_percentage }}%
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span v-if="trainee.grade_percentage >= 80" class="inline-flex items-center gap-1.5 text-[10px] font-black text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-500/15 ring-1 ring-emerald-200 dark:ring-emerald-500/30 px-2.5 py-1 rounded-full uppercase">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Ready for promotion
                                        </span>
                                        <span v-else-if="trainee.grade_percentage > 0" class="inline-flex items-center gap-1.5 text-[10px] font-black text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-500/15 ring-1 ring-amber-200 dark:ring-amber-500/30 px-2.5 py-1 rounded-full uppercase">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span> In progress
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1.5 text-[10px] font-black text-slate-500 bg-slate-100 dark:bg-zinc-800 ring-1 ring-slate-200 dark:ring-zinc-700 px-2.5 py-1 rounded-full uppercase">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Not graded
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div v-if="canEdit" class="flex items-center justify-end gap-2">
                                            <button @click="openGradeModal(trainee)"
                                                class="p-2 rounded-xl text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-600 hover:text-white transition hover:-translate-y-0.5"
                                                title="Grade trainee">
                                                <Edit3 class="h-4 w-4" />
                                            </button>
                                            <button v-if="trainee.grade_percentage >= 80"
                                                @click="openPassModal(trainee)"
                                                class="p-2 rounded-xl text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 hover:bg-emerald-600 hover:text-white transition hover:-translate-y-0.5"
                                                title="Pass to staff">
                                                <UserCheck class="h-4 w-4" />
                                            </button>
                                            <button @click="openFailModal(trainee)"
                                                class="p-2 rounded-xl text-rose-600 bg-rose-50 dark:bg-rose-900/30 hover:bg-rose-600 hover:text-white transition hover:-translate-y-0.5"
                                                title="Fail trainee">
                                                <UserMinus class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <div v-else class="text-xs text-slate-400 italic">Read only</div>
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isGradeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="bg-gradient-to-br from-blue-700 via-indigo-700 to-violet-800 p-6 text-white relative overflow-hidden">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <h2 class="relative text-xl font-black uppercase tracking-tight">Grade Trainee</h2>
                            <p class="relative text-blue-100/90 text-xs mt-1">{{ selectedTrainee?.name }}</p>
                        </div>
                        <div class="p-6 space-y-5">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1.5">Skills & Performance (1-5)</label>
                                    <input type="number" v-model.number="gradeForm.skills_performance" min="1" max="5" step="1"
                                        class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-indigo-500 outline-none transition" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1.5">Behaviour & Attitude (1-5)</label>
                                    <input type="number" v-model.number="gradeForm.behaviour" min="1" max="5" step="1"
                                        class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-indigo-500 outline-none transition" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1.5">Technicals & Logistics Knowledge (1-5)</label>
                                    <input type="number" v-model.number="gradeForm.technicals" min="1" max="5" step="1"
                                        class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-indigo-500 outline-none transition" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1.5">Safety Awareness (1-5)</label>
                                    <input type="number" v-model.number="gradeForm.safety_awareness" min="1" max="5" step="1"
                                        class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-indigo-500 outline-none transition" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1.5">Productivity & Efficiency (1-5)</label>
                                    <input type="number" v-model.number="gradeForm.productivity" min="1" max="5" step="1"
                                        class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-indigo-500 outline-none transition" />
                                </div>
                            </div>
                            <div class="bg-slate-50 dark:bg-zinc-800 rounded-2xl p-3 text-center ring-1 ring-slate-200 dark:ring-zinc-700">
                                <p class="text-[10px] text-slate-400 uppercase tracking-wider font-black">Total Percentage</p>
                                <p class="text-2xl font-black" :class="computedGradePercentage >= 80 ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ computedGradePercentage }}%
                                </p>
                            </div>
                            <div class="flex gap-3 pt-2">
                                <button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button>
                                <button @click="submitGrade" class="flex-1 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all active:scale-95">Save Grade</button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Pass Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isPassModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden text-center border border-gray-100 dark:border-zinc-800">
                        <div class="bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 p-8 text-white relative overflow-hidden">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <div class="relative inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/20 mb-4 animate-pop">
                                <UserCheck class="h-8 w-8" />
                            </div>
                            <h2 class="relative text-xl font-black uppercase tracking-tight">Promote to Staff</h2>
                        </div>
                        <div class="p-6">
                            <p class="text-slate-600 dark:text-slate-300 mb-6">
                                Are you sure you want to promote <strong>{{ selectedTrainee?.name }}</strong> to Logistics Staff?
                                They will receive default dashboard access.
                            </p>
                            <div class="flex gap-3">
                                <button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button>
                                <button @click="passTrainee" class="flex-1 py-3 bg-emerald-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:bg-emerald-700 transition-all active:scale-95">Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Fail Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="isFailModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md" @click.self="closeModals">
                    <div class="bg-white dark:bg-zinc-900 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-100 dark:border-zinc-800">
                        <div class="bg-gradient-to-br from-rose-600 via-red-600 to-orange-600 p-6 text-white relative overflow-hidden">
                            <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
                            <h2 class="relative text-xl font-black uppercase tracking-tight">Fail Trainee</h2>
                            <p class="relative text-rose-100 text-xs mt-1">Provide reason for failure</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Reason *</label>
                                <textarea v-model="failReason" rows="3" class="w-full px-4 py-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border-none ring-1 ring-slate-200 dark:ring-zinc-700 focus:ring-2 focus:ring-rose-500 outline-none" placeholder="e.g., Failed to meet performance targets..."></textarea>
                            </div>
                            <div class="flex gap-3">
                                <button @click="closeModals" class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button>
                                <button @click="failTrainee" :disabled="!failReason.trim()" class="flex-1 py-3 bg-rose-600 text-white rounded-2xl text-xs font-black uppercase shadow-lg hover:bg-rose-700 transition-all disabled:opacity-50 active:scale-95">Confirm Fail</button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    GraduationCap, CheckCircle, XCircle, Edit3, UserCheck, UserMinus,
    Calendar, Mail, Phone, X
} from 'lucide-vue-next';

const props = defineProps({
    trainees: {
        type: Array,
        default: () => []
    },
    permissions: {
        type: Object,
        default: () => ({})
    }
});

const canEdit = computed(() => props.permissions?.trainee === 'edit');

// Toast
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('success');
const triggerToast = (msg, type = 'success') => {
    toastMessage.value = msg;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 4000);
};

const page = usePage();
if (page.props.flash?.message) triggerToast(page.props.flash.message);
if (page.props.flash?.error) triggerToast(page.props.flash.error, 'error');

// Modal states
const isGradeModalOpen = ref(false);
const isPassModalOpen = ref(false);
const isFailModalOpen = ref(false);
const selectedTrainee = ref(null);
const failReason = ref('');

// Grade form
const gradeForm = ref({
    skills_performance: 3,
    behaviour: 3,
    technicals: 3,
    safety_awareness: 3,
    productivity: 3
});

const computedGradePercentage = computed(() => {
    const total = gradeForm.value.skills_performance +
                  gradeForm.value.behaviour +
                  gradeForm.value.technicals +
                  gradeForm.value.safety_awareness +
                  gradeForm.value.productivity;
    return (total / 25) * 100;
});

// Helpers
const getInitials = (name) => name ? name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?';
const formatDate = (date) => date ? new Date(date).toLocaleDateString() : 'N/A';

const getGradeColor = (percentage) => {
    if (percentage >= 80) return 'bg-emerald-500';
    if (percentage >= 60) return 'bg-amber-500';
    return 'bg-rose-500';
};

const getGradeTextColor = (percentage) => {
    if (percentage >= 80) return 'text-emerald-600 dark:text-emerald-400';
    if (percentage >= 60) return 'text-amber-600 dark:text-amber-400';
    return 'text-rose-600 dark:text-rose-400';
};

// Open modals
const openGradeModal = (trainee) => {
    if (!canEdit.value) { triggerToast('No permission to grade trainees.', 'error'); return; }
    selectedTrainee.value = trainee;
    // Load existing grade if available (via props, but for simplicity we can assume grade data is embedded)
    gradeForm.value = {
        skills_performance: trainee.skills_performance || 3,
        behaviour: trainee.behaviour || 3,
        technicals: trainee.technicals || 3,
        safety_awareness: trainee.safety_awareness || 3,
        productivity: trainee.productivity || 3
    };
    isGradeModalOpen.value = true;
};

const openPassModal = (trainee) => {
    if (!canEdit.value) { triggerToast('No permission.', 'error'); return; }
    selectedTrainee.value = trainee;
    isPassModalOpen.value = true;
};

const openFailModal = (trainee) => {
    if (!canEdit.value) { triggerToast('No permission.', 'error'); return; }
    selectedTrainee.value = trainee;
    failReason.value = '';
    isFailModalOpen.value = true;
};

const closeModals = () => {
    isGradeModalOpen.value = false;
    isPassModalOpen.value = false;
    isFailModalOpen.value = false;
    selectedTrainee.value = null;
    gradeForm.value = { skills_performance: 3, behaviour: 3, technicals: 3, safety_awareness: 3, productivity: 3 };
    failReason.value = '';
};

// API calls
const submitGrade = () => {
    router.post(route('logistics.trainee.grade', selectedTrainee.value.id), gradeForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast('Grade saved successfully.');
            closeModals();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Failed to save grade.', 'error');
        }
    });
};

const passTrainee = () => {
    router.post(route('logistics.trainee.pass', selectedTrainee.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`${selectedTrainee.value.name} promoted to staff.`);
            closeModals();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Failed to promote trainee.', 'error');
        }
    });
};

const failTrainee = () => {
    if (!failReason.value.trim()) {
        triggerToast('Please provide a reason for failure.', 'error');
        return;
    }
    router.post(route('logistics.trainee.fail', selectedTrainee.value.id), {
        reason: failReason.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`${selectedTrainee.value.name} marked as failed.`);
            closeModals();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Failed to process.', 'error');
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
.card-enter-active { transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.22,1,0.36,1); }
.card-enter-from { opacity: 0; transform: translateY(18px) scale(0.98); }
.card-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; position: absolute; }
.card-leave-to { opacity: 0; transform: scale(0.96); }
.card-move { transition: transform 0.4s ease; }
.modal-enter-active, .modal-leave-active { transition: all 0.3s cubic-bezier(0.22,1,0.36,1); }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95) translateY(12px); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(-16px); }
</style>
