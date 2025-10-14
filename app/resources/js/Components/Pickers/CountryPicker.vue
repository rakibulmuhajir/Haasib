<script setup>
import { ref } from 'vue'
import AutoComplete from 'primevue/autocomplete'
import { http } from '@/lib/http'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search country…' },
})
const emit = defineEmits(['update:modelValue'])

const selectedCountry = ref(null)
const suggestions = ref([])
const loading = ref(false)

async function search(event) {
  loading.value = true
  try {
    const { data } = await http.get('/web/countries/suggest', { 
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
    v-model="selectedCountry"
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
          {{ slotProps.option.name }} ({{ slotProps.option.code }})
        </div>
        <div class="text-xs text-gray-500">
          {{ slotProps.option.emoji }} · {{ slotProps.option.region }}
        </div>
      </div>
    </template>
  </AutoComplete>
</template>