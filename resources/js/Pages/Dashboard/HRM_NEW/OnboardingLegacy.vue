<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Users, UserCheck, Award, Calendar, Search, Filter,
    CheckCircle, X, UserPlus, TrendingUp, Briefcase
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

// Check if user can edit onboarding (convert trainees)
const canEdit = computed(() => props.permissions?.onboarding === 'edit');

// Toast notification
const showToast = ref(false);
const toastMessage = ref('');
const triggerToast = (msg) => {
    toastMessage.value = msg;
    showToast.value = true;
    setTimeout(() => { showToast.value = false; }, 4000);
};

// Flash messages from server
const page = usePage();
if (page.props.flash?.message) {
    triggerToast(page.props.flash.message);
}
if (page.props.flash?.error) {
    triggerToast(page.props.flash.error);
}

// Modal state
const isConvertModalOpen = ref(false);
const selectedTrainee = ref(null);

// Search and filter
const searchQuery = ref('');
const departmentFilter = ref('ALL');

const departments = computed(() => {
    const depts = ['ALL', ...new Set(props.trainees.map(t => t.role))];
    return depts;
});

const filteredTrainees = computed(() => {
    let list = props.trainees;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(t =>
            t.name.toLowerCase().includes(q) ||
            t.email.toLowerCase().includes(q) ||
            t.role.toLowerCase().includes(q)
        );
    }
    if (departmentFilter.value !== 'ALL') {
        list = list.filter(t => t.role === departmentFilter.value);
    }
    return list;
});

// Stats
const stats = computed(() => ({
    total: props.trainees.length,
    avgGrade: props.trainees.length
        ? (props.trainees.reduce((sum, t) => sum + (t.grade_percentage || 0), 0) / props.trainees.length).toFixed(1)
        : 0,
    readyForConversion: props.trainees.filter(t => (t.grade_percentage || 0) >= 80).length
}));

// Helper functions
const getInitials = (name) => name ? name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?';
const formatDate = (date) => date ? new Date(date).toLocaleDateString() : 'N/A';
const getGradeColor = (percentage) => {
    if (percentage >= 80) return 'text-emerald-600 bg-emerald-50';
    if (percentage >= 60) return 'text-blue-600 bg-blue-50';
    if (percentage >= 40) return 'text-amber-600 bg-amber-50';
    return 'text-red-600 bg-red-50';
};

// Modal actions
const openConvertModal = (trainee) => {
    if (!canEdit.value) {
        triggerToast('You do not have permission to convert trainees.', 'error');
        return;
    }
    selectedTrainee.value = trainee;
    isConvertModalOpen.value = true;
};

const closeConvertModal = () => {
    isConvertModalOpen.value = false;
    selectedTrainee.value = null;
};

const convertTrainee = () => {
    if (!selectedTrainee.value) return;
    router.post(route('hrm.onboarding.convert', selectedTrainee.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast(`${selectedTrainee.value.name} has been converted to Staff.`);
            closeConvertModal();
        },
        onError: (errors) => {
            triggerToast(Object.values(errors)[0] || 'Conversion failed.');
        }
    });
};
</script>

<template>
    <Head title="Onboarding Management" />

    <AuthenticatedLayout>
        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="showToast"
                class="fixed top-6 right-6 z-[100] flex items-center gap-3 px-6 py-4 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-2xl shadow-2xl border border-white/10">
                <CheckCircle class="h-5 w-5 text-emerald-400 dark:text-emerald-600" />
                <p class="text-sm font-bold uppercase tracking-tight">{{ toastMessage }}</p>
            </div>
        </Transition>

        <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6 sm:space-y-8">
            <!-- Header -->
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Onboarding <span class="text-blue-600">Pipeline</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">
                    Convert qualified trainees to official employees.
                </p>
                <!-- Permission indicator -->
                <div v-if="!canEdit && permissions.onboarding === 'view'" class="mt-2 text-xs text-amber-600 bg-amber-50 inline-block px-2 py-0.5 rounded-full">
                    View only access
                </div>
                <div v-else-if="canEdit" class="mt-2 text-xs text-emerald-600 bg-emerald-50 inline-block px-2 py-0.5 rounded-full">
                    Full access (can convert trainees)
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
                <div
                    class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between mb-2">
                        <Users class="h-6 w-6 text-blue-500" />
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.total }}</span>
                    </div>
                    <p class="text-sm font-medium text-slate-500">Total Trainees</p>
                </div>
                <div
                    class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between mb-2">
                        <TrendingUp class="h-6 w-6 text-emerald-500" />
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.avgGrade }}%</span>
                    </div>
                    <p class="text-sm font-medium text-slate-500">Average Grade</p>
                </div>
                <div
                    class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-between mb-2">
                        <UserCheck class="h-6 w-6 text-purple-500" />
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ stats.readyForConversion
                            }}</span>
                    </div>
                    <p class="text-sm font-medium text-slate-500">Ready for Conversion</p>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                <div class="relative w-full sm:w-80">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                    <input v-model="searchQuery" type="text" placeholder="Search by name, email, or department..."
                        class="w-full pl-11 pr-4 py-3 rounded-2xl bg-white dark:bg-slate-800 border-none ring-1 ring-slate-200 dark:ring-slate-700 focus:ring-2 focus:ring-blue-600 text-sm" />
                </div>
                <div class="flex gap-2 overflow-x-auto pb-1 w-full sm:w-auto no-scrollbar">
                    <button v-for="dept in departments" :key="dept" @click="departmentFilter = dept" :class="[
                        'px-4 py-2 rounded-xl text-xs font-bold uppercase transition-all whitespace-nowrap',
                        departmentFilter === dept
                            ? 'bg-blue-600 text-white shadow-md'
                            : 'bg-white dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-700 hover:border-blue-300'
                    ]">
                        {{ dept === 'ALL' ? 'All Departments' : dept }}
                    </button>
                </div>
            </div>

            <!-- Trainees Table -->
            <div
                class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/60 dark:bg-slate-900/40">
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Trainee</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Department</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Join Date</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Grade</th>
                                <th
                                    class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr v-if="filteredTrainees.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">
                                    No trainees found.
                                </td>
                            </tr>
                            <tr v-for="trainee in filteredTrainees" :key="trainee.id"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center font-bold text-xs text-slate-600 dark:text-slate-300">
                                            {{ getInitials(trainee.name) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ trainee.name
                                                }}</p>
                                            <p class="text-xs text-slate-400">{{ trainee.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2 py-1 rounded-md text-[10px] font-black uppercase bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ trainee.role }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                    {{ formatDate(trainee.join_date) }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold"
                                        :class="getGradeColor(trainee.grade_percentage)">
                                        {{ trainee.grade_percentage }}%
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button v-if="canEdit"
                                        @click="openConvertModal(trainee)"
                                        :disabled="trainee.grade_percentage < 80"
                                        :class="[
                                            'px-4 py-2 rounded-xl text-xs font-black uppercase transition-all',
                                            trainee.grade_percentage >= 80
                                                ? 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-md'
                                                : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                        ]">
                                        Convert to Staff
                                    </button>
                                    <span v-else class="text-xs text-gray-400 italic">View only</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Convert Confirmation Modal -->
        <div v-if="isConvertModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
            @click.self="closeConvertModal">
            <div class="bg-white dark:bg-slate-800 rounded-[2.5rem] shadow-2xl w-full max-w-md overflow-hidden">
                <div class="bg-emerald-600 p-6 text-white">
                    <h2 class="text-xl font-black uppercase">Confirm Conversion</h2>
                    <p class="text-emerald-200 text-xs mt-1">Convert trainee to official staff</p>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-slate-600 dark:text-slate-300">
                        Are you sure you want to convert <strong>{{ selectedTrainee?.name }}</strong> to a permanent
                        staff member?
                        This action will grant them full employee access.
                    </p>
                    <div class="flex gap-3">
                        <button @click="closeConvertModal"
                            class="flex-1 py-3 text-slate-500 font-bold text-xs uppercase">Cancel</button>
                        <button @click="convertTrainee"
                            class="flex-1 py-3 bg-emerald-600 text-white rounded-xl text-xs font-black uppercase shadow-lg hover:bg-emerald-700 transition-all">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}

.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>