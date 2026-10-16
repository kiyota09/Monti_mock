<template>
    <AuthenticatedLayout>
        <Head title="Notifications" />
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    My <span class="text-blue-600">Notifications</span>
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Stay updated with your application status and important updates.</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    v-if="unreadCount > 0"
                    @click="confirmMarkAllAsRead"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 active:scale-95"
                >
                    Mark All as Read
                </button>
                <button
                    v-if="notifications && notifications.length > 0"
                    @click="confirmDeleteAll"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 text-red-600 dark:text-red-400 text-sm font-bold rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-red-50 dark:hover:bg-red-900/20 transition active:scale-95"
                >
                    Delete All
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
            <!-- Tabs -->
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id; fetchNotifications(tab.id)"
                    class="px-4 py-2 rounded-xl text-sm font-bold transition active:scale-95"
                    :class="activeTab === tab.id
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'"
                >
                    {{ tab.label }}
                    <span v-if="tab.id === 'unread' && unreadCount > 0"
                          class="ml-2 px-2 py-0.5 bg-red-500 text-white rounded-full text-xs">
                        {{ unreadCount }}
                    </span>
                </button>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="ml-3 text-sm text-slate-500 dark:text-slate-400">Loading notifications...</span>
            </div>

            <!-- Notifications List -->
            <div v-else-if="notifications && notifications.length > 0" class="p-4 space-y-3">
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    @click="openNotificationModal(notification)"
                    class="rounded-xl border border-slate-200 dark:border-slate-700 border-l-4 p-4 hover:shadow-md transition cursor-pointer bg-white dark:bg-slate-800"
                    :class="{
                        'border-l-blue-600 bg-blue-50/40 dark:bg-blue-900/10': !notification.read_at,
                        'border-l-red-500 bg-red-50 dark:bg-red-900/10': notification.read_at && (notification.type === 'rejection' || notification.data?.type === 'rejection'),
                        'border-l-purple-500 bg-purple-50 dark:bg-purple-900/10': notification.read_at && notification.data?.type === 'interview',
                        'border-l-emerald-500 bg-emerald-50 dark:bg-emerald-900/10': notification.read_at && notification.data?.type === 'onboarding'
                    }"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                    :class="getNotificationIconClass(notification.type || notification.data?.type)"
                                >
                                    <component :is="getNotificationIcon(notification.type || notification.data?.type)" class="w-5 h-5" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                            {{ notification.data?.title || notification.title || 'Notification' }}
                                        </h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">
                                            {{ notification.data?.message || notification.message || 'No message provided' }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-[10px] text-slate-400">
                                                {{ formatDate(notification.created_at) }}
                                            </span>
                                            <span v-if="!notification.read_at"
                                                  class="px-2 py-0.5 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 rounded-full text-[10px] font-bold">
                                                Unread
                                            </span>
                                            <span class="text-[10px] text-blue-600 dark:text-blue-400 flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                                Click to view details
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button
                                v-if="!notification.read_at"
                                @click.stop="confirmMarkAsRead(notification.id)"
                                class="p-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors"
                                title="Mark as read"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                            <button
                                @click.stop="confirmDeleteNotification(notification.id)"
                                class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                title="Delete"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
                    <button
                        v-for="page in pagination.last_page"
                        :key="page"
                        @click="goToPage(page)"
                        class="px-3 py-1 rounded-lg text-sm font-bold transition"
                        :class="pagination.current_page === page
                            ? 'bg-blue-600 text-white'
                            : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'"
                    >
                        {{ page }}
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="p-12 text-center">
                <div class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">No Notifications</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ activeTab === 'unread' ? 'You have no unread notifications' : 'You have no notifications' }}
                </p>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ✅ NOTIFICATION DETAIL MODAL                   -->
        <!-- ============================================ -->
        <div
            v-if="showNotificationModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeNotificationModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-3xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col animate-modalIn">
                <!-- Modal Header -->
                <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                            :class="getNotificationIconClass(selectedNotification?.type || selectedNotification?.data?.type)"
                        >
                            <component :is="getNotificationIcon(selectedNotification?.type || selectedNotification?.data?.type)" class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Notification Details</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ formatDate(selectedNotification?.created_at) }}</p>
                        </div>
                    </div>
                    <button @click="closeNotificationModal" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl text-slate-400 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body - Scrollable -->
                <div class="flex-1 overflow-y-auto p-6 space-y-4">
                    <div v-if="selectedNotification">
                        <!-- REJECTION NOTIFICATION DETAILS - SIMPLIFIED -->
                        <div v-if="selectedNotification.type === 'rejection' || selectedNotification.data?.type === 'rejection'">
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-6 text-center">
                                <div class="mb-4 flex justify-center"><XCircle class="w-16 h-16 text-red-500" /></div>
                                <h4 class="text-xl font-bold text-red-700 dark:text-red-400">Application Rejected</h4>
                                <p class="text-sm text-slate-600 dark:text-slate-300 mt-2">
                                    {{ selectedNotification.data?.message || 'Your application has been rejected.' }}
                                </p>
                                <div class="mt-4 pt-4 border-t border-red-200 dark:border-red-800">
                                    <p class="text-sm text-amber-700 dark:text-amber-400 flex items-center justify-center gap-2">
                                        <Lightbulb class="w-4 h-4 flex-shrink-0 text-amber-500" /> We appreciate your interest in joining our team. We encourage you to apply for other positions that match your skills and experience.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- INTERVIEW NOTIFICATION DETAILS -->
                        <div v-else-if="selectedNotification.data?.type === 'interview'">
                            <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-xl p-4 mb-4">
                                <h4 class="text-lg font-bold text-purple-700 dark:text-purple-400 flex items-center gap-2">
                                    <Calendar class="w-5 h-5" /> Interview Scheduled
                                </h4>
                                <p class="text-sm text-slate-600 dark:text-slate-300 mt-2">
                                    {{ selectedNotification.data?.message || 'Your interview has been scheduled.' }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                    <span class="text-[10px] text-slate-400 block">Position</span>
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ selectedNotification.data?.position || 'N/A' }}</span>
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                    <span class="text-[10px] text-slate-400 block">Interview Type</span>
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ selectedNotification.data?.interview_type || 'N/A' }}</span>
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                    <span class="text-[10px] text-slate-400 block">Date</span>
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ selectedNotification.data?.date ? formatDate(selectedNotification.data.date) : 'N/A' }}</span>
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                    <span class="text-[10px] text-slate-400 block">Time</span>
                                    <span class="font-semibold text-slate-900 dark:text-white">
                                        {{ selectedNotification.data?.start_time ? formatTime(selectedNotification.data.start_time) : 'N/A' }}
                                        -
                                        {{ selectedNotification.data?.end_time ? formatTime(selectedNotification.data.end_time) : 'N/A' }}
                                    </span>
                                </div>
                                <div v-if="showNotificationLocation(selectedNotification.data)" class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3 col-span-2">
                                    <span class="text-[10px] text-slate-400 block">Location</span>
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ selectedNotification.data?.location || 'N/A' }}</span>
                                </div>
                                <div v-if="showNotificationLink(selectedNotification.data)" class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3 col-span-2">
                                    <span class="text-[10px] text-slate-400 block">Meeting Link</span>
                                    <a :href="selectedNotification.data.meeting_link" target="_blank" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline">{{ selectedNotification.data.meeting_link }}</a>
                                </div>
                                <div v-if="selectedNotification.data?.notes" class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3 col-span-2">
                                    <span class="text-[10px] text-slate-400 block">Notes</span>
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ selectedNotification.data.notes }}</span>
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3 col-span-2">
                                    <span class="text-[10px] text-slate-400 block">Status</span>
                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ selectedNotification.data?.status || 'Scheduled' }}</span>
                                </div>
                            </div>
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 mt-4">
                                <p class="text-sm text-blue-700 dark:text-blue-400 flex items-center gap-2">
                                    <ClipboardList class="w-4 h-4 flex-shrink-0 text-blue-600 dark:text-blue-400" /> Please come prepared and on time. Bring any required documents or materials.
                                </p>
                            </div>
                        </div>

                        <!-- ONBOARDING NOTIFICATION DETAILS -->
                        <div v-else-if="selectedNotification.data?.type === 'onboarding'">
                            <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-6 text-center">
                                <div class="mb-4 flex justify-center"><PartyPopper class="w-16 h-16 text-emerald-600 dark:text-emerald-400" /></div>
                                <h4 class="text-xl font-bold text-emerald-700 dark:text-emerald-400">Application Approved for Onboarding</h4>
                                <p class="text-sm text-slate-600 dark:text-slate-300 mt-2">
                                    {{ selectedNotification.data?.message || 'Your application has been approved to move on to the onboarding / training stage.' }}
                                </p>
                                <div class="mt-4 pt-4 border-t border-emerald-200 dark:border-emerald-800">
                                    <p class="text-sm text-emerald-700 dark:text-emerald-400 flex items-center justify-center gap-2">
                                        <ClipboardList class="w-4 h-4 flex-shrink-0 text-emerald-600 dark:text-emerald-400" /> Congratulations! Our HR team will reach out with the onboarding details and next steps.
                                    </p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm mt-4">
                                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                    <span class="text-[10px] text-slate-400 block">Position</span>
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ selectedNotification.data?.position || 'N/A' }}</span>
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                    <span class="text-[10px] text-slate-400 block">Application Status</span>
                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ selectedNotification.data?.status || 'Onboarding' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- REGULAR NOTIFICATION DETAILS -->
                        <div v-else>
                            <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-4">
                                <h4 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <component :is="getNotificationIcon(selectedNotification.type || selectedNotification.data?.type)" class="w-5 h-5" />
                                    {{ selectedNotification.data?.title || selectedNotification.title || 'Notification' }}
                                </h4>
                                <p class="text-sm text-slate-600 dark:text-slate-300 mt-2">
                                    {{ selectedNotification.data?.message || selectedNotification.message || 'No message provided' }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                    <span class="text-[10px] text-slate-400 block">Date</span>
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ formatDate(selectedNotification.created_at) }}</span>
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                    <span class="text-[10px] text-slate-400 block">Status</span>
                                    <span v-if="!selectedNotification.read_at" class="font-semibold text-blue-600 dark:text-blue-400">Unread</span>
                                    <span v-else class="font-semibold text-emerald-600 dark:text-emerald-400">Read</span>
                                </div>
                            </div>
                        </div>

                        <!-- Mark as Read Button (if unread) -->
                        <div v-if="!selectedNotification.read_at" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                            <button
                                @click="confirmMarkAsRead(selectedNotification.id)"
                                class="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition active:scale-95"
                            >
                                Mark as Read
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-end gap-3 shrink-0">
                    <button
                        @click="closeNotificationModal"
                        class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-bold transition active:scale-95"
                    >
                        Close
                    </button>
                    <button
                        v-if="selectedNotification && !selectedNotification.read_at"
                        @click="confirmMarkAsRead(selectedNotification.id)"
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 active:scale-95"
                    >
                        Mark as Read
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ✅ CONFIRMATION MODAL                         -->
        <!-- ============================================ -->
        <div
            v-if="showConfirmationModal"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            @click.self="closeConfirmationModal"
        >
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 w-full max-w-md max-h-[90vh] overflow-hidden shadow-2xl animate-modalIn flex flex-col">
                <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                            :class="confirmationData.iconClass || 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'"
                        >
                            <component :is="confirmationData.icon || AlertTriangle" class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ confirmationData.title }}</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ confirmationData.message }}</p>
                        </div>
                    </div>
                    <button @click="closeConfirmationModal" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl text-slate-400 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-6 space-y-4">
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ confirmationData.detail }}</p>
                </div>
                <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-end gap-3 shrink-0">
                    <button @click="closeConfirmationModal" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-bold transition active:scale-95">Cancel</button>
                    <button @click="confirmAction" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-blue-500/30 active:scale-95">Confirm</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { FileText, Calendar, PartyPopper, RefreshCw, MessageSquare, XCircle, Bell, Lightbulb, ClipboardList, AlertTriangle, Trash2, BookOpen } from 'lucide-vue-next';

// Props from controller
const props = defineProps({
    notifications: {
        type: Array,
        default: () => []
    },
    pagination: {
        type: Object,
        default: () => ({})
    },
    unreadCount: {
        type: Number,
        default: 0
    }
});

// State
const loading = ref(false);
const activeTab = ref('all');
const notifications = ref(props.notifications || []);
const pagination = ref(props.pagination || {});
const unreadCount = ref(props.unreadCount || 0);
const showNotificationModal = ref(false);
const showConfirmationModal = ref(false);
const selectedNotification = ref(null);
const pendingAction = ref(null);
const pendingData = ref(null);
const confirmationData = ref({
    title: '',
    message: '',
    detail: '',
    icon: AlertTriangle,
    iconClass: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'
});

const tabs = [
    { id: 'all', label: 'All' },
    { id: 'unread', label: 'Unread' },
    { id: 'read', label: 'Read' },
];

// Fetch notifications
const fetchNotifications = async (tab) => {
    loading.value = true;
    try {
        const response = await fetch(`/applicant/notifications/${tab}`);
        const data = await response.json();
        notifications.value = data.data || [];
        pagination.value = data.pagination || {};
        if (tab === 'unread') {
            unreadCount.value = notifications.value.length;
        }
    } catch (error) {
        console.error('Failed to fetch notifications:', error);
    } finally {
        loading.value = false;
    }
};

// Go to page
const goToPage = async (page) => {
    loading.value = true;
    try {
        const response = await fetch(`/applicant/notifications/${activeTab.value}?page=${page}`);
        const data = await response.json();
        notifications.value = data.data || [];
        pagination.value = data.pagination || {};
    } catch (error) {
        console.error('Failed to fetch page:', error);
    } finally {
        loading.value = false;
    }
};

// Open notification modal
const openNotificationModal = (notification) => {
    selectedNotification.value = notification;
    showNotificationModal.value = true;
    document.body.style.overflow = 'hidden';

    // Auto mark as read when opened
    if (!notification.read_at) {
        markAsRead(notification.id);
    }
};

// Close notification modal
const closeNotificationModal = () => {
    showNotificationModal.value = false;
    selectedNotification.value = null;
    document.body.style.overflow = '';
};

// Mark as read
const markAsRead = async (id) => {
    try {
        const response = await fetch(`/applicant/notifications/${id}/mark-read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        if (response.ok) {
            const notification = notifications.value.find(n => n.id === id);
            if (notification) {
                notification.read_at = new Date().toISOString();
            }
            if (selectedNotification.value && selectedNotification.value.id === id) {
                selectedNotification.value.read_at = new Date().toISOString();
            }
            unreadCount.value = Math.max(0, unreadCount.value - 1);
            if (activeTab.value === 'unread') {
                await fetchNotifications(activeTab.value);
            }
        }
    } catch (error) {
        console.error('Failed to mark as read:', error);
    }
};

// Mark all as read
const markAllAsRead = async () => {
    try {
        const response = await fetch('/applicant/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        if (response.ok) {
            notifications.value.forEach(n => n.read_at = new Date().toISOString());
            unreadCount.value = 0;
            if (activeTab.value === 'unread') {
                await fetchNotifications(activeTab.value);
            }
            closeConfirmationModal();
        }
    } catch (error) {
        console.error('Failed to mark all as read:', error);
    }
};

// Delete notification
const deleteNotification = async (id) => {
    try {
        const response = await fetch(`/applicant/notifications/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        if (response.ok) {
            notifications.value = notifications.value.filter(n => n.id !== id);
            if (selectedNotification.value && selectedNotification.value.id === id) {
                closeNotificationModal();
            }
            if (activeTab.value === 'unread' || activeTab.value === 'read') {
                await fetchNotifications(activeTab.value);
            }
            closeConfirmationModal();
        }
    } catch (error) {
        console.error('Failed to delete notification:', error);
    }
};

// Delete all notifications
const deleteAll = async () => {
    try {
        const response = await fetch('/applicant/notifications/delete-all', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        if (response.ok) {
            notifications.value = [];
            unreadCount.value = 0;
            closeNotificationModal();
            await fetchNotifications(activeTab.value);
            closeConfirmationModal();
        }
    } catch (error) {
        console.error('Failed to delete all notifications:', error);
    }
};

// Confirmation Dialog Functions
const showConfirmDialog = (title, message, detail, icon, iconClass, action, data) => {
    confirmationData.value = {
        title: title,
        message: message,
        detail: detail,
        icon: icon || AlertTriangle,
        iconClass: iconClass || 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'
    };
    pendingAction.value = action;
    pendingData.value = data;
    showConfirmationModal.value = true;
    document.body.style.overflow = 'hidden';
};

const closeConfirmationModal = () => {
    showConfirmationModal.value = false;
    pendingAction.value = null;
    pendingData.value = null;
    document.body.style.overflow = '';
};

const confirmAction = () => {
    if (pendingAction.value) {
        pendingAction.value(pendingData.value);
    }
};

// Confirmation Wrappers
const confirmMarkAsRead = (id) => {
    showConfirmDialog(
        'Mark as Read',
        'Are you sure?',
        'This notification will be marked as read.',
        BookOpen,
        'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        () => markAsRead(id),
        id
    );
};

const confirmMarkAllAsRead = () => {
    showConfirmDialog(
        'Mark All as Read',
        'Are you sure?',
        'All notifications will be marked as read. This action cannot be undone.',
        BookOpen,
        'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        markAllAsRead,
        null
    );
};

const confirmDeleteNotification = (id) => {
    showConfirmDialog(
        'Delete Notification',
        'Are you sure?',
        'This notification will be permanently deleted. This action cannot be undone.',
        Trash2,
        'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        () => deleteNotification(id),
        id
    );
};

const confirmDeleteAll = () => {
    showConfirmDialog(
        'Delete All Notifications',
        'Are you sure?',
        'All notifications will be permanently deleted. This action cannot be undone.',
        Trash2,
        'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        deleteAll,
        null
    );
};

// Format date
const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// Format time
const formatTime = (time) => {
    if (!time) return 'N/A';
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${minutes} ${ampm}`;
};

// Online meetings show the link and hide the location; onsite interviews
// show the location and hide the link. Notifications sent before the method
// was included keep the previous behavior (show whatever is present).
const isOnlineMethod = (data) => String(data?.method || '').toLowerCase() === 'online';
const isOnsiteMethod = (data) => String(data?.method || '').toLowerCase() === 'onsite';
const locationMeaningful = (value) => {
    const s = String(value ?? '').trim();
    return !!s && s.toLowerCase() !== 'n/a' && s !== '-';
};
const showNotificationLocation = (data) => {
    if (isOnlineMethod(data)) return false;
    // No stored method (older notifications): a present link with no real
    // location still means online — hide the empty "N/A" location row.
    if (data?.meeting_link) return locationMeaningful(data?.location);
    return true;
};
const showNotificationLink = (data) => !!data?.meeting_link && !isOnsiteMethod(data);

// Get notification icon based on type
const getNotificationIcon = (type) => {
    const icons = {
        'application': FileText,
        'interview': Calendar,
        'interview_scheduled': Calendar,
        'offer': PartyPopper,
        'status': RefreshCw,
        'message': MessageSquare,
        'rejection': XCircle,
        'onboarding': PartyPopper,
        'default': Bell
    };
    return icons[type] || icons.default;
};

// Get icon class
const getNotificationIconClass = (type) => {
    const classes = {
        'application': 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        'interview': 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
        'interview_scheduled': 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
        'offer': 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
        'status': 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
        'message': 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400',
        'rejection': 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        'onboarding': 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
        'default': 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300'
    };
    return classes[type] || classes.default;
};
</script>

<style scoped>
/* Modal animation */
.animate-modalIn {
    animation: modalIn 0.25s ease-out;
}
@keyframes modalIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(15px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Line clamp for preview */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Custom scrollbar for modal */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
