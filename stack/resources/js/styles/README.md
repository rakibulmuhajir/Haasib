# CSS Architecture Guide

## Overview
This project uses a structured CSS architecture with shared stylesheets to avoid duplication and maintain consistency across all pages.

## File Structure

```
resources/js/styles/
├── app.css              # Main entry point (imports all styles)
├── layout/
│   └── shell.css        # Layout shell and sidebar styles
├── pages/
│   └── common.css       # Common page layouts and utilities
├── components/
│   └── common.css       # Reusable component styles
└── themes/
    └── blue-whale.css   # Theme-specific styles
```

## Shared Styles

### Page Layouts (`pages/common.css`)
- **Grid Systems**: 
  - `.content-grid-2-3` (2/3 split)
  - `.content-grid-3-4` (3/4 split) 
  - `.content-grid-5-6` (5/6 split)
- **Page Headers**: `.page-header`, `.page-title`, `.page-subtitle`
- **Stats Grids**: `.stats-grid-2`, `.stats-grid-3`, `.stats-grid-4`, `.stats-grid-6`
- **Form Grids**: `.form-grid-2`, `.form-grid-3`, `.form-grid-4`, `.form-grid-6`
- **Content Areas**: `.content-area`, `.content-section`, `.list-container`
- **Responsive Utilities**: `.mobile-stack`, `.desktop-only`, `.mobile-only`

### Component Styles (`components/common.css`)
- **Cards**: `.card`, `.card-header`, `.card-title`, `.card-content`, `.card-footer`
- **Dashboard Cards**: `.dashboard-card`, `.dashboard-card-header`, `.dashboard-card-title`
- **Stat Cards**: `.stat-card`, `.stat-value`, `.stat-label`, `.stat-change`
- **Action Buttons**: `.action-button`, `.action-button.secondary`, `.action-button.outline`
- **Status Badges**: `.status-badge` with variants (success, warning, error, info, neutral)

## Usage Guidelines

### 1. Use Shared Classes Instead of Custom CSS
Instead of:
```vue
<style scoped>
.my-card {
  @apply bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6;
}
</style>
```

Use:
```vue
<div class="card">
  <!-- content -->
</div>
```

### 2. Choose the Right Grid Layout
- **3/4 split** (default): `content-grid-3-4` 
- **5/6 split** (more main content): `content-grid-5-6`
- **2/3 split** (more sidebar): `content-grid-2-3`

### 3. Use Appropriate Card Types
- **Standard cards**: `.card`
- **Dashboard cards**: `.dashboard-card` (with hover effects)
- **Stat cards**: `.stat-card` (for metrics)

### 4. Responsive Design
All grid classes are mobile-first and responsive:
- Mobile: Single column
- Tablet: 2 columns where appropriate
- Desktop: Full grid layout

## Benefits

1. **Consistency**: All pages use the same design patterns
2. **Maintainability**: Changes in one place affect all pages
3. **Performance**: Less CSS duplication
4. **Developer Experience**: Faster development with predictable patterns
5. **Bundle Size**: Optimized CSS with shared utilities

## Adding New Styles

1. **Component-specific**: Add to the component file if truly unique
2. **Reusable patterns**: Add to `components/common.css`
3. **Layout patterns**: Add to `pages/common.css`
4. **Theme changes**: Add to `themes/blue-whale.css`

## Migration Notes

- Individual page `<style>` sections have been removed where possible
- Common patterns have been extracted to shared files
- Grid layouts standardized to use semantic class names
- Card styles unified across all pages

This architecture ensures a consistent, maintainable, and scalable approach to styling across the entire application.