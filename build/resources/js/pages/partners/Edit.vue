<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { UsersRound, Save, ArrowLeft } from 'lucide-vue-next'

interface Partner {
  id: string
  name: string
  phone: string | null
  email: string | null
  cnic: string | null
  address: string | null
  profit_share_percentage: number
  drawing_limit_period: string
  drawing_limit_amount: number | null
  drawing_account_id: string | null
  is_active: boolean
}

interface EquityAccount {
  id: string
  code: string
  name: string
}

const props = defineProps<{
  partner: Partner
  equityAccounts: EquityAccount[]
  currency: string
}>()

const page = usePage()
const companySlug = computed(() => {
  const slug = (page.props as any)?.auth?.currentCompany?.slug as string | undefined
  if (slug) return slug
  const match = page.url.match(/^\/([^/]+)/)
  return match ? match[1] : ''
})

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Dashboard', href: `/${companySlug.value}` },
  { title: 'Partners', href: `/${companySlug.value}/partners` },
  { title: props.partner.name, href: `/${companySlug.value}/partners/${props.partner.id}` },
  { title: 'Edit', href: `/${companySlug.value}/partners/${props.partner.id}/edit` },
])

const form = useForm({
  name: props.partner.name,
  phone: props.partner.phone || '',
  email: props.partner.email || '',
  cnic: props.partner.cnic || '',
  address: props.partner.address || '',
  profit_share_percentage: props.partner.profit_share_percentage,
  drawing_limit_period: props.partner.drawing_limit_period,
  drawing_limit_amount: props.partner.drawing_limit_amount,
  drawing_account_id: props.partner.drawing_account_id || '',
  is_active: props.partner.is_active,
})

const submit = () => {
  form.put(`/${companySlug.value}/partners/${props.partner.id}`, {
    preserveScroll: true,
  })
}

const goBack = () => {
  router.get(`/${companySlug.value}/partners/${props.partner.id}`)
}
</script>

<template>
  <Head :title="`Edit ${partner.name}`" />

  <PageShell
    :title="`Edit ${partner.name}`"
    description="Update partner details and profit sharing configuration."
    :icon="UsersRound"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="goBack">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </template>

    <form @submit.prevent="submit" class="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Partner Information</CardTitle>
          <CardDescription>Basic details about the business partner.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="name">Name <span class="text-destructive">*</span></Label>
              <Input
                id="name"
                v-model="form.name"
                placeholder="Partner name"
                :class="{ 'border-destructive': form.errors.name }"
              />
              <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="phone">Phone</Label>
              <Input
                id="phone"
                v-model="form.phone"
                placeholder="Phone number"
                :class="{ 'border-destructive': form.errors.phone }"
              />
              <p v-if="form.errors.phone" class="text-sm text-destructive">{{ form.errors.phone }}</p>
            </div>

            <div class="space-y-2">
              <Label for="email">Email</Label>
              <Input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="Email address"
                :class="{ 'border-destructive': form.errors.email }"
              />
              <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-2">
              <Label for="cnic">CNIC</Label>
              <Input
                id="cnic"
                v-model="form.cnic"
                placeholder="National ID number"
                :class="{ 'border-destructive': form.errors.cnic }"
              />
              <p v-if="form.errors.cnic" class="text-sm text-destructive">{{ form.errors.cnic }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="address">Address</Label>
            <Textarea
              id="address"
              v-model="form.address"
              placeholder="Full address"
              rows="2"
              :class="{ 'border-destructive': form.errors.address }"
            />
            <p v-if="form.errors.address" class="text-sm text-destructive">{{ form.errors.address }}</p>
          </div>

          <div class="flex items-center gap-3 rounded-lg border border-border/70 bg-muted/40 px-4 py-3">
            <div class="flex-1">
              <Label for="is_active">Active</Label>
              <p class="text-sm text-text-secondary">Inactive partners won't appear in daily close.</p>
            </div>
            <Switch id="is_active" v-model:checked="form.is_active" />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Profit Sharing</CardTitle>
          <CardDescription>Configure the partner's share in profits.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="profit_share_percentage">Profit Share Percentage <span class="text-destructive">*</span></Label>
              <div class="relative">
                <Input
                  id="profit_share_percentage"
                  v-model.number="form.profit_share_percentage"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  placeholder="0"
                  :class="{ 'border-destructive': form.errors.profit_share_percentage }"
                />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground">%</span>
              </div>
              <p v-if="form.errors.profit_share_percentage" class="text-sm text-destructive">{{ form.errors.profit_share_percentage }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Drawing Limits</CardTitle>
          <CardDescription>Set withdrawal limits for the partner.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="drawing_limit_period">Limit Period <span class="text-destructive">*</span></Label>
              <Select v-model="form.drawing_limit_period">
                <SelectTrigger :class="{ 'border-destructive': form.errors.drawing_limit_period }">
                  <SelectValue placeholder="Select period" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">No Limit</SelectItem>
                  <SelectItem value="monthly">Monthly</SelectItem>
                  <SelectItem value="yearly">Yearly</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.drawing_limit_period" class="text-sm text-destructive">{{ form.errors.drawing_limit_period }}</p>
            </div>

            <div class="space-y-2">
              <Label for="drawing_limit_amount">Limit Amount</Label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">{{ currency }}</span>
                <Input
                  id="drawing_limit_amount"
                  v-model.number="form.drawing_limit_amount"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="0"
                  class="pl-14"
                  :disabled="form.drawing_limit_period === 'none'"
                  :class="{ 'border-destructive': form.errors.drawing_limit_amount }"
                />
              </div>
              <p v-if="form.errors.drawing_limit_amount" class="text-sm text-destructive">{{ form.errors.drawing_limit_amount }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <div class="flex justify-end gap-4">
        <Button type="button" variant="outline" @click="goBack" :disabled="form.processing">
          Cancel
        </Button>
        <Button type="submit" :disabled="form.processing">
          <span
            v-if="form.processing"
            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
          />
          <Save v-else class="mr-2 h-4 w-4" />
          Save Changes
        </Button>
      </div>
    </form>
  </PageShell>
</template>
