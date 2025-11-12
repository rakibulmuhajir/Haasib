<template>
  <LayoutShell>
    <Head title="Edit Purchase Order" />

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Edit Purchase Order</h1>
          <p class="mt-1 text-sm text-gray-500">
            PO Number: {{ purchaseOrder.po_number }}
          </p>
        </div>
        
        <SecondaryButton @click="goBack">
          Cancel
        </SecondaryButton>
      </div>

      <!-- Form -->
      <Form
        :action="route('purchase-orders.update', purchaseOrder.id)"
        method="PUT"
        #default="{ errors, hasErrors, processing, wasSuccessful, recentlySuccessful, submit }"
      >
        <div class="bg-white rounded-lg border border-gray-200 p-6">
          <!-- Basic Information -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label for="vendor_id" class="block text-sm font-medium text-gray-700 mb-2">
                Vendor *
              </label>
              <select
                id="vendor_id"
                name="vendor_id"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.vendor_id"
                required
              >
                <option value="">Select a vendor</option>
                <option
                  v-for="vendor in vendors"
                  :key="vendor.id"
                  :value="vendor.id"
                >
                  {{ vendor.display_name || vendor.legal_name }}
                </option>
              </select>
              <p v-if="errors.vendor_id" class="mt-1 text-sm text-red-600">
                {{ errors.vendor_id }}
              </p>
            </div>

            <div>
              <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                Status
              </label>
              <div class="flex items-center gap-2">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="getStatusClass(purchaseOrder.status)"
                >
                  {{ getStatusLabel(purchaseOrder.status) }}
                </span>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label for="order_date" class="block text-sm font-medium text-gray-700 mb-2">
                Order Date *
              </label>
              <input
                type="date"
                id="order_date"
                name="order_date"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.order_date"
                required
              />
              <p v-if="errors.order_date" class="mt-1 text-sm text-red-600">
                {{ errors.order_date }}
              </p>
            </div>

            <div>
              <label for="expected_delivery_date" class="block text-sm font-medium text-gray-700 mb-2">
                Expected Delivery Date
              </label>
              <input
                type="date"
                id="expected_delivery_date"
                name="expected_delivery_date"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.expected_delivery_date"
                :min="form.order_date"
              />
              <p v-if="errors.expected_delivery_date" class="mt-1 text-sm text-red-600">
                {{ errors.expected_delivery_date }}
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">
                Currency *
              </label>
              <select
                id="currency"
                name="currency"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.currency"
                required
              >
                <option value="USD">USD - US Dollar</option>
                <option value="EUR">EUR - Euro</option>
                <option value="GBP">GBP - British Pound</option>
                <option value="CAD">CAD - Canadian Dollar</option>
                <option value="AUD">AUD - Australian Dollar</option>
              </select>
              <p v-if="errors.currency" class="mt-1 text-sm text-red-600">
                {{ errors.currency }}
              </p>
            </div>

            <div>
              <label for="exchange_rate" class="block text-sm font-medium text-gray-700 mb-2">
                Exchange Rate *
              </label>
              <input
                type="number"
                id="exchange_rate"
                name="exchange_rate"
                step="0.0001"
                min="0"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.exchange_rate"
                required
              />
              <p v-if="errors.exchange_rate" class="mt-1 text-sm text-red-600">
                {{ errors.exchange_rate }}
              </p>
            </div>
          </div>

          <!-- Line Items -->
          <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-medium text-gray-900">Line Items</h3>
              <SecondaryButton type="button" @click="addLine">
                Add Line Item
              </SecondaryButton>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full border-collapse">
                <thead>
                  <tr class="bg-gray-50">
                    <th class="border border-gray-200 px-4 py-2 text-left text-sm font-medium text-gray-900">Description *</th>
                    <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-900">Quantity *</th>
                    <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-900">Unit Price *</th>
                    <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-900">Discount %</th>
                    <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-900">Tax %</th>
                    <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-900">Total</th>
                    <th class="border border-gray-200 px-4 py-2 text-center text-sm font-medium text-gray-900">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, index) in form.lines" :key="index">
                    <td class="border border-gray-200 p-2">
                      <input
                        type="text"
                        :name="`lines[${index}].description`"
                        class="w-full border-0 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        v-model="line.description"
                        placeholder="Item description"
                        required
                      />
                      <span v-if="errors[`lines.${index}.description`]" class="text-xs text-red-600">
                        {{ errors[`lines.${index}.description`] }}
                      </span>
                    </td>
                    <td class="border border-gray-200 p-2">
                      <input
                        type="number"
                        :name="`lines[${index}].quantity`"
                        step="0.0001"
                        min="0"
                        class="w-full border-0 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-right"
                        v-model="line.quantity"
                        @input="calculateLineTotals"
                        required
                      />
                      <span v-if="errors[`lines.${index}.quantity`]" class="text-xs text-red-600">
                        {{ errors[`lines.${index}.quantity`] }}
                      </span>
                    </td>
                    <td class="border border-gray-200 p-2">
                      <input
                        type="number"
                        :name="`lines[${index}].unit_price`"
                        step="0.000001"
                        min="0"
                        class="w-full border-0 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-right"
                        v-model="line.unit_price"
                        @input="calculateLineTotals"
                        required
                      />
                      <span v-if="errors[`lines.${index}.unit_price`]" class="text-xs text-red-600">
                        {{ errors[`lines.${index}.unit_price`] }}
                      </span>
                    </td>
                    <td class="border border-gray-200 p-2">
                      <input
                        type="number"
                        :name="`lines[${index}].discount_percentage`"
                        step="0.01"
                        min="0"
                        max="100"
                        class="w-full border-0 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-right"
                        v-model="line.discount_percentage"
                        @input="calculateLineTotals"
                      />
                      <span v-if="errors[`lines.${index}.discount_percentage`]" class="text-xs text-red-600">
                        {{ errors[`lines.${index}.discount_percentage`] }}
                      </span>
                    </td>
                    <td class="border border-gray-200 p-2">
                      <input
                        type="number"
                        :name="`lines[${index}].tax_rate`"
                        step="0.001"
                        min="0"
                        max="100"
                        class="w-full border-0 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-right"
                        v-model="line.tax_rate"
                        @input="calculateLineTotals"
                      />
                      <span v-if="errors[`lines.${index}.tax_rate`]" class="text-xs text-red-600">
                        {{ errors[`lines.${index}.tax_rate`] }}
                      </span>
                    </td>
                    <td class="border border-gray-200 px-4 py-2 text-right text-sm text-gray-900">
                      {{ formatCurrency(calculateLineTotal(line)) }}
                    </td>
                    <td class="border border-gray-200 p-2 text-center">
                      <SecondaryButton
                        type="button"
                        @click="removeLine(index)"
                        size="sm"
                        class="bg-red-100 text-red-800 hover:bg-red-200 focus:ring-red-500"
                      >
                        Remove
                      </SecondaryButton>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Totals -->
            <div class="mt-4 space-y-2 text-right">
              <div class="text-sm">
                <span class="font-medium">Subtotal:</span>
                <span class="ml-2">{{ formatCurrency(totals.subtotal) }}</span>
              </div>
              <div class="text-sm">
                <span class="font-medium">Tax Total:</span>
                <span class="ml-2">{{ formatCurrency(totals.tax) }}</span>
              </div>
              <div class="text-lg font-bold">
                <span>Total:</span>
                <span class="ml-2">{{ formatCurrency(totals.total) }}</span>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                Vendor Notes
              </label>
              <textarea
                id="notes"
                name="notes"
                rows="3"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.notes"
                placeholder="Notes for vendor"
              ></textarea>
              <p v-if="errors.notes" class="mt-1 text-sm text-red-600">
                {{ errors.notes }}
              </p>
            </div>

            <div>
              <label for="internal_notes" class="block text-sm font-medium text-gray-700 mb-2">
                Internal Notes
              </label>
              <textarea
                id="internal_notes"
                name="internal_notes"
                rows="3"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.internal_notes"
                placeholder="Internal notes"
              ></textarea>
              <p v-if="errors.internal_notes" class="mt-1 text-sm text-red-600">
                {{ errors.internal_notes }}
              </p>
            </div>
          </div>

          <!-- Action Selection -->
          <div class="border-t border-gray-200 pt-6">
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Action *
              </label>
              <div class="space-y-2">
                <label class="flex items-center">
                  <input
                    type="radio"
                    name="action"
                    value="draft"
                    v-model="form.action"
                    class="mr-2"
                    required
                  />
                  <span class="text-sm text-gray-700">Save as Draft</span>
                </label>
                <label class="flex items-center">
                  <input
                    type="radio"
                    name="action"
                    value="submit_for_approval"
                    v-model="form.action"
                    class="mr-2"
                    required
                  />
                  <span class="text-sm text-gray-700">Submit for Approval</span>
                </label>
              </div>
              <p v-if="errors.action" class="mt-1 text-sm text-red-600">
                {{ errors.action }}
              </p>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="flex justify-end gap-3 border-t border-gray-200 pt-6">
            <SecondaryButton type="button" @click="goBack">
              Cancel
            </SecondaryButton>
            
            <PrimaryButton
              type="submit"
              :disabled="processing || form.lines.length === 0"
              @click="submit"
            >
              {{ processing ? 'Updating...' : (form.action === 'draft' ? 'Update Draft' : 'Update and Submit') }}
            </PrimaryButton>
          </div>

          <!-- Success Message -->
          <div v-if="wasSuccessful" class="rounded-md bg-green-50 p-4 mt-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-green-800">
                  Purchase Order updated successfully!
                </p>
              </div>
            </div>
          </div>

          <!-- Error Messages -->
          <div v-if="hasErrors" class="rounded-md bg-red-50 p-4 mt-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-red-800">
                  There were errors with your submission. Please review the form fields.
                </p>
              </div>
            </div>
          </div>
        </div>
      </Form>
    </div>
  </LayoutShell>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { Form } from '@inertiajs/vue3'
import { formatCurrency } from '@/utils/format'
import type { PurchaseOrder, PurchaseOrderLine, Vendor } from '@/types/models'

interface Props {
  purchaseOrder: PurchaseOrder & {
    lines: PurchaseOrderLine[]
    vendor: Vendor
  }
  vendors: Vendor[]
}

const props = defineProps<Props>()
const page = usePage()

// Initialize form with purchase order data
const form = ref({
  vendor_id: props.purchaseOrder.vendor_id,
  order_date: props.purchaseOrder.order_date,
  expected_delivery_date: props.purchaseOrder.expected_delivery_date,
  currency: props.purchaseOrder.currency,
  exchange_rate: props.purchaseOrder.exchange_rate,
  notes: props.purchaseOrder.notes || '',
  internal_notes: props.purchaseOrder.internal_notes || '',
  action: 'draft',
  lines: props.purchaseOrder.lines.map(line => ({
    description: line.description,
    quantity: line.quantity,
    unit_price: line.unit_price,
    discount_percentage: line.discount_percentage,
    tax_rate: line.tax_rate,
  })),
})

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

const calculateLineTotal = (line: any): number => {
  const subtotal = (parseFloat(line.quantity.toString()) || 0) * (parseFloat(line.unit_price.toString()) || 0)
  const discount = subtotal * ((parseFloat(line.discount_percentage.toString()) || 0) / 100)
  return subtotal - discount
}

const calculateLineTax = (line: any): number => {
  const lineTotal = calculateLineTotal(line)
  return lineTotal * ((parseFloat(line.tax_rate.toString()) || 0) / 100)
}

const totals = computed(() => {
  let subtotal = 0
  let tax = 0
  
  form.value.lines.forEach(line => {
    subtotal += calculateLineTotal(line)
    tax += calculateLineTax(line)
  })
  
  return {
    subtotal,
    tax,
    total: subtotal + tax
  }
})

const calculateLineTotals = () => {
  // Trigger reactivity by accessing the computed property
  totals.value
}

const addLine = () => {
  form.value.lines.push({
    description: '',
    quantity: 1,
    unit_price: 0,
    discount_percentage: 0,
    tax_rate: 0,
  })
}

const removeLine = (index: number) => {
  if (form.value.lines.length > 1) {
    form.value.lines.splice(index, 1)
  }
}

const goBack = () => {
  router.get(route('purchase-orders.show', props.purchaseOrder.id))
}
</script>
