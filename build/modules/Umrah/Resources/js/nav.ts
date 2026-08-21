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
    Undo2,
    Users,
    WalletCards,
} from 'lucide-vue-next';

/**
 * Umrah navigation.
 *
 * These groups are top-level entries, not headings inside one list. That
 * distinction did not matter while the shell was a sidebar, where a group
 * rendered as a caption above rows that were all visible at once. In the
 * header a group renders as a single dropdown trigger, so the one
 * "Umrah Operations" group this file used to declare collapsed the entire
 * module into one menu with everything stuffed inside it, while Accounting —
 * which declares Sales, Purchases and Banking separately — kept three.
 *
 * The items below are unchanged: same titles, same hrefs, same icons, same
 * role gates, same order. Only the grouping the shell lays them out with.
 */
export const umrahNav: ModuleNavConfig = {
    id: 'umrah',
    label: 'Umrah',
    mode: 'replace',
    isEnabled: (context) => Boolean(context.slug && context.isUmrahCompany),
    getNavGroups: (context) => {
        const { slug } = context;
        if (!slug || !context.isUmrahCompany) return [];

        const role = String(context.currentCompanyRole);
        const isAgent = role === 'agent';
        const isOperations = role === 'operations';
        const isBackOffice = !isAgent && !isOperations;

        const reportItems = isAgent
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
              ];

        const moneyItems = [
            ...(isOperations
                ? []
                : [
                      {
                          title: 'Payments',
                          href: `/${slug}/umrah/payments`,
                          icon: WalletCards,
                      },
                  ]),
            {
                title: 'Refunds',
                href: `/${slug}/umrah/refunds`,
                icon: Undo2,
            },
            ...(isBackOffice
                ? [
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
                  ]
                : []),
        ];

        const directoryItems = isBackOffice
            ? [
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
                      title: 'Transport Vendors',
                      href: `/${slug}/umrah/transport-providers`,
                      icon: Bus,
                  },
              ]
            : [];

        const settingsItems = ['owner', 'manager', 'super_admin'].includes(role)
            ? [
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
              ]
            : [];

        const groups = [
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
                ],
            },
            { label: 'Money', items: moneyItems },
            // Reports were a nested submenu under the single group. A submenu
            // inside a header dropdown is a menu two levels down; as its own
            // entry the same links are one click away.
            { label: 'Reports', items: isOperations ? [] : reportItems },
            { label: 'Directory', items: directoryItems },
            { label: 'Settings', items: settingsItems },
        ];

        // A role that cannot see anything in a group must not see the group.
        return groups.filter((group) => group.items.length > 0);
    },
};
