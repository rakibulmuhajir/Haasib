# AmountInput

## Description
A specialized monetary input component with integrated currency support, conversion display, and balance information. Designed for financial applications where accurate amount entry is critical with proper formatting and validation.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | number \| string \| null | No | - | Input value |
| currencies | Array<Currency> | No | [] | Available currencies for conversion |
| currency | string \| number \| null | No | - | Selected currency ID |
| defaultCurrency | string | No | 'USD' | Default currency code |
| defaultCurrencySymbol | string | No | '$' | Default currency symbol |
| baseCurrency | string | No | - | Base currency for conversions |
| showCurrency | boolean | No | true | Show currency selector/symbol |
| allowCurrencyChange | boolean | No | false | Allow changing currency |
| mode | 'decimal' \| 'currency' | No | 'currency' | Input display mode |
| locale | string | No | 'en-US' | Locale for formatting |
| min | number | No | - | Minimum value |
| max | number | No | - | Maximum value |
| step | number | No | 0.01 | Step value for increments |
| minFractionDigits | number | No | 2 | Minimum decimal places |
| maxFractionDigits | number | No | 2 | Maximum decimal places |
| placeholder | string | No | '0.00' | Placeholder text |
| disabled | boolean | No | false | Disable input |
| readonly | boolean | No | false | Make input readonly |
| invalid | boolean | No | false | Show invalid state |
| showClear | boolean | No | false | Show clear button |
| error | string | No | - | Error message to display |
| helperText | string | No | - | Helper text below input |
| inputClass | string \| object \| array | No | - | Additional input classes |
| inputId | string | No | - | Input element ID |
| inputStyle | object | No | - | Input element styles |
| balance | number | No | - | Available balance amount |
| balanceLabel | string | No | 'Available' | Label for balance display |
| showBalanceInfo | boolean | No | false | Show available balance |
| showConversion | boolean | No | false | Show currency conversion |
| autoFocus | boolean | No | false | Auto-focus on mount |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| @update:modelValue | (value: number \| null) => void | When value changes |
| @input | (value: number \| null) => void | On input event |
| @change | (value: number \| null) => void | When value is committed |
| @blur | (event: Event) => void | When input loses focus |
| @focus | (event: Event) => void | When input gains focus |
| @currency-change | (currency: Currency \| null) => void | When currency changes |
| @clear | () => void | When value is cleared |

## Currency Interface
```typescript
interface Currency {
  id: number | string
  code: string           // e.g., "USD", "EUR"
  symbol: string         // e.g., "$", "€"
  name: string           // e.g., "US Dollar"
  exchange_rate?: number // Rate relative to base currency
  is_base?: boolean      // Whether this is the base currency
  [key: string]: any
}
```

## Usage Examples

### Basic Amount Input
```vue
<template>
  <AmountInput
    v-model="amount"
    label="Amount"
    :error="errors.amount"
  />
</template>

<script setup>
import { ref } from 'vue'

const amount = ref(1000)
</script>
```

### With Currency Selection
```vue
<template>
  <AmountInput
    v-model="payment.amount"
    v-model:currency="payment.currency_id"
    :currencies="currencies"
    :allowCurrencyChange="true"
    :balance="customerBalance"
    showBalanceInfo
    @currency-change="updateExchangeRate"
  />
</template>
```

### In a Payment Form
```vue
<template>
  <div class="space-y-4">
    <AmountInput
      v-model="form.amount"
      v-model:currency="form.currency_id"
      :currencies="currencies"
      :balance="invoice.outstanding_balance"
      balanceLabel="Invoice Balance"
      showBalanceInfo
      showConversion
      baseCurrency="USD"
      :error="form.errors.amount"
      :max="invoice.outstanding_balance"
      :min="0"
      @currency-change="onCurrencyChange"
    />
  </div>
</template>
```

### With Validation
```vue
<template>
  <AmountInput
    v-model="form.amount"
    :error="form.errors.amount"
    :helperText="'Minimum amount: $10.00'"
    :min="10"
    :max="10000"
    :showClear="true"
    :invalid="!!form.errors.amount"
    @change="validateAmount"
  />
</template>
```

## Features
- **Currency Integration**: Built-in currency picker/symbol display
- **Format Control**: Configurable decimal places and locale formatting
- **Balance Display**: Shows available balance with context
- **Currency Conversion**: Automatic conversion display when using foreign currencies
- **Validation Support**: Min/max values with error display
- **Accessibility**: Full keyboard navigation and ARIA support
- **Clear Button**: Optional clear functionality
- **Auto-focus**: Programmatic focus control

## Conversion Display
- Shows converted amount when currency differs from base
- Uses real-time exchange rates when available
- Formatted according to locale settings
- Helpful for multi-currency transactions

## Balance Information
- Shows available balance for customer/invoice contexts
- Helps prevent overpayment scenarios
- Customizable label for different use cases
- Color-coded for visual hierarchy

## Styling
- Consistent with PrimeVue design system
- Responsive layout that adapts to container width
- Error states with clear visual feedback
- Customizable through props and CSS variables

## Accessibility
- Semantic HTML structure
- Proper labeling and ARIA attributes
- Keyboard navigation support
- Screen reader compatibility
- High contrast support

## Dependencies
- PrimeVue InputNumber (base component)
- PrimeVue InputGroup
- CurrencyPicker component
- BalanceDisplay component
- No external dependencies

## Performance Considerations
- Lightweight wrapper with minimal overhead
- Efficient currency lookup
- Optimized for frequent user input
- Minimal re-renders

## Methods Exposed
- `focus()` - Focus the input element
- `blur()` - Blur the input element

## Testing
Test scenarios to cover:
- Amount entry with keyboard and mouse
- Currency selection and conversion display
- Balance information visibility
- Error state display
- Validation constraints (min/max)
- Clear button functionality
- Disabled/readonly states
- Auto-focus behavior
- Various locale formats

## Notes
- Handles both numeric and string values internally
- Null values represent empty/undefined state
- Currency exchange rates should be kept up-to-date
- Use showConversion for international transactions
- Balance display helps prevent user errors

## Best Practices
1. **Always use currency mode** for monetary amounts
2. **Set appropriate min/max values** for your use case
3. **Use balance display** when there's a limit
4. **Show conversion** for multi-currency scenarios
5. **Provide clear error messages** for validation failures
6. **Use consistent decimal places** across your application
7. **Consider locale settings** for international users

## Future Enhancements
- Percentage calculation mode
- Tax-inclusive/exclusive toggle
- Multi-currency amount entry
- Quick amount presets
- Historical currency conversion
- Batch amount entry
- Amount validation rules engine