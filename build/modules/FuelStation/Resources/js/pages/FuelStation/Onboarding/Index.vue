<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Checkbox } from '@/components/ui/checkbox'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Spinner } from '@/components/ui/spinner'
import type { BreadcrumbItem } from '@/types'
import {
  Building,
  Calendar,
  Landmark,
  Settings,
  Users,
  UserPlus,
  Percent,
  Hash,
  CreditCard,
  Fuel,
  Warehouse,
  Gauge,
  TrendingUp,
  Package,
  Droplet,
  CircleDollarSign,
  CheckCircle,
  Circle,
  AlertTriangle,
  ArrowLeft,
  ArrowRight,
  Plus,
  Trash2,
} from 'lucide-vue-next'

interface WizardStep {
  name: string
  description: string
  complete: boolean
  hidden?: boolean
  [key: string]: any
}

interface WizardData {
  is_complete: boolean
  progress_percentage: number
  completed_steps: string[]
  total_steps: number
  current_step: string
  steps: Record<string, WizardStep>
  company_name: string
  industry: string
}

interface CompanySummary {
  id: string
  name: string
  slug: string
  base_currency: string
  industry_code?: string | null
  registration_number?: string | null
  trade_name?: string | null
  timezone?: string | null
  fiscal_year_start_month?: number | null
  period_frequency?: string | null
  tax_registered?: boolean | null
  tax_rate?: number | null
  tax_inclusive?: boolean | null
  invoice_prefix?: string | null
  invoice_start_number?: number | null
  bill_prefix?: string | null
  bill_start_number?: number | null
  default_customer_payment_terms?: number | null
  default_vendor_payment_terms?: number | null
  default_drawing_limit_period?: string | null
  default_drawing_limit_amount?: number | null
  ar_account_id?: string | null
  ap_account_id?: string | null
  income_account_id?: string | null
  expense_account_id?: string | null
  bank_account_id?: string | null
  retained_earnings_account_id?: string | null
  sales_tax_payable_account_id?: string | null
  purchase_tax_receivable_account_id?: string | null
}

interface AccountOption {
  id: string
  code: string
  name: string
}

interface PartnerRow {
  id?: string | null
  name: string
  phone?: string | null
  profit_share_percentage: number | string
  drawing_limit_period: string
  drawing_limit_amount?: number | string | null
}

interface EmployeeRow {
  id?: string | null
  first_name: string
  last_name: string
  phone?: string | null
  position?: string | null
  base_salary: number | string
}

interface FuelItemRow {
  id?: string | null
  name: string
  sku?: string
  fuel_category: string
  avg_cost?: number | string | null
  sale_price?: number | string | null
}

interface LubricantRow {
  id?: string | null
  name: string
  sku: string
  brand?: string | null
  unit?: string | null
  cost_price?: number | string | null
  sale_price?: number | string | null
  opening_quantity?: number | string | null
}

interface TankRow {
  id?: string | null
  name: string
  code: string
  capacity: number | string
  fuel_category?: string | null
  linked_item_id?: string | null
  linked_item_name?: string | null
  linked_item_sku?: string | null
  dip_stick_code?: string | null
}

interface PumpRow {
  id?: string | null
  name: string
  tank_id?: string | null
  nozzle_count: number | string
  // Front nozzle readings
  front_electronic?: number | string | null
  front_manual?: number | string | null
  // Back nozzle readings
  back_electronic?: number | string | null
  back_manual?: number | string | null
}

interface RateChangeRow {
  id: string
  item_id: string
  effective_date: string
  purchase_rate: number
  sale_rate: number
}

interface OpeningReadingRow {
  id: string
  tank_id: string
  reading_date: string
  liters_measured: number
  stick_reading?: number | null
}

const props = defineProps<{
  wizard: WizardData
  company: CompanySummary
  industries: Array<{ code: string; name: string; description?: string | null }>
  timezones: Record<string, string>
  months: Array<{ value: number; label: string }>
  currencies: Array<{ code: string; name: string; symbol: string }>
  arAccounts: AccountOption[]
  apAccounts: AccountOption[]
  revenueAccounts: AccountOption[]
  expenseAccounts: AccountOption[]
  bankAccounts: AccountOption[]
  existingBankAccounts: Array<{ id: string; name: string; currency: string; subtype: string }>
  retainedEarningsAccounts: AccountOption[]
  taxPayableAccounts: AccountOption[]
  taxReceivableAccounts: AccountOption[]
  transitColumnsReady: boolean
  transitColumnsMessage: string | null
  partners: PartnerRow[]
  employees: EmployeeRow[]
  fuelItems: FuelItemRow[]
  lubricants: Array<{
    id: string
    name: string
    sku: string
    brand?: string | null
    unit_of_measure?: string | null
    cost_price?: number | null
    sale_price?: number | null
  }>
  tanks: TankRow[]
  pumps: PumpRow[]
  rateChanges: RateChangeRow[]
  openingReadings: OpeningReadingRow[]
  openingBalances: Record<string, any>
  dipSticks: Array<{ id: string; code: string }>
}>()

const companySlug = computed(() => props.company.slug)
const currencyCode = computed(() => props.company.base_currency || 'PKR')

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/dashboard` },
  { title: 'Setup Wizard', href: `/${companySlug.value}/fuel/onboarding` },
])

const stepOrder = [
  'company_identity',
  'fiscal_year',
  'bank_accounts',
  'default_accounts',
  'partners',
  'employees',
  'tax_settings',
  'numbering',
  'payment_terms',
  'fuel_items',
  'tanks',
  'pumps',
  'rates',
  'lubricants',
  'initial_stock',
  'opening_cash',
]

const stepIcons: Record<string, any> = {
  company_identity: Building,
  fiscal_year: Calendar,
  bank_accounts: Landmark,
  default_accounts: Settings,
  partners: Users,
  employees: UserPlus,
  tax_settings: Percent,
  numbering: Hash,
  payment_terms: CreditCard,
  fuel_items: Fuel,
  tanks: Warehouse,
  pumps: Gauge,
  rates: TrendingUp,
  lubricants: Package,
  initial_stock: Droplet,
  opening_cash: CircleDollarSign,
}

const visibleSteps = computed(() => {
  const industryReady = Boolean(props.company.industry_code)
  return stepOrder
    .filter((id) => {
      if (id !== 'company_identity' && !industryReady) return false
      const stepData = props.wizard.steps?.[id]
      return !stepData?.hidden
    })
    .map((id) => {
      const stepData = props.wizard.steps?.[id]
      return {
        id,
        title: stepData?.name || id.replace('_', ' '),
        description: stepData?.description || '',
        complete: stepData?.complete ?? false,
        icon: stepIcons[id] || Circle,
      }
    })
})

const completeStep = computed(() => ({
  id: 'complete',
  title: 'Complete',
  description: 'Review progress and mark setup complete',
  complete: props.wizard.is_complete,
  icon: CheckCircle,
}))

const stepsForNav = computed(() => [...visibleSteps.value, completeStep.value])

const activeStepId = ref<string>('')
const hasInitialized = ref(false)

watch(
  () => visibleSteps.value,
  (steps) => {
    if (!steps.length) return
    if (!activeStepId.value || (!steps.find((step) => step.id === activeStepId.value) && activeStepId.value !== 'complete')) {
      activeStepId.value = steps[0].id
    }
  },
  { immediate: true }
)

watch(
  () => props.wizard.current_step,
  (next) => {
    if (hasInitialized.value) return
    if (next) {
      activeStepId.value = next
    }
    hasInitialized.value = true
  },
  { immediate: true }
)

const currentStep = computed(() => stepsForNav.value.find((step) => step.id === activeStepId.value))

const progress = computed(() => {
  const total = visibleSteps.value.length
  if (!total) return 0
  const completed = visibleSteps.value.filter((step) => step.complete).length
  return Math.round((completed / total) * 100)
})

const completedStepCount = computed(() => visibleSteps.value.filter((step) => step.complete).length)

const isStepComplete = (stepId: string) => {
  if (stepId === 'complete') return props.wizard.is_complete
  return props.wizard.steps?.[stepId]?.complete ?? false
}

const isStepAccessible = (stepId: string) => {
  if (stepId === 'complete') return true
  if (stepId === 'company_identity') return true
  return Boolean(props.company.industry_code)
}

const stepReloadMap: Record<string, string[]> = {
  company_identity: ['wizard', 'company', 'industries', 'timezones', 'currencies'],
  fiscal_year: ['wizard', 'company', 'months'],
  bank_accounts: ['wizard', 'company', 'existingBankAccounts', 'currencies'],
  default_accounts: [
    'wizard',
    'company',
    'arAccounts',
    'apAccounts',
    'revenueAccounts',
    'expenseAccounts',
    'bankAccounts',
    'retainedEarningsAccounts',
    'taxPayableAccounts',
    'taxReceivableAccounts',
  ],
  partners: ['wizard', 'partners', 'company'],
  employees: ['wizard', 'employees', 'company'],
  tax_settings: ['wizard', 'company'],
  numbering: ['wizard', 'company'],
  payment_terms: ['wizard', 'company'],
  fuel_items: ['wizard', 'fuelItems'],
  tanks: ['wizard', 'tanks', 'fuelItems', 'lubricants', 'dipSticks'],
  pumps: ['wizard', 'pumps', 'tanks'],
  rates: ['wizard', 'fuelItems', 'rateChanges'],
  lubricants: ['wizard', 'lubricants'],
  initial_stock: ['wizard', 'tanks', 'openingReadings', 'fuelItems'],
  opening_cash: ['wizard', 'openingBalances', 'bankAccounts', 'company'],
  complete: ['wizard', 'company'],
}

const isStepLoading = ref(false)
const loadingStepId = ref<string | null>(null)

const loadStepData = (stepId: string) => {
  const only = stepReloadMap[stepId] || ['wizard']
  isStepLoading.value = true
  loadingStepId.value = stepId

  router.reload({
    only,
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      isStepLoading.value = false
      loadingStepId.value = null
    },
  })
}

watch(
  () => activeStepId.value,
  (next, prev) => {
    if (!next || next === prev) return
    loadStepData(next)
  }
)

const goToStep = (stepId: string) => {
  if (!isStepAccessible(stepId)) return
  activeStepId.value = stepId
}

const nextStep = () => {
  const steps = stepsForNav.value
  const currentIndex = steps.findIndex((step) => step.id === activeStepId.value)
  const next = steps[currentIndex + 1]
  if (next) activeStepId.value = next.id
}

const previousStep = () => {
  const steps = stepsForNav.value
  const currentIndex = steps.findIndex((step) => step.id === activeStepId.value)
  const prev = steps[currentIndex - 1]
  if (prev) activeStepId.value = prev.id
}

const companyIdentityForm = useForm({
  industry_code: props.company.industry_code || '',
  registration_number: props.company.registration_number || '',
  trade_name: props.company.trade_name || '',
  timezone: props.company.timezone || 'Asia/Karachi',
  flow: 'fuel',
})

watch(
  () => props.company,
  (company) => {
    companyIdentityForm.industry_code = company.industry_code || ''
    companyIdentityForm.registration_number = company.registration_number || ''
    companyIdentityForm.trade_name = company.trade_name || ''
    companyIdentityForm.timezone = company.timezone || 'Asia/Karachi'
  },
  { immediate: true }
)

const fiscalYearForm = useForm({
  fiscal_year_start_month: props.company.fiscal_year_start_month || 1,
  period_frequency: props.company.period_frequency || 'monthly',
  flow: 'fuel',
})

watch(
  () => props.company,
  (company) => {
    fiscalYearForm.fiscal_year_start_month = company.fiscal_year_start_month || 1
    fiscalYearForm.period_frequency = company.period_frequency || 'monthly'
  },
  { immediate: true }
)

const bankAccountsForm = useForm({
  bank_accounts: [
    {
      id: null as string | null,
      account_name: '',
      currency: props.company.base_currency || 'PKR',
      account_type: 'bank' as 'bank' | 'cash',
    },
  ],
  flow: 'fuel',
})

const applyBankAccountPrefill = () => {
  if (props.existingBankAccounts.length > 0) {
    bankAccountsForm.bank_accounts = props.existingBankAccounts.map((account) => ({
      id: account.id,
      account_name: account.name,
      currency: account.currency || props.company.base_currency || 'PKR',
      account_type: account.subtype === 'cash' ? 'cash' : 'bank',
    }))
  } else if (!bankAccountsForm.bank_accounts.length) {
    bankAccountsForm.bank_accounts = [
      {
        id: null,
        account_name: '',
        currency: props.company.base_currency || 'PKR',
        account_type: 'bank',
      },
    ]
  }
}

watch(
  () => props.existingBankAccounts,
  () => applyBankAccountPrefill(),
  { immediate: true }
)

const addBankAccount = () => {
  bankAccountsForm.bank_accounts.push({
    id: null,
    account_name: '',
    currency: props.company.base_currency || 'PKR',
    account_type: 'bank',
  })
}

const removeBankAccount = (index: number) => {
  if (bankAccountsForm.bank_accounts.length <= 1) return
  bankAccountsForm.bank_accounts.splice(index, 1)
}

const pickByCode = (accounts: AccountOption[], code: string, fallback?: string | null) => {
  return fallback || accounts.find((account) => account.code === code)?.id || accounts[0]?.id || ''
}

const defaultAccountsForm = useForm({
  ar_account_id: pickByCode(props.arAccounts, '1100', props.company.ar_account_id),
  ap_account_id: pickByCode(props.apAccounts, '2100', props.company.ap_account_id),
  income_account_id: pickByCode(props.revenueAccounts, '4100', props.company.income_account_id),
  expense_account_id: pickByCode(props.expenseAccounts, '6100', props.company.expense_account_id),
  bank_account_id: pickByCode(props.bankAccounts, '1000', props.company.bank_account_id),
  retained_earnings_account_id: pickByCode(props.retainedEarningsAccounts, '3100', props.company.retained_earnings_account_id),
  sales_tax_payable_account_id: props.company.sales_tax_payable_account_id || props.taxPayableAccounts[0]?.id || '',
  purchase_tax_receivable_account_id: props.company.purchase_tax_receivable_account_id || props.taxReceivableAccounts[0]?.id || '',
  flow: 'fuel',
})

const defaultAccountsError = computed(() => Object.values(defaultAccountsForm.errors)[0] ?? '')

watch(
  () => props.company,
  (company) => {
    defaultAccountsForm.ar_account_id = pickByCode(props.arAccounts, '1100', company.ar_account_id)
    defaultAccountsForm.ap_account_id = pickByCode(props.apAccounts, '2100', company.ap_account_id)
    defaultAccountsForm.income_account_id = pickByCode(props.revenueAccounts, '4100', company.income_account_id)
    defaultAccountsForm.expense_account_id = pickByCode(props.expenseAccounts, '6100', company.expense_account_id)
    defaultAccountsForm.bank_account_id = pickByCode(props.bankAccounts, '1000', company.bank_account_id)
    defaultAccountsForm.retained_earnings_account_id = pickByCode(
      props.retainedEarningsAccounts,
      '3100',
      company.retained_earnings_account_id
    )
    defaultAccountsForm.sales_tax_payable_account_id = company.sales_tax_payable_account_id || props.taxPayableAccounts[0]?.id || ''
    defaultAccountsForm.purchase_tax_receivable_account_id =
      company.purchase_tax_receivable_account_id || props.taxReceivableAccounts[0]?.id || ''
  },
  { immediate: true }
)

const partnersForm = useForm({
  partners: [] as PartnerRow[],
})

const partnersError = computed(() => Object.values(partnersForm.errors)[0] ?? '')

const applyPartnerPrefill = () => {
  if (props.partners.length > 0) {
    partnersForm.partners = props.partners.map((partner) => ({
      id: partner.id ?? null,
      name: partner.name,
      phone: partner.phone || '',
      profit_share_percentage: Number(partner.profit_share_percentage ?? 0),
      drawing_limit_period: partner.drawing_limit_period || props.company.default_drawing_limit_period || 'monthly',
      drawing_limit_amount: partner.drawing_limit_amount ?? '',
    }))
  } else if (!partnersForm.partners.length) {
    partnersForm.partners = [
      {
        id: null,
        name: '',
        phone: '',
        profit_share_percentage: 0,
        drawing_limit_period: props.company.default_drawing_limit_period || 'monthly',
        drawing_limit_amount: props.company.default_drawing_limit_amount ?? '',
      },
    ]
  }
}

watch(
  () => props.partners,
  () => applyPartnerPrefill(),
  { immediate: true }
)

const addPartner = () => {
  partnersForm.partners.push({
    id: null,
    name: '',
    phone: '',
    profit_share_percentage: 0,
    drawing_limit_period: props.company.default_drawing_limit_period || 'monthly',
    drawing_limit_amount: props.company.default_drawing_limit_amount ?? '',
  })
}

const removePartner = (index: number) => {
  if (partnersForm.partners.length <= 1) return
  partnersForm.partners.splice(index, 1)
}

const totalProfitShare = computed(() => {
  return partnersForm.partners.reduce((sum, partner) => {
    const value = Number(partner.profit_share_percentage || 0)
    return sum + (Number.isFinite(value) ? value : 0)
  }, 0)
})

const isProfitShareValid = computed(() => totalProfitShare.value <= 100)

const employeesForm = useForm({
  employees: [] as EmployeeRow[],
})

const employeeDesignationPresets = ['Attendant', 'Accountant', 'Manager', 'Guard', 'Cashier']

const applyEmployeePrefill = () => {
  if (props.employees.length > 0) {
    employeesForm.employees = props.employees.map((employee) => ({
      id: employee.id ?? null,
      first_name: employee.first_name || '',
      last_name: employee.last_name || '',
      phone: employee.phone || '',
      position: employee.position || 'Attendant',
      base_salary: employee.base_salary ?? '',
    }))
  } else if (!employeesForm.employees.length) {
    employeesForm.employees = [
      {
        id: null,
        first_name: '',
        last_name: '',
        phone: '',
        position: 'Attendant',
        base_salary: '',
      },
    ]
  }
}

watch(
  () => props.employees,
  () => applyEmployeePrefill(),
  { immediate: true }
)

const addEmployee = () => {
  employeesForm.employees.push({
    id: null,
    first_name: '',
    last_name: '',
    phone: '',
    position: 'Attendant',
    base_salary: '',
  })
}

const removeEmployee = (index: number) => {
  if (employeesForm.employees.length <= 1) return
  employeesForm.employees.splice(index, 1)
}

const taxSettingsForm = useForm({
  tax_registered: Boolean(props.company.tax_registered),
  tax_rate: props.company.tax_rate ?? '',
  tax_inclusive: Boolean(props.company.tax_inclusive),
  flow: 'fuel',
})

watch(
  () => props.company,
  (company) => {
    taxSettingsForm.tax_registered = Boolean(company.tax_registered)
    taxSettingsForm.tax_rate = company.tax_rate ?? ''
    taxSettingsForm.tax_inclusive = Boolean(company.tax_inclusive)
  },
  { immediate: true }
)

const numberingForm = useForm({
  invoice_prefix: props.company.invoice_prefix || 'INV',
  invoice_start_number: props.company.invoice_start_number || 1,
  bill_prefix: props.company.bill_prefix || 'BILL',
  bill_start_number: props.company.bill_start_number || 1,
  flow: 'fuel',
})

watch(
  () => props.company,
  (company) => {
    numberingForm.invoice_prefix = company.invoice_prefix || 'INV'
    numberingForm.invoice_start_number = company.invoice_start_number || 1
    numberingForm.bill_prefix = company.bill_prefix || 'BILL'
    numberingForm.bill_start_number = company.bill_start_number || 1
  },
  { immediate: true }
)

const paymentTermsForm = useForm({
  default_customer_payment_terms: props.company.default_customer_payment_terms ?? 0,
  default_vendor_payment_terms: props.company.default_vendor_payment_terms ?? 0,
  flow: 'fuel',
})

watch(
  () => props.company,
  (company) => {
    paymentTermsForm.default_customer_payment_terms = company.default_customer_payment_terms ?? 0
    paymentTermsForm.default_vendor_payment_terms = company.default_vendor_payment_terms ?? 0
  },
  { immediate: true }
)

const fuelDefaults = [
  { key: 'petrol', name: 'Petrol', fuel_category: 'petrol', description: 'Regular petrol/gasoline', enabledByDefault: true },
  { key: 'diesel', name: 'Diesel', fuel_category: 'diesel', description: 'Diesel fuel', enabledByDefault: true },
  { key: 'high_octane', name: 'Hi-Octane', fuel_category: 'high_octane', description: 'Premium high-octane fuel', enabledByDefault: false },
  { key: 'lubricant', name: 'Lubricant (Open)', fuel_category: 'lubricant', description: 'Open/bulk lubricant sold by liter', enabledByDefault: false },
]

const fuelItemsForm = useForm({
  fuel_items: fuelDefaults.map((item) => ({
    id: null as string | null,
    name: item.name,
    fuel_category: item.fuel_category,
    description: item.description,
    enabled: item.enabledByDefault,
  })),
})

const normalizeFuelCategory = (category: string | null | undefined) => {
  if (!category) return ''
  return category === 'hi_octane' ? 'high_octane' : category
}

const applyFuelItemsPrefill = () => {
  const existing = props.fuelItems.map((item) => ({
    ...item,
    fuel_category: normalizeFuelCategory(item.fuel_category),
  }))

  // If there are existing items in the database, only those should be checked
  // Otherwise, use defaults for first-time setup
  const hasExistingItems = existing.length > 0

  fuelItemsForm.fuel_items = fuelDefaults.map((item) => {
    const matched = existing.find((existingItem) => existingItem.fuel_category === item.fuel_category)
    return {
      id: matched?.id ?? null,
      name: item.name,
      fuel_category: item.fuel_category,
      description: item.description,
      // If items exist in DB, only check the ones that are saved; otherwise use defaults
      enabled: hasExistingItems ? !!matched : item.enabledByDefault,
    }
  })
}

watch(
  () => props.fuelItems,
  () => applyFuelItemsPrefill(),
  { immediate: true }
)

const lubricantsForm = useForm({
  lubricants: [] as LubricantRow[],
})

// Packed lubricant products (bottles) - stored in standard warehouse
// Note: Open/bulk lubricant (sold by liter) should be added as a Tank in the Tanks step
const lubricantPresets: Array<Omit<LubricantRow, 'cost_price' | 'sale_price' | 'opening_quantity'>> = [
  { name: 'Packaged Lubricant 0.7L', sku: 'LUB-07', brand: 'Generic', unit: 'bottle' },
  { name: 'Packaged Lubricant 1L', sku: 'LUB-01', brand: 'Generic', unit: 'bottle' },
  { name: 'Packaged Lubricant 2L', sku: 'LUB-02', brand: 'Generic', unit: 'bottle' },
  { name: 'Packaged Lubricant 4L', sku: 'LUB-04', brand: 'Generic', unit: 'bottle' },
]

const applyLubricantPrefill = () => {
  if (props.lubricants.length > 0) {
    lubricantsForm.lubricants = props.lubricants.map((item) => ({
      id: item.id,
      name: item.name,
      sku: item.sku,
      brand: item.brand || 'Generic',
      unit: item.unit_of_measure || '',
      cost_price: item.cost_price ?? '',
      sale_price: item.sale_price ?? '',
      opening_quantity: '',
    }))
  } else if (!lubricantsForm.lubricants.length) {
    lubricantsForm.lubricants = lubricantPresets.map((preset) => ({
      id: null,
      name: preset.name,
      sku: preset.sku,
      brand: preset.brand || 'Generic',
      unit: preset.unit || '',
      cost_price: '',
      sale_price: '',
      opening_quantity: '',
    }))
  }
}

watch(
  () => props.lubricants,
  () => applyLubricantPrefill(),
  { immediate: true }
)

const addLubricant = () => {
  lubricantsForm.lubricants.push({
    id: null,
    name: '',
    sku: '',
    brand: '',
    unit: '',
    cost_price: '',
    sale_price: '',
    opening_quantity: '',
  })
}

const addLubricantPreset = () => {
  lubricantPresets.forEach((preset) => {
    const exists = lubricantsForm.lubricants.some((row) => row.sku === preset.sku)
    if (exists) return
    lubricantsForm.lubricants.push({
      id: null,
      name: preset.name,
      sku: preset.sku,
      brand: preset.brand || '',
      unit: preset.unit || '',
      cost_price: '',
      sale_price: '',
      opening_quantity: '',
    })
  })
}

const removeLubricant = (index: number) => {
  if (lubricantsForm.lubricants.length <= 1) return
  lubricantsForm.lubricants.splice(index, 1)
}

const tankItemOptions = computed(() => {
  const fuelOptions = props.fuelItems.map((item) => ({
    id: item.id as string,
    label: item.name,
    sku: item.sku,
    category: normalizeFuelCategory(item.fuel_category),
  }))
  const lubricantOptions = props.lubricants.map((item) => ({
    id: item.id,
    label: item.name,
    sku: item.sku,
    category: 'lubricant',
  }))

  return [...fuelOptions, ...lubricantOptions]
})

const defaultTankPresets = [
  { label: 'Petrol Tank', prefix: 'TANK-PET', category: 'petrol' },
  { label: 'Diesel Tank', prefix: 'TANK-DSL', category: 'diesel' },
  { label: 'Hi-Octane Tank', prefix: 'TANK-HOC', category: 'high_octane' },
  { label: 'Bulk Lubricant Tank', prefix: 'TANK-LUB', category: 'lubricant' },
]

const hasFuelCategory = (category: string) =>
  props.fuelItems.some((item) => normalizeFuelCategory(item.fuel_category) === category)

const hasBulkLubricant = computed(() => hasFuelCategory('lubricant'))

const bankAccountLabels = computed(() => {
  const map = new Map<string, string>()
  props.bankAccounts.forEach((account) => {
    map.set(account.id, account.name)
  })
  return map
})

const tanksForm = useForm({
  tanks: [] as TankRow[],
})

const buildDefaultTanks = () => {
  const availableCategories = new Set(props.fuelItems.map((item) => normalizeFuelCategory(item.fuel_category)))
  const presets = defaultTankPresets.filter((preset) => availableCategories.has(preset.category))
  const fallback = [defaultTankPresets[0], defaultTankPresets[1]]
  const selectedPresets = presets.length ? presets : fallback

  return selectedPresets.map((preset) => {
    const linkedItem = tankItemOptions.value.find((item) => item.category === preset.category)
    return {
      id: null,
      name: `${preset.label} 1`,
      code: `${preset.prefix}-01`,
      capacity: '',
      fuel_category: preset.category,
      linked_item_id: linkedItem?.id || null,
      linked_item_name: linkedItem?.label || null,
      linked_item_sku: linkedItem?.sku || null,
      dip_stick_code: '',
    }
  })
}

const applyTanksPrefill = () => {
  if (props.tanks.length > 0) {
    tanksForm.tanks = props.tanks.map((tank) => ({
      id: tank.id ?? null,
      name: tank.name,
      code: tank.code,
      capacity: tank.capacity ?? '',
      fuel_category: normalizeFuelCategory(tank.fuel_category || ''),
      linked_item_id:
        tank.linked_item_id ?? tankItemOptions.value.find((item) => item.category === normalizeFuelCategory(tank.fuel_category || ''))?.id ?? null,
      linked_item_name: tank.linked_item_name ?? null,
      linked_item_sku: tank.linked_item_sku ?? null,
      dip_stick_code: tank.dip_stick_code ?? '',
    }))
  } else if (!tanksForm.tanks.length) {
    tanksForm.tanks = buildDefaultTanks()
  }
}

watch(
  () => props.tanks,
  () => applyTanksPrefill(),
  { immediate: true }
)

const addTank = () => {
  tanksForm.tanks.push({
    id: null,
    name: '',
    code: '',
    capacity: '',
    fuel_category: null,
    linked_item_id: null,
    linked_item_name: null,
    linked_item_sku: null,
    dip_stick_code: '',
  })
}

const nextTankSequence = (prefix: string) => {
  const matches = tanksForm.tanks.filter((tank) => tank.code?.startsWith(prefix))
  return matches.length + 1
}

const addTankPreset = (label: string, prefix: string, category: string, skuHint?: string) => {
  const sequence = nextTankSequence(prefix)
  const linkedItem = tankItemOptions.value.find((item) => item.category === category || item.sku === skuHint)

  tanksForm.tanks.push({
    id: null,
    name: `${label} ${sequence}`,
    code: `${prefix}-${String(sequence).padStart(2, '0')}`,
    capacity: '',
    fuel_category: category,
    linked_item_id: linkedItem?.id || null,
    linked_item_name: linkedItem?.label || null,
    linked_item_sku: linkedItem?.sku || null,
    dip_stick_code: '',
  })
}

const addPetrolTank = () => addTankPreset('Petrol Tank', 'TANK-PET', 'petrol')
const addDieselTank = () => addTankPreset('Diesel Tank', 'TANK-DSL', 'diesel')
const addHiOctaneTank = () => addTankPreset('Hi-Octane Tank', 'TANK-HOC', 'high_octane')
const addBulkLubricantTank = () => addTankPreset('Bulk Lubricant Tank', 'TANK-LUB', 'lubricant', 'FUEL-LUB')

const removeTank = (index: number) => {
  if (tanksForm.tanks.length <= 1) return
  tanksForm.tanks.splice(index, 1)
}

const pumpsForm = useForm({
  pumps: [] as PumpRow[],
})

const pumpsError = computed(() => Object.values(pumpsForm.errors)[0] ?? '')

const tankOptions = computed(() =>
  props.tanks.map((tank) => ({
    id: tank.id as string,
    label: `${tank.name} (${tank.code})`,
  }))
)

const applyPumpsPrefill = () => {
  if (props.pumps.length > 0) {
    pumpsForm.pumps = props.pumps.map((pump) => ({
      id: pump.id ?? null,
      name: pump.name,
      tank_id: pump.tank_id ?? null,
      nozzle_count: pump.nozzle_count ?? 2,
      front_electronic: pump.front_electronic ?? '',
      front_manual: pump.front_manual ?? '',
      back_electronic: pump.back_electronic ?? '',
      back_manual: pump.back_manual ?? '',
    }))
  } else if (!pumpsForm.pumps.length) {
    const defaultTankId = tankOptions.value[0]?.id || null
    pumpsForm.pumps = [
      {
        id: null,
        name: 'Pump 1',
        tank_id: defaultTankId,
        nozzle_count: 2,
        front_electronic: '',
        front_manual: '',
        back_electronic: '',
        back_manual: '',
      },
    ]
  }
}

watch(
  () => props.pumps,
  () => applyPumpsPrefill(),
  { immediate: true }
)

const addPump = () => {
  const defaultTankId = tankOptions.value[0]?.id || null
  pumpsForm.pumps.push({
    id: null,
    name: `Pump ${pumpsForm.pumps.length + 1}`,
    tank_id: defaultTankId,
    nozzle_count: 2,
    front_electronic: '',
    front_manual: '',
    back_electronic: '',
    back_manual: '',
  })
}

const removePump = (index: number) => {
  if (pumpsForm.pumps.length <= 1) return
  pumpsForm.pumps.splice(index, 1)
}

const formatLocalDate = (date: Date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const todayLocal = () => formatLocalDate(new Date())

const ratesForm = useForm({
  effective_date: todayLocal(),
  rates: [] as Array<{ item_id: string; purchase_rate: number | string; sale_rate: number | string }>,
})

const rateMap = computed(() => {
  const map = new Map<string, RateChangeRow>()
  props.rateChanges.forEach((change) => {
    const existing = map.get(change.item_id)
    if (!existing || new Date(change.effective_date).getTime() > new Date(existing.effective_date).getTime()) {
      map.set(change.item_id, change)
    }
  })
  return map
})

const applyRatesPrefill = () => {
  ratesForm.rates = props.fuelItems.map((item) => {
    const latestRate = rateMap.value.get(item.id || '')
    return {
      item_id: item.id as string,
      purchase_rate: latestRate?.purchase_rate ?? item.avg_cost ?? '',
      sale_rate: latestRate?.sale_rate ?? item.sale_price ?? '',
    }
  })
}

watch(
  () => [props.rateChanges, props.fuelItems],
  () => applyRatesPrefill(),
  { immediate: true }
)

const openingStockForm = useForm({
  stock_date: todayLocal(),
  tank_readings: [] as Array<{
    id?: string | null
    tank_id: string
    stick_reading?: number | string | null
    liters: number | string
    value?: number | string | null
  }>,
})

const applyOpeningStockPrefill = () => {
  const readingByTank = new Map<string, OpeningReadingRow>()
  props.openingReadings.forEach((reading) => {
    readingByTank.set(reading.tank_id, reading)
  })

  // Use date from existing readings if available, otherwise use today
  if (props.openingReadings.length > 0 && props.openingReadings[0]?.reading_date) {
    // Extract just the date part (YYYY-MM-DD) from the reading_date
    const existingDate = props.openingReadings[0].reading_date
    const dateOnly = typeof existingDate === 'string'
      ? existingDate.split('T')[0]
      : formatLocalDate(new Date(existingDate))
    openingStockForm.stock_date = dateOnly
  }

  openingStockForm.tank_readings = props.tanks.map((tank) => {
    const reading = readingByTank.get(tank.id || '')
    return {
      id: reading?.id ?? null,
      tank_id: tank.id as string,
      stick_reading: reading?.stick_reading ?? '',
      liters: reading?.liters_measured ?? '',
      value: '',
    }
  })
}

watch(
  () => [props.tanks, props.openingReadings],
  () => applyOpeningStockPrefill(),
  { immediate: true }
)

const openingCashForm = useForm({
  as_of_date: todayLocal(),
  cash_on_hand: '',
  bank_balance: '',
  bank_balances: [] as Array<{ account_id: string; balance: number | string }>,
})

const applyOpeningCashPrefill = () => {
  const openingBalances = props.openingBalances || {}
  const cash = openingBalances.cash_on_hand
  const banks = openingBalances.banks || {}

  openingCashForm.cash_on_hand = cash?.amount ?? ''
  openingCashForm.as_of_date = cash?.as_of_date || openingCashForm.as_of_date

  const primaryBank = props.bankAccounts.find((account) => account.code === '1000')
  openingCashForm.bank_balance = primaryBank ? banks[primaryBank.id]?.amount ?? '' : ''

  openingCashForm.bank_balances = props.bankAccounts
    .filter((account) => account.id !== primaryBank?.id)
    .map((account) => ({
      account_id: account.id,
      balance: banks[account.id]?.amount ?? '',
    }))
}

watch(
  () => [props.openingBalances, props.bankAccounts],
  () => applyOpeningCashPrefill(),
  { immediate: true }
)

const formatMoney = (value: number | string) => {
  const numberValue = Number(value || 0)
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(numberValue)
}

const formatDate = (value?: string | null) => {
  if (!value) return 'Not set'
  return value.includes('T') ? value.split('T')[0] : value
}

const mostRecentOgraDate = () => {
  const now = new Date()
  const first = new Date(now.getFullYear(), now.getMonth(), 1)
  const fifteenth = new Date(now.getFullYear(), now.getMonth(), 15)

  if (now >= fifteenth) return fifteenth
  return first
}

const setOgraDate = (day: number) => {
  const now = new Date()
  const date = new Date(now.getFullYear(), now.getMonth(), day)
  ratesForm.effective_date = formatLocalDate(date)
}

const setMostRecentOgraDate = () => {
  ratesForm.effective_date = formatLocalDate(mostRecentOgraDate())
}

const submitCompanyIdentity = () => {
  companyIdentityForm.post(`/${companySlug.value}/onboarding/company-identity`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => nextStep(),
  })
}

const submitFiscalYear = () => {
  fiscalYearForm.post(`/${companySlug.value}/onboarding/fiscal-year`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => nextStep(),
  })
}

const submitBankAccounts = () => {
  bankAccountsForm.post(`/${companySlug.value}/onboarding/bank-accounts`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => nextStep(),
  })
}

const submitDefaultAccounts = () => {
  defaultAccountsForm
    .transform((data) => ({
      ...data,
      sales_tax_payable_account_id: data.sales_tax_payable_account_id || null,
      purchase_tax_receivable_account_id: data.purchase_tax_receivable_account_id || null,
    }))
    .post(`/${companySlug.value}/onboarding/default-accounts`, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => nextStep(),
    })
}

const submitPartners = () => {
  if (!isProfitShareValid.value) return
  partnersForm.post(`/${companySlug.value}/fuel/onboarding/partners`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => nextStep(),
  })
}

const submitEmployees = () => {
  employeesForm.post(`/${companySlug.value}/fuel/onboarding/employees`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => nextStep(),
  })
}

const submitTaxSettings = () => {
  taxSettingsForm.post(`/${companySlug.value}/onboarding/tax-settings`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => nextStep(),
  })
}

const submitNumbering = () => {
  numberingForm.post(`/${companySlug.value}/onboarding/numbering`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => nextStep(),
  })
}

const submitPaymentTerms = () => {
  paymentTermsForm.post(`/${companySlug.value}/onboarding/payment-terms`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => nextStep(),
  })
}

const isValidNumber = (value: number | string | null | undefined) => {
  if (value === '' || value === null || value === undefined) return false
  const numberValue = Number(value)
  return Number.isFinite(numberValue) && numberValue >= 0
}

const submitFuelItems = () => {
  const enabledItems = fuelItemsForm.fuel_items.filter((item) => item.enabled)
  if (!enabledItems.length) {
    fuelItemsForm.setError('fuel_items', 'Select at least one fuel type to continue.')
    return
  }

  router.post(
    `/${companySlug.value}/fuel/onboarding/fuel-items`,
    {
      fuel_items: enabledItems.map((item) => ({
        id: item.id || null,
        name: item.name,
        fuel_category: item.fuel_category,
      })),
    },
    {
      preserveScroll: true,
      // Don't preserve state - we need fresh props with the newly created fuel items
      preserveState: false,
      onSuccess: () => nextStep(),
    }
  )
}

const submitLubricants = () => {
  const rows = lubricantsForm.lubricants.filter((row) => row.name && row.sku)
  if (!rows.length) {
    lubricantsForm.setError('lubricants', 'Add at least one lubricant or oil product.')
    return
  }

  const invalid = rows.filter((row) => !isValidNumber(row.cost_price) || !isValidNumber(row.sale_price))
  if (invalid.length > 0) {
    lubricantsForm.setError('lubricants', 'Enter purchase and sale prices for all lubricants.')
    return
  }

  router.post(
    `/${companySlug.value}/fuel/onboarding/lubricants`,
    {
      lubricants: rows.map((row) => ({
        id: row.id || null,
        name: row.name,
        sku: row.sku,
        brand: row.brand || null,
        unit: row.unit || null,
        cost_price: Number(row.cost_price),
        sale_price: Number(row.sale_price),
        opening_quantity: row.opening_quantity ? Number(row.opening_quantity) : null,
      })),
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => nextStep(),
    }
  )
}

const submitTanks = () => {
  const invalid = tanksForm.tanks.filter((tank) => !tank.name || !tank.code || !tank.capacity)
  if (invalid.length > 0) {
    tanksForm.setError('tanks', 'Fill tank name, code, and capacity for each tank.')
    return
  }

  router.post(
    `/${companySlug.value}/fuel/onboarding/tanks`,
    {
      tanks: tanksForm.tanks.map((tank) => ({
        id: tank.id || null,
        name: tank.name,
        code: tank.code,
        capacity: Number(tank.capacity),
        fuel_category: tank.fuel_category || null,
        linked_item_id: tank.linked_item_id || null,
        linked_item_sku: tank.linked_item_sku || null,
        linked_item_name: tank.linked_item_name || null,
        dip_stick_code: tank.dip_stick_code || null,
      })),
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => nextStep(),
    }
  )
}

const submitPumps = () => {
  const invalid = pumpsForm.pumps.filter((pump) => !pump.name || !pump.tank_id)
  if (invalid.length > 0) {
    pumpsForm.setError('pumps', 'Select a tank for each pump.')
    return
  }

  router.post(
    `/${companySlug.value}/fuel/onboarding/pumps`,
    {
      pumps: pumpsForm.pumps.map((pump) => ({
        id: pump.id || null,
        name: pump.name,
        tank_id: pump.tank_id,
        nozzle_count: Number(pump.nozzle_count || 2),
        front_electronic: pump.front_electronic ? Number(pump.front_electronic) : 0,
        front_manual: pump.front_manual ? Number(pump.front_manual) : 0,
        back_electronic: pump.back_electronic ? Number(pump.back_electronic) : 0,
        back_manual: pump.back_manual ? Number(pump.back_manual) : 0,
      })),
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => nextStep(),
    }
  )
}

const submitRates = () => {
  const rows = ratesForm.rates.filter((row) => row.item_id)
  const invalid = rows.filter((row) => !isValidNumber(row.purchase_rate) || !isValidNumber(row.sale_rate))
  if (invalid.length > 0) {
    ratesForm.setError('rates', 'Enter purchase and sale rates for all fuel products.')
    return
  }

  router.post(
    `/${companySlug.value}/fuel/onboarding/rates`,
    {
      effective_date: ratesForm.effective_date,
      rates: rows.map((row) => ({
        item_id: row.item_id,
        purchase_rate: Number(row.purchase_rate),
        sale_rate: Number(row.sale_rate),
      })),
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => nextStep(),
    }
  )
}

const submitOpeningStock = () => {
  const invalid = openingStockForm.tank_readings.filter((row) => !row.tank_id || !isValidNumber(row.liters))
  if (invalid.length > 0) {
    openingStockForm.setError('tank_readings', 'Enter opening liters for each tank.')
    return
  }

  router.post(
    `/${companySlug.value}/fuel/onboarding/initial-stock`,
    {
      stock_date: openingStockForm.stock_date,
      tank_readings: openingStockForm.tank_readings.map((row) => ({
        id: row.id || null,
        tank_id: row.tank_id,
        stick_reading: row.stick_reading ? Number(row.stick_reading) : null,
        liters: Number(row.liters),
        value: row.value ? Number(row.value) : null,
      })),
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => nextStep(),
    }
  )
}

const submitOpeningCash = () => {
  router.post(
    `/${companySlug.value}/fuel/onboarding/opening-cash`,
    {
      as_of_date: openingCashForm.as_of_date,
      cash_on_hand: Number(openingCashForm.cash_on_hand || 0),
      bank_balance: openingCashForm.bank_balance ? Number(openingCashForm.bank_balance) : 0,
      bank_balances: openingCashForm.bank_balances.map((row) => ({
        account_id: row.account_id,
        balance: Number(row.balance || 0),
      })),
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => nextStep(),
    }
  )
}

const submitComplete = () => {
  router.post(
    `/${companySlug.value}/fuel/onboarding/complete`,
    {},
    {
      preserveScroll: true,
      preserveState: true,
    }
  )
}

onMounted(() => {
  const initial = visibleSteps.value[0]?.id
  if (!activeStepId.value && initial) {
    activeStepId.value = initial
  }
})
</script>

<template>
  <Head title="Fuel Station Setup" />

  <PageShell
    title="Fuel Station Setup"
    description="Complete setup wizard for your fuel station operations"
    :icon="Fuel"
    :breadcrumbs="breadcrumbs"
  >
    <Card class="border-border/80 mb-6">
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle class="text-base">Setup Progress</CardTitle>
            <CardDescription>
              {{ completedStepCount }} of {{ visibleSteps.length }} steps complete
            </CardDescription>
          </div>
          <Badge :class="props.wizard.is_complete ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
            {{ props.wizard.is_complete ? 'Complete' : 'In Progress' }}
          </Badge>
        </div>
      </CardHeader>
      <CardContent>
        <Progress :model-value="progress" class="mb-4" />
        <div class="flex justify-between text-sm text-text-secondary">
          <span>0%</span>
          <span>{{ progress }}% Complete</span>
          <span>100%</span>
        </div>
      </CardContent>
    </Card>

    <div class="grid gap-6 lg:grid-cols-4">
      <div class="lg:col-span-1">
        <Card class="border-border/80">
          <CardHeader>
            <CardTitle class="text-base">Steps</CardTitle>
          </CardHeader>
          <CardContent class="p-0">
            <div class="space-y-1">
              <div
                v-for="step in stepsForNav"
                :key="step.id"
                :class="[
                  'flex items-center gap-3 p-3 cursor-pointer transition-colors',
                  activeStepId === step.id ? 'bg-muted border-r-2 border-primary' : 'hover:bg-muted/50',
                  !isStepAccessible(step.id) ? 'opacity-50 cursor-not-allowed' : '',
                ]"
                @click="goToStep(step.id)"
              >
                <div class="flex-shrink-0">
                  <component
                    :is="isStepComplete(step.id) ? CheckCircle : Circle"
                    :class="[
                      'h-5 w-5',
                      isStepComplete(step.id) ? 'text-emerald-600' : 'text-text-tertiary',
                    ]"
                  />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ step.title }}</p>
                  <p class="text-xs text-text-secondary truncate">{{ step.description }}</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="lg:col-span-3">
        <Card class="border-border/80 relative overflow-hidden">
          <CardHeader>
            <div class="flex items-center gap-3">
              <component :is="currentStep?.icon || Fuel" class="h-6 w-6 text-blue-600" />
              <div>
                <CardTitle class="text-lg">{{ currentStep?.title }}</CardTitle>
                <CardDescription>{{ currentStep?.description }}</CardDescription>
              </div>
            </div>
          </CardHeader>

          <CardContent class="min-h-[480px] relative">
            <div
              v-if="isStepLoading && loadingStepId === activeStepId"
              class="absolute inset-0 z-10 flex items-center justify-center bg-background/80"
            >
              <div class="flex items-center gap-2 text-sm text-text-secondary">
                <Spinner class="size-5" />
                Loading latest data…
              </div>
            </div>

            <!-- Company Identity -->
            <div v-if="activeStepId === 'company_identity'" class="space-y-6">
              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Company Name</Label>
                  <Input :model-value="props.company.name" disabled />
                </div>
                <div class="space-y-2">
                  <Label>Base Currency</Label>
                  <Input :model-value="props.company.base_currency" disabled />
                </div>
              </div>

              <div class="space-y-2">
                <Label>Industry <span class="text-red-500">*</span></Label>
                <Select v-model="companyIdentityForm.industry_code" :disabled="Boolean(props.company.industry_code)" required>
                  <SelectTrigger>
                    <SelectValue placeholder="Select industry" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="industry in props.industries"
                      :key="industry.code"
                      :value="industry.code"
                    >
                      {{ industry.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="props.company.industry_code" class="text-xs text-text-secondary">
                  Industry is locked after initial setup.
                </p>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Registration / Tax Number</Label>
                  <Input v-model="companyIdentityForm.registration_number" type="text" placeholder="e.g. NTN-12345" />
                </div>
                <div class="space-y-2">
                  <Label>Trade Name</Label>
                  <Input v-model="companyIdentityForm.trade_name" type="text" placeholder="Optional" />
                </div>
              </div>

              <div class="space-y-2">
                <Label>Timezone <span class="text-red-500">*</span></Label>
                <Select v-model="companyIdentityForm.timezone" required>
                  <SelectTrigger>
                    <SelectValue placeholder="Select timezone" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="(label, tz) in props.timezones" :key="tz" :value="tz">
                      {{ label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <div class="text-xs text-text-secondary">
                  Company name, base currency, and industry are locked after first save.
                </div>
                <Button
                  type="button"
                  :disabled="companyIdentityForm.processing || !companyIdentityForm.industry_code"
                  @click="submitCompanyIdentity"
                >
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Fiscal Year -->
            <div v-if="activeStepId === 'fiscal_year'" class="space-y-6">
              <div class="space-y-2">
                <Label>Fiscal Year Start Month <span class="text-red-500">*</span></Label>
                <Select v-model="fiscalYearForm.fiscal_year_start_month" required>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="month in props.months" :key="month.value" :value="month.value">
                      {{ month.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div class="space-y-3">
                <Label>Accounting Period Frequency <span class="text-red-500">*</span></Label>
                <RadioGroup v-model="fiscalYearForm.period_frequency" class="space-y-2">
                  <div class="flex items-center gap-3 border rounded-md p-3">
                    <RadioGroupItem value="monthly" id="monthly" />
                    <Label for="monthly">Monthly</Label>
                  </div>
                  <div class="flex items-center gap-3 border rounded-md p-3">
                    <RadioGroupItem value="quarterly" id="quarterly" />
                    <Label for="quarterly">Quarterly</Label>
                  </div>
                  <div class="flex items-center gap-3 border rounded-md p-3">
                    <RadioGroupItem value="yearly" id="yearly" />
                    <Label for="yearly">Yearly</Label>
                  </div>
                </RadioGroup>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="fiscalYearForm.processing" @click="submitFiscalYear">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Bank Accounts -->
            <div v-if="activeStepId === 'bank_accounts'" class="space-y-6">
              <p class="text-sm text-text-secondary">
                Add your primary bank and cash accounts. You can edit these later from Chart of Accounts.
              </p>

              <div class="space-y-4">
                <Card
                  v-for="(account, index) in bankAccountsForm.bank_accounts"
                  :key="index"
                  class="border-border/70"
                >
                  <CardContent class="pt-6 space-y-4">
                    <div class="flex items-center justify-between">
                      <h4 class="text-sm font-semibold">Account {{ index + 1 }}</h4>
                      <Button
                        v-if="index > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removeBankAccount(index)"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>

                    <div class="space-y-2">
                      <Label>Account Name <span class="text-red-500">*</span></Label>
                      <Input v-model="account.account_name" placeholder="Meezan Bank" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label>Currency <span class="text-red-500">*</span></Label>
                        <Select v-model="account.currency" required>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem v-for="currency in props.currencies" :key="currency.code" :value="currency.code">
                              {{ currency.code }} - {{ currency.name }}
                            </SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                      <div class="space-y-2">
                        <Label>Type <span class="text-red-500">*</span></Label>
                        <Select v-model="account.account_type" required>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="bank">Bank</SelectItem>
                            <SelectItem value="cash">Cash</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>

              <Button type="button" variant="outline" @click="addBankAccount">
                <Plus class="mr-2 h-4 w-4" />
                Add Account
              </Button>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="bankAccountsForm.processing" @click="submitBankAccounts">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Default Accounts -->
            <div v-if="activeStepId === 'default_accounts'" class="space-y-6">
              <Alert v-if="defaultAccountsError" variant="destructive">
                <AlertTriangle class="h-4 w-4" />
                <AlertTitle>Could not save defaults</AlertTitle>
                <AlertDescription>{{ defaultAccountsError }}</AlertDescription>
              </Alert>
              <Alert v-if="!props.transitColumnsReady" class="border-amber-200 bg-amber-50 text-amber-900">
                <AlertTriangle class="h-4 w-4 text-amber-600" />
                <AlertTitle>System update required</AlertTitle>
                <AlertDescription>{{ props.transitColumnsMessage }}</AlertDescription>
              </Alert>
              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Accounts Receivable <span class="text-red-500">*</span></Label>
                  <Select v-model="defaultAccountsForm.ar_account_id" required>
                    <SelectTrigger>
                      <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="account in props.arAccounts" :key="account.id" :value="account.id">
                        {{ account.code }} - {{ account.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-2">
                  <Label>Accounts Payable <span class="text-red-500">*</span></Label>
                  <Select v-model="defaultAccountsForm.ap_account_id" required>
                    <SelectTrigger>
                      <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="account in props.apAccounts" :key="account.id" :value="account.id">
                        {{ account.code }} - {{ account.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Default Revenue <span class="text-red-500">*</span></Label>
                  <Select v-model="defaultAccountsForm.income_account_id" required>
                    <SelectTrigger>
                      <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="account in props.revenueAccounts" :key="account.id" :value="account.id">
                        {{ account.code }} - {{ account.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-2">
                  <Label>Default Expense <span class="text-red-500">*</span></Label>
                  <Select v-model="defaultAccountsForm.expense_account_id" required>
                    <SelectTrigger>
                      <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="account in props.expenseAccounts" :key="account.id" :value="account.id">
                        {{ account.code }} - {{ account.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Default Bank/Cash <span class="text-red-500">*</span></Label>
                  <Select v-model="defaultAccountsForm.bank_account_id" required>
                    <SelectTrigger>
                      <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="account in props.bankAccounts" :key="account.id" :value="account.id">
                        {{ account.code }} - {{ account.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-2">
                  <Label>Retained Earnings <span class="text-red-500">*</span></Label>
                  <Select v-model="defaultAccountsForm.retained_earnings_account_id" required>
                    <SelectTrigger>
                      <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="account in props.retainedEarningsAccounts"
                        :key="account.id"
                        :value="account.id"
                      >
                        {{ account.code }} - {{ account.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Sales Tax Payable</Label>
                  <Select v-model="defaultAccountsForm.sales_tax_payable_account_id">
                    <SelectTrigger>
                      <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="account in props.taxPayableAccounts"
                        :key="account.id"
                        :value="account.id"
                      >
                        {{ account.code }} - {{ account.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-2">
                  <Label>Purchase Tax Receivable</Label>
                  <Select v-model="defaultAccountsForm.purchase_tax_receivable_account_id">
                    <SelectTrigger>
                      <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="account in props.taxReceivableAccounts"
                        :key="account.id"
                        :value="account.id"
                      >
                        {{ account.code }} - {{ account.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="defaultAccountsForm.processing" @click="submitDefaultAccounts">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Partners -->
            <div v-if="activeStepId === 'partners'" class="space-y-6">
              <Alert v-if="partnersError" variant="destructive">
                <AlertTriangle class="h-4 w-4" />
                <AlertTitle>Could not save partners</AlertTitle>
                <AlertDescription>{{ partnersError }}</AlertDescription>
              </Alert>
              <p class="text-sm text-text-secondary">
                Add business partners and profit share percentages. Total share cannot exceed 100%.
              </p>

              <div class="space-y-4">
                <Card
                  v-for="(partner, index) in partnersForm.partners"
                  :key="index"
                  class="border-border/70"
                >
                  <CardContent class="pt-6 space-y-4">
                    <div class="flex items-center justify-between">
                      <h4 class="text-sm font-semibold">Partner {{ index + 1 }}</h4>
                      <Button
                        v-if="index > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removePartner(index)"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label>Name <span class="text-red-500">*</span></Label>
                        <Input v-model="partner.name" placeholder="Partner name" />
                      </div>
                      <div class="space-y-2">
                        <Label>Phone</Label>
                        <Input v-model="partner.phone" placeholder="Optional" />
                      </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label>Profit Share % <span class="text-red-500">*</span></Label>
                        <Input v-model="partner.profit_share_percentage" type="number" step="0.01" />
                      </div>
                      <div class="space-y-2">
                        <Label>Drawing Limit Period</Label>
                        <Select v-model="partner.drawing_limit_period">
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="monthly">Monthly</SelectItem>
                            <SelectItem value="yearly">Yearly</SelectItem>
                            <SelectItem value="none">None</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    <div class="space-y-2">
                      <Label>Drawing Limit Amount</Label>
                      <Input v-model="partner.drawing_limit_amount" type="number" step="0.01" />
                    </div>
                  </CardContent>
                </Card>
              </div>

              <div class="flex items-center justify-between">
                <Button type="button" variant="outline" @click="addPartner">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Partner
                </Button>
                <div :class="['text-sm font-medium', isProfitShareValid ? 'text-emerald-600' : 'text-red-600']">
                  Total profit share: {{ formatMoney(totalProfitShare) }}%
                </div>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="partnersForm.processing || !isProfitShareValid" @click="submitPartners">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Employees -->
            <div v-if="activeStepId === 'employees'" class="space-y-6">
              <p class="text-sm text-text-secondary">
                Add pump attendants and staff. Employees can be edited later from Payroll.
              </p>

              <div class="space-y-4">
                <Card
                  v-for="(employee, index) in employeesForm.employees"
                  :key="index"
                  class="border-border/70"
                >
                  <CardContent class="pt-6 space-y-4">
                    <div class="flex items-center justify-between">
                      <h4 class="text-sm font-semibold">Employee {{ index + 1 }}</h4>
                      <Button
                        v-if="index > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removeEmployee(index)"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label>First Name <span class="text-red-500">*</span></Label>
                        <Input v-model="employee.first_name" />
                      </div>
                      <div class="space-y-2">
                        <Label>Last Name <span class="text-red-500">*</span></Label>
                        <Input v-model="employee.last_name" />
                      </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label>Phone</Label>
                        <Input v-model="employee.phone" />
                      </div>
                      <div class="space-y-2">
                        <Label>Designation</Label>
                        <div class="flex flex-wrap gap-2">
                          <Button
                            v-for="preset in employeeDesignationPresets"
                            :key="preset"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="employee.position = preset"
                          >
                            {{ preset }}
                          </Button>
                        </div>
                        <Input v-model="employee.position" placeholder="Custom designation" />
                      </div>
                    </div>

                    <div class="space-y-2">
                      <Label>Base Salary <span class="text-red-500">*</span></Label>
                      <Input v-model="employee.base_salary" type="number" step="0.01" />
                    </div>
                  </CardContent>
                </Card>
              </div>

              <Button type="button" variant="outline" @click="addEmployee">
                <Plus class="mr-2 h-4 w-4" />
                Add Employee
              </Button>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="employeesForm.processing" @click="submitEmployees">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Tax Settings -->
            <div v-if="activeStepId === 'tax_settings'" class="space-y-6">
              <div class="space-y-3">
                <Label>Tax Registration <span class="text-red-500">*</span></Label>
                <div class="flex items-center gap-3">
                  <Switch v-model:checked="taxSettingsForm.tax_registered" />
                  <span class="text-sm text-text-secondary">Tax registered business</span>
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Tax Rate (%)</Label>
                  <Input v-model="taxSettingsForm.tax_rate" type="number" step="0.01" />
                </div>
                <div class="space-y-2">
                  <Label>Tax Inclusive</Label>
                  <div class="flex items-center gap-3">
                    <Switch v-model:checked="taxSettingsForm.tax_inclusive" />
                    <span class="text-sm text-text-secondary">Prices include tax</span>
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="taxSettingsForm.processing" @click="submitTaxSettings">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Numbering -->
            <div v-if="activeStepId === 'numbering'" class="space-y-6">
              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Invoice Prefix <span class="text-red-500">*</span></Label>
                  <Input v-model="numberingForm.invoice_prefix" />
                </div>
                <div class="space-y-2">
                  <Label>Invoice Start Number <span class="text-red-500">*</span></Label>
                  <Input v-model="numberingForm.invoice_start_number" type="number" />
                </div>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Bill Prefix <span class="text-red-500">*</span></Label>
                  <Input v-model="numberingForm.bill_prefix" />
                </div>
                <div class="space-y-2">
                  <Label>Bill Start Number <span class="text-red-500">*</span></Label>
                  <Input v-model="numberingForm.bill_start_number" type="number" />
                </div>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="numberingForm.processing" @click="submitNumbering">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Payment Terms -->
            <div v-if="activeStepId === 'payment_terms'" class="space-y-6">
              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Customer Payment Terms (days)</Label>
                  <Input v-model="paymentTermsForm.default_customer_payment_terms" type="number" />
                </div>
                <div class="space-y-2">
                  <Label>Vendor Payment Terms (days)</Label>
                  <Input v-model="paymentTermsForm.default_vendor_payment_terms" type="number" />
                </div>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="paymentTermsForm.processing" @click="submitPaymentTerms">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Products You Sell -->
            <div v-if="activeStepId === 'fuel_items'" class="space-y-6">
              <p class="text-sm text-text-secondary">
                Select the products your station sells. These will be available as options when configuring tanks, pumps, and rates.
              </p>

              <div class="space-y-3">
                <Label
                  v-for="item in fuelItemsForm.fuel_items"
                  :key="item.fuel_category"
                  class="flex items-start gap-3 rounded-lg border p-4 cursor-pointer hover:bg-muted/30 transition-colors has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5"
                >
                  <Checkbox
                    :id="'fuel-' + item.fuel_category"
                    v-model="item.enabled"
                    class="mt-0.5"
                  />
                  <div class="grid gap-1 font-normal">
                    <span class="text-sm font-medium leading-none">{{ item.name }}</span>
                    <span class="text-sm text-muted-foreground">{{ item.description }}</span>
                  </div>
                </Label>
              </div>

              <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <strong>Note:</strong> Prices will be set in the Rates step. Packaged lubricant bottles (0.7L, 1L, etc.) are configured separately in the Lubricants step.
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="fuelItemsForm.processing" @click="submitFuelItems">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Tanks -->
            <div v-if="activeStepId === 'tanks'" class="space-y-6">
              <p class="text-sm text-text-secondary">
                Configure your storage tanks. Link each tank to a product from the previous step.
              </p>

              <div class="space-y-4">
                <Card v-for="(tank, index) in tanksForm.tanks" :key="index" class="border-border/70">
                  <CardContent class="pt-6 space-y-4">
                    <div class="flex items-center justify-between">
                      <h4 class="text-sm font-semibold">Tank {{ index + 1 }}</h4>
                      <Button
                        v-if="index > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removeTank(index)"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label>Tank Name <span class="text-red-500">*</span></Label>
                        <Input v-model="tank.name" />
                      </div>
                      <div class="space-y-2">
                        <Label>Tank Code <span class="text-red-500">*</span></Label>
                        <Input v-model="tank.code" />
                      </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label>Capacity (Liters) <span class="text-red-500">*</span></Label>
                        <Input v-model="tank.capacity" type="number" step="0.01" />
                      </div>
                      <div class="space-y-2">
                        <Label>Linked Product <span class="text-red-500">*</span></Label>
                        <Select v-model="tank.linked_item_id">
                          <SelectTrigger>
                            <SelectValue placeholder="Select product" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem
                              v-for="option in tankItemOptions"
                              :key="option.id"
                              :value="option.id"
                            >
                              {{ option.label }}
                            </SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    <div class="space-y-2">
                      <Label>Dip Stick Code</Label>
                      <Input v-model="tank.dip_stick_code" placeholder="Optional" />
                    </div>
                  </CardContent>
                </Card>
              </div>

              <div class="flex flex-wrap gap-2">
                <Button v-if="hasFuelCategory('petrol')" type="button" variant="outline" @click="addPetrolTank">
                  Add Petrol Tank
                </Button>
                <Button v-if="hasFuelCategory('diesel')" type="button" variant="outline" @click="addDieselTank">
                  Add Diesel Tank
                </Button>
                <Button v-if="hasFuelCategory('high_octane')" type="button" variant="outline" @click="addHiOctaneTank">
                  Add Hi-Octane Tank
                </Button>
                <Button v-if="hasBulkLubricant" type="button" variant="outline" @click="addBulkLubricantTank">
                  Add Bulk Lubricant Tank
                </Button>
                <Button type="button" variant="outline" @click="addTank">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Custom Tank
                </Button>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="tanksForm.processing" @click="submitTanks">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Pumps -->
            <div v-if="activeStepId === 'pumps'" class="space-y-6">
              <Alert v-if="pumpsError" variant="destructive">
                <AlertTriangle class="h-4 w-4" />
                <AlertTitle>Could not save pumps</AlertTitle>
                <AlertDescription>{{ pumpsError }}</AlertDescription>
              </Alert>
              <p class="text-sm text-text-secondary">
                Assign each pump to a tank. Save tanks first if the list is empty.
              </p>

              <div class="space-y-4">
                <Card v-for="(pump, index) in pumpsForm.pumps" :key="index" class="border-border/70">
                  <CardContent class="pt-6 space-y-4">
                    <div class="flex items-center justify-between">
                      <h4 class="text-sm font-semibold">Pump {{ index + 1 }}</h4>
                      <Button
                        v-if="index > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removePump(index)"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label>Pump Name <span class="text-red-500">*</span></Label>
                        <Input v-model="pump.name" />
                      </div>
                      <div class="space-y-2">
                        <Label>Linked Tank <span class="text-red-500">*</span></Label>
                        <Select v-model="pump.tank_id">
                          <SelectTrigger>
                            <SelectValue placeholder="Select tank" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem v-for="option in tankOptions" :key="option.id" :value="option.id">
                              {{ option.label }}
                            </SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    <div class="space-y-4">
                      <div class="flex items-center gap-4">
                        <div class="space-y-2 w-40">
                          <Label>Nozzles</Label>
                          <Select v-model="pump.nozzle_count">
                            <SelectTrigger>
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem :value="1">1 (Single side)</SelectItem>
                              <SelectItem :value="2">2 (Front & Back)</SelectItem>
                            </SelectContent>
                          </Select>
                        </div>
                      </div>

                      <!-- Initial Readings Table -->
                      <div>
                        <p class="text-xs text-text-secondary">
                          Initial meter readings (optional). These set the starting point for today only and won’t block adding older readings later.
                        </p>
                        <div class="border rounded-lg overflow-hidden mt-2">
                        <table class="w-full text-sm">
                          <thead class="bg-muted/50">
                            <tr>
                              <th class="text-left py-2 px-3 font-medium">Side</th>
                              <th class="text-right py-2 px-3 font-medium">Electronic</th>
                              <th class="text-right py-2 px-3 font-medium">Manual</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr class="border-t">
                              <td class="py-2 px-3 font-medium">Front</td>
                              <td class="py-2 px-3">
                                <Input v-model="pump.front_electronic" type="number" step="0.01" placeholder="0" class="text-right" />
                              </td>
                              <td class="py-2 px-3">
                                <Input v-model="pump.front_manual" type="number" step="0.01" placeholder="0" class="text-right" />
                              </td>
                            </tr>
                            <tr v-if="Number(pump.nozzle_count) === 2" class="border-t">
                              <td class="py-2 px-3 font-medium">Back</td>
                              <td class="py-2 px-3">
                                <Input v-model="pump.back_electronic" type="number" step="0.01" placeholder="0" class="text-right" />
                              </td>
                              <td class="py-2 px-3">
                                <Input v-model="pump.back_manual" type="number" step="0.01" placeholder="0" class="text-right" />
                              </td>
                            </tr>
                          </tbody>
                        </table>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>

              <Button type="button" variant="outline" @click="addPump">
                <Plus class="mr-2 h-4 w-4" />
                Add Pump
              </Button>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="pumpsForm.processing" @click="submitPumps">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Rates -->
            <div v-if="activeStepId === 'rates'" class="space-y-6">
              <p class="text-sm text-text-secondary">
                OGRA rates override the initial prices above. Rates are announced on the 1st and 15th, effective from 00:01.
              </p>

              <div class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label>Effective Date</Label>
                    <Input v-model="ratesForm.effective_date" type="date" />
                  </div>
                  <div class="space-y-2">
                    <Label>Quick Select</Label>
                    <div class="flex flex-wrap gap-2">
                      <Button type="button" variant="outline" size="sm" @click="setOgraDate(1)">
                        Use 1st
                      </Button>
                      <Button type="button" variant="outline" size="sm" @click="setOgraDate(15)">
                        Use 15th
                      </Button>
                      <Button type="button" variant="outline" size="sm" @click="setMostRecentOgraDate">
                        Most Recent OGRA
                      </Button>
                    </div>
                  </div>
                </div>

                <Card class="border-border/70">
                  <CardContent class="pt-6">
                    <div class="text-sm font-semibold mb-3">Current Rates</div>
                    <div class="space-y-3">
                      <div
                        v-for="item in props.fuelItems"
                        :key="item.id"
                        class="flex items-center justify-between border-b border-border/40 pb-2 last:border-b-0"
                      >
                        <div>
                          <div class="font-medium">{{ item.name }}</div>
                          <div class="text-xs text-text-secondary">
                            Effective: {{ formatDate(rateMap.get(item.id || '')?.effective_date) }}
                          </div>
                        </div>
                        <div class="text-right text-sm">
                          <div>Buy: {{ formatMoney(rateMap.get(item.id || '')?.purchase_rate ?? item.avg_cost ?? 0) }}</div>
                          <div>Sell: {{ formatMoney(rateMap.get(item.id || '')?.sale_rate ?? item.sale_price ?? 0) }}</div>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <div class="space-y-4">
                  <Card v-for="(rate, index) in ratesForm.rates" :key="rate.item_id" class="border-border/70">
                    <CardContent class="pt-6 space-y-4">
                      <div class="font-medium">
                        {{ props.fuelItems[index]?.name || 'Fuel Item' }}
                      </div>
                      <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                          <Label>Purchase Rate ({{ currencyCode }}/L)</Label>
                          <Input v-model="rate.purchase_rate" type="number" step="0.01" />
                        </div>
                        <div class="space-y-2">
                          <Label>Sale Rate ({{ currencyCode }}/L)</Label>
                          <Input v-model="rate.sale_rate" type="number" step="0.01" />
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="ratesForm.processing" @click="submitRates">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Lubricants -->
            <div v-if="activeStepId === 'lubricants'" class="space-y-6">
              <p class="text-sm text-text-secondary">
                Add packaged lubricants (bottles). The open/bulk lubricant sold by liter is configured in Products and Tanks.
              </p>

              <div class="space-y-4">
                <Card v-for="(lubricant, index) in lubricantsForm.lubricants" :key="index" class="border-border/70">
                  <CardContent class="pt-6 space-y-4">
                    <div class="flex items-center justify-between">
                      <h4 class="text-sm font-semibold">Product {{ index + 1 }}</h4>
                      <Button
                        v-if="index > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removeLubricant(index)"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label>Name <span class="text-red-500">*</span></Label>
                        <Input v-model="lubricant.name" />
                        <p class="text-xs text-text-secondary">Include type (bike/car/truck) in the name if needed.</p>
                      </div>
                      <div class="space-y-2">
                        <Label>SKU <span class="text-red-500">*</span></Label>
                        <Input v-model="lubricant.sku" />
                      </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                      <div class="space-y-2">
                        <Label>Brand</Label>
                        <Input v-model="lubricant.brand" placeholder="Brand (optional)" />
                      </div>
                      <div class="space-y-2">
                        <Label>Unit / Size</Label>
                        <Input v-model="lubricant.unit" placeholder="1L, 2L, 40L" />
                      </div>
                      <div class="space-y-2">
                        <Label>Opening Quantity</Label>
                        <Input v-model="lubricant.opening_quantity" type="number" step="1" />
                      </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label>Purchase Cost <span class="text-red-500">*</span></Label>
                        <Input v-model="lubricant.cost_price" type="number" step="0.01" />
                      </div>
                      <div class="space-y-2">
                        <Label>Sale Price <span class="text-red-500">*</span></Label>
                        <Input v-model="lubricant.sale_price" type="number" step="0.01" />
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>

              <div class="flex flex-wrap gap-2">
                <Button type="button" variant="outline" @click="addLubricant">
                  <Plus class="mr-2 h-4 w-4" />
                  Add Lubricant
                </Button>
                <Button type="button" variant="outline" @click="addLubricantPreset">
                  Add Packed Oil Presets
                </Button>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="lubricantsForm.processing" @click="submitLubricants">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Initial Stock -->
            <div v-if="activeStepId === 'initial_stock'" class="space-y-6">
              <p class="text-sm text-text-secondary">
                Record opening tank readings for starting inventory valuation. These can be updated later.
              </p>

              <div class="space-y-2">
                <Label>Stock Date</Label>
                <Input v-model="openingStockForm.stock_date" type="date" />
              </div>

              <div class="space-y-4">
                <Card
                  v-for="(reading, index) in openingStockForm.tank_readings"
                  :key="reading.tank_id"
                  class="border-border/70"
                >
                  <CardContent class="pt-6 space-y-4">
                    <div class="text-sm font-semibold">
                      {{ props.tanks[index]?.name || 'Tank' }}
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                      <div class="space-y-2">
                        <Label>Stick Reading</Label>
                        <Input v-model="reading.stick_reading" type="number" step="0.01" />
                      </div>
                      <div class="space-y-2">
                        <Label>Liters <span class="text-red-500">*</span></Label>
                        <Input v-model="reading.liters" type="number" step="0.01" />
                      </div>
                      <div class="space-y-2">
                        <Label>Value (optional)</Label>
                        <Input v-model="reading.value" type="number" step="0.01" />
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="openingStockForm.processing" @click="submitOpeningStock">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Opening Cash -->
            <div v-if="activeStepId === 'opening_cash'" class="space-y-6">
              <p class="text-sm text-text-secondary">
                Record opening balances for cash and bank accounts. Values can be reconciled later.
              </p>

              <div class="space-y-2">
                <Label>As of Date</Label>
                <Input v-model="openingCashForm.as_of_date" type="date" />
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Cash on Hand ({{ currencyCode }})</Label>
                  <Input v-model="openingCashForm.cash_on_hand" type="number" step="0.01" />
                </div>
                <div class="space-y-2">
                  <Label>Operating Bank Balance ({{ currencyCode }})</Label>
                  <Input v-model="openingCashForm.bank_balance" type="number" step="0.01" />
                </div>
              </div>

              <div class="space-y-4">
                <div class="text-sm font-semibold">Other Bank Balances</div>
                <div class="grid gap-4 md:grid-cols-2">
                  <div
                    v-for="row in openingCashForm.bank_balances"
                    :key="row.account_id"
                    class="space-y-2"
                  >
                    <Label>{{ bankAccountLabels.get(row.account_id) || 'Bank Account' }}</Label>
                    <Input v-model="row.balance" type="number" step="0.01" />
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="openingCashForm.processing" @click="submitOpeningCash">
                  Save & Continue
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Complete -->
            <div v-if="activeStepId === 'complete'" class="space-y-6">
              <div class="rounded-lg border border-border/70 bg-muted/30 p-4">
                <div class="flex items-center gap-3">
                  <CheckCircle class="h-6 w-6 text-emerald-600" />
                  <div>
                    <div class="font-semibold">Setup Status</div>
                    <div class="text-sm text-text-secondary">
                      {{ props.wizard.is_complete ? 'All required setup steps are complete.' : 'Finish the remaining steps to complete setup.' }}
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between pt-6 border-t">
                <Button type="button" variant="outline" @click="previousStep">
                  <ArrowLeft class="mr-2 h-4 w-4" />
                  Previous
                </Button>
                <Button type="button" :disabled="props.wizard.is_complete" @click="submitComplete">
                  {{ props.wizard.is_complete ? 'Setup Complete' : 'Mark Setup Complete' }}
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
