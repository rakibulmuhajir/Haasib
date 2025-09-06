<!-- resources/js/Pages/DevCli.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const command = ref('')
const busy = ref(false)
const output = ref([])
const cursor = ref(-1) // suggestion cursor

// Command grammar
const ENTITIES = [
  { key: 'user',    aliases: ['u'] },
  { key: 'company', aliases: ['c', 'co'] },
  { key: 'bootstrap', aliases: [] }, // special
]
const ACTIONS = [
  { key: 'add',      aliases: [] },
  { key: 'assign',   aliases: ['ass'] },
  { key: 'unassign', aliases: ['unass'] },
  { key: 'delete',   aliases: ['del','rm'] },
]

// Tokenize by space, keep flags as-is
function tokens(str){ return str.trim().split(/\s+/).filter(Boolean) }
function resolveEntity(tok){
  const t = tok?.toLowerCase() || ''
  return ENTITIES.find(e => e.key.startsWith(t) || e.aliases.some(a => a.startsWith(t)))
}
function resolveAction(tok){
  const t = tok?.toLowerCase() || ''
  return ACTIONS.find(a => a.key.startsWith(t) || a.aliases.some(x => x.startsWith(t)))
}
function suggestions(){
  const [t1, t2] = tokens(command.value)
  if (!t1) { // suggest entities
    return ENTITIES.map(e => e.key)
  }
  const ent = resolveEntity(t1)
  if (!ent) {
    return ENTITIES.map(e => e.key).filter(k => k.startsWith(t1.toLowerCase()))
  }
  if (!t2 && ent.key !== 'bootstrap') { // suggest actions
    return ACTIONS.map(a => a.key)
  }
  if (ent.key === 'bootstrap') { // bootstrap has no action
    return []
  }
  // otherwise suggest nothing; user types flags
  return []
}
const sugg = computed(suggestions)

function onKeydown(e){
  if (sugg.value.length === 0) return
  if (e.key === 'Tab') {
    e.preventDefault()
    applySuggestion()
  } else if (e.key === 'ArrowDown') {
    e.preventDefault()
    cursor.value = (cursor.value + 1) % sugg.value.length
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    cursor.value = (cursor.value - 1 + sugg.value.length) % sugg.value.length
  }
}
function applySuggestion(){
  const s = sugg.value[cursor.value >=0 ? cursor.value : 0]
  if (!s) return
  const parts = tokens(command.value)
  if (parts.length === 0) command.value = s + ' '
  else if (parts.length === 1) command.value = s + ' '
  else if (parts.length >= 2) command.value = parts[0] + ' ' + s + ' ' + parts.slice(2).join(' ')
  cursor.value = -1
}

async function run(){
  if (!command.value.trim()) return
  busy.value = true
  const cmd = command.value.trim()
  output.value.unshift({ at: new Date().toLocaleTimeString(), cmd, res: '…' })
  try {
    const { data } = await axios.post('/dev/cli/execute', { command: cmd })
    output.value[0].res = JSON.stringify(data, null, 2)
  } catch (e) {
    output.value[0].res = JSON.stringify(e?.response?.data || { ok:false, error:e.message }, null, 2)
  } finally {
    busy.value = false
    command.value = ''
    cursor.value = -1
  }
}
</script>

<template>
  <div class="mx-auto max-w-5xl p-6">
    <h1 class="text-2xl font-semibold mb-4">Dev Console (local)</h1>

    <div class="flex gap-2 relative">
      <input
        v-model="command"
        @keydown="onKeydown"
        @keyup.enter="run"
        placeholder='c ass --email=jane@example.com --company=Acme --role=admin'
        class="flex-1 border rounded px-3 py-2"
        autocomplete="off"
      />
      <button :disabled="busy" @click="run" class="border rounded px-4 py-2">Run</button>

      <!-- suggestions dropdown -->
      <div v-if="sugg.length" class="absolute top-full mt-1 w-full bg-white border rounded shadow z-10 max-h-56 overflow-auto">
        <div
          v-for="(s, i) in sugg"
          :key="s"
          class="px-3 py-2 text-sm cursor-pointer"
          :class="i === cursor ? 'bg-gray-100' : ''"
          @mouseenter="cursor = i"
          @mousedown.prevent="applySuggestion"
        >{{ s }}</div>
      </div>
    </div>

    <div class="mt-6 space-y-3">
      <div v-for="(row,i) in output" :key="i" class="border rounded">
        <div class="px-3 py-2 text-sm bg-gray-50 border-b font-mono">
          <span class="text-gray-500">{{ row.at }}</span>
          <span class="ml-2">› {{ row.cmd }}</span>
        </div>
        <pre class="px-3 py-3 text-sm overflow-auto">{{ row.res }}</pre>
      </div>
    </div>
  </div>
</template>
