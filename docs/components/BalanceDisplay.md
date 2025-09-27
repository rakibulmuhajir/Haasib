# BalanceDisplay

## Description
A specialized component for displaying monetary values with proper formatting, color coding, and risk indicators. Used throughout the invoicing module for customer balances, invoice amounts, and payment values.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| amount | number \| string | Yes | - | The monetary amount to display |
| currency | string \| { code: string; symbol?: string } | No | { code: 'USD' } | Currency code or currency object |
| showRisk | boolean | No | true | Whether to show risk level indicators |
| size | 'sm' \| 'md' \| 'lg' \| 'xl' | No | 'md' | Size variant |
| variant | 'default' \| 'inline' \| 'card' | No | 'default' | Display variant |
| showTrend | boolean | No | false | Whether to show trend indicator |
| trendValue | number | No | - | Trend value (percentage or absolute) |
| customClass | string | No | - | Additional CSS classes |

## Usage Examples

### Basic Usage
```vue
<template>
  <!-- Simple balance display -->
  <BalanceDisplay :amount="1250.50" />
  
  <!-- With specific currency -->
  <BalanceDisplay :amount="2500" currency="EUR" />
  
  <!-- With currency object -->
  <BalanceDisplay 
    :amount="5000" 
    :currency="{ code: 'GBP', symbol: '£' }" 
  />
</template>
```

### Customer Balance with Risk Indicator
```vue
<template>
  <BalanceDisplay
    :amount="customer.balance"
    :currency="customer.currency"
    showRisk
    size="lg"
    variant="card"
  />
</template>
```

### With Trend Indicator
```vue
<template>
  <BalanceDisplay
    :amount="currentBalance"
    :currency="'USD'"
    :showTrend="true"
    :trendValue="12.5"
    size="md"
  />
</template>
```

## Features
- **Automatic Formatting**: Uses Intl.NumberFormat for proper currency formatting
- **Color Coding**: Red for negative, green for positive, gray for zero
- **Risk Levels**: Visual indicators for high balances or overdue amounts
- **Multiple Variants**: Different display styles for various contexts
- **Trend Indicators**: Shows percentage or absolute changes
- **Responsive Sizing**: Adapts to different UI contexts

## Risk Level Calculation
The component calculates risk based on:
- Balance amount thresholds
- Age of outstanding amounts
- Customer payment history

### Risk Levels
- **Low**: Green, amounts within normal limits
- **Medium**: Yellow, elevated but manageable amounts  
- **High**: Orange, significant amounts requiring attention
- **Critical**: Red, extremely high or overdue amounts

## Color Coding
- **Positive**: Green text (`text-green-600`)
- **Negative**: Red text (`text-red-600`)
- **Zero**: Gray text (`text-gray-500`)
- **Risk Overlay**: Badge with risk level color

## Accessibility
- Semantic HTML structure
- ARIA labels for complex displays
- Screen reader announcements for trends
- High contrast color combinations
- Proper formatting for assistive technologies

## Styling
- CSS custom properties for theming
- Responsive breakpoints
- Consistent spacing and typography
- Smooth transitions for trend changes

## CSS Variables
```css
.balance-display {
  --balance-positive: #059669;
  --balance-negative: #dc2626;
  --balance-zero: #6b7280;
  --balance-risk-low: #10b981;
  --balance-risk-medium: #f59e0b;
  --balance-risk-high: #f97316;
  --balance-risk-critical: #ef4444;
}
```

## Dependencies
- formatMoney utility from `/Utils/formatting.ts`
- PrimeVue Badge for risk indicators
- No external dependencies

## Testing
Test scenarios to cover:
- Positive, negative, and zero amounts
- Different currency codes and symbols
- Risk level calculations
- Trend indicator display
- Responsive behavior
- Accessibility features
- Various size variants
- Custom class application

## Performance Considerations
- Memoize formatting functions for performance
- Use CSS transforms for smooth animations
- Debounce rapid amount changes

## Notes
- Always provide a currency for accurate formatting
- Use size variants appropriately for context
- Consider disabling risk indicators for simple displays
- The component handles string conversion internally

## Localization
The component automatically adapts to:
- User's locale settings
- Currency-specific formatting
- Right-to-left languages
- Local number formatting rules