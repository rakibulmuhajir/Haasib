# Automatic Onboarding Detection System

**Created**: 2025-12-09
**Status**: ✅ Complete and Functional

---

## Overview

The system automatically detects first-time users and guides them through company setup, either via the guided wizard or manual setup.

---

## How It Works

### 1. User Login Detection

When a user logs in and navigates to any page:

1. **CheckFirstTimeUser Middleware** runs on every request
2. Checks if user has any company memberships
3. If no companies found → Redirect to Welcome page
4. If companies exist → Continue normally

### 2. Welcome Page

**Route**: `/welcome`
**Component**: `resources/js/pages/onboarding/FirstTimeSetup.vue`

Presents two options:

#### Option A: Guided Setup (Recommended)
- Beautiful card with "RECOMMENDED" badge
- Lists features:
  - ✅ Industry-Specific Accounts (20-30 pre-configured)
  - ✅ Automatic Configuration (fiscal year, bank accounts, tax)
  - ✅ Best Practices Built-In
- Button: "Start Guided Setup (7 steps)"
- Takes 5-10 minutes

#### Option B: Manual Setup
- For advanced users with accounting experience
- Lists features:
  - Full Control
  - Custom Configuration
  - For Advanced Users
- Button: "Set Up Manually"

### 3. User Paths

```
User Logs In (First Time)
    ↓
CheckFirstTimeUser Middleware
    ↓
No Companies? → /welcome
    ↓
┌─────────────────────────┬──────────────────────────┐
│ Guided Setup            │ Manual Setup             │
│ (Recommended)           │ (Advanced)               │
├─────────────────────────┼──────────────────────────┤
│ /companies/create       │ /companies/create        │
│ ?guided=true            │                          │
│         ↓               │         ↓                │
│ Create Company          │ Create Company           │
│         ↓               │         ↓                │
│ Redirect to Onboarding  │ Redirect to Onboarding   │
│ /{company}/onboarding   │ /{company}/onboarding    │
│         ↓               │         ↓                │
│ 7-Step Wizard           │ Can skip/configure later │
└─────────────────────────┴──────────────────────────┘
```

---

## Components Created

### 1. Middleware: `CheckFirstTimeUser.php`
**Location**: `app/Http/Middleware/CheckFirstTimeUser.php`

**Logic**:
```php
- Skip if not authenticated
- Skip for god-mode users (UUID starts with 00000000)
- Skip if on welcome/onboarding pages
- Check company memberships
- Redirect to /welcome if no companies
```

**Registered in**: `bootstrap/app.php` (web middleware group)

### 2. Controller: `WelcomeController.php`
**Location**: `app/Http/Controllers/WelcomeController.php`

**Method**: `index()`
- Renders welcome page with user info

### 3. Vue Component: `FirstTimeSetup.vue`
**Location**: `resources/js/pages/onboarding/FirstTimeSetup.vue`

**Features**:
- Hero section with animated rocket icon
- Two-option cards (Guided vs Manual)
- Features showcase section (4 key features)
- Help links at bottom
- Full responsive design
- Dark mode support

---

## Routes

```php
// Welcome page for first-time users
Route::get('welcome', [WelcomeController::class, 'index'])
    ->middleware(['auth'])
    ->name('welcome');
```

---

## Migrations

### Migration 1: `2025_11_27_100005_create_industry_coa_packs.php`
Creates:
- `acct.industry_coa_packs` - 14 industries
- `acct.industry_coa_templates` - Account templates per industry

### Migration 2: `2025_12_13_000000_create_company_onboarding_table.php`
Creates:
- `auth.company_onboarding` - Progress tracking table

### Migration 3: `2025_12_13_000001_add_onboarding_fields_to_companies.php`
Adds to `auth.companies`:
- `industry_code`
- `registration_number`
- `trade_name`
- `timezone`
- `fiscal_year_start_month`
- `period_frequency`
- `invoice_prefix`
- `invoice_start_number`
- `bill_prefix`
- `bill_start_number`
- `default_customer_payment_terms`
- `default_vendor_payment_terms`
- `tax_registered`
- `tax_rate`
- `tax_inclusive`
- `onboarding_completed`
- `onboarding_completed_at`
- `ar_account_id`
- `ap_account_id`
- `income_account_id`
- `expense_account_id`
- `bank_account_id`
- `retained_earnings_account_id`
- `sales_tax_payable_account_id`
- `purchase_tax_receivable_account_id`

---

## User Experience Flow

### First-Time User Journey

1. **Register/Login**
   - User creates account or logs in
   - No companies exist yet

2. **Automatic Redirect**
   - Middleware detects no companies
   - Redirects to `/welcome`

3. **Welcome Page**
   - Beautiful, friendly interface
   - Clear explanation of what's ahead
   - Two clear paths to choose from

4. **Guided Setup Path** (Most users)
   - Click "Start Guided Setup"
   - Create company with basic info
   - Automatically redirected to onboarding wizard
   - Complete 7 steps (5-10 minutes)
   - Company fully configured

5. **Manual Setup Path** (Advanced users)
   - Click "Set Up Manually"
   - Create company
   - Can configure everything themselves
   - Can skip onboarding if desired

---

## Skipping Checks

The middleware skips checking for:
- **Unauthenticated users** - Let auth middleware handle
- **God-mode users** - Admins don't need onboarding
- **Welcome page** - Prevent redirect loop
- **Company creation pages** - Let user create company
- **Onboarding pages** - User is already in the flow

---

## Benefits

### 1. Zero Friction for New Users
- No confusion about "what do I do first?"
- Clear path forward
- Automatic detection

### 2. Flexibility
- Guided path for non-accountants
- Manual path for experts
- Can always access onboarding later

### 3. Professional UX
- Beautiful, modern interface
- Clear value proposition
- Reduces drop-off

### 4. Smart Detection
- Works automatically
- No configuration needed
- Respects user's current state

---

## Testing Scenarios

### Scenario 1: Brand New User
```
1. User registers account
2. Logs in
3. Redirected to /welcome
4. Chooses guided setup
5. Creates company
6. Completes 7-step wizard
7. Company ready to use
```

### Scenario 2: User With Existing Company
```
1. User logs in
2. Middleware checks companies
3. Finds existing membership
4. Continues to requested page (e.g., /dashboard)
5. No redirect
```

### Scenario 3: Advanced User
```
1. New user logs in
2. Redirected to /welcome
3. Chooses "Manual Setup"
4. Creates company
5. Can configure manually
6. Not forced through wizard
```

### Scenario 4: Multi-Company User
```
1. User with Company A logs in
2. Middleware finds membership
3. No redirect
4. User can switch companies or create new ones normally
```

---

## Configuration

### Middleware Registration
**File**: `bootstrap/app.php`
```php
$middleware->web(append: [
    HandleAppearance::class,
    HandleInertiaRequests::class,
    AddLinkHeadersForPreloadedAssets::class,
    CheckFirstTimeUser::class, // Automatically checks first-time users
]);
```

### God-Mode Exclusion
God-mode users (UUID starts with `00000000-0000-0000-0000-`) are excluded from the check and never redirected to welcome page.

---

## Future Enhancements

### Optional Improvements

1. **Onboarding Progress Badge**
   - Show "Complete Setup" badge in sidebar if onboarding not done
   - Let users resume later

2. **Skip Option**
   - "Skip for now" button on welcome page
   - Creates minimal company, can complete later

3. **Email Reminder**
   - Send reminder if user hasn't completed setup after 24 hours

4. **Analytics**
   - Track which path users choose
   - Measure completion rates
   - Identify drop-off points

5. **Video Walkthrough**
   - Embed quick video explaining options
   - Show what each path includes

---

## Integration Points

### With Company Creation
`CompanyController@store` redirects to:
```php
return redirect("/{$company->slug}/onboarding")
    ->with('success', 'Company created! Let\'s set it up.');
```

### With Dashboard
Dashboard assumes company context exists. First-time user middleware ensures this by redirecting before dashboard access.

### With Onboarding Wizard
Welcome page funnels users into existing 7-step wizard. No changes needed to wizard itself.

---

## Technical Details

### Middleware Order
1. `HandleAppearance` - Theme detection
2. `HandleInertiaRequests` - Inertia setup
3. `AddLinkHeadersForPreloadedAssets` - Performance
4. `CheckFirstTimeUser` - **New** - Onboarding detection

Runs before route resolution, ensuring check happens on every request.

### Database Queries
- **Single query** per request: `SELECT user_id FROM auth.company_user WHERE user_id = ? LIMIT 1`
- Minimal performance impact
- Can be cached if needed

### Security
- God-mode users excluded
- Auth middleware still enforces login
- No bypassing of permissions

---

## Files Modified/Created

### Created:
1. `app/Http/Middleware/CheckFirstTimeUser.php`
2. `app/Http/Controllers/WelcomeController.php`
3. `resources/js/pages/onboarding/FirstTimeSetup.vue`
4. `database/migrations/2025_12_13_000000_create_company_onboarding_table.php`
5. `database/migrations/2025_12_13_000001_add_onboarding_fields_to_companies.php`
6. `docs/auto-onboarding-system.md` (this file)

### Modified:
1. `bootstrap/app.php` - Registered middleware
2. `routes/web.php` - Added welcome route
3. `database/migrations/2025_11_27_100005_create_industry_coa_packs.php` - Removed company_onboarding table creation

---

**Status**: ✅ Production Ready
**Last Tested**: 2025-12-09
**Migrations**: All passing
**Components**: Fully functional
