<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Clock,
    Calendar as CalendarIcon,
    ChevronLeft,
    ChevronRight,
    MoreHorizontal,
    FileText,
    BadgeCheck,
    DoorOpen,
    Flag,
    Timer,
    Activity,
    X,
    User as UserIcon,
    Mail,
    Camera
} from 'lucide-vue-next';

// Define props to receive live data from AppController.php
const props = defineProps({
    user: Object,
    today_log: Object,
    assigned_shift: Object,
    attendance_history: Array
});

// --- PHOTO & IDENTITY LOGIC ---
const isEditModalOpen = ref(false);
const photoInput = ref(null);
const photoPreview = ref(null);

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    photo: null, // Field for the image file
});

/**
 * Resolves the profile photo URL. 
 * If the user has a path in DB, it points to storage; otherwise, uses a UI avatar.
 */
const userPhotoUrl = computed(() => {
    return props.user.profile_photo_path
        ? `/storage/${props.user.profile_photo_path}`
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(props.user.name)}&background=random&color=fff`;
});

const openEditModal = () => {
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    photoPreview.value = null;
    form.reset();
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const handlePhotoChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    form.photo = file;

    // Create a local preview URL for the UI
    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const submitProfileUpdate = () => {
    /**
     * FIX: Standard PHP/Laravel cannot process 'multipart/form-data' with PATCH.
     * We use Method Spoofing: Send a POST request but tell Laravel to treat it as PATCH.
     */
    form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
        onError: (errors) => {
            console.error('Update failed:', errors);
        }
    });
};

// --- DASHBOARD LOGIC ---
const isClockedIn = computed(() => !!props.today_log?.clock_in && !props.today_log?.clock_out);
const shiftDisplay = computed(() => props.assigned_shift?.shift_type || 'No Shift Today');
const shiftTimeRange = computed(() => props.assigned_shift?.schedule_range || '--:-- to --:--');

const schedule = [
    {
        time: props.assigned_shift ? 'Duty' : 'N/A',
        title: shiftDisplay.value,
        type: 'Assigned Schedule',
        color: props.assigned_shift ? 'bg-purple-100 text-purple-700 border-purple-200' : 'bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-zinc-500 border-slate-200'
    },
];

const attendanceRate = computed(() => props.attendance_history.length > 0 ? 100 : 0);
</script>

<template>

    <Head title="Employee Dashboard" />

    <AuthenticatedLayout>
        <div class="p-6 bg-[#f8faff] min-h-screen relative">
            <div class="max-w-[1400px] mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                    <div class="lg:col-span-8 space-y-8">
                        <div class="flex justify-between items-center">
                            <h1 class="text-3xl font-bold text-slate-800 dark:text-zinc-200 italic uppercase tracking-tighter">
                                System <span class="text-blue-600 dark:text-blue-400 font-light">Overview</span>
                            </h1>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div
                                class="bg-white dark:bg-zinc-900 rounded-[2rem] p-8 shadow-sm border border-slate-100 dark:border-zinc-800 flex flex-col items-center justify-center relative overflow-hidden">
                                <span
                                    class="absolute top-4 left-6 text-[10px] font-black uppercase text-slate-400 dark:text-zinc-500 tracking-widest">Efficiency</span>
                                <div class="relative size-32 flex items-center justify-center">
                                    <svg class="size-full -rotate-90" viewBox="0 0 36 36">
                                        <circle cx="18" cy="18" r="16" fill="none" class="stroke-slate-100"
                                            stroke-width="3"></circle>
                                        <circle cx="18" cy="18" r="16" fill="none" class="stroke-blue-600"
                                            stroke-width="3" :stroke-dasharray="`${attendanceRate}, 100`"
                                            stroke-linecap="round"></circle>
                                    </svg>
                                    <span class="absolute text-3xl font-black text-slate-800 dark:text-zinc-200 italic">{{ attendanceRate
                                        }}%</span>
                                </div>
                                <p
                                    class="mt-4 text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest text-center">
                                    Active Service Records</p>
                            </div>

                            <div
                                class="relative group h-full min-h-[320px] rounded-[2rem] overflow-hidden shadow-xl shadow-blue-500/5 border border-slate-100 dark:border-zinc-800 bg-slate-900">
                                <img :src="userPhotoUrl" alt="Profile"
                                    class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110 opacity-80" />
                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent">
                                </div>
                                <div class="absolute inset-0 p-8 flex flex-col justify-end text-white">
                                    <div class="flex items-center gap-2 mb-2">
                                        <h2 class="text-2xl font-black italic uppercase tracking-tight">{{ user.name }}
                                        </h2>
                                        <!-- <BadgeCheck class="size-5 text-blue-400 fill-white" /> -->
                                    </div>
                                    <p class="text-slate-300 dark:text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-6">
                                        {{ user.role }} | {{ user.position }} | ID: {{ user.id.toString().padStart(5,
                                            '0') }}
                                    </p>
                                    <div class="flex items-center justify-between">
                                        <button @click="openEditModal"
                                            class="bg-white dark:bg-zinc-900 text-slate-900 dark:text-zinc-100 px-6 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-50 transition-colors shadow-lg">
                                            Edit Identity
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div :class="isClockedIn ? 'bg-emerald-600 shadow-emerald-200' : 'bg-indigo-600 shadow-indigo-200'"
                                class="rounded-[2rem] p-8 text-white flex flex-col justify-between shadow-lg transition-colors duration-500 relative overflow-hidden min-h-[220px]">
                                <div class="z-10 flex items-center gap-3">
                                    <div class="bg-white/20 p-3 rounded-2xl backdrop-blur-md">
                                        <Clock class="size-5" />
                                    </div>
                                    <h3 class="font-black uppercase text-[11px] tracking-[0.2em]">{{ isClockedIn ?
                                        'DutyActive' : 'Shift Status' }}</h3>
                                </div>
                                <div class="z-10 mt-6">
                                    <p class="text-3xl font-black italic uppercase tracking-tighter">{{ shiftDisplay }}
                                    </p>
                                    <p class="text-[10px] font-bold text-white/70 uppercase tracking-widest mt-1">{{
                                        shiftTimeRange }}</p>
                                </div>
                                <div class="z-10 mt-6 flex items-center justify-between">
                                    <Link :href="route('employee.ui.clock')"
                                        class="flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-xl transition-colors">
                                        <span class="text-[9px] font-black uppercase tracking-widest">Attendance
                                            Portal</span>
                                        <ChevronRight class="size-4" />
                                    </Link>
                                    <Timer v-if="isClockedIn"
                                        class="size-12 opacity-20 absolute -right-2 -bottom-2 animate-spin-slow" />
                                </div>
                            </div>

                            <div class="bg-white dark:bg-zinc-900 rounded-[2rem] p-8 shadow-sm border border-slate-100 dark:border-zinc-800 min-h-[220px]">
                                <div class="flex justify-between items-center mb-6">
                                    <h3
                                        class="text-slate-800 dark:text-zinc-200 font-black flex items-center gap-2 uppercase text-[11px] tracking-widest">
                                        <Activity class="size-4 text-blue-600 dark:text-blue-400" /> Recent Activity
                                    </h3>
                                    <MoreHorizontal class="size-4 text-slate-300 dark:text-zinc-500" />
                                </div>
                                <div v-if="attendance_history.length > 0" class="space-y-4">
                                    <div v-for="log in attendance_history" :key="log.date"
                                        class="flex items-center justify-between p-3 hover:bg-slate-50 dark:bg-zinc-800 rounded-2xl transition-colors border border-transparent hover:border-slate-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-3">
                                            <div class="bg-blue-50 dark:bg-blue-900/30 p-2 rounded-xl text-blue-600 dark:text-blue-400">
                                                <CalendarIcon class="size-4" />
                                            </div>
                                            <div>
                                                <p class="text-xs font-black uppercase text-slate-700 dark:text-zinc-300 tracking-tighter">
                                                    {{ log.date }}</p>
                                                <p :class="log.status === 'On-Time' ? 'text-emerald-500' : 'text-amber-500'"
                                                    class="text-[8px] font-black uppercase tracking-widest">{{
                                                        log.status }}</p>
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-mono font-black text-slate-400 dark:text-zinc-500 italic">{{
                                            log.clockIn }}</span>
                                    </div>
                                </div>
                                <div v-else class="py-10 text-center opacity-40">
                                    <Clock class="size-8 mx-auto mb-2 text-slate-300 dark:text-zinc-500" />
                                    <p class="text-[9px] font-black uppercase tracking-widest">No Logs Detected</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-4">
                        <div class="bg-white dark:bg-zinc-900 rounded-[2rem] shadow-sm border border-slate-100 dark:border-zinc-800 p-8 sticky top-8">
                            <h2 class="text-xl font-black text-slate-800 dark:text-zinc-200 uppercase italic tracking-tighter mb-8">Service
                                <span class="text-blue-600 dark:text-blue-400 font-light">Calendar</span>
                            </h2>
                            <div class="grid grid-cols-7 gap-1 text-center mb-8">
                                <span v-for="d in ['S', 'M', 'T', 'W', 'T', 'F', 'S']" :key="d"
                                    class="text-[9px] font-black text-slate-300 dark:text-zinc-500 uppercase">{{ d }}</span>
                                <div v-for="i in 31" :key="i"
                                    :class="['aspect-square flex items-center justify-center text-[10px] font-black rounded-xl transition-all cursor-default', i === new Date().getDate() ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'text-slate-500 dark:text-zinc-400 hover:bg-slate-50']">
                                    {{ i }}</div>
                            </div>
                            <hr class="border-slate-50 dark:border-zinc-800 mb-8" />
                            <div class="space-y-6 relative">
                                <div class="absolute left-[39px] top-0 h-full w-[1px] bg-slate-50 dark:bg-zinc-800"></div>
                                <div v-for="item in schedule" :key="item.time" class="flex gap-6 relative group">
                                    <span
                                        class="text-[10px] font-black text-slate-300 dark:text-zinc-500 w-10 text-right uppercase mt-4">{{
                                            item.time }}</span>
                                    <div class="flex-1">
                                        <div :class="item.color"
                                            class="p-5 rounded-2xl border shadow-sm transition-all hover:translate-x-1">
                                            <p class="text-[9px] font-black opacity-60 uppercase tracking-[0.2em]">{{
                                                item.type }}</p>
                                            <p class="text-xs font-black mt-1 uppercase italic tracking-tight">{{
                                                item.title }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <Transition enter-active-class="duration-300 ease-out" enter-from-class="opacity-0"
                enter-to-class="opacity-100" leave-active-class="duration-200 ease-in" leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="isEditModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6">
                    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-md" @click="closeEditModal"></div>

                    <div
                        class="relative bg-white dark:bg-zinc-900 w-full max-w-lg rounded-[2.5rem] shadow-2xl overflow-hidden transform transition-all scale-100">
                        <div class="p-8 border-b border-slate-50 dark:border-zinc-800 flex items-center justify-between bg-slate-50/30">
                            <div>
                                <h3 class="text-xl font-black text-slate-800 dark:text-zinc-200 uppercase italic tracking-tighter">Edit
                                    <span class="text-blue-600 dark:text-blue-400 font-light">Identity</span>
                                </h3>
                                <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest mt-1">Update
                                    your system credentials</p>
                            </div>
                            <button @click="closeEditModal"
                                class="p-2 hover:bg-white dark:bg-zinc-900 rounded-xl transition-colors text-slate-400 dark:text-zinc-500 hover:text-slate-600 border border-transparent hover:border-slate-100 dark:border-zinc-800">
                                <X class="size-5" />
                            </button>
                        </div>

                        <form @submit.prevent="submitProfileUpdate" class="p-8 space-y-6">
                            <div class="flex justify-center mb-4">
                                <div class="relative group">
                                    <input type="file" class="hidden" ref="photoInput" @change="handlePhotoChange"
                                        accept="image/*">
                                    <div
                                        class="size-24 rounded-[2rem] bg-slate-100 dark:bg-zinc-800 border-4 border-white shadow-sm overflow-hidden flex items-center justify-center">
                                        <img v-if="photoPreview" :src="photoPreview" class="size-full object-cover" />
                                        <img v-else :src="userPhotoUrl" class="size-full object-cover" />
                                    </div>
                                    <button type="button" @click="selectNewPhoto"
                                        class="absolute -bottom-1 -right-1 bg-blue-600 text-white p-2 rounded-xl shadow-lg hover:scale-110 transition-transform">
                                        <Camera class="size-4" />
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <div class="space-y-2">
                                    <label
                                        class="text-[10px] font-black uppercase text-slate-400 dark:text-zinc-500 tracking-[0.2em] ml-1">Full
                                        Name</label>
                                    <div class="relative group">
                                        <div
                                            class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 transition-colors group-focus-within:text-blue-600">
                                            <UserIcon class="size-4" />
                                        </div>
                                        <input v-model="form.name" type="text"
                                            class="w-full bg-slate-50 dark:bg-zinc-800 border-none rounded-2xl py-4 pl-12 pr-4 text-xs font-bold text-slate-700 dark:text-zinc-300 focus:ring-2 focus:ring-blue-600/10 placeholder:text-slate-300 transition-all"
                                            placeholder="Enter your name" required />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label
                                        class="text-[10px] font-black uppercase text-slate-400 dark:text-zinc-500 tracking-[0.2em] ml-1">Email
                                        Address</label>
                                    <div class="relative group">
                                        <div
                                            class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 transition-colors group-focus-within:text-blue-600">
                                            <Mail class="size-4" />
                                        </div>
                                        <input v-model="form.email" type="email"
                                            class="w-full bg-slate-50 dark:bg-zinc-800 border-none rounded-2xl py-4 pl-12 pr-4 text-xs font-bold text-slate-700 dark:text-zinc-300 focus:ring-2 focus:ring-blue-600/10 placeholder:text-slate-300 transition-all"
                                            placeholder="Enter your email" required />
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-3 pt-4">
                                <button type="button" @click="closeEditModal"
                                    class="flex-1 px-8 py-4 rounded-2xl bg-slate-50 dark:bg-zinc-800 text-slate-400 dark:text-zinc-500 text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all border border-transparent">Cancel</button>
                                <button type="submit" :disabled="form.processing"
                                    class="flex-1 px-8 py-4 rounded-2xl bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-blue-700 transition-all shadow-lg shadow-blue-200 disabled:opacity-50">
                                    {{ form.processing ? 'Updating...' : 'Save Changes' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
button:active {
    transform: scale(0.96);
}

.animate-spin-slow {
    animation: spin 8s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}
</style>