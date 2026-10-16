<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Users,
    UserCheck,
    Clock,
    Wallet,
    TrendingUp,
    FileText,
    Calendar,
    Download,
    Lightbulb,
    Bot,
    MoreHorizontal,
    Search
} from 'lucide-vue-next';

// Shadcn UI Imports
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

// Receive real data from AnalyticsController
const props = defineProps({
    stats: Object,
    deptBreakdown: Array,
    chartData: Array,
    permissions: {
        type: Object,
        default: () => ({})
    }
});

// Map real stats to the card UI
const statCards = computed(() => [
    { label: 'Active Headcount', value: props.stats.headcount, trend: 'Current', up: true, icon: Users, color: 'blue' },
    { label: 'Turnover Rate', value: props.stats.turnoverRate, trend: 'Overall', up: false, icon: UserCheck, color: 'emerald' },
    { label: 'Total Applicants', value: props.stats.totalApplicants, trend: 'All Time', up: true, icon: Clock, color: 'amber' },
    { label: 'Successful Hires', value: props.stats.hiringSuccess, trend: 'Completed', up: true, icon: Wallet, color: 'indigo' },
]);

const reportForm = useForm({
    name: '',
    type: 'analytical',
    range: 'Last 30 Days',
});

const animateBars = ref(false);
onMounted(() => {
    setTimeout(() => { animateBars.value = true; }, 300);
});

// Check if user has edit permission (not used for analytics, but kept for consistency)
const canEdit = computed(() => props.permissions?.analytics === 'edit');
</script>

<template>

    <Head title="HR Analytics & Reports" />

    <AuthenticatedLayout>
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">HR Analytics & Reports</h1>
            <p class="text-gray-500 dark:text-gray-400">Real-time workforce intelligence and data-driven insights.</p>
            <!-- Optional: show a badge if user has edit access (though no edits on this page) -->
            <div v-if="canEdit" class="mt-2 text-xs text-emerald-600 bg-emerald-50 inline-block px-2 py-1 rounded-full">
                Full access (edit permissions granted)
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div v-for="stat in statCards" :key="stat.label"
                class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div :class="`p-2 bg-${stat.color}-50 dark:bg-${stat.color}-900/20 rounded-lg`">
                        <component :is="stat.icon"
                            :class="`h-6 w-6 text-${stat.color}-600 dark:text-${stat.color}-400`" />
                    </div>
                    <span
                        :class="[stat.up ? 'text-emerald-600 bg-emerald-50' : 'text-blue-600 bg-blue-50', 'text-xs font-bold px-2 py-1 rounded-full']">
                        {{ stat.trend }}
                    </span>
                </div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ stat.label }}</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stat.value }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <div class="lg:col-span-2 space-y-8">
                <div
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div
                        class="p-6 border-b border-gray-50 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Monthly Registration Trend</h2>
                            <p class="text-sm text-gray-500">New users added per month</p>
                        </div>
                        <Button variant="outline" size="icon" class="rounded-xl">
                            <Download class="h-4 w-4" />
                        </Button>
                    </div>
                    <div class="p-6">
                        <div
                            class="h-[250px] flex items-end justify-between px-4 relative border-b border-gray-100 dark:border-gray-700">
                            <div v-for="bar in chartData" :key="bar.m"
                                class="relative group flex flex-col items-center w-full mx-1">
                                <div class="w-full bg-blue-600 dark:bg-blue-500 rounded-t-md transition-all duration-1000 ease-out opacity-80 group-hover:opacity-100"
                                    :style="{ height: animateBars ? bar.h : '0%' }">
                                    <div
                                        class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity">
                                        {{ bar.m }}
                                    </div>
                                </div>
                                <span class="mt-4 text-[10px] text-gray-400 font-bold uppercase tracking-tighter">{{
                                    bar.m }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-50 dark:border-gray-700 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Department Headcount & Turnover</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 dark:bg-gray-900/50">
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest">
                                        Department</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest">
                                        Total Staff</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest">
                                        Turnover %</th>
                                    <th
                                        class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest text-right">
                                        Health Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                <tr v-for="dept in deptBreakdown" :key="dept.name"
                                    class="group hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div
                                            class="flex items-center font-bold text-sm text-gray-900 dark:text-white uppercase">
                                            {{ dept.name }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-600 dark:text-gray-400">{{
                                        dept.headcount }}</td>
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-white">{{
                                        dept.turnover }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <span :class="[
                                            dept.status === 'Optimal' ? 'bg-emerald-100 text-emerald-700' :
                                                dept.status === 'Stable' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700',
                                            'px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider'
                                        ]">
                                            {{ dept.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-8">
                <div
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Live
                            Predictions</h3>
                        <Badge variant="outline"
                            class="text-blue-600 border-blue-200 animate-pulse text-[10px] font-bold">LIVE</Badge>
                    </div>
                    <div class="space-y-4">
                        <div
                            class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700 flex gap-4">
                            <Lightbulb class="h-5 w-5 text-amber-500 shrink-0" />
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white">Retention Insight</h4>
                                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                    Current retention is at {{ 100 - parseFloat(props.stats.turnoverRate) }}%.
                                </p>
                            </div>
                        </div>
                        <div
                            class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700 flex gap-4">
                            <Bot class="h-5 w-5 text-blue-500 shrink-0" />
                            <div>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white">Recruitment Status</h4>
                                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                    {{ props.stats.hiringSuccess }} out of {{ props.stats.totalApplicants }} applicants
                                    have successfully joined the team.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Additional footer note about permissions (optional) -->
                <div class="text-xs text-gray-400 text-center">
                    Page access: {{ permissions.analytics === 'edit' ? 'Edit mode' : 'View only' }}
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>