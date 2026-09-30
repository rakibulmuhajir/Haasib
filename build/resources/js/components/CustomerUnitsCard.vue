<script setup lang="ts">
/**
 * On a customer's page: their own vehicles, sites, rooms or departments -- picked on a sale
 * instead of typed free-hand into the reference field. See CustomerUnitController.
 */
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Pencil, Check, X } from 'lucide-vue-next'

export interface CustomerUnit {
  id: string
  name: string
  is_active: boolean
}

const props = defineProps<{
  companySlug: string
  customerId: string
  units: CustomerUnit[]
}>()

const newName = ref('')
const adding = ref(false)
const add = () => {
  if (!newName.value.trim()) return
  adding.value = true
  router.post(`/${props.companySlug}/customers/${props.customerId}/units`, { name: newName.value.trim() }, {
    preserveScroll: true,
    onFinish: () => { adding.value = false },
    onSuccess: () => { newName.value = '' },
  })
}

const editingId = ref<string | null>(null)
const editName = ref('')
const startEdit = (unit: CustomerUnit) => {
  editingId.value = unit.id
  editName.value = unit.name
}
const saveEdit = (unit: CustomerUnit) => {
  const name = editName.value.trim()
  editingId.value = null
  if (!name || name === unit.name) return
  router.patch(`/${props.companySlug}/customers/${props.customerId}/units/${unit.id}`, { name }, { preserveScroll: true })
}

const toggleActive = (unit: CustomerUnit) => {
  router.patch(`/${props.companySlug}/customers/${props.customerId}/units/${unit.id}`, { is_active: !unit.is_active }, { preserveScroll: true })
}
</script>

<template>
  <Card class="border-border/80">
    <CardHeader>
      <CardTitle class="text-base">Units</CardTitle>
    </CardHeader>
    <CardContent class="space-y-3">
      <ul v-if="units.length" class="divide-y">
        <li v-for="unit in units" :key="unit.id" class="flex items-center gap-2 py-2">
          <template v-if="editingId === unit.id">
            <Input v-model="editName" maxlength="60" class="h-8" @keyup.enter="saveEdit(unit)" @keyup.escape="editingId = null" />
            <Button size="icon" variant="ghost" class="h-8 w-8" @click="saveEdit(unit)"><Check class="h-4 w-4" /></Button>
            <Button size="icon" variant="ghost" class="h-8 w-8" @click="editingId = null"><X class="h-4 w-4" /></Button>
          </template>
          <template v-else>
            <span class="flex-1 text-sm" :class="!unit.is_active && 'text-muted-foreground line-through'">{{ unit.name }}</span>
            <Button size="icon" variant="ghost" class="h-8 w-8" aria-label="Rename" @click="startEdit(unit)"><Pencil class="h-4 w-4" /></Button>
            <Button size="sm" variant="outline" class="h-8" @click="toggleActive(unit)">{{ unit.is_active ? 'Deactivate' : 'Activate' }}</Button>
          </template>
        </li>
      </ul>
      <p v-else class="text-sm text-muted-foreground">No units yet.</p>
      <div class="flex items-center gap-2 pt-1">
        <Input v-model="newName" maxlength="60" placeholder="e.g. GAL-1804" class="h-8" @keyup.enter="add" />
        <Button size="sm" :disabled="adding || !newName.trim()" @click="add">Add</Button>
      </div>
    </CardContent>
  </Card>
</template>
