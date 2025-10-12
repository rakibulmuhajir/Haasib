<script setup>
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

// SVG icon mapping - using common FontAwesome icon names mapped to simple SVG paths
const iconMap = {
  // Navigation
  'home': '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
  'building': '<path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-3"></path>',
  'file-text': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>',
  'calculator': '<rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="8" y1="6" x2="16" y2="6"></line><line x1="8" y1="10" x2="16" y2="10"></line><line x1="8" y1="14" x2="16" y2="14"></line><line x1="8" y1="18" x2="16" y2="18"></line>',
  'chart-bar': '<line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line>',
  'cog': '<circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6m4.22-13.22l4.24 4.24M1.54 9.96l4.24 4.24m12.44 0l4.24 4.24M1.54 14.04l4.24-4.24"></path>',
  'users': '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
  'credit-card': '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line>',
  'plus': '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>',
  'list': '<line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line>',
  'book': '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>',
  'pie-chart': '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path>',
  'settings': '<circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6m4.22-13.22l4.24 4.24M1.54 9.96l4.24 4.24m12.44 0l4.24 4.24M1.54 14.04l4.24-4.24"></path>',
  'download': '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>',
  'arrow-left': '<line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>',
  'arrow-right': '<line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>',
  'chevron-down': '<polyline points="6 9 12 15 18 9"></polyline>',
  'chevron-right': '<polyline points="9 18 15 12 9 6"></polyline>',
  'chevron-up': '<polyline points="18 15 12 9 6 15"></polyline>',
  'chevron-left': '<polyline points="15 18 9 12 15 6"></polyline>',
  'lock': '<rect x="3" y="11" width="18" height="10" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
  'palette': '<circle cx="13.5" cy="6.5" r=".5"></circle><circle cx="17.5" cy="10.5" r=".5"></circle><circle cx="8.5" cy="7.5" r=".5"></circle><circle cx="12.5" cy="16.5" r=".5"></circle><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path>',
  'bell': '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
  'dollar': '<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
  'danger': '<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>',
  'trash': '<polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>'
}

const iconSvg = computed(() => {
  return iconMap[props.name] || iconMap['home'] // Fallback to home icon
})

const iconStyle = computed(() => ({
  width: props.size,
  height: props.size,
  color: props.color,
  fill: props.monochrome ? props.color : 'none',
  stroke: props.monochrome ? 'none' : props.color,
  strokeWidth: props.monochrome ? '0' : '2',
  strokeLinecap: 'round',
  strokeLinejoin: 'round'
}))
</script>

<template>
  <div class="svg-icon" :style="iconStyle" v-html="iconSvg"></div>
</template>

<style scoped>
.svg-icon {
  display: inline-block;
  vertical-align: middle;
}

.svg-icon :deep(svg) {
  width: 100%;
  height: 100%;
}
</style>