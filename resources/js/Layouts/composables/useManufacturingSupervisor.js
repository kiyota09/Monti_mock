import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

export function useManufacturingSupervisor(user) {
    const isManufacturingSupervisor = computed(() => user.value?.is_manufacturing_supervisor === true)
    const supervisorRoles = computed(() => user.value?.supervisor_roles || [])
    const activeManufacturingRole = computed(() => user.value?.active_manufacturing_role || null)
    const supervisedDepartment = computed(() => {
        if (!isManufacturingSupervisor.value) return null
        return user.value?.supervisor_department || null
    })

    const switchManufacturingRole = (role) => {
        router.post(route('man.supervisor.switch'), { role }, {
            preserveScroll: true,
            onSuccess: () => window.location.reload()
        })
    }

    const formatRoleLabel = (role) => {
        if (!role) return ''
        return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    }

    return {
        isManufacturingSupervisor,
        supervisorRoles,
        activeManufacturingRole,
        supervisedDepartment,
        switchManufacturingRole,
        formatRoleLabel,
    }
}
