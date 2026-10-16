<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';
import { 
    Power, 
    Timer, 
    MapPin, 
    MapPinOff, 
    Lock, 
    ChevronLeft, 
    ChevronRight,
    Sunrise,
    Sunset,
    Moon
} from 'lucide-vue-next';

const props = defineProps({
    today_log: Object,
    assigned_shift: Object,
    history: Array,
    geofence_settings: Object 
});

const page = usePage();
const currentDistance = ref(null);

// --- DYNAMIC CALENDAR LOGIC ---
const now = new Date();
const currentMonth = ref(now.getMonth());
const currentYear = ref(now.getFullYear());
const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
const daysInMonth = computed(() => new Date(currentYear.value, currentMonth.value + 1, 0).getDate());
const firstDayOfMonth = computed(() => new Date(currentYear.value, currentMonth.value, 1).getDay());

const hasLog = (day) => {
    const d = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return props.history?.some(h => h.date === d);
};

// --- GEOFENCE MATH ---
const getDistance = (lat1, lon1, lat2, lon2) => {
    const R = 6371e3; // meters
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon/2) * Math.sin(dLon/2);
    return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
};

const isOutOfRange = computed(() => {
    if (page.props.auth.user.role === 'CEO') return false;
    const loc = page.props.auth.location;
    if (!loc || !loc.lat || !props.geofence_settings) return true;

    const dist = getDistance(
        props.geofence_settings.latitude, 
        props.geofence_settings.longitude, 
        loc.lat, 
        loc.lng
    );
    currentDistance.value = Math.round(dist);
    return dist > props.geofence_settings.range_radius;
});

// --- UI LOGIC ---
const isClockedIn = computed(() => !!props.today_log?.clock_in && !props.today_log?.clock_out);

const clockButtonText = computed(() => {
    if (props.today_log?.clock_out) return 'Log Finished';
    if (isClockedIn.value) return 'Clock Out';
    if (!page.props.auth.location) return 'Locating...';
    if (isOutOfRange.value) return 'Locked';
    return 'Clock In';
});

const handleClockToggle = () => {
    router.post(route('employee.attendance.toggle'), {}, { 
        preserveScroll: true 
    });
};

// --- LIVE TIME ---
const currentTime = ref(new Date().toLocaleTimeString());
let timer = null;
onMounted(() => { 
    timer = setInterval(() => { 
        currentTime.value = new Date().toLocaleTimeString() 
    }, 1000); 
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <Head title="Attendance Portal" />

    <AuthenticatedLayout>
        <div class="max-w-[1400px] mx-auto space-y-8">
            
            <div v-if="isOutOfRange && !isClockedIn && page.props.auth.location" 
                 class="p-5 bg-rose-50 border border-rose-100 rounded-[2rem] flex items-center gap-4 text-rose-600 shadow-sm animate-pulse">
                <MapPinOff class="size-6" />
                <div class="text-[11px] font-black uppercase tracking-widest">
                    Outside Perimeter: {{ currentDistance }}m / Allowed: {{ geofence_settings?.range_radius }}m
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <div class="lg:col-span-8 space-y-8">
                    
                    <div class="flex flex-col md:flex-row justify-between items-center bg-white dark:bg-zinc-900 p-3 rounded-[2.5rem] shadow-sm border border-slate-100 dark:border-zinc-800">
                        <div class="px-8 py-4 text-center md:text-left md:border-r border-slate-100 dark:border-zinc-800 mb-4 md:mb-0">
                            <p class="text-[9px] font-black text-slate-400 dark:text-zinc-500 uppercase tracking-widest">Local System Time</p>
                            <p class="text-2xl font-mono font-black italic text-slate-800 dark:text-zinc-200">{{ currentTime }}</p>
                        </div>

                        <button @click="handleClockToggle"
                            :disabled="(isOutOfRange && !isClockedIn) || !page.props.auth.location || today_log?.clock_out"
                            :class="[ 'w-full md:w-auto px-12 py-5 rounded-[2rem] font-black uppercase text-xs tracking-[0.2em] transition-all duration-300', isClockedIn ? 'bg-rose-500 text-white shadow-lg shadow-rose-100' : ((isOutOfRange || !page.props.auth.location) && !isClockedIn ? 'bg-slate-100 dark:bg-zinc-800 text-slate-300 dark:text-zinc-500 cursor-not-allowed' : 'bg-emerald-500 text-white shadow-lg shadow-emerald-100 hover:scale-[1.02]') ]">
                            <Power class="size-4 inline mr-2 mb-1" />
                            {{ clockButtonText }}
                        </button>
                    </div>

                    <div class="bg-white dark:bg-zinc-900 rounded-[2.5rem] border border-slate-100 dark:border-zinc-800 shadow-sm overflow-hidden">
                        <div class="p-8 border-b border-slate-50 dark:border-zinc-800">
                            <h3 class="text-xs font-black uppercase tracking-widest text-slate-800 dark:text-zinc-200 italic">Duty Cycle History</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50/50 text-[10px] font-black uppercase text-slate-400 dark:text-zinc-500 tracking-widest">
                                    <tr>
                                        <th class="p-6">Date</th>
                                        <th class="p-6 text-center">In / Out</th>
                                        <th class="p-6 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <tr v-for="log in history.slice(0, 5)" :key="log.id" class="hover:bg-slate-50/30 transition-colors">
                                        <td class="p-6 text-xs font-black text-slate-700 dark:text-zinc-300 italic uppercase">{{ log.date }}</td>
                                        <td class="p-6 text-xs font-mono text-center text-slate-500 dark:text-zinc-400">
                                            {{ log.clock_in }} <span class="mx-2 opacity-30">|</span> {{ log.clock_out || 'Active' }}
                                        </td>
                                        <td class="p-6 text-right">
                                            <span :class="log.status === 'On-Time' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'" 
                                                  class="px-4 py-2 rounded-xl text-[9px] font-black uppercase border tracking-tighter">
                                                {{ log.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4 space-y-8">
                    <div class="bg-white dark:bg-zinc-900 p-8 rounded-[2.5rem] border border-slate-100 dark:border-zinc-800 shadow-sm">
                        <div class="flex items-center justify-between mb-8 px-2">
                            <h3 class="font-black uppercase italic text-sm tracking-tighter">
                                {{ monthNames[currentMonth] }} <span class="text-blue-600 dark:text-blue-400 font-light">{{ currentYear }}</span>
                            </h3>
                            <div class="flex gap-1">
                                <ChevronLeft class="size-4 text-slate-300 dark:text-zinc-500 cursor-not-allowed" />
                                <ChevronRight class="size-4 text-slate-300 dark:text-zinc-500 cursor-not-allowed" />
                            </div>
                        </div>

                        <div class="grid grid-cols-7 gap-y-2 text-center">
                            <div v-for="d in ['S','M','T','W','T','F','S']" :key="d" 
                                 class="text-[9px] font-black text-slate-300 dark:text-zinc-500 uppercase pb-2">{{ d }}</div>
                            
                            <div v-for="e in firstDayOfMonth" :key="'e'+e"></div>
                            
                            <div v-for="day in daysInMonth" :key="day" class="relative py-1">
                                <div :class="[ 'size-8 mx-auto flex items-center justify-center rounded-xl text-[10px] font-black transition-all', day === now.getDate() && currentMonth === now.getMonth() ? 'bg-blue-600 text-white shadow-lg shadow-blue-100' : (hasLog(day) ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600' : 'text-slate-600') ]">
                                    {{ day }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 rounded-[2.5rem] p-8 text-white relative overflow-hidden group">
                        <MapPin class="absolute right-0 bottom-0 size-32 -mb-8 -mr-8 opacity-10 group-hover:scale-110 transition-transform duration-700" />
                        <div class="relative z-10">
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-blue-400 mb-4">Current Duty Assignment</p>
                            <h3 class="text-2xl font-black italic uppercase tracking-tighter">
                                {{ assigned_shift?.shift_type || 'No Assigned Shift' }}
                            </h3>
                            <p class="text-xs font-medium text-slate-400 dark:text-zinc-500 mt-2">
                                {{ assigned_shift?.schedule_range || 'Contact Admin for Schedule' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Scoped styles kept empty to avoid PostCSS conflicts with script tags */
</style>