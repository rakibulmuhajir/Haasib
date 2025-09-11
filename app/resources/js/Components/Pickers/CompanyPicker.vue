<script setup>
import { ref } from 'vue'
import AutoComplete from 'primevue/autocomplete'
import { http } from '@/lib/http'

const props = defineProps({
  modelValue: { type: [String, Object], default: '' }, // can be slug/id or full company object
  placeholder: { type: String, default: 'Search companies…' },
  excludeUserId: { type: String, default: '' }, // user ID to exclude companies for
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
    const params = { q: event.query, limit: 8 }
    
    // If excludeUserId is provided, filter out companies this user is already assigned to
    if (props.excludeUserId) {
      params.user_id = props.excludeUserId
    }
    
    const { data } = await http.get('/web/companies', { params })
    suggestions.value = data.data || []
  } catch (e) {
    suggestions.value = []
  } finally {
    loading.value = false
  }
}

function onSelect(event) {
  emit('update:modelValue', event.value) // emit full company object
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

