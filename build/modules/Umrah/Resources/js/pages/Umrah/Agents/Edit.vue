<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Save, Users } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string }
  agent: any
  countries: Record<string, string>
  companyUsers: Array<{ id: string; name: string; email: string; role: string }>
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Agents', href: `/${props.company.slug}/umrah/agents` },
  { title: props.agent.name, href: `/${props.company.slug}/umrah/agents/${props.agent.id}` },
  { title: 'Edit', href: `/${props.company.slug}/umrah/agents/${props.agent.id}/edit` },
]

const form = useForm({
  agent_number: props.agent.agent_number || '',
  user_id: props.agent.user_id || 'none',
  name: props.agent.name || '',
  phone: props.agent.phone || '',
  email: props.agent.email || '',
  city: props.agent.city || '',
  country: props.agent.country || 'Pakistan',
  notes: props.agent.notes || '',
})

const submit = () => form
  .transform((data) => ({
    ...data,
    user_id: data.user_id === 'none' ? null : data.user_id,
  }))
  .put(`/${props.company.slug}/umrah/agents/${props.agent.id}`, {
    onSuccess: () => toast.success('Agent updated successfully'),
    onError: () => toast.error('Failed to update agent'),
  })
</script>

<template>
  <Head :title="`Edit ${agent.name}`" />
  <PageShell title="Edit Agent" description="Update agent details used for future groups." :breadcrumbs="breadcrumbs" :icon="Users">
    <Card class="mx-auto max-w-2xl">
      <CardHeader><CardTitle>Agent Details</CardTitle></CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Agent #</Label>
              <Input v-model="form.agent_number" />
              <p v-if="form.errors.agent_number" class="text-xs text-destructive">{{ form.errors.agent_number }}</p>
            </div>
            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="form.name" required />
              <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
            </div>
            <div class="space-y-2">
              <Label>Phone</Label>
              <Input v-model="form.phone" />
              <p v-if="form.errors.phone" class="text-xs text-destructive">{{ form.errors.phone }}</p>
            </div>
            <div class="space-y-2">
              <Label>Email</Label>
              <Input v-model="form.email" type="email" />
              <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
            </div>
            <div class="space-y-2 md:col-span-2">
              <Label>Login user</Label>
              <Select v-model="form.user_id">
                <SelectTrigger><SelectValue placeholder="Optional login user" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">No login access</SelectItem>
                  <SelectItem v-for="user in companyUsers" :key="user.id" :value="user.id">
                    {{ user.name }} · {{ user.email }} · {{ user.role }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-xs text-muted-foreground">Link a company member if this agent should create their own vouchers.</p>
              <p v-if="form.errors.user_id" class="text-xs text-destructive">{{ form.errors.user_id }}</p>
            </div>
            <div class="space-y-2 md:col-span-2">
              <Label>City</Label>
              <Input v-model="form.city" />
              <p v-if="form.errors.city" class="text-xs text-destructive">{{ form.errors.city }}</p>
            </div>
            <div class="space-y-2 md:col-span-2">
              <Label>Country</Label>
              <Select v-model="form.country">
                <SelectTrigger><SelectValue placeholder="Select country" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="(label, value) in countries" :key="value" :value="value">{{ label }}</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.country" class="text-xs text-destructive">{{ form.errors.country }}</p>
            </div>
            <div class="space-y-2 md:col-span-2">
              <Label>Notes</Label>
              <Textarea v-model="form.notes" />
              <p v-if="form.errors.notes" class="text-xs text-destructive">{{ form.errors.notes }}</p>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t pt-4">
            <Button type="button" variant="outline" @click="router.get(`/${company.slug}/umrah/agents/${agent.id}`)">Cancel</Button>
            <Button type="submit" :disabled="form.processing">
              <Save class="mr-2 h-4 w-4" />
              Save Changes
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </PageShell>
</template>
