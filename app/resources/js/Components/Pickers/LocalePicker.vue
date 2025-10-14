<script setup>
import { ref, watch } from 'vue'
import AutoComplete from 'primevue/autocomplete'
import { http } from '@/lib/http'

const props = defineProps({
  modelValue: { type: String, default: '' },
  language: { type: String, default: '' },
  placeholder: { type: String, default: 'Search locale…' },
})
const emit = defineEmits(['update:modelValue'])

const selectedLocale = ref(null)
const suggestions = ref([])
const loading = ref(false)

async function search(event) {
  if (!event.query || event.query.length < 1) {
    suggestions.value = []
    return
  }
  
  loading.value = true
  try {
    const params = { q: event.query, limit: 8 }
    if (props.language) params.language = props.language
    
    const { data } = await http.get('/web/locales/suggest', { params })
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
    v-model="selectedLocale"
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
          {{ slotProps.option.code }}
        </div>
      </div>
    </template>
  </AutoComplete>
</template>