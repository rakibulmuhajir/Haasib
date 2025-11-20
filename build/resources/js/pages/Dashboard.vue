<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue';
import SectionCards from "@/components/dashboard/dashboard-01/SectionCards.vue"
import ChartAreaInteractive from "@/components/dashboard/dashboard-01/ChartAreaInteractive.vue"
import DataTable from "@/components/dashboard/dashboard-01/DataTable.vue"
import { Head } from '@inertiajs/vue3';

// Dashboard configuration
const breadcrumbs = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Overview', active: true }
]

const headerActions = [
    { label: 'Export Data', variant: 'outline' as const },
    { label: 'Create Invoice', variant: 'default' as const }
]

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
  }
]
</script>

<template>
    <Head title="Dashboard" />

    <UniversalLayout
        title="Dashboard"
        subtitle="Overview"
        :breadcrumbs="breadcrumbs"
        :header-actions="headerActions"
    >
        <!-- Analytics Cards -->
        <SectionCards />
        
        <!-- Interactive Chart -->
        <div class="px-4 lg:px-6">
            <ChartAreaInteractive />
        </div>
        
        <!-- Data Table -->
        <DataTable :data="invoiceData" />
    </UniversalLayout>
</template>
