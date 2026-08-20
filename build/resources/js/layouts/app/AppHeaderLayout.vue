<script setup lang="ts">
/**
 * The horizontal shell's layout.
 *
 * It was a three-element stub — shell, header, content — which is why swapping
 * to it was never as cheap as "change one import": it dropped the toast outlet
 * and the footer, so every flash message in the app would have gone nowhere.
 * Parity with AppSidebarLayout is the requirement, minus the sidebar.
 */
import AppFooter from '@/components/AppFooter.vue'
import AppHeader from '@/components/AppHeader.vue'
import Sonner from '@/components/ui/sonner/Sonner.vue'
import type { BreadcrumbItemType } from '@/types'

withDefaults(
  defineProps<{
    breadcrumbs?: BreadcrumbItemType[]
    showFooter?: boolean
    fullWidth?: boolean
  }>(),
  {
    breadcrumbs: () => [],
    showFooter: true,
    fullWidth: false,
  },
)
</script>

<template>
  <div class="flex min-h-screen flex-col bg-surface-canvas">
    <AppHeader :breadcrumbs="breadcrumbs">
      <template #actions>
        <slot name="header-actions" />
      </template>
    </AppHeader>

    <main class="flex flex-1 flex-col px-4 pt-4 pb-8 lg:px-8">
      <div
        class="mx-auto flex w-full flex-1 flex-col gap-4"
        :class="fullWidth ? 'max-w-none' : 'max-w-7xl'"
      >
        <!-- Rules, not elevation: the page sections are separated by borders
             on the canvas rather than floated on shadowed rounded cards. -->
        <section
          v-if="$slots.hero"
          class="rounded-md border border-rule-default bg-surface-raised p-4"
        >
          <slot name="hero" />
        </section>

        <section
          class="flex flex-1 flex-col rounded-md border border-rule-default bg-surface-raised p-4"
        >
          <slot />
        </section>
      </div>
    </main>

    <AppFooter v-if="showFooter">
      <template #links>
        <slot name="footer-links" />
      </template>
    </AppFooter>

    <Sonner />
  </div>
</template>
