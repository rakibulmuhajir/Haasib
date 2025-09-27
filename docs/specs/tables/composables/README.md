# Composables Documentation

This directory contains detailed documentation for all custom composables used in the Haasib application.

## Available Composables

### useDataTable
- **File**: `useDataTable.md`
- **Description**: Comprehensive data table functionality with filtering, sorting, pagination, and DSL support
- **Use Case**: Managing data tables with server-side operations

### useDeleteConfirmation
- **Description**: Handles delete operations with confirmation dialogs
- **Use Case**: Safe deletion of records with user confirmation

### useFormatting
- **Description**: Common formatting utilities for dates, currencies, and custom formats
- **Use Case**: Consistent data display across the application

### usePageActions
- **Description**: Manages page-level action buttons in headers
- **Use Case**: Standardized action button placement and behavior

## Composable Pattern

All composables follow these conventions:

1. **Return objects** with reactive state and methods
2. **Use TypeScript interfaces** for type safety
3. **Accept configuration objects** as parameters
4. **Provide clear documentation** with examples
5. **Handle edge cases** and error states
6. **Work seamlessly** with Inertia.js and Laravel

## Creating New Composables

When creating new composables:

1. **Follow the naming convention**: `use[FeatureName].ts`
2. **Place in appropriate directory**:
   - `/resources/js/composables/` - General composables
   - `/resources/js/Composables/` - App-specific composables
3. **Export single function** as default export
4. **Document all parameters** and return values
5. **Include TypeScript types** and interfaces
6. **Provide usage examples**

## Example Composable Structure

```typescript
// useFeature.ts
import { ref, computed } from 'vue'

interface UseFeatureOptions {
  initialValue?: any
  enabled?: boolean
}

interface UseFeatureReturn {
  state: Ref<any>
  computedValue: ComputedRef<any>
  method: () => void
}

export function useFeature(options: UseFeatureOptions = {}): UseFeatureReturn {
  // Implementation
}
```

## Best Practices

1. **Keep composables focused** on a single concern
2. **Avoid side effects** in the composable itself
3. **Use dependency injection** for services
4. **Provide cleanup methods** when needed
5. **Test composables independently**
6. **Use composable chaining** for complex logic

## Related Documentation

- [Components Documentation](../components/README.md)
- [Utility Functions](../utils/README.md)
- [Vue Style Guide](../style-guide.md)