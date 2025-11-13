<?php

use App\Http\Requests\BaseFormRequest;
use App\Services\ServiceContext;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates service context from request', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    
    $user->companies()->attach($company, ['is_active' => true]);
    
    $this->actingAs($user);
    
    $request = new class($user) extends BaseFormRequest {
        public function rules(): array
        {
            return [];
        }
        
        public function authorize(): bool
        {
            return true;
        }
    };
    
    $request->merge(['company_id' => $company->id]);
    
    $context = $request->getServiceContext();
    
    expect($context)->toBeInstanceOf(ServiceContext::class);
    expect($context->getUserId())->toBe($user->id);
    expect($context->getCompanyId())->toBe($company->id);
});

it('validates company access correctly', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    
    $user->companies()->attach($company, ['is_active' => true]);
    
    $this->actingAs($user);
    
    $request = new class($user) extends BaseFormRequest {
        public function rules(): array
        {
            return [];
        }
        
        public function authorize(): bool
        {
            return true;
        }
    };
    
    // Should have access to own company
    expect($request->validateUserCompanyAccess($company->id))->toBeTrue();
    
    // Should not have access to other company
    expect($request->validateUserCompanyAccess($otherCompany->id))->toBeFalse();
});

it('provides common validation rules', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $request = new class($user) extends BaseFormRequest {
        public function rules(): array
        {
            return $this->getCommonUuidRules();
        }
        
        public function authorize(): bool
        {
            return true;
        }
    };
    
    $rules = $request->rules();
    
    expect($rules)->toHaveKey('id');
    expect($rules)->toHaveKey('company_id');
    expect($rules['id'])->toContain('required', 'uuid');
    expect($rules['company_id'])->toContain('required', 'uuid');
});

it('provides financial validation rules', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $request = new class($user) extends BaseFormRequest {
        public function rules(): array
        {
            return $this->getFinancialValidationRules();
        }
        
        public function authorize(): bool
        {
            return true;
        }
    };
    
    $rules = $request->rules();
    
    expect($rules)->toHaveKey('amount');
    expect($rules)->toHaveKey('currency');
    expect($rules)->toHaveKey('date');
    expect($rules['amount'])->toContain('required', 'numeric', 'min:0.01');
    expect($rules['currency'])->toContain('required', 'string', 'size:3');
    expect($rules['date'])->toContain('required', 'date');
});

it('validates RLS context for financial operations', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    
    $user->companies()->attach($company, ['is_active' => true]);
    $user->givePermissionTo('rls.context');
    
    $this->actingAs($user);
    
    $request = new class($user) extends BaseFormRequest {
        public function rules(): array
        {
            return [];
        }
        
        public function authorize(): bool
        {
            return true;
        }
    };
    
    // Mock company context
    $request->setCompanyContext($company->id);
    
    expect($request->validateRlsContext())->toBeTrue();
});

it('sanitizes sensitive data for logging', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $request = new class($user) extends BaseFormRequest {
        public function rules(): array
        {
            return [];
        }
        
        public function authorize(): bool
        {
            return true;
        }
    };
    
    $data = [
        'name' => 'Test User',
        'password' => 'secret123',
        'api_key' => 'sk_test_123',
        'credit_card' => '4111111111111111',
        '_token' => 'abc123',
        'description' => 'Normal field',
    ];
    
    $sanitized = $request->sanitizeForLogging($data);
    
    expect($sanitized['name'])->toBe('Test User');
    expect($sanitized['password'])->toBe('[REDACTED]');
    expect($sanitized['api_key'])->toBe('[REDACTED]');
    expect($sanitized['credit_card'])->toBe('[REDACTED]');
    expect($sanitized)->not->toHaveKey('_token');
    expect($sanitized['description'])->toBe('Normal field');
});

it('provides pagination rules', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $request = new class($user) extends BaseFormRequest {
        public function rules(): array
        {
            return $this->getPaginationRules();
        }
        
        public function authorize(): bool
        {
            return true;
        }
    };
    
    $rules = $request->rules();
    
    expect($rules)->toHaveKey('page');
    expect($rules)->toHaveKey('per_page');
    expect($rules)->toHaveKey('sort');
    expect($rules)->toHaveKey('order');
    expect($rules['per_page'])->toContain('between:1,100');
    expect($rules['order'])->toContain('in:asc,desc');
});
