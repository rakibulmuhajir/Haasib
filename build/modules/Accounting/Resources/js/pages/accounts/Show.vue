<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { Layers, Pencil, Trash2 } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'

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
  normal_balance: string
  currency: string | null
  is_active: boolean
  is_system: boolean
  parent_id: string | null
  children?: { id: string }[]
  description: string | null
  created_at: string
  updated_at: string
}

const props = defineProps<{
  company: CompanyRef
  account: AccountRef
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Accounts', href: `/${props.company.slug}/accounts` },
  { title: props.account.code, href: `/${props.company.slug}/accounts/${props.account.id}` },
]

const handleDelete = () => {
  if (!confirm('Delete this account?')) return
  router.delete(`/${props.company.slug}/accounts/${props.account.id}`)
}
</script>

<template>
  <Head :title="`Account ${account.code}`" />
  <PageShell
    :title="`${account.code} — ${account.name}`"
    :breadcrumbs="breadcrumbs"
    :icon="Layers"
  >
    <template #actions>
      <div class="flex gap-2">
        <Button variant="outline" @click="router.get(`/${company.slug}/accounts/${account.id}/edit`)">
          <Pencil class="mr-2 h-4 w-4" />
          Edit
        </Button>
        <Button
          v-if="!account.is_system"
          variant="destructive"
          @click="handleDelete"
        >
          <Trash2 class="mr-2 h-4 w-4" />
          Delete
        </Button>
      </div>
    </template>

    <div class="grid gap-4 md:grid-cols-2">
      <div class="space-y-2">
        <div class="text-sm text-muted-foreground">Type</div>
        <div class="text-base font-medium">{{ account.type }} / {{ account.subtype }}</div>
      </div>
      <div class="space-y-2">
        <div class="text-sm text-muted-foreground">Normal Balance</div>
        <div class="text-base font-medium capitalize">{{ account.normal_balance }}</div>
      </div>
      <div class="space-y-2">
        <div class="text-sm text-muted-foreground">Currency</div>
        <div class="text-base font-medium">{{ account.currency || company.base_currency }}</div>
      </div>
      <div class="space-y-2">
        <div class="text-sm text-muted-foreground">Status</div>
        <Badge :variant="account.is_active ? 'success' : 'secondary'">
          {{ account.is_active ? 'Active' : 'Inactive' }}
        </Badge>
      </div>
      <div class="space-y-2">
        <div class="text-sm text-muted-foreground">Parent</div>
        <div class="text-base font-medium">{{ account.parent_id ?? '—' }}</div>
      </div>
      <div class="space-y-2">
        <div class="text-sm text-muted-foreground">Children</div>
        <div class="text-base font-medium">{{ account.children?.length ?? 0 }}</div>
      </div>
      <div class="space-y-2 md:col-span-2">
        <div class="text-sm text-muted-foreground">Description</div>
        <div class="text-base font-medium">{{ account.description || '—' }}</div>
      </div>
    </div>
  </PageShell>
</template>
