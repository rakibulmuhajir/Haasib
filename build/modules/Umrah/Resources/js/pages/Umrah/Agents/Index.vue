<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import RecordPagination from '@/components/RecordPagination.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { BreadcrumbItem } from '@/types'
import { Pencil, Plus, Search, Trash2, Users } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  agents: { data: any[]; total: number; current_page: number; last_page: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null }
  filters: { search?: string }
}>()

const search = ref(props.filters.search || '')
const removeForm = useForm({})
const agentToRemove = ref<any | null>(null)
const removeDialogOpen = ref(false)
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Agents', href: `/${props.company.slug}/umrah/agents` },
]

const applySearch = () => router.get(`/${props.company.slug}/umrah/agents`, { search: search.value }, { preserveState: true })

const removeAgent = (agent: any) => {
  agentToRemove.value = agent
  removeDialogOpen.value = true
}

const confirmRemoveAgent = () => {
  if (!agentToRemove.value) return

  removeForm.delete(`/${props.company.slug}/umrah/agents/${agentToRemove.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Agent removed successfully')
      removeDialogOpen.value = false
      agentToRemove.value = null
    },
    onError: () => toast.error('Failed to remove agent'),
  })
}
</script>

<template>
  <Head title="Agents" />
  <PageShell title="Agents" description="People or companies sending passports and groups." :breadcrumbs="breadcrumbs" :icon="Users">
    <template #actions>
      <Button @click="router.get(`/${company.slug}/umrah/agents/create`)">
        <Plus class="mr-2 h-4 w-4" />
        New Agent
      </Button>
    </template>

    <div class="relative max-w-xl">
      <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
      <Input v-model="search" class="pl-10" placeholder="Search agents..." @keyup.enter="applySearch" />
    </div>

    <Card>
      <CardContent class="p-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Agent #</TableHead>
              <TableHead>Agent</TableHead>
              <TableHead>Phone</TableHead>
              <TableHead>City</TableHead>
              <TableHead>Country</TableHead>
              <TableHead class="text-right">Balance</TableHead>
              <TableHead class="w-24 text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableEmpty v-if="!agents.data.length" :colspan="7">No agents yet.</TableEmpty>
            <TableRow v-for="agent in agents.data" :key="agent.id" class="cursor-pointer" @click="router.get(`/${company.slug}/umrah/agents/${agent.id}`)">
              <TableCell class="font-medium">{{ agent.agent_number }}</TableCell>
              <TableCell>{{ agent.name }}</TableCell>
              <TableCell>{{ agent.phone || '-' }}</TableCell>
              <TableCell>{{ agent.city || '-' }}</TableCell>
              <TableCell>{{ agent.country || '-' }}</TableCell>
              <TableCell class="text-right font-semibold"><MoneyText :amount="agent.balance" :currency="company.base_currency" /></TableCell>
              <TableCell>
                <div class="flex items-center justify-end gap-1" @click.stop>
              <Button type="button" variant="ghost" size="icon" @click="router.get(`/${company.slug}/umrah/agents/${agent.id}/edit`)">
                <Pencil class="h-4 w-4" />
                <span class="sr-only">Edit {{ agent.name }}</span>
              </Button>
              <Button type="button" variant="ghost" size="icon" :disabled="removeForm.processing" @click="removeAgent(agent)">
                <Trash2 class="h-4 w-4" />
                <span class="sr-only">Remove {{ agent.name }}</span>
              </Button>
            </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
        <RecordPagination :current-page="agents.current_page" :last-page="agents.last_page" :from="agents.from" :to="agents.to" :total="agents.total" :previous-url="agents.prev_page_url" :next-url="agents.next_page_url" />
      </CardContent>
    </Card>

    <ConfirmDialog
      v-model:open="removeDialogOpen"
      variant="destructive"
      title="Remove Agent"
      :description="`Remove ${agentToRemove?.name || 'this agent'} from future use? Existing groups keep their history.`"
      confirm-text="Remove Agent"
      :loading="removeForm.processing"
      @confirm="confirmRemoveAgent"
    />
  </PageShell>
</template>
