<script>
import { LayoutDashboard, CalendarCheck, FileText, UserX, CalendarDays } from 'lucide-vue-next'

export const workforceModule = {
    key: 'WRF',
    label: 'Workforce Management',
    icon: CalendarDays,
    group: 'feature',

    condition: (ctx) => ctx.canAccessWorkforce(),

    getChildren(ctx) {
        const { route, isCEO, isSecretaryOrGM, userPosition, user, canAccessModule, hasWorkforcePermission, grantedModules } = ctx
        const all = [
            { label: 'Dashboard', href: route('workforce.dashboard'), icon: LayoutDashboard, permKey: 'dashboard' },
            { label: 'Scheduler', href: route('workforce.scheduler'), icon: CalendarCheck, permKey: 'scheduler' },
            { label: 'Leave Requests', href: route('workforce.leave'), icon: FileText, permKey: 'leave' },
            { label: 'Absence Tracking', href: route('workforce.absent'), icon: UserX, permKey: 'absent' },
        ]
        if (isCEO) return all
        if (userPosition === 'manager' && user?.role === 'WRF') return all
        if (isSecretaryOrGM && canAccessModule('WRF')) return all
        if (user?.is_manufacturing_supervisor && grantedModules.includes('WRF')) return all
        return all.filter(child => hasWorkforcePermission(child.permKey))
    },
}
</script>
