<?php

namespace Tests\Feature\Ledger\PeriodClose;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Domain\AccountingPeriod\Models\AccountingPeriod;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Services\PeriodCloseService;
use Tests\TestCase;

class PeriodCloseReportTest extends TestCase
{
    private User $user;

    private Company $company;

    private AccountingPeriod $period;

    private PeriodClose $periodClose;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->period = AccountingPeriod::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'closed',
        ]);

        $this->periodClose = PeriodClose::factory()->create([
            'company_id' => $this->company->id,
            'accounting_period_id' => $this->period->id,
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $this->user->id,
        ]);

        // Give user required permissions
        $this->user->givePermissionTo('period-close.view');
        $this->user->givePermissionTo('period-close.reports');

        $this->actingAs($this->user);
        $this->withHeader('X-Company-Id', $this->company->id);
    }

    /** @test */
    public function it_can_generate_period_close_reports()
    {
        Storage::fake('local');

        $response = $this->postJson("/api/v1/ledger/periods/{$this->period->id}/close/reports", [
            'report_types' => ['income_statement', 'balance_sheet', 'cash_flow'],
        ]);

        $response->assertStatus(Response::HTTP_ACCEPTED)
            ->assertJson([
                'message' => 'Report generation started',
                'status' => 'processing',
            ]);

        $this->assertDatabaseHas('period_close_reports', [
            'period_close_id' => $this->periodClose->id,
            'report_types' => json_encode(['income_statement', 'balance_sheet', 'cash_flow']),
            'status' => 'processing',
        ]);
    }

    /** @test */
    public function it_validates_required_report_types()
    {
        $response = $this->postJson("/api/v1/ledger/periods/{$this->period->id}/close/reports", []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['report_types']);
    }

    /** @test */
    public function it_validates_valid_report_types()
    {
        $response = $this->postJson("/api/v1/ledger/periods/{$this->period->id}/close/reports", [
            'report_types' => ['invalid_type'],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['report_types.0']);
    }

    /** @test */
    public function it_requires_period_close_to_exist()
    {
        $nonExistentPeriod = AccountingPeriod::factory()->create();

        $response = $this->postJson("/api/v1/ledger/periods/{$nonExistentPeriod->id}/close/reports", [
            'report_types' => ['income_statement'],
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'message' => 'Period close not found',
            ]);
    }

    /** @test */
    public function it_requires_closed_period_for_final_reports()
    {
        $openPeriod = AccountingPeriod::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'open',
        ]);

        $openPeriodClose = PeriodClose::factory()->create([
            'company_id' => $this->company->id,
            'accounting_period_id' => $openPeriod->id,
            'status' => 'in_review',
        ]);

        $response = $this->postJson("/api/v1/ledger/periods/{$openPeriod->id}/close/reports", [
            'report_types' => ['final_statements'],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson([
                'message' => 'Final reports require closed period',
            ]);
    }

    /** @test */
    public function it_can_retrieve_existing_reports()
    {
        // Create a completed report
        $report = \DB::table('period_close_reports')->insert([
            'id' => \Str::uuid(),
            'period_close_id' => $this->periodClose->id,
            'report_types' => json_encode(['income_statement', 'balance_sheet']),
            'status' => 'completed',
            'generated_at' => now(),
            'file_paths' => json_encode([
                'income_statement' => 'reports/income_statement_2025_10.pdf',
                'balance_sheet' => 'reports/balance_sheet_2025_10.pdf',
            ]),
            'metadata' => json_encode([
                'generated_by' => $this->user->id,
                'generation_time' => 2.5,
                'page_count' => ['income_statement' => 3, 'balance_sheet' => 2],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/ledger/periods/{$this->period->id}/close/reports");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'status' => 'completed',
                'reports' => [
                    'income_statement' => [
                        'file_path' => 'reports/income_statement_2025_10.pdf',
                        'page_count' => 3,
                    ],
                    'balance_sheet' => [
                        'file_path' => 'reports/balance_sheet_2025_10.pdf',
                        'page_count' => 2,
                    ],
                ],
                'metadata' => [
                    'generated_by' => $this->user->id,
                    'generation_time' => 2.5,
                ],
            ]);
    }

    /** @test */
    public function it_can_download_report_files()
    {
        Storage::fake('local');
        $filePath = 'reports/test_report.pdf';
        $content = 'Test PDF content';

        Storage::disk('local')->put($filePath, $content);

        // Create report record
        \DB::table('period_close_reports')->insert([
            'id' => \Str::uuid(),
            'period_close_id' => $this->periodClose->id,
            'report_types' => json_encode(['income_statement']),
            'status' => 'completed',
            'generated_at' => now(),
            'file_paths' => json_encode(['income_statement' => $filePath]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/ledger/periods/{$this->period->id}/close/reports/download/income_statement");

        $response->assertStatus(Response::HTTP_OK)
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="income_statement_2025-10.pdf"');
    }

    /** @test */
    public function it_returns_404_for_nonexistent_report_file()
    {
        $response = $this->getJson("/api/v1/ledger/periods/{$this->period->id}/close/reports/download/nonexistent_report");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** @test */
    public function it_logs_report_generation_audit_trail()
    {
        Event::fake();

        $response = $this->postJson("/api/v1/ledger/periods/{$this->period->id}/close/reports", [
            'report_types' => ['income_statement'],
        ]);

        $response->assertStatus(Response::HTTP_ACCEPTED);

        // Verify audit event was dispatched
        Event::assertDispatched('ledger.period.close.reports.requested', function ($event) {
            return $event->periodClose->id === $this->periodClose->id
                && $event->user->id === $this->user->id
                && $event->reportTypes === ['income_statement'];
        });
    }

    /** @test */
    public function it_prevents_unauthorized_access()
    {
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);
        $this->withHeader('X-Company-Id', $this->company->id);

        $response = $this->postJson("/api/v1/ledger/periods/{$this->period->id}/close/reports", [
            'report_types' => ['income_statement'],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function it_handles_report_generation_failure()
    {
        // Mock the service to throw an exception
        $this->mock(PeriodCloseService::class, function ($mock) {
            $mock->shouldReceive('generateReports')
                ->once()
                ->andThrow(new \Exception('Report generation failed'));
        });

        $response = $this->postJson("/api/v1/ledger/periods/{$this->period->id}/close/reports", [
            'report_types' => ['income_statement'],
        ]);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJson([
                'message' => 'Failed to generate reports',
                'error' => 'Report generation failed',
            ]);
    }

    /** @test */
    public function it_can_generate_interim_reports_for_open_periods()
    {
        $openPeriod = AccountingPeriod::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'open',
        ]);

        $openPeriodClose = PeriodClose::factory()->create([
            'company_id' => $this->company->id,
            'accounting_period_id' => $openPeriod->id,
            'status' => 'in_review',
        ]);

        $response = $this->postJson("/api/v1/ledger/periods/{$openPeriod->id}/close/reports", [
            'report_types' => ['interim_trial_balance'],
        ]);

        $response->assertStatus(Response::HTTP_ACCEPTED);
    }

    /** @test */
    public function it_validates_company_scoping()
    {
        $otherCompany = Company::factory()->create();
        $otherPeriod = AccountingPeriod::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->postJson("/api/v1/ledger/periods/{$otherPeriod->id}/close/reports", [
            'report_types' => ['income_statement'],
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** @test */
    public function it_returns_report_generation_status()
    {
        // Create a processing report
        $reportId = \Str::uuid();
        \DB::table('period_close_reports')->insert([
            'id' => $reportId,
            'period_close_id' => $this->periodClose->id,
            'report_types' => json_encode(['income_statement']),
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/ledger/periods/{$this->period->id}/close/reports/status");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'status' => 'processing',
                'report_id' => $reportId,
            ]);
    }
}
