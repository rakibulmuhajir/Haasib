<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { BreadcrumbItem } from '@/types'
import { Building2, FileText, Pencil, Trash2 } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
  base_currency: string
}

interface VendorRef {
  id: string
  vendor_number: string
  name: string
  email: string | null
  phone: string | null
  base_currency: string
  payment_terms: number
  tax_id: string | null
  account_number: string | null
  website: string | null
  notes: string | null
  is_active: boolean
  address?: Record<string, string | null>
}

interface BillSummary {
  id: string
  bill_number: string
  bill_date: string
  due_date: string
  total_amount: number
  balance: number
  currency: string
  status: string
}

const props = defineProps<{
  company: CompanyRef
  vendor: VendorRef
  stats: {
    bill_count: number
    unpaid: number
    amount_owed: number
  }
  recentBills?: BillSummary[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Vendors', href: `/${props.company.slug}/vendors` },
  { title: props.vendor.vendor_number, href: `/${props.company.slug}/vendors/${props.vendor.id}` },
]

const handleDelete = () => {
  if (!confirm('Delete this vendor?')) return
  router.delete(`/${props.company.slug}/vendors/${props.vendor.id}`)
}
</script>

<template>
  <Head :title="`Vendor ${vendor.vendor_number}`" />
  <PageShell
    :title="`${vendor.vendor_number} — ${vendor.name}`"
    :breadcrumbs="breadcrumbs"
    :icon="Building2"
  >
    <template #actions>
      <div class="flex gap-2">
        <Button variant="outline" @click="router.get(`/${company.slug}/vendors/${vendor.id}/edit`)">
          <Pencil class="mr-2 h-4 w-4" />
          Edit
        </Button>
        <Button variant="destructive" @click="handleDelete">
          <Trash2 class="mr-2 h-4 w-4" />
          Delete
        </Button>
      </div>
    </template>

    <div class="grid gap-4 md:grid-cols-2">
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Email</div>
        <div class="font-medium">{{ vendor.email || '—' }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Phone</div>
        <div class="font-medium">{{ vendor.phone || '—' }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Currency</div>
        <div class="font-medium">{{ vendor.base_currency }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Payment Terms</div>
        <div class="font-medium">{{ vendor.payment_terms }} days</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Tax ID</div>
        <div class="font-medium">{{ vendor.tax_id || '—' }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Website</div>
        <div class="font-medium">{{ vendor.website || '—' }}</div>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Status</div>
        <Badge :variant="vendor.is_active ? 'success' : 'secondary'">
          {{ vendor.is_active ? 'Active' : 'Inactive' }}
        </Badge>
      </div>
      <div class="space-y-1">
        <div class="text-sm text-muted-foreground">Account #</div>
        <div class="font-medium">{{ vendor.account_number || '—' }}</div>
      </div>
    </div>

    <div class="mt-6 space-y-2">
      <div class="text-sm text-muted-foreground">Address</div>
      <div class="font-medium leading-relaxed">
        {{ vendor.address?.street || '' }}<br />
        {{ vendor.address?.city || '' }} {{ vendor.address?.state || '' }} {{ vendor.address?.zip || '' }}<br />
        {{ vendor.address?.country || '' }}
      </div>
    </div>

    <div class="mt-6 space-y-2">
      <div class="text-sm text-muted-foreground">Notes</div>
      <div class="font-medium">{{ vendor.notes || '—' }}</div>
    </div>

    <div class="mt-8 grid grid-cols-3 gap-4">
      <div class="rounded-lg border p-4">
        <div class="text-sm text-muted-foreground">Bills</div>
        <div class="text-2xl font-semibold">{{ stats.bill_count }}</div>
      </div>
      <div class="rounded-lg border p-4">
        <div class="text-sm text-muted-foreground">Unpaid</div>
        <div class="text-2xl font-semibold">{{ stats.unpaid }}</div>
      </div>
      <div class="rounded-lg border p-4">
        <div class="text-sm text-muted-foreground">Amount Owed</div>
        <div class="text-2xl font-semibold">{{ stats.amount_owed }}</div>
      </div>
    </div>

    <div class="mt-8">
      <div class="flex items-center justify-between mb-3">
        <div class="text-lg font-semibold">Recent Bills</div>
        <Button variant="outline" @click="router.get(`/${company.slug}/bills/create`)">
          <FileText class="mr-2 h-4 w-4" />
          Create Bill
        </Button>
      </div>
      <div v-if="!recentBills?.length" class="text-sm text-muted-foreground">No bills yet.</div>
      <div v-else class="space-y-2">
        <div
          v-for="bill in recentBills"
          :key="bill.id"
          class="flex items-center justify-between rounded-lg border p-3"
        >
          <div>
            <div class="font-medium">{{ bill.bill_number }}</div>
            <div class="text-xs text-muted-foreground">Due {{ bill.due_date }}</div>
          </div>
          <div class="text-right">
            <div class="font-semibold">{{ bill.total_amount }} {{ bill.currency }}</div>
            <div class="text-xs text-muted-foreground">Balance {{ bill.balance }} {{ bill.currency }}</div>
          </div>
        </div>
      </div>
    </div>
  </PageShell>
</template>
