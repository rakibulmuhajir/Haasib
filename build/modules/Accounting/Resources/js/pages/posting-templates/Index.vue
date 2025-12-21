<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import DataTable from '@/components/DataTable.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { Settings, Plus, Pencil } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface AccountRef {
  id: string
  code: string
  name: string
  type: string
  subtype: string
}

interface PostingTemplateLine {
  id: string
  role: string
  account_id: string
  account?: AccountRef | null
}

interface PostingTemplate {
  id: string
  doc_type: string
  name: string
  description?: string | null
  is_active: boolean
  is_default: boolean
  version: number
  effective_from: string
  effective_to?: string | null
  lines: PostingTemplateLine[]
}

const props = defineProps<{
  company: CompanyRef
  templates: PostingTemplate[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Company', href: `/${props.company.slug}` },
  { title: 'Posting Templates', href: `/${props.company.slug}/posting-templates` },
]

const columns = [
  { key: 'doc_type', label: 'Doc Type' },
  { key: 'name', label: 'Name' },
  { key: 'status', label: 'Status' },
  { key: 'effective_from', label: 'Effective From' },
  { key: '_actions', label: '', sortable: false },
]

const tableData = computed(() =>
  props.templates.map((t) => ({
    id: t.id,
    doc_type: t.doc_type,
    name: t.name,
    status: t.is_active ? 'Active' : 'Inactive',
    effective_from: t.effective_from,
    is_default: t.is_default,
    _templateId: t.id,
  })),
)
</script>

<template>
  <Head title="Posting Templates" />

  <PageShell title="Posting Templates" :breadcrumbs="breadcrumbs">
    <template #actions>
      <Button @click="router.get(`/${company.slug}/posting-templates/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Template
      </Button>
    </template>

    <div class="text-sm text-muted-foreground mb-6">
      Control how documents post to the general ledger.
    </div>

    <DataTable :columns="columns" :data="tableData" key-field="id">
      <template #cell-name="{ row }">
        <div class="flex items-center gap-2">
          <span>{{ row.name }}</span>
          <Badge v-if="row.is_default" variant="secondary">Default</Badge>
        </div>
      </template>

      <template #cell-status="{ value }">
        <Badge :variant="value === 'Active' ? 'default' : 'secondary'">
          {{ value }}
        </Badge>
      </template>

      <template #cell-_actions="{ row }">
        <Button variant="outline" size="sm" @click="router.get(`/${company.slug}/posting-templates/${row._templateId}/edit`)">
          <Pencil class="mr-2 h-4 w-4" />
          Edit
        </Button>
      </template>
    </DataTable>

    <div class="mt-6 text-sm text-muted-foreground flex items-center gap-2">
      <Settings class="w-4 h-4" />
      Templates are company-scoped and used by auto-posting.
    </div>
  </PageShell>
</template>
