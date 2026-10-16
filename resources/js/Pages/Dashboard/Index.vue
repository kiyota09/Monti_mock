<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    stats: Object,
    user: Object
})

// Define dashboards in an array to keep the template clean
const departments = [
    { name: 'HRM Department', desc: 'Manage employees and recruitment', route: 'hrm.dashboard', color: 'from-blue-500 to-blue-600', role: 'HRM' },
    { name: 'SCM Department', desc: 'Manage inventory and logistics', route: 'scm.employee.dashboard', color: 'from-green-500 to-green-600', role: 'SCM' },
    { name: 'Finance', desc: 'Handle accounting and payroll', route: 'fin.employee.dashboard', color: 'from-teal-500 to-teal-600', role: 'FIN' },
    { name: 'Manufacturing', desc: 'Monitor production lines', route: 'man.employee.dashboard', color: 'from-orange-500 to-orange-600', role: 'MAN' },
    { name: 'Inventory', desc: 'Manage stock levels', route: 'inv.employee.dashboard', color: 'from-purple-500 to-purple-600', role: 'INV' },
    { name: 'Order Management', desc: 'Manage Orders', route: 'ord.employee.dashboard', color: 'from-pink-500 to-pink-600', role: 'ORD' },
    { name: 'Warehouse', desc: 'Manage Warehouse operations', route: 'war.employee.dashboard', color: 'from-indigo-500 to-indigo-600', role: 'WAR' },
    { name: 'CRM', desc: 'Manage customer relationships', route: 'crm.employee.dashboard', color: 'from-red-500 to-red-600', role: 'CRM' },
    { name: 'E-Commerce', desc: 'Manage online store orders', route: 'eco.employee.dashboard', color: 'from-yellow-500 to-yellow-600', role: 'ECO' },
]

// Filter departments based on user role if needed
const filteredDepartments = departments.filter(dept => {
    // If user has no specific role or is admin, show all
    if (!props.user?.role || props.user?.role === 'ADMIN') return true
    // Otherwise only show their department
    return dept.role === props.user?.role
})
</script>

<template>

    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Welcome, {{ user?.name || 'User' }}</h1>
            <p class="text-gray-600 dark:text-gray-300">Manage your operations efficiently</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div v-for="(val, label) in { 'Total Tasks': stats?.total_tasks, 'Pending Tasks': stats?.pending_tasks, 'Completed Tasks': stats?.completed_tasks }"
                :key="label"
                class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ label }}</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ val || 0 }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">Your Role</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white capitalize">{{ user?.position || 'N/A' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            <div v-for="dept in filteredDepartments" :key="dept.route"
                :class="['bg-gradient-to-r shadow-lg rounded-lg p-6 text-white transition-transform hover:scale-[1.02]', dept.color]">
                <h3 class="text-lg font-semibold mb-2">{{ dept.name }}</h3>
                <p class="mb-4 text-white/80 text-sm">{{ dept.desc }}</p>
                <Link :href="route(dept.route)"
                    class="inline-block bg-white/20 backdrop-blur-md text-white border border-white/30 px-4 py-2 rounded-lg font-semibold hover:bg-white hover:text-gray-900 transition-all">
                    Open Dashboard
                </Link>
            </div>

            <div
                class="bg-gradient-to-r from-gray-500 to-gray-600 rounded-lg shadow-lg p-6 text-white transition-transform hover:scale-[1.02]">
                <h3 class="text-lg font-semibold mb-2">Profile Settings</h3>
                <p class="mb-4 text-gray-50 text-sm">Update your personal information</p>
                <Link :href="route('profile.edit')"
                    class="inline-block bg-white text-gray-700 px-4 py-2 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                    Edit Profile
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>