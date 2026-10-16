<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';
import {
    Plane,
    Calendar as CalendarIcon,
    Clock,
    CheckCircle2,
    XCircle
} from 'lucide-vue-next';

// 1. Properly receiving real data from LeaveController
const props = defineProps({
    leaveHistory: {
        type: Array,
        default: () => []
    }
});

const form = useForm({
    leave_type: '',
    start_date: '',
    end_date: '',
    reason: '',
});

const submit = () => {
    form.post(route('employee.leave.store'), {
        onSuccess: () => {
            form.reset();
        },
    });
};

const getStatusStyles = (status) => {
    if (!status) return 'bg-slate-100 dark:bg-zinc-800 text-slate-600';
    const styles = {
        'approved': 'bg-emerald-100 text-emerald-700',
        'pending': 'bg-amber-100 text-amber-700',
        'rejected': 'bg-rose-100 text-rose-700',
    };
    return styles[status.toLowerCase()] || 'bg-slate-100 dark:bg-zinc-800 text-slate-600';
};

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.message);

// Calculated real-time balances based on leaveHistory
const leaveBalances = computed(() => {
    const sickUsed = props.leaveHistory
        .filter(l => l.leave_type === 'sick' && l.status === 'approved').length;
    const vacUsed = props.leaveHistory
        .filter(l => l.leave_type === 'vacation' && l.status === 'approved').length;

    return [
        { type: 'Sick Leave', used: sickUsed, total: 10, color: 'stroke-rose-500', text: 'text-rose-500' },
        { type: 'Vacation', used: vacUsed, total: 15, color: 'stroke-blue-600', text: 'text-blue-600' },
    ];
});
</script>

<template>

    <Head title="Leave Requests" />

    <AuthenticatedLayout>
        <div class="p-6 bg-[#f8faff] min-h-screen">
            <div class="max-w-[1400px] mx-auto">

                <Transition enter-active-class="transition ease-out duration-300"
                    enter-from-class="transform opacity-0 -translate-y-2"
                    enter-to-class="transform opacity-100 translate-y-0">
                    <div v-if="flashSuccess"
                        class="mb-6 p-4 bg-emerald-500 text-white rounded-2xl shadow-lg flex items-center gap-3 font-bold uppercase text-xs tracking-widest">
                        <CheckCircle2 class="size-5" />
                        {{ flashSuccess }}
                    </div>
                </Transition>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <div class="lg:col-span-8 space-y-8">
                        <div>
                            <h1 class="text-3xl font-black text-slate-900 dark:text-zinc-100 uppercase tracking-tight">
                                Leave <span class="text-blue-600 dark:text-blue-400 font-light">Management</span>
                            </h1>
                            <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium">Request time off and track your balances</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div v-for="balance in leaveBalances" :key="balance.type"
                                class="bg-white dark:bg-zinc-900 rounded-[2.5rem] p-8 shadow-sm border border-slate-100 dark:border-zinc-800 flex items-center gap-8 transition-transform hover:scale-[1.02]">
                                <div class="relative size-24 flex items-center justify-center flex-shrink-0">
                                    <svg class="size-full -rotate-90" viewBox="0 0 36 36">
                                        <circle cx="18" cy="18" r="16" fill="none" class="stroke-slate-100"
                                            stroke-width="3"></circle>
                                        <circle cx="18" cy="18" r="16" fill="none" :class="balance.color"
                                            stroke-width="3"
                                            :stroke-dasharray="`${(balance.used / balance.total) * 100}, 100`"
                                            stroke-linecap="round"></circle>
                                    </svg>
                                    <span class="absolute text-xl font-black text-slate-800 dark:text-zinc-200 italic">{{ balance.total -
                                        balance.used }}</span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-1">
                                        Available Days</p>
                                    <h3 :class="['text-xl font-black uppercase italic', balance.text]">{{ balance.type
                                        }}</h3>
                                    <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium mt-1">{{ balance.used }} days used of
                                        {{ balance.total }}</p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-blue-600 rounded-[2.5rem] p-10 shadow-xl shadow-blue-200 text-white relative overflow-hidden">
                            <div class="relative z-10">
                                <h3 class="text-xl font-black uppercase italic mb-8 flex items-center gap-3">
                                    <Plane class="size-6 rotate-45" /> New Leave Application
                                </h3>
                                <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <InputLabel for="leave_type" value="Leave Type"
                                            class="text-blue-100 text-[10px] font-bold uppercase ml-1" />
                                        <select id="leave_type" v-model="form.leave_type"
                                            class="w-full bg-white/10 border-white/20 rounded-xl text-sm font-bold text-white focus:ring-white/30">
                                            <option value="" disabled class="text-slate-900 dark:text-zinc-100">Select Type</option>
                                            <option value="sick" class="text-slate-900 dark:text-zinc-100">Sick Leave</option>
                                            <option value="vacation" class="text-slate-900 dark:text-zinc-100">Vacation</option>
                                            <option value="personal" class="text-slate-900 dark:text-zinc-100">Personal</option>
                                        </select>
                                        <InputError :message="form.errors.leave_type" class="text-white mt-1" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <InputLabel value="Start Date"
                                                class="text-blue-100 text-[10px] font-bold uppercase" />
                                            <TextInput type="date" v-model="form.start_date"
                                                class="w-full bg-white/10 border-white/20 text-white" />
                                            <InputError :message="form.errors.start_date" class="text-white" />
                                        </div>
                                        <div>
                                            <InputLabel value="End Date"
                                                class="text-blue-100 text-[10px] font-bold uppercase" />
                                            <TextInput type="date" v-model="form.end_date"
                                                class="w-full bg-white/10 border-white/20 text-white" />
                                            <InputError :message="form.errors.end_date" class="text-white" />
                                        </div>
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel value="Reason"
                                            class="text-blue-100 text-[10px] font-bold uppercase" />
                                        <textarea v-model="form.reason"
                                            class="w-full bg-white/10 border-white/20 rounded-2xl text-white text-sm"
                                            rows="3"></textarea>
                                        <InputError :message="form.errors.reason" class="text-white" />
                                    </div>
                                    <div class="md:col-span-2 pt-4">
                                        <button type="submit" :disabled="form.processing"
                                            class="w-full bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 font-black uppercase py-4 rounded-2xl shadow-lg hover:bg-blue-50 transition-all text-xs disabled:opacity-50">
                                            {{ form.processing ? 'Submitting...' : 'Submit Request' }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-4">
                        <div class="bg-white dark:bg-zinc-900 rounded-[2.5rem] shadow-sm border border-slate-100 dark:border-zinc-800 p-8 h-full">
                            <h2 class="text-lg font-black text-slate-800 dark:text-zinc-200 italic mb-10">Application Status</h2>
                            <div class="space-y-6 relative">
                                <div class="absolute left-[15px] top-0 h-full w-[2px] bg-slate-50 dark:bg-zinc-800"></div>

                                <div v-if="leaveHistory.length === 0" class="ml-8 text-xs text-slate-400 dark:text-zinc-500 italic">
                                    No records found in database.
                                </div>

                                <div v-for="history in leaveHistory" :key="history.id"
                                    class="flex gap-4 relative group">
                                    <div
                                        class="size-8 rounded-full bg-white dark:bg-zinc-900 border-4 border-slate-50 dark:border-zinc-800 z-10 shadow-sm flex items-center justify-center">
                                        <CheckCircle2 v-if="history.status === 'approved'"
                                            class="size-3 text-emerald-500" />
                                        <XCircle v-else-if="history.status === 'rejected'"
                                            class="size-3 text-rose-500" />
                                        <Clock v-else class="size-3 text-amber-500" />
                                    </div>
                                    <div
                                        class="flex-1 bg-slate-50/50 p-4 rounded-[1.5rem] border border-slate-100 dark:border-zinc-800 group-hover:bg-white dark:bg-zinc-900 transition-colors">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p
                                                    class="text-[10px] font-black text-slate-400 dark:text-zinc-500 uppercase tracking-widest">
                                                    {{ history.start_date }} - {{ history.end_date }}
                                                </p>
                                                <p class="text-sm font-bold text-slate-800 dark:text-zinc-200 mt-1 uppercase">{{
                                                    history.leave_type }}</p>
                                            </div>
                                            <span
                                                :class="['text-[8px] font-black uppercase px-2 py-1 rounded-lg', getStatusStyles(history.status)]">
                                                {{ history.status }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>