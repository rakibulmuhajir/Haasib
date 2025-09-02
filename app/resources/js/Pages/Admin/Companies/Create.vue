<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { http } from '@/lib/http'
import CurrencyPicker from '@/Components/Pickers/CurrencyPicker.vue'
import LanguagePicker from '@/Components/Pickers/LanguagePicker.vue'
import LocalePicker from '@/Components/Pickers/LocalePicker.vue'

const form = ref({ name: '', base_currency: '', language: '', locale: '' })
const loading = ref(false)
const error = ref('')
const created = ref(null)

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.post('/api/v1/companies', form.value)
    created.value = data.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to create company'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Head title="Create Company" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Create Company</h2>
        <Link :href="route('admin.companies.index')" class="text-sm text-gray-600 hover:underline">Back to companies</Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow sm:rounded-md p-6">
          <div v-if="created" class="mb-4 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-700">
            Company created: <span class="font-medium">{{ created.name }}</span>
          </div>
          <div v-if="error" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>

          <div class="space-y-4">
            <div>
              <InputLabel value="Name" />
              <TextInput v-model="form.name" class="mt-1 block w-full" placeholder="Acme LLC" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <InputLabel value="Base Currency" />
                <CurrencyPicker v-model="form.base_currency" />
              </div>
              <div>
                <InputLabel value="Language" />
                <LanguagePicker v-model="form.language" />
              </div>
              <div>
                <InputLabel value="Locale" />
                <LocalePicker v-model="form.locale" :language="form.language" />
              </div>
            </div>

            <div class="pt-2 flex items-center gap-2">
              <PrimaryButton @click="submit" :disabled="loading">Create</PrimaryButton>
              <span v-if="loading" class="text-sm text-gray-500">Saving…</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
