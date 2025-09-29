import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { formatMoney } from '@/Utils/formatting'

export interface InvoiceItem {
  id?: number | string
  description: string
  quantity: number
  unit_price: number
  tax_rate: number
}

export interface InvoiceFormData {
  customer_id?: string | number | null
  currency_id?: string | number | null
  invoice_number: string
  invoice_date: string
  due_date: string
  notes: string
  terms: string
  items: InvoiceItem[]
}

export interface UseInvoiceFormOptions {
  isEdit?: boolean
  initialData?: Partial<InvoiceFormData>
  nextInvoiceNumber?: string
  customers: Array<any>
  currencies: Array<any>
  submitRoute: string
  submitMethod?: 'POST' | 'PUT'
  onSuccess?: () => void
  onError?: () => void
}

export function useInvoiceForm(options: UseInvoiceFormOptions) {
  const {
    isEdit = false,
    initialData = {},
    nextInvoiceNumber = '',
    customers,
    currencies,
    submitRoute,
    submitMethod = isEdit ? 'PUT' : 'POST',
    onSuccess,
    onError
  } = options

  // Form initialization
  const form = reactive<InvoiceFormData>({
    customer_id: null,
    currency_id: null,
    invoice_number: initialData.invoice_number || nextInvoiceNumber,
    invoice_date: initialData.invoice_date || new Date().toISOString().split('T')[0],
    due_date: initialData.due_date || '',
    notes: initialData.notes || '',
    terms: initialData.terms || '',
    items: initialData.items || [{
      id: Date.now(),
      description: '',
      quantity: 1,
      unit_price: 0,
      tax_rate: 0
    }]
  })

  // Set initial due date if not provided
  if (!initialData.due_date && form.invoice_date) {
    const dueDate = new Date(form.invoice_date)
    dueDate.setDate(dueDate.getDate() + 30) // Default 30 days
    form.due_date = dueDate.toISOString().split('T')[0]
  }

  // Computed properties
  const selectedCustomer = computed(() => {
    return customers.find(c => 
      c.customer_id === form.customer_id || c.id === form.customer_id
    )
  })

  const selectedCurrency = computed(() => {
    return currencies.find(c => 
      c.currency_id === form.currency_id || c.id === form.currency_id
    )
  })

  // Calculations
  const calculations = computed(() => {
    const subtotal = form.items.reduce((sum, item) => {
      return sum + (item.quantity * item.unit_price)
    }, 0)

    const tax = form.items.reduce((sum, item) => {
      const itemTotal = item.quantity * item.unit_price
      return sum + (itemTotal * (item.tax_rate / 100))
    }, 0)

    const total = subtotal + tax

    return { subtotal, tax, total }
  })

  // Currency formatting
  const formatCurrency = (amount: number) => {
    if (selectedCurrency.value) {
      return formatMoney(amount, selectedCurrency.value)
    }
    return formatMoney(amount, 'USD')
  }

  // Customer selection handler
  const onCustomerChange = () => {
    // Auto-set currency if not already set
    if (!form.currency_id && selectedCustomer.value?.currency_id) {
      form.currency_id = selectedCustomer.value.currency_id
    }

    // Update due date based on payment terms
    if (selectedCustomer.value?.payment_terms && form.invoice_date) {
      const dueDate = new Date(form.invoice_date)
      dueDate.setDate(dueDate.getDate() + selectedCustomer.value.payment_terms)
      form.due_date = dueDate.toISOString().split('T')[0]
    }
  }

  // Date change handler
  const onInvoiceDateChange = () => {
    if (form.invoice_date && selectedCustomer.value?.payment_terms) {
      const dueDate = new Date(form.invoice_date)
      dueDate.setDate(dueDate.getDate() + selectedCustomer.value.payment_terms)
      form.due_date = dueDate.toISOString().split('T')[0]
    }
  }

  // Line item management
  const addItem = () => {
    form.items.push({
      id: Date.now(),
      description: '',
      quantity: 1,
      unit_price: 0,
      tax_rate: 0
    })
  }

  const removeItem = (index: number) => {
    if (form.items.length > 1) {
      form.items.splice(index, 1)
    }
  }

  const updateItem = (index: number, field: keyof InvoiceItem, value: any) => {
    if (form.items[index]) {
      form.items[index][field] = value
    }
  }

  // Validation
  const validateForm = () => {
    const errors: Record<string, string> = {}

    if (!form.customer_id) {
      errors.customer_id = 'Please select a customer'
    }

    if (!form.currency_id) {
      errors.currency_id = 'Please select a currency'
    }

    if (!form.invoice_date) {
      errors.invoice_date = 'Please enter an invoice date'
    }

    if (!form.due_date) {
      errors.due_date = 'Please enter a due date'
    }

    // Validate line items
    form.items.forEach((item, index) => {
      if (!item.description) {
        errors[`items.${index}.description`] = 'Description is required'
      }
      if (item.quantity <= 0) {
        errors[`items.${index}.quantity`] = 'Quantity must be greater than 0'
      }
      if (item.unit_price < 0) {
        errors[`items.${index}.unit_price`] = 'Unit price cannot be negative'
      }
    })

    return errors
  }

  // Form submission
  const submitForm = () => {
    const errors = validateForm()
    
    if (Object.keys(errors).length > 0) {
      // Set errors on form (assuming form has errors property)
      if ('errors' in form) {
        Object.assign((form as any).errors, errors)
      }
      onError?.()
      return false
    }

    // Prepare data for submission
    const submitData = {
      ...form,
      items: form.items.map(item => ({
        ...item,
        // Remove temporary IDs for new items
        id: typeof item.id === 'string' && item.id.startsWith('temp-') ? null : item.id
      }))
    }

    router[submitMethod.toLowerCase() as 'post' | 'put'](submitRoute, submitData, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        onSuccess?.()
      },
      onError: (errors) => {
        if ('errors' in form) {
          Object.assign((form as any).errors, errors)
        }
        onError?.()
      }
    })

    return true
  }

  // Reset form
  const resetForm = () => {
    Object.assign(form, {
      customer_id: null,
      currency_id: null,
      invoice_number: nextInvoiceNumber,
      invoice_date: new Date().toISOString().split('T')[0],
      due_date: '',
      notes: '',
      terms: '',
      items: [{
        id: Date.now(),
        description: '',
        quantity: 1,
        unit_price: 0,
        tax_rate: 0
      }]
    })

    // Reset due date
    const dueDate = new Date()
    dueDate.setDate(dueDate.getDate() + 30)
    form.due_date = dueDate.toISOString().split('T')[0]
  }

  // Watchers
  watch(() => form.customer_id, onCustomerChange)
  watch(() => form.invoice_date, onInvoiceDateChange)

  return {
    form,
    selectedCustomer,
    selectedCurrency,
    calculations,
    formatCurrency,
    addItem,
    removeItem,
    updateItem,
    validateForm,
    submitForm,
    resetForm
  }
}