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
                    {
                        title: 'Reports',
                        icon: BarChart3,
                        children:
                            context.currentCompanyRole === 'agent'
                                ? [
                                      {
                                          title: 'My Statement',
                                          href: `/${slug}/umrah/reports/agent-statement`,
                                          icon: FileText,
                                      },
                                      {
                                          title: 'Passenger Status',
                                          href: `/${slug}/umrah/reports/passenger-status`,
                                          icon: Users,
                                      },
                                      {
                                          title: 'Departure Manifest',
                                          href: `/${slug}/umrah/reports/departure-manifest`,
                                          icon: Plane,
                                      },
                                      {
                                          title: 'Hotel Rooming',
                                          href: `/${slug}/umrah/reports/hotel-rooming`,
                                          icon: Hotel,
                                      },
                                      {
                                          title: 'Voucher Control',
                                          href: `/${slug}/umrah/reports/voucher-control`,
                                          icon: ScrollText,
                                      },
                                  ]
                                : [
                                      {
                                          title: 'Group Profitability',
                                          href: `/${slug}/umrah/reports/group-profitability`,
                                          icon: BarChart3,
                                      },
                                      {
                                          title: 'Agent Statement',
                                          href: `/${slug}/umrah/reports/agent-statement`,
                                          icon: FileText,
                                      },
                                      {
                                          title: 'Receivable Aging',
                                          href: `/${slug}/umrah/reports/receivable-aging`,
                                          icon: WalletCards,
                                      },
                                      {
                                          title: 'Vendor Payables',
                                          href: `/${slug}/umrah/reports/vendor-aging`,
                                          icon: ReceiptText,
                                      },
                                      {
                                          title: 'Advances',
                                          href: `/${slug}/umrah/reports/advances`,
                                          icon: BadgeDollarSign,
                                      },
                                      {
                                          title: 'Passenger Status',
                                          href: `/${slug}/umrah/reports/passenger-status`,
                                          icon: Users,
                                      },
                                      {
                                          title: 'Departure Manifest',
                                          href: `/${slug}/umrah/reports/departure-manifest`,
                                          icon: Plane,
                                      },
                                      {
                                          title: 'Hotel Rooming',
                                          href: `/${slug}/umrah/reports/hotel-rooming`,
                                          icon: Hotel,
                                      },
                                      {
                                          title: 'Transport Dispatch',
                                          href: `/${slug}/umrah/reports/transport-dispatch`,
                                          icon: Bus,
                                      },
                                      {
                                          title: 'Voucher Control',
                                          href: `/${slug}/umrah/reports/voucher-control`,
                                          icon: ScrollText,
                                      },
                                  ],
                    },
                    ...(context.currentCompanyRole === 'agent'
                        ? []
                        : [
                              {
                                  title: 'Expenses',
                                  href: `/${slug}/umrah/expenses`,
                                  icon: ReceiptText,
                              },
                              ...(context.isPayrollEnabled
                                  ? [
                                        {
                                            title: 'Salaries',
                                            href: `/${slug}/payroll`,
                                            icon: BadgeDollarSign,
                                        },
                                    ]
                                  : []),
                          ]),
                    ...(context.currentCompanyRole === 'agent'
                        ? []
                        : [
                              {
                                  title: 'Agents',
                                  href: `/${slug}/umrah/agents`,
                                  icon: Users,
                              },
                              {
                                  title: 'Visa & Transport Vendors',
                                  href: `/${slug}/umrah/vendors`,
                                  icon: FileText,
                              },
                              ...(['owner', 'admin', 'super_admin'].includes(
                                  String(context.currentCompanyRole),
                              )
                                  ? [
                                        {
                                            title: 'Settings',
                                            icon: Settings,
                                            children: [
                                                {
                                                    title: 'Company & Currencies',
                                                    href: `/${slug}/settings`,
                                                    icon: Building2,
                                                },
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
                                    ]
                                  : []),
                          ]),
                ],
            },
        ];
    },
};
