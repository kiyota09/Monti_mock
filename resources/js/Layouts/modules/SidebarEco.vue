<script>
import {
    LayoutDashboard, ShoppingBag, Users as UsersIcon,
    CreditCard, Send, Building2,
} from 'lucide-vue-next'

export const ecoModule = {
    key: 'ECO',
    label: 'E-Commerce',
    icon: ShoppingBag,
    group: 'feature',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.canAccessModule('ECO') || ctx.hasAnyModuleGrant('ECO'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasModulePermission, grantedModules } = ctx
        const all = [
            { label: 'Dashboard', href: route('eco.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            { label: 'Store', href: route('eco.store'), icon: ShoppingBag, permKey: 'store' },
            { label: 'Suppliers', href: route('eco.suppliers'), icon: UsersIcon, permKey: 'supplier' },
            { label: 'Clients', href: route('eco.clients'), icon: Building2, permKey: 'client' },
            { label: 'Credit', href: route('eco.credit'), icon: CreditCard, permKey: 'credit' },
            { label: 'Push Center', href: route('eco.push'), icon: Send, permKey: 'push' },
        ]
        if (isCEO) return all
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        if (userPosition === 'manager' && user?.role === 'ECO' && !ctx.hasExplicitModuleGrants('ECO')) return all
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'ECO') return all
        // No supervisor shortcut: the backend grants supervisors the module shell
        // but every page still needs an explicit row — fall through to filter.
        return all.filter(child => hasModulePermission('ECO', child.permKey))
    },
}
</script>
