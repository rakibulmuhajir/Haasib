<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const currentYear = new Date().getFullYear()
const appName = computed(() => (page.props as any).name || 'Haasib')
</script>

<template>
  <footer class="border-t border-footer-border/80 bg-footer/95 py-4 px-4 shadow-[0_-14px_40px_-32px_rgba(0,0,0,0.35)] backdrop-blur-lg lg:px-6">
    <div class="flex flex-col gap-3 text-xs text-footer-foreground sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-2 text-text-tertiary">
        <span class="font-medium text-text-secondary">{{ appName }}</span>
        <span class="hidden sm:inline">&bull;</span>
        <span>&copy; {{ currentYear }}</span>
      </div>

      <div class="flex flex-wrap items-center gap-4">
        <slot name="links">
          <a href="#" class="hover:text-text-secondary transition-colors">Status</a>
          <a href="#" class="hover:text-text-secondary transition-colors">Docs</a>
          <a href="mailto:support@haasib.com" class="hover:text-text-secondary transition-colors">Support</a>
          <a href="#" class="hover:text-text-secondary transition-colors">Changelog</a>
        </slot>
      </div>
    </div>
  </footer>
</template>
