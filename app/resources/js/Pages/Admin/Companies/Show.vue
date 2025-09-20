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
import CompanyOverview from './CompanyOverview.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, onMounted, computed } from 'vue'

const props = defineProps({ company: { type: String, required: true } })

const c = ref(null)
const error = ref('')
const confirm = useConfirm()
const toast = useToast()
const { setActions } = usePageActions()

async function loadCompany() {
  try {
    const { data } = await http.get(`/web/companies/${encodeURIComponent(props.company)}`)
    c.value = data.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load company'
  }
}

onMounted(() => {
  loadCompany()
  
  // Set page actions
  setActions([
    { key: 'back', label: 'Back to Companies', icon: 'pi pi-arrow-left', severity: 'secondary', click: () => router.visit(route('admin.companies.index')) },
    { key: 'deactivate', label: 'Deactivate Company', icon: 'pi pi-ban', severity: 'warning', click: deactivateCompany, disabled: () => !c.value?.is_active },
    { key: 'activate', label: 'Activate Company', icon: 'pi pi-check', severity: 'success', click: () => http.patch(`/web/companies/${slug.value}/activate`).then(() => loadCompany()), disabled: () => c.value?.is_active },
    { key: 'delete', label: 'Delete Company', icon: 'pi pi-trash', severity: 'danger', click: deleteCompany }
  ])
})

const slug = computed(() => c.value?.slug || props.company)

const tabNames = ['members', 'invite']
const storageKey = computed(() => `admin.company.tab.${slug.value}`)
const { selectedTab } = usePersistentTabs(tabNames, storageKey) // number index

// Actions
async function deactivateCompany() {
  confirm.require({
    message: `Are you sure you want to deactivate the company "${c.value?.name}"? Users will not be able to access this company.`,
    header: 'Confirm Deactivate',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, deactivate it',
    rejectLabel: 'Cancel',
    accept: async () => {
      try {
        await http.patch(`/web/companies/${slug.value}/deactivate`)
        toast.add({ severity: 'success', summary: 'Success', detail: 'Company deactivated successfully', life: 3000 })
        await loadCompany()
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e?.response?.data?.message || 'Failed to deactivate company', life: 3000 })
      }
    }
  })
}

async function deleteCompany() {
  confirm.require({
    message: `Are you sure you want to delete the company "${c.value?.name}"? This action cannot be undone and all associated data will be permanently removed.`,
    header: 'Confirm Delete',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Yes, delete it',
    rejectLabel: 'Cancel',
    accept: async () => {
      try {
        await http.delete(`/web/companies/${slug.value}`)
        toast.add({ severity: 'success', summary: 'Success', detail: 'Company deleted successfully', life: 3000 })
        router.visit(route('admin.companies.index'))
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e?.response?.data?.message || 'Failed to delete company', life: 3000 })
      }
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
            </TabView>
          </div>
        </div>
      </div>
    </div>
</LayoutShell>
</template>
