<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { BreadcrumbItem } from '@/types'
import { Keyboard, Save } from 'lucide-vue-next'

interface AccountRef {
  id: string
  code: string
  name: string
  subtype: string
}

const props = defineProps<{
  company: { id: string; name: string; slug: string }
  defaults: { receive_account_id: string | null; pay_account_id: string | null }
  accounts: AccountRef[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Quick entry', href: `/${props.company.slug}/accounting/quick-entry` },
]

// The select cannot hold an empty value, so "not set" travels as a word.
const NONE = 'none'

const form = useForm({
  receive_account_id: props.defaults.receive_account_id ?? NONE,
  pay_account_id: props.defaults.pay_account_id ?? NONE,
})

const submit = () => {
  form
    .transform((d) => ({
      receive_account_id: d.receive_account_id === NONE ? null : d.receive_account_id,
      pay_account_id: d.pay_account_id === NONE ? null : d.pay_account_id,
    }))
    .patch(`/${props.company.slug}/accounting/quick-entry`, { preserveScroll: true })
}

const optionLabel = (a: AccountRef) => `${a.code} — ${a.name}`
</script>

<template>
  <Head title="Quick entry" />
  <PageShell title="Quick entry" :breadcrumbs="breadcrumbs" :icon="Keyboard">
    <Card variant="form">
      <CardHeader>
        <CardTitle>Accounts for typed entries</CardTitle>
      </CardHeader>
      <CardContent>
        <form novalidate class="space-y-6" @submit.prevent="submit">
          <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Money received goes to</Label>
              <Select v-model="form.receive_account_id">
                <SelectTrigger data-testid="receive-account">
                  <SelectValue placeholder="Not set" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="NONE">Not set</SelectItem>
                  <SelectItem v-for="a in accounts" :key="a.id" :value="a.id">{{ optionLabel(a) }}</SelectItem>
                </SelectContent>
              </Select>
              <p class="text-xs text-muted-foreground">Used by received and sale. A deposit goes to it too, when it is a bank.</p>
              <InputError :message="form.errors.receive_account_id" />
            </div>

            <div class="space-y-2">
              <Label>Money paid comes from</Label>
              <Select v-model="form.pay_account_id">
                <SelectTrigger data-testid="pay-account">
                  <SelectValue placeholder="Not set" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="NONE">Not set</SelectItem>
                  <SelectItem v-for="a in accounts" :key="a.id" :value="a.id">{{ optionLabel(a) }}</SelectItem>
                </SelectContent>
              </Select>
              <p class="text-xs text-muted-foreground">Used by paid and expense. Withdraw and bank charge use it too, when it is a bank.</p>
              <InputError :message="form.errors.pay_account_id" />
            </div>
          </div>

          <p class="text-xs text-muted-foreground">
            Naming an account in the line, like “into meezan”, always wins. Not set: the cash account, then the default bank.
          </p>

          <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">
              <Save class="mr-2 h-4 w-4" />
              Save
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </PageShell>
</template>
