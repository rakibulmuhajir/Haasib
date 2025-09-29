# CountryPicker

## Description
An enhanced country selection component with flag display, phone codes, region filtering, and rich country information. Provides a consistent way to select countries across the application with advanced features like search, region filtering, and inline actions.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | number \| string \| null | No | - | Selected country ID |
| countries | Array<Country> | Yes | - | Array of country objects |
| optionLabel | string | No | 'name' | Field to use as display label |
| optionValue | string | No | 'id' | Field to use as value |
| optionDisabled | (country: Country) => boolean | No | - | Function to disable options |
| placeholder | string | No | 'Select a country...' | Placeholder text |
| filterPlaceholder | string | No | 'Search countries...' | Filter input placeholder |
| filterFields | string[] | No | ['name', 'code', 'phone_code'] | Fields to search in |
| showClear | boolean | No | true | Show clear button |
| disabled | boolean | No | false | Disable the component |
| loading | boolean | No | false | Loading state |
| error | string | No | - | Error message to display |
| showExtraInfo | boolean | No | false | Show currency and timezone info |
| showPhoneCode | boolean | No | false | Show phone code in selected value |
| showRegionFilter | boolean | No | false | Show region filter in header |
| showTimezone | boolean | No | false | Show timezone info below picker |
| showCurrency | boolean | No | false | Show currency info below picker |
| allowCreate | boolean | No | false | Allow creating new countries |
| regions | Array<Region> | No | [] | Available regions for filtering |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| @update:modelValue | (value: number \| string \| null) => void | When selection changes |
| @change | (country: Country \| null) => void | When country is selected |
| @filter | (event: Event) => void | When filter is applied |
| @show | () => void | When dropdown is shown |
| @hide | () => void | When dropdown is hidden |
| @region-change | (region: string \| null) => void | When region filter changes |
| @create-country | () => void | When create country is clicked |

## Country Interface
```typescript
interface Country {
  id?: number | string
  code?: string           // ISO 3166-1 alpha-2 code
  name: string           // Country name
  flag?: string          // Flag emoji
  phone_code?: string    // International dialing code
  region?: string        // Continent/region
  currency_code?: string // ISO 4217 currency code
  currency_symbol?: string // Currency symbol
  timezone?: string      // Default timezone
  [key: string]: any
}
```

## Region Interface
```typescript
interface Region {
  code: string
  name: string
}
```

## Usage Examples

### Basic Usage
```vue
<template>
  <CountryPicker
    v-model="form.country_id"
    :countries="countries"
    @change="onCountryChange"
  />
</template>

<script setup>
import { ref } from 'vue'

const form = ref({
  country_id: null
})

const countries = ref([
  { id: 1, name: 'United States', code: 'US', phone_code: '1' },
  { id: 2, name: 'United Kingdom', code: 'GB', phone_code: '44' }
])
</script>
```

### With Phone Code Selection
```vue
<template>
  <CountryPicker
    v-model="selectedCountry"
    :countries="countries"
    :showPhoneCode="true"
    placeholder="Select your country"
    @change="updatePhonePrefix"
  />
</template>
```

### With Region Filtering
```vue
<template>
  <CountryPicker
    v-model="form.country_id"
    :countries="countries"
    :showRegionFilter="true"
    :regions="regions"
    :showExtraInfo="true"
    @region-change="filterByRegion"
  />
</template>

<script setup>
const regions = [
  { code: 'EU', name: 'Europe' },
  { code: 'AS', name: 'Asia' },
  { code: 'NA', name: 'North America' }
]
</script>
```

### In an Address Form
```vue
<template>
  <div class="space-y-4">
    <CountryPicker
      v-model="address.country_id"
      :countries="countries"
      :error="errors.country_id"
      :showCurrency="true"
      :showTimezone="true"
      @change="onCountryChange"
    />
    
    <!-- State/Province input (shown when country has states) -->
    <InputText
      v-if="selectedCountry?.has_states"
      v-model="address.state"
      placeholder="State/Province"
    />
  </div>
</template>
```

### With Phone Number Input
```vue
<template>
  <InputGroup>
    <InputGroupAddon>
      <CountryPicker
        v-model="phone.country_id"
        :countries="countries"
        :showClear="false"
        :showPhoneCode="true"
        optionValue="phone_code"
        @change="updateCountryCode"
      />
    </InputGroupAddon>
    <InputText
      v-model="phone.number"
      placeholder="Phone number"
    />
  </InputGroup>
</template>
```

## Features
- **Rich Country Display**: Shows flag, name, code, and phone code
- **Advanced Search**: Multi-field filtering with customizable fields
- **Flag Display**: Automatic flag emoji generation from country codes
- **Phone Code Support**: International dialing codes for telecom forms
- **Region Filtering**: Filter countries by continent/region
- **Currency Info**: Display local currency when available
- **Timezone Display**: Show default timezone for the country
- **Create Country**: Option to add new countries from picker
- **Error Handling**: Clear error message display
- **Loading States**: Visual feedback during operations
- **Accessibility**: Full keyboard navigation and ARIA support

## Flag Generation
- Automatically generates flag emojis from ISO country codes
- Fallback to generic flag for invalid codes
- Supports all standard country codes

## Region Filtering
- Optional region filter in dropdown header
- Helps users find countries more quickly
- Customizable region list
- Updates displayed countries in real-time

## Phone Code Integration
- Displays international dialing codes
- Useful for phone number forms
- Can be used as standalone phone code selector
- Shows in both dropdown and selected value

## Currency and Timezone Display
- Shows local currency information
- Displays default timezone
- Helpful for international applications
- Color-coded information display

## Accessibility
- Semantic HTML structure
- ARIA labels and descriptions
- Keyboard navigation support
- Screen reader compatibility
- High contrast support

## Styling
- Consistent with PrimeVue design system
- Responsive layout that adapts to container width
- Hover states and transitions
- Loading and disabled states
- Customizable through CSS variables

## Dependencies
- PrimeVue Dropdown (base component)
- No external dependencies
- Automatic flag generation (no external flag libraries)

## Performance Considerations
- Efficient filtering algorithm
- Minimal re-renders
- Optimized flag generation
- Lazy loading ready for large datasets

## Methods Exposed
- `show()` - Programmatically open the dropdown
- `hide()` - Programmatically close the dropdown
- `focus()` - Focus the input element

## Testing
Test scenarios to cover:
- Country selection and clearing
- Search/filter functionality
- Region filtering
- Phone code display
- Flag generation for various codes
- Error state display
- Keyboard navigation
- Screen reader compatibility
- Custom field configurations
- Disabled state behavior

## Notes
- Country codes should follow ISO 3166-1 alpha-2 standard
- Phone codes should include the '+' symbol in data but not in display
- Use showPhoneCode for phone number forms
- Region filtering helps with large country lists
- Flag generation works for all standard country codes

## Best Practices
1. **Keep country data updated**: Country information changes occasionally
2. **Use region filtering**: Essential for applications with many countries
3. **Show relevant information**: Phone code for telecom, currency for e-commerce
4. **Consider user's location**: Pre-select user's country when possible
5. **Handle missing data**: Gracefully handle incomplete country records
6. **Use consistent codes**: Stick to ISO standards for country codes

## Future Enhancements
- Country state/province selection
- Favorite/recently used countries
- Country grouping by region
- Advanced search filters (language, currency, etc.)
- Country statistics display
- Map-based country selection
- Timezone selection within country
- Multiple country selection mode