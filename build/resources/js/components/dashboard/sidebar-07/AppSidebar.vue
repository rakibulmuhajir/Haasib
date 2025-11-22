<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import type { SidebarProps } from "@/components/ui/sidebar"
import {
  AudioWaveform,
  BookOpen,
  Bot,
  Building,
  Calendar,
  Calculator,
  CheckSquare,
  ChevronRight,
  ClipboardList,
  Command,
  CreditCard,
  DollarSign,
  FileText,
  Frame,
  GalleryVerticalEnd,
  HelpCircle,
  Home,
  Layers,
  Map,
  PieChart,
  Receipt,
  Settings2,
  ShieldAlt,
  SquareTerminal,
  Star,
  TachometerAlt,
  Tags,
  University,
  UserCog,
  Users,
} from "lucide-vue-next"

import NavMain from "@/components/dashboard/sidebar-07/NavMain.vue"
import NavProjects from "@/components/dashboard/sidebar-07/NavProjects.vue"
import NavUser from "@/components/dashboard/sidebar-07/NavUser.vue"
import CompanySwitcher from "@/components/dashboard/sidebar-07/CompanySwitcher.vue"
import { ThemeToggle } from "@/components/ui/theme"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
  SidebarMenuButton,
} from "@/components/ui/sidebar"
import { Button } from "@/components/ui/button"
import { useToast } from '@/components/ui/toast/use-toast'

const props = withDefaults(defineProps<SidebarProps>(), {
  collapsible: "icon",
})

const page = usePage()
const auth = page.props.auth as any
const user = auth?.user
const { toast } = useToast()

const companyList = computed(() => {
  const companiesProp = (page.props.userCompanies as Array<any>) ?? (page.props.companies as Array<any>) ?? []
  console.debug('[Sidebar] Companies prop payload:', companiesProp)
  return companiesProp
})
const activeCompanyId = computed(() => page.props.activeCompanyId as string | null)
console.debug('[Sidebar] activeCompanyId prop:', page.props.activeCompanyId, 'currentCompany:', page.props.currentCompany)

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
  if (!company) {
    return
  }

  if (!company.id) {
    if (company.url) {
      router.visit(company.url)
    }
    return
  }

  router.post(`/company/${company.id}/switch`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      toast({
        title: 'Company Switched',
        description: `Switched to ${company.name}.`,
      })
    },
    onError: () => {
      toast({
        title: 'Switch failed',
        description: 'Unable to switch to the company. Please try again.',
        variant: 'destructive',
      })
    },
  })
}

const fallbackTeams = [
  {
    name: "No Companies",
    logo: Building,
    plan: "Create your first company",
    url: "/companies/create"
  }
]

const companiesForSwitcher = computed(() => companyOptions.value.length ? companyOptions.value : fallbackTeams)

// Dashboard navigation data
const data = {
  user: {
    name: user?.name || "Admin User",
    email: user?.email || "admin@haasib.com", 
    avatar: user?.avatar || "/avatars/admin.jpg",
  },
  navMain: [
    {
      title: "Dashboard",
      url: "/dashboard",
      icon: Home,
      isActive: true,
      items: [],
    },
    {
      title: "Sales & Receivables",
      url: "#",
      icon: DollarSign,
      items: [
        {
          title: "Invoices",
          url: "/invoices",
        },
        {
          title: "Payments",
          url: "/payments",
        },
        {
          title: "Customers",
          url: "/customers",
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
      title: "Period Close & Compliance",
      url: "#",
      icon: Calendar,
      items: [
        {
          title: "Period Close Checklist",
          url: "/period-close",
        },
        {
          title: "Audit Trail",
          url: "/audit-trail",
        },
        {
          title: "Policy & Compliance",
          url: "/compliance",
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
      ],
    },
  ],
  projects: [
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
    {
      name: "What's New",
      url: "/whats-new",
      icon: HelpCircle,
    },
  ],
}
</script>

<template>
  <Sidebar v-bind="props">
    <SidebarHeader>
      <CompanySwitcher
        :companies="companiesForSwitcher"
        :active-company-id="activeCompanyId"
        @select="selectCompany"
      />
    </SidebarHeader>
    <SidebarContent>
      <NavMain :items="data.navMain" />
      <NavProjects :projects="data.projects" />
    </SidebarContent>
    <SidebarFooter>
      <div class="flex items-center gap-2 px-2 py-1">
        <ThemeToggle />
        <NavUser :user="data.user" />
      </div>
    </SidebarFooter>
    <SidebarRail />
  </Sidebar>
</template>
