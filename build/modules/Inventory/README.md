# Inventory Module

Inventory features: items, warehouses, stock movements, and adjustments.

## Module Type
- Helper module (supports other business modules).
- Exposes navigation via `Resources/js/nav.ts` and is aggregated by the host sidebar.

## Module Navigation
- Register entries in `Resources/js/nav.ts`.
- Example:
```ts
import type { ModuleNavConfig } from '@/navigation/types'

export const inventoryNav: ModuleNavConfig = {
  id: 'inventory',
  label: 'Inventory',
  isEnabled: (context) => Boolean(context.slug),
  getNavGroups: (context) => {
    const { slug } = context
    return [
      {
        label: 'Inventory',
        items: [
          { title: 'Items', href: `/${slug}/items` },
        ],
      },
    ]
  },
}
```

## Module Development
- Follow `docs/modules.md` before adding new features.
- Keep all module logic inside this module (migrations, models, controllers, services, routes, views, sidebar).
- Create `permissions.md` and `coa.md` in this module root before implementation.
