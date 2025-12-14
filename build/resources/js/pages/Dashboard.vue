<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import type { BreadcrumbItem } from '@/types'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Head, router } from '@inertiajs/vue3'
import { Mail, Building2, Clock3 } from 'lucide-vue-next'

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: dashboard().url,
  },
]

const props = defineProps<{
  pendingInvitations: {
    id: string
    token: string
    company_name: string
    company_slug: string
    role: string
    inviter_name: string
    inviter_email: string
    expires_at: string
    created_at: string
  }[]
}>()

const goToInvitation = (invitation: (typeof props.pendingInvitations)[number]) => {
  router.visit(`/invite/${invitation.token}`)
}
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6 rounded-xl">
      <Card class="border-amber-100 bg-amber-50">
        <CardHeader class="pb-3">
          <CardTitle class="flex items-center gap-2 text-amber-900">
            <Mail class="h-4 w-4" />
            Pending Invitations
          </CardTitle>
          <CardDescription class="text-amber-700">
            Invitations sent to you
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="props.pendingInvitations.length === 0" class="text-sm text-amber-700">
            You have no pending invitations.
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="invite in props.pendingInvitations"
              :key="invite.id"
              class="flex flex-col gap-1 rounded-lg border border-amber-200/80 bg-white px-4 py-3 shadow-xs"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <Building2 class="h-4 w-4 text-amber-700" />
                  <div>
                    <div class="font-medium text-amber-900">{{ invite.company_name }}</div>
                    <div class="text-xs text-amber-700">{{ invite.inviter_name }} • {{ invite.inviter_email }}</div>
                  </div>
                </div>
                <Badge variant="outline" class="capitalize text-amber-800">{{ invite.role }}</Badge>
              </div>
              <div class="flex items-center justify-between text-xs text-amber-700">
                <span class="flex items-center gap-1">
                  <Clock3 class="h-3 w-3" /> Expires {{ new Date(invite.expires_at).toLocaleDateString() }}
                </span>
                <Button variant="outline" size="sm" @click="goToInvitation(invite)">
                  View
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
