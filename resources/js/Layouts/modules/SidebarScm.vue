<script>
import { ShoppingCart, ClipboardList, Building2, Truck, LayoutDashboard, CalendarClock, PackageCheck, ClipboardCheck, BarChart3 } from 'lucide-vue-next'

export const scmModule = {
    key: 'SCM',
    label: 'Supply Chain',
    icon: Truck,
    group: 'feature',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.canAccessModule('SCM') || ctx.hasAnyModuleGrant('SCM'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasModulePermission, grantedModules } = ctx
        const all = [
            { label: 'Dashboard', href: route('scm.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            { label: 'Sales Orders', href: route('scm.sales-orders'), icon: ShoppingCart, permKey: 'sales' },
            { label: 'Procurement Orders', href: route('scm.procurement-orders'), icon: ClipboardList, permKey: 'procurement' },
            { label: 'Planning', href: route('scm.planning'), icon: CalendarClock, permKey: 'planning' },
            { label: 'Purchase Orders', href: route('scm.purchase-orders'), icon: PackageCheck, permKey: 'purchase' },
            { label: 'Deliveries', href: route('scm.deliveries'), icon: ClipboardCheck, permKey: 'deliveries' },
            { label: 'Vendors', href: route('scm.vendors'), icon: Building2, permKey: 'vendor' },
            { label: 'Analytics', href: route('scm.analytics'), icon: BarChart3, permKey: 'analytics' },
        ]
        if (isCEO) return all
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        if (userPosition === 'manager' && user?.role === 'SCM' && !ctx.hasExplicitModuleGrants('SCM')) return all
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'SCM') return all
        // No supervisor shortcut: the backend grants supervisors the module shell
        // but every page still needs an explicit row — fall through to filter.
        return all.filter(child => hasModulePermission('SCM', child.permKey))
    },
}
</script>
