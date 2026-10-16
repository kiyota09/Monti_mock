<script>
import { LayoutDashboard, LifeBuoy, MonitorSmartphone, Activity, BookOpen, GitPullRequest, ShieldCheck, Users, ScrollText, MapPin } from 'lucide-vue-next'

export const itModule = {
    key: 'IT',
    label: 'IT & Systems',
    icon: MonitorSmartphone,
    group: 'feature',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.canAccessModule('IT') || ctx.hasAnyModuleGrant('IT'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasModulePermission, grantedModules } = ctx
        const all = [
            { label: 'Dashboard', href: route('it.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            { label: 'Service Desk', href: route('it.tickets'), icon: LifeBuoy, permKey: 'tickets' },
            { label: 'Assets', href: route('it.assets'), icon: MonitorSmartphone, permKey: 'assets' },
            { label: 'Monitoring', href: route('it.monitoring'), icon: Activity, permKey: 'monitoring' },
            { label: 'Knowledge Base', href: route('it.knowledge'), icon: BookOpen, permKey: 'knowledge' },
            { label: 'Geolocation', href: route('it.location.index'), icon: MapPin, permKey: 'location' },
            { label: 'Changes', href: route('it.changes'), icon: GitPullRequest, permKey: 'changes' },
            { label: 'Access Control', href: route('it.access-control'), icon: Users, permKey: 'access_control' },
            { label: 'Access Logs', href: route('it.access-logs'), icon: ScrollText, permKey: 'access_logs' },
        ]
        if (isCEO) {
            all.push({ label: 'Access Control', href: route('it.access'), icon: ShieldCheck, permKey: 'access' })
        }
        if (isCEO) return all
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        if (userPosition === 'manager' && user?.role === 'IT' && !ctx.hasExplicitModuleGrants('IT')) return all
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'IT') return all
        // No supervisor shortcut: the backend grants supervisors the module shell
        // but every page still needs an explicit row — fall through to filter.
        return all.filter(child => hasModulePermission('IT', child.permKey))
    },
}
</script>
