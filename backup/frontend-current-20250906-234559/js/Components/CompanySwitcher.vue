<!-- resources/js/Components/CompanySwitcher.vue -->
<script setup>
import { ref, onMounted } from 'vue'
import { http } from '@/lib/http'
const companies = ref([])
const currentId = ref(localStorage.getItem('currentCompanyId') || null)

onMounted(async () => {
  try {
    const { data } = await http.get('/web/me/companies')
    companies.value = data.data
    if (!currentId.value && companies.value.length === 1) {
      currentId.value = companies.value[0].id
      localStorage.setItem('currentCompanyId', currentId.value)
    }
  } catch (e) {
    // Silently ignore if unauthenticated or endpoint not available
    companies.value = []
  }
})

async function switchCompany(id) {
  if (!id) return
  await http.post('/web/companies/switch', { company_id: id })
  localStorage.setItem('currentCompanyId', id)
  window.location.reload()
}
</script>

<template>
  <select :value="currentId" @change="switchCompany($event.target.value)" class="border rounded px-2 py-1">
    <option disabled value="">Select company…</option>
    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
  </select>
</template>
