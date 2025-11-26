<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { 
  Collapsible, 
  CollapsibleContent, 
  CollapsibleTrigger 
} from '@/components/ui/collapsible'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { 
  ChevronDown, 
  ChevronRight,
  Home,
  Users,
  FileText,
  CreditCard,
  DollarSign,
  TrendingUp,
  Package,
  Building,
  Settings,
  Calculator,
  Receipt,
  PieChart,
  Calendar,
  Banknote,
  Wallet,
  ShoppingCart,
  Truck,
  UserCheck,
  FileCheck,
  BarChart3,
  Globe
} from 'lucide-vue-next'

interface MenuItem {
  id: string
  label: string
  icon?: any
  href?: string
  badge?: string | number
  submenu?: MenuItem[]
  isOpen?: boolean
}

const page = usePage()
const openMenus = ref<Set<string>>(new Set(['dashboard']))

const currentPath = computed(() => {
  const path = (page.url as string | undefined) || (typeof window !== 'undefined' ? window.location.pathname : '/')
  return (path || '/').split('?')[0] || '/'
})

const menuItems = ref<MenuItem[]>([
  {
    id: 'dashboard',
    label: 'Dashboard',
    icon: Home,
    href: '/dashboard'
  },
  {
    id: 'sales',
    label: 'Sales',
    icon: TrendingUp,
    submenu: [
      { id: 'customers', label: 'Customers', icon: Users, href: '/customers' },
      { id: 'invoices', label: 'Invoices', icon: FileText, href: '/invoices', badge: '12' },
      { id: 'quotes', label: 'Quotes', icon: FileCheck, href: '/quotes' },
      { id: 'sales-orders', label: 'Sales Orders', icon: ShoppingCart, href: '/sales-orders' },
      { id: 'delivery-notes', label: 'Delivery Notes', icon: Truck, href: '/delivery-notes' },
      { id: 'credit-notes', label: 'Credit Notes', icon: Receipt, href: '/credit-notes' },
    ]
  },
  {
    id: 'purchases',
    label: 'Purchases',
    icon: Package,
    submenu: [
      { id: 'vendors', label: 'Vendors', icon: Building, href: '/vendors' },
      { id: 'bills', label: 'Bills', icon: FileText, href: '/bills', badge: 'New' },
      { id: 'purchase-orders', label: 'Purchase Orders', icon: ShoppingCart, href: '/purchase-orders' },
      { id: 'vendor-credits', label: 'Vendor Credits', icon: CreditCard, href: '/vendor-credits' },
      { id: 'expenses', label: 'Expenses', icon: Receipt, href: '/expenses' },
    ]
  },
  {
    id: 'banking',
    label: 'Banking',
    icon: Banknote,
    submenu: [
      { id: 'bank-accounts', label: 'Bank Accounts', icon: Wallet, href: '/bank-accounts' },
      { id: 'deposits', label: 'Deposits', icon: DollarSign, href: '/deposits' },
      { id: 'transfers', label: 'Transfers', icon: CreditCard, href: '/transfers' },
      { id: 'reconciliation', label: 'Reconciliation', icon: UserCheck, href: '/reconciliation', badge: '3' },
    ]
  },
  {
    id: 'accounting',
    label: 'Accounting',
    icon: Calculator,
    submenu: [
      { id: 'chart-of-accounts', label: 'Chart of Accounts', icon: BarChart3, href: '/chart-of-accounts' },
      { id: 'journal-entries', label: 'Journal Entries', icon: FileText, href: '/journal-entries' },
      { id: 'trial-balance', label: 'Trial Balance', icon: PieChart, href: '/trial-balance' },
      { id: 'closing-entries', label: 'Closing Entries', icon: Calendar, href: '/closing-entries' },
    ]
  },
  {
    id: 'reports',
    label: 'Reports',
    icon: BarChart3,
    submenu: [
      { id: 'financial-statements', label: 'Financial Statements', icon: FileText, href: '/reports/financial-statements' },
      { id: 'profit-loss', label: 'Profit & Loss', icon: TrendingUp, href: '/reports/profit-loss' },
      { id: 'balance-sheet', label: 'Balance Sheet', icon: PieChart, href: '/reports/balance-sheet' },
      { id: 'cash-flow', label: 'Cash Flow', icon: DollarSign, href: '/reports/cash-flow' },
      { id: 'aged-receivables', label: 'Aged Receivables', icon: Users, href: '/reports/aged-receivables' },
      { id: 'aged-payables', label: 'Aged Payables', icon: Building, href: '/reports/aged-payables' },
    ]
  },
  {
    id: 'inventory',
    label: 'Inventory',
    icon: Package,
    submenu: [
      { id: 'items', label: 'Items', icon: Package, href: '/inventory/items' },
      { id: 'adjustments', label: 'Adjustments', icon: Calculator, href: '/inventory/adjustments' },
      { id: 'transfers', label: 'Transfers', icon: Truck, href: '/inventory/transfers' },
      { id: 'assemblies', label: 'Assemblies', icon: Settings, href: '/inventory/assemblies' },
    ]
  },
  {
    id: 'payroll',
    label: 'Payroll',
    icon: Users,
    submenu: [
      { id: 'employees', label: 'Employees', icon: Users, href: '/payroll/employees' },
      { id: 'payruns', label: 'Pay Runs', icon: Calendar, href: '/payroll/payruns' },
      { id: 'timesheets', label: 'Timesheets', icon: Calendar, href: '/payroll/timesheets' },
      { id: 'payroll-reports', label: 'Payroll Reports', icon: BarChart3, href: '/payroll/reports' },
    ]
  }
])

const settingsItems = ref<MenuItem[]>([
  { id: 'company-settings', label: 'Company Settings', icon: Building, href: '/settings/company' },
  { id: 'currencies', label: 'Currencies', icon: Globe, href: '/settings/currencies' },
  { id: 'tax-settings', label: 'Tax Settings', icon: Calculator, href: '/settings/taxes' },
  { id: 'integrations', label: 'Integrations', icon: Settings, href: '/settings/integrations' },
])

// Auto-expand settings when currency settings is active
const shouldExpandSettings = computed(() => {
  return settingsItems.value.some(item => isActive(item.href || ''))
})

const toggleMenu = (menuId: string) => {
  const newOpenMenus = new Set(openMenus.value)
  if (newOpenMenus.has(menuId)) {
    newOpenMenus.delete(menuId)
  } else {
    newOpenMenus.add(menuId)
  }
  openMenus.value = newOpenMenus
}

const isMenuOpen = (menuId: string) => {
  return openMenus.value.has(menuId)
}

const isActive = (href: string) => {
  if (!href) return false
  return currentPath.value === href || currentPath.value.startsWith(href + '/')
}

const hasActiveSubmenu = (submenu: MenuItem[] = []) => {
  return submenu.some(item => isActive(item.href || ''))
}
</script>

<template>
  <aside class="fixed left-0 top-0 h-screen w-64 flex-col border-r bg-background z-50 flex">
    <!-- Header -->
    <div class="flex h-14 items-center border-b px-4">
      <div class="flex items-center gap-2">
        <div class="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
          <Calculator class="h-4 w-4" />
        </div>
        <span class="font-semibold">Haasib Accounting</span>
      </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 space-y-1 p-4">
      <!-- Main Menu Items -->
      <div class="space-y-1">
        <template v-for="item in menuItems" :key="item.id">
          <!-- Items with submenus -->
          <Collapsible v-if="item.submenu" :open="isMenuOpen(item.id)">
            <CollapsibleTrigger as-child>
              <Button
                variant="ghost"
                class="w-full justify-between px-2 py-2 h-auto"
                :class="{
                  'bg-accent text-accent-foreground': hasActiveSubmenu(item.submenu),
                  'hover:bg-accent hover:text-accent-foreground': !hasActiveSubmenu(item.submenu)
                }"
                @click="toggleMenu(item.id)"
              >
                <div class="flex items-center gap-3">
                  <component :is="item.icon" class="h-4 w-4 shrink-0" />
                  <span class="text-sm font-medium">{{ item.label }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Badge v-if="item.badge" variant="secondary" class="text-xs">
                    {{ item.badge }}
                  </Badge>
                  <ChevronRight 
                    class="h-4 w-4 transition-transform duration-200"
                    :class="{ 'rotate-90': isMenuOpen(item.id) }"
                  />
                </div>
              </Button>
            </CollapsibleTrigger>
            <CollapsibleContent class="space-y-1 pl-4 pt-1">
              <template v-for="subitem in item.submenu" :key="subitem.id">
                <Button
                  variant="ghost"
                  class="w-full justify-start px-2 py-1.5 h-auto text-left"
                  :class="{
                    'bg-accent text-accent-foreground': isActive(subitem.href || ''),
                    'hover:bg-accent hover:text-accent-foreground': !isActive(subitem.href || '')
                  }"
                  @click="router.visit(subitem.href || '#')"
                >
                  <div class="flex items-center gap-3 w-full">
                    <component :is="subitem.icon" class="h-3 w-3 shrink-0" />
                    <span class="text-sm">{{ subitem.label }}</span>
                    <Badge v-if="subitem.badge" variant="secondary" class="ml-auto text-xs">
                      {{ subitem.badge }}
                    </Badge>
                  </div>
                </Button>
              </template>
            </CollapsibleContent>
          </Collapsible>

          <!-- Items without submenus -->
          <Button
            v-else
            variant="ghost"
            class="w-full justify-start px-2 py-2 h-auto"
            :class="{
              'bg-accent text-accent-foreground': isActive(item.href || ''),
              'hover:bg-accent hover:text-accent-foreground': !isActive(item.href || '')
            }"
            @click="router.visit(item.href || '#')"
          >
            <div class="flex items-center gap-3">
              <component :is="item.icon" class="h-4 w-4 shrink-0" />
              <span class="text-sm font-medium">{{ item.label }}</span>
              <Badge v-if="item.badge" variant="secondary" class="ml-auto text-xs">
                {{ item.badge }}
              </Badge>
            </div>
          </Button>
        </template>
      </div>

      <Separator class="my-4" />

      <!-- Settings Section -->
      <div class="space-y-1">
        <Collapsible :open="shouldExpandSettings">
          <CollapsibleTrigger as-child @click="() => {}">
            <div class="flex items-center justify-between px-2 py-2 cursor-pointer hover:bg-accent/50 rounded">
              <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Settings</span>
              <ChevronRight 
                class="h-4 w-4 transition-transform duration-200"
                :class="{ 'rotate-90': shouldExpandSettings }"
              />
            </div>
          </CollapsibleTrigger>
          <CollapsibleContent class="space-y-1 pl-4 pt-1">
            <template v-for="item in settingsItems" :key="item.id">
              <Button
                variant="ghost"
                class="w-full justify-start px-2 py-1.5 h-auto text-left"
                :class="{
                  'bg-accent text-accent-foreground': isActive(item.href || ''),
                  'hover:bg-accent hover:text-accent-foreground': !isActive(item.href || '')
                }"
                @click="router.visit(item.href || '#')"
              >
                <div class="flex items-center gap-3 w-full">
                  <component :is="item.icon" class="h-3 w-3 shrink-0" />
                  <span class="text-sm">{{ item.label }}</span>
                </div>
              </Button>
            </template>
          </CollapsibleContent>
        </Collapsible>
      </div>
    </nav>

    <!-- Footer -->
    <div class="border-t p-4">
      <Button variant="outline" class="w-full justify-start" size="sm">
        <Settings class="h-4 w-4 mr-2" />
        Account Settings
      </Button>
    </div>
  </aside>
</template>