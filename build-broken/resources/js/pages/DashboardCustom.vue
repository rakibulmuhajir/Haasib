<script setup lang="ts">
import { ref, computed } from 'vue'
import AppSidebar from "@/components/dashboard/sidebar-07/AppSidebar.vue"
import ChartAreaInteractive from "@/components/dashboard/dashboard-01/ChartAreaInteractive.vue"
import DataTable from "@/components/dashboard/dashboard-01/DataTable.vue"
import SectionCards from "@/components/dashboard/dashboard-01/SectionCards.vue"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import { Separator } from "@/components/ui/separator"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"

// Sample invoice data
const invoiceData = [
  {
    id: 1,
    customer: "Acme Corporation",
    invoice: "INV-2024-001",
    amount: "$5,250.00",
    status: "Paid",
    date: "2024-11-15",
    description: "Web development services",
  },
  {
    id: 2,
    customer: "Global Tech Solutions",
    invoice: "INV-2024-002", 
    amount: "$3,400.00",
    status: "Pending",
    date: "2024-11-18",
    description: "System integration project",
  },
  {
    id: 3,
    customer: "StartupXYZ",
    invoice: "INV-2024-003",
    amount: "$1,200.00",
    status: "Overdue",
    date: "2024-10-28",
    description: "Consulting services",
  },
  {
    id: 4,
    customer: "Enterprise Solutions Inc",
    invoice: "INV-2024-004",
    amount: "$8,750.00",
    status: "Paid",
    date: "2024-11-12",
    description: "Custom software development",
  },
  {
    id: 5,
    customer: "Digital Marketing Pro",
    invoice: "INV-2024-005",
    amount: "$2,100.00",
    status: "Pending",
    date: "2024-11-20",
    description: "SEO optimization package",
  },
  {
    id: 6,
    customer: "CloudFirst Technologies",
    invoice: "INV-2024-006",
    amount: "$4,600.00",
    status: "Paid",
    date: "2024-11-10",
    description: "Cloud migration services",
  },
  {
    id: 7,
    customer: "Innovation Labs",
    invoice: "INV-2024-007",
    amount: "$6,300.00",
    status: "Pending",
    date: "2024-11-22",
    description: "Product development consultation",
  },
  {
    id: 8,
    customer: "RetailChain Plus",
    invoice: "INV-2024-008",
    amount: "$1,850.00",
    status: "Overdue",
    date: "2024-10-30",
    description: "POS system setup",
  }
]
</script>

<template>
  <SidebarProvider
    :style="{
      '--sidebar-width': 'calc(var(--spacing) * 72)',
      '--header-height': 'calc(var(--spacing) * 12)',
    }"
  >
    <AppSidebar variant="inset" />
    <SidebarInset>
      <!-- Header -->
      <header class="flex h-(--header-height) shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-(--header-height)">
        <div class="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
          <SidebarTrigger class="-ml-1" />
          <Separator
            orientation="vertical"
            class="mx-2 data-[orientation=vertical]:h-4"
          />
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem class="hidden md:block">
                <BreadcrumbLink href="/dashboard">
                  Dashboard
                </BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator class="hidden md:block" />
              <BreadcrumbItem>
                <BreadcrumbPage>Overview</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
          <div class="ml-auto flex items-center gap-2">
            <Button variant="outline" size="sm" class="hidden sm:flex">
              Export Data
            </Button>
            <Button size="sm">
              Create Invoice
            </Button>
          </div>
        </div>
      </header>
      
      <!-- Main Content -->
      <div class="flex flex-1 flex-col">
        <div class="@container/main flex flex-1 flex-col gap-2">
          <div class="flex flex-col gap-4 py-4 md:gap-6 md:py-6">
            <!-- Analytics Cards -->
            <SectionCards />
            
            <!-- Interactive Chart -->
            <div class="px-4 lg:px-6">
              <ChartAreaInteractive />
            </div>
            
            <!-- Data Table -->
            <DataTable :data="invoiceData" />
          </div>
        </div>
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>