<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Plus, Save, Trash2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

type EnabledCurrency = { id: string; currency_code: string; is_base: boolean; exchange_rate: string | number }
type Currency = { code: string; name: string; symbol: string }

const props = defineProps<{
  company: { slug: string; base_currency: string }
  enabled: EnabledCurrency[]
  available: Currency[]
  canManage: boolean
}>()

const rates = reactive<Record<string, string>>({})
const syncRates = () => props.enabled.forEach((currency) => { rates[currency.id] = String(currency.exchange_rate || 1) })
syncRates()
watch(() => props.enabled, syncRates, { deep: true })

const enabledCodes = computed(() => new Set(props.enabled.map((currency) => currency.currency_code)))
const currencyOptions = computed(() => props.available.filter((currency) => !enabledCodes.value.has(currency.code)))
const addForm = useForm({ currency_code: '', exchange_rate: '' })
const rateForm = useForm({ exchange_rate: '' })
const rateTargetId = ref<string | null>(null)
const removeForm = useForm({})
const removeTarget = ref<EnabledCurrency | null>(null)
const removeDialogOpen = ref(false)

const addCurrency = () => addForm.post(`/${props.company.slug}/settings/currencies`, {
  preserveScroll: true,
  onSuccess: () => { addForm.reset(); toast.success('Currency enabled successfully') },
  onError: () => toast.error('Failed to enable currency'),
})
const saveRate = (currency: EnabledCurrency) => {
  rateTargetId.value = currency.id
  rateForm.exchange_rate = rates[currency.id]
  rateForm.patch(`/${props.company.slug}/settings/currencies/${currency.id}`, {
    preserveScroll: true,
    onSuccess: () => toast.success(`${currency.currency_code} rate updated`),
    onError: () => toast.error('Failed to update exchange rate'),
  })
}
const removeCurrency = () => {
  if (!removeTarget.value) return
  removeForm.delete(`/${props.company.slug}/settings/currencies/${removeTarget.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { removeTarget.value = null; removeDialogOpen.value = false; toast.success('Currency disabled successfully') },
    onError: () => toast.error('Currency cannot be disabled'),
  })
}
</script>

<template>
  <div class="space-y-4">
    <div class="divide-y rounded-md border">
      <div v-for="currency in enabled" :key="currency.id" class="grid gap-3 p-3 md:grid-cols-[120px_1fr_auto] md:items-end">
        <div>
          <div class="font-medium">{{ currency.currency_code }}</div>
          <Badge v-if="currency.is_base" variant="secondary">Base</Badge>
        </div>
        <div class="space-y-1">
          <Label>{{ currency.is_base ? 'Base rate' : `1 ${currency.currency_code} in ${company.base_currency}` }}</Label>
          <Input v-model="rates[currency.id]" type="number" min="0.00000001" step="0.00000001" :disabled="currency.is_base || !canManage" />
          <p v-if="rateTargetId === currency.id && rateForm.errors.exchange_rate" class="text-xs text-destructive">{{ rateForm.errors.exchange_rate }}</p>
        </div>
        <div v-if="!currency.is_base && canManage" class="flex gap-2">
          <Button type="button" variant="outline" size="icon" title="Save exchange rate" :disabled="rateForm.processing" @click="saveRate(currency)"><Save class="h-4 w-4" /></Button>
          <Button type="button" variant="ghost" size="icon" title="Disable currency" :disabled="removeForm.processing" @click="removeTarget = currency; removeDialogOpen = true"><Trash2 class="h-4 w-4" /></Button>
        </div>
      </div>
    </div>

    <form v-if="canManage && currencyOptions.length" class="grid gap-3 rounded-md border p-3 md:grid-cols-[minmax(180px,1fr)_minmax(180px,1fr)_auto] md:items-end" @submit.prevent="addCurrency">
      <div class="space-y-1">
        <Label>Secondary Currency</Label>
        <Select v-model="addForm.currency_code"><SelectTrigger><SelectValue placeholder="Select currency" /></SelectTrigger><SelectContent><SelectItem v-for="currency in currencyOptions" :key="currency.code" :value="currency.code">{{ currency.code }} · {{ currency.name }}</SelectItem></SelectContent></Select>
        <p v-if="addForm.errors.currency_code" class="text-xs text-destructive">{{ addForm.errors.currency_code }}</p>
      </div>
      <div class="space-y-1">
        <Label>Exchange Rate</Label>
        <Input v-model="addForm.exchange_rate" type="number" min="0.00000001" step="0.00000001" :placeholder="`1 secondary = X ${company.base_currency}`" />
        <p v-if="addForm.errors.exchange_rate" class="text-xs text-destructive">{{ addForm.errors.exchange_rate }}</p>
      </div>
      <Button type="submit" :disabled="addForm.processing || !addForm.currency_code || !addForm.exchange_rate"><Plus class="mr-2 h-4 w-4" />Enable</Button>
    </form>

    <ConfirmDialog v-model:open="removeDialogOpen" title="Disable Currency" :description="`Disable ${removeTarget?.currency_code || ''}?`" confirm-text="Disable" variant="destructive" :loading="removeForm.processing" @confirm="removeCurrency" />
  </div>
</template>
