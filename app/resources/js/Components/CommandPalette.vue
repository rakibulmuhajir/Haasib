<!-- resources/js/Components/CommandPalette.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { Dialog, Combobox, ComboboxInput, ComboboxOptions, ComboboxOption } from '@headlessui/vue'
import Fuse from 'fuse.js'
import axios from 'axios'
import { registry } from '@/palette/registry'
import { parse } from '@/palette/parser'

const open = ref(false)
const size = ref<'half' | 'full'>('half')
const query = ref('')
const selected = ref<any|null>(null)
const fuse = new Fuse(registry, { keys: ['label','aliases'] })
const results = computed(() => query.value ? fuse.search(query.value).map(r => r.item) : registry)
const extra = ref<Record<string,string>>({})
const missing = ref<string[]>([])

function submit() {
  const text = selected.value ? selected.value.label : query.value
  const { action, params } = parse(text)
  Object.assign(params, extra.value)
  missing.value = (registry.find(r => r.id === action)?.needs || [])
    .filter(n => !params[n.replace('?','')] && !n.endsWith('?'))
  if (missing.value.length) return
  axios.post('/commands', params, {
    headers: {
      'X-Action': action,
      'X-Idempotency-Key': crypto.randomUUID(),
    }
  })
  query.value = ''
  selected.value = null
  extra.value = {}
  missing.value = []
  open.value = false
}
</script>

<template>
  <div class="fixed bottom-0 inset-x-0 p-2 flex justify-center space-x-2">
    <button @click="open=true; size='half'" class="px-2 py-1 bg-gray-200 rounded">half</button>
    <button @click="open=false" class="px-2 py-1 bg-gray-200 rounded">hide</button>
    <button @click="open=true; size='full'" class="px-2 py-1 bg-gray-200 rounded">full</button>
  </div>
  <Dialog v-if="open" @close="open=false" :class="size==='full'?'fixed inset-0':'fixed inset-x-0 bottom-0 h-1/2'" class="bg-white shadow-lg">
    <div class="p-4 flex">
      <div class="flex-1">
        <Combobox v-model="selected" @change="submit">
          <ComboboxInput v-model="query" class="w-full border p-2" placeholder="Run command..." />
          <ComboboxOptions>
            <ComboboxOption v-for="item in results" :key="item.id" :value="item" class="px-2 py-1 hover:bg-gray-100">{{ item.label }}</ComboboxOption>
          </ComboboxOptions>
        </Combobox>
      </div>
      <div v-if="missing.length" class="w-64 ps-4 space-y-2">
        <div v-for="m in missing" :key="m">
          <input v-model="extra[m]" :placeholder="m" class="w-full border p-1" />
        </div>
        <button @click="submit" class="mt-2 px-2 py-1 bg-blue-500 text-white rounded">Run</button>
      </div>
    </div>
  </Dialog>
</template>
