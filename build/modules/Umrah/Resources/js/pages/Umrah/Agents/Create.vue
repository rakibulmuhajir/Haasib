<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { BreadcrumbItem } from '@/types'
import { Save, Users } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const props = defineProps<{
  company: { slug: string }
  nextAgentNumber: string
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Umrah', href: `/${props.company.slug}/umrah` },
  { title: 'Agents', href: `/${props.company.slug}/umrah/agents` },
  { title: 'New Agent', href: `/${props.company.slug}/umrah/agents/create` },
]

const form = useForm({
  agent_number: props.nextAgentNumber,
  name: '',
  phone: '',
  email: '',
  city: '',
  notes: '',
})

const submit = () => form.post(`/${props.company.slug}/umrah/agents`, {
  onSuccess: () => toast.success('Agent created successfully'),
  onError: () => toast.error('Failed to create agent'),
})
</script>

<template>
  <Head title="New Agent" />
  <PageShell title="New Agent" description="Add the agent who sends passports or groups." :breadcrumbs="breadcrumbs" :icon="Users">
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
            </div>
            <div class="space-y-2">
              <Label>Email</Label>
              <Input v-model="form.email" type="email" />
            </div>
            <div class="space-y-2 md:col-span-2">
              <Label>City</Label>
              <Input v-model="form.city" />
            </div>
            <div class="space-y-2 md:col-span-2">
              <Label>Notes</Label>
              <Textarea v-model="form.notes" />
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t pt-4">
            <Button type="button" variant="outline" @click="router.get(`/${company.slug}/umrah/agents`)">Cancel</Button>
            <Button type="submit" :disabled="form.processing"><Save class="mr-2 h-4 w-4" />Save Agent</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </PageShell>
</template>
