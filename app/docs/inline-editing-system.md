# Universal Inline Editing System Documentation

This document explains how to use and extend the universal inline editing system throughout your application.

## Overview

The universal inline editing system provides a centralized way to handle inline field updates across all models in your application. It includes:

- **Frontend Service**: `UniversalFieldSaver` - Handles all inline edit operations
- **Backend Controller**: `InlineEditController` - Single endpoint for all inline updates
- **Field Mapping**: Centralized field name mappings between frontend and backend
- **Composable**: `useInlineEdit` - Vue 3 composable for easy integration
- **Component**: `InlineEditable` - Reusable inline edit component

## Adding Support for New Models

### 1. Backend Configuration

Add your model to the `$modelMap` in `InlineEditController.php`:

```php
'your_model' => [
    'model' => YourModel::class,
    'service' => YourModelService::class, // Optional
    'validationRules' => [
        'field_name' => 'required|string|max:255',
        'another_field' => 'nullable|email|max:255',
        // Add validation rules for each field
    ],
    'fieldHandlers' => [
        // Optional custom field handlers for complex fields
        'nested_field' => function ($value, $model) {
            // Custom processing logic
            return ['processed_field' => $processed_value];
        }
    ]
]
```

### 2. Frontend Field Mapping

Add field mappings to `fieldMap.ts` if needed:

```typescript
export const fieldMap: Record<string, string> = {
  // Your model mappings
  yourFieldName: 'your_field_name',
}
```

### 3. Using in Components

Use the `useInlineEdit` composable in your Vue components:

```typescript
const {
  localData: modelData,
  editingField,
  createEditingComputed,
  isSaving,
  saveField: onSaveField,
  cancelEditing
} = useInlineEdit({
  model: 'your_model',
  id: props.model.id,
  data: props.model,
  onSuccess: (updatedModel) => {
    // Handle successful update
  },
  onError: (error) => {
    // Handle errors
  }
})
```

### 4. Template Usage

```vue
<InlineEditable
  v-model="modelData.field_name"
  v-model:editing="isEditingFieldName"
  label="Field Name"
  type="text"
  :saving="isSaving('field_name')"
  @save="onSaveField('field_name', $event)"
  @cancel="cancelEditing"
/>
```

## Model Examples

### Invoice Model Example

```php
// In InlineEditController.php
'invoice' => [
    'model' => Invoice::class,
    'validationRules' => [
        'invoice_number' => 'sometimes|required|string|max:50',
        'invoice_date' => 'sometimes|required|date',
        'due_date' => 'sometimes|required|date|after_or_equal:invoice_date',
        'notes' => 'sometimes|nullable|string|max:2000',
        'status' => 'sometimes|required|string|in:draft,sent,paid,overdue,cancelled',
    ],
    'fieldHandlers' => [
        'status' => function ($value, $model) {
            // Custom status handling logic
            return ['status' => $value];
        }
    ]
]
```

### User Model Example

```php
// In InlineEditController.php
'user' => [
    'model' => User::class,
    'validationRules' => [
        'name' => 'sometimes|required|string|max:255',
        'email' => 'sometimes|required|email|max:255|unique:users,email',
        'role' => 'sometimes|required|string|in:admin,manager,user',
    ]
]
```

## Advanced Features

### Nested Field Handling

For nested fields like addresses, use the field handlers:

```php
'address' => function ($value, $model) {
    $existing = is_array($model->address) 
        ? $model->address 
        : json_decode($model->address ?: '{}', true);
    
    $merged = array_filter(array_merge($existing, $value), fn($v) => $v !== null && $v !== '');
    
    return ['address' => !empty($merged) ? json_encode($merged) : null];
}
```

### Custom Validation

Add complex validation rules as needed:

```php
'validationRules' => [
    'credit_limit' => [
        'sometimes',
        'nullable',
        'numeric',
        'min:0',
        function ($attribute, $value, $fail) {
            if ($value > 1000000) {
                $fail('Credit limit cannot exceed $1,000,000');
            }
        }
    ]
]
```

### Service Integration

For complex business logic, integrate with your service layer:

```php
if (isset($modelConfig['service']) && method_exists($modelConfig['service'], 'updateFromInline')) {
    $service = app($modelConfig['service']);
    $resource = $service->updateFromInline($resource, $updateData);
} else {
    // Direct model update
    $resource->update($updateData);
}
```

## Best Practices

1. **Always use the UniversalFieldSaver** - Don't create custom save logic
2. **Map fields consistently** - Use the field mapping utilities
3. **Handle errors properly** - The UniversalFieldSaver includes error handling
4. **Use optimistic updates** - They provide better UX
5. **Add proper validation** - Both frontend and backend
6. **Log updates** - The controller automatically logs inline edits

## Testing

Test your inline edits:

1. **Happy path**: Edit a field and verify it saves
2. **Validation errors**: Try invalid values
3. **Network errors**: Test with network throttling
4. **Concurrent edits**: Test multiple fields at once
5. **Nested fields**: Test address and other nested structures

## Troubleshooting

### Field not saving?
- Check if the field is in the model's `$fillable`
- Verify field mapping in `fieldMap.ts`
- Check browser network tab for errors

### Validation not working?
- Ensure validation rules are correct
- Check field name matches backend expectations
- Verify the payload structure

### Address fields not updating?
- Ensure address fields are properly nested
- Check the field handler configuration
- Verify JSON encoding in the model

## Migration Guide

To migrate existing inline edit implementations:

1. Replace custom save logic with `useInlineEdit` composable
2. Update controllers to use the `InlineEditController`
3. Add model configuration to the `$modelMap`
4. Update field validations
5. Test thoroughly

This system provides a robust, centralized way to handle inline edits throughout your application while maintaining consistency and reducing code duplication.