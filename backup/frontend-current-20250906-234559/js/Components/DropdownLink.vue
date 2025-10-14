<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  href: { type: String, required: true },
})

// Forward non-prop attributes (e.g., method, as, data, preserveScroll)
defineOptions({ inheritAttrs: false })
</script>

<template>
  <Link
    v-bind="$attrs"
    :href="props.href"
    class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
  >
    <slot />
  </Link>
</template>
