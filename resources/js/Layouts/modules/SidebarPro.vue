<script>
import { LayoutDashboard, FileText, Send, ShoppingCart, ClipboardList, Navigation } from 'lucide-vue-next'

export const proModule = {
    key: 'PRO',
    label: 'Procurement',
    icon: ShoppingCart,
    group: 'feature',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.canAccessModule('PRO') || ctx.hasAnyModuleGrant('PRO'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasModulePermission, grantedModules } = ctx
        const all = [
            { label: 'Dashboard', href: route('pro.manager.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            { label: 'Material Requests', href: route('pro.manager.material-requests'), icon: ClipboardList, permKey: 'requests' },
            { label: 'Quotations', href: route('pro.manager.supplier-quotations'), icon: FileText, permKey: 'quotations' },
            { label: 'Receipts', href: route('pro.manager.receipt'), icon: Send, permKey: 'receipt' },
            { label: 'Order Tracking', href: route('pro.manager.tracking'), icon: Navigation, permKey: 'tracking' },
        ]
        if (isCEO) return all
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        if (userPosition === 'manager' && user?.role === 'PRO' && !ctx.hasExplicitModuleGrants('PRO')) return all
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'PRO') return all
        // No supervisor shortcut: the backend grants supervisors the module shell
        // but every page still needs an explicit row — fall through to filter.
        return all.filter(child => hasModulePermission('PRO', child.permKey))
    },
}
</script>
