# Command Palette MVP

Barebones command palette for Laravel + Vue 3 application.

## Features

- ✅ Global keyboard shortcut (Cmd+K / Ctrl+K)
- ✅ Command parsing with smart inference
- ✅ Autocomplete suggestions
- ✅ Command history (↑/↓ arrows)
- ✅ ASCII table output
- ✅ Help system (`help`, `help company`, etc.)
- ✅ Error/success highlighting
- ✅ Company context header
- ✅ Live parsed status bar (shows entity.verb + flags as you type)

## What's NOT included (intentionally)

- ❌ Multiple themes (single dark theme)
- ❌ Mode switching (single size)
- ❌ Progress bars
- ❌ Welcome screen
- ❌ Animations
- ❌ Debug panels

## File Structure

```
resources/js/
├── app.ts                          # UPDATE - add palette mounting
├── components/
│   └── palette/
│       └── CommandPalette.vue      # NEW - main component
├── palette/
│   ├── autocomplete.ts             # NEW - suggestion generation
│   ├── grammar.ts                  # NEW - command definitions
│   ├── help.ts                     # NEW - help text
│   ├── parser.ts                   # NEW - command parser
│   └── table.ts                    # NEW - ASCII table formatter
└── types/
    └── palette.ts                  # NEW - TypeScript types
```

## Integration Steps

### 1. Copy Files

```bash
# From project root
cp palette-mvp/CommandPalette.vue resources/js/components/palette/
cp palette-mvp/autocomplete.ts resources/js/palette/
cp palette-mvp/grammar.ts resources/js/palette/
cp palette-mvp/help.ts resources/js/palette/
cp palette-mvp/parser.ts resources/js/palette/
cp palette-mvp/table.ts resources/js/palette/
cp palette-mvp/types/palette.ts resources/js/types/
```

### 2. Update app.ts

Replace your existing `app.ts` with the provided one, or merge the changes:

```typescript
import CommandPalette from './components/palette/CommandPalette.vue'

// In setup():
const paletteVisible = ref(false)

function handleKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    paletteVisible.value = !paletteVisible.value
  }
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', handleKeydown))

// In render:
return () => h(Fragment, [
  h(App, props),
  h(CommandPalette, {
    visible: paletteVisible.value,
    'onUpdate:visible': (v: boolean) => paletteVisible.value = v,
  }),
])
```

### 3. Ensure Backend Routes

The palette expects these endpoints:

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'identify.company'])->group(function () {
    Route::post('/commands', [CommandController::class, 'handle'])
        ->middleware('throttle:120,1');
});
```

### 4. Add Monospace Font

Add JetBrains Mono or similar to your CSS:

```css
/* app.css */
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap');
```

Or use a local font.

## Usage

| Shortcut | Action |
|----------|--------|
| `Cmd+K` / `Ctrl+K` | Open/close palette |
| `Enter` | Execute command |
| `Tab` | Accept suggestion |
| `↑` / `↓` | Navigate history/suggestions |
| `Ctrl+L` | Clear output |
| `Ctrl+U` | Clear input |
| `Escape` | Close palette |

## Parsed Status Bar

As you type, a status bar shows how your command is being interpreted:

```
┌─────────────────────────────────────────────────────────┐
│ company . create   --name=Acme Corp  --currency=USD  ✓ ready │
│ [cyan]   [purple]  [yellow flags]                    [green]  │
└─────────────────────────────────────────────────────────┘
```

- **Entity pill** (cyan when valid): The target entity
- **Verb pill** (purple when valid): The operation
- **Flag pills** (yellow): Parsed parameters
- **Status**: `✓ ready` when complete, `✗ error` with message when invalid

This gives immediate feedback whether your command will work before hitting Enter.

## Commands

### Company
```
company.list                    List all companies
company.create "Name" USD       Create company
company.switch slug             Switch to company
company.delete slug             Delete company
```

### User
```
user.list                       List users
user.invite email@example.com   Invite user
user.assign-role email --role=admin
user.deactivate email
```

### Role
```
role.list                       List roles
role.assign --permission=X --role=Y
role.revoke --permission=X --role=Y
```

### Built-in
```
help                            Show all commands
help company                    Show company commands
clear                           Clear output
```

## Shortcuts

All commands support shortcuts:

| Full | Shortcuts |
|------|-----------|
| `company` | `co`, `comp` |
| `user` | `u`, `usr` |
| `role` | `r` |
| `list` | `ls`, `all` |
| `create` | `new`, `add` |
| `delete` | `del`, `rm` |

Examples:
```
co.ls           → company.list
u.inv x@y.com   → user.invite --email=x@y.com
co.new "X" USD  → company.create --name="X" --currency=USD
```

## Adding New Entities

1. Add to `grammar.ts`:

```typescript
export const GRAMMAR = {
  // ... existing ...
  
  invoice: {
    name: 'invoice',
    shortcuts: ['inv', 'i'],
    defaultVerb: 'list',
    verbs: [
      {
        name: 'create',
        aliases: ['new'],
        requiresSubject: true,
        flags: [
          { name: 'customer', type: 'string', required: true },
          { name: 'amount', type: 'number', required: true },
        ],
      },
      // ... more verbs
    ],
  },
}
```

2. Add examples to `help.ts`:

```typescript
const examples = {
  invoice: [
    '  invoice.list --unpaid',
    '  invoice.create "Acme" 1500',
  ],
}
```

3. Add subject inference to `parser.ts` (optional):

```typescript
// In inferFromSubject():
if (result.entity === 'invoice' && result.verb === 'create') {
  // Parse "inv new acme 1500" → customer=acme, amount=1500
}
```

4. Create backend Action class.

## Customization

### Change Colors

Edit the CSS variables in `CommandPalette.vue`:

```css
.palette {
  background: #0f172a;     /* Main background */
  border: 1px solid #334155;
}

.palette-prompt {
  color: #22d3ee;          /* Prompt color (cyan) */
}

.palette-line--error {
  color: #f43f5e;          /* Error color (red) */
}

.palette-line--success {
  color: #10b981;          /* Success color (green) */
}
```

### Change Size

```css
.palette {
  width: 680px;            /* Width */
  max-height: 70vh;        /* Max height */
  top: 10vh;               /* Distance from top */
}
```

## Response Format

Backend should return:

```typescript
// Success
{
  ok: true,
  message: "Company created",
  data: { id: "...", name: "..." },
  redirect: "/companies/acme"  // Optional
}

// Success with table
{
  ok: true,
  data: {
    headers: ["Name", "Slug", "Currency"],
    rows: [
      ["Acme Corp", "acme", "USD"],
      ["Big Co", "big-co", "EUR"]
    ],
    footer: "2 companies"
  }
}

// Error
{
  ok: false,
  message: "Validation failed",
  errors: {
    name: ["Name is required"],
    currency: ["Invalid currency code"]
  }
}
```
