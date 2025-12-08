<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'

interface CompanyRef {
  id: string
  slug: string
}

interface Jurisdiction {
  id: string
  name: string
  code: string
  country_code?: string
}

interface TaxRate {
  id: string
  code: string
  name: string
  rate: number
  tax_type: string
  is_active: boolean
  jurisdiction_id: string
}

interface TaxGroup {
  id: string
  code: string
  name: string
  jurisdiction_id: string
  is_active: boolean
  tax_rates?: TaxRate[]
}

interface TaxRegistration {
  id: string
  jurisdiction_id: string
  registration_number: string
  registration_type: string
  registered_name?: string | null
  effective_from: string
  effective_to?: string | null
  is_active: boolean
}

interface TaxExemption {
  id: string
  code: string
  name: string
  exemption_type: string
  requires_certificate: boolean
  is_active: boolean
}

const props = defineProps<{
  company: CompanyRef
  taxSettings: {
    tax_enabled: boolean
    default_jurisdiction_id?: string | null
    default_sales_tax_rate_id?: string | null
    default_purchase_tax_rate_id?: string | null
    price_includes_tax: boolean
    rounding_mode: string
    rounding_precision: number
    tax_number_label: string
    show_tax_column: boolean
  }
  jurisdictions: Jurisdiction[]
  taxRates: TaxRate[]
  taxGroups: TaxGroup[]
  taxRegistrations: TaxRegistration[]
  taxExemptions: TaxExemption[]
  canManageTax: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Tax', href: `/${props.company.slug}/tax/settings` },
]

const noneValue = '__none'
const canManage = computed(() => props.canManageTax)

const settingsForm = useForm({
  tax_enabled: props.taxSettings.tax_enabled ?? false,
  default_jurisdiction_id: props.taxSettings.default_jurisdiction_id ?? noneValue,
  default_sales_tax_rate_id: props.taxSettings.default_sales_tax_rate_id ?? noneValue,
  default_purchase_tax_rate_id: props.taxSettings.default_purchase_tax_rate_id ?? noneValue,
  price_includes_tax: props.taxSettings.price_includes_tax ?? false,
  rounding_mode: props.taxSettings.rounding_mode ?? 'half_up',
  rounding_precision: props.taxSettings.rounding_precision ?? 2,
  tax_number_label: props.taxSettings.tax_number_label ?? 'Tax ID',
  show_tax_column: props.taxSettings.show_tax_column ?? true,
})

const rateForm = useForm({
  jurisdiction_id: '',
  code: '',
  name: '',
  rate: 0,
  tax_type: 'sales',
  is_compound: false,
  compound_priority: 0,
  gl_account_id: noneValue,
  recoverable_account_id: noneValue,
  effective_from: '',
  effective_to: '',
  is_default: false,
  is_active: true,
  description: '',
})

const groupForm = useForm({
  jurisdiction_id: '',
  code: '',
  name: '',
  is_default: false,
  is_active: true,
  description: '',
  components: [{ tax_rate_id: '', priority: 1 }],
})

const registrationForm = useForm({
  jurisdiction_id: '',
  registration_number: '',
  registration_type: 'vat',
  registered_name: '',
  effective_from: '',
  effective_to: '',
  is_active: true,
  notes: '',
})

const exemptionForm = useForm({
  code: '',
  name: '',
  description: '',
  exemption_type: 'full',
  override_rate: '',
  requires_certificate: false,
  is_active: true,
})

const nullableId = (value: string) => (value === noneValue ? null : value)

const saveSettings = () => {
  settingsForm
    .transform((data) => ({
      ...data,
      default_jurisdiction_id: nullableId(data.default_jurisdiction_id),
      default_sales_tax_rate_id: nullableId(data.default_sales_tax_rate_id),
      default_purchase_tax_rate_id: nullableId(data.default_purchase_tax_rate_id),
    }))
    .post(`/${props.company.slug}/tax/settings`, {
      preserveScroll: true,
      onFinish: () => settingsForm.transform((data) => data),
    })
}

const createRate = () => {
  rateForm
    .transform((data) => ({
      ...data,
      gl_account_id: nullableId(data.gl_account_id),
      recoverable_account_id: nullableId(data.recoverable_account_id),
      effective_to: data.effective_to || null,
    }))
    .post(`/${props.company.slug}/tax/rates`, {
      preserveScroll: true,
      onSuccess: () =>
        rateForm.reset(
          'jurisdiction_id',
          'code',
          'name',
          'rate',
          'tax_type',
          'is_compound',
          'compound_priority',
          'gl_account_id',
          'recoverable_account_id',
          'effective_from',
          'effective_to',
          'is_default',
          'is_active',
          'description',
        ),
    })
}

const addComponentRow = () => {
  groupForm.components.push({ tax_rate_id: '', priority: groupForm.components.length + 1 })
}

const createGroup = () => {
  groupForm
    .transform((data) => ({
      ...data,
      components: data.components.map((component, index) => ({
        tax_rate_id: component.tax_rate_id,
        priority: component.priority || index + 1,
      })),
    }))
    .post(`/${props.company.slug}/tax/groups`, {
      preserveScroll: true,
      onSuccess: () =>
        groupForm.reset('jurisdiction_id', 'code', 'name', 'is_default', 'is_active', 'description', 'components'),
    })
}

const createRegistration = () => {
  registrationForm.post(`/${props.company.slug}/tax/registrations`, {
    preserveScroll: true,
    onSuccess: () =>
      registrationForm.reset(
        'jurisdiction_id',
        'registration_number',
        'registration_type',
        'registered_name',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
      ),
  })
}

const createExemption = () => {
  exemptionForm.post(`/${props.company.slug}/tax/exemptions`, {
    preserveScroll: true,
    onSuccess: () =>
      exemptionForm.reset('code', 'name', 'description', 'exemption_type', 'override_rate', 'requires_certificate', 'is_active'),
  })
}
</script>

<template>
  <div>
    <Head title="Tax Settings" />

    <PageShell title="Tax Settings" description="Configure jurisdictions, rates, and defaults." :breadcrumbs="breadcrumbs">
      <div class="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Company Tax Settings</CardTitle>
            <CardDescription>Defaults that drive invoice and bill tax calculation.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex items-center justify-between rounded-lg border p-3">
              <div>
                <p class="text-sm font-medium">Enable tax features</p>
                <p class="text-sm text-muted-foreground">Turn on tax calculations and defaults.</p>
              </div>
              <Switch v-model:checked="settingsForm.tax_enabled" :disabled="!canManage.value" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="default_jurisdiction_id">Default jurisdiction</Label>
                <Select v-model="settingsForm.default_jurisdiction_id" :disabled="!canManage.value">
                  <SelectTrigger id="default_jurisdiction_id">
                    <SelectValue placeholder="Select jurisdiction" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="noneValue">None</SelectItem>
                    <SelectItem v-for="jurisdiction in jurisdictions" :key="jurisdiction.id" :value="jurisdiction.id">
                      {{ jurisdiction.name }} ({{ jurisdiction.code }})
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="rounding_mode">Rounding mode</Label>
                <Select v-model="settingsForm.rounding_mode" :disabled="!canManage.value">
                  <SelectTrigger id="rounding_mode">
                    <SelectValue placeholder="Select rounding" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="half_up">Half up</SelectItem>
                    <SelectItem value="half_down">Half down</SelectItem>
                    <SelectItem value="floor">Floor</SelectItem>
                    <SelectItem value="ceiling">Ceiling</SelectItem>
                    <SelectItem value="bankers">Bankers</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="default_sales_tax_rate_id">Default sales rate</Label>
                <Select v-model="settingsForm.default_sales_tax_rate_id" :disabled="!canManage.value">
                  <SelectTrigger id="default_sales_tax_rate_id">
                    <SelectValue placeholder="Select sales rate" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="noneValue">None</SelectItem>
                    <SelectItem v-for="rate in taxRates" :key="rate.id" :value="rate.id">
                      {{ rate.code }} — {{ rate.rate }}%
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div class="space-y-2">
                <Label for="default_purchase_tax_rate_id">Default purchase rate</Label>
                <Select v-model="settingsForm.default_purchase_tax_rate_id" :disabled="!canManage.value">
                  <SelectTrigger id="default_purchase_tax_rate_id">
                    <SelectValue placeholder="Select purchase rate" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="noneValue">None</SelectItem>
                    <SelectItem v-for="rate in taxRates" :key="rate.id" :value="rate.id">
                      {{ rate.code }} — {{ rate.rate }}%
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="rounding_precision">Rounding precision</Label>
                <Input id="rounding_precision" v-model.number="settingsForm.rounding_precision" type="number" min="0" max="6" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="tax_number_label">Tax number label</Label>
                <Input id="tax_number_label" v-model="settingsForm.tax_number_label" type="text" maxlength="50" :disabled="!canManage.value" />
              </div>
            </div>

            <div class="flex items-center justify-between rounded-lg border p-3">
              <div>
                <p class="text-sm font-medium">Prices include tax</p>
                <p class="text-sm text-muted-foreground">Treat line prices as tax-inclusive.</p>
              </div>
              <Switch v-model:checked="settingsForm.price_includes_tax" :disabled="!canManage.value" />
            </div>

            <div class="flex items-center justify-between rounded-lg border p-3">
              <div>
                <p class="text-sm font-medium">Show tax column</p>
                <p class="text-sm text-muted-foreground">Expose tax amounts on documents.</p>
              </div>
              <Switch v-model:checked="settingsForm.show_tax_column" :disabled="!canManage.value" />
            </div>

            <Button class="w-full" :disabled="!canManage.value || settingsForm.processing" @click="saveSettings">
              Save settings
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Create Tax Rate</CardTitle>
            <CardDescription>Company-scoped rate tied to a jurisdiction.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="rate_jurisdiction">Jurisdiction</Label>
              <Select v-model="rateForm.jurisdiction_id" :disabled="!canManage.value">
                <SelectTrigger id="rate_jurisdiction">
                  <SelectValue placeholder="Select jurisdiction" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="jurisdiction in jurisdictions" :key="jurisdiction.id" :value="jurisdiction.id">
                    {{ jurisdiction.name }} ({{ jurisdiction.code }})
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="rate_code">Code</Label>
                <Input id="rate_code" v-model="rateForm.code" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="rate_name">Name</Label>
                <Input id="rate_name" v-model="rateForm.name" :disabled="!canManage.value" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="rate_percentage">Rate (%)</Label>
                <Input id="rate_percentage" v-model.number="rateForm.rate" type="number" min="0" step="0.01" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="rate_type">Type</Label>
                <Select v-model="rateForm.tax_type" :disabled="!canManage.value">
                  <SelectTrigger id="rate_type">
                    <SelectValue placeholder="Select type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="sales">Sales</SelectItem>
                    <SelectItem value="purchase">Purchase</SelectItem>
                    <SelectItem value="withholding">Withholding</SelectItem>
                    <SelectItem value="both">Both</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="rate_effective_from">Effective from</Label>
                <Input id="rate_effective_from" v-model="rateForm.effective_from" type="date" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="rate_effective_to">Effective to</Label>
                <Input id="rate_effective_to" v-model="rateForm.effective_to" type="date" :disabled="!canManage.value" />
              </div>
            </div>
            <div class="flex items-center justify-between rounded-lg border p-3">
              <div>
                <p class="text-sm font-medium">Compound</p>
                <p class="text-sm text-muted-foreground">Apply on running total in sequence.</p>
              </div>
              <Switch v-model:checked="rateForm.is_compound" :disabled="!canManage.value" />
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="compound_priority">Compound priority</Label>
                <Input id="compound_priority" v-model.number="rateForm.compound_priority" type="number" min="0" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="rate_description">Description</Label>
                <Textarea id="rate_description" v-model="rateForm.description" rows="2" :disabled="!canManage.value" />
              </div>
            </div>
            <div class="flex items-center gap-3">
              <Switch id="rate_default" v-model:checked="rateForm.is_default" :disabled="!canManage.value" />
              <Label for="rate_default">Set as default</Label>
            </div>
            <div class="flex items-center gap-3">
              <Switch id="rate_active" v-model:checked="rateForm.is_active" :disabled="!canManage.value" />
              <Label for="rate_active">Active</Label>
            </div>
            <Button class="w-full" :disabled="!canManage.value || rateForm.processing" @click="createRate">
              Create rate
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Create Tax Group</CardTitle>
            <CardDescription>Combine multiple rates for quick assignment.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="group_jurisdiction">Jurisdiction</Label>
              <Select v-model="groupForm.jurisdiction_id" :disabled="!canManage.value">
                <SelectTrigger id="group_jurisdiction">
                  <SelectValue placeholder="Select jurisdiction" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="jurisdiction in jurisdictions" :key="jurisdiction.id" :value="jurisdiction.id">
                    {{ jurisdiction.name }} ({{ jurisdiction.code }})
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="group_code">Code</Label>
                <Input id="group_code" v-model="groupForm.code" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="group_name">Name</Label>
                <Input id="group_name" v-model="groupForm.name" :disabled="!canManage.value" />
              </div>
            </div>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium">Components</span>
                <Button variant="secondary" size="sm" :disabled="!canManage.value" @click="addComponentRow">Add</Button>
              </div>
              <div class="space-y-3">
                <div v-for="(component, index) in groupForm.components" :key="index" class="grid gap-3 rounded-lg border p-3 md:grid-cols-2">
                  <div class="space-y-2">
                    <Label :for="`component_rate_${index}`">Tax rate</Label>
                    <Select v-model="component.tax_rate_id" :disabled="!canManage.value">
                      <SelectTrigger :id="`component_rate_${index}`">
                        <SelectValue placeholder="Select rate" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="rate in taxRates" :key="rate.id" :value="rate.id">
                          {{ rate.code }} — {{ rate.rate }}%
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="space-y-2">
                    <Label :for="`component_priority_${index}`">Priority</Label>
                    <Input
                      :id="`component_priority_${index}`"
                      v-model.number="component.priority"
                      type="number"
                      min="1"
                      :disabled="!canManage.value"
                    />
                  </div>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <Switch id="group_default" v-model:checked="groupForm.is_default" :disabled="!canManage.value" />
              <Label for="group_default">Set as default</Label>
            </div>
            <div class="flex items-center gap-3">
              <Switch id="group_active" v-model:checked="groupForm.is_active" :disabled="!canManage.value" />
              <Label for="group_active">Active</Label>
            </div>
            <Button class="w-full" :disabled="!canManage.value || groupForm.processing" @click="createGroup">
              Create group
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Tax Registrations</CardTitle>
            <CardDescription>Store registration numbers by jurisdiction.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="registration_jurisdiction">Jurisdiction</Label>
              <Select v-model="registrationForm.jurisdiction_id" :disabled="!canManage.value">
                <SelectTrigger id="registration_jurisdiction">
                  <SelectValue placeholder="Select jurisdiction" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="jurisdiction in jurisdictions" :key="jurisdiction.id" :value="jurisdiction.id">
                    {{ jurisdiction.name }} ({{ jurisdiction.code }})
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="registration_number">Registration number</Label>
                <Input id="registration_number" v-model="registrationForm.registration_number" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="registration_type">Type</Label>
                <Select v-model="registrationForm.registration_type" :disabled="!canManage.value">
                  <SelectTrigger id="registration_type">
                    <SelectValue placeholder="Select type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="vat">VAT</SelectItem>
                    <SelectItem value="gst">GST</SelectItem>
                    <SelectItem value="sales_tax">Sales Tax</SelectItem>
                    <SelectItem value="withholding">Withholding</SelectItem>
                    <SelectItem value="other">Other</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="registered_name">Registered name</Label>
                <Input id="registered_name" v-model="registrationForm.registered_name" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="registration_from">Effective from</Label>
                <Input id="registration_from" v-model="registrationForm.effective_from" type="date" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="registration_to">Effective to</Label>
                <Input id="registration_to" v-model="registrationForm.effective_to" type="date" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="registration_notes">Notes</Label>
                <Textarea id="registration_notes" v-model="registrationForm.notes" rows="2" :disabled="!canManage.value" />
              </div>
            </div>
            <div class="flex items-center gap-3">
              <Switch id="registration_active" v-model:checked="registrationForm.is_active" :disabled="!canManage.value" />
              <Label for="registration_active">Active</Label>
            </div>
            <Button class="w-full" :disabled="!canManage.value || registrationForm.processing" @click="createRegistration">
              Save registration
            </Button>

            <div class="space-y-3">
              <p class="text-sm font-semibold">Existing registrations</p>
              <div v-if="taxRegistrations.length === 0" class="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                No registrations yet.
              </div>
              <div v-else class="space-y-2">
                <div v-for="registration in taxRegistrations" :key="registration.id" class="rounded-lg border p-3">
                  <div class="flex items-center justify-between">
                    <div class="space-y-1">
                      <p class="font-medium">{{ registration.registration_number }}</p>
                      <p class="text-sm text-muted-foreground">{{ registration.registration_type.toUpperCase() }}</p>
                    </div>
                    <Badge :variant="registration.is_active ? 'default' : 'secondary'">
                      {{ registration.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                  </div>
                  <p class="text-xs text-muted-foreground">Effective {{ registration.effective_from }}</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Exemptions</CardTitle>
            <CardDescription>Manage exemption reasons and overrides.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="exemption_code">Code</Label>
                <Input id="exemption_code" v-model="exemptionForm.code" :disabled="!canManage.value" />
              </div>
              <div class="space-y-2">
                <Label for="exemption_name">Name</Label>
                <Input id="exemption_name" v-model="exemptionForm.name" :disabled="!canManage.value" />
              </div>
            </div>
            <div class="space-y-2">
              <Label for="exemption_type">Type</Label>
              <Select v-model="exemptionForm.exemption_type" :disabled="!canManage.value">
                <SelectTrigger id="exemption_type">
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="full">Full</SelectItem>
                  <SelectItem value="partial">Partial</SelectItem>
                  <SelectItem value="rate_override">Rate override</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label for="override_rate">Override rate (%)</Label>
              <Input
                id="override_rate"
                v-model="exemptionForm.override_rate"
                type="number"
                min="0"
                step="0.01"
                :disabled="!canManage.value || exemptionForm.exemption_type !== 'rate_override'"
              />
            </div>
            <div class="space-y-2">
              <Label for="exemption_description">Description</Label>
              <Textarea id="exemption_description" v-model="exemptionForm.description" rows="2" :disabled="!canManage.value" />
            </div>
            <div class="flex items-center gap-3">
              <Switch id="requires_certificate" v-model:checked="exemptionForm.requires_certificate" :disabled="!canManage.value" />
              <Label for="requires_certificate">Requires certificate</Label>
            </div>
            <div class="flex items-center gap-3">
              <Switch id="exemption_active" v-model:checked="exemptionForm.is_active" :disabled="!canManage.value" />
              <Label for="exemption_active">Active</Label>
            </div>
            <Button class="w-full" :disabled="!canManage.value || exemptionForm.processing" @click="createExemption">
              Save exemption
            </Button>

            <div class="space-y-3">
              <p class="text-sm font-semibold">Existing exemptions</p>
              <div v-if="taxExemptions.length === 0" class="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                No exemptions yet.
              </div>
              <div v-else class="space-y-2">
                <div v-for="exemption in taxExemptions" :key="exemption.id" class="rounded-lg border p-3">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="font-medium">{{ exemption.code }} — {{ exemption.name }}</p>
                      <p class="text-sm text-muted-foreground">{{ exemption.exemption_type }}</p>
                    </div>
                    <Badge :variant="exemption.is_active ? 'default' : 'secondary'">
                      {{ exemption.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card class="lg:col-span-2">
          <CardHeader>
            <CardTitle>Current Rates & Groups</CardTitle>
            <CardDescription>Reference data applied across AR/AP.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-4 md:grid-cols-2">
            <div class="space-y-3">
              <p class="text-sm font-semibold">Rates</p>
              <div v-if="taxRates.length === 0" class="rounded-lg bg-muted p-3 text-sm text-muted-foreground">No tax rates yet.</div>
              <div v-else class="space-y-2">
                <div v-for="rate in taxRates" :key="rate.id" class="rounded-lg border p-3">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="font-medium">{{ rate.code }} — {{ rate.rate }}%</p>
                      <p class="text-sm text-muted-foreground">{{ rate.name }}</p>
                    </div>
                    <Badge :variant="rate.is_active ? 'default' : 'secondary'">{{ rate.is_active ? 'Active' : 'Inactive' }}</Badge>
                  </div>
                  <p class="text-xs text-muted-foreground mt-1">Type: {{ rate.tax_type }}</p>
                </div>
              </div>
            </div>
            <div class="space-y-3">
              <p class="text-sm font-semibold">Groups</p>
              <div v-if="taxGroups.length === 0" class="rounded-lg bg-muted p-3 text-sm text-muted-foreground">No tax groups yet.</div>
              <div v-else class="space-y-2">
                <div v-for="group in taxGroups" :key="group.id" class="rounded-lg border p-3">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="font-medium">{{ group.code }} — {{ group.name }}</p>
                      <p class="text-xs text-muted-foreground">
                        {{ group.tax_rates?.length || 0 }} rates
                      </p>
                    </div>
                    <Badge :variant="group.is_active ? 'default' : 'secondary'">{{ group.is_active ? 'Active' : 'Inactive' }}</Badge>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </PageShell>
  </div>
</template>
