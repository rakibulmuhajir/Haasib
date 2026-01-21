import type { ModuleNavConfig } from '@/navigation/types'
import {
  Package,
  Warehouse,
  FolderTree,
  Layers,
  ArrowLeftRight,
  PackageCheck,
} from 'lucide-vue-next'

export const inventoryNav: ModuleNavConfig = {
  id: 'inventory',
  label: 'Inventory',
  isEnabled: (context) => Boolean(context.slug && context.isInventoryEnabled),
  getNavGroups: (context) => {
    const { slug, t } = context
    if (!slug || !context.isInventoryEnabled) return []

    return [
      {
        label: t('inventory'),
        items: [
          { title: t('items'), href: `/${slug}/items`, icon: Package },
          { title: t('warehouses'), href: `/${slug}/warehouses`, icon: Warehouse },
          { title: t('categories'), href: `/${slug}/item-categories`, icon: FolderTree },
          { title: t('stockLevels'), href: `/${slug}/stock`, icon: Layers },
          { title: t('stockReceipts'), href: `/${slug}/stock/receipts`, icon: PackageCheck },
          { title: t('stockMovements'), href: `/${slug}/stock/movements`, icon: ArrowLeftRight },
        ],
      },
    ]
  },
}
