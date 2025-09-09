<script setup>
import { ref } from 'vue'
import AutoComplete from 'primevue/autocomplete'
import { http } from '@/lib/http'

const props = defineProps({
  modelValue: { type: String, default: '' }, // emits slug (or id)
  placeholder: { type: String, default: 'Search companies…' },
})
const emit = defineEmits(['update:modelValue'])

const selectedCompany = ref(null)
const suggestions = ref([])
const loading = ref(false)

async function search(event) {
  if (!event.query || event.query.length < 2) {
    suggestions.value = []
    return
  }
  
  loading.value = true
  try {
    const { data } = await http.get('/web/companies', { 
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
  emit('update:modelValue', event.value.slug || event.value.id)
}
</script>

<template>
  <AutoComplete
    v-model="selectedCompany"
    :suggestions="suggestions"
    :loading="loading"
    :placeholder="placeholder"
    optionLabel="name"
    @complete="search"
    @item-select="onSelect"
    class="w-full"
  >
    <template #option="slotProps">
      <div class="flex flex-col">
        <div class="text-sm font-medium text-gray-900">
          {{ slotProps.option.name }}
        </div>
        <div class="text-xs text-gray-500">
          {{ slotProps.option.slug }} · {{ slotProps.option.base_currency }} · {{ slotProps.option.language }}
        </div>
      </div>
    </template>
  </AutoComplete>
</template>

