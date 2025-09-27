# UI Components Directory

This directory contains reusable UI components organized by category.

## Directory Structure

```
UI/
├── Layout/           # Layout and structural components
│   ├── PageHeader.vue
│   ├── Card.vue
│   └── ...
├── DataDisplay/      # Data presentation components
│   ├── DataTablePro.vue
│   ├── StatusBadge.vue
│   ├── BalanceDisplay.vue
│   └── ...
├── Forms/           # Form input components
│   ├── CustomerPicker.vue
│   ├── CurrencyPicker.vue
│   ├── CountryPicker.vue
│   └── ...
├── Actions/         # Action and button components
│   ├── ActionButton.vue
│   └── ...
├── Dialogs/         # Modal and dialog components
│   ├── ConfirmationDialog.vue
│   └── ...
├── Navigation/      # Navigation components
│   └── ...
└── Utility/         # Utility and helper components
    └── ...
```

## Component Guidelines

### 1. Naming Conventions
- Use PascalCase for component files: `CustomerPicker.vue`
- Be descriptive: `BalanceDisplay` not just `Balance`
- Prefix specialized components: `CustomerPicker` not just `Picker`

### 2. File Structure
Each component should include:
- Component file (.vue)
- Type definitions if needed (.ts)
- Tests file (.spec.ts)
- Documentation (.md in docs/components)

### 3. Component Documentation
Every component MUST have:
- Description of purpose
- Props table with types
- Usage examples
- Accessibility notes
- Dependencies list

### 4. TypeScript Support
- Prefer `<script setup>` with TypeScript
- Define prop interfaces
- Use proper typing for events
- Export component types when needed

### 5. Styling
- Use scoped styles
- Leverage CSS custom properties
- Follow the design system
- Support dark mode when applicable

### 6. Accessibility
- Semantic HTML structure
- ARIA attributes where needed
- Keyboard navigation support
- Screen reader testing

### 7. Testing
- Unit tests for all components
- Test all prop combinations
- Test user interactions
- Test accessibility features

## Component Development Workflow

1. **Planning**
   - Define component purpose and scope
   - Identify all required props and events
   - Consider edge cases

2. **Development**
   - Create component file with proper structure
   - Implement functionality
   - Add TypeScript types
   - Write tests

3. **Documentation**
   - Create documentation file
   - Add usage examples
   - Document accessibility features

4. **Review**
   - Code review for standards
   - Accessibility review
   - Performance check

## Existing Components to Extract

Based on invoicing module audit:

### High Priority
1. **CustomerPicker** - Enhanced customer selector with search and details
2. **CurrencyPicker** - Currency selection with codes and symbols
3. **CountryPicker** - Country selector with flags
4. **AmountInput** - Currency-aware amount input
5. **DateRangePicker** - Date range selection

### Medium Priority
1. **StatusFilter** - Status filtering component
2. **SearchInput** - Standardized search input
3. **FilterChips** - Active filter display
4. **ActionsMenu** - Dropdown action menu
5. **InfoCard** - Standardized information card

### Low Priority
1. **AvatarGenerator** - Initials-based avatar
2. **Tooltip** - Enhanced tooltip component
3. **SkeletonLoader** - Loading skeleton states
4. **EmptyState** - Empty state display

## Integration Steps

1. Create component in appropriate category
2. Update main component exports
3. Add to documentation index
4. Update existing pages to use new component
5. Test across different contexts

## Notes

- Always check if a component exists before creating new ones
- Prefer composition over inheritance
- Keep components focused and single-purpose
- Document breaking changes when updating components