# Toast Notifications

We use Sonner for all user feedback.

## Flash Messages (Inertia redirects)

Backend:
```php
return redirect()->route('somewhere')->with('success', 'Done!');
// or
return back()->with('error', 'Something failed');
```

Frontend (already wired in app layout, just verify flash is being read):
```tsx
// In your layout or a dedicated FlashHandler component
const { flash } = usePage().props

useEffect(() => {
  if (flash.success) toast.success(flash.success)
  if (flash.error) toast.error(flash.error)
}, [flash])
```

## Client-Side Actions (non-Inertia, e.g., API calls)
```tsx
const handleSomething = async () => {
  try {
    await apiCall()
    toast.success('Worked!')
  } catch (e) {
    toast.error(e.message || 'Something went wrong')
  }
}
```

## Rules

- One toast per action (don't stack success + info)
- Keep messages under 60 chars
- Use `success` for completions, `error` for failures, `info` for neutral
- Never use toasts for validation errors (show inline instead)
