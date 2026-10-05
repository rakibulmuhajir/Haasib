<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import LedgerRegister from '@/components/LedgerRegister.vue'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { BreadcrumbItem } from '@/types'
import { Check, Pencil, Tags, Trash2, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

interface CategoryRow {
  id: string
  name: string
  description: string | null
  customers_count: number
}

const props = defineProps<{
  company: { id: string; name: string; slug: string }
  categories: CategoryRow[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Customers', href: `/${props.company.slug}/customers` },
  { title: 'Categories', href: `/${props.company.slug}/customer-categories` },
]

const columns = [
  { key: 'name', label: 'Category', kind: 'text' as const },
  { key: 'customers_count', label: 'Customers', kind: 'amount' as const },
  { key: 'actions', label: '', kind: 'text' as const },
]

const addForm = useForm({ name: '' })
const add = () => {
  addForm.post(`/${props.company.slug}/customer-categories`, {
    preserveScroll: true,
    onSuccess: () => addForm.reset(),
  })
}

const editingId = ref<string | null>(null)
const editForm = useForm({ name: '' })
const startEdit = (row: CategoryRow) => {
  editingId.value = row.id
  editForm.name = row.name
  editForm.clearErrors()
}
const saveEdit = (row: CategoryRow) => {
  editForm.put(`/${props.company.slug}/customer-categories/${row.id}`, {
    preserveScroll: true,
    onSuccess: () => { editingId.value = null },
  })
}

const remove = (row: CategoryRow) => {
  router.delete(`/${props.company.slug}/customer-categories/${row.id}`, {
    preserveScroll: true,
    onError: (errors) => toast.error(Object.values(errors)[0] as string),
  })
}
</script>

<template>
  <Head title="Customer categories" />

  <PageShell title="Customer categories" :icon="Tags" :breadcrumbs="breadcrumbs">
    <div class="max-w-2xl space-y-4">
      <form class="flex items-start gap-2" @submit.prevent="add">
        <div class="grid flex-1 gap-1">
          <Input v-model="addForm.name" placeholder="New category" maxlength="100" />
          <InputError :message="addForm.errors.name" />
        </div>
        <Button type="submit" :disabled="addForm.processing || !addForm.name.trim()">Add</Button>
      </form>

      <LedgerRegister :data="categories" :columns="columns">
        <template #empty>No categories.</template>

        <template #cell-name="{ row }">
          <form v-if="editingId === row.id" class="flex items-start gap-2" @submit.prevent="saveEdit(row)">
            <div class="grid gap-1">
              <Input v-model="editForm.name" maxlength="100" class="h-8" />
              <InputError :message="editForm.errors.name" />
            </div>
            <Button type="submit" size="icon" variant="ghost" class="h-8 w-8" :disabled="editForm.processing"><Check class="h-4 w-4" /></Button>
            <Button type="button" size="icon" variant="ghost" class="h-8 w-8" @click="editingId = null"><X class="h-4 w-4" /></Button>
          </form>
          <span v-else class="font-medium">{{ row.name }}</span>
        </template>

        <template #cell-customers_count="{ row }">{{ row.customers_count }}</template>

        <template #cell-actions="{ row }">
          <div class="flex justify-end gap-1">
            <Button size="icon" variant="ghost" class="h-8 w-8" title="Rename" @click="startEdit(row)"><Pencil class="h-4 w-4" /></Button>
            <Button size="icon" variant="ghost" class="h-8 w-8" title="Delete" @click="remove(row)"><Trash2 class="h-4 w-4" /></Button>
          </div>
        </template>
      </LedgerRegister>
    </div>
  </PageShell>
</template>
