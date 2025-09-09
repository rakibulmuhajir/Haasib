<script setup>
import { ref } from 'vue'
import AutoComplete from 'primevue/autocomplete'
import { http } from '@/lib/http'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search currency…' },
})
const emit = defineEmits(['update:modelValue'])

const selectedCurrency = ref(null)
const suggestions = ref([])
const loading = ref(false)

async function search(event) {
  loading.value = true
  try {
    const { data } = await http.get('/web/currencies/suggest', { 
      params: { q: event.query, limit: 8 } 
    })
    suggestions.value = data.data || []
  } catch (e) {
    suggestions.value = []
  } finally {
    loading.value = false
  }
}

function onSelect(event) {
  emit('update:modelValue', event.value.code)
}
</script>

<template>
  <AutoComplete
    v-model="selectedCurrency"
    :suggestions="suggestions"
    :loading="loading"
    :placeholder="placeholder"
    optionLabel="code"
    @complete="search"
    @item-select="onSelect"
    class="w-full"
  >
    <template #option="slotProps">
      <div class="flex flex-col">
        <div class="text-sm font-medium text-gray-900">
          {{ slotProps.option.code }} — {{ slotProps.option.name }}
        </div>
        <div class="text-xs text-gray-500">
          {{ slotProps.option.symbol }} · {{ slotProps.option.numeric_code }}
        </div>
      </div>
    </template>
  </AutoComplete>
</template>
