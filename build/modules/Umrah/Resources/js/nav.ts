import type { ModuleNavConfig } from '@/navigation/types'
import {
  BarChart3,
  Bus,
  FileText,
  Hotel,
  ScrollText,
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
          { title: 'Trips / Visa Groups', href: `/${slug}/umrah/groups`, icon: Plane },
          { title: 'Vouchers', href: `/${slug}/umrah/vouchers`, icon: ScrollText },
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
              { title: 'Transport Services', href: `/${slug}/umrah/settings/transport-services`, icon: Bus },
              { title: 'Drivers', href: `/${slug}/umrah/settings/drivers`, icon: Users },
              { title: 'Hotels', href: `/${slug}/umrah/settings/hotels`, icon: Hotel },
            ],
          },
        ],
      },
    ]
  },
}
