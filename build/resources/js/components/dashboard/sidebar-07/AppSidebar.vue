<script setup lang="ts">
import type { SidebarProps } from "@/components/ui/sidebar"
import {
  AudioWaveform,
  BookOpen,
  Bot,
  Command,
  Frame,
  GalleryVerticalEnd,
  Map,
  PieChart,
  Settings2,
  SquareTerminal,
} from "lucide-vue-next"

import NavMain from "@/components/dashboard/sidebar-07/NavMain.vue"
import NavProjects from "@/components/dashboard/sidebar-07/NavProjects.vue"
import NavUser from "@/components/dashboard/sidebar-07/NavUser.vue"
import TeamSwitcher from "@/components/dashboard/sidebar-07/TeamSwitcher.vue"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
} from "@/components/ui/sidebar"

const props = withDefaults(defineProps<SidebarProps>(), {
  collapsible: "icon",
})

// Dashboard navigation data
const data = {
  user: {
    name: "Admin User",
    email: "admin@haasib.com", 
    avatar: "/avatars/admin.jpg",
  },
  teams: [
    {
      name: "Haasib Accounting",
      logo: GalleryVerticalEnd,
      plan: "Enterprise",
    },
    {
      name: "Haasib Hospitality", 
      logo: AudioWaveform,
      plan: "Professional",
    },
    {
      name: "Demo Company",
      logo: Command,
      plan: "Starter",
    },
  ],
  navMain: [
    {
      title: "Dashboard",
      url: "/dashboard",
      icon: SquareTerminal,
      isActive: true,
      items: [
        {
          title: "Overview",
          url: "/dashboard",
        },
        {
          title: "Analytics", 
          url: "/dashboard/analytics",
        },
        {
          title: "Reports",
          url: "/dashboard/reports",
        },
      ],
    },
    {
      title: "Customers",
      url: "/customers",
      icon: Bot,
      items: [
        {
          title: "All Customers",
          url: "/customers",
        },
        {
          title: "Add Customer",
          url: "/customers/create",
        },
        {
          title: "Customer Types",
          url: "/customers/types",
        },
      ],
    },
    {
      title: "Invoices",
      url: "/invoices", 
      icon: BookOpen,
      items: [
        {
          title: "All Invoices",
          url: "/invoices",
        },
        {
          title: "Create Invoice",
          url: "/invoices/create",
        },
        {
          title: "Templates",
          url: "/invoices/templates",
        },
        {
          title: "Recurring",
          url: "/invoices/recurring",
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
  ],
}
</script>

<template>
  <Sidebar v-bind="props">
    <SidebarHeader>
      <TeamSwitcher :teams="data.teams" />
    </SidebarHeader>
    <SidebarContent>
      <NavMain :items="data.navMain" />
      <NavProjects :projects="data.projects" />
    </SidebarContent>
    <SidebarFooter>
      <NavUser :user="data.user" />
    </SidebarFooter>
    <SidebarRail />
  </Sidebar>
</template>