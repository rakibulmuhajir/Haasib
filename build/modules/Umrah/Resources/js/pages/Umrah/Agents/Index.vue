<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import MoneyText from '@/components/MoneyText.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Plus, Search, Users } from 'lucide-vue-next'

const props = defineProps<{
  company: { slug: string; base_currency: string }
  agents: { data: any[]; total: number }
  filters: { search?: string }
}>()

const search = ref(props.filters.search || '')
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Agents', href: `/${props.company.slug}/umrah/agents` },
]

const applySearch = () => router.get(`/${props.company.slug}/umrah/agents`, { search: search.value }, { preserveState: true })
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
        <div v-for="agent in agents.data" :key="agent.id" class="flex cursor-pointer items-center justify-between border-b p-4 last:border-b-0" @click="router.get(`/${company.slug}/umrah/agents/${agent.id}`)">
          <div>
            <div class="font-medium">{{ agent.name }}</div>
            <div class="text-sm text-muted-foreground">{{ agent.agent_number }} · {{ agent.phone || 'No phone' }} · {{ agent.city || 'No city' }}</div>
          </div>
          <div class="text-right">
            <div class="font-semibold"><MoneyText :amount="agent.balance" :currency="company.base_currency" /></div>
            <div class="text-xs text-muted-foreground">balance</div>
          </div>
        </div>
      </CardContent>
    </Card>
  </PageShell>
</template>
