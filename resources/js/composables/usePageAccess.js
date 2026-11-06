import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

/**
 * Global view/edit gate for every MontiERP page.
 *
 * Reads the real permission levels shared on every Inertia response
 * (auth.page_permissions: [{ module, page, permission_level }]).
 * Native managers/staff without explicit rows receive full auto-grants
 * from the backend, so `can()` stays true for them with zero changes.
 *
 * Usage:
 *   const { canView, canEdit } = usePageAccess()
 *   <button v-if="canEdit('HRM', 'employee')">…</button>
 */
export function usePageAccess() {
    const page = usePage()

    const grants = computed(() => page.props.auth?.page_permissions || [])

    const levelOf = (module, pg) => {
        const authUser = page.props.auth?.user
        if (String(module).toUpperCase() === 'MAN' && authUser?.is_manufacturing_supervisor) {
            const role = authUser.manufacturing_supervisor_current_role
            const rolePage = String(pg || '').toLowerCase()
            const scoped = role && authUser.manufacturing_supervisor_page_permissions?.[role]
            if (scoped && Object.prototype.hasOwnProperty.call(scoped, rolePage)) {
                return String(scoped[rolePage]).toLowerCase()
            }
        }
        const hit = grants.value.find(
            (g) =>
                String(g.module || '').toUpperCase() === String(module).toUpperCase() &&
                String(g.page || '').toLowerCase() === String(pg).toLowerCase()
        )
        return hit ? String(hit.permission_level || 'edit').toLowerCase() : null
    }

    const can = (module, pg, level = 'view') => {
        const lvl = levelOf(module, pg)
        if (!lvl) return false
        return level === 'view' ? true : lvl === 'edit'
    }

    return {
        can,
        canView: (module, pg) => can(module, pg, 'view'),
        canEdit: (module, pg) => can(module, pg, 'edit'),
        levelOf,
    }
}
