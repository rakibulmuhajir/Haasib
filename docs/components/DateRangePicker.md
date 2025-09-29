# DateRangePicker

## Description
A comprehensive date range selection component with preset options, validation, and flexible display modes. Designed for applications requiring date range filtering, reporting periods, or scheduling with built-in duration calculations and validation rules.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | { startDate: Date \| string \| null; endDate: Date \| string \| null } | No | - | Selected date range |
| mode | 'input' \| 'display' | No | 'input' | Component display mode |
| startLabel | string | No | 'Start Date' | Label for start date |
| endLabel | string | No | 'End Date' | Label for end date |
| startPlaceholder | string | No | 'Select start date' | Start date placeholder |
| endPlaceholder | string | No | 'Select end date' | End date placeholder |
| dateFormat | string | No | 'yy-mm-dd' | Date format string |
| showIcon | boolean | No | true | Show calendar icon |
| icon | string | No | 'pi pi-calendar' | Calendar icon class |
| disabled | boolean | No | false | Disable component |
| readonly | boolean | No | false | Make component readonly |
| minDate | Date | No | - | Minimum selectable date |
| maxDate | Date | No | - | Maximum selectable date |
| showTime | boolean | No | false | Include time selection |
| hourFormat | '12' \| '24' | No | '24' | Hour format (12/24) |
| stepHour | number | No | 1 | Hour step increment |
| stepMinute | number | No | 1 | Minute step increment |
| stepSecond | number | No | 1 | Second step increment |
| showSeconds | boolean | No | false | Show seconds in time picker |
| numberOfMonths | number | No | 1 | Number of months to display |
| inline | boolean | No | false | Show calendar inline |
| showPresets | boolean | No | true | Show preset date ranges |
| presets | Array<DatePreset> | No | [] | Custom preset ranges |
| minDuration | number | No | - | Minimum duration in minutes |
| maxDuration | number | No | - | Maximum duration in minutes |
| showValidation | boolean | No | true | Show duration validation |
| error | string | No | - | General error message |
| startError | string | No | - | Start date specific error |
| endError | string | No | - | End date specific error |
| helperText | string | No | - | Helper text below inputs |
| startInputId | string | No | 'start-date' | Start date input ID |
| endInputId | string | No | 'end-date' | End date input ID |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| @update:modelValue | (value: { startDate: Date \| null; endDate: Date \| null }) => void | When date range changes |
| @start-date-change | (date: Date \| null) => void | When start date changes |
| @end-date-change | (date: Date \| null) => void | When end date changes |
| @range-change | (startDate: Date \| null, endDate: Date \| null) => void | When complete range changes |
| @preset-apply | (preset: DatePreset) => void | When a preset is applied |

## DatePreset Interface
```typescript
interface DatePreset {
  key: string      // Unique identifier
  label: string    // Display label
  startDate: Date  // Preset start date
  endDate: Date    // Preset end date
}
```

## Usage Examples

### Basic Date Range
```vue
<template>
  <DateRangePicker
    v-model="dateRange"
    :error="errors.date_range"
    @range-change="onRangeChange"
  />
</template>

<script setup>
import { ref } from 'vue'

const dateRange = ref({
  startDate: null,
  endDate: null
})
</script>
```

### With Time Selection
```vue
<template>
  <DateRangePicker
    v-model="appointmentRange"
    :showTime="true"
    :minDate="new Date()"
    :maxDuration="120" // 2 hours max
    :showValidation="true"
    :helperText="'Select appointment time (max 2 hours)'"
  />
</template>
```

### For Reporting Periods
```vue
<template>
  <DateRangePicker
    v-model="reportPeriod"
    :showPresets="true"
    :maxDate="new Date()"
    :presets="customPresets"
    @preset-apply="loadReportData"
  />
</template>

<script setup>
const customPresets = [
  {
    key: 'lastQuarter',
    label: 'Last Quarter',
    startDate: getQuarterStart(-1),
    endDate: getQuarterEnd(-1)
  },
  {
    key: 'yearToDate',
    label: 'Year to Date',
    startDate: new Date(new Date().getFullYear(), 0, 1),
    endDate: new Date()
  }
]
</script>
```

### With Minimum Duration
```vue
<template>
  <DateRangePicker
    v-model="rentalPeriod"
    :minDuration="1440" // 1 day minimum
    :showValidation="true"
    :startError="errors.start_date"
    :endError="errors.end_date"
    @range-change="calculatePrice"
  />
</template>
```

### Display Mode for Selected Range
```vue
<template>
  <div class="space-y-4">
    <DateRangePicker
      v-model="selectedRange"
      mode="display"
      @range-change="refreshData"
    />
    
    <Button
      label="Change Date Range"
      icon="pi pi-calendar"
      @click="editRange"
    />
  </div>
</template>
```

## Features
- **Flexible Display Modes**: Input mode for selection, display mode for read-only
- **Quick Presets**: Common date ranges (today, this week, last month, etc.)
- **Duration Validation**: Min/max duration constraints with visual feedback
- **Time Support**: Optional time selection with configurable format
- **Date Constraints**: Min/max date limits with smart end date adjustment
- **Custom Presets**: Add your own date range presets
- **Inline Display**: Option to show calendar inline instead of dropdown
- **Auto-completion**: Automatically set end date based on min duration
- **Multiple Months**: Display multiple months for easier range selection

## Default Presets
The component includes these built-in presets:
- Today
- Yesterday
- This Week
- Last Week
- This Month
- Last Month
- Last 30 Days
- This Year

## Duration Validation
- Shows actual duration in human-readable format
- Validates against min/max duration constraints
- Visual feedback for validation errors
- Helps users select appropriate ranges

## Date Constraints
- End date automatically adjusts based on start date and max duration
- Smart min date calculation for end date picker
- Prevents invalid date selections
- Useful for booking, rental, and scheduling scenarios

## Accessibility
- Semantic HTML structure
- Proper labeling for screen readers
- Keyboard navigation support
- Clear error messaging
- High contrast support

## Styling
- Consistent with PrimeVue design system
- Responsive grid layout for date inputs
- Customizable through CSS variables
- Clean, modern interface

## Dependencies
- PrimeVue Calendar (base component)
- PrimeVue Button
- No external dependencies

## Performance Considerations
- Efficient date calculations
- Minimal re-renders
- Optimized for frequent use
- Lightweight implementation

## Methods Exposed
- `focusStart()` - Focus the start date input
- `focusEnd()` - Focus the end date input

## Testing
Test scenarios to cover:
- Date range selection and clearing
- Preset application
- Duration validation
- Date constraints
- Time selection
- Error state display
- Keyboard navigation
- Screen reader compatibility
- Various display modes
- Custom preset functionality

## Notes
- Handles both Date objects and string date values
- Null values represent unset dates
- Duration is calculated in minutes internally
- Time selection affects duration calculations
- Presets include time boundaries for full day ranges

## Best Practices
1. **Use presets for common ranges**: Improves user experience
2. **Set appropriate constraints**: Prevents invalid selections
3. **Show validation feedback**: Helps users understand requirements
4. **Consider timezone implications**: Be clear about timezone handling
5. **Use clear labels**: Especially important for form accessibility
6. **Provide helpful text**: Guide users on expected input

## Future Enhancements
- Timezone selection and conversion
- Relative date presets (next 7 days, etc.)
- Date range exclusion (block out dates)
- Business day calculations
- Holiday integration
- Recurring date ranges
- Drag-to-select on calendar
- Visual range indicator on calendar
- Multiple range selection
- Date range comparison mode