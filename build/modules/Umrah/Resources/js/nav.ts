import type { ModuleNavConfig } from '@/navigation/types';
import {
    BadgeDollarSign,
    BarChart3,
    Building2,
    Bus,
    FileText,
    Hotel,
    LayoutDashboard,
    Plane,
    ReceiptText,
    ScrollText,
    Settings,
    Users,
    WalletCards,
} from 'lucide-vue-next';

export const umrahNav: ModuleNavConfig = {
    id: 'umrah',
    label: 'Umrah',
    mode: 'replace',
    isEnabled: (context) => Boolean(context.slug && context.isUmrahCompany),
    getNavGroups: (context) => {
        const { slug } = context;
        if (!slug || !context.isUmrahCompany) return [];

        return [
            {
                label: 'Umrah Operations',
                items: [
                    {
                        title: 'Dashboard',
                        href: `/${slug}/umrah`,
                        icon: LayoutDashboard,
                    },
                    {
                        title: 'Trips / Visa Groups',
                        href: `/${slug}/umrah/groups`,
                        icon: Plane,
                    },
                    {
                        title: 'Vouchers',
                        href: `/${slug}/umrah/vouchers`,
                        icon: ScrollText,
                    },
                    {
                        title: 'Payments',
                        href: `/${slug}/umrah/payments`,
                        icon: WalletCards,
                    },
                    ...(context.currentCompanyRole === 'member'
                        ? []
                        : [
                              {
                                  title: 'Expenses',
                                  href: `/${slug}/umrah/expenses`,
                                  icon: ReceiptText,
                              },
                              {
                                  title: 'Salaries',
                                  href: `/${slug}/payroll`,
                                  icon: BadgeDollarSign,
                              },
                          ]),
                    {
                        title: 'Agents',
                        href: `/${slug}/umrah/agents`,
                        icon: Users,
                    },
                    {
                        title: 'Visa Vendors',
                        href: `/${slug}/umrah/vendors`,
                        icon: FileText,
                    },
                    {
                        title: 'Reports',
                        icon: BarChart3,
                        children: [
                            {
                                title: 'Earnings',
                                href: `/${slug}/umrah/reports/earnings`,
                                icon: BarChart3,
                            },
                        ],
                    },
                    {
                        title: 'Settings',
                        icon: Settings,
                        children: [
                            ...(context.currentCompanyRole === 'member'
                                ? []
                                : [
                                      {
                                          title: 'Company & Currencies',
                                          href: `/${slug}/settings`,
                                          icon: Building2,
                                      },
                                  ]),
                            {
                                title: 'Transport Services',
                                href: `/${slug}/umrah/settings/transport-services`,
                                icon: Bus,
                            },
                            {
                                title: 'Drivers',
                                href: `/${slug}/umrah/settings/drivers`,
                                icon: Users,
                            },
                            {
                                title: 'Hotels',
                                href: `/${slug}/umrah/settings/hotels`,
                                icon: Hotel,
                            },
                        ],
                    },
                ],
            },
        ];
    },
};
