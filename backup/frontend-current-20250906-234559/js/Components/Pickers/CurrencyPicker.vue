<script setup>
import { ref, watch } from 'vue'
import { http } from '@/lib/http'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search currency…' },
})
const emit = defineEmits(['update:modelValue'])

const q = ref('')
const open = ref(false)
const items = ref([])
const loading = ref(false)
const error = ref('')

async function search() {
  if (!q.value || q.value.length < 1) { items.value = []; return }
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get('/web/currencies/suggest', { params: { q: q.value, limit: 8 } })
    items.value = data.data || []
    open.value = true
  } catch (e) {
    error.value = 'Failed to load currencies'
  } finally {
    loading.value = false
  }
}

let timer
watch(q, () => { clearTimeout(timer); timer = setTimeout(search, 150) })

function select(it) {
  emit('update:modelValue', it.code)
  q.value = `${it.code} — ${it.name}`
  open.value = false
}
</script>

<template>
  <div class="relative">
    <input
      :placeholder="placeholder"
      v-model="q"
      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
      @focus="search"
      @keydown.escape.prevent="open = false"
    />
    <div v-if="open" class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow">
      <div v-if="loading" class="px-3 py-2 text-sm text-gray-500">Loading…</div>
      <div v-else-if="error" class="px-3 py-2 text-sm text-red-600">{{ error }}</div>
      <template v-else>
        <button
          v-for="it in items"
          :key="it.code"
          type="button"
          class="w-full px-3 py-2 text-left hover:bg-gray-50"
          @click="select(it)"
        >
          <div class="text-sm font-medium text-gray-900">{{ it.code }} — {{ it.name }}</div>
          <div class="text-xs text-gray-500">{{ it.symbol }} · {{ it.numeric_code }}</div>
        </button>
        <div v-if="items.length === 0" class="px-3 py-2 text-sm text-gray-500">No results</div>
      </template>
    </div>
  </div>
</template>
