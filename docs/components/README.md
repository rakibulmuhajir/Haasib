# Component Documentation Template

## Template Structure

Each component should be documented with the following sections:

```markdown
# [Component Name]

## Description
Brief description of what the component does and its purpose.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| propName | Type | Yes/No | defaultValue | Description of the prop |

## Slots
| Slot Name | Description | Props |
|-----------|-------------|-------|
| default | Default slot content | - |
| slotName | Description of slot | { slotProp: Type } |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| eventName | (payload: Type) => void | Description of when event is emitted |

## Usage Examples

### Basic Usage
```vue
<template>
  <ComponentName :prop="value" @event="handler">
    Content
  </ComponentName>
</template>

<script setup>
import { ref } from 'vue'

const value = ref('example')
const handler = (payload) => {
  console.log(payload)
}
</script>
```

### Advanced Usage
```vue
<!-- Example with all features -->
```

## Features
- Feature 1
- Feature 2
- Feature 3

## Accessibility
- ARIA attributes used
- Keyboard navigation support
- Screen reader considerations

## Styling
- CSS classes available
- CSS variables for customization
- Theme considerations

## Dependencies
- PrimeVue components used
- Other custom components
- External libraries

## Testing
- What scenarios are covered
- How to test the component
- Mock data examples

## Notes
Any additional information, limitations, or gotchas.
```

---

## Component Documentation Index

This file serves as an index for all component documentation.

## Layout Components
- [LayoutShell](./components/LayoutShell.md)
- [PageHeader](./components/PageHeader.md)
- [Breadcrumb](./components/Breadcrumb.md)

## Data Display Components
- [DataTablePro](./components/DataTablePro.md)
- [StatusBadge](./components/StatusBadge.md)
- [BalanceDisplay](./components/BalanceDisplay.md)
- [CustomerInfoDisplay](./components/CustomerInfoDisplay.md)
- [CountryDisplay](./components/CountryDisplay.md)

## Form Components
- [InputText](./components/InputText.md)
- [Dropdown](./components/Dropdown.md)
- [Calendar](./components/Calendar.md)
- [InputNumber](./components/InputNumber.md)
- [Textarea](./components/Textarea.md)
- [RadioButton](./components/RadioButton.md)

## Action Components
- [Button](./components/Button.md)
- [PageActions](./components/PageActions.md)

## Dialog Components
- [Dialog](./components/Dialog.md)

## Navigation Components
- [Link](./components/Link.md)

## Layout Components
- [Card](./components/Card.md)
- [Fieldset](./components/Fieldset.md)
- [Divider](./components/Divider.md)

## Utility Components
- [InputGroup](./components/InputGroup.md)
- [InputGroupAddon](./components/InputGroupAddon.md)
- [OverlayBadge](./components/OverlayBadge.md)