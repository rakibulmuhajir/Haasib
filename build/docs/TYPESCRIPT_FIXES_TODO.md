# TypeScript Fixes TODO

This document outlines remaining TypeScript errors and how to fix them. Run `npx vue-tsc --noEmit` to see current errors.

---

## 1. DataTable.vue Generic Type Issues

**File:** `resources/js/components/DataTable.vue`
**Lines:** 82, 86, 208, 248

**Problem:** The component uses generics with `keyof T` but Vue's type inference struggles with complex generic prop types.

**How to Fix:**
1. Open `resources/js/components/DataTable.vue`
2. Find the `sortKey` ref and related code around lines 82-86
3. Cast the type explicitly or use `as string` assertions where needed

**Example Fix:**
```typescript
// Before (line ~82)
const sortKey = ref<string | keyof T | null>(props.defaultSortKey)

// After - use string type and cast when needed
const sortKey = ref<string | null>(props.defaultSortKey as string | null)
```

For the template errors (lines 208, 248), ensure you're accessing properties with proper type guards:
```typescript
// When accessing row[sortKey], add a type guard
if (sortKey.value && typeof sortKey.value === 'string') {
  // access row[sortKey.value]
}
```

---

## 2. InlineEditable.vue AcceptableValue Issue

**File:** `resources/js/components/InlineEditable.vue`
**Line:** 109

**Problem:** `AcceptableValue` type (from Radix Vue) includes `null` but the component expects `string | number | undefined`.

**How to Fix:**
1. Open `resources/js/components/InlineEditable.vue`
2. Find line 109 where the value is being assigned
3. Add a type guard or filter out null values

**Example Fix:**
```typescript
// Before
const value = someAcceptableValue

// After - filter null and cast
const value = someAcceptableValue === null ? undefined : someAcceptableValue as string | number | undefined
```

---

## 3. TwoFactorSetupModal.vue Property Access Issue

**File:** `resources/js/components/TwoFactorSetupModal.vue`
**Line:** 269

**Problem:** Accessing `.code` on a string type.

**How to Fix:**
1. Open `resources/js/components/TwoFactorSetupModal.vue`
2. Find line 269
3. Check if the variable should be an object instead of a string, or if you need to parse it

**Investigation Steps:**
```bash
# Look at the context around line 269
head -280 resources/js/components/TwoFactorSetupModal.vue | tail -20
```

The fix depends on what the code is trying to do - likely either:
- Parse a JSON string: `JSON.parse(stringVar).code`
- Or the variable type definition is wrong and needs to be an object type

---

## 4. CommandPalette.vue Missing activateChip Method

**File:** `resources/js/components/palette/CommandPalette.vue`
**Lines:** 147, 189, 208

**Problem:** Calling `activateChip` method on a component ref, but the method isn't exposed.

**How to Fix:**
1. First, find which component is being referenced (likely `CommandInput.vue` or similar)
2. In that child component, expose the method using `defineExpose`

**In the child component (e.g., CommandInput.vue):**
```typescript
<script setup lang="ts">
// ... existing code

const activateChip = () => {
  // implementation
}

// Add this at the end of script setup
defineExpose({
  activateChip
})
</script>
```

**Alternative:** If `activateChip` should be defined in CommandPalette itself, add the method there.

---

## 5. useInlineEdit.ts Type Incompatibility

**File:** `resources/js/composables/useInlineEdit.ts`
**Lines:** 82, 99

**Problem:**
- Line 82: `{ [x: string]: unknown }` not assignable to `FormDataType`
- Line 99: `Errors` type incompatible with `Record<string, string[]>`

**How to Fix:**

For line 82 (FormDataType issue):
```typescript
// Before
const form = useForm({ [fieldName]: value })

// After - use type assertion
const form = useForm({ [fieldName]: value } as Record<string, unknown>)
```

For line 99 (Errors type issue):
```typescript
// Check what Inertia's Errors type looks like
// It's likely Record<string, string> but you need Record<string, string[]>

// Before
setErrors(form.errors)

// After - transform the errors
const transformedErrors: Record<string, string[]> = {}
Object.entries(form.errors).forEach(([key, value]) => {
  transformedErrors[key] = Array.isArray(value) ? value : [value]
})
setErrors(transformedErrors)
```

---

## 6. companies/Index.vue AcceptableValue Issues

**File:** `resources/js/pages/companies/Index.vue`
**Lines:** 220, 221

**Problem:** `AcceptableValue` includes types that can't be used as string or index.

**How to Fix:**
1. Open `resources/js/pages/companies/Index.vue`
2. Find lines 220-221
3. Add type guards and assertions

**Example Fix:**
```typescript
// Before (line 220)
const value: string = someAcceptableValue

// After
const value: string = String(someAcceptableValue ?? '')

// Before (line 221) - using as index
const result = someObject[acceptableValue]

// After - guard and cast
if (typeof acceptableValue === 'string' || typeof acceptableValue === 'number') {
  const result = someObject[acceptableValue]
}
```

---

## 7. company/Settings.vue Missing Required Props

**File:** `resources/js/pages/company/Settings.vue`
**Line:** 204

**Problem:** Passing empty object `{}` to a component that requires a `title` prop.

**How to Fix:**
1. Open `resources/js/pages/company/Settings.vue`
2. Find line 204
3. Add the required `title` prop

**Example Fix:**
```vue
<!-- Before -->
<SomeComponent v-bind="{}" />

<!-- After -->
<SomeComponent title="Settings" />
```

---

## 8. TaxSettings.vue Boolean to AcceptableValue

**File:** `resources/js/pages/onboarding/TaxSettings.vue`
**Lines:** 118, 120, 132, 188, 190, 205

**Problem:** Passing `boolean` values where `AcceptableValue` is expected (AcceptableValue typically means `string | number | null`).

**How to Fix:**

Option A - Convert boolean to string:
```vue
<!-- Before -->
<RadioGroupItem :value="true" />

<!-- After -->
<RadioGroupItem value="true" />
```

Then handle the conversion in your logic:
```typescript
const isEnabled = selectedValue === 'true'
```

Option B - Extend AcceptableValue type (if you control the component):
```typescript
// In the component's type definition
type AcceptableValue = string | number | boolean | null
```

---

## General Tips

### Running Type Check
```bash
# Check all TypeScript errors
npx vue-tsc --noEmit

# Count errors
npx vue-tsc --noEmit 2>&1 | wc -l

# Filter errors by file
npx vue-tsc --noEmit 2>&1 | grep "DataTable"
```

### Common Patterns

**Type Assertion (use sparingly):**
```typescript
const value = someValue as ExpectedType
```

**Type Guard:**
```typescript
if (typeof value === 'string') {
  // TypeScript knows value is string here
}
```

**Nullish Coalescing:**
```typescript
const safeValue = possiblyNullValue ?? defaultValue
```

### Before Committing
1. Run `npx vue-tsc --noEmit` and ensure no errors
2. Run `npm run build` to verify the build works
3. Test the affected components in the browser

---

## Priority Order

Fix in this order (easiest to hardest):
1. **TaxSettings.vue** - Simple boolean to string conversion
2. **company/Settings.vue** - Add missing prop
3. **InlineEditable.vue** - Simple null filter
4. **companies/Index.vue** - Type guards
5. **useInlineEdit.ts** - Type transformations
6. **TwoFactorSetupModal.vue** - Investigate and fix
7. **CommandPalette.vue** - Add defineExpose
8. **DataTable.vue** - Complex generic fixes

---

## Questions?

If stuck on any fix, check:
1. The component's prop types in its `<script setup>` section
2. The type definitions in `resources/js/types/`
3. The Radix Vue or Shadcn component source for `AcceptableValue` definition
