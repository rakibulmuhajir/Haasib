<?php

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Modules\Ledger\Jobs\NormalizeBankStatement;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
    $this->actingAs($this->user);

    $this->bankAccount = ChartOfAccount::factory()->create([
        'company_id' => $this->company->id,
        'account_type' => 'asset',
        'account_subtype' => 'bank',
    ]);

    Storage::fake('bank-statements');
    Bus::fake();
});

it('can upload and import a CSV bank statement', function () {
    $csvContent = "Transaction Date,Description,Amount,Balance\n".
                  "2025-09-01,Deposit from Customer,1000.00,1000.00\n".
                  "2025-09-05,Payment to Vendor,-500.00,500.00\n".
                  '2025-09-10,Service Fee,-25.00,475.00';

    $file = UploadedFile::fake()->createWithContent('statement.csv', $csvContent);

    $response = $this->postJson(route('bank-statements.import'), [
        'bank_account_id' => $this->bankAccount->id,
        'statement_file' => $file,
        'statement_period_start' => '2025-09-01',
        'statement_period_end' => '2025-09-30',
        'opening_balance' => '0.00',
        'closing_balance' => '475.00',
        'currency' => 'USD',
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'status' => 'pending',
        'bank_account_id' => $this->bankAccount->id,
        'currency' => 'USD',
    ]);

    expect(BankStatement::count())->toBe(1);
    $statement = BankStatement::first();
    expect($statement->company_id)->toBe($this->company->id);
    expect($statement->ledger_account_id)->toBe($this->bankAccount->id);
    expect($statement->status)->toBe('pending');
    expect($statement->opening_balance)->toBe(0);
    expect($statement->closing_balance)->toBe(475.00);

    Bus::assertDispatched(NormalizeBankStatement::class, function ($job) use ($statement) {
        return $job->bankStatement->id === $statement->id;
    });
});

it('can upload and import an OFX bank statement', function () {
    $ofxContent = generateSampleOfxContent();

    $file = UploadedFile::fake()->createWithContent('statement.ofx', $ofxContent);

    $response = $this->postJson(route('bank-statements.import'), [
        'bank_account_id' => $this->bankAccount->id,
        'statement_file' => $file,
        'statement_period_start' => '2025-09-01',
        'statement_period_end' => '2025-09-30',
        'opening_balance' => '1000.00',
        'closing_balance' => '1475.00',
        'currency' => 'USD',
    ]);

    $response->assertStatus(201);
    expect(BankStatement::count())->toBe(1);

    $statement = BankStatement::first();
    expect($statement->format)->toBe('ofx');
    expect($statement->opening_balance)->toBe(1000.00);
    expect($statement->closing_balance)->toBe(1475.00);

    Bus::assertDispatched(NormalizeBankStatement::class);
});

it('validates required fields for bank statement import', function () {
    $response = $this->postJson(route('bank-statements.import'), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'bank_account_id',
        'statement_file',
        'statement_period_start',
        'statement_period_end',
        'opening_balance',
        'closing_balance',
        'currency',
    ]);
});

it('validates file format and size', function () {
    $invalidFile = UploadedFile::fake()->create('statement.txt', 15000); // 15MB, over limit

    $response = $this->postJson(route('bank-statements.import'), [
        'bank_account_id' => $this->bankAccount->id,
        'statement_file' => $invalidFile,
        'statement_period_start' => '2025-09-01',
        'statement_period_end' => '2025-09-30',
        'opening_balance' => '0.00',
        'closing_balance' => '1000.00',
        'currency' => 'USD',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['statement_file']);
});

it('prevents duplicate statement imports', function () {
    // Create existing statement
    $existingStatement = BankStatement::factory()->create([
        'company_id' => $this->company->id,
        'ledger_account_id' => $this->bankAccount->id,
        'statement_uid' => 'test-statement-123',
        'statement_start_date' => '2025-09-01',
        'statement_end_date' => '2025-09-30',
    ]);

    $csvContent = "Transaction Date,Description,Amount,Balance\n2025-09-01,Test,1000.00,1000.00";
    $file = UploadedFile::fake()->createWithContent('statement.csv', $csvContent);

    $response = $this->postJson(route('bank-statements.import'), [
        'bank_account_id' => $this->bankAccount->id,
        'statement_file' => $file,
        'statement_period_start' => '2025-09-01',
        'statement_period_end' => '2025-09-30',
        'opening_balance' => '0.00',
        'closing_balance' => '1000.00',
        'currency' => 'USD',
    ]);

    $response->assertStatus(409);
    $response->assertJsonFragment([
        'message' => 'A statement for this period and account already exists',
    ]);
});

it('normalizes CSV statement lines correctly', function () {
    $csvContent = "Transaction Date,Description,Reference,Amount,Balance\n".
                  "2025-09-01,Customer Payment,INV-001,1000.00,1000.00\n".
                  "2025-09-05,Supplier Payment,SUP-002,-500.00,500.00\n".
                  '2025-09-10,Bank Fee,FEE-001,-10.00,490.00';

    $file = UploadedFile::fake()->createWithContent('statement.csv', $csvContent);

    $response = $this->postJson(route('bank-statements.import'), [
        'bank_account_id' => $this->bankAccount->id,
        'statement_file' => $file,
        'statement_period_start' => '2025-09-01',
        'statement_period_end' => '2025-09-30',
        'opening_balance' => '0.00',
        'closing_balance' => '490.00',
        'currency' => 'USD',
    ]);

    $response->assertStatus(201);

    // Process the normalization job
    Bus::assertDispatched(NormalizeBankStatement::class);

    // Manually process the job to test normalization
    $statement = BankStatement::first();
    $job = new NormalizeBankStatement($statement);
    $job->handle();

    expect(BankStatementLine::count())->toBe(3);

    $lines = BankStatementLine::orderBy('transaction_date')->get();
    expect($lines[0]->description)->toBe('Customer Payment');
    expect($lines[0]->reference_number)->toBe('INV-001');
    expect($lines[0]->amount)->toBe(1000.00);
    expect($lines[0]->balance_after)->toBe(1000.00);

    expect($lines[1]->description)->toBe('Supplier Payment');
    expect($lines[1]->reference_number)->toBe('SUP-002');
    expect($lines[1]->amount)->toBe(-500.00);
    expect($lines[1]->balance_after)->toBe(500.00);

    expect($lines[2]->description)->toBe('Bank Fee');
    expect($lines[2]->reference_number)->toBe('FEE-001');
    expect($lines[2]->amount)->toBe(-10.00);
    expect($lines[2]->balance_after)->toBe(490.00);
});

it('handles statement import with missing balance column', function () {
    $csvContent = "Transaction Date,Description,Amount\n".
                  "2025-09-01,Initial Deposit,1000.00\n".
                  '2025-09-05,Payment,-250.00';

    $file = UploadedFile::fake()->createWithContent('statement.csv', $csvContent);

    $response = $this->postJson(route('bank-statements.import'), [
        'bank_account_id' => $this->bankAccount->id,
        'statement_file' => $file,
        'statement_period_start' => '2025-09-01',
        'statement_period_end' => '2025-09-30',
        'opening_balance' => '0.00',
        'closing_balance' => '750.00',
        'currency' => 'USD',
    ]);

    $response->assertStatus(201);

    // Process the normalization job
    $statement = BankStatement::first();
    $job = new NormalizeBankStatement($statement);
    $job->handle();

    expect(BankStatementLine::count())->toBe(2);

    $lines = BankStatementLine::get();
    expect($lines[0]->balance_after)->toBeNull();
    expect($lines[1]->balance_after)->toBeNull();
});

it('prevents unauthorized access to bank statement import', function () {
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    $file = UploadedFile::fake()->create('statement.csv', 1000);

    $response = $this->postJson(route('bank-statements.import'), [
        'bank_account_id' => $this->bankAccount->id,
        'statement_file' => $file,
        'statement_period_start' => '2025-09-01',
        'statement_period_end' => '2025-09-30',
        'opening_balance' => '0.00',
        'closing_balance' => '1000.00',
        'currency' => 'USD',
    ]);

    $response->assertStatus(403);
});

// Helper function to generate sample OFX content
function generateSampleOfxContent(): string
{
    return <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
    <SIGNONMSGSRSV1>
        <SONRS>
            <STATUS>
                <CODE>0</CODE>
                <SEVERITY>INFO</SEVERITY>
            </STATUS>
            <DTSERVER>20250910120000</DTSERVER>
            <LANGUAGE>ENG</LANGUAGE>
        </SONRS>
    </SIGNONMSGSRSV1>
    <BANKMSGSRSV1>
        <STMTTRNRS>
            <TRNUID>1</TRNUID>
            <STATUS>
                <CODE>0</CODE>
                <SEVERITY>INFO</SEVERITY>
            </STATUS>
            <STMTRS>
                <CURDEF>USD</CURDEF>
                <BANKACCTFROM>
                    <BANKID>123456789</BANKID>
                    <ACCTID>987654321</ACCTID>
                    <ACCTTYPE>CHECKING</ACCTTYPE>
                </BANKACCTFROM>
                <BANKTRANLIST>
                    <DTSTART>20250901</DTSTART>
                    <DTEND>20250930</DTEND>
                    <STMTTRN>
                        <TRNTYPE>CREDIT</TRNTYPE>
                        <DTPOSTED>20250901</DTPOSTED>
                        <TRNAMT>1000.00</TRNAMT>
                        <FITID>001</FITID>
                        <MEMO>Initial Deposit</MEMO>
                    </STMTTRN>
                    <STMTTRN>
                        <TRNTYPE>DEBIT</TRNTYPE>
                        <DTPOSTED>20250905</DTPOSTED>
                        <TRNAMT>-500.00</TRNAMT>
                        <FITID>002</FITID>
                        <MEMO>Payment to Vendor</MEMO>
                    </STMTTRN>
                </BANKTRANLIST>
                <LEDGERBAL>
                    <BALAMT>1475.00</BALAMT>
                    <DTASOF>20250930</DTASOF>
                </LEDGERBAL>
            </STMTRS>
        </STMTTRNRS>
    </BANKMSGSRSV1>
</OFX>
OFX;
}
