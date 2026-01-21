import type { ModuleNavConfig } from '@/navigation/types'
import { UserCog, Calendar, FileCheck } from 'lucide-vue-next'

export const payrollNav: ModuleNavConfig = {
  id: 'payroll',
  label: 'Payroll',
  isEnabled: (context) => Boolean(context.slug && context.isPayrollEnabled),
  getNavGroups: (context) => {
    const { slug, t } = context
    if (!slug || !context.isPayrollEnabled) return []

    return [
      {
        label: t('payroll'),
        items: [
          { title: t('employees'), href: `/${slug}/employees`, icon: UserCog },
          { title: t('payrollPeriods'), href: `/${slug}/payroll-periods`, icon: Calendar },
          { title: t('payslips'), href: `/${slug}/payslips`, icon: FileCheck },
        ],
      },
    ]
  },
}
