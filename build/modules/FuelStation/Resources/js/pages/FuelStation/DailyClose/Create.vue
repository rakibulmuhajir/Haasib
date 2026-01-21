<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import TankLevelGauge from '../../../components/TankLevelGauge.vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogClose,
} from '@/components/ui/dialog'
import type { BreadcrumbItem } from '@/types'
import {
  Fuel,
  Droplets,
  Wallet,
  ArrowDownRight,
  Calculator,
  Plus,
  Trash2,
  CheckCircle,
  AlertCircle,
  Save,
  Loader2,
  FileWarning,
  RotateCcw,
} from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface FuelItem {
  id: string
  name: string
  fuel_category: string
  avg_cost: number
  sale_price: number
}

interface Tank {
  id: string
  code: string
  name: string
  capacity: number
  linked_item_id: string
  dip_stick_id: string | null
  linked_item?: { id: string; name: string; fuel_category: string }
  dip_stick?: { id: string; code: string; name: string; unit: string }
}

interface Pump {
  id: string
  name: string
  tank_id: string
  current_meter_reading: number
  nozzle_count: number
  tank?: { id: string; name: string; linked_item_id: string }
}

interface Nozzle {
  id: string
  code: string
  label: string | null
  pump_id: string
  pump_name: string | null
  tank_id: string
  tank_name: string | null
  item_id: string
  fuel_name: string | null
  fuel_category: string | null
  has_electronic_meter: boolean
  opening_reading: number
  opening_manual: number | null
  sale_rate: number
}

interface Partner {
  id: string
  name: string
  drawing_limit_period: string
  drawing_limit_amount: number | null
  current_period_withdrawn: number
}

interface Employee {
  id: string
  first_name: string
  last_name: string
  position: string
  base_salary: number
}

interface BankAccount {
  id: string
  code: string
  name: string
}

interface ExpenseAccount {
  id: string
  code: string
  name: string
}

interface LubricantItem {
  id: string
  name: string
  sku: string
  brand: string | null
  unit: string
  sale_price: number
}

interface PaymentChannel {
  code: string
  label: string
  type: 'cash' | 'bank_transfer' | 'card_pos' | 'fuel_card' | 'mobile_wallet'
  enabled: boolean
  bank_account_id: string | null
  clearing_account_id: string | null
}

interface Features {
  has_partners: boolean
  has_amanat: boolean
  has_lubricant_sales: boolean
  has_investors: boolean
  dual_meter_readings: boolean
}

const props = defineProps<{
  company: { id: string; name: string; slug: string; base_currency: string }
  date: string
  fuelItems: FuelItem[]
  rates: Record<string, { purchase_rate: number; sale_rate: number }>
  tanks: Tank[]
  pumps: Pump[]
  nozzles: Nozzle[]
  partners: Partner[]
  employees: Employee[]
  bankAccounts: BankAccount[]
  expenseAccounts: ExpenseAccount[]
  lubricantItems: LubricantItem[]
  existingTankReadings: any[]
  previousTankReadings: Array<{ tank_id: string; liters: number; stick_reading: number }>
  previousClose: { date: string | null; closing_cash: number; exists: boolean }
  paymentChannels: PaymentChannel[]
  features: Features
  fuelVendor: string
  fuelCardLabel: string
  isAmendment?: boolean
  originalTransaction?: {
    id: string
    transaction_number: string
    metadata: Record<string, unknown>
  } | null
  originalFormData?: {
    nozzle_readings?: Array<{
      nozzle_id: string
      item_id: string
      opening_electronic: number
      closing_electronic: number
      opening_manual?: number
      closing_manual?: number
      liters_sold: number
      sale_rate: number
    }>
    other_sales?: Array<{
      item_id: string
      item_name: string
      quantity: number
      unit_price: number
      amount: number
    }>
    tank_readings?: Array<{
      tank_id: string
      stick_reading: number
      liters: number
    }>
    opening_cash?: number
    closing_cash?: number
    partner_deposits?: Array<{ partner_id: string; amount: number }>
    payment_receipts?: Record<string, { entries: Array<{ reference?: string; last_four?: string; amount: number }> }>
    bank_deposits?: Array<{ bank_account_id: string; amount: number; reference?: string; purpose?: string }>
    partner_withdrawals?: Array<{ partner_id: string; amount: number }>
    employee_advances?: Array<{ employee_id: string; amount: number; reason?: string }>
    amanat_disbursements?: Array<{ customer_name: string; amount: number }>
    expenses?: Array<{ account_id: string; description: string; amount: number }>
    notes?: string
  }
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fuel', href: `/${props.company.slug}/fuel/dashboard` },
  { title: 'Daily Close', href: `/${props.company.slug}/fuel/daily-close` },
])

const activeTab = ref('sales')
const currency = computed(() => currencySymbol(props.company.base_currency || 'PKR'))

// Amendment mode
const isAmendmentMode = computed(() => props.isAmendment && props.originalTransaction !== null)
const amendmentReason = ref('')

// localStorage draft management
const currentDraftDate = ref(props.date)
const DRAFT_KEY = computed(() => `daily-close-draft-${props.company.id}-${currentDraftDate.value}`)
const showDraftRestoreDialog = ref(false)
const hasDraft = ref(false)
const draftTimestamp = ref<string | null>(null)
const suppressDateChange = ref(false)

// Check if form has meaningful data worth saving
const hasFormData = (formData: Record<string, unknown>): boolean => {
  // Check nozzle readings - any closing reading entered
  const nozzleReadings = formData.nozzle_readings as Array<{ closing_electronic: number }> | undefined
  if (nozzleReadings?.some(r => r.closing_electronic > 0)) return true

  // Check other sales (lubricants)
  const otherSales = formData.other_sales as Array<{ amount: number }> | undefined
  if (otherSales && otherSales.length > 0) return true

  // Check tank readings
  const tankReadings = formData.tank_readings as Array<{ stick_reading: number; liters: number }> | undefined
  if (tankReadings?.some(t => t.stick_reading > 0 || t.liters > 0)) return true

  // Check partner deposits
  const partnerDeposits = formData.partner_deposits as Array<{ amount: number }> | undefined
  if (partnerDeposits && partnerDeposits.length > 0) return true

  // Check payment receipts
  const paymentReceipts = formData.payment_receipts as Record<string, { entries: Array<{ amount: number }> }> | undefined
  if (paymentReceipts) {
    for (const channelCode of Object.keys(paymentReceipts)) {
      if (paymentReceipts[channelCode]?.entries?.length > 0) return true
    }
  }

  // Check bank deposits
  const bankDeposits = formData.bank_deposits as Array<{ amount: number }> | undefined
  if (bankDeposits && bankDeposits.length > 0) return true

  // Check partner withdrawals
  const partnerWithdrawals = formData.partner_withdrawals as Array<{ amount: number }> | undefined
  if (partnerWithdrawals && partnerWithdrawals.length > 0) return true

  // Check employee advances
  const employeeAdvances = formData.employee_advances as Array<{ amount: number }> | undefined
  if (employeeAdvances && employeeAdvances.length > 0) return true

  // Check amanat disbursements
  const amanatDisbursements = formData.amanat_disbursements as Array<{ amount: number }> | undefined
  if (amanatDisbursements && amanatDisbursements.length > 0) return true

  // Check expenses
  const expenses = formData.expenses as Array<{ amount: number }> | undefined
  if (expenses && expenses.length > 0) return true

  // Check closing cash
  const closingCash = formData.closing_cash as number | undefined
  if (closingCash && closingCash > 0) return true

  // Check notes
  const notes = formData.notes as string | undefined
  if (notes && notes.trim().length > 0) return true

  return false
}

// Check for existing draft for a specific date
const checkForDraft = (date: string) => {
  if (isAmendmentMode.value) return

  const draftKey = `daily-close-draft-${props.company.id}-${date}`
  const savedDraft = localStorage.getItem(draftKey)

  if (savedDraft) {
    try {
      const parsed = JSON.parse(savedDraft)
      // Only show restore dialog if draft has meaningful data
      if (parsed.formData && hasFormData(parsed.formData)) {
        hasDraft.value = true
        draftTimestamp.value = parsed.savedAt
        showDraftRestoreDialog.value = true
      } else {
        // Draft exists but has no meaningful data - remove it
        localStorage.removeItem(draftKey)
        hasDraft.value = false
        draftTimestamp.value = null
      }
    } catch {
      localStorage.removeItem(draftKey)
      hasDraft.value = false
      draftTimestamp.value = null
    }
  } else {
    hasDraft.value = false
    draftTimestamp.value = null
  }
}

// Check for existing draft on mount
onMounted(() => {
  checkForDraft(props.date)
})

// Auto-save draft every 30 seconds
let draftSaveInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  if (!isAmendmentMode.value) {
    draftSaveInterval = setInterval(saveDraft, 30000)
  }
})

onUnmounted(() => {
  if (draftSaveInterval) {
    clearInterval(draftSaveInterval)
  }
})

const saveDraft = () => {
  if (isAmendmentMode.value) return

  const formData = form.data()

  // Only save if there's meaningful data
  if (!hasFormData(formData)) {
    // Remove any existing empty draft
    localStorage.removeItem(DRAFT_KEY.value)
    return
  }

  const draftData = {
    savedAt: new Date().toISOString(),
    formData: formData,
  }
  localStorage.setItem(DRAFT_KEY.value, JSON.stringify(draftData))
}

const restoreDraft = () => {
  const savedDraft = localStorage.getItem(DRAFT_KEY.value)
  if (savedDraft) {
    try {
      const parsed = JSON.parse(savedDraft)
      const savedData = parsed.formData

      // Restore form data
      Object.keys(savedData).forEach(key => {
        if (key in form) {
          (form as any)[key] = savedData[key]
        }
      })

      toast.success('Draft restored', { description: 'Your previous work has been loaded' })
    } catch {
      toast.error('Failed to restore draft')
    }
  }
  showDraftRestoreDialog.value = false
}

const discardDraft = () => {
  localStorage.removeItem(DRAFT_KEY.value)
  showDraftRestoreDialog.value = false
  hasDraft.value = false
}

const clearDraftOnSuccess = () => {
  localStorage.removeItem(DRAFT_KEY.value)
}

// Get enabled payment channels
const enabledChannels = computed(() => {
  return (props.paymentChannels || []).filter(ch => ch.enabled)
})

// Channels grouped by type for UI sections
const bankTransferChannels = computed(() => enabledChannels.value.filter(ch => ch.type === 'bank_transfer'))
const cardPosChannels = computed(() => enabledChannels.value.filter(ch => ch.type === 'card_pos'))
const fuelCardChannels = computed(() => enabledChannels.value.filter(ch => ch.type === 'fuel_card'))
const mobileWalletChannels = computed(() => enabledChannels.value.filter(ch => ch.type === 'mobile_wallet'))

// Group nozzles by pump for display
const nozzlesByPump = computed(() => {
  const grouped: Record<string, { pump_id: string; pump_name: string; fuel_name: string; fuel_category: string; nozzle_indices: number[] }> = {}
  form.nozzle_readings.forEach((reading, index) => {
    const pumpId = reading.pump_id
    if (!grouped[pumpId]) {
      grouped[pumpId] = {
        pump_id: pumpId,
        pump_name: reading.pump_name || 'Unknown Pump',
        fuel_name: reading.fuel_name || '',
        fuel_category: reading.fuel_category || '',
        nozzle_indices: []
      }
    }
    grouped[pumpId].nozzle_indices.push(index)
  })
  return Object.values(grouped)
})

// Get pump total liters and amount
const getPumpTotalLiters = (nozzleIndices: number[]) => {
  return nozzleIndices.reduce((sum, idx) => sum + form.nozzle_readings[idx].liters_sold, 0)
}

const getPumpTotalAmount = (nozzleIndices: number[]) => {
  return nozzleIndices.reduce((sum, idx) => {
    const r = form.nozzle_readings[idx]
    return sum + (r.liters_sold * r.sale_rate)
  }, 0)
}

// Form data
const form = useForm({
  date: props.date,

  // Tab 1: Nozzle readings (each nozzle has electronic + optional manual readings)
  nozzle_readings: props.nozzles.map(nozzle => ({
    nozzle_id: nozzle.id,
    nozzle_code: nozzle.code,
    nozzle_label: nozzle.label,
    item_id: nozzle.item_id,
    fuel_name: nozzle.fuel_name,
    fuel_category: nozzle.fuel_category,
    pump_id: nozzle.pump_id,
    pump_name: nozzle.pump_name,
    has_electronic_meter: nozzle.has_electronic_meter,
    opening_electronic: nozzle.opening_reading,
    closing_electronic: 0,
    opening_manual: nozzle.opening_manual ?? null,
    closing_manual: null as number | null,
    liters_sold: 0,
    sale_rate: nozzle.sale_rate,
  })),

  other_sales: [] as { item_id: string; item_name: string; quantity: number; unit_price: number; amount: number }[],

  // Tab 2: Tank readings - include previous day's closing for variance calculation
  tank_readings: props.tanks.map(tank => {
    const prevReading = props.previousTankReadings?.find(r => r.tank_id === tank.id)
    return {
      tank_id: tank.id,
      tank_name: tank.name,
      tank_code: tank.code,
      capacity: tank.capacity,
      fuel_name: tank.linked_item?.name || '',
      fuel_category: tank.linked_item?.fuel_category || '',
      dip_stick_code: tank.dip_stick?.code || '',
      previous_liters: prevReading?.liters ?? 0,
      previous_stick: prevReading?.stick_reading ?? 0,
      stick_reading: 0,
      liters: 0,
    }
  }),

  // Tab 3: Money In - Dynamic payment receipts
  opening_cash: props.previousClose.closing_cash || 0,
  partner_deposits: [] as { partner_id: string; partner_name: string; amount: number }[],

  // Dynamic payment channel receipts (money coming in via non-cash methods)
  payment_receipts: {} as Record<string, { entries: Array<{ reference: string; amount: number; customer_name?: string; last_four?: string }> }>,

  // Tab 4: Money Out
  bank_deposits: [] as { bank_account_id: string; amount: number; reference: string; purpose: string }[],
  partner_withdrawals: [] as { partner_id: string; partner_name: string; amount: number }[],
  employee_advances: [] as { employee_id: string; employee_name: string; amount: number; reason: string }[],
  amanat_disbursements: [] as { customer_name: string; amount: number }[],
  expenses: [] as { account_id: string; account_name: string; description: string; amount: number }[],

  // Tab 5: Summary
  closing_cash: 0,
  cash_variance: 0,
  notes: '',
})

// Reset form to initial empty state (preserving structure from props)
const resetFormToInitial = () => {
  // Reset nozzle readings - keep structure but clear entered values
  form.nozzle_readings = props.nozzles.map(nozzle => ({
    nozzle_id: nozzle.id,
    nozzle_code: nozzle.code,
    nozzle_label: nozzle.label,
    item_id: nozzle.item_id,
    fuel_name: nozzle.fuel_name,
    fuel_category: nozzle.fuel_category,
    pump_id: nozzle.pump_id,
    pump_name: nozzle.pump_name,
    has_electronic_meter: nozzle.has_electronic_meter,
    opening_electronic: nozzle.opening_reading,
    closing_electronic: 0,
    opening_manual: nozzle.opening_manual ?? null,
    closing_manual: null,
    liters_sold: 0,
    sale_rate: nozzle.sale_rate,
  }))

  // Reset other sales
  form.other_sales = []

  // Reset tank readings - keep structure but clear entered values
  form.tank_readings = props.tanks.map(tank => {
    const prevReading = props.previousTankReadings?.find(r => r.tank_id === tank.id)
    return {
      tank_id: tank.id,
      tank_name: tank.name,
      tank_code: tank.code,
      capacity: tank.capacity,
      fuel_name: tank.linked_item?.name || '',
      fuel_category: tank.linked_item?.fuel_category || '',
      dip_stick_code: tank.dip_stick?.code || '',
      previous_liters: prevReading?.liters ?? 0,
      previous_stick: prevReading?.stick_reading ?? 0,
      stick_reading: 0,
      liters: 0,
    }
  })

  // Reset money in
  form.opening_cash = props.previousClose.closing_cash || 0
  form.partner_deposits = []

  // Reset payment receipts - reinitialize empty structure for each channel
  form.payment_receipts = {}
  enabledChannels.value.forEach(channel => {
    if (channel.type !== 'cash') {
      form.payment_receipts[channel.code] = { entries: [] }
    }
  })

  // Reset money out
  form.bank_deposits = []
  form.partner_withdrawals = []
  form.employee_advances = []
  form.amanat_disbursements = []
  form.expenses = []

  // Reset summary
  form.closing_cash = 0
  form.cash_variance = 0
  form.notes = ''

  // Reset tab saved states
  tabsSaved.value = {
    sales: false,
    tanks: false,
    moneyIn: false,
    moneyOut: false,
  }
}

// Watch for date changes and check for draft (must be after form is defined)
watch(() => form.date, (newDate, oldDate) => {
  if (suppressDateChange.value) {
    return
  }
  if (newDate !== oldDate) {
    // Save current draft before switching dates (only if there's meaningful data)
    if (oldDate && !isAmendmentMode.value) {
      const formData = form.data()
      if (hasFormData(formData)) {
        const oldDraftKey = `daily-close-draft-${props.company.id}-${oldDate}`
        const draftData = {
          savedAt: new Date().toISOString(),
          formData: formData,
        }
        localStorage.setItem(oldDraftKey, JSON.stringify(draftData))
      }
    }

    if (isAmendmentMode.value) {
      suppressDateChange.value = true
      form.date = oldDate ?? props.date
      nextTick(() => {
        suppressDateChange.value = false
      })
      return
    }

    router.get(
      `/${props.company.slug}/fuel/daily-close`,
      { date: newDate },
      { preserveScroll: true, preserveState: false, replace: true },
    )
  }
})

// Initialize payment_receipts for each enabled non-cash channel
enabledChannels.value.forEach(channel => {
  if (channel.type !== 'cash') {
    form.payment_receipts[channel.code] = { entries: [] }
  }
})

// Hydrate form with original data when in amendment mode
const hydrateFormForAmendment = () => {
  if (!props.isAmendment || !props.originalFormData) return

  const orig = props.originalFormData

  // Hydrate nozzle readings - match by nozzle_id
  if (orig.nozzle_readings) {
    const origReadingsMap = new Map(orig.nozzle_readings.map(r => [r.nozzle_id, r]))
    form.nozzle_readings.forEach((nozzle, idx) => {
      const origReading = origReadingsMap.get(nozzle.nozzle_id)
      if (origReading) {
        form.nozzle_readings[idx].opening_electronic = origReading.opening_electronic ?? nozzle.opening_electronic
        form.nozzle_readings[idx].closing_electronic = origReading.closing_electronic ?? 0
        form.nozzle_readings[idx].opening_manual = origReading.opening_manual ?? null
        form.nozzle_readings[idx].closing_manual = origReading.closing_manual ?? null
        form.nozzle_readings[idx].liters_sold = origReading.liters_sold ?? 0
        form.nozzle_readings[idx].sale_rate = origReading.sale_rate ?? nozzle.sale_rate
      }
    })
  }

  // Hydrate other sales
  if (orig.other_sales && orig.other_sales.length > 0) {
    form.other_sales = orig.other_sales.map(sale => ({
      item_id: sale.item_id,
      item_name: sale.item_name,
      quantity: sale.quantity,
      unit_price: sale.unit_price,
      amount: sale.amount,
    }))
  }

  // Hydrate tank readings - match by tank_id
  if (orig.tank_readings) {
    const origTankMap = new Map(orig.tank_readings.map(t => [t.tank_id, t]))
    form.tank_readings.forEach((tank, idx) => {
      const origTank = origTankMap.get(tank.tank_id)
      if (origTank) {
        form.tank_readings[idx].stick_reading = origTank.stick_reading ?? 0
        form.tank_readings[idx].liters = origTank.liters ?? 0
      }
    })
  }

  // Hydrate money values
  if (orig.opening_cash !== undefined) form.opening_cash = orig.opening_cash
  if (orig.closing_cash !== undefined) form.closing_cash = orig.closing_cash

  // Hydrate partner deposits
  if (orig.partner_deposits && orig.partner_deposits.length > 0) {
    form.partner_deposits = orig.partner_deposits.map(pd => {
      const partner = props.partners.find(p => p.id === pd.partner_id)
      return {
        partner_id: pd.partner_id,
        partner_name: partner?.name ?? '',
        amount: pd.amount,
      }
    })
  }

  // Hydrate payment receipts
  if (orig.payment_receipts) {
    Object.keys(orig.payment_receipts).forEach(channelCode => {
      const origChannel = orig.payment_receipts![channelCode]
      if (origChannel?.entries) {
        form.payment_receipts[channelCode] = {
          entries: origChannel.entries.map(e => ({
            reference: e.reference ?? '',
            last_four: e.last_four ?? '',
            amount: e.amount,
          })),
        }
      }
    })
  }

  // Hydrate bank deposits
  if (orig.bank_deposits && orig.bank_deposits.length > 0) {
    form.bank_deposits = orig.bank_deposits.map(bd => ({
      bank_account_id: bd.bank_account_id,
      amount: bd.amount,
      reference: bd.reference ?? '',
      purpose: bd.purpose ?? '',
    }))
  }

  // Hydrate partner withdrawals
  if (orig.partner_withdrawals && orig.partner_withdrawals.length > 0) {
    form.partner_withdrawals = orig.partner_withdrawals.map(pw => {
      const partner = props.partners.find(p => p.id === pw.partner_id)
      return {
        partner_id: pw.partner_id,
        partner_name: partner?.name ?? '',
        amount: pw.amount,
      }
    })
  }

  // Hydrate employee advances
  if (orig.employee_advances && orig.employee_advances.length > 0) {
    form.employee_advances = orig.employee_advances.map(ea => {
      const employee = props.employees.find(e => e.id === ea.employee_id)
      return {
        employee_id: ea.employee_id,
        employee_name: employee ? `${employee.first_name} ${employee.last_name}` : '',
        amount: ea.amount,
        reason: ea.reason ?? '',
      }
    })
  }

  // Hydrate amanat disbursements
  if (orig.amanat_disbursements && orig.amanat_disbursements.length > 0) {
    form.amanat_disbursements = orig.amanat_disbursements.map(ad => ({
      customer_name: ad.customer_name,
      amount: ad.amount,
    }))
  }

  // Hydrate expenses
  if (orig.expenses && orig.expenses.length > 0) {
    form.expenses = orig.expenses.map(exp => {
      const account = props.expenseAccounts.find(a => a.id === exp.account_id)
      return {
        account_id: exp.account_id,
        account_name: account?.name ?? '',
        description: exp.description,
        amount: exp.amount,
      }
    })
  }

  // Hydrate notes
  if (orig.notes) form.notes = orig.notes
}

// Run hydration on mount if in amendment mode
onMounted(() => {
  if (props.isAmendment && props.originalFormData) {
    hydrateFormForAmendment()
  }
})

// Helper to add entry to a payment channel
const addPaymentEntry = (channelCode: string) => {
  if (!form.payment_receipts[channelCode]) {
    form.payment_receipts[channelCode] = { entries: [] }
  }
  form.payment_receipts[channelCode].entries.push({ reference: '', amount: 0 })
}

// Helper to remove entry from a payment channel
const removePaymentEntry = (channelCode: string, index: number) => {
  if (form.payment_receipts[channelCode]) {
    form.payment_receipts[channelCode].entries.splice(index, 1)
  }
}

// Get total for a specific payment channel
const getChannelTotal = (channelCode: string): number => {
  const entries = form.payment_receipts[channelCode]?.entries || []
  return entries.reduce((sum, e) => sum + (e.amount || 0), 0)
}

// Computed calculations

// Calculate liters sold per tank (from nozzle readings)
const litersSoldByTank = computed(() => {
  const byTank: Record<string, number> = {}
  form.nozzle_readings.forEach(r => {
    // Find the nozzle's tank from props
    const nozzle = props.nozzles.find(n => n.id === r.nozzle_id)
    if (nozzle?.tank_id) {
      byTank[nozzle.tank_id] = (byTank[nozzle.tank_id] || 0) + r.liters_sold
    }
  })
  return byTank
})

// Calculate tank variance (shrinkage/gain)
// Formula: Previous Closing - Today's Closing = Usage from Dip
// If Usage from Dip > Liters Sold => Loss (shrinkage/evaporation)
// If Usage from Dip < Liters Sold => Gain (rare, might indicate measurement error)
const tankVariances = computed(() => {
  return form.tank_readings.map(tank => {
    const soldFromTank = litersSoldByTank.value[tank.tank_id] || 0
    const usageFromDip = tank.previous_liters - tank.liters // What dip says was used
    const variance = usageFromDip - soldFromTank // Positive = loss, Negative = gain

    return {
      tank_id: tank.tank_id,
      tank_name: tank.tank_name,
      fuel_category: tank.fuel_category,
      previous_liters: tank.previous_liters,
      current_liters: tank.liters,
      usage_from_dip: usageFromDip,
      liters_sold: soldFromTank,
      variance: variance,
      variance_percent: soldFromTank > 0 ? (variance / soldFromTank) * 100 : 0,
    }
  })
})

// Total variance across all tanks
const totalTankVariance = computed(() => {
  return tankVariances.value.reduce((sum, v) => sum + v.variance, 0)
})

const getTankFillPercent = (tank: { capacity?: number; liters?: number; previous_liters?: number }): number => {
  const capacity = Number(tank.capacity ?? 0)
  if (capacity <= 0) return 0
  const liters = Number(tank.liters > 0 ? tank.liters : tank.previous_liters ?? 0)
  return Math.min(100, Math.max(0, Math.round((liters / capacity) * 100)))
}

const totalFuelSales = computed(() => {
  return form.nozzle_readings.reduce((sum, r) => {
    return sum + (r.liters_sold * r.sale_rate)
  }, 0)
})

const totalOtherSales = computed(() => {
  return form.other_sales.reduce((sum, s) => sum + s.amount, 0)
})

const totalSales = computed(() => totalFuelSales.value + totalOtherSales.value)

// Sales breakdown by fuel type for summary
const salesByFuelType = computed(() => {
  const byFuel: Record<string, { fuel_name: string; fuel_category: string; liters: number; amount: number }> = {}
  form.nozzle_readings.forEach(r => {
    const key = r.item_id
    if (!byFuel[key]) {
      byFuel[key] = {
        fuel_name: r.fuel_name || 'Unknown',
        fuel_category: r.fuel_category || '',
        liters: 0,
        amount: 0,
      }
    }
    byFuel[key].liters += r.liters_sold
    byFuel[key].amount += r.liters_sold * r.sale_rate
  })
  return Object.values(byFuel).filter(f => f.liters > 0)
})

// Partner deposits total
const totalPartnerDeposits = computed(() => {
  return form.partner_deposits.reduce((sum, d) => sum + d.amount, 0)
})

// Money out breakdown totals
const totalBankDeposits = computed(() => {
  return form.bank_deposits.reduce((sum, d) => sum + d.amount, 0)
})

const totalPartnerWithdrawals = computed(() => {
  return form.partner_withdrawals.reduce((sum, w) => sum + w.amount, 0)
})

const totalEmployeeAdvances = computed(() => {
  return form.employee_advances.reduce((sum, a) => sum + a.amount, 0)
})

const totalAmanatDisbursements = computed(() => {
  return form.amanat_disbursements.reduce((sum, a) => sum + a.amount, 0)
})

const totalExpenses = computed(() => {
  return form.expenses.reduce((sum, e) => sum + e.amount, 0)
})

// Total of all non-cash payment receipts
const totalNonCashReceipts = computed(() => {
  let total = 0
  for (const channelCode of Object.keys(form.payment_receipts)) {
    total += getChannelTotal(channelCode)
  }
  return total
})

const cashSales = computed(() => {
  return totalSales.value - totalNonCashReceipts.value
})

const totalMoneyIn = computed(() => {
  const partnerDeposits = form.partner_deposits.reduce((sum, d) => sum + d.amount, 0)
  return form.opening_cash + partnerDeposits + totalSales.value
})

const totalCardAndBank = computed(() => {
  return totalNonCashReceipts.value
})

const totalMoneyOut = computed(() => {
  const bankDeposits = form.bank_deposits.reduce((sum, d) => sum + d.amount, 0)
  const partnerWithdrawals = form.partner_withdrawals.reduce((sum, w) => sum + w.amount, 0)
  const employeeAdvances = form.employee_advances.reduce((sum, a) => sum + a.amount, 0)
  const amanat = form.amanat_disbursements.reduce((sum, a) => sum + a.amount, 0)
  const expenses = form.expenses.reduce((sum, e) => sum + e.amount, 0)

  return bankDeposits + partnerWithdrawals + employeeAdvances + amanat + expenses
})

const expectedClosingCash = computed(() => {
  const cashIn = form.opening_cash +
    form.partner_deposits.reduce((sum, d) => sum + d.amount, 0) +
    (totalSales.value - totalCardAndBank.value)

  return cashIn - totalMoneyOut.value
})

const cashVariance = computed(() => {
  return form.closing_cash - expectedClosingCash.value
})

// Watch for closing reading changes to auto-calculate liters (from electronic readings)
watch(
  () => form.nozzle_readings.map(r => ({ o: r.opening_electronic, c: r.closing_electronic })),
  (readings) => {
    readings.forEach((r, i) => {
      form.nozzle_readings[i].liters_sold = Math.max(0, r.c - r.o)
    })
  },
  { deep: true }
)

// Add/remove helpers
const addOtherSale = () => {
  form.other_sales.push({ item_id: '', item_name: '', quantity: 1, unit_price: 0, amount: 0 })
}

const removeOtherSale = (index: number) => {
  form.other_sales.splice(index, 1)
}

// When lubricant item is selected, populate name and price
const setOtherSaleItem = (index: number) => {
  const item = props.lubricantItems.find(i => i.id === form.other_sales[index].item_id)
  if (item) {
    form.other_sales[index].item_name = item.name
    form.other_sales[index].unit_price = item.sale_price
    recalculateOtherSaleAmount(index)
  }
}

// Recalculate amount when quantity or price changes
const recalculateOtherSaleAmount = (index: number) => {
  const sale = form.other_sales[index]
  sale.amount = sale.quantity * sale.unit_price
}

const addPartnerDeposit = () => {
  form.partner_deposits.push({ partner_id: '', partner_name: '', amount: 0 })
}

const removePartnerDeposit = (index: number) => {
  form.partner_deposits.splice(index, 1)
}

const addBankDeposit = () => {
  form.bank_deposits.push({ bank_account_id: '', amount: 0, reference: '', purpose: '' })
}

const removeBankDeposit = (index: number) => {
  form.bank_deposits.splice(index, 1)
}

const addPartnerWithdrawal = () => {
  form.partner_withdrawals.push({ partner_id: '', partner_name: '', amount: 0 })
}

const removePartnerWithdrawal = (index: number) => {
  form.partner_withdrawals.splice(index, 1)
}

const addEmployeeAdvance = () => {
  form.employee_advances.push({ employee_id: '', employee_name: '', amount: 0, reason: '' })
}

const removeEmployeeAdvance = (index: number) => {
  form.employee_advances.splice(index, 1)
}

const addAmanat = () => {
  form.amanat_disbursements.push({ customer_name: '', amount: 0 })
}

const removeAmanat = (index: number) => {
  form.amanat_disbursements.splice(index, 1)
}

const addExpense = () => {
  form.expenses.push({ account_id: '', account_name: '', description: '', amount: 0 })
}

const removeExpense = (index: number) => {
  form.expenses.splice(index, 1)
}

// Partner/Employee name helpers
const setPartnerName = (index: number, field: 'deposits' | 'withdrawals') => {
  const list = field === 'deposits' ? form.partner_deposits : form.partner_withdrawals
  const partner = props.partners.find(p => p.id === list[index].partner_id)
  if (partner) list[index].partner_name = partner.name
}

const setEmployeeName = (index: number) => {
  const employee = props.employees.find(e => e.id === form.employee_advances[index].employee_id)
  if (employee) form.employee_advances[index].employee_name = `${employee.first_name} ${employee.last_name}`
}

const setExpenseAccountName = (index: number) => {
  const account = props.expenseAccounts.find(a => a.id === form.expenses[index].account_id)
  if (account) form.expenses[index].account_name = account.name
}

// Track which tabs have been saved/validated
const tabsSaved = ref({
  sales: false,
  tanks: false,
  moneyIn: false,
  moneyOut: false,
})

// Per-tab validation functions (local save with toast feedback)
const saveSales = () => {
  // Validate: check if all nozzles have closing readings
  const missingReadings = form.nozzle_readings.filter(r => r.closing_electronic <= r.opening_electronic)
  if (missingReadings.length > 0 && totalFuelSales.value === 0) {
    toast.error('Please enter closing readings for all nozzles')
    return
  }

  tabsSaved.value.sales = true
  toast.success('Sales data saved', { description: `Total: ${currency.value} ${formatCurrency(totalSales.value)}` })
}

const saveTanks = () => {
  // Validate: check if tank readings are entered
  const emptyTanks = form.tank_readings.filter(t => t.stick_reading === 0 && t.liters === 0)
  if (emptyTanks.length === form.tank_readings.length) {
    toast.warning('No tank readings entered', { description: 'You can continue without tank readings' })
  } else {
    toast.success('Tank readings saved')
  }
  tabsSaved.value.tanks = true
}

const saveMoneyIn = () => {
  tabsSaved.value.moneyIn = true
  toast.success('Money In saved', { description: `Opening cash: ${currency.value} ${formatCurrency(form.opening_cash)}` })
}

const saveMoneyOut = () => {
  tabsSaved.value.moneyOut = true
  const total = totalMoneyOut.value
  toast.success('Money Out saved', { description: `Total outflows: ${currency.value} ${formatCurrency(total)}` })
}

// Final submit - posts everything to server
const submitting = ref(false)

// Clean form data before submission - filter out incomplete entries
const getCleanedFormData = () => {
  const data = form.data()

  // Filter out incomplete expenses (missing account_id or amount)
  data.expenses = (data.expenses || []).filter(
    (e: { account_id: string; amount: number }) => e.account_id && e.amount > 0
  )

  // Filter out incomplete partner deposits
  data.partner_deposits = (data.partner_deposits || []).filter(
    (d: { partner_id: string; amount: number }) => d.partner_id && d.amount > 0
  )

  // Filter out incomplete bank deposits
  data.bank_deposits = (data.bank_deposits || []).filter(
    (d: { bank_account_id: string; amount: number }) => d.bank_account_id && d.amount > 0
  )

  // Filter out incomplete partner withdrawals
  data.partner_withdrawals = (data.partner_withdrawals || []).filter(
    (w: { partner_id: string; amount: number }) => w.partner_id && w.amount > 0
  )

  // Filter out incomplete employee advances
  data.employee_advances = (data.employee_advances || []).filter(
    (a: { employee_id: string; amount: number }) => a.employee_id && a.amount > 0
  )

  // Filter out incomplete amanat disbursements
  data.amanat_disbursements = (data.amanat_disbursements || []).filter(
    (a: { customer_name: string; amount: number }) => a.customer_name && a.amount > 0
  )

  // Filter out incomplete other sales
  data.other_sales = (data.other_sales || []).filter(
    (s: { item_id: string; amount: number }) => s.item_id && s.amount > 0
  )

  // Clean payment receipts - filter out empty entries from each channel
  if (data.payment_receipts) {
    for (const channelCode of Object.keys(data.payment_receipts)) {
      if (data.payment_receipts[channelCode]?.entries) {
        data.payment_receipts[channelCode].entries = data.payment_receipts[channelCode].entries.filter(
          (e: { amount: number }) => e.amount > 0
        )
      }
    }
  }

  return data
}

const submitDailyClose = () => {
  // Validate closing cash is entered
  if (form.closing_cash <= 0) {
    toast.error('Please enter the actual closing cash amount')
    return
  }

  // For amendments, require a reason
  if (isAmendmentMode.value && amendmentReason.value.length < 10) {
    toast.error('Please provide a reason for the amendment (at least 10 characters)')
    return
  }

  submitting.value = true
  form.cash_variance = cashVariance.value

  const cleanedData = getCleanedFormData()

  if (isAmendmentMode.value && props.originalTransaction) {
    // Amendment submission
    router.post(`/${props.company.slug}/fuel/daily-close/${props.originalTransaction.id}/amend`, {
      ...cleanedData,
      amendment_reason: amendmentReason.value,
    }, {
      preserveScroll: true,
      onError: (errors) => {
        const firstError = Object.values(errors)[0]
        toast.error('Failed to post amendment', { description: firstError as string })
      },
      onFinish: () => { submitting.value = false }
    })
  } else {
    // Normal submission
    router.post(`/${props.company.slug}/fuel/daily-close`, cleanedData, {
      preserveScroll: true,
      onSuccess: (page) => {
        const flash = (page.props as any).flash
        if (flash?.success) {
          clearDraftOnSuccess()
        }
      },
      onError: (errors) => {
        const firstError = Object.values(errors)[0]
        toast.error('Failed to post daily close', { description: firstError as string })
      },
      onFinish: () => { submitting.value = false }
    })
  }
}

// Helper to get nozzle position label (Front/Back)
const getNozzlePosition = (index: number, totalInPump: number): string => {
  if (totalInPump === 1) return ''
  return index === 0 ? 'Front' : 'Back'
}

// Format currency
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PK', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

const tabs = [
  { id: 'sales', label: 'Sales', icon: Fuel },
  { id: 'tanks', label: 'Tank Readings', icon: Droplets },
  { id: 'money-in', label: 'Money In', icon: Wallet },
  { id: 'money-out', label: 'Money Out', icon: ArrowDownRight },
  { id: 'summary', label: 'Summary', icon: Calculator },
]
</script>

<template>
  <Head :title="isAmendmentMode ? `Amend Daily Close - ${originalTransaction?.transaction_number}` : 'Daily Close'" />

  <PageShell
    :title="isAmendmentMode ? 'Amend Daily Close' : 'Daily Close'"
    :description="isAmendmentMode ? `Amending ${originalTransaction?.transaction_number} for ${form.date}` : `Record daily transactions for ${form.date}`"
    :icon="isAmendmentMode ? RotateCcw : Calculator"
    :breadcrumbs="breadcrumbs"
  >
    <!-- Draft Restore Dialog -->
    <Dialog :open="showDraftRestoreDialog" @update:open="showDraftRestoreDialog = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <FileWarning class="h-5 w-5 text-amber-500" />
            Restore Draft?
          </DialogTitle>
          <DialogDescription>
            You have an unsaved draft for this date from {{ draftTimestamp ? new Date(draftTimestamp).toLocaleString() : 'earlier' }}.
            Would you like to restore it?
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="discardDraft">Discard</Button>
          <Button @click="restoreDraft">Restore Draft</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Amendment Banner -->
    <div v-if="isAmendmentMode" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
      <div class="flex items-start gap-3">
        <RotateCcw class="h-5 w-5 text-amber-600 mt-0.5" />
        <div class="flex-1">
          <h3 class="font-medium text-amber-900">Amending Entry: {{ originalTransaction?.transaction_number }}</h3>
          <p class="text-sm text-amber-700 mt-1">
            This will create a reversal of the original entry and post a new corrected entry.
            Both entries will remain in history for audit purposes.
          </p>
          <div class="mt-3">
            <Label for="amendment-reason" class="text-amber-900">Reason for Amendment *</Label>
            <Textarea
              id="amendment-reason"
              v-model="amendmentReason"
              placeholder="Explain why this entry needs to be amended (minimum 10 characters)..."
              class="mt-1"
              :class="{ 'border-red-500': amendmentReason.length > 0 && amendmentReason.length < 10 }"
            />
            <p v-if="amendmentReason.length > 0 && amendmentReason.length < 10" class="text-sm text-red-500 mt-1">
              Please provide at least 10 characters
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Date Selector -->
    <Card class="mb-6">
      <CardContent class="pt-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="space-y-1">
              <Label>Date</Label>
              <Input v-model="form.date" type="date" class="w-48" />
            </div>
            <div v-if="previousClose.exists" class="text-sm text-muted-foreground">
              Previous close: {{ previousClose.date }} - {{ currency }} {{ formatCurrency(previousClose.closing_cash) }}
            </div>
          </div>
          <Badge v-if="cashVariance !== 0" :class="cashVariance > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
            {{ cashVariance > 0 ? 'Over' : 'Short' }}: {{ currency }} {{ formatCurrency(Math.abs(cashVariance)) }}
          </Badge>
        </div>
      </CardContent>
    </Card>

    <!-- Tabbed Content -->
    <Tabs v-model="activeTab" class="space-y-6">
      <TabsList class="grid w-full grid-cols-5">
        <TabsTrigger v-for="tab in tabs" :key="tab.id" :value="tab.id" class="flex items-center gap-2">
          <component :is="tab.icon" class="h-4 w-4" />
          {{ tab.label }}
        </TabsTrigger>
      </TabsList>

      <!-- Tab 1: Sales -->
      <TabsContent value="sales">
        <Card>
          <CardHeader>
            <CardTitle>Fuel Sales</CardTitle>
            <CardDescription>Enter meter readings for each pump</CardDescription>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Nozzle Readings grouped by Pump -->
            <div class="space-y-5">
              <div v-for="pump in nozzlesByPump" :key="pump.pump_id" class="rounded-lg border">
                <!-- Pump Header -->
                <div class="flex items-center justify-between px-5 py-3 bg-muted/40 border-b">
                  <div class="flex items-center gap-3">
                    <div class="text-base font-semibold">{{ pump.pump_name }}</div>
                    <Badge variant="outline">{{ pump.fuel_category }}</Badge>
                    <span class="text-sm text-muted-foreground">{{ pump.fuel_name }}</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-sm text-muted-foreground">Total:</span>
                    <span class="text-lg font-bold">{{ currency }} {{ formatCurrency(getPumpTotalAmount(pump.nozzle_indices)) }}</span>
                  </div>
                </div>

                <!-- Automatic Readings Row - Both nozzles in one row -->
                <div class="px-5 py-4">
                  <!-- Header Row -->
                  <div class="grid grid-cols-12 gap-4 mb-3 text-xs text-muted-foreground font-medium">
                    <div class="col-span-1">Side</div>
                    <div class="col-span-2 text-right">Opening</div>
                    <div class="col-span-2 text-right">Closing</div>
                    <div class="col-span-2 text-right">Liters</div>
                    <div class="col-span-2 text-right">Rate/L</div>
                    <div class="col-span-3 text-right">Amount</div>
                  </div>

                  <!-- Automatic Readings - One row per nozzle but visually grouped -->
                  <div class="space-y-3">
                    <div v-for="(idx, localIdx) in pump.nozzle_indices" :key="form.nozzle_readings[idx].nozzle_id"
                      class="grid grid-cols-12 gap-4 items-center">
                      <!-- Nozzle Label (Front/Back) -->
                      <div class="col-span-1">
                        <span class="text-sm font-medium">{{ getNozzlePosition(localIdx, pump.nozzle_indices.length) || form.nozzle_readings[idx].nozzle_code }}</span>
                      </div>
                      <!-- Opening -->
                      <div class="col-span-2">
                        <Input
                          v-model.number="form.nozzle_readings[idx].opening_electronic"
                          type="number"
                          step="1"
                          class="h-9 text-right bg-muted/30"
                        />
                      </div>
                      <!-- Closing -->
                      <div class="col-span-2">
                        <Input
                          v-model.number="form.nozzle_readings[idx].closing_electronic"
                          type="number"
                          step="1"
                          class="h-9 text-right"
                        />
                      </div>
                      <!-- Liters -->
                      <div class="col-span-2 text-right">
                        <span class="text-base font-semibold">{{ form.nozzle_readings[idx].liters_sold.toFixed(0) }}</span>
                        <span class="text-xs text-muted-foreground ml-1">L</span>
                      </div>
                      <!-- Rate -->
                      <div class="col-span-2">
                        <Input
                          v-model.number="form.nozzle_readings[idx].sale_rate"
                          type="number"
                          step="0.01"
                          class="h-9 text-right"
                        />
                      </div>
                      <!-- Amount -->
                      <div class="col-span-3 text-right">
                        <span class="text-base font-semibold">{{ currency }} {{ formatCurrency(form.nozzle_readings[idx].liters_sold * form.nozzle_readings[idx].sale_rate) }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Manual Readings (optional) -->
                  <div class="mt-4 pt-4 border-t border-dashed">
                    <div class="text-xs text-muted-foreground font-medium mb-1">Manual Readings (daily, optional)</div>
                    <p class="text-xs text-muted-foreground mb-3">
                      Use these to verify the electronic readings for today. Leaving them blank won’t block submission,
                      and you can enter or backdate manual readings later.
                    </p>
                    <div class="grid grid-cols-12 gap-4">
                      <div v-for="(idx, localIdx) in pump.nozzle_indices" :key="'manual-' + form.nozzle_readings[idx].nozzle_id"
                        :class="pump.nozzle_indices.length === 2 ? 'col-span-6' : 'col-span-12'">
                        <div class="flex items-center gap-3 p-3 rounded-md bg-muted/30">
                          <span class="text-sm font-medium w-12">{{ getNozzlePosition(localIdx, pump.nozzle_indices.length) || 'Manual' }}</span>
                          <div class="flex-1 flex items-center gap-2">
                            <Input
                              v-model.number="form.nozzle_readings[idx].opening_manual"
                              type="number"
                              step="1"
                              placeholder="Opening"
                              class="h-8 text-right text-sm"
                            />
                            <span class="text-muted-foreground">→</span>
                            <Input
                              v-model.number="form.nozzle_readings[idx].closing_manual"
                              type="number"
                              step="1"
                              placeholder="Closing"
                              class="h-8 text-right text-sm"
                            />
                          </div>
                          <!-- Variance indicator -->
                          <div class="w-20 text-right">
                            <template v-if="form.nozzle_readings[idx].closing_manual && form.nozzle_readings[idx].opening_manual">
                              <span v-if="Math.abs((form.nozzle_readings[idx].closing_manual - form.nozzle_readings[idx].opening_manual) - form.nozzle_readings[idx].liters_sold) <= 0.5"
                                class="text-green-600 text-sm font-medium">
                                <CheckCircle class="h-4 w-4 inline" />
                              </span>
                              <span v-else class="text-amber-600 text-sm">
                                {{ ((form.nozzle_readings[idx].closing_manual - form.nozzle_readings[idx].opening_manual) - form.nozzle_readings[idx].liters_sold).toFixed(0) }}L
                              </span>
                            </template>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Pump Total Row -->
                  <div class="mt-4 pt-3 border-t flex justify-between items-center">
                    <span class="text-sm font-medium text-muted-foreground">Pump Total</span>
                    <div class="flex items-center gap-6">
                      <span class="text-sm">{{ getPumpTotalLiters(pump.nozzle_indices).toFixed(0) }} L</span>
                      <span class="text-lg font-bold">{{ currency }} {{ formatCurrency(getPumpTotalAmount(pump.nozzle_indices)) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Other Sales (Lubricants, etc.) -->
            <template v-if="features.has_lubricant_sales && lubricantItems.length > 0">
              <Separator />
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <h4 class="font-medium">Other Sales (Lubricants, etc.)</h4>
                  <Button variant="outline" size="sm" @click="addOtherSale">
                    <Plus class="h-4 w-4 mr-1" /> Add Item
                  </Button>
                </div>

                <!-- Header Row -->
                <div v-if="form.other_sales.length > 0" class="grid grid-cols-12 gap-3 text-xs text-muted-foreground font-medium px-1">
                  <div class="col-span-5">Product</div>
                  <div class="col-span-2 text-right">Qty</div>
                  <div class="col-span-2 text-right">Price</div>
                  <div class="col-span-2 text-right">Amount</div>
                  <div class="col-span-1"></div>
                </div>

                <div v-for="(sale, index) in form.other_sales" :key="index" class="grid grid-cols-12 gap-3 items-center">
                  <!-- Product Select -->
                  <div class="col-span-5">
                    <Select v-model="sale.item_id" @update:model-value="setOtherSaleItem(index)">
                      <SelectTrigger>
                        <SelectValue placeholder="Select product" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="item in lubricantItems" :key="item.id" :value="item.id">
                          {{ item.name }}
                          <span v-if="item.brand" class="text-muted-foreground ml-1">({{ item.brand }})</span>
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <!-- Quantity -->
                  <div class="col-span-2">
                    <Input
                      v-model.number="sale.quantity"
                      type="number"
                      min="1"
                      step="1"
                      class="text-right"
                      @input="recalculateOtherSaleAmount(index)"
                    />
                  </div>
                  <!-- Unit Price -->
                  <div class="col-span-2">
                    <Input
                      v-model.number="sale.unit_price"
                      type="number"
                      step="0.01"
                      class="text-right"
                      @input="recalculateOtherSaleAmount(index)"
                    />
                  </div>
                  <!-- Amount (calculated) -->
                  <div class="col-span-2 text-right font-semibold">
                    {{ currency }} {{ formatCurrency(sale.amount) }}
                  </div>
                  <!-- Delete -->
                  <div class="col-span-1 text-right">
                    <Button variant="ghost" size="icon" @click="removeOtherSale(index)">
                      <Trash2 class="h-4 w-4 text-destructive" />
                    </Button>
                  </div>
                </div>

                <!-- Subtotal -->
                <div v-if="form.other_sales.length > 0" class="flex justify-end pt-2 border-t">
                  <div class="text-sm">
                    <span class="text-muted-foreground mr-2">Lubricant Sales:</span>
                    <span class="font-semibold">{{ currency }} {{ formatCurrency(totalOtherSales) }}</span>
                  </div>
                </div>
              </div>
            </template>

            <Separator />

            <!-- Sales Summary by Product -->
            <div class="p-4 rounded-lg bg-muted/30 space-y-3">
              <h4 class="font-semibold text-sm">Sales Summary</h4>
              <div class="space-y-2">
                <!-- Fuel Sales by Type -->
                <div v-for="fuel in salesByFuelType" :key="fuel.fuel_name" class="flex justify-between text-sm">
                  <div class="flex items-center gap-2">
                    <Badge variant="outline" class="text-xs">{{ fuel.fuel_category }}</Badge>
                    <span>{{ fuel.fuel_name }}</span>
                    <span class="text-muted-foreground">({{ fuel.liters.toFixed(0) }} L)</span>
                  </div>
                  <span class="font-medium">{{ currency }} {{ formatCurrency(fuel.amount) }}</span>
                </div>
                <!-- Lubricants/Other -->
                <div v-if="totalOtherSales > 0" class="flex justify-between text-sm">
                  <span>Lubricants & Other</span>
                  <span class="font-medium">{{ currency }} {{ formatCurrency(totalOtherSales) }}</span>
                </div>
                <Separator />
                <!-- Grand Total -->
                <div class="flex justify-between text-base font-semibold">
                  <span>Total Sales</span>
                  <span>{{ currency }} {{ formatCurrency(totalSales) }}</span>
                </div>
              </div>
            </div>

            <!-- Footer with Submit -->
            <div class="flex justify-end">
              <Button @click="saveSales" :variant="tabsSaved.sales ? 'outline' : 'default'" class="min-w-32">
                <CheckCircle v-if="tabsSaved.sales" class="h-4 w-4 mr-2 text-green-600" />
                <Save v-else class="h-4 w-4 mr-2" />
                {{ tabsSaved.sales ? 'Saved' : 'Save Sales' }}
              </Button>
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Tab 2: Tank Readings -->
      <TabsContent value="tanks">
        <Card>
          <CardHeader>
            <CardTitle>Tank Dip Readings</CardTitle>
            <CardDescription>Record physical tank measurements and compare with nozzle sales to detect shrinkage</CardDescription>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Tank Reading Cards -->
            <div v-for="(tank, index) in form.tank_readings" :key="tank.tank_id" class="rounded-lg border">
              <!-- Tank Header -->
              <div class="flex items-center justify-between px-4 py-3 bg-muted/40 border-b">
                <div class="flex items-center gap-3">
                  <div class="flex flex-col items-center gap-1">
                    <TankLevelGauge :percent="getTankFillPercent(tank)" :size="36" />
                    <span class="text-[11px] text-muted-foreground">{{ getTankFillPercent(tank) }}%</span>
                  </div>
                  <div>
                    <div class="text-base font-semibold">{{ tank.tank_name }}</div>
                    <div class="flex items-center gap-2">
                      <Badge variant="outline">{{ tank.fuel_category }}</Badge>
                      <span class="text-sm text-muted-foreground">{{ tank.fuel_name }}</span>
                    </div>
                  </div>
                </div>
                <div v-if="tank.dip_stick_code" class="text-xs text-muted-foreground">
                  Dip Stick: {{ tank.dip_stick_code }}
                </div>
              </div>

              <div class="p-4 space-y-4">
                <!-- Readings Row -->
                <div class="grid grid-cols-12 gap-4">
                  <!-- Previous Day's Reading -->
                  <div class="col-span-3">
                    <Label class="text-xs text-muted-foreground">Previous Closing (L)</Label>
                    <div class="text-lg font-semibold mt-1">
                      {{ tank.previous_liters > 0 ? formatCurrency(tank.previous_liters) : '—' }}
                    </div>
                    <div v-if="tank.previous_stick > 0" class="text-xs text-muted-foreground">
                      Stick: {{ tank.previous_stick }} cm
                    </div>
                  </div>

                  <!-- Today's Reading Inputs -->
                  <div class="col-span-2">
                    <Label class="text-xs">Stick Reading (cm)</Label>
                    <Input v-model.number="tank.stick_reading" type="number" step="0.1" placeholder="cm" class="mt-1" />
                  </div>
                  <div class="col-span-2">
                    <Label class="text-xs">Today's Closing (L)</Label>
                    <Input v-model.number="tank.liters" type="number" step="1" class="mt-1" />
                  </div>

                  <!-- Calculated Values -->
                  <div class="col-span-2">
                    <Label class="text-xs text-muted-foreground">Usage (Dip)</Label>
                    <div class="text-base font-medium mt-1">
                      {{ tank.previous_liters > 0 && tank.liters > 0 ? formatCurrency(tank.previous_liters - tank.liters) : '—' }} L
                    </div>
                  </div>
                  <div class="col-span-2">
                    <Label class="text-xs text-muted-foreground">Nozzle Sales</Label>
                    <div class="text-base font-medium mt-1">
                      {{ formatCurrency(litersSoldByTank[tank.tank_id] || 0) }} L
                    </div>
                  </div>

                  <!-- Variance -->
                  <div class="col-span-1">
                    <Label class="text-xs text-muted-foreground">Variance</Label>
                    <div v-if="tank.previous_liters > 0 && tank.liters > 0" class="mt-1">
                      <span
                        :class="[
                          'text-base font-semibold',
                          tankVariances[index]?.variance > 0 ? 'text-red-600' :
                          tankVariances[index]?.variance < 0 ? 'text-amber-600' : 'text-green-600'
                        ]"
                      >
                        {{ tankVariances[index]?.variance > 0 ? '-' : tankVariances[index]?.variance < 0 ? '+' : '' }}{{ Math.abs(tankVariances[index]?.variance || 0).toFixed(0) }} L
                      </span>
                    </div>
                    <div v-else class="text-base text-muted-foreground mt-1">—</div>
                  </div>
                </div>

                <!-- Variance Explanation (if significant) -->
                <div v-if="tank.previous_liters > 0 && tank.liters > 0 && Math.abs(tankVariances[index]?.variance || 0) > 5" class="text-xs px-3 py-2 rounded-md"
                  :class="tankVariances[index]?.variance > 0 ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'"
                >
                  <span v-if="tankVariances[index]?.variance > 0">
                    Loss of {{ Math.abs(tankVariances[index]?.variance).toFixed(0) }}L detected ({{ Math.abs(tankVariances[index]?.variance_percent).toFixed(1) }}% of sales) — may indicate evaporation, leakage, or measurement error
                  </span>
                  <span v-else>
                    Gain of {{ Math.abs(tankVariances[index]?.variance).toFixed(0) }}L detected — may indicate measurement error or unrecorded receipt
                  </span>
                </div>
              </div>
            </div>

            <!-- Summary -->
            <div v-if="form.tank_readings.some(t => t.previous_liters > 0 && t.liters > 0)" class="p-4 rounded-lg bg-muted/50">
              <div class="flex items-center justify-between">
                <div class="text-sm font-medium">Total Variance</div>
                <div
                  :class="[
                    'text-lg font-bold',
                    totalTankVariance > 0 ? 'text-red-600' :
                    totalTankVariance < 0 ? 'text-amber-600' : 'text-green-600'
                  ]"
                >
                  {{ totalTankVariance > 0 ? 'Loss: ' : totalTankVariance < 0 ? 'Gain: ' : '' }}{{ Math.abs(totalTankVariance).toFixed(0) }} L
                </div>
              </div>
            </div>

            <Separator />

            <!-- Submit Button -->
            <div class="flex justify-end">
              <Button @click="saveTanks" :variant="tabsSaved.tanks ? 'outline' : 'default'" class="min-w-32">
                <CheckCircle v-if="tabsSaved.tanks" class="h-4 w-4 mr-2 text-green-600" />
                <Save v-else class="h-4 w-4 mr-2" />
                {{ tabsSaved.tanks ? 'Saved' : 'Save Tank Readings' }}
              </Button>
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Tab 3: Money In -->
      <TabsContent value="money-in">
        <Card>
          <CardHeader>
            <CardTitle>Money Received</CardTitle>
            <CardDescription>Opening cash, cash sales, deposits, and non-cash receipts</CardDescription>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Opening Cash -->
            <div class="p-4 rounded-lg bg-muted/50">
              <div class="flex items-center justify-between">
                <div>
                  <Label>Opening Cash Balance</Label>
                  <p class="text-xs text-muted-foreground">Carried forward from previous day</p>
                </div>
                <div class="w-48">
                  <Input v-model.number="form.opening_cash" type="number" class="text-right text-lg font-semibold" />
                </div>
              </div>
            </div>

            <div class="p-4 rounded-lg border">
              <div class="flex items-center justify-between">
                <div>
                  <Label>Cash Sales (calculated)</Label>
                  <p class="text-xs text-muted-foreground">Fuel + other sales minus non-cash receipts</p>
                </div>
                <div class="text-right">
                  <div class="text-lg font-semibold">{{ currency }} {{ formatCurrency(cashSales) }}</div>
                  <p v-if="cashSales < 0" class="text-xs text-destructive">Non-cash receipts exceed total sales</p>
                </div>
              </div>
            </div>

            <!-- Partner Deposits (only if partners feature enabled) -->
            <template v-if="features.has_partners && partners.length > 0">
              <Separator />

              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <h4 class="font-medium">Partner Deposits</h4>
                  <Button variant="outline" size="sm" @click="addPartnerDeposit">
                    <Plus class="h-4 w-4 mr-1" /> Add
                  </Button>
                </div>

                <div v-for="(deposit, index) in form.partner_deposits" :key="index" class="flex gap-4 items-end">
                  <div class="flex-1">
                    <Label class="text-xs">Partner</Label>
                    <Select v-model="deposit.partner_id" @update:model-value="setPartnerName(index, 'deposits')">
                      <SelectTrigger>
                        <SelectValue placeholder="Select partner" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="p in partners" :key="p.id" :value="p.id">{{ p.name }}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="w-32">
                    <Label class="text-xs">Amount</Label>
                    <Input v-model.number="deposit.amount" type="number" />
                  </div>
                  <Button variant="ghost" size="icon" @click="removePartnerDeposit(index)">
                    <Trash2 class="h-4 w-4 text-destructive" />
                  </Button>
                </div>
              </div>
            </template>

            <Separator />

            <!-- Dynamic Payment Channels -->
            <template v-for="channel in enabledChannels" :key="channel.code">
              <div v-if="channel.type !== 'cash'" class="space-y-4">
                <div class="flex items-center justify-between">
                  <div>
                    <h4 class="font-medium">{{ channel.label }}</h4>
                    <p class="text-xs text-muted-foreground">
                      {{ channel.type === 'bank_transfer' ? 'Bank transfer payments received' : '' }}
                      {{ channel.type === 'card_pos' ? 'Credit/debit card swipes' : '' }}
                      {{ channel.type === 'fuel_card' ? `${fuelCardLabel} sales (goes to clearing)` : '' }}
                      {{ channel.type === 'mobile_wallet' ? 'Mobile wallet payments' : '' }}
                    </p>
                  </div>
                  <Button variant="outline" size="sm" @click="addPaymentEntry(channel.code)">
                    <Plus class="h-4 w-4 mr-1" /> Add
                  </Button>
                </div>

                <div v-for="(entry, index) in form.payment_receipts[channel.code]?.entries || []" :key="index" class="flex gap-4 items-end">
                  <!-- Reference field varies by type -->
                  <div v-if="channel.type === 'card_pos'" class="w-32">
                    <Label class="text-xs">Last 4 Digits</Label>
                    <Input v-model="entry.last_four" maxlength="4" placeholder="1234" />
                  </div>
                  <div v-else class="flex-1">
                    <Label class="text-xs">{{ channel.type === 'bank_transfer' ? 'Customer / Reference' : 'Card / Reference' }}</Label>
                    <Input v-model="entry.reference" :placeholder="channel.type === 'bank_transfer' ? 'Customer name or slip #' : 'Card #'" />
                  </div>
                  <div class="w-32">
                    <Label class="text-xs">Amount</Label>
                    <Input v-model.number="entry.amount" type="number" />
                  </div>
                  <Button variant="ghost" size="icon" @click="removePaymentEntry(channel.code, index)">
                    <Trash2 class="h-4 w-4 text-destructive" />
                  </Button>
                </div>

                <!-- Channel subtotal -->
                <div v-if="(form.payment_receipts[channel.code]?.entries?.length || 0) > 0" class="text-right text-sm text-muted-foreground">
                  Subtotal: {{ currency }} {{ formatCurrency(getChannelTotal(channel.code)) }}
                </div>

                <Separator />
              </div>
            </template>

            <!-- Money In Summary -->
            <div class="p-4 rounded-lg bg-muted/30 space-y-3">
              <h4 class="font-semibold text-sm">Money In Summary</h4>
              <div class="space-y-2">
                <!-- Opening Cash -->
                <div class="flex justify-between text-sm">
                  <span>Opening Cash</span>
                  <span class="font-medium">{{ currency }} {{ formatCurrency(form.opening_cash) }}</span>
                </div>
                <!-- Partner Deposits -->
                <div v-if="totalPartnerDeposits > 0" class="flex justify-between text-sm">
                  <span>Partner Deposits</span>
                  <span class="font-medium">{{ currency }} {{ formatCurrency(totalPartnerDeposits) }}</span>
                </div>
                <!-- Cash Sales -->
                <div class="flex justify-between text-sm">
                  <span>Cash Sales</span>
                  <span class="font-medium">{{ currency }} {{ formatCurrency(cashSales) }}</span>
                </div>
                <Separator />
                <!-- Total Cash Available -->
                <div class="flex justify-between text-sm font-semibold">
                  <span>Total Cash Available</span>
                  <span>{{ currency }} {{ formatCurrency(form.opening_cash + totalPartnerDeposits + cashSales) }}</span>
                </div>
                <Separator class="my-2" />
                <!-- Non-Cash Section -->
                <div class="text-xs text-muted-foreground mb-1">Non-Cash Receipts (goes directly to bank/clearing)</div>
                <template v-for="channel in enabledChannels" :key="'summary-' + channel.code">
                  <div v-if="channel.type !== 'cash' && getChannelTotal(channel.code) > 0" class="flex justify-between text-sm text-muted-foreground">
                    <span>{{ channel.label }}</span>
                    <span>{{ currency }} {{ formatCurrency(getChannelTotal(channel.code)) }}</span>
                  </div>
                </template>
                <div v-if="totalNonCashReceipts > 0" class="flex justify-between text-sm">
                  <span>Total Non-Cash</span>
                  <span class="font-medium">{{ currency }} {{ formatCurrency(totalNonCashReceipts) }}</span>
                </div>
                <Separator />
                <!-- Grand Total -->
                <div class="flex justify-between text-base font-semibold">
                  <span>Total Money In (All Sources)</span>
                  <span>{{ currency }} {{ formatCurrency(form.opening_cash + totalPartnerDeposits + totalSales) }}</span>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end pt-2">
              <Button @click="saveMoneyIn" :variant="tabsSaved.moneyIn ? 'outline' : 'default'" class="min-w-32">
                <CheckCircle v-if="tabsSaved.moneyIn" class="h-4 w-4 mr-2 text-green-600" />
                <Save v-else class="h-4 w-4 mr-2" />
                {{ tabsSaved.moneyIn ? 'Saved' : 'Save Money In' }}
              </Button>
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Tab 4: Money Out -->
      <TabsContent value="money-out">
        <Card>
          <CardHeader>
            <CardTitle>Money Out</CardTitle>
            <CardDescription>Deposits, withdrawals, and expenses</CardDescription>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Bank Deposits -->
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <h4 class="font-medium">Bank Deposits (for Vendor)</h4>
                <Button variant="outline" size="sm" @click="addBankDeposit">
                  <Plus class="h-4 w-4 mr-1" /> Add
                </Button>
              </div>

              <div v-for="(deposit, index) in form.bank_deposits" :key="index" class="grid grid-cols-5 gap-4 items-end">
                <div>
                  <Label class="text-xs">Bank Account</Label>
                  <Select v-model="deposit.bank_account_id">
                    <SelectTrigger>
                      <SelectValue placeholder="Select" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ b.name }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label class="text-xs">Amount</Label>
                  <Input v-model.number="deposit.amount" type="number" />
                </div>
                <div>
                  <Label class="text-xs">Reference</Label>
                  <Input v-model="deposit.reference" placeholder="Slip #" />
                </div>
                <div>
                  <Label class="text-xs">Purpose</Label>
                  <Input v-model="deposit.purpose" placeholder="e.g., vendor card payment" />
                </div>
                <Button variant="ghost" size="icon" @click="removeBankDeposit(index)">
                  <Trash2 class="h-4 w-4 text-destructive" />
                </Button>
              </div>
            </div>

            <!-- Partner Withdrawals (only if partners feature enabled) -->
            <template v-if="features.has_partners && partners.length > 0">
              <Separator />

              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <h4 class="font-medium">Partner Withdrawals</h4>
                  <Button variant="outline" size="sm" @click="addPartnerWithdrawal">
                    <Plus class="h-4 w-4 mr-1" /> Add
                  </Button>
                </div>

                <div v-for="(withdrawal, index) in form.partner_withdrawals" :key="index" class="flex gap-4 items-end">
                  <div class="flex-1">
                    <Label class="text-xs">Partner</Label>
                    <Select v-model="withdrawal.partner_id" @update:model-value="setPartnerName(index, 'withdrawals')">
                      <SelectTrigger>
                        <SelectValue placeholder="Select partner" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="p in partners" :key="p.id" :value="p.id">
                          {{ p.name }}
                          <span v-if="p.drawing_limit_amount" class="text-xs text-muted-foreground ml-2">
                            (Limit: {{ formatCurrency(p.drawing_limit_amount - p.current_period_withdrawn) }} left)
                          </span>
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="w-32">
                    <Label class="text-xs">Amount</Label>
                    <Input v-model.number="withdrawal.amount" type="number" />
                  </div>
                  <Button variant="ghost" size="icon" @click="removePartnerWithdrawal(index)">
                    <Trash2 class="h-4 w-4 text-destructive" />
                  </Button>
                </div>
              </div>
            </template>

            <Separator />

            <!-- Employee Advances -->
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <h4 class="font-medium">Employee Salary Advances</h4>
                <Button variant="outline" size="sm" @click="addEmployeeAdvance">
                  <Plus class="h-4 w-4 mr-1" /> Add
                </Button>
              </div>

              <div v-for="(advance, index) in form.employee_advances" :key="index" class="grid grid-cols-4 gap-4 items-end">
                <div>
                  <Label class="text-xs">Employee</Label>
                  <Select v-model="advance.employee_id" @update:model-value="setEmployeeName(index)">
                    <SelectTrigger>
                      <SelectValue placeholder="Select" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="e in employees" :key="e.id" :value="e.id">
                        {{ e.first_name }} {{ e.last_name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label class="text-xs">Amount</Label>
                  <Input v-model.number="advance.amount" type="number" />
                </div>
                <div>
                  <Label class="text-xs">Reason</Label>
                  <Input v-model="advance.reason" placeholder="Optional" />
                </div>
                <Button variant="ghost" size="icon" @click="removeEmployeeAdvance(index)">
                  <Trash2 class="h-4 w-4 text-destructive" />
                </Button>
              </div>
            </div>

            <!-- Amanat Disbursements (only if amanat feature enabled) -->
            <template v-if="features.has_amanat">
              <Separator />

              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <h4 class="font-medium">Amanat Disbursements</h4>
                  <Button variant="outline" size="sm" @click="addAmanat">
                    <Plus class="h-4 w-4 mr-1" /> Add
                  </Button>
                </div>

                <div v-for="(amanat, index) in form.amanat_disbursements" :key="index" class="flex gap-4 items-end">
                  <div class="flex-1">
                    <Label class="text-xs">Customer Name</Label>
                    <Input v-model="amanat.customer_name" placeholder="Name" />
                  </div>
                  <div class="w-32">
                    <Label class="text-xs">Amount</Label>
                    <Input v-model.number="amanat.amount" type="number" />
                  </div>
                  <Button variant="ghost" size="icon" @click="removeAmanat(index)">
                    <Trash2 class="h-4 w-4 text-destructive" />
                  </Button>
                </div>
              </div>
            </template>

            <Separator />

            <!-- Operating Expenses -->
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <h4 class="font-medium">Operating Expenses</h4>
                <Button variant="outline" size="sm" @click="addExpense">
                  <Plus class="h-4 w-4 mr-1" /> Add
                </Button>
              </div>

              <div v-for="(expense, index) in form.expenses" :key="index" class="grid grid-cols-4 gap-4 items-end">
                <div>
                  <Label class="text-xs">Category</Label>
                  <Select v-model="expense.account_id" @update:model-value="setExpenseAccountName(index)">
                    <SelectTrigger>
                      <SelectValue placeholder="Select" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="a in expenseAccounts" :key="a.id" :value="a.id">{{ a.name }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label class="text-xs">Description</Label>
                  <Input v-model="expense.description" placeholder="Details" />
                </div>
                <div>
                  <Label class="text-xs">Amount</Label>
                  <Input v-model.number="expense.amount" type="number" />
                </div>
                <Button variant="ghost" size="icon" @click="removeExpense(index)">
                  <Trash2 class="h-4 w-4 text-destructive" />
                </Button>
              </div>
            </div>

            <Separator />

            <!-- Money Out Summary -->
            <div class="p-4 rounded-lg bg-muted/30 space-y-3">
              <h4 class="font-semibold text-sm">Money Out Summary</h4>
              <div class="space-y-2">
                <!-- Bank Deposits -->
                <div v-if="totalBankDeposits > 0" class="flex justify-between text-sm">
                  <span>Bank Deposits (Vendor Payments)</span>
                  <span class="font-medium text-destructive">{{ currency }} {{ formatCurrency(totalBankDeposits) }}</span>
                </div>
                <!-- Partner Withdrawals -->
                <div v-if="totalPartnerWithdrawals > 0" class="flex justify-between text-sm">
                  <span>Partner Withdrawals</span>
                  <span class="font-medium text-destructive">{{ currency }} {{ formatCurrency(totalPartnerWithdrawals) }}</span>
                </div>
                <!-- Employee Advances -->
                <div v-if="totalEmployeeAdvances > 0" class="flex justify-between text-sm">
                  <span>Employee Salary Advances</span>
                  <span class="font-medium text-destructive">{{ currency }} {{ formatCurrency(totalEmployeeAdvances) }}</span>
                </div>
                <!-- Amanat Disbursements -->
                <div v-if="totalAmanatDisbursements > 0" class="flex justify-between text-sm">
                  <span>Amanat Disbursements</span>
                  <span class="font-medium text-destructive">{{ currency }} {{ formatCurrency(totalAmanatDisbursements) }}</span>
                </div>
                <!-- Expenses -->
                <div v-if="totalExpenses > 0" class="flex justify-between text-sm">
                  <span>Operating Expenses</span>
                  <span class="font-medium text-destructive">{{ currency }} {{ formatCurrency(totalExpenses) }}</span>
                </div>
                <!-- Empty state -->
                <div v-if="totalMoneyOut === 0" class="text-sm text-muted-foreground text-center py-2">
                  No outflows recorded
                </div>
                <Separator />
                <!-- Total -->
                <div class="flex justify-between text-base font-semibold">
                  <span>Total Money Out</span>
                  <span class="text-destructive">{{ currency }} {{ formatCurrency(totalMoneyOut) }}</span>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
              <Button @click="saveMoneyOut" :variant="tabsSaved.moneyOut ? 'outline' : 'default'" class="min-w-32">
                <CheckCircle v-if="tabsSaved.moneyOut" class="h-4 w-4 mr-2 text-green-600" />
                <Save v-else class="h-4 w-4 mr-2" />
                {{ tabsSaved.moneyOut ? 'Saved' : 'Save Money Out' }}
              </Button>
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Tab 5: Summary -->
      <TabsContent value="summary">
        <Card>
          <CardHeader>
            <CardTitle>Daily Summary</CardTitle>
            <CardDescription>Review and finalize the daily close</CardDescription>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Summary Grid -->
            <div class="grid grid-cols-2 gap-6">
              <!-- Left: Cash Flow -->
              <div class="space-y-4">
                <h4 class="font-semibold">Cash Flow</h4>
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span>Opening Cash</span>
                    <span>{{ currency }} {{ formatCurrency(form.opening_cash) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span>+ Partner Deposits</span>
                    <span>{{ currency }} {{ formatCurrency(form.partner_deposits.reduce((s, d) => s + d.amount, 0)) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span>+ Cash Sales</span>
                    <span>{{ currency }} {{ formatCurrency(totalSales - totalCardAndBank) }}</span>
                  </div>
                  <Separator />
                  <div class="flex justify-between font-medium">
                    <span>Total Cash Available</span>
                    <span>{{ currency }} {{ formatCurrency(form.opening_cash + form.partner_deposits.reduce((s, d) => s + d.amount, 0) + totalSales - totalCardAndBank) }}</span>
                  </div>
                  <div class="flex justify-between text-destructive">
                    <span>- Money Out</span>
                    <span>{{ currency }} {{ formatCurrency(totalMoneyOut) }}</span>
                  </div>
                  <Separator />
                  <div class="flex justify-between font-semibold text-lg">
                    <span>Expected Closing</span>
                    <span>{{ currency }} {{ formatCurrency(expectedClosingCash) }}</span>
                  </div>
                </div>
              </div>

              <!-- Right: Sales Summary (by Pump) -->
              <div class="space-y-4">
                <h4 class="font-semibold">Sales Summary</h4>
                <div class="space-y-2 text-sm">
                  <div v-for="pump in nozzlesByPump" :key="pump.pump_id" class="flex justify-between">
                    <span>{{ pump.pump_name }} - {{ pump.fuel_name }} ({{ getPumpTotalLiters(pump.nozzle_indices).toFixed(0) }}L)</span>
                    <span>{{ currency }} {{ formatCurrency(getPumpTotalAmount(pump.nozzle_indices)) }}</span>
                  </div>
                  <div v-if="totalOtherSales > 0" class="flex justify-between">
                    <span>Other Sales</span>
                    <span>{{ currency }} {{ formatCurrency(totalOtherSales) }}</span>
                  </div>
                  <Separator />
                  <div class="flex justify-between font-semibold">
                    <span>Total Sales</span>
                    <span>{{ currency }} {{ formatCurrency(totalSales) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <Separator />

            <!-- Actual Closing Cash -->
            <div class="p-6 rounded-lg bg-muted/50">
              <div class="flex items-center justify-between">
                <div>
                  <Label class="text-lg font-semibold">Actual Closing Cash</Label>
                  <p class="text-sm text-muted-foreground">Count the cash and enter the actual amount</p>
                </div>
                <div class="w-64">
                  <Input v-model.number="form.closing_cash" type="number" class="text-right text-2xl font-bold h-14" />
                </div>
              </div>

              <!-- Variance -->
              <div v-if="form.closing_cash > 0" class="mt-4 flex items-center gap-2">
                <component :is="cashVariance === 0 ? CheckCircle : AlertCircle"
                  :class="['h-5 w-5', cashVariance === 0 ? 'text-green-600' : cashVariance > 0 ? 'text-blue-600' : 'text-red-600']"
                />
                <span v-if="cashVariance === 0" class="text-green-600 font-medium">Cash matches expected amount</span>
                <span v-else-if="cashVariance > 0" class="text-blue-600 font-medium">
                  Cash over by {{ currency }} {{ formatCurrency(cashVariance) }}
                </span>
                <span v-else class="text-red-600 font-medium">
                  Cash short by {{ currency }} {{ formatCurrency(Math.abs(cashVariance)) }}
                </span>
              </div>
            </div>

            <!-- Notes -->
            <div class="space-y-2">
              <Label>Notes</Label>
              <Textarea v-model="form.notes" placeholder="Any additional notes for this day..." rows="3" />
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-4">
              <Button variant="outline" @click="router.visit(`/${company.slug}/fuel/dashboard`)">
                Cancel
              </Button>
              <Button @click="submitDailyClose" :disabled="submitting" class="bg-green-600 hover:bg-green-700 min-w-40">
                <Loader2 v-if="submitting" class="h-4 w-4 mr-2 animate-spin" />
                <CheckCircle v-else class="h-4 w-4 mr-2" />
                Post Daily Close
              </Button>
            </div>
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  </PageShell>
</template>
