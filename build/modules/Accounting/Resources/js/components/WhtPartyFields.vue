<script setup lang="ts">
/**
 * Withholding tax (WHT) fields on a vendor or customer: whether they are a tax filer, and the
 * rate usually withheld. The parent shows this only while the company charges tax.
 */
import InputError from '@/components/InputError.vue'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { pctText, type WhtRateOption } from '@/composables/useWithholding'

defineProps<{
  filerStatus: string
  rateId: string
  rates: WhtRateOption[]
  errors?: { filer_status?: string; default_wht_rate_id?: string }
}>()

const emit = defineEmits<{
  (e: 'update:filerStatus', value: string): void
  (e: 'update:rateId', value: string): void
}>()

const none = '__none'
</script>

<template>
  <div>
    <Label for="filer_status">Tax filer</Label>
    <Select :model-value="filerStatus || 'unknown'" @update:model-value="(v) => emit('update:filerStatus', String(v))">
      <SelectTrigger id="filer_status"><SelectValue /></SelectTrigger>
      <SelectContent>
        <SelectItem value="unknown">Not known</SelectItem>
        <SelectItem value="filer">Filer</SelectItem>
        <SelectItem value="non_filer">Non-filer</SelectItem>
      </SelectContent>
    </Select>
    <InputError :message="errors?.filer_status" />
  </div>
  <div>
    <Label for="default_wht_rate_id">Usual WHT rate</Label>
    <Select :model-value="rateId || none" @update:model-value="(v) => emit('update:rateId', v === none ? '' : String(v))">
      <SelectTrigger id="default_wht_rate_id"><SelectValue placeholder="None" /></SelectTrigger>
      <SelectContent>
        <SelectItem :value="none">None</SelectItem>
        <SelectItem v-for="r in rates" :key="r.id" :value="r.id">
          {{ r.name }}{{ r.section ? ` (${r.section})` : '' }} — {{ pctText(r.rate) }}<template v-if="r.rate_non_filer !== null"> / {{ pctText(r.rate_non_filer) }} non-filer</template>
        </SelectItem>
      </SelectContent>
    </Select>
    <InputError :message="errors?.default_wht_rate_id" />
  </div>
</template>
