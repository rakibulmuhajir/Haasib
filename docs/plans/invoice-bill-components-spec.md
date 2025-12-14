# Invoice & Bill Component Specifications

**Status**: Ready for Implementation
**Date**: 2025-12-12
**Related**: `docs/plans/invoice-bill-creation-ux.md`

---

## 1. Component Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    INVOICE/BILL CREATION                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  QuickInvoiceCreate / QuickBillCreate               │   │
│  │  (Page Component - Owner Mode)                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                          OR                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  InvoiceCreate / BillCreate                         │   │
│  │  (Page Component - Accountant Mode)                 │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Uses shared components:                                    │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐        │
│  │EntitySearch  │ │AmountInput   │ │DueDatePicker │        │
│  └──────────────┘ └──────────────┘ └──────────────┘        │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐        │
│  │QuickAddModal │ │TaxToggle     │ │LineItemsGrid │        │
│  └──────────────┘ └──────────────┘ └──────────────┘        │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Shared Components

### 2.1 EntitySearch (Customer/Vendor Search)

**File**: `resources/js/components/forms/EntitySearch.vue`

**Purpose**: Searchable dropdown with recent items and quick-add capability.

**Props**:
```typescript
interface EntitySearchProps {
  modelValue: string | null          // Selected entity ID
  entityType: 'customer' | 'vendor'  // Which type to search
  placeholder?: string               // Search placeholder
  recentLimit?: number               // Number of recent items (default: 3)
  allowQuickAdd?: boolean            // Show "+ New" option (default: true)
  disabled?: boolean
  error?: string                     // Validation error message
}
```

**Emits**:
```typescript
interface EntitySearchEmits {
  'update:modelValue': [id: string | null]
  'entity-selected': [entity: Customer | Vendor]
  'quick-add-click': []
}
```

**Template Structure**:
```vue
<template>
  <div class="entity-search">
    <!-- Search Input -->
    <Popover v-model:open="open">
      <PopoverTrigger as-child>
        <Button variant="outline" role="combobox" class="w-full justify-between">
          <span v-if="selectedEntity">{{ selectedEntity.name }}</span>
          <span v-else class="text-muted-foreground">{{ placeholder }}</span>
          <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>

      <PopoverContent class="w-full p-0">
        <Command>
          <CommandInput :placeholder="t('searchCustomers')" />

          <!-- Recent Items -->
          <CommandGroup v-if="recentItems.length" :heading="t('recentCustomers')">
            <CommandItem
              v-for="item in recentItems"
              :key="item.id"
              :value="item.id"
              @select="selectEntity(item)"
            >
              {{ item.name }}
            </CommandItem>
          </CommandGroup>

          <!-- Search Results -->
          <CommandGroup v-if="searchResults.length" heading="Results">
            <CommandItem
              v-for="item in searchResults"
              :key="item.id"
              :value="item.id"
              @select="selectEntity(item)"
            >
              {{ item.name }}
              <span class="text-muted-foreground ml-2">{{ item.email }}</span>
            </CommandItem>
          </CommandGroup>

          <CommandEmpty>No results found</CommandEmpty>

          <!-- Quick Add -->
          <CommandGroup v-if="allowQuickAdd">
            <CommandItem @select="$emit('quick-add-click')">
              <Plus class="mr-2 h-4 w-4" />
              {{ t('addNewCustomer') }}
            </CommandItem>
          </CommandGroup>
        </Command>
      </PopoverContent>
    </Popover>

    <!-- Error -->
    <p v-if="error" class="text-sm text-destructive mt-1">{{ error }}</p>
  </div>
</template>
```

**Data Fetching**:
```typescript
// Fetch recent items on mount
const { data: recentItems } = useQuery({
  queryKey: ['recent', entityType],
  queryFn: () => fetchRecentEntities(entityType, recentLimit),
})

// Search on input (debounced)
const { data: searchResults } = useQuery({
  queryKey: ['search', entityType, searchQuery],
  queryFn: () => searchEntities(entityType, searchQuery),
  enabled: searchQuery.length >= 2,
})
```

---

### 2.2 AmountInput

**File**: `resources/js/components/forms/AmountInput.vue`

**Purpose**: Currency-aware amount input with formatting.

**Props**:
```typescript
interface AmountInputProps {
  modelValue: number | null
  currency?: string              // ISO code (default: company currency)
  placeholder?: string
  disabled?: boolean
  error?: string
  size?: 'sm' | 'md' | 'lg'     // Input size
  showCurrency?: boolean        // Show currency symbol (default: true)
}
```

**Features**:
- Auto-formats on blur (1000 → 1,000.00)
- Strips formatting on focus for editing
- Respects currency decimal places
- Keyboard: Enter to confirm, Tab to next field

**Template**:
```vue
<template>
  <div class="amount-input" :class="sizeClass">
    <span v-if="showCurrency" class="currency-symbol">{{ currencySymbol }}</span>
    <Input
      type="text"
      inputmode="decimal"
      :value="displayValue"
      @focus="onFocus"
      @blur="onBlur"
      @input="onInput"
      :placeholder="placeholder"
      :disabled="disabled"
      :class="{ 'pl-12': showCurrency }"
    />
    <p v-if="error" class="text-sm text-destructive mt-1">{{ error }}</p>
  </div>
</template>
```

---

### 2.3 DueDatePicker

**File**: `resources/js/components/forms/DueDatePicker.vue`

**Purpose**: Due date selection with relative options (Owner mode) or calendar (Accountant mode).

**Props**:
```typescript
interface DueDatePickerProps {
  modelValue: string | null      // ISO date string
  invoiceDate?: string           // Reference date for calculating relative
  disabled?: boolean
  error?: string
}
```

**Owner Mode UI**:
```vue
<template>
  <!-- Owner Mode: Relative dropdown -->
  <div v-if="!isAccountantMode" class="due-date-picker">
    <Select v-model="relativeOption" @update:modelValue="updateFromRelative">
      <SelectTrigger>
        <SelectValue :placeholder="t('dueIn')" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value="receipt">{{ t('dueOnReceipt') }}</SelectItem>
        <SelectItem value="7">{{ tpl('dueInDays', { days: 7 }) }}</SelectItem>
        <SelectItem value="15">{{ tpl('dueInDays', { days: 15 }) }}</SelectItem>
        <SelectItem value="30">{{ tpl('dueInDays', { days: 30 }) }}</SelectItem>
        <SelectItem value="45">{{ tpl('dueInDays', { days: 45 }) }}</SelectItem>
        <SelectItem value="60">{{ tpl('dueInDays', { days: 60 }) }}</SelectItem>
        <SelectItem value="eom">{{ t('dueEndOfMonth') }}</SelectItem>
        <SelectItem value="custom">Custom date...</SelectItem>
      </SelectContent>
    </Select>

    <!-- Show computed date -->
    <span class="text-sm text-muted-foreground ml-2">
      {{ formatDate(modelValue) }}
    </span>
  </div>

  <!-- Accountant Mode: Standard date picker -->
  <DatePicker v-else v-model="modelValue" :disabled="disabled" />
</template>
```

---

### 2.4 TaxToggle

**File**: `resources/js/components/forms/TaxToggle.vue`

**Purpose**: Simple tax checkbox that applies customer/vendor default tax profile.

**Props**:
```typescript
interface TaxToggleProps {
  modelValue: boolean            // Whether tax is applied
  entityId?: string              // Customer/Vendor ID to get default tax
  entityType: 'customer' | 'vendor'
  label?: string                 // Override label
  inclusive?: boolean            // For bills: tax inclusive mode
}
```

**Template**:
```vue
<template>
  <div class="tax-toggle flex items-center gap-2">
    <Checkbox
      :id="id"
      :checked="modelValue"
      @update:checked="$emit('update:modelValue', $event)"
    />
    <Label :for="id" class="text-sm cursor-pointer">
      {{ label || (entityType === 'vendor' ? t('includesTax') : t('addTax')) }}
    </Label>

    <!-- Show which tax will be applied -->
    <span v-if="modelValue && defaultTaxCode" class="text-xs text-muted-foreground">
      ({{ defaultTaxCode.name }} - {{ defaultTaxCode.rate }}%)
    </span>
  </div>
</template>
```

---

### 2.5 QuickAddModal

**File**: `resources/js/components/forms/QuickAddModal.vue`

**Purpose**: Inline modal for quick customer/vendor creation without leaving the form.

**Props**:
```typescript
interface QuickAddModalProps {
  open: boolean
  entityType: 'customer' | 'vendor'
  initialName?: string           // Pre-fill from search query
}
```

**Emits**:
```typescript
interface QuickAddModalEmits {
  'update:open': [value: boolean]
  'created': [entity: Customer | Vendor]
}
```

**Template**:
```vue
<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ t(entityType === 'customer' ? 'quickAddCustomer' : 'quickAddVendor') }}</DialogTitle>
      </DialogHeader>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <!-- Name (Required) -->
        <div>
          <Label for="name">Name *</Label>
          <Input
            id="name"
            v-model="form.name"
            :placeholder="entityType === 'customer' ? 'Acme Corporation' : 'Office Depot'"
            autofocus
          />
          <p v-if="form.errors.name" class="text-sm text-destructive mt-1">
            {{ form.errors.name }}
          </p>
        </div>

        <!-- Email (Optional) -->
        <div>
          <Label for="email">Email</Label>
          <Input
            id="email"
            type="email"
            v-model="form.email"
            placeholder="billing@example.com"
          />
        </div>

        <!-- Help Text -->
        <p class="text-sm text-muted-foreground">
          {{ t('addDetailsLater') }}
        </p>

        <DialogFooter>
          <Button type="button" variant="outline" @click="$emit('update:open', false)">
            Cancel
          </Button>
          <Button type="submit" :disabled="form.processing">
            {{ t('createAndSelect') }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
```

**API Call**:
```typescript
const form = useForm({
  name: props.initialName || '',
  email: '',
})

const handleSubmit = () => {
  form.post(route(`${props.entityType}s.quick-store`, { company: company.slug }), {
    preserveScroll: true,
    onSuccess: (page) => {
      const entity = page.props.entity
      emit('created', entity)
      emit('update:open', false)
      showSuccess(t(entityType === 'customer' ? 'customerCreated' : 'vendorCreated'))
    },
  })
}
```

---

### 2.6 LineItemsGrid (Accountant Mode)

**File**: `resources/js/components/forms/LineItemsGrid.vue`

**Purpose**: Full line items grid for accountant mode with all columns.

**Props**:
```typescript
interface LineItem {
  id: string
  item_id?: string
  description: string
  quantity: number
  unit_price: number
  account_id?: string
  tax_code_id?: string
  line_total: number
}

interface LineItemsGridProps {
  modelValue: LineItem[]
  type: 'invoice' | 'bill'
  currency: string
  showAccounts?: boolean         // Show account column (default: true in accountant)
  showTax?: boolean              // Show tax column (default: true)
  disabled?: boolean
}
```

**Columns**:
| Column | Invoice | Bill | Notes |
|--------|:-------:|:----:|-------|
| Item/Product | Optional | Optional | Searchable dropdown |
| Description | Required | Required | Text |
| Quantity | Required | Required | Number, default 1 |
| Rate/Price | Required | Required | Amount |
| Account | Accountant only | Accountant only | Account dropdown |
| Tax | Optional | Optional | Tax code dropdown |
| Amount | Computed | Computed | Read-only |
| Actions | Delete | Delete | Row delete |

---

## 3. Page Components

### 3.1 QuickInvoiceCreate (Owner Mode)

**File**: `modules/Accounting/Resources/js/pages/invoices/QuickCreate.vue`

**Purpose**: Simplified invoice creation for Owner mode.

**Route**: `/{company}/invoices/create` (when mode = owner)

**Template**:
```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { useLexicon } from '@/composables/useLexicon'
import { useUserMode } from '@/composables/useUserMode'
import { useFormFeedback } from '@/composables/useFormFeedback'
import EntitySearch from '@/components/forms/EntitySearch.vue'
import AmountInput from '@/components/forms/AmountInput.vue'
import DueDatePicker from '@/components/forms/DueDatePicker.vue'
import TaxToggle from '@/components/forms/TaxToggle.vue'
import QuickAddModal from '@/components/forms/QuickAddModal.vue'

const { t, tpl } = useLexicon()
const { isAccountantMode } = useUserMode()
const { showSuccess, showError } = useFormFeedback()

const props = defineProps<{
  company: Company
  recentCustomers: Customer[]
  defaultTaxCode: TaxCode | null
  defaultTerms: number
}>()

// Redirect to full form if accountant mode
if (isAccountantMode.value) {
  router.replace(route('invoices.create', { company: props.company.slug }))
}

const form = useForm({
  customer_id: null as string | null,
  description: '',
  amount: null as number | null,
  apply_tax: false,
  due_date: null as string | null,
  invoice_date: new Date().toISOString().split('T')[0],
})

const showQuickAdd = ref(false)
const selectedCustomer = ref<Customer | null>(null)

// Compute due date from terms
const computedDueDate = computed(() => {
  if (form.due_date) return form.due_date
  const date = new Date(form.invoice_date)
  date.setDate(date.getDate() + (selectedCustomer.value?.payment_terms || props.defaultTerms || 30))
  return date.toISOString().split('T')[0]
})

const handleCustomerSelected = (customer: Customer) => {
  selectedCustomer.value = customer
  form.customer_id = customer.id
  // Apply customer defaults
  if (customer.payment_terms) {
    // Update due date based on customer terms
  }
}

const handleQuickAddCreated = (customer: Customer) => {
  handleCustomerSelected(customer)
  showQuickAdd.value = false
}

const saveDraft = () => {
  form.transform((data) => ({
    ...data,
    due_date: computedDueDate.value,
    status: 'draft',
  })).post(route('invoices.store', { company: props.company.slug }), {
    preserveScroll: true,
    onSuccess: () => {
      showSuccess(t('invoiceSaved'))
    },
    onError: (errors) => showError(errors),
  })
}

const sendInvoice = () => {
  form.transform((data) => ({
    ...data,
    due_date: computedDueDate.value,
    status: 'approved',
    send_immediately: true,
  })).post(route('invoices.store', { company: props.company.slug }), {
    preserveScroll: true,
    onSuccess: () => {
      showSuccess(t('invoiceSent'))
    },
    onError: (errors) => showError(errors),
  })
}
</script>

<template>
  <Head :title="t('newInvoice')" />

  <div class="max-w-lg mx-auto py-8">
    <h1 class="text-2xl font-semibold mb-6">{{ t('newInvoice') }}</h1>

    <form @submit.prevent class="space-y-6">
      <!-- Customer -->
      <div>
        <Label class="text-base font-medium">{{ t('whoIsThisFor') }}</Label>
        <EntitySearch
          v-model="form.customer_id"
          entity-type="customer"
          :placeholder="t('searchCustomers')"
          @entity-selected="handleCustomerSelected"
          @quick-add-click="showQuickAdd = true"
          :error="form.errors.customer_id"
          class="mt-2"
        />
      </div>

      <Separator />

      <!-- Description -->
      <div>
        <Label class="text-base font-medium">{{ t('whatDidYouSell') }}</Label>
        <Textarea
          v-model="form.description"
          :placeholder="t('descriptionPlaceholder')"
          rows="2"
          class="mt-2"
        />
        <p v-if="form.errors.description" class="text-sm text-destructive mt-1">
          {{ form.errors.description }}
        </p>
      </div>

      <!-- Amount + Tax -->
      <div>
        <Label class="text-base font-medium">{{ t('howMuch') }}</Label>
        <div class="flex items-center gap-4 mt-2">
          <AmountInput
            v-model="form.amount"
            :currency="company.currency_code"
            size="lg"
            class="flex-1"
            :error="form.errors.amount"
          />
          <TaxToggle
            v-model="form.apply_tax"
            entity-type="customer"
            :entity-id="form.customer_id"
          />
        </div>
      </div>

      <!-- Due Date -->
      <div>
        <Label class="text-base font-medium">{{ t('dueIn') }}</Label>
        <DueDatePicker
          v-model="form.due_date"
          :invoice-date="form.invoice_date"
          class="mt-2"
        />
      </div>

      <Separator />

      <!-- Expand Details -->
      <Collapsible>
        <CollapsibleTrigger class="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
          <ChevronRight class="h-4 w-4 transition-transform" />
          {{ t('addMoreDetails') }}
        </CollapsibleTrigger>
        <CollapsibleContent class="mt-4 space-y-4">
          <!-- Reference -->
          <div>
            <Label>Reference</Label>
            <Input
              v-model="form.reference"
              :placeholder="t('referencePlaceholder')"
            />
          </div>
          <!-- Notes -->
          <div>
            <Label>Notes</Label>
            <Textarea
              v-model="form.notes"
              placeholder="Notes for the customer..."
              rows="2"
            />
          </div>
        </CollapsibleContent>
      </Collapsible>

      <Separator />

      <!-- Totals -->
      <div class="bg-muted/50 rounded-lg p-4 space-y-2">
        <div class="flex justify-between">
          <span>Subtotal</span>
          <span>{{ formatCurrency(form.amount || 0) }}</span>
        </div>
        <div v-if="form.apply_tax" class="flex justify-between text-muted-foreground">
          <span>Tax ({{ defaultTaxCode?.rate || 0 }}%)</span>
          <span>{{ formatCurrency(taxAmount) }}</span>
        </div>
        <Separator />
        <div class="flex justify-between font-semibold text-lg">
          <span>Total</span>
          <span>{{ formatCurrency(totalAmount) }}</span>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-3">
        <Button
          type="button"
          variant="outline"
          @click="saveDraft"
          :disabled="form.processing"
          class="flex-1"
        >
          {{ t('saveDraft') }}
        </Button>
        <Button
          type="button"
          @click="sendInvoice"
          :disabled="form.processing || !isValid"
          class="flex-1"
        >
          {{ t('sendInvoice') }}
        </Button>
      </div>
    </form>

    <!-- Quick Add Customer Modal -->
    <QuickAddModal
      v-model:open="showQuickAdd"
      entity-type="customer"
      @created="handleQuickAddCreated"
    />
  </div>
</template>
```

---

### 3.2 QuickBillCreate (Owner Mode)

**File**: `modules/Accounting/Resources/js/pages/bills/QuickCreate.vue`

**Route**: `/{company}/bills/create` (when mode = owner)

Same pattern as QuickInvoiceCreate with these differences:

| Aspect | Invoice | Bill |
|--------|---------|------|
| Entity | Customer | Vendor |
| Question 1 | "Who is this for?" | "Who is it from?" |
| Question 2 | "What did you sell?" | "What did you buy?" |
| Tax toggle | "Add tax" | "Includes tax" |
| Extra field | — | Category (required) |
| Primary action | "Send Invoice" | "Save & Pay Now" |

**Category field (Bill only)**:
```vue
<!-- Category - Required for Bills -->
<div>
  <Label class="text-base font-medium">{{ t('expenseCategory') }}</Label>
  <Select v-model="form.account_id" class="mt-2">
    <SelectTrigger>
      <SelectValue placeholder="Select a category..." />
    </SelectTrigger>
    <SelectContent>
      <SelectGroup v-for="group in expenseCategories" :key="group.type">
        <SelectLabel>{{ group.label }}</SelectLabel>
        <SelectItem v-for="account in group.accounts" :key="account.id" :value="account.id">
          {{ account.name }}
        </SelectItem>
      </SelectGroup>
    </SelectContent>
  </Select>
  <p v-if="form.errors.account_id" class="text-sm text-destructive mt-1">
    {{ form.errors.account_id }}
  </p>
</div>
```

---

### 3.3 InvoiceCreate (Accountant Mode)

**File**: `modules/Accounting/Resources/js/pages/invoices/Create.vue`

**Route**: `/{company}/invoices/create` (when mode = accountant)

Full form with all fields visible:
- Header section (customer, dates, terms, currency, reference)
- Line items grid
- Totals section
- Journal preview
- Notes section

---

## 4. API Contracts

### 4.1 Quick Create Invoice (Minimal Payload)

**Endpoint**: `POST /{company}/invoices`

**Request (Owner Mode - Minimal)**:
```typescript
interface QuickInvoiceRequest {
  // Required
  customer_id: string
  description: string
  amount: number

  // Auto-computed if not provided
  invoice_date?: string          // Default: today
  due_date?: string              // Default: from customer terms
  currency_code?: string         // Default: customer currency

  // Optional
  apply_tax?: boolean            // Default: false
  reference?: string
  notes?: string

  // Control
  status?: 'draft' | 'approved'  // Default: draft
  send_immediately?: boolean     // If status=approved
}
```

**Response**:
```typescript
interface InvoiceResponse {
  id: string
  invoice_number: string
  status: 'draft' | 'approved' | 'sent'
  grand_total_tc: number
  grand_total_fc: number
  // ... full invoice object
}
```

### 4.2 Full Create Invoice (Accountant Payload)

**Request (Accountant Mode - Full)**:
```typescript
interface FullInvoiceRequest {
  // Required
  customer_id: string
  invoice_date: string
  due_date: string
  currency_code: string

  // Line items (required)
  line_items: Array<{
    description: string
    quantity: number
    unit_price: number
    revenue_account_id: string
    tax_code_id?: string
    discount?: number
  }>

  // Optional
  invoice_number?: string        // Auto-generate if not provided
  reference?: string
  notes?: string
  internal_notes?: string
  payment_terms_id?: string

  // Control
  status?: 'draft' | 'approved'
}
```

### 4.3 Quick Create Bill (Minimal Payload)

**Endpoint**: `POST /{company}/bills`

**Request (Owner Mode - Minimal)**:
```typescript
interface QuickBillRequest {
  // Required
  vendor_id: string
  description: string
  amount: number
  account_id: string             // Expense category - REQUIRED for bills

  // Auto-computed if not provided
  bill_date?: string             // Default: today
  due_date?: string              // Default: from vendor terms
  currency_code?: string         // Default: vendor currency

  // Optional
  tax_inclusive?: boolean        // Default: false
  bill_number?: string           // Vendor's invoice number
  reference?: string

  // Control
  status?: 'draft' | 'approved'
  pay_immediately?: boolean      // If status=approved, create payment
}
```

### 4.4 Quick Store Customer/Vendor

**Endpoint**: `POST /{company}/customers/quick-store`
**Endpoint**: `POST /{company}/vendors/quick-store`

**Request**:
```typescript
interface QuickEntityRequest {
  name: string                   // Required
  email?: string                 // Optional
}
```

**Response**:
```typescript
interface QuickEntityResponse {
  id: string
  name: string
  email?: string
  // Defaults applied:
  currency_code: string          // Company default
  payment_terms: number          // Company default
}
```

---

## 5. Backend Changes Required

### 5.1 Controller Updates

**InvoiceController.php**:
```php
public function store(StoreInvoiceRequest $request): RedirectResponse
{
    // Handle minimal payload (Owner mode)
    if ($this->isMinimalPayload($request)) {
        $data = $this->expandMinimalInvoice($request->validated());
    } else {
        $data = $request->validated();
    }

    $invoice = Bus::dispatch('invoice.create', $data);

    // Auto-send if requested
    if ($request->boolean('send_immediately') && $invoice->status === 'approved') {
        Bus::dispatch('invoice.send', ['invoice_id' => $invoice->id]);
    }

    return redirect()
        ->route('invoices.show', ['company' => $this->company->slug, 'invoice' => $invoice->id])
        ->with('success', __('Invoice created'));
}

private function expandMinimalInvoice(array $data): array
{
    $customer = Customer::findOrFail($data['customer_id']);

    // Create single line item from amount + description
    $data['line_items'] = [[
        'description' => $data['description'],
        'quantity' => 1,
        'unit_price' => $data['amount'],
        'revenue_account_id' => $this->company->default_revenue_account_id,
        'tax_code_id' => $data['apply_tax'] ? $customer->tax_code_id : null,
    ]];

    // Apply defaults
    $data['invoice_date'] ??= now()->toDateString();
    $data['currency_code'] ??= $customer->currency_code ?? $this->company->currency_code;
    $data['due_date'] ??= $this->calculateDueDate($data['invoice_date'], $customer);

    unset($data['amount'], $data['description'], $data['apply_tax']);

    return $data;
}
```

### 5.2 New Routes

```php
// Quick-add endpoints for inline creation
Route::post('/{company}/customers/quick-store', [CustomerController::class, 'quickStore']);
Route::post('/{company}/vendors/quick-store', [VendorController::class, 'quickStore']);

// Recent items for search
Route::get('/{company}/customers/recent', [CustomerController::class, 'recent']);
Route::get('/{company}/vendors/recent', [VendorController::class, 'recent']);
```

### 5.3 Request Validation

**StoreInvoiceRequest.php** - Support both minimal and full:
```php
public function rules(): array
{
    // Minimal payload rules
    if ($this->isMinimalPayload()) {
        return [
            'customer_id' => 'required|exists:acct.customers,id',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'apply_tax' => 'boolean',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|in:draft,approved',
            'send_immediately' => 'boolean',
        ];
    }

    // Full payload rules
    return [
        'customer_id' => 'required|exists:acct.customers,id',
        'invoice_date' => 'required|date',
        'due_date' => 'required|date|after_or_equal:invoice_date',
        'currency_code' => 'required|exists:public.currencies,code',
        'line_items' => 'required|array|min:1',
        'line_items.*.description' => 'required|string',
        'line_items.*.quantity' => 'required|numeric|min:0.0001',
        'line_items.*.unit_price' => 'required|numeric|min:0',
        'line_items.*.revenue_account_id' => 'required|exists:acct.accounts,id',
        'line_items.*.tax_code_id' => 'nullable|exists:tax.tax_codes,id',
        // ... rest of full rules
    ];
}

private function isMinimalPayload(): bool
{
    return $this->has('amount') && !$this->has('line_items');
}
```

---

## 6. Implementation Checklist

### Phase 1: Shared Components
- [ ] EntitySearch component
- [ ] AmountInput component
- [ ] DueDatePicker component
- [ ] TaxToggle component
- [ ] QuickAddModal component

### Phase 2: Quick Invoice (Owner)
- [ ] QuickInvoiceCreate page
- [ ] Backend: expandMinimalInvoice logic
- [ ] Backend: quick-store customer endpoint
- [ ] API: minimal payload validation
- [ ] Test: 3-click flow

### Phase 3: Quick Bill (Owner)
- [ ] QuickBillCreate page
- [ ] Category selector for expenses
- [ ] Backend: expandMinimalBill logic
- [ ] Backend: quick-store vendor endpoint
- [ ] "Save & Pay Now" flow

### Phase 4: Full Forms (Accountant)
- [ ] Update InvoiceCreate with mode check
- [ ] Update BillCreate with mode check
- [ ] LineItemsGrid component
- [ ] Journal preview component

### Phase 5: Polish
- [ ] Auto-save drafts (30s interval)
- [ ] Keyboard shortcuts
- [ ] Form state persistence (on navigation)
- [ ] Error recovery

---

## 7. Testing Scenarios

### Quick Invoice
1. Create with customer + amount + description → Draft saved
2. Create and send → Approved + sent
3. Quick-add customer inline → Customer created, selected
4. Tax checkbox → Applies customer default tax
5. Validation: missing customer → Error shown
6. Validation: zero amount → Error shown

### Quick Bill
1. Create with vendor + amount + description + category → Draft saved
2. "Save & Pay Now" → Bill approved + payment created
3. Category required → Cannot save without category
4. Tax inclusive → Amount includes tax

### Mode Switching
1. Owner creates draft → Switch to accountant → See full form
2. Accountant creates with line items → Switch to owner → See totals only

---

**End of Component Specifications**
