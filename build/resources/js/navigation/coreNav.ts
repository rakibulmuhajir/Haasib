import { LayoutGrid, Settings } from 'lucide-vue-next'
import { dashboard } from '@/routes'
import type { ModuleNavConfig } from './types'

export const coreNav: ModuleNavConfig = {
  id: 'core',
  label: 'Core',
  getNavGroups: (context) => {
    const { slug, t } = context

    if (!slug) {
      return [
        {
          label: 'Overview',
          items: [
            { title: t('dashboard'), href: dashboard(), icon: LayoutGrid },
          ],
        },
        {
          label: 'Admin',
          items: [
            { title: 'Companies', href: '/companies', icon: Settings },
          ],
        },
      ]
    }

    return [
      {
        label: 'Overview',
        items: [
          { title: t('dashboard'), href: `/${slug}`, icon: LayoutGrid },
        ],
      },
    ]
  },
}
