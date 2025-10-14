import { ref, reactive, computed, watch } from 'vue'
import { formatMoney } from '@/Utils/formatting'

export interface PaymentAllocation {
  invoice_id: number | string
  invoice_number: string
  balance_due: number
  applied_amount: number
  currency_code: string
  currency?: any
}

export interface CustomerInvoice {
  id: number | string
  invoice_number: string
  balance_due: number
  currency?: any
  currency_code: string
  invoice_date: string
  due_date: string
  status: string
}

export interface UsePaymentAllocationOptions {
  initialAllocations?: PaymentAllocation[]
  customerInvoices?: CustomerInvoice[]
  paymentAmount?: number
  currency?: any
}

export function usePaymentAllocation(options: UsePaymentAllocationOptions = {}) {
  const {
    initialAllocations = [],
    customerInvoices = [],
    paymentAmount = 0,
    currency = null
  } = options

  // State
  const allocations = reactive<PaymentAllocation[]>([...initialAllocations])
  const selectedInvoice = ref<number | string | null>(null)
  const autoAllocate = ref(false)
  const remainderStrategy = ref<'credit' | 'unapplied'>('credit')
  const overpaymentOption = ref<'apply' | 'distribute' | 'credit'>('credit')
  const showOverpaymentModal = ref(false)

  // Computed properties
  const totalApplied = computed(() => {
    return allocations.reduce((sum, allocation) => sum + allocation.applied_amount, 0)
  })

  const remainingPayment = computed(() => {
    return Math.max(0, paymentAmount - totalApplied.value)
  })

  const hasOverpayment = computed(() => {
    return selectedInvoice.value && remainingPayment.value > 0
  })

  const overpaymentAmount = computed(() => {
    if (!selectedInvoice.value) return 0
    const allocation = allocations.find(a => a.invoice_id === selectedInvoice.value)
    if (!allocation) return 0
    const invoice = customerInvoices.find(i => i.id === selectedInvoice.value)
    if (!invoice) return 0
    return Math.max(0, allocation.applied_amount - invoice.balance_due)
  })

  const remainingForOtherInvoices = computed(() => {
    if (!selectedInvoice.value || !hasOverpayment.value) return remainingPayment.value
    const invoice = customerInvoices.find(i => i.id === selectedInvoice.value)
    if (!invoice) return remainingPayment.value
    return Math.max(0, remainingPayment.value - (overpaymentAmount.value - invoice.balance_due))
  })

  const remainingAfterAllocation = computed(() => {
    if (overpaymentOption.value === 'apply' && selectedInvoice.value) {
      return Math.max(0, remainingPayment.value - overpaymentAmount.value)
    }
    return remainingPayment.value
  })

  const outstandingInvoices = computed(() => {
    return customerInvoices.filter(invoice => invoice.balance_due > 0)
  })

  const totalOutstanding = computed(() => {
    return outstandingInvoices.value.reduce((sum, invoice) => sum + invoice.balance_due, 0)
  })

  const allocationSummary = computed(() => {
    return {
      total_applied: totalApplied.value,
      remaining_amount: remainingPayment.value,
      overpayment_amount: overpaymentAmount.value,
      allocations: allocations.map(allocation => ({
        invoice_id: allocation.invoice_id,
        applied_amount: allocation.applied_amount
      }))
    }
  })

  // Methods
  const addAllocation = (invoice: CustomerInvoice, amount: number) => {
    const existingIndex = allocations.findIndex(a => a.invoice_id === invoice.id)
    
    if (existingIndex >= 0) {
      allocations[existingIndex].applied_amount = amount
    } else {
      allocations.push({
        invoice_id: invoice.id,
        invoice_number: invoice.invoice_number,
        balance_due: invoice.balance_due,
        applied_amount: amount,
        currency_code: invoice.currency_code,
        currency: invoice.currency
      })
    }
  }

  const updateAllocation = (invoiceId: number | string, amount: number) => {
    const allocation = allocations.find(a => a.invoice_id === invoiceId)
    if (allocation) {
      allocation.applied_amount = Math.max(0, Math.min(amount, allocation.balance_due))
    }
  }

  const removeAllocation = (invoiceId: number | string) => {
    const index = allocations.findIndex(a => a.invoice_id === invoiceId)
    if (index >= 0) {
      allocations.splice(index, 1)
    }
  }

  const clearAllocations = () => {
    allocations.length = 0
    selectedInvoice.value = null
  }

  const autoAllocatePayments = () => {
    if (!autoAllocate.value || paymentAmount <= 0) {
      clearAllocations()
      return
    }

    clearAllocations()
    
    // Sort by due date (oldest first)
    const sortedInvoices = [...outstandingInvoices.value].sort((a, b) => 
      new Date(a.due_date).getTime() - new Date(b.due_date).getTime()
    )

    let remaining = paymentAmount

    for (const invoice of sortedInvoices) {
      if (remaining <= 0) break

      const amount = Math.min(remaining, invoice.balance_due)
      addAllocation(invoice, amount)
      remaining -= amount
    }
  }

  const applyOverpayment = () => {
    if (!selectedInvoice.value || !hasOverpayment.value) return

    const invoice = customerInvoices.find(i => i.id === selectedInvoice.value)
    if (!invoice) return

    switch (overpaymentOption.value) {
      case 'apply':
        // Keep the overpayment allocation as is
        break
      case 'distribute':
        // Distribute remaining to other invoices
        const remainingAfterSelected = remainingForOtherInvoices.value
        if (remainingAfterSelected > 0) {
          const otherInvoices = outstandingInvoices.value.filter(i => i.id !== selectedInvoice.value)
          let remaining = remainingAfterSelected

          for (const inv of otherInvoices) {
            if (remaining <= 0) break
            const amount = Math.min(remaining, inv.balance_due)
            addAllocation(inv, amount)
            remaining -= amount
          }
        }
        break
      case 'credit':
        // Remove excess allocation, creating credit
        const allocation = allocations.find(a => a.invoice_id === selectedInvoice.value)
        if (allocation) {
          allocation.applied_amount = invoice.balance_due
        }
        break
    }

    showOverpaymentModal.value = false
  }

  const validateAllocations = () => {
    const errors: string[] = []

    if (allocations.length === 0) {
      errors.push('Please allocate payment to at least one invoice')
    }

    for (const allocation of allocations) {
      if (allocation.applied_amount <= 0) {
        errors.push(`Allocation for invoice ${allocation.invoice_number} must be greater than 0`)
      }
      if (allocation.applied_amount > allocation.balance_due) {
        errors.push(`Allocation for invoice ${allocation.invoice_number} cannot exceed balance due`)
      }
    }

    if (totalApplied.value > paymentAmount) {
      errors.push('Total allocated amount cannot exceed payment amount')
    }

    return errors
  }

  const prepareForSubmission = () => {
    return allocations.map(allocation => ({
      invoice_id: allocation.invoice_id,
      applied_amount: allocation.applied_amount
    }))
  }

  // Watchers
  watch(autoAllocate, autoAllocatePayments, { immediate: true })
  watch([() => paymentAmount, () => customerInvoices], () => {
    if (autoAllocate.value) {
      autoAllocatePayments()
    }
  })

  watch(selectedInvoice, (newValue) => {
    if (newValue && !autoAllocate.value) {
      // When selecting a specific invoice, clear other allocations
      const allocationsToKeep = allocations.filter(a => a.invoice_id === newValue)
      allocations.splice(0, allocations.length, ...allocationsToKeep)
      
      // If this invoice doesn't have an allocation yet, add it with full payment amount
      if (!allocationsToKeep.length && paymentAmount > 0) {
        const invoice = customerInvoices.find(i => i.id === newValue)
        if (invoice) {
          addAllocation(invoice, paymentAmount)
        }
      }
    }
  })

  return {
    // State
    allocations,
    selectedInvoice,
    autoAllocate,
    remainderStrategy,
    overpaymentOption,
    showOverpaymentModal,
    
    // Computed
    totalApplied,
    remainingPayment,
    hasOverpayment,
    overpaymentAmount,
    remainingForOtherInvoices,
    remainingAfterAllocation,
    outstandingInvoices,
    totalOutstanding,
    allocationSummary,
    
    // Methods
    addAllocation,
    updateAllocation,
    removeAllocation,
    clearAllocations,
    autoAllocatePayments,
    applyOverpayment,
    validateAllocations,
    prepareForSubmission
  }
}