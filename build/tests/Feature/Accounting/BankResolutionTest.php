<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bank;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\BankTransaction;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\BankFeedResolutionService;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BankResolutionTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $user;
    protected $bankAccount;
    protected $resolutionService;
    protected $expenseAccount;
    protected $glBankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup User & Company
        $this->user = User::factory()->create();
        $this->company = \App\Models\Company::create([
            'name' => 'Test Corp',
            'owner_id' => $this->user->id,
            'slug' => 'test-corp',
        ]);
        
        // Ensure currencies exist (assuming seeder/factory usually handles this, but manual here for safety)
        if (!DB::table('public.currencies')->where('code', 'USD')->exists()) {
            DB::table('public.currencies')->insert(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
        }

        // 2. Setup Fiscal Year & Period
        $fy = FiscalYear::create([
            'company_id' => $this->company->id,
            'name' => 'FY 2024',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'status' => 'open',
        ]);
        
        AccountingPeriod::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $fy->id,
            'name' => 'Jan 2024',
            'period_number' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
        ]);

        // 3. Setup COA (Bank GL + Expense)
        $this->glBankAccount = Account::create([
            'company_id' => $this->company->id,
            'code' => '1000',
            'name' => 'Checking Account',
            'type' => 'asset',
            'subtype' => 'bank',
            'normal_balance' => 'debit',
            'currency' => 'USD',
        ]);

        $this->expenseAccount = Account::create([
            'company_id' => $this->company->id,
            'code' => '6000',
            'name' => 'Office Supplies',
            'type' => 'expense',
            'subtype' => 'expense',
            'normal_balance' => 'debit',
        ]);

        // 4. Setup Bank Account
        $bank = Bank::create(['name' => 'Test Bank']);
        $this->bankAccount = BankAccount::create([
            'company_id' => $this->company->id,
            'bank_id' => $bank->id,
            'gl_account_id' => $this->glBankAccount->id,
            'account_name' => 'Main Checking',
            'account_number' => '123456',
            'currency' => 'USD',
        ]);

        $this->resolutionService = new BankFeedResolutionService(new GlPostingService());
    }

    public function test_create_mode_spend_money()
    {
        // Arrange: A spent transaction of $100
        $bt = BankTransaction::create([
            'company_id' => $this->company->id,
            'bank_account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-01-15',
            'description' => 'Staples Store',
            'transaction_type' => 'debit',
            'amount' => -100.00, // Negative for spend
            'is_reconciled' => false,
        ]);

        $allocation = [
            [
                'account_id' => $this->expenseAccount->id,
                'amount' => 100.00, // Allocation is positive magnitude
                'description' => 'Pens and paper',
            ]
        ];

        // Act
        $resolvedBt = $this->resolutionService->resolveCreate($bt, $allocation);

        // Assert
        $this->assertTrue($resolvedBt->is_reconciled);
        $this->assertNotNull($resolvedBt->gl_transaction_id);

        // Verify Ledger
        $transaction = Transaction::find($resolvedBt->gl_transaction_id);
        $this->assertNotNull($transaction);
        $this->assertEquals(-100.00, $bt->amount);
        $this->assertEquals(100.00, $transaction->total_credit);
        $this->assertEquals(100.00, $transaction->total_debit);

        // Verify Journal Entries
        // Expect: CR Bank 100, DR Expense 100
        $entries = $transaction->journalEntries;
        $bankEntry = $entries->where('account_id', $this->glBankAccount->id)->first();
        $expenseEntry = $entries->where('account_id', $this->expenseAccount->id)->first();

        $this->assertEquals(100.00, $bankEntry->credit_amount);
        $this->assertEquals(0.00, $bankEntry->debit_amount);

        $this->assertEquals(100.00, $expenseEntry->debit_amount);
        $this->assertEquals(0.00, $expenseEntry->credit_amount);
    }

    public function test_park_mode()
    {
        $bt = BankTransaction::create([
            'company_id' => $this->company->id,
            'bank_account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-01-15',
            'description' => 'Mystery Charge',
            'transaction_type' => 'debit',
            'amount' => -50.00,
            'is_reconciled' => false,
        ]);

        $this->resolutionService->resolvePark($bt, "What is this?");

        $bt->refresh();
        $this->assertFalse($bt->is_reconciled);
        $this->assertEquals("What is this?", $bt->notes);
    }
}
