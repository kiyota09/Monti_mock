<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { format } from 'date-fns';
import { CreditCardIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
  user: any;
  payslips: Array<{
    id: number;
    period: string;
    grossPay: number;
    netPay: number;
    date: string;
    status: string;
    statusBadge: { label: string; color: string };
  }>;
  payslipDetails: {
    id: number;
    period: string;
    date: string;
    employeeName: string;
    employeeId: string;
    role: string;
    baseSalary: number;
    daysWorked: number;
    dailyRate: number;
    totalDaysAmt: number;
    nightHours: number;
    nightRate: number;
    nightAmt: number;
    overtimeHours: number;
    otRate: number;
    otAmt: number;
    sundayRestdayHours: number;
    sunSpRate: number;
    sunSpAmt: number;
    holidayAmt: number;
    lateMinutes: number;
    lateRateMin: number;
    lateTotalDeduction: number;
    grossPay: number;
    deductions: {
      sss: number;
      philhealth: number;
      pagibig: number;
      taxWithheld: number;
      sssLoan: number;
      pfLoan: number;
      totalDeductions: number;
    };
    netPay: number;
    status: string;
  } | null;
  currentMonth: number;
  currentYear: number;
}>();

const selectedPayslip = ref(props.payslipDetails);
const showDetails = ref(false);

const currentDate = computed(() => format(new Date(), 'EEEE, MMMM d, yyyy'));

const selectPayslip = async (payslipId: number) => {
  const payslip = props.payslips.find(p => p.id === payslipId);
  if (payslip) {
    try {
      const response = await fetch(`/trainee/payslip/${payslipId}`);
      const data = await response.json();
      selectedPayslip.value = data;
      showDetails.value = true;
    } catch (error) {
      console.error('Failed to load payslip details:', error);
    }
  }
};

const formatCurrency = (amount: number): string => {
  return amount.toLocaleString('en-US', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
  });
};

const downloadPayslipPDF = (payslipId: number) => {
  alert(`PDF download for payslip #${payslipId} would be implemented here.`);
};
</script>

<template>

  <Head title="Payslip" />
  <AuthenticatedLayout>
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900 dark:text-zinc-100">Payslips</h1>
      <p class="text-gray-500 dark:text-zinc-400 mt-2">{{ currentDate }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Payslip List -->
      <div class="lg:col-span-1">
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-6">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4 flex items-center gap-2">
            <CreditCardIcon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
            Payslip History
          </h2>

          <div v-if="props.payslips.length === 0" class="text-center py-8">
            <p class="text-gray-500 dark:text-zinc-400">No payslips available</p>
          </div>

          <div v-else class="space-y-3 max-h-96 overflow-y-auto">
            <button v-for="payslip in props.payslips" :key="payslip.id" @click="selectPayslip(payslip.id)" :class="[ 'w-full p-4 text-left rounded-lg border-2 transition-all', selectedPayslip?.id === payslip.id ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-500' : 'bg-gray-50 border-gray-200 dark:border-zinc-700 hover:border-gray-300', ]">
              <p class="font-semibold text-gray-900 dark:text-zinc-100">{{ payslip.period }}</p>
              <p class="text-sm text-gray-600 dark:text-zinc-400 mt-1">{{ format(new Date(payslip.date), 'MMM d, yyyy') }}</p>
              <div class="flex items-center justify-between mt-2">
                <span :class="[ 'px-2 py-1 rounded text-xs font-semibold', payslip.statusBadge.color === 'success' ? 'bg-green-100 dark:bg-green-900/30 text-green-800' : payslip.statusBadge.color === 'warning' ? 'bg-yellow-100 text-yellow-800' : payslip.statusBadge.color === 'danger' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800', ]">
                  {{ payslip.statusBadge.label }}
                </span>
                <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                  ₱{{ payslip.netPay.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </p>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Payslip Details -->
      <div class="lg:col-span-2">
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-gray-200 dark:border-zinc-700 p-8">
          <div v-if="selectedPayslip" class="space-y-6">
            <!-- Header -->
            <div class="border-b-2 border-gray-200 dark:border-zinc-700 pb-6">
              <div class="flex items-center justify-between mb-2">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-zinc-100">{{ selectedPayslip.period }}</h2>
                <button @click="downloadPayslipPDF(selectedPayslip.id)"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition">
                  <ArrowDownTrayIcon class="w-4 h-4" />
                  Download PDF
                </button>
              </div>
              <p class="text-sm text-gray-600 dark:text-zinc-400">For the month of {{ selectedPayslip.period }}</p>
              <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p class="text-gray-600 dark:text-zinc-400">Employee Name</p>
                  <p class="font-semibold text-gray-900 dark:text-zinc-100">{{ selectedPayslip.employeeName }}</p>
                </div>
                <div>
                  <p class="text-gray-600 dark:text-zinc-400">Employee ID</p>
                  <p class="font-semibold text-gray-900 dark:text-zinc-100">{{ selectedPayslip.employeeId }}</p>
                </div>
              </div>
            </div>

            <!-- Earnings Section -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Earnings</h3>
              <div class="space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">Base Salary</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.baseSalary) }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">Days Worked</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ selectedPayslip.daysWorked }} days</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">Daily Rate Amount</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.totalDaysAmt) }}</span>
                </div>
                <div v-if="selectedPayslip.nightAmt > 0" class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">Night Differential ({{ selectedPayslip.nightHours }}h)</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.nightAmt) }}</span>
                </div>
                <div v-if="selectedPayslip.otAmt > 0" class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">Overtime ({{ selectedPayslip.overtimeHours }}h)</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.otAmt) }}</span>
                </div>
                <div v-if="selectedPayslip.sunSpAmt > 0" class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">Sunday/Special Holiday</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.sunSpAmt) }}</span>
                </div>
                <div v-if="selectedPayslip.holidayAmt > 0" class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">Holiday</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.holidayAmt) }}</span>
                </div>
                <div v-if="selectedPayslip.lateTotalDeduction > 0"
                  class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700 text-red-600 dark:text-red-400">
                  <span>Late Deduction ({{ selectedPayslip.lateMinutes }}min)</span>
                  <span class="font-medium">{{ formatCurrency(selectedPayslip.lateTotalDeduction) }}</span>
                </div>
                <div class="flex justify-between py-3 bg-blue-50 dark:bg-blue-900/30 px-3 rounded-lg">
                  <span class="font-semibold text-gray-900 dark:text-zinc-100">Gross Pay</span>
                  <span class="font-bold text-lg text-indigo-600 dark:text-indigo-400">{{ formatCurrency(selectedPayslip.grossPay) }}</span>
                </div>
              </div>
            </div>

            <!-- Deductions Section -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-4">Deductions</h3>
              <div class="space-y-3">
                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">SSS Contribution</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.deductions.sss) }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">PhilHealth Premium</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.deductions.philhealth)
                  }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">PAG-IBIG Contribution</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.deductions.pagibig)
                  }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">Tax Withheld</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.deductions.taxWithheld)
                  }}</span>
                </div>
                <div v-if="selectedPayslip.deductions.sssLoan > 0"
                  class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">SSS Loan</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.deductions.sssLoan)
                  }}</span>
                </div>
                <div v-if="selectedPayslip.deductions.pfLoan > 0"
                  class="flex justify-between py-2 border-b border-gray-200 dark:border-zinc-700">
                  <span class="text-gray-600 dark:text-zinc-400">PAG-IBIG Loan</span>
                  <span class="font-medium text-gray-900 dark:text-zinc-100">{{ formatCurrency(selectedPayslip.deductions.pfLoan) }}</span>
                </div>
                <div class="flex justify-between py-3 bg-red-50 dark:bg-red-900/20 px-3 rounded-lg">
                  <span class="font-semibold text-gray-900 dark:text-zinc-100">Total Deductions</span>
                  <span class="font-bold text-lg text-red-600 dark:text-red-400">{{
                    formatCurrency(selectedPayslip.deductions.totalDeductions) }}</span>
                </div>
              </div>
            </div>

            <!-- Net Pay Summary -->
            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 p-6 rounded-lg border-2 border-indigo-200 dark:border-indigo-800">
              <p class="text-sm text-indigo-600 dark:text-indigo-400 font-medium mb-2">Net Amount to Receive</p>
              <p class="text-3xl font-bold text-indigo-900">{{ formatCurrency(selectedPayslip.netPay) }}</p>
            </div>

            <!-- Status Badge -->
            <div :class="[ 'p-4 rounded-lg font-semibold text-center', selectedPayslip.status === 'paid' ? 'bg-green-100 dark:bg-green-900/30 text-green-800' : selectedPayslip.status === 'approved' ? 'bg-blue-100 text-blue-800' : selectedPayslip.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800', ]">
              Status: {{ selectedPayslip.status }}
            </div>
          </div>

          <div v-else class="text-center py-16">
            <CreditCardIcon class="w-16 h-16 text-gray-400 dark:text-zinc-500 mx-auto mb-4" />
            <p class="text-gray-500 dark:text-zinc-400 text-lg">Select a payslip from the list to view details</p>
          </div>
        </div>
      </div>
    </div>

    <!-- <div class="mt-8 flex justify-center">
      <Link href="/trainee/dashboard"
        class="px-6 py-3 bg-gray-200 dark:bg-zinc-700 text-gray-900 dark:text-zinc-100 rounded-lg font-medium hover:bg-gray-300 transition">
        Back to Dashboard
      </Link>
    </div> -->
  </AuthenticatedLayout>
</template>