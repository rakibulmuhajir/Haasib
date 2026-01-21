<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { useLexicon } from '@/composables/useLexicon'
import { useFormFeedback } from '@/composables/useFormFeedback'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { Plus, Trash2 } from 'lucide-vue-next'

type CompanyRef = {
  id: string
  name: string
  slug: string
  base_currency: string
  ar_account_id: string | null
  bank_account_id: string | null
}

type AccountRef = { id: string; code: string; name: string; subtype?: string }

type LineItem = {
  description: string
  amount: number | null
  income_account_id: string
}

const props = defineProps<{
  company: CompanyRef
  depositAccounts: AccountRef[]
  revenueAccounts: AccountRef[]
}>()

const { t } = useLexicon()
const { showSuccess, showError } = useFormFeedback()

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: '/dashboard' },
  { title: props.company.name, href: `/${props.company.slug}` },
  { title: t('sales'), href: `/${props.company.slug}/sales/create` },
  { title: t('recordSale') },
])

const defaultDeposit = computed(() => {
  return props.company.bank_account_id
    || props.depositAccounts?.[0]?.id
    || ''
})

const lineItemTemplate = (): LineItem => ({
  description: '',
  amount: null,
  income_account_id: '',
})

const form = useForm({
  sale_date: new Date().toISOString().split('T')[0],
  deposit_account_id: defaultDeposit.value,
  line_items: [lineItemTemplate()] as LineItem[],
})

const total = computed(() => {
  return form.line_items.reduce((sum, item) => sum + Number(item.amount ?? 0), 0)
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currencyDisplay: 'narrowSymbol',
    currency: props.company.base_currency || 'USD',
  }).format(amount)
}

const addLine = () => {
  form.line_items.push(lineItemTemplate())
}

const removeLine = (index: number) => {
  if (form.line_items.length <= 1) return
  form.line_items.splice(index, 1)
}

const submit = () => {
  form.post(`/${props.company.slug}/sales`, {
    preserveScroll: true,
    onSuccess: () => showSuccess('Saved'),
    onError: (errors) => showError(errors),
  })
}
</script>

<template>
  <Head :title="t('recordSale')" />

  <PageShell :title="t('recordSale')" :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-4xl space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Details</CardTitle>
        </CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label>Sale date</Label>
            <Input v-model="form.sale_date" type="date" />
            <p v-if="form.errors.sale_date" class="text-sm text-destructive">{{ form.errors.sale_date }}</p>
          </div>

          <div class="space-y-2">
            <Label>Deposit to</Label>
            <Select v-model="form.deposit_account_id">
              <SelectTrigger>
                <SelectValue placeholder="Select account" />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectLabel>Bank / Cash</SelectLabel>
                  <SelectItem v-for="a in depositAccounts" :key="a.id" :value="a.id">
                    {{ a.code }} — {{ a.name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            <p v-if="form.errors.deposit_account_id" class="text-sm text-destructive">{{ form.errors.deposit_account_id }}</p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between">
          <CardTitle>Items</CardTitle>
          <Button type="button" variant="outline" size="sm" @click="addLine">
            <Plus class="mr-2 h-4 w-4" />
            {{ t('addLineItem') }}
          </Button>
        </CardHeader>
        <CardContent class="space-y-4">
          <div v-for="(item, idx) in form.line_items" :key="idx" class="space-y-3 rounded-md border p-4">
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm font-medium text-muted-foreground">Line {{ idx + 1 }}</div>
              <Button type="button" variant="ghost" size="sm" :disabled="form.line_items.length <= 1" @click="removeLine(idx)">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label>{{ t('description') }}</Label>
                <Input v-model="item.description" placeholder="e.g. Sale" />
              </div>
              <div class="space-y-2">
                <Label>{{ t('amount') }}</Label>
                <Input v-model="item.amount" type="number" min="0" step="0.01" />
              </div>
            </div>

            <div class="space-y-2">
              <Label>{{ t('incomeAccount') }}</Label>
              <Select v-model="item.income_account_id">
                <SelectTrigger>
                  <SelectValue :placeholder="t('useCompanyDefault')" />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectLabel>Revenue accounts</SelectLabel>
                    <SelectItem value="">{{ t('useCompanyDefault') }}</SelectItem>
                    <SelectItem v-for="a in revenueAccounts" :key="a.id" :value="a.id">
                      {{ a.code }} — {{ a.name }}
                    </SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
            </div>
          </div>

          <Separator />

          <div class="flex items-center justify-between">
            <div class="text-sm text-muted-foreground">Total</div>
            <div class="text-lg font-semibold tabular-nums">{{ formatCurrency(total) }}</div>
          </div>

          <div class="flex justify-end">
            <Button :disabled="form.processing" @click="submit">
              {{ t('save') }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>

