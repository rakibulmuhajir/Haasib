<script setup>
import { ref, onMounted, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { http } from '@/lib/http'
import Dropdown from 'primevue/dropdown'

const page = usePage()
const companies = ref([])
const currentCompanyId = computed(() => page.props.auth.companyId)

onMounted(async () => {
  try {
    // This endpoint returns all companies for a superadmin,
    // and associated companies for a regular user.
    const { data } = await http.get('/web/companies')
    companies.value = data.data
    console.log('🔍 [DEBUG] Loaded companies:', companies.value)
    console.log('🔍 [DEBUG] Current company ID from page props:', currentCompanyId.value)
    console.log('🔍 [DEBUG] Current company from session:', page.props.auth?.currentCompany)
  } catch (e) {
    console.error('🔍 [DEBUG] Error loading companies:', e)
    companies.value = []
  }
})

async function switchCompany(event) {
  const companyId = event.value
  console.log('🔍 [DEBUG] Switching to company:', companyId)
  console.log('🔍 [DEBUG] Current company ID:', currentCompanyId.value)
  console.log('🔍 [DEBUG] Available companies:', companies.value)
  
  if (!companyId || companyId === currentCompanyId.value) return

  try {
    const response = await http.post(`/company/${companyId}/switch`)
    console.log('🔍 [DEBUG] Switch response:', response.data)
    
    // Instead of a full reload, we visit the current page to refresh Inertia props.
    router.visit(window.location.href, {
      preserveState: false, // We want a fresh state
    })
  } catch (e) {
    console.error('🔍 [DEBUG] Error switching company:', e)
    console.error('🔍 [DEBUG] Error response:', e.response?.data)
  }
}
</script>

<template>
  <Dropdown
    :model-value="currentCompanyId"
    :options="companies"
    optionLabel="name"
    optionValue="id"
    placeholder="Select Company"
    @change="switchCompany"
    class="w-full md:w-56"
  />
</template>
