<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Props defined to satisfy the layout if necessary, 
// though not used for the simple display.

</script>

<template>
    <AuthenticatedLayout>
        <div data-erp-page-banner="fallback" class="flex items-center justify-center min-h-[140px]">
            <h1 class="text-4xl font-black text-gray-800 dark:text-white uppercase tracking-widest">
                Project Management Manager Department Dashboard
            </h1>
        </div>
    </AuthenticatedLayout>
</template>
