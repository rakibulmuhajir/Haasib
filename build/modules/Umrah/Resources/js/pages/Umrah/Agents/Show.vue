<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Pencil, Trash2, Users } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  agent: any
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Agents', href: `/${props.company.slug}/umrah/agents` },
  { title: props.agent.name, href: `/${props.company.slug}/umrah/agents/${props.agent.id}` },
]

const removeForm = useForm({})
const removeDialogOpen = ref(false)

const confirmRemoveAgent = () => {
  removeForm.delete(`/${props.company.slug}/umrah/agents/${props.agent.id}`, {
    onSuccess: () => toast.success('Agent removed successfully'),
    onError: () => toast.error('Failed to remove agent'),
  })
}
</script>

<template>
  <Head :title="agent.name" />
  <PageShell :title="agent.name" :description="`${agent.agent_number} · ${agent.phone || 'No phone'} · ${agent.country || 'No country'}`" :breadcrumbs="breadcrumbs" :icon="Users">
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/umrah/agents/${agent.id}/edit`)">
        <Pencil class="mr-2 h-4 w-4" />
        Edit
      </Button>
      <Button variant="destructive" :disabled="removeForm.processing" @click="removeDialogOpen = true">
        <Trash2 class="mr-2 h-4 w-4" />
        Delete
      </Button>
    </template>

    <div class="grid gap-4 md:grid-cols-3">
      <Card><CardHeader><CardTitle>Total Receivable</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="agent.total_receivable" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Paid</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="agent.total_paid" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Balance</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="agent.balance" :currency="company.base_currency" /></CardContent></Card>
    </div>

    <Card>
      <CardHeader><CardTitle>Groups</CardTitle></CardHeader>
      <CardContent class="space-y-3">
        <div v-if="!agent.groups?.length" class="text-sm text-muted-foreground">No groups for this agent yet.</div>
        <div v-for="group in agent.groups" :key="group.id" class="flex items-center justify-between rounded-md border p-3">
          <div>
            <div class="font-medium">{{ group.group_number }} · {{ group.name }}</div>
            <div class="text-sm text-muted-foreground">{{ group.status }} · {{ group.passenger_count }} passengers</div>
          </div>
          <Button variant="outline" size="sm" @click="router.get(`/${company.slug}/umrah/groups/${group.id}`)">Open</Button>
        </div>
      </CardContent>
    </Card>

    <ConfirmDialog
      v-model:open="removeDialogOpen"
      variant="destructive"
      title="Remove Agent"
      :description="`Remove ${agent.name} from future use? Existing groups keep their history.`"
      confirm-text="Remove Agent"
      :loading="removeForm.processing"
      @confirm="confirmRemoveAgent"
    />
  </PageShell>
</template>
