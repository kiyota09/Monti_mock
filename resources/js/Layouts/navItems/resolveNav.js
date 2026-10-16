import { getLeadingNav } from './executiveNav'

const MAX_BAR_ITEMS = 5
// The last bottom-bar slot is always the More button.
const BAR_SLOTS = MAX_BAR_ITEMS - 1

/**
 * Mobile bottom-nav resolver.
 *
 * Mirrors the desktop access model (usePermissions + Sidebar modules) so the
 * mobile nav shows exactly what the desktop sidebar — and the IT Access
 * Control page grants — allow:
 *
 * - Executives/secretary get their workspace first (via getLeadingNav,
 *   same items as Sidebar.vue), then module pages.
 * - CEO sees Executive + CRM only; COO / vice_president sees Vice
 *   Presidency + CRM only, never HRM (overseer model).
 * - Native managers keep full home-module access ONLY while IT has seeded
 *   zero explicit page rows; seeded rows are the exact set.
 * - Secretary / special_officer: full home (root) module, per-page filter
 *   on extra granted modules.
 * - Staff: strictly the granted (view/edit) pages — 'disabled' rows never
 *   appear. Extra-module page grants still display that section.
 * - Manufacturing supervisors: per-page filter on MAN (no blanket grant).
 * - Legacy MAN managers: no shortcut (mirrors SidebarMan — no manager
 *   exists there anymore), so they fall through to the per-page filter.
 * - Workforce (WRF) is workforce-permission based, mirroring
 *   canAccessWorkforce/hasWorkforcePermission.
 *
 * options:
 *   permissions:  ['mod.page', ...] usable (non-disabled) page grants, any case
 *   modules:      visible module ids (home role + granted_modules)
 *   explicitModules: UPPER module ids with explicit IT page rows
 *   rootModule:   UPPER home module id (secretary/special_officer) or null
 *   workforce:    workforce page keys (e.g. ['scheduler', 'leave'])
 *   warehouse/inventory/ord/logistics: booleans mirroring the
 *     hasWarehouseAccess/hasInventoryAccess/hasOrdAccess/hasLogisticsAccess
 *     helpers (secretary/supervisor/flag based)
 */
export function resolveNav(config, user, options = {}) {
    const {
        permissions = [],
        modules = [],
        explicitModules = [],
        rootModule = null,
        workforce = [],
        warehouse = false,
        inventory = false,
        ord = false,
        logistics = false,
    } = options

    // Case-tolerant like the backend — writers store UPPER modules.
    const normPerms = new Set((permissions || []).map(p => String(p).toLowerCase()))
    const workforceKeys = (workforce || []).map(k => String(k).toLowerCase())
    for (const k of workforceKeys) normPerms.add(`wrf.${k}`)
    const explicitMods = new Set((explicitModules || []).map(m => String(m).toUpperCase()))
    const root = rootModule ? String(rootModule).toUpperCase() : null
    const moduleList = modules || []
    // Secretary/special_officer module access (mirrors secretaryHasModule):
    // explicit granted_modules win, otherwise the home role module.
    const grantedList = (user?.granted_modules || []).map(m => String(m).toUpperCase())

    // ─── Executive / secretary workspace (same items as desktop) ──
    const lead = getLeadingNav(user)
    const leadItems = ((lead && lead.items) || []).map(p => ({
        ...p,
        moduleId: lead.group.moduleId,
        moduleLabel: lead.group.moduleLabel,
    }))

    const allVisiblePages = []
    const visibleModules = []

    for (const mod of config) {
        if (!moduleVisible(mod, user, moduleList, grantedList, workforceKeys, normPerms, { warehouse, inventory, ord, logistics })) continue

        const visiblePages = mod.pages.filter(page =>
            pageVisible(mod, page, user, moduleList, grantedList, normPerms, explicitMods, root))

        if (visiblePages.length === 0) continue

        visibleModules.push({ ...mod, visiblePages })
        allVisiblePages.push(...visiblePages.map(p => ({ ...p, moduleId: mod.id, moduleLabel: mod.label })))
    }

    const totalCount = leadItems.length + allVisiblePages.length

    if (totalCount === 0) {
        return { barItems: [], moreGroups: [], totalCount: 0, showBar: false, showEmptyState: true }
    }

    if (totalCount === 1) {
        return { barItems: [...leadItems, ...allVisiblePages], moreGroups: [], totalCount: 1, showBar: false, showEmptyState: false }
    }

    if (totalCount <= MAX_BAR_ITEMS) {
        return {
            barItems: qualifyBarLabels([...leadItems, ...sortForBar(allVisiblePages)]),
            moreGroups: [],
            totalCount,
            showBar: true,
            showEmptyState: false,
        }
    }

    // Workspace items take the first bar slots, then the module home page,
    // pinned pages and top-priority pages fill the rest (max 4 + More).
    const sorted = sortForBar(allVisiblePages)
    const homePage = sorted.find(p => p.id.includes('dashboard') || p.id.includes('overview'))
    const barItems = leadItems.slice(0, BAR_SLOTS)

    if (barItems.length < BAR_SLOTS) {
        const picks = []
        if (homePage) picks.push(homePage)
        const rest = [...sorted.filter(p => p.pinned && p !== homePage), ...sorted.filter(p => !p.pinned && p !== homePage)]
        for (const p of rest) {
            if (barItems.length + picks.length >= BAR_SLOTS) break
            picks.push(p)
        }
        barItems.push(...picks)
    }

    const barIds = new Set(barItems.map(p => p.id))
    const leadRemainder = leadItems.filter(p => !barIds.has(p.id))
    const remaining = allVisiblePages.filter(p => !barIds.has(p.id))

    const moreGroups = []
    if (leadRemainder.length > 0 && lead) {
        moreGroups.push({ moduleId: lead.group.moduleId, moduleLabel: lead.group.moduleLabel, pages: leadRemainder })
    }
    for (const page of remaining) {
        const existing = moreGroups.find(g => g.moduleId === page.moduleId)
        if (existing) {
            existing.pages.push(page)
        } else {
            moreGroups.push({ moduleId: page.moduleId, moduleLabel: page.moduleLabel, pages: [page] })
        }
    }

    return { barItems: qualifyBarLabels(barItems), moreGroups, totalCount, showBar: true, showEmptyState: false }
}

function isExec(user) {
    const role = user?.role
    const pos = String(user?.position || '').toLowerCase()
    return role === 'CEO' || role === 'COO' || pos === 'vice_president'
}

function isSecretaryOrGM(user) {
    const pos = String(user?.position || '').toLowerCase()
    return pos === 'secretary' || pos === 'special_officer'
}

function hasPagePrefix(normPerms, modId) {
    const prefix = `${String(modId).toLowerCase()}.`
    for (const p of normPerms) {
        if (p.startsWith(prefix)) return true
    }
    return false
}

// Mirrors usePermissions.canAccessModule (+hasAnyModuleGrant section
// allowance + the warehouse/inventory/ord/logistics flag helpers).
function moduleVisible(mod, user, moduleList, grantedList, workforceKeys, normPerms, flags) {
    const key = mod.id
    const role = user?.role
    const pos = String(user?.position || '').toLowerCase()

    // Workforce is workforce-permission based (mirrors canAccessWorkforce).
    if (key === 'WRF') {
        if (role === 'CEO') return false
        if (isSecretaryOrGM(user)) return grantedList.includes('WRF')
        if (user?.is_manufacturing_supervisor) return grantedList.includes('WRF')
        return workforceKeys.length > 0
    }

    // Flag-gated modules (mirror hasWarehouseAccess & friends).
    if (key === 'WAR' && !flags.warehouse) return hasPagePrefix(normPerms, key)
    if (key === 'INV' && !flags.inventory) return hasPagePrefix(normPerms, key)
    if (key === 'ORD' && !flags.ord) return hasPagePrefix(normPerms, key)
    if (key === 'LOG' && !flags.logistics) return hasPagePrefix(normPerms, key)

    // Overseer model: executives only ever see CRM here; VP never sees HRM.
    if (key === 'CRM' && isExec(user)) return true
    if (key === 'HRM' && (role === 'COO' || pos === 'vice_president')) return false
    if (role === 'CEO') return false

    if (moduleList.includes(key)) return true
    if (key === 'MAN' && user?.manufacturing_role) return true
    // Extra-module page grants still display the section (filtered per page).
    return hasPagePrefix(normPerms, key)
}

// Mirrors each Sidebar* module's getChildren page filter.
function pageVisible(mod, page, user, moduleList, grantedList, normPerms, explicitMods, root) {
    const role = user?.role
    const pos = String(user?.position || '').toLowerCase()

    // Workforce pages mirror SidebarWorkforce.getChildren.
    if (mod.id === 'WRF') {
        if (pos === 'manager' && role === 'WRF') return true
        if (isSecretaryOrGM(user) && moduleList.includes('WRF')) return true
        if (user?.is_manufacturing_supervisor && grantedList.includes('WRF')) return true
        return normPerms.has(`wrf.${permKeyOf(page)}`)
    }

    // Executives hold every CRM page; nothing else here (their workspace
    // comes from the leading nav).
    if (role === 'CEO') return mod.id === 'CRM'
    if (role === 'COO' || pos === 'vice_president') return mod.id === 'CRM'

    // Native manager with no explicit IT rows keeps full home-module access;
    // seeded rows are the exact set. No MAN shortcut — there is no
    // manufacturing manager anymore (mirrors SidebarMan).
    if (mod.id !== 'MAN' && pos === 'manager' && role === mod.id && !explicitMods.has(mod.id)) return true

    // Secretary / special officer: full home (root) module, per-page filter
    // on extra granted modules (mirrors backend CheckPagePermission).
    const effectiveRoot = root || (isSecretaryOrGM(user) ? String(role || '').toUpperCase() : null)
    if (isSecretaryOrGM(user) && effectiveRoot && effectiveRoot === mod.id) return true

    // No supervisor blanket grant: supervisors need usable per-page rows
    // (mirrors SidebarMan — the shared list already excludes 'disabled').
    return normPerms.has(String(page.permission).toLowerCase())
}

function permKeyOf(page) {
    return String(page.permission || '').split('.').slice(1).join('.').toLowerCase()
}

function sortForBar(pages) {
    return [...pages].sort((a, b) => {
        if (a.pinned && !b.pinned) return -1
        if (!a.pinned && b.pinned) return 1
        if (b.priority !== a.priority) return b.priority - a.priority
        return 0
    })
}

// Generic module labels ("Dashboard", "Overview") repeat across modules —
// once they share the bottom bar, qualify the duplicates with the module id
// ("HRM Dashboard") so every tab is distinguishable. Groups keep their
// module headers, so More/drawer lists stay untouched.
function qualifyBarLabels(items) {
    const counts = {}
    for (const p of items) counts[p.label] = (counts[p.label] || 0) + 1
    return items.map(p => (
        counts[p.label] > 1 && p.moduleId ? { ...p, label: `${p.moduleId} ${p.label}` } : p
    ))
}
