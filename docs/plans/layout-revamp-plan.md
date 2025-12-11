# Layout Revamp Plan

## Current State Analysis

### Existing Structure
```
AppLayout.vue → AppSidebarLayout.vue → SidebarProvider
  ├── AppSidebar.vue (flat navigation, no sub-menus)
  │   ├── CompanySwitcher
  │   ├── NavMain (flat items only)
  │   ├── NavSecondary
  │   └── NavUser
  └── SidebarInset
      ├── DashboardHeader
      └── Content slot
```

### Pain Points
1. **Flat navigation** - No parent/child menu support
2. **Monotone colors** - Gray-only palette, lacks visual hierarchy
3. **Hard-coded colors** - Mix of CSS variables and inline Tailwind classes
4. **No footer** - Missing application-level footer
5. **Limited text differentiation** - Same text colors everywhere

---

## Phase 1: Enhanced Theme System

### 1.1 Extended Color Palette

Add semantic text colors and accent variations to `app.css`:

```css
:root {
  /* Existing colors... */

  /* Text hierarchy */
  --text-primary: hsl(0 0% 9%);           /* Main headings */
  --text-secondary: hsl(0 0% 25%);        /* Body text */
  --text-tertiary: hsl(0 0% 45%);         /* Muted/secondary info */
  --text-quaternary: hsl(0 0% 60%);       /* Placeholder/disabled */

  /* Accent colors for visual interest */
  --accent-blue: hsl(217 91% 60%);
  --accent-blue-foreground: hsl(0 0% 100%);
  --accent-green: hsl(142 76% 36%);
  --accent-green-foreground: hsl(0 0% 100%);
  --accent-amber: hsl(38 92% 50%);
  --accent-amber-foreground: hsl(0 0% 9%);
  --accent-purple: hsl(262 83% 58%);
  --accent-purple-foreground: hsl(0 0% 100%);

  /* Navigation specific */
  --nav-section-text: hsl(0 0% 45%);
  --nav-item-text: hsl(0 0% 35%);
  --nav-item-text-active: hsl(0 0% 9%);
  --nav-item-text-hover: hsl(0 0% 15%);

  /* Footer */
  --footer-background: hsl(0 0% 98%);
  --footer-text: hsl(0 0% 45%);
  --footer-border: hsl(0 0% 92%);
}

.dark {
  /* Text hierarchy (inverted) */
  --text-primary: hsl(0 0% 98%);
  --text-secondary: hsl(0 0% 80%);
  --text-tertiary: hsl(0 0% 55%);
  --text-quaternary: hsl(0 0% 40%);

  /* Accent colors (slightly brighter for dark) */
  --accent-blue: hsl(217 91% 65%);
  --accent-green: hsl(142 70% 45%);
  --accent-amber: hsl(38 92% 55%);
  --accent-purple: hsl(262 83% 68%);

  /* Navigation specific */
  --nav-section-text: hsl(0 0% 55%);
  --nav-item-text: hsl(0 0% 70%);
  --nav-item-text-active: hsl(0 0% 98%);
  --nav-item-text-hover: hsl(0 0% 90%);

  /* Footer */
  --footer-background: hsl(0 0% 5%);
  --footer-text: hsl(0 0% 55%);
  --footer-border: hsl(0 0% 15%);
}
```

### 1.2 Tailwind Theme Extension

Add to `@theme inline` block in `app.css`:

```css
@theme inline {
  /* Text colors */
  --color-text-primary: var(--text-primary);
  --color-text-secondary: var(--text-secondary);
  --color-text-tertiary: var(--text-tertiary);
  --color-text-quaternary: var(--text-quaternary);

  /* Accent colors */
  --color-accent-blue: var(--accent-blue);
  --color-accent-blue-foreground: var(--accent-blue-foreground);
  --color-accent-green: var(--accent-green);
  --color-accent-green-foreground: var(--accent-green-foreground);
  --color-accent-amber: var(--accent-amber);
  --color-accent-amber-foreground: var(--accent-amber-foreground);
  --color-accent-purple: var(--accent-purple);
  --color-accent-purple-foreground: var(--accent-purple-foreground);

  /* Navigation */
  --color-nav-section-text: var(--nav-section-text);
  --color-nav-item-text: var(--nav-item-text);
  --color-nav-item-text-active: var(--nav-item-text-active);
  --color-nav-item-text-hover: var(--nav-item-text-hover);

  /* Footer */
  --color-footer: var(--footer-background);
  --color-footer-foreground: var(--footer-text);
  --color-footer-border: var(--footer-border);
}
```

---

## Phase 2: Navigation with Parent/Child Menus

### 2.1 Enhanced Type Definitions

Update `types/index.d.ts`:

```typescript
export interface NavItem {
  title: string;
  href?: NonNullable<InertiaLinkProps['href']>;  // Optional for parent menus
  icon?: LucideIcon;
  isActive?: boolean;
  external?: boolean;
  badge?: string | number;                        // NEW: Badge support
  children?: NavItem[];                           // NEW: Sub-menu items
}

export interface NavGroup {
  label: string;
  collapsible?: boolean;                          // NEW: Group collapsibility
  items: NavItem[];
}
```

### 2.2 New NavMainCollapsible Component

Create `components/NavMainCollapsible.vue`:

```vue
<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ChevronRight } from 'lucide-vue-next'
import { Link, usePage } from '@inertiajs/vue3'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubItem,
  SidebarMenuSubButton,
} from '@/components/ui/sidebar'
import { urlIsActive } from '@/lib/utils'
import type { NavItem, NavGroup } from '@/types'

const props = defineProps<{
  groups: NavGroup[]
}>()

const page = usePage()

// Track open state for collapsible parents
const openItems = ref<Record<string, boolean>>({})

// Auto-expand parent if child is active
function isChildActive(item: NavItem): boolean {
  if (!item.children) return false
  return item.children.some(child =>
    child.href && urlIsActive(child.href, page.url)
  )
}

// Initialize open state based on active children
watch(() => page.url, () => {
  props.groups.forEach(group => {
    group.items.forEach(item => {
      if (item.children && isChildActive(item)) {
        openItems.value[item.title] = true
      }
    })
  })
}, { immediate: true })

function toggleItem(title: string) {
  openItems.value[title] = !openItems.value[title]
}
</script>

<template>
  <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-0">
    <SidebarGroupLabel class="text-nav-section-text text-xs uppercase tracking-wider font-medium">
      {{ group.label }}
    </SidebarGroupLabel>

    <SidebarMenu>
      <template v-for="item in group.items" :key="item.title">
        <!-- Parent with children -->
        <Collapsible
          v-if="item.children && item.children.length > 0"
          v-model:open="openItems[item.title]"
          as-child
        >
          <SidebarMenuItem>
            <CollapsibleTrigger as-child>
              <SidebarMenuButton
                :tooltip="item.title"
                class="text-nav-item-text hover:text-nav-item-text-hover"
                :class="{ 'text-nav-item-text-active font-medium': isChildActive(item) }"
              >
                <component :is="item.icon" v-if="item.icon" />
                <span>{{ item.title }}</span>
                <ChevronRight
                  class="ml-auto size-4 transition-transform duration-200"
                  :class="{ 'rotate-90': openItems[item.title] }"
                />
              </SidebarMenuButton>
            </CollapsibleTrigger>

            <CollapsibleContent>
              <SidebarMenuSub>
                <SidebarMenuSubItem v-for="child in item.children" :key="child.title">
                  <SidebarMenuSubButton
                    as-child
                    :is-active="child.href && urlIsActive(child.href, page.url)"
                    class="text-nav-item-text hover:text-nav-item-text-hover"
                  >
                    <Link v-if="child.href && !child.external" :href="child.href">
                      <component :is="child.icon" v-if="child.icon" />
                      <span>{{ child.title }}</span>
                    </Link>
                    <a
                      v-else-if="child.href && child.external"
                      :href="child.href"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <component :is="child.icon" v-if="child.icon" />
                      <span>{{ child.title }}</span>
                    </a>
                  </SidebarMenuSubButton>
                </SidebarMenuSubItem>
              </SidebarMenuSub>
            </CollapsibleContent>
          </SidebarMenuItem>
        </Collapsible>

        <!-- Standalone item (no children) -->
        <SidebarMenuItem v-else>
          <SidebarMenuButton
            as-child
            :is-active="item.href && urlIsActive(item.href, page.url)"
            :tooltip="item.title"
            class="text-nav-item-text hover:text-nav-item-text-hover data-[active=true]:text-nav-item-text-active"
          >
            <Link v-if="item.href && !item.external" :href="item.href">
              <component :is="item.icon" v-if="item.icon" />
              <span>{{ item.title }}</span>
            </Link>
            <a
              v-else-if="item.href && item.external"
              :href="item.href"
              target="_blank"
              rel="noopener noreferrer"
            >
              <component :is="item.icon" v-if="item.icon" />
              <span>{{ item.title }}</span>
            </a>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </template>
    </SidebarMenu>
  </SidebarGroup>
</template>
```

### 2.3 Updated Navigation Structure

Update `AppSidebar.vue` with grouped navigation:

```typescript
const navGroups = computed<NavGroup[]>(() => {
  const slug = currentCompany.value?.slug || slugFromUrl.value

  const groups: NavGroup[] = [
    {
      label: 'Overview',
      items: [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Company', href: slug ? `/${slug}` : undefined, icon: Building2 },
      ]
    }
  ]

  if (slug) {
    groups.push(
      {
        label: 'Sales',
        items: [
          { title: 'Customers', href: `/${slug}/customers`, icon: Users },
          {
            title: 'Receivables',
            icon: FileText,
            children: [
              { title: 'Invoices', href: `/${slug}/invoices`, icon: FileText },
              { title: 'Payments', href: `/${slug}/payments`, icon: DollarSign },
              { title: 'Credit Notes', href: `/${slug}/credit-notes`, icon: Receipt },
            ]
          },
        ]
      },
      {
        label: 'Purchases',
        items: [
          { title: 'Vendors', href: `/${slug}/vendors`, icon: Truck },
          {
            title: 'Payables',
            icon: ReceiptText,
            children: [
              { title: 'Bills', href: `/${slug}/bills`, icon: ReceiptText },
              { title: 'Bill Payments', href: `/${slug}/bill-payments`, icon: Banknote },
              { title: 'Vendor Credits', href: `/${slug}/vendor-credits`, icon: Receipt },
            ]
          },
        ]
      },
      {
        label: 'Accounting',
        items: [
          { title: 'Chart of Accounts', href: `/${slug}/accounts`, icon: BookOpen },
          { title: 'Journal Entries', href: `/${slug}/journals`, icon: FileText },
        ]
      }
    )
  }

  groups.push({
    label: 'Settings',
    items: [
      { title: 'Companies', href: '/companies', icon: Building2 },
    ]
  })

  return groups
})
```

---

## Phase 3: Enhanced Header

### 3.1 Updated DashboardHeader

Enhance with better styling and new features:

```vue
<template>
  <header
    class="flex h-(--header-height) shrink-0 items-center gap-2 border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-(--header-height)"
  >
    <div class="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
      <SidebarTrigger class="-ml-1 text-text-secondary hover:text-text-primary" />

      <template v-if="breadcrumbs && breadcrumbs.length > 0">
        <Separator orientation="vertical" class="mx-2 data-[orientation=vertical]:h-4" />
        <Breadcrumbs :breadcrumbs="breadcrumbs" />
      </template>

      <template v-if="title">
        <Separator v-if="!breadcrumbs || breadcrumbs.length === 0" orientation="vertical" class="mx-2 data-[orientation=vertical]:h-4" />
        <h1 class="text-base font-medium text-text-primary">{{ title }}</h1>
      </template>

      <!-- Spacer -->
      <div class="flex-1" />

      <!-- Actions slot -->
      <div v-if="actions.length > 0 || $slots.actions" class="flex items-center gap-2">
        <slot name="actions">
          <!-- Action buttons... -->
        </slot>
      </div>
    </div>
  </header>
</template>
```

---

## Phase 4: Application Footer

### 4.1 New AppFooter Component

Create `components/AppFooter.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const currentYear = new Date().getFullYear()
const appName = computed(() => (page.props as any).name || 'Haasib')
</script>

<template>
  <footer class="border-t border-footer-border bg-footer py-3 px-4 lg:px-6">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-footer-foreground">
      <div class="flex items-center gap-1">
        <span>&copy; {{ currentYear }} {{ appName }}.</span>
        <span class="hidden sm:inline">All rights reserved.</span>
      </div>

      <div class="flex items-center gap-4">
        <slot name="links">
          <a href="#" class="hover:text-text-secondary transition-colors">Privacy</a>
          <a href="#" class="hover:text-text-secondary transition-colors">Terms</a>
          <a href="#" class="hover:text-text-secondary transition-colors">Help</a>
        </slot>
      </div>
    </div>
  </footer>
</template>
```

---

## Phase 5: Unified Layout Structure

### 5.1 Updated AppSidebarLayout

Integrate footer into main layout:

```vue
<template>
  <SidebarProvider
    :default-open="defaultOpen"
    :open="open"
    @update:open="$emit('update:open', $event)"
  >
    <AppSidebar :variant="variant" :collapsible="collapsible" />

    <SidebarInset class="flex flex-col min-h-screen">
      <!-- Header slot -->
      <slot name="header" />

      <!-- Main content area -->
      <main class="flex-1 p-4 lg:p-6">
        <slot />
      </main>

      <!-- Footer -->
      <AppFooter>
        <template #links>
          <slot name="footer-links" />
        </template>
      </AppFooter>
    </SidebarInset>
  </SidebarProvider>
</template>
```

---

## Phase 6: Visual Polish

### 6.1 Sidebar Visual Improvements

- Add subtle gradient to sidebar background
- Improve hover/active states with accent color hints
- Add micro-animations for expand/collapse

### 6.2 Typography Consistency

Apply text color classes consistently:

| Element | Light Mode Class | Dark Mode Auto |
|---------|-----------------|----------------|
| Page titles | `text-text-primary` | Yes |
| Body text | `text-text-secondary` | Yes |
| Labels/captions | `text-text-tertiary` | Yes |
| Placeholders | `text-text-quaternary` | Yes |
| Nav section labels | `text-nav-section-text` | Yes |
| Nav items | `text-nav-item-text` | Yes |

---

## Implementation Order

1. **Phase 1**: Theme system (CSS variables) - Foundation
2. **Phase 2**: Navigation with sub-menus - Core feature
3. **Phase 3**: Enhanced header - Polish
4. **Phase 4**: Footer component - Completion
5. **Phase 5**: Layout integration - Tie together
6. **Phase 6**: Visual polish - Refinement

---

## Files to Create/Modify

### New Files
- `components/NavMainCollapsible.vue`
- `components/AppFooter.vue`

### Modified Files
- `resources/css/app.css` - Theme variables
- `types/index.d.ts` - Navigation types
- `components/AppSidebar.vue` - Navigation structure
- `components/DashboardHeader.vue` - Styling
- `layouts/app/AppSidebarLayout.vue` - Footer integration

---

## Testing Checklist

- [ ] Light mode colors render correctly
- [ ] Dark mode colors render correctly
- [ ] Parent menus expand/collapse
- [ ] Child menu active state highlights parent
- [ ] Collapsed sidebar shows tooltips
- [ ] Mobile responsive behavior works
- [ ] Footer displays on all pages
- [ ] Text hierarchy is visually distinct
