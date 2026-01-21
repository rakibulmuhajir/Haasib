<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import TankLevelGauge from '../../../components/TankLevelGauge.vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import {
  ArrowLeft,
  CheckCircle2,
  ClipboardCheck,
  Droplet,
  FileText,
  Warehouse,
} from 'lucide-vue-next'

interface FuelItemRef {
  id: string
  name: string
  fuel_category?: string | null
}

interface TankRef {
  id: string
  name: string
  capacity?: number | null
  linked_item?: FuelItemRef | null
}

type TankReadingStatus = 'draft' | 'confirmed' | 'posted'
type VarianceType = 'loss' | 'gain' | 'none'

interface TankReading {
  id: string
  tank_id: string
  reading_date: string
  reading_type: 'opening' | 'closing' | 'spot_check'
  dip_measurement_liters: number
  system_calculated_liters: number
  variance_liters: number
  variance_type: VarianceType
  variance_reason?: string | null
  status: TankReadingStatus
  notes?: string | null
  journal_entry_id?: string | null
  tank?: TankRef | null
  item?: FuelItemRef | null
}

const props = defineProps<{
  reading: TankReading
  varianceReasons: string[]
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const currencyCode = computed(() => ((page.props as any)?.auth?.currentCompany?.base_currency as string) || 'PKR')

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel', href: `/${companySlug.value}/fuel/tank-readings` },
  { title: 'Tank Readings', href: `/${companySlug.value}/fuel/tank-readings` },
  { title: props.reading.tank?.name ?? 'Reading' },
])

const formatLiters = (n: number) =>
  new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(n ?? 0)

const formatMoney = (n: number) => {
  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currencyDisplay: 'narrowSymbol',
      currency: currencyCode.value,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(n ?? 0)
  } catch (_e) {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
  }
}

const statusClass = computed(() => {
  switch (props.reading.status) {
    case 'posted':
      return 'bg-emerald-600 text-white hover:bg-emerald-600'
    case 'confirmed':
      return 'bg-sky-100 text-sky-800 hover:bg-sky-100'
    default:
      return 'bg-amber-100 text-amber-800 hover:bg-amber-100'
  }
})

const variance = computed(() => {
  const v = Number(props.reading.variance_liters ?? 0)
  if (props.reading.variance_type === 'none' || v === 0) {
    return { label: 'No variance', cls: 'bg-zinc-200 text-zinc-800 hover:bg-zinc-200', v }
  }
  if (props.reading.variance_type === 'gain') {
    return { label: `Gain • ${formatLiters(Math.abs(v))}L`, cls: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100', v }
  }
  return { label: `Loss • ${formatLiters(Math.abs(v))}L`, cls: 'bg-red-100 text-red-800 hover:bg-red-100', v }
})

const tankFillPercent = computed(() => {
  const capacity = Number(props.reading.tank?.capacity ?? 0)
  const dip = Number(props.reading.dip_measurement_liters ?? 0)
  if (capacity <= 0) return 0
  return Math.min(100, Math.max(0, Math.round((dip / capacity) * 100)))
})

const confirmOpen = ref(false)
const postOpen = ref(false)
const editOpen = ref(false)

const doConfirm = () => {
  const slug = companySlug.value
  confirmOpen.value = false
  router.post(`/${slug}/fuel/tank-readings/${props.reading.id}/confirm`, {}, { preserveScroll: true })
}

const doPost = () => {
  const slug = companySlug.value
  postOpen.value = false
  router.post(`/${slug}/fuel/tank-readings/${props.reading.id}/post`, {}, { preserveScroll: true })
}

const varianceReasons = computed(() => {
  const values = props.varianceReasons?.length ? props.varianceReasons : []
  return [
    { value: '', label: 'No reason' },
    ...values.map((v) => ({
      value: v,
      label: v.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase()),
    })),
  ]
})

const form = useForm<{
  dip_measurement_liters: number | null
  variance_reason: string
  notes: string
}>({
  dip_measurement_liters: props.reading.dip_measurement_liters ?? null,
  variance_reason: props.reading.variance_reason ?? '',
  notes: props.reading.notes ?? '',
})

const openEdit = () => {
  form.clearErrors()
  form.dip_measurement_liters = props.reading.dip_measurement_liters ?? null
  form.variance_reason = props.reading.variance_reason ?? ''
  form.notes = props.reading.notes ?? ''
  editOpen.value = true
}

const submitEdit = () => {
  const slug = companySlug.value
  form.put(`/${slug}/fuel/tank-readings/${props.reading.id}`, {
    preserveScroll: true,
    onSuccess: () => (editOpen.value = false),
  })
}
</script>

<template>
  <Head title="Tank Reading" />

  <PageShell
    title="Tank Reading"
    description="Review dip vs system, then confirm and post."
    :icon="Warehouse"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <div class="flex flex-wrap items-center gap-2">
        <Button variant="outline" @click="router.get(`/${companySlug}/fuel/tank-readings`)">
          <ArrowLeft class="mr-2 h-4 w-4" />
          Back
        </Button>

        <Button v-if="reading.status === 'draft'" variant="outline" @click="openEdit">
          <FileText class="mr-2 h-4 w-4" />
          Edit draft
        </Button>

        <Button v-if="reading.status === 'draft'" variant="outline" @click="confirmOpen = true">
          <ClipboardCheck class="mr-2 h-4 w-4" />
          Confirm
        </Button>

        <Button v-if="reading.status === 'confirmed'" @click="postOpen = true">
          <CheckCircle2 class="mr-2 h-4 w-4" />
          Post
        </Button>
      </div>
    </template>

    <div class="grid gap-4 lg:grid-cols-3">
      <Card class="border-border/80 lg:col-span-2">
        <CardHeader>
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-4">
              <div class="flex flex-col items-center gap-1">
                <TankLevelGauge :percent="tankFillPercent" :size="52" />
                <span class="text-xs text-text-tertiary">{{ tankFillPercent }}%</span>
              </div>
              <div>
                <CardTitle class="flex items-center gap-2">
                  <Droplet class="h-5 w-5 text-sky-600" />
                  {{ reading.tank?.name ?? 'Tank' }}
                </CardTitle>
                <CardDescription class="mt-1">
                  {{ reading.reading_date }} • {{ reading.reading_type.replace('_', ' ') }}
                </CardDescription>
              </div>
            </div>
            <div class="flex flex-col items-end gap-2">
              <Badge :class="statusClass">{{ reading.status }}</Badge>
              <Badge :class="variance.cls">{{ variance.label }}</Badge>
            </div>
          </div>
        </CardHeader>

        <CardContent class="grid gap-4 sm:grid-cols-3">
          <div class="rounded-xl border border-border/70 bg-gradient-to-br from-sky-500/10 to-indigo-500/5 p-4">
            <p class="text-sm font-medium text-text-tertiary">Dip measurement</p>
            <p class="mt-2 text-2xl font-semibold text-text-primary">{{ formatLiters(reading.dip_measurement_liters) }}L</p>
            <p class="mt-1 text-sm text-text-secondary">Manual dip reading</p>
          </div>

          <div class="rounded-xl border border-border/70 bg-gradient-to-br from-emerald-500/10 to-sky-500/5 p-4">
            <p class="text-sm font-medium text-text-tertiary">System expected</p>
            <p class="mt-2 text-2xl font-semibold text-text-primary">{{ formatLiters(reading.system_calculated_liters) }}L</p>
            <p class="mt-1 text-sm text-text-secondary">Calculated from purchases/sales</p>
          </div>

          <div class="rounded-xl border border-border/70 bg-gradient-to-br from-amber-500/10 to-rose-500/5 p-4">
            <p class="text-sm font-medium text-text-tertiary">Variance</p>
            <p class="mt-2 text-2xl font-semibold text-text-primary">{{ formatLiters(reading.variance_liters) }}L</p>
            <p class="mt-1 text-sm text-text-secondary">
              {{ reading.variance_reason ? `Reason: ${reading.variance_reason}` : 'No reason selected' }}
            </p>
          </div>

          <div class="sm:col-span-3 rounded-xl border border-border/70 bg-muted/30 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-sm font-medium text-text-primary">Journal entry</p>
                <p class="mt-1 text-sm text-text-secondary">
                  {{ reading.journal_entry_id ? `JE: ${reading.journal_entry_id}` : 'No journal entry yet (post to create).' }}
                </p>
              </div>
              <Badge variant="outline" class="border-sky-200 text-sky-700">{{ currencyCode }}</Badge>
            </div>
          </div>

          <div v-if="reading.notes" class="sm:col-span-3 rounded-xl border border-border/70 bg-surface-1 p-4">
            <p class="text-sm font-medium text-text-primary">Notes</p>
            <p class="mt-1 text-sm text-text-secondary whitespace-pre-wrap">{{ reading.notes }}</p>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/80">
        <CardHeader>
          <CardTitle class="text-base">Workflow</CardTitle>
          <CardDescription>Controlled posting prevents “fixing numbers”.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-3">
          <div class="rounded-xl border border-border/70 bg-surface-1 p-4">
            <p class="text-sm font-medium text-text-primary">1) Draft</p>
            <p class="mt-1 text-sm text-text-secondary">Editable. Capture dip and notes.</p>
          </div>
          <div class="rounded-xl border border-border/70 bg-surface-1 p-4">
            <p class="text-sm font-medium text-text-primary">2) Confirm</p>
            <p class="mt-1 text-sm text-text-secondary">Locks the reading for review.</p>
          </div>
          <div class="rounded-xl border border-border/70 bg-surface-1 p-4">
            <p class="text-sm font-medium text-text-primary">3) Post</p>
            <p class="mt-1 text-sm text-text-secondary">Creates the variance journal entry.</p>
          </div>
        </CardContent>
      </Card>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      title="Confirm this reading?"
      description="After confirmation, the reading can’t be edited."
      confirm-text="Confirm"
      variant="success"
      @confirm="doConfirm"
    />

    <ConfirmDialog
      v-model:open="postOpen"
      title="Post variance journal?"
      confirm-text="Post"
      variant="default"
      @confirm="doPost"
    >
      <template #description>
        <p>Posting creates the variance journal entry. This can’t be undone.</p>
        <p class="mt-2 text-sm text-text-secondary">
          Variance: <span class="font-medium text-text-primary">{{ formatLiters(reading.variance_liters) }}L</span>
        </p>
      </template>
    </ConfirmDialog>

    <Dialog :open="editOpen" @update:open="(v) => (editOpen = v)">
      <DialogContent class="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>Edit draft</DialogTitle>
          <DialogDescription>Only draft readings can be edited.</DialogDescription>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitEdit">
          <div class="space-y-2">
            <Label for="dip">Dip measurement (liters)</Label>
            <Input
              id="dip"
              v-model.number="form.dip_measurement_liters"
              type="number"
              min="0"
              step="0.01"
              :class="{ 'border-destructive': form.errors.dip_measurement_liters }"
            />
            <p v-if="form.errors.dip_measurement_liters" class="text-sm text-destructive">
              {{ form.errors.dip_measurement_liters }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="reason">Variance reason</Label>
            <Select v-model="form.variance_reason">
              <SelectTrigger id="reason" :class="{ 'border-destructive': form.errors.variance_reason }">
                <SelectValue placeholder="Select reason..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="r in varianceReasons" :key="r.value" :value="r.value">
                  {{ r.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.variance_reason" class="text-sm text-destructive">{{ form.errors.variance_reason }}</p>
          </div>

          <div class="space-y-2">
            <Label for="notes">Notes</Label>
            <Textarea id="notes" v-model="form.notes" rows="3" :class="{ 'border-destructive': form.errors.notes }" />
            <p v-if="form.errors.notes" class="text-sm text-destructive">{{ form.errors.notes }}</p>
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" :disabled="form.processing" @click="editOpen = false">
              Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
              <span
                v-if="form.processing"
                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
              />
              Save
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </PageShell>
</template>
