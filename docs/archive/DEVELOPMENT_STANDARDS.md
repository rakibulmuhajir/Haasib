# Haasib Development Standards & Patterns

> **Consolidated from Dos & Don'ts, Frontend Architecture, Development Guide, and Team Memory**
>
> **Last Updated**: 2025-11-13 | **Enforced**: Pre-commit hooks + CI checks

---

## Backend Development Standards

### PHP/Laravel Standards

#### Code Style & Formatting
```php
// PSR-12 via Laravel Pint - no exceptions
composer pint --test  # Must pass before commit

// Type declarations everywhere
public function createInvoice(CreateInvoiceRequest $request): JsonResponse
{
    // Implementation
}

// Return type hints on all methods
private function calculateTotal(array $items): float
{
    return array_sum(array_column($items, 'amount'));
}
```

#### Controller Patterns
```php
class InvoiceController extends Controller
{
    // ✅ CORRECT: Thin coordinator with command bus
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $context = ServiceContextHelper::fromRequest($request);

        try {
            $invoice = Bus::dispatch('invoices.create', $request->validated(), $context);

            return response()->json([
                'success' => true,
                'data' => new InvoiceResource($invoice),
                'message' => 'Invoice created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    // ❌ FORBIDDEN: Direct service calls, business logic in controllers
    public function storeBad(StoreInvoiceRequest $request)
    {
        $invoice = new Invoice();
        $invoice->customer_id = $request->customer_id;
        // ... business logic doesn't belong here
    }
}
```

#### Form Request Validation (MANDATORY)
```php
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'uuid',
                Rule::exists('acct.customers', 'id')->where(function ($query) {
                    $query->where('company_id', $this->user()->currentCompany()->id);
                })
            ],
            'issue_date' => 'required|date|before_or_equal:due_date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.min' => 'At least one item is required',
            'customer_id.exists' => 'Selected customer is invalid',
        ];
    }
}
```

#### Model Standards
```php
class Invoice extends BaseModel
{
    use BelongsToCompany, HasUuids, SoftDeletes;

    protected $table = 'acct.invoices';

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'status',
        'total_amount',
        'tax_amount',
        'balance_due',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
    ];

    // Always declare relationships with return types
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, InvoiceItem::class);
    }

    // Business logic methods with clear names and return types
    public function isPaid(): bool
    {
        return $this->balance_due <= 0;
    }

    public function isOverdue(): bool
    {
        return !$this->isPaid() && $this->due_date->isPast();
    }

    public function canBePosted(): bool
    {
        return $this->status === 'draft' && $this->items->isNotEmpty();
    }
}
```

#### Service Layer (Command Bus Actions)
```php
class InvoiceCreate extends CommandAction
{
    public function __construct(
        private InvoiceService $invoiceService,
        private LedgerService $ledgerService,
        private AuditService $auditService
    ) {}

    public function handle(array $data, ServiceContext $context): Invoice
    {
        return DB::transaction(function () use ($data, $context) {
            // Create invoice
            $invoice = $this->invoiceService->create($data, $context);

            // Post to ledger if needed
            if ($data['post_immediately'] ?? false) {
                $this->ledgerService->postInvoice($invoice, $context);
            }

            // Audit the operation
            $this->auditService->log('invoice_created', $invoice, $context);

            return $invoice;
        });
    }
}
```

### Database Standards

#### Migration Patterns
```php
return new class extends Migration
{
    public function up(): void
    {
        // Create schema first
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('invoice_number', 50);
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status', 20)->default('draft');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2)->storedAs('total_amount - paid_amount');
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys with proper constraints
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');

            $table->foreign('customer_id')
                  ->references('id')
                  ->on('acct.customers')
                  ->onDelete('restrict');

            // Unique constraints
            $table->unique(['company_id', 'invoice_number']);

            // Indexes for performance
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'due_date']);
        });

        // Enable RLS
        DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');

        // RLS Policies
        DB::statement("
            CREATE POLICY invoices_company_policy ON acct.invoices
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Force RLS (owner can't bypass)
        DB::statement('ALTER TABLE acct.invoices FORCE ROW LEVEL SECURITY');

        // Audit trigger
        DB::statement('
            CREATE TRIGGER invoices_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.invoices
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP TRIGGER IF EXISTS invoices_audit_trigger ON acct.invoices');
        DB::statement('DROP POLICY IF EXISTS invoices_company_policy ON acct.invoices');
        Schema::dropIfExists('acct.invoices');
    }
};
```

#### Critical Database Rules
- **NEVER use `Schema::hasSchema()`** - doesn't exist in Laravel
- **Enable extensions before using them** - `CREATE EXTENSION IF NOT EXISTS pgcrypto`
- **Check for existence before creating** - prevents migration failures
- **Tear down in reverse order** - drop triggers, policies, then tables
- **Always test rollback** - `php artisan migrate:rollback` must work

---

## Frontend Development Standards

### Vue.js Component Structure

#### Component Template (Composition API)
```vue
<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'

// Props and emits with proper typing
const props = defineProps({
    invoice: {
        type: Object,
        required: true
    },
    editable: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['saved', 'cancelled', 'error'])

// Composables
const { t } = useI18n()
const page = usePage()
const toast = useToast()

// Reactive state (always use ref() for primitives)
const loading = ref(false)
const form = ref({
    customer_id: null,
    issue_date: null,
    due_date: null,
    items: []
})

// Computed properties (always use computed() for derived state)
const hasItems = computed(() => {
    return form.value.items.some(item => item.description && item.unit_price > 0)
})

const totalAmount = computed(() => {
    return form.value.items.reduce((sum, item) => {
        return sum + (item.quantity * item.unit_price)
    }, 0)
})

const canSave = computed(() => {
    return hasItems.value && !loading.value && props.editable
})

// Methods (always async/await for async operations)
const save = async () => {
    if (!canSave.value) {
        showErrorToast('Cannot save - missing required data')
        return
    }

    loading.value = true

    try {
        const response = await router.post('/api/invoices', form.value)

        emit('saved', response.data)
        showSuccessToast('Invoice saved successfully')

    } catch (error) {
        emit('error', error)
        showErrorToast('Failed to save invoice')
    } finally {
        loading.value = false
    }
}

const showErrorToast = (message) => {
    toast.add({
        severity: 'error',
        summary: 'Error',
        detail: message,
        life: 3000
    })
}

const showSuccessToast = (message) => {
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: message,
        life: 3000
    })
}

// Lifecycle
onMounted(() => {
    // Initialize form with props data
    if (props.invoice) {
        form.value = { ...props.invoice }
    }
})
</script>

<template>
    <div class="invoice-form p-4">
        <form @submit.prevent="save">
            <!-- PrimeVue components only -->
            <Dropdown
                v-model="form.customer_id"
                :options="customers"
                optionLabel="name"
                optionValue="id"
                placeholder="Select Customer"
                :class="{ 'p-invalid': !form.customer_id }"
            />

            <Calendar
                v-model="form.issue_date"
                placeholder="Issue Date"
                dateFormat="yy-mm-dd"
                :class="{ 'p-invalid': !form.issue_date }"
            />

            <Button
                type="submit"
                label="Save Invoice"
                :loading="loading"
                :disabled="!canSave"
                icon="pi pi-save"
            />
        </form>
    </div>
</template>

<style scoped>
.invoice-form {
    /* Component-specific styles only */
    /* Use PrimeVue classes for consistent styling */
}
</style>
```

#### PrimeVue Usage Standards
```vue
<!-- ✅ CORRECT: Use PrimeVue components -->
<DataTable
    :value="invoices"
    :paginator="true"
    :rows="10"
    :loading="loading"
    responsiveLayout="scroll"
    :globalFilterFields="['invoice_number', 'customer.name']"
    v-model:filters="filters"
    filterDisplay="row"
>
    <Column field="invoice_number" header="Invoice #" />
    <Column field="customer.name" header="Customer" />
    <Column field="total_amount" header="Amount">
        <template #body="{ data }">
            {{ formatCurrency(data.total_amount) }}
        </template>
    </Column>
</DataTable>

<!-- ❌ FORBIDDEN: Custom component implementations -->
<table class="custom-table">
    <!-- Don't recreate table functionality -->
</table>
```

### State Management Patterns

#### Composables for Shared Logic
```javascript
// composables/useInvoices.js
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'

export function useInvoices() {
    const loading = ref(false)
    const invoices = ref([])
    const toast = useToast()

    const loadInvoices = async (filters = {}) => {
        loading.value = true

        try {
            const response = await router.get('/api/invoices', filters, {
                preserveState: true,
                preserveScroll: true
            })

            invoices.value = response.props.invoices.data || []
        } catch (error) {
            console.error('Failed to load invoices:', error)
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load invoices',
                life: 3000
            })
        } finally {
            loading.value = false
        }
    }

    const createInvoice = async (invoiceData) => {
        loading.value = true

        try {
            await router.post('/api/invoices', invoiceData)
            await loadInvoices() // Refresh list
            return true
        } catch (error) {
            console.error('Failed to create invoice:', error)
            return false
        } finally {
            loading.value = false
        }
    }

    return {
        loading,
        invoices,
        loadInvoices,
        createInvoice
    }
}
```

### Component Standards

#### Reusable Component Pattern
```vue
<!-- Components/UI/InlineEditable.vue -->
<script setup>
import { ref, computed, watch } from 'vue'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    modelValue: [String, Number],
    type: {
        type: String,
        default: 'text'
    },
    editable: {
        type: Boolean,
        default: true
    },
    placeholder: String
})

const emit = defineEmits(['update:modelValue', 'saved'])

const toast = useToast()
const isEditing = ref(false)
const localValue = ref(props.modelValue)
const saving = ref(false)

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
    localValue.value = newValue
})

const startEditing = () => {
    if (!props.editable) return
    isEditing.value = true
}

const cancelEditing = () => {
    localValue.value = props.modelValue
    isEditing.value = false
}

const save = async () => {
    if (localValue.value === props.modelValue) {
        isEditing.value = false
        return
    }

    saving.value = true
    try {
        emit('saved', localValue.value)
        emit('update:modelValue', localValue.value)
        isEditing.value = false

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Changes saved',
            life: 2000
        })
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save changes',
            life: 3000
        })
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="inline-editable">
        <span v-if="!isEditing" @click="startEditing" class="cursor-pointer">
            {{ modelValue || placeholder }}
            <i v-if="editable" class="pi pi-pencil ml-2 text-gray-400"></i>
        </span>

        <div v-else class="flex gap-2">
            <InputText
                v-model="localValue"
                :type="type"
                :placeholder="placeholder"
                @keyup.enter="save"
                @keyup.escape="cancelEditing"
                class="flex-1"
                autofocus
            />
            <Button
                icon="pi pi-check"
                @click="save"
                :loading="saving"
                size="small"
            />
            <Button
                icon="pi pi-times"
                @click="cancelEditing"
                severity="secondary"
                size="small"
            />
        </div>
    </div>
</template>
```

### Form Handling Standards

#### Inertia.js Form Patterns
```vue
<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
    customer_id: null,
    issue_date: null,
    due_date: null,
    items: []
})

const submit = () => {
    form.post('/invoices', {
        onSuccess: () => {
            // Handle success
        },
        onError: (errors) => {
            // Handle validation errors
        },
        preserveState: true
    })
}
</script>

<template>
    <form @submit.prevent="submit">
        <Dropdown
            v-model="form.customer_id"
            :options="customers"
            :class="{ 'p-invalid': form.errors.customer_id }"
        />
        <small v-if="form.errors.customer_id" class="p-error">
            {{ form.errors.customer_id }}
        </small>

        <Button
            type="submit"
            :loading="form.processing"
            label="Save"
        />
    </form>
</template>
```

---

## Integration Patterns

### API Integration
```javascript
// services/InvoiceService.js
import axios from 'axios'

class InvoiceService {
    async createInvoice(data) {
        try {
            const response = await axios.post('/api/invoices', data, {
                headers: {
                    'Idempotency-Key': this.generateUUID(),
                    'Content-Type': 'application/json'
                }
            })

            return response.data
        } catch (error) {
            if (error.response?.status === 422) {
                throw new ValidationException(error.response.data.errors)
            }
            throw error
        }
    }

    async getInvoices(filters = {}) {
        const response = await axios.get('/api/invoices', {
            params: filters
        })

        return response.data
    }

    generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0
            const v = c === 'x' ? r : (r & 0x3 | 0x8)
            return v.toString(16)
        })
    }
}
```

### Error Handling Standards
```vue
<script setup>
const handleError = (error) => {
    const toast = useToast()

    if (error.response?.status === 422) {
        // Validation errors
        Object.entries(error.response.data.errors).forEach(([field, messages]) => {
            toast.add({
                severity: 'warn',
                summary: 'Validation Error',
                detail: `${field}: ${messages.join(', ')}`,
                life: 5000
            })
        })
    } else if (error.response?.status === 403) {
        // Permission errors
        toast.add({
            severity: 'error',
            summary: 'Access Denied',
            detail: 'You do not have permission to perform this action',
            life: 5000
        })
    } else {
        // Generic errors
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'An unexpected error occurred',
            life: 3000
        })
    }
}
</script>
```

---

## Testing Standards

### Backend Testing (Pest)
```php
// tests/Feature/InvoiceManagementTest.php
test('user can create invoice for their company', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $user->companies()->attach($company, ['role' => 'admin']);

    $this->actingAs($user);

    $invoiceData = [
        'customer_id' => $customer->id,
        'issue_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(30)->format('Y-m-d'),
        'items' => [
            [
                'description' => 'Test Service',
                'quantity' => 1,
                'unit_price' => 100.00
            ]
        ]
    ];

    $response = $this->postJson('/api/invoices', $invoiceData);

    $response->assertStatus(201)
             ->assertJson(['success' => true])
             ->assertJsonStructure([
                 'data' => [
                     'id',
                     'invoice_number',
                     'total_amount'
                 ]
             ]);

    $this->assertDatabaseHas('acct.invoices', [
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'total_amount' => 100.00
    ]);
});

test('user cannot access invoice from another company', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $invoice = Invoice::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($user)
         ->getJson("/api/invoices/{$invoice->id}")
         ->assertStatus(404); // 404 due to RLS, not 403
});
```

### Frontend Testing (Vitest)
```javascript
// tests/components/InvoiceForm.spec.js
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import InvoiceForm from '@/components/InvoiceForm.vue'

describe('InvoiceForm', () => {
    it('emits save event with valid data', async () => {
        const wrapper = mount(InvoiceForm, {
            props: {
                customers: [
                    { id: '1', name: 'Test Customer' }
                ]
            }
        })

        await wrapper.setData({
            form: {
                customer_id: '1',
                issue_date: '2025-01-01',
                due_date: '2025-01-15',
                items: [{
                    description: 'Test Item',
                    quantity: 1,
                    unit_price: 100
                }]
            }
        })

        await wrapper.find('form').trigger('submit')

        expect(wrapper.emitted('save')).toBeTruthy()
        expect(wrapper.emitted('save')[0][0]).toMatchObject({
            customer_id: '1',
            total_amount: 100
        })
    })

    it('shows validation errors for missing required fields', async () => {
        const wrapper = mount(InvoiceForm)

        await wrapper.find('form').trigger('submit')

        expect(wrapper.find('.p-error').exists()).toBe(true)
        expect(wrapper.text()).toContain('Customer is required')
    })
})
```

---

## Performance Standards

### Database Query Optimization
```php
// ✅ CORRECT: Efficient queries with proper indexes
$invoices = Invoice::with(['customer', 'items'])
    ->where('company_id', $context->getCompanyId())
    ->where('status', 'sent')
    ->whereBetween('issue_date', [$startDate, $endDate])
    ->orderBy('issue_date', 'desc')
    ->paginate(20);

// ❌ FORBIDDEN: N+1 queries
$invoices = Invoice::where('company_id', $context->getCompanyId())->get();
foreach ($invoices as $invoice) {
    echo $invoice->customer->name; // N+1 query!
    foreach ($invoice->items as $item) {
        echo $item->description; // Another N+1!
    }
}
```

### Frontend Performance
```vue
<script setup>
// ✅ CORRECT: Lazy load heavy components
const InvoiceChart = defineAsyncComponent(() =>
    import('@/components/InvoiceChart.vue')
)

// ✅ CORRECT: Computed properties for expensive calculations
const expensiveData = computed(() => {
    return largeDataset.value.map(item => ({
        ...item,
        calculated: complexCalculation(item)
    }))
})
</script>

<template>
    <!-- Virtual scrolling for large lists -->
    <DataTable
        :value="invoices"
        :virtualScrollerOptions="{ itemSize: 50 }"
        scrollHeight="400px"
    />
</template>
```

---

## Security Standards

### Input Validation
```php
// ✅ CORRECT: Validate everything
class PaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'payment_method' => 'required|in:cash,bank,transfer,card',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}

// ❌ FORBIDDEN: Never trust user input
public function unsafeMethod(Request $request)
{
    $amount = $request->input('amount'); // Unsafe!
    DB::insert("INSERT INTO payments (amount) VALUES ({$amount})"); // SQL injection!
}
```

### XSS Prevention
```vue
<template>
    <!-- ✅ CORRECT: Auto-escaped -->
    <p>{{ userContent }}</p>

    <!-- ❌ FORBIDDEN: Raw HTML without sanitization -->
    <div v-html="userContent"></div>

    <!-- ✅ CORRECT: Sanitized HTML when needed -->
    <div v-html="sanitizedHtml"></div>
</template>

<script setup>
import DOMPurify from 'dompurify'

const sanitizedHtml = computed(() => {
    return DOMPurify.sanitize(userContent.value)
})
</script>
```

---

## Documentation Standards

### Code Documentation
```php
/**
 * Creates a new invoice and posts it to the ledger
 *
 * @param array $data Invoice data including customer_id, items, dates
 * @param ServiceContext $context User and company context
 * @return Invoice Created invoice with relationships loaded
 *
 * @throws ValidationException When invoice data is invalid
 * @throws InsufficientCreditException When customer exceeds credit limit
 * @throws LedgerException When posting to ledger fails
 */
public function createInvoice(array $data, ServiceContext $context): Invoice
{
    // Implementation
}
```

### Component Documentation
```vue
<script setup>
/**
 * InvoiceForm - Form for creating and editing invoices
 *
 * @props {Object} invoice - Initial invoice data (optional)
 * @props {Boolean} editable - Whether form is editable (default: true)
 *
 * @emits {Object} saved - Emitted when invoice is successfully saved
 * @emits {Object} error - Emitted when save fails
 * @emits {void} cancelled - Emitted when user cancels editing
 *
 * @example
 * <InvoiceForm
 *   :invoice="existingInvoice"
 *   :editable="canEdit"
 *   @saved="handleSave"
 *   @error="handleError"
 * />
 */
</script>
```

---

**This document consolidates all development standards from your existing documentation. Follow these patterns exactly to maintain consistency across your team.**