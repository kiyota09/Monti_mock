import { computed } from 'vue'

export function usePermissions(user, page) {
    const userModuleAccess = computed(() => {
        if (user.value?.position === 'secretary' || user.value?.position === 'special_officer') {
            return page.props.auth.user?.granted_modules || []
        }
        return []
    })

    const grantedModules = computed(() => {
        return (page.props.auth.user?.granted_modules || []).map(module => String(module).toUpperCase())
    })

    const hasUsableModulePage = (moduleName) => {
        const perms = user.value?.page_permissions || page.props.auth?.page_permissions || []
        return perms.some(p => String(p.module || '').toUpperCase() === String(moduleName || '').toUpperCase())
    }

    // Secretary / GM: explicit grants win; with no grants yet, fall back to
    // their home (root) module — mirrors backend User::canAccessModule().
    const secretaryHasModule = (moduleName) => {
        if (userModuleAccess.value.length === 0) return user.value?.role === moduleName
        return userModuleAccess.value.includes(moduleName)
    }

    const canAccessModule = (moduleName) => {
        const key = String(moduleName || '').toUpperCase()
        const isExecutive = ['CEO', 'COO'].includes(user.value?.role) || user.value?.position === 'vice_president'
        if (isExecutive) return grantedModules.value.includes(key) || hasUsableModulePage(key)
        if (user.value?.position === 'secretary' || user.value?.position === 'special_officer') {
            return secretaryHasModule(moduleName)
        }
        if (user.value?.is_manufacturing_supervisor) {
            if (moduleName === 'MAN') return true
            return grantedModules.value.includes(moduleName)
        }
        if (moduleName === 'MAN' && user.value?.manufacturing_role) return true
        return user.value?.role === moduleName
    }

    const canAccessWorkforce = () => {
        if (['CEO', 'COO'].includes(user.value?.role) || user.value?.position === 'vice_president') {
            return canAccessModule('WRF')
        }
        if (user.value?.position === 'secretary' || user.value?.position === 'special_officer') {
            return userModuleAccess.value.includes('WRF')
        }
        if (user.value?.is_manufacturing_supervisor) {
            return grantedModules.value.includes('WRF')
        }
        const perms = user.value?.workforce_permissions
        return perms && perms.length > 0
    }

    const hasWorkforcePermission = (pageName) => {
        if (['CEO', 'COO'].includes(user.value?.role) || user.value?.position === 'vice_president') {
            return hasPagePermission('WRF', pageName)
        }
        const perms = user.value?.workforce_permissions
        if (!perms) return false
        return perms.includes(pageName)
    }

    const hasPagePermission = (moduleKey, pageKey) => {
        const perms = user.value?.page_permissions || page.props.auth?.page_permissions || []
        // Case-tolerant like the backend (writers store UPPER modules;
        // legacy rows may differ) — a strict check hid pages whose rows
        // used another case, e.g. a special_officer CRM grant.
        return perms.some(p => String(p.module || '').toUpperCase() === String(moduleKey || '').toUpperCase()
            && String(p.page || '').toLowerCase() === String(pageKey || '').toLowerCase())
    }

    const hasHrmPermission = (pageKey) => hasPagePermission('HRM', pageKey)
    const hasCrmPermission = (pageKey) => hasPagePermission('CRM', pageKey)

    // Modules with ANY explicit PagePermission row (including all-'disabled'
    // ones). Mirrors backend CheckPagePermission: explicit rows are the exact
    // access set and override the native manager/staff full-access shortcut.
    // A native manager with zero explicit rows keeps full access; the moment
    // IT seeds rows (default disabled), only granted pages show.
    const hasExplicitModuleGrants = (moduleKey) => {
        const mods = user.value?.explicit_modules || []
        return mods.some(m => String(m).toUpperCase() === String(moduleKey).toUpperCase())
    }

    // Home module of a secretary / special officer (shared by the backend,
    // mirroring CheckPagePermission::getRootModuleForUser). Only this module
    // carries automatic full access — extra granted modules are filtered
    // per page, exactly like the backend enforces.
    const rootModule = computed(() => {
        const r = user.value?.root_module
        return r ? String(r).toUpperCase() : null
    })

    // Whether the user holds any usable grant in a module. The shared list
    // already excludes 'disabled' rows, so presence means view/edit access.
    // Used by sidebar conditions so extra-module grants (e.g. a manager given
    // pages of another module) actually display — mirroring the
    // CheckModuleAccess explicit-grants allowance.
    const hasAnyModuleGrant = (moduleKey) => {
        const perms = user.value?.page_permissions || page.props.auth?.page_permissions || []
        return perms.some(p => String(p.module || '').toUpperCase() === String(moduleKey).toUpperCase())
    }

    const hasModulePermission = (moduleKey, permissionKey) => {
        const isExecutive = ['CEO', 'COO'].includes(user.value?.role) || user.value?.position === 'vice_president'
        if (isExecutive) return hasPagePermission(moduleKey, permissionKey)
        const modulePerms = user.value?.permissions?.[moduleKey]
        return modulePerms ? modulePerms.includes(permissionKey) : false
    }

    const hasManufacturingSupervisorPagePermission = (role, pageKey) => {
        if (!user.value?.is_manufacturing_supervisor) return hasModulePermission('MAN', pageKey)
        const byRole = user.value?.manufacturing_supervisor_page_permissions?.[role]
        if (byRole && Object.prototype.hasOwnProperty.call(byRole, String(pageKey).toLowerCase())) {
            return ['view', 'edit'].includes(String(byRole[String(pageKey).toLowerCase()]).toLowerCase())
        }
        // Preserve the legacy Production-grant behavior until IT sets a
        // role-specific choice for this supervisor and staff role page.
        return hasModulePermission('MAN', 'production')
    }

    const hasWarehouseAccess = computed(() => {
        if (['CEO', 'COO'].includes(user.value?.role) || user.value?.position === 'vice_president') return canAccessModule('WAR')
        if (user.value?.position === 'secretary' || user.value?.position === 'special_officer') return canAccessModule('WAR')
        if (user.value?.is_manufacturing_supervisor) return grantedModules.value.includes('WAR')
        return user.value?.has_warehouse_access === true
    })

    const hasInventoryAccess = computed(() => {
        if (['CEO', 'COO'].includes(user.value?.role) || user.value?.position === 'vice_president') return canAccessModule('INV')
        if (user.value?.position === 'secretary' || user.value?.position === 'special_officer') return canAccessModule('INV')
        if (user.value?.is_manufacturing_supervisor) return grantedModules.value.includes('INV')
        return user.value?.has_inventory_access === true
    })

    const hasOrdAccess = computed(() => {
        if (['CEO', 'COO'].includes(user.value?.role) || user.value?.position === 'vice_president') return canAccessModule('ORD')
        if (user.value?.position === 'secretary' || user.value?.position === 'special_officer') return canAccessModule('ORD')
        if (user.value?.is_manufacturing_supervisor) return grantedModules.value.includes('ORD')
        return user.value?.has_ord_access === true
    })

    const hasLogisticsAccess = computed(() => {
        if (['CEO', 'COO'].includes(user.value?.role) || user.value?.position === 'vice_president') return canAccessModule('LOG')
        if (user.value?.position === 'secretary' || user.value?.position === 'special_officer') return canAccessModule('LOG')
        if (user.value?.is_manufacturing_supervisor) return grantedModules.value.includes('LOG')
        if (user.value?.role === 'LOG' && user.value?.position === 'manager') return true
        return user.value?.logistics_access === true
    })

    const canAccessModuleChild = (moduleKey, childPermKey, moduleSpecificCheck = null) => {
        if (moduleSpecificCheck) return moduleSpecificCheck(childPermKey)
        const isExecutive = ['CEO', 'COO'].includes(user.value?.role) || user.value?.position === 'vice_president'
        if (isExecutive) return hasPagePermission(moduleKey, childPermKey)
        const granularPerms = user.value?.permissions?.[moduleKey]
        if (granularPerms && granularPerms.length) return granularPerms.includes(childPermKey)
        const isManager = user.value?.role === moduleKey && user.value?.position === 'manager'
        const isSecretaryOrGMWithModule = (user.value?.position === 'secretary' || user.value?.position === 'special_officer') &&
            secretaryHasModule(moduleKey)
        const isSupervisorWithModule = user.value?.is_manufacturing_supervisor && grantedModules.value.includes(moduleKey)
        return isManager || isSecretaryOrGMWithModule || isSupervisorWithModule
    }

    return {
        userModuleAccess,
        rootModule,
        hasAnyModuleGrant,
        grantedModules,
        canAccessModule,
        canAccessWorkforce,
        hasWorkforcePermission,
        hasPagePermission,
        hasExplicitModuleGrants,
        hasHrmPermission,
        hasCrmPermission,
        hasModulePermission,
        hasManufacturingSupervisorPagePermission,
        hasWarehouseAccess,
        hasInventoryAccess,
        hasOrdAccess,
        hasLogisticsAccess,
        canAccessModuleChild,
    }
}
