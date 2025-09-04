<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, computed } from 'vue'
import { TabsRoot as Tabs, TabsList, TabsTrigger, TabsContent } from 'reka-ui'
import { http } from '@/lib/http'
import { usePersistentTabs } from '@/composables/usePersistentTabs.js'
import CompanyMembersSection from './CompanyMembersSection.vue'
import CompanyInviteSection from './CompanyInviteSection.vue'
import CompanyOverview from './CompanyOverview.vue'

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
const { selectedTab } = usePersistentTabs(tabNames, storageKey)
const tabValue = computed({
  get: () => String(selectedTab.value),
  set: (val) => { selectedTab.value = Number(val) }
})
</script>

<template>
  <Head :title="c ? `Company · ${c.name}` : 'Company'" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Company</h2>
        <Link :href="route('admin.companies.index')" class="text-sm text-gray-600 hover:underline">Back to companies</Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div v-if="error" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="lg:col-span-1">
            <CompanyOverview :company="c" />
          </div>

          <div class="lg:col-span-2">
            <Tabs v-model="tabValue" class="w-full">
              <div class="sticky top-16 z-10 bg-white/80 backdrop-blur supports-[backdrop-filter]:bg-white/60">
                <TabsList class="flex space-x-2 border-b border-gray-200 px-2">
                  <TabsTrigger value="0" class="focus:outline-none px-4 py-2 text-sm data-[state=active]:border-b-2 data-[state=active]:border-indigo-600 data-[state=active]:text-indigo-600 text-gray-600 hover:text-gray-800">
                    Members
                  </TabsTrigger>
                  <TabsTrigger value="1" class="focus:outline-none px-4 py-2 text-sm data-[state=active]:border-b-2 data-[state=active]:border-indigo-600 data-[state=active]:text-indigo-600 text-gray-600 hover:text-gray-800">
                    Invite
                  </TabsTrigger>
                </TabsList>
              </div>
              <div class="mt-4">
                <TabsContent value="0">
                  <CompanyMembersSection :company="slug" />
                </TabsContent>
                <TabsContent value="1">
                  <CompanyInviteSection :company="slug" />
                </TabsContent>
              </div>
            </Tabs>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
