<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Check, ChevronsUpDown } from 'lucide-vue-next'

type Option = { value: string; label: string }
const props = withDefaults(defineProps<{ modelValue: string; options: Option[]; placeholder?: string; searchPlaceholder?: string; showValue?: boolean; openOnFocus?: boolean }>(), {
  showValue: true,
  openOnFocus: false,
})
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const open = ref(false)
const search = ref('')
const selected = computed(() => props.options.find((option) => option.value === props.modelValue))
const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  return term ? props.options.filter((option) => `${option.value} ${option.label}`.toLowerCase().includes(term)) : props.options
})
watch(open, (value) => { if (!value) search.value = '' })
const choose = (value: string) => { emit('update:modelValue', value); open.value = false }
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <Button type="button" variant="outline" role="combobox" :aria-expanded="open" class="w-full justify-between px-3 font-normal" @focus="openOnFocus && (open = true)">
        <span class="truncate">{{ selected ? (showValue ? `${selected.value} · ${selected.label}` : selected.label) : (placeholder || 'Select') }}</span>
        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-[320px] p-2" align="start">
      <Input v-model="search" :placeholder="searchPlaceholder || 'Type to search...'" autofocus />
      <div class="mt-2 max-h-64 overflow-y-auto">
        <div v-if="!filtered.length" class="p-3 text-sm text-muted-foreground">No matching option.</div>
        <Button v-for="option in filtered" :key="option.value" type="button" variant="ghost" class="h-auto w-full justify-start px-2 py-2 text-left" @click="choose(option.value)">
          <Check class="mr-2 h-4 w-4" :class="modelValue === option.value ? 'opacity-100' : 'opacity-0'" />
          <span v-if="showValue"><span class="font-medium">{{ option.value }}</span> · {{ option.label }}</span>
          <span v-else class="font-medium">{{ option.label }}</span>
        </Button>
      </div>
    </PopoverContent>
  </Popover>
</template>
