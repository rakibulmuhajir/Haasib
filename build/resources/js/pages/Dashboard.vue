<script setup lang="ts">
import UniversalLayout from '@/layouts/UniversalLayout.vue';
import SectionCards from "@/components/dashboard/dashboard-01/SectionCards.vue"
import ChartAreaInteractive from "@/components/dashboard/dashboard-01/ChartAreaInteractive.vue"
import DataTable from "@/components/dashboard/dashboard-01/DataTable.vue"
import { Head } from '@inertiajs/vue3';

// Props from backend
const props = defineProps<{
    invoices?: Array<{
        id: string
        customer: string
        invoice: string
        amount: string
        status: string
        date: string
        description: string
    }>
}>()

// Dashboard configuration
const breadcrumbs = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Overview', active: true }
]

const headerActions = [
    { label: 'Export Data', variant: 'outline' as const },
    { label: 'Create Invoice', variant: 'default' as const }
]

// Use real invoice data from backend
const invoiceData = props.invoices || []
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
