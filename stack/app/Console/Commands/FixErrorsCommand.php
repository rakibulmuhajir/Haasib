<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class FixErrorsCommand extends Command
{
    protected $signature = 'fix:errors {--all}';
    protected $description = 'Automatically fix common application errors';

    protected $fixes = [];

    public function handle()
    {
        $this->info('🔧 Automatically Fixing Common Errors');
        $this->info('======================================');

        if ($this->option('all')) {
            $this->fixAllIssues();
        } else {
            $this->showMenu();
        }

        return self::SUCCESS;
    }

    protected function showMenu()
    {
        $this->info('Available fixes:');
        $this->info('1. Fix Vite CSS issue');
        $this->info('2. Fix missing tables');
        $this->info('3. Fix controller method signatures');
        $this->info('4. Fix API routes');
        $this->info('5. Clear caches and optimize');
        $this->info('6. Run all fixes');

        $choice = $this->ask('Choose a fix option (1-6):');

        switch ($choice) {
            case 1:
                $this->fixViteCssIssue();
                break;
            case 2:
                $this->fixMissingTables();
                break;
            case 3:
                $this->fixControllerSignatures();
                break;
            case 4:
                $this->fixApiRoutes();
                break;
            case 5:
                $this->clearAndOptimize();
                break;
            case 6:
                $this->fixAllIssues();
                break;
            default:
                $this->error('Invalid choice');
                return self::FAILURE;
        }
    }

    protected function fixAllIssues()
    {
        $this->info('🚀 Running all automatic fixes...');

        $this->fixViteCssIssue();
        $this->fixMissingTables();
        $this->fixControllerSignatures();
        $this->fixApiRoutes();
        $this->clearAndOptimize();

        $this->info('✅ All automatic fixes completed!');
        $this->showSummary();
    }

    protected function fixViteCssIssue()
    {
        $this->info('🎨 Fixing Vite CSS issue...');

        $manifestPath = public_path('build/manifest.json');
        $appCssPath = resource_path('js/styles/app.css');
        $appJsPath = resource_path('js/app.js');

        // Check if app.css exists in resources/js/styles
        if (!file_exists($appCssPath)) {
            $this->info('  Creating missing app.css file...');
            $this->ensureDirectoryExists(dirname($appCssPath));

            $cssContent = <<<'CSS'
/* app.css - Main application styles */
:root {
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --secondary: #6b7280;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --dark: #1f2937;
    --light: #f9fafb;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: var(--dark);
    background-color: var(--light);
}

/* Layout styles */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.card {
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
}

.btn-primary:hover {
    background-color: var(--primary-dark);
}

/* Navigation */
.navbar {
    background: white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 1rem 0;
}

.navbar-brand {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--primary);
}

/* Forms */
.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    font-size: 1rem;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Tables */
.table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
}

.table th,
.table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.table th {
    background-color: #f9fafb;
    font-weight: 600;
}

/* Alerts */
.alert {
    padding: 1rem;
    border-radius: 0.25rem;
    margin-bottom: 1rem;
}

.alert-success {
    background-color: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-warning {
    background-color: #fffbeb;
    color: #92400e;
    border: 1px solid #fde68a;
}

.alert-danger {
    background-color: #fef2f2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* Utility classes */
.text-center { text-align: center; }
.text-right { text-align: right; }
.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.hidden { display: none; }

/* Responsive */
@media (max-width: 768px) {
    .container {
        padding: 0 0.5rem;
    }

    .card {
        padding: 1rem;
    }
}
CSS;

            file_put_contents($appCssPath, $cssContent);
            $this->fixes[] = 'Created app.css file';
        }

        // Check if app.js exists
        if (!file_exists($appJsPath)) {
            $this->info('  Creating minimal app.js file...');

            $jsContent = <<<'JS'
// app.js - Main application JavaScript
import './bootstrap.js';

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    console.log('Application initialized');

    // Basic functionality
    initTooltips();
    initDropdowns();
    initModals();
});

// Bootstrap functionality
function initTooltips() {
    // Initialize tooltip functionality
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function initDropdowns() {
    // Initialize dropdown functionality
    const dropdownToggles = document.querySelectorAll('[data-dropdown-toggle]');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', toggleDropdown);
    });
}

function initModals() {
    // Initialize modal functionality
    const modalTriggers = document.querySelectorAll('[data-modal-trigger]');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', openModal);
    });
}

// Utility functions
function showTooltip(event) {
    const text = event.target.getAttribute('data-tooltip');
    // Tooltip implementation
}

function hideTooltip() {
    // Hide tooltip implementation
}

function toggleDropdown(event) {
    event.preventDefault();
    // Dropdown toggle implementation
}

function openModal(event) {
    event.preventDefault();
    // Modal open implementation
}

// CSRF token setup
window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// AJAX helper
function ajaxRequest(url, options = {}) {
    const defaults = {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken
        }
    };

    const config = Object.assign(defaults, options);

    return fetch(url, config);
}

// Export for use in other modules
window.Haasib = {
    ajax: ajaxRequest,
    tooltip: { show: showTooltip, hide: hideTooltip },
    dropdown: toggleDropdown,
    modal: { open: openModal }
};
JS;

            file_put_contents($appJsPath, $jsContent);
            $this->fixes[] = 'Created app.js file';
        }

        // Rebuild assets
        $this->info('  Rebuilding Vite assets...');
        $this->executeArtisanCommand('npm run build');

        $this->info('✅ Vite CSS issue fixed');
    }

    protected function fixMissingTables()
    {
        $this->info('🗄️ Fixing missing database tables...');

        // Check if users table exists in auth schema
        try {
            DB::connection('pgsql')->statement("SELECT 1 FROM auth.users LIMIT 1");
            $this->info('  auth.users table exists');
        } catch (\Exception $e) {
            $this->info('  Creating auth.users table...');
            $this->createAuthUsersTable();
        }

        // Check if companies table exists
        try {
            DB::connection('pgsql')->statement("SELECT 1 FROM auth.companies LIMIT 1");
            $this->info('  auth.companies table exists');
        } catch (\Exception $e) {
            $this->info('  Creating auth.companies table...');
            $this->createAuthCompaniesTable();
        }

        // Check if fiscal_years table exists (mentioned in errors)
        try {
            DB::connection('pgsql')->statement("SELECT 1 FROM acct.fiscal_years LIMIT 1");
            $this->info('  acct.fiscal_years table exists');
        } catch (\Exception $e) {
            $this->info('  Creating acct.fiscal_years table...');
            $this->createFiscalYearsTable();
        }

        $this->info('✅ Database tables fixed');
    }

    protected function fixControllerSignatures()
    {
        $this->info('🎮 Fixing controller method signatures...');

        // Fix InvoiceTemplateController
        $controllerPath = app_path('Http/Controllers/Invoicing/InvoiceTemplateController.php');
        if (file_exists($controllerPath)) {
            $content = file_get_contents($controllerPath);

            // Fix validate method signature
            if (str_contains($content, 'function validate(')) {
                $content = preg_replace(
                    '/function validate\(Illuminate\\\\Http\\\\Request \$request.*?\):/',
                    'function validate(Illuminate\Http\Request $request, ?App\Models\InvoiceTemplate $template = null):',
                    $content
                );

                file_put_contents($controllerPath, $content);
                $this->fixes[] = 'Fixed InvoiceTemplateController validate method';
            }
        }

        // Fix PeriodCloseController
        $periodControllerPath = app_path('Http/Controllers/Ledger/PeriodCloseController.php');
        if (file_exists($periodControllerPath)) {
            $content = file_get_contents($periodControllerPath);

            // Fix validate method signature
            if (str_contains($content, 'function validate(')) {
                $content = preg_replace(
                    '/function validate\(Illuminate\\\\Http\\\\Request \$request.*?\):/',
                    'function validate(Illuminate\Http\Request $request, string $periodId):',
                    $content
                );

                file_put_contents($periodControllerPath, $content);
                $this->fixes[] = 'Fixed PeriodCloseController validate method';
            }
        }

        $this->info('✅ Controller signatures fixed');
    }

    protected function fixApiRoutes()
    {
        $this->info('🔌 Fixing API routes...');

        $apiRoutesPath = base_path('routes/api.php');

        if (!file_exists($apiRoutesPath)) {
            $this->info('  Creating api.php routes file...');

            $routesContent = <<<'PHP'
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('/v1')->group(function () {
    // Companies
    Route::get('/companies', [App\Http\Controllers\Api\CompanyController::class, 'index']);
    Route::post('/companies', [App\Http\Controllers\Api\CompanyController::class, 'store']);
    Route::get('/companies/{company}', [App\Http\Controllers\Api\CompanyController::class, 'show']);
    Route::put('/companies/{company}', [App\Http\Controllers\Api\CompanyController::class, 'update']);
    Route::delete('/companies/{company}', [App\Http\Controllers\Api\CompanyController::class, 'destroy']);

    // Customers
    Route::get('/customers', [App\Http\Controllers\Api\CustomerController::class, 'index']);
    Route::post('/customers', [App\Http\Controllers\Api\CustomerController::class, 'store']);
    Route::get('/customers/{customer}', [App\Http\Controllers\Api\CustomerController::class, 'show']);
    Route::put('/customers/{customer}', [App\Http\Controllers\Api\CustomerController::class, 'update']);
    Route::delete('/customers/{customer}', [App\Http\Controllers\Api\CustomerController::class, 'destroy']);

    // Invoices
    Route::get('/invoices', [App\Http\Controllers\Api\InvoiceController::class, 'index']);
    Route::post('/invoices', [App\Http\Controllers\Api\InvoiceController::class, 'store']);
    Route::get('/invoices/{invoice}', [App\Http\Controllers\Api\InvoiceController::class, 'show']);
    Route::put('/invoices/{invoice}', [App\Http\Controllers\Api\InvoiceController::class, 'update']);
    Route::delete('/invoices/{invoice}', [App\Http\Controllers\Api\InvoiceController::class, 'destroy']);

    // Payments
    Route::get('/payments', [App\Http\Controllers\Api\PaymentController::class, 'index']);
    Route::post('/payments', [App\Http\Controllers\Api\PaymentController::class, 'store']);
    Route::get('/payments/{payment}', [App\Http\Controllers\Api\PaymentController::class, 'show']);
    Route::put('/payments/{payment}', [App\Http\Controllers\Api\PaymentController::class, 'update']);
    Route::delete('/payments/{payment}', [App\Http\Controllers\Api\PaymentController::class, 'destroy']);

    // Journal Entries
    Route::get('/journal-entries', [App\Http\Controllers\Api\JournalEntryController::class, 'index']);
    Route::post('/journal-entries', [App\Http\Controllers\Api\JournalEntryController::class, 'store']);
    Route::get('/journal-entries/{entry}', [App\Http\Controllers\Api\JournalEntryController::class, 'show']);
    Route::put('/journal-entries/{entry}', [App\Http\Controllers\Api\JournalEntryController::class, 'update']);
    Route::delete('/journal-entries/{entry}', [App\Http\Controllers\Api\JournalEntryController::class, 'destroy']);
});

// Public API endpoints (no auth required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0')
    ]);
});
PHP;

            file_put_contents($apiRoutesPath, $routesContent);
            $this->fixes[] = 'Created API routes file';
        }

        // Create missing API controllers
        $this->createApiControllers();

        $this->info('✅ API routes fixed');
    }

    protected function clearAndOptimize()
    {
        $this->info('🧹 Clearing caches and optimizing...');

        $this->executeArtisanCommand('php artisan config:clear');
        $this->executeArtisanCommand('php artisan cache:clear');
        $this->executeArtisanCommand('php artisan route:clear');
        $this->executeArtisanCommand('php artisan view:clear');
        $this->executeArtisanCommand('php artisan config:cache');
        $this->executeArtisanCommand('php artisan route:cache');
        $this->executeArtisanCommand('php artisan view:cache');

        $this->info('✅ Caches cleared and optimized');
    }

    protected function createAuthUsersTable()
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS auth.users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    system_role VARCHAR(50) NOT NULL DEFAULT 'user',
    email_verified_at TIMESTAMP NULL,
    created_by_user_id UUID NULL REFERENCES auth.users(id),
    is_active BOOLEAN NOT NULL DEFAULT true,
    settings JSONB NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Enable RLS
ALTER TABLE auth.users ENABLE ROW LEVEL SECURITY;

-- Create RLS policy
DROP POLICY IF EXISTS users_policy ON auth.users;
CREATE POLICY users_policy ON auth.users
FOR ALL USING (true);

-- Indexes
CREATE INDEX IF NOT EXISTS users_email_index ON auth.users(email);
CREATE INDEX IF NOT EXISTS users_username_index ON auth.users(username);
CREATE INDEX IF NOT EXISTS users_system_role_index ON auth.users(system_role);
SQL;

        DB::connection('pgsql')->statement($sql);
        $this->fixes[] = 'Created auth.users table';
    }

    protected function createAuthCompaniesTable()
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS auth.companies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    business_name VARCHAR(255) NULL,
    tax_id VARCHAR(50) NULL,
    registration_number VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    website VARCHAR(255) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(100) NULL DEFAULT 'US',
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    fiscal_year_start DATE DEFAULT (date_trunc('year', CURRENT_DATE) + interval '1 month')::date,
    is_active BOOLEAN NOT NULL DEFAULT true,
    settings JSONB NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Enable RLS
ALTER TABLE auth.companies ENABLE ROW LEVEL SECURITY;

-- Create RLS policy
DROP POLICY IF EXISTS companies_policy ON auth.companies;
CREATE POLICY companies_policy ON auth.companies
FOR ALL USING (true);

-- Indexes
CREATE INDEX IF NOT EXISTS companies_name_index ON auth.companies(name);
CREATE INDEX IF NOT EXISTS companies_is_active_index ON auth.companies(is_active);
SQL;

        DB::connection('pgsql')->statement($sql);
        $this->fixes[] = 'Created auth.companies table';
    }

    protected function createFiscalYearsTable()
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS acct.fiscal_years (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN NOT NULL DEFAULT false,
    is_locked BOOLEAN NOT NULL DEFAULT false,
    created_by_user_id UUID NULL REFERENCES auth.users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fiscal_years_date_order CHECK (end_date > start_date),
    CONSTRAINT fiscal_years_unique_current_company UNIQUE (company_id, is_current) DEFERRABLE INITIALLY DEFERRED
);

-- Enable RLS
ALTER TABLE acct.fiscal_years ENABLE ROW LEVEL SECURITY;

-- Create RLS policy
DROP POLICY IF EXISTS fiscal_years_policy ON acct.fiscal_years;
CREATE POLICY fiscal_years_policy ON acct.fiscal_years
FOR ALL USING (
    company_id = current_setting('app.current_company_id', true)::uuid
    OR current_setting('app.is_super_admin', true)::boolean = true
);

-- Indexes
CREATE INDEX IF NOT EXISTS fiscal_years_company_id_index ON acct.fiscal_years(company_id);
CREATE INDEX IF NOT EXISTS fiscal_years_dates_index ON acct.fiscal_years(start_date, end_date);
CREATE INDEX IF NOT EXISTS fiscal_years_current_index ON acct.fiscal_years(is_current);
SQL;

        DB::connection('pgsql')->statement($sql);
        $this->fixes[] = 'Created acct.fiscal_years table';
    }

    protected function createApiControllers()
    {
        $controllers = [
            'CompanyController',
            'CustomerController',
            'InvoiceController',
            'PaymentController',
            'JournalEntryController'
        ];

        foreach ($controllers as $controller) {
            $this->createApiController($controller);
        }
    }

    protected function createApiController($name)
    {
        $path = app_path("Http/Controllers/Api/{$name}.php");

        if (!file_exists($path)) {
            $content = $this->getApiControllerTemplate($name);
            file_put_contents($path, $content);
            $this->fixes[] = "Created {$name} API controller";
        }
    }

    protected function getApiControllerTemplate($name)
    {
        $model = str_replace('Controller', '', $name);
        $resourceName = strtolower(str_plural($model));
        $resourceNameSingular = strtolower($model);

        return "<?php\n\nnamespace App\\Http\\Controllers\\Api;\n\nuse App\\Http\\Controllers\\Controller;\nuse App\\Models\\{$model};\nuse Illuminate\\Http\\Request;\nuse Illuminate\\Http\\JsonResponse;\n\nclass {$name} extends Controller\n{\n    public function index(): JsonResponse\n    {\n        return response()->json([\n            'data' => {$model}::all()\n        ]);\n    }\n\n    public function store(Request \$request): JsonResponse\n    {\n        \$validated = \$request->validate([\n            // Add validation rules here\n        ]);\n\n        \${$resourceNameSingular} = {$model}::create(\$validated);\n\n        return response()->json([\n            'data' => \${$resourceNameSingular}\n        ], 201);\n    }\n\n    public function show({$model} \${$resourceNameSingular}): JsonResponse\n    {\n        return response()->json([\n            'data' => \${$resourceNameSingular}\n        ]);\n    }\n\n    public function update(Request \$request, {$model} \${$resourceNameSingular}): JsonResponse\n    {\n        \$validated = \$request->validate([\n            // Add validation rules here\n        ]);\n\n        \${$resourceNameSingular}->update(\$validated);\n\n        return response()->json([\n            'data' => \${$resourceNameSingular}\n        ]);\n    }\n\n    public function destroy({$model} \${$resourceNameSingular}): JsonResponse\n    {\n        \${$resourceNameSingular}->delete();\n\n        return response()->json(null, 204);\n    }\n}\n";
    }

    protected function ensureDirectoryExists($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    protected function executeArtisanCommand($command)
    {
        $this->info("  Running: {$command}");
        Artisan::call($command);
    }

    protected function showSummary()
    {
        $this->info('');
        $this->info('📊 Fix Summary');
        $this->info('================');

        $this->info('Fixed ' . count($this->fixes) . ' issues:');
        foreach ($this->fixes as $fix) {
            $this->info("  ✅ {$fix}");
        }

        $this->info('');
        $this->info('🔄 Next steps:');
        $this->info('1. Test the application at http://localhost:8000');
        $this->info('2. Login with admin/password');
        $this->info('3. Check that main pages are working');
        $this->info('4. Run php artisan error:scan to verify fixes');
    }
}