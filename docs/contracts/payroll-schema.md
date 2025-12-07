# Schema Contract — Payroll & HR (pay)

Single source of truth for employees, payroll processing, payslips, benefits, and leave management. Read this before touching migrations, models, or services.

## Guardrails
- Schema: `pay` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes via `deleted_at` on employees, earning/deduction types.
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Money precision: `numeric(15,2)` for amounts; `numeric(7,4)` for rates/percentages.
- Payroll calculations done in application layer; DB stores results.
- Employee PII (personally identifiable information) requires encryption consideration.

## Tables

### pay.employees
- Purpose: employee master record.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE) (if employee has system access).
  - `employee_number` varchar(50) not null.
  - `first_name` varchar(100) not null.
  - `last_name` varchar(100) not null.
  - `email` varchar(255) nullable.
  - `phone` varchar(50) nullable.
  - `date_of_birth` date nullable.
  - `gender` varchar(20) nullable. Enum: male, female, other, prefer_not_to_say.
  - `national_id` varchar(100) nullable (encrypted at rest).
  - `tax_id` varchar(100) nullable.
  - `address` jsonb nullable (keys: street, city, state, zip, country).
  - `hire_date` date not null.
  - `termination_date` date nullable.
  - `termination_reason` varchar(255) nullable.
  - `employment_type` varchar(30) not null default 'full_time'. Enum: full_time, part_time, contract, intern.
  - `employment_status` varchar(30) not null default 'active'. Enum: active, on_leave, suspended, terminated.
  - `department` varchar(100) nullable.
  - `position` varchar(100) nullable.
  - `manager_id` uuid nullable FK → `pay.employees.id` (SET NULL/CASCADE).
  - `pay_frequency` varchar(20) not null default 'monthly'. Enum: weekly, biweekly, semimonthly, monthly.
  - `base_salary` numeric(15,2) not null default 0.
  - `hourly_rate` numeric(10,4) nullable.
  - `currency` char(3) not null FK → `public.currencies.code`.
  - `bank_account_name` varchar(255) nullable.
  - `bank_account_number` varchar(100) nullable (encrypted at rest).
  - `bank_name` varchar(255) nullable.
  - `bank_routing_number` varchar(50) nullable.
  - `notes` text nullable.
  - `is_active` boolean not null default true.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `employee_number`) where deleted_at is null.
  - Index: `company_id`; (`company_id`, `employment_status`); `manager_id`; `department`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'pay.employees'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','user_id','employee_number','first_name','last_name','email','phone','date_of_birth','gender','national_id','tax_id','address','hire_date','termination_date','termination_reason','employment_type','employment_status','department','position','manager_id','pay_frequency','base_salary','hourly_rate','currency','bank_account_name','bank_account_number','bank_name','bank_routing_number','notes','is_active','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','user_id'=>'string','date_of_birth'=>'date','address'=>'array','hire_date'=>'date','termination_date'=>'date','manager_id'=>'string','base_salary'=>'decimal:2','hourly_rate'=>'decimal:4','is_active'=>'boolean','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
  - Consider `$hidden = ['national_id','bank_account_number']` for API responses.
- Relationships: belongsTo Company; belongsTo User; belongsTo Manager (self); hasMany DirectReports (self); hasMany Payslip; hasMany EmployeeBenefit; hasMany LeaveRequest.
- Validation:
  - `employee_number`: required|string|max:50; unique per company (soft-delete aware).
  - `first_name`: required|string|max:100.
  - `last_name`: required|string|max:100.
  - `email`: nullable|email|max:255.
  - `hire_date`: required|date.
  - `employment_type`: required|in:full_time,part_time,contract,intern.
  - `employment_status`: required|in:active,on_leave,suspended,terminated.
  - `pay_frequency`: required|in:weekly,biweekly,semimonthly,monthly.
  - `base_salary`: numeric|min:0.
  - `currency`: required|string|size:3|uppercase.
- Business rules:
  - Cannot process payroll for terminated employees.
  - termination_date required when status = terminated.
  - manager_id cannot reference self.
  - Sensitive fields (national_id, bank_account_number) should be encrypted.

### pay.earning_types
- Purpose: salary components (base pay, overtime, bonus, etc.).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `code` varchar(50) not null.
  - `name` varchar(255) not null.
  - `description` text nullable.
  - `is_taxable` boolean not null default true.
  - `affects_overtime` boolean not null default false (include in OT base).
  - `is_recurring` boolean not null default true.
  - `gl_account_id` uuid nullable FK → `acct.accounts.id` (expense account).
  - `is_system` boolean not null default false.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `code`) where deleted_at is null.
  - Index: `company_id`; (`company_id`, `is_active`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'pay.earning_types'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','code','name','description','is_taxable','affects_overtime','is_recurring','gl_account_id','is_system','is_active'];`
  - `$casts = ['company_id'=>'string','is_taxable'=>'boolean','affects_overtime'=>'boolean','is_recurring'=>'boolean','gl_account_id'=>'string','is_system'=>'boolean','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Business rules:
  - System types (base_salary, overtime) cannot be deleted.
  - Seed with common types: BASE, OT, BONUS, COMMISSION, ALLOWANCE.

### pay.deduction_types
- Purpose: payroll deductions (tax, insurance, loans, etc.).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `code` varchar(50) not null.
  - `name` varchar(255) not null.
  - `description` text nullable.
  - `is_pre_tax` boolean not null default false (deduct before tax calculation).
  - `is_statutory` boolean not null default false (government-mandated).
  - `is_recurring` boolean not null default true.
  - `gl_account_id` uuid nullable FK → `acct.accounts.id` (liability account).
  - `is_system` boolean not null default false.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `code`) where deleted_at is null.
  - Index: `company_id`; (`company_id`, `is_active`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'pay.deduction_types'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','code','name','description','is_pre_tax','is_statutory','is_recurring','gl_account_id','is_system','is_active'];`
  - `$casts = ['company_id'=>'string','is_pre_tax'=>'boolean','is_statutory'=>'boolean','is_recurring'=>'boolean','gl_account_id'=>'string','is_system'=>'boolean','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Business rules:
  - Seed with common types: INCOME_TAX, SOCIAL_SECURITY, HEALTH_INS, PENSION, LOAN.
  - Statutory deductions may have jurisdiction-specific rules.

### pay.benefit_plans
- Purpose: company benefit offerings.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `code` varchar(50) not null.
  - `name` varchar(255) not null.
  - `description` text nullable.
  - `benefit_type` varchar(30) not null. Enum: health, dental, vision, life, pension, other.
  - `provider` varchar(255) nullable.
  - `employee_contrib_rate` numeric(7,4) not null default 0 (% of salary).
  - `employer_contrib_rate` numeric(7,4) not null default 0.
  - `employee_fixed_amount` numeric(15,2) nullable.
  - `employer_fixed_amount` numeric(15,2) nullable.
  - `currency` char(3) not null FK → `public.currencies.code`.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `code`).
  - Index: `company_id`; (`company_id`, `is_active`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'pay.benefit_plans'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','code','name','description','benefit_type','provider','employee_contrib_rate','employer_contrib_rate','employee_fixed_amount','employer_fixed_amount','currency','is_active'];`
  - `$casts = ['company_id'=>'string','employee_contrib_rate'=>'decimal:4','employer_contrib_rate'=>'decimal:4','employee_fixed_amount'=>'decimal:2','employer_fixed_amount'=>'decimal:2','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Use rate OR fixed amount, not both (enforce in validation).
  - Employer contribution is company expense.

### pay.employee_benefits
- Purpose: employee enrollment in benefit plans.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `employee_id` uuid not null FK → `pay.employees.id` (CASCADE/CASCADE).
  - `benefit_plan_id` uuid not null FK → `pay.benefit_plans.id` (CASCADE/CASCADE).
  - `start_date` date not null.
  - `end_date` date nullable.
  - `employee_override_amount` numeric(15,2) nullable.
  - `employer_override_amount` numeric(15,2) nullable.
  - `coverage_level` varchar(30) nullable. Enum: employee_only, employee_spouse, family.
  - `notes` text nullable.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`employee_id`, `benefit_plan_id`, `start_date`).
  - Index: `company_id`; `employee_id`; `benefit_plan_id`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'pay.employee_benefits'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','employee_id','benefit_plan_id','start_date','end_date','employee_override_amount','employer_override_amount','coverage_level','notes','is_active'];`
  - `$casts = ['company_id'=>'string','employee_id'=>'string','benefit_plan_id'=>'string','start_date'=>'date','end_date'=>'date','employee_override_amount'=>'decimal:2','employer_override_amount'=>'decimal:2','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Override amounts take precedence over plan rates.
  - Only active enrollments included in payroll.

### pay.leave_types
- Purpose: types of time off.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `code` varchar(50) not null.
  - `name` varchar(255) not null.
  - `description` text nullable.
  - `is_paid` boolean not null default true.
  - `accrual_rate_hours` numeric(7,3) not null default 0 (hours accrued per pay period).
  - `max_carryover_hours` numeric(7,3) nullable.
  - `max_balance_hours` numeric(7,3) nullable.
  - `requires_approval` boolean not null default true.
  - `is_active` boolean not null default true.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `code`).
  - Index: `company_id`; (`company_id`, `is_active`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'pay.leave_types'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','code','name','description','is_paid','accrual_rate_hours','max_carryover_hours','max_balance_hours','requires_approval','is_active'];`
  - `$casts = ['company_id'=>'string','is_paid'=>'boolean','accrual_rate_hours'=>'decimal:3','max_carryover_hours'=>'decimal:3','max_balance_hours'=>'decimal:3','requires_approval'=>'boolean','is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Seed with: ANNUAL, SICK, PERSONAL, UNPAID, MATERNITY, PATERNITY.
  - Accrual calculated during payroll processing.

### pay.leave_requests
- Purpose: employee leave requests.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `employee_id` uuid not null FK → `pay.employees.id` (CASCADE/CASCADE).
  - `leave_type_id` uuid not null FK → `pay.leave_types.id` (RESTRICT/CASCADE).
  - `start_date` date not null.
  - `end_date` date not null.
  - `hours` numeric(7,2) not null.
  - `reason` text nullable.
  - `status` varchar(20) not null default 'pending'. Enum: pending, approved, rejected, cancelled, taken.
  - `approved_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `approved_at` timestamp nullable.
  - `rejection_reason` varchar(255) nullable.
  - `notes` text nullable.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `employee_id`; (`company_id`, `status`); (`employee_id`, `start_date`, `end_date`).
  - Check: end_date >= start_date.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'pay.leave_requests'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','employee_id','leave_type_id','start_date','end_date','hours','reason','status','approved_by_user_id','approved_at','rejection_reason','notes'];`
  - `$casts = ['company_id'=>'string','employee_id'=>'string','leave_type_id'=>'string','start_date'=>'date','end_date'=>'date','hours'=>'decimal:2','approved_by_user_id'=>'string','approved_at'=>'datetime','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Cannot exceed available balance (if tracked).
  - Overlapping leave requests not allowed.
  - Approved requests reduce leave balance.

### pay.payroll_periods
- Purpose: define pay periods.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `period_start` date not null.
  - `period_end` date not null.
  - `payment_date` date not null.
  - `status` varchar(20) not null default 'open'. Enum: open, processing, closed, posted.
  - `closed_at` timestamp nullable.
  - `closed_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `period_start`, `period_end`).
  - Index: `company_id`; (`company_id`, `status`).
  - Check: period_end > period_start.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'pay.payroll_periods'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','period_start','period_end','payment_date','status','closed_at','closed_by_user_id'];`
  - `$casts = ['company_id'=>'string','period_start'=>'date','period_end'=>'date','payment_date'=>'date','closed_at'=>'datetime','closed_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Periods cannot overlap.
  - Cannot modify payslips in closed period.
  - Posted = GL entries created.

### pay.payslips
- Purpose: payslip header per employee per period.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `payroll_period_id` uuid not null FK → `pay.payroll_periods.id` (CASCADE/CASCADE).
  - `employee_id` uuid not null FK → `pay.employees.id` (RESTRICT/CASCADE).
  - `payslip_number` varchar(50) not null.
  - `currency` char(3) not null FK → `public.currencies.code`.
  - `gross_pay` numeric(15,2) not null default 0.
  - `total_earnings` numeric(15,2) not null default 0.
  - `total_deductions` numeric(15,2) not null default 0.
  - `employer_costs` numeric(15,2) not null default 0.
  - `net_pay` numeric(15,2) not null default 0.
  - `status` varchar(20) not null default 'draft'. Enum: draft, approved, paid, cancelled.
  - `approved_at` timestamp nullable.
  - `approved_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `paid_at` timestamp nullable.
  - `payment_method` varchar(30) nullable. Enum: bank_transfer, check, cash.
  - `payment_reference` varchar(100) nullable.
  - `gl_transaction_id` uuid nullable FK → `acct.transactions.id` (SET NULL/CASCADE).
  - `notes` text nullable.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `payslip_number`).
  - Unique (`payroll_period_id`, `employee_id`).
  - Index: `company_id`; `payroll_period_id`; `employee_id`; (`company_id`, `status`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'pay.payslips'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','payroll_period_id','employee_id','payslip_number','currency','gross_pay','total_earnings','total_deductions','employer_costs','net_pay','status','approved_at','approved_by_user_id','paid_at','payment_method','payment_reference','gl_transaction_id','notes'];`
  - `$casts = ['company_id'=>'string','payroll_period_id'=>'string','employee_id'=>'string','gross_pay'=>'decimal:2','total_earnings'=>'decimal:2','total_deductions'=>'decimal:2','employer_costs'=>'decimal:2','net_pay'=>'decimal:2','approved_at'=>'datetime','approved_by_user_id'=>'string','paid_at'=>'datetime','gl_transaction_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo PayrollPeriod; belongsTo Employee; hasMany PayslipLine; belongsTo GlTransaction.
- Business rules:
  - net_pay = total_earnings - total_deductions.
  - Totals updated by trigger on payslip_lines.
  - Cannot modify after approved.

### pay.payslip_lines
- Purpose: individual earning/deduction lines.
- Columns:
  - `id` uuid PK.
  - `payslip_id` uuid not null FK → `pay.payslips.id` (CASCADE/CASCADE).
  - `line_type` varchar(20) not null. Enum: earning, deduction, employer.
  - `earning_type_id` uuid nullable FK → `pay.earning_types.id` (SET NULL/CASCADE).
  - `deduction_type_id` uuid nullable FK → `pay.deduction_types.id` (SET NULL/CASCADE).
  - `description` varchar(255) nullable.
  - `quantity` numeric(10,3) not null default 1 (hours, units).
  - `rate` numeric(15,4) not null default 0.
  - `amount` numeric(15,2) not null.
  - `sort_order` integer not null default 0.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `payslip_id`.
  - Check: amount >= 0.
  - Check: (line_type = 'earning' AND earning_type_id IS NOT NULL) OR (line_type = 'deduction' AND deduction_type_id IS NOT NULL) OR (line_type = 'employer').
- RLS: inherited from parent (payslips).
- Model:
  - `$connection = 'pgsql'; $table = 'pay.payslip_lines'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['payslip_id','line_type','earning_type_id','deduction_type_id','description','quantity','rate','amount','sort_order'];`
  - `$casts = ['payslip_id'=>'string','earning_type_id'=>'string','deduction_type_id'=>'string','quantity'=>'decimal:3','rate'=>'decimal:4','amount'=>'decimal:2','sort_order'=>'integer','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Trigger rolls up totals to payslip header.
  - line_type = 'employer' for employer-paid items (benefits, taxes).

## Payroll Calculation Flow

1. **Generate payslips** for period
   - Select active employees matching pay_frequency
   - Create payslip header for each

2. **Calculate earnings**
   - Add base salary (prorated if partial period)
   - Add overtime (hours * rate)
   - Add bonuses, commissions, allowances

3. **Calculate deductions**
   - Pre-tax deductions (401k, HSA)
   - Calculate taxable income
   - Calculate taxes (income tax, social security)
   - Post-tax deductions (loans, garnishments)

4. **Add employer costs**
   - Employer portion of benefits
   - Employer taxes (social security match)

5. **Approve and pay**
   - Review and approve payslips
   - Generate payments (bank transfers)
   - Mark as paid

6. **Post to GL**
   - Debit salary expense accounts
   - Credit payroll liability
   - Credit bank account

## Enums Reference

### Employment Type
| Type | Description |
|------|-------------|
| full_time | Full-time employee |
| part_time | Part-time employee |
| contract | Contractor/temp |
| intern | Intern/trainee |

### Payslip Status
| Status | Description |
|--------|-------------|
| draft | Being prepared |
| approved | Approved for payment |
| paid | Payment processed |
| cancelled | Cancelled |

## Form Behaviors

### Employee Form
- Fields: employee_number, first_name, last_name, email, phone, hire_date, employment_type, department, position, pay_frequency, base_salary, currency, bank details
- Sensitive fields masked in display
- Manager dropdown filtered to same company employees
- Termination fields shown only when terminating

### Payroll Run Form
- Select or create payroll period
- Auto-generate payslips for eligible employees
- Review each payslip (earnings, deductions)
- Bulk approve
- Generate payment file (optional)
- Post to GL

### Leave Request Form
- Employee selects leave type
- Date range picker
- Hours calculated from dates
- Shows available balance
- Submitted to manager for approval

## Out of Scope (v1)
- Time tracking / timesheets.
- Expense reimbursements.
- Tax filing / W-2 generation.
- Direct deposit file generation.
- Multiple pay rates per employee.
- Jurisdiction-specific tax calculations.
- Retirement plan administration.

## Extending
- Add new employment_type values here first.
- Tax calculations would add `pay.tax_tables` and jurisdiction-specific logic.
- Time tracking would add `pay.timesheets` and `pay.timesheet_entries`.
