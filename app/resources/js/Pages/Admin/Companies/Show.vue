<script setup>
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import { http } from '@/lib/http'
import { usePersistentTabs } from '@/composables/usePersistentTabs.js'
import CompanyMembersSection from './CompanyMembersSection.vue'
import CompanyInviteSection from './CompanyInviteSection.vue'
import CompanyOverview from './CompanyOverview.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, computed } from 'vue'

const props = defineProps({ company: { type: String, required: true } })

const c = ref(null)
const error = ref('')

async function loadCompany() {
  try {
    const { data } = await http.get(`/web/companies/${encodeURIComponent(props.company)}`)
    c.value = data.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load company'
  }
}

onMounted(loadCompany)

const slug = computed(() => c.value?.slug || props.company)

const tabNames = ['members', 'invite']
const storageKey = computed(() => `admin.company.tab.${slug.value}`)
const { selectedTab } = usePersistentTabs(tabNames, storageKey) // number index

// Breadcrumb items
const breadcrumbItems = ref([
  { label: 'Admin', url: '/admin', icon: 'settings' },
  { label: 'Companies', url: '/admin/companies', icon: 'companies' },
  { label: c.value?.name || 'Company', url: '#' }
])
</script>

<template>
  <Head :title="c ? `Company · ${c.name}` : 'Company'" />

<LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel" />
    </template>

    <template #topbar>
      <div class="flex items-center justify-between w-full">
        <Breadcrumb :items="breadcrumbItems" />
        <Link :href="route('admin.companies.index')">
          <Button label="Back to Companies" icon="pi pi-arrow-left" severity="secondary" />
        </Link>
      </div>
    </template>

    <div class="space-y-4">
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
