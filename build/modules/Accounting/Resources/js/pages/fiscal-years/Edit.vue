<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import type { BreadcrumbItem } from '@/types'
import { CalendarDays, Save, Trash2 } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface FiscalYear {
  id: string
  name: string
  start_date: string
  end_date: string
  is_current: boolean
  is_closed: boolean
  periods_count?: number
}

const props = defineProps<{
  company: CompanyRef
  fiscalYear: FiscalYear
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Fiscal Years', href: `/${props.company.slug}/fiscal-years` },
  { title: props.fiscalYear.name, href: `/${props.company.slug}/fiscal-years/${props.fiscalYear.id}` },
  { title: 'Edit', href: `/${props.company.slug}/fiscal-years/${props.fiscalYear.id}/edit` },
]

const hasPeriods = computed(() => (props.fiscalYear.periods_count ?? 0) > 0)

const form = useForm({
  name: props.fiscalYear.name,
  start_date: props.fiscalYear.start_date,
  end_date: props.fiscalYear.end_date,
  is_current: props.fiscalYear.is_current,
})

const submit = () => {
  form.put(`/${props.company.slug}/fiscal-years/${props.fiscalYear.id}`, { preserveScroll: true })
}

const destroy = () => {
  router.delete(`/${props.company.slug}/fiscal-years/${props.fiscalYear.id}`, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Edit ${fiscalYear.name}`" />
  <PageShell
    :title="`Edit fiscal year`"
    :breadcrumbs="breadcrumbs"
    :icon="CalendarDays"
  >
    <Card>
      <CardHeader>
        <CardTitle>Fiscal year</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <div>
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" />
            <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <Label for="start_date">Start date</Label>
              <Input id="start_date" type="date" v-model="form.start_date" :disabled="hasPeriods" />
              <p v-if="hasPeriods" class="text-xs text-muted-foreground mt-1">
                Dates are locked after periods are created.
              </p>
              <p v-if="form.errors.start_date" class="text-sm text-red-600 mt-1">{{ form.errors.start_date }}</p>
            </div>
            <div>
              <Label for="end_date">End date</Label>
              <Input id="end_date" type="date" v-model="form.end_date" :disabled="hasPeriods" />
              <p v-if="form.errors.end_date" class="text-sm text-red-600 mt-1">{{ form.errors.end_date }}</p>
            </div>
          </div>

          <div class="flex items-center justify-between rounded-lg border p-3">
            <div class="space-y-1">
              <p class="text-sm font-medium">Set as current fiscal year</p>
              <p class="text-xs text-muted-foreground">Marks this as the active fiscal year for the company</p>
            </div>
            <Switch v-model:checked="form.is_current" />
          </div>

          <div class="flex justify-between gap-3">
            <Button
              type="button"
              variant="destructive"
              @click="destroy"
            >
              <Trash2 class="mr-2 h-4 w-4" />
              Delete
            </Button>
            <div class="flex gap-3">
              <Button type="button" variant="outline" @click="router.get(`/${company.slug}/fiscal-years/${fiscalYear.id}`)">
                Back
              </Button>
              <Button type="submit" :disabled="form.processing">
                <Save class="mr-2 h-4 w-4" />
                Save
              </Button>
            </div>
          </div>
        </form>
      </CardContent>
    </Card>
  </PageShell>
</template>

