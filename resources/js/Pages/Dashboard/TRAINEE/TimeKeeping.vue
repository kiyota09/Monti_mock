<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { ClockIcon, CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
  user: any;
  todayAttendance: {
    id: number;
    date: string;
    clockIn: string | null;
    clockOut: string | null;
    status: string;
  } | null;
  currentStatus: 'not_clocked_in' | 'clocked_in' | 'clocked_out';
  weeklyRecords: Array<{
    id: number;
    date: string;
    dayOfWeek: string;
    clockIn: string | null;
    clockOut: string | null;
    duration: string | null;
    status: string;
  }>;
}>();

const currentTime = ref<Date>(new Date());
const isLoading = ref(false);
const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

onMounted(() => {
  const interval = setInterval(() => {
    currentTime.value = new Date();
  }, 1000);
  return () => clearInterval(interval);
});

const formattedTime = computed(() => format(currentTime.value, 'HH:mm:ss'));
const formattedDate = computed(() => format(currentTime.value, 'EEEE, MMMM d, yyyy'));

const canClockIn = computed(() => {
  return props.currentStatus === 'not_clocked_in' || props.currentStatus === 'clocked_out';
});

const canClockOut = computed(() => {
  return props.currentStatus === 'clocked_in';
});

const statusDisplay = computed(() => {
  switch (props.currentStatus) {
    case 'clocked_in':
      return {
        icon: CheckCircleIcon,
        color: 'text-green-600',
        bgColor: 'bg-green-50',
        borderColor: 'border-green-200',
        label: 'Clocked In',
      };
    case 'clocked_out':
      return {
        icon: XCircleIcon,
        color: 'text-red-600',
        bgColor: 'bg-red-50',
        borderColor: 'border-red-200',
        label: 'Clocked Out',
      };
    default:
      return {
        icon: ExclamationTriangleIcon,
        color: 'text-yellow-600',
        bgColor: 'bg-yellow-50',
        borderColor: 'border-yellow-200',
        label: 'Not Clocked In',
      };
  }
});

const handleClockIn = () => {
  if (!canClockIn.value) {
    errorMessage.value = 'You have already clocked in. Please clock out first.';
    return;
  }

  isLoading.value = true;
  router.post('/trainee/timekeeping/clock', { action: 'in' }, {
    onSuccess: () => {
      successMessage.value = 'Clocked in successfully!';
      isLoading.value = false;
      setTimeout(() => (successMessage.value = null), 4000);
    },
    onError: () => {
      errorMessage.value = 'Failed to clock in. Please try again.';
      isLoading.value = false;
      setTimeout(() => (errorMessage.value = null), 4000);
    },
  });
};

const handleClockOut = () => {
  if (!canClockOut.value) {
    errorMessage.value = 'Please clock in first before clocking out.';
    return;
  }

  isLoading.value = true;
  router.post('/trainee/timekeeping/clock', { action: 'out' }, {
    onSuccess: () => {
      successMessage.value = 'Clocked out successfully!';
      isLoading.value = false;
      setTimeout(() => (successMessage.value = null), 4000);
    },
    onError: () => {
      errorMessage.value = 'Failed to clock out. Please try again.';
      isLoading.value = false;
      setTimeout(() => (errorMessage.value = null), 4000);
    },
  });
};

const formatTimeDisplay = (timeString: string | null): string => {
  if (!timeString) return '--:--';
  const parts = timeString.split(':');
  return `${parts[0]}:${parts[1]}`;
};
</script>

<template>

  <Head title="Time Keeping" />
  <AuthenticatedLayout>
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900 dark:text-zinc-100">Time Keeping</h1>
      <p class="text-gray-500 dark:text-zinc-400 mt-2">Time in/out and manage your daily time records</p>
    </div>

    <!-- Alerts -->
    <transition name="fade">
      <div v-if="successMessage" class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
        <p class="text-green-800 dark:text-green-300 font-medium">{{ successMessage }}</p>
      </div>
    </transition>
    <transition name="fade">
      <div v-if="errorMessage" class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
        <p class="text-red-800 dark:text-red-300 font-medium">{{ errorMessage }}</p>
      </div>
    </transition>

    <!-- Main Clock Section -->
    <div class="bg-gradient-to-br from-indigo-600 to-blue-600 rounded-lg shadow-lg p-8 mb-8 text-white">
      <div class="text-center">
        <p class="text-indigo-100 mb-2">{{ formattedDate }}</p>
        <p class="text-6xl font-bold font-mono mb-8">{{ formattedTime }}</p>

        <!-- Status Indicator -->
        <div :class="['inline-flex items-center gap-3 px-6 py-3 rounded-full mb-8', statusDisplay.bgColor]">
          <component :is="statusDisplay.icon" :class="['w-6 h-6', statusDisplay.color]" />
          <span :class="['text-lg font-semibold', statusDisplay.color]">{{ statusDisplay.label }}</span>
        </div>

        <!-- Action Buttons -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-md mx-auto">
          <button @click="handleClockIn" :disabled="!canClockIn || isLoading" :class="[ 'relative px-6 py-4 rounded-lg font-bold text-lg transition-all duration-300', canClockIn ? 'bg-white dark:bg-zinc-900 text-green-600 dark:text-green-400 hover:bg-green-50 shadow-lg' : 'bg-gray-50 text-gray-400 cursor-not-allowed opacity-50', ]">
            <span v-if="!isLoading">Clock In</span>
            <span v-else class="flex items-center justify-center">
              <svg class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
            </span>
          </button>
          <button @click="handleClockOut" :disabled="!canClockOut || isLoading" :class="[ 'relative px-6 py-4 rounded-lg font-bold text-lg transition-all duration-300', canClockOut ? 'bg-white dark:bg-zinc-900 text-red-600 dark:text-red-400 hover:bg-red-50 shadow-lg' : 'bg-gray-50 text-gray-400 cursor-not-allowed opacity-50', ]">
            <span v-if="!isLoading">Clock Out</span>
            <span v-else class="flex items-center justify-center">
              <svg class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- Today's Record -->
    <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-6 mb-8">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4 flex items-center gap-2">
        <ClockIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
        Today's Record
      </h2>

      <div v-if="props.todayAttendance" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-800">
          <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Clock In</p>
          <p class="text-2xl font-bold text-blue-900 dark:text-blue-200 mt-2">{{ formatTimeDisplay(props.todayAttendance.clockIn) }}</p>
        </div>
        <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
          <p class="text-sm text-red-600 dark:text-red-400 font-medium">Clock Out</p>
          <p class="text-2xl font-bold text-red-900 mt-2">{{ formatTimeDisplay(props.todayAttendance.clockOut) }}</p>
        </div>
        <div class="p-4 bg-gray-50 dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
          <p class="text-sm text-gray-600 dark:text-zinc-400 font-medium">Status</p>
          <span :class="[ 'inline-block mt-2 px-3 py-1 rounded-full text-sm font-semibold', props.todayAttendance.status === 'present' ? 'bg-green-100 dark:bg-green-900/30 text-green-800' : props.todayAttendance.status === 'late' ? 'bg-yellow-100 text-yellow-800' : props.todayAttendance.status === 'absent' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800', ]">
            {{ props.todayAttendance.status }}
          </span>
        </div>
      </div>

      <div v-else class="text-center py-8">
        <ExclamationTriangleIcon class="w-12 h-12 text-gray-400 dark:text-zinc-500 mx-auto mb-3" />
        <p class="text-gray-500 dark:text-zinc-400">No record for today yet. Click "Clock In" to get started.</p>
      </div>
    </div>

    <!-- Weekly Records Table -->
    <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4 flex items-center gap-2">
        <ClockIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
        This Week's Records
      </h2>

      <div v-if="props.weeklyRecords.length === 0" class="text-center py-8">
        <p class="text-gray-500 dark:text-zinc-400">No records for this week yet</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-zinc-300">Date</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-zinc-300">Day</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-zinc-300">Clock In</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-zinc-300">Clock Out</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-zinc-300">Duration</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-zinc-300">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="record in props.weeklyRecords" :key="record.id"
              class="border-b border-gray-200 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-800 dark:bg-zinc-800">
              <td class="px-4 py-3 text-gray-900 dark:text-zinc-100 font-medium">{{ record.date }}</td>
              <td class="px-4 py-3 text-gray-600 dark:text-zinc-400">{{ record.dayOfWeek }}</td>
              <td class="px-4 py-3 text-gray-600 dark:text-zinc-400">{{ formatTimeDisplay(record.clockIn) }}</td>
              <td class="px-4 py-3 text-gray-600 dark:text-zinc-400">{{ formatTimeDisplay(record.clockOut) }}</td>
              <td class="px-4 py-3 text-gray-600 dark:text-zinc-400">{{ record.duration || '--:--' }}</td>
              <td class="px-4 py-3">
                <span :class="[ 'px-3 py-1 rounded-full text-xs font-semibold', record.status === 'present' ? 'bg-green-100 dark:bg-green-900/30 text-green-800' : record.status === 'late' ? 'bg-yellow-100 text-yellow-800' : record.status === 'absent' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800', ]">
                  {{ record.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: all 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>