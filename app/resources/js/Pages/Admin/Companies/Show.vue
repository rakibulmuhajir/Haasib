<script setup>
import LayoutShell from '@/Components/Layout/LayoutShell.vue'
import Sidebar from '@/Components/Sidebar/Sidebar.vue'
import SidebarMenu from '@/Components/Sidebar/SidebarMenu.vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Toolbar from 'primevue/toolbar'
import Message from 'primevue/message'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
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
</script>

<template>
  <Head :title="c ? `Company · ${c.name}` : 'Company'" />

<LayoutShell>
    <template #sidebar>
      <Sidebar title="Admin Panel">
        <SidebarMenu iconSet="line" :sections="[
          { title: 'Admin', items: [
            { label: 'Companies', path: '/admin/companies', icon: 'companies', routeName: 'admin.companies.index' },
            { label: 'Users', path: '/admin/users', icon: 'users', routeName: 'admin.users.index' }
          ]}
        ]" />
      </Sidebar>
    </template>

    <template #topbar>
      <Toolbar class="border-0 bg-transparent px-0">
        <template #start>
          <h1 class="text-2xl font-bold">Company</h1>
        </template>
        <template #end>
          <Link :href="route('admin.companies.index')">
            <Button label="Back to Companies" icon="pi pi-arrow-left" severity="secondary" />
          </Link>
        </template>
      </Toolbar>
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
