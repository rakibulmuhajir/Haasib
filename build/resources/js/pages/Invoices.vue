<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue';
import DataTable from "@/components/dashboard/dashboard-01/DataTable.vue"
import { Head } from '@inertiajs/vue3';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"

// Page configuration
const breadcrumbs = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Invoices', active: true }
]

const headerActions = [
    { label: 'Export Invoices', variant: 'outline' as const },
    { label: 'Create Invoice', variant: 'default' as const }
]

// Sample invoice data
const invoices = [
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
  }
]

// Calculate stats
const totalInvoices = invoices.length
const paidInvoices = invoices.filter(inv => inv.status === 'Paid').length
const pendingInvoices = invoices.filter(inv => inv.status === 'Pending').length
const overdueInvoices = invoices.filter(inv => inv.status === 'Overdue').length

const totalAmount = invoices.reduce((sum, inv) => {
  const amount = parseFloat(inv.amount.replace(/[$,]/g, ''))
  return sum + amount
}, 0)

const paidAmount = invoices
  .filter(inv => inv.status === 'Paid')
  .reduce((sum, inv) => {
    const amount = parseFloat(inv.amount.replace(/[$,]/g, ''))
    return sum + amount
  }, 0)
</script>

<template>
    <Head title="Invoices" />

    <UniversalLayout
        title="Invoices"
        subtitle="Manage Billing & Payments"
        :breadcrumbs="breadcrumbs"
        :header-actions="headerActions"
    >
        <!-- Invoice Stats -->
        <div class="grid grid-cols-1 gap-4 px-4 lg:px-6 @xl/main:grid-cols-2 @5xl/main:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Invoices</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ totalInvoices }}</div>
                    <p class="text-xs text-muted-foreground">
                        All time invoices
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Paid</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-green-600">{{ paidInvoices }}</div>
                    <p class="text-xs text-muted-foreground">
                        ${{ paidAmount.toLocaleString() }} collected
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Pending</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-yellow-600">{{ pendingInvoices }}</div>
                    <p class="text-xs text-muted-foreground">
                        Awaiting payment
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Overdue</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-red-600">{{ overdueInvoices }}</div>
                    <p class="text-xs text-muted-foreground">
                        Requires attention
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Invoices Table -->
        <DataTable :data="invoices" />
    </UniversalLayout>
</template>