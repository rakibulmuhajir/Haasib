<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Sidebar from '@/Layouts/Sidebar.vue'
import PageHeader from '@/Components/PageHeader.vue'
import PageActions from '@/Components/PageActions.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const props = defineProps({
  payments: Object
})

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const getPaymentStatusSeverity = (status) => {
  const severities = {
    'received': 'success',
    'pending': 'warning',
    'failed': 'danger',
    'draft': 'info'
  }
  return severities[status] || 'info'
}

const getPaymentMethodSeverity = (method) => {
  const severities = {
    'cash': 'success',
    'bank_transfer': 'info',
    'credit_card': 'warning',
    'check': 'secondary'
  }
  return severities[method] || 'info'
}

const actions = [
  {
    label: 'Create Payment',
    icon: 'pi pi-plus',
    route: route('payments.create'),
    permission: 'payments.create'
  }
]
</script>

<template>
  <AuthenticatedLayout>
    <div class="page-container">
      <Sidebar theme="blu-whale" />
      
      <div class="main-content">
        <PageHeader title="Payments" subtitle="Manage customer payments">
          <template #actionsRight>
            <PageActions :actions="actions" />
          </template>
        </PageHeader>
        
        <div class="page-content">
          <div class="card">
            <DataTable 
              :value="payments.data" 
              :paginator="true" 
              :rows="15"
              :totalRecords="payments.total"
              :lazy="true"
              dataKey="id"
              responsiveLayout="scroll"
              :loading="false"
            >
              <Column field="reference" header="Reference" :sortable="true">
                <template #body="{ data }">
                  <Link 
                    :href="route('payments.show', data.id)"
                    class="text-primary-600 hover:text-primary-700"
                  >
                    {{ data.reference || `PAY-${data.id.substring(0, 8).toUpperCase()}` }}
                  </Link>
                </template>
              </Column>
              
              <Column field="customer.name" header="Customer" :sortable="true">
                <template #body="{ data }">
                  {{ data.customer?.name || 'Unknown' }}
                </template>
              </Column>
              
              <Column field="amount" header="Amount" :sortable="true">
                <template #body="{ data }">
                  {{ formatCurrency(data.amount) }}
                </template>
              </Column>
              
              <Column field="payment_date" header="Date" :sortable="true">
                <template #body="{ data }">
                  {{ formatDate(data.payment_date) }}
                </template>
              </Column>
              
              <Column field="payment_method" header="Method" :sortable="true">
                <template #body="{ data }">
                  <Tag 
                    :value="data.payment_method?.replace('_', ' ')"
                    :severity="getPaymentMethodSeverity(data.payment_method)"
                  />
                </template>
              </Column>
              
              <Column field="status" header="Status" :sortable="true">
                <template #body="{ data }">
                  <Tag 
                    :value="data.status"
                    :severity="getPaymentStatusSeverity(data.status)"
                  />
                </template>
              </Column>
              
              <Column header="Actions">
                <template #body="{ data }">
                  <div class="flex gap-2">
                    <Button 
                      icon="pi pi-eye"
                      class="p-button-sm p-button-outlined"
                      @click="router.visit(route('payments.show', data.id))"
                      v-tooltip="'View Payment'"
                    />
                    <Button 
                      icon="pi pi-pencil"
                      class="p-button-sm p-button-outlined"
                      @click="router.visit(route('payments.edit', data.id))"
                      v-tooltip="'Edit Payment'"
                    />
                  </div>
                </template>
              </Column>
            </DataTable>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>