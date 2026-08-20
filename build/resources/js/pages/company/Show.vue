<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import InlineEditable from '@/components/InlineEditable.vue'
import MoneyText from '@/components/MoneyText.vue'
import DateTimeText from '@/components/DateTimeText.vue'
import Derivation from '@/components/Derivation.vue'
import type { DerivationLine } from '@/components/Derivation.vue'
import MetaChip from '@/components/MetaChip.vue'
import type { RegisterColumn } from '@/components/LedgerRegister.vue'
import { useInlineEdit } from '@/composables/useInlineEdit'
import { useFormFeedback } from '@/composables/useFormFeedback'
import { useLexicon } from '@/composables/useLexicon'
import { formatDateTime } from '@/lib/datetime'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { BreadcrumbItem } from '@/types'
import {
  Building2,
  Users,
  UserPlus,
  Mail,
  Calendar,
  Shield,
  MoreVertical,
  Trash2,
  UserCog,
  CheckCircle2,
  XCircle,
  Receipt,
  AlertTriangle,
  Wallet,
  Ban,
  BarChart3,
  Settings,
  Globe,
  Languages,
  TrendingUp,
  TrendingDown,
  FileText,
  Package,
  Plus,
  Loader2,
  Eye,
  Pencil,
  Power,
  PowerOff,
  Warehouse,
  ChevronDown,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { currencySymbol as sharedCurrencySymbol } from '@/lib/utils'

const formatDate = (value: string) => formatDateTime(value, { mode: 'date' })

interface Company {
  id: string
  name: string
  slug: string
  base_currency: string
  is_active: boolean
  created_at: string
  industry?: string
  industry_code?: string | null
  industry_name?: string | null
  country?: string
  language?: string
  locale?: string
  fiscal_year_start_month?: number
}

interface Stats {
  total_users: number
  active_users: number
  admins: number
}

interface Financials {
  ar_outstanding: number
  ar_outstanding_count: number
  ar_overdue: number
  ar_overdue_count: number
  payments_mtd: number
  expenses_mtd_placeholder: string
  aging: {
    current: number
    bucket_1_30: number
    bucket_31_60: number
    bucket_61_90: number
    bucket_90_plus: number
  }
  quick_stats: {
    invoices_sent_this_month: number
    payments_received_this_month: number
    new_customers_this_month: number
  }
  recent_activity: Array<{
    type: string
    label: string
    amount?: number
    currency?: string
    status?: string
    occurred_at: string
    /** Which column the figure belongs in. `null` for entries with no money. */
    direction?: 'in' | 'out' | null
  }>
}

interface User {
  id: string
  name: string | null
  email: string
  role: string
  is_active: boolean
  joined_at: string | null
}

interface DashboardData {
  cash_position: {
    total: number
    accounts: Array<{ name: string, balance: number, currency: string }>
  }
  money_in_out: {
    money_in: { current: number, last: number, growth: number }
    money_out: { current: number, last: number, growth: number }
  }
  needs_attention: {
    overdue_invoices: number
    bills_due_soon: number
    bills_due_soon_amount?: number
    unreconciled_transactions: number
  }
  profit_loss: {
    income: number
    expenses: number
    profit: number
    last_month_profit: number
    profit_growth: number
    period: string
  }
}

interface FuelHomeDashboard {
  summary: {
    active_pumps: number
    today_readings: number
    pending_tank_readings: number
  }
  pendingHandovers: {
    count: number
    total_amount: number
  }
  tanks: Array<{
    tank_id: string
    item_name: string
    capacity: number
    current_level: number
    fill_percentage: number
    last_reading_date: string | null
  }>
  rates: Array<{
    item_name: string
    purchase_rate: number
    sale_rate: number
    margin: number
    effective_date: string | null
  }>
  products?: FuelProductDashboard | null
}

interface FuelProductSalesPeriod {
  quantity: number
  amount: number
  cogs: number
}

interface FuelProductDashboardItem {
  id: string
  name: string
  sku: string | null
  fuel_category: string | null
  unit: string | null
  is_active: boolean
  track_inventory: boolean
  current_stock: number
  available_stock: number
  last_stock_movement_at: string | null
  last_stock_movement_date: string | null
  last_stock_movement_type: string | null
  last_stock_movement_reason: string | null
  low_stock_level: number
  is_low_stock: boolean
  capacity: number | null
  fill_percentage: number | null
  last_dip_quantity: number | null
  last_dip_at: string | null
  last_dip_recorded_at: string | null
  last_dip_status: string | null
  last_tank_reading_type: string | null
  stock_variance: number | null
  stock_value: number
  purchase_rate: number
  sale_rate: number
  margin: number
  last_sold_at: string | null
  sales: {
    yesterday: FuelProductSalesPeriod
    last_week: FuelProductSalesPeriod
    last_month: FuelProductSalesPeriod
    last_sold_at: string | null
  }
}

interface FuelProductDashboard {
  summary: {
    as_of_date?: string
    total_products: number
    active_products: number
    fuel_products: number
    low_stock_count: number
    inventory_value: number
    yesterday_sales: number
    last_week_sales: number
    last_month_sales: number
    yesterday_liters: number
    last_week_liters: number
    last_month_liters: number
  }
  low_stock: FuelProductDashboardItem[]
  top_products: FuelProductDashboardItem[]
  items: FuelProductDashboardItem[]
}

interface FuelTankOption {
  id: string
  name: string
  code: string
  capacity: number | null
  linked_item_id: string | null
}

const props = defineProps<{
  company: Company
  stats: Stats
  users: User[]
  currentUserRole: string
  financials: Financials
  dashboard: DashboardData
  isFuelStation?: boolean
  fuelDashboard?: FuelHomeDashboard | null
  productDashboardDate?: string
  fuelTanks?: FuelTankOption[]
}>()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: props.company.name },
])

// Tab state
const activeTab = ref('overview')

// Setup inline editing
const inlineEdit = useInlineEdit({
  endpoint: `/${props.company.slug}/settings`,
  successMessage: 'Setting updated successfully',
  errorMessage: 'Failed to update setting',
})

const { t, tpl } = useLexicon()
const { showError } = useFormFeedback()

const productsDialogOpen = ref(false)
const productAsOfDate = ref(props.productDashboardDate ?? new Date().toISOString().slice(0, 10))
const tankDialogOpen = ref(false)
const activeTankRowIndex = ref<number | null>(null)
const tankDraft = ref({
  name: '',
  code: '',
  capacity: '',
  low_level_alert: '',
})
const tankDraftErrors = ref<Record<string, string>>({})

const fuelTanks = ref<FuelTankOption[]>(props.fuelTanks ?? [])

watch(
  () => props.fuelTanks,
  (next) => {
    fuelTanks.value = next ?? []
  }
)

watch(
  () => props.productDashboardDate,
  (next) => {
    productAsOfDate.value = next ?? new Date().toISOString().slice(0, 10)
  }
)

// Register editable fields
const nameField = inlineEdit.registerField('name', props.company.name)
const languageField = inlineEdit.registerField('language', props.company.language || 'en')
const localeField = inlineEdit.registerField('locale', props.company.locale || 'en_US')
const fiscalYearField = inlineEdit.registerField('fiscal_year_start_month', props.company.fiscal_year_start_month || 1)

// User management dialogs
const createUserDialogOpen = ref(false)
const roleDialogOpen = ref(false)
const removeDialogOpen = ref(false)
const selectedUser = ref<User | null>(null)
const productDeleteDialogOpen = ref(false)
const selectedProduct = ref<FuelProductDashboardItem | null>(null)

const createUserForm = useForm({
  name: '',
  email: '',
  role: 'operations',
  password: '',
  password_confirmation: '',
})

const roleForm = useForm({
  userId: '',
  role: '',
})

const removeForm = useForm({})

const todayLocal = () => {
  const date = new Date()
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const buildNozzleRows = (count = 2) => Array.from({ length: count }, (_, index) => ({
  code: '',
  label: index === 0 ? 'Front' : 'Back',
  opening_electronic: '',
  opening_manual: '',
}))

const buildPumpSetup = (index = 0) => ({
  name: `Point ${index + 1}`,
  nozzle_count: 2,
  nozzles: buildNozzleRows(2),
})

const buildProductRow = (index = 0) => ({
  type: 'fuel',
  name: 'Petrol',
  sku: '',
  fuel_category: 'petrol',
  lubricant_format: 'packaged',
  packaging: 'open',
  category_name: '',
  unit_of_measure: 'liters',
  track_inventory: true,
  purchase_rate: '',
  sale_rate: '',
  opening_quantity: '',
  tank_id: '',
  new_tank: null as null | {
    name: string
    code: string
    capacity: string | number
    low_level_alert: string | number
  },
  create_pump_points: true,
  pump_setups: [buildPumpSetup(index)],
})

const productsForm = useForm({
  effective_date: todayLocal(),
  products: [buildProductRow()],
})

const defaultFuelNames: Record<string, string> = {
  petrol: 'Petrol',
  diesel: 'Diesel',
  high_octane: 'Hi-Octane',
}

const isOpenPackaging = (row: ReturnType<typeof buildProductRow>) => {
  if (row.type === 'fuel') return true
  if (row.type === 'lubricant') return row.lubricant_format === 'open'
  if (row.type === 'other') return row.packaging === 'open'
  return false
}

const shouldTrackInventory = (row: ReturnType<typeof buildProductRow>) => {
  return row.track_inventory || Number(row.opening_quantity || 0) > 0 || Boolean(row.tank_id || row.new_tank)
}

const setRowDefaults = (row: ReturnType<typeof buildProductRow>) => {
  if (row.type === 'fuel') {
    row.fuel_category = row.fuel_category || 'petrol'
    row.packaging = 'open'
    row.lubricant_format = 'packaged'
    row.track_inventory = true
    row.unit_of_measure = row.unit_of_measure || 'liters'
    row.create_pump_points = true
    if (!row.pump_setups?.length) {
      row.pump_setups = [buildPumpSetup()]
    }
    if (!row.name) {
      row.name = defaultFuelNames[row.fuel_category] || 'Fuel'
    }
    return
  }

  if (row.type === 'lubricant') {
    row.lubricant_format = row.lubricant_format || 'packaged'
    row.packaging = row.lubricant_format
    row.track_inventory = true
    row.fuel_category = row.lubricant_format === 'open' ? 'lubricant' : ''
    row.unit_of_measure = row.unit_of_measure || (row.lubricant_format === 'open' ? 'liters' : 'bottle')
    row.create_pump_points = false
    row.pump_setups = []
    return
  }

  row.packaging = row.packaging || 'packaged'
  row.fuel_category = ''
  row.lubricant_format = 'packaged'
  row.unit_of_measure = row.unit_of_measure || 'unit'
  if (row.track_inventory === null || row.track_inventory === undefined) {
    row.track_inventory = true
  }
  row.create_pump_points = false
  row.pump_setups = []
}

const handleTypeChange = (row: ReturnType<typeof buildProductRow>) => {
  row.name = row.type === 'fuel' ? '' : row.name
  row.sku = row.sku || ''
  row.category_name = row.type === 'other' ? row.category_name : ''
  row.packaging = ''
  row.lubricant_format = ''
  row.fuel_category = ''
  row.opening_quantity = ''
  row.tank_id = ''
  row.new_tank = null
  row.create_pump_points = true
  row.pump_setups = [buildPumpSetup()]
  setRowDefaults(row)
}

const handleFuelCategoryChange = (row: ReturnType<typeof buildProductRow>) => {
  if (!row.name) {
    row.name = defaultFuelNames[row.fuel_category] || 'Fuel'
  }
}

const handleStorageTypeChange = (row: ReturnType<typeof buildProductRow>) => {
  row.track_inventory = true

  if (row.type === 'lubricant') {
    row.packaging = row.lubricant_format
    row.fuel_category = row.lubricant_format === 'open' ? 'lubricant' : ''
    if (row.lubricant_format === 'open' && (!row.unit_of_measure || row.unit_of_measure === 'bottle')) {
      row.unit_of_measure = 'liters'
    }
    if (row.lubricant_format === 'packaged' && (!row.unit_of_measure || row.unit_of_measure === 'liters')) {
      row.unit_of_measure = 'bottle'
    }
  }

  if (row.type === 'other') {
    if (row.packaging === 'open' && (!row.unit_of_measure || row.unit_of_measure === 'unit')) {
      row.unit_of_measure = 'liters'
    }
    if (row.packaging === 'packaged' && (!row.unit_of_measure || row.unit_of_measure === 'liters')) {
      row.unit_of_measure = 'unit'
    }
  }

  if (!isOpenPackaging(row)) {
    row.tank_id = ''
    row.new_tank = null
    row.create_pump_points = false
    row.pump_setups = []
  }
}

const syncNozzleRows = (row: ReturnType<typeof buildProductRow>, pumpIndex = 0) => {
  const pumpSetup = row.pump_setups[pumpIndex]
  if (!pumpSetup) return

  const count = Math.max(1, Math.min(2, Number(pumpSetup.nozzle_count || 2)))
  pumpSetup.nozzle_count = count
  const existing = pumpSetup.nozzles || []
  pumpSetup.nozzles = Array.from({ length: count }, (_, index) => ({
    code: existing[index]?.code || '',
    label: existing[index]?.label || (index === 0 ? 'Front' : 'Back'),
    opening_electronic: existing[index]?.opening_electronic || '',
    opening_manual: existing[index]?.opening_manual || '',
  }))
}

const handlePumpSetupToggle = (row: ReturnType<typeof buildProductRow>, checked: boolean) => {
  row.create_pump_points = checked

  if (checked) {
    if (!row.pump_setups.length) {
      row.pump_setups = [buildPumpSetup()]
    }
    row.pump_setups.forEach((_, index) => syncNozzleRows(row, index))
    return
  }

  row.pump_setups = []
}

const addPumpPoint = (row: ReturnType<typeof buildProductRow>) => {
  row.create_pump_points = true
  row.pump_setups.push(buildPumpSetup(row.pump_setups.length))
}

const removePumpPoint = (row: ReturnType<typeof buildProductRow>, pumpIndex: number) => {
  row.pump_setups.splice(pumpIndex, 1)
  row.pump_setups.forEach((pumpSetup, index) => {
    if (!pumpSetup.name.trim()) {
      pumpSetup.name = `Point ${index + 1}`
    }
  })

  if (row.pump_setups.length === 0) {
    row.create_pump_points = false
  }
}

const clearNewTank = (row: ReturnType<typeof buildProductRow>) => {
  row.new_tank = null
}

const handleTankSelection = (row: ReturnType<typeof buildProductRow>) => {
  if (row.tank_id) {
    row.new_tank = null
  }
}

const addProductRow = () => {
  productsForm.products.push(buildProductRow(productsForm.products.length))
}

const removeProductRow = (index: number) => {
  if (productsForm.products.length <= 1) return
  productsForm.products.splice(index, 1)
}

const openTankDialog = (index: number) => {
  activeTankRowIndex.value = index
  const row = productsForm.products[index]
  tankDraft.value = {
    name: row?.new_tank?.name || '',
    code: row?.new_tank?.code || '',
    capacity: row?.new_tank?.capacity || '',
    low_level_alert: row?.new_tank?.low_level_alert || '',
  }
  tankDraftErrors.value = {}
  tankDialogOpen.value = true
}

const saveTankDraft = () => {
  if (activeTankRowIndex.value === null) return
  const row = productsForm.products[activeTankRowIndex.value]
  if (!row) return
  tankDraftErrors.value = {}
  if (!tankDraft.value.name.trim()) {
    tankDraftErrors.value.name = 'Tank name is required.'
  }
  if (!tankDraft.value.code.trim()) {
    tankDraftErrors.value.code = 'Tank code is required.'
  }
  if (tankDraft.value.capacity === '' || Number(tankDraft.value.capacity) < 1) {
    tankDraftErrors.value.capacity = 'Tank capacity is required.'
  }
  if (Object.keys(tankDraftErrors.value).length > 0) {
    showError('Fill all required tank details.')
    return
  }

  if (!row.name.trim()) {
    showError('Enter a product name before creating a tank.')
    return
  }
  if (row.type === 'fuel' && !row.fuel_category) {
    showError('Select a fuel type before creating a tank.')
    return
  }

  row.tank_id = ''
  row.new_tank = {
    name: tankDraft.value.name.trim(),
    code: tankDraft.value.code.trim(),
    capacity: tankDraft.value.capacity,
    low_level_alert: tankDraft.value.low_level_alert,
  }
  tankDraft.value = {
    name: '',
    code: '',
    capacity: '',
    low_level_alert: '',
  }
  tankDialogOpen.value = false
  toast.success('Tank added to this product setup')
}

const buildProductSetupPayload = () => ({
  effective_date: productsForm.effective_date,
  products: productsForm.products.slice(0, 1).map((row) => ({
    type: row.type,
    name: row.name,
    sku: row.sku,
    fuel_category: row.fuel_category,
    lubricant_format: row.lubricant_format,
    packaging: row.packaging,
    category_name: row.category_name,
    unit_of_measure: row.unit_of_measure,
    track_inventory: shouldTrackInventory(row),
    purchase_rate: row.purchase_rate,
    sale_rate: row.sale_rate,
    opening_quantity: row.opening_quantity,
    tank_id: row.new_tank ? null : (row.tank_id || null),
    new_tank: row.new_tank,
    pump_setups: row.type === 'fuel' && row.track_inventory && row.create_pump_points
      ? row.pump_setups
      : [],
  })),
})

const submitProducts = () => {
  let handled = false
  productsForm
    .transform(() => buildProductSetupPayload())
    .post(`/${props.company.slug}/fuel/products/setup`, {
    preserveScroll: true,
    onSuccess: (page) => {
      handled = true
      const flash = (page.props as { flash?: { error?: string; success?: string } })?.flash
      if (flash?.error) {
        return
      }
      const updatedTanks = (page.props as { fuelTanks?: FuelTankOption[] })?.fuelTanks
      if (updatedTanks) {
        fuelTanks.value = updatedTanks
      }
      if (!flash?.error && !flash?.success) {
        toast.success('Products saved successfully')
      }
      productsForm.reset()
      productsForm.products = [buildProductRow()]
      productsForm.clearErrors()
      productsDialogOpen.value = false
      router.reload({
        only: ['fuelDashboard', 'fuelTanks'],
        preserveScroll: true,
      })
    },
    onError: (errors) => {
      handled = true
      showError(errors)
    },
    onFinish: () => {
      if (!handled) {
        toast.error('Failed to save products. Please try again.')
      }
    },
  })
}

const canManage = computed(() => ['owner', 'manager'].includes(props.currentUserRole))
const isFuelStationCompany = computed(() => props.isFuelStation === true)
const pageTitle = computed(() => isFuelStationCompany.value ? 'Products You Sell' : props.company.name)
const pageIcon = computed(() => isFuelStationCompany.value ? Package : Building2)
const pageBreadcrumbs = computed<BreadcrumbItem[]>(() => isFuelStationCompany.value
  ? [{ title: 'Products', href: `/${props.company.slug}` }]
  : breadcrumbs.value
)
const fuelProductDashboard = computed(() => props.fuelDashboard?.products ?? null)
const fuelProductSummary = computed(() => fuelProductDashboard.value?.summary ?? {
  total_products: 0,
  active_products: 0,
  fuel_products: 0,
  low_stock_count: 0,
  inventory_value: 0,
  yesterday_sales: 0,
  last_week_sales: 0,
  last_month_sales: 0,
  yesterday_liters: 0,
  last_week_liters: 0,
  last_month_liters: 0,
})
const fuelProductRows = computed(() => fuelProductDashboard.value?.items ?? [])
const lowStockProducts = computed(() => fuelProductDashboard.value?.low_stock ?? [])
const topFuelProducts = computed(() => fuelProductDashboard.value?.top_products ?? [])

const applyProductDate = () => {
  router.get(`/${props.company.slug}`, {
    product_date: productAsOfDate.value,
  }, {
    only: ['fuelDashboard', 'productDashboardDate'],
    preserveScroll: true,
    preserveState: true,
  })
}

const resetProductDate = () => {
  router.get(`/${props.company.slug}`, {}, {
    only: ['fuelDashboard', 'productDashboardDate'],
    preserveScroll: true,
    preserveState: true,
  })
}

const availableRoles = ['manager', 'accountant', 'operations']

const languageOptions = [
  { value: 'en', label: 'English' },
  { value: 'ar', label: 'Arabic' },
  { value: 'fr', label: 'French' },
  { value: 'de', label: 'German' },
  { value: 'es', label: 'Spanish' },
]

const localeOptions = [
  { value: 'en_US', label: 'English (US)' },
  { value: 'en_GB', label: 'English (UK)' },
  { value: 'ar_SA', label: 'Arabic (Saudi Arabia)' },
  { value: 'ar_AE', label: 'Arabic (UAE)' },
  { value: 'fr_FR', label: 'French (France)' },
  { value: 'de_DE', label: 'German (Germany)' },
  { value: 'es_ES', label: 'Spanish (Spain)' },
]

const monthOptions = [
  { value: 1, label: 'January' },
  { value: 2, label: 'February' },
  { value: 3, label: 'March' },
  { value: 4, label: 'April' },
  { value: 5, label: 'May' },
  { value: 6, label: 'June' },
  { value: 7, label: 'July' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]

const getRoleBadgeVariant = (role: string) => {
  const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    owner: 'default',
    manager: 'default',
    accountant: 'secondary',
    viewer: 'outline',
    member: 'outline',
  }
  return variants[role.toLowerCase()] || 'outline'
}

const handleCreateUser = () => {
  createUserForm.post(`/${props.company.slug}/users`, {
    onSuccess: () => {
      createUserForm.reset()
      createUserForm.role = 'operations'
      createUserDialogOpen.value = false
      toast.success('User created successfully')
    },
    onError: () => {
      toast.error('Failed to create user')
    },
  })
}

const openRoleDialog = (user: User) => {
  selectedUser.value = user
  roleForm.userId = user.id
  roleForm.role = user.role
  roleDialogOpen.value = true
}

const handleRoleUpdate = () => {
  roleForm.put(`/${props.company.slug}/users/${roleForm.userId}/role`, {
    onSuccess: () => {
      roleDialogOpen.value = false
      selectedUser.value = null
      toast.success('Role updated successfully')
    },
    onError: () => {
      toast.error('Failed to update role')
    },
  })
}

const openRemoveDialog = (user: User) => {
  selectedUser.value = user
  removeDialogOpen.value = true
}

const handleRemoveUser = () => {
  if (!selectedUser.value) return

  removeForm.delete(`/${props.company.slug}/users/${selectedUser.value.id}`, {
    onSuccess: () => {
      removeDialogOpen.value = false
      selectedUser.value = null
      toast.success('User removed successfully')
    },
    onError: () => {
      toast.error('Failed to remove user')
    },
  })
}

const openProduct = (product: FuelProductDashboardItem) => {
  router.visit(`/${props.company.slug}/items/${product.id}`)
}

const editProduct = (product: FuelProductDashboardItem) => {
  router.visit(`/${props.company.slug}/items/${product.id}/edit`)
}

const openProductStock = (product: FuelProductDashboardItem) => {
  router.visit(`/${props.company.slug}/stock/items/${product.id}`)
}

const toggleProductStatus = (product: FuelProductDashboardItem) => {
  router.patch(`/${props.company.slug}/items/${product.id}/status`, {
    is_active: !product.is_active,
  }, {
    preserveScroll: true,
    only: ['fuelDashboard'],
  })
}

const openProductDeleteDialog = (product: FuelProductDashboardItem) => {
  selectedProduct.value = product
  productDeleteDialogOpen.value = true
}

const deleteProduct = () => {
  if (!selectedProduct.value) return

  router.delete(`/${props.company.slug}/items/${selectedProduct.value.id}`, {
    data: { return_to: 'back' },
    preserveScroll: true,
    only: ['fuelDashboard'],
    onSuccess: () => {
      productDeleteDialogOpen.value = false
      selectedProduct.value = null
    },
  })
}

const tableColumns = [
  { key: 'name', label: 'User', sortable: true, kind: 'text' as const },
  { key: 'role', label: 'Role', sortable: true, kind: 'text' as const },
  { key: 'is_active', label: 'Status', sortable: true, kind: 'status' as const },
  { key: 'joined_at', label: 'Joined', sortable: true, kind: 'date' as const },
  { key: 'actions', label: '', class: 'text-right' },
]

const moneyLocale = (currencyCode?: string) => {
  const code = currencyCode || props.company.base_currency || 'USD'
  if (code === 'PKR') return 'en-PK'
  return 'en-US'
}

/* The symbol lookup lives in lib/utils; this page only supplies the locale,
   because Rs and ₨ differ by locale for the same PKR code. */
const currencySymbol = (currencyCode: string) => sharedCurrencySymbol(currencyCode, moneyLocale(currencyCode))

const formatQuantity = (value?: number | null, unit?: string | null) => {
  const quantity = Number(value ?? 0).toLocaleString(moneyLocale(props.company.base_currency), {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })
  return `${quantity} ${unit || 'units'}`
}

const formatPercent = (value?: number | null) => {
  if (value === null || value === undefined) return '—'
  return `${Number(value).toFixed(1)}%`
}

const productCategoryLabel = (category?: string | null) => {
  if (!category) return 'Product'
  return category
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

const movementTypeLabel = (value?: string | null, reason?: string | null) => {
  if (reason === 'Daily close tank reconciliation') return 'Reconciled by Daily Close'
  if (!value) return 'Stock entry'
  return value
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

const tankReadingLabel = (product: FuelProductDashboardItem) => {
  if (product.last_tank_reading_type === 'opening') return 'Opening stock'
  if (product.last_tank_reading_type === 'closing') return 'Closing tank reading'
  return 'Last dip reading'
}

const hasPhysicalTankReading = (product: FuelProductDashboardItem) => {
  return product.last_dip_quantity !== null && product.last_tank_reading_type !== 'opening'
}

const varianceClass = (value?: number | null) => {
  if (value === null || value === undefined) return 'text-text-secondary'
  if (Math.abs(value) < 0.001) return 'text-status-success'
  return value < 0 ? 'text-status-critical' : 'text-status-attention'
}

const variancePrefix = (value?: number | null) => {
  if (value === null || value === undefined || Math.abs(value) < 0.001) return ''
  return value > 0 ? '+' : ''
}

const dateOnlyTime = (value?: string | null) => {
  if (!value) return null
  const parsed = new Date(value.slice(0, 10))
  return Number.isNaN(parsed.getTime()) ? null : parsed.getTime()
}

const dateTimeValue = (value?: string | null) => {
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed.getTime()
}

const stockPrecedence = (product: FuelProductDashboardItem) => {
  const stockDate = dateOnlyTime(product.last_stock_movement_date ?? product.last_stock_movement_at)
  const readingDate = dateOnlyTime(product.last_dip_at)
  const stockAt = dateTimeValue(product.last_stock_movement_at)
  const readingRecordedAt = dateTimeValue(product.last_dip_recorded_at ?? product.last_dip_at)

  if (product.last_stock_movement_reason === 'Daily close tank reconciliation') return 'Reconciled by Daily Close'
  if (!readingDate || product.last_tank_reading_type === 'opening') return null
  if (!stockDate) return 'No stock entry after this reading'
  if (stockAt && readingRecordedAt && stockAt > readingRecordedAt) return 'Stock changed after this reading'
  if (readingDate > stockDate) return 'Physical reading newer than stock'
  if (readingDate === stockDate) return 'Same business day as latest stock entry'
  return 'Older physical reading'
}

const shouldShowCurrentVariance = (product: FuelProductDashboardItem) => {
  if (product.stock_variance === null || product.stock_variance === undefined) return false
  if (!hasPhysicalTankReading(product)) return false
  if (product.last_stock_movement_reason === 'Daily close tank reconciliation') return false

  const stockDate = dateOnlyTime(product.last_stock_movement_date ?? product.last_stock_movement_at)
  const readingDate = dateOnlyTime(product.last_dip_at)
  const stockAt = dateTimeValue(product.last_stock_movement_at)
  const readingRecordedAt = dateTimeValue(product.last_dip_recorded_at ?? product.last_dip_at)

  if (!readingDate || !stockDate) return true
  if (stockAt && readingRecordedAt && stockAt > readingRecordedAt) return false

  return readingDate >= stockDate
}
/* ── The dashboard, read as a reckoning ─────────────────────────────────────
   Eight cards each announcing a number is a list of facts. The page below is
   an argument: this is what you hold, this is what is already spoken for,
   therefore this is what is free. Everything else on the page answers to it. */

const baseCurrency = computed(() => props.company.base_currency)
const baseLocale = computed(() => moneyLocale(props.company.base_currency))

/** Money already promised to someone else in the next few days. */
const committedSoon = computed(() => props.dashboard.needs_attention.bills_due_soon_amount ?? 0)

const standLines = computed<DerivationLine[]>(() => {
  const lines: DerivationLine[] = [
    { label: 'In your accounts', amount: props.dashboard.cash_position.total, sign: null },
  ]
  if (committedSoon.value > 0) {
    lines.push({ label: 'Bills falling due', amount: committedSoon.value, sign: '−' })
  }
  return lines
})

const freeToCommit = computed(() => props.dashboard.cash_position.total - committedSoon.value)

const accountCount = computed(() => props.dashboard.cash_position.accounts.length)

/* Profit and loss, stated the same way — earned, less spent, therefore kept. */
const periodLines = computed<DerivationLine[]>(() => [
  { label: 'Money earned', amount: props.dashboard.profit_loss.income, sign: null },
  { label: 'Money spent', amount: props.dashboard.profit_loss.expenses, sign: '−' },
])

const periodResultLabel = computed(() =>
  props.dashboard.profit_loss.profit < 0 ? 'Loss for the period' : 'Kept',
)

interface AttentionItem {
  key: string
  label: string
  why: string
  chip: string
  tone: 'late' | 'attention' | 'info'
  href: string
}

/* Ordered by how badly it wants you: money already late, then money about to
   leave, then bookkeeping. Rows with a count of zero never appear — an item
   that says "0 overdue invoices" is asking to be read and then ignored. */
const attentionItems = computed<AttentionItem[]>(() => {
  const n = props.dashboard.needs_attention
  const slug = props.company.slug
  const items: AttentionItem[] = []

  if (n.overdue_invoices > 0) {
    items.push({
      key: 'overdue',
      label: n.overdue_invoices === 1 ? 'One invoice is overdue' : `${n.overdue_invoices} invoices are overdue`,
      why: 'Work you have already done that has not been paid for.',
      chip: 'Past due',
      tone: 'late',
      href: `/${slug}/invoices?status=overdue`,
    })
  }

  if (n.bills_due_soon > 0) {
    items.push({
      key: 'bills',
      label: n.bills_due_soon === 1 ? 'One bill is due this week' : `${n.bills_due_soon} bills are due this week`,
      why: 'Money that leaves the account whether or not you look.',
      chip: '7 days',
      tone: 'attention',
      href: `/${slug}/bills`,
    })
  }

  if (n.unreconciled_transactions > 0) {
    items.push({
      key: 'unreconciled',
      label: `${n.unreconciled_transactions} bank ${n.unreconciled_transactions === 1 ? 'line has' : 'lines have'} not been matched`,
      why: 'Until these are matched, the figures above are an estimate.',
      chip: 'To match',
      tone: 'info',
      href: `/${slug}/bank-reconciliation`,
    })
  }

  return items
})

/* The register. IN and OUT are separate columns because a signed single column
   makes the reader do the sorting; two columns let the eye do it. */
interface ActivityRow {
  key: string
  occurred_at: string
  label: string
  inAmount: number | null
  outAmount: number | null
  currency: string
}

const activityRows = computed<ActivityRow[]>(() =>
  props.financials.recent_activity.map((item, index) => ({
    key: `${item.type}-${index}`,
    occurred_at: item.occurred_at,
    label: item.label,
    inAmount: item.direction === 'in' ? (item.amount ?? null) : null,
    outAmount: item.direction === 'out' ? (item.amount ?? null) : null,
    currency: item.currency || props.company.base_currency,
  })),
)

const activityColumns: RegisterColumn<ActivityRow>[] = [
  { key: 'occurred_at', label: 'Date', kind: 'date' },
  { key: 'label', label: 'Entry', kind: 'text' },
  { key: 'inAmount', label: 'In', kind: 'in' },
  { key: 'outAmount', label: 'Out', kind: 'out' },
]

/* Where the page lets you start something rather than only read. */
const startActions = computed(() => {
  const slug = props.company.slug
  return [
    { key: 'invoice', label: 'Write an invoice', href: `/${slug}/invoices/create` },
    { key: 'payment', label: 'Record a payment', href: `/${slug}/payments/create` },
    { key: 'bill', label: 'Enter a bill', href: `/${slug}/bills/create` },
    { key: 'customer', label: 'Add a customer', href: `/${slug}/customers/create` },
  ]
})
</script>

<template>
  <Head :title="pageTitle" />
  <Tabs v-model="activeTab" class="w-full">
    <PageShell
      :title="pageTitle"
      :icon="pageIcon"
      :breadcrumbs="pageBreadcrumbs"
      :badge="isFuelStationCompany ? undefined : { text: company.is_active ? 'Active' : 'Inactive', variant: company.is_active ? 'default' : 'secondary' }"
      compact
    >
      <template v-if="isFuelStationCompany" #before-header>
        <div class="flex flex-col gap-2 rounded-lg border border-rule-default bg-surface-raised p-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-xs font-medium uppercase text-text-secondary">Working date</p>
            <p class="text-sm text-text-secondary">Products, stock, rates, and sales are shown for this business date.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-2 rounded-md border border-rule-default bg-surface-raised px-2 py-1">
              <Calendar class="h-4 w-4 text-text-secondary" />
              <Input
                v-model="productAsOfDate"
                type="date"
                class="h-8 w-[9.5rem] border-0 px-0 text-sm shadow-none focus-visible:ring-0"
                @change="applyProductDate"
              />
            </div>
            <Button size="sm" variant="ghost" @click="resetProductDate">
              Today
            </Button>
          </div>
        </div>
      </template>

      <template v-if="!isFuelStationCompany" #description>
        <span class="font-mono text-text-tertiary">{{ company.slug }}</span>
        <span class="mx-2 text-text-quaternary">•</span>
        <span class="text-text-secondary">{{ currencySymbol(company.base_currency) }}</span>
      </template>

      <template v-if="!isFuelStationCompany" #actions>
        <TabsList class="bg-surface-sunken">
        <TabsTrigger value="overview" class="gap-2">
          <BarChart3 class="h-4 w-4" />
          Dashboard
        </TabsTrigger>
        <TabsTrigger v-if="canManage" value="settings" class="gap-2">
          <Settings class="h-4 w-4" />
          Settings
        </TabsTrigger>
        <TabsTrigger v-if="canManage" value="users" class="gap-2">
          <Users class="h-4 w-4" />
          Users
        </TabsTrigger>
      </TabsList>
      </template>

      <!-- Overview Tab (Dashboard) -->
      <TabsContent value="overview" class="space-y-6">

        <template v-if="isFuelStationCompany">
          <Card class="border-rule-subtle bg-surface-raised">
            <CardContent class="space-y-5 pt-6">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-text-secondary">Stock, rates, and recent sales for fuels, lubricants, and shop products.</p>
                <div class="flex flex-wrap items-center gap-2">
                  <Button size="sm" variant="outline" @click="router.visit(`/${company.slug}/fuel/daily-close`)">
                    <FileText class="mr-2 h-4 w-4" />
                    Daily Close
                  </Button>
                  <Button size="sm" @click="productsDialogOpen = true">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Fuel Product
                  </Button>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
                <div class="rounded-lg border border-rule-subtle bg-surface-sunken p-3">
                  <div class="text-xs text-text-secondary">Products</div>
                  <div class="mt-1 text-2xl font-semibold text-foreground">{{ fuelProductSummary.total_products }}</div>
                  <div class="text-xs text-text-secondary">{{ fuelProductSummary.active_products }} active</div>
                </div>
                <div class="rounded-lg border border-rule-subtle bg-surface-sunken p-3">
                  <div class="text-xs text-text-secondary">Low stock</div>
                  <div class="mt-1 text-2xl font-semibold" :class="fuelProductSummary.low_stock_count > 0 ? 'text-status-attention' : 'text-foreground'">
                    {{ fuelProductSummary.low_stock_count }}
                  </div>
                  <div class="text-xs text-text-secondary">Needs refill</div>
                </div>
                <div class="rounded-lg border border-rule-subtle bg-surface-sunken p-3">
                  <div class="text-xs text-text-secondary">Yesterday</div>
                  <div class="mt-1 text-lg font-semibold text-foreground">
                    <MoneyText :amount="fuelProductSummary.yesterday_sales" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                  </div>
                  <div class="text-xs text-text-secondary">{{ formatQuantity(fuelProductSummary.yesterday_liters, 'L') }}</div>
                </div>
                <div class="rounded-lg border border-rule-subtle bg-surface-sunken p-3">
                  <div class="text-xs text-text-secondary">Last 7 days</div>
                  <div class="mt-1 text-lg font-semibold text-foreground">
                    <MoneyText :amount="fuelProductSummary.last_week_sales" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                  </div>
                  <div class="text-xs text-text-secondary">{{ formatQuantity(fuelProductSummary.last_week_liters, 'L') }}</div>
                </div>
                <div class="rounded-lg border border-rule-subtle bg-surface-sunken p-3">
                  <div class="text-xs text-text-secondary">Last 30 days</div>
                  <div class="mt-1 text-lg font-semibold text-foreground">
                    <MoneyText :amount="fuelProductSummary.last_month_sales" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                  </div>
                  <div class="text-xs text-text-secondary">{{ formatQuantity(fuelProductSummary.last_month_liters, 'L') }}</div>
                </div>
                <div class="rounded-lg border border-rule-subtle bg-surface-sunken p-3">
                  <div class="text-xs text-text-secondary">Stock value</div>
                  <div class="mt-1 text-lg font-semibold text-foreground">
                    <MoneyText :amount="fuelProductSummary.inventory_value" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                  </div>
                  <div class="text-xs text-text-secondary">At product cost</div>
                </div>
              </div>
            </CardContent>
          </Card>

          <div class="grid gap-6 lg:grid-cols-3">
            <Card class="border-rule-subtle bg-surface-raised lg:col-span-2">
              <CardHeader>
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <CardTitle class="text-base font-semibold text-foreground">Product Overview</CardTitle>
                    <CardDescription>Current stock, sale rate, margin, and recent movement.</CardDescription>
                  </div>
                  <Badge variant="outline">{{ fuelProductRows.length }} products · {{ fuelProductSummary.active_products }} active</Badge>
                </div>
              </CardHeader>
              <CardContent>
                <div v-if="fuelProductRows.length === 0" class="rounded-lg border border-dashed border-rule-default p-6 text-center">
                  <Package class="mx-auto h-8 w-8 text-text-tertiary" />
                  <p class="mt-2 text-sm font-medium text-foreground">No products yet</p>
                  <p class="mt-1 text-sm text-text-secondary">Add the fuels and products this station sells.</p>
                  <Button class="mt-4" size="sm" @click="productsDialogOpen = true">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Fuel Product
                  </Button>
                </div>
                <div v-else class="space-y-3">
                  <div
                    v-for="product in fuelProductRows"
                    :key="product.id"
                    class="cursor-pointer rounded-lg border border-rule-subtle bg-surface-raised p-3 transition-colors hover:border-rule-default hover:bg-surface-sunken"
                    role="button"
                    tabindex="0"
                    @click="openProduct(product)"
                    @keydown.enter="openProduct(product)"
                  >
                    <div class="grid gap-3 lg:grid-cols-[minmax(180px,1.2fr)_minmax(160px,1fr)_repeat(3,minmax(120px,0.8fr))_auto] lg:items-center">
                      <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                          <p class="truncate text-sm font-semibold text-foreground">{{ product.name }}</p>
                          <Badge :variant="!product.is_active ? 'outline' : product.is_low_stock ? 'destructive' : 'secondary'" class="text-xs">
                            {{ !product.is_active ? 'Inactive' : product.is_low_stock ? 'Low stock' : productCategoryLabel(product.fuel_category) }}
                          </Badge>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-text-secondary">
                          <span v-if="product.sku">{{ product.sku }}</span>
                          <span>Rate <MoneyText :amount="product.sale_rate" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" /></span>
                          <span>Margin <MoneyText :amount="product.margin" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" /></span>
                        </div>
                      </div>

                      <div class="rounded-md bg-surface-sunken p-2">
                        <div class="flex items-center justify-between gap-2 text-xs text-text-secondary">
                          <span>System stock</span>
                          <span v-if="product.capacity">{{ formatPercent(product.fill_percentage) }}</span>
                        </div>
                        <div class="mt-1 text-sm font-semibold text-foreground">
                          {{ formatQuantity(product.current_stock, product.unit) }}
                        </div>
                        <div class="mt-1 space-y-1 text-xs text-text-secondary">
                          <div v-if="product.last_stock_movement_at">
                            {{ movementTypeLabel(product.last_stock_movement_type, product.last_stock_movement_reason) }}:
                            <DateTimeText :value="product.last_stock_movement_at" mode="datetime" :locale="moneyLocale(company.base_currency)" />
                          </div>
                          <div v-else>No stock entry yet</div>
                          <div v-if="product.last_dip_quantity !== null">
                            {{ tankReadingLabel(product) }}: {{ formatQuantity(product.last_dip_quantity, product.unit) }}
                            <span v-if="product.last_dip_at">
                              · <DateTimeText :value="product.last_dip_at" mode="datetime" :locale="moneyLocale(company.base_currency)" />
                            </span>
                          </div>
                          <div v-if="product.last_dip_recorded_at">
                            Recorded:
                            <DateTimeText :value="product.last_dip_recorded_at" mode="datetime" :locale="moneyLocale(company.base_currency)" />
                          </div>
                          <div v-if="stockPrecedence(product)" class="font-medium" :class="shouldShowCurrentVariance(product) ? 'text-text-secondary' : 'text-status-attention'">
                            {{ stockPrecedence(product) }}
                          </div>
                          <div v-if="product.stock_variance !== null && shouldShowCurrentVariance(product)" :class="varianceClass(product.stock_variance)">
                            {{ Math.abs(product.stock_variance) < 0.001 ? 'No open variance' : 'Open variance' }}:
                            {{ variancePrefix(product.stock_variance) }}{{ formatQuantity(product.stock_variance, product.unit) }}
                          </div>
                          <div v-else-if="product.stock_variance !== null && hasPhysicalTankReading(product)" class="text-text-secondary">
                            Last checked variance: {{ variancePrefix(product.stock_variance) }}{{ formatQuantity(product.stock_variance, product.unit) }}
                          </div>
                          <div v-if="product.low_stock_level > 0">
                            Alert at {{ formatQuantity(product.low_stock_level, product.unit) }}
                          </div>
                        </div>
                      </div>

                      <div>
                        <div class="text-xs text-text-secondary">Yesterday</div>
                        <div class="text-sm font-semibold text-foreground">
                          <MoneyText :amount="product.sales.yesterday.amount" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                        </div>
                        <div class="text-xs text-text-secondary">{{ formatQuantity(product.sales.yesterday.quantity, product.unit) }}</div>
                      </div>

                      <div>
                        <div class="text-xs text-text-secondary">Last 7 days</div>
                        <div class="text-sm font-semibold text-foreground">
                          <MoneyText :amount="product.sales.last_week.amount" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                        </div>
                        <div class="text-xs text-text-secondary">{{ formatQuantity(product.sales.last_week.quantity, product.unit) }}</div>
                      </div>

                      <div>
                        <div class="text-xs text-text-secondary">Last 30 days</div>
                        <div class="text-sm font-semibold text-foreground">
                          <MoneyText :amount="product.sales.last_month.amount" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                        </div>
                        <div class="text-xs text-text-secondary">
                          {{ formatQuantity(product.sales.last_month.quantity, product.unit) }}
                          <span v-if="product.last_sold_at"> · {{ formatDate(product.last_sold_at) }}</span>
                        </div>
                      </div>

                      <div class="flex justify-end" @click.stop>
                        <DropdownMenu>
                          <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="sm" aria-label="Product actions">
                              <MoreVertical class="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="openProduct(product)">
                              <Eye class="mr-2 h-4 w-4" />
                              Open
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="editProduct(product)">
                              <Pencil class="mr-2 h-4 w-4" />
                              Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="openProductStock(product)">
                              <Warehouse class="mr-2 h-4 w-4" />
                              Stock
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="toggleProductStatus(product)">
                              <component :is="product.is_active ? PowerOff : Power" class="mr-2 h-4 w-4" />
                              {{ product.is_active ? 'Deactivate' : 'Activate' }}
                            </DropdownMenuItem>
                            <DropdownMenuItem class="text-status-critical focus:text-status-critical" @click="openProductDeleteDialog(product)">
                              <Trash2 class="mr-2 h-4 w-4" />
                              Delete
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <div class="space-y-6">
              <Card class="border-rule-subtle bg-surface-raised">
                <CardHeader>
                  <div class="flex items-center gap-2">
                    <Warehouse class="h-5 w-5 text-text-secondary" />
                    <CardTitle class="text-base font-semibold text-foreground">Stock Management</CardTitle>
                  </div>
                  <CardDescription>Add stock, manage tanks, and review stock movement.</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-2">
                  <Button variant="outline" class="justify-start" @click="router.visit(`/${company.slug}/stock/adjustment`)">
                    <Plus class="mr-2 h-4 w-4" />
                    Add or adjust stock
                  </Button>
                  <Button variant="outline" class="justify-start" @click="router.visit(`/${company.slug}/fuel/receipts`)">
                    <FileText class="mr-2 h-4 w-4" />
                    Fuel deliveries
                  </Button>
                  <Button variant="outline" class="justify-start" @click="router.visit(`/${company.slug}/warehouses`)">
                    <Warehouse class="mr-2 h-4 w-4" />
                    Tanks and warehouses
                  </Button>
                  <Button variant="ghost" class="justify-start" @click="router.visit(`/${company.slug}/stock`)">
                    View stock levels
                  </Button>
                </CardContent>
              </Card>

              <Card class="border-rule-subtle bg-surface-raised">
                <CardHeader>
                  <div class="flex items-center gap-2">
                    <AlertTriangle class="h-5 w-5" :class="lowStockProducts.length > 0 ? 'text-status-attention' : 'text-text-tertiary'" />
                    <CardTitle class="text-base font-semibold text-foreground">Low Stock</CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <div v-if="lowStockProducts.length === 0" class="text-sm text-text-secondary">No product is below its alert level.</div>
                  <div v-else class="space-y-3">
                    <div v-for="product in lowStockProducts" :key="product.id" class="rounded-md border border-status-attention/30 bg-status-attention/10 p-3">
                      <div class="flex items-start justify-between gap-3">
                        <div>
                          <p class="text-sm font-semibold text-foreground">{{ product.name }}</p>
                          <p class="text-xs text-text-secondary">{{ productCategoryLabel(product.fuel_category) }}</p>
                        </div>
                        <Badge variant="destructive">{{ formatPercent(product.fill_percentage) }}</Badge>
                      </div>
                      <div class="mt-2 text-sm text-text-secondary">
                        {{ formatQuantity(product.current_stock, product.unit) }} left
                      </div>
                      <div class="text-xs text-text-secondary">
                        Alert level {{ formatQuantity(product.low_stock_level, product.unit) }}
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <Card class="border-rule-subtle bg-surface-raised">
                <CardHeader>
                  <div class="flex items-center gap-2">
                    <TrendingUp class="h-5 w-5 text-status-success" />
                    <CardTitle class="text-base font-semibold text-foreground">Top Products</CardTitle>
                  </div>
                  <CardDescription>Ranked by sales in the last 30 days.</CardDescription>
                </CardHeader>
                <CardContent>
                  <div v-if="topFuelProducts.length === 0" class="text-sm text-text-secondary">No posted sales in the last 30 days.</div>
                  <div v-else class="space-y-3">
                    <div v-for="(product, index) in topFuelProducts" :key="product.id" class="flex items-center justify-between gap-3">
                      <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-sunken text-xs font-semibold text-text-secondary">
                          {{ index + 1 }}
                        </div>
                        <div class="min-w-0">
                          <p class="truncate text-sm font-medium text-foreground">{{ product.name }}</p>
                          <p class="text-xs text-text-secondary">{{ formatQuantity(product.sales.last_month.quantity, product.unit) }}</p>
                        </div>
                      </div>
                      <div class="text-sm font-semibold text-foreground">
                        <MoneyText :amount="product.sales.last_month.amount" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </template>
        <template v-else>
        <div class="ledger-home">
          <!-- Where you stand ------------------------------------------------
               The one conclusion the page exists to state. Everything under it
               is either the working that produced it or the work that changes
               it. -->
          <section class="reckon">
            <h2 class="reckon__title">Where you stand</h2>
            <Derivation
              :lines="standLines"
              total-label="Free to commit"
              :total-amount="freeToCommit"
              :currency="baseCurrency"
              :locale="baseLocale"
            >
              <template #footnote>
                Across {{ accountCount }} {{ accountCount === 1 ? 'account' : 'accounts' }}.
                Money customers still owe you is not counted here — it is not yours until it arrives.
              </template>
            </Derivation>
          </section>

          <!-- What needs you -------------------------------------------------
               A queue, not a scoreboard. Each row is one click from the thing
               that clears it. -->
          <section class="needs">
            <h2 class="needs__title">What needs you</h2>

            <ul v-if="attentionItems.length" class="needs__list">
              <li v-for="item in attentionItems" :key="item.key">
                <button type="button" class="need" @click="router.visit(item.href)">
                  <span class="need__body">
                    <span class="need__label">{{ item.label }}</span>
                    <span class="need__why">{{ item.why }}</span>
                  </span>
                  <MetaChip :tone="item.tone">{{ item.chip }}</MetaChip>
                </button>
              </li>
            </ul>

            <p v-else class="needs__clear">Nothing is waiting on you.</p>
          </section>

          <!-- What's been happening -------------------------------------------
               In and out kept apart so the direction is read, not computed. -->
          <section class="happening">
            <LedgerRegister
              title="What's been happening"
              :data="activityRows"
              :columns="activityColumns"
              key-field="key"
              :clickable="false"
              sprockets
            >
              <template #cell-label="{ row }">
                <span class="entry">{{ row.label }}</span>
              </template>
              <template #cell-inAmount="{ row }">
                <MoneyText
                  v-if="row.inAmount !== null"
                  :amount="row.inAmount"
                  :currency="row.currency"
                  :locale="moneyLocale(row.currency)"
                  :show-currency="false"
                />
                <span v-else class="void" aria-hidden="true">—</span>
              </template>
              <template #cell-outAmount="{ row }">
                <MoneyText
                  v-if="row.outAmount !== null"
                  :amount="row.outAmount"
                  :currency="row.currency"
                  :locale="moneyLocale(row.currency)"
                  :show-currency="false"
                />
                <span v-else class="void" aria-hidden="true">—</span>
              </template>
              <template #empty>Nothing has been recorded yet.</template>
            </LedgerRegister>
          </section>

          <!-- The period ------------------------------------------------------ -->
          <section class="reckon reckon--period">
            <h2 class="reckon__title">{{ dashboard.profit_loss.period }}</h2>
            <Derivation
              :lines="periodLines"
              :total-label="periodResultLabel"
              :total-amount="dashboard.profit_loss.profit"
              :currency="baseCurrency"
              :locale="baseLocale"
            />
          </section>

          <!-- Start something -------------------------------------------------- -->
          <section class="start">
            <h2 class="start__title">Start something</h2>
            <div class="start__strip">
              <button
                v-for="action in startActions"
                :key="action.key"
                type="button"
                class="start__action"
                @click="router.visit(action.href)"
              >
                {{ action.label }}
              </button>
            </div>
          </section>
        </div>
        </template>
      </TabsContent>

      <!-- Settings Tab -->
      <TabsContent v-if="canManage && !isFuelStationCompany" value="settings" class="space-y-6">
        <!-- Editable Settings -->
        <Card class="border-rule-subtle bg-surface-raised">
          <CardHeader>
            <CardTitle class="text-foreground">Company Settings</CardTitle>
            <CardDescription class="text-text-secondary">
              {{ canManage ? 'Click on the pencil icon to edit a setting' : 'Contact an owner or manager to make changes' }}
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-6 md:grid-cols-2">
              <!-- Company Name (Editable) -->
              <InlineEditable
                v-model="nameField.value.value"
                label="Company Name"
                :editing="nameField.isEditing.value"
                :saving="nameField.isSaving.value"
                :can-edit="canManage"
                type="text"
                @start-edit="nameField.startEditing()"
                @save="nameField.save()"
                @cancel="nameField.cancelEditing()"
              />

              <!-- Slug (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-text-secondary">Slug</Label>
                <div class="font-mono text-base text-foreground">{{ company.slug }}</div>
                <p class="text-xs text-text-tertiary">Cannot be changed</p>
              </div>

              <!-- Base Currency (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-text-secondary">Base Currency</Label>
                <div class="text-base text-foreground">
                  <span class="font-mono">{{ currencySymbol(company.base_currency) }}</span>
                  <span class="ml-2 text-sm text-text-secondary">({{ company.base_currency }})</span>
                </div>
                <p class="text-xs text-text-tertiary">Cannot be changed after creation</p>
              </div>

              <!-- Country (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-text-secondary">Country</Label>
                <div class="text-base text-foreground">{{ company.country || '—' }}</div>
                <p class="text-xs text-text-tertiary">Cannot be changed</p>
              </div>

              <!-- Industry (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-text-secondary">Industry</Label>
                <div class="text-base text-foreground capitalize">
                  {{ company.industry_name || company.industry || company.industry_code || '—' }}
                </div>
              </div>

              <!-- Created Date (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-text-secondary">Created</Label>
                <div class="flex items-center gap-1.5 text-base text-foreground">
                  <Calendar class="h-3.5 w-3.5 text-text-tertiary" />
                  {{ formatDate(company.created_at) }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Regional Settings -->
        <Card class="border-rule-subtle bg-surface-raised">
          <CardHeader>
            <CardTitle class="text-foreground flex items-center gap-2">
              <Globe class="h-4 w-4" />
              Regional Settings
            </CardTitle>
            <CardDescription class="text-text-secondary">
              Language, locale, and fiscal year preferences
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-6 md:grid-cols-2">
              <!-- Language (Editable) -->
              <InlineEditable
                v-model="languageField.value.value"
                label="Language"
                :editing="languageField.isEditing.value"
                :saving="languageField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="languageOptions"
                :icon="Languages"
                @start-edit="languageField.startEditing()"
                @save="languageField.save()"
                @cancel="languageField.cancelEditing()"
              />

              <!-- Locale (Editable) -->
              <InlineEditable
                v-model="localeField.value.value"
                label="Locale"
                :editing="localeField.isEditing.value"
                :saving="localeField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="localeOptions"
                @start-edit="localeField.startEditing()"
                @save="localeField.save()"
                @cancel="localeField.cancelEditing()"
              />

              <!-- Fiscal Year Start Month (Editable) -->
              <InlineEditable
                v-model="fiscalYearField.value.value"
                label="Fiscal Year Start"
                :editing="fiscalYearField.isEditing.value"
                :saving="fiscalYearField.isSaving.value"
                :can-edit="canManage"
                type="select"
                :options="monthOptions"
                :icon="Calendar"
                helper-text="Month when your fiscal year begins"
                @start-edit="fiscalYearField.startEditing()"
                @save="fiscalYearField.save()"
                @cancel="fiscalYearField.cancelEditing()"
              />
            </div>
          </CardContent>
        </Card>

      </TabsContent>

      <!-- Users Tab -->
      <TabsContent v-if="canManage && !isFuelStationCompany" value="users" class="space-y-6">
        <LedgerRegister
          :data="users"
          :columns="tableColumns"
          title="Team Members"
          :description="`${users.length} ${users.length === 1 ? 'member' : 'members'} in this company`"
          key-field="id"
          hoverable
        >
          <template #header>
            <Button v-if="canManage" size="sm" @click="createUserDialogOpen = true">
              <UserPlus class="mr-2 h-4 w-4" />
              Add User
            </Button>
          </template>

          <template #cell-name="{ row }">
            <div class="flex flex-col">
              <span class="font-medium text-foreground">{{ row.name || 'Unknown' }}</span>
              <div class="flex items-center gap-1 text-text-secondary">
                <Mail class="h-3 w-3" />
                <span class="text-xs">{{ row.email }}</span>
              </div>
            </div>
          </template>

          <template #cell-role="{ row }">
            <Badge :variant="getRoleBadgeVariant(row.role)" class="capitalize">
              <Shield class="mr-1 h-3 w-3" />
              {{ row.role }}
            </Badge>
          </template>

          <template #cell-is_active="{ row }">
            <Badge :variant="row.is_active ? 'default' : 'secondary'">
              <component :is="row.is_active ? CheckCircle2 : XCircle" class="mr-1 h-3 w-3" />
              {{ row.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </template>

          <template #cell-joined_at="{ row }">
            <div v-if="row.joined_at" class="flex items-center gap-1 text-text-secondary">
              <Calendar class="h-3 w-3 text-text-tertiary" />
              <span>{{ formatDate(row.joined_at) }}</span>
            </div>
            <span v-else class="text-text-tertiary">—</span>
          </template>

          <template #cell-actions="{ row }">
            <div class="flex justify-end">
              <DropdownMenu v-if="canManage">
                <DropdownMenuTrigger as-child>
                  <Button variant="ghost" size="sm">
                    <MoreVertical class="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem @click="openRoleDialog(row)">
                    <UserCog class="mr-2 h-4 w-4" />
                    Change Role
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="openRemoveDialog(row)" class="text-status-critical focus:text-status-critical">
                    <Trash2 class="mr-2 h-4 w-4" />
                    Remove User
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </template>
        </LedgerRegister>
      </TabsContent>

    <!-- Product Setup Dialog -->
    <Dialog v-model:open="productsDialogOpen">
      <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle class="text-foreground">Add Product</DialogTitle>
          <DialogDescription class="text-text-secondary">
            Add the product, rate, opening stock, and storage in one place.
          </DialogDescription>
        </DialogHeader>

        <div v-for="(row, index) in productsForm.products.slice(0, 1)" :key="index" class="space-y-5 py-2">
          <section class="space-y-3">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-sm font-semibold text-foreground">Product</h3>
                <p class="text-xs text-text-secondary">What the station sells.</p>
              </div>
              <Badge variant="secondary">{{ row.type === 'fuel' ? 'Fuel' : row.type === 'lubricant' ? 'Lubricant' : 'Other' }}</Badge>
            </div>

            <div class="grid gap-3 md:grid-cols-[150px_170px_1fr]">
              <div class="space-y-2">
                <Label>Type</Label>
                <Select v-model="row.type" @update:modelValue="handleTypeChange(row)">
                  <SelectTrigger>
                    <SelectValue placeholder="Select type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="fuel">Fuel</SelectItem>
                    <SelectItem value="lubricant">Lubricant</SelectItem>
                    <SelectItem value="other">Other</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div v-if="row.type === 'fuel'" class="space-y-2">
                <Label>Fuel Type</Label>
                <Select v-model="row.fuel_category" @update:modelValue="handleFuelCategoryChange(row)">
                  <SelectTrigger>
                    <SelectValue placeholder="Select fuel" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="petrol">Petrol</SelectItem>
                    <SelectItem value="diesel">Diesel</SelectItem>
                    <SelectItem value="high_octane">High Octane</SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="productsForm.errors[`products.${index}.fuel_category`]" class="text-xs text-status-critical">
                  {{ productsForm.errors[`products.${index}.fuel_category`] }}
                </p>
              </div>

              <div v-if="row.type === 'lubricant'" class="space-y-2">
                <Label>Storage Type</Label>
                <Select v-model="row.lubricant_format" @update:modelValue="handleStorageTypeChange(row)">
                  <SelectTrigger>
                    <SelectValue placeholder="Select storage" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="open">Open / Bulk stock</SelectItem>
                    <SelectItem value="packaged">Packaged item</SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-text-secondary">
                  Bulk needs storage setup. Packaged is counted as bottles/units.
                </p>
                <p v-if="productsForm.errors[`products.${index}.lubricant_format`]" class="text-xs text-status-critical">
                  {{ productsForm.errors[`products.${index}.lubricant_format`] }}
                </p>
              </div>

              <div v-if="row.type === 'other'" class="space-y-2">
                <Label>Storage Type</Label>
                <Select v-model="row.packaging" @update:modelValue="handleStorageTypeChange(row)">
                  <SelectTrigger>
                    <SelectValue placeholder="Select storage" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="open">Open / Bulk stock</SelectItem>
                    <SelectItem value="packaged">Packaged item</SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-text-secondary">
                  Bulk needs storage setup. Packaged is counted as units.
                </p>
                <p v-if="productsForm.errors[`products.${index}.packaging`]" class="text-xs text-status-critical">
                  {{ productsForm.errors[`products.${index}.packaging`] }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Name</Label>
                <Input v-model="row.name" placeholder="Product name" />
                <p v-if="productsForm.errors[`products.${index}.name`]" class="text-xs text-status-critical">
                  {{ productsForm.errors[`products.${index}.name`] }}
                </p>
              </div>
            </div>
          </section>

          <section class="space-y-3 border-t border-rule-subtle pt-4">
            <div>
              <h3 class="text-sm font-semibold text-foreground">Rate and Opening Stock</h3>
              <p class="text-xs text-text-secondary">Starting numbers for the product.</p>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
              <div class="space-y-2">
                <Label>Effective Date</Label>
                <Input v-model="productsForm.effective_date" type="date" />
                <p v-if="productsForm.errors.effective_date" class="text-xs text-status-critical">
                  {{ productsForm.errors.effective_date }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Purchase Rate</Label>
                <Input v-model="row.purchase_rate" type="number" step="0.01" min="0" />
                <p v-if="productsForm.errors[`products.${index}.purchase_rate`]" class="text-xs text-status-critical">
                  {{ productsForm.errors[`products.${index}.purchase_rate`] }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Sale Rate</Label>
                <Input v-model="row.sale_rate" type="number" step="0.01" min="0" />
                <p v-if="productsForm.errors[`products.${index}.sale_rate`]" class="text-xs text-status-critical">
                  {{ productsForm.errors[`products.${index}.sale_rate`] }}
                </p>
              </div>

              <div v-if="row.track_inventory" class="space-y-2">
                <Label>Opening Stock</Label>
                <Input v-model="row.opening_quantity" type="number" step="0.001" min="0" placeholder="Optional" />
                <p v-if="productsForm.errors[`products.${index}.opening_quantity`]" class="text-xs text-status-critical">
                  {{ productsForm.errors[`products.${index}.opening_quantity`] }}
                </p>
              </div>
            </div>
          </section>

          <section v-if="row.track_inventory && isOpenPackaging(row)" class="space-y-3 border-t border-rule-subtle pt-4">
            <div>
              <h3 class="text-sm font-semibold text-foreground">Storage and Pump</h3>
              <p class="text-xs text-text-secondary">Where the product is stored and how it is served.</p>
            </div>

            <div class="grid gap-3 md:grid-cols-[1fr_auto]">
              <div class="space-y-2">
                <Label>Tank / Storage Source</Label>
                <Select v-model="row.tank_id" :disabled="!!row.new_tank" @update:modelValue="handleTankSelection(row)">
                  <SelectTrigger>
                    <SelectValue placeholder="Select tank" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="tank in fuelTanks" :key="tank.id" :value="tank.id">
                      {{ tank.name }} ({{ tank.code }})
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="productsForm.errors[`products.${index}.tank_id`]" class="text-xs text-status-critical">
                  {{ productsForm.errors[`products.${index}.tank_id`] }}
                </p>
                <div v-if="row.new_tank" class="flex items-center justify-between gap-3 rounded-md border border-status-success/30 bg-status-success/10 px-3 py-2">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-status-success">
                      {{ row.new_tank.name }} ({{ row.new_tank.code }})
                    </div>
                    <div class="text-xs text-status-success">
                      Will be created with this product · {{ row.new_tank.capacity }} liters
                    </div>
                  </div>
                  <Button type="button" variant="ghost" size="sm" @click="clearNewTank(row)">
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </div>
              <div class="flex items-end">
                <Button type="button" variant="outline" @click="openTankDialog(index)">
                  {{ row.new_tank ? 'Edit Tank' : 'Add Tank' }}
                </Button>
              </div>
            </div>

            <div v-if="row.type === 'fuel'" class="flex items-center justify-between gap-3 rounded-lg border border-rule-subtle bg-surface-sunken p-3">
              <div>
                <div class="text-sm font-medium text-foreground">Create pump points</div>
                <div class="text-xs text-text-secondary">Turn off only if pump/nozzles already exist.</div>
              </div>
              <Switch
                :checked="row.create_pump_points"
                @update:checked="(checked) => handlePumpSetupToggle(row, checked)"
              />
            </div>

            <div v-if="row.type === 'fuel' && row.track_inventory && row.create_pump_points" class="space-y-3">
              <div
                v-for="(pumpSetup, pumpIndex) in row.pump_setups"
                :key="pumpIndex"
                class="grid gap-3 rounded-lg border border-rule-subtle p-3 md:grid-cols-[1fr_160px_auto] md:items-end"
              >
                <div class="space-y-2">
                  <Label>{{ pumpIndex === 0 ? 'Pump Point' : `Pump Point ${pumpIndex + 1}` }}</Label>
                  <Input v-model="pumpSetup.name" :placeholder="`Point ${pumpIndex + 1}`" />
                  <p v-if="productsForm.errors[`products.${index}.pump_setups.${pumpIndex}.name`]" class="text-xs text-status-critical">
                    {{ productsForm.errors[`products.${index}.pump_setups.${pumpIndex}.name`] }}
                  </p>
                </div>
                <div class="space-y-2">
                  <Label>Nozzles</Label>
                  <Select v-model="pumpSetup.nozzle_count" @update:modelValue="syncNozzleRows(row, pumpIndex)">
                    <SelectTrigger>
                      <SelectValue placeholder="Nozzles" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem :value="1">1 nozzle</SelectItem>
                      <SelectItem :value="2">2 nozzles</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  :disabled="row.pump_setups.length === 1"
                  @click="removePumpPoint(row, pumpIndex)"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>

              <Button type="button" variant="outline" class="w-full border-dashed" @click="addPumpPoint(row)">
                <Plus class="mr-2 h-4 w-4" />
                Add Another Pump Point
              </Button>
            </div>

          </section>

          <Collapsible>
            <CollapsibleTrigger class="flex w-full items-center justify-between rounded-lg border border-rule-default px-3 py-2 text-sm text-text-secondary transition-colors hover:bg-surface-sunken hover:text-foreground">
              <span>Advanced setup</span>
              <ChevronDown class="h-4 w-4" />
            </CollapsibleTrigger>

            <CollapsibleContent class="mt-4 space-y-4">
              <div class="grid gap-4 md:grid-cols-3">
                <div class="space-y-2">
                  <Label>SKU</Label>
                  <Input v-model="row.sku" placeholder="Auto-generated if blank" />
                  <p v-if="productsForm.errors[`products.${index}.sku`]" class="text-xs text-status-critical">
                    {{ productsForm.errors[`products.${index}.sku`] }}
                  </p>
                </div>

                <div class="space-y-2">
                  <Label>Unit</Label>
                  <Input
                    v-model="row.unit_of_measure"
                    :disabled="row.type === 'fuel'"
                    placeholder="e.g., liters, bottle, unit"
                  />
                </div>

                <div v-if="row.type === 'other'" class="space-y-2">
                  <Label>Category</Label>
                  <Input v-model="row.category_name" placeholder="Free-form category" />
                </div>
              </div>

              <div v-if="row.type === 'fuel' && row.track_inventory && row.create_pump_points" class="space-y-3 rounded-lg border border-rule-subtle bg-surface-sunken p-3">
                <div>
                  <h3 class="text-sm font-semibold text-foreground">Nozzle Details</h3>
                  <p class="text-xs text-text-secondary">Leave IDs blank for automatic numbering.</p>
                </div>

                <div
                  v-for="(pumpSetup, pumpIndex) in row.pump_setups"
                  :key="pumpIndex"
                  class="space-y-3 rounded-md border border-rule-default bg-surface-raised p-3"
                >
                  <div class="text-xs font-medium text-text-secondary">{{ pumpSetup.name || `Point ${pumpIndex + 1}` }}</div>
                  <div
                    v-for="(nozzle, nozzleIndex) in pumpSetup.nozzles"
                    :key="nozzleIndex"
                    class="grid gap-3 md:grid-cols-4"
                  >
                    <div class="space-y-2">
                      <Label>Nozzle ID</Label>
                      <Input v-model="nozzle.code" placeholder="Auto" />
                      <p v-if="productsForm.errors[`products.${index}.pump_setups.${pumpIndex}.nozzles.${nozzleIndex}.code`]" class="text-xs text-status-critical">
                        {{ productsForm.errors[`products.${index}.pump_setups.${pumpIndex}.nozzles.${nozzleIndex}.code`] }}
                      </p>
                    </div>
                    <div class="space-y-2">
                      <Label>Nozzle Name</Label>
                      <Input v-model="nozzle.label" placeholder="Front" />
                    </div>
                    <div class="space-y-2">
                      <Label>Electronic Reading</Label>
                      <Input v-model="nozzle.opening_electronic" type="number" step="0.01" min="0" />
                    </div>
                    <div class="space-y-2">
                      <Label>Manual Reading</Label>
                      <Input v-model="nozzle.opening_manual" type="number" step="0.01" min="0" placeholder="Optional" />
                    </div>
                  </div>
                </div>
              </div>
            </CollapsibleContent>
          </Collapsible>
        </div>

        <DialogFooter class="mt-2">
          <Button type="button" variant="outline" @click="productsDialogOpen = false">
            Cancel
          </Button>
          <Button type="button" :disabled="productsForm.processing" @click="submitProducts">
            <Loader2 v-if="productsForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            Create Product
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Add Tank Dialog -->
    <Dialog v-model:open="tankDialogOpen">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle class="text-foreground">Add Tank</DialogTitle>
          <DialogDescription class="text-text-secondary">
            This tank will be created when you create the product.
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Tank Name</Label>
              <Input v-model="tankDraft.name" placeholder="e.g., Petrol Tank 1" />
              <p v-if="tankDraftErrors.name" class="text-xs text-status-critical">{{ tankDraftErrors.name }}</p>
            </div>
            <div class="space-y-2">
              <Label>Tank Code</Label>
              <Input v-model="tankDraft.code" placeholder="e.g., TANK-PET" />
              <p v-if="tankDraftErrors.code" class="text-xs text-status-critical">{{ tankDraftErrors.code }}</p>
            </div>
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Capacity (Liters)</Label>
              <Input v-model="tankDraft.capacity" type="number" step="0.01" min="1" />
              <p v-if="tankDraftErrors.capacity" class="text-xs text-status-critical">{{ tankDraftErrors.capacity }}</p>
            </div>
            <div class="space-y-2">
              <Label>Low Level Alert (optional)</Label>
              <Input v-model="tankDraft.low_level_alert" type="number" step="0.01" min="0" />
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" @click="tankDialogOpen = false">
            Cancel
          </Button>
          <Button type="button" @click="saveTankDraft">
            Use This Tank
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Create User Dialog -->
    <Dialog v-model:open="createUserDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-foreground">Add User</DialogTitle>
          <DialogDescription class="text-text-secondary">
            Create a login for {{ company.name }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="company-user-name" class="text-text-secondary">Full name</Label>
            <Input id="company-user-name" v-model="createUserForm.name" class="border-rule-default" />
            <p v-if="createUserForm.errors.name" class="text-xs text-status-critical">{{ createUserForm.errors.name }}</p>
          </div>
          <div class="space-y-2">
            <Label for="company-user-email" class="text-text-secondary">Email</Label>
            <Input
              id="company-user-email"
              v-model="createUserForm.email"
              type="email"
              placeholder="user@example.com"
              class="border-rule-default"
            />
            <p v-if="createUserForm.errors.email" class="text-xs text-status-critical">
              {{ createUserForm.errors.email }}
            </p>
          </div>
          <div class="space-y-2">
            <Label class="text-text-secondary">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between border-rule-default">
                  <span class="capitalize">{{ createUserForm.role }}</span>
                  <span class="ml-2">▼</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-full">
                <DropdownMenuItem
                  v-for="role in availableRoles"
                  :key="role"
                  @click="createUserForm.role = role"
                  class="capitalize"
                >
                  {{ role }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
          <div class="space-y-2">
            <Label for="company-user-password" class="text-text-secondary">Password</Label>
            <Input id="company-user-password" v-model="createUserForm.password" type="password" autocomplete="new-password" class="border-rule-default" />
            <p v-if="createUserForm.errors.password" class="text-xs text-status-critical">{{ createUserForm.errors.password }}</p>
          </div>
          <div class="space-y-2">
            <Label for="company-user-password-confirmation" class="text-text-secondary">Confirm password</Label>
            <Input id="company-user-password-confirmation" v-model="createUserForm.password_confirmation" type="password" autocomplete="new-password" class="border-rule-default" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="createUserDialogOpen = false" :disabled="createUserForm.processing">
            Cancel
          </Button>
          <Button @click="handleCreateUser" :disabled="createUserForm.processing">
            <span v-if="createUserForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            Create User
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Change Role Dialog -->
    <Dialog v-model:open="roleDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-foreground">Change User Role</DialogTitle>
          <DialogDescription class="text-text-secondary">
            Update the role for {{ selectedUser?.name || selectedUser?.email }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label class="text-text-secondary">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between border-rule-default">
                  <span class="capitalize">{{ roleForm.role }}</span>
                  <span class="ml-2">▼</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-full">
                <DropdownMenuItem
                  v-for="role in availableRoles"
                  :key="role"
                  @click="roleForm.role = role"
                  class="capitalize"
                >
                  {{ role }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="roleDialogOpen = false" :disabled="roleForm.processing">
            Cancel
          </Button>
          <Button @click="handleRoleUpdate" :disabled="roleForm.processing">
            <span v-if="roleForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            Update Role
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Remove User Confirmation -->
    <ConfirmDialog
      v-model:open="removeDialogOpen"
      variant="destructive"
      title="Remove User"
      :description="`Are you sure you want to remove ${selectedUser?.name || selectedUser?.email} from ${company.name}? This action cannot be undone.`"
      confirm-text="Remove User"
      :loading="removeForm.processing"
      @confirm="handleRemoveUser"
    />

    <ConfirmDialog
      v-model:open="productDeleteDialogOpen"
      variant="destructive"
      title="Delete Product"
      :description="`Delete ${selectedProduct?.name || 'this product'}? Products with stock on hand cannot be deleted.`"
      confirm-text="Delete Product"
      @confirm="deleteProduct"
    />
  </PageShell>
  </Tabs>
</template>

<style scoped>
/* The generic dashboard, set as one continuous sheet rather than a grid of
   cards. Cards break a page into equally-loud boxes; rules and space let one
   thing be the conclusion and the rest be support. */
.ledger-home {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.reckon__title,
.needs__title,
.start__title {
    font-family: var(--display-family);
    font-size: 19px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--text-primary);
    padding-bottom: 8px;
    border-bottom: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
}

/* The period reckoning is the same device at a quieter volume — it reports a
   month, not a standing. */
.reckon--period :deep(.total__what),
.reckon--period :deep(.money--conclusion) {
    font-size: 26px;
}

.needs__list {
    display: flex;
    flex-direction: column;
    margin-top: 4px;
}

.need {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    width: 100%;
    padding: 12px 8px 12px 0;
    text-align: left;
    background: none;
    border: 0;
    border-bottom: var(--rule-w-hair, 1px) solid var(--rule-default);
    cursor: pointer;
}

.needs__list li:last-child .need {
    border-bottom: 0;
}

/* Hover is an outline, not a fill — the same move the register makes, so a
   row behaves the same whichever part of the page it is in. */
.need:hover {
    outline: var(--rule-w-base, 1.5px) solid var(--rule-emphasis);
    outline-offset: calc(var(--rule-w-base, 1.5px) * -1);
}

.need__body {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.need__label {
    font-size: 15px;
    font-weight: 500;
    color: var(--text-primary);
}

.need__why {
    font-size: 13px;
    color: var(--text-secondary);
}

.needs__clear {
    margin-top: 16px;
    font-size: 15px;
    color: var(--text-secondary);
}

/* The register carries its own card padding for the pages that give it a
   border. Here it sits bare on the sheet, so its heading has to share the left
   edge with every other section heading or the page reads as two documents. */
.happening :deep(.reghead) {
    padding-left: 0;
    padding-right: 0;
    padding-top: 0;
}

.happening :deep(.reghead__title) {
    font-size: 19px;
}

.entry {
    color: var(--text-primary);
}

/* An empty column reads as "nothing moved that way", which is information.
   Blank would read as a rendering fault. */
.void {
    font-family: var(--mono-family);
    color: var(--text-tertiary);
}

.start__strip {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
    margin-top: 16px;
    border: var(--rule-w-hair, 1px) solid var(--rule-default);
}

.start__action {
    flex: 1 1 180px;
    padding: 14px 16px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    background: var(--surface-raised);
    border: 0;
    border-right: var(--rule-w-hair, 1px) solid var(--rule-default);
    cursor: pointer;
}

.start__action:last-child {
    border-right: 0;
}

.start__action:hover {
    background: var(--surface-band);
}

@media (max-width: 640px) {
    .ledger-home {
        gap: 32px;
    }

    .start__action {
        flex-basis: 100%;
        border-right: 0;
        border-bottom: var(--rule-w-hair, 1px) solid var(--rule-default);
    }
}
</style>
