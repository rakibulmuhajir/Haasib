<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const props = defineProps<{
    company: {
        id: string
        name: string
        industry: string
        country: string
        base_currency: string
        settings?: any
    }
}>()

const form = useForm({
    name: props.company.name,
    industry: props.company.industry,
    country: props.company.country,
    base_currency: props.company.base_currency,
    settings: props.company.settings || {}
})

const submit = () => {
    form.put(`/companies/${props.company.id}`, {
        onSuccess: () => {
            router.visit('/companies')
        }
    })
}

</script>

<template>
  <Head :title="`Edit ${props.company.name}`" />
  
  <AppLayout>
    <div class="p-6">
      <div class="bg-white dark:bg-gray-800 rounded-lg border p-6 max-w-2xl">
        <form @submit.prevent="submit" class="space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <Label for="name">Company Name</Label>
              <Input
                id="name"
                v-model="form.name"
                type="text"
                required
                :disabled="form.processing"
                :class="{ 'border-red-500': form.errors.name }"
              />
              <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
              <Label for="industry">Industry</Label>
              <Input
                id="industry"
                v-model="form.industry"
                type="text"
                required
                :disabled="form.processing"
                :class="{ 'border-red-500': form.errors.industry }"
              />
              <p v-if="form.errors.industry" class="text-red-500 text-sm mt-1">{{ form.errors.industry }}</p>
            </div>

            <div>
              <Label for="country">Country</Label>
              <Input
                id="country"
                v-model="form.country"
                type="text"
                required
                :disabled="form.processing"
                :class="{ 'border-red-500': form.errors.country }"
              />
              <p v-if="form.errors.country" class="text-red-500 text-sm mt-1">{{ form.errors.country }}</p>
            </div>

            <div>
              <Label for="base_currency">Base Currency</Label>
              <Input
                id="base_currency"
                v-model="form.base_currency"
                type="text"
                maxlength="3"
                required
                :disabled="form.processing"
                :class="{ 'border-red-500': form.errors.base_currency }"
              />
              <p v-if="form.errors.base_currency" class="text-red-500 text-sm mt-1">{{ form.errors.base_currency }}</p>
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <Button
              type="button"
              variant="outline"
              @click="router.visit('/companies')"
              :disabled="form.processing"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Saving...' : 'Save Changes' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>