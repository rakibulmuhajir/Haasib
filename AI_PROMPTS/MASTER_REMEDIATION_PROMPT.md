# Master Code Remediation Prompt Template

## AI Developer Instructions

You are a **Constitutional Compliance Expert** for the Haasib accounting system. Your task is to systematically remediate non-compliant code to meet all architectural requirements while fixing any issues found.

## CRITICAL: Before ANY Code Changes

### 1. **Analyze Current State**
```bash
# First, understand what you're working with
- Identify ALL constitutional violations
- Find security vulnerabilities
- Detect performance issues
- Check for missing RLS policies
- Validate UUID usage
- Check command bus compliance
```

### 2. **Establish Working Baseline**
```bash
# Run tests to understand current state
cd stack && composer quality-check
php artisan test tests/Feature/CriticalPathTest.php
```

## CONSTITUTIONAL REQUIREMENTS (NON-NEGOTIABLE)

### **Database & Architecture Requirements**
- **Multi-Schema**: Tables MUST be in correct schema (auth/acct/ledger/ops)
- **UUID Primary Keys**: NO integer primary keys, use `$table->uuid('id')->primary()`
- **Company-Based Tenancy**: ALL tenant tables need `company_id uuid`
- **RLS Policies**: ALL tenant tables need Row Level Security
- **Foreign Keys**: Proper schema-aware foreign key constraints
- **Audit Triggers**: Financial tables need audit logging

### **Controller Requirements**
- **Thin Controllers**: ONLY coordination, NO business logic
- **FormRequest Validation**: ALL inputs validated via FormRequest classes
- **Command Bus**: ALL write operations via `Bus::dispatch('action.name', $data, $context)`
- **ServiceContext**: User/company context via `ServiceContextHelper::fromRequest($request)`
- **JSON Responses**: Standard format `{success, data, message}`
- **Error Handling**: Proper try-catch with user feedback

### **Model Requirements**
- **Extend BaseModel**: Use established base class
- **Traits**: `HasUuids`, `BelongsToCompany`, `SoftDeletes`, `AuditLog`
- **UUID Configuration**: `$keyType = 'string'` and `$incrementing = false`
- **Relationships**: Typed relationships with proper return types
- **Business Logic**: Separate methods with clear boolean returns
- **Fillable Arrays**: Explicit allowed fields only

### **Service/Command Requirements**
- **Command Bus Actions**: All writes through registered bus actions
- **ServiceContext**: Explicit context injection (no auth() calls in services)
- **Transaction Boundaries**: Proper DB transaction management
- **DTO Contracts**: Explicit data transfer objects
- **No Globals**: No request(), session(), auth() in business logic

### **Frontend Requirements**
- **PrimeVue Only**: Use PrimeVue v4 components exclusively
- **Composition API**: Use `<script setup>` with proper TypeScript
- **Component Structure**: Proper props, emits, and reactive state
- **Error Handling**: Toast notifications for user feedback
- **Responsive Design**: Mobile-first with Tailwind utilities

## SYSTEMATIC REMEDIATION APPROACH

### **Phase 1: Database & Schema Remediation**

#### **Migration Remediation Pattern**
```php
// BEFORE (Non-compliant)
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});

// AFTER (Constitutional Compliant)
return new class extends Migration
{
    public function up(): void
    {
        // Create schema if needed
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('customer_number', 50);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('tax_id')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys to correct schemas
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');

            // Unique constraints
            $table->unique(['company_id', 'customer_number']);
            $table->unique(['company_id', 'email']);

            // Indexes for performance
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
        });

        // Enable RLS (MANDATORY)
        DB::statement('ALTER TABLE acct.customers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.customers FORCE ROW LEVEL SECURITY');

        // Create RLS policy
        DB::statement("
            CREATE POLICY customers_company_policy ON acct.customers
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Audit trigger for financial data
        DB::statement('
            CREATE TRIGGER customers_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.customers
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP TRIGGER IF EXISTS customers_audit_trigger ON acct.customers');
        DB::statement('DROP POLICY IF EXISTS customers_company_policy ON acct.customers');
        Schema::dropIfExists('acct.customers');
    }
};
```

#### **Model Remediation Pattern**
```php
// BEFORE (Non-compliant)
class Customer extends Model
{
    protected $fillable = ['name', 'email'];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}

// AFTER (Constitutional Compliant)
class Customer extends BaseModel
{
    use HasUuids, BelongsToCompany, SoftDeletes, AuditLog;

    protected $table = 'acct.customers';

    protected $fillable = [
        'company_id',
        'customer_number',
        'name',
        'email',
        'tax_id',
        'status',
        'credit_limit',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'status' => CustomerStatus::class,
        'deleted_at' => 'datetime',
    ];

    // Relationships with proper typing
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    // Business logic methods with clear return types
    public function isActive(): bool
    {
        return $this->status === CustomerStatus::ACTIVE;
    }

    public function canPlaceInvoice(float $amount): bool
    {
        return ($this->getOutstandingBalance() + $amount) <= $this->credit_limit;
    }

    public function getOutstandingBalance(): float
    {
        return $this->invoices()
            ->where('status', '!=', 'paid')
            ->sum('balance_due');
    }
}
```

### **Phase 2: Controller Remediation**

#### **Controller Remediation Pattern**
```php
// BEFORE (Non-compliant)
class CustomerController extends Controller
{
    public function store(Request $request)
    {
        $customer = new Customer();
        $customer->name = $request->name;
        $customer->email = $request->email;
        $customer->save();

        return response()->json($customer);
    }
}

// AFTER (Constitutional Compliant)
class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {}

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $customer = Bus::dispatch('customers.create', [
                'company_id' => $context->getCompanyId(),
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'tax_id' => $request->validated('tax_id'),
                'credit_limit' => $request->validated('credit_limit', 0),
            ], $context);

            return response()->json([
                'success' => true,
                'data' => new CustomerResource($customer),
                'message' => 'Customer created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (InsufficientCreditException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Credit limit exceeded',
                'errors' => ['credit_limit' => [$e->getMessage()]]
            ], 422);

        } catch (\Exception $e) {
            Log::error('Customer creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer'
            ], 500);
        }
    }
}
```

#### **FormRequest Creation Pattern**
```php
// Create: app/Http/Requests/StoreCustomerRequest.php
class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                Rule::unique('acct.customers', 'email')->where(function ($query) {
                    $query->where('company_id', $this->user()->currentCompany()->id);
                })
            ],
            'tax_id' => 'nullable|string|max:50',
            'credit_limit' => 'nullable|numeric|min:0|max:99999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A customer with this email already exists',
            'credit_limit.max' => 'Credit limit cannot exceed 99,999,999.99',
        ];
    }
}
```

### **Phase 3: Service Layer Remediation**

#### **Command Bus Action Creation**
```php
// Create: app/Domain/Customers/Actions/CustomerCreate.php
class CustomerCreate extends CommandAction
{
    public function __construct(
        private CustomerService $customerService,
        private CustomerNumberGenerator $numberGenerator
    ) {}

    public function handle(array $data, ServiceContext $context): Customer
    {
        return DB::transaction(function () use ($data, $context) {
            // Generate customer number
            $data['customer_number'] = $this->numberGenerator->generate($context->getCompanyId());

            // Create customer
            $customer = $this->customerService->create($data, $context);

            // Log action
            $this->auditLog('customer_created', $customer, $context);

            return $customer;
        });
    }

    private function auditLog(string $action, Customer $customer, ServiceContext $context): void
    {
        audit_log([
            'action' => $action,
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'user_id' => $context->getUserId(),
            'company_id' => $context->getCompanyId(),
            'old_values' => null,
            'new_values' => $customer->toArray(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

#### **Service Implementation**
```php
// Create: app/Domain/Customers/Services/CustomerService.php
class CustomerService
{
    public function create(array $data, ServiceContext $context): Customer
    {
        // Validate credit limit
        if (isset($data['credit_limit']) && $data['credit_limit'] > 1000000) {
            throw new InsufficientCreditException('Credit limit exceeds maximum allowed amount');
        }

        // Create customer
        $customer = Customer::create([
            'company_id' => $context->getCompanyId(),
            'customer_number' => $data['customer_number'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'status' => CustomerStatus::ACTIVE,
            'credit_limit' => $data['credit_limit'] ?? 0,
        ]);

        return $customer;
    }

    public function update(string $id, array $data, ServiceContext $context): Customer
    {
        $customer = $this->findById($id, $context);

        // Track changes for audit
        $oldValues = $customer->getOriginal();

        $customer->update($data);

        // Log significant changes
        if (isset($data['credit_limit']) && $data['credit_limit'] != $oldValues['credit_limit']) {
            $this->logCreditLimitChange($customer, $oldValues['credit_limit'], $data['credit_limit'], $context);
        }

        return $customer;
    }

    private function findById(string $id, ServiceContext $context): Customer
    {
        return Customer::where('company_id', $context->getCompanyId())
                      ->where('id', $id)
                      ->firstOrFail();
    }
}
```

### **Phase 4: Frontend Remediation**

#### **Vue Component Remediation**
```vue
<!-- BEFORE (Non-compliant) -->
<template>
  <div>
    <input v-model="form.name" placeholder="Customer Name">
    <input v-model="form.email" placeholder="Email">
    <button @click="save">Save</button>
  </div>
</template>

<script>
export default {
  data() {
    return {
      form: {
        name: '',
        email: ''
      }
    }
  },
  methods: {
    async save() {
      const response = await fetch('/api/customers', {
        method: 'POST',
        body: JSON.stringify(this.form)
      });
    }
  }
}
</script>
```

```vue
<!-- AFTER (Constitutional Compliant) -->
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'

// Props and emits
const props = defineProps({
    customer: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['saved', 'error'])

// Composables
const toast = useToast()

// Form with Inertia
const form = useForm({
    name: '',
    email: '',
    tax_id: '',
    credit_limit: 0
})

// Computed properties
const canSave = computed(() => {
    return form.name.trim() !== '' && !form.processing
})

// Initialize form with customer data
onMounted(() => {
    if (props.customer) {
        form.defaults({
            name: props.customer.name,
            email: props.customer.email || '',
            tax_id: props.customer.tax_id || '',
            credit_limit: props.customer.credit_limit
        })
        form.reset()
    }
})

// Methods
const save = () => {
    if (!canSave.value) {
        showErrorToast('Please fill in required fields')
        return
    }

    const url = props.customer ? `/api/customers/${props.customer.id}` : '/api/customers'
    const method = props.customer ? 'PUT' : 'POST'

    form.transform(data => ({
        ...data,
        _method: method
    })).post(url, {
        onSuccess: (page) => {
            showSuccessToast(props.customer ? 'Customer updated successfully' : 'Customer created successfully')
            emit('saved', page.props.customer)
        },
        onError: (errors) => {
            showValidationErrors(errors)
            emit('error', errors)
        },
        preserveState: true
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

const showErrorToast = (message) => {
    toast.add({
        severity: 'error',
        summary: 'Error',
        detail: message,
        life: 3000
    })
}

const showValidationErrors = (errors) => {
    Object.entries(errors).forEach(([field, messages]) => {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: `${field}: ${Array.isArray(messages) ? messages[0] : messages}`,
            life: 5000
        })
    })
}
</script>

<template>
    <div class="customer-form p-4">
        <form @submit.prevent="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Customer Name -->
                <div class="field">
                    <label for="name" class="block text-600 text-sm font-medium mb-2">
                        Customer Name <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        id="name"
                        v-model="form.name"
                        placeholder="Enter customer name"
                        :class="{ 'p-invalid': form.errors.name }"
                        autofocus
                    />
                    <small v-if="form.errors.name" class="p-error">
                        {{ form.errors.name }}
                    </small>
                </div>

                <!-- Email -->
                <div class="field">
                    <label for="email" class="block text-600 text-sm font-medium mb-2">
                        Email Address
                    </label>
                    <InputText
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="customer@example.com"
                        :class="{ 'p-invalid': form.errors.email }"
                    />
                    <small v-if="form.errors.email" class="p-error">
                        {{ form.errors.email }}
                    </small>
                </div>

                <!-- Tax ID -->
                <div class="field">
                    <label for="tax_id" class="block text-600 text-sm font-medium mb-2">
                        Tax ID / VAT Number
                    </label>
                    <InputText
                        id="tax_id"
                        v-model="form.tax_id"
                        placeholder="Tax identification number"
                        :class="{ 'p-invalid': form.errors.tax_id }"
                    />
                    <small v-if="form.errors.tax_id" class="p-error">
                        {{ form.errors.tax_id }}
                    </small>
                </div>

                <!-- Credit Limit -->
                <div class="field">
                    <label for="credit_limit" class="block text-600 text-sm font-medium mb-2">
                        Credit Limit
                    </label>
                    <InputNumber
                        id="credit_limit"
                        v-model="form.credit_limit"
                        mode="currency"
                        currency="USD"
                        :min="0"
                        :maxFractionDigits="2"
                        placeholder="0.00"
                        :class="{ 'p-invalid': form.errors.credit_limit }"
                    />
                    <small v-if="form.errors.credit_limit" class "p-error">
                        {{ form.errors.credit_limit }}
                    </small>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2 mt-6">
                <Button
                    type="button"
                    label="Cancel"
                    severity="secondary"
                    @click="$emit('cancelled')"
                    :disabled="form.processing"
                />
                <Button
                    type="submit"
                    :label="customer ? 'Update Customer' : 'Create Customer'"
                    :loading="form.processing"
                    :disabled="!canSave"
                    icon="pi pi-save"
                />
            </div>
        </form>
    </div>
</template>

<style scoped>
.customer-form {
    max-width: 800px;
}

.field {
    margin-bottom: 1rem;
}

.field label {
    margin-bottom: 0.5rem;
    display: block;
}

.grid {
    display: grid;
    gap: 1rem;
}

.grid-cols-1 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 768px) {
    .md\:grid-cols-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
```

### **Phase 5: Command Bus Registration**

#### **Update Command Bus Configuration**
```php
// config/command-bus.php
return [
    // Customer Management Actions
    'customers.create' => App\Domain\Customers\Actions\CustomerCreate::class,
    'customers.update' => App\Domain\Customers\Actions\CustomerUpdate::class,
    'customers.delete' => App\Domain\Customers\Actions\CustomerDelete::class,
    'customers.status_change' => App\Domain\Customers\Actions\CustomerStatusChange::class,

    // Add other existing actions...
];
```

### **Phase 6: API Resource Creation**

#### **API Resource for Consistent Responses**
```php
// Create: app/Http/Resources/CustomerResource.php
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_number' => $this->customer_number,
            'name' => $this->name,
            'email' => $this->email,
            'tax_id' => $this->tax_id,
            'status' => $this->status,
            'credit_limit' => (float) $this->credit_limit,
            'outstanding_balance' => (float) $this->getOutstandingBalance(),
            'available_credit' => (float) ($this->credit_limit - $this->getOutstandingBalance()),
            'is_active' => $this->isActive(),
            'contacts_count' => $this->whenLoaded('contacts', fn() => $this->contacts->count()),
            'invoices_count' => $this->whenLoaded('invoices', fn() => $this->invoices->count()),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

## VALIDATION CHECKLIST

After completing remediation, validate:

### **Database Validation**
```bash
# Test migrations
php artisan migrate:fresh --seed

# Check RLS policies
php artisan tinker
> DB::select("SELECT * FROM pg_policies WHERE tablename LIKE '%customer%'");

# Verify schema compliance
php artisan schema:check-integrity
```

### **Backend Validation**
```bash
# Run all tests
composer quality-check

# Test critical paths
php artisan test tests/Feature/CriticalPathTest.php

# Check API endpoints
curl -X POST http://localhost/api/customers \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"name":"Test Customer","email":"test@example.com"}'
```

### **Frontend Validation**
```bash
# Build frontend
npm run build

# Test component rendering
npm run dev
# Navigate to customer form and test all interactions
```

### **Integration Validation**
```bash
# Test complete flow
1. Create customer via API
2. Verify RLS isolation
3. Check audit logs
4. Test frontend form
5. Validate error handling
```

## NEXT STEPS

1. **Run the comprehensive quality checks**
2. **Test all remediated functionality**
3. **Verify no regressions in existing features**
4. **Update documentation if needed**
5. **Deploy to staging for final validation**

---

**REMEMBER**: Every change must maintain constitutional compliance. If unsure, reference the working examples and follow them exactly.