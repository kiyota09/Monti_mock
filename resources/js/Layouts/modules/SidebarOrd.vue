<script>
import { ClipboardList, Factory, Truck, ClipboardCheck, LayoutDashboard, Undo2 } from 'lucide-vue-next'

export const ordModule = {
    key: 'ORD',
    label: 'Order Management',
    icon: ClipboardCheck,
    group: 'feature',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.hasOrdAccess || ctx.hasAnyModuleGrant('ORD'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasModulePermission, grantedModules } = ctx
        const all = [
            { label: 'Dashboard', href: route('ord.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            { label: 'Orders', href: route('ord.orders'), icon: ClipboardList, permKey: 'orders' },
            { label: 'Productions', href: route('ord.productions'), icon: Factory, permKey: 'productions' },
            { label: 'Delivery', href: route('ord.delivery'), icon: Truck, permKey: 'delivery' },
            { label: 'Returns', href: route('ord.returns'), icon: Undo2, permKey: 'returns' },
        ]
        if (isCEO) return all
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        if (userPosition === 'manager' && user?.role === 'ORD' && !ctx.hasExplicitModuleGrants('ORD')) return all
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'ORD') return all
        // No supervisor shortcut: the backend grants supervisors the module shell
        // but every page still needs an explicit row — fall through to filter.
        return all.filter(child => hasModulePermission('ORD', child.permKey))
    },
}
</script>
