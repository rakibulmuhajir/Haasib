<template>
  <Card>
    <template #title>Contacts</template>
    <template #content>
      <!-- Add Contact Button -->
      <div v-if="!addingContact" class="mb-4">
        <Button
          label="+ Add Contact"
          icon="pi pi-plus"
          severity="secondary"
          text
          @click="startAddingContact"
        />
      </div>

      <!-- Add Contact Form -->
      <div v-else class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">First Name *</label>
            <InputText v-model="newContact.first_name" class="w-full" placeholder="First name" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Last Name *</label>
            <InputText v-model="newContact.last_name" class="w-full" placeholder="Last name" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Position</label>
            <InputText v-model="newContact.position" class="w-full" placeholder="Job title" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
            <InputText v-model="newContact.email" class="w-full" placeholder="Email address" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone</label>
            <InputText v-model="newContact.phone" class="w-full" placeholder="Phone number" />
          </div>
        </div>
        <div class="mt-4 flex items-center gap-2">
          <button class="text-xs text-green-600 hover:text-green-800" @click="saveContact">
            <i class="fas fa-check text-xs mr-1"></i> save contact
          </button>
          <button class="text-xs text-red-600 hover:text-red-800" @click="cancelAddingContact">
            <i class="fas fa-times text-xs mr-1"></i> cancel
          </button>
        </div>
      </div>

      <!-- Existing Contacts -->
      <div v-if="contacts && contacts.length > 0" class="space-y-4">
        <div v-for="contact in contacts" :key="contact.id" class="flex items-center justify-between p-4 border rounded-lg">
          <div class="flex-1">
            <div class="font-medium">{{ contact.first_name }} {{ contact.last_name }}</div>
            <div v-if="contact.position" class="text-sm text-gray-500">{{ contact.position }}</div>
            <div v-if="contact.email" class="text-sm text-blue-600">{{ contact.email }}</div>
            <div v-if="contact.phone" class="text-sm text-gray-600">{{ contact.phone }}</div>
          </div>
          <div v-if="contact.is_primary" class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
            Primary
          </div>
        </div>
      </div>
      <div v-else class="text-center py-4 text-gray-500">
        <p>No contacts added yet</p>
      </div>
    </template>
  </Card>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { http } from '@/lib/http'

interface Contact {
  id: number
  first_name: string
  last_name: string
  position?: string
  email?: string
  phone?: string
  is_primary: boolean
}

const props = defineProps<{
  customerId: string
  contacts: Contact[]
}>()

const toast = useToast()
const addingContact = ref(false)
const newContact = ref({
  first_name: '',
  last_name: '',
  position: '',
  email: '',
  phone: '',
})

const resetForm = () => {
  newContact.value = { first_name: '', last_name: '', position: '', email: '', phone: '' }
}

const startAddingContact = () => {
  addingContact.value = true
  resetForm()
}

const cancelAddingContact = () => {
  addingContact.value = false
  resetForm()
}

const saveContact = async () => {
  try {
    await http.post(route('customers.contacts.store', props.customerId), newContact.value)
    router.reload({ only: ['customer'] }) // Only refresh customer data
    addingContact.value = false
    toast.add({ severity: 'success', summary: 'Success', detail: 'Contact added successfully', life: 3000 })
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: error.response?.data?.message || 'Failed to add contact',
      life: 3000,
    })
  }
}
</script>
