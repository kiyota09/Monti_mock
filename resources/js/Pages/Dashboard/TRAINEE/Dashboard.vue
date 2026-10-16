<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { format } from 'date-fns';
import {
  CreditCardIcon,
  CalendarIcon,
  ClockIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<{
  user: any;
  attendancePercentage: number;
  currentPayroll: {
    id: number;
    gross_pay: number;
    net_pay: number;
    status: string;
  } | null;
  upcomingHolidays: Array<{
    id: number;
    date: string;
    name: string;
    type: string;
  }>;
  recentAttendance: Array<{
    date: string;
    clockIn: string | null;
    clockOut: string | null;
    status: string;
  }>;
  leaveBalance: {
    total: number;
    used: number;
    remaining: number;
  };
  attendanceStats: {
    present: number;
    late: number;
    absent: number;
    onLeave: number;
  };
}>();

const currentDate = computed(() => format(new Date(), 'EEEE, MMMM d, yyyy'));

const attendanceColorClass = computed(() => {
  const p = props.attendancePercentage;
  if (p >= 95) return 'text-green-600';
  if (p >= 80) return 'text-blue-600';
  if (p >= 70) return 'text-yellow-600';
  return 'text-red-600';
});

const attendanceBgClass = computed(() => {
  const p = props.attendancePercentage;
  if (p >= 95) return 'bg-green-500';
  if (p >= 80) return 'bg-blue-500';
  if (p >= 70) return 'bg-yellow-500';
  return 'bg-red-500';
});

const statusBgClass = (status: string): string => {
  return {
    pending: 'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200',
    approved: 'bg-green-50 dark:bg-green-900/20 border border-green-200',
    paid: 'bg-blue-50 dark:bg-blue-900/30 border border-blue-200',
    rejected: 'bg-red-50 dark:bg-red-900/20 border border-red-200',
  }[status] || 'bg-gray-50 dark:bg-zinc-800 border border-gray-200';
};
</script>

<template>

  <Head title="Trainee Dashboard" />
  <AuthenticatedLayout>
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900 dark:text-zinc-100">Welcome back, {{ props.user.name }}</h1>
      <p class="text-gray-500 dark:text-zinc-400 mt-2">{{ currentDate }}</p>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
      <!-- Attendance Card -->
      <div class="lg:col-span-2 bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 flex items-center gap-2">
            <CheckCircleIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
            Attendance Percentage
          </h2>
          <span :class="['text-3xl font-bold', attendanceColorClass]">
            {{ props.attendancePercentage }}%
          </span>
        </div>
        <div class="w-full bg-gray-200 dark:bg-zinc-700 rounded-full h-3">
          <div :class="['h-3 rounded-full transition-all duration-500', attendanceBgClass]"
            :style="{ width: `${props.attendancePercentage}%` }" />
        </div>
        <p class="text-sm text-gray-500 dark:text-zinc-400 mt-3">Last 30 days performance</p>
      </div>

      <!-- Leave Balance Card -->
      <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Leave Balance</h3>
        <div class="space-y-3">
          <div>
            <p class="text-sm text-gray-600 dark:text-zinc-400">Remaining Days</p>
            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ props.leaveBalance.remaining }}</p>
          </div>
          <div class="pt-2 border-t border-gray-200 dark:border-zinc-700">
            <p class="text-xs text-gray-500 dark:text-zinc-400">Used: {{ props.leaveBalance.used }} / Total: {{ props.leaveBalance.total }}
            </p>
          </div>
        </div>
      </div>

      <!-- Quick Actions Card -->
      <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Quick Actions</h3>
        <div class="space-y-2">
          <Link href="/trainee/timekeeping"
            class="block w-full px-3 py-2 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg font-medium hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition text-center text-sm">
            Check In/Out
          </Link>
          <Link href="/trainee/attendance"
            class="block w-full px-3 py-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg font-medium hover:bg-blue-100 transition text-center text-sm">
            View Attendance
          </Link>
        </div>
      </div>
    </div>

    <!-- Attendance Statistics -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-4">
        <p class="text-sm text-gray-600 dark:text-zinc-400 font-medium">Present Days</p>
        <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">{{ props.attendanceStats.present }}</p>
      </div>
      <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-4">
        <p class="text-sm text-gray-600 dark:text-zinc-400 font-medium">Late Days</p>
        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-2">{{ props.attendanceStats.late }}</p>
      </div>
      <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-4">
        <p class="text-sm text-gray-600 dark:text-zinc-400 font-medium">Absent Days</p>
        <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-2">{{ props.attendanceStats.absent }}</p>
      </div>
      <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-4">
        <p class="text-sm text-gray-600 dark:text-zinc-400 font-medium">Leave Days</p>
        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-2">{{ props.attendanceStats.onLeave }}</p>
      </div>
    </div>

    <!-- Recent Attendance & Current Payroll -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Recent Attendance -->
      <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4 flex items-center gap-2">
          <ClockIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
          Recent Attendance (Last 7 Days)
        </h2>
        <div class="space-y-3 max-h-64 overflow-y-auto">
          <div v-if="props.recentAttendance.length === 0" class="text-center py-6">
            <p class="text-gray-500 dark:text-zinc-400">No attendance records yet</p>
          </div>
          <div v-for="record in props.recentAttendance" :key="record.date"
            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
            <div>
              <p class="font-medium text-gray-900 dark:text-zinc-100">{{ format(new Date(record.date), 'MMM d, yyyy') }}</p>
              <p class="text-sm text-gray-600 dark:text-zinc-400">{{ record.clockIn }} - {{ record.clockOut || 'Not clocked out' }}</p>
            </div>
            <span :class="[ 'px-3 py-1 rounded-full text-xs font-semibold', record.status === 'present' ? 'bg-green-100 dark:bg-green-900/30 text-green-800' : record.status === 'late' ? 'bg-yellow-100 text-yellow-800' : record.status === 'absent' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800', ]">
              {{ record.status }}
            </span>
          </div>
        </div>
        <Link href="/trainee/attendance"
          class="block text-center mt-4 text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 font-medium text-sm">
          View All Records →
        </Link>
      </div>

      <!-- Current Payroll Status -->
      <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4 flex items-center gap-2">
          <CreditCardIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
          Current Payroll Status
        </h2>
        <div v-if="props.currentPayroll" class="space-y-4">
          <div class="p-4 bg-gray-50 dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
            <p class="text-sm text-gray-600 dark:text-zinc-400 mb-1">Gross Pay</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-zinc-100">
              ₱{{ props.currentPayroll.gross_pay.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
            </p>
          </div>
          <div class="p-4 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg border border-indigo-200 dark:border-indigo-800">
            <p class="text-sm text-indigo-600 dark:text-indigo-400 mb-1">Net Pay</p>
            <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">
              ₱{{ props.currentPayroll.net_pay.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
            </p>
          </div>
          <div class="p-3 rounded-lg" :class="statusBgClass(props.currentPayroll.status)">
            <p class="text-sm font-medium">Status: {{ props.currentPayroll.status }}</p>
          </div>
          <Link href="/trainee/payslip"
            class="block text-center bg-indigo-600 text-white py-2 rounded-lg font-medium hover:bg-indigo-700 transition">
            View Payslips
          </Link>
        </div>
        <div v-else class="text-center py-8">
          <ExclamationTriangleIcon class="w-8 h-8 text-gray-400 dark:text-zinc-500 mx-auto mb-2" />
          <p class="text-gray-500 dark:text-zinc-400">No payroll data available</p>
        </div>
      </div>
    </div>

    <!-- Upcoming Holidays -->
    <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4 flex items-center gap-2">
        <CalendarIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
        Upcoming Holidays
      </h2>
      <div v-if="props.upcomingHolidays.length === 0" class="text-center py-8">
        <p class="text-gray-500 dark:text-zinc-400">No holidays in the next 30 days</p>
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div v-for="holiday in props.upcomingHolidays" :key="holiday.id"
          class="p-4 bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-800">
          <p class="text-sm font-semibold text-blue-900 dark:text-blue-200">{{ holiday.name }}</p>
          <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">{{ format(new Date(holiday.date), 'MMM d, yyyy') }}</p>
          <span class="inline-block mt-2 px-2 py-1 bg-blue-200 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300 text-xs rounded font-medium">
            {{ holiday.type }}
          </span>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>