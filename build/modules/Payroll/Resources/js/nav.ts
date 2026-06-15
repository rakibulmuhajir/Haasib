import type { ModuleNavConfig } from '@/navigation/types'
import { LayoutDashboard, UserCog, Calendar, FileCheck, WalletCards } from 'lucide-vue-next'

export const payrollNav: ModuleNavConfig = {
  id: 'payroll',
  label: 'Payroll',
  isEnabled: (context) => Boolean(context.slug),
  getNavGroups: (context) => {
    const { slug, t } = context
    if (!slug) return []

    return [
      {
        label: t('payroll'),
        items: [
          { title: 'Payroll Overview', href: `/${slug}/payroll`, icon: LayoutDashboard },
          { title: t('employees'), href: `/${slug}/employees`, icon: UserCog },
          { title: t('payrollPeriods'), href: `/${slug}/payroll-periods`, icon: Calendar },
          { title: t('payslips'), href: `/${slug}/payslips`, icon: FileCheck },
          { title: 'Salary Advances', href: `/${slug}/salary-advances`, icon: WalletCards },
        ],
      },
    ]
  },
}
