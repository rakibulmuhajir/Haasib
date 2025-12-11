<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import CompanySwitcher from '@/components/CompanySwitcher.vue'
import NavMainCollapsible from '@/components/NavMainCollapsible.vue'
import NavUser from '@/components/NavUser.vue'
import { useAppearance } from '@/composables/useAppearance'
import { Button } from '@/components/ui/button'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
} from '@/components/ui/sidebar'
import { dashboard } from '@/routes'
import type { NavGroup } from '@/types'
import { usePage } from '@inertiajs/vue3'
import {
  LayoutGrid,
  Building2,
  FileText,
  BookOpen,
  Users,
  DollarSign,
  Receipt,
  Banknote,
  ReceiptText,
  Truck,
  Settings,
  CircleDollarSign,
  CreditCard,
  Moon,
  SunMedium,
  Laptop2,
} from 'lucide-vue-next'

interface Props {
  variant?: 'inset' | 'sidebar' | 'floating'
  collapsible?: 'offcanvas' | 'icon' | 'none'
}

withDefaults(defineProps<Props>(), {
  variant: 'inset',
  collapsible: 'icon',
})

const page = usePage()
const authProps = computed(() => (page.props.auth as any) || {})
const currentCompany = computed(() => authProps.value.currentCompany || null)
const userCompanies = computed(() => authProps.value.companies || [])
const slugFromUrl = computed(() => {
  const match = page.url.match(/^\/([^/]+)/)
  const possibleSlug = match ? match[1] : null
  if (!possibleSlug) return null

  return userCompanies.value.find((company: any) => company.slug === possibleSlug)?.slug || null
})

const navGroups = computed<NavGroup[]>(() => {
  const slug = currentCompany.value?.slug || slugFromUrl.value

  const groups: NavGroup[] = [
    {
      label: 'Overview',
      items: [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
      ]
    }
  ]

  if (slug) {
    // Add company item to Overview
    groups[0].items.push({ title: 'Company', href: `/${slug}`, icon: Building2 })

    // Sales group with receivables
    groups.push({
      label: 'Sales',
      items: [
        { title: 'Customers', href: `/${slug}/customers`, icon: Users },
        {
          title: 'Receivables',
          icon: CircleDollarSign,
          children: [
            { title: 'Invoices', href: `/${slug}/invoices`, icon: FileText },
            { title: 'Payments', href: `/${slug}/payments`, icon: DollarSign },
            { title: 'Credit Notes', href: `/${slug}/credit-notes`, icon: Receipt },
          ]
        },
      ]
    })

    // Purchases group with payables
    groups.push({
      label: 'Purchases',
      items: [
        { title: 'Vendors', href: `/${slug}/vendors`, icon: Truck },
        {
          title: 'Payables',
          icon: CreditCard,
          children: [
            { title: 'Bills', href: `/${slug}/bills`, icon: ReceiptText },
            { title: 'Bill Payments', href: `/${slug}/bill-payments`, icon: Banknote },
            { title: 'Vendor Credits', href: `/${slug}/vendor-credits`, icon: Receipt },
          ]
        },
      ]
    })

    // Accounting group
    groups.push({
      label: 'Accounting',
      items: [
        { title: 'Chart of Accounts', href: `/${slug}/accounts`, icon: BookOpen },
        { title: 'Journal Entries', href: `/${slug}/journals`, icon: FileText },
      ]
    })
  }

  // Settings group
  groups.push({
    label: 'Settings',
    items: [
      { title: 'Companies', href: '/companies', icon: Settings },
    ]
  })

  return groups
})

const { appearance, updateAppearance } = useAppearance()
const systemPrefersDark = ref(false)
const removeMediaListener = ref<(() => void) | null>(null)

onMounted(() => {
  if (typeof window === 'undefined') return
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  systemPrefersDark.value = mediaQuery.matches

  const handleChange = (event: MediaQueryListEvent) => {
    systemPrefersDark.value = event.matches
  }

  mediaQuery.addEventListener('change', handleChange)
  removeMediaListener.value = () => mediaQuery.removeEventListener('change', handleChange)
})

onBeforeUnmount(() => {
  removeMediaListener.value?.()
})

const isDark = computed(() =>
  appearance.value === 'dark' || (appearance.value === 'system' && systemPrefersDark.value),
)

const appearanceLabel = computed(() => {
  if (appearance.value === 'system') {
    return systemPrefersDark.value ? 'System: Dark' : 'System: Light'
  }

  return appearance.value === 'dark' ? 'Dark mode' : 'Light mode'
})

const toggleAppearance = () => {
  updateAppearance(isDark.value ? 'light' : 'dark')
}

const setSystem = () => updateAppearance('system')
</script>

<template>
  <Sidebar :collapsible="collapsible" :variant="variant">
    <SidebarHeader>
      <CompanySwitcher />
    </SidebarHeader>

    <SidebarContent>
      <NavMainCollapsible :groups="navGroups" />
    </SidebarContent>

    <SidebarFooter class="border-t border-sidebar-border/80 bg-sidebar/95">
      <div class="flex flex-col gap-2 p-2">
        <div class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 bg-sidebar-accent/70 px-3 py-2">
          <div class="flex items-center gap-2">
            <component :is="isDark ? Moon : SunMedium" class="size-4 text-nav-item-text" />
            <div class="flex flex-col leading-tight">
              <span class="text-[11px] uppercase tracking-wide text-nav-section-text">Appearance</span>
              <span class="text-sm font-medium text-nav-item-text">{{ appearanceLabel }}</span>
            </div>
          </div>

          <div class="ml-auto flex items-center gap-1">
            <Button
              size="icon"
              variant="ghost"
              class="h-8 w-8 rounded-full text-nav-item-text hover:bg-sidebar-border/60 hover:text-nav-item-text-active"
              @click="toggleAppearance"
              :aria-pressed="isDark"
              :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
            >
              <component :is="isDark ? Moon : SunMedium" class="size-4" />
            </Button>

            <Button
              size="icon"
              variant="ghost"
              class="h-8 w-8 rounded-full text-nav-item-text hover:bg-sidebar-border/60 hover:text-nav-item-text-active"
              :class="{ 'bg-sidebar-border/60 text-nav-item-text-active': appearance === 'system' }"
              @click="setSystem"
              aria-label="Use system appearance"
            >
              <Laptop2 class="size-4" />
            </Button>
          </div>
        </div>

        <NavUser />
      </div>
    </SidebarFooter>
    <SidebarRail />
  </Sidebar>
</template>
