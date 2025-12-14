<script setup lang="ts">
/**
 * TaxToggle - Tax Application Checkbox
 *
 * A simple checkbox that toggles tax application.
 * Shows the default tax profile from customer/vendor or company settings.
 *
 * @see docs/plans/invoice-bill-components-spec.md
 */
import { ref, computed, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useLexicon } from '@/composables/useLexicon'
import { cn } from '@/lib/utils'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Loader2, Percent } from 'lucide-vue-next'

// Types
export interface TaxCode {
  id: string
  name: string
  code: string
  rate: number
  is_compound?: boolean
}

// Props
export interface TaxToggleProps {
  modelValue: boolean
  entityId?: string | null           // Customer/Vendor ID to get default tax
  entityType: 'customer' | 'vendor'
  label?: string                     // Override label
  inclusive?: boolean                // For bills: tax inclusive mode
  disabled?: boolean
  class?: string
}

const props = withDefaults(defineProps<TaxToggleProps>(), {
  inclusive: false,
  disabled: false,
})

// Emits
const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'tax-code-resolved': [taxCode: TaxCode | null]
}>()

// Composables
const { t } = useLexicon()
const page = usePage()

// Company context
const company = computed(() => (page.props.auth as any)?.currentCompany)

// State
const id = `tax-toggle-${Math.random().toString(36).slice(2, 9)}`
const defaultTaxCode = ref<TaxCode | null>(null)
const isLoadingTax = ref(false)

// Computed
const checkboxLabel = computed(() => {
  if (props.label) return props.label

  if (props.entityType === 'vendor') {
    return props.inclusive ? t('includesTax') : t('taxDeductible')
  }

  return t('addTax')
})

const taxInfo = computed(() => {
  if (!defaultTaxCode.value) return null
  const rate = defaultTaxCode.value.rate
  const name = defaultTaxCode.value.name || defaultTaxCode.value.code
  return `${name} (${rate}%)`
})

// Fetch entity's default tax code
const fetchEntityTaxCode = async () => {
  if (!props.entityId || !company.value) {
    defaultTaxCode.value = null
    return
  }

  isLoadingTax.value = true

  try {
    const endpoint = `/${company.value.slug}/${props.entityType}s/${props.entityId}/tax-default`
    const response = await fetch(endpoint, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (response.ok) {
      const data = await response.json()
      defaultTaxCode.value = data.tax_code || null
      emit('tax-code-resolved', defaultTaxCode.value)
    } else {
      // Fallback to company default
      await fetchCompanyDefaultTax()
    }
  } catch (error) {
    console.error('[TaxToggle] Failed to fetch entity tax code:', error)
    // Fallback to company default
    await fetchCompanyDefaultTax()
  } finally {
    isLoadingTax.value = false
  }
}

// Fetch company's default tax code
const fetchCompanyDefaultTax = async () => {
  if (!company.value) return

  try {
    const endpoint = `/${company.value.slug}/settings/tax-default`
    const response = await fetch(endpoint, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (response.ok) {
      const data = await response.json()
      defaultTaxCode.value = data.tax_code || null
      emit('tax-code-resolved', defaultTaxCode.value)
    }
  } catch (error) {
    console.error('[TaxToggle] Failed to fetch company tax default:', error)
  }
}

// Handle checkbox change
const handleChange = (checked: boolean | 'indeterminate') => {
  const newValue = checked === true
  emit('update:modelValue', newValue)
}

// Watch entity ID changes
watch(() => props.entityId, (newId) => {
  if (newId) {
    fetchEntityTaxCode()
  } else {
    // No entity, use company default
    fetchCompanyDefaultTax()
  }
}, { immediate: true })

// Initialize
onMounted(() => {
  if (!props.entityId) {
    fetchCompanyDefaultTax()
  }
})
</script>

<template>
  <div
    :class="cn(
      'tax-toggle flex items-center gap-2 rounded-lg border border-border/70 bg-muted/20 px-3 py-2 transition-colors hover:bg-muted/30',
      props.class
    )"
  >
    <Checkbox
      :id="id"
      :checked="modelValue"
      @update:checked="handleChange"
      :disabled="disabled"
    />

    <div class="flex items-center gap-2 flex-1 min-w-0">
      <Label
        :for="id"
        class="text-sm font-medium cursor-pointer select-none"
        :class="{ 'opacity-50': disabled }"
      >
        {{ checkboxLabel }}
      </Label>

      <!-- Loading indicator -->
      <Loader2
        v-if="isLoadingTax"
        class="h-3.5 w-3.5 animate-spin text-muted-foreground"
      />

      <!-- Tax info badge -->
      <span
        v-else-if="modelValue && taxInfo"
        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs bg-muted text-muted-foreground"
      >
        <Percent class="h-3 w-3" />
        {{ taxInfo }}
      </span>

      <!-- No tax configured warning -->
  <span
        v-else-if="modelValue && !taxInfo && !isLoadingTax"
        class="text-xs text-amber-600"
      >
        {{ t('noTaxProfileConfigured') }}
      </span>
    </div>
  </div>
</template>
