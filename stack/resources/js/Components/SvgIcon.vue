``<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: {
    type: String,
    required: true
  },
  set: {
    type: String,
    default: 'line' // 'line', 'solid', 'duotone', etc.
  },
  size: {
    type: String,
    default: '1em'
  },
  color: {
    type: String,
    default: 'currentColor'
  },
  monochrome: {
    type: Boolean,
    default: true
  }
})

// SVG icon mapping - using simple, proven SVG paths
const iconMap = {
  // Navigation - Simple filled icons for better visibility
  'home': '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="currentColor"></path>',
  'building': '<path d="M3 21h18v-2H3v2zm0-4h18v-2H3v2zm0-4h18v-2H3v2zm0-4h18V7H3v2zm0-4h18V3H3v2z" fill="currentColor"></path>',
  'file-text': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="currentColor"></path>',
  'calculator': '<rect x="4" y="2" width="16" height="20" rx="2" fill="currentColor"></rect>',
  'chart-bar': '<rect x="4" y="10" width="4" height="10" fill="currentColor"></rect><rect x="10" y="4" width="4" height="16" fill="currentColor"></rect><rect x="16" y="8" width="4" height="12" fill="currentColor"></rect>',
  'cog': '<circle cx="12" cy="12" r="8" fill="currentColor"></circle><circle cx="12" cy="12" r="3" fill="white"></circle>',
  'users': '<circle cx="8" cy="8" r="4" fill="currentColor"></circle><path d="M16 20v-4H4v4h12z" fill="currentColor"></path><circle cx="16" cy="8" r="4" fill="currentColor"></circle>',
  'credit-card': '<rect x="2" y="6" width="20" height="12" rx="2" fill="currentColor"></rect>',
  'plus': '<path d="M12 4v16M4 12h16" stroke="currentColor" stroke-width="3" fill="none"></path>',
  'list': '<line x1="6" y1="8" x2="18" y2="8" stroke="currentColor" stroke-width="2"></line><line x1="6" y1="12" x2="18" y2="12" stroke="currentColor" stroke-width="2"></line><line x1="6" y1="16" x2="18" y2="16" stroke="currentColor" stroke-width="2"></line>',
  'book': '<path d="M4 4h12v16H4V4zm2 2v12h8V6H6z" fill="currentColor"></path>',
  'pie-chart': '<circle cx="12" cy="12" r="10" fill="currentColor"></circle>',
  'settings': '<circle cx="12" cy="12" r="6" fill="currentColor"></circle>',
  'download': '<path d="M12 2v12M8 10l4 4 4-4" stroke="currentColor" stroke-width="2" fill="none"></path><rect x="4" y="16" width="16" height="4" fill="currentColor"></rect>',
  'arrow-left': '<path d="M16 4l-8 8 8 8" stroke="currentColor" stroke-width="2" fill="none"></path>',
  'arrow-right': '<path d="M8 4l8 8-8 8" stroke="currentColor" stroke-width="2" fill="none"></path>',
  'chevron-down': '<path d="M6 8l6 6 6-6" stroke="currentColor" stroke-width="2" fill="none"></path>',
  'chevron-right': '<path d="M8 6l6 6-6 6" stroke="currentColor" stroke-width="2" fill="none"></path>',
  'chevron-up': '<path d="M6 16l6-6 6 6" stroke="currentColor" stroke-width="2" fill="none"></path>',
  'chevron-left': '<path d="M16 6l-6 6 6 6" stroke="currentColor" stroke-width="2" fill="none"></path>',
  'lock': '<rect x="6" y="10" width="12" height="8" rx="2" fill="currentColor"></rect><path d="M8 10V8a4 4 0 0 1 8 0v2" stroke="currentColor" stroke-width="2" fill="none"></path>',
  'bell': '<path d="M12 2a10 10 0 0 0-10 10c0 4 2 8 2 8h16s2-4 2-8A10 10 0 0 0 12 2z" fill="currentColor"></path>',
  'dollar': '<path d="M12 2v20M4 8h16M4 16h16" stroke="currentColor" stroke-width="2" fill="none"></path>',
  'danger': '<circle cx="12" cy="12" r="10" fill="currentColor"></circle>',
  'trash': '<path d="M6 6h12v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6z" fill="currentColor"></path>',

  // Additional icons needed for sidebar - simplified versions
  'tachometer-alt': '<circle cx="12" cy="12" r="10" fill="currentColor"></circle>',
  'hand-holding-usd': '<circle cx="12" cy="12" r="10" fill="currentColor"></circle>',
  'file-invoice': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="currentColor"></path>',
  'university': '<path d="M12 2L2 7v10h20V7L12 2z" fill="currentColor"></path>',
  'balance-scale': '<circle cx="12" cy="12" r="10" fill="currentColor"></circle>',
  'file-import': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="currentColor"></path>',
  'file-alt': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="currentColor"></path>',
  'balance-scale-left': '<circle cx="12" cy="12" r="10" fill="currentColor"></circle>',
  'ledger': '<rect x="4" y="4" width="16" height="16" fill="currentColor"></rect>',
  'calendar-check': '<rect x="4" y="4" width="16" height="16" rx="2" fill="currentColor"></rect>',
  'tasks': '<rect x="4" y="4" width="16" height="16" fill="currentColor"></rect>',
  'history': '<circle cx="12" cy="12" r="10" fill="currentColor"></circle>',
  'shield-alt': '<path d="M12 2L4 8v6c0 4 8 8 8 8s8-4 8-8V8l-8-6z" fill="currentColor"></path>',
  'chart-line': '<polyline points="4 16 8 12 12 14 16 8 20 10" stroke="currentColor" stroke-width="2" fill="none"></polyline>',
  'chart-pie': '<circle cx="12" cy="12" r="10" fill="currentColor"></circle>',
  'file-invoice-dollar': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="currentColor"></path>',
  'users-cog': '<circle cx="8" cy="8" r="4" fill="currentColor"></circle><path d="M16 20v-4H4v4h12z" fill="currentColor"></path>',
  'user-cog': '<circle cx="12" cy="12" r="6" fill="currentColor"></circle>',
  'user': '<circle cx="12" cy="8" r="4" fill="currentColor"></circle><path d="M4 20h16v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2z" fill="currentColor"></path>',
  'info-circle': '<circle cx="12" cy="12" r="10" fill="currentColor"></circle>',
  'calendar': '<rect x="4" y="4" width="16" height="16" rx="2" fill="currentColor"></rect>',
  'th-large': '<rect x="4" y="4" width="6" height="6" fill="currentColor"></rect><rect x="14" y="4" width="6" height="6" fill="currentColor"></rect><rect x="14" y="14" width="6" height="6" fill="currentColor"></rect><rect x="4" y="14" width="6" height="6" fill="currentColor"></rect>',
  'times': '<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" fill="none"></path>',
  'question-circle': '<circle cx="12" cy="12" r="10" fill="currentColor"></circle>',
  'dashboard': '<rect x="4" y="4" width="16" height="16" rx="2" fill="currentColor"></rect>'
}

const iconSvg = computed(() => {
  return iconMap[props.name] || iconMap['home'] // Fallback to home icon
})

const iconStyle = computed(() => ({
  width: props.size,
  height: props.size,
  color: props.color,
  fill: props.color, /* Always fill for better visibility */
  stroke: 'none', /* No stroke for cleaner look */
  strokeWidth: '0',
  strokeLinecap: 'round',
  strokeLinejoin: 'round'
}))
</script>

<template>
  <!-- Fallback to text/emoji for testing -->
  <div class="svg-icon" :style="iconStyle">
    <span v-if="iconSvg.includes('home')" style="font-size: 1.2em;">🏠</span>
    <span v-else-if="iconSvg.includes('building')" style="font-size: 1.2em;">🏢</span>
    <span v-else-if="iconSvg.includes('file')" style="font-size: 1.2em;">📄</span>
    <span v-else-if="iconSvg.includes('calculator')" style="font-size: 1.2em;">🧮</span>
    <span v-else-if="iconSvg.includes('chart')" style="font-size: 1.2em;">📊</span>
    <span v-else-if="iconSvg.includes('cog')" style="font-size: 1.2em;">⚙️</span>
    <span v-else-if="iconSvg.includes('users')" style="font-size: 1.2em;">👥</span>
    <span v-else-if="iconSvg.includes('credit-card')" style="font-size: 1.2em;">💳</span>
    <span v-else-if="iconSvg.includes('plus')" style="font-size: 1.2em;">➕</span>
    <span v-else-if="iconSvg.includes('list')" style="font-size: 1.2em;">📋</span>
    <span v-else-if="iconSvg.includes('book')" style="font-size: 1.2em;">📚</span>
    <span v-else-if="iconSvg.includes('download')" style="font-size: 1.2em;">⬇️</span>
    <span v-else-if="iconSvg.includes('arrow')" style="font-size: 1.2em;">➡️</span>
    <span v-else-if="iconSvg.includes('chevron')" style="font-size: 1.2em;">›</span>
    <span v-else-if="iconSvg.includes('lock')" style="font-size: 1.2em;">🔒</span>
    <span v-else-if="iconSvg.includes('bell')" style="font-size: 1.2em;">🔔</span>
    <span v-else-if="iconSvg.includes('dollar')" style="font-size: 1.2em;">💵</span>
    <span v-else-if="iconSvg.includes('danger')" style="font-size: 1.2em;">⚠️</span>
    <span v-else-if="iconSvg.includes('trash')" style="font-size: 1.2em;">🗑️</span>
    <span v-else-if="iconSvg.includes('calendar')" style="font-size: 1.2em;">📅</span>
    <span v-else-if="iconSvg.includes('times')" style="font-size: 1.2em;">✖️</span>
    <span v-else-if="iconSvg.includes('question')" style="font-size: 1.2em;">❓</span>
    <span v-else style="font-size: 1.2em;">📄</span>
  </div>
</template>

<style scoped>
.svg-icon {
  display: inline-block;
  vertical-align: middle;
  color: inherit !important;
}

.svg-icon :deep(svg) {
  width: 100%;
  height: 100%;
  color: inherit !important;
}

/* Force all SVG elements to inherit color */
.svg-icon :deep(*) {
  color: inherit !important;
  fill: currentColor !important;
  stroke: currentColor !important;
}
</style>
