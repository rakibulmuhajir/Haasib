<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import SvgIcon from '@/Components/SvgIcon.vue'

interface BreadcrumbItem {
  label: string
  url?: string
}

interface Props {
  items?: BreadcrumbItem[]
  home?: BreadcrumbItem
}

const props = withDefaults(defineProps<Props>(), {
  items: () => [],
  home: () => ({ label: 'Home', url: '/' })
})

// Generate breadcrumbs based on current route if items not provided
const page = usePage()
const breadcrumbs = computed(() => {
  if (props.items.length > 0) {
    return [props.home, ...props.items]
  }

  // Auto-generate breadcrumbs based on URL structure
  const url = page.url
  const segments = url.split('/').filter(segment => segment)

  const generatedItems: BreadcrumbItem[] = [props.home]

  segments.forEach((segment, index) => {
    const path = '/' + segments.slice(0, index + 1).join('/')
    generatedItems.push({
      label: segment.charAt(0).toUpperCase() + segment.slice(1).replace(/-/g, ' '),
      url: path
    })
  })

  return generatedItems
})
</script>

<template>
  <nav class="breadcrumb-container" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
      <li
        v-for="(item, index) in breadcrumbs"
        :key="index"
        :class="[
          'breadcrumb-item',
          { 'breadcrumb-item-active': index === breadcrumbs.length - 1 }
        ]"
      >
        <template v-if="index < breadcrumbs.length - 1 && item.url">
          <a
            :href="item.url"
            class="breadcrumb-link"
          >
            {{ item.label }}
          </a>
        </template>
        <template v-else>
          <span class="breadcrumb-current">
            {{ item.label }}
          </span>
        </template>

        <SvgIcon
          v-if="index < breadcrumbs.length - 1"
          name="chevron-right"
          set="line"
          class="breadcrumb-separator w-4 h-4"
        />
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.breadcrumb-container {
  @apply text-sm;
}

.breadcrumb-list {
  @apply flex items-center space-x-1;
}

.breadcrumb-item {
  @apply flex items-center space-x-1;
}

.breadcrumb-link {
  @apply text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100
         transition-colors duration-200;
}

.breadcrumb-current {
  @apply text-gray-900 dark:text-gray-100 font-medium;
}

.breadcrumb-separator {
  @apply text-gray-400 dark:text-gray-500;
}
</style>
