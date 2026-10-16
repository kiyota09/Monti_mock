import { hrmModule } from '../modules/SidebarHrm.vue'
import { crmModule } from '../modules/SidebarCrm.vue'
import { manModule } from '../modules/SidebarMan.vue'
import { logModule } from '../modules/SidebarLog.vue'
import { ecoModule } from '../modules/SidebarEco.vue'
import { ordModule } from '../modules/SidebarOrd.vue'
import { scmModule } from '../modules/SidebarScm.vue'
import { warehouseModule } from '../modules/SidebarWarehouse.vue'
import { inventoryModule } from '../modules/SidebarInventory.vue'
import { proModule } from '../modules/SidebarPro.vue'
import { finModule } from '../modules/SidebarFin.vue'
import { itModule } from '../modules/SidebarIt.vue'
import { workforceModule } from '../modules/SidebarWorkforce.vue'

// Order matters here — it's the order modules appear in the sidebar.
// HRM/CRM/MAN/LOG render under "Core Modules", the rest under "Feature Modules".
const CORE_AND_FEATURE_MODULES = [
    hrmModule, crmModule, manModule, logModule,
    ecoModule, ordModule, scmModule, warehouseModule, inventoryModule, proModule, finModule, itModule,
]

/**
 * @param {object} ctx - everything a module's condition()/getChildren() might need:
 *   route, isCEO, isSecretaryOrGM, userPosition, user, grantedModules,
 *   canAccessModule, canAccessWorkforce, hasModulePermission, hasHrmPermission,
 *   hasCrmPermission, hasWorkforcePermission, hasWarehouseAccess, hasInventoryAccess,
 *   hasOrdAccess, hasLogisticsAccess, isManufacturingSupervisor, supervisedDepartment,
 *   qualityChecker: { isOpen, toggle }, roleDropdowns: { getState, toggle },
 *   dropdowns: { HRM: {isOpen, toggle}, CRM: {...}, ... , WRF: {...} }
 */
export function buildNavItems(ctx) {
    const coreModules = []
    const featureModules = []

    for (const mod of CORE_AND_FEATURE_MODULES) {
        if (!mod.condition(ctx)) continue
        const children = mod.getChildren(ctx)
        if (!children || children.length === 0) continue

        const dropdown = ctx.dropdowns[mod.key]
        const item = {
            label: mod.label,
            icon: mod.icon,
            isDropdown: true,
            isOpen: dropdown.isOpen.value,
            toggle: dropdown.toggle,
            children,
        }
        ;(mod.group === 'core' ? coreModules : featureModules).push(item)
    }

    // Workforce is feature-only and was special-cased in the original file too
    if (workforceModule.condition(ctx)) {
        const children = workforceModule.getChildren(ctx)
        if (children.length > 0) {
            const dropdown = ctx.dropdowns.WRF
            featureModules.push({
                label: workforceModule.label,
                icon: workforceModule.icon,
                isDropdown: true,
                isOpen: dropdown.isOpen.value,
                toggle: dropdown.toggle,
                children,
            })
        }
    }

    const items = []
    if (coreModules.length > 0) {
        items.push({ isHeading: true, label: 'Core Modules' })
        items.push(...coreModules)
    }
    if (featureModules.length > 0) {
        items.push({ isHeading: true, label: 'Feature Modules' })
        items.push(...featureModules)
    }
    return items
}
