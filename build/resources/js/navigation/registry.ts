import type { NavGroup } from '@/types'
import type { ModuleNavConfig, NavContext } from './types'
import { coreNav } from './coreNav'
import { fuelStationNav } from '../../../modules/FuelStation/Resources/js/nav'
import { accountingNav } from '../../../modules/Accounting/Resources/js/nav'
import { inventoryNav } from '../../../modules/Inventory/Resources/js/nav'
import { payrollNav } from '../../../modules/Payroll/Resources/js/nav'
import { taxNav } from '../../../modules/Tax/Resources/js/nav'

const moduleNavs: ModuleNavConfig[] = [
  coreNav,
  fuelStationNav,
  accountingNav,
  inventoryNav,
  payrollNav,
  taxNav,
]

export const getSidebarGroups = (context: NavContext): NavGroup[] => {
  const enabled = moduleNavs.filter((module) => module.isEnabled?.(context) ?? true)
  const replacement = enabled.find((module) => module.mode === 'replace')
  if (replacement) {
    return replacement.getNavGroups(context)
  }

  return enabled.flatMap((module) => module.getNavGroups(context))
}
