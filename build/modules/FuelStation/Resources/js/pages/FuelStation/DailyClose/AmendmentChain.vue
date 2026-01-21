<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'
import {
  CheckCircle,
  XCircle,
  RotateCcw,
  ArrowDown,
} from 'lucide-vue-next'

interface ChainItem {
  id: string
  transaction_number: string
  transaction_date: string
  created_at: string
  type: 'original' | 'reversal' | 'correction'
  status: string
  metadata: Record<string, unknown>
  amendment_reason: string | null
}

const props = defineProps<{
  chain: ChainItem[]
  currentId: string
  companySlug: string
}>()

const formatDateTime = (datetime: string) => {
  return new Date(datetime).toLocaleString('en-PK', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getTypeConfig = (type: string, status: string) => {
  if (status === 'reversed') {
    return {
      label: 'Original (Reversed)',
      variant: 'destructive' as const,
      icon: XCircle,
      bgClass: 'bg-red-50 border-red-200',
    }
  }

  const configs: Record<string, { label: string; variant: 'default' | 'secondary' | 'outline'; icon: typeof CheckCircle; bgClass: string }> = {
    original: { label: 'Original', variant: 'secondary', icon: CheckCircle, bgClass: 'bg-muted/50' },
    reversal: { label: 'Reversal', variant: 'outline', icon: RotateCcw, bgClass: 'bg-amber-50 border-amber-200' },
    correction: { label: 'Correction', variant: 'default', icon: CheckCircle, bgClass: 'bg-green-50 border-green-200' },
  }
  return configs[type] || configs.original
}

// Reverse the chain so newest is at top
const reversedChain = computed(() => [...props.chain].reverse())
</script>

<template>
  <div class="space-y-3">
    <template v-for="(item, index) in reversedChain" :key="item.id">
      <!-- Arrow between items -->
      <div v-if="index > 0" class="flex justify-center py-1">
        <ArrowDown class="h-4 w-4 text-muted-foreground" />
      </div>

      <!-- Chain Item -->
      <div
        :class="[
          'rounded-lg border p-4 transition-all',
          getTypeConfig(item.type, item.status).bgClass,
          item.id === currentId ? 'ring-2 ring-primary ring-offset-2' : '',
        ]"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-center gap-3">
            <component
              :is="getTypeConfig(item.type, item.status).icon"
              :class="[
                'h-5 w-5',
                item.status === 'reversed' ? 'text-red-600' : '',
                item.type === 'reversal' ? 'text-amber-600' : '',
                item.type === 'correction' ? 'text-green-600' : '',
              ]"
            />
            <div>
              <div class="flex items-center gap-2">
                <Link
                  v-if="item.id !== currentId"
                  :href="`/${companySlug}/fuel/daily-close/${item.id}`"
                  class="font-mono font-semibold hover:underline"
                >
                  {{ item.transaction_number }}
                </Link>
                <span v-else class="font-mono font-semibold">
                  {{ item.transaction_number }}
                </span>
                <Badge v-if="item.id === currentId" variant="outline" class="text-xs">
                  Current
                </Badge>
              </div>
              <p class="text-sm text-muted-foreground">
                {{ formatDateTime(item.created_at) }}
              </p>
            </div>
          </div>

          <Badge :variant="getTypeConfig(item.type, item.status).variant">
            {{ getTypeConfig(item.type, item.status).label }}
          </Badge>
        </div>

        <p v-if="item.amendment_reason" class="mt-2 text-sm text-muted-foreground pl-8">
          Reason: {{ item.amendment_reason }}
        </p>
      </div>
    </template>
  </div>
</template>
