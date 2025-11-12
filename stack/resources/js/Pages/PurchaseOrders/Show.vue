<template>
  <LayoutShell>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Purchase Order Details</h1>
          <p class="mt-1 text-sm text-gray-500">
            PO Number: {{ purchaseOrder.po_number }}
          </p>
        </div>
        
        <div class="flex items-center gap-3">
          <SecondaryButton
            v-if="purchaseOrder.canBeEdited"
            @click="editPurchaseOrder"
          >
            Edit
          </SecondaryButton>
          
          <SecondaryButton
            v-if="purchaseOrder.canBeApproved"
            @click="approvePurchaseOrder"
          >
            Approve
          </SecondaryButton>
          
          <SecondaryButton
            v-if="purchaseOrder.canBeSent"
            @click="sendToVendor"
          >
            Send to Vendor
          </SecondaryButton>
          
          <DangerButton
            v-if="purchaseOrder.canBeCancelled"
            @click="cancelPurchaseOrder"
          >
            Cancel
          </DangerButton>
          
          <SecondaryButton @click="generatePdf">
            Generate PDF
          </SecondaryButton>
          
          <SecondaryButton @click="goBack">
            Back to List
          </SecondaryButton>
        </div>
      </div>

      <!-- Status and Approval Info -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <p class="text-sm font-medium text-gray-500">Status</p>
            <div class="mt-1">
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getStatusClass(purchaseOrder.status)"
              >
                {{ getStatusLabel(purchaseOrder.status) }}
              </span>
            </div>
          </div>
          
          <div>
            <p class="text-sm font-medium text-gray-500">Order Date</p>
            <p class="mt-1 text-sm text-gray-900">{{ formatDate(purchaseOrder.order_date) }}</p>
          </div>
          
          <div v-if="purchaseOrder.expected_delivery_date">
            <p class="text-sm font-medium text-gray-500">Expected Delivery</p>
            <p class="mt-1 text-sm text-gray-900">{{ formatDate(purchaseOrder.expected_delivery_date) }}</p>
          </div>
          
          <div v-if="purchaseOrder.approved_at">
            <p class="text-sm font-medium text-gray-500">Approved</p>
            <p class="mt-1 text-sm text-gray-900">
              {{ formatDate(purchaseOrder.approved_at) }} by {{ purchaseOrder.approved_by?.name }}
            </p>
          </div>
        </div>
      </div>

      <!-- Vendor Information -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Vendor Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p class="text-sm font-medium text-gray-500">Vendor</p>
            <p class="mt-1 text-sm text-gray-900">{{ purchaseOrder.vendor?.display_name || purchaseOrder.vendor?.legal_name }}</p>
          </div>
          <div v-if="purchaseOrder.vendor?.vendor_code">
            <p class="text-sm font-medium text-gray-500">Vendor Code</p>
            <p class="mt-1 text-sm text-gray-900">{{ purchaseOrder.vendor.vendor_code }}</p>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Line Items</h2>
        
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="text-left py-3 px-4 text-sm font-medium text-gray-900">#</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-gray-900">Description</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-gray-900">Quantity</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-gray-900">Unit Price</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-gray-900">Discount</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-gray-900">Tax</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-gray-900">Total</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-gray-900">Received</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="line in purchaseOrder.lines"
                :key="line.id"
                class="border-b border-gray-100"
              >
                <td class="py-3 px-4 text-sm text-gray-900">{{ line.line_number }}</td>
                <td class="py-3 px-4 text-sm text-gray-900">{{ line.description }}</td>
                <td class="py-3 px-4 text-sm text-gray-900 text-right">{{ line.formatted_quantity }}</td>
                <td class="py-3 px-4 text-sm text-gray-900 text-right">{{ formatCurrency(line.unit_price) }}</td>
                <td class="py-3 px-4 text-sm text-gray-900 text-right">
                  {{ line.discount_percentage ? `${line.discount_percentage}%` : '-' }}
                </td>
                <td class="py-3 px-4 text-sm text-gray-900 text-right">
                  {{ line.tax_rate ? `${line.tax_rate}%` : '-' }}
                </td>
                <td class="py-3 px-4 text-sm text-gray-900 text-right">{{ formatCurrency(line.line_total) }}</td>
                <td class="py-3 px-4 text-sm text-gray-900 text-right">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                    :class="getReceptionStatusClass(line.reception_status)"
                  >
                    {{ line.formatted_received_quantity }} / {{ line.formatted_quantity }}
                  </span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-gray-200">
                <td colspan="6" class="py-3 px-4 text-sm font-medium text-gray-900 text-right">Subtotal:</td>
                <td class="py-3 px-4 text-sm text-gray-900 text-right">{{ formatCurrency(purchaseOrder.subtotal) }}</td>
                <td></td>
              </tr>
              <tr>
                <td colspan="6" class="py-3 px-4 text-sm font-medium text-gray-900 text-right">Tax Total:</td>
                <td class="py-3 px-4 text-sm text-gray-900 text-right">{{ formatCurrency(purchaseOrder.tax_total) }}</td>
                <td></td>
              </tr>
              <tr class="border-t-2 border-gray-200">
                <td colspan="6" class="py-3 px-4 text-base font-medium text-gray-900 text-right">Total:</td>
                <td class="py-3 px-4 text-base font-medium text-gray-900 text-right">
                  {{ formatCurrency(purchaseOrder.total_amount) }}
                </td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Notes -->
      <div v-if="purchaseOrder.notes || purchaseOrder.internal_notes" class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Notes</h2>
        
        <div v-if="purchaseOrder.notes" class="mb-4">
          <p class="text-sm font-medium text-gray-500 mb-2">Vendor Notes</p>
          <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ purchaseOrder.notes }}</p>
        </div>
        
        <div v-if="purchaseOrder.internal_notes">
          <p class="text-sm font-medium text-gray-500 mb-2">Internal Notes</p>
          <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ purchaseOrder.internal_notes }}</p>
        </div>
      </div>

      <!-- Metadata -->
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Additional Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p class="text-sm font-medium text-gray-500">Currency</p>
            <p class="mt-1 text-sm text-gray-900">{{ purchaseOrder.currency }}</p>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-500">Exchange Rate</p>
            <p class="mt-1 text-sm text-gray-900">{{ purchaseOrder.exchange_rate }}</p>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-500">Created By</p>
            <p class="mt-1 text-sm text-gray-900">{{ purchaseOrder.created_by?.name }}</p>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-500">Created At</p>
            <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(purchaseOrder.created_at) }}</p>
          </div>
          <div v-if="purchaseOrder.sent_to_vendor_at">
            <p class="text-sm font-medium text-gray-500">Sent to Vendor</p>
            <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(purchaseOrder.sent_to_vendor_at) }}</p>
          </div>
        </div>
      </div>
    </div>
  </LayoutShell>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import { formatDate, formatDateTime, formatCurrency } from '@/utils/format'
import type { PurchaseOrder, PurchaseOrderLine } from '@/types/models'

interface Props {
  purchaseOrder: PurchaseOrder & {
    lines: PurchaseOrderLine[]
    vendor: {
      id: string
      legal_name: string
      display_name: string | null
      vendor_code: string | null
    }
    approved_by: {
      name: string
    } | null
    created_by: {
      name: string
    }
  }
}

const props = defineProps<Props>()
const page = usePage()

const getStatusClass = (status: string): string => {
  const classes = {
    draft: 'bg-gray-100 text-gray-800',
    pending_approval: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-blue-100 text-blue-800',
    sent: 'bg-purple-100 text-purple-800',
    partially_received: 'bg-orange-100 text-orange-800',
    fully_received: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    closed: 'bg-gray-100 text-gray-800',
  }
  
  return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800'
}

const getStatusLabel = (status: string): string => {
  const labels = {
    draft: 'Draft',
    pending_approval: 'Pending Approval',
    approved: 'Approved',
    sent: 'Sent to Vendor',
    partially_received: 'Partially Received',
    fully_received: 'Fully Received',
    cancelled: 'Cancelled',
    closed: 'Closed',
  }
  
  return labels[status as keyof typeof labels] || status
}

const getReceptionStatusClass = (status: string): string => {
  const classes = {
    not_received: 'bg-gray-100 text-gray-800',
    partially_received: 'bg-orange-100 text-orange-800',
    fully_received: 'bg-green-100 text-green-800',
  }
  
  return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800'
}

const editPurchaseOrder = () => {
  router.get(route('purchase-orders.edit', props.purchaseOrder.id))
}

const approvePurchaseOrder = () => {
  if (confirm('Are you sure you want to approve this purchase order?')) {
    router.post(
      route('purchase-orders.approve', props.purchaseOrder.id),
      {},
      {
        onSuccess: () => {
          // Flash message will be handled by the backend
        },
      }
    )
  }
}

const sendToVendor = () => {
  if (confirm('Are you sure you want to send this purchase order to the vendor?')) {
    router.post(
      route('purchase-orders.send', props.purchaseOrder.id),
      {},
      {
        onSuccess: () => {
          // Flash message will be handled by the backend
        },
      }
    )
  }
}

const cancelPurchaseOrder = () => {
  if (confirm('Are you sure you want to cancel this purchase order? This action cannot be undone.')) {
    router.delete(route('purchase-orders.destroy', props.purchaseOrder.id), {
      onSuccess: () => {
        router.visit(route('purchase-orders.index'))
      },
    })
  }
}

const generatePdf = () => {
  window.open(route('purchase-orders.pdf', props.purchaseOrder.id), '_blank')
}

const goBack = () => {
  router.get(route('purchase-orders.index'))
}
</script>
