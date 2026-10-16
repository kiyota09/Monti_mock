import { resolveNav } from './resolveNav'
import { MODULES } from './navConfig'

describe('resolveNav', () => {
    test('CEO: Executive workspace first + CRM only (overseer model)', () => {
        const user = { role: 'CEO', position: 'president', modules: [] }
        const result = resolveNav(MODULES, user, { permissions: [], modules: [] })
        // 10 executive pages + 11 CRM pages
        expect(result.totalCount).toBe(21)
        expect(result.showBar).toBe(true)
        // Bar holds the first 4 executive items — never 4x "Dashboard"
        expect(result.barItems.map(i => i.id)).toEqual([
            'exec_ceo_dashboard', 'ceo_inbox', 'exec_ceo_approvals', 'exec_ceo_reports',
        ])
        expect(new Set(result.barItems.map(i => i.label)).size).toBe(result.barItems.length)
        // Remaining executive pages stay grouped, then CRM
        expect(result.moreGroups[0].moduleId).toBe('EXEC')
        expect(result.moreGroups[0].pages.map(p => p.id)).toEqual([
            'exec_ceo_board_pack', 'exec_ceo_deliveries', 'exec_ceo_traceability',
            'exec_ceo_goals', 'exec_ceo_audit', 'exec_ceo_access',
        ])
        const moduleIds = [...new Set(result.moreGroups.flatMap(g => g.pages).map(p => p.moduleId))]
        expect(moduleIds).toContain('CRM')
        expect(moduleIds).not.toContain('HRM')
        expect(moduleIds).not.toContain('FIN')
        expect(moduleIds).not.toContain('MAN')
    })

    test('COO: VP workspace + CRM only, never HRM', () => {
        const user = { role: 'COO', position: 'vice_president', modules: [] }
        const result = resolveNav(MODULES, user, { permissions: [], modules: [] })
        expect(result.totalCount).toBe(9 + 11)
        expect(result.barItems[0].id).toBe('exec_vp_dashboard')
        const allPages = [...result.barItems, ...result.moreGroups.flatMap(g => g.pages)]
        const moduleIds = [...new Set(allPages.map(p => p.moduleId))]
        expect(moduleIds).toContain('VP')
        expect(moduleIds).toContain('CRM')
        expect(moduleIds).not.toContain('HRM')
    })

    test('vice_president position without COO role: CRM only (mirrors desktop Sidebar)', () => {
        // Desktop only renders a workspace for role CEO/COO; a VP-positioned
        // non-COO account keeps module nav with the overseer filter: CRM
        // allowed, HRM denied.
        const user = { role: 'HRM', position: 'vice_president', modules: ['HRM'] }
        const result = resolveNav(MODULES, user, { permissions: [], modules: ['HRM'] })
        const allPages = [...result.barItems, ...result.moreGroups.flatMap(g => g.pages)]
        const moduleIds = [...new Set(allPages.map(p => p.moduleId))]
        expect(moduleIds).toEqual(['CRM'])
    })

    test('secretary: workspace + full home module, no ungranted modules', () => {
        const user = { role: 'HRM', position: 'secretary', modules: ['HRM'] }
        const result = resolveNav(MODULES, user, {
            permissions: [],
            modules: ['HRM'],
            rootModule: 'HRM',
        })
        // 4 secretary items first, then HRM pages fill the More sheet
        expect(result.barItems.map(i => i.id)).toEqual([
            'exec_sec_dashboard', 'exec_sec_documents', 'exec_sec_meetings', 'exec_sec_memos',
        ])
        const allPages = [...result.barItems, ...result.moreGroups.flatMap(g => g.pages)]
        const moduleIds = [...new Set(allPages.map(p => p.moduleId))]
        expect(moduleIds).toContain('SEC')
        expect(moduleIds).toContain('HRM')
        expect(moduleIds).not.toContain('FIN')
    })

    test('secretary with extra FIN grant: FIN section filtered per page', () => {
        const user = { role: 'HRM', position: 'secretary', modules: ['HRM', 'FIN'], granted_modules: ['HRM', 'FIN'] }
        const result = resolveNav(MODULES, user, {
            permissions: ['fin.dashboard', 'fin.receivables'],
            modules: ['HRM', 'FIN'],
            rootModule: 'HRM',
        })
        const allPages = [...result.barItems, ...result.moreGroups.flatMap(g => g.pages)]
        const finPages = allPages.filter(p => p.moduleId === 'FIN')
        expect(finPages.length).toBe(2)
        expect(finPages.map(p => p.id).sort()).toEqual(['fin_overview', 'fin_receivables'])
    })

    test('manager with explicit IT rows: rows are the exact set (no full-module shortcut)', () => {
        const user = { role: 'HRM', position: 'manager', modules: ['HRM'], explicit_modules: ['HRM'] }
        const result = resolveNav(MODULES, user, {
            permissions: ['hrm.dashboard', 'hrm.attendance'],
            modules: ['HRM'],
            explicitModules: ['HRM'],
        })
        const allPages = [...result.barItems, ...result.moreGroups.flatMap(g => g.pages)]
        expect(allPages.map(p => p.id).sort()).toEqual(['hrm_attendance', 'hrm_dashboard'])
    })

    test('manager with no explicit rows: full home module', () => {
        const user = { role: 'HRM', position: 'manager', modules: ['HRM'] }
        const result = resolveNav(MODULES, user, { permissions: [], modules: ['HRM'] })
        expect(result.totalCount).toBeGreaterThan(5)
        expect(result.showBar).toBe(true)
    })

    test('duplicate generic labels across modules are qualified in the bar', () => {
        const user = { role: 'HRM', position: 'staff', modules: ['HRM', 'LOG'] }
        const result = resolveNav(MODULES, user, {
            permissions: ['hrm.dashboard', 'log.dashboard'],
            modules: ['HRM', 'LOG'],
        })
        expect(result.totalCount).toBe(2)
        expect(result.barItems.map(i => i.label).sort()).toEqual(['HRM Dashboard', 'LOG Dashboard'])
    })

    test('manufacturing supervisor: MAN pages filtered per grant (no blanket access)', () => {
        const user = { role: 'PRO', position: 'staff', is_manufacturing_supervisor: true, modules: ['PRO', 'MAN'] }
        const result = resolveNav(MODULES, user, {
            permissions: ['man.dashboard', 'man.production'],
            modules: ['PRO', 'MAN'],
        })
        const allPages = [...result.barItems, ...result.moreGroups.flatMap(g => g.pages)]
        const manPages = allPages.filter(p => p.moduleId === 'MAN')
        // Quality Checker shares the man.production grant, so it is included.
        expect(manPages.map(p => p.id).sort()).toEqual(['man_dashboard', 'man_production', 'man_quality_checker'])
        expect(allPages.some(p => p.id === 'man_rejected')).toBe(false)
        expect(allPages.some(p => p.id === 'man_inventory')).toBe(false)
    })

    test('staff with exactly 5 pages: all 5 in the bar, no More', () => {
        const user = { role: 'HRM', position: 'staff', modules: ['HRM'] }
        // One grant per page (note: 'hrm.employee' alone unlocks 4 pages,
        // so single-page grants are used here to total exactly 5).
        const perms = ['hrm.dashboard', 'hrm.attendance', 'hrm.leave', 'hrm.payroll', 'hrm.analytics']
        const result = resolveNav(MODULES, user, { permissions: perms, modules: ['HRM'] })
        expect(result.totalCount).toBe(5)
        expect(result.barItems).toHaveLength(5)
        expect(result.moreGroups).toHaveLength(0)
        expect(result.showBar).toBe(true)
    })

    test('staff with 3 pages: 3 equal slots, no More', () => {
        const user = { role: 'HRM', position: 'staff', modules: ['HRM'] }
        const perms = ['hrm.dashboard', 'hrm.attendance', 'hrm.leave']
        const result = resolveNav(MODULES, user, { permissions: perms, modules: ['HRM'] })
        expect(result.totalCount).toBe(3)
        expect(result.barItems).toHaveLength(3)
        expect(result.moreGroups).toHaveLength(0)
        expect(result.showBar).toBe(true)
    })

    test('user with 1 page: no bar shown', () => {
        const user = { role: 'HRM', position: 'staff', modules: ['HRM'] }
        const perms = ['hrm.dashboard']
        const result = resolveNav(MODULES, user, { permissions: perms, modules: ['HRM'] })
        expect(result.totalCount).toBe(1)
        expect(result.showBar).toBe(false)
        expect(result.showEmptyState).toBe(false)
    })

    test('user with 0 pages: empty state shown', () => {
        const user = { role: 'HRM', position: 'staff', modules: [] }
        const result = resolveNav(MODULES, user, { permissions: [], modules: [] })
        expect(result.totalCount).toBe(0)
        expect(result.showBar).toBe(false)
        expect(result.showEmptyState).toBe(true)
    })

    test('page permission removed while on that page: redirected', () => {
        const user = { role: 'HRM', position: 'staff', modules: ['HRM'] }
        const permsBefore = ['hrm.dashboard', 'hrm.employee', 'hrm.attendance']
        const result = resolveNav(MODULES, user, { permissions: permsBefore, modules: ['HRM'] })
        expect(result.barItems.some(p => p.id === 'hrm_attendance')).toBe(true)

        const permsAfter = ['hrm.dashboard', 'hrm.employee']
        const resultAfter = resolveNav(MODULES, user, { permissions: permsAfter, modules: ['HRM'] })
        expect(resultAfter.barItems.some(p => p.id === 'hrm_attendance')).toBe(false)
    })

    test('pinned pages always appear in the bar', () => {
        const user = { role: 'HRM', position: 'staff', modules: ['HRM'] }
        const perms = [
            'hrm.dashboard', 'hrm.employee', 'hrm.attendance', 'hrm.leave',
            'hrm.payroll', 'hrm.training', 'hrm.analytics', 'hrm.trainees'
        ]
        const result = resolveNav(MODULES, user, { permissions: perms, modules: ['HRM'] })
        const dashboardInBar = result.barItems.some(p => p.id === 'hrm_dashboard')
        expect(dashboardInBar).toBe(true)
    })

    test('Home/Dashboard always gets slot 1', () => {
        const user = { role: 'FIN', position: 'manager', modules: ['FIN'] }
        const result = resolveNav(MODULES, user, { permissions: [], modules: ['FIN'] })
        expect(result.barItems[0].id).toBe('fin_overview')
    })

    test('totalCount > 5 triggers More button', () => {
        const user = { role: 'HRM', position: 'manager', modules: ['HRM'] }
        const result = resolveNav(MODULES, user, { permissions: [], modules: ['HRM'] })
        expect(result.totalCount).toBeGreaterThan(5)
        expect(result.barItems.length + 1).toBeLessThanOrEqual(5)
        expect(result.moreGroups.length).toBeGreaterThan(0)
    })
})
