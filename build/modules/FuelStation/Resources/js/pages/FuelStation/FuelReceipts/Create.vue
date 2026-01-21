<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Separator } from '@/components/ui/separator'
import type { BreadcrumbItem } from '@/types'
import { Droplets, Save, ArrowLeft, Plus, Trash2 } from 'lucide-vue-next'
import { currencySymbol } from '@/lib/utils'

interface Tank {
  id: string
  name: string
  capacity: number | null
  current_stock: number
  fuel_name: string | null
  fuel_category: string | null
  item_id: string | null
}

interface Vendor {
  id: string
  name: string
  code: string | null
}

const props = defineProps<{
  tanks: Tank[]
  vendors: Vendor[]
  currency: string
  today: string
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Fuel Receipts', href: `/${companySlug.value}/fuel/receipts` },
  { title: 'New Receipt', href: `/${companySlug.value}/fuel/receipts/create` },
])

const currency = computed(() => currencySymbol(props.currency))

interface ReceiptLine {
  tank_id: string
  liters: number | null
  rate: number | null
}

const form = useForm({
  receipt_date: props.today,
  vendor_id: '',
  invoice_number: '',
  lines: [{ tank_id: '', liters: null, rate: null }] as ReceiptLine[],
  notes: '',
})

const addLine = () => {
  form.lines.push({ tank_id: '', liters: null, rate: null })
}

const removeLine = (index: number) => {
  if (form.lines.length > 1) {
    form.lines.splice(index, 1)
  }
}

const getTankInfo = (tankId: string) => {
  return props.tanks.find((t) => t.id === tankId)
}

const lineTotal = (line: ReceiptLine) => {
  if (!line.liters || !line.rate) return 0
  return line.liters * line.rate
}

const grandTotal = computed(() => {
  return form.lines.reduce((sum, line) => sum + lineTotal(line), 0)
})

const totalLiters = computed(() => {
  return form.lines.reduce((sum, line) => sum + (line.liters || 0), 0)
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount)
}

const submit = () => {
  form.post(`/${companySlug.value}/fuel/receipts`, {
    preserveScroll: true,
  })
}

const goBack = () => {
  router.get(`/${companySlug.value}/fuel/receipts`)
}
</script>

<template>
  <Head title="Record Fuel Receipt" />

  <PageShell
    title="Record Fuel Receipt"
    description="Record a fuel delivery from a tanker. This will increase tank inventory."
    :icon="Droplets"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="goBack">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6">
      <!-- Header Info -->
      <Card>
        <CardHeader>
          <CardTitle>Receipt Details</CardTitle>
          <CardDescription>Basic information about the fuel delivery.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-2">
              <Label for="receipt_date">Receipt Date <span class="text-destructive">*</span></Label>
              <Input
                id="receipt_date"
                v-model="form.receipt_date"
                type="date"
                :class="{ 'border-destructive': form.errors.receipt_date }"
              />
              <p v-if="form.errors.receipt_date" class="text-sm text-destructive">{{ form.errors.receipt_date }}</p>
            </div>

            <div class="space-y-2">
              <Label for="vendor_id">Vendor / Supplier <span class="text-destructive">*</span></Label>
              <Select v-model="form.vendor_id">
                <SelectTrigger :class="{ 'border-destructive': form.errors.vendor_id }">
                  <SelectValue placeholder="Select vendor" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="v in vendors" :key="v.id" :value="v.id">
                    {{ v.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.vendor_id" class="text-sm text-destructive">{{ form.errors.vendor_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="invoice_number">Invoice / Delivery #</Label>
              <Input
                id="invoice_number"
                v-model="form.invoice_number"
                placeholder="e.g., INV-12345"
                :class="{ 'border-destructive': form.errors.invoice_number }"
              />
              <p v-if="form.errors.invoice_number" class="text-sm text-destructive">{{ form.errors.invoice_number }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Receipt Lines -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle>Fuel Lines</CardTitle>
              <CardDescription>Add fuel received for each tank.</CardDescription>
            </div>
            <Button type="button" variant="outline" size="sm" @click="addLine">
              <Plus class="mr-2 h-4 w-4" />
              Add Line
            </Button>
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-for="(line, index) in form.lines" :key="index" class="rounded-lg border p-4 space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-muted-foreground">Line {{ index + 1 }}</span>
              <Button
                v-if="form.lines.length > 1"
                type="button"
                variant="ghost"
                size="icon"
                @click="removeLine(index)"
              >
                <Trash2 class="h-4 w-4 text-destructive" />
              </Button>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
              <div class="space-y-2">
                <Label>Tank <span class="text-destructive">*</span></Label>
                <Select v-model="line.tank_id">
                  <SelectTrigger>
                    <SelectValue placeholder="Select tank" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="tank in tanks" :key="tank.id" :value="tank.id">
                      {{ tank.name }} ({{ tank.fuel_name || tank.fuel_category }})
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="line.tank_id" class="text-xs text-muted-foreground">
                  Current: {{ formatCurrency(getTankInfo(line.tank_id)?.current_stock || 0) }} L
                  <span v-if="getTankInfo(line.tank_id)?.capacity">
                    / {{ formatCurrency(getTankInfo(line.tank_id)?.capacity || 0) }} L
                  </span>
                </p>
              </div>

              <div class="space-y-2">
                <Label>Liters <span class="text-destructive">*</span></Label>
                <Input
                  v-model.number="line.liters"
                  type="number"
                  min="0.01"
                  step="0.01"
                  placeholder="0"
                />
              </div>

              <div class="space-y-2">
                <Label>Rate / Liter <span class="text-destructive">*</span></Label>
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">{{ currency }}</span>
                  <Input
                    v-model.number="line.rate"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0"
                    class="pl-14"
                  />
                </div>
              </div>

              <div class="space-y-2">
                <Label>Line Total</Label>
                <div class="h-10 px-3 py-2 rounded-md border bg-muted/50 flex items-center font-medium">
                  {{ currency }} {{ formatCurrency(lineTotal(line)) }}
                </div>
              </div>
            </div>
          </div>

          <p v-if="form.errors.lines" class="text-sm text-destructive">{{ form.errors.lines }}</p>

          <Separator />

          <!-- Totals -->
          <div class="flex justify-end">
            <div class="w-64 space-y-2">
              <div class="flex justify-between text-sm">
                <span>Total Liters</span>
                <span class="font-medium">{{ formatCurrency(totalLiters) }} L</span>
              </div>
              <div class="flex justify-between text-base font-semibold">
                <span>Grand Total</span>
                <span>{{ currency }} {{ formatCurrency(grandTotal) }}</span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Notes -->
      <Card>
        <CardHeader>
          <CardTitle>Notes</CardTitle>
        </CardHeader>
        <CardContent>
          <Textarea
            v-model="form.notes"
            placeholder="Any additional notes about this delivery..."
            rows="3"
          />
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-4">
        <Button type="button" variant="outline" @click="goBack" :disabled="form.processing">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <span
            v-if="form.processing"
            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
          />
          <Save v-else class="mr-2 h-4 w-4" />
          Record Receipt
        </Button>
      </div>
    </form>
  </PageShell>
</template>
