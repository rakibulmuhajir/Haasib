<script setup lang="ts">
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import { Building2, ChevronsUpDown, Check, Plus } from 'lucide-vue-next'

const page = usePage()
const currentCompany = computed(() => (page.props.auth as any)?.currentCompany || null)
const companies = computed(() => (page.props.auth as any)?.companies || [])

const switchCompany = (slug: string) => {
  router.post('/companies/switch', { slug }, {
    preserveScroll: true,
  })
}

const createCompany = () => {
  router.visit('/companies')
}
</script>

<template>
  <SidebarMenu>
    <SidebarMenuItem>
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <SidebarMenuButton
            size="lg"
            class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
          >
            <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
              <Building2 class="size-4" />
            </div>
            <div class="grid flex-1 text-left text-sm leading-tight">
              <span class="truncate font-semibold">
                {{ currentCompany?.name || 'No Company' }}
              </span>
              <span class="truncate text-xs text-muted-foreground">
                {{ currentCompany?.slug || 'Select company' }}
              </span>
            </div>
            <ChevronsUpDown class="ml-auto" />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent
          class="w-(--reka-dropdown-menu-trigger-width) min-w-56"
          align="start"
          side="bottom"
          :side-offset="4"
        >
          <DropdownMenuLabel class="text-xs text-muted-foreground">
            Your Companies
          </DropdownMenuLabel>
          <DropdownMenuItem
            v-for="company in companies"
            :key="company.id"
            @click="switchCompany(company.slug)"
            class="gap-2 p-2"
          >
            <div class="flex size-6 items-center justify-center rounded-sm border">
              <Building2 class="size-4 shrink-0" />
            </div>
            <div class="flex-1">
              <div class="font-medium">{{ company.name }}</div>
              <div class="text-xs text-muted-foreground">{{ company.slug }}</div>
            </div>
            <Check v-if="currentCompany?.id === company.id" class="size-4" />
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem @click="createCompany" class="gap-2 p-2">
            <div class="flex size-6 items-center justify-center rounded-md border border-dashed">
              <Plus class="size-4" />
            </div>
            <div class="font-medium text-muted-foreground">Add Company</div>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  </SidebarMenu>
</template>
