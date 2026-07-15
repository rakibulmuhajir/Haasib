<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

defineProps<{
  currentPage: number
  lastPage: number
  from?: number | null
  to?: number | null
  total?: number
  previousUrl?: string | null
  nextUrl?: string | null
}>()

const openPage = (url?: string | null) => {
  if (url) router.get(url, {}, { preserveState: true, preserveScroll: true })
}
</script>

<template>
  <div v-if="lastPage > 1" class="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-sm text-muted-foreground">
      <template v-if="from && to && total">Showing {{ from }}-{{ to }} of {{ total }}</template>
      <template v-else>Page {{ currentPage }} of {{ lastPage }}</template>
    </p>
    <div class="flex items-center gap-2">
      <Button type="button" variant="outline" size="sm" :disabled="!previousUrl" @click="openPage(previousUrl)">
        <ChevronLeft class="mr-1 h-4 w-4" />Previous
      </Button>
      <Button type="button" variant="outline" size="sm" :disabled="!nextUrl" @click="openPage(nextUrl)">
        Next<ChevronRight class="ml-1 h-4 w-4" />
      </Button>
    </div>
  </div>
</template>
