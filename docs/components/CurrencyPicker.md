# CurrencyPicker

## Description
A specialized currency selection component that displays currency codes, symbols, names, and exchange rates in a consistent format. Designed for financial applications where currency selection is frequent and accuracy is critical.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | number \| string \| null | No | - | Selected currency ID |
| currencies | Array<Currency> | Yes | - | Array of currency objects |
| optionLabel | string | No | 'code' | Field to use as display label |
| optionValue | string | No | 'id' | Field to use as value |
| optionDisabled | (currency: Currency) => boolean | No | - | Function to disable options |
| placeholder | string | No | 'Select currency...' | Placeholder text |
| showClear | boolean | No | false | Show clear button |
| disabled | boolean | No | false | Disable the component |
| loading | boolean | No | false | Loading state |
| error | string | No | - | Error message to display |
| showName | boolean | No | false | Show currency name in selected value |
| showExtraInfo | boolean | No | false | Show exchange rate and base currency info |
| showExchangeRate | boolean | No | false | Show exchange rate info below picker |
| baseCurrency | string | No | 'USD' | Base currency for exchange rate display |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| @update:modelValue | (value: number \| string \| null) => void | When selection changes |
| @change | (currency: Currency \| null) => void | When currency is selected |
| @show | () => void | When dropdown is shown |
| @hide | () => void | When dropdown is hidden |

## Currency Interface
```typescript
interface Currency {
  id: number | string
  code: string           // e.g., "USD", "EUR", "GBP"
  symbol: string         // e.g., "$", "€", "£"
  name: string           // e.g., "US Dollar", "Euro"
  exchange_rate?: number // Rate relative to base currency
  is_base?: boolean      // Whether this is the base currency
  [key: string]: any
}
```

## Usage Examples

### Basic Usage
```vue
<template>
  <CurrencyPicker
    v-model="form.currency_id"
    :currencies="currencies"
    @change="onCurrencyChange"
  />
</template>

<script setup>
import { ref } from 'vue'

const form = ref({
  currency_id: null
})

const currencies = ref([
  { id: 1, code: 'USD', symbol: '$', name: 'US Dollar', is_base: true },
  { id: 2, code: 'EUR', symbol: '€', name: 'Euro', exchange_rate: 0.85 },
  { id: 3, code: 'GBP', symbol: '£', name: 'British Pound', exchange_rate: 0.73 }
])
</script>
```

### With Exchange Rates
```vue
<template>
  <CurrencyPicker
    v-model="selectedCurrency"
    :currencies="currencies"
    showExtraInfo
    showExchangeRate
    baseCurrency="USD"
    @change="updateAmounts"
  />
</template>
```

### In an Amount Input Group
```vue
<template>
  <InputGroup>
    <InputNumber
      v-model="amount"
      mode="currency"
      :currency="selectedCurrency?.code"
    />
    <InputGroupAddon>
      <CurrencyPicker
        v-model="currencyId"
        :currencies="currencies"
        :showClear="false"
        placeholder="Currency"
      />
    </InputGroupAddon>
  </InputGroup>
</template>
```

## Features
- **Rich Display**: Shows currency code, symbol, and name
- **Exchange Rates**: Displays relative exchange rates when available
- **Base Currency Indicator**: Highlights the base currency
- **Consistent Formatting**: Monospace font for currency codes
- **Compact Mode**: Minimal display for space-constrained areas
- **Error Handling**: Clear error message display
- **Accessibility**: Full keyboard navigation support

## Exchange Rate Display
- Shows rates relative to base currency
- Formatted with 4 decimal places for accuracy
- Optional display below the picker
- Updates automatically when currency changes

## Styling
- Monospace font for currency codes for alignment
- Consistent spacing and typography
- Color coding for base currency indicator
- Responsive dropdown sizing

## Accessibility
- Semantic HTML structure
- ARIA labels for currency information
- Keyboard navigation support
- Screen reader compatibility

## Dependencies
- PrimeVue Dropdown (base component)
- No external dependencies

## Performance Considerations
- Lightweight component with minimal computations
- Efficient currency lookup
- No unnecessary re-renders

## Testing
Test scenarios to cover:
- Currency selection and clearing
- Exchange rate display accuracy
- Base currency highlighting
- Error state display
- Disabled state behavior
- Keyboard navigation
- Screen reader compatibility
- Various prop combinations

## Notes
- Currency codes should follow ISO 4217 standard
- Exchange rates should be relative to a single base currency
- Use showExchangeRate for financial forms where rate visibility matters
- The component handles both numeric and string ID values

## Best Practices
1. **Keep currency data updated**: Exchange rates change frequently
2. **Use consistent base currency**: All rates should be relative to one base
3. **Consider user's location**: Pre-select user's local currency when possible
4. **Handle conversion errors**: Gracefully handle invalid exchange rates
5. **Show relevant currencies**: Filter to show only active/traded currencies

## Future Enhancements
- Currency flag icons
- Favorite/recently used currencies
- Multi-currency selection mode
- Real-time exchange rate updates
- Currency conversion calculator
- Historical exchange rate display