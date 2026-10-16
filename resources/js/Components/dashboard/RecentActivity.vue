<script setup>
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';
import { CheckCircle, Clock, AlertCircle, UserPlus, FileText } from 'lucide-vue-next';

defineProps({
    activities: {
        type: Array,
        default: () => []
    },
    loading: Boolean
});

const activityIcons = {
    completed: CheckCircle,
    pending: Clock,
    alert: AlertCircle,
    new_user: UserPlus,
    document: FileText
};

const activityColors = {
    completed: 'bg-green-100 text-green-800',
    pending: 'bg-orange-100 text-orange-800',
    alert: 'bg-red-100 text-red-800',
    new_user: 'bg-blue-100 text-blue-800',
    document: 'bg-purple-100 text-purple-800'
};
</script>

<template>
    <div class="space-y-4">
        <div v-if="loading">
            <div v-for="i in 3" :key="i" class="flex items-start space-x-3">
                <div class="h-8 w-8 bg-gray-200 rounded-full animate-pulse"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-4 w-3/4 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-3 w-1/2 bg-gray-200 rounded animate-pulse"></div>
                </div>
            </div>
        </div>
        <template v-else>
            <div v-for="activity in activities" :key="activity.id" class="flex items-start space-x-3">
                <div :class="cn('p-2 rounded-full', activityColors[activity.type])">
                    <component :is="activityIcons[activity.type]" class="h-4 w-4" />
                </div>
                <div class="flex-1">
                    <p class="text-sm">{{ activity.message }}</p>
                    <div class="flex items-center space-x-2 mt-1">
                        <span class="text-xs text-gray-500">{{ activity.time }}</span>
                        <Badge v-if="activity.category" variant="outline" class="text-xs">
                            {{ activity.category }}
                        </Badge>
                    </div>
                </div>
            </div>
            <div v-if="activities.length === 0" class="text-center py-4 text-gray-500">
                No recent activity
            </div>
        </template>
    </div>
</template>