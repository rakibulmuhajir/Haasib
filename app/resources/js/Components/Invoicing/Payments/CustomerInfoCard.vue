<template>
  <div>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
      Customer *
    </label>
    <CustomerPicker
      v-model="selectedCustomerId"
      :customers="customers"
      :error="errors.customer_id"
      placeholder="Search and select customer..."
      @change="onCustomerChange"
    />
    <small v-if="errors.customer_id" class="text-red-600 dark:text-red-400">
      {{ errors.customer_id }}
    </small>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CustomerPicker from '@/Components/UI/Forms/CustomerPicker.vue'

interface Props {
  modelValue?: number | string | null
  customers: Array<any>
  errors?: Record<string, string>
}

interface Emits {
  (e: 'update:modelValue', value: number | string | null): void
  (e: 'customer-change', customer: any): void
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  errors: () => ({})
})

const emit = defineEmits<Emits>()

const selectedCustomerId = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const onCustomerChange = (customer: any) => {
  emit('customer-change', customer)
}
</script>