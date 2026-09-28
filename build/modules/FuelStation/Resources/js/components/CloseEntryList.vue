<script setup lang="ts">
/**
 * One row layout for every simple Daily Close entry (deposits, withdrawals, advances, expenses,
 * card slips...): who / what · optional second choice · detail · amount · remove. Same heights
 * and column order in every section, so the form reads the same wherever you are.
 */
import { computed } from 'vue'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Trash2 } from 'lucide-vue-next'

interface Option { id: string; name: string }
interface Choice {
  key: string
  label: string
  options: Option[]
  nameKey?: string
  placeholder?: string
  disabled?: (row: any) => boolean
}

const rows = defineModel<any[]>({ required: true })
const props = defineProps<{
  party?: Choice
  extra?: Choice
  text?: { key: string; label: string; placeholder?: string }
  hint?: (row: any) => string | null
  locked?: (row: any) => string | null
  onParty?: (row: any) => void
  errorsPrefix: string
  errors?: Record<string, string>
  disabled?: boolean
}>()

const columns = computed(() => [
  props.party ? 'minmax(0,1.3fr)' : null,
  props.extra ? 'minmax(0,1fr)' : null,
  props.text ? 'minmax(0,1.3fr)' : null,
  '9rem',
  '2.25rem',
].filter(Boolean).join(' '))

const err = (index: number, field: string) => props.errors?.[`${props.errorsPrefix}.${index}.${field}`]
const setParty = (row: any, value: unknown) => {
  row[props.party!.key] = value
  if (props.party!.nameKey) row[props.party!.nameKey] = props.party!.options.find((o) => o.id === value)?.name ?? ''
  props.onParty?.(row)
}
</script>

<template>
  <div class="space-y-2">
    <div v-for="(row, index) in rows" :key="index">
      <p v-if="locked?.(row)" class="text-sm">
        <span class="font-medium">{{ locked(row) }}</span>
      </p>
      <div v-else class="grid items-start gap-2 md:[grid-template-columns:var(--cols)]" :style="{ '--cols': columns }">
        <div v-if="party">
          <Select :model-value="row[party.key]" :disabled="disabled" @update:model-value="(v) => setParty(row, v)">
            <SelectTrigger class="h-9" :aria-label="party.label"><SelectValue :placeholder="party.placeholder ?? party.label" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="o in party.options" :key="o.id" :value="o.id">{{ o.name }}</SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="err(index, party.key)" />
          <p v-if="hint?.(row)" class="mt-0.5 text-xs text-muted-foreground">{{ hint(row) }}</p>
        </div>
        <div v-if="extra">
          <Select v-model="row[extra.key]" :disabled="disabled || extra.disabled?.(row)">
            <SelectTrigger class="h-9" :aria-label="extra.label"><SelectValue :placeholder="extra.placeholder ?? extra.label" /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="o in extra.options" :key="o.id" :value="o.id">{{ o.name }}</SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="err(index, extra.key)" />
        </div>
        <div v-if="text">
          <Input v-model="row[text.key]" class="h-9" :placeholder="text.placeholder ?? text.label" :aria-label="text.label" :disabled="disabled" maxlength="255" />
          <InputError :message="err(index, text.key)" />
        </div>
        <div>
          <Input v-model.number="row.amount" class="h-9 text-right" type="number" min="0" step="0.01" aria-label="Amount" :disabled="disabled" />
          <InputError :message="err(index, 'amount')" />
        </div>
        <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Remove" :disabled="disabled" @click="rows.splice(index, 1)">
          <Trash2 class="h-4 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>
