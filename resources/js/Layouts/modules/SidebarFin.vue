<script>
import {
    LayoutDashboard, HandCoins, Receipt, Wallet, PieChart,
    Users, BarChart3, Stamp,
} from 'lucide-vue-next'

export const finModule = {
    key: 'FIN',
    label: 'Finance',
    icon: Wallet,
    group: 'feature',

    // Extra-module grants also display the section (filtered per page below).
    condition: (ctx) => ctx.canAccessModule('FIN') || ctx.hasAnyModuleGrant('FIN'),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasModulePermission, grantedModules } = ctx
        const all = [
            { label: 'Overview', href: route('fin.manager.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            { label: 'Receivables', href: route('fin.manager.receivables'), icon: HandCoins, permKey: 'receivables' },
            { label: 'Payables', href: route('fin.manager.payables'), icon: Receipt, permKey: 'payables' },
            { label: 'Expenses', href: route('fin.manager.expenses'), icon: PieChart, permKey: 'expenses' },
            { label: 'Payroll', href: route('fin.manager.payroll'), icon: Users, permKey: 'payroll' },
            { label: 'Reports', href: route('fin.manager.reports'), icon: BarChart3, permKey: 'reports' },
            { label: 'PO Approvals', href: route('fin.manager.approvals'), icon: Stamp, permKey: 'approvals' },
        ]
        if (isCEO) return all
        // Explicit rows (even all-'disabled') are the exact access set and
        // override this shortcut — mirrors backend CheckPagePermission.
        if (userPosition === 'manager' && user?.role === 'FIN' && !ctx.hasExplicitModuleGrants('FIN')) return all
        // Auto-full on the root module only (mirrors backend CheckPagePermission);
        // extra granted modules fall through to the per-page filter below.
        if (isSecretaryOrGM && ctx.rootModule === 'FIN') return all
        // No supervisor shortcut: the backend grants supervisors the module shell
        // but every page still needs an explicit row — fall through to filter.
        return all.filter(child => hasModulePermission('FIN', child.permKey))
    },
}
</script>
