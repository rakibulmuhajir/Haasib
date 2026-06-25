<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Pencil, Plus, Search, Trash2, Users } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  agents: { data: any[]; total: number }
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
        <div v-if="!agents.data.length" class="p-8 text-center text-sm text-muted-foreground">No agents yet.</div>
        <div v-for="agent in agents.data" :key="agent.id" class="flex cursor-pointer items-center justify-between gap-4 border-b p-4 last:border-b-0" @click="router.get(`/${company.slug}/umrah/agents/${agent.id}`)">
          <div>
            <div class="font-medium">{{ agent.name }}</div>
            <div class="text-sm text-muted-foreground">{{ agent.agent_number }} · {{ agent.phone || 'No phone' }} · {{ agent.city || 'No city' }} · {{ agent.country || 'No country' }}</div>
          </div>
          <div class="flex items-center gap-3">
            <div class="text-right">
              <div class="font-semibold"><MoneyText :amount="agent.balance" :currency="company.base_currency" /></div>
              <div class="text-xs text-muted-foreground">balance</div>
            </div>
            <div class="flex items-center gap-1" @click.stop>
              <Button type="button" variant="ghost" size="icon" @click="router.get(`/${company.slug}/umrah/agents/${agent.id}/edit`)">
                <Pencil class="h-4 w-4" />
              </Button>
              <Button type="button" variant="ghost" size="icon" :disabled="removeForm.processing" @click="removeAgent(agent)">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>
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
