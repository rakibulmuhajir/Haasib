<script setup lang="ts">
import { computed } from 'vue'
import CompanySwitcher from '@/components/CompanySwitcher.vue'
import NavFooter from '@/components/NavFooter.vue'
import NavMain from '@/components/NavMain.vue'
import NavSecondary from '@/components/NavSecondary.vue'
import NavUser from '@/components/NavUser.vue'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
} from '@/components/ui/sidebar'
import { dashboard } from '@/routes'
import type { NavItem } from '@/types'
import { usePage } from '@inertiajs/vue3'
import {
  LayoutGrid,
  Building2,
  FileText,
  BookOpen,
  Folder,
  Users,
  DollarSign,
  Receipt,
  Clock,
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

const mainNavItems = computed<NavItem[]>(() => {
  const items: NavItem[] = [
    {
      title: 'Dashboard',
      href: dashboard(),
      icon: LayoutGrid,
    },
  ]

  const slug = currentCompany.value?.slug || slugFromUrl.value
  if (slug) {
    items.push(
      {
        title: 'Company',
        href: `/${slug}`,
        icon: Building2,
      },
      {
        title: 'Customers',
        href: `/${slug}/customers`,
        icon: Users,
      },
      {
        title: 'Invoices',
        href: `/${slug}/invoices`,
        icon: FileText,
      },
      {
        title: 'Payments',
        href: `/${slug}/payments`,
        icon: DollarSign,
      },
      {
        title: 'Credit Notes',
        href: `/${slug}/credit-notes`,
        icon: Receipt,
      }
    )
  }

  // Always show Companies link for navigation
  items.push({
    title: 'Companies',
    href: '/companies',
    icon: Building2,
  })

  return items
})

const secondaryNavItems: NavItem[] = [
  {
    title: 'Documentation',
    href: 'https://laravel.com/docs',
    icon: FileText,
    external: true,
  },
]

const footerNavItems: NavItem[] = [
  {
    title: 'GitHub Repository',
    href: 'https://github.com/laravel/vue-starter-kit',
    icon: Folder,
    external: true,
  },
  {
    title: 'Starter Kit Docs',
    href: 'https://laravel.com/docs/starter-kits#vue',
    icon: BookOpen,
    external: true,
  },
]
</script>

<template>
  <Sidebar :collapsible="collapsible" :variant="variant">
    <SidebarHeader>
      <CompanySwitcher />
    </SidebarHeader>

    <SidebarContent>
      <NavMain :items="mainNavItems" />
      <NavSecondary :items="secondaryNavItems" />
    </SidebarContent>

    <SidebarFooter>
      <NavUser />
    </SidebarFooter>
    <SidebarRail />
  </Sidebar>
</template>
