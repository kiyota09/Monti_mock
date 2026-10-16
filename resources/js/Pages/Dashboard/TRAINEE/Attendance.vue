<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { format, parseISO } from 'date-fns';
import { CalendarIcon, ChevronLeftIcon, ChevronRightIcon, ClockIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
  user: any; // optional, not used here
  currentMonth: number;
  currentYear: number;
  calendarDays: Array<{
    day: number;
    date: string;
    dayOfWeek: string;
    status: string | null;
    statusClass: string;
    hasRecord: boolean;
  }>;
  statistics: {
    totalDays: number;
    present: number;
    late: number;
    absent: number;
    onLeave: number;
    attendanceRate: number;
  };
  detailedRecords: Array<{
    id: number;
    date: string;
    clockIn: string | null;
    clockOut: string | null;
    duration: string | null;
    status: string;
    statusBadge: { label: string; color: string };
  }>;
}>();

// Format current date
const currentDate = computed(() => format(new Date(), 'EEEE, MMMM d, yyyy'));

// Get month name
const monthName = computed(() => {
  const date = new Date(props.currentYear, props.currentMonth - 1);
  return format(date, 'MMMM yyyy');
});

const navigateMonth = (newMonth: number, newYear: number) => {
  if (newMonth < 1) {
    newMonth = 12;
    newYear--;
  } else if (newMonth > 12) {
    newMonth = 1;
    newYear++;
  }

  router.get('/trainee/attendance', {
    month: newMonth,
    year: newYear
  }, {
    preserveState: true,
    replace: true
  });
};

const goToPreviousMonth = () => navigateMonth(props.currentMonth - 1, props.currentYear);
const goToNextMonth = () => navigateMonth(props.currentMonth + 1, props.currentYear);

const getStatusBadgeColor = (status: string) => {
  const colors = {
    present: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 border-green-200',
    late: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 border-yellow-200',
    absent: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 border-red-200',
    on_leave: 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 border-blue-200',
  };
  return colors[status as keyof typeof colors] || 'bg-gray-100 dark:bg-zinc-800 text-gray-800 dark:text-zinc-200 border-gray-200';
};

const formatTimeDisplay = (timeString: string | null): string => {
  if (!timeString) return '--:--';
  const time = timeString.includes('T') ? format(parseISO(timeString), 'HH:mm') : timeString.substring(0, 5);
  return time;
};
</script>

<template>
  <Head title="Attendance" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-zinc-100">Attendance Records</h1>
          <p class="text-sm text-gray-500 dark:text-zinc-400">{{ currentDate }}</p>
        </div>
        <Link href="/trainee/dashboard"
          class="inline-flex items-center px-4 py-2 bg-white dark:bg-zinc-900 border border-gray-300 dark:border-zinc-600 rounded-md font-semibold text-xs text-gray-700 dark:text-zinc-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
          Dashboard
        </Link>
      </div>
    </template>

    <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Statistics Cards -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
        <div v-for="(val, label) in {
          'Total Days': { v: props.statistics.totalDays, c: 'text-gray-900' },
          'Present': { v: props.statistics.present, c: 'text-green-600' },
          'Late': { v: props.statistics.late, c: 'text-yellow-600' },
          'Absent': { v: props.statistics.absent, c: 'text-red-600' },
          'Rate': { v: props.statistics.attendanceRate + '%', c: 'text-indigo-600' }
        }" :key="label" class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-zinc-800">
          <p class="text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">{{ label }}</p>
          <p :class="['text-2xl font-bold mt-1', val.c]">{{ val.v }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Calendar -->
        <div class="lg:col-span-2 bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-200 dark:border-zinc-700 overflow-hidden">
          <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                <CalendarIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              </div>
              <h2 class="text-lg font-bold text-gray-900 dark:text-zinc-100">{{ monthName }}</h2>
            </div>
            <div class="flex items-center bg-gray-50 dark:bg-zinc-800 rounded-lg p-1">
              <button @click="goToPreviousMonth"
                class="p-2 hover:bg-white dark:bg-zinc-900 hover:shadow-sm rounded-md transition text-gray-600 dark:text-zinc-400">
                <ChevronLeftIcon class="w-4 h-4" />
              </button>
              <button @click="goToNextMonth"
                class="p-2 hover:bg-white dark:bg-zinc-900 hover:shadow-sm rounded-md transition text-gray-600 dark:text-zinc-400">
                <ChevronRightIcon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="p-6">
            <div class="grid grid-cols-7 gap-px bg-gray-200 dark:bg-zinc-700 border border-gray-200 dark:border-zinc-700 rounded-lg overflow-hidden">
              <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day"
                class="bg-gray-50 dark:bg-zinc-800 py-2 text-center text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase">
                {{ day }}
              </div>
              <div v-for="day in props.calendarDays" :key="day.date" :class="[ 'relative min-h-[80px] p-2 bg-white dark:bg-zinc-900 transition-colors', day.hasRecord ? 'hover:brightness-95' : '', !day.day ? 'bg-gray-50/50' : '' ]">
                <span v-if="day.day"
                  :class="['text-sm font-medium', day.hasRecord ? 'text-gray-900' : 'text-gray-400']">
                  {{ day.day }}
                </span>
                <div v-if="day.hasRecord"
                  :class="['mt-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase text-center border truncate', day.statusClass]">
                  <!-- FIX: Handle null status safely -->
                  {{ day.status ? day.status.replace('_', ' ') : '' }}
                </div>
              </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-4 text-xs font-medium text-gray-500 dark:text-zinc-400 border-t pt-4">
              <div class="flex items-center gap-1.5"><span
                  class="w-3 h-3 rounded bg-green-100 dark:bg-green-900/30 border border-green-300"></span> Present</div>
              <div class="flex items-center gap-1.5"><span
                  class="w-3 h-3 rounded bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300"></span> Late</div>
              <div class="flex items-center gap-1.5"><span
                  class="w-3 h-3 rounded bg-red-100 dark:bg-red-900/30 border border-red-300"></span>
                Absent</div>
              <div class="flex items-center gap-1.5"><span
                  class="w-3 h-3 rounded bg-blue-100 dark:bg-blue-900/40 border border-blue-300"></span> Leave</div>
            </div>
          </div>
        </div>

        <!-- Detailed Log -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-gray-200 dark:border-zinc-700 flex flex-col">
          <div class="p-6 border-b border-gray-100 dark:border-zinc-800">
            <h2 class="text-lg font-bold text-gray-900 dark:text-zinc-100 flex items-center gap-2">
              <ClockIcon class="w-5 h-5 text-gray-400 dark:text-zinc-500" />
              Detailed Log
            </h2>
          </div>

          <div class="flex-1 overflow-y-auto max-h-[600px]">
            <div v-if="props.detailedRecords.length === 0" class="p-12 text-center">
              <p class="text-gray-400 dark:text-zinc-500 text-sm">No records found for this period.</p>
            </div>
            <div v-else class="divide-y divide-gray-100">
              <div v-for="record in props.detailedRecords" :key="record.id" class="p-4 hover:bg-gray-50 dark:hover:bg-zinc-800 dark:bg-zinc-800 transition">
                <div class="flex justify-between items-start mb-2">
                  <p class="font-bold text-gray-900 dark:text-zinc-100">{{ format(new Date(record.date), 'MMM d, yyyy') }}</p>
                  <span
                    :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase border', getStatusBadgeColor(record.status)]">
                    {{ record.statusBadge.label }}
                  </span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm text-gray-600 dark:text-zinc-400">
                  <div>
                    <span class="text-[10px] uppercase text-gray-400 dark:text-zinc-500 block">Clock In</span>
                    {{ formatTimeDisplay(record.clockIn) }}
                  </div>
                  <div>
                    <span class="text-[10px] uppercase text-gray-400 dark:text-zinc-500 block">Clock Out</span>
                    {{ formatTimeDisplay(record.clockOut) }}
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