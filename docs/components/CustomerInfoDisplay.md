# CustomerInfoDisplay

## Description
A standardized component for displaying customer information with consistent formatting, icons, and interactive elements. Shows customer name, email, phone, and other contact details in a structured format.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| customer | Object | Yes | - | Customer object with contact information |
| showAvatar | boolean | No | true | Whether to show customer avatar |
| showEmail | boolean | No | true | Whether to show email |
| showPhone | boolean | No | true | Whether to show phone number |
| showStatus | boolean | No | false | Whether to show customer status |
| variant | 'default' \| 'compact' \| 'detailed' | No | 'default' | Display variant |
| avatarSize | 'sm' \| 'md' \| 'lg' | No | 'md' | Avatar size |
| clickable | boolean | No | false | Whether the component is clickable |
| customClass | string | No | - | Additional CSS classes |

## Customer Object Structure
```typescript
interface Customer {
  customer_id: number
  name: string
  email?: string
  phone?: string
  status?: string
  avatar?: string
  [key: string]: any
}
```

## Slots
| Slot Name | Description | Props |
|-----------|-------------|-------|
| default | Additional customer information | { customer: Customer } |
| actions | Action buttons for the customer | { customer: Customer } |
| after | Content after customer info | { customer: Customer } |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| @click | (customer: Customer) => void | When component is clicked |
| @email-click | (email: string) => void | When email is clicked |
| @phone-click | (phone: string) => void | When phone is clicked |

## Usage Examples

### Basic Usage
```vue
<template>
  <CustomerInfoDisplay 
    :customer="selectedCustomer" 
    @click="viewCustomer"
  />
</template>
```

### Compact Variant in Table
```vue
<template>
  <Column field="customer" header="Customer">
    <template #body="{ data }">
      <CustomerInfoDisplay
        :customer="data.customer"
        variant="compact"
        avatarSize="sm"
        :clickable="true"
        @click="viewCustomer(data.customer)"
      />
    </template>
  </Column>
</template>
```

### Detailed View with Actions
```vue
<template>
  <CustomerInfoDisplay
    :customer="customer"
    variant="detailed"
    :showStatus="true"
    avatarSize="lg"
  >
    <template #actions="{ customer }">
      <div class="flex gap-2">
        <Button 
          icon="pi pi-envelope" 
          class="p-button-text p-button-sm"
          @click="emailCustomer(customer)"
        />
        <Button 
          icon="pi pi-phone" 
          class="p-button-text p-button-sm"
          @click="callCustomer(customer)"
        />
      </div>
    </template>
    
    <template #after="{ customer }">
      <div class="mt-2 text-sm text-gray-500">
        Customer since {{ formatDate(customer.created_at) }}
      </div>
    </template>
  </CustomerInfoDisplay>
</template>
```

## Features
- **Consistent Formatting**: Standardized display of customer information
- **Avatar Generation**: Automatic avatar from customer initials
- **Interactive Elements**: Clickable email and phone with default handlers
- **Multiple Variants**: Compact, default, and detailed display modes
- **Status Integration**: Shows customer status when enabled
- **Accessibility**: Proper labels and keyboard navigation

## Avatar Behavior
- Shows customer image if provided
- Generates initials from customer name
- Uses consistent color scheme
- Scales appropriately with size setting

## Variants

### Default
- Avatar on the left
- Name below avatar
- Email and phone stacked
- Medium spacing

### Compact
- Avatar and name inline
- Smaller text sizes
- Tight spacing
- Ideal for tables

### Detailed
- Large avatar
- Prominent name display
- Additional information slots
- Action buttons included

## Accessibility
- Semantic HTML structure
- ARIA labels for interactive elements
- Keyboard navigation support
- Screen reader friendly
- High contrast support

## Styling
- CSS custom properties for theming
- Responsive design
- Consistent spacing
- Hover states for interactive elements

## CSS Variables
```css
.customer-info-display {
  --customer-avatar-bg: #e5e7eb;
  --customer-avatar-text: #374151;
  --customer-name-color: #111827;
  --customer-contact-color: #6b7280;
  --customer-hover-bg: #f9fafb;
}
```

## Dependencies
- PrimeVue Avatar (for avatar display)
- StatusBadge component (when showStatus=true)
- format utilities
- No external dependencies

## Testing
Test scenarios to cover:
- All information display variants
- Avatar generation with and without images
- Interactive elements (email, phone clicks)
- Clickable behavior
- Responsive behavior
- Accessibility features
- Status badge integration
- Custom slot content

## Performance Considerations
- Memoize computed values
- Lazy load avatar images
- Use CSS transforms for hover effects

## Notes
- Customer object must have at least a name property
- Email and phone are automatically linked when clicked
- Use compact variant for space-constrained areas
- Consider adding loading states for async customer data

## Extending the Component
To add additional customer fields:
1. Add new props for controlling visibility
2. Update the customer interface
3. Add conditional rendering logic
4. Update documentation