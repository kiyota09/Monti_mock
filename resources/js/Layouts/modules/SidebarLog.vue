<script>
import {
    LayoutDashboard, Package, Send, Truck, Users, Eye, Award,
    Navigation, MapPin, Camera, FileText,
} from 'lucide-vue-next'

export const logModule = {
    key: 'LOG',
    label: 'Logistics',
    icon: Truck,
    group: 'core',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.hasLogisticsAccess || ctx.hasAnyModuleGrant('LOG'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasModulePermission, grantedModules } = ctx
        const all = [
            { label: 'Dashboard', href: route('logistics.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            { label: 'Load', href: route('logistics.load.index'), icon: Package, permKey: 'load' },
            { label: 'Dispatch', href: route('logistics.dispatch.index'), icon: Send, permKey: 'dispatch' },
            { label: 'Fleet', href: route('logistics.fleet.index'), icon: Truck, permKey: 'fleet' },
            { label: 'Drivers', href: route('logistics.drivers.index'), icon: Users, permKey: 'drivers' },
            { label: 'Routes', href: route('logistics.routes'), icon: Navigation, permKey: 'routes' },
            { label: 'Tracking', href: route('logistics.tracking'), icon: MapPin, permKey: 'tracking' },
            { label: 'Proof', href: route('logistics.proof.index'), icon: Camera, permKey: 'proof' },
            { label: 'Reports', href: route('logistics.reports.index'), icon: FileText, permKey: 'reports' },
        ]
        if (isCEO) return all
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        // With zero explicit rows the backend auto-grants fill the shared
        // list, so managers/staff without seeded rows still see everything.
        if (userPosition === 'manager' && user?.role === 'LOG' && !ctx.hasExplicitModuleGrants('LOG')) return all
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'LOG') return all
        // No supervisor shortcut: the backend grants supervisors the module shell
        // but every page still needs an explicit row — fall through to filter.
        // No fallback: all-'disabled' (the IT default) yields an empty list,
        // buildNavItems skips the module → empty sidebar → AwaitingAccess.
        return all.filter(child => hasModulePermission('LOG', child.permKey))
    },
}
</script>
