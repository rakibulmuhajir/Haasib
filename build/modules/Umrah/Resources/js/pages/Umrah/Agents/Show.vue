<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
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
const accessForm = useForm({
  can_create_voucher: Boolean(props.agent.can_create_voucher),
  can_approve_voucher: Boolean(props.agent.can_approve_voucher),
  can_edit_voucher: Boolean(props.agent.can_edit_voucher),
  voucher_cutoff_hours: String(props.agent.voucher_cutoff_hours || 6),
})
const removeDialogOpen = ref(false)

const confirmRemoveAgent = () => {
  removeForm.delete(`/${props.company.slug}/umrah/agents/${props.agent.id}`, {
    onSuccess: () => toast.success('Agent removed successfully'),
    onError: () => toast.error('Failed to remove agent'),
  })
}
const saveAccess = () => accessForm.transform((data) => ({ ...data, voucher_cutoff_hours: Number(data.voucher_cutoff_hours) })).put(`/${props.company.slug}/umrah/agents/${props.agent.id}/voucher-access`, {
  preserveScroll: true,
  onSuccess: () => toast.success('Agent voucher access updated successfully'),
  onError: () => toast.error('Failed to update agent voucher access'),
})
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

    <div v-if="agent.logo_url" class="flex items-center gap-3">
      <img :src="agent.logo_url" :alt="`${agent.name} logo`" class="h-16 w-16 rounded-md border object-contain" />
      <div><div class="font-medium">{{ agent.name }}</div><div class="text-sm text-muted-foreground">{{ agent.email || agent.phone || agent.agent_number }}</div></div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <Card><CardHeader><CardTitle>Total Receivable</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="agent.total_receivable" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Paid</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="agent.total_paid" :currency="company.base_currency" /></CardContent></Card>
      <Card><CardHeader><CardTitle>Balance</CardTitle></CardHeader><CardContent class="text-2xl font-semibold"><MoneyText :amount="agent.balance" :currency="company.base_currency" /></CardContent></Card>
    </div>

    <Card>
      <CardHeader><CardTitle>Login and Voucher Access</CardTitle></CardHeader>
      <CardContent class="space-y-4">
        <div class="rounded-md border p-3"><div class="text-sm text-muted-foreground">Username</div><div class="font-medium">{{ agent.user?.username || 'No login access' }}</div></div>
        <form v-if="agent.user_id" class="space-y-3" @submit.prevent="saveAccess">
          <Label class="flex cursor-pointer items-center gap-2"><Checkbox v-model="accessForm.can_create_voucher" />Create vouchers</Label>
          <Label class="flex cursor-pointer items-center gap-2"><Checkbox v-model="accessForm.can_approve_voucher" />Approve vouchers</Label>
          <Label class="flex cursor-pointer items-center gap-2"><Checkbox v-model="accessForm.can_edit_voucher" />Edit draft vouchers and schedules</Label>
          <div class="max-w-sm space-y-2"><Label>Creation and change cutoff</Label><Select v-model="accessForm.voucher_cutoff_hours"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem v-for="hours in [2, 6, 12, 18, 24, 48]" :key="hours" :value="String(hours)">{{ hours }} hours before flight</SelectItem></SelectContent></Select></div>
          <Button type="submit" :disabled="accessForm.processing">Save Access</Button>
        </form>
      </CardContent>
    </Card>

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
