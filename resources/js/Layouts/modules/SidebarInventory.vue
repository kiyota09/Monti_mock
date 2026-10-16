<script>
import { LayoutDashboard, Spool, Package, AlertCircle, Boxes } from 'lucide-vue-next'

export const inventoryModule = {
    key: 'INV',
    label: 'Inventory',
    icon: Boxes,
    group: 'feature',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.hasInventoryAccess || ctx.hasAnyModuleGrant('INV'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasModulePermission, grantedModules } = ctx
        const all = [
            { label: 'Dashboard', href: route('inv.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            { label: 'Materials', href: route('inv.materials'), icon: Spool, permKey: 'materials' },
            { label: 'Products', href: route('inv.products'), icon: Package, permKey: 'products' },
            { label: 'Stock Checker', href: route('inv.checker'), icon: AlertCircle, permKey: 'checker' },
        ]
        if (isCEO) return all
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        if (userPosition === 'manager' && user?.role === 'INV' && !ctx.hasExplicitModuleGrants('INV')) return all
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'INV') return all
        // No supervisor shortcut: the backend grants supervisors the module shell
        // but every page still needs an explicit row — fall through to filter.
        return all.filter(child => hasModulePermission('INV', child.permKey))
    },
}
</script>
