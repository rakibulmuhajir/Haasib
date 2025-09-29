# Admin & Feature Areas Refactoring Plan

## Overview
This document outlines the plan to refactor Admin pages and other feature areas to align with the patterns established in the Ledger module refactoring.

## Priority Levels
- **High**: Critical issues that affect functionality or consistency
- **Medium**: Important for maintainability and UX consistency
- **Low**: Nice to have, but not blocking

## 1. Admin Pages Refactoring (High Priority)

### 1.1 Missing usePage imports (All 10 files)
**Files**: All Admin Vue pages
**Issue**: Cannot access page props, auth permissions, or shared state
**Fix**: Add `usePage` import to all files
**Effort**: Low

### 1.2 TypeScript Support (6 files)
**Files**: All Admin pages except Companies/Index.vue and Users/Index.vue
**Issue**: Missing type safety and better development experience
**Fix**: Change `<script setup>` to `<script setup lang="ts">` and add proper interfaces
**Effort**: Medium

### 1.3 Hardcoded USD Currency Formatting
**Files**: CompanyOverview.vue, CompanyCurrenciesSection.vue
**Issue**: Inconsistent currency handling
**Fix**: Replace with `useFormatting` composable
**Effort**: Medium

### 1.4 useForm Implementation
**Files**: Companies/Create.vue, Users/Create.vue, Companies/Show.vue, Users/Show.vue
**Issue**: Direct HTTP calls instead of Inertia forms
**Fix**: Replace with `useForm` for better form handling
**Effort**: High

### 1.5 Delete Confirmation Consistency
**Files**: Companies/Index.vue, Users/Index.vue, Companies/Show.vue
**Issue**: Mixed use of native confirm() and PrimeVue useConfirm
**Fix**: Implement `useDeleteConfirmation` composable
**Effort**: Medium

### 1.6 Page Layout Patterns
**Files**: Most Admin pages
**Issue**: Inconsistent breadcrumb handling, missing action cleanup
**Fix**: Implement consistent `usePageActions` and `onUnmounted` cleanup
**Effort**: Medium

## 2. Other Feature Areas (Medium Priority)

### 2.1 Invoicing/Currencies/Index.vue
- Add missing `usePage` import
- Ensure consistent delete confirmation patterns

### 2.2 Invoicing/Payments/Index.vue
- Add missing `usePage` import
- Replace hardcoded USD locale with dynamic formatting

### 2.3 Invoicing/Invoices/Index.vue
- Add missing `usePage` import

## 3. Implementation Strategy

### Phase 1: Foundation (Week 1)
1. Add `usePage` imports to all Admin pages
2. Convert Admin pages to TypeScript
3. Create any missing composables for shared functionality

### Phase 2: Forms & Data Handling (Week 2)
1. Implement `useForm` in all Admin create/edit pages
2. Replace hardcoded currency formatting with `useFormatting`
3. Standardize error handling patterns

### Phase 3: UX Consistency (Week 3)
1. Implement `useDeleteConfirmation` across all Admin pages
2. Standardize page layout patterns with `usePageActions`
3. Add proper loading states and toast notifications

### Phase 4: Polish & Testing (Week 4)
1. Review and fix any remaining inconsistencies
2. Add proper TypeScript interfaces where missing
3. Test all functionality end-to-end

## 4. Specific Files to Update

### Admin/Companies/
- **Index.vue**: Add usePage, TypeScript, delete confirmation composable
- **Create.vue**: Convert to TypeScript, implement useForm
- **Show.vue**: Convert to TypeScript, implement useForm, add delete confirmation
- **CompanyMembersSection.vue**: Convert to TypeScript
- **CompanyInviteSection.vue**: Convert to TypeScript
- **CompanyOverview.vue**: Convert to TypeScript, fix currency formatting
- **CompanyCurrenciesSection.vue**: Convert to TypeScript, fix currency formatting

### Admin/Users/
- **Index.vue**: Add usePage, TypeScript, delete confirmation composable
- **Create.vue**: Convert to TypeScript, implement useForm
- **Show.vue**: Convert to TypeScript, implement useForm, add delete confirmation

### Other Areas
- **Invoicing/Currencies/Index.vue**: Add usePage import
- **Invoicing/Payments/Index.vue**: Add usePage import, fix hardcoded USD
- **Invoicing/Invoices/Index.vue**: Add usePage import

## 5. Success Criteria

- All Admin pages follow the same patterns as Ledger pages
- Consistent use of composables across all feature areas
- Proper TypeScript support throughout
- Unified UX for forms, deletions, and notifications
- Code is maintainable and follows established conventions

## 6. Risks & Mitigations

**Risk**: Breaking existing functionality during refactoring
**Mitigation**: Test thoroughly after each change, implement incrementally

**Risk**: Time estimates may be too optimistic
**Mitigation**: Focus on high-impact items first, defer less critical changes

**Risk**: Resistance to changing working code
**Mitigation**: Emphasize long-term maintainability benefits

## 7. Next Steps

1. Get approval for this refactoring plan
2. Set up proper testing environment
3. Begin with Phase 1 implementation
4. Review progress after each phase
5. Update documentation as changes are made

---

*This plan should be reviewed and adjusted as needed during implementation.*