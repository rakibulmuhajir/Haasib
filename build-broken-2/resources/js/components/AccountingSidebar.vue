<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { 
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
  SidebarMenu,
  SidebarMenuButton,
} from '@/components/ui/sidebar'
import { Badge } from '@/components/ui/badge'
import { 
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
  Globe,
  Frame,
  HelpCircle,
  Star,
  Map,
  Settings2,
  University,
  Command,
  CheckSquare,
  Layers,
  Tags,
  User,
  ChevronsUpDown,
  Sun,
  Moon
} from 'lucide-vue-next'

import CompanySwitcher from '@/components/CompanySwitcher.vue'
import NavMain from '@/components/NavMain.vue'
import NavProjects from '@/components/NavProjects.vue'
import NavUser from '@/components/UserMenuContent.vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"

interface ExtendedNavItem {
  title: string
  url: string
  icon?: any
  isActive?: boolean
  items?: {
    title: string
    url: string
  }[]
}

interface ProjectItem {
  name: string
  url: string
  icon: any
}

const page = usePage()
const auth = page.props.auth as any
const user = auth?.user

// Theme toggle functionality
const isDark = ref(false)

// Initialize theme based on system preference or localStorage
const initializeTheme = () => {
  const savedTheme = localStorage.getItem('theme')
  if (savedTheme) {
    isDark.value = savedTheme === 'dark'
  } else {
    isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
  }
  updateTheme()
}

const updateTheme = () => {
  if (isDark.value) {
    document.documentElement.classList.add('dark')
    localStorage.setItem('theme', 'dark')
  } else {
    document.documentElement.classList.remove('dark')
    localStorage.setItem('theme', 'light')
  }
}

const toggleTheme = () => {
  isDark.value = !isDark.value
  updateTheme()
}

// Initialize theme on component mount
onMounted(() => {
  initializeTheme()
})

const companyList = computed(() => {
  const companiesProp = (page.props.userCompanies as Array<any>) ?? (page.props.companies as Array<any>) ?? []
  return companiesProp
})
const activeCompanyId = computed(() => page.props.activeCompanyId as string | null)

// Company switcher options
const companyOptions = computed(() => {
  const list = companyList.value ?? []

  if (!list.length) {
    return [
      {
        name: "No Companies",
        logo: Building,
        plan: "Create your first company",
        url: "/companies/create"
      }
    ]
  }

  return list.map((company: any) => ({
    name: company.name,
    logo: Building,
    plan: company.industry || 'General',
    id: company.id,
    isActive: company.isActive ?? (company.id === activeCompanyId.value),
  }))
})

const selectCompany = (company: any) => {
  if (!company || !company.id) {
    if (company?.url) {
      router.visit(company.url)
    }
    return
  }

  router.post(`/company/${company.id}/switch`, {}, {
    preserveScroll: true,
  })
}

const companiesForSwitcher = computed(() => companyOptions.value.length ? companyOptions.value : [{
  name: "No Companies",
  logo: Building,
  plan: "Create your first company",
  url: "/companies/create"
}])

// Dashboard navigation data
const currentPath = computed(() => {
  const path = (page.url as string | undefined) || (typeof window !== 'undefined' ? window.location.pathname : '/')
  return (path || '/').split('?')[0] || '/'
})

// Main navigation items with organized structure
const navMainItems = computed(() => {
  const items = [
    {
      title: "Dashboard",
      url: "/dashboard",
      icon: Home,
      items: [],
    },
    {
      title: "Sales & Receivables",
      url: "#",
      icon: DollarSign,
      items: [
        {
          title: "Accounting Home",
          url: "/dashboard/accounting",
        },
        {
          title: "Invoices",
          url: "/accounting/invoices",
        },
        {
          title: "Customers",
          url: "/accounting/customers",
        },
        {
          title: "Credit Limits",
          url: "/customers/credit-limits",
        },
        {
          title: "Statements & Aging",
          url: "/reports/statements",
        },
      ],
    },
    {
      title: "Expense Cycle",
      url: "#",
      icon: FileText,
      items: [
        {
          title: "Vendors",
          url: "/vendors",
        },
        {
          title: "Purchase Orders",
          url: "/purchase-orders",
        },
        {
          title: "Bills",
          url: "/bills",
        },
        {
          title: "Expense Reports",
          url: "/expenses",
        },
        {
          title: "Expense Categories",
          url: "/expense-categories",
        },
        {
          title: "Vendor Payments",
          url: "/vendor-payments",
        },
      ],
    },
    {
      title: "Banking & Cash",
      url: "#",
      icon: University,
      items: [
        {
          title: "Reconciliation Workspace",
          url: "/bank-reconciliation",
        },
        {
          title: "Statement Import",
          url: "/bank-import",
        },
        {
          title: "Bank Reports",
          url: "/bank-reports",
        },
        {
          title: "Bank Accounts",
          url: "/bank-accounts",
        },
      ],
    },
    {
      title: "Accounting Operations",
      url: "#",
      icon: Calculator,
      items: [
        {
          title: "Journal Entries",
          url: "/journal-entries",
        },
        {
          title: "Trial Balance",
          url: "/trial-balance",
        },
        {
          title: "Ledger",
          url: "/ledger",
        },
        {
          title: "Adjustments & Batches",
          url: "/journal-entries/batches",
        },
      ],
    },
    {
      title: "Tax Management",
      url: "#",
      icon: Receipt,
      items: [
        {
          title: "Tax Dashboard",
          url: "/tax/dashboard",
        },
        {
          title: "Tax Agencies",
          url: "/tax/agencies",
        },
        {
          title: "Tax Rates",
          url: "/tax/rates",
        },
        {
          title: "Tax Settings",
          url: "/tax/settings",
        },
        {
          title: "Tax Returns",
          url: "/tax/returns",
        },
        {
          title: "Tax Reports",
          url: "/tax/reports/sales-tax",
        },
      ],
    },
    {
      title: "Reporting & Analytics",
      url: "#",
      icon: PieChart,
      items: [
        {
          title: "Reporting Dashboard",
          url: "/reports/dashboard",
        },
        {
          title: "Financial Reports",
          url: "/reports/financial",
        },
        {
          title: "Reporting Schedules",
          url: "/reports/schedules",
        },
        {
          title: "Statements",
          url: "/reports/statements",
        },
        {
          title: "Templates",
          url: "/reports/templates",
        },
      ],
    },
    {
      title: "Settings",
      url: "/settings",
      icon: Settings2,
      items: [
        {
          title: "General",
          url: "/settings/general",
        },
        {
          title: "Company",
          url: "/settings/company",
        },
        {
          title: "Users & Permissions",
          url: "/settings/users",
        },
        {
          title: "Integrations",
          url: "/settings/integrations",
        },
        {
          title: "Currencies",
          url: "/settings/currencies",
        },
      ],
    },
  ]

  return items.map((item) => {
    const subItems = item.items || []
    const hasActiveChild = subItems.some((subItem) => currentPath.value.startsWith(subItem.url))
    const isSelfActive = item.url && item.url !== '#' ? currentPath.value.startsWith(item.url) : false

    return {
      ...item,
      isActive: Boolean(item.isActive || isSelfActive || hasActiveChild),
    }
  })
})

// Projects data
const projectsData = ref<ProjectItem[]>([
  {
    name: "Monthly Reporting",
    url: "/projects/monthly-reports",
    icon: Frame,
  },
  {
    name: "Tax Preparation",
    url: "/projects/tax-prep", 
    icon: PieChart,
  },
  {
    name: "Audit Trail",
    url: "/projects/audit",
    icon: Map,
  },
  {
    name: "Getting Started",
    url: "/welcome",
    icon: Star,
  },
  {
    name: "Help & Resources",
    url: "/help",
    icon: HelpCircle,
  },
])
</script>

<template>
  <Sidebar collapsible="icon" class="border-r">
    <!-- Company Switcher Header -->
    <SidebarHeader>
      <CompanySwitcher
        :companies="companiesForSwitcher"
        :active-company-id="activeCompanyId"
        @select="selectCompany"
      />
    </SidebarHeader>

    <!-- Navigation Content -->
    <SidebarContent>
      <!-- Main Navigation -->
      <NavMain :items="navMainItems" />
      
      <!-- Projects Section -->
      <NavProjects :projects="projectsData" />
    </SidebarContent>

    <!-- Enhanced Footer -->
    <SidebarFooter class="border-t">
      <div class="flex items-center gap-1 p-1">
        <!-- Theme Toggle Button -->
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton 
              @click="toggleTheme"
              class="w-8 justify-center"
              title="Toggle theme"
            >
              <Sun v-if="!isDark" class="h-3 w-3" />
              <Moon v-else class="h-3 w-3" />
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
        
        <!-- User Menu Button -->
        <SidebarMenu class="flex-1">
          <SidebarMenuItem>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <SidebarMenuButton class="w-full justify-start">
                  <User class="h-4 w-4 mr-2" />
                  {{ user?.name || 'Admin User' }}
                  <ChevronsUpDown class="ml-auto h-4 w-4" />
                </SidebarMenuButton>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-56" align="start" side="right">
                <NavUser :user="{ name: user?.name || 'Admin User', email: user?.email || 'admin@haasib.com', avatar: user?.avatar || '' }" />
              </DropdownMenuContent>
            </DropdownMenu>
          </SidebarMenuItem>
        </SidebarMenu>
      </div>
    </SidebarFooter>
    
    <SidebarRail />
  </Sidebar>
</template>
