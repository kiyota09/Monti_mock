<script setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { cn } from '@/lib/utils';
import { computed } from 'vue';

const props = defineProps({
    title: String,
    value: [String, Number],
    description: String,
    icon: Object,
    trend: {
        type: String,
        default: 'neutral',
        validator: (value) => ['up', 'down', 'neutral'].includes(value)
    },
    trendValue: String,
    color: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'blue', 'green', 'red', 'orange', 'purple'].includes(value)
    },
    loading: Boolean
});

const colorClasses = {
    primary: 'bg-blue-100 text-blue-600',
    blue: 'bg-blue-100 text-blue-600',
    green: 'bg-green-100 text-green-600',
    red: 'bg-red-100 text-red-600',
    orange: 'bg-orange-100 text-orange-600',
    purple: 'bg-purple-100 text-purple-600'
};

const trendIcons = {
    up: 'M5 10l7-7m0 0l7 7m-7-7v18',
    down: 'M19 14l-7 7m0 0l-7-7m7 7V3',
    neutral: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'
};
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium text-gray-600">
                {{ title }}
            </CardTitle>
            <div :class="cn('p-2 rounded-full', colorClasses[color])">
                <component :is="icon" class="h-4 w-4" />
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="space-y-2">
                <div class="h-8 w-24 bg-gray-200 rounded animate-pulse"></div>
                <div class="h-4 w-32 bg-gray-200 rounded animate-pulse"></div>
            </div>
            <template v-else>
                <div class="text-2xl font-bold">{{ value }}</div>
                <div class="flex items-center text-xs mt-1">
                    <span :class="cn('flex items-center', trend === 'up' ? 'text-green-600' : trend === 'down' ? 'text-red-600' : 'text-gray-600' )">
                        <svg v-if="trend !== 'neutral'" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                :d="trendIcons[trend]" />
                        </svg>
                        {{ trendValue }}
                    </span>
                    <span v-if="description" class="text-gray-500 ml-2">{{ description }}</span>
                </div>
            </template>
        </CardContent>
    </Card>
</template>