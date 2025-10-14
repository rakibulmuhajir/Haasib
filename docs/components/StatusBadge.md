# StatusBadge

## Description
A reusable status indicator component that displays status information with consistent color coding and styling. Used throughout the application for customer, invoice, and payment statuses.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| value | string | Yes | - | The status value to display |
| variant | 'default' \| 'pill' \| 'dot' | No | 'default' | Visual variant of the badge |
| size | 'sm' \| 'md' \| 'lg' | No | 'md' | Size variant |
| showIcon | boolean | No | true | Whether to show status icon |
| customClass | string | No | - | Additional CSS classes |

## Slots
| Slot Name | Description | Props |
|-----------|-------------|-------|
| default | Custom content for the badge | - |

## Usage Examples

### Basic Usage
```vue
<template>
  <!-- Default usage -->
  <StatusBadge value="active" />
  
  <!-- With custom variant -->
  <StatusBadge value="pending" variant="pill" />
  
  <!-- With size -->
  <StatusBadge value="inactive" size="sm" />
</template>
```

### Custom Content
```vue
<template>
  <StatusBadge value="custom">
    <span class="flex items-center gap-2">
      <i class="pi pi-star"></i>
      Custom Status
    </span>
  </StatusBadge>
</template>
```

## Status Mapping
The component automatically maps status values to colors and icons:

### Customer Statuses
- `active` - Green, check icon
- `inactive` - Gray, times icon
- `prospect` - Blue, user icon
- `blocked` - Red, ban icon

### Invoice Statuses
- `draft` - Gray, file icon
- `sent` - Blue, paper-plane icon
- `paid` - Green, check-circle icon
- `overdue` - Red, exclamation-triangle icon
- `cancelled` - Orange, times-circle icon

### Payment Statuses
- `pending` - Yellow, clock icon
- `completed` - Green, check-circle icon
- `failed` - Red, times-circle icon
- `refunded` - Blue, undo icon

## Features
- Automatic color coding based on status value
- Consistent icon assignment
- Multiple visual variants
- Responsive sizing
- Custom content support via slot
- Accessibility features

## Accessibility
- Uses semantic HTML structure
- ARIA labels for status information
- High contrast color combinations
- Screen reader friendly

## Styling
- CSS custom properties for theming
- Consistent spacing and typography
- Hover states for interactive badges
- Responsive sizing

## CSS Variables
```css
.status-badge {
  --status-badge-bg: #e5e7eb;
  --status-badge-text: #1f2937;
  --status-badge-icon: #6b7280;
}
```

## Dependencies
- PrimeVue Badge (base component)
- PrimeVue icons
- No external dependencies

## Testing
Test scenarios to cover:
- All status values display correctly
- Color coding is consistent
- Icons match status types
- Responsive behavior
- Custom slot content
- Accessibility attributes
- Different size variants

## Notes
- Add new status mappings to the component's internal mapping
- Consider color contrast when adding new status types
- Use descriptive status values that make sense to users
- The component handles unknown statuses with default styling

## Extending Status Mappings
To add new status mappings, modify the component's status configuration:
```javascript
const statusConfig = {
  // Existing mappings...
  'new_status': {
    color: 'purple',
    icon: 'pi pi-plus',
    label: 'New Status'
  }
}
```