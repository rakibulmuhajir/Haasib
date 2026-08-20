<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { formatDateTime } from '@/lib/datetime'
import { CheckCircle2, XCircle, Mail } from 'lucide-vue-next'

interface InvitationProps {
  id: string
  email: string
  role: string
  status: string
  expires_at: string
  is_expired: boolean
  is_valid: boolean
  company: {
    id: string
    name: string
    slug: string
  }
  inviter: {
    name: string
    email: string
  }
}

const props = defineProps<{
  invitation: InvitationProps
  token: string
}>()

const acceptForm = useForm({})
const rejectForm = useForm({})
const formatDate = (value: string) => formatDateTime(value, { mode: 'date' })

const handleAccept = () => {
  acceptForm.post(`/invite/${props.token}/accept`, {
    preserveScroll: true,
    onSuccess: () => {
      // Success toast handled via flash on redirect
    },
  })
}

const handleReject = () => {
  rejectForm.post(`/invite/${props.token}/reject`, {
    preserveScroll: true,
    onSuccess: () => {
      // Success toast handled via flash on redirect
    },
  })
}
</script>

<template>
  <Head title="Accept Invitation" />
  <div class="flex min-h-screen items-center justify-center bg-surface-canvas px-4 py-10">
    <div class="w-full max-w-xl">
      <Card variant="detail" class="border-rule-default">
        <CardHeader class="space-y-2">
          <CardTitle class="text-text-primary">Invitation to join {{ invitation.company.name }}</CardTitle>
          <CardDescription class="text-text-secondary">
            You were invited by {{ invitation.inviter.name }} ({{ invitation.inviter.email }})
          </CardDescription>
          <div class="flex items-center gap-2 text-sm text-text-secondary">
            <Mail class="h-4 w-4" />
            <span>{{ invitation.email }}</span>
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <Alert v-if="invitation.is_expired || !invitation.is_valid" variant="destructive">
            <AlertTitle class="flex items-center gap-2">
              <XCircle class="h-4 w-4" />
              Invitation not valid
            </AlertTitle>
            <AlertDescription>This invitation has expired or is no longer valid.</AlertDescription>
          </Alert>

          <div class="flex items-center justify-between rounded-lg border border-rule-default bg-surface-sunken px-4 py-3">
            <div>
              <div class="text-sm text-text-secondary">Role</div>
              <div class="text-base font-medium text-text-primary capitalize">{{ invitation.role }}</div>
            </div>
            <div class="text-right">
              <div class="text-sm text-text-secondary">Expires</div>
              <div class="text-base font-medium text-text-primary">
                {{ formatDate(invitation.expires_at) }}
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <Button
              variant="outline"
              @click="handleReject"
              :disabled="rejectForm.processing || invitation.is_expired || !invitation.is_valid"
            >
              Reject
            </Button>
            <Button
              @click="handleAccept"
              :disabled="acceptForm.processing || invitation.is_expired || !invitation.is_valid"
            >
              <CheckCircle2 v-if="acceptForm.processing" class="mr-2 h-4 w-4 animate-spin" />
              Accept Invitation
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
