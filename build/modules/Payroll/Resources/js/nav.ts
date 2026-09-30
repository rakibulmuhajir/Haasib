import type { ModuleNavConfig } from '@/navigation/types';
import { Banknote, UserCog, WalletCards } from 'lucide-vue-next';

// Three places: the people, the month's pay, and advances. Payroll periods, the payslips list and
// the salary report are all the Payroll page now; pay setup lives in Settings > Payroll.
export const payrollNav: ModuleNavConfig = {
    id: 'payroll',
    label: 'Payroll',
    isEnabled: (context) => Boolean(context.slug && context.isPayrollEnabled),
    getNavGroups: (context) => {
        const { slug, t } = context;
        if (!slug || !context.isPayrollEnabled) return [];

        return [
            {
                label: t('payroll'),
                items: [
                    { title: t('employees'), href: `/${slug}/employees`, icon: UserCog },
                    { title: t('payroll'), href: `/${slug}/payroll`, icon: Banknote },
                    { title: 'Advances', href: `/${slug}/salary-advances`, icon: WalletCards },
                ],
            },
        ];
    },
};
