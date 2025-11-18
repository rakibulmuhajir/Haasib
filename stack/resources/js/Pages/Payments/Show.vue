<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import PageHeader from '@/Components/PageHeader.vue'
import PageActions from '@/Components/PageActions.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

const props = defineProps({
  payment: Object
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

const totalAllocated = computed(() => {
  return props.payment.allocations?.reduce((sum, allocation) => sum + allocation.amount, 0) || 0
})

const unallocatedAmount = computed(() => {
  return props.payment.amount - totalAllocated.value
})

const actions = [
  {
    label: 'Edit Payment',
    icon: 'pi pi-pencil',
    route: route('payments.edit', props.payment.id),
    permission: 'payments.update'
  },
  {
    label: 'Allocate Payment',
    icon: 'pi pi-sliders-h',
    route: route('payments.allocate', props.payment.id),
    permission: 'payments.allocate'
  },
  {
    label: 'Back to Payments',
    icon: 'pi pi-arrow-left',
    route: route('payments.index')
  }
]
</script>

<template>
  <AuthenticatedLayout>
    <div class="page-container">
      <Sidebar theme="blu-whale" />
      
      <div class="main-content">
        <PageHeader 
          :title="`Payment ${payment.reference || `PAY-${payment.id.substring(0, 8).toUpperCase()}`}`"
          :subtitle="`Payment from ${payment.customer?.name || 'Unknown'}`"
        >
          <template #actionsRight>
            <PageActions :actions="actions" />
          </template>
        </PageHeader>
        
        <div class="page-content">
          <div class="grid">
            <!-- Payment Details -->
            <div class="col-12 lg:col-8">
              <Card>
                <template #title>Payment Details</template>
                <template #content>
                  <div class="grid">
                    <div class="col-12 md:col-6">
                      <div class="mb-4">
                        <strong>Customer:</strong>
                        <div>{{ payment.customer?.name || 'Unknown' }}</div>
                      </div>
                      <div class="mb-4">
                        <strong>Payment Date:</strong>
                        <div>{{ formatDate(payment.payment_date) }}</div>
                      </div>
                      <div class="mb-4">
                        <strong>Payment Method:</strong>
                        <div>
                          <Tag 
                            :value="payment.payment_method?.replace('_', ' ')"
                            :severity="getPaymentMethodSeverity(payment.payment_method)"
                          />
                        </div>
                      </div>
                    </div>
                    
                    <div class="col-12 md:col-6">
                      <div class="mb-4">
                        <strong>Amount:</strong>
                        <div class="text-2xl font-bold">{{ formatCurrency(payment.amount) }}</div>
                      </div>
                      <div class="mb-4">
                        <strong>Status:</strong>
                        <div>
                          <Tag 
                            :value="payment.status"
                            :severity="getPaymentStatusSeverity(payment.status)"
                          />
                        </div>
                      </div>
                      <div class="mb-4" v-if="payment.reference">
                        <strong>Reference:</strong>
                        <div>{{ payment.reference }}</div>
                      </div>
                    </div>
                    
                    <div class="col-12" v-if="payment.notes">
                      <div class="mb-4">
                        <strong>Notes:</strong>
                        <div>{{ payment.notes }}</div>
                      </div>
                    </div>
                  </div>
                </template>
              </Card>
            </div>
            
            <!-- Payment Summary -->
            <div class="col-12 lg:col-4">
              <Card>
                <template #title>Payment Summary</template>
                <template #content>
                  <div class="space-y-3">
                    <div class="flex justify-between">
                      <span>Total Payment:</span>
                      <strong>{{ formatCurrency(payment.amount) }}</strong>
                    </div>
                    <div class="flex justify-between">
                      <span>Allocated:</span>
                      <strong>{{ formatCurrency(totalAllocated) }}</strong>
                    </div>
                    <div class="flex justify-between">
                      <span>Unallocated:</span>
                      <strong :class="unallocatedAmount > 0 ? 'text-yellow-600' : 'text-green-600'">
                        {{ formatCurrency(unallocatedAmount) }}
                      </strong>
                    </div>
                  </div>
                </template>
              </Card>
            </div>
          </div>
          
          <!-- Allocations -->
          <div class="col-12 mt-4" v-if="payment.allocations && payment.allocations.length > 0">
            <Card>
              <template #title>Payment Allocations</template>
              <template #content>
                <DataTable 
                  :value="payment.allocations" 
                  dataKey="id"
                  responsiveLayout="scroll"
                >
                  <Column field="invoice.invoice_number" header="Invoice">
                    <template #body="{ data }">
                      <Link 
                        :href="route('invoices.show', data.invoice_id)"
                        class="text-primary-600 hover:text-primary-700"
                      >
                        {{ data.invoice?.invoice_number || `INV-${data.invoice_id.substring(0, 8).toUpperCase()}` }}
                      </Link>
                    </template>
                  </Column>
                  
                  <Column field="invoice.due_date" header="Due Date">
                    <template #body="{ data }">
                      {{ formatDate(data.invoice?.due_date) }}
                    </template>
                  </Column>
                  
                  <Column field="amount" header="Allocated Amount">
                    <template #body="{ data }">
                      {{ formatCurrency(data.amount) }}
                    </template>
                  </Column>
                  
                  <Column field="allocated_at" header="Allocated On">
                    <template #body="{ data }">
                      {{ formatDate(data.allocated_at) }}
                    </template>
                  </Column>
                </DataTable>
              </template>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>