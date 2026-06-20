<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import InlineEditable from '@/components/InlineEditable.vue'
import MoneyText from '@/components/MoneyText.vue'
import DateTimeText from '@/components/DateTimeText.vue'
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
  }>
}

interface PendingInvitation {
  id: string
  email: string
  role: string
  expires_at: string
  created_at: string
  inviter_name: string | null
  inviter_email: string | null
  token: string
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
  pendingInvitations: PendingInvitation[]
  financials: Financials
  dashboard: DashboardData
  isFuelStation?: boolean
  fuelDashboard?: FuelHomeDashboard | null
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

// Register editable fields
const nameField = inlineEdit.registerField('name', props.company.name)
const languageField = inlineEdit.registerField('language', props.company.language || 'en')
const localeField = inlineEdit.registerField('locale', props.company.locale || 'en_US')
const fiscalYearField = inlineEdit.registerField('fiscal_year_start_month', props.company.fiscal_year_start_month || 1)

// Invite dialog
const inviteDialogOpen = ref(false)
const roleDialogOpen = ref(false)
const removeDialogOpen = ref(false)
const selectedUser = ref<User | null>(null)
const productDeleteDialogOpen = ref(false)
const selectedProduct = ref<FuelProductDashboardItem | null>(null)

const inviteForm = useForm({
  email: '',
  role: 'member',
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

const canManage = computed(() => ['owner', 'admin'].includes(props.currentUserRole))
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

const availableRoles = ['owner', 'admin', 'accountant', 'viewer', 'member']

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
    admin: 'default',
    accountant: 'secondary',
    viewer: 'outline',
    member: 'outline',
  }
  return variants[role.toLowerCase()] || 'outline'
}

const handleInvite = () => {
  inviteForm.post(`/${props.company.slug}/users/invite`, {
    onSuccess: () => {
      inviteForm.reset()
      inviteDialogOpen.value = false
      toast.success('Invitation sent successfully')
    },
    onError: () => {
      toast.error('Failed to send invitation')
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
  { key: 'name', label: 'User', sortable: true },
  { key: 'role', label: 'Role', sortable: true },
  { key: 'is_active', label: 'Status', sortable: true },
  { key: 'joined_at', label: 'Joined', sortable: true },
  { key: 'actions', label: '', class: 'text-right' },
]

const moneyLocale = (currencyCode?: string) => {
  const code = currencyCode || props.company.base_currency || 'USD'
  if (code === 'PKR') return 'en-PK'
  return 'en-US'
}

const currencySymbol = (currencyCode: string) => {
  const locale = moneyLocale(currencyCode)
  const formatter = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currencyCode,
    currencyDisplay: 'narrowSymbol',
  })
  return formatter.formatToParts(0).find((p) => p.type === 'currency')?.value ?? currencyCode
}

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

const movementTypeLabel = (value?: string | null) => {
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
  if (value === null || value === undefined) return 'text-zinc-500'
  if (Math.abs(value) < 0.001) return 'text-emerald-700'
  return value < 0 ? 'text-red-700' : 'text-amber-700'
}

const variancePrefix = (value?: number | null) => {
  if (value === null || value === undefined || Math.abs(value) < 0.001) return ''
  return value > 0 ? '+' : ''
}

const stockPrecedence = (product: FuelProductDashboardItem) => {
  const stockAt = product.last_stock_movement_at ? new Date(product.last_stock_movement_at) : null
  const readingAt = product.last_dip_at ? new Date(product.last_dip_at) : null

  if (!readingAt || product.last_tank_reading_type === 'opening') return null
  if (!stockAt) return 'No stock entry after this reading'
  if (readingAt.getTime() > stockAt.getTime()) return 'Newer than latest stock entry'
  if (readingAt.getTime() === stockAt.getTime()) return 'Same time as latest stock entry'
  return 'Older than latest stock entry'
}

const shouldShowCurrentVariance = (product: FuelProductDashboardItem) => {
  if (product.stock_variance === null || product.stock_variance === undefined) return false
  if (!hasPhysicalTankReading(product)) return false
  if (!product.last_dip_at || !product.last_stock_movement_at) return true

  return new Date(product.last_dip_at).getTime() >= new Date(product.last_stock_movement_at).getTime()
}
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
      <template v-if="!isFuelStationCompany" #description>
        <span class="font-mono text-zinc-400">{{ company.slug }}</span>
        <span class="mx-2 text-zinc-300">•</span>
        <span class="text-zinc-600">{{ currencySymbol(company.base_currency) }}</span>
      </template>

      <template v-if="!isFuelStationCompany" #actions>
        <TabsList class="bg-zinc-100">
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
          <Card class="border-zinc-200/80 bg-white">
            <CardContent class="space-y-5 pt-6">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-zinc-600">Stock, rates, and recent sales for fuels, lubricants, and shop products.</p>
                <div class="flex flex-wrap gap-2">
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
                <div class="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                  <div class="text-xs text-zinc-500">Products</div>
                  <div class="mt-1 text-2xl font-semibold text-zinc-900">{{ fuelProductSummary.total_products }}</div>
                  <div class="text-xs text-zinc-500">{{ fuelProductSummary.active_products }} active</div>
                </div>
                <div class="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                  <div class="text-xs text-zinc-500">Low stock</div>
                  <div class="mt-1 text-2xl font-semibold" :class="fuelProductSummary.low_stock_count > 0 ? 'text-amber-700' : 'text-zinc-900'">
                    {{ fuelProductSummary.low_stock_count }}
                  </div>
                  <div class="text-xs text-zinc-500">Needs refill</div>
                </div>
                <div class="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                  <div class="text-xs text-zinc-500">Yesterday</div>
                  <div class="mt-1 text-lg font-semibold text-zinc-900">
                    <MoneyText :amount="fuelProductSummary.yesterday_sales" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                  </div>
                  <div class="text-xs text-zinc-500">{{ formatQuantity(fuelProductSummary.yesterday_liters, 'L') }}</div>
                </div>
                <div class="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                  <div class="text-xs text-zinc-500">Last 7 days</div>
                  <div class="mt-1 text-lg font-semibold text-zinc-900">
                    <MoneyText :amount="fuelProductSummary.last_week_sales" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                  </div>
                  <div class="text-xs text-zinc-500">{{ formatQuantity(fuelProductSummary.last_week_liters, 'L') }}</div>
                </div>
                <div class="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                  <div class="text-xs text-zinc-500">Last 30 days</div>
                  <div class="mt-1 text-lg font-semibold text-zinc-900">
                    <MoneyText :amount="fuelProductSummary.last_month_sales" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                  </div>
                  <div class="text-xs text-zinc-500">{{ formatQuantity(fuelProductSummary.last_month_liters, 'L') }}</div>
                </div>
                <div class="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                  <div class="text-xs text-zinc-500">Stock value</div>
                  <div class="mt-1 text-lg font-semibold text-zinc-900">
                    <MoneyText :amount="fuelProductSummary.inventory_value" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                  </div>
                  <div class="text-xs text-zinc-500">At product cost</div>
                </div>
              </div>
            </CardContent>
          </Card>

          <div class="grid gap-6 lg:grid-cols-3">
            <Card class="border-zinc-200/80 bg-white lg:col-span-2">
              <CardHeader>
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <CardTitle class="text-base font-semibold text-zinc-900">Product Overview</CardTitle>
                    <CardDescription>Current stock, sale rate, margin, and recent movement.</CardDescription>
                  </div>
                  <Badge variant="outline">{{ fuelProductRows.length }} products · {{ fuelProductSummary.active_products }} active</Badge>
                </div>
              </CardHeader>
              <CardContent>
                <div v-if="fuelProductRows.length === 0" class="rounded-lg border border-dashed border-zinc-200 p-6 text-center">
                  <Package class="mx-auto h-8 w-8 text-zinc-400" />
                  <p class="mt-2 text-sm font-medium text-zinc-900">No products yet</p>
                  <p class="mt-1 text-sm text-zinc-500">Add the fuels and products this station sells.</p>
                  <Button class="mt-4" size="sm" @click="productsDialogOpen = true">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Fuel Product
                  </Button>
                </div>
                <div v-else class="space-y-3">
                  <div
                    v-for="product in fuelProductRows"
                    :key="product.id"
                    class="cursor-pointer rounded-lg border border-zinc-100 bg-white p-3 transition-colors hover:border-zinc-300 hover:bg-zinc-50"
                    role="button"
                    tabindex="0"
                    @click="openProduct(product)"
                    @keydown.enter="openProduct(product)"
                  >
                    <div class="grid gap-3 lg:grid-cols-[minmax(180px,1.2fr)_minmax(160px,1fr)_repeat(3,minmax(120px,0.8fr))_auto] lg:items-center">
                      <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                          <p class="truncate text-sm font-semibold text-zinc-900">{{ product.name }}</p>
                          <Badge :variant="!product.is_active ? 'outline' : product.is_low_stock ? 'destructive' : 'secondary'" class="text-xs">
                            {{ !product.is_active ? 'Inactive' : product.is_low_stock ? 'Low stock' : productCategoryLabel(product.fuel_category) }}
                          </Badge>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500">
                          <span v-if="product.sku">{{ product.sku }}</span>
                          <span>Rate <MoneyText :amount="product.sale_rate" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" /></span>
                          <span>Margin <MoneyText :amount="product.margin" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" /></span>
                        </div>
                      </div>

                      <div class="rounded-md bg-zinc-50 p-2">
                        <div class="flex items-center justify-between gap-2 text-xs text-zinc-500">
                          <span>System stock</span>
                          <span v-if="product.capacity">{{ formatPercent(product.fill_percentage) }}</span>
                        </div>
                        <div class="mt-1 text-sm font-semibold text-zinc-900">
                          {{ formatQuantity(product.current_stock, product.unit) }}
                        </div>
                        <div class="mt-1 space-y-1 text-xs text-zinc-500">
                          <div v-if="product.last_stock_movement_at">
                            {{ movementTypeLabel(product.last_stock_movement_type) }}:
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
                          <div v-if="stockPrecedence(product)" class="font-medium" :class="shouldShowCurrentVariance(product) ? 'text-zinc-700' : 'text-amber-700'">
                            {{ stockPrecedence(product) }}
                          </div>
                          <div v-if="product.stock_variance !== null && shouldShowCurrentVariance(product)" :class="varianceClass(product.stock_variance)">
                            Current variance: {{ variancePrefix(product.stock_variance) }}{{ formatQuantity(product.stock_variance, product.unit) }}
                          </div>
                          <div v-else-if="product.stock_variance !== null && hasPhysicalTankReading(product)" class="text-zinc-500">
                            Older reading variance: {{ variancePrefix(product.stock_variance) }}{{ formatQuantity(product.stock_variance, product.unit) }}
                          </div>
                          <div v-if="product.low_stock_level > 0">
                            Alert at {{ formatQuantity(product.low_stock_level, product.unit) }}
                          </div>
                        </div>
                      </div>

                      <div>
                        <div class="text-xs text-zinc-500">Yesterday</div>
                        <div class="text-sm font-semibold text-zinc-900">
                          <MoneyText :amount="product.sales.yesterday.amount" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                        </div>
                        <div class="text-xs text-zinc-500">{{ formatQuantity(product.sales.yesterday.quantity, product.unit) }}</div>
                      </div>

                      <div>
                        <div class="text-xs text-zinc-500">Last 7 days</div>
                        <div class="text-sm font-semibold text-zinc-900">
                          <MoneyText :amount="product.sales.last_week.amount" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                        </div>
                        <div class="text-xs text-zinc-500">{{ formatQuantity(product.sales.last_week.quantity, product.unit) }}</div>
                      </div>

                      <div>
                        <div class="text-xs text-zinc-500">Last 30 days</div>
                        <div class="text-sm font-semibold text-zinc-900">
                          <MoneyText :amount="product.sales.last_month.amount" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                        </div>
                        <div class="text-xs text-zinc-500">
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
                            <DropdownMenuItem class="text-red-600 focus:text-red-600" @click="openProductDeleteDialog(product)">
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
              <Card class="border-zinc-200/80 bg-white">
                <CardHeader>
                  <div class="flex items-center gap-2">
                    <Warehouse class="h-5 w-5 text-zinc-700" />
                    <CardTitle class="text-base font-semibold text-zinc-900">Stock Management</CardTitle>
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

              <Card class="border-zinc-200/80 bg-white">
                <CardHeader>
                  <div class="flex items-center gap-2">
                    <AlertTriangle class="h-5 w-5" :class="lowStockProducts.length > 0 ? 'text-amber-600' : 'text-zinc-400'" />
                    <CardTitle class="text-base font-semibold text-zinc-900">Low Stock</CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <div v-if="lowStockProducts.length === 0" class="text-sm text-zinc-500">No product is below its alert level.</div>
                  <div v-else class="space-y-3">
                    <div v-for="product in lowStockProducts" :key="product.id" class="rounded-md border border-amber-100 bg-amber-50 p-3">
                      <div class="flex items-start justify-between gap-3">
                        <div>
                          <p class="text-sm font-semibold text-zinc-900">{{ product.name }}</p>
                          <p class="text-xs text-zinc-600">{{ productCategoryLabel(product.fuel_category) }}</p>
                        </div>
                        <Badge variant="destructive">{{ formatPercent(product.fill_percentage) }}</Badge>
                      </div>
                      <div class="mt-2 text-sm text-zinc-700">
                        {{ formatQuantity(product.current_stock, product.unit) }} left
                      </div>
                      <div class="text-xs text-zinc-500">
                        Alert level {{ formatQuantity(product.low_stock_level, product.unit) }}
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <Card class="border-zinc-200/80 bg-white">
                <CardHeader>
                  <div class="flex items-center gap-2">
                    <TrendingUp class="h-5 w-5 text-emerald-600" />
                    <CardTitle class="text-base font-semibold text-zinc-900">Top Products</CardTitle>
                  </div>
                  <CardDescription>Ranked by sales in the last 30 days.</CardDescription>
                </CardHeader>
                <CardContent>
                  <div v-if="topFuelProducts.length === 0" class="text-sm text-zinc-500">No posted sales in the last 30 days.</div>
                  <div v-else class="space-y-3">
                    <div v-for="(product, index) in topFuelProducts" :key="product.id" class="flex items-center justify-between gap-3">
                      <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-700">
                          {{ index + 1 }}
                        </div>
                        <div class="min-w-0">
                          <p class="truncate text-sm font-medium text-zinc-900">{{ product.name }}</p>
                          <p class="text-xs text-zinc-500">{{ formatQuantity(product.sales.last_month.quantity, product.unit) }}</p>
                        </div>
                      </div>
                      <div class="text-sm font-semibold text-zinc-900">
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

        <!-- Cash Position -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium text-zinc-500 uppercase tracking-wider">Cash Position</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="mb-4">
              <div class="text-3xl font-bold text-zinc-900">
                <MoneyText :amount="dashboard.cash_position.total" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
              </div>
              <p class="text-xs text-zinc-500 mt-1">Total across all accounts</p>
            </div>
            <div v-if="dashboard.cash_position.accounts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
              <div v-for="(account, idx) in dashboard.cash_position.accounts" :key="idx" 
                class="p-3 bg-zinc-50 rounded-lg border border-zinc-100"
              >
                <div class="text-xs text-zinc-500 mb-1 truncate">{{ account.name }}</div>
                <div class="font-semibold text-zinc-800">
                  <MoneyText :amount="account.balance" :currency="account.currency" :locale="moneyLocale(account.currency)" />
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-zinc-500 italic">No bank accounts connected.</div>
          </CardContent>
        </Card>

        <!-- P&L Summary Widget -->
        <Card class="border-zinc-200/80 bg-gradient-to-br from-white to-zinc-50">
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
              <CardTitle class="text-sm font-medium text-zinc-500 uppercase tracking-wider">{{ t('profit') }} - {{ dashboard.profit_loss.period }}</CardTitle>
              <Button variant="ghost" size="sm" class="text-xs text-zinc-500 hover:text-zinc-700" @click="router.visit(`/${company.slug}/reports/profit-loss`)">
                View Report →
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div class="flex items-center gap-4 mb-4">
              <div class="text-4xl font-bold" :class="dashboard.profit_loss.profit >= 0 ? 'text-emerald-600' : 'text-red-600'">
                <MoneyText :amount="dashboard.profit_loss.profit" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
              </div>
              <div v-if="dashboard.profit_loss.profit_growth !== 0" class="flex items-center gap-1 px-2 py-1 rounded-full text-sm"
                :class="dashboard.profit_loss.profit_growth >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                <component :is="dashboard.profit_loss.profit_growth >= 0 ? TrendingUp : TrendingDown" class="h-4 w-4" />
                {{ dashboard.profit_loss.profit_growth >= 0 ? '+' : '' }}{{ dashboard.profit_loss.profit_growth }}%
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4 pt-4 border-t border-zinc-100">
              <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">{{ t('moneyIn') }}</div>
                <div class="text-lg font-semibold text-zinc-800">
                  <MoneyText :amount="dashboard.profit_loss.income" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                </div>
              </div>
              <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wide mb-1">{{ t('moneyOut') }}</div>
                <div class="text-lg font-semibold text-zinc-800">
                  <MoneyText :amount="dashboard.profit_loss.expenses" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Money In / Money Out -->
        <div class="grid gap-6 md:grid-cols-2">
          <!-- Money In -->
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500 uppercase tracking-wider">{{ t('moneyIn') }}</CardTitle>
              <Badge :variant="dashboard.money_in_out.money_in.growth >= 0 ? 'default' : 'destructive'" class="text-xs">
                {{ dashboard.money_in_out.money_in.growth >= 0 ? '+' : '' }}{{ dashboard.money_in_out.money_in.growth.toFixed(1) }}%
              </Badge>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold text-zinc-900">
                <MoneyText :amount="dashboard.money_in_out.money_in.current" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
              </div>
              <p class="text-xs text-zinc-500 mt-1">
                This month vs
                <MoneyText :amount="dashboard.money_in_out.money_in.last" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                last month
              </p>
              
              <div class="mt-4 pt-4 border-t flex justify-between items-center">
                <div class="text-sm text-zinc-600">
                  <span class="font-medium">{{ financials.ar_overdue_count }}</span> invoices overdue
                </div>
                <div class="text-sm font-semibold text-amber-600">
                  <MoneyText :amount="financials.ar_overdue" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                  overdue
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Money Out -->
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader class="flex flex-row items-center justify-between pb-2">
              <CardTitle class="text-sm font-medium text-zinc-500 uppercase tracking-wider">{{ t('moneyOut') }}</CardTitle>
              <Badge :variant="dashboard.money_in_out.money_out.growth <= 0 ? 'default' : 'outline'" class="text-xs">
                {{ dashboard.money_in_out.money_out.growth >= 0 ? '+' : '' }}{{ dashboard.money_in_out.money_out.growth.toFixed(1) }}%
              </Badge>
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold text-zinc-900">
                <MoneyText :amount="dashboard.money_in_out.money_out.current" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
              </div>
              <p class="text-xs text-zinc-500 mt-1">
                This month vs
                <MoneyText :amount="dashboard.money_in_out.money_out.last" :currency="company.base_currency" :locale="moneyLocale(company.base_currency)" />
                last month
              </p>
              
            <div class="mt-4 pt-4 border-t flex justify-between items-center">
              <div class="text-sm text-zinc-600">
                <span class="font-medium">{{ dashboard.needs_attention.bills_due_soon }}</span> bills due soon
              </div>
              <div class="text-sm font-semibold text-amber-600">
                <MoneyText
                  :amount="dashboard.needs_attention.bills_due_soon_amount || 0"
                  :currency="company.base_currency"
                  :locale="moneyLocale(company.base_currency)"
                />
                due soon
              </div>
            </div>
          </CardContent>
          </Card>
        </div>

        <!-- Quick Actions & Needs Attention -->
        <div class="grid gap-6 md:grid-cols-3">
          
          <!-- Quick Actions -->
          <Card class="md:col-span-2 border-zinc-200/80 bg-white">
            <CardHeader>
              <CardTitle class="text-base font-semibold text-zinc-900">Quick Actions</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/invoices/create`)">
                  <Receipt class="h-6 w-6 text-primary" />
                  <span>{{ t('createInvoice') }}</span>
                </Button>
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/payments/create`)">
                  <Wallet class="h-6 w-6 text-emerald-600" />
                  <span>{{ t('recordPayment') }}</span>
                </Button>
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/bills/create`)">
                  <Ban class="h-6 w-6 text-red-500" />
                  <span>{{ t('enterBill') }}</span>
                </Button>
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/banking/feed`)">
                  <BarChart3 class="h-6 w-6 text-indigo-600" />
                  <span>{{ t('reviewTransactionsAction') }}</span>
                </Button>
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/sales/create`)">
                  <Receipt class="h-6 w-6 text-primary" />
                  <span>{{ t('recordSale') }}</span>
                </Button>
                <Button variant="outline" class="h-auto py-4 flex flex-col items-center gap-2 hover:border-primary/50 hover:bg-primary/5" @click="router.visit(`/${company.slug}/reports/profit-loss`)">
                  <BarChart3 class="h-6 w-6 text-indigo-600" />
                  <span>{{ t('profitAndLoss') }}</span>
                </Button>
              </div>
            </CardContent>
          </Card>

          <!-- Needs Attention -->
          <Card class="border-zinc-200/80 bg-white">
            <CardHeader>
              <CardTitle class="text-base font-semibold text-zinc-900">Needs Attention</CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-3">
                <div v-if="dashboard.needs_attention.overdue_invoices > 0" class="flex items-center gap-3 p-2 rounded-md bg-red-50 border border-red-100">
                  <AlertTriangle class="h-5 w-5 text-red-600" />
                  <div class="text-sm">
                    <span class="font-bold text-red-800">{{ dashboard.needs_attention.overdue_invoices }}</span> invoices overdue
                  </div>
                </div>
                
                <div v-if="dashboard.needs_attention.bills_due_soon > 0" class="flex items-center gap-3 p-2 rounded-md bg-amber-50 border border-amber-100">
                  <Calendar class="h-5 w-5 text-amber-600" />
                  <div class="text-sm">
                    <span class="font-bold text-amber-800">{{ dashboard.needs_attention.bills_due_soon }}</span> bills due this week
                  </div>
                </div>

                <div v-if="dashboard.needs_attention.unreconciled_transactions > 0" class="flex items-center gap-3 p-2 rounded-md bg-indigo-50 border border-indigo-100 cursor-pointer hover:bg-indigo-100 transition-colors" @click="router.visit(`/${company.slug}/banking/feed`)">
                  <Wallet class="h-5 w-5 text-indigo-600" />
                  <div class="text-sm">
                    {{ tpl('transactionsToReviewCount', { count: dashboard.needs_attention.unreconciled_transactions }) }}
                  </div>
                </div>

                <div v-if="dashboard.needs_attention.overdue_invoices === 0 && dashboard.needs_attention.bills_due_soon === 0 && dashboard.needs_attention.unreconciled_transactions === 0" class="text-center py-4 text-zinc-500 text-sm">
                  <CheckCircle2 class="h-8 w-8 text-emerald-500 mx-auto mb-2" />
                  All caught up!
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-sm font-medium text-zinc-500">Recent Activity</CardTitle>
            <CardDescription>Last few accounting events</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3 text-sm text-zinc-700">
            <div v-for="(item, idx) in financials.recent_activity" :key="idx" class="flex justify-between border-b border-zinc-200 pb-2 last:border-b-0 last:pb-0">
              <div>
                <p class="font-medium text-zinc-900">{{ item.label }}</p>
                <p class="text-xs text-zinc-500">{{ formatDate(item.occurred_at) }}</p>
              </div>
              <div v-if="item.amount" class="font-mono text-sm text-zinc-800">
                <MoneyText :amount="item.amount" :currency="item.currency || company.base_currency" :locale="moneyLocale(item.currency || company.base_currency)" />
              </div>
            </div>
            <div v-if="financials.recent_activity.length === 0" class="text-sm text-zinc-500">No recent activity.</div>
          </CardContent>
        </Card>
        </template>
      </TabsContent>

      <!-- Settings Tab -->
      <TabsContent v-if="canManage && !isFuelStationCompany" value="settings" class="space-y-6">
        <!-- Editable Settings -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900">Company Settings</CardTitle>
            <CardDescription class="text-zinc-500">
              {{ canManage ? 'Click on the pencil icon to edit a setting' : 'Contact an owner or admin to make changes' }}
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
                <Label class="text-sm font-medium text-zinc-500">Slug</Label>
                <div class="font-mono text-base text-zinc-900">{{ company.slug }}</div>
                <p class="text-xs text-zinc-400">Cannot be changed</p>
              </div>

              <!-- Base Currency (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Base Currency</Label>
                <div class="text-base text-zinc-900">
                  <span class="font-mono">{{ currencySymbol(company.base_currency) }}</span>
                  <span class="ml-2 text-sm text-zinc-500">({{ company.base_currency }})</span>
                </div>
                <p class="text-xs text-zinc-400">Cannot be changed after creation</p>
              </div>

              <!-- Country (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Country</Label>
                <div class="text-base text-zinc-900">{{ company.country || '—' }}</div>
                <p class="text-xs text-zinc-400">Cannot be changed</p>
              </div>

              <!-- Industry (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Industry</Label>
                <div class="text-base text-zinc-900 capitalize">
                  {{ company.industry_name || company.industry || company.industry_code || '—' }}
                </div>
              </div>

              <!-- Created Date (Read-only) -->
              <div class="space-y-1.5">
                <Label class="text-sm font-medium text-zinc-500">Created</Label>
                <div class="flex items-center gap-1.5 text-base text-zinc-900">
                  <Calendar class="h-3.5 w-3.5 text-zinc-400" />
                  {{ formatDate(company.created_at) }}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Regional Settings -->
        <Card class="border-zinc-200/80 bg-white">
          <CardHeader>
            <CardTitle class="text-zinc-900 flex items-center gap-2">
              <Globe class="h-4 w-4" />
              Regional Settings
            </CardTitle>
            <CardDescription class="text-zinc-500">
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

        <!-- Pending Invitations (visible to owners) -->
        <Card v-if="currentUserRole === 'owner' && pendingInvitations.length > 0" class="border-amber-100 bg-amber-50">
          <CardHeader class="pb-3">
            <CardTitle class="text-amber-900">Pending Invitations</CardTitle>
            <CardDescription class="text-amber-700">
              Invites sent from {{ company.name }}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-3">
              <div
                v-for="invite in pendingInvitations"
                :key="invite.id"
                class="flex flex-col gap-1 rounded-lg border border-amber-200/80 bg-white px-4 py-3 shadow-xs"
              >
                <div class="flex items-center justify-between">
                  <div class="font-medium text-amber-900">{{ invite.email }}</div>
                  <Badge variant="outline" class="capitalize text-amber-800">
                    {{ invite.role }}
                  </Badge>
                </div>
                <div class="text-xs text-amber-700">
                  Expires {{ formatDate(invite.expires_at) }}
                  <span v-if="invite.inviter_name"> • Invited by {{ invite.inviter_name }}</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      <!-- Users Tab -->
      <TabsContent v-if="canManage && !isFuelStationCompany" value="users" class="space-y-6">
        <DataTable
          :data="users"
          :columns="tableColumns"
          title="Team Members"
          :description="`${users.length} ${users.length === 1 ? 'member' : 'members'} in this company`"
          key-field="id"
          hoverable
        >
          <template #header>
            <Button v-if="canManage" size="sm" @click="inviteDialogOpen = true">
              <UserPlus class="mr-2 h-4 w-4" />
              Invite User
            </Button>
          </template>

          <template #cell-name="{ row }">
            <div class="flex flex-col">
              <span class="font-medium text-zinc-900">{{ row.name || 'Unknown' }}</span>
              <div class="flex items-center gap-1 text-zinc-500">
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
            <div v-if="row.joined_at" class="flex items-center gap-1 text-zinc-700">
              <Calendar class="h-3 w-3 text-zinc-400" />
              <span>{{ formatDate(row.joined_at) }}</span>
            </div>
            <span v-else class="text-zinc-400">—</span>
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
                  <DropdownMenuItem @click="openRemoveDialog(row)" class="text-red-600 focus:text-red-600">
                    <Trash2 class="mr-2 h-4 w-4" />
                    Remove User
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </template>
        </DataTable>
      </TabsContent>

    <!-- Product Setup Dialog -->
    <Dialog v-model:open="productsDialogOpen">
      <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle class="text-zinc-900">Add Product</DialogTitle>
          <DialogDescription class="text-zinc-500">
            Add the product, rate, opening stock, and storage in one place.
          </DialogDescription>
        </DialogHeader>

        <div v-for="(row, index) in productsForm.products.slice(0, 1)" :key="index" class="space-y-5 py-2">
          <section class="space-y-3">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-sm font-semibold text-zinc-900">Product</h3>
                <p class="text-xs text-zinc-500">What the station sells.</p>
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
                <p v-if="productsForm.errors[`products.${index}.fuel_category`]" class="text-xs text-red-600">
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
                <p class="text-xs text-zinc-500">
                  Bulk needs storage setup. Packaged is counted as bottles/units.
                </p>
                <p v-if="productsForm.errors[`products.${index}.lubricant_format`]" class="text-xs text-red-600">
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
                <p class="text-xs text-zinc-500">
                  Bulk needs storage setup. Packaged is counted as units.
                </p>
                <p v-if="productsForm.errors[`products.${index}.packaging`]" class="text-xs text-red-600">
                  {{ productsForm.errors[`products.${index}.packaging`] }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Name</Label>
                <Input v-model="row.name" placeholder="Product name" />
                <p v-if="productsForm.errors[`products.${index}.name`]" class="text-xs text-red-600">
                  {{ productsForm.errors[`products.${index}.name`] }}
                </p>
              </div>
            </div>
          </section>

          <section class="space-y-3 border-t border-zinc-100 pt-4">
            <div>
              <h3 class="text-sm font-semibold text-zinc-900">Rate and Opening Stock</h3>
              <p class="text-xs text-zinc-500">Starting numbers for the product.</p>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
              <div class="space-y-2">
                <Label>Effective Date</Label>
                <Input v-model="productsForm.effective_date" type="date" />
                <p v-if="productsForm.errors.effective_date" class="text-xs text-red-600">
                  {{ productsForm.errors.effective_date }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Purchase Rate</Label>
                <Input v-model="row.purchase_rate" type="number" step="0.01" min="0" />
                <p v-if="productsForm.errors[`products.${index}.purchase_rate`]" class="text-xs text-red-600">
                  {{ productsForm.errors[`products.${index}.purchase_rate`] }}
                </p>
              </div>

              <div class="space-y-2">
                <Label>Sale Rate</Label>
                <Input v-model="row.sale_rate" type="number" step="0.01" min="0" />
                <p v-if="productsForm.errors[`products.${index}.sale_rate`]" class="text-xs text-red-600">
                  {{ productsForm.errors[`products.${index}.sale_rate`] }}
                </p>
              </div>

              <div v-if="row.track_inventory" class="space-y-2">
                <Label>Opening Stock</Label>
                <Input v-model="row.opening_quantity" type="number" step="0.001" min="0" placeholder="Optional" />
                <p v-if="productsForm.errors[`products.${index}.opening_quantity`]" class="text-xs text-red-600">
                  {{ productsForm.errors[`products.${index}.opening_quantity`] }}
                </p>
              </div>
            </div>
          </section>

          <section v-if="row.track_inventory && isOpenPackaging(row)" class="space-y-3 border-t border-zinc-100 pt-4">
            <div>
              <h3 class="text-sm font-semibold text-zinc-900">Storage and Pump</h3>
              <p class="text-xs text-zinc-500">Where the product is stored and how it is served.</p>
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
                <p v-if="productsForm.errors[`products.${index}.tank_id`]" class="text-xs text-red-600">
                  {{ productsForm.errors[`products.${index}.tank_id`] }}
                </p>
                <div v-if="row.new_tank" class="flex items-center justify-between gap-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-emerald-950">
                      {{ row.new_tank.name }} ({{ row.new_tank.code }})
                    </div>
                    <div class="text-xs text-emerald-700">
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

            <div v-if="row.type === 'fuel'" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-100 bg-zinc-50 p-3">
              <div>
                <div class="text-sm font-medium text-zinc-900">Create pump points</div>
                <div class="text-xs text-zinc-500">Turn off only if pump/nozzles already exist.</div>
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
                class="grid gap-3 rounded-lg border border-zinc-100 p-3 md:grid-cols-[1fr_160px_auto] md:items-end"
              >
                <div class="space-y-2">
                  <Label>{{ pumpIndex === 0 ? 'Pump Point' : `Pump Point ${pumpIndex + 1}` }}</Label>
                  <Input v-model="pumpSetup.name" :placeholder="`Point ${pumpIndex + 1}`" />
                  <p v-if="productsForm.errors[`products.${index}.pump_setups.${pumpIndex}.name`]" class="text-xs text-red-600">
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
            <CollapsibleTrigger class="flex w-full items-center justify-between rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-600 transition-colors hover:bg-zinc-50 hover:text-zinc-900">
              <span>Advanced setup</span>
              <ChevronDown class="h-4 w-4" />
            </CollapsibleTrigger>

            <CollapsibleContent class="mt-4 space-y-4">
              <div class="grid gap-4 md:grid-cols-3">
                <div class="space-y-2">
                  <Label>SKU</Label>
                  <Input v-model="row.sku" placeholder="Auto-generated if blank" />
                  <p v-if="productsForm.errors[`products.${index}.sku`]" class="text-xs text-red-600">
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

              <div v-if="row.type === 'fuel' && row.track_inventory && row.create_pump_points" class="space-y-3 rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                <div>
                  <h3 class="text-sm font-semibold text-zinc-900">Nozzle Details</h3>
                  <p class="text-xs text-zinc-500">Leave IDs blank for automatic numbering.</p>
                </div>

                <div
                  v-for="(pumpSetup, pumpIndex) in row.pump_setups"
                  :key="pumpIndex"
                  class="space-y-3 rounded-md border border-zinc-200 bg-white p-3"
                >
                  <div class="text-xs font-medium text-zinc-600">{{ pumpSetup.name || `Point ${pumpIndex + 1}` }}</div>
                  <div
                    v-for="(nozzle, nozzleIndex) in pumpSetup.nozzles"
                    :key="nozzleIndex"
                    class="grid gap-3 md:grid-cols-4"
                  >
                    <div class="space-y-2">
                      <Label>Nozzle ID</Label>
                      <Input v-model="nozzle.code" placeholder="Auto" />
                      <p v-if="productsForm.errors[`products.${index}.pump_setups.${pumpIndex}.nozzles.${nozzleIndex}.code`]" class="text-xs text-red-600">
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
          <DialogTitle class="text-zinc-900">Add Tank</DialogTitle>
          <DialogDescription class="text-zinc-500">
            This tank will be created when you create the product.
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Tank Name</Label>
              <Input v-model="tankDraft.name" placeholder="e.g., Petrol Tank 1" />
              <p v-if="tankDraftErrors.name" class="text-xs text-red-600">{{ tankDraftErrors.name }}</p>
            </div>
            <div class="space-y-2">
              <Label>Tank Code</Label>
              <Input v-model="tankDraft.code" placeholder="e.g., TANK-PET" />
              <p v-if="tankDraftErrors.code" class="text-xs text-red-600">{{ tankDraftErrors.code }}</p>
            </div>
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Capacity (Liters)</Label>
              <Input v-model="tankDraft.capacity" type="number" step="0.01" min="1" />
              <p v-if="tankDraftErrors.capacity" class="text-xs text-red-600">{{ tankDraftErrors.capacity }}</p>
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

    <!-- Invite User Dialog -->
    <Dialog v-model:open="inviteDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-zinc-900">Invite User</DialogTitle>
          <DialogDescription class="text-zinc-500">
            Send an invitation to join {{ company.name }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label for="email" class="text-zinc-700">Email</Label>
            <Input
              id="email"
              v-model="inviteForm.email"
              type="email"
              placeholder="user@example.com"
              class="border-zinc-200"
            />
            <p v-if="inviteForm.errors.email" class="text-xs text-red-600">
              {{ inviteForm.errors.email }}
            </p>
          </div>
          <div class="space-y-2">
            <Label class="text-zinc-700">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between border-zinc-200">
                  <span class="capitalize">{{ inviteForm.role }}</span>
                  <span class="ml-2">▼</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-full">
                <DropdownMenuItem
                  v-for="role in availableRoles"
                  :key="role"
                  @click="inviteForm.role = role"
                  class="capitalize"
                >
                  {{ role }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="inviteDialogOpen = false" :disabled="inviteForm.processing">
            Cancel
          </Button>
          <Button @click="handleInvite" :disabled="inviteForm.processing">
            <span v-if="inviteForm.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
            Send Invitation
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Change Role Dialog -->
    <Dialog v-model:open="roleDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle class="text-zinc-900">Change User Role</DialogTitle>
          <DialogDescription class="text-zinc-500">
            Update the role for {{ selectedUser?.name || selectedUser?.email }}
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <Label class="text-zinc-700">Role</Label>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="w-full justify-between border-zinc-200">
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
