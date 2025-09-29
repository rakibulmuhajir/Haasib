<template>
  <Head title="Create Payment" />

  <LayoutShell>
    <template #sidebar>
      <Sidebar title="Invoicing" />
    </template>

    <template #topbar>
      <Breadcrumb :items="breadcrumbItems" />
    </template>

    <div class="max-w-7xl mx-auto p-6 space-y-6">
      <PageHeader
        title="Create Payment"
        subtitle="Record a new payment transaction"
      >
        <template #actions-right>
          <div class="text-right">
            <div class="text-xs text-gray-500">Payment Number</div>
            <div class="text-base font-medium text-gray-900">{{ form.payment_number }}</div>
          </div>
        </template>
      </PageHeader>

      <form @submit.prevent="submit" novalidate>
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
          <!-- Main Form Area (Left Side) -->
          <div class="xl:col-span-7 space-y-6">

            <!-- Customer Selection Card -->
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
              <div class="bg-gray-50 border-b p-4">
                <h3 class="text-lg font-medium text-gray-900">Customer Information</h3>
              </div>
              <div class="p-6">
                <div class="space-y-3">
                  <label class="block text-sm font-semibold text-gray-700">
                    Customer <span class="text-red-500">*</span>
                  </label>
                  <CustomerPicker
                    v-model="form.customer_id"
                    :customers="propsCustomers"
                    :error="form.errors.customer_id"
                    optionValue="customer_id"
                    placeholder="Search and select customer..."
                    @change="onCustomerChange"
                  />
                  <div v-if="form.errors.customer_id" class="text-sm text-red-600 flex items-center">
                    <i class="pi pi-exclamation-triangle mr-1"></i>
                    {{ form.errors.customer_id }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment Details Card -->
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
              <div class="bg-gray-50 border-b p-4">
                <h3 class="text-lg font-medium text-gray-900">Payment Details</h3>
              </div>
              <div class="p-6 space-y-6">

                <!-- Amount and Currency Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">
                      Amount <span class="text-red-500">*</span>
                    </label>
                    <InputGroup>
                      <InputNumber
                        name="amount"
                        v-model="form.amount"
                        :min="0.01"
                        :max="maxAmount"
                        :locale="locale"
                        mode="decimal"
                        :minFractionDigits="2"
                        :maxFractionDigits="2"
                        class="flex-1"
                        :class="{ 'p-invalid': form.errors.amount }"
                        placeholder="0.00"
                        aria-label="Payment amount"
                      />
                      <InputGroupAddon>
                        <CurrencyPicker
                          v-model="form.currency_id"
                          :currencies="availableCurrencies"
                          :showClear="false"
                          class="w-24"
                          :class="{ 'p-invalid': form.errors.currency_id }"
                          @change="onCurrencyChange"
                          aria-label="Currency"
                        />
                      </InputGroupAddon>
                    </InputGroup>
                    <div v-if="form.errors.amount" class="text-sm text-red-600 flex items-center">
                      <i class="pi pi-exclamation-triangle mr-1"></i>
                      {{ form.errors.amount }}
                    </div>
                  </div>

                  <div class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">
                      Payment Date <span class="text-red-500">*</span>
                    </label>
                    <Calendar
                      name="payment_date"
                      v-model="form.payment_date"
                      dateFormat="yy-mm-dd"
                      class="w-full"
                      :class="{ 'p-invalid': form.errors.payment_date }"
                      :showIcon="true"
                      iconDisplay="input"
                    />
                    <div v-if="form.errors.payment_date" class="text-sm text-red-600 flex items-center">
                      <i class="pi pi-exclamation-triangle mr-1"></i>
                      {{ form.errors.payment_date }}
                    </div>
                  </div>
                </div>

                <!-- Payment Method -->
                <div class="space-y-3">
                  <label class="block text-sm font-semibold text-gray-700">
                    Payment Method <span class="text-red-500">*</span>
                  </label>
                  <Dropdown
                    name="payment_method"
                    v-model="form.payment_method"
                    :options="paymentMethods"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Select payment method..."
                    class="w-full"
                    :class="{ 'p-invalid': form.errors.payment_method }"
                  >
                    <template #option="{ option }">
                      <div class="flex items-center space-x-2">
                        <span class="text-lg">{{ getPaymentMethodIcon(option.value) }}</span>
                        <span>{{ option.label }}</span>
                      </div>
                    </template>
                  </Dropdown>
                  <div v-if="form.errors.payment_method" class="text-sm text-red-600 flex items-center">
                    <i class="pi pi-exclamation-triangle mr-1"></i>
                    {{ form.errors.payment_method }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment Application Section (appears when customer selected) -->
            <div v-if="selectedCustomer" class="bg-white rounded-lg shadow-sm border overflow-hidden">
              <div class="bg-gray-50 border-b p-4">
                <h3 class="text-lg font-medium text-gray-900">Payment Application</h3>
              </div>
              <div class="p-6">
                <!-- Radio button group for mutually exclusive options -->
                <div class="space-y-4">
                  <!-- Auto-allocate option -->
                  <div
                    class="border rounded-lg p-4 cursor-pointer transition-colors"
                    :class="form.auto_allocate ? 'border-gray-900 bg-gray-50' : 'border-gray-300'"
                    @click="form.auto_allocate = true; form.invoice_id = null"
                  >
                    <div class="flex items-start space-x-3">
                      <RadioButton
                        v-model="form.auto_allocate"
                        :value="true"
                        inputId="autoAllocate"
                        name="paymentApplication"
                      />
                      <div class="flex-1">
                        <label for="autoAllocate" class="font-medium text-gray-700 cursor-pointer">
                          Auto-allocate to outstanding invoices
                        </label>
                        <p class="text-sm text-gray-600 mt-1">
                          Automatically distribute payment to all outstanding invoices (oldest first)
                        </p>
                      </div>
                    </div>
                  </div>

                  <!-- Specific invoice option -->
                  <div
                    class="border rounded-lg p-4 cursor-pointer transition-colors"
                    :class="!form.auto_allocate && form.invoice_id ? 'border-gray-900 bg-gray-50' : 'border-gray-300'"
                    @click="form.auto_allocate = false"
                  >
                    <div class="flex items-start space-x-3">
                      <RadioButton
                        v-model="form.auto_allocate"
                        :value="false"
                        inputId="specificInvoice"
                        name="paymentApplication"
                      />
                      <div class="flex-1">
                        <label for="specificInvoice" class="font-medium text-gray-700 cursor-pointer">
                          Apply to specific invoice
                        </label>
                        <div class="mt-3">
                          <InvoicePicker
                            v-model="form.invoice_id"
                            :invoices="customerInvoices"
                            :optionDisabled="invoice => invoice.balance_due <= 0"
                            placeholder="Select invoice..."
                            :show-clear="true"
                            :error="form.errors.invoice_id && !form.auto_allocate ? form.errors.invoice_id : undefined"
                            :customer-filter="selectedCustomer?.customer_id"
                            @change="onInvoiceChange"
                          />
                          <div v-if="form.errors.invoice_id && !form.auto_allocate" class="text-sm text-red-600 flex items-center mt-1">
                            <i class="pi pi-exclamation-triangle mr-1"></i>
                            {{ form.errors.invoice_id }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Reference and Notes Row -->
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
              <div class="bg-gray-50 border-b p-4">
                <h3 class="text-lg font-medium text-gray-900">Additional Information</h3>
              </div>
              <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">
                      Payment Reference
                    </label>
                    <InputText
                      name="reference_number"
                      v-model="form.reference_number"
                      placeholder="Enter reference number..."
                      class="w-full"
                    />
                    <p class="text-xs text-gray-500">Leave blank to auto-generate</p>
                  </div>

                  <div class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-700">
                      Notes
                    </label>
                    <Textarea
                      name="notes"
                      v-model="form.notes"
                      rows="3"
                      placeholder="Enter additional notes..."
                      class="w-full resize-none"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- Allocation Preview Section (appears when customer selected) -->
            <div v-if="selectedCustomer && (form.auto_allocate || form.invoice_id || allocations.length > 0)" class="bg-white rounded-lg shadow-sm border overflow-hidden">
              <div class="bg-gray-50 border-b p-4">
                <div class="flex justify-between items-center">
                  <h3 class="text-lg font-medium text-gray-900">Allocation Preview</h3>
                  <div class="text-xs text-gray-500">
                    Total Applied: {{ formatMoney(totalApplied, previewCurrency) }}
                  </div>
                </div>
              </div>
              <div class="p-6">
                <!-- Allocations Table -->
                <div class="overflow-x-auto">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="border-b border-gray-200">
                        <th class="text-left py-2 px-3 font-medium text-gray-700">Invoice #</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-700">Date</th>
                        <th class="text-right py-2 px-3 font-medium text-gray-700">Balance Due</th>
                        <th class="text-right py-2 px-3 font-medium text-gray-700">Applied</th>
                        <th class="text-right py-2 px-3 font-medium text-gray-700">Remaining</th>
                        <th class="text-center py-2 px-3 font-medium text-gray-700">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="allocation in allocations" :key="allocation.invoice_id" class="border-b border-gray-100 last:border-0">
                        <td class="py-3 px-3">
                          <div class="font-medium text-gray-900">{{ allocation.invoice_number }}</div>
                        </td>
                        <td class="py-3 px-3 text-gray-600">{{ formatDate(allocation.invoice_date) }}</td>
                        <td class="py-3 px-3 text-right">
                          <span class="font-medium">{{ formatMoney(allocation.balance_due, { code: allocation.currency_code }) }}</span>
                        </td>
                        <td class="py-3 px-3">
                          <InputNumber
                            v-model="allocation.applied_amount"
                            :min="0"
                            :max="allocation.balance_due"
                            mode="decimal"
                            :minFractionDigits="2"
                            :maxFractionDigits="2"
                            class="w-32 text-right"
                            @update:modelValue="updateAllocation(allocation.invoice_id, $event)"
                          />
                        </td>
                        <td class="py-3 px-3 text-right font-medium">
                          {{ formatMoney(allocation.balance_due - allocation.applied_amount, { code: allocation.currency_code }) }}
                        </td>
                        <td class="py-3 px-3 text-center">
                          <Button
                            icon="pi pi-times"
                            class="p-button-text p-button-rounded p-button-sm text-red-600"
                            @click="removeAllocation(allocation.invoice_id)"
                          />
                        </td>
                      </tr>
                      <tr v-if="allocations.length === 0">
                        <td colspan="6" class="py-8 text-center text-gray-500">
                          No allocations yet. Select an invoice to apply payment.
                        </td>
                      </tr>
                    </tbody>
                    <tfoot v-if="allocations.length > 0">
                      <tr class="border-t-2 border-gray-200">
                        <td colspan="3" class="py-3 px-3 font-medium text-gray-900">Total</td>
                        <td class="py-3 px-3 text-right font-bold text-lg">{{ formatMoney(totalApplied, previewCurrency) }}</td>
                        <td colspan="2"></td>
                      </tr>
                      <tr v-if="remainingPayment > 0" class="bg-orange-50">
                        <td colspan="3" class="py-3 px-3 font-medium text-gray-700">Remaining Payment</td>
                        <td class="py-3 px-3 text-right font-bold text-orange-700">{{ formatMoney(remainingPayment, previewCurrency) }}</td>
                        <td colspan="2"></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                <!-- Remainder Handling -->
                <div v-if="remainingPayment > 0" class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                  <h4 class="text-sm font-medium text-blue-900 mb-3">Remaining Amount ({{ formatMoney(remainingPayment, previewCurrency) }})</h4>
                  <div class="space-y-2">
                    <div class="flex items-center">
                      <RadioButton
                        v-model="remainderStrategy"
                        value="credit"
                        inputId="remainderCredit"
                        name="remainderStrategy"
                      />
                      <label for="remainderCredit" class="ml-2 text-sm text-blue-800">
                        Create Customer Credit (recommended)
                      </label>
                    </div>
                    <div class="flex items-center">
                      <RadioButton
                        v-model="remainderStrategy"
                        value="leave_unapplied"
                        inputId="remainderUnapplied"
                        name="remainderStrategy"
                      />
                      <label for="remainderUnapplied" class="ml-2 text-sm text-blue-800">
                        Leave Unapplied
                        <span class="text-xs text-blue-600 ml-1">(not recommended)</span>
                      </label>
                    </div>
                  </div>
                  <p class="text-xs text-blue-700 mt-3">
                    A remaining amount of {{ formatMoney(remainingPayment, previewCurrency) }} will be created as a Customer Credit and linked to this payment.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Sidebar - Customer Details & Preview -->
          <div class="xl:col-span-5 space-y-6">

            <!-- Customer Details Card (appears when customer selected) -->
            <div v-if="selectedCustomer" class="bg-white rounded-lg shadow-sm border overflow-hidden">
              <div class="bg-gray-50 border-b p-4">
                <h3 class="text-lg font-medium text-gray-900">Customer Details</h3>
              </div>
              <div class="p-6">
                <div class="space-y-4">
                  <!-- Customer Info -->
                  <div class="space-y-3">
                    <div class="flex items-start space-x-3">
                      <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-gray-600 font-medium">{{ selectedCustomer.name.charAt(0) }}</span>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900">{{ selectedCustomer.name }}</div>
                        <div v-if="selectedCustomer.email" class="text-xs text-gray-500 truncate">{{ selectedCustomer.email }}</div>
                      </div>
                    </div>
                  </div>

                  <!-- Quick Stats -->
                  <div class="pt-3 border-t border-gray-200">
                    <h4 class="text-xs font-medium text-gray-700 uppercase tracking-wide mb-3">Quick Overview</h4>
                    <div class="grid grid-cols-2 gap-3">
                      <div class="bg-blue-50 rounded-lg p-3 border border-blue-100">
                        <div class="text-xs text-blue-600 font-medium">Outstanding Invoices</div>
                        <div class="text-xl font-bold text-blue-900 mt-1">{{ outstandingInvoices.length }}</div>
                      </div>
                      <div class="bg-green-50 rounded-lg p-3 border border-green-100">
                        <div class="text-xs text-green-600 font-medium">Total Due</div>
                        <div class="text-lg font-bold text-green-900 mt-1">{{ formatMoney(totalOutstanding, outstandingCurrency) }}</div>
                      </div>
                    </div>
                    <div v-if="form.amount > 0" class="mt-3 bg-purple-50 rounded-lg p-3 border border-purple-100">
                      <div class="text-xs text-purple-600 font-medium">Payment Amount</div>
                      <div class="text-lg font-bold text-purple-900 mt-1">{{ formatMoney(Number(form.amount || 0), previewCurrency) }}</div>
                    </div>
                  </div>

                  <!-- Outstanding Invoices List -->
                  <div v-if="outstandingInvoices.length > 0" class="pt-3 border-t border-gray-200">
                    <h4 class="text-xs font-medium text-gray-700 uppercase tracking-wide mb-3">Outstanding Invoices</h4>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                      <div v-for="invoice in outstandingInvoices.slice(0, 5)" :key="invoice.invoice_id"
                           class="flex items-center justify-between p-2 bg-gray-50 rounded hover:bg-gray-100 transition-colors">
                        <div class="flex-1 min-w-0">
                          <div class="text-xs font-medium text-gray-900 truncate">{{ invoice.invoice_number }}</div>
                          <div class="text-xs text-gray-500">{{ formatDate(invoice.invoice_date) }}</div>
                        </div>
                        <div class="text-right ml-2">
                          <div class="text-xs font-bold text-red-600">{{ formatMoney(invoice.balance_due, invoice.currency || { code: companyBaseCurrency }) }}</div>
                        </div>
                      </div>
                      <div v-if="outstandingInvoices.length > 5" class="text-xs text-gray-500 text-center py-1">
                        +{{ outstandingInvoices.length - 5 }} more invoices
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Live Preview Card -->
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden sticky top-6">
              <div class="bg-gray-50 border-b p-4">
                <div class="flex justify-between items-center">
                  <h3 class="text-lg font-medium text-gray-900">Payment Preview</h3>
                  <div class="flex space-x-2">
                    <Button
                      type="button"
                      icon="pi pi-print"
                      class="p-button-text p-button-rounded p-button-sm"
                      @click="printPreview"
                      title="Print preview"
                    />
                    <Button
                      type="button"
                      icon="pi pi-download"
                      class="p-button-text p-button-rounded p-button-sm"
                      @click="downloadPdf"
                      title="Download PDF"
                    />
                  </div>
                </div>
              </div>

              <div class="p-6">
                <!-- Receipt Preview -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 border-2 border-dashed border-gray-300 rounded-xl p-5 font-mono text-sm">
                  <!-- Header -->
                  <div class="flex justify-between items-start mb-4">
                    <div>
                      <div class="font-bold text-lg text-gray-900 flex items-center gap-2">
                        <span class="text-2xl">💰</span>
                        {{ companyName }}
                      </div>
                      <div class="text-gray-600 text-xs mt-1">Payment #{{ form.payment_number }}</div>
                    </div>
                    <div class="text-right text-gray-600 text-xs">
                      {{ formatDate(form.payment_date) }}
                    </div>
                  </div>

                  <!-- Customer Info -->
                  <div class="border-t border-gray-300 pt-3 mb-3">
                    <div class="grid grid-cols-2 gap-2 text-xs">
                      <div>
                        <div class="text-gray-500 uppercase tracking-wide text-xs mb-1">Customer</div>
                        <div class="font-semibold text-gray-900">{{ previewCustomerName || '—' }}</div>
                        <div class="text-gray-500 text-xs mt-1">{{ previewCustomerEmail }}</div>
                      </div>
                      <div class="text-right">
                        <div class="text-gray-500 uppercase tracking-wide text-xs mb-1">Applied To</div>
                        <div class="font-semibold text-gray-900">{{ previewInvoiceNumber || 'Auto-allocate' }}</div>
                      </div>
                    </div>
                  </div>

                  <!-- Amount Section -->
                  <div class="border-t-2 border-gray-300 pt-3">
                    <div class="flex justify-between items-center mb-3">
                      <span class="text-gray-700 font-medium">Payment Amount</span>
                      <span class="text-2xl font-bold text-green-600">
                        {{ formatMoney(Number(form.amount || 0), previewCurrency) }}
                      </span>
                    </div>

                    <!-- Payment Details -->
                    <div class="bg-white rounded-lg p-3 mt-3 space-y-1">
                      <div class="flex justify-between text-xs">
                        <span class="text-gray-500">Method:</span>
                        <span class="font-medium text-gray-900">{{ paymentMethodLabel }}</span>
                      </div>
                      <div class="flex justify-between text-xs">
                        <span class="text-gray-500">Auto-allocate:</span>
                        <span class="font-medium text-gray-900">{{ form.auto_allocate ? 'Yes' : 'No' }}</span>
                      </div>
                      <div v-if="form.notes" class="flex justify-between text-xs mt-2 pt-2 border-t border-gray-100">
                        <span class="text-gray-500">Notes:</span>
                        <span class="font-medium text-gray-900 text-right max-w-[120px]">{{ form.notes }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Status Badge -->
                  <div class="mt-4 text-center">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      <span class="w-2 h-2 bg-blue-600 rounded-full mr-2"></span>
                      Preview Mode
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </LayoutShell>

  <!-- Overpayment Modal -->
  <Dialog
    v-model:visible="showOverpaymentModal"
    modal
    header="Overpayment detected — choose how to handle"
    :style="{ width: '650px' }"
    :breakpoints="{ '640px': '90vw' }"
  >
    <div class="space-y-4">
      <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
        <div class="flex items-start space-x-3">
          <i class="pi pi-exclamation-triangle text-orange-600 text-xl mt-0.5"></i>
          <div>
            <h4 class="font-medium text-orange-900">Overpayment Detected</h4>
            <p class="text-sm text-orange-700 mt-1">
              You've entered {{ formatMoney(form.amount || 0, previewCurrency) }} but {{ selectedInvoice?.invoice_number }} only has 
              {{ formatMoney(selectedInvoice?.balance_due || 0, selectedInvoice?.currency || { code: companyBaseCurrency }) }} due.
            </p>
          </div>
        </div>
      </div>

      <div class="space-y-3">
        <h4 class="font-medium text-gray-900">Choose how to handle the excess:</h4>
        
        <div class="border rounded-lg p-4 cursor-pointer transition-colors hover:bg-gray-50"
             :class="overpaymentOption === 'auto_allocate' ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
             @click="overpaymentOption = 'auto_allocate'">
          <div class="flex items-start space-x-3">
            <RadioButton
              v-model="overpaymentOption"
              value="auto_allocate"
              inputId="autoAllocateExcess"
              name="overpaymentOption"
            />
            <div class="flex-1">
              <label for="autoAllocateExcess" class="font-medium text-gray-900 cursor-pointer">
                Apply excess to other open invoices (oldest-first) and create credit for remainder
              </label>
              <p class="text-sm text-gray-600 mt-1">
                Automatically distribute remaining {{ formatMoney(overpaymentAmount, previewCurrency) }} across other outstanding invoices.
                Any leftover will be created as a Customer Credit.
              </p>
            </div>
          </div>
        </div>

        <div class="border rounded-lg p-4 cursor-pointer transition-colors hover:bg-gray-50"
             :class="overpaymentOption === 'credit_only' ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
             @click="overpaymentOption = 'credit_only'">
          <div class="flex items-start space-x-3">
            <RadioButton
              v-model="overpaymentOption"
              value="credit_only"
              inputId="creditOnly"
              name="overpaymentOption"
            />
            <div class="flex-1">
              <label for="creditOnly" class="font-medium text-gray-900 cursor-pointer">
                Create customer credit for remainder only
              </label>
              <p class="text-sm text-gray-600 mt-1">
                Keep the full payment amount applied to {{ selectedInvoice?.invoice_number }} and create a 
                Customer Credit for the overpayment amount.
              </p>
            </div>
          </div>
        </div>

        <div class="border rounded-lg p-4 cursor-pointer transition-colors hover:bg-gray-50"
             :class="overpaymentOption === 'manual' ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
             @click="overpaymentOption = 'manual'">
          <div class="flex items-start space-x-3">
            <RadioButton
              v-model="overpaymentOption"
              value="manual"
              inputId="manualAllocate"
              name="overpaymentOption"
            />
            <div class="flex-1">
              <label for="manualAllocate" class="font-medium text-gray-900 cursor-pointer">
                Manually allocate
              </label>
              <p class="text-sm text-gray-600 mt-1">
                Return to the allocation table to manually specify how much to apply to each invoice.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Preview of selected option -->
      <div v-if="overpaymentOption" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
        <h5 class="text-sm font-medium text-gray-700 mb-2">Preview:</h5>
        <div class="text-sm text-gray-600 space-y-1">
          <div v-if="overpaymentOption === 'auto_allocate'">
            • {{ formatMoney(selectedInvoice?.balance_due || 0, selectedInvoice?.currency || { code: companyBaseCurrency }) }} applied to {{ selectedInvoice?.invoice_number }}
            • {{ formatMoney(remainingForOtherInvoices, previewCurrency) }} distributed to other invoices
            • {{ formatMoney(remainingAfterAllocation, previewCurrency) }} created as Customer Credit
          </div>
          <div v-else-if="overpaymentOption === 'credit_only'">
            • {{ formatMoney(form.amount || 0, previewCurrency) }} applied to {{ selectedInvoice?.invoice_number }}
            • {{ formatMoney(overpaymentAmount, previewCurrency) }} created as Customer Credit
          </div>
          <div v-else-if="overpaymentOption === 'manual'">
            • Return to allocation table to manually specify amounts
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end space-x-3">
        <Button
          label="Cancel"
          class="p-button-text"
          @click="showOverpaymentModal = false"
        />
        <Button
          label="Apply & Continue"
          class="p-button-primary"
          @click="applyOverpaymentOption"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CustomerPicker from '@/Components/UI/Forms/CustomerPicker.vue'
import CurrencyPicker from '@/Components/UI/Forms/CurrencyPicker.vue'
import InvoicePicker from '@/Components/UI/Forms/InvoicePicker.vue'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import RadioButton from 'primevue/radiobutton'
import Calendar from 'primevue/calendar'
import Button from 'primevue/button'
import InputGroup from 'primevue/inputgroup'
import InputGroupAddon from 'primevue/inputgroupaddon'
import Dialog from 'primevue/dialog'
import { formatMoney } from '@/Utils/formatting'
import { format } from 'date-fns'
import { usePageActions } from '@/composables/usePageActions'

interface Customer { customer_id: number; name: string; email?: string; currency_id?: number }
interface Invoice { invoice_id: number; customer_id: number; invoice_number: string; invoice_date: string; total_amount: number; balance_due: number; currency?: { id: number; code: string; symbol: string } }
interface Currency { id: number; code: string; symbol: string; name?: string }

const props = defineProps<{
  customers: Customer[]
  invoices: Invoice[]
  selectedInvoice?: Invoice | null
  currencies: Currency[]
  nextPaymentNumber: string
}>()

// Handle customer change event
const onCustomerChange = (_customer: Customer) => {
  // Add any customer change logic here
  // The existing watchers will handle invoice filtering
}

// Handle invoice change event
const onInvoiceChange = (_invoice: Invoice | null) => {
  // Add any invoice change logic here
  // The existing watchers will handle amount and allocation logic
}

// Lightweight aliases so template is tidy
const propsCustomers = props.customers
const propsInvoices = props.invoices
const propsCurrencies = props.currencies

const breadcrumbItems = ref([
  { label: 'Invoicing', url: '/invoicing', icon: 'invoice' },
  { label: 'Payments', url: '/invoicing/payments', icon: 'payments' },
  { label: 'Create Payment', url: '#', icon: 'plus' }
])

const page = usePage()
const companyBaseCurrency = computed(() => (page.props.auth as any)?.currentCompany?.base_currency || 'USD')
const companyName = computed(() => (page.props.auth as any)?.currentCompany?.name || 'Your Company')
const locale = 'en-US'


// Reactive state
const customerInvoices = ref<Invoice[]>([])
const selectedCustomer = ref<Customer | null>(null)
const outstandingInvoices = ref<Invoice[]>([])
const selectedCurrency = ref<Currency | null>(null)

// Allocation state
const allocations = ref<Array<{
  invoice_id: number
  invoice_number: string
  invoice_date: string
  balance_due: number
  currency_code: string
  applied_amount: number
  exchange_rate: number
}>>([])

const showOverpaymentModal = ref(false)
const overpaymentAmount = ref(0)
const selectedInvoice = ref<Invoice | null>(null)
const remainderStrategy = ref<'credit' | 'leave_unapplied'>('credit')
const overpaymentOption = ref<'auto_allocate' | 'credit_only' | 'manual'>('auto_allocate')

const paymentMethods = [
  { label: 'Cash', value: 'cash' },
  { label: 'Check', value: 'check' },
  { label: 'Bank Transfer', value: 'bank_transfer' },
  { label: 'Credit Card', value: 'credit_card' },
  { label: 'Debit Card', value: 'debit_card' },
  { label: 'PayPal', value: 'paypal' },
  { label: 'Stripe', value: 'stripe' },
  { label: 'Other', value: 'other' }
]

const form = useForm({
  customer_id: null as number | null,
  invoice_id: null as number | null,
  payment_number: props.nextPaymentNumber,
  payment_date: new Date(),
  amount: 0 as number,
  currency_id: props.currencies[0]?.id || null as number | null,
  payment_method: '',
  reference_number: '',
  notes: '',
  auto_allocate: true,
  invoice_allocations: []
})

// Computed helpers
const availableCurrencies = computed<Currency[]>(() => {
  if (!selectedCustomer.value) return []
  const base = propsCurrencies.find(c => c.code === companyBaseCurrency.value)
  const secondary = propsCurrencies.filter(c => c.code !== (base?.code || ''))
  return base ? [base, ...secondary] : secondary
})

const totalOutstanding = computed(() => outstandingInvoices.value.reduce((s, i) => s + i.balance_due, 0))
const outstandingCurrency = computed(() => ({ code: outstandingInvoices.value[0]?.currency?.code || companyBaseCurrency.value }))

// Form validation
const isFormValid = computed(() => {
  return form.customer_id &&
         form.amount > 0 &&
         form.currency_id &&
         form.payment_method &&
         form.payment_date
})



// Maximum allowed amount — if invoice applied, limit to its balance
const maxAmount = computed(() => {
  if (form.invoice_id) {
    const inv = customerInvoices.value.find(i => i.invoice_id === form.invoice_id)
    return inv ? inv.balance_due : Number.MAX_SAFE_INTEGER
  }
  return Number.MAX_SAFE_INTEGER
})

// Form reactivity: when customer changes, load invoices and default currency
watch(() => form.customer_id, (val) => {
  if (!val) {
    selectedCustomer.value = null
    customerInvoices.value = []
    outstandingInvoices.value = []
    selectedCurrency.value = null
    form.invoice_id = null
    form.invoice_allocations = []
    return
  }

  const customer = propsCustomers.find(c => c.customer_id === val) || null
  selectedCustomer.value = customer
  customerInvoices.value = propsInvoices.filter(inv => inv.customer_id === val)
  outstandingInvoices.value = customerInvoices.value.filter(inv => inv.balance_due > 0)

  // Pick default currency: prefer customer's currency (if provided), else company base
  const preferredCurrency = propsCurrencies.find(c => c.id === customer?.currency_id) || propsCurrencies.find(c => c.code === companyBaseCurrency.value)
  if (preferredCurrency) {
    form.currency_id = preferredCurrency.id
    selectedCurrency.value = preferredCurrency
  }

  // reset invoice-specific values
  form.invoice_id = null
  allocations.value = []
})

// When invoice chosen, pre-fill amount and allocations and set currency to invoice's currency
watch(() => form.invoice_id, (val) => {
  if (!val) {
    allocations.value = []
    return
  }
  const invoice = customerInvoices.value.find(i => i.invoice_id === val)
  if (!invoice) return

  // Check for overpayment
  if (form.amount > invoice.balance_due) {
    selectedInvoice.value = invoice
    overpaymentAmount.value = form.amount - invoice.balance_due
    showOverpaymentModal.value = true
  } else {
    form.amount = invoice.balance_due
    addAllocation(invoice, invoice.balance_due)
  }

  if (invoice.currency) {
    const cur = propsCurrencies.find(c => c.code === invoice.currency.code) || null
    if (cur) {
      form.currency_id = cur.id
      selectedCurrency.value = cur
    }
  }
})

// Watch auto_allocate changes
watch(() => form.auto_allocate, (val) => {
  if (val && selectedCustomer.value && form.amount > 0) {
    autoAllocate()
  } else if (!val) {
    // Clear allocations when switching to manual
    allocations.value = []
  }
})

// Watch form amount for auto-allocation
watch(() => form.amount, (val) => {
  if (form.auto_allocate && selectedCustomer.value && val > 0) {
    autoAllocate()
  }
})

// Watch currency id to sync selectedCurrency
watch(() => form.currency_id, (val) => {
  selectedCurrency.value = propsCurrencies.find(c => c.id === val) || null
})

// Preview computed values
const previewCustomerName = computed(() => {
  const c = propsCustomers.find(x => x.customer_id === form.customer_id)
  return c?.name || ''
})
const previewCustomerEmail = computed(() => {
  const c = propsCustomers.find(x => x.customer_id === form.customer_id)
  return c?.email || ''
})
const previewInvoiceNumber = computed(() => {
  const inv = customerInvoices.value.find(i => i.invoice_id === form.invoice_id)
  return inv?.invoice_number || ''
})
const previewCurrency = computed(() => ({ code: selectedCurrency.value?.code || companyBaseCurrency.value }))
const paymentMethodLabel = computed(() => paymentMethods.find(m => m.value === form.payment_method)?.label || '—')

// Allocation computed properties
const totalApplied = computed(() => {
  return allocations.value.reduce((sum, allocation) => sum + allocation.applied_amount, 0)
})

const remainingPayment = computed(() => {
  return Math.max(0, (form.amount || 0) - totalApplied.value)
})

const remainingForOtherInvoices = computed(() => {
  if (!selectedInvoice.value || !overpaymentAmount.value) return 0
  const otherInvoicesTotal = outstandingInvoices.value
    .filter(inv => inv.invoice_id !== selectedInvoice.value?.invoice_id)
    .reduce((sum, inv) => sum + inv.balance_due, 0)
  return Math.min(overpaymentAmount.value, otherInvoicesTotal)
})

const remainingAfterAllocation = computed(() => {
  return Math.max(0, overpaymentAmount.value - remainingForOtherInvoices.value)
})

// Helper functions
const formatDate = (d: string | Date) => {
  const date = typeof d === 'string' ? new Date(d) : d
  return format(date, 'MMM dd, yyyy')
}

const getPaymentMethodIcon = (method: string) => {
  const icons: Record<string, string> = {
    cash: '💵',
    check: '📝',
    bank_transfer: '🏦',
    credit_card: '💳',
    debit_card: '💳',
    paypal: '🅿️',
    stripe: '💎',
    other: '💰'
  }
  return icons[method] || '💰'
}

function onCurrencyChange() {
  // small hook — currently handled by watcher; keep for side-effects later
}

// Allocation methods
const updateAllocation = (invoiceId: number, amount: number) => {
  const allocation = allocations.value.find(a => a.invoice_id === invoiceId)
  if (allocation) {
    allocation.applied_amount = amount
  }
}

const removeAllocation = (invoiceId: number) => {
  allocations.value = allocations.value.filter(a => a.invoice_id !== invoiceId)
  
  // If we removed the selected invoice, clear it from form
  if (form.invoice_id === invoiceId) {
    form.invoice_id = null
  }
}

const addAllocation = (invoice: Invoice, amount: number) => {
  const existing = allocations.value.find(a => a.invoice_id === invoice.invoice_id)
  if (existing) {
    existing.applied_amount = Math.min(amount, invoice.balance_due)
  } else {
    allocations.value.push({
      invoice_id: invoice.invoice_id,
      invoice_number: invoice.invoice_number,
      invoice_date: invoice.invoice_date,
      balance_due: invoice.balance_due,
      currency_code: invoice.currency?.code || companyBaseCurrency.value,
      applied_amount: Math.min(amount, invoice.balance_due),
      exchange_rate: 1 // Default exchange rate
    })
  }
}

const autoAllocate = () => {
  if (!selectedCustomer.value || !form.amount) return
  
  // Clear existing allocations
  allocations.value = []
  
  // Sort invoices by due date, then invoice date
  const sortedInvoices = [...outstandingInvoices.value].sort((a, b) => {
    const dateA = new Date(a.invoice_date).getTime()
    const dateB = new Date(b.invoice_date).getTime()
    return dateA - dateB
  })
  
  let remainingAmount = form.amount
  
  for (const invoice of sortedInvoices) {
    if (remainingAmount <= 0) break
    
    const appliedAmount = Math.min(remainingAmount, invoice.balance_due)
    addAllocation(invoice, appliedAmount)
    remainingAmount -= appliedAmount
  }
}

const applyOverpaymentOption = () => {
  if (!selectedInvoice.value || !overpaymentAmount.value) return
  
  showOverpaymentModal.value = false
  
  switch (overpaymentOption.value) {
    case 'auto_allocate':
      // Apply full amount to selected invoice
      addAllocation(selectedInvoice.value, form.amount || 0)
      
      // Auto-allocate remaining to other invoices
      const otherInvoices = outstandingInvoices.value
        .filter(inv => inv.invoice_id !== selectedInvoice.value?.invoice_id)
        .sort((a, b) => new Date(a.invoice_date).getTime() - new Date(b.invoice_date).getTime())
      
      let remainingForOthers = overpaymentAmount.value
      for (const invoice of otherInvoices) {
        if (remainingForOthers <= 0) break
        const appliedAmount = Math.min(remainingForOthers, invoice.balance_due)
        addAllocation(invoice, appliedAmount)
        remainingForOthers -= appliedAmount
      }
      break
      
    case 'credit_only':
      // Apply full amount to selected invoice (even if it's over the balance)
      addAllocation(selectedInvoice.value, form.amount || 0)
      break
      
    case 'manual':
      // Just close modal and let user handle manually
      break
  }
}

const submit = () => {
  // Ensure numeric values are sane
  if (form.amount <= 0) {
    form.setError('amount', 'Amount must be greater than 0')
    return
  }

  // Validate allocations exist
  if (allocations.value.length === 0) {
    form.setError('customer_id', 'Please allocate payment to at least one invoice')
    return
  }

  // Check if any allocation exceeds invoice balance
  const invalidAllocation = allocations.value.find(a => a.applied_amount > a.balance_due)
  if (invalidAllocation) {
    form.setError('amount', `Amount applied to ${invalidAllocation.invoice_number} exceeds its balance due`)
    return
  }

  form.post(route('payments.store'), {
    ...form,
    auto_allocate: form.auto_allocate && allocations.value.length === 0,
    invoice_allocations: allocations.value.map(a => ({
      invoice_id: a.invoice_id,
      applied_amount: a.applied_amount,
      applied_currency_id: propsCurrencies.find(c => c.code === a.currency_code)?.id,
      exchange_rate: a.exchange_rate
    })),
    remainder_handling: {
      strategy: remainingPayment.value > 0 ? remainderStrategy.value : 'credit'
    },
    onSuccess: () => {},
    onError: () => {}
  })
}

const cancel = () => router.visit(route('payments.index'))

// Set up page actions
const { setActions, clearActions } = usePageActions()

// Register page actions
setActions([
  {
    label: 'Create Payment',
    icon: 'pi pi-check',
    click: submit,
    disabled: () => !isFormValid.value,
    severity: 'primary'
  },
  {
    label: 'Cancel',
    icon: 'pi pi-times',
    click: cancel,
    severity: 'secondary',
    outlined: true
  }
])

// Print preview: open a new window with a printable receipt
function printPreview() {
  const html = `<!DOCTYPE html>
    <html>
      <head>
        <title>Payment Preview - ${form.payment_number}</title>
        <style>
          body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 40px;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            background: #f8f9fa;
          }
          .receipt {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
          }
          .header{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #e9ecef;
          }
          .company-name {
            font-size: 24px;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 5px;
          }
          .payment-number {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
          }
          .date {
            color: #6b7280;
            font-size: 14px;
          }
          .section {
            margin: 20px 0;
            padding: 15px 0;
          }
          .section-title {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
          }
          .section-content {
            font-weight: 600;
            font-size: 16px;
            color: #374151;
          }
          .amount {
            font-size: 32px;
            font-weight: 700;
            color: #059669;
            text-align: right;
          }
          .amount-section {
            background: #f0fdf4;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
          }
          .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
          }
          .notes {
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            border-left: 3px solid #3b82f6;
          }
          .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
          }
        </style>
      </head>
      <body>
        <div class="receipt">
          <div class="header">
            <div>
              <div class="company-name">💰 ${companyName.value}</div>
              <div class="payment-number">Payment #${form.payment_number}</div>
            </div>
            <div class="date">${formatDate(form.payment_date)}</div>
          </div>

          <div class="details">
            <div class="section">
              <div class="section-title">👤 Customer</div>
              <div class="section-content">${previewCustomerName.value || '—'}</div>
              <div style="color: #6b7280; font-size: 14px; margin-top: 2px;">${previewCustomerEmail.value || ''}</div>
            </div>
            <div class="section">
              <div class="section-title">📄 Applied To</div>
              <div class="section-content">${previewInvoiceNumber.value || 'Not applied to specific invoice'}</div>
            </div>
          </div>

          <div class="amount-section">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <div class="section-title">💳 Payment Amount</div>
                <div style="font-size: 14px; color: #6b7280;">Method: ${paymentMethodLabel.value}</div>
              </div>
              <div class="amount">${formatMoney(Number(form.amount || 0), previewCurrency.value)}</div>
            </div>
          </div>

          ${form.notes ? `<div class="notes">
            <div class="section-title">📝 Notes</div>
            <div style="color: #374151; margin-top: 5px;">${form.notes}</div>
          </div>` : ''}

          <div style="margin-top: 25px; font-size: 12px; color: #6b7280;">
            <div style="margin-bottom: 8px;">
              <strong>Payment Details:</strong>
            </div>
            <div>• Auto-allocate: ${form.auto_allocate ? 'Enabled' : 'Disabled'}</div>
            <div>• Reference: ${form.reference_number || 'Auto-generated'}</div>
            <div>• Currency: ${previewCurrency.value}</div>
          </div>

          <div class="footer">
            <div>Thank you for your payment! 🙏</div>
            <div style="margin-top: 5px;">Generated on ${new Date().toLocaleString()}</div>
          </div>
        </div>
        <script>
          window.onload = function(){
            setTimeout(() => window.print(), 500);
          }
        <\/script>
      </body>
    </html>`

  const w = window.open('', '_blank', 'width=800,height=1000')
  if (!w) return
  w.document.write(html)
  w.document.close()
}

// Download PDF: experimental (requires server or client lib); fallback to print
function downloadPdf() {
  // For now we reuse print preview — the UX can be improved by integrating html2pdf or server-side PDF generation
  printPreview()
}

onMounted(() => {
  // preselect when server passed selectedInvoice
  if (props.selectedInvoice) {
    form.customer_id = props.selectedInvoice.customer_id
    // watch will load invoices and set currency
    setTimeout(() => {
      form.invoice_id = props.selectedInvoice?.invoice_id || null
    }, 0)
  }

  // ensure currency object is in sync
  selectedCurrency.value = propsCurrencies.find(c => c.id === form.currency_id) || null
})

// Clean up actions on unmount
onBeforeUnmount(() => {
  clearActions()
})
</script>
