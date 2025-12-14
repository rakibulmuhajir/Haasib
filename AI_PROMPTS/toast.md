# Toast Notifications

We use Sonner (vue-sonner) for all user feedback.

## Setup (already done)

1. **Sonner component** is in `resources/js/components/ui/sonner/Sonner.vue`
2. **Already added** to `AppSidebarLayout.vue`
3. **CSS imported** in `resources/css/app.css`: `@import 'vue-sonner/style.css';`
4. **Composable** at `resources/js/composables/useFormFeedback.ts`

## Usage in Vue Components

### Import the composable:
```vue
<script setup lang="ts">
import { useFormFeedback } from '@/composables/useFormFeedback'

const { showSuccess, showError } = useFormFeedback()
</script>
```

### Show success toast:
```ts
showSuccess('Vendor credit created successfully')
```

### Show error toast:
```ts
// Simple string
showError('Something went wrong')

// Validation errors object (shows first error)
showError({
  'name': ['Name is required'],
  'email': ['Email is invalid']
})
```

## Common Patterns

### Inertia Form Submission
```vue
<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import { useFormFeedback } from '@/composables/useFormFeedback'

const { showSuccess, showError } = useFormFeedback()

const form = useForm({
  name: '',
  email: '',
})

const handleSubmit = () => {
  form.post(`/${company.slug}/customers`, {
    preserveScroll: true,
    onSuccess: () => {
      showSuccess('Customer created successfully')
      router.visit(`/${company.slug}/customers`)
    },
    onError: (errors) => {
      console.error('Validation errors:', errors)
      showError(errors)
    },
  })
}
</script>
```

### Flash Messages (Inertia redirects)

Backend:
```php
return redirect()->route('customers.index', ['company' => $company->slug])
    ->with('success', 'Customer created!');
// or
return back()->with('error', 'Something failed');
```

Frontend (add to your layout):
```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'
import { toast } from 'vue-sonner'

const page = usePage()

watch(() => page.props.flash, (flash) => {
  if (flash?.success) toast.success(flash.success)
  if (flash?.error) toast.error(flash.error)
}, { deep: true })
</script>
```

### Direct toast usage (without composable)
```vue
<script setup lang="ts">
import { toast } from 'vue-sonner'

const handleAction = async () => {
  try {
    await someApiCall()
    toast.success('Action completed!')
  } catch (e) {
    toast.error(e.message || 'Something went wrong')
  }
}
</script>
```

## Toast Styling

Error toasts are **red** (destructive color).
Success toasts are **green** (accent-green color).

Configured in `Sonner.vue:22-23`:
```ts
error: 'group-[.toaster]:bg-destructive group-[.toaster]:text-destructive-foreground group-[.toaster]:border-destructive',
success: 'group-[.toaster]:bg-accent-green group-[.toaster]:text-accent-green-foreground',
```

## Rules

- One toast per action (don't stack success + info)
- Keep messages under 60 chars
- Use `success` for completions, `error` for failures, `info` for neutral
- Validation errors: Use `showError(errors)` to display first error as toast
- Forms should show validation errors both inline AND as toast for user feedback
