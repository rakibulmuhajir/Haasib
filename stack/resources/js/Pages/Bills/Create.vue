<template>
  <LayoutShell>
    <Head title="Create Bill" />

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Create Bill</h1>
          <p class="mt-1 text-sm text-gray-500">Create a new vendor bill</p>
        </div>
        
        <Link :href="route('bills.index')" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
          Cancel
        </Link>
      </div>

      <!-- Purchase Order Selection -->
      <div v-if="!purchaseOrder" class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Create from Purchase Order</h2>
        <div class="flex items-center gap-4">
          <div class="flex-1">
            <label for="purchase_order_id" class="block text-sm font-medium text-gray-700 mb-2">
              Select Purchase Order (Optional)
            </label>
            <select
              id="purchase_order_id"
              v-model="selectedPurchaseOrderId"
              @change="loadPurchaseOrder"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            >
              <option value="">Create from scratch</option>
              <option v-for="po in availablePurchaseOrders" :key="po.id" :value="po.id">
                {{ po.po_number }} - {{ po.vendor?.display_name || po.vendor?.legal_name }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Form -->
      <Form
        :action="route('bills.store')"
        method="POST"
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
                :disabled="!!purchaseOrder"
              >
                <option value="">Select a vendor</option>
                <option
                  v-for="vendor in vendors"
                  :key="vendor.id"
                  :value="vendor.id"
                  :selected="purchaseOrder?.vendor_id === vendor.id"
                >
                  {{ vendor.display_name || vendor.legal_name }}
                </option>
              </select>
              <p v-if="errors.vendor_id" class="mt-1 text-sm text-red-600">
                {{ errors.vendor_id }}
              </p>
            </div>

            <div>
              <label for="vendor_bill_number" class="block text-sm font-medium text-gray-700 mb-2">
                Vendor Bill Number
              </label>
              <input
                type="text"
                id="vendor_bill_number"
                name="vendor_bill_number"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.vendor_bill_number"
                placeholder="Bill number from vendor"
                maxlength="50"
              />
              <p v-if="errors.vendor_bill_number" class="mt-1 text-sm text-red-600">
                {{ errors.vendor_bill_number }}
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label for="bill_date" class="block text-sm font-medium text-gray-700 mb-2">
                Bill Date *
              </label>
              <input
                type="date"
                id="bill_date"
                name="bill_date"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.bill_date"
                required
              />
              <p v-if="errors.bill_date" class="mt-1 text-sm text-red-600">
                {{ errors.bill_date }}
              </p>
            </div>

            <div>
              <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">
                Due Date *
              </label>
              <input
                type="date"
                id="due_date"
                name="due_date"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.due_date"
                :min="form.bill_date"
                required
              />
              <p v-if="errors.due_date" class="mt-1 text-sm text-red-600">
                {{ errors.due_date }}
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
              <button
                type="button"
                @click="addLine"
                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                Add Line Item
              </button>
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
                      <button
                        type="button"
                        @click="removeLine(index)"
                        class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                      >
                        Remove
                      </button>
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
                Notes
              </label>
              <textarea
                id="notes"
                name="notes"
                rows="3"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                v-model="form.notes"
                placeholder="Notes for vendor or internal reference"
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
                placeholder="Internal notes not visible to vendor"
              ></textarea>
              <p v-if="errors.internal_notes" class="mt-1 text-sm text-red-600">
                {{ errors.internal_notes }}
              </p>
            </div>
          </div>

          <!-- Purchase Order ID (Hidden) -->
          <input
            type="hidden"
            name="purchase_order_id"
            v-model="form.purchase_order_id"
          />

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
            <Link :href="route('bills.index')" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
              Cancel
            </Link>
            
            <button
              type="submit"
              :disabled="processing || form.lines.length === 0"
              @click="submit"
              class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
              {{ processing ? 'Creating...' : (form.action === 'draft' ? 'Create Draft' : 'Create and Submit') }}
            </button>
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
                  Bill created successfully!
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
import { computed, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import { Form } from '@inertiajs/vue3'
import { formatCurrency } from '@/utils/format'
import type { Vendor, PurchaseOrder } from '@/types/models'

interface Props {
  vendors: Vendor[]
  selectedVendor?: Vendor
  purchaseOrder?: PurchaseOrder & {
    lines: any[]
  }
}

const props = defineProps<Props>()
const page = usePage()

const selectedPurchaseOrderId = ref('')
const availablePurchaseOrders = ref([])

// Initialize form
const form = ref({
  vendor_id: props.selectedVendor?.id || props.purchaseOrder?.vendor_id || '',
  bill_date: new Date().toISOString().split('T')[0],
  due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 30 days from now
  currency: 'USD',
  exchange_rate: 1,
  vendor_bill_number: '',
  notes: '',
  internal_notes: '',
  purchase_order_id: props.purchaseOrder?.id || '',
  action: 'draft',
  lines: [],
})

// Initialize lines
if (props.purchaseOrder?.lines) {
  form.value.lines = props.purchaseOrder.lines.map(line => ({
    description: line.description,
    quantity: line.quantity,
    unit_price: line.unit_price,
    discount_percentage: line.discount_percentage,
    tax_rate: line.tax_rate,
    purchase_order_line_id: line.id,
    product_id: line.product_id,
  }))
} else {
  // Add one empty line by default
  form.value.lines = [{
    description: '',
    quantity: 1,
    unit_price: 0,
    discount_percentage: 0,
    tax_rate: 0,
  }]
}

// Load available purchase orders for selection
async function loadAvailablePurchaseOrders() {
  try {
    const response = await fetch('/api/purchase-orders?status=sent&per_page=50', {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      }
    })
    const data = await response.json()
    availablePurchaseOrders.value = data.data || []
  } catch (error) {
    console.error('Error loading purchase orders:', error)
  }
}

// Load selected purchase order
async function loadPurchaseOrder() {
  if (!selectedPurchaseOrderId.value) {
    // Clear form if no PO selected
    form.value.lines = [{
      description: '',
      quantity: 1,
      unit_price: 0,
      discount_percentage: 0,
      tax_rate: 0,
    }]
    form.value.purchase_order_id = ''
    form.value.vendor_id = ''
    return
  }

  try {
    const response = await fetch(`/purchase-orders/${selectedPurchaseOrderId}`, {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      }
    })
    const po = await response.json()
    
    form.value.purchase_order_id = po.id
    form.value.vendor_id = po.vendor_id
    form.value.lines = po.lines.map((line: any) => ({
      description: line.description,
      quantity: line.quantity,
      unit_price: line.unit_price,
      discount_percentage: line.discount_percentage,
      tax_rate: line.tax_rate,
      purchase_order_line_id: line.id,
      product_id: line.product_id,
    }))
  } catch (error) {
    console.error('Error loading purchase order:', error)
  }
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

function addLine() {
  form.value.lines.push({
    description: '',
    quantity: 1,
    unit_price: 0,
    discount_percentage: 0,
    tax_rate: 0,
  })
}

function removeLine(index: number) {
  if (form.value.lines.length > 1) {
    form.value.lines.splice(index, 1)
  }
}

// Load available POs on mount
if (!props.purchaseOrder) {
  loadAvailablePurchaseOrders()
}
</script>
