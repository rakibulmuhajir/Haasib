import type { ModuleNavConfig } from '@/navigation/types'
import {
  BarChart3,
  Bus,
  FileText,
  LayoutDashboard,
  Plane,
  Settings,
  Users,
} from 'lucide-vue-next'

export const umrahNav: ModuleNavConfig = {
  id: 'umrah',
  label: 'Umrah',
  mode: 'replace',
  isEnabled: (context) => Boolean(context.slug && context.isUmrahCompany),
  getNavGroups: (context) => {
    const { slug } = context
    if (!slug || !context.isUmrahCompany) return []

    return [
      {
        label: 'Umrah Operations',
        items: [
          { title: 'Dashboard', href: `/${slug}/umrah`, icon: LayoutDashboard },
          { title: 'Visa Groups', href: `/${slug}/umrah/groups`, icon: Plane },
          { title: 'Agents', href: `/${slug}/umrah/agents`, icon: Users },
          { title: 'Visa Vendors', href: `/${slug}/umrah/vendors`, icon: FileText },
          {
            title: 'Reports',
            icon: BarChart3,
            children: [
              { title: 'Earnings', href: `/${slug}/umrah/reports/earnings`, icon: BarChart3 },
            ],
          },
          {
            title: 'Settings',
            icon: Settings,
            children: [
              { title: 'Visa Services', href: `/${slug}/umrah/settings/visa-services`, icon: FileText },
              { title: 'Transport Services', href: `/${slug}/umrah/settings/transport-services`, icon: Bus },
              { title: 'Vehicle Types', href: `/${slug}/umrah/settings/vehicle-types`, icon: Bus },
              { title: 'Bank Accounts', href: `/${slug}/banking/accounts`, icon: Settings },
              { title: 'Journal Entries', href: `/${slug}/journals`, icon: FileText },
            ],
          },
        ],
      },
    ]
  },
}
