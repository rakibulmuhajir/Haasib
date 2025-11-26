<script setup lang="ts">
import type { Component } from "vue"
import { ChevronsUpDown, Plus, Check, Settings } from "lucide-vue-next"
import { ref, watch, computed } from "vue"
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar"

const props = defineProps<{
  companies: {
    name: string
    logo: Component
    plan: string
    url?: string
    id?: string
  }[]
  activeCompanyId?: string | null
}>()

const emit = defineEmits<{
  (e: 'select', company: any): void
}>()

const { isMobile } = useSidebar()
const activeCompany = ref(props.companies?.[0] ?? null)

const syncActiveCompany = () => {
  if (!props.companies || props.companies.length === 0) {
    activeCompany.value = null
    return
  }

  if (props.activeCompanyId) {
    const found = props.companies.find((company) => company.id === props.activeCompanyId)
    activeCompany.value = found ?? props.companies[0]
    return
  }

  activeCompany.value = props.companies[0]
}

watch(
  () => [props.companies, props.activeCompanyId],
  () => syncActiveCompany(),
  { deep: true, immediate: true }
)

const selectCompany = (company: any) => {
  if (!company) {
    return
  }

  if (!company.id) {
    if (company.url) {
      router.visit(company.url)
    }
    return
  }

  router.post(`/company/${company.id}/switch`, {}, {
    preserveScroll: false,
    onSuccess: () => {
      toast.success(`Switched to ${company.name}`)
      // Reload page to refresh the context
      window.location.reload()
    },
    onError: () => {
      toast.error('Unable to switch to the company. Please try again.')
    },
  })
}

const handleSelect = (company: any) => {
  activeCompany.value = company
  emit('select', company)
  selectCompany(company)
}

const isActiveCompany = (company: any) => {
  return activeCompany.value?.id === company.id || 
         props.activeCompanyId === company.id
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
              <component v-if="activeCompany?.logo" :is="activeCompany.logo" class="size-4" />
              <span v-else class="text-xs font-semibold">?</span>
            </div>
            <div class="grid flex-1 text-left text-sm leading-tight">
              <span class="truncate font-medium flex items-center gap-2">
                {{ activeCompany?.name ?? 'Select company' }}
                <span 
                  v-if="activeCompany"
                  class="inline-flex h-2 w-2 rounded-full bg-green-500"
                  title="Active company"
                />
              </span>
              <span class="truncate text-xs text-muted-foreground">
                {{ activeCompany?.plan ?? '' }}
              </span>
            </div>
            <ChevronsUpDown class="ml-auto" />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent
          class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg"
          align="start"
          :side="isMobile ? 'bottom' : 'right'"
          :side-offset="4"
        >
          <DropdownMenuLabel class="text-xs text-muted-foreground">
            Companies
          </DropdownMenuLabel>
          <DropdownMenuItem
            v-for="(company, index) in companies"
            :key="company.name"
            :class="[
              'gap-2 p-2 relative',
              isActiveCompany(company) ? 'bg-accent text-accent-foreground font-medium' : ''
            ]"
            @click="handleSelect(company)"
          >
            <div class="flex size-6 items-center justify-center rounded-sm border">
              <component :is="company.logo" class="size-3.5 shrink-0" />
            </div>
            <span class="flex-1">{{ company.name }}</span>
            <Check 
              v-if="isActiveCompany(company)"
              class="size-4 text-primary ml-auto"
            />
            <DropdownMenuShortcut v-else>⌘{{ index + 1 }}</DropdownMenuShortcut>
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem 
            class="gap-2 p-2"
            @click="router.visit('/companies/create')"
          >
            <div class="flex size-6 items-center justify-center rounded-md border bg-transparent">
              <Plus class="size-4" />
            </div>
            <div class="font-medium text-muted-foreground">
              Add company
            </div>
          </DropdownMenuItem>
          <DropdownMenuItem 
            class="gap-2 p-2"
            @click="router.visit('/companies')"
          >
            <div class="flex size-6 items-center justify-center rounded-md border bg-transparent">
              <Settings class="size-4" />
            </div>
            <div class="font-medium text-muted-foreground">
              Manage companies
            </div>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  </SidebarMenu>
</template>