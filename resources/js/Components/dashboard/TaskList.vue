<script setup>
import { Checkbox } from '@/Components/ui/checkbox';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';

defineProps({
    tasks: {
        type: Array,
        default: () => []
    },
    loading: Boolean
});

const emits = defineEmits(['task-complete']);

const completeTask = (taskId) => {
    emits('task-complete', taskId);
};

const priorityColors = {
    high: 'bg-red-100 text-red-800',
    medium: 'bg-orange-100 text-orange-800',
    low: 'bg-blue-100 text-blue-800'
};
</script>

<template>
    <div class="space-y-3">
        <div v-if="loading">
            <div v-for="i in 3" :key="i" class="flex items-center space-x-3 p-2">
                <div class="h-5 w-5 bg-gray-200 rounded animate-pulse"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-4 w-3/4 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-3 w-1/2 bg-gray-200 rounded animate-pulse"></div>
                </div>
            </div>
        </div>
        <template v-else>
            <div v-for="task in tasks" :key="task.id" class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded dark:hover:bg-zinc-800">
                <Checkbox :checked="task.completed" @change="completeTask(task.id)" />
                <div class="flex-1">
                    <p :class="cn('text-sm text-gray-900 dark:text-zinc-100', task.completed && 'line-through text-gray-500 dark:text-zinc-500)">
                        {{ task.title }}
                    </p>
                    <div class="flex items-center space-x-2 mt-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ task.due_date }}</span>
                        <Badge :class="priorityColors[task.priority]" class="text-xs">
                            {{ task.priority }}
                        </Badge>
                        <span v-if="task.category" class="text-xs text-gray-500 dark:text-gray-400">{{ task.category }}</span>
                    </div>
                </div>
            </div>
            <div v-if="tasks.length === 0" class="text-center py-4 text-gray-500 dark:text-gray-400">
                No tasks assigned
            </div>
        </template>
    </div>
</template>