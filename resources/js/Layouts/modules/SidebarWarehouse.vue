<script>
import { Warehouse, Truck, Package, XCircle } from 'lucide-vue-next'

export const warehouseModule = {
    key: 'WAR',
    label: 'Warehouse',
    icon: Warehouse,
    group: 'feature',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.hasWarehouseAccess || ctx.hasAnyModuleGrant('WAR'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasModulePermission, grantedModules } = ctx
        const all = [
            { label: 'All Warehouses', href: route('warehouse.index'), icon: Warehouse, permKey: 'warehouse' },
            { label: 'Receiving', href: route('warehouse.receiving'), icon: Truck, permKey: 'receiving' },
            { label: 'Packages', href: route('warehouse.packages'), icon: Package, permKey: 'packages' },
            { label: 'Rejects', href: route('warehouse.rejects'), icon: XCircle, permKey: 'reject' },
        ]
        if (isCEO) return all
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        if (userPosition === 'manager' && user?.role === 'WAR' && !ctx.hasExplicitModuleGrants('WAR')) return all
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'WAR') return all
        // No supervisor shortcut: the backend grants supervisors the module shell
        // but every page still needs an explicit row — fall through to filter.
        return all.filter(child => hasModulePermission('WAR', child.permKey))
    },
}
</script>
