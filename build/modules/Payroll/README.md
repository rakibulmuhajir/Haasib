# Payroll Module

Payroll features: employees, earning types, deduction types, payroll periods, payslips, and salary advances.

## Module Type
- Helper module (supports other business modules).
- Exposes navigation via `Resources/js/nav.ts` and is aggregated by the host sidebar.

## Module Navigation
- Register entries in `Resources/js/nav.ts`.
- Example:
```ts
import type { ModuleNavConfig } from '@/navigation/types'

export const payrollNav: ModuleNavConfig = {
  id: 'payroll',
  label: 'Payroll',
  isEnabled: (context) => Boolean(context.slug),
  getNavGroups: (context) => {
    const { slug } = context
    return [
      {
        label: 'Payroll',
        items: [
          { title: 'Employees', href: `/${slug}/employees` },
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
