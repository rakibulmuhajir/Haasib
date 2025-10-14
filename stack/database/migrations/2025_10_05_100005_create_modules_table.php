<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auth.modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('key')->unique();
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('category')->default('general');
            $table->string('icon')->nullable();
            $table->boolean('is_core')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('dependencies')->nullable()->comment('Array of module keys this module depends on');
            $table->json('settings_schema')->nullable()->comment('JSON schema for module settings');
            $table->json('permissions')->nullable()->comment('Array of permissions this module provides');
            $table->integer('menu_order')->default(999);
            $table->string('developer')->nullable();
            $table->string('documentation_url')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('key');
            $table->index('category');
            $table->index('is_core');
            $table->index('is_active');
            $table->index(['category', 'menu_order']);
            $table->index(['is_active', 'category']);
        });

        // Insert core modules
        DB::table('auth.modules')->insert([
            [
                'id' => Str::uuid(),
                'name' => 'Core Accounting',
                'key' => 'core_accounting',
                'description' => 'Core accounting features including chart of accounts, journal entries, and financial reports',
                'version' => '1.0.0',
                'category' => 'accounting',
                'icon' => 'calculator',
                'is_core' => true,
                'is_active' => true,
                'dependencies' => json_encode([]),
                'permissions' => json_encode([
                    'view_accounts',
                    'manage_accounts',
                    'view_journal_entries',
                    'create_journal_entries',
                    'edit_journal_entries',
                    'delete_journal_entries',
                    'view_reports',
                    'generate_reports',
                ]),
                'menu_order' => 1,
                'developer' => 'Haasib Team',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Invoicing',
                'key' => 'invoicing',
                'description' => 'Create and manage invoices, track payments, and send reminders',
                'version' => '1.0.0',
                'category' => 'sales',
                'icon' => 'file-invoice',
                'is_core' => true,
                'is_active' => true,
                'dependencies' => json_encode(['core_accounting']),
                'permissions' => json_encode([
                    'view_invoices',
                    'create_invoices',
                    'edit_invoices',
                    'delete_invoices',
                    'send_invoices',
                    'record_payments',
                ]),
                'menu_order' => 10,
                'developer' => 'Haasib Team',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Bill Management',
                'key' => 'bill_management',
                'description' => 'Manage vendor bills, expenses, and purchase orders',
                'version' => '1.0.0',
                'category' => 'purchases',
                'icon' => 'file-invoice-dollar',
                'is_core' => true,
                'is_active' => true,
                'dependencies' => json_encode(['core_accounting']),
                'permissions' => json_encode([
                    'view_bills',
                    'create_bills',
                    'edit_bills',
                    'delete_bills',
                    'pay_bills',
                    'manage_vendors',
                ]),
                'menu_order' => 20,
                'developer' => 'Haasib Team',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Banking',
                'key' => 'banking',
                'description' => 'Manage bank accounts, reconcile transactions, and import statements',
                'version' => '1.0.0',
                'category' => 'banking',
                'icon' => 'university',
                'is_core' => true,
                'is_active' => true,
                'dependencies' => json_encode(['core_accounting']),
                'permissions' => json_encode([
                    'view_bank_accounts',
                    'manage_bank_accounts',
                    'reconcile_accounts',
                    'import_transactions',
                ]),
                'menu_order' => 30,
                'developer' => 'Haasib Team',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Reporting Dashboard',
                'key' => 'reporting_dashboard',
                'description' => 'Advanced reporting and analytics dashboard',
                'version' => '1.0.0',
                'category' => 'reporting',
                'icon' => 'chart-line',
                'is_core' => true,
                'is_active' => true,
                'dependencies' => json_encode(['core_accounting']),
                'permissions' => json_encode([
                    'view_dashboard',
                    'export_reports',
                    'schedule_reports',
                ]),
                'menu_order' => 40,
                'developer' => 'Haasib Team',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Multi-Currency',
                'key' => 'multi_currency',
                'description' => 'Handle multiple currencies and automatic exchange rate updates',
                'version' => '1.0.0',
                'category' => 'settings',
                'icon' => 'coins',
                'is_core' => true,
                'is_active' => true,
                'dependencies' => json_encode(['core_accounting']),
                'permissions' => json_encode([
                    'manage_currencies',
                    'update_exchange_rates',
                    'view_currency_reports',
                ]),
                'menu_order' => 50,
                'developer' => 'Haasib Team',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create trigger for updated_at
        DB::statement('
            CREATE TRIGGER modules_updated_at
                BEFORE UPDATE ON auth.modules
                FOR EACH ROW
                EXECUTE FUNCTION auth.set_updated_by();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger first
        DB::statement('DROP TRIGGER IF EXISTS modules_updated_at ON auth.modules');

        // Drop table
        Schema::dropIfExists('auth.modules');
    }
};
