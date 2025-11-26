import { defineComponent, h } from 'vue'

export interface ChartConfig {
  [key: string]: {
    label: string
    color?: string
    theme?: {
      light?: string
      dark?: string
    }
  }
}

// Simple chart container component
export const ChartContainer = defineComponent({
  name: 'ChartContainer',
  props: {
    config: {
      type: Object as () => ChartConfig,
      required: true
    },
    class: String,
    cursor: {
      type: Boolean,
      default: true
    }
  },
  setup(props, { slots }) {
    return () => h('div', {
      class: ['chart-container', props.class].filter(Boolean).join(' ')
    }, slots.default?.())
  }
})

// Simple tooltip component 
export const ChartTooltip = defineComponent({
  name: 'ChartTooltip',
  setup(_, { slots }) {
    return () => h('div', { class: 'chart-tooltip' }, slots.default?.())
  }
})

// Simple tooltip content component
export const ChartTooltipContent = defineComponent({
  name: 'ChartTooltipContent',
  props: {
    labelFormatter: Function,
    formatter: Function
  },
  setup(props, { slots }) {
    return () => h('div', { class: 'chart-tooltip-content' }, slots.default?.())
  }
})

// Simple crosshair component
export const ChartCrosshair = defineComponent({
  name: 'ChartCrosshair',
  props: {
    template: String,
    color: [String, Function]
  },
  setup(props, { slots }) {
    return () => h('div', { class: 'chart-crosshair' }, slots.default?.())
  }
})

// Simple legend component
export const ChartLegendContent = defineComponent({
  name: 'ChartLegendContent',
  setup(_, { slots }) {
    return () => h('div', { class: 'chart-legend' }, slots.default?.())
  }
})

// Utility function
export const componentToString = (config: ChartConfig, component: any, options?: any) => {
  return ''
}