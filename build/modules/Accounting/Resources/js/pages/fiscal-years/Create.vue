<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import type { BreadcrumbItem } from '@/types'
import { CalendarDays, Save } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface ExistingFiscalYear {
  id: string
  name: string
  start_date: string
  end_date: string
}

const props = defineProps<{
  company: CompanyRef
  existingFiscalYears: ExistingFiscalYear[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fiscal Years', href: `/${props.company.slug}/fiscal-years` },
  { title: 'Create', href: `/${props.company.slug}/fiscal-years/create` },
]

const form = useForm({
  name: '',
  start_date: '',
  end_date: '',
  period_type: 'monthly',
  auto_create_periods: true,
})

const submit = () => {
  form.post(`/${props.company.slug}/fiscal-years`, { preserveScroll: true })
}
</script>

<template>
  <Head title="Create Fiscal Year" />
  <PageShell
    title="Create Fiscal Year"
    :breadcrumbs="breadcrumbs"
    :icon="CalendarDays"
  >
    <div class="grid gap-6 lg:grid-cols-3">
      <Card class="lg:col-span-2">
        <CardHeader>
          <CardTitle>Fiscal year details</CardTitle>
        </CardHeader>
        <CardContent>
          <form class="space-y-6" @submit.prevent="submit">
            <div>
              <Label for="name">Name</Label>
              <Input id="name" v-model="form.name" placeholder="FY 2025" />
              <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <Label for="start_date">Start date</Label>
                <Input id="start_date" type="date" v-model="form.start_date" />
                <p v-if="form.errors.start_date" class="text-sm text-red-600 mt-1">{{ form.errors.start_date }}</p>
              </div>
              <div>
                <Label for="end_date">End date</Label>
                <Input id="end_date" type="date" v-model="form.end_date" />
                <p v-if="form.errors.end_date" class="text-sm text-red-600 mt-1">{{ form.errors.end_date }}</p>
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 items-end">
              <div>
                <Label for="period_type">Period type</Label>
                <Select v-model="form.period_type">
                  <SelectTrigger id="period_type">
                    <SelectValue placeholder="Select period type" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="monthly">Monthly</SelectItem>
                    <SelectItem value="quarterly">Quarterly</SelectItem>
                    <SelectItem value="yearly">Yearly</SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="form.errors.period_type" class="text-sm text-red-600 mt-1">{{ form.errors.period_type }}</p>
              </div>

              <div class="flex items-center justify-between rounded-lg border p-3">
                <div class="space-y-1">
                  <p class="text-sm font-medium">Auto-create periods</p>
                  <p class="text-xs text-muted-foreground">Generate periods immediately after creation</p>
                </div>
                <Switch v-model:checked="form.auto_create_periods" />
              </div>
            </div>

            <div class="flex justify-end gap-3">
              <Button type="button" variant="outline" @click="router.get(`/${company.slug}/fiscal-years`)">
                Cancel
              </Button>
              <Button type="submit" :disabled="form.processing">
                <Save class="mr-2 h-4 w-4" />
                Create
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Existing</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-if="existingFiscalYears.length === 0" class="text-sm text-muted-foreground">
            No fiscal years yet.
          </div>
          <div
            v-for="fy in existingFiscalYears"
            :key="fy.id"
            class="rounded-md border p-3"
          >
            <div class="font-medium">{{ fy.name }}</div>
            <div class="text-xs text-muted-foreground">
              {{ new Date(fy.start_date).toLocaleDateString() }} → {{ new Date(fy.end_date).toLocaleDateString() }}
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </PageShell>
</template>

