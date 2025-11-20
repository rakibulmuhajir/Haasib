<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"

// Page configuration
const breadcrumbs = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Customers', active: true }
]

const headerActions = [
    { label: 'Import Customers', variant: 'outline' as const },
    { label: 'Add Customer', variant: 'default' as const }
]

// Sample customer data
const customers = [
  {
    id: 1,
    name: "Acme Corporation",
    email: "contact@acme.com",
    phone: "+1 (555) 123-4567",
    status: "Active",
    totalInvoices: 12,
    totalAmount: "$45,250.00",
    lastActivity: "2024-11-15"
  },
  {
    id: 2,
    name: "Global Tech Solutions",
    email: "hello@globaltech.com",
    phone: "+1 (555) 987-6543",
    status: "Active",
    totalInvoices: 8,
    totalAmount: "$23,400.00",
    lastActivity: "2024-11-18"
  },
  {
    id: 3,
    name: "StartupXYZ",
    email: "team@startupxyz.com",
    phone: "+1 (555) 456-7890",
    status: "Inactive",
    totalInvoices: 3,
    totalAmount: "$5,200.00",
    lastActivity: "2024-10-28"
  }
]
</script>

<template>
    <Head title="Customers" />

    <UniversalLayout
        title="Customers"
        subtitle="Manage Customer Relationships"
        :breadcrumbs="breadcrumbs"
        :header-actions="headerActions"
    >
        <!-- Customer Stats -->
        <div class="grid grid-cols-1 gap-4 px-4 lg:px-6 @xl/main:grid-cols-3">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Customers</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ customers.length }}</div>
                    <p class="text-xs text-muted-foreground">
                        +2 from last month
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Active Customers</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ customers.filter(c => c.status === 'Active').length }}</div>
                    <p class="text-xs text-muted-foreground">
                        {{ Math.round((customers.filter(c => c.status === 'Active').length / customers.length) * 100) }}% of total
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Revenue</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">$73,850.00</div>
                    <p class="text-xs text-muted-foreground">
                        Across all customers
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Customer List -->
        <div class="px-4 lg:px-6">
            <Card>
                <CardHeader>
                    <CardTitle>Customer Directory</CardTitle>
                    <CardDescription>
                        View and manage your customer accounts
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-for="customer in customers" :key="customer.id" class="flex items-center justify-between p-4 border rounded-lg">
                        <div class="space-y-1">
                            <div class="flex items-center gap-3">
                                <h3 class="font-semibold">{{ customer.name }}</h3>
                                <Badge :variant="customer.status === 'Active' ? 'default' : 'secondary'">
                                    {{ customer.status }}
                                </Badge>
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ customer.email }} • {{ customer.phone }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ customer.totalInvoices }} invoices • {{ customer.totalAmount }} total • Last activity: {{ customer.lastActivity }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" size="sm">View</Button>
                            <Button variant="ghost" size="sm">Edit</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </UniversalLayout>
</template>