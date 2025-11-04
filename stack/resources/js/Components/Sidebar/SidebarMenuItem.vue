<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useCompanyContext } from '@/composables/useCompanyContext'

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  depth: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['hover'])

const { hasPermission } = useCompanyContext()
const page = usePage()

const depth = computed(() => props.depth ?? 0)
const isNested = computed(() => depth.value > 0)

const visibleChildren = computed(() => {
  if (!props.item.children) return []
  return props.item.children.filter(child => {
    if (!child.permission) return true
    return hasPermission(child.permission)
  })
})

const hasChildren = computed(() => visibleChildren.value.length > 0)

function buildHref() {
  if (props.item.path) return props.item.path
  return '#'
}

const isActive = computed(() => {
  const currentPath = page.props.url || window.location.pathname

  const matchesSelf = props.item.path ? currentPath.startsWith(props.item.path) : false
  const matchesChild = hasChildren.value
    ? visibleChildren.value.some(child => child.path && currentPath.startsWith(child.path))
    : false

  return matchesSelf || matchesChild
})

const handleClick = (event) => {
  if (hasChildren.value && (!props.item.path || props.item.path === '#')) {
    event.preventDefault()
  }
}

const hasPermissionToShow = computed(() => {
  if (!props.item.permission) return true
  return hasPermission(props.item.permission)
})

const linkClasses = computed(() => {
  return [
    'sidebar-link',
    'sidebar-link--compact',
    isActive.value ? 'sidebar-link--active' : 'sidebar-link--idle',
    hasChildren.value ? 'sidebar-link--expandable' : null,
    isNested.value ? 'sidebar-link--nested' : null
  ].filter(Boolean)
})

const labelClasses = computed(() => ({
  'sidebar-label': true,
  'sidebar-label--visible': false // Never show labels (always collapsed)
}))

const iconWrapperClasses = computed(() => {
  return [
    'sidebar-icon-wrapper',
    isActive.value ? 'sidebar-icon-wrapper--active' : 'sidebar-icon-wrapper--idle'
  ]
})

const handleMouseEnter = () => {
  emit('hover', props.item)
}

const handleFocus = () => {
  emit('hover', props.item)
}
</script>

<template>
  <li
    v-if="hasPermissionToShow"
    class="sidebar-item"
    role="none"
    @mouseenter="handleMouseEnter"
    @focusin="handleFocus"
  >
    <Link
      v-if="!hasChildren"
      :href="buildHref()"
      :class="linkClasses"
      @click="handleClick"
      :aria-label="props.item.label"
      role="menuitem"
    >
      <div class="sidebar-link-content">
        <span :class="iconWrapperClasses">
          <i v-if="props.item.icon" :class="`fas fa-${props.item.icon} sidebar-icon`" />
        </span>
      </div>
    </Link>

    <button
      v-else
      type="button"
      :class="linkClasses"
      @click="handleClick"
      :aria-label="props.item.label"
      role="menuitem"
    >
      <div class="sidebar-link-content">
        <span :class="iconWrapperClasses">
          <i v-if="props.item.icon" :class="`fas fa-${props.item.icon} sidebar-icon`" />
        </span>
      </div>
    </button>
  </li>
</template>

<style scoped>
.sidebar-item {
  width: 100%;
  display: flex;
  justify-content: center;
}

.sidebar-link {
  position: relative;
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px 0;
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  text-decoration: none;
  transition: color 0.15s ease;
  overflow: visible;
}

.sidebar-link--compact {
  justify-content: center;
  padding: 12px 0;
}

.sidebar-link-content {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.sidebar-link:focus-visible {
  outline: none;
}

.sidebar-link--idle {
  color: var(--sidebar-icon-idle, #94a3b8);
}

.dark .sidebar-link--idle {
  color: rgba(226, 232, 240, 0.7);
}

.sidebar-link--active {
  color: #1d4ed8;
}

.sidebar-icon-wrapper {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 16px;
  transition: background-color 0.18s ease, box-shadow 0.18s ease, color 0.18s ease;
}

.sidebar-icon-wrapper--idle {
  background: rgba(148, 163, 184, 0.12);
}

.dark .sidebar-icon-wrapper--idle {
  background: rgba(148, 163, 184, 0.2);
}

.sidebar-link--idle:hover .sidebar-icon-wrapper--idle {
  background: rgba(148, 163, 184, 0.24);
}

.sidebar-icon-wrapper--active {
  background: linear-gradient(135deg, rgba(96, 165, 250, 0.25), rgba(37, 99, 235, 0.45));
  color: #1d4ed8;
  box-shadow: 0 12px 22px rgba(37, 99, 235, 0.25);
}

.sidebar-link:focus-visible .sidebar-icon-wrapper {
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.4);
}

.sidebar-icon {
  font-size: 1.1rem;
  color: currentColor;
  width: 1.1rem;
  height: 1.1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  opacity: 1;
  visibility: visible;
}
</style>
