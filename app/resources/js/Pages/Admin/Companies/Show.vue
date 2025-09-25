<script setup>
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { http } from '@/lib/http'
import { usePersistentTabs } from '@/composables/usePersistentTabs.js'
import { usePageActions } from '@/composables/usePageActions.js'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import CompanyMembersSection from './CompanyMembersSection.vue'
import CompanyInviteSection from './CompanyInviteSection.vue'
import CompanyCurrenciesSection from './CompanyCurrenciesSection.vue'
import CompanyOverview from './CompanyOverview.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, onMounted, computed, watch } from 'vue'

const props = defineProps({ company: { type: String, required: true } })

const c = ref(null)
const error = ref('')
const confirm = useConfirm()
const toast = useToast()
const { setActions } = usePageActions()

console.log('🎯 [DEBUG] Confirmation service initialized:', confirm)
console.log('🎯 [DEBUG] Toast service initialized:', toast)

async function loadCompany() {
  console.log('📡 [DEBUG] loadCompany called with props.company:', props.company)
  try {
    const url = `/web/companies/${encodeURIComponent(props.company)}`
    console.log('📡 [DEBUG] Fetching company from:', url)
    const { data } = await http.get(url)
    console.log('📡 [DEBUG] Company data received:', data.data)
    c.value = data.data
    // Update page actions when company loads
    console.log('📡 [DEBUG] Updating page actions after company loaded')
    updatePageActions()
  } catch (e) {
    console.error('📡 [ERROR] Failed to load company:', e)
    console.error('📡 [ERROR] Error response:', e?.response)
    error.value = e?.response?.data?.message || 'Failed to load company'
  }
}

function updatePageActions() {
  console.log('🔧 [DEBUG] updatePageActions called')
  console.log('🔧 [DEBUG] c.value:', c.value)
  console.log('🔧 [DEBUG] c.value?.is_active:', c.value?.is_active)
  
  const actions = [
    { key: 'back', label: 'Back to Companies', icon: 'pi pi-arrow-left', severity: 'secondary', click: () => {
      console.log('🔧 [DEBUG] Back button clicked')
      router.visit(route('admin.companies.index'))
    }},
    { key: 'deactivate', label: 'Deactivate Company', icon: 'pi pi-ban', severity: 'warning', click: deactivateCompany, disabled: () => {
      const disabled = !c.value || !c.value.is_active
      console.log('🔧 [DEBUG] Deactivate button disabled:', disabled, 'c.value:', c.value)
      return disabled
    }},
    { key: 'activate', label: 'Activate Company', icon: 'pi pi-check', severity: 'success', click: activateCompany, disabled: () => {
      const disabled = !c.value || c.value.is_active
      console.log('🔧 [DEBUG] Activate button disabled:', disabled, 'c.value:', c.value)
      return disabled
    }},
    { key: 'delete', label: 'Delete Company', icon: 'pi pi-trash', severity: 'danger', click: deleteCompany }
  ]
  
  console.log('🔧 [DEBUG] Setting actions:', actions)
  setActions(actions)
}

onMounted(() => {
  console.log('🎯 [DEBUG] Component mounted')
  console.log('🎯 [DEBUG] usePageActions setActions function:', setActions)
  // Set initial page actions
  updatePageActions()
  loadCompany()
})

const slug = computed(() => c.value?.slug || props.company)

const tabNames = ['members', 'invite', 'currencies']
const storageKey = computed(() => `admin.company.tab.${slug.value}`)
const { selectedTab } = usePersistentTabs(tabNames, storageKey) // number index

// Watch for company data changes
watch(c, (newValue, oldValue) => {
  console.log('👀 [DEBUG] c.value changed:', { 
    newValue: newValue?.name, 
    is_active: newValue?.is_active,
    oldValue: oldValue?.name 
  })
  console.log('👀 [DEBUG] Updating page actions due to company data change')
  updatePageActions()
}, { deep: true })

// Actions
async function activateCompany() {
  console.log('🚀 [DEBUG] activateCompany called')
  console.log('🚀 [DEBUG] slug.value:', slug.value)
  console.log('🚀 [DEBUG] Company data:', c.value)
  console.log('🚀 [DEBUG] confirm object:', confirm)
  
  if (!c.value) {
    console.error('🚀 [ERROR] No company data available!')
    toast.add({ severity: 'error', summary: 'Error', detail: 'Company data not loaded', life: 3000 })
    return
  }
  
  confirm.require({
    message: `Are you sure you want to activate the company "${c.value?.name}"? Users will be able to access this company.`,
    header: 'Confirm Activate',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, activate it',
    rejectLabel: 'Cancel',
    accept: async () => {
      console.log('🚀 [DEBUG] Activate confirmation accepted')
      try {
        const url = `/web/companies/${slug.value}/activate`
        console.log('🚀 [DEBUG] Sending activate request to:', url)
        const response = await http.patch(url)
        console.log('🚀 [DEBUG] Activate response:', response)
        toast.add({ severity: 'success', summary: 'Success', detail: 'Company activated successfully', life: 3000 })
        await loadCompany()
      } catch (e) {
        console.error('🚀 [ERROR] Activate failed:', e)
        console.error('🚀 [ERROR] Error response:', e?.response)
        toast.add({ severity: 'error', summary: 'Error', detail: e?.response?.data?.message || 'Failed to activate company', life: 3000 })
      }
    },
    reject: () => {
      console.log('🚀 [DEBUG] Activate confirmation rejected')
    }
  })
}

async function deactivateCompany() {
  console.log('🚀 [DEBUG] deactivateCompany called')
  console.log('🚀 [DEBUG] slug.value:', slug.value)
  console.log('🚀 [DEBUG] Company data:', c.value)
  
  if (!c.value) {
    console.error('🚀 [ERROR] No company data available!')
    toast.add({ severity: 'error', summary: 'Error', detail: 'Company data not loaded', life: 3000 })
    return
  }
  
  confirm.require({
    message: `Are you sure you want to deactivate the company "${c.value?.name}"? Users will not be able to access this company.`,
    header: 'Confirm Deactivate',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, deactivate it',
    rejectLabel: 'Cancel',
    accept: async () => {
      console.log('🚀 [DEBUG] Deactivate confirmation accepted')
      try {
        const url = `/web/companies/${slug.value}/deactivate`
        console.log('🚀 [DEBUG] Sending deactivate request to:', url)
        const response = await http.patch(url)
        console.log('🚀 [DEBUG] Deactivate response:', response)
        toast.add({ severity: 'success', summary: 'Success', detail: 'Company deactivated successfully', life: 3000 })
        await loadCompany()
      } catch (e) {
        console.error('🚀 [ERROR] Deactivate failed:', e)
        console.error('🚀 [ERROR] Error response:', e?.response)
        toast.add({ severity: 'error', summary: 'Error', detail: e?.response?.data?.message || 'Failed to deactivate company', life: 3000 })
      }
    },
    reject: () => {
      console.log('🚀 [DEBUG] Deactivate confirmation rejected')
    }
  })
}

async function deleteCompany() {
  console.log('🚀 [DEBUG] deleteCompany called')
  console.log('🚀 [DEBUG] slug.value:', slug.value)
  console.log('🚀 [DEBUG] Company data:', c.value)
  
  if (!c.value) {
    console.error('🚀 [ERROR] No company data available!')
    toast.add({ severity: 'error', summary: 'Error', detail: 'Company data not loaded', life: 3000 })
    return
  }
  
  confirm.require({
    message: `Are you sure you want to delete the company "${c.value?.name}"? This action cannot be undone and all associated data will be permanently removed.`,
    header: 'Confirm Delete',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, delete it',
    rejectLabel: 'Cancel',
    accept: async () => {
      console.log('🚀 [DEBUG] Delete confirmation accepted')
      try {
        const url = `/web/companies/${slug.value}`
        console.log('🚀 [DEBUG] Sending delete request to:', url)
        const response = await http.delete(url)
        console.log('🚀 [DEBUG] Delete response:', response)
        toast.add({ severity: 'success', summary: 'Success', detail: 'Company deleted successfully', life: 3000 })
        router.visit(route('admin.companies.index'))
      } catch (e) {
        console.error('🚀 [ERROR] Delete failed:', e)
        console.error('🚀 [ERROR] Error response:', e?.response)
        toast.add({ severity: 'error', summary: 'Error', detail: e?.response?.data?.message || 'Failed to delete company', life: 3000 })
      }
    },
    reject: () => {
      console.log('🚀 [DEBUG] Delete confirmation rejected')
    }
  })
}

</script>

<template>
  <Head :title="c ? `Company · ${c.name}` : 'Company'" />

<LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel" />
    </template>

    <template #topbar>
      <Breadcrumb />
    </template>

    <div class="space-y-4">
      <PageHeader 
        :title="c ? `Company · ${c.name}` : 'Company'" 
        :subtitle="!c?.is_active ? 'This company is currently inactive' : 'Manage company settings and members'"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
          </svg>
        </template>
      </PageHeader>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
          <CompanyOverview :company="c" />
        </div>

        <div class="lg:col-span-2">
          <div class="w-full">
            <TabView v-model:activeIndex="selectedTab" class="w-full">
              <TabPanel header="Members">
                <CompanyMembersSection :company="slug" />
              </TabPanel>
              <TabPanel header="Invite">
                <CompanyInviteSection :company="slug" />
              </TabPanel>
              <TabPanel header="Currencies">
                <CompanyCurrenciesSection :company="slug" />
              </TabPanel>
            </TabView>
          </div>
        </div>
      </div>
    </div>
</LayoutShell>
</template>
