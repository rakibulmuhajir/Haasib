<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useStorage } from '@vueuse/core'
import { urlIsActive } from '@/lib/utils'
import { 
  Calculator,
  BarChart3,
  Users,
  FileText,
  CreditCard,
  Building2,
  FileBarChart,
  Settings,
  ChevronDown,
  Home,
  TrendingUp,
  Receipt,
  UserCheck,
  Clock,
  Store,
  DollarSign,
  RefreshCw,
  Archive,
  PieChart,
  FileSpreadsheet,
  Wrench
} from 'lucide-vue-next'

import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'

interface Company {
  id: string
  name: string
  logo?: string
}

interface User {
  id: string
  name: string
  email: string
}

interface PageProps {
  user: User
  companies: Company[]
  currentCompany: Company
}

const page = usePage<PageProps>()

// Persistent sidebar state with localStorage
const isCollapsed = useStorage('haasib-sidebar-collapsed', false)
const expandedGroups = useStorage('haasib-sidebar-expanded-groups', new Set<string>())

// Navigation structure following accounting workflow
const navigationItems = [
  {
    title: 'Dashboard',
    icon: Home,
    items: [
      { title: 'Overview', url: '/dashboard', icon: BarChart3 },
      { title: 'Cash Flow', url: '/dashboard/cash-flow', icon: TrendingUp },
      { title: 'P&L Summary', url: '/dashboard/profit-loss', icon: PieChart },
    ]
  },
  {
    title: 'Sales & Receivables',
    icon: Users,
    items: [
      { title: 'Customers', url: '/accounting/customers', icon: Users },
      { title: 'Invoices', url: '/accounting/invoices', icon: FileText },
      { title: 'Payments Received', url: '/payments/received', icon: CreditCard },
      { title: 'Aging Reports', url: '/reports/aging', icon: Clock },
    ]
  },
  {
    title: 'Purchases & Payables',
    icon: Receipt,
    items: [
      { title: 'Vendors', url: '/vendors', icon: Store },
      { title: 'Bills', url: '/bills', icon: Receipt },
      { title: 'Payments Made', url: '/payments/made', icon: DollarSign },
      { title: 'Expenses', url: '/expenses', icon: Archive },
    ]
  },
  {
    title: 'Banking & Cash',
    icon: Building2,
    items: [
      { title: 'Bank Accounts', url: '/bank-accounts', icon: Building2 },
      { title: 'Reconciliation', url: '/bank-reconciliation', icon: UserCheck },
      { title: 'Transfers', url: '/transfers', icon: RefreshCw },
      { title: 'Cash Flow', url: '/cash-flow', icon: TrendingUp },
    ]
  },
  {
    title: 'Financial Reports',
    icon: FileBarChart,
    items: [
      { title: 'P&L Statement', url: '/reports/profit-loss', icon: BarChart3 },
      { title: 'Balance Sheet', url: '/reports/balance-sheet', icon: FileSpreadsheet },
      { title: 'Trial Balance', url: '/reports/trial-balance', icon: Calculator },
      { title: 'Custom Reports', url: '/reports/custom', icon: FileBarChart },
    ]
  },
  {
    title: 'Settings',
    icon: Settings,
    items: [
      { title: 'Chart of Accounts', url: '/settings/chart-of-accounts', icon: Calculator },
      { title: 'Tax Settings', url: '/settings/tax', icon: FileText },
      { title: 'Users & Permissions', url: '/settings/users', icon: Users },
      { title: 'Integrations', url: '/settings/integrations', icon: Wrench },
    ]
  }
]

// Quick action buttons for common tasks
const quickActions = [
  { title: 'New Invoice', url: '/accounting/invoices/create', icon: FileText, color: 'bg-blue-500' },
  { title: 'New Customer', url: '/accounting/customers/create', icon: Users, color: 'bg-green-500' },
  { title: 'Record Payment', url: '/payments/create', icon: CreditCard, color: 'bg-purple-500' },
]

function isActive(url: string): boolean {
  if (!page.url || !url) return false
  return urlIsActive(url, page.url) || page.url.startsWith(url + '/')
}

function hasActiveChild(items: any[]): boolean {
  return items.some(item => isActive(item.url))
}

function toggleGroup(groupTitle: string): void {
  const expanded = new Set(expandedGroups.value)
  if (expanded.has(groupTitle)) {
    expanded.delete(groupTitle)
  } else {
    expanded.add(groupTitle)
  }
  expandedGroups.value = expanded
}

function isGroupExpanded(groupTitle: string): boolean {
  return expandedGroups.value.has(groupTitle)
}

// Auto-expand groups that have active children
onMounted(() => {
  const expanded = new Set(expandedGroups.value)
  navigationItems.forEach(group => {
    if (hasActiveChild(group.items)) {
      expanded.add(group.title)
    }
  })
  expandedGroups.value = expanded
})
</script>

<template>
  <Sidebar collapsible="icon" variant="inset" class="border-r">
    <!-- Header with company switcher -->
    <SidebarHeader class="border-b px-4 py-3">
      <div class="flex items-center gap-3">
        <Avatar class="h-8 w-8">
          <AvatarImage :src="page.props.currentCompany?.logo" />
          <AvatarFallback class="bg-blue-500 text-white text-xs">
            {{ page.props.currentCompany?.name?.charAt(0) || 'H' }}
          </AvatarFallback>
        </Avatar>
        <div class="flex-1 min-w-0">
          <h2 class="font-semibold text-sm truncate">{{ page.props.currentCompany?.name || 'Haasib' }}</h2>
          <p class="text-xs text-muted-foreground truncate">{{ page.props.user?.name }}</p>
        </div>
        <Button variant="ghost" size="icon" class="h-6 w-6 shrink-0">
          <ChevronDown class="h-3 w-3" />
        </Button>
      </div>
      
      <!-- Quick Actions -->
      <div class="mt-3 space-y-1">
        <div class="text-xs font-medium text-muted-foreground mb-2">Quick Actions</div>
        <div class="grid grid-cols-1 gap-1">
          <Button 
            v-for="action in quickActions" 
            :key="action.title"
            variant="outline" 
            size="sm" 
            class="justify-start h-8 text-xs"
            as-child
          >
            <Link :href="action.url">
              <component :is="action.icon" class="h-3 w-3 mr-2" />
              {{ action.title }}
            </Link>
          </Button>
        </div>
      </div>
    </SidebarHeader>

    <!-- Navigation Content -->
    <SidebarContent class="px-2 py-2">
      <SidebarGroup v-for="group in navigationItems" :key="group.title">
        <SidebarGroupLabel 
          class="px-2 text-xs font-medium text-muted-foreground mb-1 cursor-pointer hover:text-foreground transition-colors"
          @click="toggleGroup(group.title)"
        >
          <component :is="group.icon" class="h-4 w-4 mr-2" />
          {{ group.title }}
          <ChevronDown 
            class="ml-auto h-3 w-3 transition-transform duration-200"
            :class="{ 'rotate-180': isGroupExpanded(group.title) }"
          />
        </SidebarGroupLabel>
        <SidebarGroupContent>
          <div 
            class="transition-all duration-300 ease-in-out overflow-hidden"
            :class="{ 'max-h-0': !isGroupExpanded(group.title), 'max-h-96': isGroupExpanded(group.title) }"
          >
            <SidebarMenu>
              <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton 
                  as-child
                  :is-active="isActive(item.url)"
                  class="w-full justify-start transition-all duration-200 hover:translate-x-1"
                >
                  <Link :href="item.url">
                    <component :is="item.icon" class="h-4 w-4 mr-3" />
                    {{ item.title }}
                    <Badge 
                      v-if="item.title === 'Invoices'" 
                      variant="secondary" 
                      class="ml-auto text-xs"
                    >
                      12
                    </Badge>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </div>
        </SidebarGroupContent>
      </SidebarGroup>
    </SidebarContent>

    <!-- Footer -->
    <SidebarFooter class="border-t px-4 py-3">
      <div class="flex items-center gap-2">
        <Avatar class="h-6 w-6">
          <AvatarFallback class="text-xs">{{ page.props.user?.name?.charAt(0) || 'U' }}</AvatarFallback>
        </Avatar>
        <div class="flex-1 min-w-0">
          <p class="text-xs font-medium truncate">{{ page.props.user?.name }}</p>
          <p class="text-xs text-muted-foreground truncate">{{ page.props.user?.email }}</p>
        </div>
      </div>
    </SidebarFooter>
  </Sidebar>
</template>

<style scoped>
/* macOS-style transitions */
.sidebar-enter-active, .sidebar-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
}

.sidebar-enter-from, .sidebar-leave-to {
  opacity: 0;
  transform: translateX(-100%);
}

/* Smooth hover transitions with macOS-style easing */
.transition-all {
  transition: all 0.3s cubic-bezier(0.25, 0.1, 0.25, 1);
}

/* Custom scrollbar for macOS feel */
:deep(.scrollbar-thin) {
  scrollbar-width: thin;
  scrollbar-color: rgba(155, 155, 155, 0.5) transparent;
}

:deep(.scrollbar-thin::-webkit-scrollbar) {
  width: 6px;
}

:deep(.scrollbar-thin::-webkit-scrollbar-track) {
  background: transparent;
}

:deep(.scrollbar-thin::-webkit-scrollbar-thumb) {
  background-color: rgba(155, 155, 155, 0.5);
  border-radius: 20px;
  border: transparent;
}

/* Hover effects for menu items */
:deep([data-sidebar="menu-button"]:hover) {
  background-color: rgba(59, 130, 246, 0.08);
  transform: translateX(2px);
}

:deep([data-sidebar="menu-button"][data-active="true"]) {
  background-color: rgba(59, 130, 246, 0.15);
  border-left: 3px solid #3b82f6;
  font-weight: 500;
}

/* Group label hover effects */
:deep([data-sidebar="group-label"]:hover) {
  background-color: rgba(0, 0, 0, 0.03);
  border-radius: 6px;
}

/* Smooth collapse animation */
:deep([data-sidebar="sidebar"][data-state="collapsed"]) {
  width: 3rem;
}

:deep([data-sidebar="sidebar"][data-state="expanded"]) {
  width: 16rem;
}
</style>
