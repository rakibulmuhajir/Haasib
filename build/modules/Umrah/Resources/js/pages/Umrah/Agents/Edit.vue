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
  canManageLogins: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Agents', href: `/${props.company.slug}/umrah/agents` },
  { title: props.agent.name, href: `/${props.company.slug}/umrah/agents/${props.agent.id}` },
  { title: 'Edit', href: `/${props.company.slug}/umrah/agents/${props.agent.id}/edit` },
]

const form = useForm({
  agent_number: props.agent.agent_number || '',
  login_username: props.canManageLogins ? props.agent.user?.username || '' : '',
  password: '',
  name: props.agent.name || '',
  phone: props.agent.phone || '',
  email: props.agent.email || '',
  city: props.agent.city || '',
  country: props.agent.country || 'Pakistan',
  logo_url: props.agent.logo_url || '',
  notes: props.agent.notes || '',
})

const submit = () => form.put(`/${props.company.slug}/umrah/agents/${props.agent.id}`, {
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
              <Label>Logo URL</Label>
              <div class="flex items-center gap-3">
                <img v-if="form.logo_url" :src="form.logo_url" alt="Agent logo preview" class="h-12 w-12 rounded-md border object-contain" />
                <Input v-model="form.logo_url" type="url" placeholder="https://example.com/logo.png" />
              </div>
              <p v-if="form.errors.logo_url" class="text-xs text-destructive">{{ form.errors.logo_url }}</p>
            </div>
            <template v-if="canManageLogins">
              <div class="space-y-2"><Label>Username</Label><Input v-model="form.login_username" autocomplete="off" /><p v-if="form.errors.login_username" class="text-xs text-destructive">{{ form.errors.login_username }}</p></div>
              <div class="space-y-2"><Label>New Password</Label><Input v-model="form.password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current password" /><p v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</p></div>
            </template>
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
